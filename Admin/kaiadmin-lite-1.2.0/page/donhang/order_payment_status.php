<?php
require_once __DIR__ . '/../../includes/auth.php';
admin_auth_bootstrap_session();

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsDonHang.php';

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Thiếu mã đơn hàng'
    ]);
    exit;
}

$db = new connect_db();
$orderService = new clsDonHang();
$orderData = $orderService->getOrderById($orderId);

if (!$orderData) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không tìm thấy đơn hàng'
    ]);
    exit;
}

$totals = $orderService->computeOrderTotals($orderId);
$totalDue = (float)($totals['total'] ?? 0);
$bookingDeposit = $orderService->getBookingDepositSummary(isset($orderData['madatban']) ? (int)$orderData['madatban'] : null, $totalDue);
$remainingDue = max(0.0, $totalDue - ($bookingDeposit['paid'] ?? 0.0));
$paidRows = $db->xuatdulieu_prepared(
    "SELECT COALESCE(SUM(SoTien), 0) AS paid
     FROM thanhtoan
     WHERE idDH = ? AND TrangThai = 'completed'",
    [$orderId]
);
$paidAmount = (float)($paidRows[0]['paid'] ?? 0);

if ($remainingDue > 0
    && $paidAmount >= $remainingDue
    && $orderData['TrangThai'] !== clsDonHang::ORDER_STATUS_DONE) {
    try {
        $orderService->completeOrder($orderId, 'transfer', $_SESSION['nhanvien_id'] ?? null);
        $orderData = $orderService->getOrderById($orderId);
    } catch (Throwable $th) {
        error_log('[order_payment_status] Auto-complete order failed: ' . $th->getMessage());
    }
}

echo json_encode([
    'status' => 'success',
    'order_status' => $orderData['TrangThai'],
    'table_label' => $orderData['table_label'] ?? null,
    'total' => $totalDue,
    'paid_amount' => $paidAmount,
    'deposit_paid' => $bookingDeposit['paid'] ?? 0,
    'remaining_due' => $remainingDue
]);
