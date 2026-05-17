<?php
/**
 * Parent Account Management
 * Allows Admins/Principals to manage parent login credentials and view linked children.
 */

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPERS_PATH . 'flash_message.php';
require_once PAGINATION_FILE;

// Check access - Only Super Admin and Principal
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = 'Manage Parent Accounts';
$page_breadcrumb = 'Parent Portal - Management';

// Handle Reset Password Action
if (isset($_GET['action']) && $_GET['action'] === 'reset' && isset($_GET['id'])) {
    $parent_id = intval($_GET['id']);
    try {
        // Fetch mobile number to use as default password
        $stmt = $conn->prepare("SELECT mobile_number FROM tbl_parent_login WHERE id = ?");
        $stmt->execute([$parent_id]);
        $parent = $stmt->fetch();

        if ($parent) {
            $new_password = $parent['mobile_number'];
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE tbl_parent_login SET password = ? WHERE id = ?");
            $update->execute([$hashed, $parent_id]);
            set_flash_message('success', "Password reset to mobile number successfully for parent: " . $parent['mobile_number']);
        } else {
            set_flash_message('error', "Parent account not found.");
        }
    } catch (PDOException $e) {
        set_flash_message('error', "Database error: " . $e->getMessage());
    }
    header('Location: manage-parents.php');
    exit;
}

// Logic for Tabs
$tab = $_GET['tab'] ?? 'active';

// Pagination settings
$perPage = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Fetch all parent accounts
$parents = [];
$totalRecords = 0;
$totalPages = 0;

if ($tab === 'active') {
    try {
        // 1. Get Total Count
        $countSql = "SELECT COUNT(*) FROM tbl_parent_login";
        $totalRecords = $conn->query($countSql)->fetchColumn();
        $totalPages = ceil($totalRecords / $perPage);

        // 2. Main Query
        $sql = "SELECT p.*, 
                (SELECT COUNT(*) FROM tbl_gm_std_registration s WHERE s.parent_mob = p.mobile_number) as child_count,
                (SELECT GROUP_CONCAT(CONCAT(surname, ' ', student_name, ' ', IFNULL(fathers_name, '')) SEPARATOR ', ') 
                 FROM tbl_gm_std_registration s 
                 WHERE s.parent_mob = p.mobile_number) as children_names
                FROM tbl_parent_login p
                ORDER BY p.created_at ASC
                LIMIT $perPage OFFSET $offset";
        $stmt = $conn->query($sql);
        $parents = $stmt->fetchAll();
    } catch (PDOException $e) {
        set_flash_message('error', "Database error: " . $e->getMessage());
    }
}

// Fetch pending students (those who don't have a parent account yet)
$pending_students = [];
if ($tab === 'pending') {
    try {
        // 1. Get Total Count
        $countSql = "SELECT COUNT(*) FROM tbl_gm_std_registration s 
                    WHERE s.parent_mob IS NOT NULL AND s.parent_mob != ''
                    AND s.parent_mob NOT IN (SELECT mobile_number FROM tbl_parent_login)";
        $totalRecords = $conn->query($countSql)->fetchColumn();
        $totalPages = ceil($totalRecords / $perPage);

        // 2. Main Query
        $sql = "SELECT s.id, s.surname, s.student_name, s.fathers_name, s.mob, s.parent_mob, c.course_name, s.created_at as admission_date
                FROM tbl_gm_std_registration s
                LEFT JOIN tbl_courses c ON s.course_id = c.id
                WHERE s.parent_mob IS NOT NULL AND s.parent_mob != ''
                AND s.parent_mob NOT IN (SELECT mobile_number FROM tbl_parent_login)
                ORDER BY s.created_at ASC
                LIMIT $perPage OFFSET $offset";
        $stmt = $conn->query($sql);
        $pending_students = $stmt->fetchAll();
    } catch (PDOException $e) {
        set_flash_message('error', "Database error: " . $e->getMessage());
    }
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<main class="app-main">
    <section class="content">
        <div class="container-fluid">
            <!-- Action Buttons for Admins -->
            <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
                <div class="d-flex justify-content-end gap-2 mb-3">
                    <a href="sync-siblings.php" class="btn btn-info" target="_blank"
                        onclick="return confirm('This will automatically identify and link siblings based on Surname, Father\'s Name, and Address. Proceed?')">
                        <i class="fas fa-users"></i> Sync Siblings
                    </a>
                    <a href="sync-parents-process.php" class="btn btn-primary"
                        onclick="return confirm('This will ensure parent accounts exist for all registered students. Proceed?')">
                        <i class="fas fa-sync"></i> Sync All Parents
                    </a>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="card card-primary card-outline card-tabs shadow-sm border-0">
                <div class="card-header p-0 pt-1 border-bottom-0">
                    <ul class="nav nav-tabs" id="parentTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $tab === 'active' ? 'active' : ''; ?>"
                                href="manage-parents.php?tab=active">
                                <i class="fas fa-user-check mr-1"></i> Active Parent Accounts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $tab === 'pending' ? 'active' : ''; ?>"
                                href="manage-parents.php?tab=pending">
                                <i class="fas fa-clock mr-1"></i> Pending Students (Mapping Needed)
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <?php if ($tab === 'active'): ?>
                        <!-- Active Parents Table -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Mobile Number (Login ID)</th>
                                        <th>Linked Children</th>
                                        <th>Count</th>
                                        <th>Created At</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($parents)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="fas fa-users-slash fa-3x mb-3"></i>
                                                <p>No parent accounts found.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($parents as $p): ?>
                                            <tr>
                                                <td><?php echo $p['id']; ?></td>
                                                <td class="fw-bold text-primary">
                                                    <i class="fas fa-phone-alt mr-2 text-muted"></i>
                                                    <?php echo $p['mobile_number']; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted d-block" style="max-width: 300px;">
                                                        <?php echo $p['children_names'] ?: '<span class="text-danger">No children linked</span>'; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $p['child_count']; ?></span>
                                                </td>
                                                <td><?php echo date('d-m-Y H:i', strtotime($p['created_at'])); ?></td>
                                                <td class="text-right">
                                                    <a href="manage-parents.php?action=reset&id=<?php echo $p['id']; ?>"
                                                        class="btn btn-sm btn-outline-warning"
                                                        onclick="return confirm('Are you sure you want to reset the password to the default (mobile number)?')"
                                                        title="Reset Password">
                                                        <i class="fas fa-key"></i> Reset Password
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <?php if ($totalPages > 1 || $totalRecords > 0): ?>
                            <div class="card-footer bg-transparent border-top p-3">
                                <?php echo renderPagination($page, $totalPages, 'manage-parents.php?tab=active', 2, $totalRecords, 'accounts'); ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Pending Students Table -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Student Name</th>
                                        <th>Student Mob</th>
                                        <th>Parent Mob (Login)</th>
                                        <th>Standard</th>
                                        <th>Admission Date</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pending_students)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted">
                                                <i class="fas fa-user-check fa-3x mb-3"></i>
                                                <p>All students have parent accounts mapped.</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_students as $s): ?>
                                            <tr>
                                                <td><?php echo $s['id']; ?></td>
                                                <td class="fw-bold">
                                                    <?php echo $s['surname'] . ' ' . $s['student_name'] . ' ' . $s['fathers_name']; ?>
                                                </td>
                                                <td><?php echo $s['mob']; ?></td>
                                                <td class="text-primary fw-bold"><?php echo $s['parent_mob']; ?></td>
                                                <td><?php echo $s['course_name']; ?></td>
                                                <td><?php echo $s['admission_date'] ? date('d-m-Y', strtotime($s['admission_date'])) : 'N/A'; ?>
                                                </td>
                                                <td class="text-right">
                                                    <a href="sync-single-parent.php?mobile=<?php echo urlencode($s['parent_mob']); ?>"
                                                        class="btn btn-sm btn-success">
                                                        <i class="fas fa-plus"></i> Create Link/Login
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <?php if ($totalPages > 1 || $totalRecords > 0): ?>
                            <div class="card-footer bg-transparent border-top p-3">
                                <?php echo renderPagination($page, $totalPages, 'manage-parents.php?tab=pending', 2, $totalRecords, 'students'); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include '../../include/footer.php'; ?>