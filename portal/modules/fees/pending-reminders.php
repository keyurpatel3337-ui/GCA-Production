<?php
header('Content-Type: text/html; charset=utf-8');
define('APP_INIT', true);
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once PAGINATION_FILE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_helper.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/fee_allocation_helper.php';

// Auth check
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    header("Location: " . BASE_URL . "/dashboard.php");
    exit;
}

// Handle POST pagination and filters
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['clear_filter'])) {
        unset($_SESSION['pending_reminders_filters']);
        $_SESSION['pending_reminders_pagination']['page'] = 1;
    } elseif (isset($_POST['apply_filter'])) {
        $_SESSION['pending_reminders_filters'] = [
            'search' => $_POST['search'] ?? '',
            'course_id' => $_POST['course_id'] ?? ''
        ];
        $_SESSION['pending_reminders_pagination']['page'] = 1;
    } elseif (isset($_POST['page'])) {
        $page = $_POST['page'] ?? 1;
        $perPage = $_POST['per_page'] ?? ($_SESSION['pending_reminders_pagination']['per_page'] ?? 25);
        $_SESSION['pending_reminders_pagination'] = [
            'page' => $page,
            'per_page' => $perPage
        ];
    } elseif (isset($_POST['selected_ids'])) {
        $selected_student_ids = array_unique($_POST['selected_ids']);
        if (!empty($selected_student_ids)) {
            require_once HELPERS_PATH . 'notification_functions.php';
            require_once HELPERS_PATH . 'fee_helper.php';
            require_once DB_CONNECT_FILE;

            $sent_count = 0;
            foreach ($selected_student_ids as $sid) {
                // Fetch student details and their pending amount from the already joined table
                $stmt = $conn->prepare("SELECT s.student_name, s.surname, s.fathers_name, s.email, s.mob, c.course_name, pp.amount as pending_amt, pp.updated_at as due_date 
                                      FROM tbl_gm_std_registration s 
                                      LEFT JOIN tbl_courses c ON s.course_id = c.id
                                      JOIN tbl_pending_payments pp ON s.id = pp.student_id
                                      WHERE s.id = ? AND pp.status = 'pending' LIMIT 1");
                $stmt->execute([$sid]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($student) {
                    $pending = $student['pending_amt'] ?? 0;

                    if ($pending > 0) {
                        $recipient = [
                            'name' => $student['student_name'] . ' ' . $student['surname'],
                            'email' => $student['email'],
                            'mobile' => $student['mob']
                        ];

                        $fullName = trim($student['surname'] . ' ' . $student['student_name'] . ' ' . ($student['fathers_name'] ?? ''));
                        $customMessage = "your pending fee of ₹" . formatIndianCurrency($pending);

                        // Support both indexed (WhatsApp) and named (Email) variables
                        $variables = [
                            0 => $customMessage, // {{1}} for WhatsApp
                            1 => $fullName,      // {{2}} for WhatsApp
                            'student_name' => $student['student_name'],
                            'amount' => formatIndianCurrency($pending),
                            'due_date' => date('d-M-Y'),
                            'course_name' => $student['course_name'] ?? 'N/A',
                            'installment_number' => 'N/A', // Could be calculated if needed
                            'days_remaining' => ceil((strtotime($student['due_date'] ?? date('Y-m-d')) - time()) / 86400),
                            'payment_url' => PORTAL_URL,
                            'portal_url' => PORTAL_URL
                        ];

                        $options = ['student_id' => $sid, 'reference_type' => 'fee', 'reference_id' => $sid];

                        $res = sendNotification($conn, 'fee_reminder', $recipient, $variables, $options);
                        if ($res['whatsapp']['success'] || $res['email']['success']) {
                            $sent_count++;
                        }
                    }
                }
            }
            $_SESSION['flash_message'] = [
                'type' => 'success',
                'message' => "Successfully sent reminders to $sent_count students."
            ];
        }
    }

    // Redirect to self to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$api = new APIClient();
$courses = [];
$courseResponse = $api->get('settings/courses');
if ($courseResponse && isset($courseResponse['success']) && $courseResponse['success']) {
    $courses = $courseResponse['data']['courses'] ?? [];
}

// Get filters and pagination from session
$filters = $_SESSION['pending_reminders_filters'] ?? [
    'search' => '',
    'course_id' => ''
];

$paginationParams = $_SESSION['pending_reminders_pagination'] ?? [
    'page' => 1,
    'per_page' => 25
];

$page = $paginationParams['page'];
$perPage = $paginationParams['per_page'];

$requestParams = array_merge($filters, [
    'page' => $page,
    'per_page' => $perPage
]);

$response = $api->get('fees/pending-reminders', $requestParams);

if ($response && isset($response['success']) && $response['success']) {
    $pending_list = $response['data']['pending_list'] ?? [];

    // Pagination
    $pagination = $response['data']['pagination'] ?? [];
    $page = $pagination['current_page'] ?? $page;
    $perPage = $pagination['per_page'] ?? $perPage;
    $totalRecords = $pagination['total_records'] ?? ($response['data']['total'] ?? count($pending_list));
    $totalPages = $pagination['total_pages'] ?? 1;
} else {
    $pending_list = [];
    $totalRecords = 0;
    $totalPages = 1;
    set_flash_message('error', $response['error'] ?? 'Failed to load pending reminders');
}

$msg = $_SESSION['message'] ?? null;
$msg_type = $_SESSION['message_type'] ?? null;
unset($_SESSION['message'], $_SESSION['message_type']);

// Handle Send Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminders'])) {
    $response = $api->post('fees/pending-reminders', $_POST);
    if ($response && isset($response['success']) && $response['success']) {
        $msg = $response['message'] ?? 'Reminders sent successfully';
        $msg_type = 'success';
    } else {
        $msg = $response['error'] ?? 'Failed to send reminders';
        $msg_type = 'danger';
    }
}

$page_title = "Pending Fee Reminders";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <?php
    if (isset($msg)): ?>
            <div class="alert alert-<?php
            echo $msg_type; ?>"><?php
              echo $msg; ?></div>
            <?php
    endif; ?>


    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Search Student</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                        placeholder="Name or Mobile..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Course / Standard</label>
                    <select name="course_id" class="form-select form-select-sm">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo $filters['course_id'] == $course['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5 text-end">
                    <button type="submit" name="apply_filter" class="btn btn-sm btn-primary">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <button type="submit" name="clear_filter" class="btn btn-sm btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="card-title">Pending Due Payments (<?php echo $totalRecords; ?>)</h3>
                <button type="button" name="send_reminders" class="btn btn-sm btn-success">
                    <i class="fab fa-whatsapp"></i> Send Reminders
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <form method="POST" id="reminderForm">
                <input type="hidden" name="send_reminders" value="1">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 40px"><input type="checkbox" id="selectAll"></th>
                                <th>Student</th>
                                <th>Standard</th>
                                <th>Fee Type</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Days Overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_list)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No pending payments found.</td>
                                    </tr>
                            <?php else: ?>
                                    <?php
                                    foreach ($pending_list as $item): ?>
                                            <?php
                                            $dueDateStr = $item['due_date'] ?? '';
                                            $due = $dueDateStr ? strtotime($dueDateStr) : time();
                                            $today = time();
                                            $days = floor(($today - $due) / (60 * 60 * 24));
                                            ?>
                                            <tr>
                                                <td><input type="checkbox" name="selected_ids[]" value="<?php
                                                echo $item['student_id']; ?>" class="row-check"></td>
                                                <td><?php
                                                echo htmlspecialchars($item['surname'] . ' ' . $item['student_name'] ?? ''); ?>
                                                </td>
                                                <td><?php
                                                echo htmlspecialchars($item['course_name'] ?? ''); ?></td>
                                                <td><?php
                                                echo ucwords(str_replace('_', ' ', $item['payment_type'])); ?></td>
                                                <td>
                                                    ₹<?php echo formatIndianCurrency($item['actual_pending'] ?? $item['amount']); ?>
                                                    <?php if (isset($item['actual_pending']) && floatval($item['actual_pending']) != floatval($item['amount'])): ?>
                                                            <br><small class="text-muted" title="Original Reminder Amount">(Orig: ₹<?php echo formatIndianCurrency($item['amount']); ?>)</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php
                                                echo $dueDateStr ? date('d-M-Y', strtotime($dueDateStr)) : 'N/A'; ?></td>
                                                <td>
                                                    <span class="badge bg-danger"><?php echo $days; ?> Days</span>
                                                    <a href="../students/details.php?id=<?php echo $item['student_id']; ?>&tab=ledger" target="_blank" class="btn btn-xs btn-link p-0" title="View Ledger">
                                                        <i class="fas fa-file-invoice"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php
                                    endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Pagination Controls -->
            <?php if ($totalRecords > 0): ?>
                    <div class="p-3 border-top">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                <?php echo getPaginationInfo($page, $perPage, $totalRecords); ?>
                            </div>
                            <?php if ($totalPages > 1): ?>
                                    <?php
                                    echo renderPaginationPost($page, $totalPages);
                                    ?>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.getElementById('selectAll').addEventListener('change', function () {
        var checkboxes = document.querySelectorAll('.row-check');
        for (var checkbox of checkboxes) {
            checkbox.checked = this.checked;
        }
    });

    // Handle form submission via the external button
    const formBtn = document.querySelector('button[name="send_reminders"]');
    if (formBtn) {
        formBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (!confirm('Send WhatsApp reminders to selected students?')) return;

            // Check if any selected
            const checked = document.querySelectorAll('.row-check:checked');
            if (checked.length === 0) {
                alert('Please select at least one student.');
                return;
            }

            document.getElementById('reminderForm').submit();
        });
    }
</script>

<?php
include '../../include/footer.php'; ?>