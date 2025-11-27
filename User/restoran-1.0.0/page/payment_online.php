<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$siteBase = '/CNM/User/restoran-1.0.0';

// Debug: Kiểm tra session và GET parameters
error_log("Debug payment_online: madatban in session = " . (isset($_SESSION['madatban']) ? $_SESSION['madatban'] : 'not set'));
error_log("Debug payment_online: madatban in GET = " . (isset($_GET['madatban']) ? $_GET['madatban'] : 'not set'));

// Kiểm tra session và dữ liệu cần thiết
if (!isset($_SESSION['madatban']) && !isset($_GET['madatban'])) {
    $_SESSION['error'] = 'Thông tin thanh toán không tồn tại. Vui lòng thử lại.';
    header('Location: ' . $siteBase . '/index.php?page=trangchu');
    exit;
}

// Nếu có madatban từ GET parameter (từ profile), sử dụng nó
if (isset($_GET['madatban'])) {
    $_SESSION['madatban'] = (int)$_GET['madatban'];
    // Tạo thông tin thanh toán mới cho đặt bàn từ profile
    $_SESSION['payment_method'] = 'online';
    $_SESSION['payment_expires'] = date('Y-m-d H:i:s', strtotime('+6 hours'));
}

// Đặt timezone sớm để mọi hàm thời gian nhất quán
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../class/clsconnect.php';
require_once __DIR__ . '/../class/clsdatban.php';

// Lấy thông tin đặt bàn
$db = new connect_db();
$datban = new datban();

$sql = "SELECT
  db.madatban,
  db.payment_expires,
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

// Lấy hạn thanh toán ưu tiên từ DB, fallback session, cuối cùng +6 giờ
$paymentExpires = $booking_info['payment_expires'] ?? null;
if (empty($paymentExpires) && isset($_SESSION['payment_expires'])) {
    $paymentExpires = $_SESSION['payment_expires'];
}
if (empty($paymentExpires)) {
    $paymentExpires = date('Y-m-d H:i:s', strtotime('+6 hours'));
}
// Chuẩn hóa và lưu lại session
$expiryTs = strtotime($paymentExpires);
if ($expiryTs === false) {
    $expiryTs = time() + 6 * 3600;
    $paymentExpires = date('Y-m-d H:i:s', $expiryTs);
}
$_SESSION['payment_expires'] = $paymentExpires;
// Nếu DB chưa có hạn, cập nhật để đồng bộ (không ảnh hưởng nếu cột đã có)
if (empty($booking_info['payment_expires'])) {
    try {
        $db->tuychinh("UPDATE datban SET payment_expires = ? WHERE madatban = ?", [$paymentExpires, $_SESSION['madatban']]);
    } catch (Exception $e) {
        // bỏ qua nếu DB thiếu cột hoặc lỗi quyền
    }
}

// Kiểm tra hết hạn theo timestamp
if (time() > $expiryTs) {
    $_SESSION['error'] = 'Đơn hàng đã hết hạn thanh toán. Vui lòng đặt lại.';
    unset($_SESSION['madatban'], $_SESSION['payment_method'], $_SESSION['payment_expires']);
    header('Location: ' . $siteBase . '/index.php?page=trangchu');
    exit;
}

// Lấy thông tin thanh toán đã thực hiện
$paymentHistory = $db->xuatdulieu_prepared(
    "SELECT COALESCE(SUM(SoTien), 0) AS paid_amount
     FROM thanhtoan
     WHERE madatban = ? AND TrangThai = 'completed'",
    [$_SESSION['madatban']]
);
$paid_amount = isset($paymentHistory[0]['paid_amount']) ? (int)$paymentHistory[0]['paid_amount'] : 0;

// Cấu hình MoMo
$momoConfig = [
    'partnerCode' => 'MOMOBKUN20180529',
    'accessKey' => 'klm05TvNBzhg7h7j',
    'secretKey' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
    'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
    'returnUrl' => 'http://localhost/CNM/User/restoran-1.0.0/page/payment_callback.php',
    'notifyUrl' => 'http://localhost/CNM/User/restoran-1.0.0/page/payment_callback.php'
];

/**
 * Gọi API MoMo, tự động thử lại khi gặp lỗi QR code 98 hoặc lỗi kết nối tạm thời.
 *
 * @return array [decodedResponse, rawResponse, ['error' => string, 'errno' => int]]
 */
function callMomoApi(string $endpoint, array $payload, int $maxRetries = 1): array
{
    $rawResponse = '';
    $decodedResponse = [];
    $lastError = '';
    $lastErrno = 0;

    for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen(json_encode($payload))
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        // Bật verify để handshake ổn định hơn
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawResponse = curl_exec($ch);
        $lastError = curl_error($ch);
        $lastErrno = curl_errno($ch);
        curl_close($ch);

        error_log("MoMo API Attempt #" . ($attempt + 1) . " Raw Response: " . $rawResponse);
        error_log("MoMo API Attempt #" . ($attempt + 1) . " Curl Error: " . $lastError);
        error_log("MoMo API Attempt #" . ($attempt + 1) . " Curl Errno: " . $lastErrno);

        if ($rawResponse !== false && $rawResponse !== null) {
            $decodedResponse = json_decode($rawResponse, true) ?: [];
        }

        $payUrl = $decodedResponse['payUrl'] ?? '';
        $resultCode = isset($decodedResponse['resultCode']) ? (int)$decodedResponse['resultCode'] : null;

        // Thành công -> dừng vòng lặp
        if (!empty($payUrl)) {
            return [$decodedResponse, $rawResponse, ['error' => $lastError, 'errno' => $lastErrno]];
        }

        // Nếu lỗi QR code 98 (thường do handshake / timeout) thì thử lại (nếu còn lượt)
        if ($resultCode === 98 && $attempt < $maxRetries) {
            sleep(1);
            continue;
        }

        // Nếu lỗi kết nối nhưng còn lượt, thử lại
        if ($lastErrno !== 0 && $attempt < $maxRetries) {
            sleep(1);
            continue;
        }

        // Không còn điều kiện để retry -> thoát vòng lặp
        break;
    }

    return [$decodedResponse, $rawResponse, ['error' => $lastError, 'errno' => $lastErrno]];
}

// Tạo dữ liệu thanh toán MoMo
$orderId = $_SESSION['madatban'] . '_' . time();
$requestId = $orderId;
// Tính khoản đặt cọc bổ sung cần thanh toán
$total_amount = (int)$booking_info['tongtien'];
$required_deposit = (int)ceil($total_amount * 0.5);

$amount = max(0, $required_deposit - $paid_amount); // số tiền cần thanh toán thêm

if ($amount <= 0) {
    $_SESSION['success'] = 'Đơn đặt bàn đã đạt đủ mức đặt cọc. Không cần thanh toán thêm.';
    header('Location: ' . $siteBase . '/index.php?page=profile#bookings');
    exit;
}

$orderInfo = 'Bo sung dat coc - Dat ban ' . $_SESSION['madatban'];
$remaining_after_deposit = max(0, $total_amount - $required_deposit);
$requestType = 'captureWallet';
$extraData = '';

// Tạo chữ ký
$rawHash = "accessKey=" . $momoConfig['accessKey'] . 
           "&amount=" . $amount . 
           "&extraData=" . $extraData . 
           "&ipnUrl=" . $momoConfig['notifyUrl'] . 
           "&orderId=" . $orderId . 
           "&orderInfo=" . $orderInfo . 
           "&partnerCode=" . $momoConfig['partnerCode'] . 
           "&redirectUrl=" . $momoConfig['returnUrl'] . 
           "&requestId=" . $requestId . 
           "&requestType=" . $requestType;

$signature = hash_hmac('sha256', $rawHash, $momoConfig['secretKey']);

// Dữ liệu gửi đến MoMo
$data = [
    'partnerCode' => $momoConfig['partnerCode'],
    'partnerName' => 'Restaurant',
    'storeId' => 'RestaurantStore',
    'requestId' => $requestId,
    'amount' => $amount,
    'orderId' => $orderId,
    'orderInfo' => $orderInfo,
    'redirectUrl' => $momoConfig['returnUrl'],
    'ipnUrl' => $momoConfig['notifyUrl'],
    'lang' => 'vi',
    'extraData' => $extraData,
    'requestType' => $requestType,
    'signature' => $signature
];

// Gọi API MoMo (có retry)
[$momoResponse, $rawResponse, $curlInfo] = callMomoApi($momoConfig['endpoint'], $data, 1);
$curl_error = $curlInfo['error'] ?? '';
$curl_errno = $curlInfo['errno'] ?? 0;

// Debug: Hiển thị response nếu không thành công
if (!isset($momoResponse['payUrl']) || empty($momoResponse['payUrl'])) {
    echo '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px;">';
    echo '<h3>Debug Information - MoMo API Response:</h3>';
    echo '<pre>' . htmlspecialchars(print_r($momoResponse, true)) . '</pre>';
    echo '<h4>Raw Response:</h4>';
    echo '<pre>' . htmlspecialchars($rawResponse) . '</pre>';
    echo '<h4>Request Data:</h4>';
    echo '<pre>' . htmlspecialchars(print_r($data, true)) . '</pre>';
    if ($curl_error) {
        echo '<h4>Curl Error:</h4>';
        echo '<p>' . htmlspecialchars($curl_error) . ' (Error #' . $curl_errno . ')</p>';
    }
    echo '</div>';
}

// Tạo mã QR
require_once __DIR__ . '/../vendor/autoload.php';

try {
    if (isset($momoResponse['payUrl']) && !empty($momoResponse['payUrl'])) {
        $momoPayUrl = $momoResponse['payUrl'];
        
        // Tao QR code bang API v3: QrCode + Writer (fallback SVG neu thieu GD)
        $qrCode = new \Endroid\QrCode\QrCode($momoPayUrl);
        $qrCode->setSize(300);
        $qrCode->setMargin(10);
        if (extension_loaded('gd') && function_exists('imagepng')) {
            $writer = new \Endroid\QrCode\Writer\PngWriter();
        } else {
            error_log('GD extension missing - falling back to SVG QR code rendering.');
            $writer = new \Endroid\QrCode\Writer\SvgWriter();
        }
        $result = $writer->write($qrCode);
        
        // Lấy data URI để hiển thị trong HTML
        $qrCodeBase64 = $result->getDataUri();
    } else {
        throw new Exception('Không nhận được URL thanh toán từ MoMo. Response: ' . json_encode($momoResponse));
    }
} catch (Exception $e) {
    // Log lỗi để debug
    error_log('QR Code Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    error_log('MoMo Response: ' . json_encode($momoResponse));
    
    // Tạo một hình ảnh mặc định nếu không thể tạo mã QR
    if (extension_loaded('gd')) {
        $im = imagecreatetruecolor(200, 200);
        $bgColor = imagecolorallocate($im, 255, 255, 255);
        $textColor = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, 0, 0, 200, 200, $bgColor);
        imagestring($im, 5, 40, 80, 'Scan to pay', $textColor);
        imagestring($im, 3, 30, 100, 'Error generating QR', $textColor);
        
        ob_start();
        imagepng($im);
        $imageData = ob_get_clean();
        imagedestroy($im);
        
        $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($imageData);
    } else {
        $qrCodeBase64 = '';
    }
}

// Lưu thông tin vào session để callback có thể truy cập
$_SESSION['payment_info'] = [
    'madatban' => $_SESSION['madatban'],
    'amount' => $booking_info['tongtien'],
    'orderId' => $orderId,
    'requestId' => $requestId
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán online - Restoran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 20px auto;
            max-width: 800px;
        }
        .payment-header {
            background: linear-gradient(135deg, #a50064, #d60085);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .payment-body {
            padding: 40px;
        }
        .qr-section {
            text-align: center;
            margin: 30px 0;
        }
        .qr-code-container {
            position: relative;
            display: inline-block;
            border: 3px solid #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            background: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .qr-code img {
            border-radius: 10px;
        }
        .qr-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            justify-content: center;
            align-items: center;
            color: white;
            font-weight: bold;
            border-radius: 15px;
        }
        .order-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }
        .amount-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            text-align: center;
            margin: 20px 0;
        }
        .countdown {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
            font-size: 1.1rem;
            font-weight: bold;
        }
        .payment-steps {
            background: #ecf0f1;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }
        .step {
            display: flex;
            align-items: center;
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .step-number {
            background: #3498db;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
        }
        .btn-refresh {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-refresh:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
            color: white;
        }
        .btn-back {
            background: #95a5a6;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-back:hover {
            background: #7f8c8d;
            color: white;
        }
        .status-checking {
            background: #f39c12;
            color: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
            display: none;
        }
        .success-message {
            background: #27ae60;
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
            display: none;
        }
        .deposit-alert {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="payment-container">
            <!-- Header -->
            <div class="payment-header">
                <h1><i class="fas fa-credit-card me-3"></i>Thanh Toán Online</h1>
                <p class="mb-0">Quét mã QR để thanh toán qua MoMo</p>
            </div>

            <!-- Body -->
            <div class="payment-body">
                <!-- Thông báo thành công -->
                <div class="success-message" id="successMessage">
                    <h4><i class="fas fa-check-circle me-2"></i>Thanh toán thành công!</h4>
                    <p>Đơn đặt bàn của bạn đã được xác nhận. Đang chuyển hướng...</p>
                </div>

                <!-- Thông báo đang kiểm tra -->
                <div class="status-checking" id="statusChecking">
                    <i class="fas fa-spinner fa-spin me-2"></i>Đang kiểm tra trạng thái thanh toán...
                </div>

                <!-- Thông tin đơn hàng -->
                <div class="order-info">
                    <h5><i class="fas fa-receipt me-2"></i>Thông tin đặt bàn</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Mã đặt bàn:</strong> <?php echo htmlspecialchars($booking_info['madatban']); ?></p>
                            <p><strong>Khách hàng:</strong> <?php echo htmlspecialchars($booking_info['tenKH']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($booking_info['email']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Bàn:</strong> <?php echo htmlspecialchars($booking_info['danh_sach_ban']); ?></p>
                            <p><strong>Thời gian:</strong> <?php echo date('d/m/Y H:i', strtotime($booking_info['thoigian'])); ?></p>
                            <p><strong>Số người:</strong> <?php echo $booking_info['soluongKH']; ?></p>
                        </div>
                    </div>
                </div>

                <div class="deposit-alert">
                    <i class="fas fa-hourglass-half fa-lg"></i>
                    <span>Vui lòng thanh toán tiền cọc trong vòng <strong>6 giờ</strong> kể từ khi đặt bàn để giữ chỗ.Nếu không đơn đặt bàn của bạn sẽ bị hủy</span>
                </div>

                <!-- Số tiền cần thanh toán -->
                <div class="amount-display">
                    <div class="text-primary">Số tiền cần thanh toán thêm</div>
                    <div class="h2 text-success"><?php echo number_format($amount); ?> VND</div>
                    <div class="text-muted mt-2">
                        <small>
                            <i class="fas fa-info-circle me-1"></i>
                            Tổng đơn hàng: <?php echo number_format($total_amount); ?> VND<br>
                            Đã đặt cọc trước: <?php echo number_format($paid_amount); ?> VND<br>
                            Yêu cầu đặt cọc (50%): <?php echo number_format($required_deposit); ?> VND<br>
                            Số tiền còn lại thanh toán tại nhà hàng: <?php echo number_format($remaining_after_deposit); ?> VND
                        </small>
                    </div>
                </div>

                <!-- Đếm ngược thời gian hết hạn -->
                <div class="countdown" id="countdown">
                    <i class="fas fa-clock me-2"></i>
                    <span id="countdownText">Đang tính toán...</span>
                </div>

                <!-- Mã QR -->
                <div class="qr-section">
                    <h4><i class="fas fa-qrcode me-2"></i>Quét mã QR để thanh toán qua ví MoMo</h4>
                    <div class="qr-code-container">
                        <div class="qr-code">
                            <img src="<?php echo $qrCodeBase64; ?>" alt="MoMo QR Code" style="max-width: 300px;">
                        </div>
                        <div class="qr-overlay" id="qrOverlay">
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                                <p>Mã QR đã hết hạn</p>
                                <p>Vui lòng làm mới để tạo mã mới</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-muted mt-3">
                        <i class="fas fa-info-circle me-1"></i>
                        Quét mã QR bằng app MoMo để thanh toán.
                    </p>
                </div>

                <!-- Hướng dẫn thanh toán -->
                <div class="payment-steps">
                    <h5><i class="fas fa-list-ol me-2"></i>Hướng dẫn thanh toán</h5>
                    <div class="step">
                        <div class="step-number">1</div>
                        <div>
                            <strong>Mở ứng dụng ngân hàng</strong>
                            <p class="mb-0 text-muted">Mở ứng dụng ngân hàng trên điện thoại của bạn</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div>
                            <strong>Quét mã QR</strong>
                            <p class="mb-0 text-muted">Sử dụng chức năng quét QR trong ứng dụng</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div>
                            <strong>Xác nhận thanh toán</strong>
                            <p class="mb-0 text-muted">Kiểm tra thông tin và xác nhận thanh toán</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <div>
                            <strong>Hoàn tất</strong>
                            <p class="mb-0 text-muted">Chờ xác nhận và nhận thông báo thành công</p>
                        </div>
                    </div>
                </div>

                <!-- Nút hành động -->
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-refresh me-3" onclick="window.location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>Làm mới mã QR
                    </button>
                    <a href="<?php echo $siteBase; ?>/index.php?page=trangchu" class="btn btn-back">
                        <i class="fas fa-home me-2"></i>Về trang chủ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script>
        // Đếm ngược thời gian hết hạn (dùng timestamp để tránh lỗi parse)
        const expiryTime = <?php echo ($expiryTs * 1000); ?>;
        
        function updateCountdown() {
            const now = new Date().getTime();
            const timeLeft = expiryTime - now;
            
            if (timeLeft <= 0) {
                document.getElementById('countdownText').innerHTML = 'Đơn hàng đã hết hạn thanh toán!';
                document.getElementById('countdown').style.background = '#e74c3c';
                return;
            }
            
            const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            
            document.getElementById('countdownText').innerHTML = 
                `Còn lại: ${hours} giờ ${minutes} phút ${seconds} giây`;
        }
        
        // Cập nhật mỗi giây
        setInterval(updateCountdown, 1000);
        
        // Cập nhật ngay khi trang load
        updateCountdown();

        // Kiểm tra trạng thái thanh toán
        function checkPaymentStatus() {
            document.getElementById('statusChecking').style.display = 'block';
            
            fetch('page/payment_callback.php?action=check_status&madatban=<?php echo $_SESSION['madatban']; ?>', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('statusChecking').style.display = 'none';
                
                if (data.status === 'success' && data.payment_status === 'paid') {
                    document.getElementById('successMessage').style.display = 'block';
                    
                    // Chuyển hướng qua payment_callback trước, sau đó đến payment_success
                    setTimeout(() => {
                        // Stop polling before redirecting
                        if (statusCheckInterval) {
                            clearInterval(statusCheckInterval);
                        }
                        // Gọi payment_callback để xử lý hoàn tất giao dịch
                        window.location.href = 'page/payment_callback.php?action=process_success&madatban=<?php echo $_SESSION['madatban']; ?>';
                    }, 2000);
                }
            })
            .catch(error => {
                document.getElementById('statusChecking').style.display = 'none';
                console.error('Error checking payment status:', error);
            });
        }

        // Kiểm tra trạng thái mỗi 5 giây
        let statusCheckInterval = setInterval(checkPaymentStatus, 5000);

        // Cleanup interval khi rời trang
        window.addEventListener('beforeunload', function() {
            if (statusCheckInterval) {
                clearInterval(statusCheckInterval);
            }
        });

        // Kiểm tra trạng thái ngay khi trang load
        checkPaymentStatus();
    </script>
</body>
</html>


