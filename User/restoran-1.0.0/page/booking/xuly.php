<?php

// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session
if (!isset($_SESSION['debug'])) {
    $_SESSION['debug'] = [];
}
$_SESSION['debug'][] = [
    'time' => date('Y-m-d H:i:s'),
    'page' => 'booking.php',
    'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
    'session_id' => session_id(),
    'request' => $_SERVER['REQUEST_METHOD'],
    'get' => $_GET,
    'post' => $_POST
];

require_once 'class/clsdatban.php';
require_once 'class/clskvban.php';
require_once 'class/clsconnect.php';

if (!isset($_GET['page'])) {
    $page = 'booking';
} else {
    $page = $_GET['page'];
}

// Xử lý yêu cầu POST hoặc GET
$maKhuVuc = null;
$datetime = null;
$people_count = 0;
$tenKhuVuc = '';
$dsBan = [];
$dsBanDaDat = [];
$selectKhuVuc = '';
$edit_mode = false;
$madatban = null;
$selected_tables = [];

// Unified POST/GET handling for initial booking parameters
// Accept requests that include at least 'khuvuc' and 'datetime'. 'people_count' is optional (derived later if missing).
if ((
    $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['khuvuc'], $_POST['datetime'])
) || (
    $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['khuvuc'], $_GET['datetime'])
)) {
    // Extract params from whichever superglobal they came from
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $maKhuVuc = (int)trim($_POST['khuvuc']);
        $datetime = trim($_POST['datetime']);
        $people_count = isset($_POST['people_count']) ? (int)trim($_POST['people_count']) : 0;
        $debugAction = 'set_booking_post';
    } else {
        $maKhuVuc = (int)trim($_GET['khuvuc']);
        $datetime = urldecode(trim($_GET['datetime']));
        $people_count = isset($_GET['people_count']) ? (int)trim($_GET['people_count']) : 0;
        $debugAction = 'set_booking_get';
    }

    // Basic validation: if people_count not provided, will try to derive later from selected tables
    if (empty($maKhuVuc) || empty($datetime)) {
        $_SESSION['error'] = 'Thông tin khu vực, thời gian hoặc số lượng người không hợp lệ.';
        header("Location: index.php?page=trangchu");
        exit;
    }

    // Kiểm tra khung giờ hoạt động (10:00–22:00)
    $bookingTime = strtotime($datetime);
    $hour = date('H', $bookingTime);
    if ($hour < 8 || $hour >= 22) {
        $_SESSION['error'] = 'Vui lòng chọn thời gian trong khung giờ hoạt động (8:00–22:00).';
        header("Location: index.php?page=trangchu");
        exit;
    }

    // Nếu người dùng không cung cấp people_count (ví dụ đã chuyển logic client-side để tính từ bàn),
    // cố gắng suy ra từ payload 'maban' (JSON) hoặc từ session 'selected_tables'.
    if (empty($people_count) || $people_count < 1) {
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_start', 'post_maban' => $_POST['maban'] ?? null, 'session_selected_tables' => $_SESSION['selected_tables'] ?? null];
        $derivedCount = 0;

        // 1) Nếu client gửi 'maban' JSON (booking.php sets this), dùng nó
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['maban'])) {
            $mabanJson = $_POST['maban'];
            $tables = json_decode($mabanJson, true);
            if (is_array($tables)) {
                foreach ($tables as $t) {
                    if (isset($t['capacity'])) {
                        $derivedCount += (int)$t['capacity'];
                    } elseif (isset($t['soluongKH'])) {
                        $derivedCount += (int)$t['soluongKH'];
                    }
                }
            }
        }

        // 2) Nếu vẫn không có, thử lấy từ session 'selected_tables' (mảng idban)
        if ($derivedCount === 0 && isset($_SESSION['selected_tables']) && is_array($_SESSION['selected_tables']) && count($_SESSION['selected_tables']) > 0) {
            // Normalize session selected_tables to array of ids (handles objects posted from client)
            $ids = [];
            foreach ($_SESSION['selected_tables'] as $t) {
                if (is_array($t)) {
                    if (isset($t['maban'])) $ids[] = (int)$t['maban'];
                    elseif (isset($t['idban'])) $ids[] = (int)$t['idban'];
                } else {
                    $ids[] = (int)$t;
                }
            }
            $ids = array_values(array_filter($ids, function($v) { return $v > 0; }));
            if (count($ids) > 0) {
                $db_temp = new connect_db();
                // Tạo placeholders an toàn
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "SELECT soluongKH FROM ban WHERE idban IN ($placeholders)";
                $rows = $db_temp->xuatdulieu_prepared($sql, $ids);
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        $derivedCount += (int)$r['soluongKH'];
                    }
                }
            }
        }

        if ($derivedCount > 0) {
            $people_count = $derivedCount;
            $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_success', 'derived' => $derivedCount];
        } else {
            $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_failed'];
        }
    }

    // Lưu vào session
    $_SESSION['booking'] = [
        'khuvuc' => $maKhuVuc,
        'datetime' => $datetime,
        'people_count' => $people_count
    ];
    $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => $debugAction, 'data' => $_SESSION['booking']];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $isEditPost = (!empty($_POST['edit_mode'])) || (isset($_POST['action']) && $_POST['action'] === 'edit_tables');
        if ($isEditPost) {
            $edit_mode = true;
            $madatban = isset($_POST['madatban']) ? (int)$_POST['madatban'] : (isset($_SESSION['madatban']) ? (int)$_SESSION['madatban'] : 0);
            if ($madatban > 0) {
                $_SESSION['madatban'] = $madatban;
            }
            $_SESSION['edit_mode'] = true;
            $_SESSION['selected_tables'] = [];
        }
    }

} elseif (isset($_GET['action']) && $_GET['action'] === 'edit_tables' && isset($_GET['madatban'])) {
    // Xử lý edit mode
    $edit_mode = true;
    $madatban = (int)$_GET['madatban'];
    
    // Lấy thông tin đặt bàn hiện tại
    $db = new connect_db();
    $sql = "SELECT * FROM datban WHERE madatban = ? AND idKH = ?";
    $booking_info = $db->xuatdulieu_prepared($sql, [$madatban, $_SESSION['khachhang_id']]);
    
    if (empty($booking_info)) {
        $_SESSION['error'] = 'Không tìm thấy đặt bàn hoặc bạn không có quyền sửa.';
        header("Location: index.php?page=profile");
        exit;
    }
    
    $booking = $booking_info[0];
    if (($booking['TrangThai'] ?? '') !== 'pending') {
        $_SESSION['error'] = 'Chỉ có thể chỉnh sửa bàn khi đơn hàng đang chờ xác nhận.';
        header("Location: index.php?page=profile#bookings");
        exit;
    }
    $datetime = $booking['NgayDatBan'];
    $people_count = $booking['SoLuongKhach'];
    
    // Lấy bàn đã chọn và MaKV từ bàn đầu tiên
    $sql = "SELECT cbd.idban, b.MaKV FROM chitiet_ban_datban cbd 
            JOIN ban b ON cbd.idban = b.idban 
            WHERE cbd.madatban = ? LIMIT 1";
    $table_info = $db->xuatdulieu_prepared($sql, [$madatban]);
    
    if (empty($table_info)) {
        $_SESSION['error'] = 'Không tìm thấy thông tin bàn cho đặt bàn này.';
        header("Location: index.php?page=profile");
        exit;
    }
    
    $maKhuVuc = $table_info[0]['MaKV'];
    
    // Lấy tất cả bàn đã chọn
    $sql = "SELECT idban FROM chitiet_ban_datban WHERE madatban = ?";
    $selected_tables_result = $db->xuatdulieu_prepared($sql, [$madatban]);
    $selected_tables = array_column($selected_tables_result, 'idban');
    
    // Lưu vào session
    $_SESSION['booking'] = [
        'khuvuc' => $maKhuVuc,
        'datetime' => $datetime,
        'people_count' => $people_count
    ];
    $_SESSION['selected_tables'] = $selected_tables;
    $_SESSION['edit_mode'] = true;
    $_SESSION['madatban'] = $madatban;
    
} elseif (isset($_SESSION['booking'])) {
    $maKhuVuc = $_SESSION['booking']['khuvuc'];
    $datetime = $_SESSION['booking']['datetime'];
    $people_count = $_SESSION['booking']['people_count'];
    $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'restore_booking_session', 'data' => $_SESSION['booking']];
} else {
    $_SESSION['error'] = 'Vui lòng chọn khu vực và thời gian trước khi chọn bàn.';
    header("Location: index.php?page=trangchu");
    exit;
}

// Nếu không phải chế độ chỉnh sửa thì đảm bảo xóa các flag edit_mode còn sót lại trong session
if (!isset($edit_mode) || !$edit_mode) {
    // chỉ unset nếu tồn tại để tránh notices
    if (isset($_SESSION['edit_mode']) || isset($_SESSION['madatban']) || isset($_SESSION['selected_tables'])) {
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'clear_edit_mode_session'];
    }
    unset($_SESSION['edit_mode']);
    unset($_SESSION['madatban']);
    unset($_SESSION['selected_tables']);
}

// Lấy danh sách bàn và khu vực
$ban = new datban();
$khuvuc = new KhuVucBan();
$dsBan = $ban->getBanTheoKhuVuc($maKhuVuc);
$dsBanDaDat = $ban->getBanDaDat($maKhuVuc, $datetime);
$selectKhuVuc = $khuvuc->selectKvban($maKhuVuc);
$tenKhuVuc = $khuvuc->getTenKhuVuc($maKhuVuc);

// Xử lý edit mode - loại bỏ bàn hiện tại khỏi danh sách bàn đã đặt
$dsBanCuaToi = [];
if ($edit_mode && !empty($selected_tables)) {
    $dsBanDaDat = array_filter($dsBanDaDat, function($banId) use ($selected_tables) {
        return !in_array($banId, $selected_tables);
    });
    $dsBanCuaToi = $selected_tables;
}

// Lấy phụ phí
$db = new connect_db();
$sql = "SELECT PhuThu FROM khuvucban WHERE MaKV = ?";
$phuThu = $db->xuatdulieu_prepared($sql, [$maKhuVuc])[0]['PhuThu'] ?? 0;

?>
