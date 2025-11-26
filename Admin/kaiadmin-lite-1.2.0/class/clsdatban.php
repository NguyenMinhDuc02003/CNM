<?php
require_once 'clsconnect.php';
class datban extends connect_db
{
    private static $adminMetaSchema = null;
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

    /**
     * Lấy danh sách bàn đã được giữ trong khung giờ bắt đầu tại thời điểm đặt và kết thúc sau đó 1 giờ.
     */
    public function getBanDaDat($maKhuVuc, $datetime, $excludeBookingId = null) {
        $db = new connect_db();
        
        // Chuyển đổi datetime thành timestamp
        $bookingTime = strtotime($datetime);
        $startTime = date('Y-m-d H:i:s', $bookingTime);
        $endTime = date('Y-m-d H:i:s', $bookingTime + 3600); // giữ bàn trong 1 giờ kể từ thời điểm đặt
        
        $sql = "SELECT DISTINCT cbd.idban 
                FROM datban d 
                JOIN chitiet_ban_datban cbd ON d.madatban = cbd.madatban 
                JOIN ban b ON cbd.idban = b.idban 
                WHERE b.makv = ? 
                AND d.NgayDatBan < ?
                AND DATE_ADD(d.NgayDatBan, INTERVAL 1 HOUR) > ?
                AND d.TrangThai IN ('pending','confirmed')";
        $params = [$maKhuVuc, $endTime, $startTime];
        if ($excludeBookingId !== null) {
            $sql .= " AND d.madatban <> ?";
            $params[] = (int)$excludeBookingId;
        }
                
        $result = $db->xuatdulieu_prepared($sql, $params);
        
        $dsBanDaDat = [];
        foreach ($result as $row) {
            $tableId = isset($row['idban']) ? (int)$row['idban'] : 0;
            if ($tableId > 0) {
                $dsBanDaDat[] = $tableId;
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

    public function checkAvailableTimeSlot($idban, $datetime, $excludeBookingId = null) {
        $db = new connect_db();
        
        // Chuyển đổi datetime thành timestamp
        $bookingTime = strtotime($datetime);
        $startTime = date('Y-m-d H:i:s', $bookingTime);
        $endTime = date('Y-m-d H:i:s', $bookingTime + 3600);
        
        // Kiểm tra xem có đặt bàn nào trong khoảng thời gian này không
        $sql = "SELECT d.madatban FROM datban d
                JOIN chitiet_ban_datban cbd ON d.madatban = cbd.madatban
                WHERE cbd.idban = ? 
                AND d.NgayDatBan < ?
                AND DATE_ADD(d.NgayDatBan, INTERVAL 1 HOUR) > ?
                AND d.TrangThai IN ('pending','confirmed')";

        $params = [$idban, $endTime, $startTime];
        if ($excludeBookingId !== null) {
            $sql .= " AND d.madatban <> ?";
            $params[] = $excludeBookingId;
        }
                
        $result = $db->xuatdulieu_prepared($sql, $params);
        
        if (empty($result)) {
            return true; // Không có đặt bàn nào trong khoảng thời gian này
        }
        
        return false; // Đã có đặt bàn trong khoảng thời gian này
    }

    public function getKhuVucInfo($maKhuVuc) {
        $sql = "SELECT MaKV, TenKV, PhuThu 
                FROM khuvucban 
                WHERE MaKV = ?";
        $rows = $this->xuatdulieu_prepared($sql, [(int)$maKhuVuc]);
        return !empty($rows) ? $rows[0] : null;
    }

    public function getAdminBookingSnapshot($madatban) {
        $bookingId = (int)$madatban;
        if ($bookingId <= 0) {
            return null;
        }

        $db = new connect_db();

        $bookingRows = $db->xuatdulieu_prepared(
            "SELECT madatban, idKH, NgayDatBan, SoLuongKhach, TongTien, TrangThai,
                    tenKH, email, sodienthoai, payment_expires, ThoiGianHetHan, NgayTao
             FROM datban
             WHERE madatban = ?
             LIMIT 1",
            [$bookingId]
        );
        if (empty($bookingRows)) {
            return null;
        }
        $booking = $bookingRows[0];

        $metaRows = $db->xuatdulieu_prepared(
            "SELECT booking_channel, payment_method, menu_mode, menu_snapshot, note, created_by
             FROM datban_admin_meta
             WHERE madatban = ?
             LIMIT 1",
            [$bookingId]
        );
        $meta = !empty($metaRows) ? $metaRows[0] : [];

        $tables = $db->xuatdulieu_prepared(
            "SELECT cbd.idban, b.SoBan, b.MaKV, b.soluongKH, cbd.phuthu, kv.PhuThu AS default_phuthu, kv.TenKV
             FROM chitiet_ban_datban cbd
             JOIN ban b ON cbd.idban = b.idban
             LEFT JOIN khuvucban kv ON b.MaKV = kv.MaKV
             WHERE cbd.madatban = ?
             ORDER BY b.SoBan",
            [$bookingId]
        );

        $menuItems = $db->xuatdulieu_prepared(
            "SELECT ct.idmonan, m.tenmonan, m.DonGia, ct.SoLuong
             FROM chitietdatban ct
             JOIN monan m ON ct.idmonan = m.idmonan
             WHERE ct.madatban = ?
             ORDER BY m.tenmonan",
            [$bookingId]
        );

        return [
            'booking' => $booking,
            'meta' => $meta,
            'tables' => $tables,
            'menu_items' => $menuItems,
        ];
    }

    public function createAdminBooking(array $payload) {
        $db = new connect_db();
        $db->beginTransaction();
        $this->lastErrorCode = null;

        try {
            if (!$this->ensureTablesAvailable($db, $payload['tables'] ?? [], $payload['datetime'] ?? null, null)) {
                $db->rollback();
                $this->lastErrorCode = 'table_conflict';
                return false;
            }

            $now = date('Y-m-d H:i:s');
            $columns = ['idKH', 'NgayDatBan', 'SoLuongKhach', 'TongTien', 'TrangThai', 'tenKH', 'email', 'sodienthoai', 'NgayTao'];
            $placeholders = array_fill(0, count($columns), '?');
            $params = [
                $payload['idKH'] ?? null,
                $payload['datetime'],
                (int)($payload['people'] ?? 0),
                (float)($payload['total_amount'] ?? 0),
                $payload['status'] ?? 'pending',
                $payload['tenKH'] ?? null,
                $payload['email'] ?? null,
                $payload['sodienthoai'] ?? null,
                $now
            ];

            $hasExpiryColumn = $db->hasColumn('datban', 'ThoiGianHetHan');
            $hasPaymentExpiresColumn = $db->hasColumn('datban', 'payment_expires');

            if ($hasExpiryColumn) {
                $columns[] = 'ThoiGianHetHan';
                $placeholders[] = '?';
                $params[] = $payload['expiry_hold'] ?? null;
            }

            if ($hasPaymentExpiresColumn) {
                $columns[] = 'payment_expires';
                $placeholders[] = '?';
                $params[] = $payload['payment_expires'] ?? null;
            }

            $insertSql = "INSERT INTO datban (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
            if (!$db->tuychinh($insertSql, $params)) {
                throw new Exception('Không thể lưu đơn đặt bàn.');
            }

            $madatban = $db->getLastInsertId();

            // Lưu chi tiết bàn
            if (!empty($payload['tables']) && is_array($payload['tables'])) {
                foreach ($payload['tables'] as $table) {
                    $tableId = (int)($table['idban'] ?? 0);
                    if ($tableId <= 0) {
                        continue;
                    }
                    $phuThu = isset($table['phuthu']) ? (float)$table['phuthu'] : 0;
                    $sqlTable = "INSERT INTO chitiet_ban_datban (madatban, idban, phuthu) VALUES (?, ?, ?)";
                    if (!$db->tuychinh($sqlTable, [$madatban, $tableId, $phuThu])) {
                        throw new Exception('Không thể lưu chi tiết bàn.');
                    }
                }
            }

            // Lưu chi tiết món ăn
            if (!empty($payload['menu_items']) && is_array($payload['menu_items'])) {
                foreach ($payload['menu_items'] as $item) {
                    $dishId = (int)($item['idmonan'] ?? 0);
                    $quantity = (int)($item['soluong'] ?? 0);
                    $price = isset($item['DonGia']) ? (float)$item['DonGia'] : 0;
                    if ($dishId <= 0 || $quantity <= 0) {
                        continue;
                    }
                    $sqlFood = "INSERT INTO chitietdatban (madatban, idmonan, SoLuong, DonGia) VALUES (?, ?, ?, ?)";
                    if (!$db->tuychinh($sqlFood, [$madatban, $dishId, $quantity, $price])) {
                        throw new Exception('Không thể lưu chi tiết món ăn.');
                    }
                }
            }

            // Lưu meta thông tin cho admin
            $hasMenuData = !empty($payload['menu_items']) || !empty($payload['menu_snapshot']) || (($payload['menu_mode'] ?? 'none') !== 'none');
            if (!empty($payload['booking_channel']) || !empty($payload['payment_method']) || !empty($payload['note']) || $hasMenuData) {
                $this->ensureAdminMetaTable();
                $metaColumns = ['madatban', 'booking_channel', 'payment_method', 'note', 'created_by'];
                $metaValues = [
                    $madatban,
                    $payload['booking_channel'] ?? 'user',
                    $payload['payment_method'] ?? 'cash',
                    $payload['note'] ?? null,
                    $payload['created_by'] ?? null
                ];

                if ((self::$adminMetaSchema['menu_mode'] ?? false)) {
                    $metaColumns[] = 'menu_mode';
                    $metaValues[] = $payload['menu_mode'] ?? 'none';
                }

                if ((self::$adminMetaSchema['menu_snapshot'] ?? false)) {
                    $metaColumns[] = 'menu_snapshot';
                    $metaValues[] = $payload['menu_snapshot'] ?? null;
                }

                $metaPlaceholders = implode(',', array_fill(0, count($metaColumns), '?'));
                $sqlMeta = "INSERT INTO datban_admin_meta (" . implode(',', $metaColumns) . ") VALUES ($metaPlaceholders)";
                if (!$db->tuychinh($sqlMeta, $metaValues)) {
                    throw new Exception('Không thể lưu metadata đơn đặt bàn.');
                }
            }

            $db->commit();
            return $madatban;
        } catch (Exception $e) {
            $db->rollback();
            error_log("createAdminBooking error: " . $e->getMessage());
            if ($this->lastErrorCode === null) {
                $this->lastErrorCode = 'create_failed';
            }
            return false;
        }
    }

    public function updateAdminBooking($madatban, array $payload) {
        $bookingId = (int)$madatban;
        if ($bookingId <= 0) {
            return false;
        }

        $db = new connect_db();
        $db->beginTransaction();
        $this->lastErrorCode = null;

        try {
            if (!$this->ensureTablesAvailable($db, $payload['tables'] ?? [], $payload['datetime'] ?? null, $bookingId)) {
                $db->rollback();
                $this->lastErrorCode = 'table_conflict';
                return false;
            }

            $hasExpiryColumn = $db->hasColumn('datban', 'ThoiGianHetHan');
            $hasPaymentExpiresColumn = $db->hasColumn('datban', 'payment_expires');

            $setParts = [
                'idKH = ?',
                'NgayDatBan = ?',
                'SoLuongKhach = ?',
                'TongTien = ?',
                'TrangThai = ?',
                'tenKH = ?',
                'email = ?',
                'sodienthoai = ?'
            ];
            $params = [
                $payload['idKH'] ?? null,
                $payload['datetime'],
                (int)($payload['people'] ?? 0),
                (float)($payload['total_amount'] ?? 0),
                $payload['status'] ?? 'pending',
                $payload['tenKH'] ?? null,
                $payload['email'] ?? null,
                $payload['sodienthoai'] ?? null
            ];

            if ($hasExpiryColumn) {
                $setParts[] = 'ThoiGianHetHan = ?';
                $params[] = $payload['expiry_hold'] ?? null;
            }

            if ($hasPaymentExpiresColumn) {
                $setParts[] = 'payment_expires = ?';
                $params[] = $payload['payment_expires'] ?? null;
            }

            $params[] = $bookingId;
            $sqlUpdate = "UPDATE datban SET " . implode(', ', $setParts) . " WHERE madatban = ?";
            if ($db->tuychinh($sqlUpdate, $params) === false) {
                throw new Exception('Không thể cập nhật đơn đặt bàn.');
            }

            $existingTables = $db->xuatdulieu_prepared(
                "SELECT idban FROM chitiet_ban_datban WHERE madatban = ?",
                [$bookingId]
            );
            $oldTableIds = [];
            foreach ($existingTables as $row) {
                $tid = isset($row['idban']) ? (int)$row['idban'] : 0;
                if ($tid > 0) {
                    $oldTableIds[] = $tid;
                }
            }

            $db->tuychinh("DELETE FROM chitiet_ban_datban WHERE madatban = ?", [$bookingId]);

            if (!empty($payload['tables']) && is_array($payload['tables'])) {
                foreach ($payload['tables'] as $table) {
                    $tableId = (int)($table['idban'] ?? 0);
                    if ($tableId <= 0) {
                        continue;
                    }
                    $phuThu = isset($table['phuthu']) ? (float)$table['phuthu'] : 0;
                    $db->tuychinh(
                        "INSERT INTO chitiet_ban_datban (madatban, idban, phuthu) VALUES (?, ?, ?)",
                        [$bookingId, $tableId, $phuThu]
                    );
                }
            }

            $db->tuychinh("DELETE FROM chitietdatban WHERE madatban = ?", [$bookingId]);
            if (!empty($payload['menu_items']) && is_array($payload['menu_items'])) {
                foreach ($payload['menu_items'] as $item) {
                    $dishId = (int)($item['idmonan'] ?? 0);
                    $quantity = (int)($item['soluong'] ?? 0);
                    $price = isset($item['DonGia']) ? (float)$item['DonGia'] : 0;
                    if ($dishId <= 0 || $quantity <= 0) {
                        continue;
                    }
                    $db->tuychinh(
                        "INSERT INTO chitietdatban (madatban, idmonan, SoLuong, DonGia) VALUES (?, ?, ?, ?)",
                        [$bookingId, $dishId, $quantity, $price]
                    );
                }
            }

            $this->ensureAdminMetaTable();
            $metaExists = $db->xuatdulieu_prepared(
                "SELECT id FROM datban_admin_meta WHERE madatban = ? LIMIT 1",
                [$bookingId]
            );

            if (!empty($metaExists)) {
                $metaParams = [
                    $payload['booking_channel'] ?? 'user',
                    $payload['payment_method'] ?? 'cash',
                    $payload['note'] ?? null,
                    $payload['created_by'] ?? null
                ];
                $set = "booking_channel = ?, payment_method = ?, note = ?, created_by = ?";
                if ((self::$adminMetaSchema['menu_mode'] ?? false)) {
                    $set .= ", menu_mode = ?";
                    $metaParams[] = $payload['menu_mode'] ?? 'none';
                }
                if ((self::$adminMetaSchema['menu_snapshot'] ?? false)) {
                    $set .= ", menu_snapshot = ?";
                    $metaParams[] = $payload['menu_snapshot'] ?? null;
                }
                $metaParams[] = $bookingId;
                if ($db->tuychinh("UPDATE datban_admin_meta SET $set WHERE madatban = ?", $metaParams) === false) {
                    throw new Exception('Không thể cập nhật metadata đơn đặt bàn.');
                }
            } else {
                $columns = ['madatban', 'booking_channel', 'payment_method', 'note', 'created_by'];
                $values = [
                    $bookingId,
                    $payload['booking_channel'] ?? 'user',
                    $payload['payment_method'] ?? 'cash',
                    $payload['note'] ?? null,
                    $payload['created_by'] ?? null
                ];
                if ((self::$adminMetaSchema['menu_mode'] ?? false)) {
                    $columns[] = 'menu_mode';
                    $values[] = $payload['menu_mode'] ?? 'none';
                }
                if ((self::$adminMetaSchema['menu_snapshot'] ?? false)) {
                    $columns[] = 'menu_snapshot';
                    $values[] = $payload['menu_snapshot'] ?? null;
                }
                $placeholders = implode(',', array_fill(0, count($columns), '?'));
                $sqlInsertMeta = "INSERT INTO datban_admin_meta (" . implode(',', $columns) . ") VALUES ($placeholders)";
                if ($db->tuychinh($sqlInsertMeta, $values) === false) {
                    throw new Exception('Không thể lưu metadata đơn đặt bàn.');
                }
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollback();
            error_log("updateAdminBooking error: " . $e->getMessage());
            if ($this->lastErrorCode === null) {
                $this->lastErrorCode = 'update_failed';
            }
            return false;
        }
    }

    public function deleteAdminBooking($madatban)
    {
        $db = new connect_db();
        $db->beginTransaction();

        try {
            $bookingId = (int)$madatban;
            if ($bookingId <= 0) {
                throw new Exception('Invalid booking ID');
            }

            $exists = $db->xuatdulieu_prepared(
                "SELECT madatban FROM datban WHERE madatban = ? LIMIT 1",
                [$bookingId]
            );
            if (empty($exists)) {
                throw new Exception('Booking not found');
            }

            $tableRows = $db->xuatdulieu_prepared(
                "SELECT idban FROM chitiet_ban_datban WHERE madatban = ?",
                [$bookingId]
            );
            $tableIds = [];
            foreach ($tableRows as $row) {
                $tid = isset($row['idban']) ? (int)$row['idban'] : 0;
                if ($tid > 0) {
                    $tableIds[] = $tid;
                }
            }

            if ($db->tuychinh("DELETE FROM datban WHERE madatban = ?", [$bookingId]) === false) {
                throw new Exception('Failed to delete booking');
            }

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollback();
            error_log("deleteAdminBooking error: " . $e->getMessage());
            return false;
        }
    }

    private function ensureTablesAvailable(connect_db $db, array $tables, $datetime, ?int $excludeBookingId = null)
    {
        $this->conflictingTableIds = [];
        if (empty($tables) || empty($datetime)) {
            return true;
        }

        $timestamp = strtotime($datetime);
        if ($timestamp === false) {
            return false;
        }

        $startTime = date('Y-m-d H:i:s', $timestamp);
        $endTime = date('Y-m-d H:i:s', $timestamp + 3600);

        foreach ($tables as $table) {
            $tableId = (int)($table['idban'] ?? 0);
            if ($tableId <= 0) {
                continue;
            }
            // Lock the table row to avoid race conditions during concurrent bookings
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
                return false;
            }
        }

        return true;
    }

    public function ensureAdminMetaTable()
    {
        static $created = false;
        if (!$created) {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS datban_admin_meta (
    id INT AUTO_INCREMENT PRIMARY KEY,
    madatban INT NOT NULL,
    booking_channel ENUM('user','walkin','phone') DEFAULT 'user',
    payment_method ENUM('cash','transfer') DEFAULT 'cash',
    note TEXT NULL,
    created_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_datban_admin_meta_booking FOREIGN KEY (madatban) REFERENCES datban(madatban) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

            $this->executeRaw($sql);
            $created = true;
        }

        $hasMenuMode = $this->hasColumn('datban_admin_meta', 'menu_mode');
        if (!$hasMenuMode) {
            $this->executeRaw("ALTER TABLE datban_admin_meta ADD COLUMN menu_mode ENUM('none','items','set') DEFAULT 'none' AFTER payment_method");
            $hasMenuMode = $this->hasColumn('datban_admin_meta', 'menu_mode');
        }

        $hasMenuSnapshot = $this->hasColumn('datban_admin_meta', 'menu_snapshot');
        if (!$hasMenuSnapshot) {
            $this->executeRaw("ALTER TABLE datban_admin_meta ADD COLUMN menu_snapshot LONGTEXT NULL AFTER note");
            $hasMenuSnapshot = $this->hasColumn('datban_admin_meta', 'menu_snapshot');
        }

        self::$adminMetaSchema = [
            'menu_mode' => $hasMenuMode,
            'menu_snapshot' => $hasMenuSnapshot
        ];

        return true;
    }
}
?>
