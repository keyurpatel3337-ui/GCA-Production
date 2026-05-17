<?php
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once __DIR__ . '/../../../common/helpers/format_helper.php';

$config_id = $_POST['id'] ?? 0;
if (!$config_id) {
    echo '<p class="text-danger">Config ID is required</p>';
    exit;
}

$api = new APIClient();
$response = $api->get('fees/fee-config-view', ['id' => $config_id]);

if ($response && isset($response['success']) && $response['success']) {
    $config = $response['data']['config'] ?? null;
    $payable_fees = $response['data']['payable_fees'] ?? 0;
    $per_installment = $response['data']['per_installment'] ?? 0;
} else {
    echo '<p class="text-danger">' . ($response['error'] ?? 'Failed to load configuration') . '</p>';
    exit;
}
?>

<div class="row mb-3">
    <div class="col-md-6">
        <strong>Course:</strong> <?php echo htmlspecialchars($config['course_name'] ?? 'N/A'); ?>
    </div>
    <div class="col-md-6">
        <strong>Academic Year:</strong> <?php echo htmlspecialchars($config['academic_year'] ?? ''); ?>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="alert alert-primary">
            <strong><i class="fas fa-tag"></i> Token Fee:</strong><br>
            <span class="fs-5">₹<?php
            echo formatIndianCurrency($config['token_fee']); ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="alert alert-success">
            <strong><i class="fas fa-money-bill-wave"></i> Total Fees:</strong><br>
            <span class="fs-5">₹<?php
            echo formatIndianCurrency($config['total_fees']); ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="alert alert-info">
            <strong><i class="fas fa-calculator"></i> Payable Fees:</strong><br>
            <span class="fs-5">₹<?php
            echo formatIndianCurrency($payable_fees); ?></span>
            <br><small class="text-muted">(Total - Token)</small>
        </div>
    </div>
</div>

<div class="alert alert-secondary mb-3">
    <div class="row">
        <div class="col-md-6">
            <strong><i class="fas fa-list-ol"></i> Number of Installments:</strong>
            <span class="badge bg-primary fs-6"><?php
            echo $config['number_of_installments']; ?></span>
        </div>
        <div class="col-md-6">
            <strong><i class="fas fa-calculator"></i> Per Installment:</strong>
            <span class="badge bg-success fs-6">₹<?php
            echo formatIndianCurrency($per_installment); ?></span>
        </div>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th width="25%">Installment</th>
                <th width="75%">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php
            for ($i = 1; $i <= $config['number_of_installments']; $i++): ?>
                <tr>
                    <td><strong>Installment <?php
                    echo $i; ?></strong></td>
                    <td>₹<?php
                    echo formatIndianCurrency($per_installment); ?></td>
                </tr>
                <?php
            endfor; ?>
        </tbody>
        <tfoot class="table-dark">
            <tr>
                <td><strong>Total Payable</strong></td>
                <td><strong>₹<?php
                echo formatIndianCurrency($payable_fees); ?></strong></td>
            </tr>
        </tfoot>
    </table>
</div>

<div class="alert alert-info">
    <small><i class="fas fa-info-circle"></i> These installments will be automatically created when fees are assigned to
        students.</small>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <strong>Status:</strong>
        <?php
        if ($config['is_active']): ?>
            <span class="badge bg-success">Active</span>
            <?php
        else: ?>
            <span class="badge bg-secondary">Inactive</span>
            <?php
        endif; ?>
    </div>
    <div class="col-md-6">
        <strong>Created:</strong> <?php
        echo date('d M Y, h:i A', strtotime($config['created_at'])); ?>
    </div>
</div>
