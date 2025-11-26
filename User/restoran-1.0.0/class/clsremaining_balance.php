<?php
require_once 'clsconnect.php';

class remaining_balance {
    private $db;
    
    public function __construct() {
        $this->db = new connect_db();
    }
    
    /**
     * Tính số tiền còn lại cần thanh toán cho một đơn đặt bàn
     * @param int $madatban Mã đặt bàn
     * @return array Thông tin về số tiền đã thanh toán và còn lại
     */
    public function calculateRemainingBalance($madatban) {
        // Lấy tổng tiền đơn hàng
        $sql_total = "SELECT TongTien FROM datban WHERE madatban = ?";
        $result_total = $this->db->xuatdulieu_prepared($sql_total, [$madatban]);
        
        if (empty($result_total)) {
            return ['error' => 'Không tìm thấy đơn đặt bàn'];
        }
        
        $total_amount = (float)$result_total[0]['TongTien'];
        
        // Lấy tổng số tiền đã thanh toán (các giao dịch thành công)
        $sql_paid = "SELECT COALESCE(SUM(SoTien), 0) as total_paid 
                     FROM thanhtoan 
                     WHERE madatban = ? AND TrangThai = 'completed'";
        $result_paid = $this->db->xuatdulieu_prepared($sql_paid, [$madatban]);
        
        $total_paid = (float)$result_paid[0]['total_paid'];
        $remaining_amount = $total_amount - $total_paid;
        
        return [
            'madatban' => $madatban,
            'total_amount' => $total_amount,
            'total_paid' => $total_paid,
            'remaining_amount' => $remaining_amount,
            'is_fully_paid' => $remaining_amount <= 0,
            'deposit_percentage' => $total_amount > 0 ? round(($total_paid / $total_amount) * 100, 2) : 0
        ];
    }
    
    /**
     * Lấy lịch sử thanh toán của một đơn đặt bàn
     * @param int $madatban Mã đặt bàn
     * @return array Danh sách các giao dịch thanh toán
     */
    public function getPaymentHistory($madatban) {
        $sql = "SELECT 
                    SoTien,
                    PhuongThuc,
                    TrangThai,
                    NgayThanhToan,
                    MaGiaoDich
                FROM thanhtoan 
                WHERE madatban = ? 
                ORDER BY NgayThanhToan DESC";
        
        return $this->db->xuatdulieu_prepared($sql, [$madatban]);
    }
    
    /**
     * Thêm giao dịch thanh toán còn lại (khi khách đến nhà hàng)
     * @param int $madatban Mã đặt bàn
     * @param float $amount Số tiền thanh toán
     * @param string $method Phương thức thanh toán (cash, card, etc.)
     * @param string $transaction_id Mã giao dịch (có thể để trống)
     * @return bool True nếu thành công
     */
    public function addRemainingPayment($madatban, $amount, $method = 'cash', $transaction_id = '') {
        try {
            $sql = "INSERT INTO thanhtoan (madatban, idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)
                    VALUES (?, NULL, ?, ?, 'completed', NOW(), ?)";
            
            $this->db->tuychinh($sql, [$madatban, $amount, $method, $transaction_id]);
            return true;
        } catch (Exception $e) {
            error_log("Error adding remaining payment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kiểm tra xem đơn đặt bàn có cần thanh toán thêm không
     * @param int $madatban Mã đặt bàn
     * @return bool True nếu còn nợ tiền
     */
    public function hasOutstandingBalance($madatban) {
        $balance_info = $this->calculateRemainingBalance($madatban);
        return isset($balance_info['remaining_amount']) && $balance_info['remaining_amount'] > 0;
    }
    
    /**
     * Lấy thông tin chi tiết đơn đặt bàn kèm thông tin thanh toán
     * @param int $madatban Mã đặt bàn
     * @return array Thông tin đầy đủ
     */
    public function getBookingWithPaymentInfo($madatban) {
        // Lấy thông tin đặt bàn
        $sql_booking = "SELECT 
                            db.madatban,
                            db.tenKH,
                            db.email,
                            db.sodienthoai,
                            db.NgayDatBan,
                            db.SoLuongKhach,
                            db.TongTien,
                            db.TrangThai,
                            (SELECT GROUP_CONCAT(b.SoBan ORDER BY b.SoBan SEPARATOR ', ')
                             FROM chitiet_ban_datban ct
                             JOIN ban b ON b.idban = ct.idban
                             WHERE ct.madatban = db.madatban) AS danh_sach_ban
                        FROM datban db
                        WHERE db.madatban = ?";
        
        $booking_result = $this->db->xuatdulieu_prepared($sql_booking, [$madatban]);
        
        if (empty($booking_result)) {
            return ['error' => 'Không tìm thấy đơn đặt bàn'];
        }
        
        $booking_info = $booking_result[0];
        
        // Tính số tiền còn lại
        $balance_info = $this->calculateRemainingBalance($madatban);
        
        // Lấy lịch sử thanh toán
        $payment_history = $this->getPaymentHistory($madatban);
        
        return [
            'booking' => $booking_info,
            'balance' => $balance_info,
            'payment_history' => $payment_history
        ];
    }
}
?>