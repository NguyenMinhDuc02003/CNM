<?php
// Bắt đầu output buffering
ob_start();

// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Debug session
if (!isset($_SESSION['debug'])) {
    $_SESSION['debug'] = [];
}
$_SESSION['debug'][] = [
    'time' => date('Y-m-d H:i:s'),
    'page' => 'index.php',
    'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
    'session_madatban' => isset($_SESSION['madatban']) ? $_SESSION['madatban'] : 'not_set',
    'session_id' => session_id(),
    'get' => $_GET,
    'post' => $_POST
];

// Kiểm tra session cho các trang cần booking
$booking_pages = ['book_menu', 'book_thucdon', 'choose_order_type', 'confirm_booking', 'customer_info', 'payment', 'success','edit_menu'];
if (in_array($_GET['page'] ?? '', $booking_pages) && !isset($_SESSION['booking'])) {
    unset($_SESSION['madatban']);
    $_SESSION['error'] = 'Thông tin đặt bàn không tồn tại. Vui lòng đặt lại.';
    header('Location: index.php?page=trangchu');
    exit;
}

// Include layout
include('layout/header.php');

// Chỉ hiển thị menu khi không trong luồng đặt bàn
if (!isset($_GET['page']) || !in_array($_GET['page'], $booking_pages)) {
    include('layout/menu.php');
}

// Xử lý các trang
$page = isset($_GET['page']) ? $_GET['page'] : 'trangchu';

// Xử lý các trang đặc biệt
if ($page === 'menu_detail') {
    $pageFile = 'page/menu_detail/menu_detail.php';
} elseif ($page === 'payment_online') {
    $pageFile = 'page/payment_online/payment_online.php';
    // Backward-compatible fallback: some deployments store the file as page/payment_online.php
    if (!file_exists($pageFile) && file_exists('page/payment_online.php')) {
        $pageFile = 'page/payment_online.php';
    }
} elseif ($page === 'payment_success') {
    $pageFile = 'page/payment_success/payment_success.php';
} elseif ($page === 'edit_menu') {
    $pageFile = 'page/edit_menu/edit_menu.php';
} elseif ($page === 'edit_menu_items') {
    $pageFile = 'page/edit_menu/edit_menu_items.php';
} elseif ($page === 'edit_menu_sets') {
    $pageFile = 'page/edit_menu/edit_menu_sets.php';
} elseif ($page === 'save_menu_edit') {
    $pageFile = 'page/edit_menu/save_menu_edit.php';
} elseif ($page === 'qr_order') {
    $pageFile = 'page/qr_order/qr_order.php';
} else {
    $pageFile = 'page/' . $page . '/' . $page . '.php';
}

if (file_exists($pageFile)) {
    include($pageFile);
} else {
    include('page/trangchu/trangchu.php');
}

include('layout/footer.php');

// Kết thúc output buffering
ob_end_flush();
?>
