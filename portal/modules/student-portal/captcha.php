<?php

/**
 * Captcha Generator
 * Clean implementation without session_config dependencies
 */

// Stop ALL output and errors
error_reporting(0);
ini_set('display_errors', '0');

// Clear ALL existing output buffers
while (@ob_end_clean())
    ;

// Start session FIRST (before any output)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_samesite', 'Lax');

    // CRITICAL: Use same session name as session_config.php
    session_name('GCA_SESSION');

    @session_start();
}

// Generate 6-digit captcha code
$captchaCode = sprintf('%06d', mt_rand(0, 999999));

// Store in session
$_SESSION['captcha'] = $captchaCode;
$_SESSION['captcha_time'] = time();

// Check GD availability
if (!function_exists('imagecreatetruecolor')) {
    header('Content-Type: text/plain');
    echo 'GD not available';
    exit;
}

// Create image
$width = 140;
$height = 50;
$image = @imagecreatetruecolor($width, $height);

if (!$image) {
    header('Content-Type: text/plain');
    echo 'Image creation failed';
    exit;
}

// Colors
$bgColor = @imagecolorallocate($image, 240, 240, 240);
$textColor = @imagecolorallocate($image, 50, 50, 150);
$lineColor = @imagecolorallocate($image, 200, 200, 200);
$dotColor = @imagecolorallocate($image, 150, 150, 150);

// Background
@imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// Noise lines
for ($i = 0; $i < 5; $i++) {
    @imageline($image, mt_rand(0, $width), mt_rand(0, $height), mt_rand(0, $width), mt_rand(0, $height), $lineColor);
}

// Noise dots
for ($i = 0; $i < 100; $i++) {
    @imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $dotColor);
}

// Draw captcha text
$x = 20;
for ($i = 0; $i < 6; $i++) {
    $y = 15 + mt_rand(-3, 3);
    @imagestring($image, 5, $x, $y, $captchaCode[$i], $textColor);
    $x += 20;
}

// Capture PNG data
@ob_start();
@imagepng($image);
$imageData = @ob_get_clean();
@imagedestroy($image);

// Final cleanup
while (@ob_end_clean())
    ;

// Send image
header('Content-Type: image/png');
header('Content-Length: ' . strlen($imageData));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

echo $imageData;
exit;
