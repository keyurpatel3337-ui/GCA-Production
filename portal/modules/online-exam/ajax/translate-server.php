<?php
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/common/constants.php';
require_once ENV_CONFIG_FILE;
require_once DB_CONNECT_FILE;
require_once PORTAL_PATH . 'session_config.php';
require_once PORTAL_GLOBALVARIABLE;

header('Content-Type: application/json');

// 1. Check access permissions
if (!hasAnyRole([ROLE_SUPER_ADMIN, ROLE_PRINCIPLE, ROLE_COUNSELLOR, ROLE_DEPT_HEAD, ROLE_ASSISTANT_TEACHER])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

// 2. Decode POST body
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['q'])) {
    echo json_encode(['success' => false, 'message' => 'Missing translation queries (q).']);
    exit;
}

$queries = $input['q'];
$isBatch = is_array($queries);
$batchQueries = $isBatch ? $queries : [$queries];

$translatedResults = [];
$api_url = defined('TRANSLATION_API_URL') ? TRANSLATION_API_URL : 'https://translate.argosopentech.com/translate';
$api_key = defined('TRANSLATION_API_KEY') ? TRANSLATION_API_KEY : '';

// 3. Translate queries (Iterate securely to prevent batch failure on public mirrors)
foreach ($batchQueries as $index => $text) {
    $text = trim($text);
    if ($text === '') {
        $translatedResults[] = '';
        continue;
    }

    // Call LibreTranslate Mirror
    $ch = curl_init($api_url);
    $payload = [
        'q' => $text,
        'source' => 'en',
        'target' => 'gu',
        'format' => 'html'
    ];
    if (!empty($api_key)) {
        $payload['api_key'] = $api_key;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 12); // Prevent hanging if mirror is slow

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $resData = json_decode($response, true);
        if (isset($resData['translatedText'])) {
            $translatedResults[] = $resData['translatedText'];
        } else {
            // Fallback: return original text on parse issue
            $translatedResults[] = $text;
        }
    } else {
        // Fallback: return original text on HTTP error
        $translatedResults[] = $text;
    }
}

// 4. Respond with clean JSON
echo json_encode([
    'success' => true,
    'translatedText' => $isBatch ? $translatedResults : $translatedResults[0]
]);
