<?php

/**
 * AJAX Data Preview Handler
 * Returns formatted HTML preview of database table data
 */

require_once __DIR__ . '/../../session_config.php';
require_once dirname(dirname(dirname(__DIR__))) . '/common/constants.php'; require_once DB_CONNECT_FILE;
require_once OPERATION_FILE;
require_once PORTAL_GLOBALVARIABLE;

// Check authentication
if (!hasRole(ROLE_WEBSITE_ADMIN) && !hasRole(ROLE_SUPER_ADMIN)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

// Validate request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['table'])) {
    echo '<div class="alert alert-danger">Invalid request</div>';
    exit;
}

$tableName = $_POST['table'];
$limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 10;

// Whitelist allowed tables for security
$allowedTables = [
    'tbl_site_settings',
    'tbl_navigation_menu',
    'tbl_social_links',
    'tbl_pages',
    'tbl_page_sections',
    'tbl_page_content'
];

if (!in_array($tableName, $allowedTables)) {
    echo '<div class="alert alert-danger">Invalid table name</div>';
    exit;
}

try {
    // Fetch data
    $stmt = $conn->prepare("SELECT * FROM $tableName LIMIT ?");
    $stmt->execute([$limit]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($data)) {
        echo '<div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No data found in this table.
              </div>';
        exit;
    }

    // Get column names
    $columns = array_keys($data[0]);

    ?>
    <div class="table-responsive">
        <table class="table table-hover table-bordered">
            <thead class="table-light">
                <tr>
                    <th class="text-center css-ajax-preview-data-ae1f13">#</th>
                    <?php foreach ($columns as $col): ?>
                        <th><?php echo ucwords(str_replace('_', ' ', $col)); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $index => $row): ?>
                    <tr>
                        <td class="text-center text-muted"><?php echo $index + 1; ?></td>
                        <?php foreach ($columns as $col): ?>
                            <td>
                                <?php
                                $value = $row[$col];

                                // Format based on column type
                                if ($col === 'id' || strpos($col, '_id') !== false) {
                                    echo '<span class="badge bg-secondary">' . htmlspecialchars($value ?? '') . '</span>';
                                } elseif ($col === 'is_active' || $col === 'status') {
                                    if ($value == 1 || $value === 'active') {
                                        echo '<span class="badge bg-success"><i class="fas fa-check"></i> Active</span>';
                                    } else {
                                        echo '<span class="badge bg-danger"><i class="fas fa-times"></i> Inactive</span>';
                                    }
                                } elseif (strpos($col, 'created_at') !== false || strpos($col, 'updated_at') !== false) {
                                    echo '<small class="text-muted">' . date('d M Y, h:i A', strtotime($value)) . '</small>';
                                } elseif (strpos($col, 'url') !== false || strpos($col, 'link') !== false) {
                                    if (!empty($value)) {
                                        echo '<a href="' . htmlspecialchars($value ?? '') . '" target="_blank" class="text-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>' .
                                            (strlen($value) > 40 ? substr($value, 0, 40) . '...' : $value) .
                                            '</a>';
                                    } else {
                                        echo '<span class="text-muted">(empty)</span>';
                                    }
                                } elseif (strpos($col, 'icon') !== false) {
                                    if (!empty($value)) {
                                        echo '<i class="' . htmlspecialchars($value ?? '') . ' me-2"></i>' . htmlspecialchars($value ?? '');
                                    } else {
                                        echo '<span class="text-muted">(no icon)</span>';
                                    }
                                } elseif (strlen($value) > 100) {
                                    echo '<div class="text-truncate css-ajax-preview-data-c0dc2c" title="' . htmlspecialchars($value ?? '') . '">'
                                        . htmlspecialchars(substr($value, 0, 100) ?? '') . '...</div>';
                                } else {
                                    echo htmlspecialchars($value ?: '(empty)' ?? '');
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="alert alert-light border mt-3">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <i class="fas fa-info-circle text-primary me-2"></i>
                <strong>Total Records:</strong> <?php echo count($data); ?>
                <?php if (count($data) >= $limit): ?>
                    <span class="text-muted">(Showing first <?php echo $limit; ?> records)</span>
                <?php endif; ?>
            </div>
            <div>
                <strong>Table:</strong> <code><?php echo $tableName; ?></code>
            </div>
        </div>
    </div>

    <?php

} catch (Exception $e) {
    echo '<div class="alert alert-danger">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Error:</strong> ' . htmlspecialchars($e->getMessage() ?? '') . '
          </div>';
}
