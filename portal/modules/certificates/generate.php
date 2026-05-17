<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
// Generators will be included dynamically below

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? 0;
    $certificate_type = $_POST['certificate_type'] ?? '';

    if (empty($student_id) || empty($certificate_type)) {
        die('Invalid input parameters');
    }

    // $generator instantiation moved inside switch for dynamic loading
    $issued_by = $_SESSION['user_id']; // assuming user_id exists in session

    try {
        $generator = null;
        switch ($certificate_type) {
            case 'bonafide':
                require_once 'generators/BonafideGenerator.php';
                $generator = new BonafideGenerator();
                break;
            case 'character':
                require_once 'generators/CharacterGenerator.php';
                $generator = new CharacterGenerator();
                break;
            case 'slc':
                require_once 'generators/SLCGenerator.php';
                $generator = new SLCGenerator();
                break;
            case 'attempt':
                require_once 'generators/AttemptGenerator.php';
                $generator = new AttemptGenerator();
                break;
            case 'fees_paid':
                require_once 'generators/FeesPaidGenerator.php';
                $generator = new FeesPaidGenerator();
                break;
            case 'provisional':
                require_once 'generators/ProvisionalGenerator.php';
                $generator = new ProvisionalGenerator();
                break;
            case 'course_completion':
                require_once 'generators/CourseCompletionGenerator.php';
                $generator = new CourseCompletionGenerator();
                break;
            case 'sports':
                require_once 'generators/SportsGenerator.php';
                $generator = new SportsGenerator();
                break;
            default:
                die('This certificate template is under development.');
        }

        if ($generator) {
            $generator->generate($student_id, $issued_by);
        }
    } catch (Exception $e) {
        die('Error generating PDF: ' . $e->getMessage());
    }
} else {
    header('Location: index.php');
    exit;
}
