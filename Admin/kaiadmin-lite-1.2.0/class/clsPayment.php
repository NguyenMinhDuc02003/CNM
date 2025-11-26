<?php
/**
 * Payment Handler Class
 * Provides utilities for processing payments in the restaurant system
 */
class PaymentHandler {
    private $db;
    
    /**
     * Constructor - initializes database connection
     */
    public function __construct() {
        // Get the root path
        $root_path = $_SERVER['DOCUMENT_ROOT'] . '/CNM/Admin/kaiadmin-lite-1.2.0/';
        
        // Include required files if not already included
        if (!class_exists('connect_db')) {
            require_once $root_path . 'class/clsconnect.php';
        }
        
        // Initialize database connection
        try {
            $this->db = new connect_db();
            
            // Test the connection
            $testSql = "SELECT 1";
            $result = $this->db->xuatdulieu($testSql);
            if (empty($result)) {
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu");
            }
            
            // Check if database supports transactions
            $supportsSql = "SHOW VARIABLES LIKE 'have_innodb'";
            $supportsResult = $this->db->xuatdulieu($supportsSql);
            if (empty($supportsResult) || strtolower($supportsResult[0]['Value'] ?? '') !== 'yes') {
                error_log("Warning: Database may not support transactions (InnoDB)");
            }
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e; // Re-throw exception to be caught by caller
        }
    }
    
    /**
     * Process cash payment for an order
     * 
     * @param int $idDH Order ID
     * @param float $amount Payment amount
     * @return array Result with status and message
     */
    public function processCashPayment($idDH, $amount) {
        try {
            // Log payment attempt
            error_log("Starting cash payment process for order #$idDH with amount $amount");
            
            // Kiểm tra kết nối cơ sở dữ liệu
            $this->checkDatabaseConnection();
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Get order information
            $orderInfo = $this->getOrderInfo($idDH);
            if (empty($orderInfo)) {
                throw new Exception("Không tìm thấy thông tin đơn hàng");
            }
            
            // Check if order is already paid
            if (isset($orderInfo['TrangThai']) && $orderInfo['TrangThai'] === 'Đã thanh toán') {
                throw new Exception("Đơn hàng đã được thanh toán trước đó");
            }
            
            // Check if order is in "Đã giao" status
            if (isset($orderInfo['TrangThai']) && $orderInfo['TrangThai'] !== 'Đã giao') {
                throw new Exception("Đơn hàng phải ở trạng thái 'Đã giao' để thanh toán");
            }
            
            $idKH = $orderInfo['idKH'];
            error_log("Found order with customer ID: $idKH");
            
            // Update order status - using direct query to ensure update happens
            $updateStatusSql = "UPDATE donhang SET TrangThai = 'Đã thanh toán' WHERE idDH = ?";
            $updateResult = $this->db->tuychinh($updateStatusSql, [$idDH]);
            
            if (!$updateResult) {
                throw new Exception("Không thể cập nhật trạng thái đơn hàng");
            }
            
            error_log("Successfully updated order status to 'Đã thanh toán'");
            
            // Create invoice directly
            $createInvoiceSql = "INSERT INTO hoadon (idKH, idDH, Ngay, hinhthucthanhtoan, TongTien) 
                                VALUES (?, ?, NOW(), 'Tiền mặt', ?)";
            $createInvoiceResult = $this->db->tuychinh($createInvoiceSql, [$idKH, $idDH, $amount]);
            
            if (!$createInvoiceResult) {
                throw new Exception("Không thể tạo hóa đơn");
            }
            
            $idHD = $this->db->getLastInsertId();
            error_log("Created invoice #$idHD for order #$idDH");
            
            // Add invoice details
            $addDetailsSql = "INSERT INTO chitiethoadon (idHD, idmonan, soluong, thanhtien)
                             SELECT ?, c.idmonan, c.SoLuong, (c.SoLuong * m.DonGia)
                             FROM chitietdonhang c
                             JOIN monan m ON c.idmonan = m.idmonan
                             WHERE c.idDH = ?";
            $addDetailsResult = $this->db->tuychinh($addDetailsSql, [$idHD, $idDH]);
            
            if (!$addDetailsResult) {
                throw new Exception("Không thể thêm chi tiết hóa đơn");
            }
            
            // Log transaction in history
            $this->logTransaction($idDH, $amount, 'Tiền mặt', 'Giao dịch thanh toán tiền mặt thành công');

            // Save payment record into thanhtoan table for cash payments so all payment methods
            // are recorded consistently. We use the existing savePaymentInfo helper which
            // creates the thanhtoan row (and creates the table if missing).
            $paymentRef = 'CASH' . date('ymdHis') . rand(1000, 9999);
            if (!$this->savePaymentInfo($idDH, $amount, 'cash', 'completed', $paymentRef)) {
                throw new Exception("Không thể lưu bản ghi thanh toán tiền mặt vào bảng thanhtoan");
            }

            // Commit transaction
            $this->db->commit();
            error_log("Transaction committed successfully for cash payment of order #$idDH");
            
            return [
                'status' => true,
                'message' => 'Thanh toán tiền mặt thành công'
            ];
        } catch (Exception $e) {
            // Rollback on error
            if (isset($this->db)) {
                $this->db->rollback();
            }
            error_log("Payment error: " . $e->getMessage());
            
            return [
                'status' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process VNPay payment (called from callback)
     * 
     * @param int $idDH Order ID
     * @param float $amount Payment amount
     * @param string $transactionNo VNPay transaction number
     * @param string $bankCode Bank code from VNPay
     * @return array Result with status and message
     */
    public function processVnPayPayment($idDH, $amount, $transactionNo, $bankCode = '') {
        try {
            // Log payment attempt
            error_log("Starting VNPay payment process for order #$idDH with amount $amount and transaction #$transactionNo");
            
            // Begin transaction
            $this->db->beginTransaction();
            
            // Get order information
            $orderInfo = $this->getOrderInfo($idDH);
            if (empty($orderInfo)) {
                throw new Exception("Không tìm thấy thông tin đơn hàng");
            }
            
            // Check if order is already paid
            if (isset($orderInfo['TrangThai']) && $orderInfo['TrangThai'] === 'Đã thanh toán') {
                throw new Exception("Đơn hàng đã được thanh toán trước đó");
            }
            
            $idKH = $orderInfo['idKH'];
            error_log("Found order with customer ID: $idKH");
            
            // Update order status - using direct query to ensure update happens
            $updateStatusSql = "UPDATE donhang SET TrangThai = 'Đã thanh toán' WHERE idDH = ?";
            $updateResult = $this->db->tuychinh($updateStatusSql, [$idDH]);
            
            if (!$updateResult) {
                throw new Exception("Không thể cập nhật trạng thái đơn hàng");
            }
            
            error_log("Successfully updated order status to 'Đã thanh toán' for VNPay payment");
            
            // Create invoice directly
            $createInvoiceSql = "INSERT INTO hoadon (idKH, idDH, Ngay, hinhthucthanhtoan, TongTien) 
                                VALUES (?, ?, NOW(), 'Chuyển khoản', ?)";
            $createInvoiceResult = $this->db->tuychinh($createInvoiceSql, [$idKH, $idDH, $amount]);
            
            if (!$createInvoiceResult) {
                throw new Exception("Không thể tạo hóa đơn");
            }
            
            $idHD = $this->db->getLastInsertId();
            error_log("Created invoice #$idHD for order #$idDH");
            
            // Add invoice details
            $addDetailsSql = "INSERT INTO chitiethoadon (idHD, idmonan, soluong, thanhtien)
                             SELECT ?, c.idmonan, c.SoLuong, (c.SoLuong * m.DonGia)
                             FROM chitietdonhang c
                             JOIN monan m ON c.idmonan = m.idmonan
                             WHERE c.idDH = ?";
            $addDetailsResult = $this->db->tuychinh($addDetailsSql, [$idHD, $idDH]);
            
            if (!$addDetailsResult) {
                throw new Exception("Không thể thêm chi tiết hóa đơn");
            }
            
            // Add payment record
            $thanhtoanSql = "INSERT INTO thanhtoan (idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich) 
                           VALUES (?, ?, 'Chuyển khoản', 'completed', NOW(), ?)";
            $this->db->tuychinh($thanhtoanSql, [$idDH, $amount, $transactionNo]);
            
            // Kiểm tra xem dữ liệu đã được thêm vào chưa
            $checkInsertSql = "SELECT idThanhToan FROM thanhtoan WHERE idDH = ? AND MaGiaoDich = ? AND TrangThai = 'completed'";
            $checkResult = $this->db->xuatdulieu_prepared($checkInsertSql, [$idDH, $transactionNo]);
            if (empty($checkResult)) {
                error_log("Payment record may not have been inserted correctly. Trying again with explicit status.");
                // Thử lại với trạng thái rõ ràng
                $retryInsertSql = "INSERT INTO thanhtoan (idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich) 
                                  VALUES (?, ?, 'Chuyển khoản', 'completed', NOW(), ?)";
                $this->db->tuychinh($retryInsertSql, [$idDH, $amount, $transactionNo]);
            }
            
            // Log transaction in history
            $this->logTransaction($idDH, $amount, 'Chuyển khoản', 'Giao dịch thanh toán VNPay thành công', $transactionNo, $bankCode);
            
            // Set payment method in session
            $_SESSION['payment_method'] = 'vnpay';
            $_SESSION['payment_success'] = true;
            
            // Commit transaction
            $this->db->commit();
            error_log("Transaction committed successfully for VNPay payment of order #$idDH");
            
            return [
                'status' => true,
                'message' => 'Thanh toán VNPay thành công'
            ];
        } catch (Exception $e) {
            // Rollback on error
            $this->db->rollback();
            error_log("VNPay payment error: " . $e->getMessage());
            
            return [
                'status' => false,
                'message' => 'Lỗi: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Calculate the total amount of an order based on order details
     * 
     * @param int $idDH Order ID
     * @return float|false Total amount or false on error
     */
    private function calculateOrderTotal($idDH) {
        try {
            error_log("Calculating total amount for order #$idDH");
            
            $sql = "SELECT SUM(c.SoLuong * m.DonGia) as TotalAmount 
                    FROM chitietdonhang c 
                    JOIN monan m ON c.idmonan = m.idmonan 
                    WHERE c.idDH = ?";
            
            $result = $this->db->xuatdulieu_prepared($sql, [$idDH]);
            
            if (empty($result) || !isset($result[0]['TotalAmount'])) {
                error_log("No total amount could be calculated for order #$idDH");
                return false;
            }
            
            $totalAmount = floatval($result[0]['TotalAmount']);
            error_log("Calculated total amount for order #$idDH: $totalAmount");
            
            return $totalAmount;
        } catch (Exception $e) {
            error_log("Error calculating order total: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get order information by ID
     * 
     * @param int $idDH Order ID
     * @return array Order information
     */
    private function getOrderInfo($idDH) {
        $sql = "SELECT idKH, TongTien, TrangThai FROM donhang WHERE idDH = ?";
        $result = $this->db->xuatdulieu_prepared($sql, [$idDH]);
        
        return !empty($result) ? $result[0] : [];
    }
    
    /**
     * Update order status
     * 
     * @param int $idDH Order ID
     * @param string $status New status
     * @return bool Success or failure
     */
    private function updateOrderStatus($idDH, $status) {
        try {
            error_log("Updating order #$idDH status to '$status'");
            $sql = "UPDATE donhang SET TrangThai = ? WHERE idDH = ?";
            $result = $this->db->tuychinh($sql, [$status, $idDH]);
            
            if ($result === false) {
                error_log("Failed to update order #$idDH status");
                return false;
            }
            
            // Verify the update
            $checkSql = "SELECT TrangThai FROM donhang WHERE idDH = ?";
            $checkResult = $this->db->xuatdulieu_prepared($checkSql, [$idDH]);
            
            if (empty($checkResult) || $checkResult[0]['TrangThai'] !== $status) {
                error_log("Order status verification failed. Expected: $status, Got: " . 
                         (empty($checkResult) ? 'no result' : $checkResult[0]['TrangThai']));
                return false;
            }
            
            error_log("Successfully updated and verified order #$idDH status to '$status'");
            return true;
        } catch (Exception $e) {
            error_log("Error updating order status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save payment information
     * 
     * @param int $idDH Order ID
     * @param float $amount Payment amount
     * @param string $method Payment method
     * @param string $status Payment status
     * @param string $transactionNo Transaction number (optional)
     * @return bool Success or failure
     */
    private function savePaymentInfo($idDH, $amount, $method, $status, $transactionNo = '') {
        try {
            // Log thông tin
            error_log("savePaymentInfo: Saving payment for order #$idDH with amount $amount");
            
            // Kiểm tra xem bảng thanhtoan có tồn tại không
            $checkTableSql = "SHOW TABLES LIKE 'thanhtoan'";
            $tableExists = $this->db->xuatdulieu($checkTableSql);
            
            if (empty($tableExists)) {
                // Nếu bảng không tồn tại, tạo bảng mới
                $createTableSql = "CREATE TABLE IF NOT EXISTS thanhtoan (
                    idThanhToan INT AUTO_INCREMENT PRIMARY KEY,
                    idDH INT NOT NULL,
                    SoTien DECIMAL(10,2) NOT NULL,
                    PhuongThuc VARCHAR(50) NOT NULL,
                    TrangThai ENUM('pending','completed','failed') DEFAULT 'pending',
                    NgayThanhToan DATETIME DEFAULT CURRENT_TIMESTAMP,
                    MaGiaoDich VARCHAR(50) DEFAULT NULL,
                    INDEX(idDH)
                )";
                $this->db->tuychinh($createTableSql);
                error_log("savePaymentInfo: Created thanhtoan table");
            }
            
            // Đảm bảo trạng thái không bị trống
            if (empty($status)) {
                $status = 'completed';
                error_log("savePaymentInfo: Empty status detected, setting to 'completed'");
            }
            
            // Sử dụng cấu trúc bảng với idDH
            $sql = "INSERT INTO thanhtoan 
                    (idDH, SoTien, PhuongThuc, TrangThai, MaGiaoDich, NgayThanhToan) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $result = $this->db->tuychinh($sql, [
                $idDH,
                $amount,
                $method,
                $status,
                $transactionNo
            ]);
            
            if ($result === false) {
                error_log("savePaymentInfo: Failed to insert payment record");
                return false;
            }
            
            error_log("savePaymentInfo: Successfully saved payment info");
            return true;
        } catch (Exception $e) {
            error_log("Error saving payment info: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Create invoice
     * 
     * @param int $idKH Customer ID
     * @param int $idDH Order ID
     * @param float $amount Payment amount
     * @param string $paymentMethod Payment method
     * @return int|false Invoice ID on success, false on failure
     */
    private function createInvoice($idKH, $idDH, $amount, $paymentMethod) {
        try {
            // Check if invoice already exists
            $checkExistingSql = "SELECT idHD FROM hoadon WHERE idDH = ?";
            $existingInvoice = $this->db->xuatdulieu_prepared($checkExistingSql, [$idDH]);
            
            if (!empty($existingInvoice)) {
                error_log("Invoice already exists for order #$idDH");
                return $existingInvoice[0]['idHD'];
            }

            // Check if hoadon table exists
            $checkTableSql = "SHOW TABLES LIKE 'hoadon'";
            $tableExists = $this->db->xuatdulieu($checkTableSql);
            
            if (empty($tableExists)) {
                // Create table if it doesn't exist
                $createTableSql = "CREATE TABLE IF NOT EXISTS hoadon (
                    idHD INT AUTO_INCREMENT PRIMARY KEY,
                    idKH INT NOT NULL,
                    idDH INT NOT NULL,
                    Ngay DATETIME DEFAULT CURRENT_TIMESTAMP,
                    hinhthucthanhtoan VARCHAR(50) NOT NULL,
                    TongTien DECIMAL(10,2) NOT NULL,
                    INDEX(idDH)
                )";
                $this->db->tuychinh($createTableSql);
                error_log("createInvoice: Created hoadon table");
            }
            
            // Kiểm tra cấu trúc bảng hoadon trước khi thêm
            $checkColumnSql = "SHOW COLUMNS FROM hoadon LIKE 'idNV'";
            $columnResult = $this->db->xuatdulieu($checkColumnSql);
            $hasIdNVColumn = !empty($columnResult);
            
            error_log("createInvoice: Checking for idNV column. Exists: " . ($hasIdNVColumn ? 'Yes' : 'No'));
            
            // Get staff ID from session
            $idNV = isset($_SESSION['nhanvien_id']) ? $_SESSION['nhanvien_id'] : null;
            
            // Create the invoice
            if ($hasIdNVColumn && $idNV) {
                // Nếu có cột idNV và có giá trị idNV
                $sql = "INSERT INTO hoadon (idKH, idDH, Ngay, hinhthucthanhtoan, TongTien, idNV) 
                        VALUES (?, ?, NOW(), ?, ?, ?)";
                $params = [$idKH, $idDH, $paymentMethod, $amount, $idNV];
                error_log("createInvoice: Using SQL with idNV column");
            } else {
                // Nếu không có cột idNV hoặc không có giá trị idNV
                $sql = "INSERT INTO hoadon (idKH, idDH, Ngay, hinhthucthanhtoan, TongTien) 
                        VALUES (?, ?, NOW(), ?, ?)";
                $params = [$idKH, $idDH, $paymentMethod, $amount];
                error_log("createInvoice: Using SQL without idNV column");
            }
            
            $result = $this->db->tuychinh($sql, $params);
            
            if ($result === false) {
                $error = mysqli_error($this->db->conn);
                error_log("Failed to create invoice for order #$idDH. Error: $error");
                return false;
            }
            
            // Get the last insert ID
            $lastIdSql = "SELECT MAX(idHD) as lastId FROM hoadon WHERE idDH = ?";
            $lastIdResult = $this->db->xuatdulieu_prepared($lastIdSql, [$idDH]);
            
            if (empty($lastIdResult) || !isset($lastIdResult[0]['lastId'])) {
                error_log("Failed to get invoice ID for order #$idDH");
                return false;
            }
            
            $invoiceId = $lastIdResult[0]['lastId'];
            error_log("Successfully created invoice #$invoiceId for order #$idDH");
            
            return $invoiceId;
        } catch (Exception $e) {
            error_log("Error creating invoice: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * Add invoice details
     * 
     * @param int $idDH Order ID
     * @param int $idHD Invoice ID
     * @return bool Success or failure
     */
    private function addInvoiceDetails($idDH, $idHD) {
        try {
            $sql = "SELECT ma.idmonan, ctdh.SoLuong, ma.DonGia, (ctdh.SoLuong * ma.DonGia) as ThanhTien
                    FROM chitietdonhang ctdh 
                    JOIN monan ma ON ctdh.idmonan = ma.idmonan 
                    WHERE ctdh.idDH = ?";
            $monAnList = $this->db->xuatdulieu_prepared($sql, [$idDH]);
            
            if (empty($monAnList)) {
                error_log("No order details found for order #$idDH");
                return false;
            }
            
            $success = true;
            foreach ($monAnList as $monAn) {
                $sql = "INSERT INTO chitiethoadon (idHD, idmonan, soluong, thanhtien) 
                        VALUES (?, ?, ?, ?)";
                $result = $this->db->tuychinh($sql, [
                    $idHD,
                    $monAn['idmonan'],
                    $monAn['SoLuong'],
                    $monAn['ThanhTien']
                ]);
                
                if ($result === false) {
                    $success = false;
                    break;
                }
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("Error adding invoice details: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log transaction in history
     * 
     * @param int $idDH Order ID
     * @param float $amount Payment amount
     * @param string $method Payment method
     * @param string $note Transaction note
     * @param string $transactionNo Transaction number (for electronic payments)
     * @param string $bankCode Bank code (for electronic payments)
     */
    private function logTransaction($idDH, $amount, $method, $note, $transactionNo = '', $bankCode = '') {
        // Check if log_giaodich table exists
        $checkTableSql = "SHOW TABLES LIKE 'log_giaodich'";
        $tableExists = $this->db->xuatdulieu($checkTableSql);
        
        if (empty($tableExists)) {
            // Create table if it doesn't exist
            $createTableSql = "CREATE TABLE IF NOT EXISTS log_giaodich (
                id INT AUTO_INCREMENT PRIMARY KEY,
                idDH INT NOT NULL,
                SoTien DECIMAL(10,2) NOT NULL,
                PhuongThuc VARCHAR(50) NOT NULL,
                MaGiaoDich VARCHAR(100),
                MaNganHang VARCHAR(50),
                GhiChu TEXT,
                ThoiGian DATETIME DEFAULT CURRENT_TIMESTAMP,
                IDNhanVien INT,
                TrangThai VARCHAR(50) DEFAULT 'completed',
                INDEX(idDH)
            )";
            $this->db->tuychinh($createTableSql);
            error_log("logTransaction: Created log_giaodich table");
        }
        
        // Get staff ID from session if available
        $idNV = isset($_SESSION['nhanvien_id']) ? $_SESSION['nhanvien_id'] : null;
        
        $sql = "INSERT INTO log_giaodich (idDH, SoTien, PhuongThuc, MaGiaoDich, MaNganHang, GhiChu, IDNhanVien, TrangThai) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')";
        $result = $this->db->tuychinh($sql, [
            $idDH,
            $amount,
            $method,
            $transactionNo,
            $bankCode,
            $note,
            $idNV
        ]);
        
        // Kiểm tra xem đã thêm thành công chưa
        if ($result === false) {
            error_log("logTransaction: Failed to insert log record for order #$idDH");
            
            // Thử lại một lần nữa với câu lệnh đơn giản hơn
            $simpleSql = "INSERT INTO log_giaodich (idDH, SoTien, PhuongThuc, TrangThai, ThoiGian) 
                         VALUES (?, ?, ?, 'completed', NOW())";
            $this->db->tuychinh($simpleSql, [$idDH, $amount, $method]);
        } else {
            error_log("logTransaction: Successfully logged transaction for order #$idDH");
        }
    }
    
    /**
     * Get payment history for an order
     * 
     * @param int $idDH Order ID
     * @return array Payment history
     */
    public function getPaymentHistory($idDH) {
        $sql = "SELECT * FROM thanhtoan WHERE idDH = ? ORDER BY NgayThanhToan DESC";
        return $this->db->xuatdulieu_prepared($sql, [$idDH]);
    }
    
    /**
     * Check if an order has been paid
     * 
     * @param int $idDH Order ID
     * @return bool True if paid, false otherwise
     */
    public function isOrderPaid($idDH) {
        $sql = "SELECT TrangThai FROM donhang WHERE idDH = ?";
        $result = $this->db->xuatdulieu_prepared($sql, [$idDH]);
        
        return !empty($result) && $result[0]['TrangThai'] === 'Đã thanh toán';
    }
    
    /**
     * Check database connection
     */
    private function checkDatabaseConnection() {
        try {
            if (!isset($this->db) || !$this->db) {
                error_log("Database connection not initialized");
                throw new Exception("Không thể kết nối đến cơ sở dữ liệu");
            }
            
            // Test the connection
            $testSql = "SELECT 1";
            $result = $this->db->xuatdulieu($testSql);
            if (empty($result)) {
                error_log("Database connection test failed");
                throw new Exception("Kết nối cơ sở dữ liệu không hoạt động");
            }
            
            error_log("Database connection verified successfully");
            return true;
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw $e; // Re-throw exception to be caught by caller
        }
    }
}
?> 