<?php

require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../common/api_client.php';

// Load user profile via API
// Get user ID from session
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? 0;

// Load user profile via API
$api = new APIClient();
$response = $api->get('profile/details', ['user_id' => $user_id]);

// Extract data from API response
if ($response && isset($response['success']) && $response['success']) {
    $data = $response['data'] ?? [];
    $user = $data['profile'] ?? $data['user'] ?? [];
} else {
    // Fallback to default values if API fails
    error_log("Profile API Error: Failed to fetch data for user_id: $user_id. Response: " . json_encode($response));
    $user = [];
}

$page_title = "My Profile";
include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>




    <div class="container-fluid">
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php
                echo $_SESSION['success_msg'];
                ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <?php
                echo $_SESSION['error_msg'];
                ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle"
                                src="../../uploads/profiles/<?php echo $user['profile_image'] ?? 'default.png'; ?>"
                                alt="User profile picture" style="width: 100px; height: 100px;">
                        </div>
                        <h3 class="profile-username text-center">
                            <?php echo htmlspecialchars($user['name'] ?? 'Guest'); ?>
                        </h3>
                        <p class="text-muted text-center"><?php echo htmlspecialchars($user['role_name'] ?? 'User'); ?>
                        </p>

                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>Email</b> <a
                                    class="float-end"><?php echo htmlspecialchars($user['email'] ?? ''); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>Phone</b> <a
                                    class="float-end"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>Status</b>
                                <span
                                    class="badge badge-<?php echo ($user['status'] ?? '') == 'active' ? 'success' : 'danger'; ?> float-end">
                                    <?php echo ucfirst($user['status'] ?? 'unknown'); ?>
                                </span>
                            </li>
                            <li class="list-group-item">
                                <b>Last Login</b> <a
                                    class="float-end"><?php echo !empty($user['last_login']) ? date('d M Y H:i', strtotime($user['last_login'])) : 'N/A'; ?></a>
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
                                <form id="profileUpdateForm" method="POST" enctype="multipart/form-data">
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Full Name</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="name" class="form-control"
                                                value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Email</label>
                                        <div class="col-sm-9">
                                            <input type="email" name="email" class="form-control"
                                                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Phone</label>
                                        <div class="col-sm-9">
                                            <input type="text" name="phone" class="form-control"
                                                value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Address</label>
                                        <div class="col-sm-9">
                                            <textarea name="address" class="form-control"
                                                rows="3"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
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
                                <form id="passwordChangeForm" method="POST">
                                    <div class="form-group row">
                                        <label class="col-sm-3 col-form-label">Current Password</label>
                                        <div class="col-sm-9">
                                            <input type="password" name="current_password" class="form-control"
                                                required>
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
                                            <input type="password" name="confirm_password" class="form-control"
                                                required>
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

<?php include '../../include/footer.php'; ?>

<script>
    // Profile Update Form Submission
    $('#profileUpdateForm').on('submit', function (e) {
        e.preventDefault();

        const formData = new FormData(this);

        $.api.post('profile/update', formData, {
            processData: false,
            contentType: false
        }).then(response => {
            if (response.success) {
                showToast('success', 'Success', response.message || 'Profile updated successfully.');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('error', 'Error', response.error || response.message || 'Failed to update profile.');
            }
        }).catch(error => {
            console.error('API Error:', error);
            showToast('error', 'Error', error.message || 'An error occurred.');
        });
    });

    // Password Change Form Submission
    $('#passwordChangeForm').on('submit', function (e) {
        e.preventDefault();

        const newPassword = $('input[name="new_password"]').val();
        const confirmPassword = $('input[name="confirm_password"]').val();

        if (newPassword !== confirmPassword) {
            showToast('error', 'Error', 'Passwords do not match.');
            return false;
        }

        const formData = {};
        $(this).serializeArray().forEach(item => { formData[item.name] = item.value; });

        $.api.post('profile/password-update', formData).then(response => {
            if (response.success) {
                showToast('success', 'Success', response.message || 'Password changed successfully.');
                this.reset();
            } else {
                showToast('error', 'Error', response.error || response.message || 'Failed to change password.');
            }
        }).catch(error => {
            console.error('API Error:', error);
            showToast('error', 'Error', error.message || 'An error occurred.');
        });
    });
</script>