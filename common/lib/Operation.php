<?php

/**
 * Database Operations Class - Shared
 * Centralized CRUD operations with SQL Injection Prevention
 * All operations use prepared statements with parameter binding
 *
 * @author Counselling Portal Team
 * @version 1.1
 * @date January 2026
 * @location common/lib/Operation.php
 */

// Dynamic db_connect detection - works from any location
$dbConnectPaths = [
  __DIR__ . '/../../counselling-backend/db_connect.php',  // From common/lib
  // __DIR__ . '/../counselling-backend/db_connect.php',  // Fallback
  // __DIR__ . '/db_connect.php',  // Local
];

foreach ($dbConnectPaths as $path) {
  if (file_exists($path)) {
    require_once $path;
    break;
  }
}

if (!class_exists('DatabaseOperations')) {
  class DatabaseOperations
  {
    private $conn;

    /**
     * Constructor - Initialize database connection
     */
    public function __construct()
    {
      global $conn;
      if (!isset($conn) || $conn === null) {
        // Try multiple paths for db_connect
        $paths = [
          __DIR__ . '/../../counselling-backend/db_connect.php',
          __DIR__ . '/../counselling-backend/db_connect.php',
        ];
        foreach ($paths as $path) {
          if (file_exists($path)) {
            require_once $path;
            break;
          }
        }
      }
      $this->conn = $conn;
    }

    /**
     * Get the database connection
     * @return PDO
     */
    public function getConnection()
    {
      return $this->conn;
    }

    /**
     * SELECT Operation - Fetch records with SQL Injection Prevention
     *
     * @param string $table Table name
     * @param array $columns Array of column names to select (default: all columns)
     * @param array $where Associative array of WHERE conditions [column => value]
     * @param string $orderBy ORDER BY clause (e.g., "id DESC")
     * @param int $limit Limit number of records
     * @param int $offset Offset for pagination
     * @return array|false Returns array of records or false on failure
     */
    public function select($table, $columns = ['*'], $where = [], $orderBy = '', $limit = 0, $offset = 0)
    {
      try {
        // Sanitize table name
        $table = $this->sanitizeIdentifier($table);

        // Build column list
        $columnList = empty($columns) ? '*' : implode(', ', array_map([$this, 'sanitizeIdentifier'], $columns));

        // Build query
        $sql = "SELECT {$columnList} FROM {$table}";

        // Build WHERE clause
        $params = [];
        if (!empty($where)) {
          $conditions = [];
          foreach ($where as $column => $value) {
            $column = $this->sanitizeIdentifier($column);
            $conditions[] = "{$column} = ?";
            $params[] = $value;
          }
          $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        // Add ORDER BY
        if (!empty($orderBy)) {
          $sql .= ' ORDER BY ' . $this->sanitizeOrderBy($orderBy);
        }

        // Add LIMIT and OFFSET
        if ($limit > 0) {
          $sql .= ' LIMIT ' . intval($limit);
          if ($offset > 0) {
            $sql .= ' OFFSET ' . intval($offset);
          }
        }

        // Prepare and execute
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        $this->logError('SELECT Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * SELECT Single Record
     *
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param array $where WHERE conditions
     * @return array|false Returns single record or false
     */
    public function selectOne($table, $columns = ['*'], $where = [])
    {
      $result = $this->select($table, $columns, $where, '', 1);
      return !empty($result) ? $result[0] : false;
    }

    /**
     * * Read all records (alias for select)
     *
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @param string $orderBy ORDER BY clause
     * @param array $columns Columns to select
     * @return array|false Returns array of records or false
     */
    public function readAll($table, $where = [], $orderBy = '', $columns = ['*'])
    {
      return $this->select($table, $columns, $where, $orderBy);
    }

    /**
     * * Custom SELECT Query with SQL Injection Prevention
     *
     * @param string $sql SQL query with ? placeholders
     * @param array $params Parameters to bind
     * @return array|false Returns array of records or false
     */
    public function customSelect($sql, $params = [])
    {
      try {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        $this->logError('Custom SELECT Error', $e->getMessage(), $sql);
        return false;
      }
    }

    /**
     * Custom Query (Single Record)
     *
     * @param string $sql SQL query
     * @param array $params Parameters
     * @return array|false Single record
     */
    public function customSelectOne($sql, $params = [])
    {
      $result = $this->customSelect($sql, $params);
      return !empty($result) ? $result[0] : false;
    }

    /**
     * INSERT Operation - Add new record with SQL Injection Prevention
     *
     * @param string $table Table name
     * @param array $data Associative array [column => value]
     * @return int|false Returns last insert ID or false on failure
     */
    public function insert($table, $data)
    {
      try {
        if (empty($data)) {
          throw new Exception('No data provided for INSERT');
        }

        // Sanitize table name
        $table = $this->sanitizeIdentifier($table);

        // Build query
        $columns = array_keys($data);
        $sanitizedColumns = array_map([$this, 'sanitizeIdentifier'], $columns);
        $placeholders = array_fill(0, count($data), '?');

        $sql = "INSERT INTO {$table} (" . implode(', ', $sanitizedColumns) . ') 
                    VALUES (' . implode(', ', $placeholders) . ')';

        // Prepare and execute
        $stmt = $this->conn->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->conn->lastInsertId();
      } catch (PDOException $e) {
        $this->logError('INSERT Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * UPDATE Operation - Update records with SQL Injection Prevention
     *
     * @param string $table Table name
     * @param array $data Associative array of data to update [column => value]
     * @param array $where WHERE conditions [column => value]
     * @return int|false Returns number of affected rows or false on failure
     */
    public function update($table, $data, $where)
    {
      try {
        if (empty($data)) {
          throw new Exception('No data provided for UPDATE');
        }

        if (empty($where)) {
          throw new Exception('WHERE condition required for UPDATE');
        }

        // Sanitize table name
        $table = $this->sanitizeIdentifier($table);

        // Build SET clause
        $setClause = [];
        $params = [];
        foreach ($data as $column => $value) {
          $column = $this->sanitizeIdentifier($column);
          $setClause[] = "{$column} = ?";
          $params[] = $value;
        }

        // Build WHERE clause
        $whereClause = [];
        foreach ($where as $column => $value) {
          $column = $this->sanitizeIdentifier($column);
          $whereClause[] = "{$column} = ?";
          $params[] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $setClause)
          . ' WHERE ' . implode(' AND ', $whereClause);

        // Prepare and execute
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
      } catch (PDOException $e) {
        $this->logError('UPDATE Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * DELETE Operation - Delete records with SQL Injection Prevention
     *
     * @param string $table Table name
     * @param array $where WHERE conditions [column => value]
     * @return int|false Returns number of affected rows or false on failure
     */
    public function delete($table, $where)
    {
      try {
        if (empty($where)) {
          throw new Exception('WHERE condition required for DELETE');
        }

        // Sanitize table name
        $table = $this->sanitizeIdentifier($table);

        // Build WHERE clause
        $whereClause = [];
        $params = [];
        foreach ($where as $column => $value) {
          $column = $this->sanitizeIdentifier($column);
          $whereClause[] = "{$column} = ?";
          $params[] = $value;
        }

        $sql = "UPDATE {$table} SET is_deleted = 1, deleted_at = NOW() WHERE " . implode(' AND ', $whereClause);

        // Prepare and execute
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
      } catch (PDOException $e) {
        $this->logError('DELETE Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * Hard DELETE Operation - Permanently delete records
     *
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return int|false Returns affected rows or false
     */
    public function hardDelete($table, $where)
    {
      try {
        if (empty($where)) {
          throw new Exception('WHERE condition required for DELETE');
        }

        $table = $this->sanitizeIdentifier($table);

        $whereClause = [];
        $params = [];
        foreach ($where as $column => $value) {
          $column = $this->sanitizeIdentifier($column);
          $whereClause[] = "{$column} = ?";
          $params[] = $value;
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClause);

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
      } catch (PDOException $e) {
        $this->logError('HARD DELETE Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * COUNT Operation - Count records
     *
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return int|false Returns count or false
     */
    public function count($table, $where = [])
    {
      try {
        $table = $this->sanitizeIdentifier($table);
        $sql = "SELECT COUNT(*) as count FROM {$table}";

        $params = [];
        if (!empty($where)) {
          $conditions = [];
          foreach ($where as $column => $value) {
            $column = $this->sanitizeIdentifier($column);
            $conditions[] = "{$column} = ?";
            $params[] = $value;
          }
          $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $result['count'];
      } catch (PDOException $e) {
        $this->logError('COUNT Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * EXISTS Operation - Check if record exists
     *
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return bool Returns true if exists, false otherwise
     */
    public function exists($table, $where)
    {
      $count = $this->count($table, $where);
      return $count > 0;
    }

    /**
     * BEGIN Transaction
     */
    public function beginTransaction()
    {
      try {
        return $this->conn->beginTransaction();
      } catch (PDOException $e) {
        $this->logError('BEGIN Transaction Error', $e->getMessage());
        return false;
      }
    }

    /**
     * COMMIT Transaction
     */
    public function commit()
    {
      try {
        return $this->conn->commit();
      } catch (PDOException $e) {
        $this->logError('COMMIT Error', $e->getMessage());
        return false;
      }
    }

    /**
     * ROLLBACK Transaction
     */
    public function rollback()
    {
      try {
        return $this->conn->rollBack();
      } catch (PDOException $e) {
        $this->logError('ROLLBACK Error', $e->getMessage());
        return false;
      }
    }

    /**
     * Execute Custom Query (INSERT/UPDATE/DELETE)
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return bool Returns true on success, false on failure
     */
    public function execute($sql, $params = [])
    {
      try {
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
      } catch (PDOException $e) {
        $this->logError('EXECUTE Error', $e->getMessage(), $sql);
        return false;
      }
    }

    /**
     * Get Last Insert ID
     */
    public function lastInsertId()
    {
      return $this->conn->lastInsertId();
    }

    /**
     * Sanitize Table/Column Identifier
     * Prevents SQL injection in identifiers
     *
     * @param string $identifier Table or column name
     * @return string Sanitized identifier
     */
    private function sanitizeIdentifier($identifier)
    {
      // Remove backticks and allow only alphanumeric, underscore, and dot
      $identifier = str_replace('`', '', $identifier);
      if (!preg_match('/^[a-zA-Z0-9_\.\*]+$/', $identifier)) {
        throw new Exception("Invalid identifier: {$identifier}");
      }
      return $identifier;
    }

    /**
     * Sanitize ORDER BY clause
     *
     * @param string $orderBy ORDER BY clause
     * @return string Sanitized ORDER BY
     */
    private function sanitizeOrderBy($orderBy)
    {
      // Allow only alphanumeric, underscore, dot, comma, space, ASC, DESC
      if (!preg_match('/^[a-zA-Z0-9_\.\,\s]+(ASC|DESC)?$/i', trim($orderBy))) {
        throw new Exception('Invalid ORDER BY clause');
      }
      return $orderBy;
    }

    /**
     * Log Database Errors
     *
     * @param string $operation Operation type
     * @param string $message Error message
     * @param string $sql SQL query (optional)
     */
    private function logError($operation, $message, $sql = '')
    {
      $logMessage = "[{$operation}] {$message}";
      if (!empty($sql)) {
        $logMessage .= " | SQL: {$sql}";
      }
      error_log($logMessage);

      // Log to file if logger exists
      if (function_exists('logDatabaseError')) {
        logDatabaseError(new Exception($message), $operation);
      }
    }

    /**
     * Escape LIKE pattern
     *
     * @param string $pattern Pattern to escape
     * @return string Escaped pattern
     */
    public function escapeLike($pattern)
    {
      return str_replace(['%', '_'], ['\%', '\_'], $pattern);
    }

    /**
     * Advanced SELECT with LIKE, IN, and custom operators
     *
     * @param string $table Table name
     * @param array $columns Columns to select
     * @param array $conditions Conditions with operators
     *        Format: ['column' => ['operator' => '=', 'value' => 'test']]
     *        Operators: =, !=, >, <, >=, <=, LIKE, IN, NOT IN
     * @param string $orderBy ORDER BY clause
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array|false Results or false
     */
    public function advancedSelect($table, $columns = ['*'], $conditions = [], $orderBy = '', $limit = 0, $offset = 0)
    {
      try {
        $table = $this->sanitizeIdentifier($table);
        $columnList = empty($columns) ? '*' : implode(', ', array_map([$this, 'sanitizeIdentifier'], $columns));

        $sql = "SELECT {$columnList} FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
          $whereClauses = [];
          foreach ($conditions as $column => $condition) {
            $column = $this->sanitizeIdentifier($column);
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'];

            switch (strtoupper($operator)) {
              case 'IN':
              case 'NOT IN':
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $whereClauses[] = "{$column} {$operator} ({$placeholders})";
                $params = array_merge($params, $value);
                break;
              case 'LIKE':
                $whereClauses[] = "{$column} LIKE ?";
                $params[] = $value;
                break;
              default:
                $whereClauses[] = "{$column} {$operator} ?";
                $params[] = $value;
            }
          }
          $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }

        if (!empty($orderBy)) {
          $sql .= ' ORDER BY ' . $this->sanitizeOrderBy($orderBy);
        }

        if ($limit > 0) {
          $sql .= ' LIMIT ' . intval($limit);
          if ($offset > 0) {
            $sql .= ' OFFSET ' . intval($offset);
          }
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        $this->logError('Advanced SELECT Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * SELECT with JOIN - Secure JOIN queries with parameter binding
     *
     * @param string $mainTable Main table name with alias (e.g., 'tbl_users u')
     * @param array $columns Columns to select (use alias like 'u.name', 'r.role_name')
     * @param array $joins Array of JOIN configurations
     * @param array $where WHERE conditions with parameter binding
     * @param string $orderBy ORDER BY clause
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array|false Results or false
     */
    public function selectWithJoin($mainTable, $columns = ['*'], $joins = [], $where = [], $orderBy = '', $limit = 0, $offset = 0)
    {
      try {
        $mainTable = $this->sanitizeTableAlias($mainTable);
        $columnList = empty($columns) ? '*' : implode(', ', array_map([$this, 'sanitizeTableAlias'], $columns));
        $sql = "SELECT {$columnList} FROM {$mainTable}";

        if (!empty($joins)) {
          foreach ($joins as $join) {
            $joinType = strtoupper($join['type'] ?? 'LEFT');
            if (!in_array($joinType, ['LEFT', 'RIGHT', 'INNER', 'OUTER'])) {
              throw new Exception("Invalid JOIN type: {$joinType}");
            }

            // Handle table with or without alias
            $joinTableFull = $join['table'];
            $tableParts = preg_split('/\s+/', trim($joinTableFull), 2);
            $joinTable = $this->sanitizeIdentifier($tableParts[0]);
            $joinAlias = isset($tableParts[1]) ? $tableParts[1] : (isset($join['alias']) ? $this->sanitizeIdentifier($join['alias']) : '');

            $joinOn = $this->sanitizeJoinCondition($join['on']);
            $sql .= " {$joinType} JOIN {$joinTable}";
            if (!empty($joinAlias)) {
              $sql .= " {$joinAlias}";
            }
            $sql .= " ON {$joinOn}";
          }
        }

        $params = [];
        if (!empty($where)) {
          $conditions = [];
          foreach ($where as $column => $value) {
            $column = $this->sanitizeTableAlias($column);
            $conditions[] = "{$column} = ?";
            $params[] = $value;
          }
          $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (!empty($orderBy)) {
          $sql .= ' ORDER BY ' . $this->sanitizeOrderBy($orderBy);
        }
        if ($limit > 0) {
          $sql .= ' LIMIT ' . intval($limit);
          if ($offset > 0) {
            $sql .= ' OFFSET ' . intval($offset);
          }
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        $this->logError('SELECT with JOIN Error', $e->getMessage(), $sql ?? '');
        return false;
      }
    }

    /**
     * Read single record with JOIN (alias for selectWithJoin that returns one row)
     *
     * @param string $mainTable Main table name with alias
     * @param array $columns Columns to select
     * @param array $joins Array of JOIN configurations
     * @param array $where WHERE conditions
     * @param string $orderBy ORDER BY clause
     * @return array|false Single record or false
     */
    public function readWithJoin($mainTable, $columns = ['*'], $joins = [], $where = [], $orderBy = '')
    {
      $results = $this->selectWithJoin($mainTable, $columns, $joins, $where, $orderBy, 1, 0);
      return $results && count($results) > 0 ? $results[0] : false;
    }

    private function sanitizeTableAlias($identifier)
    {
      if (!preg_match('/^[a-zA-Z0-9_\.\ \*]+$/', $identifier)) {
        throw new Exception("Invalid table alias: {$identifier}");
      }
      return trim($identifier);
    }

    private function sanitizeJoinCondition($condition)
    {
      if (!preg_match('/^[a-zA-Z0-9_\.]+\s*=\s*[a-zA-Z0-9_\.]+(\s+AND\s+[a-zA-Z0-9_\.]+\s*=\s*[0-9]+)?$/i', trim($condition))) {
        throw new Exception("Invalid JOIN condition: {$condition}");
      }
      return trim($condition);
    }
  }  // End of class DatabaseOperations
}  // End of if (!class_exists('DatabaseOperations'))

// Create alias for backward compatibility
if (!class_exists('Operation')) {
  class_alias('DatabaseOperations', 'Operation');
}

// Create global instance for easy access
if (!isset($dbOps)) {
  $dbOps = new DatabaseOperations();
}


