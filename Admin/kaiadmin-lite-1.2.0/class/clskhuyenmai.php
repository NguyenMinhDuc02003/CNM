<?php
require_once 'clsconnect.php';

class clsKhuyenMai extends connect_db {
    public function checkKhuyenMai($code) {
        $code = trim($code);
        $code = strtoupper($code); // Chuyển về chữ hoa để so sánh
        error_log('Mã truyền vào DB: [' . $code . ']');
        
        // Kiểm tra mã khuyến mãi tồn tại
        $sql = "SELECT * FROM khuyenmai WHERE UPPER(TRIM(MaKhuyenMai)) = ?";
        $result = $this->xuatdulieu_prepared($sql, [$code]);
        error_log('Kết quả truy vấn: ' . print_r($result, true));
        
        if (!$result) {
            error_log("Promo code not found: [$code]");
            return ['error' => 'Mã khuyến mãi không tồn tại'];
        }
        
        $promo = $result[0];
        
        // Kiểm tra trạng thái
        if ($promo['TrangThai'] !== 'active') {
            error_log("Promo code is inactive: " . $code);
            return ['error' => 'Mã khuyến mãi không còn hoạt động'];
        }
        
        // Kiểm tra thời gian
        $today = date('Y-m-d');
        if ($promo['NgayBatDau'] > $today) {
            error_log("Promo code not started yet: " . $code . ", start date: " . $promo['NgayBatDau']);
            return ['error' => 'Mã khuyến mãi chưa đến thời gian áp dụng'];
        }
        if ($promo['NgayKetThuc'] < $today) {
            error_log("Promo code expired: " . $code . ", end date: " . $promo['NgayKetThuc']);
            return ['error' => 'Mã khuyến mãi đã hết hạn'];
        }
        
        // Nếu tất cả điều kiện đều thỏa mãn
        error_log("Valid promo code found: " . $code);
        return $promo;
    }
}
?>