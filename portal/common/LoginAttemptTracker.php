<?php
/**
 * Login Attempt Tracker
 * 
 * Protects against brute-force attacks by:
 * - Tracking failed login attempts
 * - Temporarily locking accounts after too many failures
 * - Logging suspicious activity
 * 
 * Usage:
 *   require_once 'common/LoginAttemptTracker.php';
 *   $tracker = new LoginAttemptTracker($conn);
 *   
 *   // Before login attempt:
 *   if ($tracker->isLocked($email)) {
 *       $lockInfo = $tracker->getLockInfo($email);
 *       die("Account locked. Try again in {$lockInfo['remaining_minutes']} minutes.");
 *   }
 *   
 *   // After failed login:
 *   $tracker->recordFailedAttempt($email);
 *   
 *   // After successful login:
 *   $tracker->clearAttempts($email);
 */

class LoginAttemptTracker
{
    private $conn;
    private $maxAttempts;           // Max failed attempts before lock
    private $lockDuration;          // Lock duration in seconds
    private $attemptWindow;         // Time window to count attempts (seconds)
    private $tableName = 'tbl_login_attempts';

    /**
     * Initialize Login Attempt Tracker
     * 
     * @param PDO $conn Database connection
     * @param int $maxAttempts Max failed attempts before lock (default: 5)
     * @param int $lockDuration Lock duration in minutes (default: 15)
     * @param int $attemptWindow Time window in minutes to count attempts (default: 30)
     */
    public function __construct(PDO $conn, int $maxAttempts = 5, int $lockDuration = 15, int $attemptWindow = 30)
    {
        $this->conn = $conn;
        $this->maxAttempts = $maxAttempts;
        $this->lockDuration = $lockDuration * 60; // Convert to seconds
        $this->attemptWindow = $attemptWindow * 60; // Convert to seconds

        // Ensure table exists
        $this->createTableIfNotExists();
    }

    /**
     * Create login attempts table if it doesn't exist
     */
    private function createTableIfNotExists(): void
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent TEXT,
                attempt_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                is_successful TINYINT(1) DEFAULT 0,
                is_locked TINYINT(1) DEFAULT 0,
                locked_until DATETIME NULL,
                INDEX idx_email (email),
                INDEX idx_ip (ip_address),
                INDEX idx_attempt_time (attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $this->conn->exec($sql);
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: Failed to create table - " . $e->getMessage());
        }
    }

    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
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
     * Check if email/IP is currently locked
     * 
     * @param string $email Email address
     * @return bool True if locked
     */
    public function isLocked(string $email): bool
    {
        $email = strtolower(trim($email));
        $ip = $this->getClientIP();

        try {
            // Check for active lock
            $stmt = $this->conn->prepare("
                SELECT locked_until 
                FROM {$this->tableName} 
                WHERE (email = :email OR ip_address = :ip)
                  AND is_locked = 1 
                  AND locked_until > NOW()
                ORDER BY locked_until DESC
                LIMIT 1
            ");
            $stmt->execute(['email' => $email, 'ip' => $ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return !empty($result);
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: isLocked check failed - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get lock information
     * 
     * @param string $email Email address
     * @return array Lock info with remaining time
     */
    public function getLockInfo(string $email): array
    {
        $email = strtolower(trim($email));
        $ip = $this->getClientIP();

        try {
            $stmt = $this->conn->prepare("
                SELECT locked_until, 
                       TIMESTAMPDIFF(SECOND, NOW(), locked_until) as remaining_seconds
                FROM {$this->tableName} 
                WHERE (email = :email OR ip_address = :ip)
                  AND is_locked = 1 
                  AND locked_until > NOW()
                ORDER BY locked_until DESC
                LIMIT 1
            ");
            $stmt->execute(['email' => $email, 'ip' => $ip]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return [
                    'is_locked' => true,
                    'locked_until' => $result['locked_until'],
                    'remaining_seconds' => max(0, $result['remaining_seconds']),
                    'remaining_minutes' => ceil(max(0, $result['remaining_seconds']) / 60)
                ];
            }
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: getLockInfo failed - " . $e->getMessage());
        }

        return ['is_locked' => false, 'remaining_seconds' => 0, 'remaining_minutes' => 0];
    }

    /**
     * Get failed attempt count within time window
     * 
     * @param string $email Email address
     * @return int Number of failed attempts
     */
    public function getFailedAttemptCount(string $email): int
    {
        $email = strtolower(trim($email));
        $ip = $this->getClientIP();

        try {
            $stmt = $this->conn->prepare("
                SELECT COUNT(*) as count 
                FROM {$this->tableName} 
                WHERE (email = :email OR ip_address = :ip)
                  AND is_successful = 0
                  AND attempt_time > DATE_SUB(NOW(), INTERVAL :window SECOND)
            ");
            $stmt->execute([
                'email' => $email,
                'ip' => $ip,
                'window' => $this->attemptWindow
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: getFailedAttemptCount failed - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get remaining attempts before lock
     * 
     * @param string $email Email address
     * @return int Remaining attempts
     */
    public function getRemainingAttempts(string $email): int
    {
        return max(0, $this->maxAttempts - $this->getFailedAttemptCount($email));
    }

    /**
     * Record a failed login attempt
     * 
     * @param string $email Email address
     * @return array Status info
     */
    public function recordFailedAttempt(string $email): array
    {
        $email = strtolower(trim($email));
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        try {
            // Insert failed attempt
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->tableName} (email, ip_address, user_agent, is_successful)
                VALUES (:email, :ip, :user_agent, 0)
            ");
            $stmt->execute([
                'email' => $email,
                'ip' => $ip,
                'user_agent' => substr($userAgent, 0, 500)
            ]);

            // Check if should lock
            $failedCount = $this->getFailedAttemptCount($email);

            if ($failedCount >= $this->maxAttempts) {
                $this->lockAccount($email);
                $this->logSuspiciousActivity($email, 'Account locked after ' . $failedCount . ' failed attempts');

                return [
                    'locked' => true,
                    'message' => "Account locked due to too many failed attempts. Please try again in " . ($this->lockDuration / 60) . " minutes.",
                    'remaining_attempts' => 0,
                    'lock_duration_minutes' => $this->lockDuration / 60
                ];
            }

            $remaining = $this->maxAttempts - $failedCount;
            return [
                'locked' => false,
                'message' => "Invalid credentials. {$remaining} attempt(s) remaining before account lock.",
                'remaining_attempts' => $remaining
            ];
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: recordFailedAttempt failed - " . $e->getMessage());
            return ['locked' => false, 'remaining_attempts' => $this->maxAttempts];
        }
    }

    /**
     * Lock account
     * 
     * @param string $email Email address
     */
    private function lockAccount(string $email): void
    {
        $email = strtolower(trim($email));
        $ip = $this->getClientIP();

        try {
            $lockedUntil = date('Y-m-d H:i:s', time() + $this->lockDuration);

            $stmt = $this->conn->prepare("
                INSERT INTO {$this->tableName} (email, ip_address, user_agent, is_locked, locked_until)
                VALUES (:email, :ip, :user_agent, 1, :locked_until)
            ");
            $stmt->execute([
                'email' => $email,
                'ip' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'locked_until' => $lockedUntil
            ]);
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: lockAccount failed - " . $e->getMessage());
        }
    }

    /**
     * Record successful login and clear attempts
     * 
     * @param string $email Email address
     */
    public function recordSuccessfulLogin(string $email): void
    {
        $email = strtolower(trim($email));
        $ip = $this->getClientIP();

        try {
            // Record successful login
            $stmt = $this->conn->prepare("
                INSERT INTO {$this->tableName} (email, ip_address, user_agent, is_successful)
                VALUES (:email, :ip, :user_agent, 1)
            ");
            $stmt->execute([
                'email' => $email,
                'ip' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

            // Clear locks for this email/IP
            $this->clearAttempts($email);
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: recordSuccessfulLogin failed - " . $e->getMessage());
        }
    }

    /**
     * Clear failed attempts and locks for an email/IP
     * 
     * @param string $email Email address
     */
    public function clearAttempts(string $email): void
    {
        $email = strtolower(trim($email));
        $ip = $this->getClientIP();

        try {
            // Remove locks
            $stmt = $this->conn->prepare("
                UPDATE {$this->tableName} 
                SET is_locked = 0, locked_until = NULL
                WHERE (email = :email OR ip_address = :ip) AND is_locked = 1
            ");
            $stmt->execute(['email' => $email, 'ip' => $ip]);
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: clearAttempts failed - " . $e->getMessage());
        }
    }

    /**
     * Log suspicious activity
     */
    private function logSuspiciousActivity(string $email, string $activity): void
    {
        $logFile = __DIR__ . '/../logs/security_alerts.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logEntry = sprintf(
            "[%s] SECURITY ALERT - Email: %s, IP: %s, Activity: %s, User-Agent: %s\n",
            date('Y-m-d H:i:s'),
            $email,
            $this->getClientIP(),
            $activity,
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 200)
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Cleanup old records (run periodically via cron)
     * 
     * @param int $daysToKeep Days to keep records (default: 30)
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM {$this->tableName}
                WHERE attempt_time < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $daysToKeep]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: cleanup failed - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get recent login attempts for an email (for admin review)
     * 
     * @param string $email Email address
     * @param int $limit Number of records
     * @return array Login attempts
     */
    public function getRecentAttempts(string $email, int $limit = 10): array
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM {$this->tableName}
                WHERE email = :email
                ORDER BY attempt_time DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':email', strtolower(trim($email)));
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("LoginAttemptTracker: getRecentAttempts failed - " . $e->getMessage());
            return [];
        }
    }
}


