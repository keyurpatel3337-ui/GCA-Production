<?php
/**
 * Rate Limiter Class
 * 
 * Protects against DoS/DDoS attacks by limiting request frequency.
 * Uses file-based storage for simplicity (can be upgraded to Redis for production).
 * 
 * Usage:
 *   require_once 'common/RateLimiter.php';
 *   $limiter = new RateLimiter();
 *   if (!$limiter->isAllowed()) {
 *       http_response_code(429);
 *       die('Too many requests. Please try again later.');
 *   }
 */

class RateLimiter
{
    private $storageDir;
    private $maxRequests;      // Max requests allowed
    private $timeWindow;       // Time window in seconds
    private $clientId;

    /**
     * Initialize Rate Limiter
     * 
     * @param int $maxRequests Maximum requests allowed in time window (default: 60)
     * @param int $timeWindow Time window in seconds (default: 60 seconds = 1 minute)
     * @param string|null $storageDir Directory to store rate limit data
     */
    public function __construct(int $maxRequests = 60, int $timeWindow = 60, ?string $storageDir = null)
    {
        $this->maxRequests = $maxRequests;
        $this->timeWindow = $timeWindow;

        if ($storageDir !== null) {
            $this->storageDir = $storageDir;
        } elseif (defined('LOGS_PATH')) {
            $this->storageDir = rtrim(LOGS_PATH, '/\\') . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR;
        } else {
            // Fallback: common/logs/rate_limits relative to this file's location (portal/common/)
            $this->storageDir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'rate_limits' . DIRECTORY_SEPARATOR;
        }

        $this->clientId = $this->getClientId();

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Get unique client identifier (IP + User Agent hash)
     */
    private function getClientId(): string
    {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        return md5($ip . $userAgent);
    }

    /**
     * Get client IP address (handles proxies)
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // Nginx
            'HTTP_CLIENT_IP',            // General
            'REMOTE_ADDR'                // Direct connection
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }

    /**
     * Check if request is allowed
     * 
     * @return bool True if request is allowed, false if rate limited
     */
    public function isAllowed(): bool
    {
        $data = $this->getRequestData();
        $currentTime = time();

        // Clean old entries (outside time window)
        $data['requests'] = array_filter($data['requests'], function ($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->timeWindow;
        });

        // Check if limit exceeded
        if (count($data['requests']) >= $this->maxRequests) {
            $this->logRateLimitExceeded();
            return false;
        }

        // Add current request
        $data['requests'][] = $currentTime;
        $this->saveRequestData($data);

        return true;
    }

    /**
     * Get remaining requests
     */
    public function getRemainingRequests(): int
    {
        $data = $this->getRequestData();
        $currentTime = time();

        // Count valid requests within time window
        $validRequests = array_filter($data['requests'], function ($timestamp) use ($currentTime) {
            return ($currentTime - $timestamp) < $this->timeWindow;
        });

        return max(0, $this->maxRequests - count($validRequests));
    }

    /**
     * Get seconds until rate limit resets
     */
    public function getResetTime(): int
    {
        $data = $this->getRequestData();
        if (empty($data['requests'])) {
            return 0;
        }

        $oldestRequest = min($data['requests']);
        $resetTime = $oldestRequest + $this->timeWindow - time();
        return max(0, $resetTime);
    }

    /**
     * Get request data from storage
     */
    private function getRequestData(): array
    {
        $file = $this->storageDir . $this->clientId . '.json';

        if (file_exists($file)) {
            $content = file_get_contents($file);
            $data = json_decode($content, true);
            if (is_array($data) && isset($data['requests'])) {
                return $data;
            }
        }

        return ['requests' => [], 'ip' => $this->getClientIP()];
    }

    /**
     * Save request data to storage
     */
    private function saveRequestData(array $data): void
    {
        $file = $this->storageDir . $this->clientId . '.json';
        $data['ip'] = $this->getClientIP();
        $data['last_request'] = date('Y-m-d H:i:s');
        file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Log rate limit exceeded event
     */
    private function logRateLimitExceeded(): void
    {
        // Write to common/logs/ (parent of rate_limits/)
        $logsDir = dirname(rtrim($this->storageDir, '/\\'));
        $logFile = $logsDir . DIRECTORY_SEPARATOR . 'rate_limit_exceeded-' . date('Y-m-d') . '.log';
        $logEntry = sprintf(
            "[%s] Rate limit exceeded - IP: %s, Client ID: %s, Requests: %d/%d\n",
            date('Y-m-d H:i:s'),
            $this->getClientIP(),
            $this->clientId,
            $this->maxRequests,
            $this->maxRequests
        );
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Clean up old rate limit files (run periodically)
     */
    public function cleanup(): void
    {
        $files = glob($this->storageDir . '*.json');
        $expireTime = time() - ($this->timeWindow * 2);

        foreach ($files as $file) {
            if (filemtime($file) < $expireTime) {
                unlink($file);
            }
        }
    }

    /**
     * Send rate limit headers
     */
    public function sendHeaders(): void
    {
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . $this->getRemainingRequests());
        header('X-RateLimit-Reset: ' . (time() + $this->getResetTime()));
    }

    /**
     * Get rate limit exceeded response
     */
    public static function getRateLimitResponse(): array
    {
        return [
            'success' => false,
            'error' => 'rate_limit_exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => 60
        ];
    }
}

/**
 * Helper function to apply rate limiting
 * 
 * @param int $maxRequests Maximum requests per minute
 * @param bool $sendJson Whether to send JSON response
 */
function applyRateLimit(int $maxRequests = 60, bool $sendJson = false): void
{
    $limiter = new RateLimiter($maxRequests);
    $limiter->sendHeaders();

    if (!$limiter->isAllowed()) {
        http_response_code(429);
        if ($sendJson) {
            header('Content-Type: application/json');
            echo json_encode(RateLimiter::getRateLimitResponse());
        } else {
            echo '<h1>429 Too Many Requests</h1>';
            echo '<p>You have exceeded the request limit. Please try again in ' . $limiter->getResetTime() . ' seconds.</p>';
        }
        exit;
    }
}
