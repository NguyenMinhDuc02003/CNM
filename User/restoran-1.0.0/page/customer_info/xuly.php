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
    'page' => 'customer_info.php',
    'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
    'session_monan' => isset($_SESSION['selected_monan']) ? $_SESSION['selected_monan'] : 'not_set',
    'request' => $_SERVER['REQUEST_METHOD'],
    'post' => $_POST
];

require_once 'class/clsconnect.php';
require_once 'class/clsdatban.php';
require_once 'class/clskvban.php';

// Kiểm tra session và POST
// If booking exists but people_count missing, try to derive from POST maban JSON
if (isset($_SESSION['booking']) && (!isset($_SESSION['booking']['people_count']) || (int)$_SESSION['booking']['people_count'] < 1)) {
    $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_customer_info_start', 'post_maban' => $_POST['maban'] ?? null, 'session_selected_tables' => $_SESSION['selected_tables'] ?? null];
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
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_customer_info_success', 'derived' => $derived];
    } else {
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_customer_info_failed'];
    }
}

if (!isset($_SESSION['booking']) || !isset($_POST['maban']) || !isset($_POST['total_tien'])) {
    $_SESSION['error'] = 'Thông tin đặt bàn không đầy đủ. Vui lòng thử lại.';
    header('Location: index.php?page=confirm_booking');
    exit;
}

// Lấy thông tin từ session và POST
$booking = $_SESSION['booking'];
$tables = json_decode($_POST['maban'], true);
$total_tien = (float)$_POST['total_tien'];
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';

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

// Lấy thông tin khách hàng từ tài khoản đăng nhập (nếu có)
$user_info = ['tenKH' => '', 'email' => '', 'soDienThoai' => ''];
if (isset($_SESSION['khachhang_id'])) {
    // Sử dụng thông tin từ session đăng nhập
    $user_info['tenKH'] = $_SESSION['khachhang_name'] ?? '';
    $user_info['email'] = $_SESSION['khachhang_email'] ?? '';
    
    // Lấy số điện thoại từ database
    try {
        $db = new connect_db();
        $sql = "SELECT soDienThoai FROM khachhang WHERE idKH = ?";
        $result = $db->xuatdulieu_prepared($sql, [$_SESSION['khachhang_id']]);
        if (!empty($result)) {
            $user_info['soDienThoai'] = $result[0]['soDienThoai'] ?? '';
        }
    } catch (Exception $e) {
        // Không làm gì, giữ giá trị rỗng
    }
}

// Xử lý form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenKH'], $_POST['email'], $_POST['soDienThoai'])) {
    $datban = new datban();
    $tenKH = trim($_POST['tenKH']);
    $email = trim($_POST['email']);
    $soDienThoai = trim($_POST['soDienThoai']);
    $selected_payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cash';
    
    // Validate input
    if (empty($tenKH) || empty($email) || empty($soDienThoai)) {
        $_SESSION['error'] = 'Vui lòng nhập đầy đủ thông tin.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Email không hợp lệ.';
    } elseif (!preg_match('/^[0-9]{10}$/', $soDienThoai)) {
        $_SESSION['error'] = 'Số điện thoại phải là 10 chữ số.';
    } else {
        // Kiểm tra trạng thái bàn trước khi lưu
        foreach ($tables as $table) {
            if (!$datban->checkAvailableTimeSlot($table['maban'], $booking['datetime'])) {
                $_SESSION['error'] = 'Một hoặc nhiều bàn đã được đặt hoặc tạm giữ. Vui lòng chọn lại.';
                header('Location: index.php?page=booking');
                exit;
            }
        }

        // Tính thời gian hết hạn (6 giờ kể từ thời điểm tạo)
        $payment_expires = date('Y-m-d H:i:s', strtotime('+6 hours'));

        // Nếu đã có madatban pending trong session và DB, không cho tạo trùng
        if (!empty($_SESSION['madatban'])) {
            $existingId = (int)$_SESSION['madatban'];
            $existingRow = $datban->xuatdulieu_prepared("SELECT TrangThai FROM datban WHERE madatban = ? LIMIT 1", [$existingId]);
            if (!empty($existingRow) && $existingRow[0]['TrangThai'] !== 'canceled') {
                $_SESSION['error'] = 'Bạn đang có một đặt bàn chờ thanh toán. Vui lòng hoàn tất trước khi tạo mới.';
                header('Location: index.php?page=profile#bookings');
                exit;
            }
        }

        // FORCE: luôn tính số lượng người từ tổng sức chứa các bàn được POST (đảm bảo không tin vào input client)
        $derived_people = 0;
        foreach ($tables as $t) {
            if (isset($t['capacity'])) {
                $derived_people += (int)$t['capacity'];
            } elseif (isset($t['soluongKH'])) {
                $derived_people += (int)$t['soluongKH'];
            }
        }
        // Fallback: nếu vẫn 0, dùng giá trị trong session booking (nếu có)
        if ($derived_people <= 0 && isset($booking['people_count']) && (int)$booking['people_count'] > 0) {
            $derived_people = (int)$booking['people_count'];
        }

        // Cập nhật lại session để đảm bảo nhất quán
        $_SESSION['booking']['people_count'] = $derived_people;
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'force_people_count_from_tables', 'derived' => $derived_people];

        // Lưu vào cơ sở dữ liệu với trạng thái pending và thời gian hết hạn
        $madatban = $datban->saveDatBanWithPaymentHold(
            array_map(function($table) use ($booking) {
                return [
                    'idban' => $table['maban'],
                    'makv' => $booking['khuvuc'],
                    'phuthu' => $table['phuthu']
                ];
            }, $tables),
            $booking['datetime'],
            $derived_people,
            $total_tien,
            $tenKH,
            $email,
            $soDienThoai,
            $payment_expires
        );
        
        if ($madatban) {
            $_SESSION['madatban'] = $madatban;
            $_SESSION['payment_method'] = $selected_payment_method;
            $_SESSION['payment_expires'] = $payment_expires;

            if ($selected_payment_method === 'online') {
                // Ghi nhận giao dịch pending vào bảng thanh toán để theo dõi đặt cọc
                try {
                    $pendingAmount = (int)ceil($total_tien * 0.5);
                    if ($pendingAmount > 0) {
                        $paymentDb = new connect_db();
                        $insertPendingSql = "INSERT INTO thanhtoan (madatban, idDH, SoTien, PhuongThuc, TrangThai, NgayThanhToan, MaGiaoDich)
                                             VALUES (?, NULL, ?, 'momo', 'pending', NOW(), NULL)";
                        $paymentDb->tuychinh($insertPendingSql, [$madatban, $pendingAmount]);
                    }
                } catch (Exception $e) {
                    error_log('Failed to insert pending deposit for booking ' . $madatban . ': ' . $e->getMessage());
                }
                header('Location: page/payment_online.php');
            } else {
                header('Location: page/payment_success.php');
            }
            exit;
        } else {
            $lastError = $datban->getLastErrorCode();
            if ($lastError === 'table_conflict') {
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
                $_SESSION['error'] = implode(', ', $conflictNames) . ' đã được đặt trong cùng khung giờ. Vui lòng chọn lại.';
                header('Location: index.php?page=booking');
                exit;
            }
            $_SESSION['error'] = 'Đã có lỗi xảy ra khi lưu đơn đặt bàn. Vui lòng thử lại.';
        }
    }
}
?>
