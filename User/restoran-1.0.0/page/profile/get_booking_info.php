<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['khachhang_id'])) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng đăng nhập']);
    exit;
}

require_once __DIR__ . '/../../class/clsconnect.php';

try {
    $db = new connect_db();
    $madatban = $_GET['madatban'] ?? '';
    
    if (empty($madatban)) {
        echo json_encode(['success' => false, 'message' => 'Mã đặt bàn không hợp lệ']);
        exit;
    }
    
    // Lấy thông tin đặt bàn
    $sql = "SELECT d.*, k.tenKH 
            FROM datban d 
            JOIN khachhang k ON d.idKH = k.idKH 
            WHERE d.madatban = ? AND d.idKH = ?";
    $result = $db->xuatdulieu_prepared($sql, [$madatban, $_SESSION['khachhang_id']]);
    
    if (empty($result)) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đặt bàn']);
        exit;
    }
    
    $booking = $result[0];
    
    echo json_encode([
        'success' => true,
        'booking' => [
            'madatban' => $booking['madatban'],
            'email' => $booking['email'],
            'tenKH' => $booking['tenKH'],
            'SoLuongKhach' => $booking['SoLuongKhach'],
            'sodienthoai' => $booking['sodienthoai'],
            'NgayDatBan' => $booking['NgayDatBan']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>