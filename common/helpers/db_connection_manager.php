<?php

require_once __DIR__ . '/../../common/constants.php';
/**
 * Database Connection Manager
 * Ensures proper connection handling and automatic cleanup
 * Prevents max_connections_per_hour errors
 */

class DatabaseConnectionManager
{
    private static $instance = null;
    private $conn = null;
    private $isConnected = false;

    private function __construct()
    {
        // Private constructor to prevent direct instantiation
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get database connection (creates if doesn't exist)
     */
    public function getConnection()
    {
        if (!$this->isConnected || $this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }

    /**
     * Create database connection
     */
    private function connect()
    {
        try {
            require_once ENV_CONFIG_FILE;

            $this->conn = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    // Disable persistent connections to prevent connection leaks
                    PDO::ATTR_PERSISTENT => false
                ]
            );

            $this->isConnected = true;
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Close connection explicitly
     */
    public function close()
    {
        if ($this->conn !== null) {
            $this->conn = null;
            $this->isConnected = false;
        }
    }

    /**
     * Check if connection is active
     */
    public function isConnected()
    {
        return $this->isConnected;
    }

    /**
     * Destructor - automatically close connection
     */
    public function __destruct()
    {
        $this->close();
    }
}

/**
 * Helper function to get database connection
 * Usage: $conn = getDbConnection();
 */
function getDbConnection()
{
    return DatabaseConnectionManager::getInstance()->getConnection();
}

/**
 * Helper function to close database connection
 * Usage: closeDbConnection();
 */
function closeDbConnection()
{
    DatabaseConnectionManager::getInstance()->close();
}

/**
 * Auto-close connection at script end
 */
register_shutdown_function(function () {
    closeDbConnection();
});
