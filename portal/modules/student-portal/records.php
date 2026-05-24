<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Student (either regular login or student-specific login)
$is_student_login = isset($_SESSION['is_student_login']) && $_SESSION['is_student_login'] === true;
$student_id = $is_student_login ? $_SESSION['student_id'] : ($_SESSION['user_id'] ?? null);

if (!$is_student_login && !hasRole(ROLE_STUDENT)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "My Records";
$page_breadcrumb = "Records";

// Get student details with joins for descriptive names
try {
    $op = new Operation();
    $student = $op->readWithJoin(
        'tbl_gm_std_registration s',
        [
            's.*',
            'b.board_name',
            'm.medium_name',
            'g.group_name',
            'c.course_name',
            'cp.campus_name',
            'ay.year_name as academic_year'
        ],
        [
            ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
            ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
            ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 's.course_id = c.id'],
            ['type' => 'LEFT', 'table' => 'tbl_campuses cp', 'on' => 's.campus_id = cp.id'],
            ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id']
        ],
        ['s.id' => $student_id]
    );

    if (!$student) {
        $student = [];
    }
} catch (Exception $e) {
    logDatabaseError($e, "Fetch Student Details");
    $student = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="content-header p-0 mb-4">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h1 class="m-0 fw-bold text-dark">My Personal Portfolio</h1>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid records-container pb-5">
    <div class="row">
        <!-- Sidebar Column -->
        <div class="col-lg-3 mb-4">
            <div class="profile-sidebar sticky-top css-records-dc1c6c">
                <div class="avatar-section">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['surname'] . ' ' . $student['student_name']); ?>&size=128&background=random&font-size=0.4" 
                         alt="Profile" class="rounded-circle avatar-img shadow">
                    <h4 class="fw-bold mb-1 text-white"><?php echo htmlspecialchars($student['surname'] ?? ''); ?></h4>
                    <p class="mb-0 opacity-75 small"><?php echo htmlspecialchars($student['student_name'] . ' ' . $student['fathers_name'] ?? ''); ?></p>
                </div>
                <div class="p-4">
                    <div class="mb-3">
                        <div class="info-label text-white-50">Current Course</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="info-label text-white-50">Campus</div>
                        <div class="fw-bold text-truncate"><?php echo htmlspecialchars($student['campus_name'] ?? 'Not Set'); ?></div>
                    </div>
                    <hr class="border-light opacity-10 my-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="small opacity-50">Status</span>
                        <span class="badge <?php echo ($student['status'] == 1) ? 'bg-success' : 'bg-danger'; ?> rounded-pill px-3 py-1 text-white">
                            <?php echo ($student['status'] == 1) ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                    <button class="btn btn-outline-light w-100 rounded-pill mb-2 py-2" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="fas fa-edit me-2"></i> Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content Column -->
        <div class="col-lg-9">
            <!-- Personal Information -->
            <div class="detail-card p-4 mb-4 shadow-sm border-0">
                <h5 class="section-title">Core Identity</h5>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars(($student['surname'] ?? '') . ' ' . ($student['student_name'] ?? '') . ' ' . ($student['fathers_name'] ?? '')); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo isset($student['dob']) ? date('d M, Y', strtotime($student['dob'])) : 'N/A'; ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><?php echo ucfirst($student['gender'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Aadhaar Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['aadhaar'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Mobile</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['mob'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">Alt. Mobile</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['amob'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <!-- Academic Information -->
                <div class="col-md-7">
                    <div class="detail-card p-4 h-100 shadow-sm border-0">
                        <h5 class="section-title">Academic Record</h5>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="info-label">Board</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['board_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="col-sm-6">
                                <div class="info-label">Medium</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['medium_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label">Standard / Group</div>
                                <div class="info-value"><?php echo htmlspecialchars(($student['standard'] ?? 'N/A') . 'th / ' . ($student['group_name'] ?? 'N/A')); ?></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label">Previous School</div>
                                <div class="info-value"><?php echo htmlspecialchars($student['schoolname'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="col-12">
                                <div class="info-label">School Address</div>
                                <div class="info-value small opacity-75"><?php echo htmlspecialchars($student['schaddr'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Residence Information -->
                <div class="col-md-5">
                    <div class="detail-card p-4 h-100 shadow-sm border-0">
                        <h5 class="section-title">Contact & Residence</h5>
                        <div class="mb-3">
                            <div class="info-label">Address</div>
                            <div class="info-value small"><?php echo htmlspecialchars($student['addr'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">District</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['district'] ?? 'N/A'); ?></div>
                        </div>
                        <div class="mb-1">
                            <div class="info-label">Hostel Facility</div>
                            <div class="info-value">
                                <?php if (($student['hostel_required'] ?? 'No') == 'Yes'): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3">Required</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary bg-opacity-10 text-muted border border-secondary rounded-pill px-3">Not Required</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Father's Information -->
            <div class="detail-card p-4 shadow-sm border-0">
                <h5 class="section-title">Guardian Details</h5>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="info-label">Father's Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['fathername'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Education</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['fatheredu'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="info-label">Occupation</div>
                        <div class="info-value"><?php echo htmlspecialchars($student['ocupation'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-12">
                        <div class="info-label">Office Address</div>
                        <div class="info-value small opacity-75"><?php echo htmlspecialchars($student['ofcaddr'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <div class="mt-4 p-3 bg-light rounded-3 d-flex align-items-center">
                <i class="fas fa-info-circle text-info me-3 fs-4"></i>
                <div class="small text-muted">
                    If you need to update any information, please contact your counsellor or the administration office directly.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow rounded-4">
            <div class="modal-header border-0 bg-primary text-white p-4">
                <h5 class="modal-title fw-bold" id="editProfileModalLabel">
                    <i class="fas fa-user-edit me-2"></i> Update Your Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProfileForm">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 small mb-4">
                        <i class="fas fa-info-circle me-2"></i> Only contact and guardian details can be updated. For academic changes, please contact the office.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">Alternate Mobile</label>
                            <input type="text" class="form-control rounded-3" name="amob" maxlength="10" 
                                   value="<?php echo htmlspecialchars($student['amob'] ?? ''); ?>" placeholder="10-digit mobile number">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">District</label>
                            <input type="text" class="form-control rounded-3" name="district" 
                                   value="<?php echo htmlspecialchars($student['district'] ?? ''); ?>" placeholder="Your district">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-bold">Present Address</label>
                            <textarea class="form-control rounded-3" name="addr" rows="2" 
                                      placeholder="Full residential address"><?php echo htmlspecialchars($student['addr'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <h6 class="text-primary fw-bold small text-uppercase mb-3">Guardian Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">Father's Education</label>
                            <input type="text" class="form-control rounded-3" name="fatheredu" 
                                   value="<?php echo htmlspecialchars($student['fatheredu'] ?? ''); ?>" placeholder="Education Level">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-bold">Father's Occupation</label>
                            <input type="text" class="form-control rounded-3" name="ocupation" 
                                   value="<?php echo htmlspecialchars($student['ocupation'] ?? ''); ?>" placeholder="Job Title / Business">
                        </div>
                        <div class="col-12">
                            <label class="form-label text-muted small fw-bold">Father's Office Address</label>
                            <textarea class="form-control rounded-3" name="ofcaddr" rows="2" 
                                      placeholder="Office or Business Address"><?php echo htmlspecialchars($student['ofcaddr'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editProfileForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
    
    fetch('<?php echo BASE_URL; ?>/counselling-backend/controllers/students/update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('success', 'Profile Updated', data.message);
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('error', 'Update Failed', data.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        showToast('error', 'Error', 'An error occurred. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});
</script>
