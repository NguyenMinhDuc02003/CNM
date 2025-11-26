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
    'page' => 'book_menu.php',
    'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
    'session_monan' => isset($_SESSION['selected_monan']) ? $_SESSION['selected_monan'] : 'not_set',
    'request' => $_SERVER['REQUEST_METHOD'],
    'post' => $_POST
];

require_once 'class/clsconnect.php';
require_once 'class/clsmonan.php';
require_once 'class/clsdanhmuc.php';
require_once 'class/clsdatban.php';

// Xử lý AJAX
if (isset($_GET['action'])) {
    $monAn = new clsMonAn();
    
    if ($_GET['action'] === 'filter_menu') {
        header('Content-Type: text/html; charset=UTF-8');
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $danhmuc = isset($_POST['danhmuc']) && $_POST['danhmuc'] !== '' ? (int)$_POST['danhmuc'] : 0;

        if ($search && $danhmuc) {
            $monAnList = $monAn->searchMonAnByDanhMuc($search, $danhmuc);
        } elseif ($search) {
            $monAnList = $monAn->searchMonAn($search);
        } elseif ($danhmuc) {
            $monAnList = $monAn->getMonAnByDanhMuc($danhmuc);
        } else {
            $monAnList = $monAn->getAllMonAn();
        }

        // Bảo đảm: lọc lại server-side để chỉ hiển thị món ăn đã được phê duyệt và đang hoạt động
        if (is_array($monAnList) && !empty($monAnList)) {
            $monAnList = array_values(array_filter($monAnList, function($m) {
                return isset($m['TrangThai']) && $m['TrangThai'] === 'approved' && isset($m['hoatdong']) && $m['hoatdong'] === 'active';
            }));
        }

        $_SESSION['debug'][] = [
            'time' => date('Y-m-d H:i:s'),
            'action' => 'filter_menu',
            'danhmuc' => $danhmuc,
            'search' => $search,
            'monAnList_count' => count($monAnList)
        ];

        ob_start();
        if (empty($monAnList)) {
            echo '<p>Không tìm thấy món ăn nào.</p>';
        } else {
            foreach ($monAnList as $mon) {
                ?>
                <div class="menu-item">
                    <img src="img/<?= htmlspecialchars($mon['hinhanh'] ?: 'default.jpg') ?>" alt="<?= htmlspecialchars($mon['tenmonan']) ?>">
                    <div class="menu-item-details">
                        <div>
                            <strong class="text-primary"><?= htmlspecialchars($mon['tenmonan']) ?></strong><br>
                            <p class="text-muted mb-2"><?= htmlspecialchars($mon['mota'] ?: 'Không có mô tả') ?></p>
                            <strong class="text-success">Giá: <?= number_format($mon['DonGia']) ?> VND</strong>
                        </div>
                        <button class="btn-choose" onclick="addMonAn(<?= $mon['idmonan'] ?>, '<?= addslashes($mon['tenmonan']) ?>', <?= $mon['DonGia'] ?>)">
                            <i class="fas fa-utensils me-2"></i>Chọn món ăn
                        </button>
                    </div>
                </div>
                <?php
            }
        }
        $html = ob_get_clean();
        echo $html;
        exit;
    }

    if ($_GET['action'] === 'update_order') {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_monan'])) {
            try {
                $selectedMonAn = json_decode($_POST['selected_monan'], true);
                if (is_array($selectedMonAn)) {
                    $_SESSION['selected_monan'] = $selectedMonAn;
                    echo json_encode(['status' => 'success']);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
                }
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        }
        exit;
    }
}

// Kiểm tra session và POST
// If booking exists but people_count missing, try to derive it from POST maban or session
if (isset($_SESSION['booking']) && (!isset($_SESSION['booking']['people_count']) || (int)$_SESSION['booking']['people_count'] < 1)) {
    $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_book_menu_start', 'post_maban' => $_POST['maban'] ?? null, 'session_selected_tables' => $_SESSION['selected_tables'] ?? null];
    $derived = 0;
    if (isset($_POST['maban']) && !empty($_POST['maban'])) {
        $tables = json_decode($_POST['maban'], true);
        if (is_array($tables)) {
            foreach ($tables as $t) {
                if (isset($t['capacity'])) $derived += (int)$t['capacity'];
                elseif (isset($t['soluongKH'])) $derived += (int)$t['soluongKH'];
            }
        }
    }
    if ($derived === 0 && isset($_SESSION['selected_tables']) && is_array($_SESSION['selected_tables']) && count($_SESSION['selected_tables'])>0) {
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
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "SELECT soluongKH FROM ban WHERE idban IN ($placeholders)";
                $rows = $db_temp->xuatdulieu_prepared($sql, $ids);
                if (is_array($rows)) {
                    foreach ($rows as $r) $derived += (int)$r['soluongKH'];
                }
            }
    }
    if ($derived > 0) {
        $_SESSION['booking']['people_count'] = $derived;
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_book_menu_success', 'derived' => $derived];
    } else {
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'derive_people_count_book_menu_failed'];
    }
}

if (!isset($_SESSION['booking']) || !isset($_POST['maban'])) {
    $_SESSION['error'] = 'Thông tin đặt bàn không tồn tại. Vui lòng thử lại.';
    header('Location: index.php?page=trangchu');
    exit;
}

// Lấy thông tin từ session và POST
$booking = $_SESSION['booking'];
$tables = json_decode($_POST['maban'], true);

// Nếu client gửi selected_monan (trở về từ confirm), cập nhật session
if (isset($_POST['selected_monan']) && !empty($_POST['selected_monan'])) {
    $selected_monan_data = json_decode($_POST['selected_monan'], true);
    if (is_array($selected_monan_data)) {
        $_SESSION['selected_monan'] = $selected_monan_data;
        // Khi người dùng chọn từng món (book_menu), xóa flag selected_thucdon nếu tồn tại
        if (isset($_SESSION['selected_thucdon'])) {
            unset($_SESSION['selected_thucdon']);
        }
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'rehydrate_selected_monan', 'count' => count($selected_monan_data)];
    }
}

// Nếu client gửi selected_thucdon (trở về từ confirm về book_thucdon), cập nhật session
if (isset($_POST['selected_thucdon']) && !empty($_POST['selected_thucdon'])) {
    $selected_thucdon_data = json_decode($_POST['selected_thucdon'], true);
    if (is_array($selected_thucdon_data)) {
        $_SESSION['selected_thucdon'] = $selected_thucdon_data;
        // Đồng bộ selected_monan với phần 'monan' trong selected_thucdon nếu có
        if (isset($selected_thucdon_data['monan']) && is_array($selected_thucdon_data['monan'])) {
            $_SESSION['selected_monan'] = $selected_thucdon_data['monan'];
        }
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'rehydrate_selected_thucdon'];
    }
}

// Nếu vào trang chọn món (book_menu) qua POST maban mà không gửi selected_monan/selected_thucdon,
// coi như người muốn bắt đầu chọn từng món mới -> xóa hết lựa chọn cũ trong session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maban']) && empty($_POST['selected_monan']) && empty($_POST['selected_thucdon'])) {
    $cleared = [];
    if (isset($_SESSION['selected_thucdon'])) {
        unset($_SESSION['selected_thucdon']);
        $cleared[] = 'selected_thucdon';
    }
    if (isset($_SESSION['selected_monan'])) {
        unset($_SESSION['selected_monan']);
        $cleared[] = 'selected_monan';
    }
    if (!empty($cleared)) {
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'action' => 'clear_all_selections_on_entry', 'cleared' => $cleared];
    }
}

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

// Kiểm tra trạng thái bàn
$datban = new datban();
foreach ($tables as $table) {
    if (!$datban->checkAvailableTimeSlot($table['maban'], $booking['datetime'])) {
        $_SESSION['error'] = 'Một hoặc nhiều bàn đã được đặt hoặc tạm giữ. Vui lòng chọn lại.';
        header('Location: index.php?page=booking');
        exit;
    }
}

// Lấy danh sách món ăn và danh mục
$monAn = new clsMonAn();
$danhMuc = new clsDanhMuc();
$danhMucList = $danhMuc->getAllDanhMuc();
$monAnList = $monAn->getAllMonAn();
// Bảo đảm: nếu lớp data không lọc, apply an extra server-side filter here as well
if (is_array($monAnList) && !empty($monAnList)) {
    $monAnList = array_values(array_filter($monAnList, function($m) {
        return isset($m['TrangThai']) && $m['TrangThai'] === 'approved' && isset($m['hoatdong']) && $m['hoatdong'] === 'active';
    }));
}

// Giới hạn số lượng món
$max_mon = $booking['people_count'] * 3; // 3 món/người
$selected_monan = isset($_SESSION['selected_monan']) ? $_SESSION['selected_monan'] : [];
$total_soluong = array_sum(array_column($selected_monan, 'soluong'));
?>