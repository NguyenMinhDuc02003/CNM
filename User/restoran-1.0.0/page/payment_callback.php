<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../class/clsconnect.php';
require_once __DIR__ . '/../class/clsdatban.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';
require_once __DIR__ . '/../helpers/booking_qr.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Cấu hình MoMo
$momoConfig = [
    'partnerCode' => 'MOMOBKUN20180529',
    'accessKey' => 'klm05TvNBzhg7h7j',
    'secretKey' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa'
];

function generateBookingQrImageFromUrl(string $qrUrl): string {
    $qrCode = QrCode::create($qrUrl)->setSize(320)->setMargin(10);
    $writer = new PngWriter();
    $result = $writer->write($qrCode);
    return $result->getString();
}

// Function gửi email xác nhận đặt cọc
function sendDepositConfirmationEmail($madatban, $db) {
    try {
        // Lấy thông tin đặt bàn chi tiết
        $sql = "SELECT 
                    db.madatban,
                    db.tenKH,
                    db.email,
                    db.sodienthoai,
                    db.NgayDatBan,
                    db.SoLuongKhach,
                    db.TongTien,
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
        
        $booking_result = $db->xuatdulieu_prepared($sql, [$madatban]);
        
        if (empty($booking_result)) {
            error_log("Không tìm thấy thông tin đặt bàn: " . $madatban);
            return false;
        }
        
        $booking = $booking_result[0];
        
        // Lấy thông tin món ăn đã đặt
        $sql_monan = "SELECT 
                        ctdb.idmonan,
                        ma.tenmonan,
                        ctdb.SoLuong,
                        ctdb.DonGia,
                        (ctdb.SoLuong * ctdb.DonGia) as thanhtien
                      FROM chitietdatban ctdb
                      JOIN monan ma ON ma.idmonan = ctdb.idmonan
                      WHERE ctdb.madatban = ?
                      ORDER BY ma.tenmonan";
        
        $monan_result = $db->xuatdulieu_prepared($sql_monan, [$madatban]);
        
        // Lấy thông tin thanh toán cọc
        $sql_payment = "SELECT SoTien, PhuongThuc, NgayThanhToan, MaGiaoDich
                        FROM thanhtoan 
                        WHERE madatban = ? AND TrangThai = 'completed'
                        ORDER BY NgayThanhToan DESC 
                        LIMIT 1";
        
        $payment_result = $db->xuatdulieu_prepared($sql_payment, [$madatban]);
        $payment = !empty($payment_result) ? $payment_result[0] : null;
        
        // Tính số tiền còn lại
        $total_amount = (float)$booking['TongTien'];
        $deposit_amount = $payment ? (float)$payment['SoTien'] : 0;
        $remaining_amount = $total_amount - $deposit_amount;

        $qrUrl = build_booking_qr_url($booking['madatban'], $booking['NgayDatBan']);
        // Tạo QR thông tin đặt bàn
        $qrImageBinary = generateBookingQrImageFromUrl($qrUrl);
        
        // Tạo nội dung email
        $emailContent = generateDepositEmailContent($booking, $monan_result, $payment, $total_amount, $deposit_amount, $remaining_amount, $qrUrl, true);
        
        // Gửi email
        $mail = new PHPMailer(true);
        
        // Cấu hình SMTP
        $mail->isSMTP();
        $mail->SMTPDebug = 2; // Bật debug để xem chi tiết
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer Debug [$level]: $str");
        };
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'nhom1.9a7.2018@gmail.com';
        $mail->Password = 'rwgt urjf wpfy iirg'; // App Password 16 ký tự từ Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        
        // Người gửi và người nhận
        $mail->setFrom('nhom1.9a7.2018@gmail.com', 'Nhà hàng Restoran');
        $mail->addAddress($booking['email'], $booking['tenKH']);

        // Đính kèm QR code
        if (!empty($qrImageBinary)) {
            $mail->addStringEmbeddedImage($qrImageBinary, 'bookingqr', 'booking_qr.png', 'base64', 'image/png');
            $mail->addStringAttachment($qrImageBinary, 'booking_qr.png', 'base64', 'image/png');
        }
        
        // Nội dung email
        $mail->isHTML(true);
        $mail->Subject = 'Xác nhận đặt cọc thành công - Mã đặt bàn: ' . $madatban;
        $mail->Body = $emailContent;
        
        // Debug: Log thông tin email trước khi gửi
        error_log("=== DEBUG EMAIL INFO ===");
        error_log("To: " . $booking['email']);
        error_log("From: nhom1.9a7.2018@gmail.com");
        error_log("Subject: Xác nhận đặt cọc thành công - Mã đặt bàn: " . $madatban);
        error_log("Customer Name: " . $booking['tenKH']);
        error_log("Total Amount: " . $total_amount);
        error_log("Deposit Amount: " . $deposit_amount);
        error_log("Remaining Amount: " . $remaining_amount);
        error_log("========================");
        
        // Gửi email
        try {
            $result = $mail->send();
            if ($result) {
                error_log("✅ Email xác nhận đặt cọc gửi THÀNH CÔNG đến: " . $booking['email']);
            } else {
                error_log("❌ Email xác nhận đặt cọc gửi THẤT BẠI đến: " . $booking['email']);
            }
            return $result;
        } catch (Exception $e) {
            error_log("❌ Lỗi khi gửi email: " . $e->getMessage());
            error_log("❌ Chi tiết lỗi: " . $e->getTraceAsString());
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Lỗi gửi email xác nhận đặt cọc: " . $e->getMessage());
        return false;
    }
}

// Function tạo nội dung email
function generateDepositEmailContent($booking, $monan_list, $payment, $total_amount, $deposit_amount, $remaining_amount, $qrUrl = '', $includeQr = false) {
    $html = '
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Xác nhận đặt cọc</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #a50064, #d60085); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .booking-info { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .amount-section { background: #e8f5e8; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .menu-section { background: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            .table th, .table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            .table th { background: #f8f9fa; font-weight: bold; }
            .highlight { color: #a50064; font-weight: bold; }
            .success-badge { background: #28a745; color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.9em; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; }
            .qr-box { text-align: center; margin: 25px 0; }
            .qr-box img { max-width: 220px; border: 8px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.12); border-radius: 12px; }
            .qr-caption { margin-top: 10px; font-size: 0.9em; color: #555; }
            .btn-link { display: inline-block; background: linear-gradient(135deg, #a50064, #d60085); color: #fff !important; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🍽️ Nhà hàng Restoran</h1>
                <h2>Xác nhận đặt cọc thành công!</h2>
                <p>Đơn đặt bàn của bạn đã được xác nhận</p>
            </div>
            
            <div class="content">
                <div class="booking-info">
                    <h3>📋 Thông tin đặt bàn</h3>
                    <table class="table">
                        <tr><td><strong>Mã đặt bàn:</strong></td><td class="highlight">#' . $booking['madatban'] . '</td></tr>
                        <tr><td><strong>Khách hàng:</strong></td><td>' . htmlspecialchars($booking['tenKH']) . '</td></tr>
                        <tr><td><strong>Email:</strong></td><td>' . htmlspecialchars($booking['email']) . '</td></tr>
                        <tr><td><strong>Số điện thoại:</strong></td><td>' . htmlspecialchars($booking['sodienthoai']) . '</td></tr>
                        <tr><td><strong>Thời gian:</strong></td><td>' . date('d/m/Y H:i', strtotime($booking['NgayDatBan'])) . '</td></tr>
                        <tr><td><strong>Số người:</strong></td><td>' . $booking['SoLuongKhach'] . ' người</td></tr>
                        <tr><td><strong>Bàn đã chọn:</strong></td><td>' . htmlspecialchars($booking['danh_sach_ban']) . '</td></tr>
                        <tr><td><strong>Khu vực:</strong></td><td>' . htmlspecialchars($booking['khu_vuc']) . '</td></tr>
                        <tr><td><strong>Trạng thái:</strong></td><td><span class="success-badge">Đã xác nhận</span></td></tr>
                    </table>
                </div>';
    
    // Thông tin món ăn nếu có
    if (!empty($monan_list)) {
        $html .= '
                <div class="menu-section">
                    <h3>🍴 Danh sách món ăn đã đặt</h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Món ăn</th>
                                <th>Số lượng</th>
                                <th>Đơn giá</th>
                                <th>Thành tiền</th>
                            </tr>
                        </thead>
                        <tbody>';
        
        $total_monan = 0;
        foreach ($monan_list as $mon) {
            $thanhtien = $mon['SoLuong'] * $mon['DonGia'];
            $total_monan += $thanhtien;
            $html .= '
                            <tr>
                                <td>' . htmlspecialchars($mon['tenmonan']) . '</td>
                                <td>' . $mon['SoLuong'] . '</td>
                                <td>' . number_format($mon['DonGia']) . ' VND</td>
                                <td>' . number_format($thanhtien) . ' VND</td>
                            </tr>';
        }
        
        $html .= '
                        </tbody>
                        <tfoot>
                            <tr style="background: #f8f9fa; font-weight: bold;">
                                <td colspan="3">Tổng tiền món ăn:</td>
                                <td>' . number_format($total_monan) . ' VND</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>';
    }
    
    if ($includeQr) {
        $html .= '
                <div class="qr-box">
                    <h3>📱 Quét mã QR để xem chi tiết đặt bàn</h3>
                    <img src="cid:bookingqr" alt="QR thông tin đặt bàn">
                    <div class="qr-caption">Mã QR chứa thông tin bàn, món ăn và thời gian đặt. Bạn có thể lưu lại để tra cứu nhanh khi đến nhà hàng.</div>
                    <a class="btn-link" href="' . htmlspecialchars($qrUrl) . '">Xem chi tiết đặt bàn</a>
                </div>';
    }
    
    // Thông tin thanh toán
    $html .= '
                <div class="amount-section">
                    <h3>💰 Thông tin thanh toán</h3>
                    <table class="table">
                        <tr><td><strong>Tổng giá trị đơn hàng:</strong></td><td class="highlight">' . number_format($total_amount) . ' VND</td></tr>
                        <tr><td><strong>Đã đặt cọc (50%):</strong></td><td class="highlight" style="color: #28a745;">' . number_format($deposit_amount) . ' VND</td></tr>
                        <tr><td><strong>Còn lại cần thanh toán:</strong></td><td class="highlight" style="color: #dc3545;">' . number_format($remaining_amount) . ' VND</td></tr>
                        <tr><td><strong>Phương thức đặt cọc:</strong></td><td>' . ($payment ? ucfirst($payment['PhuongThuc']) : 'N/A') . '</td></tr>
                        <tr><td><strong>Thời gian đặt cọc:</strong></td><td>' . ($payment ? date('d/m/Y H:i', strtotime($payment['NgayThanhToan'])) : 'N/A') . '</td></tr>
                        <tr><td><strong>Mã giao dịch:</strong></td><td>' . ($payment ? $payment['MaGiaoDich'] : 'N/A') . '</td></tr>
                    </table>
                </div>
                
                <div style="background: #fff3cd; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <h4>⚠️ Lưu ý quan trọng:</h4>
                    <ul>
                        <li>Bạn đã đặt cọc <strong>50%</strong> tổng giá trị đơn hàng</li>
                        <li>Số tiền còn lại <strong>' . number_format($remaining_amount) . ' VND</strong> sẽ được thanh toán khi bạn đến nhà hàng</li>
                        <li>Vui lòng đến đúng giờ đã đặt để đảm bảo bàn được giữ</li>
                        <li>Nếu có thay đổi, vui lòng liên hệ nhà hàng trước ít nhất 2 giờ</li>
                    </ul>
                </div>
                
                <div style="background: #d1ecf1; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #17a2b8;">
                    <h4>📞 Thông tin liên hệ:</h4>
                    <p><strong>Nhà hàng Restoran</strong></p>
                    <p>📧 Email: nhom1.9a7.2018@gmail.com</p>
                    <p>📱 Hotline: 1900-xxxx</p>
                    <p>🕒 Giờ mở cửa: 10:00 - 22:00 (Hàng ngày)</p>
                </div>
            </div>
            
            <div class="footer">
                <p>Cảm ơn bạn đã chọn nhà hàng Restoran!</p>
                <p>Email này được gửi tự động, vui lòng không trả lời.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Xử lý kiểm tra trạng thái thanh toán
if (isset($_GET['action']) && $_GET['action'] === 'check_status' && isset($_GET['madatban'])) {
    header('Content-Type: application/json');
    
    $madatban = (int)$_GET['madatban'];
    $db = new connect_db();
    
    // Ensure we return the DB column with a consistent key 'trangthai'
    $sql = "SELECT TrangThai AS trangthai FROM datban WHERE madatban = ?";
    $result = $db->xuatdulieu_prepared($sql, [$madatban]);

    if (!empty($result)) {
        $statusRaw = $result[0]['trangthai'];
        $status = is_string($statusRaw) ? strtolower($statusRaw) : '';
        error_log("[payment_callback][check_status] madatban={$madatban} statusRaw=" . var_export($statusRaw, true) . " normalized={$status}");
        if ($status === 'confirmed') {
            echo json_encode(['status' => 'success', 'payment_status' => 'paid']);
        } else {
            // Nếu đơn vẫn pending, kiểm tra bảng thanh toán xem đã có giao dịch hoàn tất chưa
            $paymentSql = "SELECT TrangThai AS trangthai FROM thanhtoan 
                           WHERE madatban = ? 
                           ORDER BY idThanhToan DESC 
                           LIMIT 1";
            $paymentResult = $db->xuatdulieu_prepared($paymentSql, [$madatban]);
            if (!empty($paymentResult)) {
                $paymentStatus = strtolower($paymentResult[0]['trangthai'] ?? '');
                if ($paymentStatus === 'completed') {
                    // Nếu thanh toán đã xong nhưng đơn chưa cập nhật, force update để đồng bộ
                    $updateSql = "UPDATE datban SET TrangThai = 'confirmed' WHERE madatban = ?";
                    $db->tuychinh($updateSql, [$madatban]);
                    echo json_encode(['status' => 'success', 'payment_status' => 'paid']);
                    exit;
                }
            }
            echo json_encode(['status' => 'success', 'payment_status' => 'pending']);
        }
    } else {
        error_log("[payment_callback][check_status] madatban={$madatban} NOT FOUND");
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng']);
    }
    exit;
}

// Xử lý hoàn tất giao dịch thành công (từ payment_online.php)
if (isset($_GET['action']) && $_GET['action'] === 'process_success' && isset($_GET['madatban'])) {
    $madatban = $_GET['madatban'];
    $db = new connect_db();
    
    // Kiểm tra trạng thái đặt bàn
    $sql = "SELECT TrangThai AS trangthai FROM datban WHERE madatban = ?";
    $result = $db->xuatdulieu_prepared($sql, [$madatban]);

    $isConfirmed = false;
    if (!empty($result)) {
        $statusRaw = $result[0]['trangthai'];
        $isConfirmed = (is_string($statusRaw) && strtolower($statusRaw) === 'confirmed');
        error_log("[payment_callback][process_success] madatban={$madatban} statusRaw=" . var_export($statusRaw, true) . " isConfirmed=" . ($isConfirmed ? '1' : '0'));
    }

    if ($isConfirmed) {
        // Đặt bàn đã được xác nhận, chuyển đến trang thành công
        $_SESSION['payment_success'] = true;
        $_SESSION['madatban'] = $madatban;
        header('Location: payment_success.php');
        exit;
    } else {
        // Đặt bàn chưa được xác nhận, quay lại trang thanh toán
        header('Location: payment_online.php');
        exit;
    }
}

// Xử lý callback từ MoMo
if (isset($_GET['resultCode']) && isset($_GET['orderId'])) {
    $resultCode = $_GET['resultCode'];
    $orderId = $_GET['orderId'];
    $amount = $_GET['amount'] ?? 0;
    $orderInfo = $_GET['orderInfo'] ?? '';
    $transId = $_GET['transId'] ?? '';
    $message = $_GET['message'] ?? '';
    $signature = $_GET['signature'] ?? '';
    $extraDataRaw = $_GET['extraData'] ?? '';
    $extraData = [];
    if (is_string($extraDataRaw) && $extraDataRaw !== '') {
        parse_str($extraDataRaw, $extraData);
    }
    $isOrderPayment = isset($extraData['type']) && $extraData['type'] === 'order' && !empty($extraData['order_id']);
    $orderPaymentId = $isOrderPayment ? (int)$extraData['order_id'] : null;
    
    // Verify signature
    $rawHash = "accessKey=" . $momoConfig['accessKey'] . 
               "&amount=" . $amount . 
               "&extraData=" . $extraDataRaw . 
               "&message=" . $message . 
               "&orderId=" . $orderId . 
               "&orderInfo=" . $orderInfo . 
               "&orderType=" . ($_GET['orderType'] ?? '') . 
               "&partnerCode=" . ($_GET['partnerCode'] ?? '') . 
               "&payType=" . ($_GET['payType'] ?? '') . 
               "&requestId=" . ($_GET['requestId'] ?? '') . 
               "&responseTime=" . ($_GET['responseTime'] ?? '') . 
               "&resultCode=" . $resultCode . 
               "&transId=" . $transId;
    
    $computedSignature = hash_hmac('sha256', $rawHash, $momoConfig['secretKey']);
    
    if ($signature === $computedSignature) {
        // Chữ ký hợp lệ
        if ($resultCode == 0) {
            // Thanh toán thành công
            $db = new connect_db();

            // Tránh ghi trùng nếu đã nhận callback
            $existingPayment = $db->xuatdulieu_prepared(
                "SELECT idThanhToan FROM thanhtoan WHERE MaGiaoDich = ? LIMIT 1",
                [$transId]
            );

            if ($isOrderPayment && $orderPaymentId) {
                $orderRow = $db->xuatdulieu_prepared(
                    "SELECT idDH, madatban, TongTien, TrangThai FROM donhang WHERE idDH = ? LIMIT 1",
                    [$orderPaymentId]
                );

                if (empty($orderRow)) {
                    $_SESSION['error'] = 'Không tìm thấy đơn hàng để ghi nhận thanh toán.';
                    header('Location: ../index.php?page=profile#bookings');
                    exit;
                }

                $bookingId = isset($orderRow[0]['madatban']) ? (int)$orderRow[0]['madatban'] : null;

                if (empty($existingPayment)) {
                    $db->tuychinh(
                        "INSERT INTO thanhtoan (madatban, idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)
                         VALUES (?, ?, ?, 'momo', 'completed', NOW(), ?)",
                        [$bookingId, $orderPaymentId, (float)$amount, $transId]
                    );
                }

                $paidRows = $db->xuatdulieu_prepared(
                    "SELECT COALESCE(SUM(SoTien), 0) AS paid
                     FROM thanhtoan
                     WHERE (madatban = ? OR idDH = ?) AND TrangThai = 'completed'",
                    [$bookingId, $orderPaymentId]
                );
                $paidTotal = isset($paidRows[0]['paid']) ? (float)$paidRows[0]['paid'] : 0.0;
                $orderTotal = isset($orderRow[0]['TongTien']) ? (float)$orderRow[0]['TongTien'] : 0.0;
                $remainingAfter = max(0, $orderTotal - $paidTotal);

                if ($orderRow[0]['TrangThai'] !== 'huy') {
                    $newStatus = $remainingAfter <= 0 ? 'hoan_thanh' : 'cho_thanh_toan';
                    $db->tuychinh(
                        "UPDATE donhang SET TrangThai = ? WHERE idDH = ?",
                        [$newStatus, $orderPaymentId]
                    );
                }

                $_SESSION['success'] = 'Thanh toán đơn hàng #' . $orderPaymentId . ' thành công.';
                header('Location: ../index.php?page=profile#bookings');
                exit;
            } else {
                // Lấy mã đặt bàn từ orderId (đặt cọc)
                $madatban = (int)explode('_', $orderId)[0];

                if (empty($existingPayment)) {
                    $pendingSql = "SELECT idThanhToan FROM thanhtoan WHERE madatban = ? AND PhuongThuc = 'momo' AND TrangThai = 'pending' ORDER BY idThanhToan DESC LIMIT 1";
                    $pendingRecord = $db->xuatdulieu_prepared($pendingSql, [$madatban]);
                    if (!empty($pendingRecord)) {
                        $updatePaymentSql = "UPDATE thanhtoan 
                                             SET SoTien = ?, TrangThai = 'completed', NgayThanhToan = NOW(), MaGiaoDich = ?
                                             WHERE idThanhToan = ?";
                        $db->tuychinh($updatePaymentSql, [(float)$amount, $transId, (int)$pendingRecord[0]['idThanhToan']]);
                    } else {
                        $insertSql = "INSERT INTO thanhtoan (madatban, idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)
                                      VALUES (?, NULL, ?, 'momo', 'completed', NOW(), ?)";
                        $db->tuychinh($insertSql, [$madatban, (float)$amount, $transId]);
                    }
                }

                // Cập nhật trạng thái đặt bàn: pending -> confirmed
                $updateSql = "UPDATE datban SET TrangThai = 'confirmed' WHERE madatban = ?";
                $db->tuychinh($updateSql, [$madatban]);

                // Gửi email xác nhận đặt cọc
                error_log("🔄 Bắt đầu gửi email xác nhận đặt cọc cho đơn hàng: " . $madatban);
                try {
                    $emailSent = sendDepositConfirmationEmail($madatban, $db);
                    if ($emailSent) {
                        error_log("✅ Email xác nhận đặt cọc đã được gửi THÀNH CÔNG cho đơn hàng: " . $madatban);
                    } else {
                        error_log("❌ KHÔNG THỂ gửi email xác nhận đặt cọc cho đơn hàng: " . $madatban);
                    }
                } catch (Exception $e) {
                    error_log("❌ LỖI khi gửi email xác nhận đặt cọc: " . $e->getMessage());
                    error_log("❌ Stack trace: " . $e->getTraceAsString());
                }
                
                // Lưu thông tin thanh toán vào session
                $_SESSION['payment_success'] = true;
                $_SESSION['payment_method'] = 'momo';
                $_SESSION['madatban'] = $madatban;
                
                // Chuyển hướng đến trang thành công
                header('Location: payment_success.php');
                exit;
            }
        } else {
            // Thanh toán thất bại
            if ($isOrderPayment) {
                $_SESSION['error'] = 'Thanh toán đơn hàng thất bại. ' . $message;
                header('Location: ../index.php?page=profile#bookings');
            } else {
                $_SESSION['payment_error'] = 'Thanh toán thất bại. ' . $message;
                header('Location: payment_failed.php');
            }
            exit;
        }
    } else {
        // Chữ ký không hợp lệ
        if ($isOrderPayment) {
            $_SESSION['error'] = 'Không thể xác thực giao dịch. Vui lòng thử lại.';
            header('Location: ../index.php?page=profile#bookings');
        } else {
            $_SESSION['payment_error'] = 'Chữ ký không hợp lệ. Có thể có lỗi bảo mật.';
            header('Location: payment_failed.php');
        }
        exit;
    }
}


// Nếu không có tham số callback nào, chuyển về trang chủ
header('Location: ../index.php?page=trangchu');
exit;
?>
