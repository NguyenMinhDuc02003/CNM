<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['khachhang_id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$madatban = isset($_POST['madatban']) ? (int)$_POST['madatban'] : 0;
if (!$madatban) {
    echo json_encode(['success' => false, 'message' => 'Thiếu mã đặt bàn']);
    exit;
}

require_once __DIR__ . '/../../class/clsconnect.php';
$db = new connect_db();

// Kiểm tra quyền sở hữu đặt bàn
$sql = "SELECT idKH, TrangThai FROM datban WHERE madatban = ?";
$result = $db->xuatdulieu_prepared($sql, [$madatban]);
if (empty($result)) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đặt bàn']);
    exit;
}
if ($result[0]['idKH'] != $_SESSION['khachhang_id']) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền hủy đặt bàn này']);
    exit;
}
// Nếu trạng thái hiện tại là 'canceled' (đã hủy) thì không làm gì
if (isset($result[0]['TrangThai']) && $result[0]['TrangThai'] === 'canceled') {
    echo json_encode(['success' => false, 'message' => 'Đặt bàn đã bị hủy trước đó']);
    exit;
}

// Cập nhật trạng thái sang 'canceled' (theo enum trong database)
$update = $db->tuychinh("UPDATE datban SET TrangThai = 'canceled' WHERE madatban = ?", [$madatban]);
if ($update) {
    echo json_encode(['success' => true, 'message' => 'Đã hủy đặt bàn thành công']);
} else {
    echo json_encode(['success' => false, 'message' => 'Có lỗi khi hủy đặt bàn']);
}
