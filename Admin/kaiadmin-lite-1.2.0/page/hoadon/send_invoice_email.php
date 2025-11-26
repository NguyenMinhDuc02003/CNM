<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Bật hiển thị lỗi cho mục đích gỡ lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ghi log bắt đầu quá trình
error_log("Starting send_invoice_email.php script");

// Kiểm tra dữ liệu gửi lên
if (!isset($_POST['idHD']) || !isset($_POST['email'])) {
    error_log("Missing required parameters: idHD or email");
    die("Thiếu thông tin cần thiết! idHD: " . (isset($_POST['idHD']) ? $_POST['idHD'] : 'không có') . 
        ", email: " . (isset($_POST['email']) ? $_POST['email'] : 'không có'));
}

$idHD = $_POST['idHD'];
$customerEmail = $_POST['email'];

error_log("Processing invoice email for idHD: $idHD, email: $customerEmail");

// Kết nối CSDL
$conn = mysqli_connect('localhost', 'hceeab2b55_chung9atm', 'Chung2002!', 'hceeab2b55_restaurant');
if (!$conn) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("Kết nối cơ sở dữ liệu thất bại: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");
require_once __DIR__ . '/../../class/clsDonHang.php';
$orderService = new clsDonHang();

// Lấy thông tin hóa đơn
$query = "SELECT hd.idHD, hd.idDH, kh.tenKH, kh.sodienthoai, ban.SoBan, hd.ngay, hd.TongTien, hd.hinhthucthanhtoan,
          dh.MaDonHang, kh.email
          FROM hoadon hd 
          JOIN khachhang kh ON hd.idKH = kh.idKH 
          JOIN donhang dh ON hd.idDH = dh.idDH 
          JOIN ban ON dh.idban = ban.idban 
          WHERE hd.idHD = $idHD";

error_log("Executing query: $query");

$result = $conn->query($query);
if (!$result || $result->num_rows == 0) {
    error_log("No invoice data found for idHD: $idHD");
    die("Không tìm thấy thông tin hóa đơn!");
}

$invoiceInfo = $result->fetch_assoc();
error_log("Invoice info retrieved successfully");

$invoiceItems = [];
$orderId = (int)($invoiceInfo['idDH'] ?? 0);
if ($orderId > 0) {
    try {
        $invoiceItems = $orderService->getInvoiceDisplayItems($orderId);
    } catch (Throwable $th) {
        error_log("Failed to load invoice items: " . $th->getMessage());
        $invoiceItems = [];
    }
}
error_log("Invoice details retrieved: " . count($invoiceItems) . " items");

// Tạo nội dung email HTML
$date = new DateTime($invoiceInfo['ngay']);
$ngay = $date->format('d');
$thang = $date->format('m');
$nam = $date->format('Y');

$emailContent = '
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hóa đơn từ nhà hàng Restoran</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; }
        .invoice { 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 20px; 
            border: 1px solid #ddd; 
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .header { text-align: center; margin-bottom: 20px; }
        .invoice-details { margin-bottom: 20px; }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px;
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 8px; 
            text-align: left;
        }
        th { background-color: #f2f2f2; }
        .total { text-align: right; font-weight: bold; }
        .footer { text-align: center; font-style: italic; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="header">
            <h1>HÓA ĐƠN BÁN HÀNG</h1>
            <p>Ngày <strong>' . $ngay . '</strong> tháng <strong>' . $thang . '</strong> năm <strong>' . $nam . '</strong></p>
            <p><strong>Ký hiệu:</strong> 2C21TBB - <strong>Số:</strong> 98723</p>
        </div>

        <div class="invoice-details">
            <p><strong>Người bán:</strong> Nhà hàng Restoran</p>
            <p><strong>Địa chỉ:</strong> 12 Nguyễn Văn Bảo, phường 4, quận Gò Vấp, TP.HCM</p>
            <p><strong>Điện thoại:</strong> 0123456789</p>
            <hr>
            <p><strong>Bàn:</strong> ' . $invoiceInfo['SoBan'] . '</p>
            <p><strong>Khách hàng:</strong> ' . $invoiceInfo['tenKH'] . '</p>
            <p><strong>Số điện thoại:</strong> ' . $invoiceInfo['sodienthoai'] . '</p>
            <p><strong>Hình thức thanh toán:</strong> ' . $invoiceInfo['hinhthucthanhtoan'] . '</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>STT</th>
                    <th>Tên hàng hóa</th>
                    <th>Đơn vị tính</th>
                    <th>Số lượng</th>
                    <th>Đơn giá</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>';

$stt = 1;
if (!empty($invoiceItems)) {
    foreach ($invoiceItems as $item) {
        $emailContent .= '
                <tr>
                    <td>' . $stt++ . '</td>
                    <td>' . htmlspecialchars($item['name']) . '</td>
                    <td>' . htmlspecialchars($item['unit'] ?? '') . '</td>
                    <td>' . (int)$item['quantity'] . '</td>
                    <td>' . number_format($item['unit_price'], 0, ',', '.') . '</td>
                    <td>' . number_format($item['total'], 0, ',', '.') . '</td>
                </tr>';
    }
} else {
    $emailContent .= '
                <tr>
                    <td colspan="6" style="text-align:center;">Không có dữ liệu món ăn.</td>
                </tr>';
}

$emailContent .= '
                <tr>
                    <td colspan="5" class="total">Chiết khấu:</td>
                    <td>' . number_format(0, 0, ',', '.') . '</td>
                </tr>
                <tr>
                    <td colspan="5" class="total">Tổng tiền:</td>
                    <td>' . number_format($invoiceInfo['TongTien'], 0, ',', '.') . '</td>
                </tr>
            </tbody>
        </table>

        <div class="signatures">
            <table style="border: none;">
                <tr>
                    <td style="text-align: center; border: none;">
                        <p><strong>Người mua hàng</strong></p>
                        <p>(Chữ ký số nếu có)</p>
                    </td>
                    <td style="text-align: center; border: none;">
                        <p><strong>Người bán hàng</strong></p>
                        <p>(Chữ ký điện tử, chữ ký số)</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer">
            <p>(Cần kiểm tra, đối chiếu khi lập, nhận hóa đơn)</p>
            <p><strong>Xin cảm ơn và hẹn gặp lại quý khách!</strong></p>
        </div>
    </div>
</body>
</html>';

error_log("Email content created successfully");

// Sử dụng PHPMailer để gửi email
$phpmailerPath = $_SERVER['DOCUMENT_ROOT'] . '/CNM/User/restoran-1.0.0/PHPMailer/';
error_log("PHPMailer path: $phpmailerPath");

if (!file_exists($phpmailerPath . 'PHPMailer.php')) {
    error_log("PHPMailer.php not found at: " . $phpmailerPath . 'PHPMailer.php');
    die("Không tìm thấy thư viện PHPMailer tại đường dẫn: " . $phpmailerPath . 'PHPMailer.php');
}

require_once($phpmailerPath . 'PHPMailer.php');
require_once($phpmailerPath . 'SMTP.php');
require_once($phpmailerPath . 'Exception.php');

error_log("PHPMailer libraries loaded successfully");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Ghi log
error_log("Trying to send invoice email to: " . $customerEmail);

try {
    // Khởi tạo PHPMailer
    $mail = new PHPMailer(true);
    error_log("PHPMailer initialized");
    
    // Bắt đầu output buffering để ngăn debug hiển thị trên trang
    ob_start();
    
    // Cấu hình SMTP - Sử dụng cấu hình giống như success.php
    $mail->isSMTP();
    $mail->SMTPDebug = 2; // Debug vẫn được ghi log nhưng không hiển thị trên trang
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer [$level]: $str"); // Ghi log thay vì hiển thị
    };
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'nhocac2k3@gmail.com'; // Email đã cấu hình trong success.php
    $mail->Password = 'synj bwev gvut uamu'; // Mật khẩu ứng dụng đã cấu hình
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';
    
    error_log("SMTP configured");

    // Người gửi và người nhận
    $mail->setFrom('nhocac2k3@gmail.com', 'Nhà hàng Restoran');
    $mail->addAddress($customerEmail, $invoiceInfo['tenKH']);
    error_log("Sender and recipient configured");

    // Nội dung email
    $mail->isHTML(true);
    $mail->Subject = 'Hóa đơn từ nhà hàng Restoran - ' . $invoiceInfo['MaDonHang'];
    $mail->Body = $emailContent;
    error_log("Email content set");

    // Gửi email
    error_log("Attempting to send email...");
    $result = $mail->send();
    error_log("Email send result: " . ($result ? 'true' : 'false'));
    
    // Xóa bỏ output buffer để không hiển thị debug trên trang
    ob_end_clean();
    
    error_log("Invoice email sent successfully to " . $customerEmail);
    
    // Hiển thị thông báo thành công và sau đó chuyển hướng
    echo '<!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gửi hóa đơn thành công</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
        <style>
            body { padding: 50px; }
            .success-box { 
                max-width: 600px; 
                margin: 0 auto;
                text-align: center;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 0 15px rgba(0,0,0,0.1);
            }
            .success-icon {
                font-size: 80px;
                color: #28a745;
                margin-bottom: 20px;
            }
            .countdown {
                font-size: 20px;
                color: #6c757d;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success-box bg-white">
                <div class="success-icon">✓</div>
                <h2 class="mb-4">Gửi hóa đơn thành công!</h2>
                <p class="lead mb-4">Hóa đơn đã được gửi thành công đến email: <strong>' . htmlspecialchars($customerEmail) . '</strong></p>
                <p>Vui lòng kiểm tra hộp thư email của bạn.</p>
                <div class="countdown" id="counter">Tự động chuyển hướng sau <span id="seconds">5</span> giây...</div>
                <div class="mt-4">
                    <a href="../../index.php?page=chitietHD&idHD=' . $idHD . '&status=success" class="btn btn-primary">Quay lại ngay</a>
                </div>
            </div>
        </div>

        <script>
            // Đếm ngược và chuyển hướng
            let seconds = 5;
            const countdown = setInterval(function() {
                seconds--;
                document.getElementById("seconds").textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(countdown);
                    window.location.href = "../../index.php?page=chitietHD&idHD=' . $idHD . '&status=success";
                }
            }, 1000);
        </script>
    </body>
    </html>';
    exit();
} catch (Exception $e) {
    // Xóa bỏ output buffer nếu có lỗi
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    error_log("Invoice email sending failed: " . $e->getMessage());
    
    // Hiển thị thông báo lỗi chi tiết để gỡ lỗi trước
    echo "<h1>Lỗi gửi email</h1>";
    echo "<p>Chi tiết lỗi: " . $e->getMessage() . "</p>";
    echo "<p>Đường dẫn PHPMailer: " . $phpmailerPath . "</p>";
    echo "<p><a href='../../index.php?page=chitietHD&idHD=$idHD'>Quay lại trang chi tiết hóa đơn</a></p>";
    die();
    
    // Khi đã xác định và sửa lỗi, bỏ comment dòng dưới và xóa mã hiển thị lỗi ở trên
    // header("Location: ../../index.php?page=chitietHD&idHD=$idHD&status=error&msg=" . urlencode($e->getMessage()));
    // exit();
} 
