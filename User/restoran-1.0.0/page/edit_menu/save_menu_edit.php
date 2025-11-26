<?php
require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsdatban.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

try {
    $db = new connect_db();
    $datban = new datban($db);
    
    // Debug logging
    error_log("Save menu edit - Raw input: " . file_get_contents('php://input'));
    
    // Lấy dữ liệu từ POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log("Save menu edit - Parsed input: " . print_r($input, true));
    
    if (!$input || !isset($input['madatban']) || !isset($input['menu_type'])) {
        throw new Exception('Dữ liệu không hợp lệ');
    }
    
    $madatban = (int)$input['madatban'];
    $menu_type = $input['menu_type'];
    
    // Kiểm tra đặt bàn có tồn tại không
    $booking_info = $db->xuatdulieu_prepared("SELECT * FROM datban WHERE madatban = ?", [$madatban]);
    if (empty($booking_info)) {
        throw new Exception('Không tìm thấy thông tin đặt bàn');
    }
    $booking_info = $booking_info[0];
    if (($booking_info['TrangThai'] ?? '') !== 'pending') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Chỉ có thể chỉnh sửa khi đơn hàng đang chờ xác nhận'
        ]);
        exit;
    }
    // Bắt đầu transaction
    $db->beginTransaction();
    
    try {
        // Xóa tất cả món ăn cũ
        $db->xuatdulieu_prepared("DELETE FROM chitietdatban WHERE madatban = ?", [$madatban]);
        
        $totalPrice = 0;
        
        if ($menu_type === 'items' && isset($input['selected_items'])) {
            // Xử lý món lẻ
            foreach ($input['selected_items'] as $item) {
                $mamon = (int)$item['mamon'];
                $soluong = (int)$item['soluong'];
                
                if ($soluong > 0) {
                    // Lấy giá món ăn
                    $monan_info = $db->xuatdulieu_prepared("SELECT DonGia FROM monan WHERE idmonan = ?", [$mamon]);
                    if (!empty($monan_info)) {
                        $gia = (int)$monan_info[0]['DonGia'];
                        $totalPrice += $gia * $soluong;
                        
                        // Thêm vào chitietdatban
                        $db->xuatdulieu_prepared(
                            "INSERT INTO chitietdatban (madatban, idmonan, SoLuong, DonGia) VALUES (?, ?, ?, ?)",
                            [$madatban, $mamon, $soluong, $gia]
                        );
                    }
                }
            }
        } elseif ($menu_type === 'sets' && isset($input['selected_sets'])) {
            // Xử lý thực đơn
            foreach ($input['selected_sets'] as $set) {
                $idthucdon = (int)$set['mathucdon'];
                $soluong = (int)$set['soluong'];
                
                if ($soluong > 0) {
                    // Lấy giá thực đơn
                    $thucdon_info = $db->xuatdulieu_prepared("SELECT tongtien FROM thucdon WHERE idthucdon = ?", [$idthucdon]);
                    if (!empty($thucdon_info)) {
                        $gia = (int)$thucdon_info[0]['tongtien'];
                        $totalPrice += $gia * $soluong;
                        
                        // Lấy các món ăn trong thực đơn
                        $monan_in_thucdon = $db->xuatdulieu_prepared(
                            "SELECT m.idmonan, m.DonGia FROM chitietthucdon ct 
                             JOIN monan m ON ct.idmonan = m.idmonan 
                             WHERE ct.idthucdon = ? AND m.TrangThai = 'approved'",
                            [$idthucdon]
                        );
                        
                        error_log("Thực đơn ID $idthucdon có " . count($monan_in_thucdon) . " món ăn");
                        error_log("Danh sách món: " . print_r($monan_in_thucdon, true));
                        
                        // Thêm từng món ăn vào chitietdatban
                        foreach ($monan_in_thucdon as $mon) {
                            error_log("Inserting món: ID=" . $mon['idmonan'] . ", Số lượng=" . $soluong . ", Giá=" . $mon['DonGia']);
                            $db->xuatdulieu_prepared(
                                "INSERT INTO chitietdatban (madatban, idmonan, SoLuong, DonGia) VALUES (?, ?, ?, ?)",
                                [$madatban, (int)$mon['idmonan'], $soluong, (int)$mon['DonGia']]
                            );
                        }
                    }
                }
            }
        }
        
        // Lấy tổng phụ phí hiện tại
        $surcharge_data = $db->xuatdulieu_prepared(
            "SELECT COALESCE(SUM(kv.PhuThu), 0) AS total_surcharge
             FROM chitiet_ban_datban cbd
             JOIN ban b ON cbd.idban = b.idban
             JOIN khuvucban kv ON kv.MaKV = b.MaKV
             WHERE cbd.madatban = ?",
            [$madatban]
        );
        $totalSurcharge = isset($surcharge_data[0]['total_surcharge']) ? (int)$surcharge_data[0]['total_surcharge'] : 0;

        $newTotalAmount = (int)($totalPrice + $totalSurcharge);

        // Cập nhật tổng tiền trong bảng datban
        $db->xuatdulieu_prepared(
            "UPDATE datban SET TongTien = ? WHERE madatban = ?",
            [$newTotalAmount, $madatban]
        );
        
        $db->commit();
        $response = [
            'status' => 'success',
            'message' => 'Cập nhật món ăn thành công',
            'total_price' => $totalPrice,
            'total_amount' => $newTotalAmount
        ];

        error_log("Success response: " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    $error_response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
    
    error_log("Error response: " . json_encode($error_response));
    echo json_encode($error_response);
}
?>
