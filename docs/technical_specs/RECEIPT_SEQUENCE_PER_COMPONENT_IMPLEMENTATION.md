# Receipt Number Sequence Per Fee Type - Implementation Guide

## Overview
This document provides a complete implementation guide for generating independent receipt sequences for each fee type, where each fee type's receipt numbers start from 1 and increment independently.

**Note**: Payment labels (GHSS, SGM, MST, GCA) are for **display purposes only** and are not used in sequence tracking. Sequences are tracked solely by fee type.

## Current System Analysis

### Current Receipt Generation Logic
Currently, your system has **mixed** receipt number generation:
1. Some receipts use **simple sequential numbers** (01, 02, 03...)
2. Some receipts use **prefix-based numbers** (MST001, GCA001, HST001...)
3. Receipt numbers are tracked in `tbl_payments` and `tbl_receipts` tables

### Current Fee Components (Payment Labels)
Based on your fee configuration:
- **GM / GHSS** (Gyanmanjari Secondary & Higher Secondary School) → School Fee (school_id = 1)
- **SGM** (Shree Gyanmanjari) → School Fee (school_id = 2)
- **MST** (Mahatma Seva Trust) → Trust Facilities Fee, Hostel Fee, Transport Fee
- **GCA** (Gyanmanjari Career Academy) → Tuition Fee Part 1, Tuition Fee Part 2

**Important**: 
- School fees need **separate receipt sequences** for GM (school_id=1) and SGM (school_id=2)
- All other fees use **single sequences** regardless of school

## Requirement Summary
**Each Fee Type has its own independent sequence starting from 1:**

| Fee Type | School ID | Receipt Numbers | Description |
|----------|-----------|----------------|-------------|
| School Fee | 1 (GM) | 1, 2, 3, 4, 5... | GM school fees |
| School Fee | 2 (SGM) | 1, 2, 3, 4, 5... | SGM school fees |
| Trust Facilities Fee | - | 1, 2, 3, 4, 5... | All trust fees share this sequence |
| Hostel Fee | - | 1, 2, 3, 4, 5... | All hostel fees share this sequence |
| Transport Fee | - | 1, 2, 3, 4, 5... | All transport fees share this sequence |
| Tuition Fee Part 1 | - | 1, 2, 3, 4, 5... | All tuition part 1 fees share this sequence |
| Tuition Fee Part 2 | - | 1, 2, 3, 4, 5... | All tuition part 2 fees share this sequence |

**Note**: 
- **School fees** are tracked separately per school using `school_id` (GM=1, SGM=2)
- **All other fees** use single sequences regardless of school
- Payment labels (GHSS, SGM, MST, GCA) are for display purposes only

---

## Implementation Steps

## STEP 1: Create Receipt Sequence Tracking Table

### 1.1 Database Schema
Create a new table to track sequences for each payment label and fee type combination:

```sql
-- File: counselling-backend/migrations/create_receipt_sequences_table.sql

CREATE TABLE IF NOT EXISTS `tbl_receipt_sequences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fee_type` enum('school_fee','trust_facilities_fee','hostel_fee','transport_fee','tuition_fee_part1','tuition_fee_part2','token_fee','other') NOT NULL,
  `school_id` int DEFAULT NULL COMMENT 'School ID (only for school_fee, NULL for others)',
  `last_sequence` int NOT NULL DEFAULT 0 COMMENT 'Last used sequence number',
  `academic_year` varchar(20) DEFAULT NULL COMMENT 'Academic year (optional - for yearly reset)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fee_school_year` (`fee_type`, `school_id`, `academic_year`),
  KEY `idx_fee_type` (`fee_type`),
  KEY `idx_school_id` (`school_id`),
  KEY `fk_school_sequences` (`school_id`),
  CONSTRAINT `fk_school_sequences` FOREIGN KEY (`school_id`) REFERENCES `tbl_schools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci 
COMMENT='Tracks receipt sequence numbers for each fee type and school';

-- Initialize sequences for all fee types
INSERT INTO `tbl_receipt_sequences` 
  (`fee_type`, `school_id`, `last_sequence`, `academic_year`) 
VALUES
  -- School fees (separate for each school)
  ('school_fee', 1, 0, '2026-2027'),  -- GM School
  ('school_fee', 2, 0, '2026-2027'),  -- SGM School
  
  -- Other fees (single sequence, school_id is NULL)
  ('trust_facilities_fee', NULL, 0, '2026-2027'),
  ('hostel_fee', NULL, 0, '2026-2027'),
  ('transport_fee', NULL, 0, '2026-2027'),
  ('tuition_fee_part1', NULL, 0, '2026-2027'),
  ('tuition_fee_part2', NULL, 0, '2026-2027'),
  ('token_fee', NULL, 0, '2026-2027');

-- Note: Set last_sequence to current max if you have existing receipts
-- For school fees, update separately for each school:
-- UPDATE tbl_receipt_sequences SET last_sequence = 50 WHERE fee_type='school_fee' AND school_id=1;  -- GM
-- UPDATE tbl_receipt_sequences SET last_sequence = 30 WHERE fee_type='school_fee' AND school_id=2;  -- SGM
-- For other fees:
-- UPDATE tbl_receipt_sequences SET last_sequence = 100 WHERE fee_type='trust_facilities_fee';
```

**Note**: The existing `tbl_receipts` and `tbl_payments` tables should already have a way to track the student's school. The student's `school_id` can be fetched from `tbl_gm_std_registration` table when needed for school fee receipts.

**Schema Note**: For school_fee, the sequence is tracked with `school_id` (1 or 2). For all other fee types, `school_id` should be NULL in the sequences table.

---

## STEP 2: Create Helper Function for Receipt Number Generation

### 2.1 Receipt Sequence Helper Function

Create a new helper file: `common/helpers/receipt_sequence_helper.php`

```php
<?php
/**
 * Receipt Sequence Helper Functions
 * Generates unique receipt numbers for each payment label and fee type combination
 */

/**
 * Get next receipt number for a fee type
 * Uses row-level locking to prevent duplicate receipt numbers in concurrent transactions
 * 
 * @param PDO $conn Database connection
 * @param string $fee_type Fee type (school_fee, trust_facilities_fee, etc.)
 * @param int|null $school_id School ID (required for school_fee, NULL for others)
 * @param string|null $academic_year Academic year (optional, for yearly sequences)
 * @return array ['success' => bool, 'receipt_no' => string, 'error' => string]
 */
function getNextReceiptNumber($conn, $fee_type, $school_id = null, $academic_year = null)
{
    try {
        // Start transaction
        $conn->beginTransaction();
        
        // Lock the row to prevent concurrent updates
        $stmt = $conn->prepare("
            SELECT id, last_sequence 
            FROM tbl_receipt_sequences 
            WHERE fee_type = ? 
              AND (school_id = ? OR (school_id IS NULL AND ? IS NULL))
              AND (academic_year = ? OR academic_year IS NULL)
            FOR UPDATE
        ");
        
        $stmt->execute([$fee_type, $school_id, $school_id, $academic_year]);
        $sequence_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$sequence_record) {
            // No sequence record found - create one
            $stmt = $conn->prepare("
                INSERT INTO tbl_receipt_sequences 
                (fee_type, school_id, last_sequence, academic_year) 
                VALUES (?, ?, 0, ?)
            ");
            $stmt->execute([$fee_type, $school_id, $academic_year]);
            
            $sequence_id = $conn->lastInsertId();
            $last_sequence = 0;
        } else {
            $sequence_id = $sequence_record['id'];
            $last_sequence = (int)$sequence_record['last_sequence'];
        }
        
        // Increment sequence
        $new_sequence = $last_sequence + 1;
        
        // Update sequence
        $stmt = $conn->prepare("
            UPDATE tbl_receipt_sequences 
            SET last_sequence = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_sequence, $sequence_id]);
        
        // Generate receipt number (simple sequential number)
        $receipt_no = (string)$new_sequence;
        
        // Commit transaction
        $conn->commit();
        
        return [
            'success' => true,
            'receipt_no' => $receipt_no,
            'sequence' => $new_sequence
        ];
        
    } catch (PDOException $e) {
        // Rollback on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Error generating receipt number: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'Failed to generate receipt number: ' . $e->getMessage()
        ];
    }
}

// Note: Receipt numbers are simple sequential integers (1, 2, 3...)
// School fees have separate counters per school_id
// Other fee types have single counters (school_id is NULL)

/**
 * Get payment label for display purposes (optional helper)
 * Payment labels are for display only and not used in sequence tracking
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID
 * @param string $fee_type Fee type
 * @return string Payment label for display (GHSS, SGM, MST, GCA)
 */
function getPaymentLabelForDisplay($conn, $student_id, $fee_type)
{
    try {
        // Get fee config for student
        $stmt = $conn->prepare("
            SELECT fc.school_fee_label, fc.trust_fee_label, fc.token_fee_label, fc.tuition_fee_label
            FROM tbl_gm_std_registration s
            INNER JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                AND s.medium_id = fc.medium_id 
                AND s.group_id = fc.group_id
            WHERE s.id = ? AND fc.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return 'N/A';
        }
        
        // Map fee type to payment label (for display only)
        switch ($fee_type) {
            case 'school_fee':
                return $config['school_fee_label'] ?? 'School';
            case 'trust_facilities_fee':
                return $config['trust_fee_label'] ?? 'Trust';
            case 'tuition_fee_part1':
            case 'token_fee':
                return $config['token_fee_label'] ?? 'Tuition';
            case 'tuition_fee_part2':
                return $config['tuition_fee_label'] ?? 'Tuition';
            case 'hostel_fee':
                return 'Hostel';
            case 'transport_fee':
                return 'Transport';
            default:
                return 'N/A';
        }
        
    } catch (PDOException $e) {
        error_log("Error getting payment label: " . $e->getMessage());
        return 'N/A';
    }
}

/**
 * Reset receipt sequences for a new academic year (optional utility function)
 * 
 * @param PDO $conn Database connection
 * @param string $academic_year New academic year
 * @return bool Success status
 */
function resetSequencesForNewYear($conn, $academic_year)
{
    try {
        // Create new sequence records for the new academic year with last_sequence = 0
        $stmt = $conn->prepare("
            INSERT INTO tbl_receipt_sequences 
                (fee_type, school_id, last_sequence, academic_year)
            SELECT fee_type, school_id, 0, ?
            FROM tbl_receipt_sequences
            WHERE academic_year = (
                SELECT MAX(academic_year) FROM tbl_receipt_sequences
            )
            ON DUPLICATE KEY UPDATE last_sequence = 0
        ");
        
        $stmt->execute([$academic_year]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error resetting sequences: " . $e->getMessage());
        return false;
    }
}
```

---

## STEP 3: Update Receipt Generation Code

### 3.1 Update `receipt-save.php`

**Location**: `counselling-backend/controllers/payments/receipt-save.php`

Replace the existing receipt number generation logic (around line 58-72) with:

```php
// Include receipt sequence helper
require_once __DIR__ . '/../../common/helpers/receipt_sequence_helper.php';

// Get student's school_id (needed for school_fee only)
$stmt = $conn->prepare("SELECT school_id FROM tbl_gm_std_registration WHERE id = ?");
$stmt->execute([$student_id]);
$student_data = $stmt->fetch(PDO::FETCH_ASSOC);
$school_id = $student_data['school_id'] ?? null;

// Get current academic year (you may have a function for this)
$academic_year = getCurrentAcademicYear($conn); // Implement this function if needed, or pass NULL

// Generate receipt number based on fee type
// For school_fee, pass school_id; for others, pass NULL
$school_id_param = ($payment_for === 'school_fee') ? $school_id : null;
$receipt_result = getNextReceiptNumber($conn, $payment_for, $school_id_param, $academic_year);

if (!$receipt_result['success']) {
    logDatabaseError(null, "Receipt Number Generation: " . $receipt_result['error']);
    set_flash_message('error', "Failed to generate receipt number. Please try again.");
    header('Location: ' . BASE_URL . '/modules/payments/generate-receipt.php');
    exit;
}

$receipt_no = $receipt_result['receipt_no'];

// Optionally get payment label for display purposes
$payment_label = getPaymentLabelForDisplay($conn, $student_id, $payment_for);

// Use existing INSERT statement - receipt_no is now just a simple number
```

### 3.2 Update `easebuzz-callback.php`

**Location**: `counselling-backend/modules/payments/easebuzz-callback.php`

Replace receipt generation logic in token fee section (around line 103-111):

```php
// Include receipt sequence helper
require_once __DIR__ . '/../../common/helpers/receipt_sequence_helper.php';

// Get payment label for student
$payment_label = getPaymentLabelForStudent($conn, $student_id, 'tuition_fee_part1');

// Generate receipt number
$receipt_result = getNextReceiptNumber($conn, $payment_label, 'tuition_fee_part1', null);

// IMPORTANT: Store payment_label and fee_type when inserting the payment record

if (!$receipt_result['success']) {
    throw new Exception("Failed to generate receipt number: " . $receipt_result['error']);
}

$receipt_no = $receipt_result['receipt_no'];
```

Replace receipt generation logic in other fee payments section (around line 180-189):

```php
// Include receipt sequence helper if not already included
require_once __DIR__ . '/../../common/helpers/receipt_sequence_helper.php';

// Get payment label for student
$payment_label = getPaymentLabelForStudent($conn, $student_id, $fee_component);

if (!$payment_label) {
    // Fallback based on fee component
    $default_labels = [
        'school_fee' => 'GHSS',
        'trust_facilities_fee' => 'MST',
        'tuition_fee_part2' => 'GCA',
        'hostel_fee' => 'MST',
        'transport_fee' => 'MST'
    ];
    $payment_label = $default_labels[$fee_component] ?? 'GEN';
}


// IMPORTANT: Store payment_label and fee_type when inserting the payment record
// INSERT INTO tbl_payments (..., receipt_no, payment_label, ...) 
// VALUES (..., ?, ?, ...)
// Generate receipt number with sequence
$receipt_result = getNextReceiptNumber($conn, $payment_label, $fee_component, null);

if (!$receipt_result['success']) {
    throw new Exception("Failed to generate receipt number: " . $receipt_result['error']);
}

$receipt_no = $receipt_result['receipt_no'];
```

### 3.3 Update Other Payment Processing Files

Apply similar changes to:
- `easebuzz-pending-callback.php`
- `payment-save.php`
- Any other files that generate receipt numbers

---

## STEP 4: Migrate Existing Data (Optional)

If you want to preserve existing receipt numbers and continue from current counts:

```sql
-- File: counselling-backend/migrations/migrate_existing_receipt_numbers.sql

-- Update sequence counters based on existing receipts
-- Count all existing receipts of each fee type

-- For School Fee GM (school_id = 1)
UPDATE tbl_receipt_sequences 
SET last_sequence = (
    SELECT COUNT(*) 
    FROM tbl_receipts r
    INNER JOIN tbl_gm_std_registration s ON r.student_id = s.id
    WHERE r.payment_for = 'school_fee' AND s.school_id = 1
)
WHERE fee_type = 'school_fee' AND school_id = 1;

-- For School Fee SGM (school_id = 2)
UPDATE tbl_receipt_sequences 
SET last_sequence = (
    SELECT COUNT(*) 
    FROM tbl_receipts r
    INNER JOIN tbl_gm_std_registration s ON r.student_id = s.id
    WHERE r.payment_for = 'school_fee' AND s.school_id = 2
)
WHERE fee_type = 'school_fee' AND school_id = 2;

-- For Trust Facilities Fee
UPDATE tbl_receipt_sequences 
SET last_sequence = (
    SELECT COUNT(*) 
    FROM tbl_receipts r
    WHERE r.payment_for = 'trust_facilities_fee'
)
WHERE fee_type = 'trust_facilities_fee';

-- For Hostel Fee
UPDATE tbl_receipt_sequences 
SET last_sequence = (
    SELECT COUNT(*) 
    FROM tbl_receipts r
    WHERE r.payment_for = 'hostel_fee'
)
WHERE fee_type = 'hostel_fee';

-- For Transport Fee
UPDATE tbl_receipt_sequences 
SET last_sequence = (
    SELECT COUNT(*) 
    FROM tbl_receipts r
    WHERE r.payment_for = 'transport_fee'
)
WHERE fee_type = 'transport_fee';

-- For Tuition Fee Part 1
UPDATE tbl_receipt_sequences 
SET last_sequence = (
    SELECT COUNT(*) 
    FROM tbl_receipts r
    WHERE r.payment_for = 'tuition_fee_part1'
)
WHERE fee_type = 'tuition_fee_part1';

-- For Tuition Fee Part 2
UPDATE tbl_receipt_sequences 
SET last_sequence = (
    SELECT COUNT(*) 
    FROM tbl_receipts r
    WHERE r.payment_for = 'tuition_fee_part2'
)
WHERE fee_type = 'tuition_fee_part2';
```

---

## STEP 5: Update Receipt Display Components

Update receipt printing/viewing components to display new format:
independent sequences (1, 2, 3...)
   - Test concurrent requests (simulate multiple users paying simultaneously)
   - Verify sequence increments correctly for each payment label + fee type
   - Verify GHSS School Fee #5 and MST Hostel Fee #5 can coexist
Ensure the receipt number displays correctly in all receipt templates.

---

## STEP 6: Testing Plan

### 6.1 Unit Tests

1. **First GCA student pays token fee → Verify receipt_no = 1, payment_label = GCA, fee_type = tuition_fee_part1
   - Second GCA student pays token fee → Verify receipt_no = 2, payment_label = GCA
   
2. **School Fee Payment Flow**
   - First GHSS student pays school fee → Verify receipt_no = 1, payment_label = GHSS, fee_type = school_fee
   - First SGM student pays school fee → Verify receipt_no = 1, payment_label = SGM, fee_type = school_fee
   - Second GHSS student pays school fee → Verify receipt_no = 2, payment_label = GHSS

3. **Trust Facilities Fee Payment Flow**
   - Student pays trust fee → Verify receipt_no = 1, payment_label = MST, fee_type = trust_facilities_fee
   - Same student pays hostel fee → Verify receipt_no = 1, payment_label = MST, fee_type = hostel_fee
   - Another student pays trust fee → Verify receipt_no = 2, payment_label = MST, fee_type = trust_facilities_fee

### 6.2 Integration Tests

1. **Token Fee Payment Flow**
   - Student pays token fee → Verify GCA-TF1-0001 format
   
2. **School Fee Payment Flow**
   - GHSS student pays school fee → Verify GHSS-SF-0001 format
   - SGM student pays school fee → Verify SGM-SF-0001 format

3. **Trust Facilities Fee Payment Flow**
   - Student pays trust fee → Verify MST-TF-0001 format
   - Same student pays hostel fee → Verify MST-HF-0001 format

### 6.3 Load Testing

- Simulate 50+ concurrent payment transactions
- Verify no duplicate receipt numbers
- Verify transaction isolation works correctly

---

## STEP 7: Admin Interface (Optional Enhancement)

Create an admin page to view/manage receipt sequences:

**File**: `portal/modules/admin/receipt-sequences.php`
 forever: 1, 2, 3, 4, 5, 6... (never reset)

**Option B**: Reset sequences each academic year
- 2026-2027: 1, 2, 3, 4, 5...
- 2027-2028: 1, 2, 3, 4, 5... (reset to 1
// Show sequence history
?>
```

---

## Additional Considerations

### 1. Academic Year Handling

**Option A**: Continue sequences across years (simpler)
- Same sequence continues: GHSS-SF-0001, GHSS-SF-0002... forever

**Option B**: Reset sequences each academic year
- 2026-2027: GHSS-SF-0001, GHSS-SF-0002...
- 2027-2028: GHSS-SF-0001, GHSS-SF-0002... (reset)

### 2. Backup and Recovery

```sql
-- Backup current receipt data
CREATE TABLE tbl_receipts_backup_20260120 AS SELECT * FROM tbl_receipts;
CREATE TABLE tbl_payments_backup_20260120 AS SELECT * FROM tbl_payments;
```

### 3. Error Handling

- Log all receipt generation failures
- Implement retry mechanism for transaction deadlocks
- Alert admin if sequence generation fails

### 4. Performance Optimization

- Add database indexes on frequently queried columns
- Consider caching payment labels for students
- Monitor database lock wait times

---

## Rollout Plan

### Phase 1: Development & Testing (Week 1-2)
1. Create database tables
2. Implement helper functions
3. Update payment processing files
4. Test on development server

### Phase 2: Staging Deployment (Week 3)
1. Deploy to staging environment
2. Migrate existing data
3. Perform load testing
4. User acceptance testing

### Phase 3: Production Deployment (Week 4)
1. Schedule maintenance window
2. Backup production database
3. Deploy changes
4. Monitor for 48 hours

### Phase 4: Post-Deployment (Week 5)
1. Train accountants on new receipt format
2. Update documentation
3. Monitor system logs
4. Address any issues

---

## Troubleshooting Guide

### Issue: Duplicate Receipt Numbers

**Cause**: Transaction isolation failure

**Solution**: 
```sql
-- Check for duplicates
SELECT receipt_no, COUNT(*) as count 
FROM tbl_receipts 
GROUP BY receipt_no 
HAVING count > 1;

-- Fix isolation level
SET TRANSACTION ISOLATION LEVEL READ COMMITTED;
```

### Issue: Sequence Gaps

**Cause**: Failed transactions

**Solution**: This is normal and acceptable. Gaps ensure uniqueness.

### Issue: Wrong Payment Label

**Cause**: Student fee configuration not found

**Solution**: 
- Verify student has valid fee config
- Check payment label mappings in fee config table
- Implement fallback logic

---

## Summary

This implementation will give you:
✅ Independent receipt sequences for each fee type  
✅ Thread-safe, concurrent-request handling  
✅ Simple sequential numbering (1, 2, 3...)  
✅ Audit trail and sequence tracking  
✅ Scalable for future fee types  
✅ Payment labels displayed for context, not stored in sequences
✅ No complex prefix/suffix formatting needed  

**Estimated Development Time**: 2-3 weeks  
**Estimated Testing Time**: 1-2 weeks  
**Risk Level**: Medium (requires careful transaction handling)

---

## Questions to Consider

1. **Do you want sequences to reset each academic year?**
2. **Should existing receipts be renumbered or keep current format?**
3. **Do you need year/month in receipt number (e.g., GHSS-SF-2026-0001)?**
4. **Should transport fee use a separate label or continue with MST?**

---

## Contact & Support

For implementation support:
- Review code with development team
- Test on staging environment first
- Keep backups of all database changes
- Monitor logs during rollout

---

**Document Version**: 1.0  
**Created**: January 20, 2026  
**Status**: Ready for Implementation
