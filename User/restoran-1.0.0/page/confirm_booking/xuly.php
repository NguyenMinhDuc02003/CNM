<?php
// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session
$_SESSION['debug'][] = [
    'time' => date('Y-m-d H:i:s'),
    'page' => 'confirm_booking.php',
    'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
    'session_monan' => isset($_SESSION['selected_monan']) ? $_SESSION['selected_monan'] : 'not_set',
    'request' => $_SERVER['REQUEST_METHOD'],
    'post' => $_POST
];

require_once 'class/clsconnect.php';
require_once 'class/clskvban.php';
require_once 'class/clsdatban.php';

// Kiểm tra session và POST
// If booking exists but people_count missing, try to derive from POST maban JSON
if (isset($_SESSION['booking']) && (!isset($_SESSION['booking']['people_count']) || (int)$_SESSION['booking']['people_count'] < 1)) {
    $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_confirm_start', 'post_maban' => $_POST['maban'] ?? null, 'session_selected_tables' => $_SESSION['selected_tables'] ?? null];
    $derived = 0;
    if (isset($_POST['maban']) && !empty($_POST['maban'])) {
        $tablesTmp = json_decode($_POST['maban'], true);
        if (is_array($tablesTmp)) {
            foreach ($tablesTmp as $t) {
                if (isset($t['capacity'])) $derived += (int)$t['capacity'];
                elseif (isset($t['soluongKH'])) $derived += (int)$t['soluongKH'];
            }
        }
    }
    if ($derived > 0) {
        $_SESSION['booking']['people_count'] = $derived;
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_confirm_success', 'derived' => $derived];
    } else {
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_confirm_failed'];
    }
}

if (!isset($_SESSION['booking']) || !isset($_POST['maban'])) {
    $_SESSION['error'] = 'Thông tin đặt bàn không tồn tại. Vui lòng thử lại.';
    header('Location: index.php?page=trangchu');
    exit;
}

// Lấy thông tin từ session và POST
$booking = $_SESSION['booking'];
$tables_json = $_POST['maban'];
$tables = json_decode($tables_json, true);

// Kiểm tra dữ liệu JSON
if (!is_array($tables) || empty($tables)) {
    $_SESSION['error'] = 'Danh sách bàn không hợp lệ. Vui lòng chọn lại.';
    header('Location: index.php?page=booking');
    exit;
}

// Kiểm tra cấu trúc của mỗi bàn
foreach ($tables as $table) {
    if (!isset($table['maban']) || !isset($table['soban']) || !isset($table['phuthu'])) {
        $_SESSION['error'] = 'Dữ liệu bàn không đầy đủ. Vui lòng chọn lại.';
        header('Location: index.php?page=booking');
        exit;
    }
}

$khuvuc = new KhuVucBan();
$tenKhuVuc = $khuvuc->getTenKhuVuc($booking['khuvuc']);

// If new food items are submitted from the menu page, update the session
if (isset($_POST['selected_monan']) && !empty($_POST['selected_monan'])) {
    $selected_monan_data = json_decode($_POST['selected_monan'], true);
    if (is_array($selected_monan_data)) {
        $_SESSION['selected_monan'] = $selected_monan_data;
    }
}

// If thucdon data is submitted, update the session
if (isset($_POST['selected_thucdon']) && !empty($_POST['selected_thucdon'])) {
    $selected_thucdon_data = json_decode($_POST['selected_thucdon'], true);
    if (is_array($selected_thucdon_data) && isset($selected_thucdon_data['monan'])) {
        $_SESSION['selected_thucdon'] = $selected_thucdon_data;
        $_SESSION['selected_monan'] = $selected_thucdon_data['monan'];
    }
}

// If user posts maban but does NOT include selected_monan/selected_thucdon,
// they explicitly chose NOT to select dishes — clear any previous selections.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maban']) && (empty($_POST['selected_monan']) && empty($_POST['selected_thucdon']))) {
    if (isset($_SESSION['selected_monan'])) unset($_SESSION['selected_monan']);
    if (isset($_SESSION['selected_thucdon'])) unset($_SESSION['selected_thucdon']);
    $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'clear_selections_user_chose_no'];
}

// Lấy danh sách món ăn từ session (nếu có)
$selected_monan = isset($_SESSION['selected_monan']) ? $_SESSION['selected_monan'] : [];

// Tính tổng tiền
$total_monan = 0;

// Nếu khách chọn thực đơn (book_thucdon), ưu tiên dùng cột `tongtien` của bảng thucdon
if (isset($_SESSION['selected_thucdon']) && !empty($_SESSION['selected_thucdon'])) {
    $st = $_SESSION['selected_thucdon'];
    // Nếu session chứa thông tin thucdon_info với tongtien, dùng luôn
    if (isset($st['thucdon_info']) && is_array($st['thucdon_info']) && isset($st['thucdon_info']['tongtien']) && is_numeric($st['thucdon_info']['tongtien'])) {
        $total_monan = (float)$st['thucdon_info']['tongtien'];
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'use_selected_thucdon_tongtien_from_session_thucdon_info', 'value' => $total_monan];
    }
    // Nếu client đã gửi tongtien tại root của payload, dùng luôn
    elseif (isset($st['tongtien']) && is_numeric($st['tongtien'])) {
        $total_monan = (float)$st['tongtien'];
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'use_selected_thucdon_tongtien_from_payload', 'value' => $total_monan];
    } else {
        // Cố gắng xác định id thucdon từ nhiều dạng field có thể xuất hiện
        $possible_ids = [];
        if (isset($st['id_thucdon'])) $possible_ids[] = $st['id_thucdon'];
        if (isset($st['idthucdon'])) $possible_ids[] = $st['idthucdon'];
        if (isset($st['thucdon_info']['id_thucdon'])) $possible_ids[] = $st['thucdon_info']['id_thucdon'];
        if (isset($st['thucdon_info']['idthucdon'])) $possible_ids[] = $st['thucdon_info']['idthucdon'];
        $found_id = null;
        foreach ($possible_ids as $pid) {
            if (!empty($pid) && is_numeric($pid)) { $found_id = (int)$pid; break; }
        }
        if ($found_id) {
            // Ngược lại, truy vấn DB lấy tongtien theo idthucdon
            try {
                $dbq = new connect_db();
                $sql_td = "SELECT tongtien FROM thucdon WHERE idthucdon = ? LIMIT 1";
                $res_td = $dbq->xuatdulieu_prepared($sql_td, [$found_id]);
                if (!empty($res_td) && isset($res_td[0]['tongtien'])) {
                    $total_monan = (float)$res_td[0]['tongtien'];
                    $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'use_selected_thucdon_tongtien_from_db', 'idthucdon' => $found_id, 'value' => $total_monan];
                }
            } catch (Exception $e) {
                // nếu lỗi, fallback về tính từng món
                $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'selected_thucdon_db_error', 'error' => $e->getMessage()];
            }
        }
    }

    // Nếu vẫn chưa có giá từ thucdon, fallback sang tổng món (đã chọn cá nhân)
    if ($total_monan <= 0) {
        $total_monan = array_sum(array_map(function($item) {
            return $item['DonGia'] * $item['soluong'];
        }, $selected_monan));
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'fallback_calculate_total_monan_from_items', 'value' => $total_monan];
    }
} else {
    // Nếu không chọn thực đơn, tính bình thường theo món đã chọn
    $total_monan = array_sum(array_map(function($item) {
        return $item['DonGia'] * $item['soluong'];
    }, $selected_monan));
}
$total_phuthu = array_sum(array_map(function($table) {
    return $table['phuthu'];
}, $tables));
$total_tien = $total_monan + $total_phuthu;

// Khóa bàn tạm thời (Pending) và kiểm tra trạng thái
$datban = new datban();
if (!$datban->assertTablesAvailable($tables, $booking['datetime'])) {
    $conflictIds = $datban->getConflictingTableIds();
    $conflictNames = [];
    foreach ($tables as $table) {
        $tid = isset($table['maban']) ? (int)$table['maban'] : (int)($table['idban'] ?? 0);
        if ($tid > 0 && in_array($tid, $conflictIds, true)) {
            $conflictNames[] = 'Bàn ' . ($table['soban'] ?? $tid);
        }
    }
    if (empty($conflictNames)) {
        $conflictNames[] = 'Một hoặc nhiều bàn đã chọn';
    }
    $_SESSION['selected_tables'] = [];
    $_SESSION['error'] = implode(', ', $conflictNames) . ' đã được đặt hoặc giữ trong khung giờ này. Vui lòng chọn lại.';
    header('Location: index.php?page=booking');
    exit;
}
?>
