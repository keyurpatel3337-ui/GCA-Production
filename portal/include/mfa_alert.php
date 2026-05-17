<?php
/**
 * Shared MFA Alert Component
 * Path: portal/include/mfa_alert.php
 */

// Check for pending login requests (MFA)
$stmt = $conn->prepare("SELECT * FROM tbl_user_devices WHERE user_id = ? AND is_authorized = 0 ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$pending_requests = $stmt->fetchAll();

if (!empty($pending_requests)): ?>
    <link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/include/mfa_alert.css">
    <div class="alert alert-warning border-0 shadow-sm mb-4 rounded-3 p-4">
        <div class="d-flex align-items-center mb-3">
            <i class="fas fa-user-shield fs-3 me-3 text-warning"></i>
            <h5 class="mb-0">Security: Pending Login Requests</h5>
        </div>
        <div class="row g-3">
            <?php foreach ($pending_requests as $req): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="bg-white p-3 rounded-3 border d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold text-dark small"><?php echo htmlspecialchars($req['device_name'] ?? ''); ?></div>
                            <div class="text-muted mfa_alert-custom-1">
                                Requested: <?php echo date('h:i A, d M', strtotime($req['created_at'])); ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button onclick="handleMFA(<?php echo $req['id']; ?>, 'approve')" class="btn btn-success btn-sm px-3">Approve</button>
                            <button onclick="handleMFA(<?php echo $req['id']; ?>, 'reject')" class="btn btn-outline-danger btn-sm">Reject</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
    async function handleMFA(deviceId, action) {
        if (!confirm(`Are you sure you want to ${action} this device?`)) return;
        
        try {
            const formData = new FormData();
            formData.append('device_id', deviceId);
            formData.append('action', action);
            
            // Adjust path based on current location (portal/modules/dashboard/...)
            const response = await fetch('<?php echo PORTAL_URL; ?>/modules/dashboard/ajax/mfa_actions.php', {
                method: 'POST',
                body: formData
            });
            const text = await response.text();
            console.log('MFA Response:', text);
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('MFA JSON Parse Error:', e);
                alert('An error occurred. Invalid response from server.');
                return;
            }
            
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Action failed');
            }
        } catch (error) {
            console.error('MFA Error:', error);
            alert('An error occurred. Please try again.');
        }
    }
    </script>
<?php endif; ?>
