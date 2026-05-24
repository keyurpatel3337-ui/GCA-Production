<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Certificate History";
$page_breadcrumb = [
    'Home' => BASE_URL . '/portal/index.php',
    'Certificates' => ''
];

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';

$op = new Operation();

// Fetch issued certificates with student details
$query = "SELECT ic.*, 
          CONCAT(s.surname, ' ', s.student_name, ' ', IFNULL(s.fathers_name, '')) as student_full_name,
          s.mob as student_mobile,
          u.name as issued_by_name
          FROM tbl_issued_certificates ic
          LEFT JOIN tbl_gm_std_registration s ON ic.student_id = s.id
          LEFT JOIN tbl_users u ON ic.issued_by = u.id
          ORDER BY ic.created_at ASC";
$issuedCertificates = $op->customSelect($query);

?>

<div class="block-header">
    <div class="row">
        <div class="col-lg-7 col-md-6 col-sm-12">
            <h2><?php echo $page_title; ?></h2>
            <ul class="breadcrumb">
                <?php foreach ($page_breadcrumb as $label => $link): ?>
                    <?php if ($link): ?>
                        <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo $label; ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active"><?php echo $label; ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="col-lg-5 col-md-6 col-sm-12 text-end">
            <a href="issue.php" class="btn btn-primary btn-round shadow-sm">
                <i class="fas fa-plus-circle me-1"></i> ISSUE NEW CERTIFICATE
            </a>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row clearfix">
        <div class="col-lg-12">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-bottom-0">
                    <h5 class="mb-0 fw-bold text-dark">Recently Issued Records</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="issuedTable">
                            <thead class="bg-light text-muted uppercase small">
                                <tr>
                                    <th class="border-0">Date</th>
                                    <th class="border-0">Serial Number</th>
                                    <th class="border-0">Student Name</th>
                                    <th class="border-0">Certificate Type</th>
                                    <th class="border-0">Issued By</th>
                                    <th class="border-0 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($issuedCertificates)): ?>
                                    <?php foreach ($issuedCertificates as $cert): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span
                                                        class="fw-bold text-dark"><?php echo date('d M Y', strtotime($cert['issued_date'])); ?></span>
                                                    <small
                                                        class="text-muted"><?php echo date('h:i A', strtotime($cert['created_at'])); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span
                                                    class="badge bg-soft-info text-info border border-info border-opacity-25 px-2 py-1">
                                                    <?php echo $cert['serial_number']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar avatar-sm bg-blue-100 text-blue-600 rounded-circle me-3 d-flex align-items-center justify-content-center css-index-d88edd">
                                                        <?php echo strtoupper(substr($cert['student_full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="d-flex flex-column">
                                                        <span
                                                            class="fw-bold text-dark"><?php echo $cert['student_full_name']; ?></span>
                                                        <small class="text-muted">ID: <?php echo $cert['student_id']; ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-dark fw-medium">
                                                    <i class="fas fa-file-alt text-primary opacity-50 me-1"></i>
                                                    <?php echo $cert['certificate_type']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-muted small"><?php echo $cert['issued_by_name']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group shadow-none">
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-secondary rounded-pill px-3"
                                                        onclick="reprintCertificate('<?php echo strtolower(str_replace(' ', '_', $cert['certificate_type'])); ?>', <?php echo $cert['student_id']; ?>)">
                                                        <i class="fas fa-print me-1"></i> Re-print
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="opacity-25 mb-3">
                                                <i class="fas fa-history fa-3x"></i>
                                            </div>
                                            <p class="text-muted mb-0">No certificates issued yet.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    function reprintCertificate(type, studentId) {
        // Map common naming to what generate.php expects
        let certType = type;
        if (type === 'fees_paid_certificate') certType = 'fees_paid';
        if (type === 'school_leaving_certificate_(slc)_/_tc') certType = 'slc';
        if (type === 'attempt_/_trial_certificate') certType = 'attempt';

        // Use a form post to target _blank and hit generate.php
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'generate.php';
        form.target = '_blank';

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'certificate_type';
        typeInput.value = certType;
        form.appendChild(typeInput);

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'student_id';
        idInput.value = studentId;
        form.appendChild(idInput);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    $(document).ready(function () {
        if ($('#issuedTable').length > 0) {
            // Optional: Initialize DataTable here
        }
    });
</script>

