<?php
require_once 'clsconnect.php';

/**
 * Dine-in order lifecycle helper for admin panel.
 *
 * Handles opening tables, maintaining order items, kitchen statuses,
 * payment finalisation, and automatic inventory deductions (FIFO).
 */
class clsDonHang
{
    public const ORDER_STATUS_OPEN = 'dang_phuc_vu';
    public const ORDER_STATUS_PENDING_PAYMENT = 'cho_thanh_toan';
    public const ORDER_STATUS_DONE = 'hoan_thanh';
    public const ORDER_STATUS_CANCELLED = 'huy';

    public const ITEM_STATUS_PREPARING = 'preparing';
    public const ITEM_STATUS_READY = 'ready';
    public const ITEM_STATUS_SERVED = 'served';
    public const ITEM_STATUS_CANCELLED = 'cancelled';

    /**
     * @var connect_db
     */
    private $db;

    /**
     * Cached walk-in customer id.
     *
     * @var int|null
     */
    private $walkInCustomerId = null;

    /**
     * Allowed order statuses.
     *
     * @var string[]
     */
    private $allowedOrderStatuses = [
        self::ORDER_STATUS_OPEN,
        self::ORDER_STATUS_PENDING_PAYMENT,
        self::ORDER_STATUS_DONE,
        self::ORDER_STATUS_CANCELLED,
    ];

    /**
     * Allowed item statuses.
     *
     * @var string[]
     */
    private $allowedItemStatuses = [
        self::ITEM_STATUS_PREPARING,
        self::ITEM_STATUS_READY,
        self::ITEM_STATUS_SERVED,
        self::ITEM_STATUS_CANCELLED,
    ];

    public function __construct()
    {
        $this->db = new connect_db();
        $this->ensureSchema();
    }

    /**
     * Ensure supporting tables/columns exist.
     */
    private function ensureSchema(): void
    {
        $this->ensureOrderMetaTable();
        $this->ensureOrderItemColumns();
        $this->ensureInventoryConsumptionTable();
        $this->ensureItemStatusLogTable();
        $this->ensureInvoiceDetailColumns();
        $this->ensureTableQrColumns();
    }

    /**
     * Create / alter admin meta table for orders.
     */
    private function ensureOrderMetaTable(): void
    {
        $table = 'donhang_admin_meta';

        if (!$this->db->tableExists($table)) {
            $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `donhang_admin_meta` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `idDH` INT NOT NULL UNIQUE,
    `opened_by` INT NULL,
    `closed_by` INT NULL,
    `people_count` INT NULL,
    `surcharge` DECIMAL(10,2) DEFAULT 0,
    `area_name` VARCHAR(120) NULL,
    `table_label` VARCHAR(160) NULL,
    `booking_time` DATETIME NULL,
    `booking_reference` INT NULL,
    `note` TEXT NULL,
    `payment_method` ENUM('none','cash','transfer','mixed') DEFAULT 'none',
    `subtotal_amount` DECIMAL(12,2) DEFAULT 0,
    `total_amount` DECIMAL(12,2) DEFAULT 0,
    `opened_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `closed_at` DATETIME NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_donhang_meta_order` FOREIGN KEY (`idDH`) REFERENCES `donhang`(`idDH`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            $this->db->executeRaw($sql);
        } else {
            $this->ensureColumn($table, 'people_count', "ADD COLUMN `people_count` INT NULL AFTER `closed_by`");
            $this->ensureColumn($table, 'surcharge', "ADD COLUMN `surcharge` DECIMAL(10,2) DEFAULT 0 AFTER `people_count`");
            $this->ensureColumn($table, 'area_name', "ADD COLUMN `area_name` VARCHAR(120) NULL AFTER `surcharge`");
            $this->ensureColumn($table, 'table_label', "ADD COLUMN `table_label` VARCHAR(160) NULL AFTER `area_name`");
            // Add column to persist explicit table id list for multi-table orders
            $this->ensureColumn($table, 'table_ids', "ADD COLUMN `table_ids` LONGTEXT NULL AFTER `table_label`");
            $this->ensureColumn($table, 'booking_time', "ADD COLUMN `booking_time` DATETIME NULL AFTER `table_ids`");
            $this->ensureColumn($table, 'booking_reference', "ADD COLUMN `booking_reference` INT NULL AFTER `booking_time`");
            $this->ensureColumn($table, 'note', "ADD COLUMN `note` TEXT NULL AFTER `booking_reference`");
            $this->ensureColumn($table, 'payment_method', "ADD COLUMN `payment_method` ENUM('none','cash','transfer','mixed') DEFAULT 'none' AFTER `note`");
            $this->ensureColumn($table, 'subtotal_amount', "ADD COLUMN `subtotal_amount` DECIMAL(12,2) DEFAULT 0 AFTER `payment_method`");
            $this->ensureColumn($table, 'total_amount', "ADD COLUMN `total_amount` DECIMAL(12,2) DEFAULT 0 AFTER `subtotal_amount`");
            $this->ensureColumn($table, 'updated_at', "ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `total_amount`");
        }
    }

    /**
     * Ensure necessary columns exist on chitietdonhang table.
     */
    private function ensureOrderItemColumns(): void
    {
        $table = 'chitietdonhang';

        $this->ensureColumn($table, 'DonGia', "ADD COLUMN `DonGia` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `SoLuong`");
        $this->ensureColumn($table, 'TrangThai', "ADD COLUMN `TrangThai` ENUM('preparing','ready','served','cancelled') DEFAULT 'preparing' AFTER `DonGia`");
        $this->ensureColumn($table, 'GhiChu', "ADD COLUMN `GhiChu` TEXT NULL AFTER `TrangThai`");
        $this->ensureColumn($table, 'metadata', "ADD COLUMN `metadata` LONGTEXT NULL AFTER `GhiChu`");
        $this->ensureColumn($table, 'sent_at', "ADD COLUMN `sent_at` DATETIME NULL AFTER `metadata`");
        $this->ensureColumn($table, 'completed_at', "ADD COLUMN `completed_at` DATETIME NULL AFTER `sent_at`");
        $this->ensureColumn($table, 'served_at', "ADD COLUMN `served_at` DATETIME NULL AFTER `completed_at`");
        $this->ensureColumn($table, 'created_at', "ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `served_at`");
        $this->ensureColumn($table, 'updated_at', "ADD COLUMN `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
    }

    /**
     * Ensure FIFO inventory consumption table exists.
     */
    private function ensureInventoryConsumptionTable(): void
    {
        $table = 'inventory_consumption_log';
        if ($this->db->tableExists($table)) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `inventory_consumption_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `idDH` INT NOT NULL,
    `idCTDH` INT NULL,
    `matonkho` INT NOT NULL,
    `maCTNK` INT NULL,
    `so_luong` DOUBLE NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_inventory_order` FOREIGN KEY (`idDH`) REFERENCES `donhang`(`idDH`) ON DELETE CASCADE,
    CONSTRAINT `fk_inventory_item` FOREIGN KEY (`idCTDH`) REFERENCES `chitietdonhang`(`idCTDH`) ON DELETE SET NULL,
    CONSTRAINT `fk_inventory_batch` FOREIGN KEY (`maCTNK`) REFERENCES `chitietnhapkho`(`maCTNK`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $this->db->executeRaw($sql);
    }

    /**
     * Status change audit table for order items.
     */
    private function ensureItemStatusLogTable(): void
    {
        $table = 'donhang_item_status_log';
        if ($this->db->tableExists($table)) {
            return;
        }

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `donhang_item_status_log` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `idCTDH` INT NOT NULL,
    `idDH` INT NOT NULL,
    `old_status` VARCHAR(32) NULL,
    `new_status` VARCHAR(32) NOT NULL,
    `changed_by` INT NULL,
    `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `source` ENUM('front','kitchen','system') DEFAULT 'system',
    CONSTRAINT `fk_item_log_item` FOREIGN KEY (`idCTDH`) REFERENCES `chitietdonhang`(`idCTDH`) ON DELETE CASCADE,
    CONSTRAINT `fk_item_log_order` FOREIGN KEY (`idDH`) REFERENCES `donhang`(`idDH`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $this->db->executeRaw($sql);
    }

    /**
     * Helper to add columns if missing.
     */
    private function ensureColumn(string $table, string $column, string $alterStatement): void
    {
        if ($this->db->hasColumn($table, $column)) {
            return;
        }

        $sql = sprintf("ALTER TABLE `%s` %s", $table, $alterStatement);
        $this->db->executeRaw($sql);
    }

    /**
     * Ensure QR token support for tables.
     */
    private function ensureTableQrColumns(): void
    {
        $table = 'ban';
        if (!$this->db->tableExists($table)) {
            return;
        }

        $this->ensureColumn($table, 'qr_token', "ADD COLUMN `qr_token` VARCHAR(64) NULL AFTER `zone`");
        $existingIndex = $this->db->xuatdulieu("SHOW INDEX FROM `ban` WHERE Key_name = 'uniq_qr_token'");
        if (empty($existingIndex)) {
            $this->db->executeRaw("ALTER TABLE `ban` ADD UNIQUE KEY `uniq_qr_token` (`qr_token`)");
        }
    }

    private function generateQrToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Return existing QR token or create a new one for a table.
     */
    public function getOrCreateTableQrToken(int $tableId): string
    {
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT qr_token FROM ban WHERE idban = ? LIMIT 1",
            [$tableId]
        );

        if (empty($rows)) {
            throw new InvalidArgumentException('Không tìm thấy bàn #' . $tableId);
        }

        $token = $rows[0]['qr_token'] ?? '';
        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->regenerateTableQrToken($tableId);
    }

    /**
     * Force-generate a new QR token for a table.
     */
    public function regenerateTableQrToken(int $tableId): string
    {
        $attempt = 0;
        do {
            $attempt++;
            $token = $this->generateQrToken();
            $conflict = $this->db->xuatdulieu_prepared(
                "SELECT idban FROM ban WHERE qr_token = ? LIMIT 1",
                [$token]
            );
        } while (!empty($conflict) && $attempt < 10);

        $this->db->tuychinh(
            "UPDATE ban SET qr_token = ? WHERE idban = ?",
            [$token, $tableId]
        );

        return $token;
    }

    /**
     * Find table by QR token.
     */
    public function getTableByQrToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $rows = $this->db->xuatdulieu_prepared(
            "SELECT * FROM ban WHERE qr_token = ? LIMIT 1",
            [$token]
        );

        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Ensure invoice detail table keeps metadata for virtual menu sets.
     */
    private function ensureInvoiceDetailColumns(): void
    {
        $table = 'chitiethoadon';
        if (!$this->db->tableExists($table)) {
            return;
        }

        $this->ensureColumn($table, 'item_name', "ADD COLUMN `item_name` VARCHAR(255) NULL AFTER `idmonan`");
        $this->ensureColumn($table, 'unit_price', "ADD COLUMN `unit_price` DECIMAL(10,2) NULL AFTER `item_name`");
        $this->ensureColumn($table, 'metadata', "ADD COLUMN `metadata` LONGTEXT NULL AFTER `thanhtien`");

        $rows = $this->db->xuatdulieu_prepared(
            "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1",
            [$table, 'idmonan']
        );

        if (!empty($rows) && strtoupper((string)($rows[0]['IS_NULLABLE'] ?? '')) !== 'YES') {
            $this->db->executeRaw("ALTER TABLE `{$table}` MODIFY `idmonan` INT NULL");
        }
    }

    /**
     * Return the default walk-in customer id (create lazily if missing).
     */
    public function ensureWalkInCustomer(): int
    {
        if ($this->walkInCustomerId !== null) {
            return $this->walkInCustomerId;
        }

        $email = 'walkin@restaurant.local';

        // Try to find existing walk-in customer
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT idKH FROM khachhang WHERE email = ? LIMIT 1",
            [$email]
        );

        if (!empty($rows) && isset($rows[0]['idKH'])) {
            $this->walkInCustomerId = (int)$rows[0]['idKH'];
            return $this->walkInCustomerId;
        }

        // Create a minimal walk-in customer record
        $res = $this->db->tuychinh(
            "INSERT INTO khachhang (tenKH, sodienthoai, email, password, ngaysinh, gioitinh) VALUES (?, ?, ?, ?, NULL, NULL)",
            ['Khách tại bàn', '', $email, '']
        );

        if ($res === false) {
            throw new RuntimeException('Không thể khởi tạo khách lẻ mặc định.');
        }

        $this->walkInCustomerId = (int)$this->db->getLastInsertId();
        return $this->walkInCustomerId;
    }

    /**
     * Tạo mã đơn hàng hiển thị.
     */
    private function generateOrderCode(int $tableId): string
    {
        $datePart = date('ymd');
        $timePart = date('His');
        $tablePart = str_pad((string)$tableId, 2, '0', STR_PAD_LEFT);
        $randomPart = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        return sprintf('DH-%s-%s-T%s-%s', $datePart, $timePart, $tablePart, $randomPart);
    }

    /**
     * Create invoice code.
     */
    private function generateInvoiceCode(int $orderId): string
    {
        return 'HD' . date('ymdHis') . sprintf('%04d', $orderId);
    }

    /**
     * Create payment reference.
     */
    private function generatePaymentRef(int $orderId): string
    {
        return 'PAY' . date('ymdHis') . sprintf('%04d', $orderId);
    }

    /**
     * Return open order for table (if any).
     */
    public function getOpenOrderForTable(int $tableId): ?array
    {
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT * FROM donhang WHERE idban = ? AND TrangThai IN (?, ?) ORDER BY idDH DESC LIMIT 1",
            [$tableId, self::ORDER_STATUS_OPEN, self::ORDER_STATUS_PENDING_PAYMENT]
        );

        if (!empty($rows)) {
            return $rows[0];
        }

        $fallbackRows = $this->db->xuatdulieu_prepared(
            "SELECT d.*, meta.table_ids
             FROM donhang d
             LEFT JOIN donhang_admin_meta meta ON d.idDH = meta.idDH
             WHERE d.TrangThai IN (?, ?)
               AND meta.table_ids IS NOT NULL
               AND meta.table_ids <> ''",
            [self::ORDER_STATUS_OPEN, self::ORDER_STATUS_PENDING_PAYMENT]
        );

        foreach ($fallbackRows as $row) {
            $tableIdsRaw = $row['table_ids'] ?? '';
            if (!is_string($tableIdsRaw) || $tableIdsRaw === '') {
                continue;
            }
            $decoded = json_decode($tableIdsRaw, true);
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $candidate) {
                if ((int)$candidate === $tableId) {
                    unset($row['table_ids']);
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * Open new order for table or reuse existing open order.
     */
    public function openOrderForTable(int $tableId, array $flow, ?int $staffId = null): int
    {
        $existing = $this->getOpenOrderForTable($tableId);
        if ($existing) {
            return (int)$existing['idDH'];
        }

        $this->db->beginTransaction();

        try {
            $customerId = $this->ensureWalkInCustomer();
            $now = date('Y-m-d H:i:s');
            $orderCode = $this->generateOrderCode($tableId);
            $invoiceHint = substr(str_replace(['-', ':'], '', $orderCode), -6);
            $bookingReference = isset($flow['booking']['source_booking_id']) ? (int)$flow['booking']['source_booking_id'] : null;

            $inserted = $this->db->tuychinh(
                "INSERT INTO donhang (idKH, idban, madatban, NgayDatHang, TongTien, TrangThai, MaDonHang, SoHoaDon) VALUES (?, ?, ?, ?, 0, ?, ?, ?)",
                [$customerId, $tableId, $bookingReference, $now, self::ORDER_STATUS_OPEN, $orderCode, $invoiceHint]
            );

            if ($inserted === false) {
                throw new RuntimeException('Không thể tạo đơn hàng mới.');
            }

            $orderId = (int)$this->db->getLastInsertId();

            // Mark table as occupied
            $this->db->tuychinh(
                "UPDATE ban SET TrangThai = 'occupied' WHERE idban = ?",
                [$tableId]
            );
            if ($bookingReference !== null && $bookingReference > 0) {
                $this->db->tuychinh(
                    "UPDATE datban
                     SET TrangThai = CASE WHEN TrangThai <> 'canceled' THEN 'confirmed' ELSE TrangThai END,
                         payment_expires = NULL
                     WHERE madatban = ?",
                    [$bookingReference]
                );
            }

            $people = isset($flow['booking']['people_count']) ? (int)$flow['booking']['people_count'] : null;
            $surcharge = isset($flow['booking']['total_surcharge']) ? (float)$flow['booking']['total_surcharge'] : 0;
            $areaName = $flow['area']['name'] ?? null;
            $tableLabel = $flow['booking']['table_label'] ?? null;
            $bookingTime = $flow['booking']['datetime'] ?? null;

            $this->db->tuychinh(
                "INSERT INTO donhang_admin_meta (idDH, opened_by, people_count, surcharge, area_name, table_label, table_ids, booking_time, booking_reference, opened_at, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $orderId,
                    $staffId,
                    $people,
                    $surcharge,
                    $areaName,
                    $tableLabel,
                    // persist table ids as JSON array for multi-table orders
                    isset($flow['booking']['tables']) && is_array($flow['booking']['tables']) ? json_encode(array_values(array_map(function ($t) { return isset($t['idban']) ? (int)$t['idban'] : 0; }, $flow['booking']['tables']))) : null,
                    $bookingTime,
                    $bookingReference,
                    $now,
                    $flow['note'] ?? null
                ]
            );

            $this->db->commit();
            return $orderId;
        } catch (Throwable $th) {
            $this->db->rollback();
            throw $th;
        }
    }

    /**
     * Fetch order with meta info.
     */
    public function getOrderById(int $orderId): ?array
    {
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT d.*, meta.people_count, meta.surcharge, meta.area_name, meta.table_label, meta.table_ids,
                    meta.booking_time, meta.booking_reference, meta.note, meta.payment_method, meta.subtotal_amount,
                    meta.total_amount, meta.opened_at, meta.closed_at, meta.opened_by, meta.closed_by
             FROM donhang d
             LEFT JOIN donhang_admin_meta meta ON d.idDH = meta.idDH
             WHERE d.idDH = ?
             LIMIT 1",
            [$orderId]
        );

        return !empty($rows) ? $rows[0] : null;
    }

    /**
     * Retrieve menu items attached to order.
     */
    public function getOrderItems(int $orderId): array
    {
        return $this->db->xuatdulieu_prepared(
            "SELECT ct.*, m.tenmonan, m.hinhanh, m.DonViTinh
             FROM chitietdonhang ct
             LEFT JOIN monan m ON ct.idmonan = m.idmonan
             WHERE ct.idDH = ?
             ORDER BY ct.created_at ASC, ct.idCTDH ASC",
            [$orderId]
        );
    }

    /**
     * Decode metadata blob for display helpers.
     */
    private function decodeItemMetadata($raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Build invoice display items (handles thực đơn ảo).
     */
    public function getInvoiceDisplayItems(int $orderId): array
    {
        $items = $this->getOrderItems($orderId);
        $results = [];

        foreach ($items as $item) {
            $status = $item['TrangThai'] ?? self::ITEM_STATUS_PREPARING;
            if ($status === self::ITEM_STATUS_CANCELLED) {
                continue;
            }
            $metadataRaw = $item['metadata'] ?? null;
            $metadata = $this->decodeItemMetadata($metadataRaw);
            $menuSetMeta = isset($metadata['menu_set']) && is_array($metadata['menu_set'])
                ? $metadata['menu_set']
                : null;
            $name = $menuSetMeta['name']
                ?? ($item['tenmonan'] ?? ('Món #' . ($item['idmonan'] ?? '')));
            $unit = $menuSetMeta ? 'Thực đơn' : ($item['DonViTinh'] ?? '');
            $unitPrice = isset($item['DonGia']) ? (float)$item['DonGia'] : 0.0;
            $quantity = isset($item['SoLuong']) ? (int)$item['SoLuong'] : 0;

            $results[] = [
                'idCTDH' => (int)($item['idCTDH'] ?? 0),
                'idmonan' => isset($item['idmonan']) ? (int)$item['idmonan'] : null,
                'name' => $name,
                'unit' => $unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => $unitPrice * $quantity,
                'metadata' => $metadata,
                'metadata_raw' => $metadataRaw,
                'note' => $item['GhiChu'] ?? null,
                'is_menu_set' => (bool)$menuSetMeta,
            ];
        }

        return $results;
    }

    /**
     * Add items to order in preparing status.
     *
     * @param int   $orderId
     * @param array $items [['idmonan'=>int,'soluong'=>int,'ghichu'=>string|null], ...]
     * @param int|null $staffId
     * @return array Summary of inserted items.
     */
    public function addItemsToOrder(int $orderId, array $items, ?int $staffId = null): array
    {
        if (empty($items)) {
            return ['inserted' => [], 'ignored' => []];
        }

        $validItems = [];
        $ignored = [];

        foreach ($items as $item) {
            $rawDishId = $item['idmonan'] ?? null;
            $dishId = is_numeric($rawDishId) ? (int)$rawDishId : 0;
            $qty = isset($item['soluong']) ? (int)$item['soluong'] : 0;
            $note = isset($item['ghichu']) ? trim((string)$item['ghichu']) : null;
            $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price'])
                ? (float)$item['unit_price']
                : null;
            $metadata = null;
            if (isset($item['metadata'])) {
                $metadata = trim((string)$item['metadata']);
                if ($metadata === '') {
                    $metadata = null;
                } elseif (mb_strlen($metadata) > 65000) {
                    $metadata = mb_substr($metadata, 0, 65000);
                }
            }

            $metadataStruct = null;
            if ($metadata !== null) {
                $decoded = json_decode($metadata, true);
                if (is_array($decoded)) {
                    $metadataStruct = $decoded;
                }
            }
            $isVirtualMenuSet = isset($metadataStruct['menu_set'])
                && is_array($metadataStruct['menu_set'])
                && !empty($metadataStruct['menu_set']['virtual_item']);

            if ($qty <= 0) {
                $ignored[] = $item;
                continue;
            }

            if ($dishId <= 0 && !$isVirtualMenuSet) {
                $ignored[] = $item;
                continue;
            }

            if ($isVirtualMenuSet && $unitPrice === null) {
                $unitPrice = 0.0;
            }

            $validItems[] = [
                'idmonan' => $isVirtualMenuSet ? null : $dishId,
                'original_idmonan' => $dishId,
                'soluong' => $qty,
                'ghichu' => $note,
                'metadata' => $metadata,
                'is_virtual' => $isVirtualMenuSet,
                'unit_price' => $unitPrice,
            ];
        }

        if (empty($validItems)) {
            return ['inserted' => [], 'ignored' => $ignored];
        }

        $dishIds = [];
        foreach ($validItems as $item) {
            if (!empty($item['idmonan'])) {
                $dishIds[] = (int)$item['idmonan'];
            }
        }
        $dishIds = array_values(array_unique($dishIds));
        $menuMap = [];
        if (!empty($dishIds)) {
            $placeholders = implode(',', array_fill(0, count($dishIds), '?'));
            $menuRows = $this->db->xuatdulieu_prepared(
                "SELECT idmonan, DonGia FROM monan WHERE idmonan IN ($placeholders)",
                $dishIds
            );

            foreach ($menuRows as $row) {
                $menuMap[(int)$row['idmonan']] = (float)$row['DonGia'];
            }
        }

        $this->db->beginTransaction();
        $insertedIds = [];

        try {
            foreach ($validItems as $item) {
                $dishId = $item['idmonan'];
                $isVirtual = !empty($item['is_virtual']);
                if (!$isVirtual && ($dishId === null || !isset($menuMap[$dishId]))) {
                    $ignored[] = $item;
                    continue;
                }

                $price = $isVirtual ? ($item['unit_price'] ?? 0.0) : $menuMap[$dishId];
                if (!$isVirtual && isset($item['unit_price']) && $item['unit_price'] !== null) {
                    $overridePrice = (float)$item['unit_price'];
                    if ($overridePrice >= 0) {
                        $price = $overridePrice;
                    }
                }
                if ($isVirtual && $price < 0) {
                    $price = 0.0;
                }
                $note = $item['ghichu'];
                $now = date('Y-m-d H:i:s');

                $result = $this->db->tuychinh(
                    "INSERT INTO chitietdonhang (idDH, idmonan, SoLuong, DonGia, TrangThai, GhiChu, metadata, sent_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $orderId,
                        $isVirtual ? null : $dishId,
                        $item['soluong'],
                        $price,
                        self::ITEM_STATUS_PREPARING,
                        $note,
                        $item['metadata'],
                        $now,
                        $now,
                        $now
                    ]
                );

                if ($result === false) {
                    throw new RuntimeException('Không thể thêm món vào đơn.');
                }

                $insertedIds[] = (int)$this->db->getLastInsertId();
            }

            $this->updateOrderTotals($orderId);
            $this->db->commit();
        } catch (Throwable $th) {
            $this->db->rollback();
            throw $th;
        }

        return ['inserted' => $insertedIds, 'ignored' => $ignored];
    }

    /**
     * Update status for an order item.
     */
    public function updateItemStatus(int $itemId, string $newStatus, ?int $staffId = null, string $source = 'front'): ?int
    {
        if (!in_array($newStatus, $this->allowedItemStatuses, true)) {
            throw new InvalidArgumentException('Trạng thái món không hợp lệ.');
        }

        $itemRows = $this->db->xuatdulieu_prepared(
            "SELECT idDH, TrangThai FROM chitietdonhang WHERE idCTDH = ? LIMIT 1",
            [$itemId]
        );

        if (empty($itemRows)) {
            return null;
        }

        $orderId = (int)$itemRows[0]['idDH'];
        $oldStatus = $itemRows[0]['TrangThai'];
        $now = date('Y-m-d H:i:s');

        $setParts = ['TrangThai = ?', 'updated_at = ?'];
        $params = [$newStatus, $now];

        if ($newStatus === self::ITEM_STATUS_READY) {
            $setParts[] = 'completed_at = COALESCE(completed_at, ?)';
            $params[] = $now;
        } elseif ($newStatus === self::ITEM_STATUS_SERVED) {
            $setParts[] = 'served_at = COALESCE(served_at, ?)';
            $params[] = $now;
        } elseif ($newStatus === self::ITEM_STATUS_PREPARING) {
            $setParts[] = 'sent_at = COALESCE(sent_at, ?)';
            $params[] = $now;
        }

        $params[] = $itemId;

        $sql = "UPDATE chitietdonhang SET " . implode(', ', $setParts) . " WHERE idCTDH = ?";
        $this->db->tuychinh($sql, $params);

        $this->db->tuychinh(
            "INSERT INTO donhang_item_status_log (idCTDH, idDH, old_status, new_status, changed_by, source, changed_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$itemId, $orderId, $oldStatus, $newStatus, $staffId, $source, $now]
        );

        $this->updateOrderTotals($orderId);
        return $orderId;
    }

    /**
     * Compute totals for order (subtotal + surcharge).
     */
    public function computeOrderTotals(int $orderId): array
    {
        $sumRows = $this->db->xuatdulieu_prepared(
            "SELECT SUM(SoLuong * DonGia) AS subtotal
             FROM chitietdonhang
             WHERE idDH = ? AND TrangThai <> 'cancelled'",
            [$orderId]
        );

        $subtotal = isset($sumRows[0]['subtotal']) ? (float)$sumRows[0]['subtotal'] : 0.0;

        $metaRows = $this->db->xuatdulieu_prepared(
            "SELECT surcharge FROM donhang_admin_meta WHERE idDH = ? LIMIT 1",
            [$orderId]
        );
        $surcharge = isset($metaRows[0]['surcharge']) ? (float)$metaRows[0]['surcharge'] : 0.0;

        $total = round($subtotal + $surcharge, 2);

        return [
            'subtotal' => $subtotal,
            'surcharge' => $surcharge,
            'total' => $total,
        ];
    }

    /**
     * Get booking deposit summary (paid/pending) for related booking.
     */
    public function getBookingDepositSummary(?int $bookingId, ?float $referenceTotal = null): array
    {
        $summary = [
            'booking_id' => ($bookingId && $bookingId > 0) ? (int)$bookingId : null,
            'required' => 0.0,
            'paid' => 0.0,
            'pending' => 0.0,
        ];

        if (!$summary['booking_id']) {
            if ($referenceTotal !== null) {
                $summary['required'] = ceil(max(0, $referenceTotal) * 0.5);
            }
            return $summary;
        }

        $bookingRows = $this->db->xuatdulieu_prepared(
            "SELECT TongTien FROM datban WHERE madatban = ? LIMIT 1",
            [$summary['booking_id']]
        );
        $bookingTotal = isset($bookingRows[0]['TongTien']) ? (float)$bookingRows[0]['TongTien'] : 0.0;
        if ($bookingTotal > 0) {
            $summary['required'] = ceil($bookingTotal * 0.5);
        } elseif ($referenceTotal !== null) {
            $summary['required'] = ceil(max(0, $referenceTotal) * 0.5);
        }

        $paymentRows = $this->db->xuatdulieu_prepared(
            "SELECT TrangThai, SUM(SoTien) AS tong
             FROM thanhtoan
             WHERE madatban = ?
             GROUP BY TrangThai",
            [$summary['booking_id']]
        );

        foreach ($paymentRows as $row) {
            $status = strtolower($row['TrangThai'] ?? '');
            $amount = isset($row['tong']) ? (float)$row['tong'] : 0.0;
            if ($status === 'completed') {
                $summary['paid'] += $amount;
            } elseif ($status === 'pending') {
                $summary['pending'] += $amount;
            }
        }

        return $summary;
    }

    /**
     * Sync totals to metadata + order table.
     */
    public function updateOrderTotals(int $orderId): void
    {
        $totals = $this->computeOrderTotals($orderId);

        $this->db->tuychinh(
            "UPDATE donhang SET TongTien = ? WHERE idDH = ?",
            [$totals['total'], $orderId]
        );

        $this->db->tuychinh(
            "UPDATE donhang_admin_meta
             SET subtotal_amount = ?, total_amount = ?, surcharge = ?, updated_at = NOW()
             WHERE idDH = ?",
            [$totals['subtotal'], $totals['total'], $totals['surcharge'], $orderId]
        );
    }

    /**
     * Check if invoice exists.
     */
    private function invoiceExists(int $orderId): bool
    {
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT idHD FROM hoadon WHERE idDH = ? LIMIT 1",
            [$orderId]
        );

        return !empty($rows);
    }

    /**
     * Check if payment record exists.
     */
    private function paymentExists(int $orderId): bool
    {
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT idThanhToan FROM thanhtoan WHERE idDH = ? AND TrangThai = 'completed' LIMIT 1",
            [$orderId]
        );

        return !empty($rows);
    }

    /**
     * Delete (or cancel) an order item.
     */
    public function deleteOrderItem(int $itemId, ?int $staffId = null): void
    {
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT idDH FROM chitietdonhang WHERE idCTDH = ? LIMIT 1",
            [$itemId]
        );
        if (empty($rows)) {
            return;
        }
        $orderId = (int)$rows[0]['idDH'];
        $this->db->tuychinh(
            "DELETE FROM chitietdonhang WHERE idCTDH = ?",
            [$itemId]
        );
        $this->updateOrderTotals($orderId);
    }

    /**
     * Get invoice id for order if exists.
     */
    private function getInvoiceId(int $orderId): ?int
    {
        $rows = $this->db->xuatdulieu_prepared(
            "SELECT idHD FROM hoadon WHERE idDH = ? LIMIT 1",
            [$orderId]
        );

        if (!empty($rows) && isset($rows[0]['idHD'])) {
            return (int)$rows[0]['idHD'];
        }
        return null;
    }

    /**
     * Ensure invoice details mirror current order items.
     */
    private function syncInvoiceDetails(int $invoiceId, int $orderId): void
    {
        $this->db->tuychinh("DELETE FROM chitiethoadon WHERE idHD = ?", [$invoiceId]);
        $items = $this->getInvoiceDisplayItems($orderId);
        foreach ($items as $item) {
            $this->db->tuychinh(
                "INSERT INTO chitiethoadon (idHD, idmonan, item_name, unit_price, soluong, thanhtien, metadata)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $invoiceId,
                    $item['idmonan'],
                    $item['name'],
                    $item['unit_price'],
                    $item['quantity'],
                    $item['total'],
                    $item['metadata_raw'] ?? null,
                ]
            );
        }
    }

    /**
     * Complete order: create invoice, record payment, release table, update inventory.
     */
    public function completeOrder(int $orderId, string $paymentMethod, ?int $staffId = null): array
    {
        $order = $this->getOrderById($orderId);
        if (!$order) {
            throw new InvalidArgumentException('Không tìm thấy đơn hàng.');
        }

        if ($order['TrangThai'] === self::ORDER_STATUS_DONE) {
            return ['totals' => $this->computeOrderTotals($orderId), 'inventory' => []];
        }

        if (!in_array($paymentMethod, ['cash', 'transfer', 'mixed'], true)) {
            throw new InvalidArgumentException('Phương thức thanh toán không hợp lệ.');
        }

        $totals = $this->computeOrderTotals($orderId);
        $now = date('Y-m-d H:i:s');
        $bookingId = isset($order['madatban']) ? (int)$order['madatban'] : 0;
        $bookingDeposit = $this->getBookingDepositSummary($bookingId ?: null, $totals['total']);
        $amountToCollect = max(0.0, round($totals['total'] - $bookingDeposit['paid'], 2));

        $this->db->beginTransaction();
        $invoiceId = $this->getInvoiceId($orderId);

        try {
            $this->db->tuychinh(
                "UPDATE donhang
                 SET TrangThai = ?, TongTien = ?
                 WHERE idDH = ?",
                [self::ORDER_STATUS_DONE, $totals['total'], $orderId]
            );

            $this->db->tuychinh(
                "UPDATE donhang_admin_meta
                 SET payment_method = ?, closed_by = ?, closed_at = ?, subtotal_amount = ?, total_amount = ?, surcharge = ?, updated_at = ?
                 WHERE idDH = ?",
                [$paymentMethod, $staffId, $now, $totals['subtotal'], $totals['total'], $totals['surcharge'], $now, $orderId]
            );

            if (!$invoiceId) {
                $invoiceCode = $this->generateInvoiceCode($orderId);
                $this->db->tuychinh(
                    "INSERT INTO hoadon (idKH, idDH, Ngay, hinhthucthanhtoan, TongTien)
                     VALUES (?, ?, ?, ?, ?)",
                    [
                        $order['idKH'],
                        $orderId,
                        $now,
                        $paymentMethod === 'cash' ? 'Tiền mặt' : 'Chuyển khoản',
                        $totals['total']
                    ]
                );
                $invoiceId = (int)$this->db->getLastInsertId();
            }

            if ($invoiceId) {
                $this->syncInvoiceDetails($invoiceId, $orderId);
            }

            if (!$this->paymentExists($orderId) && $amountToCollect > 0) {
                $paymentRef = $this->generatePaymentRef($orderId);
                $this->db->tuychinh(
                    "INSERT INTO thanhtoan (idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)
                     VALUES (?, ?, ?, 'completed', ?, ?)",
                    [$orderId, $amountToCollect, $paymentMethod, $now, $paymentRef]
                );
            }

            // Prefer explicit list of table ids stored in meta (table_ids JSON) for multi-table orders.
            $metaRows = $this->db->xuatdulieu_prepared(
                "SELECT table_ids FROM donhang_admin_meta WHERE idDH = ? LIMIT 1",
                [$orderId]
            );

            $released = false;
            if (!empty($metaRows) && !empty($metaRows[0]['table_ids'])) {
                $ids = json_decode($metaRows[0]['table_ids'], true);
                if (is_array($ids) && !empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $this->db->tuychinh(
                        "UPDATE ban SET TrangThai = 'empty' WHERE idban IN ($placeholders)",
                        $ids
                    );
                    $released = true;
                }
            }

            if (!$released) {
                $this->db->tuychinh(
                    "UPDATE ban SET TrangThai = 'empty' WHERE idban = ?",
                    [$order['idban']]
                );
            }

            $inventorySummary = $this->deductInventoryForOrder($orderId);

            if ($bookingId > 0) {
                $this->db->tuychinh(
                    "UPDATE datban
                     SET TrangThai = 'completed',
                         TongTien = ?,
                         payment_expires = NULL
                     WHERE madatban = ?",
                    [$totals['total'], $bookingId]
                );
            }

            $this->db->commit();
        } catch (Throwable $th) {
            $this->db->rollback();
            throw $th;
        }

        return [
            'totals' => $totals,
            'inventory' => $inventorySummary,
        ];
    }

    /**
     * Deduct inventory using FIFO for completed order.
     */
    public function deductInventoryForOrder(int $orderId): array
    {
        $items = $this->db->xuatdulieu_prepared(
            "SELECT idCTDH, idmonan, SoLuong
             FROM chitietdonhang
             WHERE idDH = ? AND TrangThai <> 'cancelled'",
            [$orderId]
        );

        if (empty($items)) {
            return [];
        }

        $dishQuantities = [];
        foreach ($items as $item) {
            $dishId = (int)$item['idmonan'];
            $dishQuantities[$dishId] = ($dishQuantities[$dishId] ?? 0) + (int)$item['SoLuong'];
        }

        $dishIds = array_keys($dishQuantities);
        $placeholders = implode(',', array_fill(0, count($dishIds), '?'));

        $recipeRows = $this->db->xuatdulieu_prepared(
            "SELECT idmonan, matonkho, dinhluong
             FROM thanhphan
             WHERE idmonan IN ($placeholders)",
            $dishIds
        );

        if (empty($recipeRows)) {
            return [];
        }

        $requiredByIngredient = [];
        foreach ($recipeRows as $row) {
            $dishId = (int)$row['idmonan'];
            if (!isset($dishQuantities[$dishId])) {
                continue;
            }
            $matonkho = (int)$row['matonkho'];
            $perServing = (float)$row['dinhluong'];
            $totalQty = $perServing * $dishQuantities[$dishId];
            $requiredByIngredient[$matonkho] = ($requiredByIngredient[$matonkho] ?? 0) + $totalQty;
        }

        if (empty($requiredByIngredient)) {
            return [];
        }

        $inventorySummary = [];
        foreach ($requiredByIngredient as $matonkho => $quantityNeeded) {
            $inventorySummary[] = $this->applyFifoConsumption($orderId, $matonkho, $quantityNeeded);
        }

        $this->updateOrderTotals($orderId);

        return $inventorySummary;
    }

    /**
     * Consume inventory batches using FIFO order.
     */
    private function applyFifoConsumption(int $orderId, int $matonkho, float $quantityNeeded): array
    {
        $batches = $this->db->xuatdulieu_prepared(
            "SELECT ctnk.maCTNK, ctnk.soluongthucte, nk.ngaynhap
             FROM chitietnhapkho ctnk
             JOIN nhapkho nk ON ctnk.manhapkho = nk.manhapkho
             WHERE ctnk.matonkho = ?
             ORDER BY nk.ngaynhap ASC, ctnk.maCTNK ASC",
            [$matonkho]
        );

        $remaining = $quantityNeeded;
        $consumed = 0.0;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $batchId = (int)$batch['maCTNK'];
            $totalBatch = (float)$batch['soluongthucte'];

            $usedRows = $this->db->xuatdulieu_prepared(
                "SELECT COALESCE(SUM(so_luong), 0) AS used
                 FROM inventory_consumption_log
                 WHERE maCTNK = ?",
                [$batchId]
            );
            $alreadyUsed = isset($usedRows[0]['used']) ? (float)$usedRows[0]['used'] : 0.0;
            $available = max(0.0, $totalBatch - $alreadyUsed);

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);

            $this->db->tuychinh(
                "INSERT INTO inventory_consumption_log (idDH, idCTDH, matonkho, maCTNK, so_luong)
                 VALUES (?, NULL, ?, ?, ?)",
                [$orderId, $matonkho, $batchId, $take]
            );

            $remaining -= $take;
            $consumed += $take;
        }

        if ($consumed > 0) {
            $this->db->tuychinh(
                "UPDATE tonkho SET soluong = GREATEST(0, soluong - ?) WHERE matonkho = ?",
                [ceil($consumed), $matonkho]
            );
        }

        $ingredientRows = $this->db->xuatdulieu_prepared(
            "SELECT tentonkho, DonViTinh FROM tonkho WHERE matonkho = ? LIMIT 1",
            [$matonkho]
        );
        $ingredientName = $ingredientRows[0]['tentonkho'] ?? null;
        $unit = $ingredientRows[0]['DonViTinh'] ?? null;

        return [
            'matonkho' => $matonkho,
            'name' => $ingredientName,
            'unit' => $unit,
            'required' => $quantityNeeded,
            'consumed' => $consumed,
            'shortage' => max(0.0, $remaining),
        ];
    }
}
