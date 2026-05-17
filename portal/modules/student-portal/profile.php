<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load student profile via API
$api = new APIClient();
$response = $api->get('student-portal/profile', ['student_id' => $user_id]);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $user = $data['user'] ?? [];
    $enrollment = $data['enrollment'] ?? [];
} else {
    // Fallback to default values if API fails
    $user = [];
    $enrollment = [];
}

$page_title = "My Profile";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




<div class="container-fluid">
    <?php
    if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <?php
            echo gca_safe_html($_SESSION['success_msg']);
            ?>
        </div>
        <?php
    endif; ?>
    <?php
    if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            <?php
            echo gca_safe_html($_SESSION['error_msg']);
            ?>
        </div>
        <?php
    endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card card-primary card-outline">
                <div class="card-body box-profile">
                    <div class="text-center">
                        <img class="profile-user-img img-fluid img-circle" src="../uploads/profiles/<?php
                        echo $user['profile_image'] ?? 'default.png'; ?>" alt="User profile picture"
                            style="width: 100px; height: 100px;">
                    </div>
                    <h3 class="profile-username text-center"><?php
                    echo htmlspecialchars($user['name'] ?? ''); ?></h3>
                    <p class="text-muted text-center"><?php
                    echo htmlspecialchars($user['role_name'] ?? ''); ?></p>
 
                    <ul class="list-group list-group-unbordered mb-3">
                        <li class="list-group-item">
                            <b>Email</b> <a class="float-end"><?php
                            echo htmlspecialchars($user['email'] ?? ''); ?></a>
                        </li>
                        <li class="list-group-item">
                            <b>Phone</b> <a class="float-end"><?php
                            echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></a>
                        </li>
                        <li class="list-group-item">
                            <b>Status</b>
                            <span class="badge badge-<?php
                            echo ($user['status'] ?? '') == 'active' ? 'success' : 'danger'; ?> float-end">
                                <?php
                                echo ucfirst($user['status'] ?? ''); ?>
                            </span>
                        </li>
                        <li class="list-group-item">
                            <b>Last Login</b> <a
                                class="float-end"><?php
                                echo !empty($user['last_login']) ? date('d M Y H:i', strtotime($user['last_login'])) : 'N/A'; ?></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header p-2">
                    <ul class="nav nav-pills">
                        <li class="nav-item"><a class="nav-link active" href="#details" data-bs-toggle="tab">Profile
                                Details</a></li>
                        <li class="nav-item"><a class="nav-link" href="#password" data-bs-toggle="tab">Change
                                Password</a></li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <div class="active tab-pane" id="details">
                            <form action="profile-update.php" method="POST" enctype="multipart/form-data">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Full Name</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="name" class="form-control" value="<?php
                                        echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Email</label>
                                    <div class="col-sm-9">
                                        <input type="email" name="email" class="form-control" value="<?php
                                        echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Phone</label>
                                    <div class="col-sm-9">
                                        <input type="text" name="phone" class="form-control" value="<?php
                                        echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Address</label>
                                    <div class="col-sm-9">
                                        <textarea name="address" class="form-control" rows="3"><?php
                                        echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Profile Image</label>
                                    <div class="col-sm-9">
                                        <input type="file" name="profile_image" class="form-control-file">
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="offset-sm-3 col-sm-9">
                                        <button type="submit" class="btn btn-primary">Update Profile</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane" id="password">
                            <form id="passwordUpdateForm">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Current Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" name="current_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">New Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" name="new_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Confirm Password</label>
                                    <div class="col-sm-9">
                                        <input type="password" name="confirm_password" class="form-control" required>
                                    </div>
                                </div>
                                <div class="form-group row">
                                    <div class="offset-sm-3 col-sm-9">
                                        <button type="submit" class="btn btn-danger">Change Password</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Password Update Form Handler
        $('#passwordUpdateForm').on('submit', function (e) {
            e.preventDefault();

            const newPassword = $('input[name="new_password"]').val();
            const confirmPassword = $('input[name="confirm_password"]').val();

            if (newPassword !== confirmPassword) {
                showToast('error', 'Error', 'New password and confirm password do not match');
                return false;
            }

            $.api.post('student-portal/password-update', $(this).serialize())
                .then(response => {
                    if (response.success) {
                        $(this)[0].reset();
                        showToast('success', 'Success!', response.message);
                    } else {
                        showToast('error', 'Error!', response.error || response.message);
                    }
                }).catch(error => showToast('error', 'Error!', error.message || 'Failed to update password'));
        });
    });
</script>