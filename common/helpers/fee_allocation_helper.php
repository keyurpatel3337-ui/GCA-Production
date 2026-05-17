<?php
/**
 * Fee Allocation Helper
 * 
 * Provides utility functions to synchronize student fee allocations
 * with actual payments and scholarships.
 */

require_once __DIR__ . '/receipt_mapping_functions.php';

if (!function_exists('fetchHostelSettings')) {
    function fetchHostelSettings($conn, $ay_id) {
        if (!$ay_id) {
            $stmt_ay = $conn->query("SELECT id FROM tbl_academic_years WHERE is_active = 1 LIMIT 1");
            $ay = $stmt_ay->fetch(PDO::FETCH_ASSOC);
            $ay_id = $ay['id'] ?? null;
        }

        $hostel_cfg = null;
        if ($ay_id) {
            $stmt_hc = $conn->prepare("SELECT * FROM tbl_hostel_fee_settings WHERE academic_year_id = ? AND is_active = 1 LIMIT 1");
            $stmt_hc->execute([$ay_id]);
            $hostel_cfg = $stmt_hc->fetch(PDO::FETCH_ASSOC);
        }
        if (!$hostel_cfg) {
            $hostel_cfg = $conn->query("SELECT * FROM tbl_hostel_fee_settings WHERE is_active = 1 ORDER BY academic_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }
        return $hostel_cfg;
    }
}

if (!function_exists('fetchTransportSettings')) {
    function fetchTransportSettings($conn, $ay_id, $course_id) {
        if (!$ay_id) {
            $stmt_ay = $conn->query("SELECT id FROM tbl_academic_years WHERE is_active = 1 LIMIT 1");
            $ay = $stmt_ay->fetch(PDO::FETCH_ASSOC);
            $ay_id = $ay['id'] ?? null;
        }

        $transport_cfg = null;
        if ($ay_id) {
            $stmt_tc = $conn->prepare("SELECT * FROM tbl_transport_fee_settings WHERE academic_year_id = ? AND course_id = ? AND is_active = 1 LIMIT 1");
            $stmt_tc->execute([$ay_id, $course_id]);
            $transport_cfg = $stmt_tc->fetch(PDO::FETCH_ASSOC);

            if (!$transport_cfg) {
                $stmt_tc = $conn->prepare("SELECT * FROM tbl_transport_fee_settings WHERE academic_year_id = ? AND (course_id IS NULL OR course_id = 0) AND is_active = 1 LIMIT 1");
                $stmt_tc->execute([$ay_id]);
                $transport_cfg = $stmt_tc->fetch(PDO::FETCH_ASSOC);
            }
        }
        if (!$transport_cfg) {
            $stmt_tc = $conn->prepare("SELECT * FROM tbl_transport_fee_settings WHERE is_active = 1 AND course_id = ? ORDER BY academic_year_id DESC LIMIT 1");
            $stmt_tc->execute([$course_id]);
            $transport_cfg = $stmt_tc->fetch(PDO::FETCH_ASSOC);

            if (!$transport_cfg) {
                $transport_cfg = $conn->query("SELECT * FROM tbl_transport_fee_settings WHERE is_active = 1 AND (course_id IS NULL OR course_id = 0) ORDER BY academic_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
        }
        return $transport_cfg;
    }
}

/**
 * Synchronize a student's fee allocation with their payments and scholarships.
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID
 * @return array Result summary
 */
function syncStudentFeeAllocation($conn, $student_id)
{
    try {
        // 1. Get Scholarship Data from Registration AND Enrolled Students (for Post-Admission Discount)
        $stmt = $conn->prepare("SELECT r.*, es.post_admission_discount_amount 
                               FROM tbl_gm_std_registration r
                               LEFT JOIN tbl_enrolled_students es ON r.id = es.registration_id AND es.is_active = 1
                               WHERE r.id = ?");
        $stmt->execute([$student_id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registration) {
            return ['success' => false, 'message' => "Student not found"];
        }

        $scholarship = floatval($registration['scholarship_amount'] ?? 0);
        $additional = floatval($registration['additional_scholarship_amount'] ?? 0);
        $post_admission_discount = floatval($registration['post_admission_discount_amount'] ?? 0);

        // 2. Get all Allocation records for the student with their active configuration
        // We use GREATEST(sfa.allocated_amount, fc.total_fees) to handle cases where 
        // the stored allocation is outdated or erroneously stored as Net fee.
        $stmt = $conn->prepare("SELECT sfa.*, fc.tuition_fee_part1, fc.tuition_fee_part2, fc.school_fee, 
                                     fc.trust_facilities_fee, fc.hostel_fee, fc.token_fee, fc.total_fees as config_total,
                                     fc.school_fee_gst, fc.trust_fee_gst, fc.tuition_fee_gst, fc.token_fee_gst
                               FROM tbl_student_fee_allocation sfa
                               LEFT JOIN tbl_fee_config fc ON sfa.fee_config_id = fc.id
                               WHERE sfa.student_id = ?");
        $stmt->execute([$student_id]);
        $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];

        foreach ($allocations as $alloc) {
            $alloc_id = $alloc['id'];

            // Determine relevant components for this allocation based on the config
            $relevant_components = [];
            if (floatval($alloc['tuition_fee_part1'] ?? 0) > 0)
                $relevant_components[] = 'tuition_fee_part1';
            if (floatval($alloc['tuition_fee_part2'] ?? 0) > 0)
                $relevant_components[] = 'tuition_fee_part2';
            if (floatval($alloc['school_fee'] ?? 0) > 0)
                $relevant_components[] = 'school_fee';
            if (floatval($alloc['trust_facilities_fee'] ?? 0) > 0)
                $relevant_components[] = 'trust_facilities_fee';
            if (floatval($alloc['hostel_fee'] ?? 0) > 0)
                $relevant_components[] = 'hostel_fee';
            if (floatval($alloc['token_fee'] ?? 0) > 0)
                $relevant_components[] = 'token_fee';
            
            // Always include universal components if requirement met or payments exist
            $relevant_components = array_merge($relevant_components, ['hostel_fee', 'hostel_cash_fee', 'hostel_security', 'transport_fee', 'admission_fee', 'registration_fee']);
            $relevant_components = array_unique($relevant_components);

            // If it's a generic allocation, it might not have components in config
            // But usually we sum payments matching these types

            if (empty($relevant_components)) {
                // Fallback: If no components marked in config, we can't reliably sync
                continue;
            }

            // 3. Sum ACTUAL payments for these components (from both tables)
            $placeholders = implode(',', array_fill(0, count($relevant_components), '?'));
            $sql_pay = "SELECT fee_component, SUM(amount) as total_paid, MAX(payment_date) as last_date, MAX(receipt_no) as latest_receipt_no 
                       FROM tbl_payments
                       WHERE student_id = ? AND fee_component IN ($placeholders) AND status = 'paid'
                       GROUP BY fee_component";

            $pay_params = array_merge([$student_id], $relevant_components);
            $stmt_pay = $conn->prepare($sql_pay);
            $stmt_pay->execute($pay_params);
            $pay_results = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);

            $real_paid = 0;
            $last_date = null;
            $any_without_gst = false;
            $total_base_amount_for_without_gst = 0;

            foreach ($pay_results as $p_row) {
                $comp_paid = floatval($p_row['total_paid'] ?? 0);
                $real_paid += $comp_paid;
                if ($p_row['last_date'] && (!$last_date || strtotime($p_row['last_date']) > strtotime($last_date))) {
                    $last_date = $p_row['last_date'];
                }
                if ($p_row['latest_receipt_no'] === '0') {
                    $any_without_gst = true;
                    // Note: User said absorption logic is not needed, but we keep the flag detection 
                    // for potential future use or to maintain component-level metadata.
                    if (isset($alloc[$p_row['fee_component']])) {
                        $comp_base = floatval($alloc[$p_row['fee_component']]);
                        if ($comp_paid >= $comp_base) {
                            $total_base_amount_for_without_gst += $comp_base;
                        }
                    }
                }
            }

            // 4. Determine if Scholarship applies to THIS allocation
            // Usually scholarships apply to the tuition-based allocation
            $applies_scholarship = false;
            // Scholarship + Discounts typically apply to Tuition Part 2 or generic School Fee/Tuition
            if (in_array('tuition_fee_part2', $relevant_components) || in_array('school_fee', $relevant_components) || in_array('tuition_fee_part1', $relevant_components) || in_array('trust_facilities_fee', $relevant_components)) {
                $applies_scholarship = true;
            }

            $current_sch = $applies_scholarship ? $scholarship : 0;
            $current_add = $applies_scholarship ? $additional : 0;
            // Post-Admission Discount also acts as a waiver
            $current_disc = $applies_scholarship ? $post_admission_discount : 0;

            // 5. Calculate New Pending and Status
            // Dynamic Allocation Re-calculation
            require_once HELPERS_PATH . 'fee_helper.php';
            $hostel_cfg = fetchHostelSettings($conn, $registration['academic_year_id']);
            $transport_cfg = fetchTransportSettings($conn, $registration['academic_year_id'], $registration['course_id']);
            
            $current_allocations = buildFeeAllocationPayload($registration, $alloc, $hostel_cfg, $transport_cfg, null);
            $dynamic_total = 0;
            foreach($current_allocations as $ca) {
                $dynamic_total += $ca['gross_amount'];
            }
            // DEBUG
            // echo "Student $student_id: config_total=$config_total, stored=$stored_allocated, dynamic=$dynamic_total\n";

            $stored_allocated = floatval($alloc['allocated_amount']);
            $config_total = floatval($alloc['config_total'] ?? 0);
            $allocated = max($stored_allocated, $config_total, $dynamic_total);

            $total_waiver = $current_sch + $current_add + $current_disc;

            $new_pending = max(0, $allocated - $real_paid - $total_waiver);

            $status = 'pending';
            if ($new_pending <= 0) {
                $status = 'paid';
            } elseif ($real_paid > 0 || $total_waiver > 0) {
                // If anything is paid or waived but pending > 0, it's partial
                $status = 'partial';
            }

            // 6. Update Allocation Table
            // Note: tbl_student_fee_allocation doesn't have 'post_admission_discount' column generally.
            // We usually lump it into 'additional_scholarship' OR just rely on pending calc.
            // But helper updates specific columns. 
            // If we don't save the discount amount in allocation table, subsequent reads of allocation table might miscalculate pending 
            // UNLESS the PHP logic always recalculates pending using sync.
            // However, 'pending_amount' IS a column.
            // To ensure consistency, we should probably add it to 'additional_scholarship' field for storage 
            // OR confirm if we can add a new column. 
            // Given constraints, I will add it to 'additional_scholarship' VALUE for the update query so it persists in the sum,
            // OR checks if I can change the logic. 
            // Actually, `pending-payments_controller.php` reads `pending_amount` DIRECTLY.
            // So `pending_amount` MUST be correct here.

            // I will update pending_amount correctly.
            // For metadata columns (scholarship_amount, etc), I will store the sum in additional_scholarship if strict mapping isn't needed,
            // or just update pending.
            // Let's modify the query to update pending correctly. The UI often reads scholarship columns for display.
            // If I add discount to additional_scholarship, it might look like scholarship in some UIs, which is acceptable per user request ("count as scholarship").

            $stmt_upd = $conn->prepare("UPDATE tbl_student_fee_allocation 
                                      SET allocated_amount = ?,
                                          paid_amount = ?, 
                                          scholarship_amount = ?, 
                                          additional_scholarship = ?,
                                          post_admission_discount = ?,
                                          pending_amount = ?, 
                                          status = ?, 
                                          last_payment_date = ?, 
                                          updated_at = NOW() 
                                      WHERE id = ?");
            $stmt_upd->execute([
                $allocated,
                $real_paid,
                $current_sch,
                $current_add,
                $current_disc,
                $new_pending,
                $status,
                $last_date,
                $alloc_id
            ]);


            $results[] = [
                'allocation_id' => $alloc_id,
                'paid' => $real_paid,
                'scholarship' => $total_waiver,
                'pending' => $new_pending,
                'status' => $status
            ];
        }

        return ['success' => true, 'data' => $results];

    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Ensure a student has a fee allocation record for their current course/term.
 * If not exists, it creates one based on relevant configuration.
 * 
 * @param PDO $conn Database connection
 * @param int $student_id Student ID
 * @param int $term_id Term ID (defaults to 1)
 * @return array Result summary
 */
function ensureFeeAllocation($conn, $student_id, $term_id = 1)
{
    try {
        // 1. Get Student's current enrollment/registration details
        $stmt = $conn->prepare("SELECT s.id as registration_id, s.course_id, s.school_id, s.medium_id, s.group_id, s.academic_year_id,
                                   s.scholarship_amount, s.additional_scholarship_amount,
                                   ay.year_name as academic_year
                            FROM tbl_gm_std_registration s
                            LEFT JOIN tbl_academic_years ay ON s.academic_year_id = ay.id
                            WHERE s.id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) return ['success' => false, 'message' => "Student not found"];

        // 2. Identify the matching fee configuration (most recent active for the course)
        $term_str = "Semester " . $term_id;

        // Try strict match: course + school + medium + group
        $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config
                               WHERE course_id = ? AND school_id = ? AND medium_id = ? AND group_id = ? AND is_active = 1
                               AND (term = ? OR term = ? OR term IS NULL OR term = '' OR term = '0')
                               ORDER BY academic_year DESC, id DESC LIMIT 1");
        $stmt_fc->execute([$student['course_id'], $student['school_id'], $student['medium_id'], $student['group_id'], (string)$term_id, $term_str]);
        $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);

        // Fallback 1: Drop medium (keep school_id)
        if (!$fc) {
            $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config
                                   WHERE course_id = ? AND school_id = ? AND group_id = ? AND is_active = 1
                                   AND (term = ? OR term = ? OR term IS NULL OR term = '' OR term = '0')
                                   ORDER BY academic_year DESC, id DESC LIMIT 1");
            $stmt_fc->execute([$student['course_id'], $student['school_id'], $student['group_id'], (string)$term_id, $term_str]);
            $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);
        }

        // Fallback 2: Drop school_id + medium (course + group only)
        if (!$fc) {
            $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config
                                   WHERE course_id = ? AND group_id = ? AND is_active = 1
                                   AND (term = ? OR term = ? OR term IS NULL OR term = '' OR term = '0')
                                   ORDER BY academic_year DESC, id DESC LIMIT 1");
            $stmt_fc->execute([$student['course_id'], $student['group_id'], (string)$term_id, $term_str]);
            $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);
        }

        // Fallback 3: Course only
        if (!$fc) {
            $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config
                                   WHERE course_id = ? AND is_active = 1
                                   AND (term = ? OR term = ? OR term IS NULL OR term = '' OR term = '0')
                                   ORDER BY academic_year DESC, id DESC LIMIT 1");
            $stmt_fc->execute([$student['course_id'], (string)$term_id, $term_str]);
            $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);
        }

        if (!$fc) return ['success' => false, 'message' => "Fee configuration not found for Course " . $student['course_id']];

        // 3. Check if allocation already exists
        $stmt_chk = $conn->prepare("SELECT id FROM tbl_student_fee_allocation WHERE student_id = ? AND term_id = ?");
        $stmt_chk->execute([$student_id, $term_id]);
        if ($stmt_chk->fetch()) {
            // Already exists, just sync records
            syncStudentFeeAllocation($conn, $student_id);
            return ['success' => true, 'message' => "Allocation already exists, synchronized."];
        }

        // 4. Create Allocation Record
        $stmt_ins = $conn->prepare("INSERT INTO tbl_student_fee_allocation 
            (student_id, fee_config_id, term_id, allocated_amount, paid_amount, 
            scholarship_amount, additional_scholarship, pending_amount, 
            academic_year, allocated_by, created_by, allocated_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");

        // 4. Calculate True Total (Including Hostel/Transport if required)
        require_once HELPERS_PATH . 'fee_helper.php';
        
        $hostel_cfg = fetchHostelSettings($conn, $student['academic_year_id']);
        // Fetch Transport settings
        $stmt_tc = $conn->prepare("SELECT * FROM tbl_transport_fee_settings WHERE is_active = 1 AND academic_year_id = ? AND (course_id = ? OR course_id IS NULL OR course_id = 0) ORDER BY course_id DESC LIMIT 1");
        $stmt_tc->execute([$student['academic_year_id'], $student['course_id']]);
        $transport_cfg = $stmt_tc->fetch(PDO::FETCH_ASSOC);

        // Map registration ID to the format expected by buildFeeAllocationPayload (it might need more fields)
        $full_student = array_merge($student, $conn->query("SELECT * FROM tbl_gm_std_registration WHERE id = " . $student_id)->fetch(PDO::FETCH_ASSOC));

        $allocations_breakdown = buildFeeAllocationPayload($full_student, $fc, $hostel_cfg, $transport_cfg);
        
        $gross_fees = 0;
        foreach($allocations_breakdown as $a) {
            $gross_fees += $a['gross_amount'];
        }

        $scholarship = floatval($student['scholarship_amount'] ?? 0);
        $additional = floatval($student['additional_scholarship_amount'] ?? 0);
        
        // Initial Paid amount: In this system's logic, it often starts at 0 or token_fee.
        // We initialize at 0 and let syncStudentFeeAllocation calculate real paid from tbl_payments.
        $paid = 0; 
        $pending = max(0, $gross_fees - $scholarship - $additional);

        $stmt_ins->execute([
            $student_id,
            $fc['id'],
            $term_id,
            $gross_fees,
            $paid,
            $scholarship,
            $additional,
            $pending,
            $fc['academic_year'],
            $_SESSION['user_id'] ?? 0,
            $_SESSION['user_id'] ?? 0
        ]);

        $alloc_id = $conn->lastInsertId();

        // 5. Create installment records (consistent with assign-fees module)
        $num_inst = intval($fc['number_of_installments'] ?: 1);
        if ($num_inst > 0) {
            $inst_amt = round($pending / $num_inst, 2);
            for ($i = 1; $i <= $num_inst; $i++) {
                $stmt_inst = $conn->prepare("INSERT INTO tbl_fee_installments 
                    (allocation_id, student_id, fee_config_id, installment_number, 
                    due_amount, paid_amount, payment_status, created_by) 
                    VALUES (?, ?, ?, ?, ?, 0.00, 'pending', ?)");
                $stmt_inst->execute([$alloc_id, $student_id, $fc['id'], $i, $inst_amt, $_SESSION['user_id'] ?? 0]);
            }
        }

        // 6. Perform a final sync to pull in any existing payments (like token fee)
        syncStudentFeeAllocation($conn, $student_id);

        return ['success' => true, 'message' => "Allocation created and synchronized successfully."];

    } catch (Exception $e) {
        return ['success' => false, 'message' => "Logic error: " . $e->getMessage()];
    }
}
