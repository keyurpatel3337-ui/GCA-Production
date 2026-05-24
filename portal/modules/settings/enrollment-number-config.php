<?php
/**
 * Enrollment Number Configuration Settings
 * Allows Super Admin and Principal to manage enrollment number formats
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;
require_once HELPER_ERROR_LOGGER;

// Role check - Only Principal and Super Admin can access
if (!hasRole(ROLE_PRINCIPLE) && !hasRole(ROLE_SUPER_ADMIN)) {
    set_flash_message('error', "Access denied. Only Principal and Super Admin can manage enrollment number settings.");
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$page_title = "Enrollment Number Settings";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create' || $action === 'update') {
            $config_id = intval($_POST['config_id'] ?? 0);
            $config_name = trim($_POST['config_name'] ?? '');
            $prefix = trim($_POST['prefix'] ?? '');
            $separator = $_POST['separator'] ?? '-';
            $include_year = isset($_POST['include_year']) ? 1 : 0;
            $year_format = $_POST['year_format'] ?? 'YYYY';
            $year_position = $_POST['year_position'] ?? 'after_prefix';
            $include_school_code = isset($_POST['include_school_code']) ? 1 : 0;
            $include_course_code = isset($_POST['include_course_code']) ? 1 : 0;
            $include_board_code = isset($_POST['include_board_code']) ? 1 : 0;
            $sequence_length = intval($_POST['sequence_length'] ?? 4);
            $sequence_reset = $_POST['sequence_reset'] ?? 'yearly';
            $description = trim($_POST['description'] ?? '');
            $is_default = isset($_POST['is_default']) ? 1 : 0;

            // Validation
            if (empty($config_name)) {
                throw new Exception("Configuration name is required.");
            }

            if ($sequence_length < 1 || $sequence_length > 10) {
                throw new Exception("Sequence length must be between 1 and 10.");
            }

            // Generate sample format for preview
            $sample = generateSampleEnrollmentNumber([
                'prefix' => $prefix,
                'separator' => $separator,
                'include_year' => $include_year,
                'year_format' => $year_format,
                'year_position' => $year_position,
                'include_school_code' => $include_school_code,
                'include_course_code' => $include_course_code,
                'sequence_length' => $sequence_length
            ]);

            if ($action === 'create') {
                // If setting as default, clear other defaults
                if ($is_default) {
                    $conn->exec("UPDATE tbl_enrollment_number_config SET is_default = 0");
                }

                $stmt = $conn->prepare("
                    INSERT INTO tbl_enrollment_number_config 
                    (config_name, prefix, separator, include_year, year_format, year_position,
                     include_school_code, include_course_code, include_board_code,
                     sequence_length, sequence_reset, sample_format, description, 
                     is_active, is_default, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
                ");
                $stmt->execute([
                    $config_name,
                    $prefix,
                    $separator,
                    $include_year,
                    $year_format,
                    $year_position,
                    $include_school_code,
                    $include_course_code,
                    $include_board_code,
                    $sequence_length,
                    $sequence_reset,
                    $sample,
                    $description,
                    $is_default,
                    $_SESSION['user_id']
                ]);

                set_flash_message('success', "Enrollment number configuration created successfully.");
            } else {
                // Update existing
                if ($is_default) {
                    $conn->exec("UPDATE tbl_enrollment_number_config SET is_default = 0");
                }

                $stmt = $conn->prepare("
                    UPDATE tbl_enrollment_number_config 
                    SET config_name = ?, prefix = ?, separator = ?, include_year = ?, 
                        year_format = ?, year_position = ?, include_school_code = ?, 
                        include_course_code = ?, include_board_code = ?,
                        sequence_length = ?, sequence_reset = ?, sample_format = ?, 
                        description = ?, is_default = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $config_name,
                    $prefix,
                    $separator,
                    $include_year,
                    $year_format,
                    $year_position,
                    $include_school_code,
                    $include_course_code,
                    $include_board_code,
                    $sequence_length,
                    $sequence_reset,
                    $sample,
                    $description,
                    $is_default,
                    $config_id
                ]);

                set_flash_message('success', "Enrollment number configuration updated successfully.");
            }
        } elseif ($action === 'delete') {
            $config_id = intval($_POST['config_id'] ?? 0);
            if ($config_id > 0) {
                $stmt = $conn->prepare("DELETE FROM tbl_enrollment_number_config WHERE id = ? AND is_default = 0");
                $stmt->execute([$config_id]);

                if ($stmt->rowCount() > 0) {
                    set_flash_message('success', "Configuration deleted successfully.");
                } else {
                    set_flash_message('error', "Cannot delete the default configuration.");
                }
            }
        } elseif ($action === 'set_default') {
            $config_id = intval($_POST['config_id'] ?? 0);
            if ($config_id > 0) {
                $conn->exec("UPDATE tbl_enrollment_number_config SET is_default = 0");
                $stmt = $conn->prepare("UPDATE tbl_enrollment_number_config SET is_default = 1 WHERE id = ?");
                $stmt->execute([$config_id]);
                set_flash_message('success', "Default configuration updated.");
            }
        }
    } catch (Exception $e) {
        logError("Enrollment Config Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
        set_flash_message('error', $e->getMessage());
    }

    header('Location: enrollment-number-config.php');
    exit;
}

// Helper function to generate sample enrollment number
function generateSampleEnrollmentNumber($config)
{
    $parts = [];

    if (!empty($config['prefix'])) {
        $parts[] = $config['prefix'];
    }

    if ($config['include_year'] && $config['year_position'] === 'after_prefix') {
        switch ($config['year_format']) {
            case 'YY':
                $parts[] = date('y');
                break;
            case 'YYMM':
                $parts[] = date('ym');
                break;
            default:
                $parts[] = date('Y');
                break;
        }
    }

    if ($config['include_school_code']) {
        $parts[] = 'SCH';
    }

    if ($config['include_course_code']) {
        $parts[] = 'CRS';
    }

    if ($config['include_year'] && $config['year_position'] === 'before_sequence') {
        switch ($config['year_format']) {
            case 'YY':
                $parts[] = date('y');
                break;
            case 'YYMM':
                $parts[] = date('ym');
                break;
            default:
                $parts[] = date('Y');
                break;
        }
    }

    // Add sequence
    $parts[] = str_pad('1', $config['sequence_length'], '0', STR_PAD_LEFT);

    return implode($config['separator'] ?? '-', $parts);
}

// Fetch existing configurations
try {
    $stmt = $conn->query("SELECT * FROM tbl_enrollment_number_config ORDER BY is_default DESC, config_name ASC");
    $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logError("Fetch Enrollment Configs Error: " . $e->getMessage(), __FILE__, __LINE__, $e);
    $configurations = [];
}

include '../../include/header.php';
include '../../include/navbar.php';
include '../../include/sidebar.php';
?>



<div class="container-fluid">
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo gca_safe_html($_SESSION['error_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle"></i>
            <?php echo gca_safe_html($_SESSION['success_msg']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Configuration Form -->
        <div class="col-md-5">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title" id="formTitle"><i class="fas fa-plus"></i> Create Configuration</h3>
                </div>
                <form id="configForm" method="POST">
                    <input type="hidden" name="action" id="form_action" value="create">
                    <input type="hidden" name="config_id" id="config_id" value="">

                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Configuration Name <span class="text-danger">*</span></label>
                            <input type="text" name="config_name" id="config_name" class="form-control" required
                                placeholder="e.g., Default GCI Format">
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Prefix</label>
                                    <input type="text" name="prefix" id="prefix" class="form-control"
                                        placeholder="e.g., GCI, GMS" maxlength="20">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Separator</label>
                                    <select name="separator" id="separator" class="form-control">
                                        <option value="-">Hyphen (-)</option>
                                        <option value="/">Slash (/)</option>
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="include_year" id="include_year" class="form-check-input"
                                    checked>
                                <label class="form-check-label" for="include_year">Include Year</label>
                            </div>
                        </div>

                        <div class="row" id="yearOptions">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Year Format</label>
                                    <select name="year_format" id="year_format" class="form-control">
                                        <option value="YYYY">Full Year (2026)</option>
                                        <option value="YY">Short Year (26)</option>
                                        <option value="YYMM">Year-Month (2601)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Year Position</label>
                                    <select name="year_position" id="year_position" class="form-control">
                                        <option value="after_prefix">After Prefix</option>
                                        <option value="before_sequence">Before Sequence</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="include_school_code" id="include_school_code"
                                            class="form-check-input">
                                        <label class="form-check-label" for="include_school_code">School
                                            Code</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="include_course_code" id="include_course_code"
                                            class="form-check-input">
                                        <label class="form-check-label" for="include_course_code">Standard
                                            Code</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="include_board_code" id="include_board_code"
                                            class="form-check-input">
                                        <label class="form-check-label" for="include_board_code">Board Code</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sequence Length</label>
                                    <input type="number" name="sequence_length" id="sequence_length"
                                        class="form-control" min="1" max="10" value="4">
                                    <small class="text-muted">Number of digits (e.g., 4 = 0001)</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sequence Reset</label>
                                    <select name="sequence_reset" id="sequence_reset" class="form-control">
                                        <option value="yearly">Reset Every Year</option>
                                        <option value="never">Never Reset</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="2"
                                placeholder="Optional description"></textarea>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_default" id="is_default" class="form-check-input">
                                <label class="form-check-label" for="is_default">Set as Default
                                    Configuration</label>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="card bg-light">
                            <div class="card-body text-center">
                                <h6 class="text-muted mb-1">Sample Preview</h6>
                                <h3 class="text-primary mb-0" id="samplePreview">GCI-2026-0001</h3>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save"></i> Save Configuration
                        </button>
                        <button type="button" class="btn btn-secondary" id="resetBtn" onclick="resetForm()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Existing Configurations -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-list"></i> Existing Configurations</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($configurations)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No configurations found. Create one to get started.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Sample Format</th>
                                        <th>Default</th>
                                        <th width="150">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($configurations as $config): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($config['config_name'] ?? ''); ?></strong>
                                                <?php if ($config['description']): ?>
                                                    <br><small
                                                        class="text-muted"><?php echo htmlspecialchars($config['description'] ?? ''); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code><?php echo htmlspecialchars($config['sample_format'] ?? ''); ?></code>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($config['is_default']): ?>
                                                    <span class="badge bg-success"><i class="fas fa-check"></i> Default</span>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="set_default">
                                                        <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-primary"
                                                            title="Set as default">
                                                            <i class="fas fa-star"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info edit-btn"
                                                    data-config='<?php echo htmlspecialchars(json_encode($config) ?? ''); ?>'
                                                    title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if (!$config['is_default']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="config_id" value="<?php echo $config['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                            onclick="return confirm('Are you sure you want to delete this configuration?');"
                                                            title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Card -->
            <div class="card card-info mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-info-circle"></i> About Enrollment Numbers</h3>
                </div>
                <div class="card-body">
                    <p>Enrollment numbers are unique identifiers assigned to students when they complete the
                        enrollment process.</p>
                    <h6>Format Components:</h6>
                    <ul class="mb-0">
                        <li><strong>Prefix:</strong> Custom text at the beginning (e.g., GCI, GMS)</li>
                        <li><strong>Year:</strong> Current year in various formats</li>
                        <li><strong>School Code:</strong> Code from the student's assigned school</li>
                        <li><strong>Standard Code:</strong> Code from the student's standard</li>
                        <li><strong>Sequence:</strong> Auto-incrementing number with zero padding</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../include/footer.php'; ?>

<script>
    $(document).ready(function () {
        // Toggle year options visibility
        $('#include_year').on('change', function () {
            if (this.checked) {
                $('#yearOptions').slideDown();
            } else {
                $('#yearOptions').slideUp();
            }
            updatePreview();
        });

        // Update preview on any change
        $('#prefix, #separator, #include_year, #year_format, #year_position, #include_school_code, #include_course_code, #include_board_code, #sequence_length').on('change keyup', function () {
            updatePreview();
        });

        // Edit button handler
        $('.edit-btn').on('click', function () {
            const config = $(this).data('config');

            $('#form_action').val('update');
            $('#config_id').val(config.id);
            $('#config_name').val(config.config_name);
            $('#prefix').val(config.prefix);
            $('#separator').val(config.separator);
            $('#include_year').prop('checked', config.include_year == 1);
            $('#year_format').val(config.year_format);
            $('#year_position').val(config.year_position);
            $('#include_school_code').prop('checked', config.include_school_code == 1);
            $('#include_course_code').prop('checked', config.include_course_code == 1);
            $('#include_board_code').prop('checked', config.include_board_code == 1);
            $('#sequence_length').val(config.sequence_length);
            $('#sequence_reset').val(config.sequence_reset);
            $('#description').val(config.description);
            $('#is_default').prop('checked', config.is_default == 1);

            if (config.include_year == 1) {
                $('#yearOptions').show();
            } else {
                $('#yearOptions').hide();
            }

            updatePreview();
            $('#formTitle').html('<i class="fas fa-edit"></i> Edit Configuration');
            $('#submitBtn').html('<i class="fas fa-save"></i> Update Configuration');

            // Scroll to form
            $('html, body').animate({ scrollTop: 0 }, 300);
        });

        // Initial preview
        updatePreview();
    });

    function updatePreview() {
        let parts = [];
        const prefix = $('#prefix').val();
        const separator = $('#separator').val();
        const includeYear = $('#include_year').is(':checked');
        const yearFormat = $('#year_format').val();
        const yearPosition = $('#year_position').val();
        const includeSchool = $('#include_school_code').is(':checked');
        const includeCourse = $('#include_course_code').is(':checked');
        const seqLength = parseInt($('#sequence_length').val()) || 4;

        if (prefix) parts.push(prefix);

        if (includeYear && yearPosition === 'after_prefix') {
            const now = new Date();
            let yearStr = '';
            switch (yearFormat) {
                case 'YY': yearStr = String(now.getFullYear()).slice(-2); break;
                case 'YYMM': yearStr = String(now.getFullYear()).slice(-2) + String(now.getMonth() + 1).padStart(2, '0'); break;
                default: yearStr = String(now.getFullYear()); break;
            }
            parts.push(yearStr);
        }

        if (includeSchool) parts.push('SCH');
        if (includeCourse) parts.push('CRS');

        if (includeYear && yearPosition === 'before_sequence') {
            const now = new Date();
            let yearStr = '';
            switch (yearFormat) {
                case 'YY': yearStr = String(now.getFullYear()).slice(-2); break;
                case 'YYMM': yearStr = String(now.getFullYear()).slice(-2) + String(now.getMonth() + 1).padStart(2, '0'); break;
                default: yearStr = String(now.getFullYear()); break;
            }
            parts.push(yearStr);
        }

        parts.push('1'.padStart(seqLength, '0'));

        $('#samplePreview').text(parts.join(separator));
    }

    function resetForm() {
        $('#form_action').val('create');
        $('#config_id').val('');
        $('#configForm')[0].reset();
        $('#include_year').prop('checked', true);
        $('#yearOptions').show();
        $('#formTitle').html('<i class="fas fa-plus"></i> Create Configuration');
        $('#submitBtn').html('<i class="fas fa-save"></i> Save Configuration');
        updatePreview();
    }
</script>