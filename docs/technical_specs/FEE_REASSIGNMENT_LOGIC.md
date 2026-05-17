# Fee Reassignment Logic - Implementation Plan

## Document Version
- **Created**: January 18, 2026
- **Status**: PENDING APPROVAL
- **Purpose**: Define logic for handling fee reassignments when students have existing fee allocations

---

## Current Behavior Analysis

### Current System State
**File**: `assign-fees_controller.php`

**Current Logic**:
```php
// Line 63: Simple check - if fee already assigned for THIS config, skip
$sql_student = "SELECT id, surname, student_name, mob, 
    (SELECT COUNT(*) FROM tbl_student_fee_allocation WHERE student_id = ? AND fee_config_id = ?) as already_assigned
    FROM tbl_gm_std_registration WHERE id = ? AND status = 1";

if ($student['already_assigned'] > 0) {
    // Returns warning: 'Fees already assigned for this configuration'
    return false;
}
```

**Problem**: 
- Only checks for exact same fee_config_id
- Does NOT check if student has different fee_config_id assigned
- Does NOT handle fee structure changes (old config vs new config)
- Does NOT check existing payments before reassignment

---

## Database Schema Reference

### Key Tables

#### 1. `tbl_student_fee_allocation`
```sql
- id (PK)
- student_id (FK to tbl_gm_std_registration.id)
- fee_config_id (FK to tbl_fee_config.id)
- allocated_amount (DECIMAL 10,2) -- Total fees assigned
- paid_amount (DECIMAL 10,2) -- Amount paid so far (default 0.00)
- pending_amount (DECIMAL 10,2) -- Remaining balance
- status (ENUM: pending, partial, paid, overdue, waived)
- academic_year (VARCHAR 20)
- allocated_by (FK to tbl_users.id)
- allocated_at (TIMESTAMP)
- last_payment_date (DATE)
- updated_at (TIMESTAMP)
```

#### 2. `tbl_fee_installments`
```sql
- id (PK)
- allocation_id (FK to tbl_student_fee_allocation.id)
- student_id (FK to tbl_gm_std_registration.id)
- fee_config_id (FK to tbl_fee_config.id)
- installment_number (INT) -- 1, 2, 3, etc.
- due_amount (DECIMAL 10,2)
- paid_amount (DECIMAL 10,2) -- default 0.00
- payment_status (ENUM: pending, partial, paid)
- payment_date (DATETIME)
- payment_method (VARCHAR 50)
```

#### 3. `tbl_payments`
```sql
- id (PK)
- student_id (FK)
- allocation_id (FK to tbl_student_fee_allocation.id)
- amount (DECIMAL 10,2)
- payment_mode (ENUM: cash, online, cheque, etc.)
- receipt_no (VARCHAR 50)
- status (ENUM: pending, paid, cancelled, refunded)
- created_at (TIMESTAMP)
```

#### 4. `tbl_fee_config`
```sql
- id (PK)
- academic_year (VARCHAR 20)
- course_name (VARCHAR 100)
- school_id (INT FK)
- medium_id (INT FK)
- group_id (INT FK)
- total_fees (DECIMAL 10,2)
- token_fee (DECIMAL 10,2)
- school_fee (DECIMAL 10,2)
- trust_facilities_fee (DECIMAL 10,2)
- tuition_fee_part1 (DECIMAL 10,2)
- tuition_fee_part2 (DECIMAL 10,2)
- number_of_installments (INT)
- is_active (BOOLEAN)
```

---

## Proposed New Behavior

### Requirement Analysis

**User Requirements**:
1. ✅ **Hide students with current config assigned** - Don't show students who already have THIS exact fee_config_id assigned
2. ✅ **Show students with OLD config** - Display students who have a DIFFERENT fee_config_id assigned with difference calculation
3. ✅ **Check paid amounts** - Account for any payments already made when calculating differences
4. ✅ **Handle partial payments** - Support scenarios where student has paid some installments

---

## Calculation Logic

### Scenario 1: Student Has NO Fee Assignment
**Current Behavior**: ✅ Show in list, allow assignment
**New Behavior**: ✅ Keep same - Show in list with enrollment_id format

**SQL Query**:
```sql
SELECT 
    e.enrollment_id,
    r.id,
    r.surname,
    r.student_name,
    r.fathers_name,
    NULL as old_config_id,
    NULL as old_total_fees,
    NULL as old_paid_amount,
    NULL as old_pending_amount,
    NULL as fee_difference,
    'new' as assignment_type
FROM tbl_enrolled_students e
INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
LEFT JOIN tbl_student_fee_allocation sfa ON sfa.student_id = r.id
WHERE r.status = 1 
AND sfa.id IS NULL  -- No allocation exists
ORDER BY r.surname, r.student_name
```

---

### Scenario 2: Student Has SAME Fee Config Assigned
**Current Behavior**: ⚠️ Show in list but warn on submission
**New Behavior**: ❌ HIDE from list completely

**Logic**:
```sql
-- Exclude these students from the list
WHERE NOT EXISTS (
    SELECT 1 FROM tbl_student_fee_allocation 
    WHERE student_id = r.id 
    AND fee_config_id = :selected_fee_config_id
)
```

**Display**: Student should NOT appear in dropdown at all

---

### Scenario 3: Student Has DIFFERENT Fee Config (No Payment Yet)
**Current Behavior**: ⚠️ Show but doesn't indicate existing assignment
**New Behavior**: ✅ Show with OLD config details and difference

**Example**:
```
Old Config: ₹100,800 (2025-2026, 11th Commerce)
Paid: ₹0
New Config: ₹112,600 (2026-2027, 11th Commerce)
Difference: +₹11,800 (Additional payment required)
```

**Calculation**:
```javascript
old_allocated_amount = 100800  // From old tbl_fee_config via old fee_config_id
old_paid_amount = 0            // From tbl_student_fee_allocation.paid_amount
old_pending_amount = 100800    // allocated_amount - paid_amount

new_allocated_amount = 112600  // From NEW fee_config being assigned
new_payable = 100800           // new total - token (112600 - 11800)

fee_difference = new_payable - old_pending_amount
               = 100800 - 100800
               = 0

// But if new config total is actually higher:
fee_difference = 100800 - 88900  // If new payable is higher
               = +11,900 (Student needs to pay ADDITIONAL)

// If new config is lower:
fee_difference = 88900 - 100800
               = -11,900 (Credit/Refund scenario)
```

**Action on Assignment**:
- Archive old allocation (soft delete or status change)
- Create new allocation with new fee_config_id
- Transfer paid_amount from old to new
- Recalculate installments for remaining balance

---

### Scenario 4: Student Has DIFFERENT Fee Config (Partial Payment)
**Current Behavior**: ⚠️ Show but no payment check
**New Behavior**: ✅ Show with payment history and adjusted difference

**Example**:
```
Old Config: ₹100,800 (2025-2026)
Paid: ₹30,000 (via 3 installments)
Pending: ₹70,800
New Config: ₹112,600 (2026-2027)
New Payable: ₹100,800 (total - token)
```

**Calculation Steps**:
```javascript
// Step 1: Get old allocation details
old_allocated = 100800  // From old config
old_paid = 30000        // Sum from tbl_payments WHERE allocation_id = old_allocation_id
old_pending = 70800     // allocated - paid

// Step 2: Get new config details
new_total = 112600      // From new fee_config
new_token = 11800       // From new fee_config
new_payable = 100800    // new_total - new_token

// Step 3: Calculate adjustment
payment_credit = old_paid  // 30000 already paid
remaining_due = new_payable - payment_credit
              = 100800 - 30000
              = 70,800

fee_difference = remaining_due - old_pending
               = 70800 - 70800
               = 0 (No additional payment if new config matches)

// If new config is higher:
new_payable = 110000
remaining_due = 110000 - 30000 = 80000
fee_difference = 80000 - 70800 = +9200 (Additional ₹9,200 required)

// If new config is lower:
new_payable = 90000
remaining_due = 90000 - 30000 = 60000
fee_difference = 60000 - 70800 = -10800 (Credit of ₹10,800)
```

**Display Format**:
```
Surname, Student Name, Father Name (ENR001) 
[⚠️ Has Old Config]
├─ Old: ₹1,00,800 (2025-2026, 11th Commerce)
├─ Paid: ₹30,000
├─ Pending: ₹70,800
├─ New: ₹1,12,600 (2026-2027)
└─ Difference: +₹9,200 additional
```

---

### Scenario 5: Student Has DIFFERENT Fee Config (Fully Paid)
**Current Behavior**: ⚠️ Would allow reassignment incorrectly
**New Behavior**: ❌ HIDE from list OR show warning

**Logic**:
```sql
WHERE NOT (
    sfa.status = 'paid' 
    AND sfa.fee_config_id != :selected_fee_config_id
)
```

**Reasoning**: 
- If student has fully paid old config, reassigning new config would require:
  1. Refund of overpayment (if new < old)
  2. Additional collection (if new > old)
  3. Manual intervention from accountant

**Recommendation**: HIDE these students OR show with special badge "⚠️ Manual Review Required"

---

## Implementation Plan

### Phase 1: Enhanced Student List Query

**File**: `counselling-backend/controllers/fees/assign-fees_controller.php`

**New SQL Query**:
```sql
SELECT 
    e.enrollment_id,
    r.id as student_id,
    r.surname,
    r.student_name,
    r.fathers_name,
    
    -- Old allocation details
    sfa.id as old_allocation_id,
    sfa.fee_config_id as old_config_id,
    sfa.allocated_amount as old_allocated,
    sfa.paid_amount as old_paid,
    sfa.pending_amount as old_pending,
    sfa.status as old_status,
    
    -- Old fee config details
    old_fc.academic_year as old_academic_year,
    old_fc.course_name as old_course_name,
    old_fc.total_fees as old_total_fees,
    old_fc.token_fee as old_token_fee,
    
    -- Calculate assignment type
    CASE 
        WHEN sfa.id IS NULL THEN 'new'
        WHEN sfa.status = 'paid' THEN 'fully_paid'
        WHEN sfa.paid_amount > 0 THEN 'partial_payment'
        ELSE 'no_payment'
    END as assignment_type,
    
    -- Calculate if same config
    CASE 
        WHEN sfa.fee_config_id = :selected_fee_config_id THEN 1 
        ELSE 0 
    END as is_same_config

FROM tbl_enrolled_students e
INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
LEFT JOIN tbl_student_fee_allocation sfa ON sfa.student_id = r.id
LEFT JOIN tbl_fee_config old_fc ON old_fc.id = sfa.fee_config_id

WHERE r.status = 1
-- Exclude students with SAME config already assigned
AND (sfa.fee_config_id IS NULL OR sfa.fee_config_id != :selected_fee_config_id)
-- Optionally exclude fully paid (OR mark them differently)
-- AND (sfa.status IS NULL OR sfa.status != 'paid')

ORDER BY r.surname, r.student_name
```

---

### Phase 2: Frontend Display Enhancement

**File**: `portal/modules/fees/assign-fees.php`

**Enhanced Dropdown**:
```html
<select name="student_id" class="form-control">
    <?php foreach ($students as $student): ?>
        <?php
        $display_name = htmlspecialchars(
            $student['surname'] . ', ' . 
            $student['student_name'] . ', ' . 
            $student['fathers_name']
        );
        $enrollment = htmlspecialchars($student['enrollment_id']);
        
        // Add badges based on assignment type
        $badge = '';
        $extra_info = '';
        
        if ($student['assignment_type'] === 'new') {
            $badge = '<span class="badge bg-success">New</span>';
        } 
        elseif ($student['assignment_type'] === 'partial_payment') {
            $paid = number_format($student['old_paid'], 2);
            $pending = number_format($student['old_pending'], 2);
            $badge = '<span class="badge bg-warning">Has Payments</span>';
            $extra_info = " - Paid: ₹{$paid}, Pending: ₹{$pending}";
        }
        elseif ($student['assignment_type'] === 'no_payment') {
            $badge = '<span class="badge bg-info">Reassign</span>';
            $old_config = htmlspecialchars($student['old_academic_year']);
            $extra_info = " - Old: {$old_config}";
        }
        elseif ($student['assignment_type'] === 'fully_paid') {
            $badge = '<span class="badge bg-danger">Fully Paid</span>';
            $extra_info = " - NEEDS MANUAL REVIEW";
        }
        ?>
        
        <option 
            value="<?php echo $student['student_id']; ?>"
            data-assignment-type="<?php echo $student['assignment_type']; ?>"
            data-old-paid="<?php echo $student['old_paid'] ?? 0; ?>"
            data-old-pending="<?php echo $student['old_pending'] ?? 0; ?>"
        >
            <?php echo $display_name; ?> (<?php echo $enrollment; ?>) 
            <?php echo $badge . $extra_info; ?>
        </option>
    <?php endforeach; ?>
</select>
```

---

### Phase 3: Assignment Logic Enhancement

**File**: `counselling-backend/controllers/fees/assign-fees_controller.php`

**Updated `assignFeeToStudent()` function**:

```php
function assignFeeToStudent($student_id, $fee_config_id, $dbOps, $conn) {
    global $results;
    
    try {
        $conn->beginTransaction();
        
        // 1. Get new fee configuration
        $new_fee_config = getActiveFeeConfig($fee_config_id, $dbOps);
        if (!$new_fee_config) {
            throw new Exception('Fee configuration not found or inactive');
        }
        
        // 2. Check for existing allocation
        $existing = getExistingAllocation($student_id, $dbOps);
        
        // 3. If existing allocation for SAME config, abort
        if ($existing && $existing['fee_config_id'] == $fee_config_id) {
            throw new Exception('Fees already assigned for this configuration');
        }
        
        // 4. Calculate new allocation amounts
        $new_payable = $new_fee_config['total_fees'] - $new_fee_config['token_fee'];
        $transferred_paid = 0;
        $reassignment_note = '';
        
        // 5. Handle existing allocation (DIFFERENT config)
        if ($existing) {
            $transferred_paid = $existing['paid_amount'];
            $reassignment_note = sprintf(
                "Reassigned from Config #%d (%s) - Transferred Paid: ₹%s",
                $existing['fee_config_id'],
                $existing['old_academic_year'],
                number_format($transferred_paid, 2)
            );
            
            // Archive old allocation
            archiveOldAllocation($existing['id'], $conn);
            
            // Delete pending installments from old config
            deletePendingInstallments($existing['id'], $conn);
        }
        
        // 6. Calculate new pending amount
        $new_pending = $new_payable - $transferred_paid;
        if ($new_pending < 0) {
            // Student has overpaid - needs refund or manual adjustment
            $conn->rollBack();
            throw new Exception(
                sprintf(
                    "Payment adjustment required. Student paid ₹%s but new fees are ₹%s. Difference: ₹%s needs refund/adjustment.",
                    number_format($transferred_paid, 2),
                    number_format($new_payable, 2),
                    number_format(abs($new_pending), 2)
                )
            );
        }
        
        // 7. Create new allocation
        $new_allocation_id = createFeeAllocation([
            'student_id' => $student_id,
            'fee_config_id' => $fee_config_id,
            'allocated_amount' => $new_payable,
            'paid_amount' => $transferred_paid,
            'pending_amount' => $new_pending,
            'status' => $transferred_paid > 0 ? 'partial' : 'pending',
            'academic_year' => $new_fee_config['academic_year'],
            'notes' => $reassignment_note
        ], $conn);
        
        // 8. Create new installments for remaining balance
        if ($new_pending > 0) {
            createInstallments(
                $new_allocation_id,
                $student_id,
                $fee_config_id,
                $new_pending,
                $new_fee_config['number_of_installments'],
                $conn
            );
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'message' => $existing ? 'Fee reassigned successfully' : 'Fee assigned successfully',
            'transferred_paid' => $transferred_paid,
            'new_pending' => $new_pending,
            'is_reassignment' => (bool)$existing
        ];
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
```

---

## Edge Cases & Handling

### Edge Case 1: Student Overpaid on Old Config
**Scenario**: Old config ₹100K, Paid ₹100K. New config ₹80K.
**Handling**: 
- Block automatic reassignment
- Show error: "Manual review required - Student has overpaid ₹20,000"
- Require accountant to process refund first

### Edge Case 2: Multiple Allocations for Same Student
**Scenario**: Student has allocations for Year 1 AND Year 2
**Handling**:
- Only consider allocation for SAME academic year as new config
- If reassigning within same year, use above logic
- If assigning for NEW year, treat as fresh assignment

### Edge Case 3: Installment Mismatch
**Scenario**: Old config had 4 installments (2 paid). New config has 3 installments.
**Handling**:
- Count paid installments: 2
- Remaining balance: ₹X
- Create (new_installments - paid_count) new installments for remaining balance
- Example: 3 new installments - 2 paid = 1 installment for remaining ₹Y

### Edge Case 4: Partial Payment on Current Installment
**Scenario**: Installment #2 has ₹5000 due, student paid ₹3000 (partial)
**Handling**:
- Mark installment as 'partial' status
- When creating new installments, include remaining ₹2000 in calculations
- Distribute total pending across all new installments evenly

---

## Database Changes Required

### 1. Add Archive Table (Optional but Recommended)
```sql
CREATE TABLE tbl_student_fee_allocation_archive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_allocation_id INT NOT NULL,
    student_id INT NOT NULL,
    fee_config_id INT NOT NULL,
    allocated_amount DECIMAL(10,2),
    paid_amount DECIMAL(10,2),
    pending_amount DECIMAL(10,2),
    status VARCHAR(20),
    academic_year VARCHAR(20),
    archived_reason TEXT,
    archived_by INT,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(student_id),
    INDEX(original_allocation_id)
);
```

### 2. Add Notes Field to tbl_student_fee_allocation
```sql
ALTER TABLE tbl_student_fee_allocation 
ADD COLUMN reassignment_notes TEXT AFTER waive_reason;
```

### 3. Add History Tracking
```sql
CREATE TABLE tbl_fee_reassignment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    old_allocation_id INT,
    new_allocation_id INT,
    old_config_id INT,
    new_config_id INT,
    transferred_amount DECIMAL(10,2),
    fee_difference DECIMAL(10,2),
    reason TEXT,
    processed_by INT NOT NULL,
    processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(student_id),
    INDEX(processed_at)
);
```

---

## Testing Scenarios

### Test Case 1: New Student (No Allocation)
**Input**: Student ID: 101, Fee Config: 2026-2027
**Expected**: 
- Shows in list with "New" badge
- Assigns normally
- Creates allocation with paid_amount = 0

### Test Case 2: Student with Same Config
**Input**: Student ID: 102 already has Config #5, trying to assign Config #5 again
**Expected**:
- Does NOT show in dropdown list
- If somehow submitted, returns error

### Test Case 3: Student with Different Config (No Payment)
**Input**: Student has Config #4 (₹100K), no payments, assigning Config #5 (₹112K)
**Expected**:
- Shows with "Reassign" badge
- Archives old allocation
- Creates new allocation with ₹112K total

### Test Case 4: Student with Partial Payments
**Input**: Student has Config #4, paid ₹30K, pending ₹70K. New config ₹112K.
**Expected**:
- Shows with "Has Payments" badge showing ₹30K paid
- Transfers ₹30K to new allocation
- New pending = ₹100.8K - ₹30K = ₹70.8K
- Creates installments for ₹70.8K

### Test Case 5: Student Overpaid
**Input**: Student paid ₹100K on old ₹100K config. New config ₹80K.
**Expected**:
- Shows with "Fully Paid" badge
- On assignment attempt, returns error
- Requires manual review

---

## Approval Checklist

Before implementation, confirm:

- [ ] **Calculation logic** is correct for all scenarios
- [ ] **Edge cases** are properly handled
- [ ] **Database changes** are approved (new columns/tables)
- [ ] **Display format** meets requirements
- [ ] **Error messages** are user-friendly
- [ ] **Archive strategy** is decided (soft delete vs archive table)
- [ ] **Testing plan** is sufficient
- [ ] **Rollback plan** exists if issues arise

---

## Questions for Clarification

1. **Overpayment Scenario**: Should system auto-process refunds or require manual accountant intervention?

2. **Academic Year Handling**: Can a student have multiple allocations across different academic years simultaneously?

3. **Archive Strategy**: Should we soft-delete (add status='archived') or move to separate archive table?

4. **Display Priority**: Should students with partial payments appear at top/bottom of dropdown, or mixed with others?

5. **Installment Redistribution**: When reassigning with partial payments, should remaining installments be evenly distributed or maintain old schedule where possible?

6. **Permission Control**: Should reassignment require higher permission level than initial assignment?

---

## Implementation Timeline (After Approval)

1. **Phase 1** (2 hours): Update SQL query + test with sample data
2. **Phase 2** (1 hour): Enhance frontend dropdown display
3. **Phase 3** (3 hours): Implement reassignment logic with all scenarios
4. **Phase 4** (1 hour): Add logging and error handling
5. **Phase 5** (2 hours): Testing all scenarios
6. **Total**: ~9 hours development + testing

---

**Status**: ⏳ AWAITING APPROVAL

**Next Step**: Review document, answer questions, approve for implementation
