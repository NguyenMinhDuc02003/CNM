<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['khachhang_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn cần đăng nhập để đánh giá đơn hàng.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ.']);
    exit;
}

$orderId = isset($_POST['idDH']) ? (int)$_POST['idDH'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

if ($orderId <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Thiếu thông tin đơn hàng hoặc số sao không hợp lệ.']);
    exit;
}

require_once __DIR__ . '/../../class/clsconnect.php';
$db = new connect_db();

if (!function_exists('ensureOrderRatingColumn')) {
    function ensureOrderRatingColumn(connect_db $db): bool
    {
        try {
            if (!$db->hasColumn('donhang', 'DanhGia')) {
                $db->executeRaw("ALTER TABLE donhang ADD COLUMN DanhGia TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Rating 1-5 sao từ khách hàng'");
            }
            return $db->hasColumn('donhang', 'DanhGia');
        } catch (Exception $e) {
            error_log('ensureOrderRatingColumn error: ' . $e->getMessage());
            try {
                return $db->hasColumn('donhang', 'DanhGia');
            } catch (Exception $inner) {
                return false;
            }
        }
    }
}

if (!ensureOrderRatingColumn($db)) {
    echo json_encode(['success' => false, 'message' => 'Không thể khởi tạo cột đánh giá. Vui lòng thử lại sau.']);
    exit;
}

$orderRows = $db->xuatdulieu_prepared(
    "SELECT d.idDH, d.TrangThai, d.DanhGia, b.idKH
     FROM donhang d
     JOIN datban b ON b.madatban = d.madatban
     WHERE d.idDH = ?
     LIMIT 1",
    [$orderId]
);

if (empty($orderRows)) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
    exit;
}

$order = $orderRows[0];
if ((int)$order['idKH'] !== (int)$_SESSION['khachhang_id']) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền đánh giá đơn hàng này.']);
    exit;
}

$allowedStatuses = ['hoan_thanh', 'completed'];
$currentStatus = $order['TrangThai'] ?? '';
if (!in_array($currentStatus, $allowedStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Chỉ đánh giá đơn hàng đã hoàn thành.']);
    exit;
}

$update = $db->tuychinh("UPDATE donhang SET DanhGia = ? WHERE idDH = ?", [$rating, $orderId]);

if ($update) {
    echo json_encode(['success' => true, 'rating' => $rating]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu đánh giá. Vui lòng thử lại.']);
}
