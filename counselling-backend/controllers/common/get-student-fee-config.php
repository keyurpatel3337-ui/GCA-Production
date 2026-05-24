<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';

/**
 * Get Student Fee Configuration API
 * Returns fee configuration for a student based on their course, medium, and group
 */
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Initialize database operations
$dbOps = new DatabaseOperations();

header('Content-Type: application/json; charset=utf-8');

// Check if user is Accountant, Principal, or Super Admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check role - allow Accountant (5), Principal (2), or Super Admin (1)
$allowed_roles = [ROLE_ACCOUNTANT, ROLE_PRINCIPLE, ROLE_SUPER_ADMIN];
$user_role_id = $_SESSION['role_id'] ?? 0;
if (!in_array($user_role_id, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - insufficient permissions']);
    exit;
}

$student_id = intval($_GET['student_id'] ?? 0); // Cast to int immediately — clears XSS/injection vectors

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

try {
    // Get student details including course, medium, group, scholarship info
    $student = $dbOps->selectOne('tbl_gm_std_registration', [
        'id',
        'course_id',
        'medium_id',
        'group_id',
        'hostel_required',
        'transport_required',
        'gender',
        'scholarship_amount',
        'additional_scholarship_amount',
        'scholarship_percentage'
    ], ['id' => $student_id]);

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student not found']);
        exit;
    }

    // Get enrollment details for post-admission discount and current term
    $enrollment = $dbOps->selectOne('tbl_enrolled_students', [
        'enrollment_id',
        'current_term_id',
        'post_admission_discount_amount',
        'post_admission_discount_remarks'
    ], ['registration_id' => $student_id, 'is_active' => 1]);

    // Get Term Name for fee config filtering
    $term_name = 'Semester 1'; // Default
    if ($enrollment && !empty($enrollment['current_term_id'])) {
        $term_row = $dbOps->selectOne('tbl_term', ['term_name'], ['id' => $enrollment['current_term_id']]);
        if ($term_row) {
            $term_name = $term_row['term_name'];
        }
    }

    // Progressive fallback fee config fetching
    // 1. Exact match: course + medium + group + term
    $fee_config = $dbOps->selectOne('tbl_fee_config', [
        'id',
        'course_name',
        'course_id',
        'medium_id',
        'group_id',
        'school_fee',
        'trust_facilities_fee',
        'tuition_fee_part1',
        'tuition_fee_part2',
        'hostel_fee',
        'token_fee',
        'total_fees',
        'number_of_installments'
    ], [
        'course_id' => $student['course_id'],
        'medium_id' => $student['medium_id'],
        'group_id' => $student['group_id'],
        'term' => $term_name,
        'is_active' => 1
    ]);

    // 2. Drop term filter
    if (!$fee_config) {
        $sql_fb = "SELECT fc.id, fc.course_name, fc.course_id, fc.medium_id, fc.group_id,
                    fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                    fc.hostel_fee, fc.token_fee, fc.total_fees, fc.number_of_installments
                    FROM tbl_fee_config fc
                    WHERE fc.course_id = ? AND fc.medium_id = ? AND fc.group_id = ? AND fc.is_active = 1
                    ORDER BY fc.id DESC LIMIT 1";
        $result = $dbOps->customSelect($sql_fb, [$student['course_id'], $student['medium_id'], $student['group_id']]);
        $fee_config = $result[0] ?? null;
    }

    // 3. Drop medium_id (some students have different medium than config)
    if (!$fee_config) {
        $sql_fb = "SELECT fc.id, fc.course_name, fc.course_id, fc.medium_id, fc.group_id,
                    fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                    fc.hostel_fee, fc.token_fee, fc.total_fees, fc.number_of_installments
                    FROM tbl_fee_config fc
                    WHERE fc.course_id = ? AND fc.group_id = ? AND fc.is_active = 1
                    ORDER BY fc.id DESC LIMIT 1";
        $result = $dbOps->customSelect($sql_fb, [$student['course_id'], $student['group_id']]);
        $fee_config = $result[0] ?? null;
    }

    // 4. Drop group too (course only)
    if (!$fee_config) {
        $sql_fb = "SELECT fc.id, fc.course_name, fc.course_id, fc.medium_id, fc.group_id,
                    fc.school_fee, fc.trust_facilities_fee, fc.tuition_fee_part1, fc.tuition_fee_part2,
                    fc.hostel_fee, fc.token_fee, fc.total_fees, fc.number_of_installments
                    FROM tbl_fee_config fc
                    WHERE fc.course_id = ? AND fc.is_active = 1
                    ORDER BY fc.id DESC LIMIT 1";
        $result = $dbOps->customSelect($sql_fb, [$student['course_id']]);
        $fee_config = $result[0] ?? null;
    }

    if (!$fee_config) {
        echo json_encode(['success' => false, 'message' => 'No fee configuration found for this student']);
        exit;
    }

    // Calculate Potential Hostel Fee (regardless of requirement)
    $potential_hostel_fee = 0;
    $fee_config['hostel_fee'] = 0; // Default

    try {
        $academic_years = $dbOps->customSelect("SELECT id FROM tbl_academic_years WHERE is_active = 1 ORDER BY year_name DESC LIMIT 1", []);
        $academic_year = !empty($academic_years) ? $academic_years[0] : null;

        if ($academic_year) {
            $hostel_settings = $dbOps->selectOne('tbl_hostel_fee_settings', [
                'security_deposit',
                'boys_hostel_fee',
                'girls_hostel_fee',
                'gst_applicable',
                'gst_rate',
                'split_threshold'
            ], [
                'academic_year_id' => $academic_year['id'],
                'is_active' => 1
            ]);

            if (!$hostel_settings) {
                // Fallback: If no settings for active AY, get the most recent active settings
                $hostel_settings = $dbOps->customSelect("SELECT security_deposit, boys_hostel_fee, girls_hostel_fee, gst_applicable, gst_rate, split_threshold FROM tbl_hostel_fee_settings WHERE is_active = 1 ORDER BY academic_year_id DESC LIMIT 1");
                $hostel_settings = !empty($hostel_settings) ? $hostel_settings[0] : null;
            }

            if ($hostel_settings) {
                // Calculate Security Deposit
                $security_deposit = floatval($hostel_settings['security_deposit']);

                // Calculate Full Fee based on gender
                $full_fee_base = 0;
                $student_gender = strtolower($student['gender'] ?? '');

                if ($student_gender === 'female' || $student_gender === 'girl') {
                    $full_fee_base = floatval($hostel_settings['girls_hostel_fee']);
                } else {
                    // Default to boys for male or unspecified
                    $full_fee_base = floatval($hostel_settings['boys_hostel_fee']);
                }

                // Initialize calculated amounts
                $potential_hostel_fee = $security_deposit; // Default potential is security deposit
                $hostel_full_fee = $full_fee_base;
                $hostel_security_deposit = $security_deposit;
                $hostel_split_threshold = floatval($hostel_settings['split_threshold'] ?? 0);

                // Apply GST if applicable
                if ($hostel_settings['gst_applicable']) {
                    $gst_rate = floatval($hostel_settings['gst_rate']);

                    // Apply to Security Deposit
                    $sd_gst = ($security_deposit * $gst_rate) / 100;
                    $hostel_security_deposit += $sd_gst;
                    $potential_hostel_fee = $hostel_security_deposit;

                    // Apply to Full Fee
                    $ff_gst = ($full_fee_base * $gst_rate) / 100;
                    $hostel_full_fee += $ff_gst;
                }
            }
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Hostel Fee Settings");
    }

    // Default return values if logic above didn't set them
    if (!isset($hostel_full_fee))
        $hostel_full_fee = 0;
    if (!isset($hostel_security_deposit))
        $hostel_security_deposit = 0;
    if (!isset($hostel_split_threshold))
        $hostel_split_threshold = 0;

    if (!empty($student['hostel_required']) && strtolower($student['hostel_required']) === 'yes') {
        // If required, we ideally default to full fee, but let's stick to standard behavior 
        // or let frontend decide. Historically it was just one value. 
        // Let's set the main 'hostel_fee' to security deposit as verified baseline
        $fee_config['hostel_fee'] = $hostel_security_deposit;
    }

    $fee_config['potential_hostel_fee'] = $potential_hostel_fee; // Deprecated but kept for compatibility
    $fee_config['hostel_security_deposit'] = $hostel_security_deposit;
    $fee_config['hostel_full_fee'] = $hostel_full_fee;
    $fee_config['hostel_split_threshold'] = $hostel_split_threshold;
    $fee_config['hostel_cash_fee'] = max(0, $hostel_full_fee - $hostel_security_deposit);

    // Calculate Potential Transport Fee (regardless of requirement)
    $potential_transport_fee = 0;
    $fee_config['transport_fee'] = 0; // Default

    try {
        // Re-fetch academic year if needed (already fetched above)
        if (!isset($academic_year) || !$academic_year) {
            $academic_years = $dbOps->customSelect("SELECT id FROM tbl_academic_years WHERE is_active = 1 ORDER BY year_name DESC LIMIT 1", []);
            $academic_year = !empty($academic_years) ? $academic_years[0] : null;
        }

        if ($academic_year) {
            // 1. Try course-specific first
            $transport_settings = $dbOps->selectOne('tbl_transport_fee_settings', [
                'transport_fee',
                'gst_rate',
                'collection_timeline',
                'term1_months',
                'term2_months',
                'annual_months'
            ], [
                'academic_year_id' => $academic_year['id'],
                'course_id' => $student['course_id'],
                'is_active' => 1
            ]);

            // 2. Fallback to global setting for this AY
            if (!$transport_settings) {
                $sql_fb = "SELECT transport_fee, gst_rate, collection_timeline, term1_months, term2_months, annual_months FROM tbl_transport_fee_settings 
                           WHERE academic_year_id = ? AND (course_id IS NULL OR course_id = 0) AND is_active = 1 
                           LIMIT 1";
                $result = $dbOps->customSelect($sql_fb, [$academic_year['id']]);
                $transport_settings = $result[0] ?? null;
            }

            if (!$transport_settings) {
                // Fallback: If no settings for active AY, get the most recent active settings (prioritize course match)
                $sql_fb = "SELECT transport_fee, gst_rate, collection_timeline, term1_months, term2_months, annual_months FROM tbl_transport_fee_settings 
                           WHERE is_active = 1 AND course_id = ? 
                           ORDER BY academic_year_id DESC LIMIT 1";
                $result = $dbOps->customSelect($sql_fb, [$student['course_id']]);
                $transport_settings = $result[0] ?? null;

                if (!$transport_settings) {
                    $transport_settings = $dbOps->customSelect("SELECT transport_fee, gst_rate, collection_timeline, term1_months, term2_months, annual_months FROM tbl_transport_fee_settings 
                                                                WHERE is_active = 1 AND (course_id IS NULL OR course_id = 0) 
                                                                ORDER BY academic_year_id DESC LIMIT 1");
                    $transport_settings = !empty($transport_settings) ? $transport_settings[0] : null;
                }
            }

            if ($transport_settings) {
                $monthly_rate = floatval($transport_settings['transport_fee']);

                $t1_m = intval($transport_settings['term1_months'] ?? 7);  // Std 11 Sem 1
                $t2_m = intval($transport_settings['term2_months'] ?? 6);  // Std 11 Sem 2
                $an_m = intval($transport_settings['annual_months'] ?? 12); // Std 12

                $course_id = intval($student['course_id']);
                $is_sem2 = stripos($term_name, 'Semester 2') !== false || stripos($term_name, 'Term 2') !== false;

                // Course-based override MUST take priority over collection_timeline
                $timeline = $transport_settings['collection_timeline'] ?? 'Term-wise';

                if (in_array($course_id, [1])) {
                    // Standard 11: always term-wise — Sem 1 = 7 months, Sem 2 = 6 months
                    $months = $is_sem2 ? $t2_m : $t1_m;
                } elseif (in_array($course_id, [2, 3])) {
                    // Standard 12 and Re-Neet: always 12 months annual
                    $months = $an_m;
                } elseif ($timeline === 'Monthly') {
                    $months = 1; // Always 1 month for initial Monthly collection view
                } else {
                    // Generic fallback: respect collection_timeline from settings
                    if ($timeline === 'Annually') {
                        $months = $is_sem2 ? 0 : $an_m;
                    } elseif ($timeline === 'Half-Yearly') {
                        $months = intval($an_m / 2);
                    } else {
                        // Term-wise / Quarterly
                        $months = $is_sem2 ? $t2_m : $t1_m;
                    }
                }

                $potential_transport_fee = $monthly_rate * $months;

                // Apply GST if applicable
                if (!empty($transport_settings['gst_rate']) && $transport_settings['gst_rate'] > 0) {
                    $transport_gst = ($potential_transport_fee * $transport_settings['gst_rate']) / 100;
                    $potential_transport_fee += $transport_gst;
                }
            }
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Transport Fee Settings");
    }

    if (!empty($student['transport_required']) && strtolower($student['transport_required']) === 'yes') {
        $fee_config['transport_fee'] = $potential_transport_fee;
    }

    $fee_config['potential_transport_fee'] = $potential_transport_fee;
    $fee_config['transport_collection_timeline'] = $timeline ?? 'Term-wise';
    $fee_config['transport_monthly_rate'] = $monthly_rate ?? 0;
    $fee_config['transport_gst_rate'] = floatval($transport_settings['gst_rate'] ?? 0);
    $fee_config['transport_term1_months'] = $t1_m ?? 7;
    $fee_config['transport_term2_months'] = $t2_m ?? 6;
    $fee_config['transport_annual_months'] = $an_m ?? 12;

    // Check which fee components have already been paid by this student
    $paid_fees = [];
    try {
        $sql_paid = "SELECT fee_component, SUM(amount) as total_paid, 0 as is_without_gst
                         FROM tbl_payments
                         WHERE student_id = ? 
                       AND status = 'paid' 
                       AND fee_component IS NOT NULL 
                       AND fee_component != ''
                     GROUP BY fee_component";
        $params_paid = [$student_id];
        $paid_results = $dbOps->customSelect($sql_paid, $params_paid);

        foreach ($paid_results as $paid) {
            $comp_key = $paid['fee_component'];
            $amt = floatval($paid['total_paid']);
            
            // If it's a Without-GST payment and matched the base amount in config
            // We should treat it as fully paid (gross) for UI checkbox disabling
            if ($paid['is_without_gst'] == 1 && isset($fee_config[$comp_key])) {
                $base_cfg = floatval($fee_config[$comp_key]);
                if ($amt >= $base_cfg && $base_cfg > 0) {
                    // Approximate gross (18% GST)
                    $amt = max($amt, round($base_cfg * 1.18));
                }
            }
            
            $paid_fees[$comp_key] = $amt;
        }

        // Also check by payment_type for older records that may not have fee_component
        $sql_paid_type = "SELECT payment_type, SUM(amount) as total_paid, 0 as is_without_gst
                          FROM tbl_payments
                          WHERE student_id = ? 
                            AND status = 'paid' 
                            AND (fee_component IS NULL OR fee_component = '')
                          GROUP BY payment_type";
        $paid_type_results = $dbOps->customSelect($sql_paid_type, [$student_id]);

        // Map payment_type strings to fee_component keys
        $type_to_component = [
            'School Fee' => 'school_fee',
            'Trust Facilities Fee' => 'trust_facilities_fee',
            'Tuition Fee Part 1' => 'tuition_fee_part1',
            'Tuition Fee Part 2' => 'tuition_fee_part2',
            'Hostel Fee' => 'hostel_fee'
        ];

        foreach ($paid_type_results as $paid) {
            $payment_type = $paid['payment_type'];
            // Check if payment_type contains any of the fee component names
            foreach ($type_to_component as $name => $component) {
                if (stripos($payment_type, $name) !== false) {
                    if (!isset($paid_fees[$component])) {
                        $paid_fees[$component] = 0;
                    }
                    $paid_fees[$component] += floatval($paid['total_paid']);
                }
            }
        }
    } catch (PDOException $e) {
        logDatabaseError($e, "Fetch Paid Fee Components");
        // Continue without paid fees info - all checkboxes will be enabled
    }

    // Add scholarship info to response
    $scholarship_info = [
        'scholarship_amount' => floatval($student['scholarship_amount'] ?? 0),
        'additional_scholarship_amount' => floatval($student['additional_scholarship_amount'] ?? 0),
        'scholarship_percentage' => floatval($student['scholarship_percentage'] ?? 0),
        'post_admission_discount' => floatval($enrollment['post_admission_discount_amount'] ?? 0),
        'post_admission_discount_remarks' => $enrollment['post_admission_discount_remarks'] ?? ''
    ];

    // INTEGRATE fee_helper.php logic for accurate Semester 2 carryover calculation
    require_once HELPERS_PATH . 'fee_helper.php';

    // Use the existing global $conn defined in DB_CONNECT_FILE (common/db_connect.php)
    global $conn;
    try {
        if ($conn) {
            $fee_summary = calculateStudentFeeSummary($conn, $student_id);
        } else {
            $fee_summary = null;
        }
    } catch (Exception $e) {
        logDatabaseError($e, "Helper Fee Calculation Failed");
        $fee_summary = null;
    }

    echo json_encode([
        'success' => true,
        'fee_config' => $fee_config,
        'paid_fees' => $paid_fees,
        'scholarship_info' => $scholarship_info,
        'fee_allocations' => $fee_summary ? $fee_summary['allocations'] : null, // NEW: Full calculated state
        'student_id' => $student_id
    ]);
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Student Fee Configuration");
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}


