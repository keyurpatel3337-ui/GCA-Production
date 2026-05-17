<?php

/**
 * Easebuzz Library Loader
 * Loads Easebuzz library from consolidated common/lib/easebuzz directory
 *
 * @updated January 2026 - Redirected to common/lib/easebuzz/
 */

// Include official EaseBuzz library from consolidated location
$easebuzz_lib_paths = [
    dirname(__DIR__, 2) . '/common/lib/easebuzz/easebuzz_payment_gateway.php',  // From portal/common
    dirname(__DIR__) . '/easebuzz-lib/easebuzz_payment_gateway.php',  // Fallback to old location
];

$loaded = false;
foreach ($easebuzz_lib_paths as $easebuzz_lib_path) {
    if (file_exists($easebuzz_lib_path)) {
        require_once $easebuzz_lib_path;
        $loaded = true;
        break;
    }
}

if (!$loaded) {
    throw new Exception('Easebuzz library not found. Checked: ' . implode(', ', $easebuzz_lib_paths));
}
