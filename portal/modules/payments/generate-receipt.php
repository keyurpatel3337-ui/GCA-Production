<?php
require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Check if user is Accountant
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'accountant') {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Generate Receipt" ;
$page_breadcrumb = "Receipt -";

// Get all students
try {
    $students = $dbOps->customSelect(
        "SELECT id, student_name as name, aadhaar 
         FROM tbl_gm_std_registration 
         ORDER BY student_name"
    );
} catch (PDOException $e) {
    logDatabaseError($e, "Fetch Students");
    $students = [];
}

// If payment_id is provided, load payment details
$payment_data = null;
if (isset($_POST['payment_id'])) {
    try {
        $op = new Operation();
        $payment_data = $op->readWithJoin(
            'tbl_payments p',
            ['p.*', 's.student_name', 's.aadhaar'],
            [
                ['type' => 'INNER', 'table' => 'tbl_gm_std_registration s', 'on' => 'p.student_id = s.id']
            ],
            ['p.id' => $_POST['payment_id']]
        );
    } catch (Exception $e) {
        logDatabaseError($e, "Fetch Payment Data");
    }
}
?>
<?php include '../../include/header.php'; ?>
<?php include '../../include/navbar.php'; ?>
<?php include '../../include/sidebar.php'; ?>




    <div class="container-fluid">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert">&times;</button>
                <?php
                echo htmlspecialchars($_SESSION['error'] ?? '', ENT_QUOTES, 'UTF-8');
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Receipt Details</h3>
            </div>
            <form method="POST" action="receipt-save.php">
                <input type="hidden" name="payment_id"
                    value="<?php echo htmlspecialchars($_POST['payment_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="card-body">
                    <div class="row">
                        <!-- Student Selection -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Student <span class="text-danger">*</span></label>
                                <?php if ($payment_data): ?>
                                    <input type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($payment_data['student_name'] ?? ''); ?>"
                                        readonly>
                                    <input type="hidden" name="student_id" id="student_id"
                                        value="<?php echo $payment_data['student_id'] ?? ''; ?>">
                                <?php else: ?>
                                    <div class="student-search-wrapper">
                                        <input type="text" id="student_search" class="form-control student-search-input"
                                            placeholder="Enter Student ID or Mobile Number" autocomplete="off">
                                        <input type="hidden" name="student_id" id="student_id" required>
                                        <div id="student_search_results"></div>
                                    </div>
                                    <small class="form-text text-muted">Type at least 2 characters to search</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Receipt Date -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Receipt Date <span class="text-danger">*</span></label>
                                <input type="date" name="issued_date" class="form-control"
                                    value="<?php echo $payment_data ? $payment_data['payment_date'] : date('Y-m-d'); ?>"
                                    required>
                            </div>
                        </div>

                        <!-- Amount -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount <span class="text-danger">*</span></label>
                                <input type="number" name="amount" step="0.01" class="form-control"
                                    value="<?php echo $payment_data ? $payment_data['amount'] : ''; ?>"
                                    placeholder="Enter amount" required>
                            </div>
                        </div>

                        <!-- Payment For -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment For <span class="text-danger">*</span></label>
                                <input type="text" name="payment_for" class="form-control"
                                    value="<?php echo $payment_data ? $payment_data['payment_type'] : ''; ?>"
                                    placeholder="e.g., Tuition Fee, Exam Fee" required>
                            </div>
                        </div>

                        <!-- Payment Mode -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Payment Mode <span class="text-danger">*</span></label>
                                <select name="payment_mode" id="payment_mode" class="form-control" required>
                                    <option value="">-- Select Mode --</option>
                                    <option value="cash" <?php echo ($payment_data && $payment_data['payment_mode'] == 'cash') ? 'selected' : ''; ?>>Cash</option>
                                    <option value="cheque" <?php echo ($payment_data && $payment_data['payment_mode'] == 'cheque') ? 'selected' : ''; ?>>Cheque</option>
                                    <option value="online" <?php echo ($payment_data && $payment_data['payment_mode'] == 'online') ? 'selected' : ''; ?>>Online</option>
                                    <option value="card" <?php echo ($payment_data && $payment_data['payment_mode'] == 'card') ? 'selected' : ''; ?>>Card</option>
                                </select>
                            </div>
                        </div>

                        <!-- Transaction ID (for online/card) -->
                        <div class="col-md-6 css-generate-receipt-224b51" id="transaction_id_div">
                            <div class="form-group">
                                <label>Transaction ID</label>
                                <input type="text" name="transaction_id" class="form-control"
                                    value="<?php echo $payment_data ? $payment_data['transaction_id'] : ''; ?>"
                                    placeholder="Enter transaction ID">
                            </div>
                        </div>

                        <!-- Cheque Details -->
                        <div id="cheque_details" class="css-generate-receipt-015a2b">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Cheque Number</label>
                                    <input type="text" name="cheque_no" class="form-control"
                                        value="<?php echo $payment_data ? $payment_data['cheque_no'] : ''; ?>"
                                        placeholder="Enter cheque number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Bank Name</label>
                                    <input type="text" name="bank_name" class="form-control"
                                        value="<?php echo $payment_data ? $payment_data['bank_name'] : ''; ?>"
                                        placeholder="Enter bank name">
                                </div>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="col-md-12">
                            <div class="form-group">
                                <label>Remarks</label>
                                <textarea name="remarks" class="form-control" rows="3"
                                    placeholder="Additional notes..."><?php echo $payment_data ? $payment_data['remarks'] : ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-receipt"></i> Generate Receipt
                    </button>
                    <a href="receipts.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
        </div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        <?php if (!$payment_data): ?>
            // Initialize Student Search Component only if no payment data
            const studentSearch = new StudentSearchComponent({
                inputId: 'student_search',
                hiddenInputId: 'student_id',
                resultsContainerId: 'student_search_results',
                detailsContainerId: 'student_details',
                onSelect: function (student) {
                    $('#student_search').addClass('has-selection');
                    console.log('Student selected:', student);
                }
            });

            // Clear selection styling when input is cleared
            $('#student_search').on('input', function () {
                if ($(this).val().trim() === '') {
                    $(this).removeClass('has-selection');
                }
            });
        <?php endif; ?>

        // Show/hide payment mode specific fields
        $('#payment_mode').on('change', function () {
            var mode = $(this).val();

            if (mode === 'cheque') {
                $('#cheque_details').show();
                $('#transaction_id_div').hide();
            } else if (mode === 'online' || mode === 'card') {
                $('#transaction_id_div').show();
                $('#cheque_details').hide();
            } else {
                $('#cheque_details').hide();
                $('#transaction_id_div').hide();
            }
        });

        // Trigger on page load if payment data exists
        <?php if ($payment_data): ?>
            $('#payment_mode').trigger('change');
        <?php endif; ?>
    });
</script>
