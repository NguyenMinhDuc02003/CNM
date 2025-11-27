<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$siteBase = '/CNM/User/restoran-1.0.0';

// Kiểm tra session
if (!isset($_SESSION['madatban'])) {
    $_SESSION['error'] = 'Không tìm thấy thông tin đặt bàn.';
    header('Location: ' . $siteBase . '/index.php?page=trangchu');
    exit;
}

require_once __DIR__ . '/../class/clsconnect.php';
require_once __DIR__ . '/../helpers/booking_qr.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Lấy thông tin đặt bàn
$db = new connect_db();
$madatban = (int)$_SESSION['madatban'];
$sql = "SELECT
  db.madatban,
  db.tenKH,
  db.email,
  db.sodienthoai,
  db.NgayDatBan AS thoigian,
  db.SoLuongKhach AS soluongKH,
  db.TongTien AS tongtien,
  (SELECT COALESCE(SUM(ct.phuthu),0)
   FROM chitiet_ban_datban ct
   WHERE ct.madatban = db.madatban) AS tong_phuthu,
  (SELECT GROUP_CONCAT(b.SoBan ORDER BY b.SoBan SEPARATOR ', ')
   FROM chitiet_ban_datban ct
   JOIN ban b ON b.idban = ct.idban
   WHERE ct.madatban = db.madatban) AS danh_sach_ban
FROM datban db
WHERE db.madatban = ?";
$result = $db->xuatdulieu_prepared($sql, [$_SESSION['madatban']]);

if (empty($result)) {
    $_SESSION['error'] = 'Không tìm thấy thông tin đặt bàn.';
    header('Location: ' . $siteBase . '/index.php?page=trangchu');
    exit;
}

$booking_info = $result[0];

// Tính toán tiền cọc và tiền còn lại
$total_amount = (int)$booking_info['tongtien'];
$required_deposit = (int)ceil($total_amount * 0.5);
$paymentHistory = $db->xuatdulieu_prepared(
    "SELECT COALESCE(SUM(SoTien), 0) AS paid_amount
     FROM thanhtoan
     WHERE madatban = ? AND TrangThai = 'completed'",
    [$booking_info['madatban']]
);
$deposit_amount = isset($paymentHistory[0]['paid_amount']) ? (int)$paymentHistory[0]['paid_amount'] : 0;
$remaining_amount = max(0, $total_amount - $deposit_amount);

// Lấy danh sách món ăn đã đặt
$menu_items = $db->xuatdulieu_prepared(
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
    [$madatban]
);

// Tạo QR thông tin đặt bàn
$qrUrl = build_booking_qr_url($booking_info['madatban'], $booking_info['thoigian']);
$qrCode = new \Endroid\QrCode\QrCode($qrUrl);
$qrCode->setSize(320)->setMargin(10);
$writer = new \Endroid\QrCode\Writer\PngWriter();
$qrResult = $writer->write($qrCode);
$qrBase64 = base64_encode($qrResult->getString());

// Xóa session sau khi hiển thị
unset($_SESSION['madatban'], $_SESSION['payment_method'], $_SESSION['payment_expires'], $_SESSION['payment_success']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán thành công - Restoran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 50px auto;
            max-width: 600px;
        }
        .success-header {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .success-body {
            padding: 40px;
        }
        .success-icon {
            font-size: 4rem;
            color: #27ae60;
            margin-bottom: 20px;
        }
        .booking-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #495057;
        }
        .info-value {
            color: #212529;
        }
        .amount-highlight {
            font-size: 1.5rem;
            font-weight: bold;
            color: #27ae60;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        .btn-outline-primary {
            border: 2px solid #3498db;
            color: #3498db;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-outline-primary:hover {
            background: #3498db;
            color: white;
        }
        .confirmation-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .next-steps {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #0d47a1;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .menu-section {
            background: #fdfdfd;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #f1f1f1;
        }
        .menu-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .menu-table th, .menu-table td {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
            text-align: left;
            font-size: 0.95rem;
        }
        .menu-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .qr-section {
            text-align: center;
            background: #fff;
            border: 1px solid #f1f1f1;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }
        .qr-section img {
            max-width: 240px;
            border: 8px solid #fff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
            border-radius: 16px;
        }
        .qr-caption {
            margin-top: 12px;
            font-size: 0.9rem;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="success-container">
            <!-- Header -->
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Thanh toán thành công!</h1>
                <p class="mb-0">Đơn đặt bàn của bạn đã được xác nhận</p>
            </div>

            <!-- Body -->
            <div class="success-body">
                <!-- Thông báo xác nhận -->
                <div class="confirmation-message">
                    <h5><i class="fas fa-info-circle me-2"></i>Xác nhận đặt bàn</h5>
                    <p class="mb-0">
                        Cảm ơn bạn đã đặt bàn tại nhà hàng chúng tôi. 
                        Chúng tôi sẽ gửi email xác nhận đến địa chỉ email của bạn.
                    </p>
                </div>

                <!-- Thông tin đặt bàn -->
                <div class="booking-info">
                    <h5><i class="fas fa-receipt me-2"></i>Chi tiết đặt bàn</h5>
                    <div class="info-item">
                        <span class="info-label">Mã đặt bàn:</span>
                        <span class="info-value fw-bold"><?php echo $booking_info['madatban']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Khách hàng:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking_info['tenKH']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking_info['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Số điện thoại:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking_info['sodienthoai']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Bàn:</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking_info['danh_sach_ban']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Thời gian:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking_info['thoigian'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Số người:</span>
                        <span class="info-value"><?php echo $booking_info['soluongKH']; ?> người</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tổng tiền đơn hàng:</span>
                        <span class="info-value"><?php echo number_format($total_amount); ?> VND</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Yêu cầu đặt cọc (50%):</span>
                        <span class="info-value"><?php echo number_format($required_deposit); ?> VND</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tổng tiền cọc đã thanh toán:</span>
                        <span class="info-value amount-highlight"><?php echo number_format($deposit_amount); ?> VND</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Số tiền còn lại:</span>
                        <span class="info-value" style="color: #dc3545; font-weight: bold;"><?php echo number_format($remaining_amount); ?> VND</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phương thức thanh toán:</span>
                        <span class="info-value">
                            <i class="fas fa-credit-card me-1"></i>MOMO (Đặt cọc)
                        </span>
                    </div>
                </div>

                <?php if (!empty($menu_items)): ?>
                <div class="menu-section">
                    <h5><i class="fas fa-utensils me-2"></i>Món ăn đã đặt</h5>
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
                            <?php foreach ($menu_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['tenmonan']); ?></td>
                                <td><?php echo (int)$item['SoLuong']; ?></td>
                                <td><?php echo number_format($item['DonGia']); ?> VND</td>
                                <td><?php echo number_format($item['thanhtien']); ?> VND</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="menu-section">
                    <h5><i class="fas fa-utensils me-2"></i>Món ăn đã đặt</h5>
                    <p class="text-muted mb-0">Hiện chưa có món ăn nào được chọn cho đơn đặt bàn này.</p>
                </div>
                <?php endif; ?>

                <div class="qr-section">
                    <h5><i class="fas fa-qrcode me-2"></i>Mã QR thông tin đặt bàn</h5>
                    <img src="data:image/png;base64,<?php echo $qrBase64; ?>" alt="QR thông tin đặt bàn">
                    <p class="qr-caption">Quét mã để xem nhanh thông tin bàn, thời gian và món ăn đã đặt. Một bản sao mã QR cũng đã được gửi qua email cho bạn.</p>
                    <a href="<?php echo htmlspecialchars($qrUrl); ?>" class="btn btn-primary mt-2" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt me-1"></i>Xem chi tiết đặt bàn
                    </a>
                </div>

                <!-- Thông báo về thanh toán -->
                <div class="next-steps">
                    <h5><i class="fas fa-info-circle me-2"></i>Thông tin thanh toán</h5>
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Lưu ý:</strong> Bạn đã thanh toán đủ khoản đặt cọc qua MoMo. 
                        Số tiền còn lại <strong><?php echo number_format($remaining_amount); ?> VND</strong> 
                        sẽ được thanh toán tại nhà hàng khi bạn đến dùng bữa.
                    </div>
                </div>

                <!-- Hướng dẫn tiếp theo -->
                <div class="next-steps">
                    <h5><i class="fas fa-list-ol me-2"></i>Những bước tiếp theo</h5>
                    <ul class="mb-0">
                        <li>Bạn sẽ nhận được email xác nhận trong vòng 5 phút</li>
                        <li>Vui lòng đến đúng giờ đã đặt bàn</li>
                        <li>Mang theo mã đặt bàn khi đến nhà hàng</li>
                        <li>Thanh toán số tiền còn lại tại nhà hàng</li>
                        <li>Liên hệ hotline nếu có thay đổi: <strong>0123 456 789</strong></li>
                    </ul>
                </div>

                <!-- Nút hành động -->
                <div class="text-center mt-4">
                    <a href="<?php echo $siteBase; ?>/index.php?page=trangchu" class="btn btn-primary me-3">
                        <i class="fas fa-home me-2"></i>Về trang chủ
                    </a>
                    <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>In hóa đơn
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script>
        // Tự động in sau 2 giây (tùy chọn)
        // setTimeout(() => {
        //     window.print();
        // }, 2000);
        if (window.sessionStorage) {
            sessionStorage.removeItem('pendingPaymentWatch');
        }
    </script>
</body>
</html>
