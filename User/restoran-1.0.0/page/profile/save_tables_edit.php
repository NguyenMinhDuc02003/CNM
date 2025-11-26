<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../class/clsconnect.php';

try {
    if (!isset($_SESSION['khachhang_id'])) {
        echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập']);
        exit;
    }

    if (!isset($_SESSION['edit_mode']) || !$_SESSION['edit_mode']) {
        echo json_encode(['success' => false, 'message' => 'Không phải chế độ chỉnh sửa']);
        exit;
    }

    $madatban = (int)($_POST['madatban'] ?? 0);
    $maban_json = $_POST['maban'] ?? '[]';
    $selected_tables_data = json_decode($maban_json, true);
    
    // Debug log
    error_log("Debug save_tables_edit: madatban=$madatban, maban_json=$maban_json");
    error_log("Debug selected_tables_data: " . print_r($selected_tables_data, true));
    
    // Lấy danh sách idban từ selected_tables
    $selected_tables = [];
    if (is_array($selected_tables_data)) {
        foreach ($selected_tables_data as $table) {
            if (isset($table['maban'])) {
                $selected_tables[] = (int)$table['maban'];
            }
        }
    }
    
    error_log("Debug final selected_tables: " . print_r($selected_tables, true));

    if ($madatban <= 0) {
        echo json_encode(['success' => false, 'message' => 'Mã đặt bàn không hợp lệ']);
        exit;
    }

    // Kiểm tra quyền sở hữu
    $db = new connect_db();
    $sql = "SELECT idKH, TrangThai, TongTien FROM datban WHERE madatban = ?";
    $booking = $db->xuatdulieu_prepared($sql, [$madatban]);
    
    if (empty($booking) || (int)$booking[0]['idKH'] !== (int)$_SESSION['khachhang_id']) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền sửa đặt bàn này']);
        exit;
    }
    // Kiểm tra trạng thái
    if ($booking[0]['TrangThai'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Chỉ có thể chỉnh sửa khi đơn hàng đang chờ xác nhận']);
        exit;
    }

    // Lấy thông tin chi tiết bàn để tính sức chứa
    $table_details = [];
    if (!empty($selected_tables)) {
        $placeholders = implode(',', array_fill(0, count($selected_tables), '?'));
        $table_rows = $db->xuatdulieu_prepared(
            "SELECT idban, SoBan, soluongKH, kv.PhuThu, kv.MaKV 
             FROM ban b 
             JOIN khuvucban kv ON b.MaKV = kv.MaKV 
             WHERE idban IN ($placeholders)",
            $selected_tables
        );
        foreach ($table_rows as $row) {
            $table_details[] = [
                'idban' => (int)$row['idban'],
                'soban' => $row['SoBan'],
                'capacity' => (int)$row['soluongKH'],
                'phuthu' => (int)$row['PhuThu'],
                'makv' => $row['MaKV']
            ];
        }
    }

    $people_count = 0;
    foreach ($table_details as $detail) {
        $people_count += $detail['capacity'];
    }

    // Bắt đầu transaction
    $db->beginTransaction();

    try {
        // Xóa bàn cũ
        $sql = "DELETE FROM chitiet_ban_datban WHERE madatban = ?";
        $db->tuychinh($sql, [$madatban]);

        // Thêm bàn mới
        if (!empty($table_details)) {
            $insert_sql = "INSERT INTO chitiet_ban_datban (madatban, idban, phuthu) VALUES (?, ?, ?)";
            foreach ($table_details as $table) {
                error_log("Debug: Inserting table idban={$table['idban']} for madatban=$madatban with phuthu={$table['phuthu']}");
                $result = $db->tuychinh($insert_sql, [$madatban, $table['idban'], $table['phuthu']]);
                if (!$result) {
                    error_log("Debug: Failed to insert table idban={$table['idban']}");
                    throw new Exception("Không thể thêm bàn {$table['idban']} vào đặt bàn");
                }
            }
        }

        // Tính lại tổng tiền
        $sql = "SELECT SUM(kv.PhuThu) as total_surcharge 
                FROM chitiet_ban_datban cbd 
                JOIN ban b ON cbd.idban = b.idban 
                JOIN khuvucban kv ON b.MaKV = kv.MaKV 
                WHERE cbd.madatban = ?";
        $surcharge_result = $db->xuatdulieu_prepared($sql, [$madatban]);
        $total_surcharge = isset($surcharge_result[0]['total_surcharge']) ? (int)$surcharge_result[0]['total_surcharge'] : 0;

        // Lấy tổng tiền món ăn
        $sql = "SELECT SUM(SoLuong * DonGia) as total_food 
                FROM chitietdatban 
                WHERE madatban = ?";
        $food_result = $db->xuatdulieu_prepared($sql, [$madatban]);
        $total_food = isset($food_result[0]['total_food']) ? (int)$food_result[0]['total_food'] : 0;

        $total_amount = (int)($total_surcharge + $total_food);

        // Cập nhật tổng tiền
        $sql = "UPDATE datban SET TongTien = ?, SoLuongKhach = ? WHERE madatban = ?";
        $db->tuychinh($sql, [$total_amount, max($people_count, 1), $madatban]);
        
        $db->commit();

        // Xóa session edit mode
        unset($_SESSION['edit_mode']);
        unset($_SESSION['madatban']);
        unset($_SESSION['selected_tables']);

        echo json_encode([
            'success' => true, 
            'message' => 'Cập nhật bàn thành công',
            'total_amount' => $total_amount,
            'redirect' => 'index.php?page=profile#bookings'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}
?>
