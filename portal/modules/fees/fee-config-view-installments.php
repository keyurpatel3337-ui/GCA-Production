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
$response = $api->get('fees/fee-config-view-installments', ['id' => $config_id]);

if ($response && isset($response['success']) && $response['success']) {
    $config = $response['data']['config'] ?? null;
    $installments = $response['data']['installments'] ?? [];
    $payable_fees = $response['data']['payable_fees'] ?? 0;
} else {
    echo '<p class="text-danger">' . ($response['error'] ?? 'Failed to load installments') . '</p>';
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

<?php
// payable_fees is now provided by the API
?>

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

<div class="alert alert-secondary">
    <strong><i class="fas fa-list-ol"></i> Number of Installments:</strong>
    <span class="badge bg-primary fs-6"><?php
    echo $config['number_of_installments']; ?></span>
    <br><small class="text-muted">Amount per installment:
        ₹<?php
        echo formatIndianCurrency($payable_fees / $config['number_of_installments']); ?></small>
</div>

<h6 class="mb-3"><i class="fas fa-list"></i> Installment Breakdown (Split of Payable Fees)</h6>
<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-dark">
            <tr>
                <th width="10%">#</th>
                <th width="35%">Installment Name</th>
                <th width="20%">Percentage</th>
                <th width="20%">Amount</th>
                <th width="15%">Due Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (empty($installments)): ?>
                <tr>
                    <td colspan="5" class="text-center">No installments defined</td>
                </tr>
                <?php
            else: ?>
                <?php

                $total_percentage = 0;
                $total_installment_amount = 0;
                foreach ($installments as $inst):
                    // Calculate based on payable fees (total - token)
                    $amount = ($inst['percentage'] / 100) * $payable_fees;
                    $total_percentage += $inst['percentage'];
                    $total_installment_amount += $amount;
                    ?>
                    <tr>
                        <td><?php
                        echo $inst['installment_number']; ?></td>
                        <td><?php
                        echo htmlspecialchars($inst['installment_name'] ?? ''); ?></td>
                        <td><?php
                        echo formatIndianCurrency($inst['percentage']); ?>%</td>
                        <td>₹<?php
                        echo formatIndianCurrency($amount); ?></td>
                        <td><?php
                        echo $inst['due_date'] ? date('d-M-Y', strtotime($inst['due_date'])) : 'Not set'; ?></td>
                    </tr>
                    <?php
                endforeach; ?>
                <tr class="table-secondary">
                    <td colspan="2" class="text-end"><strong>Total Installments</strong></td>
                    <td><strong><?php
                    echo formatIndianCurrency($total_percentage); ?>%</strong></td>
                    <td><strong>₹<?php
                    echo formatIndianCurrency($total_installment_amount); ?></strong></td>
                    <td></td>
                </tr>
                <tr class="table-primary">
                    <td colspan="3" class="text-end"><strong>Token Fee</strong></td>
                    <td><strong>₹<?php
                    echo formatIndianCurrency($config['token_fee']); ?></strong></td>
                    <td></td>
                </tr>
                <tr class="table-success">
                    <td colspan="3" class="text-end"><strong>Grand Total</strong></td>
                    <td><strong>₹<?php
                    echo formatIndianCurrency($config['token_fee'] + $total_installment_amount); ?></strong>
                    </td>
                    <td></td>
                </tr>
                <?php
            endif; ?>
        </tbody>
    </table>
</div>

<?php
if (abs($total_percentage - 100) > 0.01): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> Warning: Total percentage is
        <?php
        echo formatIndianCurrency($total_percentage); ?>%, should be 100%
    </div>
    <?php
endif; ?>