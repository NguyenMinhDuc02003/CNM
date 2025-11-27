<?php
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['khachhang_id'])) {
    $_SESSION['error'] = 'Vui lòng đăng nhập để thanh toán.';
    header('Location: ../index.php?page=login');
    exit;
}

require_once __DIR__ . '/../class/clsconnect.php';

$orderId = isset($_GET['idDH']) ? (int)$_GET['idDH'] : 0;
if ($orderId <= 0) {
    $_SESSION['error'] = 'Không tìm thấy thông tin đơn hàng.';
    header('Location: ../index.php?page=profile#bookings');
    exit;
}

$db = new connect_db();

$orderRows = $db->xuatdulieu_prepared(
    "SELECT d.idDH, d.madatban, d.TrangThai, d.TongTien, d.MaDonHang, meta.table_label, meta.area_name
     FROM donhang d
     JOIN datban b ON b.madatban = d.madatban
     LEFT JOIN donhang_admin_meta meta ON meta.idDH = d.idDH
     WHERE d.idDH = ? AND b.idKH = ?
     LIMIT 1",
    [$orderId, $_SESSION['khachhang_id']]
);

if (empty($orderRows)) {
    $_SESSION['error'] = 'Đơn hàng không thuộc tài khoản của bạn hoặc không tồn tại.';
    header('Location: ../index.php?page=profile#bookings');
    exit;
}

$order = $orderRows[0];
$blockedStatuses = ['huy'];
if (in_array($order['TrangThai'], $blockedStatuses, true)) {
    $_SESSION['error'] = 'Đơn hàng đã bị hủy, không thể thanh toán.';
    header('Location: ../index.php?page=profile#bookings');
    exit;
}

$bookingId = (int)$order['madatban'];
$paidRows = $db->xuatdulieu_prepared(
    "SELECT COALESCE(SUM(SoTien), 0) AS paid
     FROM thanhtoan
     WHERE (madatban = ? OR idDH = ?) AND TrangThai = 'completed'",
    [$bookingId, $orderId]
);
$paidAmount = isset($paidRows[0]['paid']) ? (float)$paidRows[0]['paid'] : 0.0;
$totalAmount = isset($order['TongTien']) ? (float)$order['TongTien'] : 0.0;
$remaining = max(0, $totalAmount - $paidAmount);

if ($remaining <= 0) {
    $_SESSION['success'] = 'Đơn hàng đã được thanh toán đủ.';
    header('Location: ../index.php?page=profile#bookings');
    exit;
}

$momoConfig = [
    'partnerCode' => 'MOMOBKUN20180529',
    'accessKey' => 'klm05TvNBzhg7h7j',
    'secretKey' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
    'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
    'returnUrl' => 'http://localhost/CNM/User/restoran-1.0.0/page/payment_callback.php',
    'notifyUrl' => 'http://localhost/CNM/User/restoran-1.0.0/page/payment_callback.php'
];

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
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $rawResponse = curl_exec($ch);
        $lastError = curl_error($ch);
        $lastErrno = curl_errno($ch);
        curl_close($ch);

        if ($rawResponse !== false && $rawResponse !== null) {
            $decodedResponse = json_decode($rawResponse, true) ?: [];
        }

        $payUrl = $decodedResponse['payUrl'] ?? '';
        $resultCode = isset($decodedResponse['resultCode']) ? (int)$decodedResponse['resultCode'] : null;

        if (!empty($payUrl)) {
            return [$decodedResponse, $rawResponse, ['error' => $lastError, 'errno' => $lastErrno]];
        }

        if ($resultCode === 98 && $attempt < $maxRetries) {
            sleep(1);
            continue;
        }

        if ($lastErrno !== 0 && $attempt < $maxRetries) {
            sleep(1);
            continue;
        }

        break;
    }

    return [$decodedResponse, $rawResponse, ['error' => $lastError, 'errno' => $lastErrno]];
}

date_default_timezone_set('Asia/Ho_Chi_Minh');
$orderCode = 'ORDER_' . $orderId . '_' . time();
$requestId = $orderCode;
$amount = (int)$remaining;
$orderInfo = 'Thanh toan don hang #' . (!empty($order['MaDonHang']) ? $order['MaDonHang'] : $orderId);
$requestType = 'captureWallet';
$extraData = http_build_query([
    'type' => 'order',
    'order_id' => $orderId
]);

$rawHash = "accessKey=" . $momoConfig['accessKey'] .
           "&amount=" . $amount .
           "&extraData=" . $extraData .
           "&ipnUrl=" . $momoConfig['notifyUrl'] .
           "&orderId=" . $orderCode .
           "&orderInfo=" . $orderInfo .
           "&partnerCode=" . $momoConfig['partnerCode'] .
           "&redirectUrl=" . $momoConfig['returnUrl'] .
           "&requestId=" . $requestId .
           "&requestType=" . $requestType;

$signature = hash_hmac('sha256', $rawHash, $momoConfig['secretKey']);

$data = [
    'partnerCode' => $momoConfig['partnerCode'],
    'partnerName' => 'Restaurant',
    'storeId' => 'RestaurantStore',
    'requestId' => $requestId,
    'amount' => $amount,
    'orderId' => $orderCode,
    'orderInfo' => $orderInfo,
    'redirectUrl' => $momoConfig['returnUrl'],
    'ipnUrl' => $momoConfig['notifyUrl'],
    'lang' => 'vi',
    'extraData' => $extraData,
    'requestType' => $requestType,
    'signature' => $signature
];

[$momoResponse] = callMomoApi($momoConfig['endpoint'], $data);
$payUrl = $momoResponse['payUrl'] ?? '';

if (!empty($payUrl)) {
    header('Location: ' . $payUrl);
    exit;
}

$_SESSION['error'] = 'Không thể khởi tạo thanh toán MoMo. Vui lòng thử lại sau.';
header('Location: ../index.php?page=profile#bookings');
exit;
