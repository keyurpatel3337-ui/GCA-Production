<?php

/**
 * Main Router / Entry Point
 * Routes all requests to appropriate controller files
 * 
 * Usage: Access endpoints via /index.php?route=controller/action
 * Example: /index.php?route=students/list
 *          /index.php?route=dashboard/admin
 *          /index.php?route=payments/initiate
 */

// Define constant to skip auth redirect in globalvariable.php

require_once __DIR__ . '/../common/bootstrap.php';
require_once BACKEND_GLOBALVARIABLE;

// Get the route from query parameter
$route = $_GET['route'] ?? '';

// Clean and validate the route
$route = trim($route, '/');
$route = preg_replace('/[^a-zA-Z0-9\-_\/]/', '', $route);

if (empty($route)) {
    sendJsonResponse([
        'success' => true,
        'message' => 'Counselling Backend API',
        'version' => '1.0.0',
        'available_routes' => getAvailableRoutes()
    ]);
}

// Parse route into parts
$routeParts = explode('/', $route);
$module = $routeParts[0] ?? '';
$action = $routeParts[1] ?? 'index';
$subAction = $routeParts[2] ?? null;

// Route mapping - Map routes to controller files
$routeMap = [
    // Dashboard routes
    'dashboard' => [
        'admin' => 'controllers/dashboard/admin_dashboard_controller.php',
        'principle' => 'controllers/dashboard/principle_dashboard_controller.php',
        'counsellor' => 'controllers/dashboard/counsellor_dashboard_controller.php',
        'accountant' => 'controllers/dashboard/accountant_dashboard_controller.php',
        'student' => 'controllers/dashboard/student_dashboard_controller.php',
        'website-admin' => 'controllers/dashboard/website_admin_dashboard_controller.php',
        'students-view' => 'controllers/dashboard/students_view_controller.php',
        'counsellors' => 'controllers/dashboard/counsellors_controller.php',
        'reports' => 'controllers/dashboard/reports_controller.php',
        'results' => 'controllers/dashboard/results_controller.php',
        'wallet_manager' => 'controllers/dashboard/wallet_manager_dashboard_controller.php',
        'computer-operator' => 'controllers/dashboard/computer_operator_dashboard_controller.php'
    ],

    // Students routes
    'students' => [
        'list' => 'controllers/students/list_controller.php',
        'details' => 'controllers/students/details_controller.php',
        'search' => 'controllers/students/search_controller.php',
        'add' => 'controllers/students/add_controller.php',
        'save' => 'controllers/students/save.php',
        'update' => 'controllers/students/update-student.php',
        'edit' => 'controllers/students/edit-student_controller.php',
        'enrolled' => 'controllers/students/enrolled-students_controller.php',
        'registered' => 'controllers/students/registered-students_controller.php',
        'admission-confirm' => 'controllers/students/admission-confirm_controller.php',
        'admission-confirm-list' => 'controllers/students/admission-confirm-list_controller.php',
        'admission-confirm-save' => 'controllers/students/admission-confirm-save.php',
        'admission-letter' => 'controllers/students/admission-letter_controller.php',
        'reset-admission' => 'controllers/students/reset-admission-confirmation.php',
        'delete-multiple' => 'controllers/students/students-delete-multiple.php',
        'get-gmsat-marks' => 'controllers/students/ajax-get-gmsat-marks.php',
        'counsellor-assignment' => 'controllers/students/counsellor-assignment-ajax.php',
        'division-shuffle-save' => 'controllers/students/division-shuffle-save.php',
        'division-assignment-bulk-save' => 'controllers/students/division-assignment-bulk-save.php',
        'division-assignment-single-save' => 'controllers/students/division-assignment-single-save.php',
        'bulk-upload' => 'controllers/students/bulk-upload_controller.php',
        'session-save' => 'controllers/students/session-save.php',
        'appointment-status' => 'controllers/students/appointment-status-update.php',
        'school-count' => 'controllers/students/get-school-student-count.php',
        'division-request-approve' => 'controllers/students/division-request-approve.php',
        'division-request-reject' => 'controllers/students/division-request-reject.php'
    ],

    // Fees routes
    'fees' => [
        'config' => 'controllers/fees/fee-config_controller.php',
        'config-update' => 'controllers/fees/fee-config-update.php',
        'config-delete-multiple' => 'controllers/fees/fee-config-delete-multiple.php',
        'fee-config-save' => 'controllers/fees/fee-config-save.php',
        'fee-config-update' => 'controllers/fees/fee-config-update.php',
        'fee-config-delete' => 'controllers/fees/fee-config-delete.php',
        'fee-config-view' => 'controllers/fees/fee-config-view_controller.php',
        'fee-config-view-installments' => 'controllers/fees/fee-config-view-installments_controller.php',
        'fee-splits' => 'controllers/fees/fee-splits_controller.php',
        'fee-split-save' => 'controllers/fees/fee-split-save.php',
        'fee-split-update' => 'controllers/fees/fee-split-update.php',
        'fee-split-delete' => 'controllers/fees/fee-split-delete.php',
        'assign-fees' => 'controllers/fees/assign-fees_controller.php',
        'pending-reminders' => 'controllers/fees/pending-reminders_controller.php',
        'refund-management' => 'controllers/fees/refund-management_controller.php',
        'list' => 'controllers/fees/fees-list_controller.php',
        'structure' => 'controllers/fees/fee-structure_controller.php'
    ],

    // Payments routes
    'payments' => [
        'list' => 'controllers/payments/payments_controller.php',
        'add' => 'controllers/payments/add-payment_controller.php',
        'pending' => 'controllers/payments/pending-payments_controller.php',
        'initiate' => 'controllers/payments/initiate-payment.php',
        'callback' => 'controllers/payments/payment-callback.php',
        'response' => 'controllers/payments/payment-response.php',
        'history' => 'controllers/payments/payment-history_controller.php',
        'save' => 'controllers/payments/payment-save.php',
        'payment-save' => 'controllers/payments/payment-save.php',
        'installment-requests' => 'controllers/payments/installment-requests_controller.php',
        'receipt' => 'controllers/payments/receipt_controller.php',
        'receipts' => 'controllers/payments/receipts_controller.php',
        'receipt-config' => 'controllers/payments/receipt-config_controller.php',
        'receipt-config-save' => 'controllers/payments/receipt-config-save.php',
        'receipt-config-get' => 'controllers/payments/receipt-config-get.php',
        'receipt-config-delete' => 'controllers/payments/receipt-config-delete.php',
        'receipt-config-set-default' => 'controllers/payments/receipt-config-set-default.php',
        'receipt-save' => 'controllers/payments/receipt-save.php',
        'fee-receipt-mapping' => 'controllers/payments/fee-receipt-mapping_controller.php',
        'fee-receipt-mapping-save' => 'controllers/payments/fee-receipt-mapping-save.php',
        'fee-receipt-mapping-update' => 'controllers/payments/fee-receipt-mapping-update.php',
        'fee-receipt-mapping-delete' => 'controllers/payments/fee-receipt-mapping-delete.php',
        'financial-reports' => 'controllers/payments/financial-reports_controller.php',
        'token-fee-save' => 'controllers/payments/token-fee-save.php',
        'token-fee-collect' => 'controllers/payments/token-fee-collect_controller.php',
        'token-fee-collection' => 'controllers/payments/token-fee-collection_controller.php',
        'easebuzz' => 'controllers/payments/easebuzz-payment.php',
        'cancel-receipt' => 'controllers/payments/cancel-receipt.php',
        'payment-without-gst-hard-delete' => 'controllers/payments/payment-without-gst-hard-delete.php'
    ],

    // Academics routes
    'academics' => [
        'divisions' => 'controllers/academics/divisions_controller.php',
        'subjects' => 'controllers/academics/subjects_controller.php',
        'timetable' => 'controllers/academics/timetable_controller.php'
    ],

    // Profile routes
    'profile' => [
        'details' => 'controllers/profile/profile_controller.php',
        'view' => 'controllers/profile/profile_controller.php',
        'edit' => 'controllers/profile/profile_controller.php',
        'update' => 'controllers/profile/profile-update.php',
        'password-update' => 'controllers/profile/password-update.php'
    ],

    // Scholarships routes
    'scholarships' => [
        'types' => 'controllers/scholarships/scholarship-types_controller.php',
        'type-save' => 'controllers/scholarships/scholarship-type-save.php',
        'type-toggle' => 'controllers/scholarships/scholarship-type-toggle.php',
        'type-hard-delete' => 'controllers/scholarships/scholarship-type-hard-delete.php',
        'types-delete-multiple' => 'controllers/scholarships/scholarship-types-delete-multiple.php',
        'rules' => 'controllers/scholarships/scholarship-rules_controller.php',
        'rule-save' => 'controllers/scholarships/scholarship-rule-save.php',
        'rule-toggle' => 'controllers/scholarships/scholarship-rule-toggle.php'
    ],

    // Hostel routes
    'hostel' => [
        'fee-config' => 'controllers/hostel/hostel-fee-config_controller.php',
        'fee-save' => 'controllers/hostel/hostel-fee-save.php',
        'fee-toggle' => 'controllers/hostel/hostel-fee-toggle.php'
    ],

    // Transport routes
    'transport' => [
        'fee-config' => 'controllers/transport/transport-fee-config_controller.php',
        'fee-save' => 'controllers/transport/transport-fee-save.php',
        'fee-toggle' => 'controllers/transport/transport-fee-toggle.php'
    ],


    // Settings routes
    'settings' => [
        'index' => 'controllers/settings/settings_controller.php',

        // User management
        'users' => 'controllers/settings/users_controller.php',
        'user-get' => 'controllers/settings/users_controller.php',
        'user-save' => 'controllers/settings/users_controller.php',
        'user-update' => 'controllers/settings/users_controller.php',
        'user-delete' => 'controllers/settings/users_controller.php',
        'users-delete-multiple' => 'controllers/settings/users_controller.php',

        // Role management
        'roles' => 'controllers/settings/roles_controller.php',
        'role-save' => 'controllers/settings/roles_controller.php',
        'role-delete' => 'controllers/settings/roles_controller.php',
        'roles-delete-multiple' => 'controllers/settings/roles_controller.php',







        // WhatsApp
        'whatsapp' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-templates' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-template-get' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-template-save' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-template-toggle' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-template-delete' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-template-test' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-template-download-csv' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-template-bulk-upload' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-logs' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-log-details' => 'controllers/settings/whatsapp_controller.php',
        'whatsapp-logs-sync' => 'controllers/settings/whatsapp_controller.php',

        // Email
        'email' => 'controllers/settings/email_controller.php',
        'email-templates' => 'controllers/settings/email_controller.php',
        'email-template-get' => 'controllers/settings/email_controller.php',
        'email-template-save' => 'controllers/settings/email_controller.php',
        'email-template-toggle' => 'controllers/settings/email_controller.php',
        'email-template-delete' => 'controllers/settings/email_controller.php',
        'email-template-test' => 'controllers/settings/email_controller.php',
        'email-template-download-csv' => 'controllers/settings/email_controller.php',
        'email-template-bulk-upload' => 'controllers/settings/email_controller.php',
        'email-logs' => 'controllers/settings/email_controller.php',
        'email-log-details' => 'controllers/settings/email_controller.php',

        // Enrollment settings
        'enrollment-settings' => 'controllers/settings/enrollment-settings.php',
        'update-enrollment-settings' => 'controllers/settings/enrollment-settings.php',

        // Academic settings - Academic Years
        'academic-years' => 'controllers/academics/academic-years_controller.php',
        'academic-year-save' => 'controllers/academics/academic-years_controller.php',
        'academic-year-update' => 'controllers/academics/academic-years_controller.php',
        'academic-year-delete' => 'controllers/academics/academic-years_controller.php',
        'academic-year-get' => 'controllers/academics/academic-years_controller.php',
        'academic-years-delete-multiple' => 'controllers/academics/academic-years_controller.php',

        // Boards
        'boards' => 'controllers/academics/boards_controller.php',
        'board-save' => 'controllers/academics/boards_controller.php',
        'board-update' => 'controllers/academics/boards_controller.php',
        'board-delete' => 'controllers/academics/boards_controller.php',
        'boards-delete-multiple' => 'controllers/academics/boards_controller.php',

        // Courses
        'courses' => 'controllers/academics/courses_controller.php',
        'course-save' => 'controllers/academics/courses_controller.php',
        'course-update' => 'controllers/academics/courses_controller.php',
        'course-delete' => 'controllers/academics/courses_controller.php',
        'courses-delete-multiple' => 'controllers/academics/courses_controller.php',

        // Groups
        'groups' => 'controllers/academics/groups_controller.php',
        'group-save' => 'controllers/academics/groups_controller.php',
        'group-update' => 'controllers/academics/groups_controller.php',
        'group-delete' => 'controllers/academics/groups_controller.php',
        'group-delete-multiple' => 'controllers/academics/groups_controller.php',

        // Mediums
        'mediums' => 'controllers/academics/mediums_controller.php',
        'medium-save' => 'controllers/academics/mediums_controller.php',
        'medium-update' => 'controllers/academics/mediums_controller.php',
        'medium-delete' => 'controllers/academics/mediums_controller.php',
        'medium-delete-multiple' => 'controllers/academics/mediums_controller.php',

        // Schools
        'schools' => 'controllers/academics/schools_controller.php',
        'school-save' => 'controllers/academics/schools_controller.php',
        'school-update' => 'controllers/academics/schools_controller.php',
        'school-delete' => 'controllers/academics/schools_controller.php',
        'school-delete-multiple' => 'controllers/academics/schools_controller.php',

        // Campuses
        'campuses' => 'controllers/academics/campuses_controller.php',
        'campus-save' => 'controllers/academics/campuses_controller.php',
        'campus-update' => 'controllers/academics/campuses_controller.php',
        'campus-get' => 'controllers/academics/campuses_controller.php',
        'campus-delete' => 'controllers/academics/campuses_controller.php',

        // Terms
        'terms' => 'controllers/academics/term_controller.php',
        'term-save' => 'controllers/academics/term_controller.php',
        'term-update' => 'controllers/academics/term_controller.php',
        'term-delete' => 'controllers/academics/term_controller.php',
        'term-delete-multiple' => 'controllers/academics/term_controller.php',

        // Divisions
        'divisions' => 'controllers/academics/divisions_controller.php',
        'division-save' => 'controllers/academics/divisions_controller.php',
        'division-update' => 'controllers/academics/divisions_controller.php',
        'division-delete' => 'controllers/academics/divisions_controller.php',
        'divisions-delete-multiple' => 'controllers/academics/divisions_controller.php',

        // Course Divisions
        'course-divisions' => 'controllers/academics/course-division_controller.php',
        'course-division-save' => 'controllers/academics/course-division_controller.php',
        'course-division-update' => 'controllers/academics/course-division_controller.php',
        'course-division-delete' => 'controllers/academics/course-division_controller.php'
    ],

    // Reports routes
    'reports' => [
        'index' => 'controllers/reports/reports_controller.php',
        'list' => 'controllers/reports/reports_controller.php'
    ],

    // Results routes
    'results' => [
        'index' => 'controllers/results/results_controller.php',
        'list' => 'controllers/results/results_controller.php',
        'marks' => 'controllers/results/marks_controller.php'
    ],


    // OMR routes
    'omr' => [
        'sheets' => 'controllers/omr/omr-sheets_controller.php'
    ],

    // Counsellors routes
    'counsellors' => [
        'list' => 'controllers/counsellors/list_controller.php'
    ],

    // Group change routes
    'group-change' => [
        'pending-requests' => 'controllers/group-change/pending-requests_controller.php',
        'index' => 'controllers/group-change/index_controller.php',
        'mark-under-review' => 'controllers/group-change/mark-under-review.php',
        'process-review' => 'controllers/group-change/process-review.php'
    ],

    // Student Portal routes
    'student-portal' => [
        'profile' => 'controllers/student-portal/profile_controller.php',
        'my-results' => 'controllers/student-portal/my-results_controller.php',
        'password-update' => 'controllers/student-portal/password-update.php',
        'appointment-save' => 'controllers/student-portal/appointment-save.php',
        'change-group-request-save' => 'controllers/student-portal/change-group-request-save.php'
    ],

    // Website routes
    'website' => [
        'ajax-save' => 'controllers/website/ajax_save.php'
    ],

    // Test Management routes
    'test-management' => [
        'subjects' => 'controllers/test-management/subjects_controller.php',
        'subjects-topics' => 'controllers/test-management/subjects-topics_controller.php',
        'paper-sets' => 'controllers/test-management/paper-sets_controller.php',
        'subject-save' => 'controllers/test-management/subject-save.php',
        'subject-delete' => 'controllers/test-management/subject-delete.php',
        'subject-toggle-status' => 'controllers/test-management/subject-toggle-status.php',
        'topic-save' => 'controllers/test-management/topic-save.php',
        'topic-delete' => 'controllers/test-management/topic-delete.php',
        'topic-toggle-status' => 'controllers/test-management/topic-toggle-status.php',
        'paper-set-save' => 'controllers/test-management/paper-set-save.php',
        'blueprint-topic-save' => 'controllers/test-management/blueprint-topic-save.php',
        'blueprint-question-save' => 'controllers/test-management/blueprint-question-save.php',
        'blueprint-question-update' => 'controllers/test-management/blueprint-question-update.php',
        'blueprint-save-final' => 'controllers/test-management/blueprint-save-final.php',
        'answer-key-save' => 'controllers/test-management/answer-key-save.php',
        'get-topics-by-subject' => 'controllers/test-management/get-topics-by-subject.php'
    ],


    // Common utility routes
    'common' => [
        'get-student-fee-config' => 'controllers/common/get-student-fee-config.php'
    ]
];

// Find and include the controller file
$controllerFile = null;

if (isset($routeMap[$module])) {
    if (isset($routeMap[$module][$action])) {
        $controllerFile = $routeMap[$module][$action];
    } elseif ($action === 'index' && isset($routeMap[$module]['index'])) {
        $controllerFile = $routeMap[$module]['index'];
    }
}

// Include the controller file or return 404.
// Only routes explicitly defined in $routeMap above are served.
// The realpath() check below additionally enforces that the resolved path
// sits inside __DIR__, preventing any symlink or traversal escape.
if ($controllerFile) {
    $resolvedController = realpath(__DIR__ . '/' . $controllerFile);
    $resolvedBase       = realpath(__DIR__);
    if (
        $resolvedController !== false &&
        $resolvedBase       !== false &&
        str_starts_with($resolvedController, $resolvedBase . DIRECTORY_SEPARATOR) &&
        pathinfo($resolvedController, PATHINFO_EXTENSION) === 'php' &&
        file_exists($resolvedController)
    ) {
        require_once $resolvedController;
    } else {
        sendErrorResponse('Route not found: ' . $route, 404);
    }
} else {
    sendErrorResponse('Route not found: ' . $route, 404);
}

/**
 * Get list of available routes for API documentation
 */
function getAvailableRoutes(): array
{
    return [
        'dashboard' => [
            'GET /index.php?route=dashboard/admin' => 'Admin Dashboard',
            'GET /index.php?route=dashboard/principle' => 'Principal Dashboard',
            'GET /index.php?route=dashboard/counsellor' => 'Counsellor Dashboard',
            'GET /index.php?route=dashboard/accountant' => 'Accountant Dashboard',
            'GET /index.php?route=dashboard/student' => 'Student Dashboard'
        ],
        'students' => [
            'GET /index.php?route=students/list' => 'List all students',
            'GET /index.php?route=students/details' => 'Get student details',
            'POST /index.php?route=students/save' => 'Add new student',
            'POST /index.php?route=students/update' => 'Update student',
            'GET /index.php?route=students/enrolled' => 'List enrolled students',
            'GET /index.php?route=students/registered' => 'List registered students'
        ],
        'fees' => [
            'GET /index.php?route=fees/config' => 'Fee configuration',
            'POST /index.php?route=fees/config-delete-multiple' => 'Delete multiple fee configurations',
            'GET /index.php?route=fees/list' => 'List fees',
            'GET /index.php?route=fees/structure' => 'Fee structure'
        ],
        'payments' => [
            'POST /index.php?route=payments/initiate' => 'Initiate payment',
            'POST /index.php?route=payments/callback' => 'Payment callback',
            'GET /index.php?route=payments/history' => 'Payment history',
            'GET /index.php?route=payments/receipt' => 'Get receipt'
        ],
        'settings' => [
            'GET /index.php?route=settings/index' => 'Settings overview',
            'GET /index.php?route=settings/academic-years' => 'Academic years',
            'GET /index.php?route=settings/boards' => 'Manage boards',
            'GET /index.php?route=settings/courses' => 'Manage courses',
            'GET /index.php?route=settings/groups' => 'Manage groups',
            'GET /index.php?route=settings/mediums' => 'Manage mediums',
            'GET /index.php?route=settings/schools' => 'Manage schools'
        ],
        'profile' => [
            'GET /index.php?route=profile/view' => 'View profile',
            'GET /index.php?route=profile/edit' => 'Edit profile',
            'POST /index.php?route=profile/update' => 'Update profile'
        ]
    ];
}
