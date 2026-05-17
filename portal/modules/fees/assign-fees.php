<?php
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once __DIR__ . '/../../session_config.php';
require_once ENV_CONFIG_FILE;
require_once __DIR__ . '/../../common/api_client.php';
require_once PORTAL_GLOBALVARIABLE;
require_once dirname(dirname(dirname(__DIR__))) . '/common/helpers/format_helper.php';

// Check if user is Super Admin
if (!hasRole(ROLE_SUPER_ADMIN)) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$api = new APIClient();
$results = [];
$has_processing_results = false;

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = $_POST;
    $action = '';

    if (isset($_POST['assign_individual'])) {
        $action = 'assign_individual';
    } elseif (isset($_POST['assign_bulk'])) {
        $action = 'assign_bulk';
    }

    if ($action) {
        $response = $api->post("fees/assign-fees&action=$action", $post_data);
        if ($response && isset($response['success']) && $response['success']) {
            $results = $response['data']['results'] ?? [];
            $has_processing_results = $response['data']['has_processing_results'] ?? false;

            if (isset($response['data']['success_count'])) {
                $_SESSION['message_type'] = $response['data']['success_count'] > 0 ? 'success' : 'warning';
                $_SESSION['message'] = "Bulk assignment completed: {$response['data']['success_count']} successful, {$response['data']['error_count']} failed/skipped";
            } else {
                $_SESSION['message_type'] = 'success';
                $_SESSION['message'] = 'Individual fee assignment processed';
            }
        } else {
            $_SESSION['message_type'] = 'danger';
            $_SESSION['message'] = $response['error'] ?? 'Failed to process fee assignment';
        }
    }
}

// Fetch Initial Data
$response = $api->get('fees/assign-fees');

if ($response && isset($response['success']) && $response['success']) {
    $fee_configs = $response['data']['fee_configs'] ?? [];
    $students = $response['data']['students'] ?? [];
} else {
    $fee_configs = [];
    $students = [];
    $_SESSION['message_type'] = 'danger';
    $_SESSION['message'] = $response['error'] ?? 'Failed to load fee configurations and students';
}

$page_title = "Assign Fees to Students";

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>
<link rel="stylesheet" href="<?php echo PORTAL_URL; ?>/assets/css/modules/fees/assign-fees.css">





    <div class="container-fluid">

        <?php
        if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php
            echo gca_safe_attr($_SESSION['message_type']); ?> alert-dismissible fade show">
                <?php

                echo gca_safe_html($_SESSION['message']);
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php
        endif; ?>

        <!-- Individual Assignment -->
        <div class="card mb-4">
            <div class="card-header bg-primary-custom text-white">
                <h3 class="card-title"><i class="fas fa-user"></i> Individual Fee Assignment</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="individualAssignForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Student <span class="text-danger">*</span></label>
                                <select name="student_id" id="student_id" class="form-control" required>
                                    <option value="">-- Select Student --</option>
                                    <?php
                                    foreach ($students as $student): ?>
                                        <option value="<?php
                                        echo $student['id']; ?>">
                                            <?php
                                            echo htmlspecialchars($student['surname'] . ', ' . $student['student_name'] . ', ' . $student['fathers_name'] ?? '') . ' (' . htmlspecialchars($student['enrollment_id'] ?? '') . ')'; ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Fee Configuration <span
                                        class="text-danger">*</span></label>
                                <select name="fee_config_id" id="fee_config_id" class="form-control" required
                                    onchange="updateFeeDetails('individual')">
                                    <option value="">-- Select Fee Configuration --</option>
                                    <?php
                                    foreach ($fee_configs as $config): ?>
                                        <option value="<?php
                                        echo $config['id']; ?>" data-course="<?php
                                          echo htmlspecialchars($config['course_name'] ?? ''); ?>" data-year="<?php
                                            echo htmlspecialchars($config['academic_year'] ?? ''); ?>" data-token="<?php
                                              echo $config['token_fee']; ?>" data-total="<?php
                                                echo $config['total_fees']; ?>" data-payable="<?php
                                                  echo $config['payable_fees']; ?>" data-installments="<?php
                                                    echo $config['number_of_installments']; ?>">
                                            <?php
                                            echo htmlspecialchars($config['academic_year'] ?? '') . ' - ' .
                                                htmlspecialchars($config['term'] ?? 'N/A') . ' | ' .
                                                htmlspecialchars($config['school_short_name'] ?? 'N/A') . ' | ' .
                                                htmlspecialchars($config['course_name'] ?? '') . ' | ' .
                                                htmlspecialchars($config['group_name'] ?? 'N/A') . ' | ₹' .
                                                formatIndianCurrency($config['payable_fees']); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="individual-fee-details" class="alert alert-info assign-fees-custom-1">
                        <h6><i class="fas fa-info-circle"></i> Fee Details:</h6>
                        <ul class="mb-0">
                            <li>Course: <strong id="ind-course">-</strong></li>
                            <li>Academic Year: <strong id="ind-year">-</strong></li>
                            <li>Total Fees: <strong id="ind-total">?0.00</strong></li>
                            <li>Token Fee: <strong id="ind-token">?0.00</strong></li>
                            <li>Payable Fees: <strong id="ind-payable">?0.00</strong></li>
                            <li>Number of Installments: <strong id="ind-installments">0</strong></li>
                            <li>Amount per Installment: <strong id="ind-per-installment">?0.00</strong></li>
                        </ul>
                    </div>

                    <button type="submit" name="assign_individual" class="btn btn-primary-custom">
                        <i class="fas fa-check"></i> Assign Fees
                    </button>
                </form>
            </div>
        </div>

        <!-- Bulk Assignment -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h3 class="card-title"><i class="fas fa-users"></i> Bulk Fee Assignment</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="bulkAssignForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Fee Configuration <span
                                        class="text-danger">*</span></label>
                                <select name="fee_config_id" id="bulk_fee_config_id" class="form-control" required
                                    onchange="updateFeeDetails('bulk')">
                                    <option value="">-- Select Fee Configuration --</option>
                                    <?php
                                    foreach ($fee_configs as $config): ?>
                                        <option value="<?php
                                        echo $config['id']; ?>" data-course="<?php
                                          echo htmlspecialchars($config['course_name'] ?? ''); ?>" data-year="<?php
                                            echo htmlspecialchars($config['academic_year'] ?? ''); ?>" data-token="<?php
                                              echo $config['token_fee']; ?>" data-total="<?php
                                                echo $config['total_fees']; ?>" data-payable="<?php
                                                  echo $config['payable_fees']; ?>" data-installments="<?php
                                                    echo $config['number_of_installments']; ?>">
                                            <?php
                                            echo htmlspecialchars($config['academic_year'] ?? '') . ' - ' .
                                                htmlspecialchars($config['term'] ?? 'N/A') . ' | ' .
                                                htmlspecialchars($config['school_short_name'] ?? 'N/A') . ' | ' .
                                                htmlspecialchars($config['course_name'] ?? '') . ' | ' .
                                                htmlspecialchars($config['group_name'] ?? 'N/A') . ' | ₹' .
                                                formatIndianCurrency($config['payable_fees']); ?>
                                        </option>
                                        <?php
                                    endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="bulk-fee-details" class="alert alert-info assign-fees-custom-1">
                        <h6><i class="fas fa-info-circle"></i> Fee Details:</h6>
                        <ul class="mb-0">
                            <li>Course: <strong id="bulk-course">-</strong></li>
                            <li>Academic Year: <strong id="bulk-year">-</strong></li>
                            <li>Total Fees: <strong id="bulk-total">?0.00</strong></li>
                            <li>Token Fee: <strong id="bulk-token">?0.00</strong></li>
                            <li>Payable Fees: <strong id="bulk-payable">?0.00</strong></li>
                            <li>Number of Installments: <strong id="bulk-installments">0</strong></li>
                            <li>Amount per Installment: <strong id="bulk-per-installment">?0.00</strong></li>
                        </ul>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select Students <span class="text-danger">*</span></label>
                        <div class="form-check mb-2">
                            <input type="checkbox" class="form-check-input" id="select_all"
                                onclick="toggleSelectAll(this)">
                            <label class="form-check-label" for="select_all">
                                <strong>Select All Students</strong>
                            </label>
                        </div>
                        <div class="assign-fees-custom-2">
                            <?php
                            foreach ($students as $student): ?>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input student-checkbox" name="student_ids[]"
                                        value="<?php
                                        echo $student['id']; ?>" id="student_<?php
                                          echo $student['id']; ?>">
                                    <label class="form-check-label" for="student_<?php
                                    echo $student['id']; ?>">
                                        <?php
                                        echo htmlspecialchars($student['surname'] . ', ' . $student['student_name'] . ', ' . $student['fathers_name'] ?? '') . ' (' . htmlspecialchars($student['enrollment_id'] ?? '') . ')'; ?>
                                    </label>
                                </div>
                                <?php
                            endforeach; ?>
                        </div>
                    </div>

                    <button type="submit" name="assign_bulk" class="btn btn-success">
                        <i class="fas fa-check-double"></i> Assign Fees to Selected Students
                    </button>
                </form>
            </div>
        </div>

        <!-- Results Display -->
        <?php
        if (!empty($results)): ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h3 class="card-title"><i class="fas fa-list"></i> Assignment Results</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-primary-custom">
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Standard</th>
                                    <th>Academic Year</th>
                                    <th>Total Fees</th>
                                    <th>Token Fee</th>
                                    <th>Payable Fees</th>
                                    <th>Installments</th>
                                    <th>Per Installment</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($results as $result): ?>
                                    <tr class="<?php

                                    echo $result['type'] === 'success' ? 'table-success' : ($result['type'] === 'warning' ? 'table-warning' : 'table-danger');
                                    ?>">
                                        <td><?php
                                        echo $result['student_id']; ?></td>
                                        <td><?php
                                        echo $result['student_name'] ?? '-'; ?></td>
                                        <td><?php
                                        echo $result['course'] ?? '-'; ?></td>
                                        <td><?php
                                        echo $result['academic_year'] ?? '-'; ?></td>
                                        <td><?php
                                        echo isset($result['total_fees']) ? '₹' . formatIndianCurrency($result['total_fees']) : '-'; ?>
                                        </td>
                                        <td><?php
                                        echo isset($result['token_fee']) ? '₹' . formatIndianCurrency($result['token_fee']) : '-'; ?>
                                        </td>
                                        <td><?php
                                        echo isset($result['payable_fees']) ? '₹' . formatIndianCurrency($result['payable_fees']) : '-'; ?>
                                        </td>
                                        <td><?php
                                        echo $result['installments'] ?? '-'; ?></td>
                                        <td><?php
                                        echo isset($result['per_installment']) ? '₹' . formatIndianCurrency($result['per_installment']) : '-'; ?>
                                        </td>
                                        <td><?php
                                        echo $result['message']; ?></td>
                                    </tr>
                                    <?php
                                endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
        endif; ?>

    </div>

    <?php
    include '../../include/footer.php'; ?>

    <script>
        function updateFeeDetails(type) {
            const selectId = type === 'individual' ? 'fee_config_id' : 'bulk_fee_config_id';
            const select = document.getElementById(selectId);
            const selectedOption = select.options[select.selectedIndex];

            if (selectedOption.value) {
                const prefix = type === 'individual' ? 'ind' : 'bulk';
                const course = selectedOption.getAttribute('data-course');
                const year = selectedOption.getAttribute('data-year');
                const token = parseFloat(selectedOption.getAttribute('data-token'));
                const total = parseFloat(selectedOption.getAttribute('data-total'));
                const payable = parseFloat(selectedOption.getAttribute('data-payable'));
                const installments = parseInt(selectedOption.getAttribute('data-installments'));
                const perInstallment = payable / installments;

                document.getElementById(`${prefix}-course`).textContent = course;
                document.getElementById(`${prefix}-year`).textContent = year;
                document.getElementById(`${prefix}-total`).textContent = '₹' + total.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                });
                document.getElementById(`${prefix}-token`).textContent = '₹' + token.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                });
                document.getElementById(`${prefix}-payable`).textContent = '₹' + payable.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                });
                document.getElementById(`${prefix}-installments`).textContent = installments;
                document.getElementById(`${prefix}-per-installment`).textContent = '₹' + perInstallment.toLocaleString('en-IN', {
                    minimumFractionDigits: 2
                });

                document.getElementById(`${type}-fee-details`).style.display = 'block';
            } else {
                document.getElementById(`${type}-fee-details`).style.display = 'none';
            }
        }

        function toggleSelectAll(checkbox) {
            const studentCheckboxes = document.querySelectorAll('.student-checkbox');
            studentCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        // Form validation
        document.getElementById('bulkAssignForm').addEventListener('submit', function (e) {
            const checkedBoxes = document.querySelectorAll('.student-checkbox:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                showToast('warning', 'No Students Selected', 'Please select at least one student for bulk assignment');
            }
        });
    </script>

</body>

</html>