<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Get student ID
$student_id = $_REQUEST['id'] ?? $_REQUEST['student_id'] ?? 0;

if (!$student_id) {
    set_flash_message('error', 'Student ID is required');
    header('Location: students.php?view=all');
    exit;
}

// Fetch student details directly from database
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
            'ay.year_name as academic_year',
            'u.name as counsellor_name',
            'u.email as counsellor_email',
            'u.phone as counsellor_phone',
            'sch.school_name as current_school_name',
            'cp.campus_name'
        ],
        [
            ['type' => 'LEFT', 'table' => 'tbl_boards b', 'on' => 's.board_id = b.id'],
            ['type' => 'LEFT', 'table' => 'tbl_medium m', 'on' => 's.medium_id = m.id'],
            ['type' => 'LEFT', 'table' => 'tbl_group g', 'on' => 's.group_id = g.id'],
            ['type' => 'LEFT', 'table' => 'tbl_courses c', 'on' => 's.course_id = c.id'],
            ['type' => 'LEFT', 'table' => 'tbl_academic_years ay', 'on' => 's.academic_year_id = ay.id'],
            ['type' => 'LEFT', 'table' => 'tbl_users u', 'on' => 's.counsellor_id = u.id'],
            ['type' => 'LEFT', 'table' => 'tbl_schools sch', 'on' => 's.school_id = sch.id'],
            ['type' => 'LEFT', 'table' => 'tbl_campuses cp', 'on' => 's.campus_id = cp.id']
        ],
        ['s.id' => $student_id]
    );

    if (!$student) {
        set_flash_message('error', 'Student not found');
        header('Location: students.php?view=all');
        exit;
    }

    // Fetch enrollment data if exists
    $enrollment = $op->readWithJoin(
        'tbl_enrolled_students e',
        ['e.*', 'sch.school_name', 'd.division_name'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_gm_std_registration r', 'on' => 'e.registration_id = r.id'],
            ['type' => 'LEFT', 'table' => 'tbl_schools sch', 'on' => 'r.school_id = sch.id'],
            ['type' => 'LEFT', 'table' => 'tbl_division d', 'on' => 'e.division_id = d.id']
        ],
        ['e.registration_id' => $student_id]
    );

    // Fetch test results
    $test_results = $op->readWithJoin(
        'tbl_test_results tr',
        ['tr.*', 'ps.paper_set_name'],
        [
            ['type' => 'LEFT', 'table' => 'tbl_paper_sets ps', 'on' => 'tr.paper_set_id = ps.id']
        ],
        ['tr.student_id' => $student_id],
        'tr.created_at DESC',
    );

    // Fetch all active counsellors for the assignment modal
    $counsellors = $op->readAll(
        'tbl_users',
        ['role_id' => ROLE_COUNSELLOR, 'status' => 'active'],
        'name ASC',
        ['id', 'name', 'email', 'phone']
    );
} catch (Exception $e) {
    set_flash_message('error', 'Database error: ' . $e->getMessage());
    header('Location: students.php?view=all');
    exit;
}

$page_title = "Student Details";
$page_breadcrumb = [
    'Home' => '#',
    'Students' => 'students.php?view=all',
    'Details' => ''
];

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>


<div class="container-fluid py-4">
    <!-- Profile Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm overflow-hidden" style="border-radius: 15px;">
                <div class="card-body p-0">
                    <div class="bg-gradient-primary p-4 p-md-5 text-white position-relative">
                        <!-- Decorative element -->
                        <div class="position-absolute top-0 end-0 p-4 opacity-10 d-none d-md-block">
                            <i class="fas fa-user-graduate fa-10x"></i>
                        </div>
                        
                        <div class="d-flex flex-column flex-md-row align-items-center position-relative">
                            <div class="avatar-wrapper mb-3 mb-md-0 me-md-4 p-1 bg-white rounded-circle shadow">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($student['surname'] . ' ' . $student['student_name'] . ' ' . ($student['fathers_name'] ?? '')); ?>&size=128&background=random&font-size=0.4" 
                                     alt="Avatar" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover;">
                            </div>
                            <div class="text-center text-md-start">
                                <h1 class="display-6 fw-bold mb-1">
                                    <?php echo htmlspecialchars($student['surname'] . ' ' . $student['student_name'] . ' ' . $student['fathers_name'] ?? ''); ?>
                                </h1>
                                <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-2 mb-3">
                                    <span class="badge bg-white text-primary rounded-pill px-3 py-2 shadow-sm">
                                        <i class="fas fa-book-reader me-1"></i> <?php echo htmlspecialchars($student['course_name'] ?? 'N/A'); ?>
                                    </span>
                                    <span class="badge bg-white text-primary rounded-pill px-3 py-2 shadow-sm">
                                        <i class="fas fa-university me-1"></i> <?php echo htmlspecialchars($student['campus_name'] ?? 'Not Set'); ?>
                                    </span>
                                    <?php if ($student['status'] == 1): ?>
                                        <span class="badge bg-success border border-white border-2 rounded-pill px-3 py-2 shadow-sm">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger border border-white border-2 rounded-pill px-3 py-2 shadow-sm">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <div class="btn-group">
                                    <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE) || hasRole(ROLE_COUNSELLOR) || hasRole(ROLE_ACCOUNTANT) || hasRole(ROLE_ESTABLISHMENT) || hasRole(ROLE_RECEPTION) || hasRole(ROLE_COMPUTER_OPERATOR)): ?>
                                        <a href="edit-student.php?id=<?php echo $student_id; ?>" class="btn btn-light rounded-pill px-3 me-2">
                                            <i class="fas fa-edit me-1"></i> Edit Profile
                                        </a>
                                    <?php endif; ?>
                                    <a href="students.php?view=all" class="btn btn-outline-light rounded-pill px-3">
                                        <i class="fas fa-arrow-left me-1"></i> Back to List
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4">
        <!-- Left Column: Primary Information -->
        <div class="col-lg-8">
            <!-- Summary Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-3 rounded-4">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary mx-auto mb-2" style="width: 50px; height: 50px; line-height: 50px; border-radius: 12px;">
                            <i class="fas fa-calendar-day fs-4"></i>
                        </div>
                        <div class="small text-muted mb-1">Date of Birth</div>
                        <div class="fw-bold"><?php echo date('d M, Y', strtotime($student['dob'])); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-3 rounded-4">
                        <div class="icon-box bg-success bg-opacity-10 text-success mx-auto mb-2" style="width: 50px; height: 50px; line-height: 50px; border-radius: 12px;">
                            <i class="fas fa-mobile-alt fs-4"></i>
                        </div>
                        <div class="small text-muted mb-1">Mobile Number</div>
                        <div class="fw-bold"><?php echo htmlspecialchars($student['mob'] ?? ''); ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-3 rounded-4">
                        <div class="icon-box bg-info bg-opacity-10 text-info mx-auto mb-2" style="width: 50px; height: 50px; line-height: 50px; border-radius: 12px;">
                            <i class="fas fa-id-card fs-4"></i>
                        </div>
                        <div class="small text-muted mb-1">GR Number</div>
                        <div class="fw-bold font-monospace"><?php echo htmlspecialchars($student['gr_no'] ?? 'N/A'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Detailed Information Tabs/Groups -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header border-0 bg-white p-4 pb-0">
                    <h5 class="card-title fw-bold text-dark mb-0">
                        <i class="fas fa-info-circle text-primary me-2"></i> Student Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Personal Info -->
                        <div class="col-md-6 border-end">
                            <h6 class="text-uppercase text-primary fw-bold small mb-3">PERSONAL & CONTACT</h6>
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="min-width: 100px;">Gender</span>
                                    <span class="fw-bold text-dark"><?php echo ucfirst($student['gender'] ?? 'N/A'); ?></span>
                                </li>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 100px;">Aadhaar</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['aadhaar'] ?? 'N/A'); ?></span>
                                </li>
                                <?php if (!empty($student['amob'])): ?>
                                    <li class="d-flex mb-3">
                                        <span class="text-muted" style="width: 100px;">Alt. Mobile</span>
                                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['amob'] ?? ''); ?></span>
                                    </li>
                                <?php endif; ?>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 100px;">Email</span>
                                    <a href="mailto:<?php echo htmlspecialchars($student['email'] ?? ''); ?>" class="text-primary fw-semibold text-decoration-none">
                                        <?php echo htmlspecialchars($student['email'] ?: 'N/A' ?? ''); ?>
                                    </a>
                                </li>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 100px;">Address</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['addr'] ?? ''); ?>, <?php echo htmlspecialchars($student['district'] ?? ''); ?></span>
                                </li>
                            </ul>
                        </div>
                        <!-- Academic Info -->
                        <div class="col-md-6 ps-md-4">
                            <h6 class="text-uppercase text-primary fw-bold small mb-3">ACADEMIC STATUS</h6>
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 120px;">Academic Year</span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary border-0"><?php echo htmlspecialchars($student['academic_year'] ?: 'Not Set' ?? ''); ?></span>
                                </li>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 120px;">Board / Medium</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['board_name'] ?? 'N/A'); ?> / <?php echo htmlspecialchars($student['medium_name'] ?? 'N/A'); ?></span>
                                </li>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 120px;">Group</span>
                                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></span>
                                </li>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 120px;">Prev. School</span>
                                    <span class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($student['schoolname'] ?? ''); ?>
                                    </span>
                                </li>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 120px;">School Addr.</span>
                                    <span class="fw-semibold text-dark small"><?php echo htmlspecialchars($student['schaddr'] ?? ''); ?></span>
                                </li>
                                <li class="d-flex mb-3">
                                    <span class="text-muted" style="width: 120px;">Registered On</span>
                                    <span class="fw-semibold text-dark small"><?php echo date('d M, Y h:i A', strtotime($student['created_at'])); ?></span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollment Details Section (If Enrolled) -->
            <?php if ($enrollment): ?>
                <div class="card border-0 shadow-sm rounded-4 mb-4" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%); border-left: 5px solid #0d6efd !important;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0">
                                <i class="fas fa-user-check text-primary me-2"></i> Enrollment Information
                            </h5>
                            <span class="badge bg-success bg-opacity-10 text-success rounded-pill px-3 py-2">Enrolled</span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-4 text-center border-end">
                                <div class="text-muted small mb-1">Enrollment No.</div>
                                <div class="h5 fw-bold text-dark font-monospace mb-0"><?php echo htmlspecialchars($enrollment['enrollment_no'] ?? ''); ?></div>
                            </div>
                            <div class="col-md-4 text-center border-end">
                                <div class="text-muted small mb-1">Division & Roll</div>
                                <div class="h5 fw-bold text-dark mb-0">
                                    <?php echo htmlspecialchars($enrollment['division_name'] ?: 'N/A' ?? ''); ?> - <?php echo htmlspecialchars($enrollment['roll_no'] ?: 'N/A' ?? ''); ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="text-muted small mb-1">School</div>
                                <div class="h6 fw-bold text-dark mb-0"><?php echo htmlspecialchars($enrollment['school_name'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Test Results Table -->
            <?php if (!empty($test_results)): ?>
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header border-0 bg-white p-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title fw-bold text-dark mb-0">
                            <i class="fas fa-chart-line text-primary me-2"></i> Performance History
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle border-0">
                                <thead class="bg-light text-muted small text-uppercase">
                                    <tr>
                                        <th class="border-0 rounded-start px-3 py-3">Date</th>
                                        <th class="border-0 py-3">Paper Set</th>
                                        <th class="border-0 py-3 text-center">Score</th>
                                        <th class="border-0 py-3 text-center">Percentage</th>
                                        <th class="border-0 rounded-end py-3 text-center">Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($test_results as $result): ?>
                                        <tr class="border-bottom border-light">
                                            <td class="px-3"><?php echo date('d M, Y', strtotime($result['created_at'])); ?></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($result['paper_set_name'] ?? 'N/A'); ?></td>
                                            <td class="text-center">
                                                <span class="fw-bold"><?php echo $result['score']; ?></span>
                                                <small class="text-muted d-block" style="font-size: 0.7rem;"><?php echo $result['correct_answers']; ?> Correct</small>
                                            </td>
                                            <td class="text-center">
                                                <?php
                                                $p = $result['percentage'];
                                                $color = $p >= 75 ? 'success' : ($p < 35 ? 'danger' : 'primary');
                                                ?>
                                                <div class="d-flex align-items-center justify-content-center">
                                                    <div class="progress flex-grow-1 me-2" style="height: 6px; width: 60px;">
                                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $p; ?>%"></div>
                                                    </div>
                                                    <span class="fw-bold text-<?php echo $color; ?>"><?php echo $p; ?>%</span>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo $color; ?> bg-opacity-10 text-<?php echo $color; ?> rounded-pill px-3"><?php echo htmlspecialchars($result['grade'] ?? 'N/A'); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Sidebar Information -->
        <div class="col-lg-4">
            <!-- Counsellor Assignment Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
                <div class="card-header bg-primary border-0 p-4 pb-3">
                    <h6 class="text-white fw-bold text-uppercase small mb-0">Counsellor Assignment</h6>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($student['counsellor_id'])): ?>
                        <div class="text-center mb-4">
                            <div class="avatar-circle bg-primary bg-gradient text-white mx-auto mb-3 shadow-sm d-flex align-items-center justify-content-center" 
                                 style="width: 80px; height: 80px; border-radius: 24px; font-size: 32px;">
                                <?php echo strtoupper(substr($student['counsellor_name'], 0, 1)); ?>
                            </div>
                            <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($student['counsellor_name'] ?? ''); ?></h5>
                            <p class="text-muted small mb-0">Assigned Counsellor</p>
                        </div>
                        <div class="p-3 bg-light rounded-3 mb-3">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-envelope text-muted me-3" style="width: 16px;"></i>
                                <span class="text-dark small overflow-hidden"><?php echo htmlspecialchars($student['counsellor_email'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="d-flex align-items-center">
                                <i class="fas fa-phone text-muted me-3" style="width: 16px;"></i>
                                <span class="text-dark small"><?php echo htmlspecialchars($student['counsellor_phone'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                        <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
                            <button type="button" class="btn btn-outline-primary w-100 rounded-pill btn-sm py-2" data-bs-toggle="modal" data-bs-target="#counsellorAssignModal">
                                <i class="fas fa-exchange-alt me-1"></i> Change Counsellor
                            </button>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <div class="bg-warning bg-opacity-10 text-warning mx-auto mb-3" style="width: 60px; height: 60px; line-height: 60px; border-radius: 50%;">
                                <i class="fas fa-user-slash fs-4"></i>
                            </div>
                            <p class="text-muted mb-4">No counsellor has been assigned to this student yet.</p>
                            <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
                                <button type="button" class="btn btn-primary w-100 rounded-pill" data-bs-toggle="modal" data-bs-target="#counsellorAssignModal">
                                    <i class="fas fa-user-plus me-1"></i> Assign Now
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Family Information Card -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header border-0 bg-primary p-4 pb-3">
                    <h6 class="text-white fw-bold text-uppercase small mb-0">Family Details</h6>
                </div>
                <div class="card-body p-4">
                    <div class="family-item mb-4">
                        <div class="d-flex align-items-start mb-1">
                            <div class="bg-light p-2 rounded-3 me-3">
                                <i class="fas fa-user-friends text-primary"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Father's Name</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['fathername'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="family-item mb-4">
                        <div class="d-flex align-items-start mb-1">
                            <div class="bg-light p-2 rounded-3 me-3">
                                <i class="fas fa-graduation-cap text-primary"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Education</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['fatheredu'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="family-item mb-4">
                        <div class="d-flex align-items-start mb-1">
                            <div class="bg-light p-2 rounded-3 me-3">
                                <i class="fas fa-briefcase text-primary"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Occupation</div>
                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($student['ocupation'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="family-item">
                        <div class="d-flex align-items-start mb-1">
                            <div class="bg-light p-2 rounded-3 me-3">
                                <i class="fas fa-building text-primary"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Office Address</div>
                                <div class="fw-bold text-dark small" style="line-height: 1.2;"><?php echo htmlspecialchars($student['ofcaddr'] ?: 'N/A' ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Extras (Hostel/Transport) -->
            <div class="row g-3">
                <div class="col-6">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3 <?php echo $student['hostel_required'] == 'Yes' ? 'bg-success bg-opacity-10 border-success border-1' : 'bg-light'; ?>">
                        <i class="fas fa-hotel mb-2 <?php echo $student['hostel_required'] == 'Yes' ? 'text-success' : 'text-muted'; ?>"></i>
                        <div class="small fw-bold">Hostel</div>
                        <div class="small fw-bold <?php echo $student['hostel_required'] == 'Yes' ? 'text-success' : 'text-muted'; ?>">
                            <?php echo $student['hostel_required'] == 'Yes' ? 'Required' : 'No'; ?>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card border-0 shadow-sm rounded-4 text-center p-3 <?php echo $student['transport_required'] == 'Yes' ? 'bg-info bg-opacity-10 border-info border-1' : 'bg-light'; ?>">
                        <i class="fas fa-bus mb-2 <?php echo $student['transport_required'] == 'Yes' ? 'text-info' : 'text-muted'; ?>"></i>
                        <div class="small fw-bold">Transport</div>
                        <div class="small fw-bold <?php echo $student['transport_required'] == 'Yes' ? 'text-info' : 'text-muted'; ?>">
                            <?php echo $student['transport_required'] == 'Yes' ? 'Required' : 'No'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<?php
if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
    <!-- Counsellor Assignment Modal -->
    <div class="modal fade" id="counsellorAssignModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-gradient-primary text-white border-0 rounded-top-4">
                    <h5 class="modal-title fw-bold"><i class="fas fa-user-tie me-2"></i> Assign Counsellor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Current Assignment -->
                    <div class="mb-3 p-3 bg-light rounded">
                        <label class="form-label fw-bold">Current Counsellor:</label>
                        <?php
                        if (!empty($student['counsellor_id'])): ?>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-info me-2"><?php
                                echo htmlspecialchars($student['counsellor_name'] ?? ''); ?></span>
                                <small class="text-muted"><?php
                                echo htmlspecialchars($student['counsellor_email'] ?? ''); ?></small>
                            </div>
                            <?php
                        else: ?>
                            <span class="badge bg-secondary">Not Assigned</span>
                            <?php
                        endif; ?>
                    </div>

                    <!-- New Counsellor Selection -->
                    <div class="mb-3">
                        <label for="newCounsellor" class="form-label fw-bold">Select New Counsellor: <span
                                class="text-danger">*</span></label>
                        <select id="newCounsellor" class="form-select" required>
                            <option value="">-- Select Counsellor --</option>
                            <?php
                            foreach ($counsellors as $counsellor): ?>
                                <option value="<?php
                                echo $counsellor['id']; ?>" <?php
                                  echo ($student['counsellor_id'] == $counsellor['id']) ? 'selected' : ''; ?>>
                                    <?php
                                    echo htmlspecialchars($counsellor['name'] ?? '') . ' (' . htmlspecialchars($counsellor['email'] ?? '') . ')'; ?>
                                </option>
                                <?php
                            endforeach; ?>
                        </select>
                    </div>

                    <?php
                    if (!empty($student['counsellor_id'])): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="removeCounsellor">
                                <label class="form-check-label text-danger" for="removeCounsellor">
                                    <i class="fas fa-user-minus"></i> Remove counsellor assignment
                                </label>
                            </div>
                        </div>
                        <?php
                    endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="updateCounsellorBtn">
                        <i class="fas fa-save"></i> Update Counsellor
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        <?php if (hasRole(ROLE_SUPER_ADMIN) || hasRole(ROLE_PRINCIPLE)): ?>
            // Handle remove checkbox
            $('#removeCounsellor').on('change', function () {
                if (this.checked) {
                    $('#newCounsellor').prop('disabled', true).val('');
                } else {
                    $('#newCounsellor').prop('disabled', false);
                }
            });

            // Update counsellor
            $('#updateCounsellorBtn').on('click', function () {
                const studentId = <?php echo $student_id; ?>;
                const removeCounsellor = $('#removeCounsellor').is(':checked');
                const newCounsellorId = $('#newCounsellor').val();

                if (!removeCounsellor && !newCounsellorId) {
                    if (typeof showToast === 'function') {
                        showToast('error', 'Error', 'Please select a counsellor');
                    } else {
                        alert('Please select a counsellor');
                    }
                    return;
                }

                const btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

                const formData = {
                    student_id: studentId,
                    counsellor_id: removeCounsellor ? '' : newCounsellorId,
                    action: removeCounsellor ? 'remove' : 'assign'
                };

                $.ajax({
                    url: 'counsellor-assign-process.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(formData),
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            if (typeof showToast === 'function') {
                                showToast('success', 'Success!', response.message);
                            }
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            if (typeof showToast === 'function') {
                                showToast('error', 'Error', response.message || 'Failed to update counsellor');
                            } else {
                                alert(response.message || 'Failed to update counsellor');
                            }
                            btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Counsellor');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('AJAX Error:', error);
                        if (typeof showToast === 'function') {
                            showToast('error', 'Error', 'An error occurred while updating counsellor');
                        } else {
                            alert('An error occurred while updating counsellor');
                        }
                        btn.prop('disabled', false).html('<i class="fas fa-save"></i> Update Counsellor');
                    }
                });
            });
        <?php endif; ?>
    });
</script>