<?php
require_once __DIR__ . '/../../includes/auth.php';
admin_auth_bootstrap_session();

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsdatban.php';

$db = new connect_db();

function admin_payment_compute_finance(connect_db $db, int $madatban): ?array
{
    $bookingRows = $db->xuatdulieu_prepared(
        "SELECT madatban, TrangThai, TongTien FROM datban WHERE madatban = ? LIMIT 1",
        [$madatban]
    );

    if (empty($bookingRows)) {
        return null;
    }

    $booking = $bookingRows[0];
    $totalAmount = (float)($booking['TongTien'] ?? 0);
    $requiredDeposit = (int)ceil($totalAmount * 0.5);

    $paymentRows = $db->xuatdulieu_prepared(
        "SELECT COALESCE(SUM(SoTien),0) AS paid_amount
         FROM thanhtoan
         WHERE madatban = ? AND TrangThai = 'completed'",
        [$madatban]
    );
    $paidAmount = (int)($paymentRows[0]['paid_amount'] ?? 0);
    $amountToPay = max(0, $requiredDeposit - $paidAmount);

    return [
        'booking' => $booking,
        'total_amount' => (int)$totalAmount,
        'required_deposit' => $requiredDeposit,
        'paid_amount' => $paidAmount,
        'amount_to_pay' => $amountToPay,
    ];
}

function admin_payment_update_deposit_note(connect_db $db, int $madatban, int $depositAmount): void
{
    if ($madatban <= 0 || $depositAmount <= 0) {
        return;
    }

    $depositLine = 'Đã cọc 50% số tiền: ' . number_format($depositAmount) . 'đ (cập nhật ' . date('d/m/Y H:i') . ')';

    $metaRows = $db->xuatdulieu_prepared(
        "SELECT id, note FROM datban_admin_meta WHERE madatban = ? LIMIT 1",
        [$madatban]
    );

    if (!empty($metaRows)) {
        $existingNote = $metaRows[0]['note'] ?? '';
        $existingNote = is_string($existingNote) ? trim($existingNote) : '';

        $updatedNote = $existingNote;
        $pattern = '/^Đã cọc 50%.*$/mu';
        $updatedNote = preg_replace($pattern, $depositLine, $updatedNote, -1, $replacedCount);
        if ($replacedCount === 0) {
            $updatedNote = $updatedNote === '' ? $depositLine : $updatedNote . PHP_EOL . $depositLine;
        }

        $db->tuychinh(
            "UPDATE datban_admin_meta SET note = ? WHERE madatban = ?",
            [$updatedNote, $madatban]
        );
    } else {
        $db->tuychinh(
            "INSERT INTO datban_admin_meta (madatban, booking_channel, payment_method, note)
             VALUES (?, 'phone', 'transfer', ?)",
            [$madatban, $depositLine]
        );
    }
}

$action = $_GET['action'] ?? null;
$madatban = isset($_GET['madatban']) ? (int)$_GET['madatban'] : (isset($_POST['madatban']) ? (int)$_POST['madatban'] : 0);

if (in_array($action, ['check_status', 'finalize'], true) && !admin_auth_is_logged_in()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit;
}

if ($action === 'check_status') {
    header('Content-Type: application/json; charset=utf-8');
    if ($madatban <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Mã đơn không hợp lệ']);
        exit;
    }

    $finance = admin_payment_compute_finance($db, $madatban);
    if ($finance === null) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy đơn đặt bàn']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'booking_status' => $finance['booking']['TrangThai'],
        'required_deposit' => $finance['required_deposit'],
        'paid_amount' => $finance['paid_amount'],
        'amount_to_pay' => $finance['amount_to_pay'],
        'deposit_status' => $finance['amount_to_pay'] <= 0 ? 'paid' : 'pending'
    ]);
    exit;
}

if ($action === 'finalize') {
    header('Content-Type: application/json; charset=utf-8');
    if ($madatban <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Mã đơn không hợp lệ']);
        exit;
    }

    $finance = admin_payment_compute_finance($db, $madatban);
    if ($finance === null) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy đơn đặt bàn']);
        exit;
    }

    if ($finance['amount_to_pay'] > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Đơn vẫn chưa đủ mức đặt cọc.']);
        exit;
    }

    if (($finance['booking']['TrangThai'] ?? '') !== 'confirmed') {
        $db->tuychinh(
            "UPDATE datban SET TrangThai = 'confirmed', payment_expires = NULL WHERE madatban = ?",
            [$madatban]
        );
    }
    $depositAmount = (int)min(
        max($finance['required_deposit'], 0),
        max($finance['paid_amount'], 0)
    );
    admin_payment_update_deposit_note($db, $madatban, $depositAmount);
    if (isset($_SESSION['admin_momo_qr'][$madatban])) {
        unset($_SESSION['admin_momo_qr'][$madatban]);
    }
    if (isset($_SESSION['admin_momo_order_time'][$madatban])) {
        unset($_SESSION['admin_momo_order_time'][$madatban]);
    }

    $_SESSION['admin_flash'] = [
        'type' => 'success',
        'message' => 'Đã ghi nhận khoản đặt cọc cho đơn #' . $madatban . '.'
    ];

    echo json_encode([
        'status' => 'success',
        'redirect_url' => 'index.php?page=dsdatban'
    ]);
    exit;
}

$momoConfig = [
    'partnerCode' => 'MOMOBKUN20180529',
    'accessKey' => 'klm05TvNBzhg7h7j',
    'secretKey' => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa'
];

// MoMo callback / return handling
$resultCode = $_REQUEST['resultCode'] ?? null;
$orderId = $_REQUEST['orderId'] ?? null;

if ($resultCode !== null && $orderId !== null) {
    $amount = $_REQUEST['amount'] ?? 0;
    $amountInt = (int)$amount;
    $orderInfo = $_REQUEST['orderInfo'] ?? '';
    $transId = $_REQUEST['transId'] ?? '';
    $message = $_REQUEST['message'] ?? '';
    $signature = $_REQUEST['signature'] ?? '';
    $extraData = $_REQUEST['extraData'] ?? '';
    $orderType = $_REQUEST['orderType'] ?? '';
    $payType = $_REQUEST['payType'] ?? '';
    $partnerCode = $_REQUEST['partnerCode'] ?? '';
    $requestId = $_REQUEST['requestId'] ?? '';
    $responseTime = $_REQUEST['responseTime'] ?? '';

    $rawHash = "accessKey=" . $momoConfig['accessKey'] .
               "&amount=" . $amount .
               "&extraData=" . $extraData .
               "&message=" . $message .
               "&orderId=" . $orderId .
               "&orderInfo=" . $orderInfo .
               "&orderType=" . $orderType .
               "&partnerCode=" . $partnerCode .
               "&payType=" . $payType .
               "&requestId=" . $requestId .
               "&responseTime=" . $responseTime .
               "&resultCode=" . $resultCode .
               "&transId=" . $transId;

    $computedSignature = hash_hmac('sha256', $rawHash, $momoConfig['secretKey']);

    if (!hash_equals($computedSignature, (string)$signature)) {
        http_response_code(400);
        echo 'INVALID_SIGNATURE';
        exit;
    }

    $madatbanFromOrder = 0;
    $extraDataBooking = isset($_REQUEST['extraData']) ? (int)$_REQUEST['extraData'] : 0;
    if ($extraDataBooking > 0) {
        $madatbanFromOrder = $extraDataBooking;
    } else {
        $candidate = $orderId;
        if (strpos($candidate, '_') !== false) {
            $parts = explode('_', $candidate);
            $candidate = $parts[0];
        }
        if (preg_match('/(\d+)/', (string)$candidate, $matches)) {
            $madatbanFromOrder = (int)$matches[1];
        }
    }

    if ((int)$resultCode === 0) {
        $existingTxn = [];
        if ($transId !== '') {
            $existingTxn = $db->xuatdulieu_prepared(
                "SELECT MaGiaoDich FROM thanhtoan WHERE MaGiaoDich = ? LIMIT 1",
                [$transId]
            );
        }

        $db->beginTransaction();
        $depositAmount = 0;
        try {
            if (empty($existingTxn)) {
                $db->tuychinh(
                    "INSERT INTO thanhtoan (madatban, idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)
                     VALUES (?, NULL, ?, 'momo_admin', 'completed', NOW(), ?)",
                    [$madatbanFromOrder, (float)$amount, $transId]
                );
            }

            $finance = admin_payment_compute_finance($db, $madatbanFromOrder);
            if ($finance !== null && $finance['amount_to_pay'] <= 0) {
                $db->tuychinh(
                    "UPDATE datban SET TrangThai = 'confirmed', payment_expires = NULL WHERE madatban = ?",
                    [$madatbanFromOrder]
                );
            }
            if (isset($_SESSION['admin_momo_qr'][$madatbanFromOrder])) {
                unset($_SESSION['admin_momo_qr'][$madatbanFromOrder]);
            }
            if (isset($_SESSION['admin_momo_order_time'][$madatbanFromOrder])) {
                unset($_SESSION['admin_momo_order_time'][$madatbanFromOrder]);
            }

            $db->commit();
            $financeAfter = admin_payment_compute_finance($db, $madatbanFromOrder);
            if ($financeAfter !== null) {
                $depositAmount = (int)min(
                    max($financeAfter['required_deposit'], 0),
                    max($financeAfter['paid_amount'], 0)
                );
                if ($depositAmount <= 0 && isset($amount)) {
                    $depositAmount = (int)$amount;
                }
                admin_payment_update_deposit_note($db, $madatbanFromOrder, $depositAmount);
            }
            $datbanModel = new datban();
            $successSnapshot = $datbanModel->getAdminBookingSnapshot($madatbanFromOrder);
            $successTotalAmount = (int)($financeAfter['total_amount'] ?? ($successSnapshot['booking']['TongTien'] ?? 0));
            $successDepositAmount = $depositAmount > 0 ? $depositAmount : $amountInt;
        } catch (Exception $e) {
            $db->rollback();
            error_log('MoMo admin callback error: ' . $e->getMessage());
            http_response_code(500);
            echo 'ERROR';
            exit;
        }

        $_SESSION['admin_flash'] = [
            'type' => 'success',
            'message' => 'Đặt cọc MoMo cho đơn #' . $madatbanFromOrder . ' đã được xác nhận.'
        ];

        if (strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
            echo 'success';
            exit;
        }

        $adminListUrl = '../../index.php?page=dsdatban';
        $tablesInfo = $successSnapshot['tables'] ?? [];
        $menuItemsInfo = $successSnapshot['menu_items'] ?? [];
        $metaInfo = $successSnapshot['meta'] ?? [];
        $menuModeSummary = $metaInfo['menu_mode'] ?? 'none';
        $menuSnapshotData = [];
        if (!empty($metaInfo['menu_snapshot'])) {
            $decodedMenuSnap = json_decode($metaInfo['menu_snapshot'], true);
            if (is_array($decodedMenuSnap)) {
                $menuSnapshotData = $decodedMenuSnap;
            }
        }
        $tableLabels = [];
        $tableCapacitySum = 0;
        foreach ($tablesInfo as $tableRow) {
            $label = 'Bàn ' . ($tableRow['SoBan'] ?? ('#' . ($tableRow['idban'] ?? '')));
            if (!empty($tableRow['TenKV'])) {
                $label .= ' (' . $tableRow['TenKV'] . ')';
            }
            $tableLabels[] = $label;
            $tableCapacitySum += (int)($tableRow['soluongKH'] ?? 0);
        }
        $bookingInfo = $successSnapshot['booking'] ?? [];
        $bookingDatetimeDisplay = isset($bookingInfo['NgayDatBan']) ? date('d/m/Y H:i', strtotime($bookingInfo['NgayDatBan'])) : '—';
        $tableListDisplay = !empty($tableLabels) ? implode(', ', $tableLabels) : 'Chưa gán bàn';
        $guestCountDisplay = max((int)($bookingInfo['SoLuongKhach'] ?? 0), $tableCapacitySum);
        $totalAmountValue = $successTotalAmount;
        $totalAmountDisplay = number_format($totalAmountValue);
        $depositAmountValue = $successDepositAmount;
        $depositAmountDisplay = number_format($depositAmountValue);
        $remainingAmountValue = max(0, $totalAmountValue - $depositAmountValue);
        $remainingAmountDisplay = number_format($remainingAmountValue);
        $noteDisplay = trim($metaInfo['note'] ?? '');
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Thanh toán thành công</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        </head>
        <body class="bg-light">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="text-center mb-4">
                            <div class="text-success display-4 mb-3">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h2 class="fw-bold mb-2">Thanh toán thành công</h2>
                            <p class="text-muted mb-0">
                                Cảm ơn bạn đã hoàn tất đặt cọc cho đơn <strong>#<?php echo htmlspecialchars($madatbanFromOrder); ?></strong>.
                                Nhân viên nhà hàng sẽ liên hệ để xác nhận thông tin đặt bàn ngay sau đây.
                            </p>
                        </div>

                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-body">
                                <h5 class="fw-semibold mb-3"><i class="fas fa-info-circle me-2 text-success"></i>Tóm tắt đặt bàn</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Thời gian</span>
                                        <strong><?php echo htmlspecialchars($bookingDatetimeDisplay); ?></strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Bàn</span>
                                        <strong class="text-end"><?php echo htmlspecialchars($tableListDisplay); ?></strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Số lượng khách</span>
                                        <strong><?php echo (int)$guestCountDisplay; ?> người</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Tổng tạm tính</span>
                                        <strong class="text-primary"><?php echo $totalAmountDisplay; ?>đ</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Đã cọc</span>
                                        <strong class="text-success"><?php echo $depositAmountDisplay; ?>đ</strong>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Số tiền còn lại</span>
                                        <strong class="text-warning"><?php echo $remainingAmountDisplay; ?>đ</strong>
                                    </li>
                                </ul>
                                <?php if ($noteDisplay !== ''): ?>
                                    <div class="alert alert-light border mt-3 mb-0">
                                        <strong>Ghi chú:</strong><br><?php echo nl2br(htmlspecialchars($noteDisplay)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($tablesInfo)): ?>
                            <div class="card shadow-sm border-0 mb-4">
                                <div class="card-body">
                                    <h5 class="fw-semibold mb-3"><i class="fas fa-chair me-2 text-warning"></i>Danh sách bàn</h5>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Bàn</th>
                                                    <th>Khu vực</th>
                                                    <th class="text-center">Sức chứa</th>
                                                    <th class="text-end">Phụ thu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($tablesInfo as $tableRow): ?>
                                                    <?php $surcharge = isset($tableRow['phuthu']) ? (float)$tableRow['phuthu'] : (float)($tableRow['default_phuthu'] ?? 0); ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($tableRow['SoBan'] ?? ('#' . ($tableRow['idban'] ?? ''))); ?></td>
                                                        <td><?php echo htmlspecialchars($tableRow['TenKV'] ?? ''); ?></td>
                                                        <td class="text-center"><?php echo (int)($tableRow['soluongKH'] ?? 0); ?></td>
                                                        <td class="text-end"><?php echo number_format($surcharge); ?>đ</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card shadow-sm border-0 mb-4">
                            <div class="card-body">
                                <h5 class="fw-semibold mb-3"><i class="fas fa-utensils me-2 text-danger"></i>Món ăn / Thực đơn</h5>
                                <?php if ($menuModeSummary === 'set' && !empty($menuSnapshotData['set'])): ?>
                                    <?php
                                        $setData = $menuSnapshotData['set'];
                                        $setItems = is_array($setData['monan'] ?? null) ? $setData['monan'] : [];
                                        $setPrice = isset($setData['tongtien']) ? (float)$setData['tongtien'] : 0;
                                    ?>
                                    <div class="mb-3">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($setData['tenthucdon'] ?? 'Thực đơn'); ?></h6>
                                        <?php if ($setPrice > 0): ?>
                                            <div class="text-success fw-semibold mb-2"><?php echo number_format($setPrice); ?>đ</div>
                                        <?php endif; ?>
                                        <?php if (!empty($setItems)): ?>
                                            <ul class="mb-0 ps-3">
                                                <?php foreach ($setItems as $item): ?>
                                                    <li><?php echo htmlspecialchars($item['tenmonan'] ?? 'Món'); ?> <span class="text-muted">x<?php echo (int)($item['soluong'] ?? 0); ?></span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <div class="text-muted">Chi tiết món trong thực đơn chưa được cập nhật.</div>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!empty($menuItemsInfo)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Món ăn</th>
                                                    <th class="text-center">Số lượng</th>
                                                    <th class="text-end">Đơn giá</th>
                                                    <th class="text-end">Thành tiền</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($menuItemsInfo as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['tenmonan'] ?? 'Món'); ?></td>
                                                        <td class="text-center"><?php echo (int)($item['SoLuong'] ?? 0); ?></td>
                                                        <td class="text-end"><?php echo number_format((float)($item['DonGia'] ?? 0)); ?>đ</td>
                                                        <td class="text-end"><?php echo number_format((float)($item['ThanhTien'] ?? 0)); ?>đ</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-light border mb-0">
                                        Khách chưa chọn món ăn cụ thể. Tổng tiền tạm tính hiện chỉ bao gồm phụ thu khu vực (nếu có).
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert alert-info text-start">
                            <h6 class="fw-semibold mb-2"><i class="fas fa-info-circle me-2"></i>Tiếp theo bạn nên</h6>
                            <ul class="mb-0 ps-3">
                                <li>Giữ lại biên lai hoặc màn hình xác nhận này.</li>
                                <li>Thông báo cho nhân viên đang hỗ trợ bạn.</li>
                                <li>Đến nhà hàng đúng giờ ghi trên đơn để được phục vụ tốt nhất.</li>
                            </ul>
                        </div>

                        <div class="d-flex flex-column flex-md-row gap-2 mt-3">
                            <a class="btn btn-outline-secondary flex-fill" href="<?php echo htmlspecialchars($adminListUrl); ?>">
                                <i class="fas fa-list me-2"></i>Quay lại danh sách đặt bàn
                            </a>
                            <button class="btn btn-success flex-fill" onclick="window.close();">
                                <i class="fas fa-window-close me-2"></i>Đóng cửa sổ
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    $_SESSION['admin_flash'] = [
        'type' => 'warning',
        'message' => 'Thanh toán MoMo không thành công: ' . $message
    ];
    header('Location: ../../index.php?page=admin_payment&madatban=' . $madatbanFromOrder);
    exit;
}

http_response_code(400);
echo 'INVALID_REQUEST';
