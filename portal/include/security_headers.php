<?php
/**
 * Security Headers Configuration
 * 
 * Sets essential security headers to protect against common web vulnerabilities.
 * Include this file at the top of your main header file.
 */

// Obscure technology identifiers
if (!headers_sent()) {
    header_remove("X-Powered-By");
    header("Server: WebServer"); // Generic server header
}

// Prevent clickjacking
header("X-Frame-Options: SAMEORIGIN");

// Prevent MIME type sniffing
header("X-Content-Type-Options: nosniff");

// Enable XSS protection in older browsers
header("X-XSS-Protection: 1; mode=block");

// Referrer policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy (Basic starting point, adjust as needed)
// This policy allows scripts/styles from 'self'
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com https://code.jquery.com https://static.cloudflareinsights.com; ";
$csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
$csp .= "font-src 'self' https://fonts.gstatic.com; ";
$csp .= "img-src 'self' data: https:; ";
$csp .= "connect-src 'self' https:; ";
$csp .= "frame-src 'self';";

header("Content-Security-Policy: " . $csp);
