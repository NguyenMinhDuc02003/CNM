<?php
require_once __DIR__ . '/../../includes/auth.php';
admin_auth_bootstrap_session();

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsDonHang.php';

if (!function_exists('admin_order_call_momo_api')) {
    function admin_order_call_momo_api(string $endpoint, array $payload, int $maxRetries = 1): array
    {
        $rawResponse = '';
        $decodedResponse = [];
        $lastError = '';
        $lastErrno = 0;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            $body = json_encode($payload);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($body)
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
}

if (!function_exists('admin_redirect')) {
    function admin_redirect($url)
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        echo '<script>window.location.href = ' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        exit;
    }
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($orderId <= 0) {
    $_SESSION['admin_flash'] = [
        'type' => 'danger',
        'message' => 'Thiếu mã đơn hàng cần thanh toán.'
    ];
    admin_redirect('index.php?page=dsdonhang');
}

$db = new connect_db();
$orderService = new clsDonHang();
$orderData = $orderService->getOrderById($orderId);

if (!$orderData) {
    $_SESSION['admin_flash'] = [
        'type' => 'danger',
        'message' => 'Không tìm thấy đơn hàng #' . $orderId . '.'
    ];
    admin_redirect('index.php?page=dsdonhang');
}

if ($orderData['TrangThai'] === clsDonHang::ORDER_STATUS_DONE) {
    $_SESSION['admin_flash'] = [
        'type' => 'info',
        'message' => 'Đơn #' . $orderId . ' đã thanh toán xong.'
    ];
    admin_redirect('page/hoadon/xuatHD.php?idDH=' . $orderId . '&auto_print=1');
}

$totals = $orderService->computeOrderTotals($orderId);
$bookingDeposit = $orderService->getBookingDepositSummary(isset($orderData['madatban']) ? (int)$orderData['madatban'] : null, $totals['total']);
$totalAmount = (int)round(max(0, ($totals['total'] ?? 0) - ($bookingDeposit['paid'] ?? 0)));
if ($totalAmount <= 0) {
    $_SESSION['admin_flash'] = [
        'type' => 'info',
        'message' => 'Khách đã thanh toán đủ bằng tiền cọc, không cần tạo mã QR.'
    ];
    admin_redirect('index.php?page=moBan');
}

$momoConfig = [
    'partnerCode' => 'MOMOBKUN20180529',
    'accessKey' => 'klm05TvNBzhg7h7j',
    'secretKey' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
    'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
    'returnUrl' => 'http://localhost/CNM/Admin/kaiadmin-lite-1.2.0/page/donhang/order_payment_return.php',
    'notifyUrl' => 'http://localhost/CNM/Admin/kaiadmin-lite-1.2.0/page/donhang/order_payment_return.php'
];

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
if (!isset($_SESSION['order_momo_qr']) || !is_array($_SESSION['order_momo_qr'])) {
    $_SESSION['order_momo_qr'] = [];
}
if ($forceRefresh && isset($_SESSION['order_momo_qr'][$orderId])) {
    unset($_SESSION['order_momo_qr'][$orderId]);
}

$cachedQr = $_SESSION['order_momo_qr'][$orderId] ?? null;
$qrDataString = '';
$qrImageSrc = '';
$momoError = null;

if ($cachedQr && isset($cachedQr['pay_url']) && (time() - (int)($cachedQr['created_at'] ?? 0) < 600)) {
    $qrDataString = $cachedQr['pay_url'];
    $qrImageSrc = $cachedQr['qr_image'] ?? '';
    if ($qrImageSrc !== '' && stripos($qrImageSrc, 'http') !== 0) {
        $qrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($qrDataString);
        $_SESSION['order_momo_qr'][$orderId]['qr_image'] = $qrImageSrc;
    }
} else {
    $orderUid = sprintf('DH%05d_%d', $orderId, time());
    $requestId = $orderUid;
    $requestType = 'captureWallet';
    $extraData = base64_encode(json_encode([
        'type' => 'order',
        'order_id' => $orderId
    ]));
    $orderInfo = 'Thanh toan don tai ban #' . $orderId;

    $rawHash = "accessKey={$momoConfig['accessKey']}"
        . "&amount={$totalAmount}"
        . "&extraData={$extraData}"
        . "&ipnUrl={$momoConfig['notifyUrl']}"
        . "&orderId={$orderUid}"
        . "&orderInfo={$orderInfo}"
        . "&partnerCode={$momoConfig['partnerCode']}"
        . "&redirectUrl={$momoConfig['returnUrl']}"
        . "&requestId={$requestId}"
        . "&requestType={$requestType}";
    $signature = hash_hmac('sha256', $rawHash, $momoConfig['secretKey']);

    $payload = [
        'partnerCode' => $momoConfig['partnerCode'],
        'accessKey' => $momoConfig['accessKey'],
        'requestId' => $requestId,
        'amount' => (string)$totalAmount,
        'orderId' => $orderUid,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $momoConfig['returnUrl'],
        'ipnUrl' => $momoConfig['notifyUrl'],
        'extraData' => $extraData,
        'requestType' => $requestType,
        'lang' => 'vi',
        'signature' => $signature
    ];

    [$momoResponse, $rawResponse, $curlInfo] = admin_order_call_momo_api($momoConfig['endpoint'], $payload, 2);
    if (!is_array($momoResponse) || (int)($momoResponse['resultCode'] ?? 0) !== 0) {
        $momoError = $momoResponse['localMessage'] ?? $curlInfo['error'] ?? 'Không thể khởi tạo QR MoMo. Vui lòng thử lại.';
    } else {
        $qrDataString = $momoResponse['payUrl'] ?? ($momoResponse['qrCodeUrl'] ?? ($momoResponse['deeplink'] ?? ''));
        if ($qrDataString !== '') {
            $qrImageSrc = $momoResponse['qrCodeUrl'] ?? '';
            if ($qrImageSrc === '' || stripos($qrImageSrc, 'http') !== 0) {
                $qrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($qrDataString);
            }
            $_SESSION['order_momo_qr'][$orderId] = [
                'pay_url' => $qrDataString,
                'qr_image' => $qrImageSrc,
                'created_at' => time()
            ];
        } else {
            $momoError = 'MoMo không trả về đường dẫn thanh toán.';
        }
    }
}

if ($qrDataString === '') {
    $qrDataString = sprintf('ORDER|DH%05d|%d|0123456789', $orderId, $totalAmount);
}
if ($qrImageSrc === '') {
    $qrImageSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($qrDataString);
}

$tableLabel = $orderData['table_label'] ?? ('Bàn #' . ($orderData['SoBan'] ?? $orderData['idban']));
$customerName = $orderData['tenKH'] ?? 'Khách tại bàn';
$orderCode = $orderData['MaDonHang'] ?? ('DH' . $orderId);
$staffId = $_SESSION['pending_transfer_order']['staff_id'] ?? ($_SESSION['nhanvien_id'] ?? null);
$errorMessage = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'finalize') {
    try {
        $orderService->completeOrder($orderId, 'transfer', $staffId);
        unset($_SESSION['open_table_flow']);
        unset($_SESSION['pending_transfer_order']);
        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => 'Đã xác nhận thanh toán chuyển khoản đơn #' . $orderId . '.'
        ];
        admin_redirect('page/hoadon/xuatHD.php?idDH=' . $orderId . '&auto_print=1');
    } catch (Throwable $th) {
        $errorMessage = $th->getMessage();
    }
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="mb-1"><i class="fas fa-qrcode me-2 text-primary"></i>Thanh toán chuyển khoản - Đơn <?php echo htmlspecialchars($orderCode); ?></h3>
            <small class="text-muted">Yêu cầu khách quét mã QR MoMo để thanh toán phần còn lại của đơn hàng.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?page=order_payment&order_id=<?php echo $orderId; ?>&refresh=1" class="btn btn-outline-primary"><i class="fas fa-sync-alt me-1"></i>Tạo QR mới</a>
            <a href="index.php?page=moBan" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Quay lại sơ đồ bàn</a>
        </div>
    </div>

    <?php if ($errorMessage): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>
    <?php if ($momoError): ?>
        <div class="alert alert-warning"><?php echo htmlspecialchars($momoError); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <span class="badge bg-warning text-dark text-uppercase mb-3">QR Thanh toán</span>
                    <div class="mb-3 fw-semibold">Số tiền cần thu</div>
                    <h2 class="text-primary mb-4"><?php echo number_format($totalAmount); ?>đ</h2>
                    <?php if (($bookingDeposit['paid'] ?? 0) > 0): ?>
                        <p class="text-muted small">
                            Tổng hóa đơn: <?php echo number_format($totals['total'] ?? 0); ?>đ &bull;
                            Đã cọc: <?php echo number_format($bookingDeposit['paid'], 0, ',', '.'); ?>đ
                        </p>
                    <?php endif; ?>
                    <img src="<?php echo htmlspecialchars($qrImageSrc); ?>" alt="QR chuyển khoản" class="img-fluid mb-3" style="max-width: 320px;">
                    <p class="text-muted small mb-1">Hoặc mở trực tiếp đường dẫn thanh toán:</p>
                    <p class="small">
                        <a href="<?php echo htmlspecialchars($qrDataString); ?>" target="_blank" rel="noopener">
                            <?php echo htmlspecialchars($qrDataString); ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-receipt me-2 text-primary"></i>Thông tin đơn</h5>
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted">Mã đơn</dt>
                        <dd class="col-7 fw-semibold"><?php echo htmlspecialchars($orderCode); ?></dd>
                        <dt class="col-5 text-muted">Khách hàng</dt>
                        <dd class="col-7 fw-semibold"><?php echo htmlspecialchars($customerName); ?></dd>
                        <dt class="col-5 text-muted">Bàn/Khu vực</dt>
                        <dd class="col-7 fw-semibold"><?php echo htmlspecialchars($tableLabel); ?></dd>
                        <dt class="col-5 text-muted">Số khách</dt>
                        <dd class="col-7 fw-semibold"><?php echo (int)($orderData['people_count'] ?? 0); ?></dd>
                        <dt class="col-5 text-muted">Tạm tính món</dt>
                        <dd class="col-7 fw-semibold"><?php echo number_format($totals['subtotal'] ?? 0); ?>đ</dd>
                        <dt class="col-5 text-muted">Phụ thu</dt>
                        <dd class="col-7 fw-semibold"><?php echo number_format($totals['surcharge'] ?? 0); ?>đ</dd>
                    </dl>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-check-circle me-2 text-success"></i>Xác nhận thanh toán</h5>
                    <form method="post">
                        <input type="hidden" name="action" value="finalize">
                        <p class="text-muted small mb-3">Sau khi kiểm tra tài khoản và xác nhận đã nhận đủ tiền, nhấn nút bên dưới để hoàn tất đơn và in hóa đơn.</p>
                        <button type="submit" class="btn btn-success w-100" id="confirmPaymentBtn">
                            <i class="fas fa-money-check-alt me-2"></i>Đã nhận đủ &amp; in hóa đơn
                        </button>
                    </form>
                    <div class="text-muted small mt-3" id="autoCheckStatus">
                        <i class="fas fa-sync-alt fa-spin me-1"></i>Đang tự động kiểm tra trạng thái thanh toán...
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const statusEndpoint = 'page/donhang/order_payment_status.php?order_id=<?php echo $orderId; ?>';
        const invoiceUrl = 'page/hoadon/xuatHD.php?idDH=<?php echo $orderId; ?>&auto_print=1';
        let isRedirecting = false;

        function redirectToInvoice() {
            if (isRedirecting) {
                return;
            }
            isRedirecting = true;
            window.location.href = invoiceUrl;
        }

        async function pollStatus() {
            if (isRedirecting) {
                return;
            }
            try {
                const response = await fetch(statusEndpoint, { cache: 'no-store' });
                if (!response.ok) {
                    throw new Error('Network error');
                }
                const payload = await response.json();
                if (payload && payload.status === 'success' && payload.order_status === 'hoan_thanh') {
                    redirectToInvoice();
                    return;
                }
            } catch (err) {
                console.warn('pollStatus error', err);
            }
            setTimeout(pollStatus, 5000);
        }

        pollStatus();
    })();
</script>
