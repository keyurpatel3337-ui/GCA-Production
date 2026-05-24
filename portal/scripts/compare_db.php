<?php

/**
 * Database Comparison & Sync Script
 * Compares structure of two databases (potentially on different hosts) and offers fix options
 */

// Configuration

// Source (Development / Local)
$source_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'Counselling@2025',
    'name' => 'counselling-dev'
];

// Target (Production / Remote - to be fixed)
$target_config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => 'Counselling@2025',
    'name' => 'counselling'
];

// Establish connections
try {
    // Source Connection
    $pdo_source = new PDO("mysql:host={$source_config['host']};charset=utf8mb4", $source_config['user'], $source_config['pass']);
    $pdo_source->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Target Connection
    $pdo_target = new PDO("mysql:host={$target_config['host']};charset=utf8mb4", $target_config['user'], $target_config['pass']);
    $pdo_target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Disable strict mode on target for easier alterations if needed
    $pdo_target->exec("SET sql_mode = ''");
}
catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$db1_name = $source_config['name'];
$db2_name = $target_config['name'];

// -----------------------------------------------------------------------------
// Helper Functions
// -----------------------------------------------------------------------------

function get_schema($pdo, $db_name)
{
    $tables = [];
    $stmt = $pdo->prepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?");
    $stmt->execute([$db_name]);
    $table_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($table_list as $table) {
        $tables[$table] = ['columns' => []];
        $stmt_cols = $pdo->prepare("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
        $stmt_cols->execute([$db_name, $table]);
        $columns = $stmt_cols->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $col) {
            $tables[$table]['columns'][$col['COLUMN_NAME']] = $col;
        }
    }
    return $tables;
}

function get_create_table_sql($pdo, $db, $table, $target_db = null)
{
    // Check if it's a View or Table
    $stmt = $pdo->prepare("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $stmt->execute([$db, $table]);
    $type = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SHOW CREATE TABLE `$db`.`$table`");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_NUM);
    $sql = $row[1];

    // If target_db is provided and it's a View, we MUST replace the source DB name with target DB name
    if ($target_db && $type === 'VIEW') {
        $sql = str_replace("`$db`.", "`$target_db`.", $sql);
        $sql = str_replace(" $db.", " $target_db.", $sql);

        // Remove DEFINER clause to avoid permission errors
        // Regex now captures "DEFINER=user@host" properly
        $sql = preg_replace('/DEFINER\s*=\s*(?:`[^`]+`|\'[^\']+\'|[^\s@]+)@(?:`[^`]+`|\'[^\']+\'|[^\s]+)\s*/', '', $sql);

    // Optional: Remove SQL SECURITY DEFINER if valid
    // $sql = str_replace("SQL SECURITY DEFINER", "SQL SECURITY INVOKER", $sql);
    }

    return $sql;
}

function generate_column_def($col_data)
{
    $def = "`{$col_data['COLUMN_NAME']}` {$col_data['COLUMN_TYPE']}";

    if ($col_data['IS_NULLABLE'] === 'NO') {
        $def .= " NOT NULL";
    }
    else {
        $def .= " NULL";
    }

    if ($col_data['COLUMN_DEFAULT'] !== null) {
        $default = $col_data['COLUMN_DEFAULT'];
        if ($default === 'CURRENT_TIMESTAMP') {
            $def .= " DEFAULT CURRENT_TIMESTAMP";
        }
        else {
            $def .= " DEFAULT '$default'";
        }
    }
    elseif ($col_data['IS_NULLABLE'] === 'YES' && $col_data['COLUMN_DEFAULT'] === null) {
        $def .= " DEFAULT NULL";
    }

    if ($col_data['EXTRA']) {
        $def .= " " . $col_data['EXTRA'];
    }

    return $def;
}

// -----------------------------------------------------------------------------
// Main Logic
// -----------------------------------------------------------------------------

// Handle Fix Actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    try {
        $pdo_target->exec("USE `$db2_name`");

        if ($_POST['action'] == 'fix') {
            // Single fix
            $sql = $_POST['sql'];
            $pdo_target->exec($sql);
            $message = "<div class='alert success'>Successfully executed on Target: <pre>$sql</pre></div>";
        }
        elseif ($_POST['action'] == 'fix_all') {
            // Fix All
            $sqls = json_decode($_POST['sqls'], true);
            $executed_queries = [];
            foreach ($sqls as $sql) {
                $pdo_target->exec($sql);
                $executed_queries[] = $sql;
            }
            $message = "<div class='alert success'>Successfully executed " . count($executed_queries) . " queries and synchronized databases!<br><button class='btn btn-sm' onclick='location.reload()'>Reload Page</button></div>";
        }
        elseif ($_POST['action'] == 'create_all_tables') {
            // Create all missing tables
            $sqls = json_decode($_POST['sqls'], true);
            $executed_queries = [];
            foreach ($sqls as $sql) {
                $pdo_target->exec($sql);
                $executed_queries[] = $sql;
            }
            $message = "<div class='alert success'>Successfully created " . count($executed_queries) . " tables!<br><button class='btn btn-sm' onclick='location.reload()'>Reload Page</button></div>";
        }
        elseif ($_POST['action'] == 'copy_data') {
            // Copy data from source to target
            $tables = json_decode($_POST['tables'], true);
            $copied_tables = [];
            $total_rows = 0;

            // Global Disable Foreign Key Checks for Bulk Copy
            $pdo_target->exec("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($tables as $table) {
                // Get data from source
                $pdo_source->exec("USE `$db1_name`");
                $stmt = $pdo_source->query("SELECT * FROM `$table`");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    // Clear target table first
                    $pdo_target->exec("TRUNCATE TABLE `$table`");

                    // Insert data into target
                    $pdo_target->beginTransaction();
                    $columns = array_keys($rows[0]);
                    $placeholders = implode(',', array_fill(0, count($columns), '?'));
                    $column_list = '`' . implode('`, `', $columns) . '`';

                    $insert_stmt = $pdo_target->prepare("INSERT INTO `$table` ($column_list) VALUES ($placeholders)");

                    foreach ($rows as $row) {
                        $insert_stmt->execute(array_values($row));
                    }
                    $pdo_target->commit();

                    $copied_tables[] = $table;
                    $total_rows += count($rows);
                }
            }

            // Re-enable Foreign Key Checks
            $pdo_target->exec("SET FOREIGN_KEY_CHECKS = 1");

            $message = "<div class='alert success'>Successfully copied data from " . count($copied_tables) . " tables ($total_rows rows total)!<br><button class='btn btn-sm' onclick='location.reload()'>Reload Page</button></div>";
        }
        elseif ($_POST['action'] == 'sync_columns') {
            // Sync columns (add missing, modify mismatched)
            $sqls = json_decode($_POST['sqls'], true);
            $executed_queries = [];
            foreach ($sqls as $sql) {
                $pdo_target->exec($sql);
                $executed_queries[] = $sql;
            }
            $message = "<div class='alert success'>Successfully synchronized " . count($executed_queries) . " columns!<br><button class='btn btn-sm' onclick='location.reload()'>Reload Page</button></div>";
        }
        elseif ($_POST['action'] == 'truncate_table') {
            $table = $_POST['table'];
            $pdo_target->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo_target->exec("TRUNCATE TABLE `$table`");
            $pdo_target->exec("SET FOREIGN_KEY_CHECKS = 1");
            $message = "<div class='alert success'>Successfully truncated table <strong>$table</strong> in Target.</div>";
        }
        elseif ($_POST['action'] == 'copy_single_table') {
            $table = $_POST['table'];
            $pdo_source->exec("USE `$db1_name`");
            $stmt = $pdo_source->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Disable Foreign Key Checks
            $pdo_target->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo_target->exec("TRUNCATE TABLE `$table`");

            if (!empty($rows)) {
                $pdo_target->beginTransaction();
                $columns = array_keys($rows[0]);
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $column_list = '`' . implode('`, `', $columns) . '`';
                $insert_stmt = $pdo_target->prepare("INSERT INTO `$table` ($column_list) VALUES ($placeholders)");
                foreach ($rows as $row) {
                    $insert_stmt->execute(array_values($row));
                }
                $pdo_target->commit();
            }

            // Re-enable Foreign Key Checks
            $pdo_target->exec("SET FOREIGN_KEY_CHECKS = 1");

            $message = "<div class='alert success'>Successfully copied " . count($rows) . " rows to table <strong>$table</strong> in Target.</div>";
        }
    }
    catch (PDOException $e) {
        if ($pdo_target->inTransaction()) {
            $pdo_target->rollBack();
        }
        $message = "<div class='alert error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Get Schemas from respective connections
$schema1 = get_schema($pdo_source, $db1_name);
$schema2 = get_schema($pdo_target, $db2_name);

// Find missing tables
$tables1 = array_keys($schema1);
$tables2 = array_keys($schema2);

$missing_in_db2 = array_diff($tables1, $tables2); // Missing in Target
$missing_in_db1 = array_diff($tables2, $tables1); // Missing in Source (Extra in Target)
$common_tables = array_intersect($tables1, $tables2);

// Collect all fix queries
$all_fix_queries = [];
$create_table_queries = [];
$column_sync_queries = [];

// Start output buffering to capture page content
ob_start();
?>
<!DOCTYPE html>
<html>

<head>
    <title>DB Sync Tool (Remote)</title>
    <style>
        body {
            font-family: sans-serif;
            padding: 20px;
            line-height: 1.5;
        }

        h2,
        h3,
        h4 {
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .warn {
            color: #856404;
            background-color: #fff3cd;
            padding: 2px 5px;
            border-radius: 3px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
            font-size: 14px;
        }

        th,
        td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8f9fa;
        }

        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #f4f4f4;
            padding: 5px;
            border: 1px solid #ddd;
            margin: 0;
        }

        .btn {
            padding: 5px 10px;
            cursor: pointer;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 3px;
        }

        .btn:hover {
            background: #0056b3;
        }

        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #212529;
        }

        .sticky-header {
            position: sticky;
            top: 0;
            background: white;
            padding: 15px 0;
            border-bottom: 2px solid #eee;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        #fixAllContainer {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
    </style>
    <script>
        function confirmFix() {
            return true; // confirm("...");
        }

        function confirmFixAll() {
            return true; // confirm("...");
        }
    </script>
</head>

<body>

    <div class="sticky-header">
        <div>
            <h1 style="margin:0;">Database Sync Tool (Remote)</h1>
            <small>Source: <strong><?php echo $source_config['host']; ?> (<?php echo $db1_name; ?>)</strong> | Target:
                <strong><?php echo $target_config['host']; ?> (<?php echo $db2_name; ?>)</strong></small>
        </div>
        <div id="fixAllContainer">
            <!-- Buttons will be rendered here by script below -->
        </div>
    </div>

    <?php echo $message; ?>

    <?php if (!empty($missing_in_db2)): ?>
        <h3><span class="badge badge-danger">Missing Tables</span> in Target</h3>
        <table>
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($missing_in_db2 as $t):
        $create_sql = get_create_table_sql($pdo_source, $db1_name, $t, $db2_name);
        $all_fix_queries[] = $create_sql;
        $create_table_queries[] = $create_sql;
?>
                    <tr>
                        <td><strong><?php echo $t; ?></strong></td>
                        <td>
                            <form method="POST" onsubmit="return confirmFix()">
                                <input type="hidden" name="action" value="fix">
                                <input type="hidden" name="target_db" value="<?php echo $db2_name; ?>">
                                <input type="hidden" name="sql" value="<?php echo htmlspecialchars($create_sql); ?>">
                                <button type="submit" class="btn">Create Table</button>
                            </form>
                            <details style="margin-top:5px; cursor:pointer;">
                                <summary style="font-size:12px; color:#666;">View SQL</summary>
                                <pre style="font-size:11px;"><?php echo htmlspecialchars($create_sql); ?></pre>
                            </details>
                        </td>
                    </tr>
                <?php
    endforeach; ?>
            </tbody>
        </table>
    <?php
endif; ?>

    <h3>Column Differences</h3>
    <table>
        <thead>
            <tr>
                <th>Table</th>
                <th>Column</th>
                <th>Issue</th>
                <th>Source</th>
                <th>Target</th>
                <th>Fix Action</th>
            </tr>
        </thead>
        <tbody>

            <?php
$has_diffs = false;

foreach ($common_tables as $table) {
    $cols1 = $schema1[$table]['columns'];
    $cols2 = $schema2[$table]['columns'];

    $col_names1 = array_keys($cols1);
    $col_names2 = array_keys($cols2);

    // Missing columns in Target
    $cols_missing_in_db2 = array_diff($col_names1, $col_names2);
    foreach ($cols_missing_in_db2 as $col) {
        $has_diffs = true;
        $col_def = generate_column_def($cols1[$col]);
        $alter_sql = "ALTER TABLE `$table` ADD COLUMN $col_def;";
        $all_fix_queries[] = $alter_sql;
        $column_sync_queries[] = $alter_sql;

        echo "<tr>
            <td>$table</td>
            <td>$col</td>
            <td><span class='badge badge-danger'>Missing in Target</span></td>
            <td>{$cols1[$col]['COLUMN_TYPE']}</td>
            <td>-</td>
            <td>
                <form method='POST' onsubmit='return confirmFix()'>
                    <input type='hidden' name='action' value='fix'>
                    <input type='hidden' name='target_db' value='$db2_name'>
                    <input type='hidden' name='sql' value='" . htmlspecialchars($alter_sql) . "'>
                    <button type='submit' class='btn'>Add Column</button>
                </form>
                <div style='font-size:10px; color:#666; margin-top:3px;'>$alter_sql</div>
            </td>
        </tr>";
    }

    // Extra columns in Target (Missing in Source)
    $cols_missing_in_db1 = array_diff($col_names2, $col_names1);
    foreach ($cols_missing_in_db1 as $col) {
        $has_diffs = true;
        // DROP COLUMN excluded from fix all
        $alter_sql = "ALTER TABLE `$table` DROP COLUMN `$col`;";

        echo "<tr>
            <td>$table</td>
            <td>$col</td>
            <td><span class='badge badge-warning'>Extra in Target</span></td>
            <td>-</td>
            <td>{$cols2[$col]['COLUMN_TYPE']}</td>
            <td>
                <form method='POST' onsubmit='return confirmFix()'>
                    <input type='hidden' name='action' value='fix'>
                    <input type='hidden' name='target_db' value='$db2_name'>
                    <input type='hidden' name='sql' value='" . htmlspecialchars($alter_sql) . "'>
                    <button type='submit' class='btn' style='background:#dc3545;'>Drop Column</button>
                </form>
                 <div style='font-size:10px; color:#666; margin-top:3px;'>$alter_sql</div>
            </td>
        </tr>";
    }

    // Compare common columns for mismatches
    $common_cols = array_intersect($col_names1, $col_names2);
    foreach ($common_cols as $col) {
        $c1 = $cols1[$col];
        $c2 = $cols2[$col];

        $diffs = [];
        if ($c1['COLUMN_TYPE'] !== $c2['COLUMN_TYPE'])
            $diffs[] = "Type: {$c1['COLUMN_TYPE']} vs {$c2['COLUMN_TYPE']}";
        if ($c1['IS_NULLABLE'] !== $c2['IS_NULLABLE'])
            $diffs[] = "Null: {$c1['IS_NULLABLE']} vs {$c2['IS_NULLABLE']}";

        if (!empty($diffs)) {
            $has_diffs = true;
            $col_def = generate_column_def($c1);
            $alter_sql = "ALTER TABLE `$table` MODIFY COLUMN $col_def;";
            $all_fix_queries[] = $alter_sql;
            $column_sync_queries[] = $alter_sql;

            echo "<tr>
                <td>$table</td>
                <td>$col</td>
                <td><span class='badge badge-warning'>Mismatch</span><br><small>" . implode('<br>', $diffs) . "</small></td>
                <td>{$c1['COLUMN_TYPE']}</td>
                <td>{$c2['COLUMN_TYPE']}</td>
                 <td>
                    <form method='POST' onsubmit='return confirmFix()'>
                        <input type='hidden' name='action' value='fix'>
                        <input type='hidden' name='target_db' value='$db2_name'>
                        <input type='hidden' name='sql' value='" . htmlspecialchars($alter_sql) . "'>
                        <button type='submit' class='btn'>Sync to Source</button>
                    </form>
                    <div style='font-size:10px; color:#666; margin-top:3px;'>Query generated from Source def</div>
                </td>
            </tr>";
        }
    }
}

if (!$has_diffs && empty($missing_in_db2)) {
    echo "<tr><td colspan='6' class='success' style='text-align:center;'><strong>Remote Target database is fully synchronized with Local Source!</strong></td></tr>";
}
?>
        </tbody>
    </table>

    <h3>Table Management</h3>
    <table>
        <thead>
            <tr>
                <th>Source Table (<?php echo $db1_name; ?>)</th>
                <th>Target Table (<?php echo $db2_name; ?>)</th>
                <th>Actions on Target</th>
            </tr>
        </thead>
        <tbody>
            <?php
$all_available_tables = array_unique(array_merge($tables1, $tables2));
sort($all_available_tables);
foreach ($all_available_tables as $table):
?>
                <tr>
                    <td>
                        <?php if (in_array($table, $tables1)): ?>
                            <span class="badge" style="background:#e9ecef; color:#495057;"><?php echo $db1_name; ?></span>
                            <strong><?php echo $table; ?></strong>
                        <?php
    else: ?>
                            <span class="text-muted italic">Not in Source</span>
                        <?php
    endif; ?>
                    </td>
                    <td>
                        <?php if (in_array($table, $tables2)): ?>
                            <span class="badge" style="background:#e9ecef; color:#495057;"><?php echo $db2_name; ?></span>
                            <strong><?php echo $table; ?></strong>
                        <?php
    else: ?>
                            <span class="text-muted italic">Not in Target</span>
                        <?php
    endif; ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:5px;">
                            <?php if (in_array($table, $tables2)): ?>
                                <form method="POST"
                                    onsubmit="return confirm('Truncate table <?php echo $table; ?> on Target?')">
                                    <input type="hidden" name="action" value="truncate_table">
                                    <input type="hidden" name="table" value="<?php echo $table; ?>">
                                    <button type="submit" class="btn"
                                        style="background:#dc3545; padding:3px 8px; font-size:12px;">Truncate</button>
                                </form>
                            <?php
    endif; ?>

                            <?php if (in_array($table, $tables1) && in_array($table, $tables2)): ?>
                                <form method="POST"
                                    onsubmit="return confirm('Copy data from Source to Target for table <?php echo $table; ?>? This will truncate Target first!')">
                                    <input type="hidden" name="action" value="copy_single_table">
                                    <input type="hidden" name="table" value="<?php echo $table; ?>">
                                    <button type="submit" class="btn"
                                        style="background:#28a745; padding:3px 8px; font-size:12px;">Copy (S->T)</button>
                                </form>
                            <?php
    endif; ?>
                        </div>
                    </td>
                </tr>
            <?php
endforeach; ?>
        </tbody>
    </table>

</body>

</html>
<?php
// Get the buffered content
$page_content = ob_get_clean();

// Generate button HTML based on collected data
$buttons_html = '';

if (!empty($create_table_queries)) {
    $count = count($create_table_queries);
    $queries_json = htmlspecialchars(json_encode($create_table_queries), ENT_QUOTES, 'UTF-8');
    $buttons_html .= <<<HTML
        <form method="POST" onsubmit="return confirm('Create {$count} missing tables?')" style="display:inline-block; margin-right:10px;">
            <input type="hidden" name="action" value="create_all_tables">
            <input type="hidden" name="sqls" value='{$queries_json}'>
            <button type="submit" class="btn" style="background: #17a2b8; font-size: 14px; padding: 10px 20px;">
                1. CREATE ALL TABLES ({$count})
            </button>
        </form>
HTML;
}

if (!empty($common_tables)) {
    $count = count($common_tables);
    $tables_json = htmlspecialchars(json_encode(array_values($common_tables)), ENT_QUOTES, 'UTF-8');
    $buttons_html .= <<<HTML
        <form method="POST" onsubmit="return confirm('Copy data from {$count} tables? This will TRUNCATE target tables first!')" style="display:inline-block; margin-right:10px;">
            <input type="hidden" name="action" value="copy_data">
            <input type="hidden" name="tables" value='{$tables_json}'>
            <button type="submit" class="btn" style="background: #fd7e14; font-size: 14px; padding: 10px 20px;">
                2. COPY DATA FROM SOURCE ({$count} tables)
            </button>
        </form>
HTML;
}

if (!empty($column_sync_queries)) {
    $count = count($column_sync_queries);
    $queries_json = htmlspecialchars(json_encode($column_sync_queries), ENT_QUOTES, 'UTF-8');
    $buttons_html .= <<<HTML
        <form method="POST" onsubmit="return confirm('Sync {$count} columns to match source?')" style="display:inline-block; margin-right:10px;">
            <input type="hidden" name="action" value="sync_columns">
            <input type="hidden" name="sqls" value='{$queries_json}'>
            <button type="submit" class="btn" style="background: #6f42c1; font-size: 14px; padding: 10px 20px;">
                3. SYNC COLUMNS ({$count})
            </button>
        </form>
HTML;
}

if (!empty($all_fix_queries)) {
    $count = count($all_fix_queries);
    $queries_json = htmlspecialchars(json_encode($all_fix_queries), ENT_QUOTES, 'UTF-8');
    $buttons_html .= <<<HTML
        <form method="POST" onsubmit="return confirm('Execute ALL {$count} fixes at once?')" style="display:inline-block;">
            <input type="hidden" name="action" value="fix_all">
            <input type="hidden" name="sqls" value='{$queries_json}'>
            <button type="submit" class="btn" style="background: #28a745; font-size: 14px; padding: 10px 20px;">
                FIX ALL ISSUES ({$count})
            </button>
        </form>
HTML;
}

// Replace the placeholder with actual buttons
$page_content = str_replace(
    '<!-- Buttons will be rendered here by script below -->',
    $buttons_html,
    $page_content
);

// Output the final page
echo $page_content;
?>