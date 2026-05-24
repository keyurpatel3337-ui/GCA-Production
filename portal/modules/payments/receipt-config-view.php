<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

if (!hasRole(ROLE_SUPER_ADMIN)) {
    echo '<p class="text-danger">Unauthorized access</p>';
    exit;
}

$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo '<p class="text-danger">Invalid ID</p>';
    exit;
}

try {
    $config = $dbOps->selectOne('tbl_receipt_configuration', ['*'], ['id' => $id]);

    if (!$config) {
        echo '<p class="text-danger">Receipt configuration not found</p>';
        exit;
    }
    ?>

    <table class="table table-bordered">
        <tr>
            <th width="30%" class="bg-light">Receipt Title</th>
            <td><?php echo htmlspecialchars($config['receipt_title'] ?? ''); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Organization Name</th>
            <td><?php echo htmlspecialchars($config['organization_name'] ?? ''); ?></td>
        </tr>
        <tr>
            <th class="bg-light">GST Number</th>
            <td><?php echo htmlspecialchars($config['gst_number'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">PAN Number</th>
            <td><?php echo htmlspecialchars($config['pan_number'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Address</th>
            <td>
                <?php
                $address_parts = array_filter([
                    $config['address'],
                    $config['city'],
                    $config['state'],
                    $config['pincode']
                ]);
                echo htmlspecialchars(implode(', ', $address_parts) ?: '-' ?? '');
                ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Phone</th>
            <td><?php echo htmlspecialchars($config['phone'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Email</th>
            <td><?php echo htmlspecialchars($config['email'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Website</th>
            <td><?php echo htmlspecialchars($config['website'] ?? '-'); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Logo</th>
            <td>
                <?php if (!empty($config['logo_path'])): ?>
                    <?php
                    $logo_path = str_replace('../', '', $config['logo_path']);
                    $logo_url = BACKEND_URL . '/' . $logo_path;
                    ?>
                    <img src="<?php echo htmlspecialchars($logo_url ?? ''); ?>" alt="Logo" class="img-thumbnail"
                        style="max-height: 80px;" onerror="this.style.display='none'">
                <?php else: ?>
                    <span class="text-muted">No logo uploaded</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Signature</th>
            <td>
                <?php if (!empty($config['signature_path'])): ?>
                    <?php
                    $sig_path = str_replace('../', '', $config['signature_path']);
                    $sig_url = BACKEND_URL . '/' . $sig_path;
                    ?>
                    <img src="<?php echo htmlspecialchars($sig_url ?? ''); ?>" alt="Signature" class="img-thumbnail"
                        style="max-height: 80px;" onerror="this.style.display='none'">
                <?php else: ?>
                    <span class="text-muted">No signature uploaded</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Authorized Signatory</th>
            <td>
                <?php echo htmlspecialchars($config['authorized_signatory'] ?? '-'); ?>
                <?php if ($config['designation']): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($config['designation'] ?? ''); ?></small>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Footer Text</th>
            <td><?php echo nl2br(htmlspecialchars($config['footer_text'] ?? '-')); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Terms & Conditions</th>
            <td><?php echo nl2br(htmlspecialchars($config['terms_conditions'] ?? '-')); ?></td>
        </tr>
        <tr>
            <th class="bg-light">Status</th>
            <td>
                <?php if ($config['is_active']): ?>
                    <span class="badge bg-success">Active/Default</span>
                <?php else: ?>
                    <span class="badge bg-secondary">Inactive</span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th class="bg-light">Created At</th>
            <td><?php echo date('d M Y, h:i A', strtotime($config['created_at'])); ?></td>
        </tr>
        <?php if ($config['updated_at']): ?>
            <tr>
                <th class="bg-light">Last Updated</th>
                <td><?php echo date('d M Y, h:i A', strtotime($config['updated_at'])); ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <?php
} catch (PDOException $e) {
    echo '<p class="text-danger">Database error: ' . htmlspecialchars($e->getMessage() ?? '') . '</p>';
}
?>