<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once OPERATION_FILE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Counsellor, Principle or Super Admin
if (!hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
  header('Location: ../dashboard/dashboard.php');
  exit;
}

$page_title = "Counselling Sessions";
$page_breadcrumb = "Sessions -";
$counsellor_id = $_SESSION['user_id'];

// Get student-wise session summary for this counsellor
try {
  $sql = "SELECT 
                                s.id as student_id,
                                s.surname, 
                                s.student_name, 
                                s.mob,
                                COUNT(cs.id) as session_count,
                                MAX(cs.session_date) as last_session_date,
                                (SELECT session_topic FROM tbl_sessions WHERE student_id = s.id ORDER BY session_date DESC, id desc LIMIT 1) as latest_topic
                           FROM tbl_sessions cs
                           INNER JOIN tbl_gm_std_registration s ON cs.student_id = s.id
                           WHERE cs.counsellor_id = ?
                           GROUP BY s.id
                           ORDER BY last_session_date ASC";
  $sessions_summary = $dbOps->customSelect($sql, [$counsellor_id]);
} catch (PDOException $e) {
  logDatabaseError($e, "Fetch Counsellor Session Summary");
  $sessions_summary = [];
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




<div class="container-fluid">

</div>

<?php include '../../include/footer.php'; ?>

<script>
  $(document).ready(function () {
    // Initialize DataTable if present
    if ($.fn.DataTable && $('#sessionsTable').length) {
      $('#sessionsTable').DataTable({
        "pageLength": 25,
        "order": [
          [4, "desc"]
        ], // Sort by last session date
        "language": {
          "search": "Search sessions:",
          "lengthMenu": "Show _MENU_ sessions",
          "info": "Showing _START_ to _END_ of _TOTAL_ students",
          "infoEmpty": "No sessions found",
          "infoFiltered": "(filtered from _MAX_ total students)"
        }
      });
    }
  });
</script>