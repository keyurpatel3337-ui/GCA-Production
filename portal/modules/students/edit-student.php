<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_FLASH_MESSAGE;

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

// Fetch edit form data from API
$api = new APIClient();
$response = $api->get('students/edit', ['id' => $student_id]);

if ($response && isset($response['success']) && $response['success']) {
    $student = $response['data']['student'] ?? null;
    $schools = $response['data']['schools'] ?? [];
    $boards = $response['data']['boards'] ?? [];
    $mediums = $response['data']['mediums'] ?? [];
    $groups = $response['data']['groups'] ?? [];
    $courses = $response['data']['courses'] ?? [];
    $campuses = $response['data']['campuses'] ?? [];
    $divisions = $response['data']['divisions'] ?? [];

    if (!$student) {
        set_flash_message('error', 'Student not found');
        header('Location: students.php?view=all');
        exit;
    }
} else {
    error_log("Edit Student Error - Failed to load data for ID $student_id: " . ($response['error'] ?? 'Unknown API Error'));
    set_flash_message('error', $response['error'] ?? 'Failed to load student data');
    header('Location: students.php?view=all');
    exit;
}

$page_title = 'Edit Student Information';
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>

<!-- Content Header -->

<!-- Main content -->

<div class="container-fluid">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error'] ?? '');
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Student Registration Form</h3>
                    <div class="card-tools">
                        <a href="students.php?view=all" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                        <form id="form_details_student_id" action="details.php" method="POST" class="css-edit-student-93b8ea">
                            <input type="hidden" name="id" value="<?php echo $student_id; ?>">
                        </form>
                        <a onclick="document.getElementById('form_details_student_id').submit()" class="css-edit-student-b202c6"
                            class="btn btn-info btn-sm">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                    </div>
                </div>

                <form id="studentEditForm" method="POST">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">

                    <div class="card-body">

                        <!-- School & Academic Information -->
                        <div class="section-divider mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-school"></i> School Information & Academic Details
                            </h4>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Select Campus <span class="text-danger">*</span></label>
                                    <select name="campus_id" id="campus_id" class="form-control" required>
                                        <option value="">-- Select Campus --</option>
                                        <?php foreach ($campuses as $campus): ?>
                                            <option value="<?php echo $campus['id']; ?>" <?php echo (isset($student['campus_id']) && $student['campus_id'] == $campus['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($campus['campus_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Select School <span class="text-danger">*</span></label>
                                    <select name="school_id" id="school_id" class="form-control" required>
                                        <option value="">-- Select School --</option>
                                        <?php foreach ($schools as $school): ?>
                                            <option value="<?php echo $school['id']; ?>" <?php echo ($student['school_id'] == $school['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($school['school_name'] ?? ''); ?>
                                                (<?php echo htmlspecialchars($school['school_code'] ?? ''); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Student's School Name (Std.10th) <span class="text-danger">*</span></label>
                                    <input type="text" name="schoolname" id="schoolname" class="form-control"
                                        value="<?php echo htmlspecialchars($student['schoolname'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Select Board <span class="text-danger">*</span></label>
                                    <select name="board_id" id="board_id" class="form-control" required>
                                        <option value="">Select Board</option>
                                        <?php foreach ($boards as $board): ?>
                                            <option value="<?php echo $board['id']; ?>" <?php echo ($student['board_id'] == $board['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($board['board_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Medium <span class="text-danger">*</span></label>
                                    <select name="medium_id" id="medium_id" class="form-control" required>
                                        <option value="">Select Medium</option>
                                        <?php foreach ($mediums as $medium): ?>
                                            <option value="<?php echo $medium['id']; ?>" <?php echo ($student['medium_id'] == $medium['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($medium['medium_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Group <span class="text-danger">*</span></label>
                                    <select name="group_id" id="group_id" class="form-control" required>
                                        <option value="">Select Group</option>
                                        <?php foreach ($groups as $group): ?>
                                            <option value="<?php echo $group['id']; ?>" <?php echo ($student['group_id'] == $group['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($group['group_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Standard <span class="text-danger">*</span></label>
                                    <select name="course_id" id="course_id" class="form-control" required>
                                        <option value="">Select Standard</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo $course['id']; ?>"
                                                data-standard="<?php echo htmlspecialchars($course['standard'] ?? ''); ?>"
                                                <?php echo ($student['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course['course_name'] ?? ''); ?>
                                                <?php if (!empty($course['standard'])): ?>
                                                    (Std. <?php echo htmlspecialchars($course['standard'] ?? ''); ?>)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>G.R. No</label>
                                    <input type="text" name="gr_no" id="gr_no" class="form-control"
                                        value="<?php echo htmlspecialchars($student['gr_no'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Division</label>
                                    <select name="division_id" id="division_id" class="form-control">
                                        <option value="">-- Select Division --</option>
                                        <?php foreach ($divisions as $division): ?>
                                            <option value="<?php echo $division['id']; ?>" <?php echo (isset($student['division_id']) && $student['division_id'] == $division['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($division['division_name'] ?? ''); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Roll Number</label>
                                    <input type="text" name="roll_no" id="roll_no" class="form-control"
                                        value="<?php echo htmlspecialchars($student['roll_no'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>School Address (Std.10th) <span class="text-danger">*</span></label>
                                    <textarea name="schaddr" id="schaddr" class="form-control" rows="2"
                                        required><?php echo htmlspecialchars($student['schaddr'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Personal Information -->
                        <div class="section-divider mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-user"></i> Personal Information
                            </h4>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Surname <span class="text-danger">*</span></label>
                                    <input type="text" name="surname" id="surname" class="form-control"
                                        value="<?php echo htmlspecialchars($student['surname'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Student Name <span class="text-danger">*</span></label>
                                    <input type="text" name="student_name" id="student_name" class="form-control"
                                        value="<?php echo htmlspecialchars($student['student_name'] ?? ''); ?>"
                                        required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Father's Name <span class="text-danger">*</span></label>
                                    <input type="text" name="fathers_name" id="student_fathers_name"
                                        class="form-control"
                                        value="<?php echo htmlspecialchars($student['fathers_name'] ?? ''); ?>"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Date of Birth <span class="text-danger">*</span></label>
                                    <input type="date" name="dob" id="dob" class="form-control"
                                        value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Gender <span class="text-danger">*</span></label>
                                    <select name="gender" id="gender" class="form-control" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Aadhaar Card Number <span class="text-danger">*</span></label>
                                    <input type="text" name="aadhaar" id="aadhaar" class="form-control"
                                        value="<?php echo htmlspecialchars($student['aadhaar'] ?? ''); ?>"
                                        pattern="[0-9]{12}" maxlength="12" required>
                                    <small class="text-muted">12-digit Aadhaar number</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Religion</label>
                                    <select name="religion" id="religion" class="form-control">
                                        <option value="">-- Select Religion --</option>
                                        <?php
                                        $religions = ["Hindu", "Muslim", "Christian", "Sikh", "Buddhist", "Jain", "Parsi", "Other"];
                                        foreach ($religions as $rel):
                                            $selected = ($student['religion'] == $rel) ? 'selected' : '';
                                            echo "<option value=\"$rel\" $selected>$rel</option>";
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Caste (Category)</label>
                                    <select name="caste" id="caste" class="form-control">
                                        <option value="">-- Select Category --</option>
                                        <?php
                                        $castes = ["General", "OBC", "SC", "ST", "NT", "VJNT", "SEBC", "EWS", "Other"];
                                        foreach ($castes as $cst):
                                            $selected = ($student['caste'] == $cst) ? 'selected' : '';
                                            echo "<option value=\"$cst\" $selected>$cst</option>";
                                        endforeach;
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Contact Information -->
                        <div class="section-divider mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-phone"></i> Contact Information
                            </h4>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Mobile No <span class="text-danger">*</span></label>
                                    <input type="text" name="mob" id="mob" class="form-control"
                                        value="<?php echo htmlspecialchars($student['mob'] ?? ''); ?>"
                                        pattern="[0-9]{10}" maxlength="10" required>
                                    <small class="text-muted">10-digit mobile number</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Alternate Mobile No</label>
                                    <input type="text" name="amob" id="amob" class="form-control"
                                        value="<?php echo htmlspecialchars($student['amob'] ?? ''); ?>"
                                        pattern="[0-9]{10}" maxlength="10">
                                    <small class="text-muted">10-digit alternate mobile</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" name="email" id="email" class="form-control"
                                        value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>">
                                    <small class="text-muted">Optional email address</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-9">
                                <div class="form-group">
                                    <label>Residence Address</label>
                                    <textarea name="addr" id="addr" class="form-control"
                                        rows="2"><?php echo htmlspecialchars($student['addr'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>District <span class="text-danger">*</span></label>
                                    <input type="text" name="district" id="district" class="form-control"
                                        value="<?php echo htmlspecialchars($student['district'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Parent/Guardian Information -->
                        <div class="section-divider mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-users"></i> Parent/Guardian Information
                            </h4>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Father's Full Name</label>
                                    <input type="text" name="fathername" id="parent_full_name" class="form-control"
                                        value="<?php echo htmlspecialchars($student['fathername'] ?? $student['fathers_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Parent Mobile No. (for Portal Login) <span class="text-danger">*</span></label>
                                    <input type="text" name="parent_mob" id="parent_mob" class="form-control"
                                        value="<?php echo htmlspecialchars($student['parent_mob'] ?? $student['mob'] ?? ''); ?>"
                                        pattern="[0-9]{10}" maxlength="10" required>
                                    <small class="text-muted">Used for Parent Portal login</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Father's Education</label>
                                    <input type="text" name="fatheredu" id="fatheredu" class="form-control"
                                        value="<?php echo htmlspecialchars($student['fatheredu'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Occupation</label>
                                    <input type="text" name="ocupation" id="ocupation" class="form-control"
                                        value="<?php echo htmlspecialchars($student['ocupation'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label>Office Address</label>
                                    <textarea name="ofcaddr" id="ofcaddr" class="form-control"
                                        rows="2"><?php echo htmlspecialchars($student['ofcaddr'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Additional Information -->
                        <div class="section-divider mb-4">
                            <h4 class="text-primary">
                                <i class="fas fa-info-circle"></i> Additional Information
                            </h4>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Hostel Facilities <span class="text-danger">*</span></label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hostel_required"
                                            id="hostelYes" value="Yes" <?php echo ($student['hostel_required'] == 'Yes') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="hostelYes">Yes</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="hostel_required"
                                            id="hostelNo" value="No" <?php echo ($student['hostel_required'] == 'No' || empty($student['hostel_required'])) ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="hostelNo">No</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Transport Required <span class="text-danger">*</span></label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="transport_required"
                                            id="transportYes" value="Yes" <?php echo ($student['transport_required'] == 'Yes') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="transportYes">Yes</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="transport_required"
                                            id="transportNo" value="No" <?php echo ($student['transport_required'] == 'No' || empty($student['transport_required'])) ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="transportNo">No</label>
                                    </div>
                                    <div id="transport_months_container" class="mt-2 css-edit-student-26f7be">
                                        <label class="small fw-bold mb-1">Duration (Months)</label>
                                        <input type="number" name="transport_months" id="transport_months" class="form-control form-control-sm" 
                                               value="<?php echo htmlspecialchars($student['transport_months'] ?? ''); ?>" min="1" max="12" placeholder="e.g. 1">
                                        <small class="text-muted">Restrict transport billing to these many months.</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>Status <span class="text-danger">*</span></label><br>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="status" id="statusActive"
                                            value="1" <?php echo ($student['status'] == 1) ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="statusActive">Active</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="status" id="statusInactive"
                                            value="0" <?php echo ($student['status'] == 0) ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="statusInactive">Inactive</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Student Information
                        </button>
                        <a href="students.php?view=all" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Mobile number validation
        $('#mob, #amob, #parent_mob').on('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Aadhaar validation
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

        // Form submission via API
        $('#studentEditForm').on('submit', function (e) {
            e.preventDefault();

            var mobile = $('#mob').val();
            var aadhaar = $('#aadhaar').val();

            if (mobile.length !== 10) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Validation Error', 'Please enter a valid 10-digit mobile number');
                } else {
                    alert('Please enter a valid 10-digit mobile number');
                }
                return false;
            }

            var parent_mob = $('#parent_mob').val();
            if (parent_mob.length !== 10) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Validation Error', 'Please enter a valid 10-digit parent mobile number');
                } else {
                    alert('Please enter a valid 10-digit parent mobile number');
                }
                return false;
            }

            if (aadhaar.length !== 12) {
                if (typeof showToast === 'function') {
                    showToast('error', 'Validation Error', 'Please enter a valid 12-digit Aadhaar number');
                } else {
                    alert('Please enter a valid 12-digit Aadhaar number');
                }
                return false;
            }

            // Collect form data
            const formData = {};
            $(this).serializeArray().forEach(item => {
                formData[item.name] = item.value;
            });

            // Show loading on button
            const btn = $(this).find('button[type="submit"]');
            const originalBtnHtml = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Updating...');

            // Submit via API
            $.api.post('students/update', formData).then(response => {
                if (response.success) {
                    if (typeof showToast === 'function') {
                        showToast('success', 'Student Updated!', response.message || 'Student information has been updated successfully.');
                    }

                    setTimeout(() => {
                        // Create a form and submit via POST
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'details.php';
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'id';
                        input.value = formData.student_id;
                        form.appendChild(input);
                        document.body.appendChild(form);
                        form.submit();
                    }, 1500);
                } else {
                    if (typeof showToast === 'function') {
                        showToast('error', 'Error', response.error || response.message || 'Failed to update student.');
                    } else {
                        alert(response.error || response.message || 'Failed to update student.');
                    }
                    btn.prop('disabled', false).html(originalBtnHtml);
                }
            }).catch(error => {
                console.error('API Error:', error);
                
                let errorMessage = 'An unexpected error occurred. Please try again.';
                
                // Try to extract formal error from response if it's a validation error (400)
                if (error.responseJSON && error.responseJSON.error) {
                    errorMessage = error.responseJSON.error;
                } else if (error.responseText) {
                    try {
                        const parsed = JSON.parse(error.responseText);
                        if (parsed.error) errorMessage = parsed.error;
                    } catch(e) {}
                } else if (error.message) {
                    errorMessage = error.message;
                }

                if (typeof showToast === 'function') {
                    showToast('error', 'Error', errorMessage);
                } else {
                    alert(errorMessage);
                }
                btn.prop('disabled', false).html(originalBtnHtml);
            });
        });
    });
</script>