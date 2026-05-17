<?php
require_once __DIR__ . '/format_helper.php';

/**
 * Unified Fee Helper
 * 
 * Provides centralized logic for calculating student fee totals, including
 * tuition, hostel, transport, and scholarships.
 */

/**
 * Shared logic to reconstruct fee components consistently across functions.
 * Use this to ensure labels and gross amounts are identical everywhere.
 */
function buildFeeAllocationPayload($student, $fc, $hostel_cfg = null, $transport_cfg = null, $term_rec = null)
{
    $allocations = [];

    // 1. Define Academic Components from Config
    $components = [
        'school_fee' => ['amount' => floatval($fc['school_fee'] ?? 0), 'gst' => (isset($fc['school_fee_gst']) && $fc['school_fee_gst'] == 1)],
        'trust_facilities_fee' => ['amount' => floatval($fc['trust_facilities_fee'] ?? 0), 'gst' => (isset($fc['trust_fee_gst']) && $fc['trust_fee_gst'] == 1)],
        'tuition_fee_part1' => ['amount' => floatval($fc['tuition_fee_part1'] ?? 0), 'gst' => (isset($fc['tuition_fee_gst']) && $fc['tuition_fee_gst'] == 1)],
        'tuition_fee_part2' => ['amount' => floatval($fc['tuition_fee_part2'] ?? 0), 'gst' => (isset($fc['tuition_fee_gst']) && $fc['tuition_fee_gst'] == 1)]
    ];

    // Special case for Token Fee mapping
    if (floatval($fc['tuition_fee_part1'] ?? 0) == 0 && isset($fc['token_fee']) && $fc['token_fee'] > 0) {
        $components['tuition_fee_part1'] = ['amount' => floatval($fc['token_fee']), 'gst' => (isset($fc['token_fee_gst']) && $fc['token_fee_gst'] == 1)];
    }

    foreach ($components as $key => $comp) {
        if ($comp['amount'] > 0) {
            $gross = $comp['amount'];
            if ($comp['gst'])
                $gross *= 1.18;

            $label = function_exists('formatFeeKey') ? formatFeeKey($key) : ucwords(str_replace('_', ' ', $key));

            // Append school name ONLY for School Fee (Term 1 component)
            if ($key === 'school_fee' && !empty($student['school_name'])) {
                if (strpos($label, $student['school_name']) === false) {
                    $label .= " - " . $student['school_name'];
                }
            }

            $allocations[$key] = [
                'label' => $label,
                'gross_amount' => $gross,
                'category' => 'Academic'
            ];
        }
    }

    // 2. Handle Hostel/Transport (Live Simulation vs Term Snapshot)
    if ($term_rec) {
        // From existing term record snapshot
        $academic_sum = 0;
        foreach ($allocations as $a)
            $academic_sum += $a['gross_amount'];

        $total_allocated_record = floatval($term_rec['allocated_amount']);
        $diff = max(0, $total_allocated_record - $academic_sum);

        if ($diff > 0) {
            $is_hostel = (strtolower($student['hostel_required'] ?? '') === 'yes');
            $is_transport = (strtolower($student['transport_required'] ?? '') === 'yes');

            if ($is_hostel && $is_transport) {
                // Split proportionally or by expected values
                $h_total = 0;
                $h_security = 0;
                if ($hostel_cfg) {
                    $h_total = ($student['gender'] === 'Male') ? floatval($hostel_cfg['boys_hostel_fee'] ?? 0) : floatval($hostel_cfg['girls_hostel_fee'] ?? 0);
                    $h_security = floatval($hostel_cfg['security_deposit'] ?? 0);
                    if (isset($hostel_cfg['gst_applicable']) && $hostel_cfg['gst_applicable'] == 1) {
                        $h_total *= (1 + (floatval($hostel_cfg['gst_rate'] ?? 0) / 100));
                    }
                }

                if ($diff >= $h_total && $h_total > 0) {
                    // Split Hostel into Security and Fee
                    if ($h_security > 0) {
                        $allocations['hostel_security'] = ['label' => 'Hostel Fee (Security Deposit)', 'gross_amount' => $h_security, 'category' => 'Hostel'];
                    }
                    $allocations['hostel_fee'] = ['label' => 'Hostel Fee', 'gross_amount' => $h_total - $h_security, 'category' => 'Hostel'];
                    $diff -= $h_total;

                    if ($diff > 0) {
                        $allocations['transport_fee'] = ['label' => 'Transport Fee', 'gross_amount' => $diff, 'category' => 'Transport'];
                    }
                } else {
                    // Just split 50/50
                    $allocations['hostel_fee'] = ['label' => 'Hostel Fee', 'gross_amount' => $diff / 2, 'category' => 'Hostel'];
                    $allocations['transport_fee'] = ['label' => 'Transport Fee', 'gross_amount' => $diff / 2, 'category' => 'Transport'];
                }
            } elseif ($is_hostel) {
                $h_total = 0;
                $h_security = 0;
                if ($hostel_cfg) {
                    $h_total = ($student['gender'] === 'Male') ? floatval($hostel_cfg['boys_hostel_fee'] ?? 0) : floatval($hostel_cfg['girls_hostel_fee'] ?? 0);
                    $h_security = floatval($hostel_cfg['security_deposit'] ?? 0);
                }

                $cid = intval($student['course_id'] ?? 0);
                $h_net = ($diff >= $h_total && $h_security > 0) ? ($h_total - $h_security) : $diff;
                $h_sec_final = ($diff >= $h_total && $h_security > 0) ? $h_security : 0;

                if ($h_sec_final > 0) {
                    $allocations['hostel_security'] = ['label' => 'Hostel Fee (Security Deposit)', 'gross_amount' => $h_sec_final, 'category' => 'Hostel'];
                }

                $split_threshold = floatval($hostel_cfg['split_threshold'] ?? 0);

                if ($split_threshold > 0) {
                    $official_cap = $split_threshold;
                    $official_amt = min($h_net, $official_cap);
                    $cash_amt = $h_net - $official_amt;
                    if ($official_amt > 0) {
                        $allocations['hostel_fee'] = ['label' => 'Hostel Fee', 'gross_amount' => $official_amt, 'category' => 'Hostel'];
                    }
                    if ($cash_amt > 0) {
                        $allocations['hostel_cash_fee'] = ['label' => 'Hostel Cash Fee (No Receipt)', 'gross_amount' => $cash_amt, 'category' => 'Hostel'];
                    }
                } else {
                    if ($h_net > 0) {
                        $allocations['hostel_fee'] = ['label' => 'Hostel Fee', 'gross_amount' => $h_net, 'category' => 'Hostel'];
                    }
                }
            } elseif ($is_transport) {
                $allocations['transport_fee'] = ['label' => 'Transport Fee', 'gross_amount' => $diff, 'category' => 'Transport'];
            } else {
                $allocations['other_fee'] = ['label' => 'Other Fee', 'gross_amount' => $diff, 'category' => 'Other'];
            }
        }

        // Fallback: if transport_required=Yes but not captured in diff (transport not in original allocation),
        // add it directly from config so it's always visible in the ledger
        if (strtolower($student['transport_required'] ?? '') === 'yes' && $transport_cfg && !isset($allocations['transport_fee'])) {
            $monthly = floatval($transport_cfg['transport_fee'] ?? 0);
            $gst_rate = floatval($transport_cfg['gst_rate'] ?? 0);
            
            // Use student-specific months if set, otherwise fallback to config
            $months = (isset($student['transport_months']) && intval($student['transport_months']) > 0) 
                ? intval($student['transport_months']) 
                : intval($transport_cfg['annual_months'] ?? 12);
                
            $gross_transport = ($monthly * $months) * (1 + ($gst_rate / 100));
            if ($gross_transport > 0) {
                $allocations['transport_fee'] = ['label' => 'Transport Fee', 'gross_amount' => $gross_transport, 'category' => 'Transport'];
            }
        }
    } else {
        // Live Simulation logic
        if (strtolower($student['hostel_required'] ?? '') === 'yes' && $hostel_cfg) {
            $security = floatval($hostel_cfg['security_deposit'] ?? 0);
            $hostel_total = ($student['gender'] === 'Male') ? floatval($hostel_cfg['boys_hostel_fee'] ?? 0) : floatval($hostel_cfg['girls_hostel_fee'] ?? 0);

            if (isset($hostel_cfg['gst_applicable']) && $hostel_cfg['gst_applicable'] == 1) {
                $hostel_total *= (1 + (floatval($hostel_cfg['gst_rate'] ?? 0) / 100));
            }

            if ($security > 0)
                $allocations['hostel_security'] = ['label' => 'Hostel Fee (Security Deposit)', 'gross_amount' => $security, 'category' => 'Hostel'];

            $hostel_fee_net = $hostel_total - $security;
            if ($hostel_fee_net > 0) {
                $split_threshold = floatval($hostel_cfg['split_threshold'] ?? 0);
                
                if ($split_threshold > 0) {
                    $official_cap = $split_threshold;
                    $official_amt = min($hostel_fee_net, $official_cap);
                    $cash_amt = $hostel_fee_net - $official_amt;

                    if ($official_amt > 0) {
                        $allocations['hostel_fee'] = ['label' => 'Hostel Fee', 'gross_amount' => $official_amt, 'category' => 'Hostel'];
                    }
                    if ($cash_amt > 0) {
                        $allocations['hostel_cash_fee'] = ['label' => 'Hostel Cash Fee (No Receipt)', 'gross_amount' => $cash_amt, 'category' => 'Hostel'];
                    }
                } else {
                    $allocations['hostel_fee'] = ['label' => 'Hostel Fee', 'gross_amount' => $hostel_fee_net, 'category' => 'Hostel'];
                }
            }
        }

        if (strtolower($student['transport_required'] ?? '') === 'yes' && $transport_cfg) {
            $monthly = floatval($transport_cfg['transport_fee'] ?? 0);
            $gst_rate = floatval($transport_cfg['gst_rate'] ?? 0);
            
            // Use student-specific months if set, otherwise fallback to annual
            $months = (isset($student['transport_months']) && intval($student['transport_months']) > 0) 
                ? intval($student['transport_months']) 
                : intval($transport_cfg['annual_months'] ?? 12);
                
            $gross_transport = ($monthly * $months) * (1 + ($gst_rate / 100));

            if ($gross_transport > 0) {
                $allocations['transport_fee'] = ['label' => 'Transport Fee', 'gross_amount' => $gross_transport, 'category' => 'Transport'];
            }
        }
    }

    return $allocations;
}

if (!function_exists('getTransportConfig')) {
    function getTransportConfig($conn, $student)
    {
        $ay_id = $student['academic_year_id'] ?? null;
        if (!$ay_id) {
            $stmt_ay = $conn->query("SELECT id FROM tbl_academic_years WHERE is_active = 1 LIMIT 1");
            $ay = $stmt_ay->fetch(PDO::FETCH_ASSOC);
            $ay_id = $ay['id'] ?? null;
        }

        $transport_cfg = null;
        if ($ay_id) {
            // 1. Try course-specific first
            $stmt_tc = $conn->prepare("SELECT * FROM tbl_transport_fee_settings WHERE academic_year_id = ? AND course_id = ? AND is_active = 1 LIMIT 1");
            $stmt_tc->execute([$ay_id, $student['course_id']]);
            $transport_cfg = $stmt_tc->fetch(PDO::FETCH_ASSOC);

            // 2. Fallback to global setting for this AY
            if (!$transport_cfg) {
                $stmt_tc = $conn->prepare("SELECT * FROM tbl_transport_fee_settings WHERE academic_year_id = ? AND (course_id IS NULL OR course_id = 0) AND is_active = 1 LIMIT 1");
                $stmt_tc->execute([$ay_id]);
                $transport_cfg = $stmt_tc->fetch(PDO::FETCH_ASSOC);
            }
        }

        // Fallback: Most recent active settings
        if (!$transport_cfg) {
            $stmt_tc = $conn->prepare("SELECT * FROM tbl_transport_fee_settings WHERE is_active = 1 AND course_id = ? ORDER BY academic_year_id DESC LIMIT 1");
            $stmt_tc->execute([$student['course_id']]);
            $transport_cfg = $stmt_tc->fetch(PDO::FETCH_ASSOC);

            if (!$transport_cfg) {
                $transport_cfg = $conn->query("SELECT * FROM tbl_transport_fee_settings WHERE is_active = 1 AND (course_id IS NULL OR course_id = 0) ORDER BY academic_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            }
        }

        if ($transport_cfg) {
            $monthly_base = floatval($transport_cfg['transport_fee'] ?? 0);
            $gst_rate = floatval($transport_cfg['gst_rate'] ?? 0);
            $monthly_total = round($monthly_base * (1 + ($gst_rate / 100)));

            return [
                'timeline' => $transport_cfg['collection_timeline'] ?? 'Term-wise',
                'monthly_rate' => $monthly_total,
                'base_rate' => $monthly_base,
                'gst_rate' => $gst_rate,
                'settings' => $transport_cfg
            ];
        }

        return null;
    }
}

if (!function_exists('calculateStudentFeeSummary')) {
    /**
     * Calculates a complete financial summary for a student.
     * 
     * @param PDO $conn Database connection
     * @param int $student_id Student ID
     * @param bool $hide_without_gst If true, "Without GST" payments are subtracted from allocated amount and hidden from paid amount (Absorption)
     * @param int|null $strict_term_id If provided, strictly restricts results to this term ONLY (used for student/parent portals)
     * @return array Summary data
     */
    function calculateStudentFeeSummary($conn, $student_id, $hide_without_gst = false, $strict_term_id = null)
    {
        // 1. Fetch Student Registration & Enrollment Info
        $stmt = $conn->prepare("SELECT r.*, es.post_admission_discount_amount, es.current_term_id, es.enrollment_id,
                                       c.course_name, g.group_name, s.school_name
                                FROM tbl_gm_std_registration r
                                LEFT JOIN tbl_enrolled_students es ON r.id = es.registration_id AND es.is_active = 1
                                LEFT JOIN tbl_courses c ON r.course_id = c.id
                                LEFT JOIN tbl_group g ON r.group_id = g.id
                                LEFT JOIN tbl_schools s ON r.school_id = s.id
                                WHERE r.id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student)
            return [];

        // 2. Fetch Active Fee Configuration
        // Note: We use term-based logic if available, otherwise fallback to term 1
        $current_term_id = $strict_term_id ?: intval($student['current_term_id'] ?? 1);
        $term_sql = ($current_term_id == 2) ? "AND (fc.term = '2nd Term' OR fc.term = 'Semester 2')" : "AND (fc.term = '1st Term' OR fc.term = 'Semester 1')";

        // Progressive fallback fee config fetching
        // 1. Exact match: course + medium + group + school + term
        $sql_fc = "SELECT * FROM tbl_fee_config fc
                  WHERE course_id = ? AND medium_id = ? AND group_id = ? AND school_id = ? 
                  AND is_active = 1 $term_sql ORDER BY id DESC LIMIT 1";
        $stmt_fc = $conn->prepare($sql_fc);
        $stmt_fc->execute([$student['course_id'], $student['medium_id'], $student['group_id'], $student['school_id']]);
        $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);

        // 2. Drop term filter
        if (!$fc) {
            $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config fc
                      WHERE course_id = ? AND medium_id = ? AND group_id = ? AND school_id = ? 
                      AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt_fc->execute([$student['course_id'], $student['medium_id'], $student['group_id'], $student['school_id']]);
            $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);
        }

        // 3. Drop medium_id (some students have different medium than config)
        if (!$fc) {
            $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config fc
                      WHERE course_id = ? AND group_id = ? AND school_id = ? 
                      AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt_fc->execute([$student['course_id'], $student['group_id'], $student['school_id']]);
            $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);
        }

        // 4. Drop school_id too (course + group only)
        if (!$fc) {
            $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config fc
                      WHERE course_id = ? AND group_id = ? 
                      AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt_fc->execute([$student['course_id'], $student['group_id']]);
            $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);
        }

        // 5. Last resort: any config for this course
        if (!$fc) {
            $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config fc
                      WHERE course_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
            $stmt_fc->execute([$student['course_id']]);
            $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);
        }

        // 3. Fetch Hostel Settings
        $ay_id = $student['academic_year_id'] ?? null;
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
            $stmt_hc = $conn->query("SELECT * FROM tbl_hostel_fee_settings WHERE is_active = 1 ORDER BY academic_year_id DESC LIMIT 1");
            $hostel_cfg = $stmt_hc->fetch(PDO::FETCH_ASSOC);
        }

        // Fetch Transport Settings using helper
        $transport_data = getTransportConfig($conn, $student);
        $transport_cfg = $transport_data['settings'] ?? null;
        $transport_timeline = $transport_data['timeline'] ?? 'Term-wise';

        // 4. Calculate Allocated Components (Gross)
        // Apply course-based month override before passing to payload builder
        if ($transport_cfg) {
            $cid = intval($student['course_id']);
            $is_s2 = ($current_term_id == 2);
            $t1_m = intval($transport_cfg['term1_months'] ?? 7);
            $t2_m = intval($transport_cfg['term2_months'] ?? 6);
            
            // Use student-specific months if set, otherwise fallback to config
            $an_m = (isset($student['transport_months']) && intval($student['transport_months']) > 0) 
                ? intval($student['transport_months']) 
                : intval($transport_cfg['annual_months'] ?? 12);

            // Override months based on course if timeline is NOT Monthly
            if ($transport_timeline !== 'Monthly') {
                if (in_array($cid, [1, 2])) {
                    $transport_cfg['annual_months'] = $is_s2 ? $t2_m : $t1_m;
                } elseif (in_array($cid, [4, 5])) {
                    $transport_cfg['annual_months'] = $an_m;
                }
            }
        }
        $allocations = buildFeeAllocationPayload($student, $fc, $hostel_cfg, $transport_cfg);

        $total_allocated = 0;
        foreach ($allocations as $a) {
            $total_allocated += $a['gross_amount'];
        }

        // 5. Fetch Actual Payments (The Truth)
        $current_term = $strict_term_id ?: intval($student['current_term_id'] ?? 1);

        // Universal components that are paid globally (not necessarily bound to a specific semester's tuition structure)
        // Note: Removed 'token_fee' and 'tuition_fee_part1' from here to force term-wise attribution.
        $universal_components = "'hostel_fee', 'hostel_cash_fee', 'transport_fee', 'admission_fee', 'security_deposit', 'registration_fee'";

        $pay_query = "SELECT fee_component, SUM(amount) as paid_sum, MAX(receipt_no) as latest_receipt_no
                      FROM tbl_payments
                      WHERE student_id = ? AND status = 'paid' ";

        if ($strict_term_id) {
            // Strict term for academic fees, but universal components (hostel, transport) are always counted
            // across all terms to avoid showing them as pending when paid in a different term.
            $pay_query .= " AND (term_id = ? OR fee_component IN ($universal_components)) ";
        } else {
            // Regular view: Current term + universal components (legacy support)
            $pay_query .= " AND (term_id = ? OR fee_component IN ($universal_components)) ";
        }

        $pay_query .= " GROUP BY fee_component";

        $stmt_pay = $conn->prepare($pay_query);
        $stmt_pay->execute([$student_id, $current_term]);

        $paid_data = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);

        $paid_by_component = [];
        $without_gst_flags = [];
        $receipt_nos = [];
        $total_paid = 0;
        foreach ($paid_data as $p) {
            $comp = $p['fee_component'];
            if ($comp === 'hostel_security') {
                $comp = 'hostel_fee'; // Merge historical security payments into hostel fee
            }
            if (!isset($paid_by_component[$comp])) {
                $paid_by_component[$comp] = 0;
                $without_gst_flags[$comp] = 0;
                $receipt_nos[$comp] = null;
            }
            $paid_by_component[$comp] += floatval($p['paid_sum']);
            $total_paid += floatval($p['paid_sum']);
            if ($p['latest_receipt_no'] === '0') {
                $without_gst_flags[$comp] = 1;
            }
            if (!empty($p['latest_receipt_no'])) {
                $receipt_nos[$comp] = $p['latest_receipt_no'];
            }
        }

        // 6. Handle Hostel Payment Distribution (hostel_fee covers both security and fee)
        if (isset($paid_by_component['hostel_fee']) && isset($allocations['hostel_security'])) {
            $total_h_paid = $paid_by_component['hostel_fee'];
            $sec_gross = $allocations['hostel_security']['gross_amount'];
            
            $paid_by_component['hostel_security'] = min($total_h_paid, $sec_gross);
            $paid_by_component['hostel_fee'] = max(0, $total_h_paid - $paid_by_component['hostel_security']);
            
            // Also sync receipt numbers if needed (usually hostel_fee has them)
            if (!isset($receipt_nos['hostel_security'])) {
                $receipt_nos['hostel_security'] = $receipt_nos['hostel_fee'] ?? null;
            }
        }

        // 7. Calculate Scholarships & Discounts
        $scholarship = floatval($student['scholarship_amount'] ?? 0);
        $additional = floatval($student['additional_scholarship_amount'] ?? 0);
        $total_post_admission_discount = floatval($student['post_admission_discount_amount'] ?? 0);

        // --- SMART WAIVER GRANULAR FETCH LOGIC ---
        global $conn;
        $smart_allocations = [];
        $smart_discount_total = 0;

        if (!empty($student['enrollment_id'])) {
            $stmt_discounts = $conn->prepare("
                SELECT discount_amount, remarks 
                FROM tbl_post_admission_discounts 
                WHERE enrollment_id = ? AND status = 'approved'
            ");
            $stmt_discounts->execute([$student['enrollment_id']]);
            $approved_discounts = $stmt_discounts->fetchAll(PDO::FETCH_ASSOC);

            foreach ($approved_discounts as $d) {
                $remarks = $d['remarks'] ?? '';
                $amount = floatval($d['discount_amount']);

                if (strpos($remarks, 'Smart Waiver Breakdown:') !== false || strpos($remarks, 'Smart Waiver Breakdown (Modifications):') !== false) {
                    // Rebuild label map dynamically based on config if available
                    $s_label = ($fc['school_fee_label'] ?? 'School Fee') ?: 'School Fee';
                    $t_label = ($fc['trust_fee_label'] ?? 'Trust Fee') ?: 'Trust Fee';
                    $tu_label = $fc['tuition_fee_label'] ?? 'Tuition';

                    $label_map = [
                        $s_label => 'school_fee',
                        $t_label => 'trust_facilities_fee',
                        ($tu_label ? $tu_label . ' Part 1' : 'Tuition Part 1') => 'tuition_fee_part1',
                        ($tu_label ? $tu_label . ' Part 2' : 'Tuition Part 2') => 'tuition_fee_part2'
                    ];

                    // Handle Token Fee alias if Tuition Part 1 is 0
                    if (isset($fc['tuition_fee_part1']) && floatval($fc['tuition_fee_part1']) == 0 && isset($fc['token_fee']) && $fc['token_fee'] > 0) {
                        $label_map['Token Fee'] = 'tuition_fee_part1';
                    }

                    $lines = explode("\n", $remarks);
                    $breakdown_started = false;

                    foreach ($lines as $line) {
                        if (strpos($line, 'Smart Waiver Breakdown:') !== false || strpos($line, 'Smart Waiver Breakdown (Modifications):') !== false) {
                            $breakdown_started = true;
                            continue;
                        }

                        if ($breakdown_started && trim($line) !== '') {
                            foreach ($label_map as $label => $db_key) {
                                // Match: '- School Fee: 500' or '- Released ₹500 from School Fee'
                                if (strpos($line, '- ' . $label . ':') !== false) {
                                    if (!isset($smart_allocations[$db_key])) {
                                        $smart_allocations[$db_key] = 0;
                                    }
                                    $smart_allocations[$db_key] += $amount; // Original Apply 
                                    $smart_discount_total += $amount;
                                    break 2;
                                } elseif (strpos($line, 'Released') !== false && strpos($line, $label) !== false) {
                                    // Match: '- Released ₹500 from School Fee'
                                    if (!isset($smart_allocations[$db_key])) {
                                        $smart_allocations[$db_key] = 0;
                                    }
                                    // For release records, 'discount_amount' (alias of discount_value) is already negative in the save logic?
                                    // Let's check release-discount-save.php line 77: $discount_diff = $new_total_discount - $old_total_discount;
                                    // If we RELEASE 1000, new < old, so diff is NEGATIVE.
                                    // So we SHOULD ADD $amount (which is negative) to reduce the total.
                                    $smart_allocations[$db_key] += $amount;
                                    $smart_discount_total += $amount;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        }
        // -------------------------------------------

        // Calculate generic pool (total discounts minus explicitly allocated smart ones)
        // This ensures the sum matches what was recorded overall, even if smart matching missed something
        $generic_waiver_pool = max(0, ($scholarship + $additional + $total_post_admission_discount) - $smart_discount_total);
        $total_waiver = $scholarship + $additional + $total_post_admission_discount;

        // 7. Assemble Final Summary with Distributive Scholarship Logic
        $detailed_allocations = [];

        // Application sequence for generic pool (Tuition Part 2 favored first per user request)
        $application_order = ['tuition_fee_part2', 'tuition_fee_part1', 'trust_facilities_fee', 'school_fee'];

        // Pass 1: Setup components, applying Specific Smart Allocations EXACTLY as requested
        foreach ($allocations as $key => $alloc) {
            $paid = $paid_by_component[$key] ?? 0;
            $waived = 0;

            if (isset($smart_allocations[$key])) {
                $max_waivable = max(0, $alloc['gross_amount']);
                $waived = min($smart_allocations[$key], $max_waivable);
            }

            $detailed_allocations[$key] = $alloc;
            $detailed_allocations[$key]['paid_amount'] = $paid;

            $detailed_allocations[$key]['waived_amount'] = $waived;
            $detailed_allocations[$key]['is_without_gst'] = ($without_gst_flags[$key] ?? 0);
            $detailed_allocations[$key]['receipt_no'] = ($receipt_nos[$key] ?? null);
        }

        // Pass 2: Apply remaining Generic Waiver Pool according to priority order
        foreach ($application_order as $order_key) {
            if ($generic_waiver_pool > 0 && isset($detailed_allocations[$order_key])) {
                $alloc = $detailed_allocations[$order_key];
                $already_waived = $alloc['waived_amount'];

                $max_waivable = max(0, $alloc['gross_amount'] - $already_waived - $alloc['paid_amount']);
                if ($max_waivable > 0) {
                    $apply_generic = min($generic_waiver_pool, $max_waivable);
                    $detailed_allocations[$order_key]['waived_amount'] += $apply_generic;
                    $generic_waiver_pool -= $apply_generic;
                }
            }
        }

        // Pass 3: Calculate final pendings, apply overpayments / historical data
        $remaining_waiver = $generic_waiver_pool; // For the generic academic spill-over

        foreach ($detailed_allocations as $key => &$d_alloc) {
            $paid = $d_alloc['paid_amount'];
            $waived = $d_alloc['waived_amount'];
            $alloc_gross = $d_alloc['gross_amount'];

            // Handle overpayments in historic hostel fee key names
            if ($key === 'hostel_fee' && $paid > $alloc_gross) {
                $difference = $paid - $alloc_gross;
                $d_alloc['base_amount'] = $paid;
                $d_alloc['gross_amount'] = $paid;
                $alloc_gross = $paid;
                $total_allocated += $difference;
            }

            $pending = max(0, round($alloc_gross - $paid - $waived));
            $d_alloc['pending_amount'] = $pending;
            $d_alloc['payable_amount'] = max(0, $alloc_gross - $waived);
        }
        unset($d_alloc); // Break reference

        // If generic scholarship STILL remains, apply randomly to remaining academic balances
        if ($remaining_waiver > 0) {
            foreach ($detailed_allocations as $key => &$d_alloc) {
                if ($remaining_waiver <= 0)
                    break;

                if ($d_alloc['category'] === 'Academic' && $d_alloc['pending_amount'] > 0) {
                    $apply = min($remaining_waiver, $d_alloc['pending_amount']);
                    $d_alloc['waived_amount'] += $apply;
                    $d_alloc['pending_amount'] -= $apply;
                    $d_alloc['payable_amount'] = max(0, $d_alloc['gross_amount'] - $d_alloc['waived_amount']);
                    $remaining_waiver -= $apply;
                }
            }
            unset($d_alloc); // Break reference
        }

        // Also add payments that don't have a matching allocation (miscellaneous/orphans)
        // This handles cases where a student paid for something not explicitly 'required' in registration
        foreach ($paid_by_component as $key => $paid) {
            if (!isset($detailed_allocations[$key])) {
                $category = 'Other';
                $academic_components = ['admission_fee', 'security_deposit', 'token_fee', 'tuition_fee_part1', 'registration_fee', 'school_fee', 'trust_facilities_fee', 'tuition_fee_part2'];

                if ($key === 'hostel_fee' || $key === 'hostel_security') {
                    $category = 'Hostel';
                } elseif ($key === 'transport_fee') {
                    $category = 'Transport';
                } elseif (in_array($key, $academic_components)) {
                    $category = 'Academic';
                }

                $detailed_allocations[$key] = [
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'base_amount' => 0,
                    'gross_amount' => 0, // Orphan payments have no auto-allocation
                    'paid_amount' => $paid,
                    'waived_amount' => 0,
                    'payable_amount' => 0,
                    'pending_amount' => 0,
                    'is_without_gst' => ($without_gst_flags[$key] ?? 0),
                    'receipt_no' => ($receipt_nos[$key] ?? null),
                    'category' => $category
                ];

                // Do NOT add to total_allocated as these are orphan payments without a required allocation.
                // $total_allocated += $paid;
            }
        }

        // 6. Calculate Final Totals
        // REFACTORED: Sum totals directly from allocation rows for absolute accuracy
        $total_allocated = 0;
        $total_paid = 0;
        $total_waiver = 0;
        $total_payable = 0;
        $total_pending = 0;

        foreach ($detailed_allocations as $key => &$alloc) {

            // Sum up final values for the summary
            $total_allocated += $alloc['gross_amount'];
            $total_paid += $alloc['paid_amount'];
            $total_waiver += $alloc['waived_amount'];
            $total_payable += $alloc['payable_amount'];
            $total_pending += $alloc['pending_amount'];
        }
        unset($alloc);

        // 6.5. RESTRICTED VIEW: For student portal, only show Security Deposit for hostels
        if ($hide_without_gst) {
            $components_to_hide = ['hostel_fee', 'hostel_cash_fee'];
            foreach ($components_to_hide as $hide_key) {
                if (isset($detailed_allocations[$hide_key])) {
                    // Subtract from totals before unsetting
                    $total_allocated -= $detailed_allocations[$hide_key]['gross_amount'];
                    $total_paid -= $detailed_allocations[$hide_key]['paid_amount'];
                    $total_waiver -= $detailed_allocations[$hide_key]['waived_amount'];
                    $total_payable -= $detailed_allocations[$hide_key]['payable_amount'];
                    $total_pending -= $detailed_allocations[$hide_key]['pending_amount'];
                    
                    unset($detailed_allocations[$hide_key]);
                }
            }
        }

        // Note: total_allocated and total_paid already reflect the sum of the processed rows.
        // We no longer need manual global subtractions here as the loop above handles it.

        return [
            'student_id' => $student_id,
            'student_name' => trim(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? '')),
            'course' => $student['course_name'],
            'group' => $student['group_name'],
            'current_term_id' => $student['current_term_id'],
            'total_allocated' => $total_allocated,
            'total_paid' => $total_paid,
            'total_waiver' => $total_waiver,
            'scholarship' => $scholarship,
            'additional_scholarship' => $additional,
            'post_admission_discount' => $total_post_admission_discount,
            'total_discount' => $total_waiver, // Alias for backward compatibility
            'total_payable' => $total_payable,
            'total_pending' => $total_pending,
            'transport_config' => $transport_data,
            'allocations' => $detailed_allocations,
            'detailed_allocations' => $detailed_allocations, // Alias for backward compatibility
            'status' => ($total_pending <= 0) ? 'Fully Paid' : (($total_paid > 0) ? 'Partially Paid' : 'Pending')
        ];

    }
}

if (!function_exists('calculateFullStudentLedger')) {
    /**
     * Calculates the complete historical financial ledger for a student,
     * grouping allocations and payments by their respective terms.
     */
    function calculateFullStudentLedger($conn, $student_id)
    {
        // 1. Fetch Student Info
        $stmt = $conn->prepare("SELECT r.*, es.enrollment_id, c.course_name, g.group_name 
                                FROM tbl_gm_std_registration r
                                LEFT JOIN tbl_enrolled_students es ON r.id = es.registration_id AND es.is_active = 1
                                LEFT JOIN tbl_courses c ON r.course_id = c.id
                                LEFT JOIN tbl_group g ON r.group_id = g.id
                                WHERE r.id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student)
            return [];

        // 2. Fetch All Unique Term/Config Pairs from Allocations
        // This is the source of truth for what has been billed to the student.
        $stmt_keys = $conn->prepare("SELECT DISTINCT term_id, fee_config_id, academic_year 
                                    FROM tbl_student_fee_allocation 
                                    WHERE student_id = ? 
                                    ORDER BY term_id ASC");
        $stmt_keys->execute([$student_id]);
        $term_keys = $stmt_keys->fetchAll(PDO::FETCH_ASSOC);

        // If no allocations found (e.g. newly registered), provide a default view for Term 1 if possible
        if (empty($term_keys)) {

            $summary = calculateStudentFeeSummary($conn, $student_id);
            $stmt_ay = $conn->query("SELECT year_name FROM tbl_academic_years WHERE is_active = 1 LIMIT 1");
            $active_ay = $stmt_ay ? ($stmt_ay->fetchColumn() ?: '') : '';
            return [
                'student' => $student,
                'ledger' => [
                    [
                        'term_name' => 'Semester 1',
                        'term_id' => 1,
                        'course_name' => $student['course_name'],
                        'academic_year' => $active_ay,
                        'summary' => $summary
                    ]
                ]
            ];
        }

        $ledger = [];
        foreach ($term_keys as $key) {
            $term_id = $key['term_id'];
            $config_id = $key['fee_config_id'];

            // Get Term Name
            $stmt_t = $conn->prepare("SELECT term_name FROM tbl_term WHERE id = ?");
            $stmt_t->execute([$term_id]);
            $term_row = $stmt_t->fetch(PDO::FETCH_ASSOC);
            $term_name = $term_row['term_name'] ?? "Term $term_id";

            // Get Config Details (to see if course changed)
            $stmt_c = $conn->prepare("SELECT c.course_name FROM tbl_fee_config fc JOIN tbl_courses c ON fc.course_id = c.id WHERE fc.id = ?");
            $stmt_c->execute([$config_id]);
            $config_row = $stmt_c->fetch(PDO::FETCH_ASSOC);
            $course_name = $config_row['course_name'] ?? $student['course_name'];

            // Calculate summary for this specific term
            $summary = calculateTermSummary($conn, $student_id, $term_id, $config_id);

            $ledger[] = [
                'term_id' => $term_id,
                'term_name' => $term_name,
                'course_name' => $course_name,
                'academic_year' => $key['academic_year'],
                'summary' => $summary
            ];
        }

        return [
            'student' => $student,
            'ledger' => $ledger
        ];
    }
}


if (!function_exists('calculateTermSummary')) {
    /**
     * Calculates a summary for a specific student term based on allocations.
     */
    function calculateTermSummary($conn, $student_id, $term_id, $fee_config_id)
    {

        // 1. Fetch Student Details (Needed for hostel/transport and school name)
        $stmt_s = $conn->prepare("SELECT r.*, s.school_name 
                                 FROM tbl_gm_std_registration r 
                                 LEFT JOIN tbl_schools s ON r.school_id = s.id 
                                 WHERE r.id = ?");
        $stmt_s->execute([$student_id]);
        $student = $stmt_s->fetch(PDO::FETCH_ASSOC);

        if (!$student)
            return [];

        // 2. Load Config to get labels and GST settings
        $stmt_fc = $conn->prepare("SELECT * FROM tbl_fee_config WHERE id = ?");
        $stmt_fc->execute([$fee_config_id]);
        $fc = $stmt_fc->fetch(PDO::FETCH_ASSOC);

        if (!$fc)
            return [];

        // 3. Fetch Hostel/Transport Settings (Needed for component separation in buildFeeAllocationPayload)
        $ay_id = $student['academic_year_id'] ?? null;
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

        if (!$hostel_cfg) {
            $hostel_cfg = $conn->query("SELECT * FROM tbl_hostel_fee_settings WHERE is_active = 1 ORDER BY academic_year_id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        }

        // Fetch Transport Settings using helper
        $transport_data = getTransportConfig($conn, $student);
        $transport_cfg = $transport_data['settings'] ?? null;
        $transport_timeline = $transport_data['timeline'] ?? 'Term-wise';

        // 3. Process Payments for this term
        // Logic: tuition-like components are strictly term-bound. 
        // Universal components (hostel, transport) are included in the summary of the term they were allocated in.
        $universal_components = "'hostel_fee', 'hostel_cash_fee', 'transport_fee', 'admission_fee', 'security_deposit', 'registration_fee'";

        $stmt_pay = $conn->prepare("SELECT fee_component, SUM(amount) as paid_sum, MAX(receipt_no) as latest_receipt_no
                                   FROM tbl_payments
                                   WHERE student_id = ? AND status = 'paid' 
                                     AND (term_id = ? OR (term_id IS NULL AND fee_component IN ($universal_components)))
                                   GROUP BY fee_component");
        $stmt_pay->execute([$student_id, $term_id]);
        $paid_data = $stmt_pay->fetchAll(PDO::FETCH_ASSOC);

        $paid_by_component = [];
        $receipt_nos = [];
        foreach ($paid_data as $p) {
            $comp = $p['fee_component'];
            if ($comp === 'hostel_security')
                $comp = 'hostel_fee';
            $paid_by_component[$comp] = floatval($p['paid_sum']);
            $receipt_nos[$comp] = $p['latest_receipt_no'];
        }

        // Handle Hostel Payment Distribution (hostel_fee covers both security and fee)
        // We need allocations first to know the gross amount

        // 4. Load Term Allocations Record (to get scholarship totals)
        $stmt_term_alloc = $conn->prepare("SELECT * FROM tbl_student_fee_allocation WHERE student_id = ? AND term_id = ? AND fee_config_id = ?");
        $stmt_term_alloc->execute([$student_id, $term_id, $fee_config_id]);
        $term_rec = $stmt_term_alloc->fetch(PDO::FETCH_ASSOC);

        if (!$term_rec)
            return []; // Should not happen if called correctly

        // 5. Reconstruct Components (Using shared logic)
        // Apply course-based month override before passing to payload builder
        if ($transport_cfg) {
            $cid = intval($student['course_id']);
            $is_s2 = ($term_id == 2);
            $t1_m = intval($transport_cfg['term1_months'] ?? 7);
            $t2_m = intval($transport_cfg['term2_months'] ?? 6);
            $an_m = intval($transport_cfg['annual_months'] ?? 12);

            // Override months based on course if timeline is NOT Monthly
            if ($transport_timeline !== 'Monthly') {
                if (in_array($cid, [1, 2])) {
                    $transport_cfg['annual_months'] = $is_s2 ? $t2_m : $t1_m;
                } elseif (in_array($cid, [4, 5])) {
                    $transport_cfg['annual_months'] = $an_m;
                }
            }
        }
        $allocations = buildFeeAllocationPayload($student, $fc, $hostel_cfg, $transport_cfg, $term_rec);

        if (isset($paid_by_component['hostel_fee']) && isset($allocations['hostel_security'])) {
            $total_h_paid = $paid_by_component['hostel_fee'];
            $sec_gross = $allocations['hostel_security']['gross_amount'];
            
            $paid_by_component['hostel_security'] = min($total_h_paid, $sec_gross);
            $paid_by_component['hostel_fee'] = max(0, $total_h_paid - $paid_by_component['hostel_security']);
            
            if (!isset($receipt_nos['hostel_security'])) {
                $receipt_nos['hostel_security'] = $receipt_nos['hostel_fee'] ?? null;
            }
        }

        // 6. Distribute Scholarships & Payments
        $sch = floatval($term_rec['scholarship_amount'] ?? 0);
        $add = floatval($term_rec['additional_scholarship'] ?? 0);
        $disc = floatval($term_rec['post_admission_discount'] ?? 0);

        // Fallback to student record if local allocation columns are zero
        if ($sch == 0 && $add == 0 && $disc == 0) {
            $stmt_e = $conn->prepare("SELECT r.scholarship_amount, r.additional_scholarship_amount, e.post_admission_discount_amount 
                                     FROM tbl_enrolled_students e
                                     INNER JOIN tbl_gm_std_registration r ON e.registration_id = r.id
                                     WHERE e.registration_id = ? AND e.is_active = 1 AND e.current_term_id = ?");
            $stmt_e->execute([$student_id, $term_id]);
            $enrolled = $stmt_e->fetch(PDO::FETCH_ASSOC);
            if ($enrolled) {
                $sch = floatval($enrolled['scholarship_amount'] ?? 0);
                $add = floatval($enrolled['additional_scholarship_amount'] ?? 0);
                $disc = floatval($enrolled['post_admission_discount_amount'] ?? 0);
            }
        }

        $total_waiver = $sch + $add + $disc;
        $remaining_waiver = $total_waiver;

        $application_order = ['tuition_fee_part2', 'tuition_fee_part1', 'trust_facilities_fee', 'school_fee'];

        foreach ($allocations as $key => &$alloc) {
            $alloc['paid_amount'] = $paid_by_component[$key] ?? 0;
            $alloc['waived_amount'] = 0; // Initialize
            $alloc['receipt_no'] = $receipt_nos[$key] ?? null;
        }
        unset($alloc);

        // Apply waiver priority
        foreach ($application_order as $order_key) {
            if ($remaining_waiver > 0 && isset($allocations[$order_key])) {
                $max_waivable = $allocations[$order_key]['gross_amount'];
                $apply = min($remaining_waiver, $max_waivable);
                $allocations[$order_key]['waived_amount'] = $apply;
                $remaining_waiver -= $apply;
            }
        }

        // Final calculations
        $processed_allocations = [];
        $total_allocated = 0;
        $total_paid = 0;
        $total_actual_waiver = 0;
        $total_pending = 0;

        foreach ($allocations as $key => $alloc) {
            $alloc['payable_amount'] = max(0, $alloc['gross_amount'] - $alloc['waived_amount']);
            $alloc['pending_amount'] = max(0, $alloc['payable_amount'] - $alloc['paid_amount']);
            $alloc['status'] = ($alloc['pending_amount'] <= 0) ? 'paid' : ($alloc['paid_amount'] > 0 ? 'partial' : 'pending');

            $total_allocated += $alloc['gross_amount'];
            $total_paid += $alloc['paid_amount'];
            $total_actual_waiver += $alloc['waived_amount'];
            $total_pending += $alloc['pending_amount'];

            $processed_allocations[] = $alloc;
        }

        return [
            'total_allocated' => $total_allocated,
            'total_paid' => $total_paid,
            'total_waiver' => $total_actual_waiver,
            'scholarship' => $sch,
            'additional_scholarship' => $add,
            'post_admission_discount' => $disc,
            'total_pending' => $total_pending,
            'allocations' => $processed_allocations,
            'status' => ($total_pending <= 0) ? 'Fully Paid' : (($total_paid > 0) ? 'Partially Paid' : 'Pending')
        ];

    }
}

