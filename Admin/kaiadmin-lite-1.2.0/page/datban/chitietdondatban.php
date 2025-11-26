<?php
include("class/clsconnect.php");
include("class/clsdatban.php");
include("class/clskvban.php");
require_once __DIR__ . '/../../../../User/restoran-1.0.0/helpers/booking_qr.php';
require_once __DIR__ . '/booking_helpers.php';
require_once __DIR__ . '/../../class/clsDonHang.php';

if (!function_exists('admin_redirect')) {
    function admin_redirect($url) {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        echo '<script>window.location.href = ' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        exit;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = isset($conn) ? $conn : (new connect_db())->getConnection();
$bookingModel = new datban();
$kvModel = new KhuVucBan();

$madatban = isset($_GET['madatban']) ? (int)$_GET['madatban'] : 0;
$message = '';
$messageType = 'success';
$flashMessage = null;
if (!empty($_SESSION['admin_flash']) && is_array($_SESSION['admin_flash'])) {
    $allowedAlertTypes = ['success', 'info', 'warning', 'danger', 'primary', 'secondary', 'light', 'dark'];
    $flashType = $_SESSION['admin_flash']['type'] ?? 'info';
    $flashMessage = [
        'type' => in_array($flashType, $allowedAlertTypes, true) ? $flashType : 'info',
        'message' => $_SESSION['admin_flash']['message'] ?? ''
    ];
    unset($_SESSION['admin_flash']);
}

if ($madatban <= 0) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Không tìm thấy đơn đặt bàn.</div></div>";
    return;
}

$allowedStatus = ['pending', 'confirmed', 'completed', 'canceled'];
$allowedChannels = ['user', 'walkin', 'phone'];
$allowedPayments = ['cash', 'transfer'];
$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'completed' => 'Hoàn tất',
    'canceled' => 'Đã hủy',
];
$formAction = $_POST['form_action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($formAction === 'update_booking') {
        $newStatus = isset($_POST['TrangThai']) && in_array($_POST['TrangThai'], $allowedStatus, true)
            ? $_POST['TrangThai'] : null;
        $bookingChannel = isset($_POST['booking_channel']) && in_array($_POST['booking_channel'], $allowedChannels, true)
            ? $_POST['booking_channel'] : 'user';
        $paymentMethod = isset($_POST['payment_method']) && in_array($_POST['payment_method'], $allowedPayments, true)
            ? $_POST['payment_method'] : 'cash';
        $note = isset($_POST['note']) ? trim($_POST['note']) : null;
        $customerName = isset($_POST['tenKH']) ? trim($_POST['tenKH']) : '';
        $customerPhone = isset($_POST['sodienthoai']) ? trim($_POST['sodienthoai']) : '';
        $customerEmailRaw = isset($_POST['email']) ? trim($_POST['email']) : '';
        $bookingTimeRaw = isset($_POST['NgayDatBan']) ? trim($_POST['NgayDatBan']) : '';

        $errors = [];
        $customerEmail = $customerEmailRaw !== '' ? $customerEmailRaw : null;
        if ($customerEmail !== null && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ.';
        }

        if ($customerName === '') {
            $errors[] = 'Vui lòng nhập họ tên khách.';
        }

        if ($customerPhone === '') {
            $errors[] = 'Vui lòng nhập số điện thoại của khách.';
        }

        $bookingDateTime = null;
        if ($bookingTimeRaw === '') {
            $errors[] = 'Vui lòng chọn thời gian đặt bàn.';
        } else {
            $bookingDate = DateTime::createFromFormat('Y-m-d\TH:i', $bookingTimeRaw);
            if ($bookingDate instanceof DateTime) {
                $bookingDateTime = $bookingDate->format('Y-m-d H:i:s');
            } else {
                $errors[] = 'Thời gian đặt bàn không hợp lệ.';
            }
        }

        if ($newStatus === null) {
            $errors[] = 'Trạng thái đơn đặt bàn không hợp lệ.';
        }

        $updatedBooking = false;

        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE datban SET TrangThai = ?, tenKH = ?, sodienthoai = ?, email = ?, NgayDatBan = ? WHERE madatban = ?");
            if ($stmt) {
                $stmt->bind_param(
                    'sssssi',
                    $newStatus,
                    $customerName,
                    $customerPhone,
                    $customerEmail,
                    $bookingDateTime,
                    $madatban
                );
                if ($stmt->execute()) {
                    $message = 'Cập nhật đơn đặt bàn thành công.';
                    $messageType = 'success';
                    $updatedBooking = true;
                } else {
                    $message = 'Không thể cập nhật thông tin đơn đặt bàn. Vui lòng thử lại.';
                    $messageType = 'danger';
                }
                $stmt->close();
            } else {
                $message = 'Không thể chuẩn bị truy vấn cập nhật.';
                $messageType = 'danger';
            }
        } else {
            $message = implode(' ', $errors);
            $messageType = 'danger';
        }

        if ($updatedBooking) {
            $bookingModel->ensureAdminMetaTable();
            $metaSelect = $conn->prepare("SELECT id FROM datban_admin_meta WHERE madatban = ? LIMIT 1");
            $metaId = null;
            if ($metaSelect) {
                $metaSelect->bind_param('i', $madatban);
                $metaSelect->execute();
                $metaSelect->bind_result($metaId);
                $metaSelect->fetch();
                $metaSelect->close();
            }

            if ($metaId) {
                $metaUpdate = $conn->prepare("UPDATE datban_admin_meta SET booking_channel = ?, payment_method = ?, note = ?, created_by = ? WHERE madatban = ?");
                if ($metaUpdate) {
                    $createdBy = $_SESSION['nhanvien_id'] ?? null;
                    $metaUpdate->bind_param('sssii', $bookingChannel, $paymentMethod, $note, $createdBy, $madatban);
                    $metaUpdate->execute();
                    $metaUpdate->close();
                }
            } else {
                $metaInsert = $conn->prepare("INSERT INTO datban_admin_meta (madatban, booking_channel, payment_method, note, created_by) VALUES (?, ?, ?, ?, ?)");
                if ($metaInsert) {
                    $createdBy = $_SESSION['nhanvien_id'] ?? null;
                    $metaInsert->bind_param('isssi', $madatban, $bookingChannel, $paymentMethod, $note, $createdBy);
                    $metaInsert->execute();
                    $metaInsert->close();
                }
            }

            $_SESSION['admin_flash'] = [
                'type' => $messageType,
                'message' => $message
            ];
            admin_redirect('index.php?page=chitietdondatban&madatban=' . $madatban);
        }
    } elseif ($formAction === 'check_in_open_table') {
        $snapshot = $bookingModel->getAdminBookingSnapshot($madatban);
        if (!$snapshot) {
            $message = 'Không thể tải thông tin đặt bàn để check-in.';
            $messageType = 'danger';
        } elseif (empty($snapshot['tables'])) {
            $message = 'Đơn chưa có bàn được gán nên không thể mở bàn phục vụ.';
            $messageType = 'danger';
        } else {
            $currentStatus = $snapshot['booking']['TrangThai'] ?? 'pending';
            if (!in_array($currentStatus, ['pending', 'confirmed'], true)) {
                $message = 'Chỉ có thể check-in khi đơn đang chờ hoặc đã xác nhận.';
                $messageType = 'danger';
            } else {
                $activeOrderExists = false;
                if ($stmt = $conn->prepare(
                    "SELECT idDH FROM donhang WHERE madatban = ? AND TrangThai IN (?, ?) LIMIT 1"
                )) {
                    $activeOpen = clsDonHang::ORDER_STATUS_OPEN;
                    $activePendingPayment = clsDonHang::ORDER_STATUS_PENDING_PAYMENT;
                    $stmt->bind_param('iss', $madatban, $activeOpen, $activePendingPayment);
                    $stmt->execute();
                    $stmt->store_result();
                    $activeOrderExists = $stmt->num_rows > 0;
                    $stmt->close();
                }

                if ($activeOrderExists) {
                    $message = 'Đã có đơn đang phục vụ cho đặt bàn này. Vui lòng tiếp tục tại giao diện mở bàn.';
                    $messageType = 'warning';
                } else {
                    $openFlow = admin_booking_snapshot_to_open_table_flow($snapshot);
                    if (empty($openFlow['booking']['tables'])) {
                        $message = 'Không thể khởi tạo sơ đồ bàn từ dữ liệu hiện có.';
                        $messageType = 'danger';
                    } else {
                        $openFlow['booking']['source_booking_id'] = $madatban;
                        $openFlow['order_id'] = null;
                        $_SESSION['open_table_flow'] = $openFlow;

                        if ($stmt = $conn->prepare(
                            "UPDATE datban
                             SET TrangThai = 'completed', payment_expires = NULL
                             WHERE madatban = ? AND TrangThai <> 'canceled'"
                        )) {
                            $stmt->bind_param('i', $madatban);
                            $stmt->execute();
                            $stmt->close();
                        }

                        $_SESSION['admin_flash'] = [
                            'type' => 'success',
                            'message' => 'Đã check-in khách và chuyển sang giao diện mở bàn.'
                        ];
                        admin_redirect('index.php?page=moBan&from_booking=' . $madatban . '&checkin=1');
                    }
                }
            }
        }
    } elseif ($formAction === 'cancel_booking') {
        $conn->begin_transaction();
        $cancelled = false;

        try {
            $updateStatus = $conn->prepare("UPDATE datban SET TrangThai = 'canceled' WHERE madatban = ?");
            if ($updateStatus) {
                $updateStatus->bind_param('i', $madatban);
                $cancelled = $updateStatus->execute();
                $updateStatus->close();
            }

            if ($cancelled) {
                $conn->commit();
                $_SESSION['admin_flash'] = [
                    'type' => 'success',
                    'message' => 'Đã hủy đơn đặt bàn thành công.'
                ];
                admin_redirect('index.php?page=chitietdondatban&madatban=' . $madatban);
            } else {
                $conn->rollback();
                $message = 'Không thể hủy đơn đặt bàn. Vui lòng thử lại.';
                $messageType = 'danger';
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Không thể hủy đơn đặt bàn. Vui lòng thử lại.';
            $messageType = 'danger';
        }
    }
}

$detailSql = "
    SELECT 
        db.madatban,
        db.NgayDatBan,
        db.SoLuongKhach,
        db.TongTien,
        db.TrangThai,
        db.tenKH,
        db.email,
        db.sodienthoai,
        db.NgayTao,
        db.payment_expires,
        db.ThoiGianHetHan,
        COALESCE(meta.booking_channel, 'user') AS booking_channel,
        COALESCE(meta.payment_method, 'cash') AS payment_method,
        COALESCE(meta.menu_mode, 'none') AS menu_mode,
        meta.menu_snapshot,
        meta.note,
        meta.created_by
    FROM datban db
    LEFT JOIN datban_admin_meta meta ON db.madatban = meta.madatban
    WHERE db.madatban = ?
    LIMIT 1
";

$detail = null;
if ($stmt = $conn->prepare($detailSql)) {
    $stmt->bind_param('i', $madatban);
    $stmt->execute();
    $result = $stmt->get_result();
    $detail = $result->fetch_assoc();
    $stmt->close();
}

if (!$detail) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Không tìm thấy thông tin đơn đặt bàn.</div></div>";
    return;
}

$tablesSql = "
    SELECT 
        b.idban,
        b.SoBan,
        b.soluongKH,
        kv.TenKV,
        kv.PhuThu,
        cbd.phuthu as phuthu_custom
    FROM chitiet_ban_datban cbd
    JOIN ban b ON cbd.idban = b.idban
    LEFT JOIN khuvucban kv ON b.MaKV = kv.MaKV
    WHERE cbd.madatban = ?
    ORDER BY b.SoBan
";
$tables = [];
if ($stmt = $conn->prepare($tablesSql)) {
    $stmt->bind_param('i', $madatban);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $tables[] = $row;
    }
    $stmt->close();
}

$menuSql = "
    SELECT m.tenmonan, m.DonGia, ct.SoLuong, (ct.SoLuong * m.DonGia) AS ThanhTien
    FROM chitietdatban ct
    JOIN monan m ON ct.idmonan = m.idmonan
    WHERE ct.madatban = ?
";
$menuItems = [];
if ($stmt = $conn->prepare($menuSql)) {
    $stmt->bind_param('i', $madatban);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $menuItems[] = $row;
    }
    $stmt->close();
}

// Ensure menu items respect approved & active flags; filter out any inactive items
if (!empty($menuItems)) {
    $menuItems = array_values(array_filter($menuItems, static function ($it) {
        // Some queries may not include status; keep item if no status fields
        if (!isset($it['TrangThai']) && !isset($it['hoatdong'])) {
            return true;
        }
        $status = isset($it['TrangThai']) ? $it['TrangThai'] : (isset($it['trangthai']) ? $it['trangthai'] : null);
        $active = isset($it['hoatdong']) ? $it['hoatdong'] : (isset($it['hoatDong']) ? $it['hoatDong'] : null);
        return $status === 'approved' && $active === 'active';
    }));
}

$payments = [];
$paymentSummary = ['completed' => 0, 'pending' => 0];
$paymentsSql = "
    SELECT idThanhToan, SoTien, TrangThai, PhuongThuc, NgayThanhToan, MaGiaoDich
    FROM thanhtoan
    WHERE madatban = ?
    ORDER BY NgayThanhToan DESC, idThanhToan DESC
";
if ($stmt = $conn->prepare($paymentsSql)) {
    $stmt->bind_param('i', $madatban);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $statusKey = strtolower($row['TrangThai'] ?? '');
        if (isset($paymentSummary[$statusKey])) {
            $paymentSummary[$statusKey] += (float)$row['SoTien'];
        }
        $payments[] = $row;
    }
    $stmt->close();
}
$menuMode = $detail['menu_mode'] ?? 'none';
$menuSnapshot = [];
if (!empty($detail['menu_snapshot'])) {
    $decodedSnapshot = json_decode($detail['menu_snapshot'], true);
    if (is_array($decodedSnapshot)) {
        $menuSnapshot = $decodedSnapshot;
    }
}
$selectedMenuName = '';
$selectedMenuUnavailable = false;
if ($menuMode === 'set' && isset($menuSnapshot['set'])) {
    $selectedMenuName = $menuSnapshot['set']['tenthucdon'] ?? ($menuSnapshot['set']['name'] ?? '');
    // If snapshot contains an id, verify the thucdon is still approved & active
    $setId = isset($menuSnapshot['set']['id_thucdon']) ? (int)$menuSnapshot['set']['id_thucdon'] : (isset($menuSnapshot['set']['idthucdon']) ? (int)$menuSnapshot['set']['idthucdon'] : 0);
    if ($setId > 0) {
        $checkSet = $conn->prepare("SELECT TrangThai, hoatdong FROM thucdon WHERE idthucdon = ? LIMIT 1");
        if ($checkSet) {
            $checkSet->bind_param('i', $setId);
            if ($checkSet->execute()) {
                $checkSet->bind_result($setStatus, $setActive);
                if ($checkSet->fetch()) {
                    if (!($setStatus === 'approved' && $setActive === 'active')) {
                        $selectedMenuUnavailable = true;
                    }
                }
            }
            $checkSet->close();
        }
    }
}

$activeOrder = null;
if ($stmt = $conn->prepare(
    "SELECT idDH, TrangThai
     FROM donhang
     WHERE madatban = ? AND TrangThai IN (?, ?)
     ORDER BY idDH DESC
     LIMIT 1"
)) {
    $openStatus = clsDonHang::ORDER_STATUS_OPEN;
    $pendingStatus = clsDonHang::ORDER_STATUS_PENDING_PAYMENT;
    $stmt->bind_param('iss', $madatban, $openStatus, $pendingStatus);
    $stmt->execute();
    $result = $stmt->get_result();
    $activeOrder = $result ? $result->fetch_assoc() : null;
    $stmt->close();
}

$canCheckIn = !empty($tables) && in_array($detail['TrangThai'] ?? 'pending', ['pending', 'confirmed'], true) && $activeOrder === null;
$menuItemsCount = count($menuItems);

$totalSurcharge = 0;
foreach ($tables as $table) {
    $totalSurcharge += (float)($table['phuthu_custom'] ?? $table['PhuThu'] ?? 0);
}

$depositRequired = (int)ceil((float)$detail['TongTien'] * 0.5);
$depositPaid = (float)$paymentSummary['completed'];
$depositPending = (float)$paymentSummary['pending'];
$depositNeeded = max(0, $depositRequired - $depositPaid);

$paymentExpires = $detail['payment_expires'] ?? $detail['ThoiGianHetHan'] ?? null;
$channel = $detail['booking_channel'] ?? 'user';
$paymentMethod = $detail['payment_method'] ?? 'cash';
$bookingTimeValue = '';
if (!empty($detail['NgayDatBan'])) {
    $bookingTimeValue = date('Y-m-d\TH:i', strtotime($detail['NgayDatBan']));
}
$isCanceled = ($detail['TrangThai'] ?? '') === 'canceled';
?>

<div class="container py-4">
    <?php if ($flashMessage && ($flashMessage['message'] ?? '') !== ''): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flashMessage['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-3 gap-3">
        <div>
            <h3 class="mb-1">Đơn đặt bàn #<?php echo (int)$detail['madatban']; ?></h3>
            <span class="text-muted">Tạo lúc: <?php echo date('d/m/Y H:i', strtotime($detail['NgayTao'])); ?></span>
        </div>
        <div>
            <a href="index.php?page=dsdatban" class="btn btn-light me-2"><i class="fas fa-arrow-left me-2"></i>Quay lại</a>
            <a href="index.php?page=admin_booking&duplicate=<?php echo (int)$detail['madatban']; ?>" class="btn btn-outline-primary">
                <i class="fas fa-copy me-2"></i>Sao chép đơn
            </a>
            <a href="index.php?page=admin_booking&edit=<?php echo (int)$detail['madatban']; ?>&step=2" class="btn btn-warning ms-2">
                <i class="fas fa-chair me-2"></i>Sửa bàn
            </a>
            <a href="index.php?page=admin_booking&edit=<?php echo (int)$detail['madatban']; ?>&step=3" class="btn btn-outline-warning ms-2">
                <i class="fas fa-utensils me-2"></i>Sửa món
            </a>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title d-flex justify-content-between align-items-center">
                        Thông tin khách hàng
                        <span class="badge bg-secondary text-uppercase"><?php echo htmlspecialchars($channel); ?></span>
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1 text-muted">Tên khách</p>
                            <p class="fw-semibold"><?php echo htmlspecialchars($detail['tenKH'] ?? 'Khách lẻ'); ?></p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1 text-muted">Số điện thoại</p>
                            <p class="fw-semibold"><?php echo htmlspecialchars($detail['sodienthoai'] ?? '-'); ?></p>
                        </div>
                        <div class="col-md-3">
                            <p class="mb-1 text-muted">Email</p>
                            <p class="fw-semibold"><?php echo htmlspecialchars($detail['email'] ?? '-'); ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-1 text-muted">Thời gian</p>
                            <p class="fw-semibold"><?php echo date('d/m/Y H:i', strtotime($detail['NgayDatBan'])); ?></p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1 text-muted">Số khách</p>
                            <p class="fw-semibold"><?php echo (int)$detail['SoLuongKhach']; ?> người</p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1 text-muted">Hạn thanh toán</p>
                            <p class="fw-semibold">
                                <?php echo $paymentExpires ? date('d/m/Y H:i', strtotime($paymentExpires)) : '—'; ?>
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <p class="mb-1 text-muted">Trạng thái</p>
                        <?php
                            $statusBadgeMap = [
                                'pending' => 'bg-warning text-dark',
                                'confirmed' => 'bg-success',
                                'completed' => 'bg-secondary',
                                'canceled' => 'bg-danger',
                            ];
                            $bookingStatus = $detail['TrangThai'] ?? 'pending';
                            $statusBadge = $statusBadgeMap[$bookingStatus] ?? 'bg-secondary';
                            $statusLabel = $statusLabels[$bookingStatus] ?? ucfirst($bookingStatus);
                        ?>
                        <span class="badge <?php echo $statusBadge; ?> px-3 py-2">
                            <?php echo htmlspecialchars($statusLabel); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Bàn &amp; khu vực</h5>
                    <?php if (!empty($tables)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Bàn</th>
                                        <th>Khu vực</th>
                                        <th>Sức chứa</th>
                                        <th>Phụ thu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tables as $table): ?>
                                        <tr>
                                            <td>Bàn <?php echo htmlspecialchars($table['SoBan']); ?></td>
                                            <td><?php echo htmlspecialchars($table['TenKV'] ?? '—'); ?></td>
                                            <td><?php echo (int)$table['soluongKH']; ?> khách</td>
                                            <td><?php echo number_format((float)($table['phuthu_custom'] ?? $table['PhuThu'] ?? 0)); ?>đ</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Chưa gán bàn cho đơn này.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Món đã chọn</h5>
                    <?php if ($menuMode === 'set' && $selectedMenuName !== ''): ?>
                        <div class="alert alert-warning py-2 px-3 mb-3">
                            <i class="fas fa-layer-group me-2"></i>Áp dụng thực đơn: <strong><?php echo htmlspecialchars($selectedMenuName); ?></strong>
                        </div>
                    <?php elseif ($menuMode === 'items' && $menuItemsCount > 0): ?>
                        <div class="alert alert-info py-2 px-3 mb-3">
                            <i class="fas fa-list-ul me-2"></i>Khách chọn <?php echo $menuItemsCount; ?> món lẻ trước khi đến.
                        </div>
                    <?php elseif ($menuMode === 'none'): ?>
                        <div class="alert alert-secondary py-2 px-3 mb-3">
                            <i class="fas fa-edit me-2"></i>Nhân viên nhập ước tính món thủ công, chưa có món cụ thể.
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($menuItems)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Món ăn</th>
                                        <th>Số lượng</th>
                                        <th>Đơn giá</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($menuItems as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['tenmonan']); ?></td>
                                            <td><?php echo (int)$item['SoLuong']; ?></td>
                                            <td><?php echo number_format((float)$item['DonGia']); ?>đ</td>
                                            <td><?php echo number_format((float)$item['ThanhTien']); ?>đ</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Chưa có món nào được chọn.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h5 class="card-title">Tóm tắt thanh toán</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Phụ thu khu vực</span>
                        <strong><?php echo number_format($totalSurcharge); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tổng món</span>
                        <strong>
                            <?php
                                $menuTotal = array_reduce($menuItems, function($carry, $item) {
                                    return $carry + (float)$item['ThanhTien'];
                                }, 0);
                                echo number_format($menuTotal);
                            ?>đ
                        </strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Yêu cầu cọc (50%)</span>
                        <strong><?php echo number_format($depositRequired); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Đã cọc</span>
                        <strong class="<?php echo $depositPaid >= $depositRequired ? 'text-success' : 'text-primary'; ?>">
                            <?php echo number_format($depositPaid); ?>đ
                        </strong>
                    </div>
                    <?php if ($depositPending > 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Đang chờ xác nhận</span>
                            <strong class="text-warning"><?php echo number_format($depositPending); ?>đ</strong>
                        </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Còn thiếu</span>
                        <strong class="<?php echo $depositNeeded <= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format(max(0, $depositNeeded)); ?>đ
                        </strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold">Tổng ước tính</span>
                        <span class="fw-bold text-primary"><?php echo number_format((float)$detail['TongTien']); ?>đ</span>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Lịch sử đặt cọc</h5>
                    <?php if (!empty($payments)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Ngày</th>
                                        <th>Số tiền</th>
                                        <th>Phương thức</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <?php
                                            $rowStatus = strtolower($payment['TrangThai'] ?? '');
                                            $badgeClass = 'bg-secondary';
                                            if ($rowStatus === 'completed') {
                                                $badgeClass = 'bg-success';
                                            } elseif ($rowStatus === 'pending') {
                                                $badgeClass = 'bg-warning text-dark';
                                            } elseif ($rowStatus === 'failed') {
                                                $badgeClass = 'bg-danger';
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo $payment['NgayThanhToan'] ? date('d/m/Y H:i', strtotime($payment['NgayThanhToan'])) : '—'; ?></td>
                                            <td class="fw-semibold"><?php echo number_format((float)$payment['SoTien']); ?>đ</td>
                                            <td><?php echo strtoupper(htmlspecialchars($payment['PhuongThuc'] ?? '')); ?></td>
                                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($rowStatus ?: 'n/a'); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Chưa ghi nhận giao dịch đặt cọc nào.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Check-in & mở bàn</h5>
                    <?php if ($activeOrder): ?>
                        <p class="text-muted mb-3">
                            Đã có đơn phục vụ đang mở (#<?php echo (int)$activeOrder['idDH']; ?>).
                            Vào mục <strong>Sơ đồ bàn</strong> để tiếp tục gọi món cho khách.
                        </p>
                        <a href="index.php?page=moBan" class="btn btn-outline-primary w-100">
                            <i class="fas fa-chair me-2"></i>Đi tới giao diện mở bàn
                        </a>
                    <?php elseif ($canCheckIn): ?>
                        <p class="text-muted mb-3">
                            Thao tác này xác nhận khách đã tới, tự động mở bàn tương ứng và chuyển bạn sang giao diện gọi món.
                        </p>
                        <form method="POST">
                            <input type="hidden" name="form_action" value="check_in_open_table">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-door-open me-2"></i>Check-in & mở bàn ngay
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Không thể check-in. Vui lòng đảm bảo đơn đã được gán bàn và chưa có order đang phục vụ.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Cập nhật đơn</h5>
                    <form method="POST">
                        <input type="hidden" name="form_action" value="update_booking">
                        <div class="mb-3">
                            <label for="tenKH" class="form-label">Họ và tên khách</label>
                            <input type="text" class="form-control" id="tenKH" name="tenKH" value="<?php echo htmlspecialchars($detail['tenKH'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="sodienthoai" class="form-label">Số điện thoại</label>
                            <input type="tel" class="form-control" id="sodienthoai" name="sodienthoai" value="<?php echo htmlspecialchars($detail['sodienthoai'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($detail['email'] ?? ''); ?>" placeholder="khach@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="NgayDatBan" class="form-label">Thời gian đặt bàn</label>
                            <input type="datetime-local" class="form-control" id="NgayDatBan" name="NgayDatBan" value="<?php echo htmlspecialchars($bookingTimeValue); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="TrangThai" class="form-label">Trạng thái</label>
                            <select class="form-select" id="TrangThai" name="TrangThai">
                                <?php foreach ($allowedStatus as $status): ?>
                                    <?php $label = $statusLabels[$status] ?? ucfirst($status); ?>
                                    <option value="<?php echo $status; ?>" <?php echo $detail['TrangThai'] === $status ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="booking_channel" class="form-label">Nguồn đặt</label>
                            <select class="form-select" id="booking_channel" name="booking_channel">
                                <option value="user" <?php echo $channel === 'user' ? 'selected' : ''; ?>>Website (Khách tự đặt)</option>
                                <option value="walkin" <?php echo $channel === 'walkin' ? 'selected' : ''; ?>>Tại nhà hàng</option>
                                <option value="phone" <?php echo $channel === 'phone' ? 'selected' : ''; ?>>Qua điện thoại</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Phương thức thanh toán dự kiến</label>
                            <select class="form-select" id="payment_method" name="payment_method">
                                <option value="cash" <?php echo $paymentMethod === 'cash' ? 'selected' : ''; ?>>Tiền mặt</option>
                                <option value="transfer" <?php echo $paymentMethod === 'transfer' ? 'selected' : ''; ?>>Chuyển khoản</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="note" class="form-label">Ghi chú nội bộ</label>
                            <textarea class="form-control" id="note" name="note" rows="3" placeholder="Ví dụ: khách đến lúc 18h, cần trang trí sinh nhật"><?php echo htmlspecialchars($detail['note'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Lưu thay đổi</button>
                    </form>
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="form_action" value="cancel_booking">
                        <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('Bạn có chắc muốn hủy đơn đặt bàn này?');" <?php echo $isCanceled ? 'disabled' : ''; ?>>
                            <i class="fas fa-ban me-2"></i>Hủy đơn đặt bàn
                        </button>
                        <?php if ($isCanceled): ?>
                            <small class="d-block text-muted text-center mt-2">Đơn này đã ở trạng thái hủy.</small>
                        <?php else: ?>
                            <small class="d-block text-muted text-center mt-2">Hủy đơn sẽ giải phóng các bàn đã giữ.</small>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-body">
                    <h5 class="card-title">Chia sẻ cho khách</h5>
                    <p class="text-muted small">Gửi liên kết thông tin đặt bàn để khách theo dõi và xác nhận lại thời gian.</p>
                    <div class="input-group mb-2">
                        <?php
                            $shareLink = '';
                            if (!empty($detail['NgayDatBan'])) {
                                $shareLink = build_booking_qr_url((int)$detail['madatban'], $detail['NgayDatBan']);
                            }
                        ?>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($shareLink); ?>" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-share-link="<?php echo htmlspecialchars($shareLink, ENT_QUOTES); ?>" onclick="const link=this.dataset.shareLink;if(link){navigator.clipboard.writeText(link);}">
                            Sao chép
                        </button>
                    </div>
                    <small class="text-muted">Liên kết cho phép khách xem lại thông tin đặt bàn; không yêu cầu thanh toán online.</small>
                </div>
            </div>
            <?php
                $shouldShowPaymentLink = $detail['TrangThai'] === 'pending' && $paymentMethod === 'transfer';
                if ($shouldShowPaymentLink) {
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
                    $paymentPath = $scriptDir . '/index.php?page=admin_payment&madatban=' . (int)$detail['madatban'];
                    $paymentLink = $protocol . '://' . $host . $paymentPath;
                }
            ?>
            <?php if (!empty($shouldShowPaymentLink)): ?>
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Link thanh toán chuyển khoản</h5>
                        <p class="text-muted small">
                            Đơn đang chờ đặt cọc. Sao chép link bên dưới và gửi cho khách để họ mở lại trang thanh toán (QR/VNPay).
                        </p>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($paymentLink); ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-share-link="<?php echo htmlspecialchars($paymentLink, ENT_QUOTES); ?>" onclick="const link=this.dataset.shareLink;if(link){navigator.clipboard.writeText(link);}">
                                Sao chép
                            </button>
                        </div>
                        <small class="text-muted">
                            Link chỉ khả dụng với nhân viên được cấp quyền truy cập trang admin. Nếu muốn khách tự xem lại, dùng thẻ “Chia sẻ cho khách” ở trên.
                        </small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
