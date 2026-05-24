# Receipt Sequence Migration Steps

Follow these steps to implement the new receipt sequence system.

## Step 1: Backup Database
```bash
# Backup your database before making any changes
mysqldump -u root -p counselling > backup_before_receipt_sequence_$(date +%Y%m%d).sql
```

## Step 2: Run Database Migration
```bash
# Connect to MySQL
mysql -u root -p counselling

# Run the migration
source C:/xampp/htdocs/counselling-backend/migrations/create_receipt_sequences_table.sql

# Verify table was created
SHOW TABLES LIKE 'tbl_receipt_sequences';
DESC tbl_receipt_sequences;
```

## Step 3: Migrate Existing Receipt Counts (Optional)
If you have existing receipts and want to continue from current counts:
```bash
mysql -u root -p counselling

source C:/xampp/htdocs/counselling-backend/migrations/migrate_existing_receipt_counts.sql
```

## Step 4: Verify Helper Functions
The helper file has been created at:
`C:/xampp/htdocs/common/helpers/receipt_sequence_helper.php`

Make sure it's accessible from your payment processing files.

## Step 5: Update Payment Processing Files

### 5.1 Update receipt-save.php
Location: `counselling-backend/controllers/payments/receipt-save.php`

Add at the top (after other requires):
```php
require_once __DIR__ . '/../../common/helpers/receipt_sequence_helper.php';
```

Replace receipt number generation (around line 58-72) with:
```php
// Get student's school_id (needed for school_fee only)
$school_id = getStudentSchoolId($conn, $student_id);

// Get current academic year
$academic_year = getCurrentAcademicYear($conn);

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
```

### 5.2 Update easebuzz-callback.php
Location: `counselling-backend/modules/payments/easebuzz-callback.php`

Add at the top:
```php
require_once __DIR__ . '/../../common/helpers/receipt_sequence_helper.php';
```

**For Token Fee (around line 103-111):**
```php
// Generate receipt number for tuition fee part 1 (school_id not needed)
$receipt_result = getNextReceiptNumber($conn, 'tuition_fee_part1', null, null);

if (!$receipt_result['success']) {
    throw new Exception("Failed to generate receipt number: " . $receipt_result['error']);
}

$receipt_no = $receipt_result['receipt_no'];

// Optionally get payment label for display
$payment_label = getPaymentLabelForDisplay($conn, $student_id, 'tuition_fee_part1');
```

**For Other Fees (around line 180-189):**
```php
// Get student's school_id if paying school fee
$school_id_param = null;
if ($fee_component === 'school_fee') {
    $school_id_param = getStudentSchoolId($conn, $student_id);
}

// Generate receipt number based on fee component
$receipt_result = getNextReceiptNumber($conn, $fee_component, $school_id_param, null);

if (!$receipt_result['success']) {
    throw new Exception("Failed to generate receipt number: " . $receipt_result['error']);
}

$receipt_no = $receipt_result['receipt_no'];

// Optionally get payment label for display
$payment_label = getPaymentLabelForDisplay($conn, $student_id, $fee_component);
```

### 5.3 Update easebuzz-pending-callback.php
Similar changes as easebuzz-callback.php

### 5.4 Update payment-save.php
Location: `counselling-backend/controllers/payments/payment-save.php`

Apply similar changes as receipt-save.php

## Step 6: Test the Implementation

### Test 1: New Receipt Generation
1. Make a GM school fee payment → Should get receipt #1 (or next number)
2. Make a SGM school fee payment → Should get receipt #1 (or next number)
3. Make a hostel fee payment → Should get receipt #1 (or next number)

### Test 2: Concurrent Payments
Simulate multiple users paying at the same time to ensure no duplicate numbers.

### Test 3: Verify Sequences
```sql
SELECT * FROM tbl_receipt_sequences ORDER BY fee_type, school_id;
```

### Test 4: Check Recent Receipts
```sql
SELECT 
    r.receipt_no,
    r.payment_for,
    s.school_id,
    r.student_name,
    r.created_at
FROM tbl_receipts r
LEFT JOIN tbl_gm_std_registration s ON r.student_id = s.id
ORDER BY r.created_at DESC
LIMIT 20;
```

## Step 7: Monitor for Issues

Watch logs for any errors:
- `C:/xampp/php/logs/php_error_log`
- `counselling-backend/logs/`

Common issues:
1. Duplicate receipt numbers → Check if helper is included correctly
2. NULL school_id for school fees → Verify getStudentSchoolId() is working
3. Sequence gaps → Normal behavior, ensures uniqueness

## Step 8: Update Receipt Display

Make sure printed/displayed receipts show:
- Receipt number (e.g., "5")
- Payment label (e.g., "GHSS School Fee" or "MST Hostel Fee")

Example: **"Receipt #5 - GHSS School Fee"**

## Rollback Plan (If Needed)

If you encounter issues:
```sql
-- Restore from backup
mysql -u root -p counselling < backup_before_receipt_sequence_YYYYMMDD.sql

-- Or just drop the new table
DROP TABLE IF EXISTS tbl_receipt_sequences;
```

## Support

For issues, check:
1. Database connection errors
2. Transaction isolation level
3. Foreign key constraints
4. File permissions

---

**Status**: Ready to implement
**Date**: January 20, 2026
