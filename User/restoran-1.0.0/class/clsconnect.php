<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class connect_db
{
    private $conn;
    private $host = "localhost";
    private $user = "root";
    private $pass = "";
    private $db = "hceeab2b55_restaurant";

    public function __construct()
    {
        $this->initializeConnection();
    }

    private function initializeConnection()
    {
        if ($this->conn instanceof mysqli) {
            if ($this->conn->ping()) {
                return;
            }
            $this->conn->close();
        }

        $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->db);
        if ($this->conn->connect_error) {
            throw new Exception("Kết nối database thất bại: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
        $this->conn->query("SET NAMES 'utf8mb4'");
        $this->conn->query("SET CHARACTER SET utf8mb4");
        $this->conn->query("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");
    }

    private function getConnection()
    {
        $this->initializeConnection();
        return $this->conn;
    }

    public function beginTransaction()
    {
        $this->getConnection()->begin_transaction();
    }

    public function commit()
    {
        $this->getConnection()->commit();
    }

    public function rollback()
    {
        $this->getConnection()->rollback();
    }
    
    public function xuatdulieu($sql)
    {
        $arr = [];
        $conn = $this->getConnection();
        $result = $conn->query($sql);
        if ($result === false) {
            throw new Exception("Lỗi query: " . $conn->error);
        }
        if ($result->num_rows) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
        }
        return $arr;
    }
    
    public function xuatdulieu_prepared($sql, $params = [])
    {
        $arr = [];
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Lỗi prepare: " . $conn->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Lỗi execute: " . $stmt->error);
        }

        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
            $result->free();
        }
        $stmt->close();
        return $arr;
    }

    public function tuychinh($sql, $params = [])
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Lỗi prepare: " . $conn->error);
        }

        if (!empty($params)) {
            $types = '';
            foreach ($params as $param) {
                if (is_int($param)) {
                    $types .= 'i';
                } elseif (is_float($param)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Lỗi execute: " . $stmt->error);
        }
        $stmt->close();
        return 1;
    }

    public function getLastInsertId()
    {
        $conn = $this->getConnection();
        $lastId = $conn->insert_id;
        error_log("Last Insert ID: " . $lastId);
        return $lastId;
    }

    public function executeRaw(string $sql)
    {
        $conn = $this->getConnection();
        if ($conn->query($sql) === false) {
            throw new Exception("Raw SQL error: " . $conn->error);
        }
        return true;
    }

    public function hasColumn(string $table, string $column): bool
    {
        $table = trim($table);
        $column = trim($column);
        if ($table === '' || $column === '') {
            return false;
        }
        $sql = "SELECT 1 FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                  AND column_name = ?
                LIMIT 1";
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("hasColumn prepare failed: " . $conn->error);
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }
        $sql = "SELECT 1 FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
                LIMIT 1";
        $conn = $this->getConnection();
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("tableExists prepare failed: " . $conn->error);
        }
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}
?>
