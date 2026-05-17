<?php

/**
 * CRUD Operations Manager
 * Handles all database operations for the system
 */

// Use centralized db_connect.php
require_once dirname(dirname(__DIR__)) . '/common/constants.php';
require_once DB_CONNECT_FILE;
require_once __DIR__ . '/globalvariable.php';
require_once __DIR__ . '/../../common/helpers/error_logger.php';

if (!class_exists('DatabaseOperations')) {
    class DatabaseOperations
    {
        private $conn;
        private $ext_conn;

        public function __construct($main_conn, $external_conn = null)
        {
            $this->conn = $main_conn;
            $this->ext_conn = $external_conn;
        }

        // ============================================
        // USER OPERATIONS
        // ============================================

        /**
         * Create a new user
         */
        public function createUser($data)
        {
            try {
                $sql = "INSERT INTO tbl_users (role_id, name, email, password, phone, address, status) 
                    VALUES (:role_id, :name, :email, :password, :phone, :address, :status)";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    'role_id' => $data['role_id'],
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'status' => $data['status'] ?? 'active'
                ]);
            } catch (PDOException $e) {
                logDatabaseError($e, "Create User");
                return false;
            }
        }

        /**
         * Get user by ID
         */
        public function getUserById($id)
        {
            try {
                $sql = "SELECT u.*, r.role_name, r.role_slug 
                    FROM tbl_users u 
                    INNER JOIN tbl_roles r ON u.role_id = r.id 
                    WHERE u.id = :id";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute(['id' => $id]);
                return $stmt->fetch();
            } catch (PDOException $e) {
                logDatabaseError($e, "Get User by ID");
                return false;
            }
        }

        /**
         * Update user
         */
        public function updateUser($id, $data)
        {
            try {
                $sql = "UPDATE tbl_users SET 
                    name = :name, 
                    email = :email, 
                    phone = :phone, 
                    address = :address,
                    status = :status
                    WHERE id = :id";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    'id' => $id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                    'status' => $data['status'] ?? 'active'
                ]);
            } catch (PDOException $e) {
                logDatabaseError($e, "Update User");
                return false;
            }
        }

        /**
         * Delete user
         */
        public function deleteUser($id)
        {
            try {
                $sql = "DELETE FROM tbl_users WHERE id = :id";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute(['id' => $id]);
            } catch (PDOException $e) {
                logDatabaseError($e, "Delete User");
                return false;
            }
        }

        /**
         * Get all users with pagination
         */
        public function getAllUsers($page = 1, $limit = RECORDS_PER_PAGE, $role_id = null)
        {
            try {
                $offset = ($page - 1) * $limit;
                $sql = "SELECT u.*, r.role_name, r.role_slug 
                    FROM tbl_users u 
                    INNER JOIN tbl_roles r ON u.role_id = r.id";

                if ($role_id !== null) {
                    $sql .= " WHERE u.role_id = :role_id";
                }

                $sql .= " ORDER BY u.created_at DESC LIMIT :limit OFFSET :offset";

                $stmt = $this->conn->prepare($sql);
                if ($role_id !== null) {
                    $stmt->bindValue(':role_id', $role_id, PDO::PARAM_INT);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                logDatabaseError($e, "Get All Users");
                return [];
            }
        }

        // ============================================
        // STUDENT OPERATIONS (External Database)
        // ============================================

        /**
         * Get student from external database
         */
        public function getStudentById($student_id)
        {
            if (!$this->ext_conn)
                return false;

            try {
                $sql = "SELECT * FROM tbl_gm_std_registration WHERE id = :id";
                $stmt = $this->ext_conn->prepare($sql);
                $stmt->execute(['id' => $student_id]);
                return $stmt->fetch();
            } catch (PDOException $e) {
                logDatabaseError($e, "Get Student by ID");
                return false;
            }
        }

        /**
         * Get all students from external database
         */
        public function getAllStudents($page = 1, $limit = RECORDS_PER_PAGE)
        {
            if (!$this->ext_conn)
                return [];

            try {
                $offset = ($page - 1) * $limit;
                $sql = "SELECT * FROM tbl_gm_std_registration ORDER BY id DESC LIMIT :limit OFFSET :offset";
                $stmt = $this->ext_conn->prepare($sql);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll();
            } catch (PDOException $e) {
                logDatabaseError($e, "Get All Students");
                return [];
            }
        }

    }
}

/**
 * Generate a unique transaction ID
 * @param string $prefix The prefix for the transaction ID (default: 'GMI')
 * @return string The generated unique transaction ID
 */
function generateUniqueTransactionID($prefix = 'GMI')
{
    // Generate a timestamp
    $timestamp = date('YmdHis');

    // Generate a unique identifier (you can customize this part)
    $uniqueIdentifier = strtoupper(uniqid());

    // Generate a random number to add to the ID
    $randomNumber = rand(1000, 9999);

    // Combine the elements to create a unique ID
    $transactionID = $prefix . $timestamp . $uniqueIdentifier . $randomNumber;

    return $transactionID;
}

// Initialize the operations class
$db_ops = new DatabaseOperations($conn, $ext_conn ?? null);


