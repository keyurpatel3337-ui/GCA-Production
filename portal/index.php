<?php

/**
 * Portal Index - Redirect to login or dashboard
 */
require_once __DIR__ . '/session_config.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) || isset($_SESSION['is_parent_login'])) {
    // Check for parent login first
    if (isset($_SESSION['is_parent_login']) && $_SESSION['is_parent_login'] === true) {
        header('Location: modules/parent-portal/dashboard.php');
        exit;
    }

    // Redirect to appropriate dashboard based on role
    $role_id = $_SESSION['role_id'] ?? 0;

    switch ($role_id) {
        case 1: // Super Admin
            header('Location: modules/dashboard/admin_dashboard.php');
            break;
        case 2: // Principle
            header('Location: modules/dashboard/principle_dashboard.php');
            break;
        case 3: // Counsellor
            header('Location: modules/dashboard/counsellor_dashboard.php');
            break;
        case 4: // Student
            header('Location: modules/dashboard/student_dashboard.php');
            break;
        case 5: // Accountant
            header('Location: modules/dashboard/accountant_dashboard.php');
            break;
        case 6: // Website Admin
            header('Location: modules/dashboard/website_admin_dashboard.php');
            break;
        case 7: // Maintenance Admin
            header('Location: modules/maintenance/index.php');
            break;
        case 8:
            header('Location: modules/dashboard/establishment_dashboard.php');
            break;
        case 9:
            header('Location: modules/dashboard/reception_dashboard.php');
            break;
        case 28: // Computer Operator
            header('Location: modules/dashboard/computer_operator_dashboard.php');
            break;
        default:
            header('Location: login.php');
            break;
    }
} else {
    // Not logged in, redirect to login
    header('Location: login.php');
}
exit;
