<?php
include("class/clsconnect.php");
include("class/clsdatban.php");
include("class/clskvban.php");
include("class/clsmonan.php");
include("class/clsdanhmuc.php");
require_once __DIR__ . '/../../../../User/restoran-1.0.0/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../../../User/restoran-1.0.0/PHPMailer/SMTP.php';
require_once __DIR__ . '/../../../../User/restoran-1.0.0/PHPMailer/Exception.php';
require_once __DIR__ . '/booking_helpers.php';
require_once __DIR__ . '/../../../../User/restoran-1.0.0/helpers/booking_qr.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('admin_redirect')) {
    function admin_redirect($url) {
        echo '<script>window.location.href = ' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        exit;
    }
}

if (!function_exists('admin_send_phone_booking_payment_email')) {
    function admin_send_phone_booking_payment_email(int $madatban, string $customerName, string $customerEmail, string $paymentLink, ?string $paymentExpires = null) {
        if ($customerEmail === '') {
            return false;
        }
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=' . urlencode($paymentLink);
        $expiresText = $paymentExpires ? date('d/m/Y H:i', strtotime($paymentExpires)) : null;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'nhom1.9a7.2018@gmail.com';
            $mail->Password = 'rwgt urjf wpfy iirg';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom('nhom1.9a7.2018@gmail.com', 'Nhà hàng Restoran');
            $mail->addAddress($customerEmail, $customerName ?: $customerEmail);

            $mail->isHTML(true);
            $mail->Subject = 'Thanh toán đặt bàn #' . $madatban . ' - Quét mã QR';
            $mail->Body = '
                <p>Chào ' . htmlspecialchars($customerName ?: 'Quý khách') . ',</p>
                <p>Bạn vừa đặt bàn qua điện thoại. Vui lòng thanh toán đặt cọc bằng cách quét mã QR hoặc nhấn nút bên dưới.</p>
                <p><a href="' . htmlspecialchars($paymentLink) . '" style="display:inline-block;background:#FEA116;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none;font-weight:bold;">Thanh toán ngay</a></p>
                <p><img src="' . htmlspecialchars($qrUrl) . '" alt="QR thanh toán" style="max-width:260px;border:8px solid #fff;box-shadow:0 8px 20px rgba(0,0,0,0.15);border-radius:12px;"></p>' .
                ($expiresText ? '<p><strong>Hạn thanh toán:</strong> ' . htmlspecialchars($expiresText) . '</p>' : '') . '
                <p>Nếu bạn không yêu cầu, vui lòng bỏ qua email này.</p>
                <p>Trân trọng,<br>Nhà hàng Restoran</p>
            ';

            return $mail->send();
        } catch (Exception $e) {
            error_log('admin_send_phone_booking_payment_email error: ' . $e->getMessage());
            return false;
        }
    }
}

$bookingStylePath = '../../../../User/restoran-1.0.0/page/booking/style.css';
if (!defined('ADMIN_BOOKING_STYLE_LOADED')) {
    if (file_exists(__DIR__ . "/../../../../User/restoran-1.0.0/page/booking/style.css")) {
        echo '<link rel="stylesheet" href="' . $bookingStylePath . '">';
    }
    echo '<style>
        .admin-booking-wrapper { max-width: 1100px; margin: 0 auto; }
        .booking-card { background: #ffffff; border-radius: 20px; padding: 32px 36px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08); border: none; }
        .booking-card h4, .booking-card h5 { font-weight: 700; }
        .step-progress { text-transform: uppercase; letter-spacing: .1em; font-size: .8rem; color: #6c757d; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
        .step-progress .badge-step { background: #ffc107; color: #212529; border-radius: 999px; padding: 6px 12px; font-weight: 700; }
        .option-radio-group { display: flex; flex-wrap: wrap; gap: 15px; }
        .option-radio-group input[type="radio"] { display: none; }
        .option-chip { display: inline-flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 14px; border: 2px solid #e2e6ea; background: #f8f9fa; cursor: pointer; transition: all .25s ease; font-weight: 600; color: #495057; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05); }
        .option-chip i { font-size: 1.4rem; color: #ffc107; }
        .option-radio-group input[type="radio"]:checked + .option-chip, .option-chip.active { border-color: #ffc107; background: rgba(255, 193, 7, 0.15); color: #1f2937; box-shadow: 0 15px 35px rgba(255, 193, 7, 0.35); }
        .option-chip.disabled { opacity: 0.45; cursor: not-allowed; }
        .booking-summary-card { background: linear-gradient(135deg, #fff7db, #ffe08f); border-radius: 20px; padding: 26px; height: 100%; display: flex; flex-direction: column; gap: 16px; color: #5f370e; box-shadow: inset 0 0 0 1px rgba(255, 193, 7, 0.25); }
        .booking-summary-card h5 { font-size: 1.15rem; }
        .summary-item { display: flex; align-items: center; gap: 12px; font-weight: 600; }
        .summary-item i { background: #fff; color: #ff8c00; border-radius: 50%; width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 8px 20px rgba(255, 140, 0, 0.25); }
        .table-input { display: none; }
        .table-input:checked + label { background: #ffc107; border-color: #ff9800; color: #0f172a; box-shadow: 0 20px 45px rgba(255, 152, 0, 0.35); }
        .table-input:disabled + label { background: #dc3545; border-color: #dc3545; color: white; opacity: .7; cursor: not-allowed; }
        .table-input:disabled + label .badge { display: none; }
        .table-input:checked + label .capacity, .table-input:checked + label .surcharge { color: #0f172a; }
        .selected-tables { display: none; }
        .selected-tables.active { display: block; }
        .book-step-actions { display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; }
        .book-step-actions .btn { min-width: 160px; font-weight: 600; }
        .booking-meta-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .meta-card { background: #f8f9fa; border-radius: 14px; padding: 16px; display: flex; gap: 12px; align-items: center; border: 1px solid rgba(15, 23, 42, 0.05); }
        .meta-card i { width: 42px; height: 42px; border-radius: 12px; background: rgba(255, 193, 7, 0.15); color: #ff9800; display: flex; justify-content: center; align-items: center; font-size: 1.3rem; }
        .zone-filter { display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0 22px; }
        .zone-filter .filter-chip { border: 1px solid rgba(148, 163, 184, 0.45); background: #f8fafc; border-radius: 30px; padding: 8px 18px; font-weight: 600; font-size: 0.9rem; color: #475569; cursor: pointer; transition: all .2s ease; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05); }
        .zone-filter .filter-chip i { font-size: 1rem; color: #f97316; }
        .zone-filter .filter-chip.active { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #0f172a; border-color: transparent; box-shadow: 0 12px 28px rgba(245, 158, 11, 0.35); }
        .zone-filter .filter-chip.active i { color: #0f172a; }
        .zone-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-top: 8px; }
        .zone-card { background: #ffffff; border-radius: 18px; padding: 18px 18px 22px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); position: relative; border: 1px solid rgba(15, 23, 42, 0.05); overflow: hidden; }
        .zone-card::before { display: none; }
        .zone-card--warm { border-color: rgba(242, 119, 138, 0.35); background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(255, 235, 239, 0.8)); }
        .zone-card--cool { border-color: rgba(96, 165, 250, 0.35); background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(231, 240, 255, 0.85)); }
        .zone-card--neutral { border-color: rgba(209, 213, 219, 0.6); background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(243, 244, 246, 0.85)); }
        .zone-header { background: #2d2f36; color: #ffffff; border-radius: 12px; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .zone-header h6 { margin: 0; font-weight: 700; font-size: 1.05rem; color: inherit; display: flex; align-items: center; gap: 10px; }
        .zone-header .zone-meta { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; opacity: 0.85; }
        .zone-header .zone-meta span { display: inline-flex; align-items: center; gap: 6px; }
        .zone-body { margin-top: 16px; background: rgba(248, 249, 250, 0.7); border-radius: 14px; padding: 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .zone-card--warm .zone-body { background: rgba(255, 241, 245, 0.65); border: 1px solid rgba(242, 119, 138, 0.2); }
        .zone-card--cool .zone-body { background: rgba(235, 243, 255, 0.7); border: 1px solid rgba(96, 165, 250, 0.2); }
        .zone-card--neutral .zone-body { background: rgba(248, 249, 250, 0.75); border: 1px solid rgba(209, 213, 219, 0.35); }
        .zone-empty { padding: 25px; border: 1px dashed rgba(0, 0, 0, 0.12); border-radius: 12px; text-align: center; color: #6c757d; font-style: italic; background: rgba(255, 255, 255, 0.65); }
        .table-btn { border-radius: 16px; border-width: 2px; backdrop-filter: blur(4px); position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: 6px; padding: 14px 16px; transition: transform .2s ease, box-shadow .2s ease; }
        .table-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(15, 23, 42, 0.18); }
        .table-btn .table-number { font-weight: 700; font-size: 1.05rem; }
        .table-btn .capacity, .table-btn .surcharge { font-size: 0.85rem; color: #4a5568; font-weight: 600; display: flex; align-items: center; gap: 6px; }
        .table-btn .capacity-icons { color: #adb5bd; font-size: 0.75rem; display: flex; align-items: center; gap: 4px; }
        .table-btn .capacity-icons i { font-size: 0.75rem; }
        .table-btn.selected { background: linear-gradient(135deg, #ffd34d, #ffb347); color: #0f172a; }
        .zone-grid + .zone-grid { margin-top: 28px; }
        .summary-pill { background: rgba(255, 193, 7, 0.18); border-radius: 999px; padding: 6px 12px; color: #b35c00; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; box-shadow: inset 0 0 0 1px rgba(255, 193, 7, 0.35); }
        .menu-selection-card { background: linear-gradient(145deg, rgba(255, 251, 235, 0.95), rgba(255, 243, 205, 0.85)); border-radius: 22px; padding: 28px; border: 1px solid rgba(251, 191, 36, 0.35); box-shadow: 0 18px 40px rgba(251, 191, 36, 0.18); position: relative; overflow: hidden; }
        .menu-selection-card::after { content: ""; position: absolute; inset: 0; pointer-events: none; border-radius: inherit; background: linear-gradient(135deg, rgba(255, 255, 255, 0.18), rgba(251, 191, 36, 0.08)); }
        .menu-selection-card > * { position: relative; z-index: 1; }
        .menu-mode-pills .nav-link { font-weight: 600; border-radius: 50px; padding: 10px 20px; color: #92400e; border: 1px solid transparent; transition: all .18s ease; background: rgba(254, 215, 170, 0.35); box-shadow: inset 0 0 0 1px rgba(217, 119, 6, 0.18); }
        .menu-mode-pills .nav-link i { margin-right: 6px; }
        .menu-mode-pills .nav-link:hover { color: #7c2d12; background: rgba(251, 191, 36, 0.35); }
        .menu-mode-pills .nav-link.active { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: #0f172a; border-color: transparent; box-shadow: 0 12px 26px rgba(245, 158, 11, 0.35); }
        .menu-panel { display: none; margin-top: 22px; }
        .menu-panel.active { display: block; }
        .selected-dish-table td { vertical-align: middle; }
        .dish-remove-btn { border: none; background: none; color: #dc2626; }
        .dish-quantity-group { display: inline-flex; border-radius: 999px; border: 1px solid rgba(248, 113, 113, 0.35); overflow: hidden; }
        .dish-quantity-group button { border: none; background: rgba(248, 113, 113, 0.12); width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; color: #b91c1c; }
        .dish-quantity-group span { padding: 0 12px; font-weight: 600; color: #7f1d1d; display: inline-flex; align-items: center; }
        .set-menu-card { border-radius: 18px; border: 1px solid rgba(251, 191, 36, 0.28); padding: 20px; cursor: pointer; transition: all .2s ease; background: rgba(255, 255, 255, 0.88); box-shadow: 0 12px 24px rgba(251, 191, 36, 0.12); }
        .set-menu-card:hover { transform: translateY(-3px); box-shadow: 0 18px 32px rgba(251, 191, 36, 0.22); }
        .set-menu-card.active { border-color: #f59e0b; background: linear-gradient(145deg, rgba(253, 224, 71, 0.26), rgba(251, 191, 36, 0.16)); box-shadow: 0 18px 38px rgba(253, 224, 71, 0.35); }
        .set-menu-card .set-meta { font-size: 0.85rem; color: #92400e; display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; }
        .set-menu-card ul { margin: 0; padding-left: 18px; font-size: 0.95rem; color: #78350f; }
        .menu-total-box { border-radius: 16px; background: rgba(251, 191, 36, 0.12); padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; gap: 18px; flex-wrap: wrap; }
        .menu-total-box h5 { margin: 0; color: #b45309; }
        .menu-total-box small { color: #a16207; }
        .menu-reset-btn { border: none; background: none; color: #dc2626; font-weight: 600; }
        .menu-reset-btn:hover { text-decoration: underline; }
        .menu-items-toolbar { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; align-items: end; }
        .dish-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; margin-top: 12px; }
        .dish-card { position: relative; border-radius: 16px; overflow: hidden; background: #fff; border: 1px solid rgba(0,0,0,0.05); box-shadow: 0 10px 30px rgba(15,23,42,0.06); transition: transform .2s ease, box-shadow .2s ease; }
        .dish-card:hover { transform: translateY(-3px); box-shadow: 0 15px 36px rgba(15,23,42,0.12); }
        .dish-thumb { width: 100%; height: 150px; background-size: cover; background-position: center; background-color: #f8fafc; }
        .dish-body { padding: 14px 14px 12px; display: flex; flex-direction: column; gap: 8px; }
        .dish-title { font-weight: 700; color: #0f172a; margin: 0; }
        .dish-meta { display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: #b45309; }
        .dish-meta small { color: #64748b; font-weight: 500; }
        .dish-qty-control { display: inline-flex; align-items: center; gap: 10px; border-radius: 999px; padding: 6px 10px; background: rgba(251, 191, 36, 0.16); border: 1px solid rgba(251, 191, 36, 0.4); }
        .dish-qty-control button { border: none; width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; color: #b45309; background: #fff7ed; box-shadow: 0 6px 14px rgba(180, 83, 9, 0.18); }
        .dish-qty-control span { min-width: 20px; text-align: center; font-weight: 700; color: #7c2d12; }
        .dish-category { position: absolute; top: 10px; left: 10px; background: rgba(15,23,42,0.78); color: #fff; border-radius: 999px; padding: 6px 10px; font-size: 12px; font-weight: 600; }
        .dish-card.selected { border-color: #f59e0b; box-shadow: 0 18px 36px rgba(245, 158, 11, 0.35); }
        .dish-card .badge-price { position: absolute; bottom: 10px; right: 10px; background: #f59e0b; color: #0f172a; font-weight: 700; padding: 8px 12px; border-radius: 12px; box-shadow: 0 10px 24px rgba(245, 158, 11, 0.35); }
        .dish-search-input { background: #fff; border-radius: 12px; border: 1px solid rgba(0,0,0,0.08); padding: 10px 12px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.4); }
        .area-chip-group { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 18px; }
        .area-chip { border: 1px solid rgba(148, 163, 184, 0.4); border-radius: 18px; padding: 14px 20px; background: rgba(248, 250, 252, 0.9); display: inline-flex; flex-direction: column; gap: 6px; min-width: 170px; align-items: flex-start; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); transition: all .2s ease; }
        .area-chip strong { font-size: 1rem; color: #0f172a; }
        .area-chip span { font-size: 0.85rem; color: #475569; }
        .area-chip i { color: #fb923c; margin-right: 6px; }
        .area-chip.active { border-color: #f59e0b; background: linear-gradient(135deg, rgba(252, 211, 77, 0.25), rgba(251, 191, 36, 0.2)); box-shadow: 0 16px 32px rgba(251, 191, 36, 0.35); }
        .area-chip:hover { transform: translateY(-2px); box-shadow: 0 14px 30px rgba(15, 23, 42, 0.15); }
        @media (max-width: 992px) {
            .booking-card { padding: 24px; border-radius: 16px; }
            .option-chip { width: 100%; justify-content: center; }
            .zone-grid { grid-template-columns: 1fr; }
            .menu-selection-card { padding: 20px; }
            .menu-mode-pills .nav-link { width: 100%; justify-content: center; }
        }
    </style>';
    define('ADMIN_BOOKING_STYLE_LOADED', true);
}
$db = new connect_db();
$bookingModel = new datban();
$kvModel = new KhuVucBan();

if (!isset($_SESSION['admin_booking_flow'])) {
    $_SESSION['admin_booking_flow'] = [];
}
if (!array_key_exists('admin_booking_edit_id', $_SESSION)) {
    $_SESSION['admin_booking_edit_id'] = null;
}

$flow =& $_SESSION['admin_booking_flow'];
$editingBookingId = $_SESSION['admin_booking_edit_id'];
$isEditing = $editingBookingId !== null;
$errors = [];
$step = isset($_GET['step']) ? max(1, (int)$_GET['step']) : 1;

if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $_SESSION['admin_booking_flow'] = [];
    $_SESSION['admin_booking_edit_id'] = null;
    $flow =& $_SESSION['admin_booking_flow'];
    $editingBookingId = null;
    $isEditing = false;
    $step = 1;
}

if (isset($_GET['duplicate'])) {
    $duplicateId = (int)$_GET['duplicate'];
    if ($duplicateId > 0) {
        $sqlDup = "
            SELECT db.tenKH, db.email, db.sodienthoai, db.SoLuongKhach, db.NgayDatBan,
                   COALESCE(meta.booking_channel, 'user') AS booking_channel,
                   COALESCE(meta.payment_method, 'cash') AS payment_method
            FROM datban db
            LEFT JOIN datban_admin_meta meta ON db.madatban = meta.madatban
            WHERE db.madatban = ?
            LIMIT 1
        ";
        if ($stmt = $db->getConnection()->prepare($sqlDup)) {
            $stmt->bind_param('i', $duplicateId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($dup = $result->fetch_assoc()) {
                $dupPayment = $dup['payment_method'] ?? 'cash';
                if (!in_array($dupPayment, ['cash', 'transfer'], true)) {
                    $dupPayment = 'cash';
                }
                $flow = [
                    'type' => in_array($dup['booking_channel'], ['walkin','phone']) ? $dup['booking_channel'] : 'walkin',
                    'customer' => [
                        'name' => $dup['tenKH'] ?? '',
                        'email' => $dup['email'] ?? '',
                        'phone' => $dup['sodienthoai'] ?? '',
                        'note' => ''
                    ],
                    'payment_method' => $dupPayment,
                ];
                $step = 2;
            }
            $stmt->close();
        }
    }
}

$stepErrorsPayload = $_SESSION['admin_booking_step_errors'] ?? null;
if (is_array($stepErrorsPayload)) {
    $targetStep = isset($stepErrorsPayload['step']) ? (int)$stepErrorsPayload['step'] : null;
    if ($targetStep === null || $targetStep === (int)$step) {
        $messages = $stepErrorsPayload['messages'] ?? [];
        if (is_array($messages)) {
            $errors = array_merge($errors, $messages);
        } elseif (is_string($messages) && $messages !== '') {
            $errors[] = $messages;
        }
    }
    unset($_SESSION['admin_booking_step_errors']);
}

if (isset($_GET['edit'])) {
    $requestedEditId = (int)$_GET['edit'];
    if ($requestedEditId > 0) {
        $snapshot = $bookingModel->getAdminBookingSnapshot($requestedEditId);
        if ($snapshot) {
            $_SESSION['admin_booking_edit_id'] = $requestedEditId;
            $_SESSION['admin_booking_flow'] = admin_booking_build_flow_from_snapshot($snapshot);
            $flow =& $_SESSION['admin_booking_flow'];
            $editingBookingId = $requestedEditId;
            $isEditing = true;
            if (!isset($_GET['step'])) {
                $step = 2;
            } else {
                $step = max(1, (int)$_GET['step']);
            }
        } else {
            $_SESSION['admin_flash'] = [
                'type' => 'danger',
                'message' => 'Không tìm thấy đơn đặt bàn cần chỉnh sửa.'
            ];
            $_SESSION['admin_booking_edit_id'] = null;
            $_SESSION['admin_booking_flow'] = [];
            admin_redirect('index.php?page=dsdatban');
        }
    }
} elseif ($isEditing && empty($flow)) {
    $snapshot = $bookingModel->getAdminBookingSnapshot($editingBookingId);
    if ($snapshot) {
        $_SESSION['admin_booking_flow'] = admin_booking_build_flow_from_snapshot($snapshot);
        $flow =& $_SESSION['admin_booking_flow'];
    } else {
        $_SESSION['admin_booking_edit_id'] = null;
        $isEditing = false;
    }
}

$editQuerySuffix = $isEditing ? '&edit=' . (int)$editingBookingId : '';

$bookingTimeSlots = [];
if ($step === 2) {
    for ($minutes = 8 * 60; $minutes <= 22 * 60; $minutes += 30) {
        $hour = (int)floor($minutes / 60);
        $minute = $minutes % 60;
        $bookingTimeSlots[] = sprintf('%02d:%02d', $hour, $minute);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $type = $_POST['booking_type'] ?? '';
        $name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['customer_phone'] ?? '');
        $email = trim($_POST['customer_email'] ?? '');
        $note = trim($_POST['customer_note'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? 'cash';
        if (!in_array($paymentMethod, ['cash', 'transfer'], true)) {
            $paymentMethod = 'cash';
        }

        if (!in_array($type, ['walkin', 'phone'], true)) {
            $errors[] = 'Vui lòng chọn loại luồng đặt bàn.';
        }
        if ($name === '') {
            $errors[] = 'Tên khách hàng không được để trống.';
        }
        if (!preg_match('/^[0-9]{9,11}$/', $phone)) {
            $errors[] = 'Vui lòng nhập số điện thoại hợp lệ (9-11 chữ số).';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email không hợp lệ.';
        }
        if ($type === 'walkin' && !in_array($paymentMethod, ['cash','transfer'], true)) {
            $errors[] = 'Vui lòng chọn phương thức thanh toán cho khách tại quầy.';
        }
        if ($type === 'phone') {
            $paymentMethod = 'transfer';
        }

        if (empty($errors)) {
            $flow['type'] = $type;
            $flow['customer'] = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'note' => $note
            ];
            $flow['payment_method'] = $paymentMethod;
            $flow['created_by'] = $_SESSION['nhanvien_id'] ?? null;
            admin_redirect('index.php?page=admin_booking&step=2' . $editQuerySuffix);
        }
    } elseif ($step === 2) {
        $formAction = $_POST['form_action'] ?? 'save';
        $bookingDate = $_POST['booking_date'] ?? '';
        $bookingTime = $_POST['booking_time'] ?? '';
        $area = isset($_POST['khuvuc']) ? (int)$_POST['khuvuc'] : 0;
        $selectedTables = isset($_POST['tables']) && is_array($_POST['tables']) ? array_map('intval', $_POST['tables']) : [];

        if (!isset($flow['booking'])) {
            $flow['booking'] = [];
        }
        $previousArea = $flow['booking']['khuvuc'] ?? null;

        if ($bookingDate !== '' && $bookingTime !== '') {
            $flow['booking']['datetime'] = date('Y-m-d H:i:s', strtotime($bookingDate . ' ' . $bookingTime));
        }
        if ($area > 0) {
            $flow['booking']['khuvuc'] = $area;
        }

        if ($formAction !== 'refresh') {
            if ($bookingDate === '' || $bookingTime === '') {
                $errors[] = 'Vui lòng chọn ngày và giờ đặt bàn.';
            }
            if ($bookingTime !== '' && !empty($bookingTimeSlots) && !in_array($bookingTime, $bookingTimeSlots, true)) {
                $errors[] = 'Giờ đặt không hợp lệ.';
            }
            if ($area <= 0) {
                $errors[] = 'Vui lòng chọn khu vực bàn.';
            }
            if (empty($selectedTables)) {
                $errors[] = 'Vui lòng chọn ít nhất một bàn cho khách.';
            }
            $datetime = null;
            if (empty($errors)) {
                $datetime = date('Y-m-d H:i:s', strtotime($bookingDate . ' ' . $bookingTime));
                if (strtotime($datetime) < time()) {
                    $errors[] = 'Thời gian đặt bàn phải ở tương lai.';
                }
            }

            $areaInfo = $bookingModel->getKhuVucInfo($area);
            $phuthu = isset($areaInfo['PhuThu']) ? (float)$areaInfo['PhuThu'] : 0;

            $tablesData = $bookingModel->getBanTheoKhuVuc($area);
            $tableMap = [];
            foreach ($tablesData as $table) {
                $tableMap[(int)$table['idban']] = $table;
            }

            $finalTables = [];
            $totalCapacity = 0;
            if (empty($errors)) {
                $excludeBookingId = $isEditing ? $editingBookingId : null;
                foreach ($selectedTables as $idban) {
                    if (!isset($tableMap[$idban])) {
                        $errors[] = "Bàn #{$idban} không thuộc khu vực đã chọn.";
                        continue;
                    }
                    if (!$bookingModel->checkAvailableTimeSlot($idban, $datetime, $excludeBookingId)) {
                        $errors[] = "Bàn " . $tableMap[$idban]['SoBan'] . " đã được giữ trong khung giờ này.";
                    } else {
                        $capacity = (int)($tableMap[$idban]['soluongKH'] ?? 0);
                        $totalCapacity += $capacity;
                        $finalTables[] = [
                            'idban' => $idban,
                            'soban' => $tableMap[$idban]['SoBan'],
                            'capacity' => $capacity,
                            'phuthu' => $phuthu
                        ];
                    }
                }
            }

            if ($totalCapacity <= 0) {
                $errors[] = 'Không thể xác định sức chứa cho các bàn đã chọn.';
            }

            if (empty($errors)) {
                $flow['booking'] = [
                    'datetime' => $datetime,
                    'khuvuc' => $area,
                    'tables' => $finalTables,
                    'total_surcharge' => $phuthu * count($finalTables),
                    'people_count' => $totalCapacity
                ];
                $flow['financial'] = [
                    'estimated_food' => $flow['financial']['estimated_food'] ?? 0,
                    'total_amount' => ($phuthu * count($finalTables)) + ($flow['financial']['estimated_food'] ?? 0)
                ];

                if ($formAction === 'confirm_edit_tables' && $isEditing && $editingBookingId) {
                    $menuSnapshot = $flow['menu'] ?? [];
                    $menuItemsForInsert = [];
                    if (($flow['menu']['mode'] ?? 'none') === 'set' && !empty($flow['menu']['set']['monan'])) {
                        $menuItemsForInsert = $flow['menu']['set']['monan'];
                    } elseif (!empty($flow['menu']['items'])) {
                        $menuItemsForInsert = $flow['menu']['items'];
                    }

                    $status = $flow['original_status'] ?? ($flow['booking_status'] ?? 'pending');
                    $paymentExpires = $flow['original_payment_expires'] ?? null;
                    $expiryHold = $flow['original_expiry_hold'] ?? null;
                    $totalAmount = $flow['financial']['total_amount'] ?? 0;

                    $payload = [
                        'idKH' => $flow['customer_id'] ?? null,
                        'datetime' => $flow['booking']['datetime'],
                        'people' => $flow['booking']['people_count'],
                        'total_amount' => $totalAmount,
                        'status' => $status,
                        'tenKH' => $flow['customer']['name'] ?? '',
                        'email' => $flow['customer']['email'] ?? '',
                        'sodienthoai' => $flow['customer']['phone'] ?? '',
                        'tables' => $finalTables,
                        'payment_expires' => $paymentExpires,
                        'expiry_hold' => $expiryHold,
                        'booking_channel' => $flow['type'] ?? 'walkin',
                        'payment_method' => $flow['payment_method'] ?? 'cash',
                        'note' => $flow['admin_note'] ?? ($flow['customer']['note'] ?? null),
                        'created_by' => $flow['created_by'] ?? ($_SESSION['nhanvien_id'] ?? null),
                        'menu_mode' => $flow['menu']['mode'] ?? 'none',
                        'menu_items' => $menuItemsForInsert,
                        'menu_snapshot' => json_encode($menuSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ];

                    $updated = $bookingModel->updateAdminBooking($editingBookingId, $payload);
                    if ($updated) {
                        $_SESSION['admin_booking_flow'] = [];
                        $_SESSION['admin_booking_edit_id'] = null;
                        $_SESSION['admin_flash'] = [
                            'type' => 'success',
                            'message' => 'Đã cập nhật bàn cho đơn #' . $editingBookingId . '.'
                        ];
                        admin_redirect('index.php?page=chitietdondatban&madatban=' . $editingBookingId);
                    } else {
                        $lastError = $bookingModel->getLastErrorCode();
                        if ($lastError === 'table_conflict') {
                            $conflictIds = $bookingModel->getConflictingTableIds();
                            $conflictNames = [];
                            foreach ($finalTables as $table) {
                                $tid = (int)($table['idban'] ?? 0);
                                if ($tid > 0 && in_array($tid, $conflictIds, true)) {
                                    $label = $table['soban'] ?? $tid;
                                    $conflictNames[] = 'Bàn ' . $label;
                                }
                            }
                            if (empty($conflictNames)) {
                                $conflictNames[] = 'Một hoặc nhiều bàn đã chọn';
                            }
                            $_SESSION['admin_booking_step_errors'] = [
                                'step' => 2,
                                'messages' => [
                                    implode(', ', $conflictNames) . ' đã được giữ trong khung giờ hiện tại. Vui lòng chọn bàn khác.'
                                ]
                            ];
                            if (isset($_SESSION['admin_booking_flow']['booking'])) {
                                $_SESSION['admin_booking_flow']['booking']['tables'] = [];
                                $_SESSION['admin_booking_flow']['booking']['total_surcharge'] = 0;
                            }
                            if (isset($_SESSION['admin_booking_flow']['financial'])) {
                                $estimated = $_SESSION['admin_booking_flow']['financial']['estimated_food'] ?? 0;
                                $_SESSION['admin_booking_flow']['financial']['total_amount'] = $estimated;
                            }
                            unset($_SESSION['admin_booking_flow']['prefill_table']);
                            admin_redirect('index.php?page=admin_booking&step=2' . $editQuerySuffix);
                        }
                        $errors[] = 'Không thể cập nhật bàn. Vui lòng thử lại.';
                    }
                } else {
                    admin_redirect('index.php?page=admin_booking&step=3' . $editQuerySuffix);
                }
            }
        }

        if ($formAction === 'refresh') {
            if ($area > 0 && $area !== $previousArea) {
                $flow['booking']['tables'] = [];
            }
        }
    } elseif ($step === 3) {
        $postedMenuMode = $_POST['menu_mode'] ?? ($flow['menu']['mode'] ?? 'none');
        $postedItemsRaw = $_POST['selected_monan_payload'] ?? '';
        $postedSetRaw = $_POST['selected_thucdon_payload'] ?? '';
        $adminNote = trim($_POST['admin_note'] ?? '');

        $allowedModes = ['none', 'items', 'set'];
        $menuMode = in_array($postedMenuMode, $allowedModes, true) ? $postedMenuMode : 'none';
        $selectedItems = [];
        $selectedSet = null;
        $calculatedFood = 0;

        if ($menuMode === 'items') {
            if ($postedItemsRaw !== '') {
                $decoded = json_decode($postedItemsRaw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $item) {
                        $id = isset($item['idmonan']) ? (int)$item['idmonan'] : 0;
                        $qty = isset($item['soluong']) ? (int)$item['soluong'] : 0;
                        $price = isset($item['DonGia']) ? (float)$item['DonGia'] : 0.0;
                        $name = trim($item['tenmonan'] ?? '');
                        if ($id > 0 && $qty > 0 && $price >= 0) {
                            $selectedItems[] = [
                                'idmonan' => $id,
                                'tenmonan' => $name,
                                'DonGia' => $price,
                                'soluong' => $qty
                            ];
                        }
                    }
                }
                if (!empty($selectedItems)) {
                    $calculatedFood = array_reduce($selectedItems, static function ($carry, $item) {
                        return $carry + ($item['DonGia'] * $item['soluong']);
                    }, 0);
                } else {
                    $menuMode = 'none';
                }
            } else {
                $menuMode = 'none';
            }
        } elseif ($menuMode === 'set') {
            if ($postedSetRaw !== '') {
                $decoded = json_decode($postedSetRaw, true);
                if (is_array($decoded)) {
                    $setId = isset($decoded['id_thucdon']) ? (int)$decoded['id_thucdon'] : (isset($decoded['idthucdon']) ? (int)$decoded['idthucdon'] : 0);
                    $setName = trim($decoded['tenthucdon'] ?? ($decoded['name'] ?? ''));
                    $setPrice = isset($decoded['tongtien']) ? (float)$decoded['tongtien'] : null;
                    $setItems = [];
                    if (isset($decoded['monan']) && is_array($decoded['monan'])) {
                        foreach ($decoded['monan'] as $item) {
                            $id = isset($item['idmonan']) ? (int)$item['idmonan'] : 0;
                            $qty = isset($item['soluong']) ? (int)$item['soluong'] : 0;
                            $price = isset($item['DonGia']) ? (float)$item['DonGia'] : 0.0;
                            $name = trim($item['tenmonan'] ?? '');
                            if ($id > 0 && $qty > 0 && $price >= 0) {
                                $setItems[] = [
                                    'idmonan' => $id,
                                    'tenmonan' => $name,
                                    'DonGia' => $price,
                                    'soluong' => $qty
                                ];
                            }
                        }
                    }
                    if (!empty($setItems)) {
                        $selectedSet = [
                            'id_thucdon' => $setId,
                            'tenthucdon' => $setName,
                            'tongtien' => $setPrice,
                            'monan' => $setItems
                        ];
                        if ($setPrice !== null && $setPrice > 0) {
                            $calculatedFood = $setPrice;
                        } else {
                            $calculatedFood = array_reduce($setItems, static function ($carry, $item) {
                                return $carry + ($item['DonGia'] * $item['soluong']);
                            }, 0);
                        }
                    }
                }
                if ($selectedSet === null) {
                    $menuMode = 'none';
                }
            } else {
                $menuMode = 'none';
            }
        }

        if ($menuMode === 'none') {
            $selectedItems = [];
            $selectedSet = null;
            $calculatedFood = 0;
        }

        if ($calculatedFood < 0) {
            $calculatedFood = 0;
        }

        if (!isset($flow['financial']) || !is_array($flow['financial'])) {
            $flow['financial'] = [];
        }
        $flow['menu'] = [
            'mode' => $menuMode,
            'items' => $selectedItems,
            'set' => $selectedSet,
            'total' => $calculatedFood
        ];
        $flow['financial']['estimated_food'] = $calculatedFood;
        $flow['financial']['total_amount'] = ($flow['booking']['total_surcharge'] ?? 0) + $calculatedFood;
        $flow['admin_note'] = $adminNote;

        if (empty($flow['booking']) || empty($flow['customer'])) {
            $errors[] = 'Thiếu thông tin đặt bàn. Vui lòng thực hiện lại từ đầu.';
        }

        if (empty($errors)) {
            $tables = $flow['booking']['tables'];
            $totalSurcharge = $flow['booking']['total_surcharge'];
            $totalAmount = $flow['financial']['total_amount'];
            $requiredDeposit = (int)ceil(max(0, (float)$totalAmount) * 0.5);
            $status = ($flow['type'] === 'walkin') ? 'confirmed' : 'pending';
            if ($isEditing && isset($flow['original_status'])) {
                $status = $flow['original_status'];
            }
            $paymentExpires = null;
            $expiryHold = null;
            if ($isEditing) {
                $paymentExpires = $flow['original_payment_expires'] ?? null;
                $expiryHold = $flow['original_expiry_hold'] ?? null;
            } else {
                $now = date('Y-m-d H:i:s');
                if ($flow['type'] === 'phone') {
                    $paymentExpires = date('Y-m-d H:i:s', strtotime($now . ' +6 hours'));
                    $expiryHold = date('Y-m-d H:i:s', strtotime($now . ' +2 hours'));
                }
                if (($flow['payment_method'] ?? 'cash') === 'transfer' && $paymentExpires === null) {
                    $paymentExpires = date('Y-m-d H:i:s', strtotime($now . ' +6 hours'));
                }
                if (($flow['payment_method'] ?? 'cash') === 'transfer' && $requiredDeposit > 0) {
                    $status = 'pending';
                }
            }

            $menuSnapshot = $flow['menu'] ?? [];
            $menuItemsForInsert = [];
            if (($flow['menu']['mode'] ?? 'none') === 'set' && !empty($flow['menu']['set']['monan'])) {
                $menuItemsForInsert = $flow['menu']['set']['monan'];
            } elseif (!empty($flow['menu']['items'])) {
                $menuItemsForInsert = $flow['menu']['items'];
            }

            $payload = [
                'idKH' => $flow['customer_id'] ?? null,
                'datetime' => $flow['booking']['datetime'],
                'people' => $flow['booking']['people_count'],
                'total_amount' => $totalAmount,
                'status' => $status,
                'tenKH' => $flow['customer']['name'],
                'email' => $flow['customer']['email'],
                'sodienthoai' => $flow['customer']['phone'],
                'tables' => $tables,
                'payment_expires' => $paymentExpires,
                'expiry_hold' => $expiryHold,
                'booking_channel' => $flow['type'],
                'payment_method' => $flow['payment_method'],
                'note' => $adminNote !== '' ? $adminNote : ($flow['customer']['note'] ?? null),
                'created_by' => $flow['created_by'] ?? ($_SESSION['nhanvien_id'] ?? null),
                'menu_mode' => $flow['menu']['mode'] ?? 'none',
                'menu_items' => $menuItemsForInsert,
                'menu_snapshot' => json_encode($menuSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ];

            if ($isEditing && $editingBookingId) {
                $updated = $bookingModel->updateAdminBooking($editingBookingId, $payload);
                if ($updated) {
                    $_SESSION['admin_booking_flow'] = [];
                    $_SESSION['admin_booking_edit_id'] = null;
                    $_SESSION['admin_flash'] = [
                        'type' => 'success',
                        'message' => 'Đã cập nhật đơn đặt bàn #' . $editingBookingId . ' thành công.'
                    ];
                    admin_redirect('index.php?page=chitietdondatban&madatban=' . $editingBookingId);
                } else {
                    $lastError = $bookingModel->getLastErrorCode();
                    if ($lastError === 'table_conflict') {
                        $conflictIds = $bookingModel->getConflictingTableIds();
                        $conflictNames = [];
                        foreach ($tables as $table) {
                            $tid = (int)($table['idban'] ?? 0);
                            if ($tid > 0 && in_array($tid, $conflictIds, true)) {
                                $label = $table['soban'] ?? $tid;
                                $conflictNames[] = 'Bàn ' . $label;
                            }
                        }
                        if (empty($conflictNames)) {
                            $conflictNames[] = 'Một hoặc nhiều bàn đã chọn';
                        }
                        $_SESSION['admin_booking_step_errors'] = [
                            'step' => 2,
                            'messages' => [
                                implode(', ', $conflictNames) . ' đã được giữ trong khung giờ hiện tại. Vui lòng chọn bàn khác.'
                            ]
                        ];
                        if (isset($_SESSION['admin_booking_flow']['booking'])) {
                            $_SESSION['admin_booking_flow']['booking']['tables'] = [];
                            $_SESSION['admin_booking_flow']['booking']['total_surcharge'] = 0;
                        }
                        if (isset($_SESSION['admin_booking_flow']['financial'])) {
                            $estimated = $_SESSION['admin_booking_flow']['financial']['estimated_food'] ?? 0;
                            $_SESSION['admin_booking_flow']['financial']['total_amount'] = $estimated;
                        }
                        unset($_SESSION['admin_booking_flow']['prefill_table']);
                        admin_redirect('index.php?page=admin_booking&step=2' . $editQuerySuffix);
                    }
                    $errors[] = 'Không thể cập nhật đơn đặt bàn. Vui lòng thử lại.';
                }
            } else {
                $madatban = $bookingModel->createAdminBooking($payload);
                if ($madatban) {
                    $paymentMethod = $flow['payment_method'] ?? 'cash';

                    // Gửi email kèm QR thanh toán cho khách đặt qua điện thoại
                    if (($flow['type'] ?? '') === 'phone' && !empty($flow['customer']['email'])) {
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
                        $paymentLink = $protocol . '://' . $host . $basePath . '/../../User/restoran-1.0.0/page/payment_online.php?madatban=' . $madatban;
                        $expireToSend = $paymentExpires ?? null;
                        admin_send_phone_booking_payment_email(
                            (int)$madatban,
                            $flow['customer']['name'] ?? '',
                            $flow['customer']['email'] ?? '',
                            $paymentLink,
                            $expireToSend
                        );
                    }

                    $_SESSION['admin_booking_flow'] = [];
                    $flow = [];
                    // If admin chose cash and a deposit is required, immediately record the deposit
                    // into `thanhtoan` so cash deposits show up in payment history like other methods.
                    if ($paymentMethod === 'cash' && isset($requiredDeposit) && $requiredDeposit > 0) {
                        $depositAmount = (int)$requiredDeposit;
                        $paymentRef = 'CASH' . date('ymdHis') . rand(1000, 9999);
                        try {
                            $db->tuychinh(
                                "INSERT INTO thanhtoan (madatban, idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)\n                                 VALUES (?, NULL, ?, 'cash', 'completed', NOW(), ?)",
                                [$madatban, $depositAmount, $paymentRef]
                            );

                            // Update datban_admin_meta note similar to admin_payment_update_deposit_note
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
                                    "INSERT INTO datban_admin_meta (madatban, booking_channel, payment_method, note) VALUES (?, 'phone', 'cash', ?)",
                                    [$madatban, $depositLine]
                                );
                            }
                        } catch (Exception $e) {
                            error_log('Failed to record cash deposit for booking ' . $madatban . ': ' . $e->getMessage());
                        }
                    }
                    if ($paymentMethod === 'transfer' && $requiredDeposit > 0) {
                        $_SESSION['madatban'] = $madatban;
                        $_SESSION['payment_method'] = 'transfer';
                        $_SESSION['payment_expires'] = $paymentExpires ?? date('Y-m-d H:i:s', strtotime('+6 hours'));
                        $_SESSION['admin_payment_return'] = '/CNM/Admin/kaiadmin-lite-1.2.0/index.php?page=dsdatban';
                        admin_redirect('index.php?page=admin_payment&madatban=' . $madatban);
                } else {
                    unset($_SESSION['madatban'], $_SESSION['payment_method'], $_SESSION['payment_expires']);
                    $successMessage = 'Đã tạo đơn đặt bàn #' . $madatban . ' thành công.';
                    if ($paymentMethod === 'transfer' && $requiredDeposit <= 0) {
                        $successMessage .= ' Không cần đặt cọc vì tổng tạm tính bằng 0.';
                    }
                    $redirectUrl = 'index.php?page=dsdatban';
                    ?>
                    <!DOCTYPE html>
                    <html lang="vi">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Đã tạo đơn đặt bàn</title>
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                    </head>
                    <body class="bg-light">
                        <div class="container py-5">
                            <div class="row justify-content-center">
                                <div class="col-lg-6">
                                    <div class="card shadow-lg border-0">
                                        <div class="card-body text-center p-5">
                                            <div class="text-success display-4 mb-3">
                                                <i class="fas fa-check-circle"></i>
                                            </div>
                                            <h2 class="fw-bold mb-3">Tạo đơn đặt bàn thành công</h2>
                                            <p class="text-muted mb-4"><?php echo htmlspecialchars($successMessage); ?></p>
                                            <a href="<?php echo htmlspecialchars($redirectUrl); ?>" class="btn btn-primary btn-lg">
                                                <i class="fas fa-arrow-right me-2"></i>Đi tới danh sách đặt bàn
                                            </a>
                                        </div>
                                    </div>
                                    <p class="text-center text-muted small mt-3 mb-0">Bạn sẽ được chuyển tự động sau vài giây...</p>
                                </div>
                            </div>
                        </div>
                        <script>
                            setTimeout(function () {
                                window.location.href = <?php echo json_encode($redirectUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
                            }, 2000);
                        </script>
                    </body>
                    </html>
                    <?php
                    exit;
                }
            } else {
                $lastError = $bookingModel->getLastErrorCode();
                if ($lastError === 'table_conflict') {
                    $conflictIds = $bookingModel->getConflictingTableIds();
                    $conflictNames = [];
                    foreach ($tables as $table) {
                        $tid = (int)($table['idban'] ?? 0);
                        if ($tid > 0 && in_array($tid, $conflictIds, true)) {
                            $label = $table['soban'] ?? $tid;
                            $conflictNames[] = 'Bàn ' . $label;
                        }
                    }
                    if (empty($conflictNames)) {
                        $conflictNames[] = 'Một hoặc nhiều bàn đã chọn';
                    }
                    $_SESSION['admin_booking_step_errors'] = [
                        'step' => 2,
                        'messages' => [
                            implode(', ', $conflictNames) . ' đã được giữ trong khung giờ hiện tại. Vui lòng chọn bàn khác.'
                        ]
                    ];
                    if (isset($_SESSION['admin_booking_flow']['booking'])) {
                        $_SESSION['admin_booking_flow']['booking']['tables'] = [];
                        $_SESSION['admin_booking_flow']['booking']['total_surcharge'] = 0;
                    }
                    if (isset($_SESSION['admin_booking_flow']['financial'])) {
                        $estimated = $_SESSION['admin_booking_flow']['financial']['estimated_food'] ?? 0;
                        $_SESSION['admin_booking_flow']['financial']['total_amount'] = $estimated;
                    }
                    unset($_SESSION['admin_booking_flow']['prefill_table']);
                    admin_redirect('index.php?page=admin_booking&step=2' . $editQuerySuffix);
                }
                $errors[] = 'Không thể tạo đơn đặt bàn. Vui lòng thử lại.';
            }
        }
        }
    }
}

if (!isset($flow['menu']) || !is_array($flow['menu'])) {
    $defaultEstimate = $flow['financial']['estimated_food'] ?? 0;
    $flow['menu'] = [
        'mode' => 'none',
        'items' => [],
        'set' => null,
        'total' => $defaultEstimate
    ];
} else {
    if (!isset($flow['menu']['total'])) {
        $flow['menu']['total'] = $flow['financial']['estimated_food'] ?? 0;
    }
}

$menuCategories = [];
$menuDishes = [];
$menuSets = [];
$menuState = [
    'mode' => $flow['menu']['mode'] ?? 'none',
    'items' => $flow['menu']['items'] ?? [],
    'set' => $flow['menu']['set'] ?? null,
    'total' => $flow['menu']['total'] ?? ($flow['financial']['estimated_food'] ?? 0)
];

if ($step === 3) {
    $danhMucModel = new clsDanhMuc();
    $monAnModel = new clsMonAn();

    try {
        $menuCategories = $danhMucModel->getAllDanhMuc();
        $catLabels = [];
        foreach ($menuCategories as $cat) {
            $catId = isset($cat['iddm']) ? (int)$cat['iddm'] : (int)($cat['MaDM'] ?? 0);
            if ($catId > 0) {
                $catLabels[$catId] = $cat['tendanhmuc'] ?? ($cat['TenDM'] ?? 'Danh mục');
            }
        }
    } catch (Exception $e) {
        $menuCategories = [];
        $catLabels = [];
    }
    try {
        $menuDishes = $monAnModel->getAllMonAn();
        // Only keep approved & active dishes
        $menuDishes = array_values(array_filter($menuDishes, static function ($d) {
            $status = isset($d['TrangThai']) ? $d['TrangThai'] : (isset($d['trangthai']) ? $d['trangthai'] : null);
            $active = isset($d['hoatdong']) ? $d['hoatdong'] : (isset($d['hoatDong']) ? $d['hoatDong'] : null);
            return $status === 'approved' && $active === 'active';
        }));
    } catch (Exception $e) {
        $menuDishes = [];
    }

    try {
        // Only include approved & active menu sets
        $menuSets = $db->xuatdulieu("SELECT idthucdon, tenthucdon, mota, tongtien FROM thucdon WHERE TrangThai = 'approved' AND hoatdong = 'active' ORDER BY tongtien ASC");
    } catch (Exception $e) {
        $menuSets = [];
    }

    if (!empty($menuSets)) {
        $setIds = array_values(array_unique(array_filter(array_map(static function ($set) {
            return isset($set['idthucdon']) ? (int)$set['idthucdon'] : 0;
        }, $menuSets))));

        if (!empty($setIds)) {
            $placeholders = implode(',', array_fill(0, count($setIds), '?'));
            $setDishRows = $db->xuatdulieu_prepared(
                "SELECT ct.idthucdon, m.idmonan, m.tenmonan, m.DonGia, 1 AS SoLuong
                 FROM chitietthucdon ct
                 JOIN monan m ON ct.idmonan = m.idmonan
                 WHERE ct.idthucdon IN ($placeholders) AND m.TrangThai = 'approved' AND m.hoatdong = 'active' 
                 ORDER BY m.tenmonan",
                $setIds
            );

            $setDishMap = [];
            foreach ($setDishRows as $row) {
                $setKey = (int)($row['idthucdon'] ?? 0);
                if ($setKey <= 0) {
                    continue;
                }
                $setDishMap[$setKey][] = [
                    'idmonan' => (int)($row['idmonan'] ?? 0),
                    'tenmonan' => $row['tenmonan'] ?? '',
                    'DonGia' => (float)($row['DonGia'] ?? 0),
                    'soluong' => (int)($row['SoLuong'] ?? 1)
                ];
            }

            foreach ($menuSets as &$set) {
                $sid = (int)($set['idthucdon'] ?? 0);
                $set['monan'] = $setDishMap[$sid] ?? [];
            }
            unset($set);
        }
    }

}
$menuItemsJson = json_encode($menuState['items'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$menuSetJson = json_encode($menuState['set'] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$menuStateJson = json_encode($menuState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$areas = $db->xuatdulieu("SELECT MaKV, TenKV, PhuThu FROM khuvucban WHERE TrangThai = 'active'   ORDER BY TenKV");
$selectedArea = $flow['booking']['khuvuc'] ?? ($areas[0]['MaKV'] ?? 0);

if ($step === 2 && isset($_GET['prefill_table'])) {
    $prefillTable = (int)$_GET['prefill_table'];
    if ($prefillTable > 0) {
        $flow['prefill_table'] = $prefillTable;
    }
}

$existingTables = [];
if ($step === 2 && $selectedArea) {
    $existingTables = $bookingModel->getBanTheoKhuVuc($selectedArea);
}

$prefilledTableIds = isset($flow['booking']['tables']) ? array_column($flow['booking']['tables'], 'idban') : [];
if (isset($flow['prefill_table']) && !in_array($flow['prefill_table'], $prefilledTableIds, true)) {
    $prefilledTableIds[] = $flow['prefill_table'];
}

$selectedTablesMap = [];
if (!empty($flow['booking']['tables'])) {
    foreach ($flow['booking']['tables'] as $table) {
        $selectedTablesMap[$table['idban']] = $table;
    }
}

$stepTitles = [
    1 => 'Khởi tạo đơn đặt bàn',
    2 => 'Chọn thời gian & bàn',
    3 => 'Xác nhận & lưu đơn'
];
?>

<div class="container py-4">
    <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-concierge-bell me-2 text-primary"></i><?php echo $stepTitles[$step]; ?></h3>
            <small class="text-muted">Luồng đặt bàn cho nhân viên: Website • Tại quầy • Qua điện thoại</small>
        </div>
        <div>
            <a href="index.php?page=dsdatban" class="btn btn-light me-2"><i class="fas fa-arrow-left me-2"></i>Hủy</a>
            <a href="index.php?page=admin_booking&reset=1" class="btn btn-outline-secondary">Làm mới</a>
        </div>
    </div>

    <div class="mb-4">
        <div class="progress" style="height: 6px;">
            <div class="progress-bar" role="progressbar" style="width: <?php echo $step * 33; ?>%;" aria-valuenow="<?php echo $step * 33; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex justify-content-between mt-2 text-muted small">
            <span>Bước 1</span>
            <span>Bước 2</span>
            <span>Bước 3</span>
        </div>
    </div>

    <?php if ($isEditing && $editingBookingId): ?>
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="fas fa-pen-to-square"></i>
            <span>Đang chỉnh sửa đơn #<?php echo (int)$editingBookingId; ?>. Vui lòng lưu lại để cập nhật thông tin.</span>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <div class="admin-booking-wrapper py-4">
            <div class="step-progress"><span class="badge-step">Bước 1/3</span> • Thông tin khách</div>
            <form method="POST" class="booking-card">
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-uppercase small fw-semibold text-muted">Loại luồng đặt bàn</label>
                                <div class="option-radio-group">
                                    <?php $currentType = $flow['type'] ?? 'walkin'; ?>
                                    <input type="radio" name="booking_type" id="type-walkin" value="walkin" <?php echo $currentType === 'walkin' ? 'checked' : ''; ?> required>
                                    <label class="option-chip" for="type-walkin"><i class="fas fa-store"></i><span>Khách tới trực tiếp</span></label>
                                    <input type="radio" name="booking_type" id="type-phone" value="phone" <?php echo $currentType === 'phone' ? 'checked' : ''; ?>>
                                    <label class="option-chip" for="type-phone"><i class="fas fa-phone-volume"></i><span>Khách đặt qua điện thoại</span></label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase small fw-semibold text-muted">Tên khách hàng</label>
                                <div class="input-group rounded-4 border px-2 py-1">
                                    <span class="input-group-text border-0 bg-transparent"><i class="fas fa-user"></i></span>
                                    <input type="text" name="customer_name" class="form-control border-0 shadow-none" placeholder="Nguyễn Văn A" value="<?php echo htmlspecialchars($flow['customer']['name'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase small fw-semibold text-muted">Số điện thoại</label>
                                <div class="input-group rounded-4 border px-2 py-1">
                                    <span class="input-group-text border-0 bg-transparent"><i class="fas fa-mobile-alt"></i></span>
                                    <input type="text" name="customer_phone" class="form-control border-0 shadow-none" placeholder="09xxxxxxxx" value="<?php echo htmlspecialchars($flow['customer']['phone'] ?? ''); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase small fw-semibold text-muted">Email (nếu có)</label>
                                <div class="input-group rounded-4 border px-2 py-1">
                                    <span class="input-group-text border-0 bg-transparent"><i class="fas fa-envelope"></i></span>
                                    <input type="email" name="customer_email" class="form-control border-0 shadow-none" placeholder="email@domain.com" value="<?php echo htmlspecialchars($flow['customer']['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-uppercase small fw-semibold text-muted">Ghi chú của khách</label>
                                <div class="input-group rounded-4 border px-2 py-1">
                                    <span class="input-group-text border-0 bg-transparent"><i class="fas fa-sticky-note"></i></span>
                                    <input type="text" name="customer_note" class="form-control border-0 shadow-none" placeholder="Ví dụ: bàn gần cửa sổ, thêm ghế trẻ em..." value="<?php echo htmlspecialchars($flow['customer']['note'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-uppercase small fw-semibold text-muted">Phương thức thanh toán</label>
                                <?php
                                    $currentPayment = $flow['payment_method'] ?? 'cash';
                                    if (!in_array($currentPayment, ['cash', 'transfer'], true)) {
                                        $currentPayment = 'cash';
                                    }
                                ?>
                                <input type="hidden" name="payment_method" id="paymentMethodInput" value="<?php echo htmlspecialchars($currentPayment); ?>">
                                <div class="option-radio-group payment-group">
                                    <button type="button" class="option-chip <?php echo $currentPayment === 'cash' ? 'active' : ''; ?>" data-method="cash"><i class="fas fa-money-bill-wave"></i><span>Tiền mặt</span></button>
                                    <button type="button" class="option-chip <?php echo $currentPayment === 'transfer' ? 'active' : ''; ?>" data-method="transfer"><i class="fas fa-university"></i><span>Chuyển khoản (MoMo)</span></button>
                                </div>
                                <small class="text-muted d-block mt-2" id="paymentNote">Chọn phương thức khách dự kiến thanh toán khi đến nhà hàng.</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="booking-summary-card">
                            <h5><i class="fas fa-lightbulb me-2"></i>Gợi ý thao tác nhanh</h5>
                            <div class="summary-item">
                                <i class="fas fa-id-card"></i>
                                <span>Nhập chính xác thông tin để tiện theo dõi và liên hệ.</span>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-money-check-alt"></i>
                                <span>Luồng điện thoại mặc định yêu cầu chuyển khoản để giữ bàn.</span>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-headset"></i>
                                <span>Ghi chú giúp đội phục vụ chuẩn bị trước yêu cầu đặc biệt.</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-warning px-4"><i class="fas fa-arrow-right me-2"></i>Tiếp tục</button>
                </div>
            </form>
        </div>
    <?php elseif ($step === 2): ?>
        <?php
            $areaInfo = $bookingModel->getKhuVucInfo($selectedArea);
            $areaSurcharge = isset($areaInfo['PhuThu']) ? (float)$areaInfo['PhuThu'] : 0;
            $zones = [];
            if (!empty($existingTables)) {
                foreach ($existingTables as $table) {
                    $zoneKey = strtoupper($table['zone'] ?? 'A');
                    $zones[$zoneKey][] = $table;
                }
                ksort($zones);
            }
            $currentBookingDate = isset($flow['booking']['datetime']) ? date('Y-m-d', strtotime($flow['booking']['datetime'])) : date('Y-m-d');
            $defaultBookingTime = '18:00';
            $currentBookingTime = isset($flow['booking']['datetime']) ? date('H:i', strtotime($flow['booking']['datetime'])) : $defaultBookingTime;
            if (!empty($bookingTimeSlots) && !in_array($currentBookingTime, $bookingTimeSlots, true)) {
                if (in_array($defaultBookingTime, $bookingTimeSlots, true)) {
                    $currentBookingTime = $defaultBookingTime;
                } else {
                    $currentBookingTime = $bookingTimeSlots[0];
                }
            }
            if (empty($bookingTimeSlots)) {
                $bookingTimeSlots = [$currentBookingTime];
            }
            $selectedDateTimeDisplay = $currentBookingDate . ' ' . $currentBookingTime . ':00';
            $bookedTables = $selectedArea ? $bookingModel->getBanDaDat($selectedArea, $selectedDateTimeDisplay, $isEditing ? $editingBookingId : null) : [];
            $currentAreaName = 'Vui lòng chọn khu vực';
            foreach ($areas as $area) {
                if ((int)$area['MaKV'] === (int)$selectedArea) {
                    $currentAreaName = $area['TenKV'];
                    break;
                }
            }
            $zoneOrder = ['A', 'B', 'C', 'D'];
            $orderedZones = [];
            foreach ($zoneOrder as $label) {
                $orderedZones[$label] = $zones[$label] ?? [];
            }
            foreach ($zones as $label => $tablesByZone) {
                if (!isset($orderedZones[$label])) {
                    $orderedZones[$label] = $tablesByZone;
                }
            }
            $zoneIcons = [
                'A' => 'fa-seedling',
                'B' => 'fa-wine-glass-alt',
                'C' => 'fa-fire-alt',
                'D' => 'fa-moon-stars'
            ];
            $areaIconMap = [
                1 => 'fa-building',
                2 => 'fa-layer-group',
                3 => 'fa-tree',
                4 => 'fa-crown'
            ];
        ?>
        <div class="admin-booking-wrapper py-4">
            <div class="step-progress"><span class="badge-step">Bước 2/3</span> • Chọn thời gian &amp; bàn</div>
            <form method="POST" class="booking-card" id="adminStep2Form">
                <input type="hidden" name="form_action" id="form_action_step2" value="save">

                <div class="text-center mb-4">
                    <div class="d-inline-flex flex-wrap align-items-center gap-2">
                        <label class="fw-semibold text-muted mb-0"><i class="fas fa-chair me-2 text-warning"></i>Chọn khu vực:</label>
                        <select name="khuvuc" class="form-select w-auto shadow-sm rounded-pill border-0 px-3" onchange="document.getElementById('form_action_step2').value='refresh'; this.form.submit();">
                            <option value="">-- Chọn khu vực bàn --</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['MaKV']; ?>" <?php echo (int)$selectedArea === (int)$area['MaKV'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area['TenKV']); ?> (phụ thu <?php echo number_format($area['PhuThu']); ?>đ)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($areas)): ?>
                        <div class="area-chip-group" data-area-chip-group>
                            <?php foreach ($areas as $area): ?>
                                <?php
                                    $areaId = (int)$area['MaKV'];
                                    $activeClass = $areaId === (int)$selectedArea ? 'active' : '';
                                    $icon = $areaIconMap[$areaId] ?? 'fa-map-marker-alt';
                                ?>
                                <button type="button" class="area-chip <?php echo $activeClass; ?>" data-area-chip data-area-id="<?php echo $areaId; ?>">
                                    <strong><i class="fas <?php echo $icon; ?>"></i><?php echo htmlspecialchars($area['TenKV']); ?></strong>
                                    <span>Phụ thu: <?php echo number_format($area['PhuThu']); ?>đ</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <h4 class="mt-3">Khu vực hiện tại: <strong class="text-warning"><?php echo htmlspecialchars($currentAreaName); ?></strong></h4>
                </div>

                <div class="booking-meta-info mb-4">
                    <div class="meta-card">
                        <i class="fas fa-calendar-day"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Ngày đặt</div>
                            <input type="date" name="booking_date" class="form-control border-0 px-0 shadow-none" value="<?php echo htmlspecialchars($currentBookingDate); ?>" required>
                        </div>
                    </div>
                    <div class="meta-card">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Giờ đặt</div>
                            <select name="booking_time" class="form-select border-0 px-0 shadow-none" required>
                                <?php foreach ($bookingTimeSlots as $timeSlot): ?>
                                    <?php $displayTime = preg_replace('/^0/', '', $timeSlot); ?>
                                    <option value="<?php echo htmlspecialchars($timeSlot); ?>" <?php echo $timeSlot === $currentBookingTime ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($displayTime); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="meta-card">
                        <i class="fas fa-users"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Tổng khách dự kiến</div>
                            <div class="fw-bold text-primary summary-capacity">0</div>
                        </div>
                    </div>
                    <div class="meta-card">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Phụ thu dự kiến</div>
                            <div class="fw-bold text-primary summary-surcharge">0đ</div>
                        </div>
                    </div>
                </div>

                <div class="selected-tables mb-4" id="selectionSummary">
                    <h6 class="fw-bold mb-2"><i class="fas fa-clipboard-list me-2"></i>Bàn đã chọn</h6>
                    <div id="summaryList" class="d-flex flex-wrap gap-2"></div>
                    <div class="mt-2 small text-muted">
                        <span>Tổng chỗ ngồi: <span class="summary-capacity fw-semibold text-primary">0</span></span>
                        <span class="ms-3">Phụ thu dự kiến: <span class="summary-surcharge fw-semibold text-primary">0đ</span></span>
                    </div>
                </div>

                <div class="floor-layout">
                    <?php if (!$selectedArea): ?>
                        <div class="alert alert-info mb-3">Vui lòng chọn khu vực ở phía trên để hiển thị sơ đồ bàn.</div>
                    <?php endif; ?>

                    <?php
                        $primaryZones = ['A', 'B', 'C', 'D'];
                        $extraZones = array_diff(array_keys($orderedZones), $primaryZones);
                        $zoneClassMap = [
                            'A' => 'zone-card--warm',
                            'B' => 'zone-card--warm',
                            'C' => 'zone-card--cool',
                            'D' => 'zone-card--cool',
                        ];
                        $availableZones = array_keys($orderedZones);

                        $renderZoneCard = function ($zoneLabel) use ($orderedZones, $bookedTables, $prefilledTableIds, $areaSurcharge, $zoneIcons, $zoneClassMap) {
                            $zoneKey = strtoupper($zoneLabel);
                            $zoneTables = $orderedZones[$zoneKey] ?? [];
                            $zoneClass = $zoneClassMap[$zoneKey] ?? 'zone-card--neutral';
                            $tableCount = count($zoneTables);
                            ?>
                            <div class="zone-card <?php echo $zoneClass; ?>" data-zone-card="<?php echo htmlspecialchars($zoneKey); ?>">
                                <div class="zone-header">
                                    <h6><i class="fas <?php echo $zoneIcons[$zoneKey] ?? 'fa-layer-group'; ?>"></i>Khu vực <?php echo htmlspecialchars($zoneKey); ?></h6>
                                    <div class="zone-meta">
                                        <span><i class="fas fa-chair"></i><?php echo $tableCount; ?> bàn</span>
                                        <span><i class="fas fa-money-bill-wave"></i><?php echo number_format($areaSurcharge); ?>đ</span>
                                    </div>
                                </div>
                                <div class="zone-body">
                                    <?php if (!empty($zoneTables)): ?>
                                        <?php foreach ($zoneTables as $table): ?>
                                            <?php
                                                $tableId = (int)$table['idban'];
                                                $isBooked = in_array($tableId, $bookedTables, true);
                                                $isSelected = in_array($tableId, $prefilledTableIds, true);
                                                $capacity = (int)($table['soluongKH'] ?? 0);
                                                $tableName = $table['SoBan'] ?? $tableId;
                                            ?>
                                            <input type="checkbox" class="table-input" id="table-<?php echo $tableId; ?>" name="tables[]" value="<?php echo $tableId; ?>" <?php echo $isSelected ? 'checked' : ''; ?> <?php echo $isBooked ? 'disabled' : ''; ?>>
                                            <label for="table-<?php echo $tableId; ?>" class="table-btn <?php echo $capacity > 6 ? 'table-large' : 'table-small'; ?><?php echo $isSelected ? ' selected' : ''; ?>" data-capacity="<?php echo $capacity; ?>" data-phuthu="<?php echo $areaSurcharge; ?>" data-name="<?php echo htmlspecialchars($tableName); ?>">
                                                <span class="table-number">Bàn <?php echo htmlspecialchars($tableName); ?></span>
                                                <span class="capacity"><i class="fas fa-users"></i> <?php echo $capacity; ?> khách</span>
                                                <span class="surcharge"><i class="fas fa-money-bill-wave"></i> <?php echo number_format($areaSurcharge); ?>đ</span>
                                                <div class="capacity-icons">
                                                    <?php for ($i = 0; $i < min($capacity, 5); $i++): ?>
                                                        <i class="fas fa-user"></i>
                                                    <?php endfor; ?>
                                                    <?php if ($capacity > 5): ?>
                                                        <span>+<?php echo $capacity - 5; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($isBooked): ?>
                                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">Đã đặt</span>
                                                <?php endif; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="zone-empty">Hiện chưa có bàn thuộc khu vực <?php echo htmlspecialchars($zoneKey); ?>.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        };
                    ?>

                    <?php if (!empty($availableZones)): ?>
                        <div class="zone-filter" data-zone-filter>
                            <button type="button" class="filter-chip active" data-zone="all">
                                <i class="fas fa-border-all"></i>Tất cả
                            </button>
                            <?php foreach ($availableZones as $zoneLabel): ?>
                                <button type="button" class="filter-chip" data-zone="<?php echo htmlspecialchars(strtoupper($zoneLabel)); ?>">
                                    <i class="fas <?php echo $zoneIcons[strtoupper($zoneLabel)] ?? 'fa-layer-group'; ?>"></i>
                                    Khu vực <?php echo htmlspecialchars(strtoupper($zoneLabel)); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="zone-grid">
                        <?php foreach ($primaryZones as $zoneLabel): ?>
                            <?php $renderZoneCard($zoneLabel); ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($extraZones)): ?>
                        <div class="zone-grid">
                            <?php foreach ($extraZones as $zoneLabel): ?>
                                <?php $renderZoneCard($zoneLabel); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="book-step-actions mt-4">
                    <a href="index.php?page=admin_booking&step=1" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Quay lại
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" onclick="document.getElementById('form_action_step2').value='refresh'; this.form.submit();">
                            <i class="fas fa-sync me-1"></i>Cập nhật
                        </button>
                        <?php if ($isEditing && $editingBookingId): ?>
                            <button type="submit" class="btn btn-success" onclick="document.getElementById('form_action_step2').value='confirm_edit_tables';">
                                <i class="fas fa-check me-2"></i>Xác nhận sửa bàn
                            </button>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-warning" onclick="document.getElementById('form_action_step2').value='save';">
                            <i class="fas fa-arrow-right me-2"></i>Tiếp tục
                        </button>
                    </div>
                </div>
            </form>
        </div>
    <?php elseif ($step === 3): ?>
        <?php
            $booking = $flow['booking'] ?? [];
            $customer = $flow['customer'] ?? [];
            $financial = $flow['financial'] ?? ['estimated_food' => 0, 'total_amount' => $booking['total_surcharge'] ?? 0];
            $selectedTables = [];
            if (isset($booking['tables']) && is_array($booking['tables'])) {
                $selectedTables = $booking['tables'];
            }
            $displaySurcharge = $booking['total_surcharge'] ?? 0;
            $displayPeople = $booking['people_count'] ?? array_sum(array_map(static function ($t) {
                return (int)($t['capacity'] ?? 0);
            }, $selectedTables));
            $menuMode = $menuState['mode'] ?? 'none';
            $menuTotal = (float)($menuState['total'] ?? ($financial['estimated_food'] ?? 0));
            $menuItemsDisplay = [];
            if ($menuMode === 'set' && isset($menuState['set']['monan'])) {
                $menuItemsDisplay = $menuState['set']['monan'];
            } elseif ($menuMode === 'items') {
                $menuItemsDisplay = $menuState['items'] ?? [];
            }
            $menuSetName = $menuMode === 'set' ? ($menuState['set']['tenthucdon'] ?? '') : '';
            $menuBadgeText = 'Chưa chọn món';
            $menuIndicatorText = 'Chưa chọn món, tổng tiền món = ' . number_format($menuTotal) . 'đ';
            $menuItemsDisplayCount = count($menuItemsDisplay);
            if ($menuMode === 'items') {
                $menuBadgeText = $menuItemsDisplayCount . ' món đã thêm';
                $menuIndicatorText = 'Đã chọn ' . $menuItemsDisplayCount . ' món lẻ.';
            } elseif ($menuMode === 'set') {
                $menuBadgeText = $menuSetName !== '' ? $menuSetName : '1 thực đơn cố định';
                $menuIndicatorText = 'Áp dụng thực đơn cố định.';
            }
        ?>
        <form method="POST" class="card border-0 shadow-sm">
            <input type="hidden" name="menu_mode" id="menuModeInput" value="<?php echo htmlspecialchars($menuMode); ?>">
            <input type="hidden" name="selected_monan_payload" id="selectedMonanInput" value="<?php echo htmlspecialchars($menuItemsJson, ENT_QUOTES); ?>">
            <input type="hidden" name="selected_thucdon_payload" id="selectedThucdonInput" value="<?php echo htmlspecialchars($menuSetJson, ENT_QUOTES); ?>">
            <div class="card-body">
                <h5 class="card-title">Xác nhận thông tin</h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label text-muted">Khách hàng</label>
                        <p class="fs-5 mb-0"><?php echo htmlspecialchars($customer['name'] ?? '—'); ?></p>
                        <small class="text-muted"><?php echo htmlspecialchars($customer['phone'] ?? '—'); ?></small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted">Thời gian</label>
                        <p class="fs-5 mb-0"><?php echo isset($booking['datetime']) ? date('d/m/Y H:i', strtotime($booking['datetime'])) : '—'; ?></p>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label text-muted">Luồng đặt</label>
                        <p class="fs-5 mb-0">
                            <?php echo ($flow['type'] ?? 'walkin') === 'walkin' ? 'Khách tại nhà hàng' : 'Khách qua điện thoại'; ?>
                        </p>
                    </div>
                </div>
                <hr>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Danh sách bàn</h6>
                        <ul class="list-group list-group-flush">
                            <?php if (!empty($selectedTables)): ?>
                                <?php foreach ($selectedTables as $table): ?>
                                    <li class="list-group-item px-0 d-flex justify-content-between">
                                        <span>Bàn <?php echo htmlspecialchars($table['soban']); ?></span>
                                        <span><i class="fas fa-users me-1 text-primary"></i><?php echo (int)($table['capacity'] ?? 0); ?> khách</span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item px-0 text-muted fst-italic">Chưa chọn bàn</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Tính toán chi phí</h6>
                        <div class="mb-3">
                            <label class="form-label">Phụ thu khu vực</label>
                            <input type="text" class="form-control" value="<?php echo number_format($displaySurcharge); ?>đ" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tổng tiền món</label>
                            <div class="form-control-plaintext fs-5 fw-semibold mb-1"><?php echo number_format($menuTotal); ?>đ</div>
                            <small class="text-muted d-block mt-2">Giá trị được hệ thống tính từ món lẻ hoặc thực đơn đã chọn; nếu không chọn món sẽ là 0đ.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ghi chú nội bộ</label>
                            <textarea class="form-control" name="admin_note" rows="3" placeholder="Thông tin cần lưu ý cho ca làm việc"><?php echo htmlspecialchars($flow['admin_note'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="menu-selection-card" data-admin-menu-root>
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                                <div>
                                    <h6 class="fw-bold mb-1"><i class="fas fa-utensils me-2 text-warning"></i>Tùy chọn món ăn</h6>
                                    <small class="text-muted">Chọn món lẻ hoặc thực đơn để tự động tính tiền món cho khách.</small>
                                </div>
                                <button type="button" class="menu-reset-btn" data-menu-reset>
                                    <i class="fas fa-undo me-1"></i>Đặt lại lựa chọn
                                </button>
                            </div>
                            <ul class="nav nav-pills menu-mode-pills mt-3" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link <?php echo $menuMode === 'none' ? 'active' : ''; ?>" type="button" data-menu-mode="none">
                                        <i class="fas fa-ban"></i>Không chọn món
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link <?php echo $menuMode === 'items' ? 'active' : ''; ?>" type="button" data-menu-mode="items">
                                        <i class="fas fa-list-ul"></i>Chọn món lẻ
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link <?php echo $menuMode === 'set' ? 'active' : ''; ?>" type="button" data-menu-mode="set">
                                        <i class="fas fa-layer-group"></i>Chọn thực đơn
                                    </button>
                                </li>
                            </ul>
                            <div class="menu-panel <?php echo $menuMode === 'none' ? 'active' : ''; ?>" data-menu-panel="none">
                                <p class="text-muted mb-0 mt-3">Không chọn món cụ thể, tổng tiền món sẽ giữ ở mức 0đ.</p>
                            </div>
                            <div class="menu-panel <?php echo $menuMode === 'items' ? 'active' : ''; ?>" data-menu-panel="items">
                                <?php
                                    $dishQtyMap = [];
                                    if (!empty($menuState['items'])) {
                                        foreach ($menuState['items'] as $it) {
                                            $dishQtyMap[(int)$it['idmonan']] = (int)$it['soluong'];
                                        }
                                    }
                                ?>
                                <div class="menu-items-toolbar mt-2">
                                    <div>
                                        <label class="form-label small text-muted mb-1">Danh mục</label>
                                        <select class="form-select" id="menuCategoryFilter">
                                            <option value="">Tất cả danh mục</option>
                                            <?php foreach ($menuCategories as $cat): ?>
                                                <?php $catId = isset($cat['iddm']) ? (int)$cat['iddm'] : (int)($cat['MaDM'] ?? 0); ?>
                                                <option value="<?php echo $catId; ?>"><?php echo htmlspecialchars($cat['tendanhmuc'] ?? ($cat['TenDM'] ?? 'Danh mục')); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label small text-muted mb-1">Tìm món</label>
                                        <input type="text" id="menuDishSearch" class="form-control dish-search-input" placeholder="Nhập tên món...">
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block mb-1">Thêm nhanh</small>
                                        <span class="badge bg-warning text-dark">Chạm + / - trên từng món</span>
                                    </div>
                                </div>

                                <div class="dish-card-grid">
                                    <?php foreach ($menuDishes as $dish): ?>
                                        <?php
                                            $dishId = (int)($dish['idmonan'] ?? 0);
                                            $dishName = $dish['tenmonan'] ?? 'Món';
                                            $dishPrice = (float)($dish['DonGia'] ?? 0);
                                            $dishCat = isset($dish['iddm']) ? (int)$dish['iddm'] : (int)($dish['MaDM'] ?? 0);
                                            $dishQty = $dishQtyMap[$dishId] ?? 0;
                                            $dishImage = $dish['hinhanh'] ?? ($dish['HinhAnh'] ?? '');
                                            $dishImage = ltrim($dishImage, '/');
                                            $imageCandidate = $dishImage !== '' ? $dishImage : 'bg.jpg';
                                            $assetBase = 'assets/img/';
                                            $assetPath = __DIR__ . '/../../assets/img/';
                                            $useImage = $imageCandidate;
                                            if (!empty($assetPath) && !file_exists($assetPath . $imageCandidate)) {
                                                $useImage = 'bg.jpg';
                                            }
                                            $dishImageUrl = $assetBase . htmlspecialchars($useImage);
                                        ?>
                                        <div class="dish-card <?php echo $dishQty > 0 ? 'selected' : ''; ?>" data-dish-card data-dish-id="<?php echo $dishId; ?>" data-category="<?php echo $dishCat; ?>" data-price="<?php echo $dishPrice; ?>" data-name="<?php echo htmlspecialchars($dishName); ?>" data-image="<?php echo htmlspecialchars($dishImage); ?>">
                                            <div class="dish-thumb" style="background-image: url('<?php echo $dishImageUrl; ?>');"></div>
                                            <span class="dish-category">#<?php echo htmlspecialchars($catLabels[$dishCat] ?? 'Món'); ?></span>
                                            <span class="badge-price"><?php echo number_format($dishPrice); ?>đ</span>
                                            <div class="dish-body">
                                                <div class="d-flex justify-content-between align-items-start gap-2">
                                                    <h6 class="dish-title mb-0"><?php echo htmlspecialchars($dishName); ?></h6>
                                                    <?php if (!empty($dish['DonViTinh'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($dish['DonViTinh']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($dish['mota'])): ?>
                                                    <?php
                                                        $desc = $dish['mota'];
                                                        if (function_exists('mb_strimwidth')) {
                                                            $desc = mb_strimwidth($desc, 0, 70, '...');
                                                        } else {
                                                            $desc = strlen($desc) > 70 ? substr($desc, 0, 70) . '...' : $desc;
                                                        }
                                                    ?>
                                                    <p class="text-muted small mb-1" style="min-height:32px;"><?php echo htmlspecialchars($desc); ?></p>
                                                <?php endif; ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="dish-qty-control">
                                                        <button type="button" data-card-action="decrease"><i class="fas fa-minus"></i></button>
                                                        <span data-dish-qty><?php echo $dishQty; ?></span>
                                                        <button type="button" data-card-action="increase"><i class="fas fa-plus"></i></button>
                                                    </div>
                                                    <small class="text-muted">Thêm món</small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-sm align-middle selected-dish-table">
                                        <thead>
                                            <tr>
                                                <th>Món</th>
                                                <th class="text-center">Số lượng</th>
                                                <th class="text-end">Đơn giá</th>
                                                <th class="text-end">Thành tiền</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody id="selectedDishTableBody">
                                            <?php if ($menuMode === 'items' && !empty($menuState['items'])): ?>
                                                <?php foreach ($menuState['items'] as $item): ?>
                                                    <tr data-dish-id="<?php echo (int)$item['idmonan']; ?>">
                                                        <td><?php echo htmlspecialchars($item['tenmonan']); ?></td>
                                                        <td class="text-center">
                                                            <div class="dish-quantity-group">
                                                                <button type="button" class="btn-qty" data-action="decrease"><i class="fas fa-minus"></i></button>
                                                                <span><?php echo (int)$item['soluong']; ?></span>
                                                                <button type="button" class="btn-qty" data-action="increase"><i class="fas fa-plus"></i></button>
                                                            </div>
                                                        </td>
                                                        <td class="text-end"><?php echo number_format((float)$item['DonGia']); ?>đ</td>
                                                        <td class="text-end"><?php echo number_format((float)$item['DonGia'] * (int)$item['soluong']); ?>đ</td>
                                                        <td class="text-end">
                                                            <button type="button" class="dish-remove-btn" data-action="remove"><i class="fas fa-times"></i></button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr class="text-muted text-center" data-empty-row>
                                                    <td colspan="5"><em>Chưa chọn món nào.</em></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="menu-panel <?php echo $menuMode === 'set' ? 'active' : ''; ?>" data-menu-panel="set">
                                <?php if (empty($menuSets)): ?>
                                    <p class="text-muted mt-3 mb-0">Chưa có thực đơn nào trong hệ thống.</p>
                                <?php else: ?>
                                    <div class="row g-3 mt-2">
                                        <?php foreach ($menuSets as $set): ?>
                                            <?php
                                                $setId = (int)($set['idthucdon'] ?? 0);
                                                $isActive = ($menuMode === 'set' && isset($menuState['set']['id_thucdon']) && (int)$menuState['set']['id_thucdon'] === $setId);
                                                $setPayload = json_encode([
                                                    'id_thucdon' => $setId,
                                                    'tenthucdon' => $set['tenthucdon'] ?? '',
                                                    'tongtien' => isset($set['tongtien']) ? (float)$set['tongtien'] : null,
                                                    'monan' => $set['monan'] ?? []
                                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                            ?>
                                            <div class="col-lg-6">
                                                <div class="set-menu-card <?php echo $isActive ? 'active' : ''; ?>" data-set-id="<?php echo $setId; ?>" data-set-payload="<?php echo htmlspecialchars($setPayload, ENT_QUOTES); ?>">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1 text-warning"><?php echo htmlspecialchars($set['tenthucdon'] ?? 'Thực đơn'); ?></h6>
                                                            <?php if (!empty($set['mota'])): ?>
                                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars($set['mota']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" name="menu_set_choice" value="<?php echo $setId; ?>" <?php echo $isActive ? 'checked' : ''; ?>>
                                                        </div>
                                                    </div>
                                                    <div class="set-meta">
                                                        <span><i class="fas fa-utensils"></i><?php echo count($set['monan'] ?? []); ?> món</span>
                                                        <span><i class="fas fa-money-bill-wave"></i><?php echo isset($set['tongtien']) && $set['tongtien'] > 0 ? number_format($set['tongtien']) . 'đ' : 'Chưa có giá'; ?></span>
                                                    </div>
                                                    <?php if (!empty($set['monan'])): ?>
                                                        <ul class="mb-0">
                                                            <?php $limit = 6; $countItems = count($set['monan']); ?>
                                                            <?php foreach (array_slice($set['monan'], 0, $limit) as $item): ?>
                                                                <li><?php echo htmlspecialchars($item['tenmonan']); ?> <span class="text-muted">x<?php echo (int)$item['soluong']; ?></span></li>
                                                            <?php endforeach; ?>
                                                            <?php if ($countItems > $limit): ?>
                                                                <li class="text-muted fst-italic">... và <?php echo $countItems - $limit; ?> món khác</li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <h6 class="fw-semibold">Thực đơn đã chọn</h6>
                                    <div id="selectedSetSummary" class="bg-white border rounded-3 p-3">
                                        <?php if ($menuMode === 'set' && !empty($menuState['set']['monan'])): ?>
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong><?php echo htmlspecialchars($menuState['set']['tenthucdon'] ?? ''); ?></strong>
                                                <?php if (isset($menuState['set']['tongtien']) && $menuState['set']['tongtien'] > 0): ?>
                                                    <span class="badge bg-warning text-dark"><?php echo number_format($menuState['set']['tongtien']); ?>đ</span>
                                                <?php endif; ?>
                                            </div>
                                            <ul class="mb-0">
                                                <?php foreach ($menuState['set']['monan'] as $item): ?>
                                                    <li><?php echo htmlspecialchars($item['tenmonan']); ?> <span class="text-muted">x<?php echo (int)$item['soluong']; ?></span></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="text-muted mb-0"><em>Chưa chọn thực đơn.</em></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="menu-total-box mt-4">
                                <div>
                                    <small class="text-muted text-uppercase fw-semibold d-block">Tổng tiền món</small>
                                    <h5 class="mb-0" id="menuTotalDisplay"><?php echo number_format($menuTotal); ?>đ</h5>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted d-block" id="menuModeIndicator"><?php echo htmlspecialchars($menuIndicatorText); ?></small>
                                    <span class="badge bg-warning text-dark mt-1" id="menuSelectionBadge"><?php echo htmlspecialchars($menuBadgeText); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="text-muted">Tổng ước tính</span>
                        <h4 class="mb-0 text-primary" id="totalEstimateDisplay">
                            <?php echo number_format($displaySurcharge + $menuTotal); ?>đ
                        </h4>
                        <small class="text-muted">Số khách: <?php echo (int)$displayPeople; ?></small>
                    </div>
                    <?php
                        $paymentCode = $flow['payment_method'] ?? 'cash';
                        $paymentLabels = [
                            'cash' => 'Tiền mặt',
                            'transfer' => 'Chuyển khoản (MoMo)'
                        ];
                        if (!isset($paymentLabels[$paymentCode])) {
                            $paymentCode = 'cash';
                        }
                        $paymentDisplay = $paymentLabels[$paymentCode];
                    ?>
                    <div class="text-end">
                        <span class="text-muted">Phương thức thanh toán</span>
                        <p class="fw-semibold mb-0"><?php echo htmlspecialchars($paymentDisplay); ?></p>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between">
                <a href="index.php?page=admin_booking&step=2<?php echo $editQuerySuffix; ?>" class="btn btn-light">Quay lại</a>
                <button type="submit" class="btn btn-primary">Hoàn tất &amp; lưu đơn</button>
            </div>
        </form>
    <?php endif; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeRadios = document.querySelectorAll('input[name="booking_type"]');
            const paymentGroup = document.querySelector('.payment-group');
            const paymentInput = document.getElementById('paymentMethodInput');
            const paymentNote = document.getElementById('paymentNote');

            if (paymentGroup && paymentInput) {
                const chips = paymentGroup.querySelectorAll('.option-chip');

                function setPayment(method) {
                    chips.forEach(chip => chip.classList.toggle('active', chip.dataset.method === method));
                    paymentInput.value = method;
                }

                function updatePaymentState() {
                    const selectedType = document.querySelector('input[name="booking_type"]:checked');
                    const isPhone = selectedType && selectedType.value === 'phone';
                    chips.forEach(chip => {
                        const isTransfer = chip.dataset.method === 'transfer';
                        if (isPhone && !isTransfer) {
                            chip.classList.add('disabled');
                            chip.classList.remove('active');
                        } else {
                            chip.classList.remove('disabled');
                        }
                    });
                    if (isPhone) {
                        setPayment('transfer');
                        if (paymentNote) paymentNote.textContent = 'Luồng điện thoại ưu tiên chuyển khoản để giữ bàn.';
                    } else if (paymentNote) {
                        paymentNote.textContent = 'Chọn phương thức khách dự kiến thanh toán khi đến nhà hàng.';
                    }
                }

                chips.forEach(chip => {
                    chip.addEventListener('click', function () {
                        if (chip.classList.contains('disabled')) return;
                        setPayment(chip.dataset.method);
                    });
                });

                typeRadios.forEach(radio => radio.addEventListener('change', updatePaymentState));
                updatePaymentState();
            }

            const adminMenuState = <?php echo $menuStateJson ?: 'null'; ?>;
            const adminMenuSurcharge = <?php echo isset($displaySurcharge) ? (float)$displaySurcharge : 0; ?>;

            const menuRoot = document.querySelector('[data-admin-menu-root]');
            if (menuRoot) {
                const menuModeInput = document.getElementById('menuModeInput');
                const selectedMonanInput = document.getElementById('selectedMonanInput');
                const selectedThucdonInput = document.getElementById('selectedThucdonInput');
                const menuTotalDisplay = document.getElementById('menuTotalDisplay');
                const totalEstimateDisplay = document.getElementById('totalEstimateDisplay');
                const menuModeIndicator = document.getElementById('menuModeIndicator');
                const menuSelectionBadge = document.getElementById('menuSelectionBadge');
                const dishTableBody = document.getElementById('selectedDishTableBody');
                const categoryFilter = document.getElementById('menuCategoryFilter');
                const dishSearchInput = document.getElementById('menuDishSearch');
                const dishCards = Array.from(menuRoot.querySelectorAll('[data-dish-card]'));
                const menuPanels = menuRoot.querySelectorAll('.menu-panel');
                const menuTabs = menuRoot.querySelectorAll('[data-menu-mode]');
                const menuResetBtn = menuRoot.querySelector('[data-menu-reset]');
                const setCards = menuRoot.querySelectorAll('.set-menu-card');
                const selectedSetSummary = document.getElementById('selectedSetSummary');
                const dishOptions = [];

                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, function (match) {
                    const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
                    return map[match] || match;
                });

                const cloneDeep = (value) => {
                    try {
                        return JSON.parse(JSON.stringify(value));
                    } catch (err) {
                        return null;
                    }
                };

                const formatCurrency = (amount) => {
                    const numeric = Number(amount) || 0;
                    return numeric.toLocaleString('vi-VN') + 'đ';
                };

                const calculateItemsTotal = (items) => {
                    return (items || []).reduce((sum, item) => {
                        const price = Number(item.DonGia || 0);
                        const qty = Number(item.soluong || 0);
                        return sum + (price * qty);
                    }, 0);
                };

                const initialState = cloneDeep(adminMenuState) || {};
                let state = {
                    mode: initialState.mode || 'none',
                    items: Array.isArray(initialState.items) ? initialState.items : [],
                    set: initialState.set || null,
                    total: Number(initialState.total ?? 0)
                };

                if (state.set && !Array.isArray(state.set.monan)) {
                    state.set.monan = [];
                }

                const showPanelForMode = (mode) => {
                    menuTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.menuMode === mode));
                    menuPanels.forEach(panel => panel.classList.toggle('active', panel.dataset.menuPanel === mode));
                };

                const normalizeSetId = (payload) => {
                    if (!payload) return null;
                    return Number(payload.id_thucdon ?? payload.idthucdon ?? 0) || null;
                };

                const highlightSetCard = (payload) => {
                    const targetId = normalizeSetId(payload);
                    setCards.forEach(card => {
                        const cardId = Number(card.dataset.setId || 0);
                        const isActive = state.mode === 'set' && targetId && cardId === targetId;
                        card.classList.toggle('active', Boolean(isActive));
                        const radio = card.querySelector('input[type="radio"]');
                        if (radio) {
                            radio.checked = Boolean(isActive);
                        }
                    });
                };

                const applyDishFilter = (catId) => {
                    const searchText = (dishSearchInput ? dishSearchInput.value : '').trim().toLowerCase();
                    if (dishCards.length) {
                        dishCards.forEach(card => {
                            const optionCat = Number(card.dataset.category || 0);
                            const matchCat = !catId || optionCat === catId;
                            const matchSearch = !searchText || (card.dataset.name || '').toLowerCase().includes(searchText);
                            const shouldShow = matchCat && matchSearch;
                            card.classList.toggle('d-none', !shouldShow);
                        });
                    }
                };

                const updateTotalFromState = () => {
                    if (state.mode === 'items') {
                        state.total = calculateItemsTotal(state.items);
                    } else if (state.mode === 'set' && state.set) {
                        const setPrice = Number(state.set.tongtien || 0);
                        if (setPrice > 0) {
                            state.total = setPrice;
                        } else {
                            state.total = calculateItemsTotal(state.set.monan || []);
                        }
                    } else {
                        state.total = 0;
                    }
                    if (!Number.isFinite(state.total)) {
                        state.total = 0;
                    }
                };

                const renderDishTable = () => {
                    if (!dishTableBody) return;
                    if (!state.items.length) {
                        dishTableBody.innerHTML = '<tr class="text-muted text-center" data-empty-row><td colspan="5"><em>Chưa chọn món nào.</em></td></tr>';
                        return;
                    }
                    dishTableBody.innerHTML = state.items.map(item => {
                        const price = Number(item.DonGia || 0);
                        const qty = Number(item.soluong || 0);
                        return `<tr data-dish-id="${item.idmonan}">
                                    <td>${escapeHtml(item.tenmonan || '')}</td>
                                    <td class="text-center">
                                        <div class="dish-quantity-group">
                                            <button type="button" class="btn-qty" data-action="decrease"><i class="fas fa-minus"></i></button>
                                            <span>${qty}</span>
                                            <button type="button" class="btn-qty" data-action="increase"><i class="fas fa-plus"></i></button>
                                        </div>
                                    </td>
                                    <td class="text-end">${formatCurrency(price)}</td>
                                    <td class="text-end">${formatCurrency(price * qty)}</td>
                                    <td class="text-end"><button type="button" class="dish-remove-btn" data-action="remove"><i class="fas fa-times"></i></button></td>
                                </tr>`;
                    }).join('');
                };

                const renderSetSummary = () => {
                    if (!selectedSetSummary) return;
                    if (state.mode === 'set' && state.set && Array.isArray(state.set.monan) && state.set.monan.length) {
                        const setPrice = Number(state.set.tongtien || 0);
                        let html = '<div class="d-flex justify-content-between align-items-center mb-2">';
                        html += `<strong>${escapeHtml(state.set.tenthucdon || 'Thực đơn')}</strong>`;
                        if (setPrice > 0) {
                            html += `<span class="badge bg-warning text-dark">${formatCurrency(setPrice)}</span>`;
                        }
                        html += '</div><ul class="mb-0">';
                        html += state.set.monan.map(item => `<li>${escapeHtml(item.tenmonan || '')} <span class="text-muted">x${Number(item.soluong || 0)}</span></li>`).join('');
                        html += '</ul>';
                        selectedSetSummary.innerHTML = html;
                    } else {
                        selectedSetSummary.innerHTML = '<p class="text-muted mb-0"><em>Chưa chọn thực đơn.</em></p>';
                    }
                };

                const refreshSummary = () => {
                    renderDishTable();
                    renderSetSummary();
                    updateTotalFromState();
                    if (menuModeInput) {
                        menuModeInput.value = state.mode;
                    }
                    if (selectedMonanInput) {
                        selectedMonanInput.value = JSON.stringify(state.items);
                    }
                    if (selectedThucdonInput) {
                        selectedThucdonInput.value = JSON.stringify(state.set);
                    }
                    if (menuTotalDisplay) {
                        menuTotalDisplay.textContent = formatCurrency(state.total);
                    }
                    if (totalEstimateDisplay) {
                        totalEstimateDisplay.textContent = formatCurrency(state.total + adminMenuSurcharge);
                    }
                    if (menuModeIndicator) {
                        if (state.mode === 'items') {
                            menuModeIndicator.textContent = `Đã chọn ${state.items.length} món lẻ.`;
                        } else if (state.mode === 'set') {
                            menuModeIndicator.textContent = 'Áp dụng thực đơn cố định.';
                        } else {
                            menuModeIndicator.textContent = 'Chưa chọn món, tổng tiền món = ' + formatCurrency(state.total);
                        }
                    }
                    if (menuSelectionBadge) {
                        if (state.mode === 'set' && state.set && state.set.tenthucdon) {
                            menuSelectionBadge.textContent = state.set.tenthucdon;
                        } else if (state.mode === 'items') {
                            menuSelectionBadge.textContent = `${state.items.length} món đã thêm`;
                        } else {
                            menuSelectionBadge.textContent = 'Chưa chọn món';
                        }
                    }
                    if (dishCards.length) {
                        dishCards.forEach(card => {
                            const dishId = Number(card.dataset.dishId || 0);
                            const qtySpan = card.querySelector('[data-dish-qty]');
                            const item = state.items.find(it => Number(it.idmonan) === dishId);
                            const qty = item ? Number(item.soluong || 0) : 0;
                            if (qtySpan) qtySpan.textContent = qty;
                            card.classList.toggle('selected', qty > 0);
                        });
                    }
                };

                const setMode = (mode) => {
                    const validModes = ['none', 'items', 'set'];
                    if (!validModes.includes(mode)) {
                        mode = 'none';
                    }
                    state.mode = mode;
                    if (mode !== 'set') {
                        highlightSetCard(null);
                    } else {
                        highlightSetCard(state.set);
                    }
                    showPanelForMode(mode);
                    refreshSummary();
                };

                if (menuTabs.length) {
                    menuTabs.forEach(tab => {
                        tab.addEventListener('click', () => {
                            const mode = tab.dataset.menuMode || 'none';
                            if (mode === 'items' && !Array.isArray(state.items)) {
                                state.items = [];
                            }
                            if (mode === 'set' && (!state.set || !Array.isArray(state.set.monan))) {
                                state.set = null;
                            }
                            setMode(mode);
                        });
                    });
                }

                const changeDishQuantity = (dishId, delta, meta = {}) => {
                    if (!dishId || !Number.isFinite(delta)) return;
                    let idx = state.items.findIndex(item => Number(item.idmonan) === dishId);
                    if (idx === -1 && delta > 0) {
                        state.items.push({
                            idmonan: dishId,
                            tenmonan: meta.tenmonan || meta.name || '',
                            DonGia: Number(meta.DonGia || meta.price || 0),
                            soluong: 0
                        });
                        idx = state.items.length - 1;
                    }
                    if (idx === -1) return;
                    const currentQty = Number(state.items[idx].soluong || 0);
                    const newQty = Math.max(0, currentQty + delta);
                    state.items[idx].soluong = newQty;
                    if (newQty === 0) {
                        state.items.splice(idx, 1);
                    }
                    state.set = null;
                    setMode('items');
                };

                if (dishTableBody) {
                    dishTableBody.addEventListener('click', (event) => {
                        const button = event.target.closest('[data-action]');
                        if (!button) return;
                        const action = button.dataset.action;
                        const row = event.target.closest('tr[data-dish-id]');
                        if (!row) return;
                        const dishId = Number(row.dataset.dishId || 0);
                        const itemIndex = state.items.findIndex(item => Number(item.idmonan) === dishId);
                        if (itemIndex === -1) return;
                        if (action === 'increase') {
                            state.items[itemIndex].soluong = Number(state.items[itemIndex].soluong || 0) + 1;
                        } else if (action === 'decrease') {
                            state.items[itemIndex].soluong = Math.max(0, Number(state.items[itemIndex].soluong || 0) - 1);
                            if (state.items[itemIndex].soluong === 0) {
                                state.items.splice(itemIndex, 1);
                            }
                        } else if (action === 'remove') {
                            state.items.splice(itemIndex, 1);
                        }
                        if (!state.items.length) {
                            state.mode = 'none';
                        }
                        refreshSummary();
                    });
                }

                if (categoryFilter) {
                    categoryFilter.addEventListener('change', () => {
                        const catId = categoryFilter.value ? Number(categoryFilter.value) : 0;
                        applyDishFilter(catId);
                    });
                    applyDishFilter(categoryFilter.value ? Number(categoryFilter.value) : 0);
                }
                if (dishSearchInput) {
                    dishSearchInput.addEventListener('input', () => {
                        const catId = categoryFilter && categoryFilter.value ? Number(categoryFilter.value) : 0;
                        applyDishFilter(catId);
                    });
                }

                if (dishCards.length) {
                    dishCards.forEach(card => {
                        const deltaButtons = card.querySelectorAll('[data-card-action]');
                        deltaButtons.forEach(btn => {
                            btn.addEventListener('click', (ev) => {
                                ev.stopPropagation();
                                const action = btn.dataset.cardAction;
                                const delta = action === 'increase' ? 1 : -1;
                                changeDishQuantity(Number(card.dataset.dishId || 0), delta, {
                                    tenmonan: card.dataset.name || '',
                                    DonGia: Number(card.dataset.price || 0)
                                });
                            });
                        });
                    });
                }

                if (menuResetBtn) {
                    menuResetBtn.addEventListener('click', () => {
                        state.items = [];
                        state.set = null;
                        state.total = 0;
                        setMode('none');
                    });
                }

                if (setCards.length) {
                    setCards.forEach(card => {
                        card.addEventListener('click', () => {
                            let payload = null;
                            try {
                                payload = JSON.parse(card.dataset.setPayload || 'null');
                            } catch (err) {
                                payload = null;
                            }
                            if (!payload) return;
                            if (!Array.isArray(payload.monan)) {
                                payload.monan = [];
                            }
                            state.set = payload;
                            state.items = [];
                            setMode('set');
                        });
                    });
                    highlightSetCard(state.set);
                }

                if (state.mode === 'set' && !state.set) {
                    state.mode = 'none';
            }

            showPanelForMode(state.mode);
            refreshSummary();
        }

            const step2Form = document.getElementById('adminStep2Form');
            const formActionInput = document.getElementById('form_action_step2');
            const triggerStep2Refresh = () => {
                if (formActionInput) {
                    formActionInput.value = 'refresh';
                }
                if (step2Form) {
                    step2Form.submit();
                }
            };

            const bookingDateInput = document.querySelector('input[name="booking_date"]');
            if (bookingDateInput) {
                bookingDateInput.addEventListener('change', triggerStep2Refresh);
            }

            const bookingTimeSelect = document.querySelector('select[name="booking_time"]');
            if (bookingTimeSelect) {
                bookingTimeSelect.addEventListener('change', triggerStep2Refresh);
            }

            const areaChipGroup = document.querySelector('[data-area-chip-group]');
            if (areaChipGroup) {
                const areaChips = areaChipGroup.querySelectorAll('[data-area-chip]');
                const areaSelect = document.querySelector('select[name="khuvuc"]');

                const activateChip = (targetId) => {
                    areaChips.forEach(chip => {
                        chip.classList.toggle('active', Number(chip.dataset.areaId || 0) === Number(targetId));
                    });
                };

                if (areaSelect) {
                    areaSelect.addEventListener('change', () => {
                        activateChip(areaSelect.value || '');
                        triggerStep2Refresh();
                    });
                }

                areaChips.forEach(chip => {
                    chip.addEventListener('click', () => {
                        const areaId = chip.dataset.areaId || '';
                        if (areaSelect) {
                            areaSelect.value = areaId;
                        }
                        activateChip(areaId);
                        triggerStep2Refresh();
                    });
                });
            }
            const zoneFilter = document.querySelector('[data-zone-filter]');
            if (zoneFilter) {
                const filterChips = zoneFilter.querySelectorAll('.filter-chip');
                const zoneCards = document.querySelectorAll('[data-zone-card]');

                const applyZoneFilter = (targetZone) => {
                    zoneCards.forEach(card => {
                        const cardZone = card.dataset.zoneCard;
                        const visible = targetZone === 'all' || cardZone === targetZone;
                        card.classList.toggle('d-none', !visible);
                    });
                };

                filterChips.forEach(chip => {
                    chip.addEventListener('click', () => {
                        const targetZone = chip.dataset.zone;
                        filterChips.forEach(c => c.classList.remove('active'));
                        chip.classList.add('active');
                        applyZoneFilter(targetZone);
                    });
                });

                applyZoneFilter('all');
            }

            const summaryBox = document.getElementById('selectionSummary');
            const tableInputs = document.querySelectorAll('.table-input');
            if (summaryBox && tableInputs.length) {
                const summaryList = document.getElementById('summaryList');
                const capacityNodes = document.querySelectorAll('.summary-capacity');
                const surchargeNodes = document.querySelectorAll('.summary-surcharge');

                const refreshSummary = () => {
                    let totalCapacity = 0;
                    let totalSurcharge = 0;
                    summaryList.innerHTML = '';

                    tableInputs.forEach(input => {
                        const label = input.nextElementSibling;
                        if (!label) return;
                        if (input.checked) {
                            label.classList.add('selected');
                            const name = label.dataset.name || input.value;
                            const capacity = parseInt(label.dataset.capacity || '0', 10) || 0;
                            const surcharge = parseFloat(label.dataset.phuthu || '0') || 0;
                            totalCapacity += capacity;
                            totalSurcharge += surcharge;
                            const pill = document.createElement('span');
                            pill.className = 'summary-pill';
                            pill.innerHTML = '<i class="fas fa-chair"></i>Bàn ' + name;
                            summaryList.appendChild(pill);
                        } else {
                            label.classList.remove('selected');
                        }
                    });

                    capacityNodes.forEach(node => node.textContent = totalCapacity);
                    surchargeNodes.forEach(node => node.textContent = totalSurcharge.toLocaleString('vi-VN') + 'đ');
                    summaryBox.classList.toggle('active', summaryList.childElementCount > 0);
                    summaryBox.classList.toggle('d-none', summaryList.childElementCount === 0);
                };

                tableInputs.forEach(input => input.addEventListener('change', refreshSummary));
                refreshSummary();
            }
        });
    </script>
</div>
