<?php
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../helpers/booking_qr.php';

$bookingId = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
$timestamp = $_GET['ts'] ?? '';
$sig = $_GET['sig'] ?? '';

if ($bookingId <= 0 || empty($timestamp) || empty($sig)) {
    http_response_code(400);
    echo '<h2>Thông tin không hợp lệ.</h2>';
    exit;
}

if (generate_booking_signature($bookingId, $timestamp) !== $sig) {
    http_response_code(403);
    echo '<h2>Mã xác thực không hợp lệ.</h2>';
    exit;
}

$db = new connect_db();
$sql = "SELECT
            db.madatban,
            db.tenKH,
            db.email,
            db.sodienthoai,
            db.NgayDatBan AS thoigian,
            db.SoLuongKhach AS soluongKH,
            db.TongTien AS tongtien,
            db.TrangThai,
            (SELECT COALESCE(SUM(ct.phuthu),0)
             FROM chitiet_ban_datban ct
             WHERE ct.madatban = db.madatban) AS tong_phuthu,
            (SELECT GROUP_CONCAT(b.SoBan ORDER BY b.SoBan SEPARATOR ', ')
             FROM chitiet_ban_datban ct
             JOIN ban b ON b.idban = ct.idban
             WHERE ct.madatban = db.madatban) AS danh_sach_ban,
            (SELECT GROUP_CONCAT(kv.TenKV ORDER BY kv.TenKV SEPARATOR ', ')
             FROM chitiet_ban_datban ct
             JOIN ban b ON b.idban = ct.idban
             JOIN khuvucban kv ON kv.MaKV = b.MaKV
             WHERE ct.madatban = db.madatban) AS khu_vuc
        FROM datban db
        WHERE db.madatban = ?";
$bookingResult = $db->xuatdulieu_prepared($sql, [$bookingId]);

if (empty($bookingResult)) {
    http_response_code(404);
    echo '<h2>Không tìm thấy thông tin đặt bàn.</h2>';
    exit;
}

$booking = $bookingResult[0];

if ($booking['thoigian'] != $timestamp) {
    http_response_code(403);
    echo '<h2>Mã xác thực không hợp lệ.</h2>';
    exit;
}

$menuItems = $db->xuatdulieu_prepared(
    "SELECT 
        ctdb.idmonan,
        ma.tenmonan,
        ctdb.SoLuong,
        ctdb.DonGia,
        (ctdb.SoLuong * ctdb.DonGia) AS thanhtien
     FROM chitietdatban ctdb
     JOIN monan ma ON ma.idmonan = ctdb.idmonan
     WHERE ctdb.madatban = ?
     ORDER BY ma.tenmonan",
    [$bookingId]
);

$totalFood = array_reduce($menuItems, function ($carry, $item) {
    return $carry + (int)$item['thanhtien'];
}, 0);
$tableTotal = (int)($booking['tongtien'] ?? 0);
$surchargeTotal = (int)($booking['tong_phuthu'] ?? 0);
$statusLabel = ucfirst($booking['TrangThai']);
$paymentRows = $db->xuatdulieu_prepared(
    "SELECT COALESCE(SUM(SoTien), 0) AS paid_amount
     FROM thanhtoan
     WHERE madatban = ? AND TrangThai = 'completed'",
    [$bookingId]
);
$depositPaid = (int)($paymentRows[0]['paid_amount'] ?? 0);
$remainingBalance = max(0, $tableTotal - $depositPaid);

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đặt bàn #<?php echo htmlspecialchars($bookingId); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fb; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .container-box { max-width: 720px; margin: 40px auto; background: #fff; border-radius: 18px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .header { background: linear-gradient(135deg, #a50064, #d60085); color: #fff; padding: 30px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .badge-status { background: rgba(255,255,255,0.2); padding: 6px 14px; border-radius: 999px; display: inline-block; }
        .content { padding: 30px; }
        .section-title { font-size: 20px; font-weight: 600; margin-bottom: 15px; color: #333; display: flex; align-items: center; gap: 10px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .info-table td:first-child { width: 40%; color: #777; font-weight: 600; }
        .menu-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .menu-table th, .menu-table td { padding: 12px 10px; border-bottom: 1px solid #f0f0f0; text-align: left; }
        .menu-table th { background: #fafafa; font-weight: 600; color: #555; }
        .summary-card { background: #f5f9ff; border-radius: 14px; padding: 20px; margin-top: 20px; border: 1px solid #e0ecff; }
        .footer { padding: 20px 30px 30px; text-align: center; color: #888; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container-box">
        <div class="header">
            <h1>Chi tiết đặt bàn #<?php echo htmlspecialchars($booking['madatban']); ?></h1>
            <div class="badge-status"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($statusLabel); ?></div>
        </div>
        <div class="content">
            <div>
                <div class="section-title"><i class="fas fa-user me-2"></i>Thông tin khách hàng</div>
                <table class="info-table">
                    <tr><td>Khách hàng</td><td><?php echo htmlspecialchars($booking['tenKH']); ?></td></tr>
                    <tr><td>Email</td><td><?php echo htmlspecialchars($booking['email']); ?></td></tr>
                    <tr><td>Số điện thoại</td><td><?php echo htmlspecialchars($booking['sodienthoai']); ?></td></tr>
                </table>
            </div>

            <div>
                <div class="section-title"><i class="fas fa-utensils me-2"></i>Thông tin đặt bàn</div>
                <table class="info-table">
                    <tr><td>Bàn đã chọn</td><td><?php echo htmlspecialchars($booking['danh_sach_ban']); ?></td></tr>
                    <tr><td>Khu vực</td><td><?php echo htmlspecialchars($booking['khu_vuc']); ?></td></tr>
                    <tr><td>Thời gian</td><td><?php echo date('d/m/Y H:i', strtotime($booking['thoigian'])); ?></td></tr>
                    <tr><td>Số lượng khách</td><td><?php echo (int)$booking['soluongKH']; ?> người</td></tr>
                </table>
            </div>

            <div>
                <div class="section-title"><i class="fas fa-list-alt me-2"></i>Món ăn đã đặt</div>
                <?php if (!empty($menuItems)): ?>
                    <table class="menu-table">
                        <thead>
                            <tr>
                                <th>Món ăn</th>
                                <th>Số lượng</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($menuItems as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['tenmonan']); ?></td>
                                <td><?php echo (int)$item['SoLuong']; ?></td>
                                <td><?php echo number_format($item['DonGia']); ?> VND</td>
                                <td><?php echo number_format($item['thanhtien']); ?> VND</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">Hiện chưa có món ăn nào được chọn cho đơn đặt bàn này.</p>
                <?php endif; ?>
            </div>

            <div class="summary-card">
                <div class="section-title" style="margin-bottom: 10px;"><i class="fas fa-receipt me-2"></i>Tổng kết</div>
                <table class="info-table" style="margin-bottom: 0;">
                    <tr><td>Tổng tiền bàn + phụ thu</td><td><?php echo number_format($tableTotal, 0, ',', '.'); ?> VND</td></tr>
                    <tr><td>Phụ thu khu vực</td><td><?php echo number_format($surchargeTotal, 0, ',', '.'); ?> VND</td></tr>
                    <tr><td>Tổng tiền món ăn</td><td><?php echo number_format($totalFood, 0, ',', '.'); ?> VND</td></tr>
                    <tr><td>Đặt cọc đã thanh toán</td><td><?php echo number_format($depositPaid, 0, ',', '.'); ?> VND</td></tr>
                    <tr><td>Số tiền còn lại</td><td><?php echo number_format($remainingBalance, 0, ',', '.'); ?> VND</td></tr>
                    <tr><td>Ngày tạo QR</td><td><?php echo date('d/m/Y H:i'); ?></td></tr>
                </table>
            </div>
        </div>
        <div class="footer">
            <p>Nhà hàng Restoran — Cảm ơn bạn đã tin tưởng và đặt bàn!</p>
        </div>
    </div>
</body>
</html>



