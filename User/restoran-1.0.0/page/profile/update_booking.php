<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

require_once('../../class/clsconnect.php');
$db = new connect_db();

try {
    if (!isset($_SESSION['khachhang_id'])) {
        echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập']);
        exit;
    }

    // Nhận dữ liệu
    $madatban = isset($_POST['madatban']) ? (int)$_POST['madatban'] : 0;
    $email = trim($_POST['email'] ?? '');
    $tenKH = trim($_POST['tenKH'] ?? '');
    $sodienthoai = trim($_POST['sodienthoai'] ?? '');
    $ngaydatban = trim($_POST['ngaydatban'] ?? ''); // datetime-local

    if ($madatban <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mã đặt bàn không hợp lệ']);
        exit;
    }

    // Kiểm tra quyền sở hữu đơn đặt bàn
    $chk = $db->xuatdulieu_prepared("SELECT idKH, TrangThai, SoLuongKhach FROM datban WHERE madatban = ?", [$madatban]);
    if (empty($chk) || (int)$chk[0]['idKH'] !== (int)$_SESSION['khachhang_id']) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa đơn này']);
        exit;
    }

    // Ràng buộc trạng thái: cho phép sửa khi pending/confirmed
    $trangthai = $chk[0]['TrangThai'];
    if (!in_array($trangthai, ['pending', 'confirmed'])) {
        echo json_encode(['success' => false, 'message' => 'Đơn ở trạng thái không thể sửa']);
        exit;
    }

    // Lấy lại tổng số ghế của các bàn đã chọn
    $seatData = $db->xuatdulieu_prepared("
        SELECT COALESCE(SUM(b.soluongKH), 0) AS totalSeats
        FROM chitiet_ban_datban cbd
        JOIN ban b ON cbd.idban = b.idban
        WHERE cbd.madatban = ?
    ", [$madatban]);

    $soluongkhach = isset($seatData[0]['totalSeats']) ? (int)$seatData[0]['totalSeats'] : 0;
    if ($soluongkhach <= 0) {
        $soluongkhach = (int)$chk[0]['SoLuongKhach'];
    }

    if ($soluongkhach <= 0) {
        echo json_encode(['success' => false, 'message' => 'Không thể xác định số lượng khách của đơn đặt bàn']);
        exit;
    }

    // Chuẩn hóa thời gian (Y-m-d H:i:s)
    $ngaydatban_sql = date('Y-m-d H:i:00', strtotime($ngaydatban));

    // Cập nhật datban
    $sql = "UPDATE datban SET email = ?, sodienthoai = ?, NgayDatBan = ?, SoLuongKhach = ? WHERE madatban = ?";
    $ok = $db->tuychinh($sql, [$email, $sodienthoai, $ngaydatban_sql, $soluongkhach, $madatban]);
    
    // Cập nhật thông tin khách hàng nếu có thay đổi tên
    if (!empty($tenKH)) {
        $sql_kh = "UPDATE khachhang SET tenKH = ? WHERE idKH = ?";
        $db->tuychinh($sql_kh, [$tenKH, $_SESSION['khachhang_id']]);
        $_SESSION['khachhang_name'] = $tenKH;
    }

    if (!$ok) {
        echo json_encode(['success' => false, 'message' => 'Không thể cập nhật, vui lòng thử lại']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
