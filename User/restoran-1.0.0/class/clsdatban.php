<?php
require_once 'clsconnect.php';
class datban extends connect_db
{
    private $lastErrorCode = null;
    private $conflictingTableIds = [];

    public function getLastErrorCode()
    {
        return $this->lastErrorCode;
    }

    public function getConflictingTableIds()
    {
        return $this->conflictingTableIds;
    }

    public function getBanDaDat($maKhuVuc, $datetime) {
        $db = new connect_db();
        
        // Chuyển đổi datetime thành timestamp
        $bookingTime = strtotime($datetime);
        $startTime = date('Y-m-d H:i:s', $bookingTime);
        $endTime = date('Y-m-d H:i:s', $bookingTime + 3600);
        
        $sql = "SELECT DISTINCT cbd.idban 
                FROM datban d 
                JOIN chitiet_ban_datban cbd ON d.madatban = cbd.madatban
                JOIN ban b ON cbd.idban = b.idban 
                WHERE b.makv = ? 
                AND d.NgayDatBan < ?
                AND DATE_ADD(d.NgayDatBan, INTERVAL 1 HOUR) > ?
                AND d.TrangThai IN ('pending','confirmed')";
                
        $result = $db->xuatdulieu_prepared($sql, [$maKhuVuc, $endTime, $startTime]);
        
        $dsBanDaDat = [];
        foreach ($result as $row) {
            $id = isset($row['idban']) ? (int)$row['idban'] : 0;
            if ($id > 0) {
                $dsBanDaDat[] = $id;
            }
        }
        
        return $dsBanDaDat;
    }
    
    public function getBanTheoKhuVuc($maKhuVuc) {
        $sql = "SELECT idban, SoBan, MaKV, soluongKH, COALESCE(zone, 'A') as zone, TrangThai 
                FROM ban 
                WHERE MaKV = ? 
                ORDER BY zone, SoBan";
        $params = [(int)$maKhuVuc];
        $result = $this->xuatdulieu_prepared($sql, $params);
        return is_array($result) ? $result : [];
    }

    public function checkAvailableTimeSlot($idban, $datetime) {
        $db = new connect_db();
        
        // Chuyển đổi datetime thành timestamp
        $bookingTime = strtotime($datetime);
        $startTime = date('Y-m-d H:i:s', $bookingTime);
        $endTime = date('Y-m-d H:i:s', $bookingTime + 3600);
        
        // Kiểm tra xem có đặt bàn nào trong khoảng thời gian này không
        $sql = "SELECT * FROM datban d
                JOIN chitiet_ban_datban cbd ON d.madatban = cbd.madatban
                WHERE cbd.idban = ? 
                AND d.NgayDatBan < ?
                AND DATE_ADD(d.NgayDatBan, INTERVAL 1 HOUR) > ?
                AND d.TrangThai IN ('pending','confirmed')";
                
        $result = $db->xuatdulieu_prepared($sql, [$idban, $endTime, $startTime]);
        
        if (empty($result)) {
            return true; // Không có đặt bàn nào trong khoảng thời gian này
        }
        
       return false; // Đã có đặt bàn trong khoảng thời gian này
   }
    
    // Thêm phương thức lưu thông tin đặt bàn với payment hold (hỗ trợ nhiều bàn)
    public function saveDatBanWithPaymentHold($tables, $ngayDatBan, $soLuongKhach, $tongTien, $tenKH, $email, $soDienThoai, $paymentExpires) {
        $db = new connect_db();
        $this->lastErrorCode = null;
        $this->conflictingTableIds = [];
        
        try {
            $db->beginTransaction();
            
            // Lấy thông tin khu vực từ bàn đầu tiên
            $idKhuVuc = $tables[0]['makv'];

            if (!$this->ensureTablesAvailable($db, $tables, $ngayDatBan, null)) {
                $db->rollback();
                $this->lastErrorCode = 'table_conflict';
                return false;
            }
            
            // Tạo mã đặt bàn unique
            $maDatBan = date('dmy-His') . '-' . rand(1000, 9999);
            
            // Lưu thông tin đặt bàn chính, bao gồm cả idKH nếu đã đăng nhập
            $idKH = isset($_SESSION['khachhang_id']) ? $_SESSION['khachhang_id'] : null;
            
            $sql = "INSERT INTO datban (idKH, NgayDatBan, SoLuongKhach, TongTien, TrangThai, tenKH, email, sodienthoai, NgayTao, payment_expires) 
                    VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, NOW(), ?)";
            
            $params = [$idKH, $ngayDatBan, $soLuongKhach, $tongTien, $tenKH, $email, $soDienThoai, $paymentExpires];
            
            $result = $db->tuychinh($sql, $params);
            
            if ($result) {
                $madatban = $db->getLastInsertId();
                
                // Lưu từng bàn vào bảng chi tiết riêng
                foreach ($tables as $table) {
                    $sqlTable = "INSERT INTO chitiet_ban_datban (madatban, idban, phuthu) VALUES (?, ?, ?)";
                    $db->tuychinh($sqlTable, [$madatban, $table['idban'], $table['phuthu']]);
                }
                
                // Lưu chi tiết món ăn nếu có
                if (isset($_SESSION['selected_monan']) && !empty($_SESSION['selected_monan'])) {
                    foreach ($_SESSION['selected_monan'] as $mon) {
                        $sqlDetail = "INSERT INTO chitietdatban (madatban, idmonan, SoLuong, DonGia) VALUES (?, ?, ?, ?)";
                        $db->tuychinh($sqlDetail, [$madatban, $mon['idmonan'], $mon['soluong'], $mon['DonGia']]);
                    }
                }

                // Ghi nhận menu snapshot để admin hiểu đây là đặt theo thực đơn hay món lẻ
                $menuMode = 'none';
                $menuSnapshot = null;
                if (isset($_SESSION['selected_thucdon']) && !empty($_SESSION['selected_thucdon'])) {
                    $menuMode = 'set';
                    $menuSnapshot = ['set' => $_SESSION['selected_thucdon']];
                } elseif (isset($_SESSION['selected_monan']) && !empty($_SESSION['selected_monan'])) {
                    $menuMode = 'items';
                    $menuSnapshot = ['items' => $_SESSION['selected_monan']];
                }

                if ($db->tableExists('datban_admin_meta')) {
                    // Chỉ chèn các cột tồn tại để tránh lỗi DB thiếu cột
                    $hasMenuMode = $db->hasColumn('datban_admin_meta', 'menu_mode');
                    $hasMenuSnapshot = $db->hasColumn('datban_admin_meta', 'menu_snapshot');
                    $hasPaymentMethod = $db->hasColumn('datban_admin_meta', 'payment_method');
                    $hasBookingChannel = $db->hasColumn('datban_admin_meta', 'booking_channel');

                    $cols = ['madatban'];
                    $vals = [$madatban];
                    $placeholders = ['?'];

                    if ($hasBookingChannel) { $cols[] = 'booking_channel'; $vals[] = 'user'; $placeholders[] = '?'; }
                    if ($hasPaymentMethod) { $cols[] = 'payment_method'; $vals[] = 'transfer'; $placeholders[] = '?'; }
                    if ($hasMenuMode) { $cols[] = 'menu_mode'; $vals[] = $menuMode; $placeholders[] = '?'; }
                    if ($hasMenuSnapshot) { $cols[] = 'menu_snapshot'; $vals[] = $menuSnapshot ? json_encode($menuSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null; $placeholders[] = '?'; }

                    $sqlMeta = "INSERT INTO datban_admin_meta (" . implode(',', $cols) . ") VALUES (" . implode(',', $placeholders) . ")";
                    $db->tuychinh($sqlMeta, $vals);
                }
                
                $db->commit();
                error_log("Successfully saved booking with payment hold. ID: $madatban");
                return $madatban;
            } else {
                throw new Exception("Failed to save booking");
            }
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error saving booking with payment hold: " . $e->getMessage());
            if ($this->lastErrorCode === null) {
                $this->lastErrorCode = 'save_failed';
            }
            return false;
        }
    }
    
    private function ensureTablesAvailable(connect_db $db, array $tables, $datetime, ?int $excludeBookingId = null)
    {
        if (empty($tables) || empty($datetime)) {
            return true;
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return false;
        }

        $startTime = date('Y-m-d H:i:s', $timestamp);
        $endTime = date('Y-m-d H:i:s', $timestamp + 3600);
        $this->conflictingTableIds = [];

        foreach ($tables as $table) {
            $tableId = (int)($table['idban'] ?? $table['maban'] ?? 0);
            if ($tableId <= 0) {
                continue;
            }
            // Lock the table row to avoid race conditions
            $db->xuatdulieu_prepared("SELECT idban FROM ban WHERE idban = ? LIMIT 1 FOR UPDATE", [$tableId]);

            $sql = "SELECT 1
                    FROM datban d
                    JOIN chitiet_ban_datban cbd ON d.madatban = cbd.madatban
                    WHERE cbd.idban = ?
                      AND d.NgayDatBan < ?
                      AND DATE_ADD(d.NgayDatBan, INTERVAL 1 HOUR) > ?
                      AND d.TrangThai IN ('pending','confirmed')";
            $params = [$tableId, $endTime, $startTime];
            if ($excludeBookingId !== null) {
                $sql .= " AND d.madatban <> ?";
                $params[] = $excludeBookingId;
            }
            $sql .= " LIMIT 1 FOR UPDATE";
            $conflicts = $db->xuatdulieu_prepared($sql, $params);
            if (!empty($conflicts)) {
                $this->conflictingTableIds[] = $tableId;
                $this->lastErrorCode = 'table_conflict';
                return false;
            }
        }

        return true;
    }
    
    public function assertTablesAvailable(array $tables, $datetime, ?int $excludeBookingId = null)
    {
        $db = new connect_db();
        $db->beginTransaction();
        $result = $this->ensureTablesAvailable($db, $tables, $datetime, $excludeBookingId);
        if ($result) {
            $db->commit();
        } else {
            $db->rollback();
            if ($this->lastErrorCode === null) {
                $this->lastErrorCode = 'table_conflict';
            }
        }
        return $result;
    }
    

}
?>
