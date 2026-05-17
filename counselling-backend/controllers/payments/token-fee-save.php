<?php

require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once dirname(dirname(__DIR__)) . '/../common/bootstrap.php';
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once DB_CONNECT_FILE;
require_once dirname(dirname(__DIR__)) . '/common/transaction_helper.php';
require_once OPERATION_FILE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';
require_once __DIR__ . '/../../../common/helpers/enrollment_functions.php';
require_once HELPER_NOTIFICATION_FUNCTIONS;
require_once HELPER_WHATSAPP_FUNCTIONS;
require_once __DIR__ . '/../../../common/helpers/division_assignment_functions.php';

header('Content-Type: application/json');

// Check if user is authenticated and has appropriate role
$allowed_roles = [ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_ACCOUNTANT];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowed_roles)) {
    sendErrorResponse('Unauthorized access', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Invalid request method', 405);
}

$student_id = $_POST['student_id'] ?? null;
$payment_mode = $_POST['payment_mode'] ?? null;
$accountant_id = $_SESSION['user_id'];

// Validation
if (empty($student_id) || empty($payment_mode)) {
    sendErrorResponse('Student ID and payment mode are required', 400);
}

try {
    // Get student details
    $stmt = $conn->prepare("SELECT s.*, 
                           b.board_name as board,
                           c.course_name,
                           m.medium_name,
                           g.group_name,
                           fc.token_fee
                           FROM tbl_gm_std_registration s
                           LEFT JOIN tbl_boards b ON s.board_id = b.id
                           LEFT JOIN tbl_courses c ON s.course_id = c.id
                           LEFT JOIN tbl_medium m ON s.medium_id = m.id
                           LEFT JOIN tbl_group g ON s.group_id = g.id
                           LEFT JOIN tbl_fee_config fc ON s.course_id = fc.course_id 
                               AND s.school_id = fc.school_id 
                               AND s.medium_id = fc.medium_id 
                               AND s.group_id = fc.group_id 
                               AND fc.is_active = 1
                           WHERE s.id = ? AND s.admission_confirmed = 1");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        sendErrorResponse('Student not found or admission not confirmed', 404);
    }

    if ($student['token_fees_paid']) {
        sendErrorResponse('Token fee already paid for this student', 400);
    }

    $conn->beginTransaction();

    if ($payment_mode === 'offline') {
        // Offline payment - mark as paid immediately
        $offline_method = $_POST['offline_method'] ?? 'cash';
        $transaction_ref = $_POST['transaction_ref'] ?? '';
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $remarks = $_POST['remarks'] ?? '';

        // Generate transaction ID
        $transaction_id = generateUniqueTransactionID('GMI');

        // Get token fee from config
        $token_amount = floatval($student['token_fee']);

        // Update student record
        $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                               SET token_fees_paid = 1,
                                   token_payment_mode = 'offline',
                                   token_payment_id = ?,
                                   token_transaction_id = ?,
                                   token_amount = ?,
                                   token_payment_date = NOW(),
                                   password = ?,
                                   updated_at = NOW()
                               WHERE id = ?");

        // Set password as mobile number
        $password = password_hash($student['mob'], PASSWORD_DEFAULT);

        $stmt->execute([
            $transaction_id,
            $transaction_ref ?: $transaction_id,
            $token_amount,
            $password,
            $student_id
        ]);

        // Generate SINGLE GCA Receipt for Tuition Fee Part 1 (incl. GST)
        // Calculate financial year
        $payment_month = intval(date('n', strtotime($payment_date))); // 1-12
        $payment_year = intval(date('Y', strtotime($payment_date)));

        if ($payment_month >= 4) {
            $fy_start = $payment_year;
        } else {
            $fy_start = $payment_year - 1;
        }

        // Get next receipt number in this financial year
        $fy_start_date = $fy_start . '-04-01';
        $stmt = $conn->prepare("SELECT MAX(CAST(receipt_no AS UNSIGNED)) as last_num 
                               FROM tbl_payments 
                               WHERE fee_component = 'tuition_fee_part1'
                               AND receipt_no REGEXP '^[0-9]+$'");
        $stmt->execute();
        $result = $stmt->fetch();
        $last_num = $result['last_num'] ?? 0;
        $receipt_no = str_pad($last_num + 1, 2, '0', STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO tbl_payments 
                               (student_id, receipt_no, amount, payment_date, payment_mode, 
                                transaction_id, payment_type, fee_component, remarks, 
                                status, created_by, created_at) 
                               VALUES 
                               (?, ?, ?, ?, ?, ?, 'token_fee', 'tuition_fee_part1', ?, 'paid', ?, ?)");
        $stmt->execute([
            $student_id,
            $receipt_no,
            $token_amount,
            $payment_date,
            $offline_method,
            $transaction_ref ?: $transaction_id,
            'Token Fee (Tuition Part 1) - ' . $remarks,
            $accountant_id,
            $payment_date
        ]);

        // Enroll student and assign fees
        $enrollment_result = enrollStudentAfterTokenPayment($conn, $student_id);

        // Assign division and roll number automatically (if enabled in settings)
        require_once __DIR__ . '/../../common/settings_helper.php';
        $auto_assign_enabled = getSetting($conn, 'auto_assign_division_on_enrollment', false);

        $division_result = null;
        if ($enrollment_result['success'] && !empty($enrollment_result['enrollment_id']) && $auto_assign_enabled) {
            $division_result = assignDivisionAndRollNumber($conn, $enrollment_result['enrollment_id']);
            if (!$division_result['success']) {
                logError("Division assignment failed for enrollment " . $enrollment_result['enrollment_id'] . ": " . $division_result['message']);
            }
        }

        $conn->commit();

        // Send notifications after successful offline payment
        if ($offline_method !== 'deduction') {
            try {
                // Fetch student details with course info
                $stmt = $conn->prepare("SELECT r.*, c.course_name 
                                       FROM tbl_gm_std_registration r
                                       LEFT JOIN tbl_courses c ON r.course_id = c.id
                                       WHERE r.id = ?");
                $stmt->execute([$student_id]);
                $student_details = $stmt->fetch();

                if ($student_details) {
                    $full_name = trim($student_details['surname'] . ' ' . $student_details['student_name'] . ' ' . $student_details['fathers_name']);
                    $recipient = [
                        'name' => $full_name,
                        'email' => '', // Email not stored in registration table
                        'mobile' => $student_details['mob']
                    ];

                    $token_variables = [
                        'student_name' => $full_name,
                        'amount' => formatIndianCurrency($token_amount),
                        'receipt_no' => $receipt_no,
                        'transaction_id' => $transaction_ref ?: $transaction_id
                    ];

                    $login_variables = [
                        'username' => $student_details['aadhaar'],
                        'password' => $student_details['mob']
                    ];

                    // Send notification (both WhatsApp and Email)
                    sendNotification(
                        $conn,
                        'token_fee_success',
                        $recipient,
                        $token_variables,
                        ['student_id' => $student_id]
                    );

                    // Send login credentials notification
                    sendNotification(
                        $conn,
                        'login_credentials',
                        $recipient,
                        $login_variables,
                        ['student_id' => $student_id]
                    );
                }
            } catch (Exception $e) {
                // Log notification error but don't fail the payment
                logError("Offline payment notification error: " . $e->getMessage());
            }
        }

        $success_message = "Token fee collected successfully! 3 receipts generated (GM, MST, GCA). Student can now access portal with Aadhaar: " . $student['aadhaar'] . " & Password: " . $student['mob'];
        $response_data = [
            'student_id' => $student_id,
            'aadhaar' => $student['aadhaar'],
            'password' => $student['mob'],
            'transaction_id' => $transaction_id
        ];

        if ($enrollment_result['success']) {
            $success_message .= " | Enrollment ID: " . $enrollment_result['enrollment_id'];
            $response_data['enrollment_id'] = $enrollment_result['enrollment_id'];
        }
        if ($division_result && $division_result['success']) {
            $success_message .= " | Division: " . ($division_result['division_id'] ?? '-') . ", Roll No: " . ($division_result['roll_no'] ?? '-');
            $response_data['division_id'] = $division_result['division_id'] ?? null;
            $response_data['roll_no'] = $division_result['roll_no'] ?? null;
        }

        sendSuccessResponse($response_data, $success_message);
    } elseif ($payment_mode === 'online') {
        // Online payment - enable student portal access for payment
        $payment_gateway = $_POST['payment_gateway'] ?? '';

        if (empty($payment_gateway)) {
            sendErrorResponse('Please select a payment gateway', 400);
        }

        // Generate transaction ID for tracking
        $transaction_id = generateUniqueTransactionID('GMI');

        // Update student record - enable portal access but mark payment as pending
        $stmt = $conn->prepare("UPDATE tbl_gm_std_registration 
                               SET token_payment_mode = 'online',
                                   token_payment_id = ?,
                                   token_transaction_id = ?,
                                   token_amount = ?,
                                   password = ?,
                                   updated_at = NOW()
                               WHERE id = ?");

        // Set password as mobile number
        $password = password_hash($student['mob'], PASSWORD_DEFAULT);

        $stmt->execute([
            $payment_gateway,
            $transaction_id,
            $student['token_fee'],
            $password,
            $student_id
        ]);

        // Store gateway selection for payment processing
        $stmt = $conn->prepare("INSERT INTO tbl_pending_payments 
                               (student_id, payment_type, amount, payment_gateway, 
                                transaction_id, status, created_by, created_at) 
                               VALUES 
                               (?, 'token_fee', ?, ?, ?, 'pending', ?, NOW())
                               ON DUPLICATE KEY UPDATE 
                               payment_gateway = VALUES(payment_gateway),
                               transaction_id = VALUES(transaction_id),
                               updated_at = NOW()");

        // Check if table exists, if not create it
        try {
            $stmt->execute([
                $student_id,
                $student['token_fee'],
                $payment_gateway,
                $transaction_id,
                $accountant_id
            ]);
        } catch (PDOException $e) {
            // Table doesn't exist, create it
            $conn->exec("CREATE TABLE IF NOT EXISTS tbl_pending_payments (
                id INT AUTO INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                payment_type VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_gateway VARCHAR(50) NOT NULL,
                transaction_id VARCHAR(100) NOT NULL,
                status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
                gateway_response TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_student_payment (student_id, payment_type),
                INDEX idx_status (status),
                INDEX idx_transaction (transaction_id)
            )");

            // Try again
            $stmt->execute([
                $student_id,
                $student['token_fee'],
                $payment_gateway,
                $transaction_id,
                $accountant_id
            ]);
        }

        $conn->commit();

        // Send login credentials notification for online payment setup
        try {
            // Fetch student details with course info
            $stmt = $conn->prepare("SELECT r.*, c.course_name 
                                   FROM tbl_gm_std_registration r
                                   LEFT JOIN tbl_courses c ON r.course_id = c.id
                                   WHERE r.id = ?");
            $stmt->execute([$student_id]);
            $student_details = $stmt->fetch();

            if ($student_details) {
                $full_name = trim($student_details['surname'] . ' ' . $student_details['student_name'] . ' ' . $student_details['fathers_name']);
                $recipient = [
                    'name' => $full_name,
                    'email' => '', // Email not stored in registration table
                    'mobile' => $student_details['mob']
                ];

                $variables = [
                    'student_name' => $full_name,
                    'username' => $student_details['aadhaar'],
                    'password' => $student_details['mob']
                ];

                // Send login credentials with online payment instructions
                sendNotification(
                    $conn,
                    'login_credentials',
                    $recipient,
                    $variables,
                    ['student_id' => $student_id]
                );
            }
        } catch (Exception $e) {
            // Log notification error but don't fail the setup
            logError("Online payment setup notification error: " . $e->getMessage());
        }

        sendSuccessResponse([
            'student_id' => $student_id,
            'aadhaar' => $student['aadhaar'],
            'password' => $student['mob'],
            'payment_gateway' => $payment_gateway,
            'transaction_id' => $transaction_id
        ], 'Online payment enabled! Student credentials - Aadhaar: ' . $student['aadhaar'] . ', Password: ' . $student['mob'] . '. Student must complete online payment to access portal.');
    }
} catch (PDOException $e) {
    // Only rollback if a transaction is active
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logDatabaseError($e, "Save Token Fee Payment");
    sendErrorResponse('Error processing token fee payment: ' . $e->getMessage(), 500);
}
