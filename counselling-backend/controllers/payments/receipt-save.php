<?php
require_once __DIR__ . '/../../common/session_config.php';
require_once dirname(dirname(__DIR__)) . '/../common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/receipt_sequence_helper.php';

// Check if user is Accountant
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
    set_flash_message('error', "Unauthorized access!");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? null;
    $issued_date = $_POST['issued_date'] ?? null;
    $amount = $_POST['amount'] ?? null;
    $payment_for = trim($_POST['payment_for'] ?? '');
    $payment_mode = $_POST['payment_mode'] ?? null;
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $cheque_no = trim($_POST['cheque_no'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $payment_id = $_POST['payment_id'] ?? null;
    $issued_by = $_SESSION['user_id'];

    // Validation
    $errors = [];

    if (empty($student_id)) {
        $errors[] = "Please select a student.";
    }

    if (empty($issued_date)) {
        $errors[] = "Receipt date is required.";
    }

    if (empty($amount) || $amount <= 0) {
        $errors[] = "Valid amount is required.";
    }

    if (empty($payment_for)) {
        $errors[] = "Payment purpose is required.";
    }

    if (empty($payment_mode)) {
        $errors[] = "Payment mode is required.";
    }

    // Generate unique receipt number using new sequence system
    try {
        // Get student's school_id
        $school_id = getStudentSchoolId($conn, $student_id);

        // Get current academic year
        $academic_year = getCurrentAcademicYear($conn);

        // Get next receipt number
        $result = getNextReceiptNumber($conn, $payment_for, $school_id, $academic_year, $student_id);

        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Failed to generate receipt number');
        }

        $receipt_no = $result['receipt_no'];
    } catch (Exception $e) {
        logError('Receipt generation error: ' . $e->getMessage());
        $receipt_no = 'ERR-' . time();
        $errors[] = 'Failed to generate receipt number: ' . $e->getMessage();
    }

    // If no errors, save receipt
    if (empty($errors)) {
        try {
            // If payment_id exists, update the payment record with the receipt number
            // receipt-print-pdf.php reads receipt data directly from tbl_payments
            if (!empty($payment_id)) {
                $stmt = $conn->prepare("UPDATE tbl_payments 
                    SET receipt_no = :receipt_no
                    WHERE id = :payment_id");
                $stmt->execute([
                    'receipt_no' => $receipt_no,
                    'payment_id' => $payment_id
                ]);
                $receipt_id = $payment_id;
            } else {
                // If no payment_id, create a new payment record for manual/standalone receipt
                $stmt = $conn->prepare("INSERT INTO tbl_payments 
                    (student_id, receipt_no, amount, payment_date, payment_type, 
                    payment_mode, transaction_id, cheque_no, bank_name, remarks, 
                    status, created_by, created_at) 
                    VALUES 
                    (:student_id, :receipt_no, :amount, :issued_date, :payment_for, 
                    :payment_mode, :transaction_id, :cheque_no, :bank_name, :remarks, 
                    'paid', :generated_by, NOW())");

                $stmt->execute([
                    'student_id' => $student_id,
                    'receipt_no' => $receipt_no,
                    'amount' => $amount,
                    'issued_date' => $issued_date,
                    'payment_for' => $payment_for,
                    'payment_mode' => $payment_mode,
                    'transaction_id' => $transaction_id,
                    'cheque_no' => $cheque_no,
                    'bank_name' => $bank_name,
                    'remarks' => $remarks,
                    'generated_by' => $issued_by
                ]);
                $receipt_id = $conn->lastInsertId();
            }

            // NEW: Automate Receipt Saving and Email Attachment
            require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/receipt_pdf_helper.php';
            require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/notification_functions.php';

            $d_receipt_folder = 'D:/receipt';
            $receipt_filename = 'Receipt_' . $receipt_no . '.pdf';
            $save_path = $d_receipt_folder . '/' . $receipt_filename;

            $pdf_result = generateAndSaveReceiptPDF($conn, $receipt_id, $save_path);

            if ($pdf_result['success']) {
                // Fetch student info for email
                $stmt_std = $conn->prepare("SELECT student_name, email, mob FROM tbl_gm_std_registration WHERE id = ?");
                $stmt_std->execute([$student_id]);
                $student = $stmt_std->fetch();

                if ($student && !empty($student['email'])) {
                    $recipient = [
                        'name' => $student['student_name'],
                        'email' => $student['email'],
                        'mobile' => $student['mob']
                    ];
                    $options = [
                        'attachments' => [
                            ['path' => $save_path, 'name' => $receipt_filename]
                        ],
                        'student_id' => $student_id
                    ];

                    // Map payment_for to notification type if needed, or use a default
                    if ($payment_for == 'token_fee') {
                        $notif_type = 'token_fee_success';
                        $variables = [
                            'student_name' => $student['student_name'],
                            'amount' => $amount,
                            'receipt_no' => $receipt_no,
                            'transaction_id' => $transaction_id
                        ];
                    } else {
                        $notif_type = 'fee_payment_success';
                        $variables = [
                            'student_name' => $student['student_name'],
                            'amount' => $amount,
                            'payment_mode' => $payment_mode,
                            'receipt_no' => $receipt_no,
                            'payment_date' => $issued_date
                        ];
                    }
                    sendNotification($conn, $notif_type, $recipient, $variables, $options);
                }
            } else {
                logError("Failed to auto-save receipt PDF: " . $pdf_result['message']);
            }

            set_flash_message('success', "Receipt generated successfully! Receipt No: " . $receipt_no . ($pdf_result['success'] ? " (Saved to D:/receipt and Emailed)" : ""));
            header('Location: ' . BASE_URL . '/modules/payments/receipt-print-pdf.php?id=' . $receipt_id);
            exit;
        } catch (PDOException $e) {
            logDatabaseError($e, "Generate Receipt");
            set_flash_message('error', "Failed to generate receipt. Please try again.");
            header('Location: ' . BASE_URL . '/modules/payments/generate-receipt.php');
            exit;
        }
    } else {
        set_flash_message('error', implode('<br>', $errors));
        header('Location: ' . BASE_URL . '/modules/payments/generate-receipt.php');
        exit;
    }
} else {
    set_flash_message('error', "Invalid request method.");
    header('Location: ' . BASE_URL . '/modules/payments/generate-receipt.php');
    exit;
}
