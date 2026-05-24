<?php
/**
 * Create Installment - Direct Creation by Principal/Accountant
 * Allows authorized users to create installments directly for any student
 */
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;
require_once __DIR__ . '/../../common/security_output.php';

// Check if user has permission (Principal, Super Admin, or Accountant)
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN) && !hasRole(ROLE_ACCOUNTANT)) {
    set_flash_message('error', 'Unauthorized access');
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Create Installment";
$page_breadcrumb = "Create Installment for Student";

$op = new Operation();

// Fee components
$fee_components = [
    'school_fee' => 'School Fee',
    'trust_facilities_fee' => 'Trust Facilities Fee',
    'tuition_fee_part2' => 'Tuition Fee Part 2'
];

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Direct Installment Creation</h3>
            <div class="card-tools">
                <span class="badge bg-info">
                    <i class="fas fa-info-circle"></i> Creates installments directly without approval workflow
                </span>
            </div>
        </div>
        <div class="card-body">
            <form id="createInstallmentForm" method="POST" action="create-installment-process.php">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Select Student <span class="text-danger">*</span></label>
                            <div class="student-search-wrapper">
                                <div class="input-group">
                                    <span class="input-group-text bg-light">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" id="student_search" class="form-control student-search-input"
                                        placeholder="Search by Name, Mobile or Enrollment Number" autocomplete="off">
                                </div>
                                <input type="hidden" name="student_id" id="student_id" required>
                                <div id="student_search_results"></div>
                            </div>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i> Type at least 2 characters to search
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Student Details</label>
                            <div id="student_details" class="p-3 bg-light rounded">
                                <span class="text-muted">Select a student to see details</span>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Fee Component <span class="text-danger">*</span></label>
                            <select name="fee_component" id="fee_component" class="form-select" required>
                                <option value="">-- Select Fee Component --</option>
                                <?php foreach ($fee_components as $key => $label): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Total Amount (₹) <span class="text-danger">*</span></label>
                            <input type="number" name="total_amount" id="total_amount" class="form-control" min="100"
                                step="1" required placeholder="Enter total amount">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Number of Installments <span class="text-danger">*</span></label>
                            <select name="num_installments" id="num_installments" class="form-select" required>
                                <option value="">-- Select --</option>
                                <?php for ($i = 2; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> Installments</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">First Installment Date <span class="text-danger">*</span></label>
                            <input type="date" name="first_due_date" id="first_due_date" class="form-control" required
                                min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Interval (Days)</label>
                            <input type="number" name="interval_days" id="interval_days" class="form-control" value="30"
                                min="7" max="90">
                            <small class="text-muted">Days between each installment (default: 30)</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Each Installment Amount</label>
                            <input type="text" id="installment_amount_preview" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2"
                        placeholder="Optional remarks for this installment creation"></textarea>
                </div>

                <!-- Installment Schedule Preview -->
                <div id="schedule_preview" class="mb-3" style="display: none;">
                    <label class="form-label">Installment Schedule Preview</label>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Installment #</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="schedule_body">
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Note:</strong> This will create installments directly without going through the
                    request/approval workflow.
                    The installments will be immediately visible in the student's fee details.
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Create Installments
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Store fee configuration for the selected student
        let currentFeeConfig = null;

        // Initialize Student Search Component
        const studentSearch = new StudentSearchComponent({
            inputId: 'student_search',
            hiddenInputId: 'student_id',
            resultsContainerId: 'student_search_results',
            detailsContainerId: 'student_details',
            onSelect: function (student) {
                // Add selected class to input
                $('#student_search').addClass('has-selection');
                console.log('Student selected:', student);

                // Display student details
                const fullName = student.surname + ' ' + student.student_name + ' ' + student.fathers_name;
                const detailsHtml = `
                <div class="alert alert-info mb-0">
                    <div class="row">
                        <div class="col-md-6">
                            <strong><i class="fas fa-user"></i> ${fullName}</strong><br>
                            <small class="text-muted">Enrollment: ${student.enrollment_id || 'Not Enrolled'} | Mobile: ${student.mob || 'N/A'}</small>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="badge bg-primary">${student.course_name || 'N/A'}</span>
                            <span class="badge bg-secondary">${student.group_name || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `;
                $('#student_details').html(detailsHtml);

                // Fetch fee configuration for this student
                fetchStudentFeeConfig(student.id);
            }
        });

        // Fetch fee configuration for student
        function fetchStudentFeeConfig(studentId) {
            currentFeeConfig = null;
            $('#fee_component').val('');
            $('#total_amount').val('');

            $.ajax({
                url: '<?php echo BACKEND_URL; ?>/index.php?route=common/get-student-fee-config',
                method: 'GET',
                data: { student_id: studentId },
                dataType: 'json',
                success: function (response) {
                    if (response.success && response.fee_config) {
                        currentFeeConfig = response.fee_config;
                        console.log('Fee config loaded:', currentFeeConfig);
                    } else {
                        console.log('No fee config found:', response.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Error fetching fee config:', error);
                }
            });
        }

        // Auto-populate amount when fee component is selected
        $('#fee_component').on('change', function () {
            const selectedComponent = $(this).val();

            if (!selectedComponent) {
                $('#total_amount').val('');
                return;
            }

            if (currentFeeConfig) {
                let amount = 0;

                switch (selectedComponent) {
                    case 'school_fee':
                        amount = parseFloat(currentFeeConfig.school_fee) || 0;
                        break;
                    case 'trust_facilities_fee':
                        amount = parseFloat(currentFeeConfig.trust_facilities_fee) || 0;
                        break;
                    case 'tuition_fee_part2':
                        // Tuition Part 2 has 18% GST
                        const baseTuition = parseFloat(currentFeeConfig.tuition_fee_part2) || 0;
                        const gst = baseTuition * 0.18;
                        amount = baseTuition + gst;
                        break;
                }

                if (amount > 0) {
                    $('#total_amount').val(Math.round(amount));
                    updatePreview();
                }
            } else {
                console.log('Fee config not loaded yet');
            }
        });

        // Clear selection styling when input is cleared
        $('#student_search').on('input', function () {
            if ($(this).val().trim() === '') {
                $(this).removeClass('has-selection');
                $('#student_details').html('<span class="text-muted">Select a student to see details</span>');
                currentFeeConfig = null;
                $('#fee_component').val('');
                $('#total_amount').val('');
            }
        });

        // Calculate installment amount and preview
        function updatePreview() {
            const totalAmount = parseFloat($('#total_amount').val()) || 0;
            const numInstallments = parseInt($('#num_installments').val()) || 0;
            const firstDueDate = $('#first_due_date').val();
            const intervalDays = parseInt($('#interval_days').val()) || 30;

            if (totalAmount > 0 && numInstallments > 0) {
                const installmentAmount = Math.round(totalAmount / numInstallments);
                $('#installment_amount_preview').val('\u20B9' + installmentAmount);

                if (firstDueDate) {
                    let scheduleHtml = '';
                    let currentDate = new Date(firstDueDate);

                    for (let i = 1; i <= numInstallments; i++) {
                        const dateStr = currentDate.toLocaleDateString('en-IN', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric'
                        });
                        scheduleHtml += `
                        <tr>
                            <td>${i}</td>
                            <td>${dateStr}</td>
                            <td>\u20B9${installmentAmount}</td>
                        </tr>
                    `;
                        currentDate.setDate(currentDate.getDate() + intervalDays);
                    }

                    $('#schedule_body').html(scheduleHtml);
                    $('#schedule_preview').show();
                }
            } else {
                $('#installment_amount_preview').val('');
                $('#schedule_preview').hide();
            }
        }

        $('#total_amount, #num_installments, #first_due_date, #interval_days').on('change keyup', updatePreview);

        // Form submission
        $('#createInstallmentForm').on('submit', function (e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to create these installments? This action cannot be undone.')) {
                return;
            }

            const form = $(this);
            const submitBtn = form.find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Creating...');

            $.ajax({
                url: form.attr('action'),
                method: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        alert(response.message);
                        window.location.href = 'installment-requests.php';
                    } else {
                        alert('Error: ' + response.message);
                        submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Create Installments');
                    }
                },
                error: function () {
                    alert('An error occurred. Please try again.');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Create Installments');
                }
            });
        });
    });
</script>

</body>

</html>