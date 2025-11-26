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
    'page' => 'book_thucdon.php',
    'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
    'session_tables' => isset($_SESSION['selected_tables']) ? $_SESSION['selected_tables'] : 'not_set',
    'request' => $_SERVER['REQUEST_METHOD'],
    'post' => $_POST
];

// Kiểm tra session và POST
if (!isset($_SESSION['booking']) || !isset($_SESSION['selected_tables'])) {
    $_SESSION['error'] = 'Thông tin đặt bàn không tồn tại. Vui lòng thử lại.';
    $_SESSION['debug'][] = [
        'time' => date('Y-m-d H:i:s'),
        'error' => 'Session missing',
        'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
        'session_tables' => isset($_SESSION['selected_tables']) ? $_SESSION['selected_tables'] : 'not_set'
    ];
    header('Location: index.php?page=choose_order_type');
    exit;
}

// Lấy thông tin từ session
$booking = $_SESSION['booking'];
$tables = $_SESSION['selected_tables'];

// Kiểm tra trạng thái bàn
require_once 'class/clsconnect.php';
require_once 'class/clsmonan.php';
require_once 'class/clsdanhmuc.php';
require_once 'class/clsdatban.php';

$datban = new datban();
foreach ($tables as $table) {
    if (!$datban->checkAvailableTimeSlot($table['maban'], $booking['datetime'])) {
        $_SESSION['error'] = 'Một hoặc nhiều bàn đã được đặt hoặc tạm giữ. Vui lòng chọn lại.';
        $_SESSION['debug'][] = [
            'time' => date('Y-m-d H:i:s'),
            'error' => 'Table not available',
            'table' => $table['maban'],
            'datetime' => $booking['datetime']
        ];
        header('Location: index.php?page=booking');
        exit;
    }
}

// Tính tổng phụ phí
$total_phuthu = array_sum(array_column($tables, 'phuthu'));

// Hàm loại bỏ hậu tố timestamp trong tên file ảnh
function cleanImageName($filename) {
    // Loại bỏ hậu tố dạng _17585217xx
    return preg_replace('/_\d{10}\./', '.', $filename);
}

// Lấy danh sách thực đơn
try {
    $db = new connect_db();
    $people_count = $booking['people_count'];

    // Lấy chỉ các thực đơn được phê duyệt và đang hoạt động
    $sql = "SELECT * FROM thucdon WHERE trangthai = 'approved' AND hoatdong = 'active' ORDER BY tongtien ASC";
    $thucdonList = $db->xuatdulieu($sql);

    // Khởi tạo biến $no_suggestion
    $no_suggestion = empty($thucdonList);
} catch (Exception $e) {
    $_SESSION['debug'][] = [
        'time' => date('Y-m-d H:i:s'),
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ];
    $_SESSION['error'] = 'Lỗi kết nối cơ sở dữ liệu. Vui lòng thử lại.';
    header('Location: index.php?page=choose_order_type');
    exit;
}

// Debug ảnh
$missing_images = [];
foreach ($thucdonList as $thucdon) {
    $cleaned_image = cleanImageName($thucdon['hinhanh']);
    $image_path = 'img/' . $cleaned_image;
    if (!file_exists($image_path) || empty($thucdon['hinhanh'])) {
        $missing_images[] = ['original' => $thucdon['hinhanh'], 'cleaned' => $cleaned_image];
    }
}
if (!empty($missing_images)) {
    $_SESSION['debug'][] = [
        'time' => date('Y-m-d H:i:s'),
        'error' => 'Missing images',
        'files' => $missing_images
    ];
}

// Lấy chi tiết thực đơn
$monAn = new clsMonAn();
$selected_thucdon = isset($_SESSION['selected_thucdon']) ? $_SESSION['selected_thucdon'] : [];
if (!is_array($selected_thucdon)) {
    $selected_thucdon = [];
}
if (!isset($selected_thucdon['monan']) || !is_array($selected_thucdon['monan'])) {
    $selected_thucdon['monan'] = [];
}
if (!isset($selected_thucdon['thucdon_info'])) {
    $selected_thucdon['thucdon_info'] = null;
}
if (!empty($selected_thucdon['monan'])) {
    foreach ($selected_thucdon['monan'] as &$mon_selected) {
        if (empty($mon_selected['tendanhmuc'])) {
            $mon_selected['tendanhmuc'] = 'Khác';
        }
    }
    unset($mon_selected);
    $_SESSION['selected_thucdon'] = $selected_thucdon;
}

// Đồng bộ với selected_monan để tương thích với confirm_booking
if (!empty($selected_thucdon) && isset($selected_thucdon['monan'])) {
    $_SESSION['selected_monan'] = $selected_thucdon['monan'];
}

// Xử lý AJAX chọn thực đơn
if (isset($_GET['action']) && $_GET['action'] === 'select_thucdon') {
    // Đảm bảo không có output nào trước đó
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_thucdon'])) {
        try {
            $id_thucdon = (int)$_POST['id_thucdon'];

            // Kiểm tra trạng thái thucdon trước: chỉ chấp nhận thucdon được phê duyệt và đang hoạt động
            $sql_td = "SELECT idthucdon, tenthucdon, tongtien, trangthai, hoatdong FROM thucdon WHERE idthucdon = ? AND trangthai = 'approved' AND hoatdong = 'active' LIMIT 1";
            $td_rows = $db->xuatdulieu_prepared($sql_td, [$id_thucdon]);
            if (empty($td_rows) || !is_array($td_rows)) {
                echo json_encode(['status' => 'error', 'message' => 'Thực đơn không tồn tại hoặc không hoạt động']);
                exit;
            }
            $thucdonInfo = $td_rows[0];

            // Lấy danh sách món của thực đơn (chỉ món được phê duyệt & hoạt động)
            $sql = "SELECT m.*, dm.tendanhmuc, 1 as soluong 
                    FROM chitietthucdon ct 
                    JOIN monan m ON ct.idmonan = m.idmonan 
                    LEFT JOIN danhmuc dm ON m.iddm = dm.iddm
                    WHERE ct.idthucdon = ? AND m.TrangThai = 'approved' AND m.hoatdong = 'active'";
            $monanList = $db->xuatdulieu_prepared($sql, [$id_thucdon]);

            // Debug truy vấn món ăn
            $_SESSION['debug'][] = [
                'time' => date('Y-m-d H:i:s'),
                'action' => 'select_thucdon',
                'id_thucdon' => $id_thucdon,
                'thucdonInfo' => $thucdonInfo,
                'monanList' => $monanList,
                'sql' => $sql
            ];

            if (empty($monanList)) {
                echo json_encode(['status' => 'error', 'message' => 'Thực đơn không có món ăn nào hoặc món ăn không hoạt động']);
                exit;
            }

            // Lưu thông tin thucdon cùng tổng tiền (tongtien) nếu có
            $_SESSION['selected_thucdon'] = [
                'id_thucdon' => $id_thucdon,
                'thucdon_info' => $thucdonInfo,
                'monan' => array_map(function($mon) {
                    return [
                        'idmonan' => $mon['idmonan'],
                        'tenmonan' => $mon['tenmonan'],
                        'DonGia' => $mon['DonGia'],
                        'soluong' => $mon['soluong'],
                        'iddm' => $mon['iddm'],
                        'tendanhmuc' => !empty($mon['tendanhmuc']) ? $mon['tendanhmuc'] : 'Khác'
                    ];
                }, $monanList)
            ];
            echo json_encode(['status' => 'success', 'selected_thucdon' => $_SESSION['selected_thucdon'], 'monan_count' => count($monanList)]);
        } catch (Exception $e) {
            $_SESSION['debug'][] = [
                'time' => date('Y-m-d H:i:s'),
                'error' => 'Database query failed in select_thucdon',
                'message' => $e->getMessage(),
                'id_thucdon' => $id_thucdon
            ];
            echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn cơ sở dữ liệu: ' . $e->getMessage()]);
        }
    } else {
        $_SESSION['debug'][] = [
            'time' => date('Y-m-d H:i:s'),
            'error' => 'Invalid AJAX request',
            'post' => $_POST
        ];
        echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ']);
    }
    exit;
}

// Xử lý AJAX chỉnh sửa món
if (isset($_GET['action']) && $_GET['action'] === 'update_thucdon') {
    // Đảm bảo không có output nào trước đó
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_monan'])) {
        try {
            $selected_monan = json_decode($_POST['selected_monan'], true);
            if (is_array($selected_monan)) {
                $_SESSION['selected_thucdon']['monan'] = $selected_monan;
                echo json_encode(['status' => 'success']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
            }
        } catch (Exception $e) {
            $_SESSION['debug'][] = [
                'time' => date('Y-m-d H:i:s'),
                'error' => 'Error processing update_thucdon',
                'message' => $e->getMessage()
            ];
            echo json_encode(['status' => 'error', 'message' => 'Lỗi xử lý dữ liệu: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ']);
    }
    exit;
}

// Giới hạn số lượng món
$max_mon = $booking['people_count'] * 3;
$total_soluong = array_sum(array_column($selected_thucdon['monan'] ?? [], 'soluong'));
?>
