<?php

/**
 * Function to enroll student and assign fees after token fee payment
 * This should be called after token_fees_paid is set to 1
 */

function enrollStudentAfterTokenPayment($conn, $student_id)
{
    try {
        // Get student details
        $stmt = $conn->prepare("SELECT s.*, 
                                fc.total_fees, fc.school_fee, fc.trust_facilities_fee, 
                                fc.tuition_fee_part1, fc.tuition_fee_part2, fc.hostel_fee,
                                fc.number_of_installments
                                FROM tbl_gm_std_registration s
                                LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                                    AND s.school_id = fc.school_id
                                    AND s.medium_id = fc.medium_id 
                                    AND s.group_id = fc.group_id 
                                    AND fc.is_active = 1
                                WHERE s.id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new Exception("Student not found");
        }

        // Skip if already enrolled
        if ($student['is_enrolled'] && $student['enrollment_id']) {
            return ['success' => true, 'message' => 'Already enrolled', 'enrollment_id' => $student['enrollment_id']];
        }

        // Note: Transaction should be managed by the caller
        // Don't start a new transaction here to avoid nested transaction issues

        // 1. Calculate fees after scholarship FIRST
        // Fee Structure: School Fee + Trust Facilities + Tuition Part 1 (with GST) + Tuition Part 2 (with GST)
        $school_fee = floatval($student['school_fee']);
        $trust_facilities_fee = floatval($student['trust_facilities_fee']);
        $tuition_part1 = floatval($student['tuition_fee_part1']);
        $gst_part1 = $tuition_part1 * 0.18;
        $tuition_part1_with_gst = $tuition_part1 + $gst_part1;

        $tuition_part2 = floatval($student['tuition_fee_part2']);
        $gst_part2 = $tuition_part2 * 0.18;
        $tuition_part2_with_gst = $tuition_part2 + $gst_part2;
        // Token fee is ONLY Tuition Part 1 with GST (Rs. 11,800)
        $token_fee_paid = $tuition_part1_with_gst;

        // Calculate scholarship on tuition part 2
        $scholarship_amount = floatval($student['scholarship_amount'] ?? 0);
        $additional_scholarship_amount = floatval($student['additional_scholarship_amount'] ?? 0);
        $total_scholarship_on_tuition = $scholarship_amount + $additional_scholarship_amount;
        $tuition_part2_after_scholarship = $tuition_part2_with_gst - $total_scholarship_on_tuition;

        // Hostel fee
        $hostel_fee = floatval($student['hostel_fee'] ?? 0);

        // Total fees = School + Trust + Tuition Part 1 + Tuition Part 2 + Hostel
        $total_fees = $school_fee + $trust_facilities_fee + $tuition_part1_with_gst + $tuition_part2_with_gst + $hostel_fee;

        // Net payable = Total fees - Token fee already paid - Scholarship
        // Pending fees = School Fee + Trust Facilities Fee + Tuition Part 2 (after scholarship) + Hostel Fee
        $net_payable = $school_fee + $trust_facilities_fee + $tuition_part2_after_scholarship + $hostel_fee;

        // 2. Create enrollment record in tbl_enrolled_students
        $academic_year = date('Y');

        // Generate enrollment number in format: YYCCXXXXX
        // YY = Admission Year (last 2 digits), CC = 12th Completion Year, XXXXX = 5-digit sequential
        $admission_year = date('y'); // e.g., "26" for 2026
        $student_standard = intval($student['standard'] ?? 11);
        $years_to_complete = 13 - $student_standard; // +1 because academic year ends in next calendar year
        $completion_year = intval(date('Y')) + $years_to_complete;
        $completion_year_short = substr($completion_year, -2); // e.g., "28"

        $prefix = $admission_year . $completion_year_short; // e.g., "2628"

        // Get last sequential number for this prefix
        $stmt_seq = $conn->prepare("
            SELECT MAX(CAST(SUBSTRING(enrollment_no, 5) AS UNSIGNED)) as last_seq 
            FROM tbl_enrolled_students 
            WHERE enrollment_no LIKE ?
        ");
        $stmt_seq->execute([$prefix . '%']);
        $seq_result = $stmt_seq->fetch(PDO::FETCH_ASSOC);
        $last_seq = intval($seq_result['last_seq'] ?? 0);
        $new_seq = $last_seq + 1;

        $enrollment_no = $prefix . str_pad($new_seq, 5, '0', STR_PAD_LEFT); // e.g., "262800001"

        // registration_id is the FK to tbl_gm_std_registration.id
        // std_id column is deprecated and no longer used

        $total_scholarship = $scholarship_amount + $additional_scholarship_amount;
        $is_scholarship_student = ($total_scholarship > 0) ? 1 : 0;
        $counsellor_id = $student['confirmed_by'] ?? null;

        $stmt = $conn->prepare("INSERT INTO tbl_enrolled_students 
                                (registration_id, enrollment_no, current_term_id,
                                 enrollment_date, enrollment_status, is_active, created_at, updated_at)
                                VALUES 
                                (?, ?, 1, NOW(), 'active', 1, NOW(), NOW())");

        $token_fee_paid = $student['token_amount'] ?? 0;
        $total_scholarship = $scholarship_amount + $additional_scholarship_amount;
        $is_scholarship_student = ($total_scholarship > 0) ? 1 : 0;
        $counsellor_id = $student['confirmed_by'] ?? null;

        $stmt->execute([
            $student_id,
            $enrollment_no
        ]);
        $enrollment_id = $conn->lastInsertId();

        // 3. Update registration record with enrollment info
        $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                                SET is_enrolled = 1,
                                    enrollment_id = ?,
                                    enrollment_date = NOW()
                                WHERE id = ?");
        $stmt->execute([$enrollment_id, $student_id]);

        // 4. Assign fees using tbl_student_fee_allocation
        // Note: Allocated amount should always represent the GROSS fee.
        // Scholarship and discounts are stored in dedicated columns.
        $allocated_amount = $total_fees;
        $paid_amount = $tuition_part1_with_gst; // Token fee already paid (Rs. 11,800)
        $pending_amount = $net_payable; // Remaining balance
        $payment_status = 'partial'; // Always partial since token is paid

        // Guard: skip fee allocation if one already exists for this student (any term_id=1 record)
        $stmt_chk = $conn->prepare("SELECT id FROM tbl_student_fee_allocation WHERE student_id = ? AND term_id = 1 LIMIT 1");
        $stmt_chk->execute([$student_id]);
        if (!$stmt_chk->fetch()) {
            $stmt = $conn->prepare("INSERT INTO tbl_student_fee_allocation
                                    (student_id, fee_config_id, allocated_amount, paid_amount,
                                     scholarship_amount, additional_scholarship, pending_amount,
                                     status, academic_year, allocated_by, created_by, allocated_at, updated_at)
                                    SELECT
                                     ?, fc.id, ?, ?,
                                     ?, ?, ?,
                                     ?, ?, 1, 1, NOW(), NOW()
                                    FROM tbl_fee_config fc
                                    WHERE fc.course_id = ? AND fc.school_id = ? AND fc.medium_id = ? AND fc.group_id = ? AND fc.is_active = 1
                                    LIMIT 1");

            $stmt->execute([
                $student_id,
                $allocated_amount,
                $paid_amount,
                $scholarship_amount,
                $additional_scholarship_amount,
                $pending_amount,
                $payment_status,
                $academic_year,
                $student['course_id'],
                $student['school_id'],
                $student['medium_id'],
                $student['group_id']
            ]);
        }

        // Transaction commit is handled by the caller

        return [
            'success' => true,
            'enrollment_id' => $enrollment_id,
            'message' => 'Student enrolled and fees assigned successfully'
        ];
    } catch (Exception $e) {
        // Don't rollback here - let the caller handle it
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Function to generate 3 separate receipts for token fee components
 */
function generateTokenFeeReceipts($conn, $student_id, $payment_date, $transaction_id, $payment_mode, $created_by = 1)
{
    try {
        // Get fee components
        $stmt = $conn->prepare("SELECT s.*,
                                fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1
                                FROM tbl_gm_std_registration s
                                LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                                    AND s.medium_id = fc.medium_id 
                                    AND s.group_id = fc.group_id 
                                    AND fc.is_active = 1
                                WHERE s.id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new Exception("Student not found");
        }

        $school_fee = floatval($student['school_fee'] ?? 0);
        $trust_facilities_fee = floatval($student['trust_facilities_fee'] ?? 0);
        $tuition_part1 = floatval($student['tuition_fee_part1'] ?? 0);
        $gst_part1 = $tuition_part1 * 0.18;
        $tuition_part1_with_gst = $tuition_part1 + $gst_part1;

        $date_suffix = date('Ymd', strtotime($payment_date));
        $std_suffix = str_pad($student_id, 6, '0', STR_PAD_LEFT);

        $receipts = [];

        // Receipt for Tuition Fee Part 1 (with GST) - GCA receipt
        // Get next sequential receipt number (no prefix)
        if ($tuition_part1_with_gst > 0) {
            $stmt = $conn->prepare("SELECT MAX(CAST(receipt_no AS UNSIGNED)) as last_num 
                                   FROM tbl_payments 
                                   WHERE fee_component = 'tuition_fee_part1' 
                                   AND receipt_no REGEXP '^[0-9]+$'");
            $stmt->execute();
            $result = $stmt->fetch();
            $last_num = intval($result['last_num'] ?? 0);
            $receipt_no = str_pad($last_num + 1, 2, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT INTO tbl_payments 
                                   (student_id, receipt_no, amount, payment_date, payment_mode, 
                                    transaction_id, payment_type, fee_component, remarks, 
                                    status, created_by, created_at) 
                                   VALUES 
                                   (?, ?, ?, ?, ?, ?, 'token_fee', 'tuition_fee_part1', 'Tuition Fee Part 1 (incl. 18% GST) - Gyanmanjari Career Academy', 'paid', ?, ?)");
            $stmt->execute([
                $student_id,
                $receipt_no,
                $tuition_part1_with_gst,
                $payment_date,
                $payment_mode,
                $transaction_id,
                $created_by,
                $payment_date
            ]);
            $receipts[] = ['receipt_no' => $receipt_no, 'amount' => $tuition_part1_with_gst, 'component' => 'Tuition Fee Part 1'];
        }

        return ['success' => true, 'receipts' => $receipts];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
