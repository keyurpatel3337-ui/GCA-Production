<?php
/**
 * Handler for sending dynamic WhatsApp messages
 * Template: parent_update_not — 2 variables: {{1}} message, {{2}} student name
 */

require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once __DIR__ . '/../../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_FLASH_MESSAGE;
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/helpers/whatsapp_functions.php';

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id    = $_POST['student_id'] ?? '';
    $message       = trim($_POST['message'] ?? '');
    $details       = trim($_POST['details'] ?? '');
    $template_name = $_POST['template_name'] ?? '';
    $user_id       = $_SESSION['user_id'] ?? null;

    $allowed_templates = ['parent_update_not', 'parent_update_note'];
    if (!in_array($template_name, $allowed_templates)) {
        set_flash_message('error', 'Invalid template selected.');
        header('Location: ../send-whatsapp-dynamic.php');
        exit;
    }

    if (empty($student_id) || empty($message)) {
        set_flash_message('error', 'Please select a student and enter a message.');
        header('Location: ../send-whatsapp-dynamic.php');
        exit;
    }

    if ($template_name === 'parent_update_note' && empty($details)) {
        set_flash_message('error', 'Please fill in the additional details field.');
        header('Location: ../send-whatsapp-dynamic.php');
        exit;
    }

    try {
        $db = getConnection();

        // Fetch student mobile and name
        $stmt = $db->prepare("SELECT surname, student_name, mob FROM tbl_gm_std_registration WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student || empty($student['mob'])) {
            set_flash_message('error', 'Student not found or mobile number missing.');
            header('Location: ../send-whatsapp-dynamic.php');
            exit;
        }

        $recipient    = $student['mob'];
        $student_name = trim($student['surname'] . ' ' . $student['student_name']);

        // Build variables based on template
        // parent_update_not: {{1}} = message, {{2}} = student name
        // parent_update_note: {{1}} = સૂચના (message), {{2}} = વધુ વિગત (details)
        if ($template_name === 'parent_update_not') {
            $variables = [$message, $student_name];
        } else {
            $variables = [$message, $details];
        }

        $result = sendWhatsAppTemplate(
            $db,
            $recipient,
            $template_name,
            $variables,
            'bhashsms',
            'dynamic',
            $student_id,
            $user_id
        );

        if ($result['success']) {
            set_flash_message('success', "WhatsApp message sent successfully to {$student_name} ({$recipient}).");
        } else {
            set_flash_message('error', 'Failed to send WhatsApp: ' . ($result['error'] ?? 'Unknown API error'));
        }

    } catch (Exception $e) {
        set_flash_message('error', 'An error occurred: ' . $e->getMessage());
    }

    header('Location: ../send-whatsapp-dynamic.php?student_id=' . $student_id);
    exit;
} else {
    header('Location: ../send-whatsapp-dynamic.php');
    exit;
}
