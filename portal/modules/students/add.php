<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;

// Check access
if (!hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_COUNSELLOR) && !hasRole(ROLE_ACCOUNTANT) && !hasRole(ROLE_ESTABLISHMENT) && !hasRole(ROLE_RECEPTION) && !hasRole(ROLE_COMPUTER_OPERATOR)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Fetch dropdown data from API
$api = new APIClient();
$response = $api->get('students/add');

if ($response && isset($response['success']) && $response['success']) {
    $schools = $response['data']['schools'] ?? [];
    $boards = $response['data']['boards'] ?? [];
    $mediums = $response['data']['mediums'] ?? [];
    $groups = $response['data']['groups'] ?? [];
    $courses = $response['data']['courses'] ?? [];
    $campuses = $response['data']['campuses'] ?? [];
    $academic_years = $response['data']['academic_years'] ?? [];
}
else {
    $schools = $boards = $mediums = $groups = $courses = $campuses = $academic_years = [];
    set_flash_message('warning', 'Unable to load form options from backend');
}

$page_title = 'Add New Student Registration';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Admission Selection Overlay -->
<div id="admissionOverlay" class="admission-overlay">
    <div class="admission-card-container">
        <h2 class="mb-4 text-center text-white">Select Admission Type</h2>
        <div class="row g-4 justify-content-center">
            <div class="col-md-5">
                <div class="admission-option-card" data-standard="11">
                    <div class="option-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3>11th Admission</h3>
                    <p>Regular admission for students who have completed Std. 10th.</p>
                    <button class="btn btn-primary w-100">Select 11th</button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admission-option-card" data-standard="12">
                    <div class="option-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Direct 12th Admission</h3>
                    <p>Direct admission for students who have completed Std. 11th.</p>
                    <button class="btn btn-info w-100 text-white">Select 12th</button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admission-option-card" data-standard="13">
                    <div class="option-icon"><i class="fas fa-redo"></i></div>
                    <h3>Re-NEET Admission</h3>
                    <p>Special course for students appearing again for NEET entrance exam.</p>
                    <button class="btn btn-warning w-100">Select Re-NEET</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Student Registration Form</h3>
                    <div class="card-tools">
                        <a href="students.php?view=all" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <!-- Step Progress Bar -->
                <div class="card-header border-0 bg-transparent py-4 w-100">
                    <div class="stepper-wrapper" id="stepIndicator">
                        <div class="stepper-item active" data-step="1">
                            <div class="step-counter"><i class="fas fa-school"></i></div>
                            <div class="step-name">Academic</div>
                        </div>
                        <div class="stepper-item" data-step="2">
                            <div class="step-counter"><i class="fas fa-user"></i></div>
                            <div class="step-name">Personal</div>
                        </div>
                        <div class="stepper-item" data-step="3">
                            <div class="step-counter"><i class="fas fa-phone"></i></div>
                            <div class="step-name">Contact</div>
                        </div>
                        <div class="stepper-item" data-step="4">
                            <div class="step-counter"><i class="fas fa-users"></i></div>
                            <div class="step-name">Parent</div>
                        </div>
                        <div class="stepper-item" data-step="5">
                            <div class="step-counter"><i class="fas fa-info-circle"></i></div>
                            <div class="step-name">Additional</div>
                        </div>
                        <div class="stepper-item" data-step="6">
                            <div class="step-counter"><i class="fas fa-key"></i></div>
                            <div class="step-name">Login</div>
                        </div>
                        <div class="stepper-item" data-step="7">
                            <div class="step-counter"><i class="fas fa-check-circle"></i></div>
                            <div class="step-name">Review</div>
                        </div>
                    </div>
                </div>

                <form id="studentForm" method="POST">
                    <div class="card-body">

                        <!-- STEP 1: School & Academic Information -->
                        <div class="step-content" id="step1" style="display: block;">
                            <h4 class="text-primary mb-4">
                                <i class="fas fa-school"></i> School Information & Academic Details
                            </h4>

                            <!-- School Selection -->
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="text-secondary mb-0">School Information</h5>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="$('#admissionOverlay').fadeIn(400)">
                                    <i class="fas fa-exchange-alt"></i> Change Admission Type
                                </button>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Select Campus <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                            <select name="campus_id" id="campus_id" class="form-select" required>
                                                <option value="">-- Select Campus --</option>
                                                <?php
foreach ($campuses as $campus): ?>
                                                    <option value="<?php
    echo $campus['id']; ?>">
                                                        <?php
    echo htmlspecialchars($campus['campus_name'] ?? ''); ?>
                                                    </option>
                                                    <?php
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Select School <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-university"></i></span>
                                            <select name="school_id" id="school_id" class="form-select" required>
                                                <option value="">-- Select School --</option>
                                                <?php
foreach ($schools as $school): ?>
                                                    <option value="<?php
    echo $school['id']; ?>">
                                                        <?php
    echo htmlspecialchars($school['school_name'] ?? ''); ?> (<?php
    echo htmlspecialchars($school['school_code'] ?? ''); ?>)
                                                    </option>
                                                    <?php
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <!-- Academic Information -->
                            <h5 class="text-secondary mb-3">Academic Information</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Select Board <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-chalkboard"></i></span>
                                            <select name="board_id" id="board_id" class="form-select" required>
                                                <option value="">Select Board</option>
                                                <?php
foreach ($boards as $board): ?>
                                                    <option value="<?php
    echo $board['id']; ?>">
                                                        <?php
    echo htmlspecialchars($board['board_name'] ?? ''); ?>
                                                    </option>
                                                    <?php
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Medium <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-language"></i></span>
                                            <select name="medium_id" id="medium_id" class="form-select" required>
                                                <option value="">Select Medium</option>
                                                <?php
foreach ($mediums as $medium): ?>
                                                    <option value="<?php
    echo $medium['id']; ?>">
                                                        <?php
    echo htmlspecialchars($medium['medium_name'] ?? ''); ?>
                                                    </option>
                                                    <?php
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Group <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-layer-group"></i></span>
                                            <select name="group_id" id="group_id" class="form-select" required>
                                                <option value="">Select Group</option>
                                                <?php
foreach ($groups as $group): ?>
                                                    <option value="<?php
    echo $group['id']; ?>">
                                                        <?php
    echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                                    </option>
                                                    <?php
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Standard <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-book"></i></span>
                                            <select name="course_id" id="course_id" class="form-select" required>
                                                <option value="">Select Standard</option>
                                                <?php
foreach ($courses as $course): ?>
                                                    <option value="<?php
    echo $course['id']; ?>" data-standard="<?php
    echo htmlspecialchars($course['standard'] ?? ''); ?>">
                                                        <?php
    echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                                        <?php
    if (!empty($course['standard'])): ?>
                                                            (Std. <?php
        echo htmlspecialchars($course['standard'] ?? ''); ?>)
                                                            <?php
    endif; ?>
                                                    </option>
                                                    <?php
endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Standard <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                            <input type="text" name="standard" id="standard" class="form-control"
                                                placeholder="Standard" readonly required>
                                        </div>
                                        <small class="form-text text-muted">Automatically filled based on
                                            course</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">G.R. No</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                            <input type="text" name="gr_no" id="gr_no" class="form-control"
                                                placeholder="Enter G.R. Number">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Student's School Name (Std.10th) <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-school"></i></span>
                                            <input type="text" name="schoolname" id="schoolname" class="form-control"
                                                placeholder="School Name: (Std.10th)" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label class="form-label">School Address (Std.10th) <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                            <textarea name="schaddr" id="schaddr" class="form-control" rows="2"
                                                placeholder="School Address: (Std.10th)" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 2: Personal Information -->
                        <div class="step-content" id="step2" style="display: none;">
                            <h4 class="text-primary mb-4">
                                <i class="fas fa-user"></i> Personal Information
                            </h4>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Surname <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                            <input type="text" name="surname" id="surname" class="form-control"
                                                placeholder="Surname" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Student Name <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                                            <input type="text" name="student_name" id="student_name"
                                                class="form-control" placeholder="Student Name" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Father's Name <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-friends"></i></span>
                                            <input type="text" name="fathers_name" id="fathers_name"
                                                class="form-control" placeholder="Father's Name" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Date of Birth <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                            <input type="date" name="dob" id="dob" class="form-control" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Gender <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                            <select name="gender" id="gender" class="form-select" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Aadhaar Card Number <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-address-card"></i></span>
                                            <input type="text" name="aadhaar" id="aadhaar" class="form-control"
                                                placeholder="Aadhaar Card Number" pattern="[0-9]{12}" maxlength="12"
                                                required>
                                        </div>
                                        <small class="text-muted">Please enter a 12-digit Aadhaar card
                                            number.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Religion</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-pray"></i></span>
                                            <select name="religion" id="religion" class="form-select">
                                                <option value="">-- Select Religion --</option>
                                                <option value="Hindu">Hindu</option>
                                                <option value="Muslim">Muslim</option>
                                                <option value="Christian">Christian</option>
                                                <option value="Sikh">Sikh</option>
                                                <option value="Buddhist">Buddhist</option>
                                                <option value="Jain">Jain</option>
                                                <option value="Parsi">Parsi</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Caste (Category)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-users-cog"></i></span>
                                            <select name="caste" id="caste" class="form-select">
                                                <option value="">-- Select Category --</option>
                                                <option value="General">General</option>
                                                <option value="OBC">OBC</option>
                                                <option value="SC">SC</option>
                                                <option value="ST">ST</option>
                                                <option value="NT">NT</option>
                                                <option value="VJNT">VJNT</option>
                                                <option value="SEBC">SEBC</option>
                                                <option value="EWS">EWS</option>
                                                <option value="Other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 3: Contact Information -->
                        <div class="step-content" id="step3" style="display: none;">
                            <h4 class="text-primary mb-4">
                                <i class="fas fa-phone"></i> Contact Information
                            </h4>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Mobile No <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                            <input type="text" name="mob" id="mob" class="form-control"
                                                placeholder="Mobile No" pattern="[0-9]{10}" maxlength="10" required>
                                        </div>
                                        <small class="text-muted">Please enter a 10-digit mobile number.</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Alternate Mobile No</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                            <input type="text" name="amob" id="amob" class="form-control"
                                                placeholder="Alternate Mobile No" pattern="[0-9]{10}" maxlength="10">
                                        </div>
                                        <small class="text-muted">Please enter a 10-digit alternate mobile
                                            number.</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                            <input type="email" name="email" id="email" class="form-control"
                                                placeholder="Enter email address">
                                        </div>
                                        <small class="text-muted">Optional email address.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Residence Address <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-home"></i></span>
                                            <textarea name="addr" id="addr" class="form-control" rows="3"
                                                placeholder="Address:" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label class="form-label">District <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-map-marked-alt"></i></span>
                                            <input type="text" name="district" id="district" class="form-control"
                                                placeholder="District" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 4: Parent/Guardian Information -->
                        <div class="step-content" id="step4" style="display: none;">
                            <h4 class="text-primary mb-4">
                                <i class="fas fa-users"></i> Parent/Guardian Information
                            </h4>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Father's Name <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                                            <input type="text" name="fathername" id="fathername" class="form-control"
                                                placeholder="Father's Name" readonly>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Parent Mobile No. (for Portal Login) <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                                            <input type="text" name="parent_mob" id="parent_mob" class="form-control"
                                                placeholder="Mobile number for Parent Portal" pattern="[0-9]{10}" maxlength="10" required>
                                        </div>
                                        <small class="text-muted">This number will be used to link siblings in the Parent Portal.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Father's Education <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-user-graduate"></i></span>
                                            <input type="text" name="fatheredu" id="fatheredu" class="form-control"
                                                placeholder="Father's Education" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Occupation <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                                            <input type="text" name="ocupation" id="ocupation" class="form-control"
                                                placeholder="Father's Occupation" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Office Address <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                                            <textarea name="ofcaddr" id="ofcaddr" class="form-control" rows="2"
                                                placeholder="Address" required></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 5: Additional Information -->
                        <div class="step-content" id="step5" style="display: none;">
                            <h4 class="text-primary mb-4">
                                <i class="fas fa-info-circle"></i> Additional Information
                            </h4>

                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label>Hostel Facilities <span class="text-danger">*</span></label><br>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="hostel_required"
                                                id="hostelYes" value="Yes" required>
                                            <label class="form-check-label" for="hostelYes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="hostel_required"
                                                id="hostelNo" value="No" checked required>
                                            <label class="form-check-label" for="hostelNo">No</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <label>Transport Facilities <span class="text-danger">*</span></label><br>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="transport_required"
                                                id="transportYes" value="Yes" required>
                                            <label class="form-check-label" for="transportYes">Yes</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="transport_required"
                                                id="transportNo" value="No" checked required>
                                            <label class="form-check-label" for="transportNo">No</label>
                                        </div>
                                        <div id="transport_months_container" class="mt-2" style="display: none;">
                                            <label class="small fw-bold mb-1">Duration (Months)</label>
                                            <input type="number" name="transport_months" id="transport_months" class="form-control form-control-sm" 
                                                   min="1" max="12" placeholder="e.g. 1">
                                            <small class="text-muted">Restrict transport billing to these many months.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 6: Login Credentials -->
                        <div class="step-content" id="step6" style="display: none;">
                            <h4 class="text-primary mb-4">
                                <i class="fas fa-key"></i> Login Credentials
                            </h4>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <strong>Note:</strong> Mobile number will be used
                                as the default password for student login.
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Password <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" name="password" id="password" class="form-control"
                                                placeholder="Mobile number will be auto-filled" readonly required>
                                        </div>
                                        <small class="text-muted">Password will be automatically set to mobile
                                            number</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Confirm Password <span
                                                class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" name="confirm_password" id="confirm_password"
                                                class="form-control" placeholder="Mobile number will be auto-filled"
                                                readonly required>
                                        </div>
                                        <small class="text-muted">Same as password</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-md-12">
                                    <div class="form-group mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="declaration"
                                                name="declaration_agreed" value="1" required>
                                            <label class="form-check-label" for="declaration">
                                                <strong>Declaration:</strong> We hereby declare that the information
                                                given above is correct to the best of our knowledge.
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- STEP 7: Review & Save -->
                        <div class="step-content" id="step7" style="display: none;">
                            <h4 class="text-primary mb-4">
                                <i class="fas fa-check-circle"></i> Review & Confirm
                            </h4>

                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-exclamation-triangle"></i> <strong>Please review all information
                                    carefully before submitting.</strong>
                            </div>

                            <div class="row">
                                <!-- Review Section: School & Personal -->
                                <div class="col-md-6">
                                    <div class="review-card">
                                        <div class="review-header">
                                            <i class="fas fa-university"></i> Academic & School
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Campus:</span>
                                            <span class="review-value" id="review_campus">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">School:</span>
                                            <span class="review-value" id="review_school">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Board:</span>
                                            <span class="review-value" id="review_board">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Medium:</span>
                                            <span class="review-value" id="review_medium">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Group:</span>
                                            <span class="review-value" id="review_group">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">G.R. No:</span>
                                            <span class="review-value" id="review_gr_no">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">10th School:</span>
                                            <span class="review-value" id="review_schoolname">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">School Addr:</span>
                                            <span class="review-value" id="review_schaddr">-</span>
                                        </div>
                                    </div>

                                    <div class="review-card">
                                        <div class="review-header">
                                            <i class="fas fa-user-graduate"></i> Personal Info
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Full Name:</span>
                                            <span class="review-value" id="review_fullname">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Date of Birth:</span>
                                            <span class="review-value" id="review_dob">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Gender:</span>
                                            <span class="review-value" id="review_gender">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Aadhaar:</span>
                                            <span class="review-value" id="review_aadhaar">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Religion:</span>
                                            <span class="review-value" id="review_religion">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Caste:</span>
                                            <span class="review-value" id="review_caste">-</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Review Section: Contact & Parent -->
                                <div class="col-md-6">
                                    <div class="review-card">
                                        <div class="review-header">
                                            <i class="fas fa-map-marked-alt"></i> Contact Details
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Mobile:</span>
                                            <span class="review-value" id="review_mob">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Alt Mobile:</span>
                                            <span class="review-value" id="review_amob">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">District:</span>
                                            <span class="review-value" id="review_district">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Address:</span>
                                            <span class="review-value" id="review_addr">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Hostel Req:</span>
                                            <span class="review-value" id="review_hostel">-</span>
                                        </div>
                                    </div>

                                    <div class="review-card">
                                        <div class="review-header">
                                            <i class="fas fa-users-cog"></i> Parent Info
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Father's Name:</span>
                                            <span class="review-value" id="review_fathername">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Parent Mobile:</span>
                                            <span class="review-value" id="review_parent_mob">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Education:</span>
                                            <span class="review-value" id="review_fatheredu">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Occupation:</span>
                                            <span class="review-value" id="review_ocupation">-</span>
                                        </div>
                                        <div class="review-item">
                                            <span class="review-label">Office Addr:</span>
                                            <span class="review-value" id="review_ofcaddr">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="card-footer">
                        <div class="row">
                            <div class="col-6 text-start">
                                <button type="button" class="btn btn-secondary" id="prevBtn" onclick="changeStep(-1)">
                                    <i class="fas fa-arrow-left"></i> Previous
                                </button>
                            </div>
                            <div class="col-6 text-end">
                                <button type="button" class="btn btn-primary" id="nextBtn" onclick="changeStep(1)">
                                    Next <i class="fas fa-arrow-right"></i>
                                </button>
                                <button type="submit" class="btn btn-success" id="submitBtn" style="display: none;">
                                    <i class="fas fa-check"></i> Register Student
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>

<style>
    /* Card Enhancements */
    .card {
        border-radius: 16px !important;
        border: none !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
        overflow: hidden;
    }

    .card-header:first-child {
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
        color: white !important;
        padding: 1.5rem !important;
    }

    .card-header .card-title {
        color: white !important;
        font-weight: 700 !important;
        letter-spacing: 0.5px;
    }

    .card-header .btn-secondary {
        background: rgba(255, 255, 255, 0.2) !important;
        border: 1px solid rgba(255, 255, 255, 0.3) !important;
        color: white !important;
        backdrop-filter: blur(5px);
    }

    .card-header .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.3) !important;
    }

    /* Stepper Styling */
    .stepper-wrapper {
        display: flex;
        justify-content: space-between;
        margin-bottom: 20px;
        position: relative;
        width: 100%;
        padding: 0 10px;
    }

    .stepper-item {
        position: relative;
        z-index: 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1 1 0;
        min-width: 0;
        transition: all 0.3s ease;
    }

    .stepper-item::before {
        content: "";
        position: absolute;
        top: 25px;
        left: -50%;
        width: 100%;
        height: 2px;
        background: #e2e8f0;
        z-index: 1;
        transition: all 0.3s ease;
    }

    .stepper-item:first-child::before {
        content: none;
    }

    .stepper-item.active::before,
    .stepper-item.completed::before {
        background: #2563eb;
    }

    .step-counter {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: white;
        border: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        font-weight: 600;
        color: #64748b;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        position: relative;
        z-index: 5;
    }

    .step-name {
        font-size: 0.8rem;
        font-weight: 600;
        color: #64748b;
        text-align: center;
        transition: all 0.3s ease;
    }

    .stepper-item.active .step-counter {
        background: #2563eb;
        border-color: #2563eb;
        color: white;
        box-shadow: 0 0 15px rgba(37, 99, 235, 0.3);
        transform: scale(1.1);
    }

    .stepper-item.active .step-name {
        color: #2563eb;
    }

    .stepper-item.completed .step-counter {
        background: #10b981;
        border-color: #10b981;
        color: white;
    }

    .stepper-item.completed .step-name {
        color: #10b981;
    }

    /* Form Enhancements */
    .form-group label {
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }

    .input-group-text {
        background: #f8fafc;
        border-color: #e2e8f0;
        color: #64748b;
    }

    .form-control:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }

    /* Animations */
    .step-content {
        animation: fadeIn 0.4s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Review Dashboard */
    .review-card {
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .review-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 1rem;
        color: #2563eb;
        font-weight: 700;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 0.5rem;
    }

    .review-item {
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
    }

    .review-label {
        color: #64748b;
        font-size: 0.9rem;
    }

    .review-value {
        color: #1e293b;
        font-weight: 600;
        font-size: 0.95rem;
    }

    @media (max-width: 768px) {
        .stepper-wrapper::before {
            top: 20px;
        }

        .step-counter {
            width: 40px;
            height: 40px;
        }

        .step-name {
            font-size: 0.7rem;
            display: none;
        }

        .stepper-item.active .step-name {
            display: block;
            margin-top: 5px;
        }
    }
    /* Admission Overlay */
    .admission-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(10px);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .admission-card-container {
        max-width: 900px;
        width: 100%;
    }

    .admission-option-card {
        background: white;
        border-radius: 20px;
        padding: 40px 30px;
        text-align: center;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        height: 100%;
        display: flex;
        flex-direction: column;
        border: 4px solid transparent;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .admission-option-card:hover {
        transform: translateY(-10px);
        border-color: #2563eb;
        box-shadow: 0 25px 50px -12px rgba(37, 99, 235, 0.25);
    }

    .option-icon {
        width: 80px;
        height: 80px;
        background: #f1f5f9;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        font-size: 32px;
        color: #2563eb;
        transition: all 0.3s ease;
    }

    .admission-option-card:hover .option-icon {
        background: #2563eb;
        color: white;
        transform: rotate(10deg);
    }

    .admission-option-card h3 {
        font-weight: 800;
        color: #1e293b;
        margin-bottom: 15px;
    }

    .admission-option-card p {
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 25px;
        flex-grow: 1;
    }

    @media (max-width: 768px) {
        .admission-option-card {
            padding: 30px 20px;
        }
    }
</style>

<script>
    let currentStep = 1;
    const totalSteps = 7;
    let selectedStandard = null;

    $(document).ready(function () {
        showStep(currentStep);

        // Handle Admission Type Selection
        $('.admission-option-card').on('click', function() {
            selectedStandard = $(this).data('standard');
            $('#admissionOverlay').fadeOut(400);
            
            // Set admission context
            applyAdmissionTypeContext(selectedStandard);
        });

        // Auto-populate standard field when course is selected (now filtered)
        $('#course_id').on('change', function () {
            const selectedOption = $(this).find('option:selected');
            const standard = selectedOption.data('standard') || '';
            $('#standard').val(standard);
        });

        function applyAdmissionTypeContext(std) {
            // Filter courses based on standard
            $('#course_id option').each(function() {
                const optStd = $(this).data('standard');
                if ($(this).val() === "" || optStd == std) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
            
            // Show/Hide admission type label in Step 1 if needed
            let typeLabel = '11th';
            if (std == 12) typeLabel = 'Direct 12th';
            else if (std == 13) typeLabel = 'Re-NEET';
            
            $('#admissionTypeBadge').remove();
            $('h4.text-primary.mb-4').first().append(' <span id="admissionTypeBadge" class="badge bg-info ms-2" style="font-size: 0.5em; vertical-align: middle;">' + typeLabel + ' Admission</span>');
            
            // Reset course selection if it doesn't match
            $('#course_id').val('');
            $('#standard').val('');
        }

        // Sync Father's name from Step 2 to Step 4
        $('#fathers_name').on('input', function () {
            $('#fathername').val($(this).val());
        });

        // Auto-fill password fields with mobile number
        $('input[name="mob"]').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
            const mobileNumber = $(this).val();
            if (mobileNumber.length === 10) {
                $('#password').val(mobileNumber);
                $('#confirm_password').val(mobileNumber);
            } else {
                $('#password').val('');
                $('#confirm_password').val('');
            }
        });

        // Mobile number validation
        $('input[name="amob"]').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Aadhaar number validation
        $('#aadhaar').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Transport months visibility toggle
        $('input[name="transport_required"]').change(function() {
            if ($(this).val() === 'Yes') {
                $('#transport_months_container').slideDown(200);
            } else {
                $('#transport_months_container').slideUp(200);
                $('#transport_months').val('');
            }
        });

        // Parent mobile validation
        $('input[name="parent_mob"]').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Form submission via API
        $('#studentForm').on('submit', function (e) {
            e.preventDefault();

            const password = $('#password').val();
            const confirmPassword = $('#confirm_password').val();

            if (password !== confirmPassword) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', 'Password and Confirm Password do not match!');
                } else {
                    alert('Password and Confirm Password do not match!');
                }
                return false;
            }

            // Collect form data
            const formData = {};
            $(this).serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });

            // Show loading
            const submitBtn = $('#submitBtn');
            const originalBtnHtml = submitBtn.html();
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Registering...');

            // Submit via API
            $.api.post('students/save', formData).then(response => {
                if (response.success) {
                    if (typeof showToast === 'function') {
                        showToast('success', 'Student Registered!', response.message || 'Student has been registered successfully.');
                    }
                    setTimeout(() => {
                        window.location.href = 'students.php?view=all';
                    }, 2000);
                } else {
                    submitBtn.prop('disabled', false).html(originalBtnHtml);
                    if (typeof showToast === 'function') {
                        showToast('error', 'Error', response.error || response.message || 'Failed to register student.');
                    } else {
                        alert(response.error || response.message || 'Failed to register student.');
                    }
                }
            }).catch(error => {
                console.error('API Error:', error);
                submitBtn.prop('disabled', false).html(originalBtnHtml);
                if (typeof showToast === 'function') {
                    showToast('error', 'Error', error.message || 'An unexpected error occurred. Please try again.');
                } else {
                    alert(error.message || 'An unexpected error occurred. Please try again.');
                }
            });
        });
    });

    function showStep(step) {
        // Hide all steps
        $('.step-content').hide();

        // Show current step
        $('#step' + step).show();

        // Update step indicators
        $('#stepIndicator .stepper-item').removeClass('active completed');
        for (let i = 1; i <= totalSteps; i++) {
            if (i < step) {
                $('#stepIndicator .stepper-item[data-step="' + i + '"]').addClass('completed');
            } else if (i === step) {
                $('#stepIndicator .stepper-item[data-step="' + i + '"]').addClass('active');
            }
        }

        // Update buttons
        if (step === 1) {
            $('#prevBtn').hide();
        } else {
            $('#prevBtn').show();
        }

        if (step === totalSteps) {
            $('#nextBtn').hide();
            $('#submitBtn').show();
            updateReviewSection();
        } else {
            $('#nextBtn').show();
            $('#submitBtn').hide();
        }

        // Scroll to top
        $('.content-wrapper').animate({
            scrollTop: 0
        }, 300);
    }

    function changeStep(direction) {
        // Validate current step before moving forward
        if (direction === 1) {
            if (!validateStep(currentStep)) {
                return false;
            }
        }

        const newStep = currentStep + direction;

        if (newStep >= 1 && newStep <= totalSteps) {
            // Auto-fill parent mobile if entering Step 4 and it's empty
            if (newStep === 4 && !$('#parent_mob').val()) {
                $('#parent_mob').val($('#mob').val());
            }

            currentStep = newStep;
            showStep(currentStep);
        }
    }

    function validateStep(step) {
        let isValid = true;
        let errorMessage = '';

        // Get all required fields in current step
        const stepElement = $('#step' + step);
        const requiredFields = stepElement.find('[required]');

        requiredFields.each(function () {
            const field = $(this);

            if (field.is('input[type="text"], input[type="date"], textarea, select')) {
                if (!field.val() || field.val().trim() === '') {
                    isValid = false;
                    field.addClass('is-invalid');
                    if (!errorMessage) {
                        errorMessage = 'Please fill in all required fields';
                    }
                } else {
                    field.removeClass('is-invalid');
                }
            } else if (field.is('input[type="radio"]')) {
                const radioName = field.attr('name');
                if (!$('input[name="' + radioName + '"]:checked').length) {
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Please select a required option';
                    }
                }
            } else if (field.is('input[type="checkbox"]')) {
                if (!field.is(':checked')) {
                    isValid = false;
                    field.addClass('is-invalid');
                    if (!errorMessage) {
                        errorMessage = 'Please accept the declaration';
                    }
                } else {
                    field.removeClass('is-invalid');
                }
            }
        });

        // Additional validation for specific steps
        if (step === 2) {
            const aadhaar = $('#aadhaar').val();
            if (aadhaar && aadhaar.length !== 12) {
                isValid = false;
                errorMessage = 'Aadhaar number must be 12 digits';
                $('#aadhaar').addClass('is-invalid');
            }
        }

        if (step === 3) {
            const mobile = $('#mob').val();
            if (mobile && mobile.length !== 10) {
                isValid = false;
                errorMessage = 'Mobile number must be 10 digits';
                $('#mob').addClass('is-invalid');
            }

            const altMobile = $('#amob').val();
            if (altMobile && altMobile.length > 0 && altMobile.length !== 10) {
                isValid = false;
                errorMessage = 'Alternate mobile number must be 10 digits';
                $('#amob').addClass('is-invalid');
            }
        }

        if (step === 4) {
            const parentMobile = $('#parent_mob').val();
            if (parentMobile && parentMobile.length !== 10) {
                isValid = false;
                errorMessage = 'Parent mobile number must be 10 digits';
                $('#parent_mob').addClass('is-invalid');
            }
        }

        if (!isValid) {
            if (typeof showToast === 'function') {
                showToast('error', 'Validation Error', errorMessage);
            } else {
                alert(errorMessage);
            }
        }

        return isValid;
    }

    function updateReviewSection() {
        // School & Academic Information
        $('#review_campus').text($('#campus_id option:selected').text() || '-');
        $('#review_school').text($('#school_id option:selected').text() || '-');
        $('#review_board').text($('#board_id option:selected').text() || '-');
        $('#review_medium').text($('#medium_id option:selected').text() || '-');
        $('#review_group').text($('#group_id option:selected').text() || '-');
        $('#review_gr_no').text($('#gr_no').val() || '-');
        $('#review_schoolname').text($('#schoolname').val() || '-');
        $('#review_schaddr').text($('#schaddr').val() || '-');

        // Personal Information
        const fullName = $('#surname').val() + ' ' + $('#student_name').val() + ' ' + $('#fathers_name').val();
        $('#review_fullname').text(fullName.trim() || '-');
        $('#review_dob').text($('#dob').val() || '-');
        $('#review_gender').text($('#gender').val() || '-');
        $('#review_aadhaar').text($('#aadhaar').val() || '-');
        $('#review_religion').text($('#religion').val() || '-');
        $('#review_caste').text($('#caste').val() || '-');

        // Contact Information
        $('#review_mob').text($('#mob').val() || '-');
        $('#review_amob').text($('#amob').val() || '-');
        $('#review_addr').text($('#addr').val() || '-');
        $('#review_district').text($('#district').val() || '-');

        // Parent Information
        $('#review_fathername').text($('#fathers_name').val() || '-');
        $('#review_parent_mob').text($('#parent_mob').val() || '-');
        $('#review_fatheredu').text($('#fatheredu').val() || '-');
        $('#review_ocupation').text($('#ocupation').val() || '-');
        $('#review_ofcaddr').text($('#ofcaddr').val() || '-');

        // Additional Information
        const hostelValue = $('input[name="hostel_required"]:checked').val();
        $('#review_hostel').text(hostelValue || '-');
    }
</script>

</body>

</html>