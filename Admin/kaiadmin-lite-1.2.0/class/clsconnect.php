<?php
if (class_exists('connect_db', false)) {
    return;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Database Connection Class
 */
class connect_db
{
    /**
     * Shared mysqli connection so legacy code that expects a global $conn continues to work.
     *
     * @var mysqli|null
     */
    private static $sharedConnection = null;

    private $conn;
    private $host = "localhost";
    private $user = "appuser";
    private $pass = "StrongPass1234";
    private $db = "hceeab2b55_restaurant";

    /**
     * Constructor - Establishes database connection
     */
    public function __construct()
    {
        if (self::$sharedConnection instanceof mysqli) {
            $this->conn = self::$sharedConnection;
        } else {
            $this->conn = mysqli_connect($this->host, $this->user, $this->pass, $this->db);
            if (!$this->conn) {
                die("Kết nối CSDL thất bại: " . mysqli_connect_error());
            }
            mysqli_set_charset($this->conn, "utf8");

            self::$sharedConnection = $this->conn;
        }

        $GLOBALS['conn'] = $this->conn;
        $GLOBALS['admin_db'] = $this;
    }

    /**
     * Execute a SQL query with prepared statements
     * 
     * @param string $sql The SQL query
     * @param array $params The parameters to bind
     * @return array The result set as an associative array or empty array on error
     */
    public function xuatdulieu_prepared($sql, $params = [])
    {
        try {
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if (!$stmt) {
                $error = "Lỗi câu truy vấn: " . mysqli_error($this->conn);
                error_log($error);
                return [];
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
            
                // Bind parameters safely
                if (!$stmt->bind_param($types, ...$params)) {
                    $error = "Lỗi bind_param: " . $stmt->error;
                    error_log($error);
                    $stmt->close();
                    return [];
        }
            }
            
            // Execute query safely
            if (!$stmt->execute()) {
                $error = "Lỗi execute: " . $stmt->error;
                error_log($error);
                $stmt->close();
                return [];
            }
            
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $stmt->close();
        return $data;
        } catch (Exception $e) {
            error_log("Database error in xuatdulieu_prepared: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Execute an SQL query that does not return a result set
     * 
     * @param string $sql The SQL query
     * @param array $params The parameters to bind
     * @return int|bool The number of affected rows or false on error
     */
    public function tuychinh($sql, $params = [])
    {
        try {
        $stmt = mysqli_prepare($this->conn, $sql);
        
        if (!$stmt) {
                $error = "Lỗi câu truy vấn: " . mysqli_error($this->conn);
                error_log($error);
                return false;
        }
        
        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } elseif (is_string($param)) {
                    $types .= 's';
                } else {
                    $types .= 'b';
                }
            }
            
                // Bind parameters safely
                if (!$stmt->bind_param($types, ...$params)) {
                    $error = "Lỗi bind_param: " . $stmt->error;
                    error_log($error);
                    $stmt->close();
                    return false;
        }
            }
            
            // Execute query safely
            if (!$stmt->execute()) {
                $error = "Lỗi execute: " . $stmt->error;
                error_log($error);
                $stmt->close();
                return false;
            }
            
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        return $affected_rows;
        } catch (Exception $e) {
            error_log("Database error in tuychinh: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a raw SQL statement without preparing.
     *
     * @param string $sql
     * @return bool
     */
    public function executeRaw($sql)
    {
        try {
            if (!mysqli_query($this->conn, $sql)) {
                $error = "Raw SQL error: " . mysqli_error($this->conn);
                error_log($error);
                return false;
            }
            return true;
        } catch (Exception $e) {
            error_log("executeRaw exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists in the current database
     *
     * @param string $table
     * @return bool
     */
    public function tableExists($table)
    {
        if (!is_string($table) || trim($table) === '') {
            return false;
        }

        try {
            $sql = "SELECT 1 
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() AND table_name = ? 
                    LIMIT 1";
            $stmt = mysqli_prepare($this->conn, $sql);
            if (!$stmt) {
                error_log("tableExists prepare failed: " . mysqli_error($this->conn));
                return false;
            }
            $tableName = trim($table);
            mysqli_stmt_bind_param($stmt, 's', $tableName);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $exists;
        } catch (Exception $e) {
            error_log("tableExists exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Begin a transaction
     */
    public function beginTransaction()
    {
        try {
            error_log("Beginning database transaction");
            if (!mysqli_begin_transaction($this->conn)) {
                $error = "Error beginning transaction: " . mysqli_error($this->conn);
                error_log($error);
                throw new Exception($error);
            }
            error_log("Transaction started successfully");
            return true;
        } catch (Exception $e) {
            error_log("Transaction start error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Commit a transaction
     */
    public function commit()
    {
        try {
            error_log("Committing transaction");
            if (!mysqli_commit($this->conn)) {
                $error = "Error committing transaction: " . mysqli_error($this->conn);
                error_log($error);
                throw new Exception($error);
            }
            error_log("Transaction committed successfully");
            return true;
        } catch (Exception $e) {
            error_log("Transaction commit error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Roll back a transaction
     */
    public function rollback()
    {
        try {
            error_log("Rolling back transaction");
            if (!mysqli_rollback($this->conn)) {
                $error = "Error rolling back transaction: " . mysqli_error($this->conn);
                error_log($error);
                // Don't throw here, just log the error
            } else {
                error_log("Transaction rolled back successfully");
            }
            return true;
        } catch (Exception $e) {
            error_log("Transaction rollback error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Close the database connection
     */
    public function close()
    {
        mysqli_close($this->conn);
    }

    public function xuatdulieu($sql)
    {
        $arr = [];
        $link = $this->conn;
        $result = $link->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi query: " . $link->error);
        }
        if ($result->num_rows) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }

    public function getLastInsertId()
    {
        return mysqli_insert_id($this->conn);
    }

    /**
     * Get underlying mysqli connection
     *
     * @return mysqli The active mysqli connection instance
     */
    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * Check if a column exists in a table
     *
     * @param string $table
     * @param string $column
     * @return bool
     */
    public function hasColumn($table, $column)
    {
        if (!is_string($table) || trim($table) === '' || !is_string($column) || trim($column) === '') {
            return false;
        }

        try {
            $sql = "SELECT 1
                    FROM information_schema.columns
                    WHERE table_schema = DATABASE()
                      AND table_name = ?
                      AND column_name = ?
                    LIMIT 1";
            $stmt = mysqli_prepare($this->conn, $sql);
            if (!$stmt) {
                error_log("hasColumn prepare failed: " . mysqli_error($this->conn));
                return false;
            }
            $tableName = trim($table);
            $columnName = trim($column);
            mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            $exists = mysqli_stmt_num_rows($stmt) > 0;
            mysqli_stmt_close($stmt);
            return $exists;
        } catch (Exception $e) {
            error_log("hasColumn exception: " . $e->getMessage());
            return false;
        }
    }

}

if (
    !isset($GLOBALS['conn']) ||
    !($GLOBALS['conn'] instanceof mysqli)
) {
    $bootstrapConnection = new connect_db();
    $GLOBALS['conn'] = $bootstrapConnection->getConnection();
    $GLOBALS['admin_db'] = $bootstrapConnection;
}
?>
