<?php
ob_start();
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

try {
    // Check if user is Accountant, Super Admin, Principal, Counsellor OR Student
    $is_student = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
    $is_authorized = !$is_student && (hasRole(ROLE_ACCOUNTANT) || hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_COUNSELLOR));

    if (!$is_authorized && !$is_student) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }

    // Get receipt ID, receipt number, or student ID
    $receipt_id = $_REQUEST['id'] ?? null;
    $receipt_ids = $_REQUEST['ids'] ?? null;
    $receipt_no = $_REQUEST['receipt_no'] ?? null;
    $fetch_student_id = $_REQUEST['student_id'] ?? null;

    if (!$receipt_id && !$receipt_ids && !$receipt_no && !$fetch_student_id) {
        set_flash_message('error', "Receipt ID, number, or student ID is required!");
        header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : 'receipts.php'));
        exit;
    }

    // Fetch receipt details
    try {
        // If student accessing SPECIFICALLY by student_id ONLY (fetch all token receipts)
        if ($is_student && $fetch_student_id && !$receipt_no) {
            $student_id = $_SESSION['student_id'];

            // Verify the student_id matches the session
            if ($fetch_student_id != $student_id) {
                set_flash_message('error', "Unauthorized access!");
                header('Location: ../student-portal/my-fees.php');
                exit;
            }

            $op = new Operation();
            $receipts = $op->selectWithJoin(
                'tbl_payments p',
                [
                    'p.receipt_no',
                    'p.amount',
                    'p.payment_date as issued_date',
                    'p.payment_mode',
                    'p.transaction_id',
                    'p.payment_id',
                    'p.student_id',
                    'p.payment_type',
                    'p.fee_component',
                    's.surname',
                    's.student_name',
                    's.fathers_name',
                    's.mob',
                    's.aadhaar',
                    's.schoolname',
                    's.standard',
                    's.course_id',
                    's.board_id',
                    's.medium_id',
                    's.group_id',
                    'b.board_name',
                    'm.medium_name',
                    'g.group_name',
                    'e.roll_no',
                    'ay.year_name as academic_year'
                ],
                [
                    ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                    ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id']
                ],
                ['p.student_id' => $student_id, 'p.payment_type' => 'token_fee'],
                ""
            );

            // Sort receipts in PHP to bypass Operation class ORDER BY restriction
            if (!empty($receipts)) {
                usort($receipts, function($a, $b) {
                    $order = [
                        'school_fee' => 1,
                        'trust_facilities_fee' => 2,
                        'tuition_fee_part1' => 3
                    ];
                    $valA = $order[$a['fee_component']] ?? 4;
                    $valB = $order[$b['fee_component']] ?? 4;
                    return $valA <=> $valB;
                });
            }

            // Format each receipt
            foreach ($receipts as &$r) {
                $r['payment_for'] = ucfirst(str_replace('_', ' ', $r['payment_type'])) . ' Payment';
                $r['course_name'] = null;
                // academic_year is now fetched from database via tbl_academic_years
            }
            unset($r); // Break the reference to avoid bugs
        }
        // If student accessing by receipt_no from tbl_payments
        elseif ($is_student && $receipt_no) {
            $student_id = $_SESSION['student_id'];
            $op = new Operation();
            $conditions = ['p.receipt_no' => $receipt_no, 'p.student_id' => $student_id];

            // Add fee_component filter if provided (fix for duplicate receipt numbers)
            if (isset($_REQUEST['fee_component']) && !empty($_REQUEST['fee_component'])) {
                $conditions['p.fee_component'] = $_REQUEST['fee_component'];
            }

            $receipt = $op->readWithJoin(
                'tbl_payments p',
                [
                    'p.receipt_no',
                    'p.amount',
                    'p.payment_date as issued_date',
                    'p.payment_mode',
                    'p.transaction_id',
                    'p.payment_id',
                    'p.student_id',
                    'p.payment_type',
                    'p.fee_component',
                    's.surname',
                    's.student_name',
                    's.fathers_name',
                    's.mob',
                    's.aadhaar',
                    's.schoolname',
                    's.standard',
                    's.course_id',
                    's.board_id',
                    's.medium_id',
                    's.group_id',
                    'b.board_name',
                    'm.medium_name',
                    'g.group_name',
                    'e.roll_no',
                    'ay.year_name as academic_year'
                ],
                [
                    ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                    ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                    ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id']
                ],
                $conditions
            );

            // If not found and it's a hostel component, try the alternative
            if (!$receipt && isset($_REQUEST['fee_component'])) {
                $requested_comp = $_REQUEST['fee_component'];
                if ($requested_comp === 'hostel_fee' || $requested_comp === 'hostel_security') {
                    $conditions['p.fee_component'] = ($requested_comp === 'hostel_fee') ? 'hostel_security' : 'hostel_fee';
                    $receipt = $op->readWithJoin(
                        'tbl_payments p',
                        [
                            'p.receipt_no',
                            'p.amount',
                            'p.payment_date as issued_date',
                            'p.payment_mode',
                            'p.transaction_id',
                            'p.payment_id',
                            'p.student_id',
                            'p.payment_type',
                            'p.fee_component',
                            's.surname',
                            's.student_name',
                            's.fathers_name',
                            's.mob',
                            's.aadhaar',
                            's.schoolname',
                            's.standard',
                            's.course_id',
                            's.board_id',
                            's.medium_id',
                            's.group_id',
                            'b.board_name',
                            'm.medium_name',
                            'g.group_name',
                            'e.roll_no',
                            'ay.year_name as academic_year'
                        ],
                        [
                            ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                            ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id']
                        ],
                        $conditions
                    );
                }
            }

            if ($receipt) {
                // Format payment_for for display
                $receipt['payment_for'] = ucfirst(str_replace('_', ' ', $receipt['payment_type'] ?? '')) . ' Payment';
                $receipt['course_name'] = null;
                $receipt['academic_year'] = $receipt['academic_year'] ?? null;

                // If this is a token_fee payment, fetch all 3 receipts
                if (($receipt['payment_type'] ?? '') == 'token_fee') {
                    $receipts = $op->selectWithJoin(
                        'tbl_payments p',
                        [
                            'p.receipt_no',
                            'p.amount',
                            'p.payment_date as issued_date',
                            'p.payment_mode',
                            'p.transaction_id',
                            'p.payment_id',
                            'p.student_id',
                            'p.payment_type',
                            'p.fee_component',
                            's.surname',
                            's.student_name',
                            's.fathers_name',
                            's.mob',
                            's.aadhaar',
                            's.schoolname',
                            's.standard',
                            's.course_id',
                            's.board_id',
                            'p.student_id as student_id_raw',
                            's.medium_id',
                            's.group_id',
                            'b.board_name',
                            'm.medium_name',
                            'g.group_name',
                            'e.roll_no',
                            'ay.year_name as academic_year'
                        ],
                        [
                            ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                            ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                            ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id']
                        ],
                        ['p.student_id' => $receipt['student_id'], 'p.payment_type' => 'token_fee'],
                        ""
                    );

                    // Sort receipts in PHP to bypass Operation class ORDER BY restriction
                    if (!empty($receipts)) {
                        usort($receipts, function($a, $b) {
                            $order = [
                                'school_fee' => 1,
                                'trust_facilities_fee' => 2,
                                'tuition_fee_part1' => 3
                            ];
                            $valA = $order[$a['fee_component']] ?? 4;
                            $valB = $order[$b['fee_component']] ?? 4;
                            return $valA <=> $valB;
                        });
                    }

                    // Format each receipt
                    foreach ($receipts as &$r) {
                        $r['payment_for'] = ucfirst(str_replace('_', ' ', $r['payment_type'])) . ' Payment';
                        $r['course_name'] = null;
                        // academic_year is now fetched from database via tbl_academic_years
                    }
                    unset($r); // Break the reference to avoid bugs
                } else {
                    $receipts = [$receipt];
                }
            }
        } else {
            // Accountant accessing by receipt_no OR id from tbl_payments
            $op = new Operation();

            $conditions = [];

            // Priority 1: Fetch by Multiple Payment IDs
            if ($receipt_ids) {
                $ids_array = explode(',', $receipt_ids);
                $ids_array = array_filter(array_map('intval', $ids_array));
                if (!empty($ids_array)) {
                    $placeholders = implode(',', array_fill(0, count($ids_array), '?'));
                    $stmt = $conn->prepare("
                        SELECT p.*, p.payment_date as issued_date,
                               s.surname, s.student_name, s.fathers_name, s.mob, s.aadhaar, s.schoolname, s.standard, s.course_id, s.board_id, s.medium_id, s.group_id,
                               b.board_name, m.medium_name, g.group_name, e.roll_no, ay.year_name as academic_year, u.name as issued_by_name
                        FROM tbl_payments p
                        INNER JOIN tbl_gm_std_registration s ON p.student_id = s.id
                        LEFT JOIN tbl_boards b ON s.board_id = b.id
                        LEFT JOIN tbl_medium m ON s.medium_id = m.id
                        LEFT JOIN tbl_group g ON s.group_id = g.id
                        LEFT JOIN tbl_enrolled_students e ON s.id = e.registration_id
                        LEFT JOIN tbl_academic_years ay ON s.academic_year_id = ay.id
                        LEFT JOIN tbl_users u ON p.created_by = u.id
                        WHERE p.id IN ($placeholders)
                        ORDER BY FIELD(p.id, $placeholders)
                    ");
                    $stmt->execute(array_merge($ids_array, $ids_array));
                    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Format payment details for each receipt
                    foreach ($receipts as &$r) {
                        $r['payment_for'] = ucfirst(str_replace('_', ' ', $r['payment_type'] ?? '')) . ' Payment';
                        $r['course_name'] = null;
                    }
                    unset($r);

                    // We already have our $receipts array, so we can skip the single record read below
                    $receipt = null;
                }
            }
            // Priority 2: Fetch by Unique Payment ID (Most Reliable)
            elseif ($receipt_id) {
                $conditions['p.id'] = $receipt_id;
            }
            // Priority 2: Fetch by Receipt No + Student ID (Strict)
            elseif ($receipt_no && $fetch_student_id) {
                $conditions['p.receipt_no'] = $receipt_no;
                $conditions['p.student_id'] = $fetch_student_id;
            }
            // Priority 3: Fetch by Receipt No (Legacy/Ambiguous - Fallback)
            elseif ($receipt_no) {
                $conditions['p.receipt_no'] = $receipt_no;
            }

            if (!empty($conditions)) {
                // Add fee_component filter if provided
                if (isset($_REQUEST['fee_component']) && !empty($_REQUEST['fee_component'])) {
                    $conditions['p.fee_component'] = $_REQUEST['fee_component'];
                }
                $receipt = $op->readWithJoin(
                    'tbl_payments p',
                    [
                        'p.*',
                        'p.payment_date as issued_date',
                        's.surname',
                        's.student_name',
                        's.fathers_name',
                        's.mob',
                        's.aadhaar',
                        's.schoolname',
                        's.standard',
                        's.course_id',
                        's.board_id',
                        's.medium_id',
                        's.group_id',
                        'b.board_name',
                        'm.medium_name',
                        'g.group_name',
                        'e.roll_no',
                        'ay.year_name as academic_year',
                        'u.name as issued_by_name'
                    ],
                    [
                        ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                        ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                        ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                        ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                        ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                        ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
                        ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'p.created_by = u.id']
                    ],
                    $conditions
                );

                // If not found and it's a hostel component, try the alternative
                if (!$receipt && isset($_REQUEST['fee_component'])) {
                    $requested_comp = $_REQUEST['fee_component'];
                    if ($requested_comp === 'hostel_fee' || $requested_comp === 'hostel_security') {
                        $conditions['p.fee_component'] = ($requested_comp === 'hostel_fee') ? 'hostel_security' : 'hostel_fee';
                        $receipt = $op->readWithJoin(
                            'tbl_payments p',
                            [
                                'p.*',
                                'p.payment_date as issued_date',
                                's.surname',
                                's.student_name',
                                's.fathers_name',
                                's.mob',
                                's.aadhaar',
                                's.schoolname',
                                's.standard',
                                's.course_id',
                                's.board_id',
                                's.medium_id',
                                's.group_id',
                                'b.board_name',
                                'm.medium_name',
                                'g.group_name',
                                'e.roll_no',
                                'ay.year_name as academic_year',
                                'u.name as issued_by_name'
                            ],
                            [
                                ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id'],
                                ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
                                ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
                                ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
                                ['type' => 'LEFT', 'table' => 'tbl_enrolled_students e', 'on' => 's.id = e.registration_id'],
                                ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
                                ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 'p.created_by = u.id']
                            ],
                            $conditions
                        );
                    }
                }

                if ($receipt) {
                    // Format payment details
                    $receipt['payment_for'] = ucfirst(str_replace('_', ' ', $receipt['payment_type'] ?? '')) . ' Payment';
                    $receipt['course_name'] = null;

                    // Single receipt array for accountant
                    $receipts = [$receipt];
                }
            }
        }

        if (empty($receipts)) {
            set_flash_message('error', "Receipt not found!");
            header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : 'receipts.php'));
            exit;
        }

        // For token fee payments with multiple receipts, use the same receipt number for all
        if (count($receipts) > 1 && isset($receipts[0]['payment_type']) && $receipts[0]['payment_type'] == 'token_fee') {
            $common_receipt_no = $receipts[0]['receipt_no']; // Use first receipt number for all
            foreach ($receipts as &$receipt) {
                $receipt['receipt_no'] = $common_receipt_no;
            }
            unset($receipt); // Break reference
        }

        // Get student's school_id from registration record
        $student_school_id = null;
        if (isset($receipts[0]['student_id'])) {
            $op = new Operation();
            $student_data = $op->selectOne('tbl_gm_std_registration', ['*'], ['id' => $receipts[0]['student_id']]);
            if ($student_data) {
                $student_school_id = $student_data['school_id'];
            }
        }

        // Get receipt configurations for each fee component
        $receipt_configs = [];
        if (isset($receipts[0]['fee_component'])) {
            require_once __DIR__ . '/../../../common/helpers/receipt_mapping_functions.php';
            foreach ($receipts as $r) {
                if (!empty($r['fee_component'])) {
                    $fee_component = $r['fee_component'];
                    $config_id = getReceiptConfigForFee($conn, $fee_component, $student_school_id);
                    if ($config_id) {
                        $config = getReceiptConfigDetails($conn, $config_id);
                        $receipt_configs[$fee_component] = $config;
                    }
                }
            }
        }

        // Get active receipt configuration (fallback)
        $op = new Operation();
        $config = $op->selectOne('tbl_receipt_configuration', ['*'], ['is_active' => 1]);

        if (!$config) {
            set_flash_message('error', "Receipt configuration not found! Please configure receipt settings first.");
            header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : 'receipts.php'));
            exit;
        }
    } catch (Exception $e) {
        logError("Receipt PDF Error: " . $e->getMessage());
        set_flash_message('error', "Failed to load receipt!");
        header('Location: ' . ($is_student ? '../student-portal/my-fees.php' : 'receipts.php'));
        exit;
    }

    // Convert amount to words
    function numberToWords($number)
    {
        $words = convertToWords($number);
        return $words . ' Only';
    }

    function convertToWords($number)
    {
        $number = round((float) $number);
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($number == 0)
            return 'Zero';

        $words = '';
        $rupees = floor($number);

        // Crore
        if ($rupees >= 10000000) {
            $crore = floor($rupees / 10000000);
            $words .= convertToWords($crore) . ' Crore ';
            $rupees %= 10000000;
        }

        // Lakh
        if ($rupees >= 100000) {
            $lakh = floor($rupees / 100000);
            $words .= convertToWords($lakh) . ' Lakh ';
            $rupees %= 100000;
        }

        // Thousand
        if ($rupees >= 1000) {
            $thousand = floor($rupees / 1000);
            $words .= convertToWords($thousand) . ' Thousand ';
            $rupees %= 1000;
        }

        // Hundred
        if ($rupees >= 100) {
            $hundred = floor($rupees / 100);
            $words .= $ones[$hundred] . ' Hundred ';
            $rupees %= 100;
        }

        // Tens and Ones
        if ($rupees >= 20) {
            $ten = floor($rupees / 10);
            $words .= $tens[$ten] . ' ';
            $rupees %= 10;
        }

        if ($rupees > 0 && $rupees < 20) {
            $words .= $ones[$rupees] . ' ';
        }

        return trim($words);
    }

    // Process first receipt for initial values
    $first_receipt = $receipts[0];
    $full_name = trim($first_receipt['surname'] . ' ' . $first_receipt['student_name'] . ' ' . $first_receipt['fathers_name']);

    // Function to calculate academic year based on date
    function getAcademicYear($date = null)
    {
        if ($date) {
            $timestamp = strtotime($date);
            $year = (int) date('Y', $timestamp);
            $month = (int) date('n', $timestamp);
        } else {
            $year = (int) date('Y');
            $month = (int) date('n');
        }

        // Academic year starts in April (month 4)
        if ($month >= 4) {
            // April onwards = current year - next year
            return $year . '-' . substr($year + 1, 2);
        } else {
            // Jan-March = previous year - current year
            return ($year - 1) . '-' . substr($year, 2);
        }
    }

    // Include image helper functions
    require_once __DIR__ . '/../../common/image_helpers.php';

    // Create new PDF document - A4 Portrait as requested
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('GCA');
    $pdf->SetAuthor($config['organization_name']);
    $pdf->SetTitle('Receipt - ' . $first_receipt['receipt_no']);
    $pdf->SetSubject('Fee Receipt');

    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Set margins
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetAutoPageBreak(false, 0);

    // Loop through each receipt and create a page
    foreach ($receipts as $receipt) {
        // Get specific config for this fee component if available
        $current_config = $config;
        if (isset($receipt['fee_component']) && isset($receipt_configs[$receipt['fee_component']])) {
            $current_config = $receipt_configs[$receipt['fee_component']];
        }

        $receipt['amount'] = round((float) $receipt['amount']);
        $amount_in_words = numberToWords($receipt['amount']);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 11);

        // Split address into exactly two lines
        $address_lines = [
            $current_config['address'],
            trim(($current_config['city'] ?? '') . ($current_config['pincode'] ? ' - ' . $current_config['pincode'] : ''))
        ];
        $address_lines = array_values(array_filter(array_map('trim', $address_lines)));

        // Outer Border
        $pdf->Rect(7, 7, 196, 134, 'D');

        // Logo
        if (!empty($current_config['logo_path'])) {
            $logo_path = str_replace('../', '', $current_config['logo_path']);
            // For PDF generation, we need the actual file path
            $full_logo_path = dirname(__DIR__, 3) . '/counselling-backend/' . $logo_path;
            if (file_exists($full_logo_path)) {
                $clean_logo = cleanPngImage($full_logo_path);
                $pdf->Image($clean_logo, 12, 12, 22, 22, '', '', '', false, 300, '', false, false, 0);
                if ($clean_logo != $full_logo_path) {
                    @unlink($clean_logo); // Clean up temp file
                }
            }
        }

        // Organization Name (Left aligned)
        $pdf->SetFont('helvetica', 'B', 15);
        $pdf->SetXY(40, 11);
        $pdf->Cell(125, 7, htmlspecialchars_decode($current_config['organization_name']), 0, 1, 'L');

        // Address lines (Left aligned with dynamic wrapping)
        $pdf->SetFont('helvetica', '', 10); // Slightly smaller font for better fit
        $pdf->SetXY(40, 18);
        $full_address = htmlspecialchars_decode($current_config['address']) . "\n" . htmlspecialchars_decode(trim(($current_config['city'] ?? '') . ($current_config['pincode'] ? ' - ' . $current_config['pincode'] : '')));

        $pdf->MultiCell(115, 4, $full_address, 0, 'L', false, 1); // 115 width to stay clear of PAN/GST
        $y_pos = $pdf->GetY();

        // PAN and GST (right aligned, shifted down)
        $pdf->SetFont('helvetica', '', 9);
        $pan_line = '';
        $gst_line = '';
        if ($current_config['pan_number'])
            $pan_line = 'PAN : ' . $current_config['pan_number'];
        if ($current_config['gst_number'])
            $gst_line = 'GSTIN : ' . $current_config['gst_number'];

        if ($pan_line) {
            $pdf->SetXY(155, 27);
            $pdf->Cell(45, 4, $pan_line, 0, 1, 'R');
        }
        if ($gst_line) {
            $pdf->SetXY(155, 31);
            $pdf->Cell(45, 4, $gst_line, 0, 1, 'R');
        }

        // Horizontal line after header
        $pdf->Line(9, 36, 201, 36);

        // Student Name - with border
        $pdf->Rect(9, 38, 192, 8);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetXY(11, 40);
        $pdf->Cell(30, 5, "Student's Name :", 0, 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, strtoupper(htmlspecialchars_decode($full_name)), 0, 1, 'L');

        // Metadata grid - Row 1 (Standard, Term, Date)
        $pdf->SetXY(11, 49);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(21, 5, 'Standard', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $display_std = $receipt['standard'] ?? $receipt['course_name'] ?? 'N/A';
        if ((isset($receipt['course_id']) && $receipt['course_id'] == 6) || strtolower(trim((string) $display_std)) === '13' || strtolower(trim((string) $display_std)) === 're-neet') {
            $display_std = 'Re-Neet';
        }
        $pdf->Cell(55, 5, $display_std, 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        if (($receipt['course_id'] ?? 0) != 6) {
            $pdf->Cell(16, 5, 'Term', 0, 0, 'L');
            $pdf->Cell(4, 5, ':', 0, 0, 'C');
            $pdf->SetFont('helvetica', '', 9);
            $pdf->Cell(40, 5, $receipt['semester'] ?? 'Semester 1', 0, 0, 'L');
        } else {
            $pdf->Cell(60, 5, '', 0, 0, 'L');
        }

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(18, 5, 'Date', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, date('d/m/Y', strtotime($receipt['issued_date'])), 0, 1, 'L');

        // Metadata grid - Row 2 (Group, Year, Receipt No.)
        $pdf->SetXY(11, 55);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(21, 5, 'Group', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 5, $receipt['group_name'] ?? 'N/A', 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(16, 5, 'Year', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(40, 5, $receipt['academic_year'] ?? getAcademicYear($receipt['issued_date']), 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(18, 5, 'Receipt No.', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, $receipt['receipt_no'], 0, 1, 'L');

        // Metadata grid - Row 3 (Medium, Roll No, Pmt.Mode)
        $pdf->SetXY(11, 61);
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(21, 5, 'Medium', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(55, 5, $receipt['medium_name'] ?? 'N/A', 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(16, 5, 'Roll No.', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(40, 5, $receipt['roll_no'] ?? 'N/A', 0, 0, 'L');

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(18, 5, 'Pmt.Mode', 0, 0, 'L');
        $pdf->Cell(4, 5, ':', 0, 0, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 5, ucfirst($receipt['payment_mode']), 0, 1, 'L');

        // Fee Table
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetXY(9, 68);

        // Table headers
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell(15, 8, 'Sr. No.', 1, 0, 'C');
        $pdf->Cell(152, 8, 'Particulars', 1, 0, 'C');
        $pdf->Cell(25, 8, 'Amount', 1, 1, 'C');

        // Table data
        $pdf->SetFont('helvetica', '', 11);
        $pdf->SetX(9);
        $pdf->Cell(15, 8, '1', 'LR', 0, 'C');

        // Display specific fee component name for token fee
        $particulars_text = $receipt['payment_for'];
        if (isset($receipt['fee_component'])) {
            $std = $receipt['standard'] ?? $receipt['course_name'] ?? '';
            $particulars_text = formatFeeKey($receipt['fee_component'], $std);
        }

        $pdf->Cell(152, 8, htmlspecialchars_decode($particulars_text), 'LR', 0, 'L');
        $pdf->Cell(25, 8, formatIndianCurrency($receipt['amount']), 'LR', 1, 'R');

        // Empty rows to fill space and provide a visual separator if needed before total
        $pdf->SetX(9);
        $pdf->Cell(15, 4, '', 'LR', 0, 'C');
        $pdf->Cell(152, 4, '', 'LR', 0, 'L');
        $pdf->Cell(25, 4, '', 'LR', 1, 'R');

        $pdf->SetX(9);
        $pdf->Cell(15, 4, '', 'LR', 0, 'C');
        $pdf->Cell(152, 4, '', 'LR', 0, 'L');
        $pdf->Cell(25, 4, '', 'LR', 1, 'R');

        // Amount in words with Total Amount (Unified Box - No internal vertical lines)
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetX(9);
        $pdf->Cell(20, 8, 'Rupees :', 'LTB', 0, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(132, 8, $amount_in_words, 'TB', 0, 'L');
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(15, 8, 'Total:', 'TB', 0, 'C');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(25, 8, formatIndianCurrency($receipt['amount']), 'TRB', 1, 'R');

        // Bank details / Payment details
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(11, 105);
        $pdf->Cell(0, 5, 'Subject to realization of cheque', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX(11);
        $pdf->Cell(0, 5, 'Name of Bank: ' . ($receipt['bank_name']), 0, 1, 'L');
        $pdf->SetX(11);
        $pdf->Cell(0, 5, 'Cheque / D.D. No.: ' . ($receipt['cheque_no']), 0, 1, 'L');
        $pdf->SetX(11);
        $pdf->Cell(0, 5, 'Transaction ID: ' . ($receipt['transaction_id']), 0, 1, 'L');

        // Payment ID (for online payments)
        if (!empty($receipt['payment_id'])) {
            $pdf->SetX(11);
            $pdf->Cell(0, 5, 'Payment ID: ' . $receipt['payment_id'], 0, 1, 'L');
        }

        // Bottom section - 2 columns layout
        $pdf->SetXY(11, 115);

        // Column 1: Jurisdiction (center-left)
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(11, 130);
        $pdf->Cell(100, 5, 'SUBJECT TO BHAVNAGAR JURISDICTION', 0, 1, 'L');
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->SetX(11);
        $pdf->Cell(100, 5, '*This receipt is valid without signature as it is generated by the system.*', 0, 0, 'L');

        // Column 3: Signature box (right)
        $pdf->Rect(153, 112, 47, 27);

        // Signature image (Conditional signature for Bhautik Dumaniya - User ID 70)
        $full_sig_path = null;
        if (isset($receipt['created_by']) && $receipt['created_by'] == 70) {
            $bhautik_sig = dirname(__DIR__, 3) . '/assets/images/Bhautik_dumaniya.png';
            if (file_exists($bhautik_sig)) {
                $full_sig_path = $bhautik_sig;
            }
        }

        if (!$full_sig_path && !empty($current_config['signature_path'])) {
            $sig_path = str_replace('../', '', $current_config['signature_path']);
            // For PDF generation, we need the actual file path
            $full_sig_path = dirname(__DIR__, 3) . '/counselling-backend/' . $sig_path;
        }

        if ($full_sig_path && file_exists($full_sig_path)) {
            $pdf->Image($full_sig_path, 158, 112, 45, 20, '', '', '', false, 400, '', false, false, 0);
        }

        // Signature text
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY(153, 133);
        $pdf->Cell(47, 5, 'Authorised Signatory', 0, 1, 'C');

        // --- Add Refund Policy Section (Only for Std 11 & Course ID 1 or 2) ---
        if (($receipt['standard'] == '11' || $receipt['standard'] == 11) && (in_array(($receipt['course_id'] ?? null), [1, 2, '1', '2']))) {
            $pdf->SetY(150);

            // Title
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->SetFillColor(0, 0, 0);
            $pdf->Rect(11, 150, 1.5, 6, 'F');
            $pdf->SetXY(14, 150);
            $pdf->Cell(0, 6, 'REFUND POLICY :', 0, 1, 'L');

            $pdf->Ln(5);

            // Section 1: Who paid only Tokan fee
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetX(11);
            $pdf->Cell(0, 6, 'Who paid only Tokan fee.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(0, 5, 'Tokan Fee will be non-refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->MultiCell(0, 5, 'Tokan Fee will be refundable only for the Pre-admitted student who has cancelled admission up to 15th March', 0, 'L');

            $pdf->Ln(4);

            // Section 2: Who paid full Fees and cancelled the admission
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetX(11);
            $pdf->Cell(0, 6, 'Who paid full Fees and cancelled the admission.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, '1 to 30 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  80% Fees Refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, '31 to 60 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  65% Fees Refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, '61 to 90 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  50% Fees Refundable.', 0, 1, 'L');

            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->SetX(14);
            $pdf->Cell(5, 5, '>', 0, 0, 'R');
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(20);
            $pdf->Cell(30, 5, 'After 90 Days', 0, 0, 'L');
            $pdf->Cell(0, 5, ':  Fees will be Non-Refundable.', 0, 1, 'L');
        } elseif ($receipt['course_id'] == '6' || $receipt['course_id'] == 6) {
            $pdf->SetY(145);

            // Rules and fees policy Title
            $pdf->SetFont('helvetica', 'BU', 12);
            $pdf->SetXY(11, 145);
            $pdf->Cell(0, 6, 'Rules and fees policy are as under :', 0, 1, 'L');

            $pdf->Ln(2);

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(11);
            $pdf->Cell(8, 5, '1>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'Student should attain all classes and lecture without being absent.', 0, 'L');

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '2>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'Student should keep discipline and obey all rules made by academy.', 0, 'L');

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '3>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, "Attain all seminar's and tests.", 0, 'L');

            $pdf->Ln(4);

            // Fees policy Title
            $pdf->SetFont('helvetica', 'BU', 12);
            $pdf->SetX(11);
            $pdf->Cell(0, 6, 'Fees policy :', 0, 1, 'L');

            $pdf->Ln(2);

            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetX(11);
            $pdf->Cell(8, 5, '1>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'Parents must pay fees regularly, each installment in time limit.', 0, 'L');

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '2>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'In case of full fees payment, fees will refunded by refund policy made by academy are as under :', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(a)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 1 week of starting of classes, 80% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(b)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 2 week of starting of classes, 60% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(c)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 3 week of starting of classes, 50% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(d)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 4 week of starting of classes, 40% of fee will be refunded.', 0, 'L');

            $pdf->SetX(21);
            $pdf->Cell(8, 5, '(e)', 0, 0, 'L');
            $pdf->MultiCell(0, 5, 'After 28 days of starting of classes, fee will not be refunded.', 0, 'L');

            $pdf->Ln(2);

            $pdf->SetX(11);
            $pdf->Cell(8, 5, '3>', 0, 0, 'R');
            $pdf->SetX(21);
            $pdf->MultiCell(0, 5, 'For benefit of refund, parents / student should apply for the same in prescribed format.', 0, 'L');
        }

    } // End foreach receipts

    // Auto-print JS
    $pdf->IncludeJS("print(true);");


    // Output PDF
    $filename = count($receipts) > 1 ? 'Token_Fee_Receipts_' . $first_receipt['receipt_no'] . '.pdf' : 'Receipt_' . $first_receipt['receipt_no'] . '.pdf';
    // Clean output buffer to prevent notices/warnings from corrupting the PDF
    if (ob_get_length())
        ob_clean();

    // Set appropriate headers
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    $pdf->Output($filename, 'I');
    exit;

} catch (Exception $e) {
    if (ob_get_length())
        ob_clean();
    $err_msg = "PDF Generation Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    $err_msg .= " | Student ID: " . ($_REQUEST['student_id'] ?? 'N/A') . " | Receipt No: " . ($_REQUEST['receipt_no'] ?? 'N/A');
    error_log($err_msg);
    if (function_exists('logError')) {
        logError($err_msg);
    }
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: " . htmlspecialchars($e->getMessage() ?? '');
    exit;
}
