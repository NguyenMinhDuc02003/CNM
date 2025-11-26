<?php
admin_auth_bootstrap_session();

$adminReturnUrl = $_SESSION['admin_payment_return'] ?? 'index.php?page=dsdatban';
if (isset($_GET['madatban'])) {
    $_SESSION['madatban'] = (int)$_GET['madatban'];
}

function admin_payment_abort(string $message, string $redirectUrl): void
{
    echo '<div class="container py-4">';
    echo '<div class="alert alert-warning">' . htmlspecialchars($message) . '</div>';
    echo '<a class="btn btn-primary" href="' . htmlspecialchars($redirectUrl) . '"><i class="fas fa-arrow-left me-2"></i>Quay lại</a>';
    echo '</div>';
    exit;
}

function admin_payment_redirect(string $url): void
{
    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        echo '<script>window.location.href = ' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    }
    exit;
}

if (!isset($_SESSION['madatban'])) {
    admin_payment_abort('Không tìm thấy thông tin thanh toán.', $adminReturnUrl);
}

$madatban = (int)$_SESSION['madatban'];
$paymentExpiresAt = $_SESSION['payment_expires'] ?? null;

if ($paymentExpiresAt !== null) {
    $now = new DateTime();
    $expires = new DateTime($paymentExpiresAt);
    if ($now > $expires) {
        unset($_SESSION['madatban'], $_SESSION['payment_method'], $_SESSION['payment_expires']);
        admin_payment_abort('Đơn đặt bàn đã hết hạn thanh toán. Vui lòng tạo lại.', $adminReturnUrl);
    }
}

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsdatban.php';

$db = new connect_db();
$bookingModel = new datban();

$vendorAutoload = dirname(__DIR__, 3) . '/../User/restoran-1.0.0/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

error_log('[ADMIN_PAYMENT] Start payment page for booking #' . $madatban);

$bookingRows = $db->xuatdulieu_prepared(
    "SELECT
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
         JOIN ban b ON ct.idban = b.idban
         WHERE ct.madatban = db.madatban) AS danh_sach_ban
     FROM datban db
     WHERE db.madatban = ?",
    [$madatban]
);

if (empty($bookingRows)) {
    admin_payment_abort('Không tìm thấy thông tin đơn đặt bàn.', $adminReturnUrl);
}

$booking = $bookingRows[0];
$totalAmount = (int)$booking['tongtien'];
$requiredDeposit = (int)ceil($totalAmount * 0.5);

$paymentHistory = $db->xuatdulieu_prepared(
    "SELECT COALESCE(SUM(SoTien), 0) AS paid_amount
     FROM thanhtoan
     WHERE madatban = ? AND TrangThai = 'completed'",
    [$madatban]
);
$paidAmount = (int)($paymentHistory[0]['paid_amount'] ?? 0);
$amountToPay = max(0, $requiredDeposit - $paidAmount);
$remainingAfterDeposit = max(0, $totalAmount - $requiredDeposit);

if ($amountToPay <= 0) {
    error_log(sprintf('[ADMIN_PAYMENT] Booking #%d already has enough deposit (required=%d, paid=%d). Redirecting.', $madatban, $requiredDeposit, $paidAmount));
    unset($_SESSION['madatban'], $_SESSION['payment_method'], $_SESSION['payment_expires']);
    if (isset($_SESSION['admin_momo_qr'][$madatban])) {
        unset($_SESSION['admin_momo_qr'][$madatban]);
    }
    if (isset($_SESSION['admin_momo_order_time'][$madatban])) {
        unset($_SESSION['admin_momo_order_time'][$madatban]);
    }
    $_SESSION['admin_flash'] = [
        'type' => 'info',
        'message' => 'Đơn đặt bàn #' . $madatban . ' đã đủ mức đặt cọc. Không cần thanh toán thêm.'
    ];
    admin_payment_redirect($adminReturnUrl);
}

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
if (!isset($_SESSION['admin_momo_qr']) || !is_array($_SESSION['admin_momo_qr'])) {
    $_SESSION['admin_momo_qr'] = [];
}
if (!isset($_SESSION['admin_momo_order_time']) || !is_array($_SESSION['admin_momo_order_time'])) {
    $_SESSION['admin_momo_order_time'] = [];
}
if ($forceRefresh) {
    if (isset($_SESSION['admin_momo_qr'][$madatban])) {
        unset($_SESSION['admin_momo_qr'][$madatban]);
    }
    if (isset($_SESSION['admin_momo_order_time'][$madatban])) {
        unset($_SESSION['admin_momo_order_time'][$madatban]);
    }
}
$cachedQr = $_SESSION['admin_momo_qr'][$madatban] ?? null;
$qrCodeBase64 = '';
$qrDataString = '';
$usedCachedQr = false;

if (!$forceRefresh && $cachedQr) {
    $cachedAmount = $cachedQr['amount'] ?? null;
    $cachedExpiry = $cachedQr['expires_at'] ?? null;
    $cachedPayUrl = $cachedQr['pay_url'] ?? '';
    $cachedBase64 = $cachedQr['qr_base64'] ?? '';
    if ((int)$cachedAmount === $amountToPay && ($cachedExpiry === ($paymentExpiresAt ?? null)) && $cachedPayUrl !== '' && $cachedBase64 !== '') {
        $qrCodeBase64 = $cachedBase64;
        $qrDataString = $cachedPayUrl;
        $usedCachedQr = true;
        error_log(sprintf('[ADMIN_PAYMENT] Reusing cached QR for booking #%d', $madatban));
    } else {
        unset($_SESSION['admin_momo_qr'][$madatban]);
    }
}

$momoConfig = [
    'partnerCode' => 'MOMOBKUN20180529',
    'accessKey' => 'klm05TvNBzhg7h7j',
    'secretKey' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
    'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
    'returnUrl' => sprintf(
        '%s://%s%s/page/datban/admin_payment_callback.php',
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
        $_SERVER['HTTP_HOST'] ?? 'localhost',
        rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\')
    ),
    'notifyUrl' => sprintf(
        '%s://%s%s/page/datban/admin_payment_callback.php',
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http',
        $_SERVER['HTTP_HOST'] ?? 'localhost',
        rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\')
    )
];

function admin_call_momo_api(string $endpoint, array $payload, int $maxRetries = 1): array
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

        if ($rawResponse !== false && $rawResponse !== '') {
            $decodedResponse = json_decode($rawResponse, true);
            $errorCode = $decodedResponse['errorCode'] ?? null;
            if (is_array($decodedResponse) && ($errorCode === 0 || $errorCode === '0')) {
                return [$decodedResponse, $rawResponse, ['error' => '', 'errno' => 0]];
            }
            if ($errorCode === 98 && $attempt < $maxRetries) {
                sleep(1);
                continue;
            }
            break;
        }

        if ($attempt < $maxRetries) {
            sleep(1);
        } else {
            break;
        }
    }

    return [$decodedResponse, $rawResponse, ['error' => $lastError, 'errno' => $lastErrno]];
}

$paymentError = null;

if (!$usedCachedQr) {
    date_default_timezone_set('Asia/Ho_Chi_Minh');
    $baseTimestamp = time();
    $lastTimestamp = $_SESSION['admin_momo_order_time'][$madatban] ?? 0;
    if ($baseTimestamp <= $lastTimestamp) {
        $baseTimestamp = $lastTimestamp + 1;
    }
    $_SESSION['admin_momo_order_time'][$madatban] = $baseTimestamp;

    $orderId = sprintf('%d_%d', $madatban, $baseTimestamp);
    $requestId = $orderId . '_' . strtoupper(bin2hex(random_bytes(2)));
    $orderInfo = 'Dat coc dat ban (ADMIN) #' . $madatban;
    $requestType = 'captureWallet';
    $extraData = (string)$madatban;

    $rawHash = "accessKey=" . $momoConfig['accessKey'] .
        "&amount=" . $amountToPay .
        "&extraData=" . $extraData .
        "&ipnUrl=" . $momoConfig['notifyUrl'] .
        "&orderId=" . $orderId .
        "&orderInfo=" . $orderInfo .
        "&partnerCode=" . $momoConfig['partnerCode'] .
        "&redirectUrl=" . $momoConfig['returnUrl'] .
        "&requestId=" . $requestId .
        "&requestType=" . $requestType;

    $signature = hash_hmac('sha256', $rawHash, $momoConfig['secretKey']);

    $payload = [
        'partnerCode' => $momoConfig['partnerCode'],
        'partnerName' => 'Restoran',
        'storeId' => 'RESTORAN_ADMIN',
        'requestId' => $requestId,
        'amount' => (string)$amountToPay,
        'orderId' => $orderId,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $momoConfig['returnUrl'],
        'ipnUrl' => $momoConfig['notifyUrl'],
        'lang' => 'vi',
        'extraData' => $extraData,
        'requestType' => $requestType,
        'signature' => $signature
    ];

    [$momoResponse, $rawResponse, $curlInfo] = admin_call_momo_api($momoConfig['endpoint'], $payload, 2);
    error_log('[ADMIN_PAYMENT] MoMo payload: ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
    error_log('[ADMIN_PAYMENT] MoMo raw response: ' . ($rawResponse === false ? 'false' : $rawResponse));
    error_log('[ADMIN_PAYMENT] MoMo curl info: ' . json_encode($curlInfo));

    if (!is_array($momoResponse) || (($momoResponse['errorCode'] ?? null) != 0)) {
        $paymentError = $momoResponse['localMessage'] ?? $curlInfo['error'] ?? 'Không thể khởi tạo thanh toán QR. Vui lòng thử lại.';
        error_log('[ADMIN_PAYMENT] MoMo returned error: ' . $paymentError);
    } else {
        $payUrl = $momoResponse['payUrl'] ?? '';
        $deepLink = $momoResponse['deeplink'] ?? '';
        $qrCodeUrl = $momoResponse['qrCodeUrl'] ?? '';
        if ($payUrl !== '') {
            $qrDataString = $payUrl;
        } elseif ($qrCodeUrl !== '') {
            $qrDataString = $qrCodeUrl;
        } else {
            $qrDataString = $deepLink;
        }
        error_log('[ADMIN_PAYMENT] MoMo QR sources: payUrl=' . $payUrl . ', qrCodeUrl=' . $qrCodeUrl . ', deepLink=' . $deepLink);

        if ($qrDataString !== '') {
            try {
                if (!class_exists('\Endroid\QrCode\QrCode') || !class_exists('\Endroid\QrCode\Writer\PngWriter')) {
                    throw new Exception('Endroid QR library not available');
                }
                $qrCode = new \Endroid\QrCode\QrCode($qrDataString);
                $qrCode->setSize(320);
                $qrCode->setMargin(10);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $result = $writer->write($qrCode);
                $qrCodeBase64 = $result->getDataUri();
                error_log('[ADMIN_PAYMENT] Generated QR via Endroid.');
            } catch (\Throwable $th) {
                error_log('[ADMIN_PAYMENT] Endroid QR generation failed: ' . $th->getMessage());
                if (extension_loaded('gd')) {
                    try {
                        $im = imagecreatetruecolor(320, 320);
                        $bg = imagecolorallocate($im, 255, 255, 255);
                        $text = imagecolorallocate($im, 0, 0, 0);
                        imagefilledrectangle($im, 0, 0, 320, 320, $bg);
                        imagestring($im, 5, 20, 150, 'Scan to pay', $text);
                        ob_start();
                        imagepng($im);
                        $imageData = ob_get_clean();
                        imagedestroy($im);
                        $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($imageData);
                        error_log('[ADMIN_PAYMENT] Generated fallback QR via GD text.');
                    } catch (\Throwable $gdEx) {
                        error_log('[ADMIN_PAYMENT] GD fallback failed: ' . $gdEx->getMessage());
                    }
                }
                if ($qrCodeBase64 === '' && $qrCodeUrl !== '') {
                    $qrCodeBase64 = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($qrDataString);
                    error_log('[ADMIN_PAYMENT] Using qrserver fallback URL.');
                }
            }
        }

        if ($qrCodeBase64 !== '' && $qrDataString !== '') {
            $_SESSION['admin_momo_qr'][$madatban] = [
                'pay_url' => $qrDataString,
                'qr_base64' => $qrCodeBase64,
                'order_id' => $orderId,
                'request_id' => $requestId,
                'extra_data' => $extraData,
                'order_timestamp' => $baseTimestamp,
                'amount' => $amountToPay,
                'expires_at' => $paymentExpiresAt,
                'created_at' => time()
            ];
        } else {
            unset($_SESSION['admin_momo_qr'][$madatban]);
            if (isset($_SESSION['admin_momo_order_time'][$madatban])) {
                unset($_SESSION['admin_momo_order_time'][$madatban]);
            }
        }
    }
} else {
    $orderId = $cachedQr['order_id'] ?? null;
    $requestId = $cachedQr['request_id'] ?? null;
    $extraData = isset($cachedQr['extra_data']) ? (string)$cachedQr['extra_data'] : (string)$madatban;
    $qrCodeBase64 = $cachedQr['qr_base64'] ?? '';
    $qrDataString = $cachedQr['pay_url'] ?? '';
    if (isset($cachedQr['order_timestamp'])) {
        $_SESSION['admin_momo_order_time'][$madatban] = $cachedQr['order_timestamp'];
    }
}

$paymentExpiresTimestamp = $paymentExpiresAt ? strtotime($paymentExpiresAt) : strtotime('+30 minutes');
error_log(sprintf('[ADMIN_PAYMENT] Render page with paymentExpires=%s, qrData=%s', $paymentExpiresAt ?? 'null', $qrCodeBase64 !== '' ? 'available' : 'missing'));

?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="fas fa-qrcode me-2 text-primary"></i>Thanh toán chuyển khoản - Đơn #<?php echo htmlspecialchars($madatban); ?></h4>
            <small class="text-muted">Vui lòng yêu cầu khách quét mã QR để đặt cọc giữ bàn.</small>
        </div>
        <div>
            <a href="index.php?page=admin_payment&madatban=<?php echo $madatban; ?>&refresh=1" class="btn btn-outline-primary me-2">
                <i class="fas fa-sync-alt me-1"></i>Tạo QR mới
            </a>
            <a href="<?php echo htmlspecialchars($adminReturnUrl); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
        </div>
    </div>

    <?php if ($paymentError): ?>
        <div class="alert alert-danger">
            <strong>Lỗi khởi tạo thanh toán:</strong> <?php echo htmlspecialchars($paymentError); ?><br>
            <small class="text-muted">Kiểm tra kết nối mạng hoặc thông tin cấu hình MoMo.</small>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <span class="badge bg-warning text-dark text-uppercase">Thanh toán MoMo QR</span>
                                <h5 class="mt-2 mb-1">Quét QR cho khách</h5>
                                <small class="text-muted">Mã QR hết hạn trong <span id="countdownText">...</span></small>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">Số tiền cọc cần thu</div>
                                <h4 class="text-primary mb-0"><?php echo number_format($amountToPay); ?>đ</h4>
                            </div>
                        </div>

                        <div class="text-center my-4">
                            <?php if ($qrCodeBase64 !== ''): ?>
                                <?php $isDataUri = strpos($qrCodeBase64, 'data:image') === 0; ?>
                                <img src="<?php echo $isDataUri ? $qrCodeBase64 : htmlspecialchars($qrCodeBase64); ?>" alt="QR MoMo" class="img-fluid" style="max-width: 320px;">
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    Không thể tải mã QR. Vui lòng thử lại.
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($qrDataString !== ''): ?>
                            <p class="text-center small text-muted mb-0">
                                Hoặc mở liên kết trực tiếp: <a href="<?php echo htmlspecialchars($qrDataString); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($qrDataString); ?></a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3"><i class="fas fa-receipt me-2 text-primary"></i>Thông tin đơn đặt bàn</h6>
                        <div class="mb-3">
                            <div class="text-muted small">Khách hàng</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($booking['tenKH'] ?? 'Khách'); ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($booking['sodienthoai'] ?? ''); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Thời gian</div>
                            <div class="fw-semibold"><?php echo date('d/m/Y H:i', strtotime($booking['thoigian'])); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Số khách &amp; bàn</div>
                            <div class="fw-semibold"><?php echo (int)$booking['soluongKH']; ?> khách</div>
                            <div class="small text-muted"><?php echo htmlspecialchars($booking['danh_sach_ban'] ?? 'Chưa gán'); ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Tổng tạm tính</div>
                            <div class="fw-semibold"><?php echo number_format($totalAmount); ?>đ</div>
                            <div class="small text-muted">Đặt cọc yêu cầu: <?php echo number_format($requiredDeposit); ?>đ</div>
                            <div class="small text-muted">Đã thu: <?php echo number_format($paidAmount); ?>đ</div>
                            <div class="small text-muted text-danger">Còn lại sau đặt cọc: <?php echo number_format($remainingAfterDeposit); ?>đ</div>
                        </div>
                        <div class="border-top pt-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div class="text-muted small">Trạng thái</div>
                                    <div id="statusChecking" class="text-warning small" style="display:none;">
                                        <i class="fas fa-spinner fa-spin me-1"></i>Đang kiểm tra thanh toán...
                                    </div>
                                    <div id="successMessage" class="text-success small fw-semibold" style="display:none;">
                                        <i class="fas fa-check-circle me-1"></i>Thanh toán thành công! Đang xử lý...
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.location.reload();">
                                    <i class="fas fa-sync-alt me-1"></i>Làm mới
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="paymentSuccessModal" tabindex="-1" aria-labelledby="paymentSuccessLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="paymentSuccessLabel"><i class="fas fa-check-circle text-success me-2"></i>Thanh toán đặt cọc thành công</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-0">
                <p class="mb-2">Hệ thống đã ghi nhận khoản đặt cọc cho đơn #<?php echo htmlspecialchars($madatban); ?>.</p>
                <p class="mb-0 text-muted small">Bạn có thể xem và quản lý đơn trong danh sách đặt bàn.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-success w-100" id="paymentSuccessConfirm">
                    <i class="fas fa-arrow-right me-1"></i>Về danh sách đặt bàn
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const expiryTime = <?php echo (int)$paymentExpiresTimestamp * 1000; ?>;
    function updateCountdown() {
        const now = new Date().getTime();
        const timeLeft = expiryTime - now;

        const countdownText = document.getElementById('countdownText');
        if (!countdownText) return;

        if (timeLeft <= 0) {
            countdownText.innerHTML = 'ĐÃ HẾT HẠN';
            countdownText.classList.add('text-danger');
            return;
        }

        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

        countdownText.textContent = `${hours} giờ ${minutes} phút ${seconds} giây`;
    }
    setInterval(updateCountdown, 1000);
    updateCountdown();

    const checkEndpoint = 'page/datban/admin_payment_callback.php?action=check_status&madatban=<?php echo $madatban; ?>';
    const finalizeEndpoint = 'page/datban/admin_payment_callback.php?action=finalize&madatban=<?php echo $madatban; ?>';
    const detailUrl = 'index.php?page=dsdatban';
    let statusCheckInterval = null;
    let successHandled = false;

    function handlePaymentSuccess(successEl, statusEl) {
        if (successHandled) {
            return;
        }
        successHandled = true;
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            statusCheckInterval = null;
        }
        if (statusEl) {
            statusEl.style.display = 'none';
        }
        if (successEl) {
            successEl.style.display = 'block';
        }

        fetch(finalizeEndpoint, { method: 'POST', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .finally(() => {
                const modalEl = document.getElementById('paymentSuccessModal');
                const confirmBtn = document.getElementById('paymentSuccessConfirm');
                if (modalEl && window.bootstrap) {
                    const modalInstance = new bootstrap.Modal(modalEl, { backdrop: 'static', keyboard: false });
                    modalInstance.show();
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', () => {
                            window.location.href = detailUrl;
                        }, { once: true });
                    }
                    modalEl.addEventListener('hidden.bs.modal', () => {
                        window.location.href = detailUrl;
                    }, { once: true });
                } else {
                    window.location.href = detailUrl;
                }
            });
    }

    function checkPaymentStatus() {
        const statusEl = document.getElementById('statusChecking');
        const successEl = document.getElementById('successMessage');
        if (statusEl && !successHandled) {
            statusEl.style.display = 'block';
        }

        fetch(checkEndpoint, { method: 'GET', headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(res => res.json())
            .then(data => {
                if (!data || data.status !== 'success' || successHandled) {
                    return;
                }
                if (data.deposit_status === 'paid') {
                    handlePaymentSuccess(successEl, statusEl);
                }
            })
            .catch(() => {})
            .finally(() => {
                if (statusEl && !successHandled) {
                    statusEl.style.display = 'none';
                }
            });
    }

    statusCheckInterval = setInterval(checkPaymentStatus, 5000);
    window.addEventListener('beforeunload', () => {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
        }
    });
    checkPaymentStatus();
</script>
