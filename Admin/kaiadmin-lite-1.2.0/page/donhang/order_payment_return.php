<?php
require_once __DIR__ . '/../../includes/auth.php';
admin_auth_bootstrap_session();

require_once __DIR__ . '/../../class/clsconnect.php';

function admin_momo_extract_order_id(string $orderIdentifier): int
{
    if (preg_match('/DH0*([0-9]+)/i', $orderIdentifier, $matches)) {
        return (int)$matches[1];
    }
    return 0;
}

function admin_momo_record_payment(array $payload): ?int
{
    $orderIdentifier = $payload['orderId'] ?? '';
    $resultCode = isset($payload['resultCode']) ? (int)$payload['resultCode'] : -1;
    $amount = isset($payload['amount']) ? (float)$payload['amount'] : 0.0;
    if ($resultCode !== 0 || $orderIdentifier === '' || $amount <= 0) {
        return null;
    }

    $orderId = admin_momo_extract_order_id($orderIdentifier);
    if ($orderId <= 0) {
        return null;
    }

    $transactionRef = $payload['transId'] ?? ($payload['requestId'] ?? $orderIdentifier);
    $db = new connect_db();

    $existing = $db->xuatdulieu_prepared(
        "SELECT idTT FROM thanhtoan WHERE MaGiaoDich = ? LIMIT 1",
        [$transactionRef]
    );

    if (empty($existing)) {
        $db->tuychinh(
            "INSERT INTO thanhtoan (idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)
             VALUES (?, ?, 'transfer', 'completed', NOW(), ?)",
            [$orderId, $amount, $transactionRef]
        );
    }

    return $orderId;
}

$payload = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$extraDataRaw = $payload['extraData'] ?? '';
$decoded = [];
if ($extraDataRaw !== '') {
    $decoded = json_decode(base64_decode($extraDataRaw, true) ?: '', true);
    if (is_array($decoded) && isset($decoded['type']) && $decoded['type'] === 'order' && isset($decoded['order_id'])) {
        $payload['orderId'] = $payload['orderId'] ?? ('DH' . str_pad((string)(int)$decoded['order_id'], 5, '0', STR_PAD_LEFT));
    }
}

$recordedOrderId = admin_momo_record_payment($payload) ?? ($decoded['order_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'received',
        'order_id' => $recordedOrderId,
    ]);
    exit;
}

$scriptBase = dirname(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''))) ?: '';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = rtrim($scheme . '://' . $host . $scriptBase, '/') . '/';

$redirect = $baseUrl . ($recordedOrderId > 0
    ? 'index.php?page=order_payment&order_id=' . $recordedOrderId
    : 'index.php?page=dsdonhang');

$_SESSION['admin_flash'] = [
    'type' => 'info',
    'message' => 'Đã nhận phản hồi từ MoMo. Vui lòng kiểm tra trạng thái thanh toán của đơn.'
];

header('Location: ' . $redirect);
exit;
