<?php
// Kiểm tra đăng nhập
if (!isset($_SESSION['khachhang_id'])) {
    header("Location: index.php?page=login");
    exit;
}

require_once('class/clsconnect.php');
$db = new connect_db();

if (!function_exists('ensureOrderRatingColumn')) {
    function ensureOrderRatingColumn(connect_db $db): bool
    {
        try {
            if (!$db->hasColumn('donhang', 'DanhGia')) {
                $db->executeRaw("ALTER TABLE donhang ADD COLUMN DanhGia TINYINT UNSIGNED NULL DEFAULT NULL COMMENT 'Rating 1-5 sao từ khách hàng'");
            }
            return $db->hasColumn('donhang', 'DanhGia');
        } catch (Exception $e) {
            error_log('ensureOrderRatingColumn error: ' . $e->getMessage());
            try {
                return $db->hasColumn('donhang', 'DanhGia');
            } catch (Exception $inner) {
                return false;
            }
        }
    }
}

$orderRatingEnabled = ensureOrderRatingColumn($db);

// Lấy thông tin khách hàng
$sql = "SELECT * FROM khachhang WHERE idKH = ?";
$result = $db->xuatdulieu_prepared($sql, [$_SESSION['khachhang_id']]);
$user = $result[0];

// Xử lý cập nhật thông tin
$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $tenKH = trim($_POST['tenKH']);
        $email = trim($_POST['email']);
        $sodienthoai = trim($_POST['sodienthoai']);
        $ngaysinh = $_POST['ngaysinh'];
        $gioitinh = $_POST['gioitinh'];

        try {
            $sql = "UPDATE khachhang SET tenKH = ?, email = ?, sodienthoai = ?, ngaysinh = ?, gioitinh = ? WHERE idKH = ?";
            $params = [$tenKH, $email, $sodienthoai, $ngaysinh, $gioitinh, $_SESSION['khachhang_id']];
            
            if ($db->tuychinh($sql, $params)) {
                $success = "Cập nhật thông tin thành công!";
                $_SESSION['khachhang_name'] = $tenKH;
                $_SESSION['khachhang_email'] = $email;
                
                // Cập nhật lại thông tin user
                $result = $db->xuatdulieu_prepared("SELECT * FROM khachhang WHERE idKH = ?", [$_SESSION['khachhang_id']]);
                $user = $result[0];
            } else {
                $error = "Có lỗi xảy ra khi cập nhật thông tin!";
            }
        } catch (Exception $e) {
            $error = "Lỗi: " . $e->getMessage();
        }
    }
    
    // Xử lý đổi mật khẩu
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error = "Mật khẩu mới không khớp!";
        } else {
            // Kiểm tra mật khẩu hiện tại
            if (password_verify($current_password, $user['password'])) {
                $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $sql = "UPDATE khachhang SET password = ? WHERE idKH = ?";
                
                if ($db->tuychinh($sql, [$new_password_hash, $_SESSION['khachhang_id']])) {
                    $success = "Đổi mật khẩu thành công!";
                } else {
                    $error = "Có lỗi xảy ra khi đổi mật khẩu!";
                }
            } else {
                $error = "Mật khẩu hiện tại không đúng!";
            }
        }
    }
}

if (isset($_SESSION['success']) && $_SESSION['success']) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error']) && $_SESSION['error']) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!-- SweetAlert2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

<!-- Hero Start -->
<div class="container-xxl py-5 bg-dark hero-header mb-5">
    <div class="container text-center my-5 pt-5 pb-4">
        <h1 class="display-3 text-white mb-3 animated slideInDown">Thông Tin Cá Nhân</h1>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb justify-content-center text-uppercase">
                <li class="breadcrumb-item"><a href="index.php">Trang Chủ</a></li>
                <li class="breadcrumb-item text-white active" aria-current="page">Thông Tin Cá Nhân</li>
            </ol>
        </nav>
    </div>
</div>
<!-- Hero End -->

<div class="container py-5">
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="profile-section">
        <div class="profile-header">
            <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($user['email']))); ?>?s=200&d=mp" alt="Avatar" class="profile-avatar">
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['tenKH']); ?></h2>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
        </div>

        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="info-tab" data-bs-toggle="tab" href="#info" role="tab">
                    <i class="fas fa-user me-2"></i>Thông tin cá nhân
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="security-tab" data-bs-toggle="tab" href="#security" role="tab">
                    <i class="fas fa-lock me-2"></i>Bảo mật
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="bookings-tab" data-bs-toggle="tab" href="#bookings" role="tab">
                    <i class="fas fa-history me-2"></i>Lịch sử đặt bàn
                </a>
            </li>
        </ul>

        <div class="tab-content" id="profileTabsContent">
            <!-- Thông tin cá nhân -->
            <div class="tab-pane fade show active" id="info" role="tabpanel">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tenKH" class="form-label">Họ và tên</label>
                                <input type="text" class="form-control" id="tenKH" name="tenKH" 
                                       value="<?php echo htmlspecialchars($user['tenKH']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sodienthoai" class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" id="sodienthoai" name="sodienthoai" 
                                       value="<?php echo htmlspecialchars($user['sodienthoai']); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="ngaysinh" class="form-label">Ngày sinh</label>
                                <input type="date" class="form-control" id="ngaysinh" name="ngaysinh" 
                                       value="<?php echo $user['ngaysinh']; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Giới tính</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gioitinh" id="nam" 
                                               value="Nam" <?php echo ($user['gioitinh'] == 'Nam') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="nam">Nam</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="gioitinh" id="nu" 
                                               value="Nữ" <?php echo ($user['gioitinh'] == 'Nữ') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="nu">Nữ</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Lưu thay đổi
                    </button>
                </form>
            </div>

            <!-- Bảo mật -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                                <input type="password" class="form-control" id="current_password" 
                                       name="current_password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Mật khẩu mới</label>
                                <input type="password" class="form-control" id="new_password" 
                                       name="new_password" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                                <input type="password" class="form-control" id="confirm_password" 
                                       name="confirm_password" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-key me-2"></i>Đổi mật khẩu
                    </button>
                </form>
            </div>

            <!-- Lịch sử đặt bàn -->
            <div class="tab-pane fade" id="bookings" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Lịch sử đặt bàn</h4>
                    <div class="booking-filters">
                        <select class="form-select form-select-sm d-inline-block w-auto me-2" id="statusFilter" onchange="filterBookings()">
                            <option value="">Tất cả trạng thái</option>
                            <option value="pending">Chờ xác nhận</option>
                            <option value="confirmed">Đã xác nhận</option>
                            <option value="completed">Hoàn thành</option>
                            <option value="canceled">Đã hủy</option>
                        </select>
                        <select class="form-select form-select-sm d-inline-block w-auto" id="sortOrder" onchange="sortBookings()">
                            <option value="newest" selected>Mới nhất</option>
                            <option value="oldest">Cũ nhất</option>
                        </select>
                    </div>
                </div>

                <?php
                $sql = "SELECT d.*, 
                               GROUP_CONCAT(b.SoBan SEPARATOR ', ') as SoBan,
                               GROUP_CONCAT(kv.TenKV SEPARATOR ', ') as TenKV
                       FROM datban d 
                       LEFT JOIN chitiet_ban_datban cbd ON d.madatban = cbd.madatban
                       LEFT JOIN ban b ON cbd.idban = b.idban 
                       LEFT JOIN khuvucban kv ON b.MaKV = kv.MaKV 
                       WHERE d.idKH = ? 
                       GROUP BY d.madatban
                       ORDER BY d.NgayDatBan DESC";
                $bookings = $db->xuatdulieu_prepared($sql, [$_SESSION['khachhang_id']]);

                if (!empty($bookings)):
                    echo '<div id="bookingsContainer">';
                    foreach ($bookings as $booking):
                        $status_class = '';
                        $status_text = '';
                        $can_edit = false;
                        $can_cancel = false;
                        
                        // Kiểm tra ràng buộc thời gian (chỉ cho sửa trước 12h)
                        $booking_time = strtotime($booking['NgayDatBan']);
                        $current_time = time();
                $time_diff = $booking_time - $current_time;
                $hours_until_booking = $time_diff / 3600; // Chuyển đổi sang giờ
                
                        // Chỉ cho phép sửa nếu còn ít nhất 12 tiếng trước khi đặt bàn
                        $can_edit_time = $hours_until_booking >= 12;
                        $is_pending = ($booking['TrangThai'] === 'pending');
                        
                        // Kiểm tra xem có cần thanh toán không (chỉ cho đặt bàn có trạng thái pending)
                        $can_pay = $is_pending && $can_edit_time;
                        
                        $order_info = null;
                        $order_outstanding = 0;
                        $order_status_class = '';
                        $order_status_label = '';
                        $can_pay_order = false;

                        $orderSelectSql = "SELECT d.idDH, d.TrangThai, d.TongTien, d.MaDonHang, d.NgayDatHang";
                        if ($orderRatingEnabled) {
                            $orderSelectSql .= ", d.DanhGia";
                        }
                        $orderSelectSql .= ", meta.table_label, meta.area_name
                             FROM donhang d
                             LEFT JOIN donhang_admin_meta meta ON meta.idDH = d.idDH
                             WHERE d.madatban = ?
                             ORDER BY d.idDH DESC
                             LIMIT 1";

                        $orderRows = $db->xuatdulieu_prepared($orderSelectSql, [$booking['madatban']]);

                        if (!empty($orderRows)) {
                            $order_info = $orderRows[0];
                            $paidRows = $db->xuatdulieu_prepared(
                                "SELECT COALESCE(SUM(SoTien), 0) AS paid
                                 FROM thanhtoan
                                 WHERE (madatban = ? OR idDH = ?) AND TrangThai = 'completed'",
                                [$booking['madatban'], $order_info['idDH']]
                            );
                            $paidAmount = isset($paidRows[0]['paid']) ? (float)$paidRows[0]['paid'] : 0.0;
                            $order_total = isset($order_info['TongTien']) ? (float)$order_info['TongTien'] : 0.0;
                            $order_outstanding = max(0, $order_total - $paidAmount);

                            switch($order_info['TrangThai']) {
                                case 'dang_phuc_vu':
                                    $order_status_class = 'badge bg-warning text-dark';
                                    $order_status_label = 'Đang phục vụ';
                                    break;
                                case 'cho_thanh_toan':
                                    $order_status_class = 'badge bg-info text-dark';
                                    $order_status_label = 'Chờ thanh toán';
                                    break;
                                case 'hoan_thanh':
                                    $order_status_class = 'badge bg-success';
                                    $order_status_label = 'Hoàn thành';
                                    break;
                                case 'huy':
                                    $order_status_class = 'badge bg-secondary';
                                    $order_status_label = 'Đã hủy';
                                    break;
                                default:
                                    $order_status_class = 'badge bg-light text-dark';
                                    $order_status_label = ucfirst($order_info['TrangThai']);
                                    break;
                            }

                            $can_pay_order = $order_outstanding > 0 && in_array($order_info['TrangThai'], ['dang_phuc_vu', 'cho_thanh_toan'], true);
                        }
                
                        switch($booking['TrangThai']) {
                            case 'pending': 
                                $status_class = 'status-pending'; 
                                $status_text = 'Chờ xác nhận';
                                $can_edit = $can_edit_time;
                                $can_cancel = $can_edit_time;
                                break;
                            case 'confirmed': 
                                $status_class = 'status-confirmed'; 
                                $status_text = 'Đã xác nhận';
                                $can_edit = false;
                                $can_cancel = false;
                                break;
                            case 'completed': 
                                $status_class = 'status-completed'; 
                                $status_text = 'Hoàn thành';
                                break;
                            case 'canceled': 
                                $status_class = 'status-cancelled'; 
                                $status_text = 'Đã hủy';
                                break;
                        }
                ?>
                <div class="booking-card" data-status="<?php echo $booking['TrangThai']; ?>" data-booking-date="<?php echo strtotime($booking['NgayDatBan']); ?>">
                    <div class="booking-header">
                        <div class="booking-info">
                            <h5 class="booking-title">
                                <i class="fas fa-utensils me-2"></i>
                                Đặt bàn #<?php echo $booking['madatban']; ?>
                            </h5>
                            <div class="booking-meta">
                                <span class="booking-date">
                                    <i class="far fa-calendar-alt me-1"></i>
                                    <?php echo date('d/m/Y', strtotime($booking['NgayDatBan'])); ?>
                                </span>
                                <span class="booking-time">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo date('H:i', strtotime($booking['NgayDatBan'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="booking-status">
                            <span class="status-badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                    </div>

                    <div class="booking-content">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-item">
                                    <i class="fas fa-chair me-2"></i>
                                    <strong>Bàn:</strong> <?php echo $booking['SoBan'] ?: 'Chưa chọn'; ?>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <strong>Khu vực:</strong> <?php echo $booking['TenKV'] ?: 'Chưa chọn'; ?>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-users me-2"></i>
                                    <strong>Số người:</strong> <?php echo $booking['SoLuongKhach']; ?> người
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-item">
                                    <i class="fas fa-money-bill-wave me-2"></i>
                                    <strong>Tổng tiền:</strong> 
                                    <span class="price"><?php echo number_format($booking['TongTien']); ?> VNĐ</span>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-phone me-2"></i>
                                    <strong>Liên hệ:</strong> <?php echo $booking['sodienthoai']; ?>
                                </div>
                                <div class="info-item">
                                    <i class="fas fa-envelope me-2"></i>
                                    <strong>Email:</strong> <?php echo $booking['email']; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Chi tiết món ăn -->
                        <div class="booking-menu mt-3">
                            <h6><i class="fas fa-utensils me-2"></i>Món đã đặt:</h6>
                            <div class="menu-items">
                                <?php
                                // Lấy chi tiết món ăn
                                $menu_sql = "SELECT m.tenmonan, c.SoLuong, c.DonGia, (c.SoLuong * c.DonGia) as ThanhTien
                                           FROM chitietdatban c
                                           JOIN monan m ON c.idmonan = m.idmonan
                                           WHERE c.madatban = ?";
                                $menu_items = $db->xuatdulieu_prepared($menu_sql, [$booking['madatban']]);

                                // Món gọi thêm tại bàn (đơn phục vụ)
                                $order_items = [];
                                if ($order_info && !empty($order_info['idDH'])) {
                                    $order_items = $db->xuatdulieu_prepared(
                                        "SELECT m.tenmonan, ct.SoLuong, ct.DonGia, (ct.SoLuong * ct.DonGia) AS ThanhTien
                                         FROM chitietdonhang ct
                                         LEFT JOIN monan m ON ct.idmonan = m.idmonan
                                         WHERE ct.idDH = ? AND (ct.TrangThai IS NULL OR ct.TrangThai <> 'cancelled')",
                                        [$order_info['idDH']]
                                    );
                                }
                                
                                if (!empty($menu_items)):
                                    foreach ($menu_items as $item):
                                ?>
                                <div class="menu-item">
                                    <span class="menu-name"><?php echo $item['tenmonan']; ?></span>
                                    <span class="menu-quantity">x<?php echo $item['SoLuong']; ?></span>
                                    <span class="menu-price"><?php echo number_format($item['ThanhTien']); ?> VNĐ</span>
                                </div>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                <p class="text-muted">Chưa có món ăn nào được đặt</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($order_items)): ?>
                        <div class="booking-menu mt-3">
                            <h6><i class="fas fa-concierge-bell me-2"></i>Món gọi thêm tại bàn:</h6>
                            <div class="menu-items">
                                <?php foreach ($order_items as $item): ?>
                                    <div class="menu-item">
                                        <span class="menu-name"><?php echo $item['tenmonan'] ?: 'Món phục vụ'; ?></span>
                                        <span class="menu-quantity">x<?php echo $item['SoLuong']; ?></span>
                                        <span class="menu-price"><?php echo number_format($item['ThanhTien']); ?> VNĐ</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($order_info): ?>
                        <div class="booking-order mt-3">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold mb-1">
                                        Đơn phục vụ #<?php echo (int)$order_info['idDH']; ?>
                                        <span class="<?php echo $order_status_class; ?> ms-2"><?php echo htmlspecialchars($order_status_label); ?></span>
                                    </div>
                                    <div class="text-muted small">
                                        <?php if (!empty($order_info['MaDonHang'])): ?>
                                            <span class="me-2">Mã: <?php echo htmlspecialchars($order_info['MaDonHang']); ?></span>
                                        <?php endif; ?>
                                        <span class="me-2">Bàn: <?php echo htmlspecialchars($order_info['table_label'] ?? $booking['SoBan'] ?? '—'); ?></span>
                                        <span>Khu vực: <?php echo htmlspecialchars($order_info['area_name'] ?? $booking['TenKV'] ?? '—'); ?></span>
                                        <?php if (!empty($order_info['NgayDatHang'])): ?>
                                            <br><span>Mở lúc: <?php echo date('d/m/Y H:i', strtotime($order_info['NgayDatHang'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end ms-auto">
                                    <div class="small text-muted">Tổng: <?php echo number_format((float)$order_info['TongTien']); ?> VNĐ</div>
                                    <div class="small text-muted">Đã thanh toán: <?php echo number_format((float)($order_info['TongTien'] - $order_outstanding)); ?> VNĐ</div>
                                    <div class="fw-bold">Còn lại: <?php echo number_format($order_outstanding); ?> VNĐ</div>
                                    <?php if ($can_pay_order): ?>
                                        <a class="btn btn-success btn-sm mt-2" href="page/payment_order.php?idDH=<?php echo (int)$order_info['idDH']; ?>" target="_blank">
                                            <i class="fas fa-wallet me-1"></i>Thanh toán đơn
                                        </a>
                                    <?php elseif ($order_outstanding <= 0): ?>
                                        <div class="badge bg-success mt-2">Đã thanh toán đủ</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php 
                            $currentRating = ($orderRatingEnabled && isset($order_info['DanhGia'])) ? (int)$order_info['DanhGia'] : 0;
                            $canRateOrder = $orderRatingEnabled && in_array($order_info['TrangThai'], ['hoan_thanh', 'completed'], true);
                            if ($canRateOrder):
                        ?>
                        <div class="order-rating mt-3">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <span class="fw-semibold d-flex align-items-center">
                                    <i class="fas fa-star text-warning me-2"></i>Đánh giá đơn:
                                </span>
                                <div class="rating-stars" data-order-id="<?php echo (int)$order_info['idDH']; ?>" data-current-rating="<?php echo $currentRating; ?>">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <button type="button" class="star-btn <?php echo $i <= $currentRating ? 'active' : ''; ?>" data-value="<?php echo $i; ?>" aria-label="Chọn <?php echo $i; ?> sao">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <?php endfor; ?>
                                </div>
                                <span class="rating-hint"><?php echo $currentRating > 0 ? 'Cảm ơn bạn đã đánh giá!' : 'Nhấp vào sao để đánh giá đơn hàng'; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="booking-actions">
                        <div class="action-buttons">
                            <?php if ($can_edit): ?>
                            <button class="btn btn-outline-primary btn-sm" onclick="editBooking(<?php echo $booking['madatban']; ?>)">
                                <i class="fas fa-edit me-1"></i>Sửa thông tin
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="editMenu(<?php echo $booking['madatban']; ?>)">
                                <i class="fas fa-utensils me-1"></i>Sửa món ăn
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($can_cancel): ?>
                            <button class="btn btn-outline-danger btn-sm" onclick="cancelBooking(<?php echo $booking['madatban']; ?>)">
                                <i class="fas fa-times me-1"></i>Hủy đặt bàn
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($can_edit): ?>
                            <button class="btn btn-outline-warning btn-sm" onclick="editTables(<?php echo $booking['madatban']; ?>)">
                                <i class="fas fa-chair me-1"></i>Sửa bàn
                            </button>
                            <?php elseif ($is_pending): ?>
                            <button class="btn btn-outline-secondary btn-sm" disabled title="Chỉ có thể sửa trước 24 tiếng so với thời gian đặt bàn">
                                <i class="fas fa-chair me-1"></i>Sửa bàn
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($can_pay): ?>
                            <button class="btn btn-success btn-sm" onclick="proceedPayment(<?php echo $booking['madatban']; ?>)">
                                <i class="fas fa-credit-card me-1"></i>Tiến hành thanh toán
                            </button>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$can_edit_time && $is_pending): ?>
                        <div class="time-restriction-notice mt-2">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Chỉ có thể sửa trước 12 tiếng so với thời gian đặt bàn
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php 
                    endforeach;
                    echo '</div>';
                else:
                ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h5>Chưa có lịch sử đặt bàn</h5>
                    <p>Bạn chưa có lịch sử đặt bàn nào. Hãy đặt bàn để trải nghiệm dịch vụ của chúng tôi!</p>
                    <a href="index.php?page=booking" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Đặt bàn ngay
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Profile Container */
.profile-section {
    background: #fff;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    margin-top: 30px;
    border: 1px solid #e9ecef;
}

.profile-header {
    display: flex;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f8f9fa;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin-right: 25px;
    object-fit: cover;
    border: 4px solid #FEA116;
    box-shadow: 0 5px 15px rgba(254, 161, 22, 0.3);
}

.profile-info h2 {
    margin: 0;
    color: #FEA116;
    font-weight: 700;
    font-size: 2rem;
}

.profile-info p {
    color: #6c757d;
    margin: 5px 0;
}

/* Navigation Tabs */
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
    margin-bottom: 30px;
}

.nav-tabs .nav-link {
    border: none;
    color: #6c757d;
    font-weight: 500;
    padding: 15px 25px;
    margin-right: 10px;
    border-radius: 10px 10px 0 0;
    transition: all 0.3s ease;
}

.nav-tabs .nav-link:hover {
    color: #FEA116;
    background: #fff3cd;
}

.nav-tabs .nav-link.active {
    color: #FEA116;
    background: #fff;
    border-bottom: 3px solid #FEA116;
}

/* Booking Cards */
.booking-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 15px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.booking-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.booking-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 20px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #dee2e6;
}

.booking-title {
    margin: 0;
    color: #2c3e50;
    font-weight: 600;
    font-size: 1.2rem;
}

.booking-meta {
    display: flex;
    gap: 20px;
    margin-top: 8px;
}

.booking-date, .booking-time {
    color: #6c757d;
    font-size: 0.9rem;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status-confirmed {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.status-completed {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.booking-content {
    padding: 25px;
}

.info-item {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
    color: #495057;
}

.info-item i {
    color: #FEA116;
    width: 20px;
    text-align: center;
}

.price {
    color: #28a745;
    font-weight: 700;
    font-size: 1.1rem;
}

/* Menu Items */
.booking-menu {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
}

.booking-menu h6 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-weight: 600;
}

.menu-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #e9ecef;
}

.menu-item:last-child {
    border-bottom: none;
}

.menu-name {
    flex: 1;
    color: #495057;
    font-weight: 500;
}

.menu-quantity {
    color: #6c757d;
    margin: 0 15px;
    font-weight: 500;
}

.menu-price {
    color: #28a745;
    font-weight: 600;
}

.booking-order {
    background: #f8f9fa;
    border: 1px dashed #e9ecef;
    border-radius: 12px;
    padding: 14px 16px;
}

.order-rating {
    background: #fffbe6;
    border: 1px dashed #ffe58f;
    border-radius: 12px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
}

.rating-stars {
    display: flex;
    align-items: center;
    gap: 6px;
}

.rating-stars .star-btn {
    background: none;
    border: none;
    color: #e0e0e0;
    font-size: 22px;
    padding: 4px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.rating-stars .star-btn.active {
    color: #ffc107;
}

.rating-stars .star-btn:hover {
    color: #ffb703;
    transform: translateY(-1px);
}

.rating-stars .star-btn:focus {
    outline: none;
}

.rating-stars .star-btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.rating-hint {
    color: #6c757d;
    font-size: 0.95rem;
}

.booking-order .badge {
    font-size: 0.85rem;
}

/* Action Buttons */
.booking-actions {
    background: #f8f9fa;
    padding: 20px 25px;
    border-top: 1px solid #e9ecef;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.action-buttons .btn {
    border-radius: 8px;
    font-weight: 500;
    padding: 8px 16px;
    transition: all 0.3s ease;
}

.action-buttons .btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 15px;
    border: 2px dashed #dee2e6;
}

.empty-icon {
    font-size: 4rem;
    color: #6c757d;
    margin-bottom: 20px;
}

.empty-state h5 {
    color: #495057;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6c757d;
    margin-bottom: 25px;
}

/* Filters */
.booking-filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

.booking-filters .form-select {
    border-radius: 8px;
    border: 1px solid #ced4da;
    padding: 8px 12px;
    min-width: 180px;
}

/* Responsive */
@media (max-width: 768px) {
    .profile-header {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-avatar {
        margin-right: 0;
        margin-bottom: 15px;
    }
    
    .booking-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .booking-meta {
        flex-direction: column;
        gap: 5px;
    }
    
    .action-buttons {
        justify-content: center;
    }
    
    .action-buttons .btn {
        flex: 1;
        min-width: 120px;
    }
}

/* Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.booking-card {
    animation: fadeInUp 0.5s ease-out;
}

/* Status Filter */
.booking-card.hidden {
    display: none;
}

/* Form styling for edit modal */
.swal2-html-container .form-label {
    text-align: left;
    font-weight: 500;
    margin-bottom: 5px;
    display: block;
}

.swal2-html-container .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.swal2-html-container .row {
    margin: 0;
}

.swal2-html-container .col-md-6 {
    padding: 0 5px;
}

.swal2-html-container .mb-3 {
    margin-bottom: 15px;
}
    margin-bottom: 20px;
}
.nav-tabs .nav-link {
    color: #666;
    border: none;
    padding: 10px 20px;
    margin-right: 5px;
    border-radius: 0;
    font-weight: 500;
    transition: all 0.3s ease;
}
.nav-tabs .nav-link:hover {
    color: #FEA116;
    background: transparent;
    border-color: transparent;
}
.nav-tabs .nav-link.active {
    color: #FEA116;
    background: transparent;
    border-bottom: 2px solid #FEA116;
}
.tab-content {
    padding: 20px 0;
}
.booking-item {
    border: 1px solid #ddd;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 15px;
}
.booking-status {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8em;
}
.status-pending { background: #ffeeba; }
.status-confirmed { background: #c3e6cb; }
.status-completed { background: #b8daff; }
.status-cancelled { background: #f5c6cb; }

/* Time restriction notice */
.time-restriction-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 8px 12px;
    margin-top: 10px;
}

.time-restriction-notice small {
    color: #856404;
    font-weight: 500;
}

.time-restriction-notice i {
    color: #f39c12;
}
</style>

<script>
// Kích hoạt tab lịch sử đặt bàn nếu có hash #bookings trong URL
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash === '#bookings') {
        var bookingsTab = document.querySelector('#bookings-tab');
        var tab = new bootstrap.Tab(bookingsTab);
        tab.show();
    }

    sortBookings();
});

// Lọc đặt bàn theo trạng thái
function filterBookings() {
    const filter = document.getElementById('statusFilter').value;
    const cards = document.querySelectorAll('.booking-card');
    
    cards.forEach(card => {
        const status = card.getAttribute('data-status');
        if (filter === '' || status === filter) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });

    sortBookings();
}

function sortBookings() {
    const order = document.getElementById('sortOrder') ? document.getElementById('sortOrder').value : 'newest';
    const container = document.getElementById('bookingsContainer');
    if (!container) {
        return;
    }

    const cards = Array.from(container.querySelectorAll('.booking-card'));
    cards.sort((a, b) => {
        const dateA = parseInt(a.dataset.bookingDate || '0', 10);
        const dateB = parseInt(b.dataset.bookingDate || '0', 10);
        return order === 'oldest' ? dateA - dateB : dateB - dateA;
    });

    cards.forEach(card => container.appendChild(card));
}

// Sửa thông tin đặt bàn
function editBooking(bookingId) {
    // Lấy thông tin đặt bàn hiện tại
    fetch('page/profile/get_booking_info.php?madatban=' + bookingId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const booking = data.booking;
                
                Swal.fire({
                    title: 'Sửa thông tin đặt bàn',
                    html: `
                        <div class="mb-3">
                            <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                <label for="edit_email" class="form-label" style="width: 120px; margin: 0; text-align: left;">Email:</label>
                                <input type="email" class="form-control" id="edit_email" value="${booking.email}" required style="flex: 1;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                <label for="edit_tenKH" class="form-label" style="width: 120px; margin: 0; text-align: left;">Họ tên:</label>
                                <input type="text" class="form-control" id="edit_tenKH" value="${booking.tenKH}" required style="flex: 1;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                <label for="edit_sodienthoai" class="form-label" style="width: 120px; margin: 0; text-align: left;">Số điện thoại:</label>
                                <input type="tel" class="form-control" id="edit_sodienthoai" value="${booking.sodienthoai}" required style="flex: 1;">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                <span class="form-label" style="width: 120px; margin: 0; text-align: left;">Số lượng khách:</span>
                                <span id="edit_soluong_text" style="flex: 1; font-weight: 500; color: #212529;">${booking.SoLuongKhach} người</span>
                            </div>
                            <small class="form-text text-muted">Số lượng khách được tính theo tổng số chỗ ngồi của bàn đã chọn.</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <label for="edit_ngay" class="form-label" style="width: 100px; margin: 0; text-align: left;">Ngày:</label>
                                        <input type="date" class="form-control" id="edit_ngay" required style="flex: 1;">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div style="display: flex; align-items: center; margin-bottom: 5px;">
                                        <label for="edit_gio" class="form-label" style="width: 100px; margin: 0; text-align: left;">Giờ:</label>
                                        <select class="form-control" id="edit_gio" required style="flex: 1;">
                                            <option value="">Chọn giờ</option>
                                            <option value="10:00">10:00</option>
                                            <option value="10:30">10:30</option>
                                            <option value="11:00">11:00</option>
                                            <option value="11:30">11:30</option>
                                            <option value="12:00">12:00</option>
                                            <option value="12:30">12:30</option>
                                            <option value="13:00">13:00</option>
                                            <option value="13:30">13:30</option>
                                            <option value="14:00">14:00</option>
                                            <option value="14:30">14:30</option>
                                            <option value="15:00">15:00</option>
                                            <option value="15:30">15:30</option>
                                            <option value="16:00">16:00</option>
                                            <option value="16:30">16:30</option>
                                            <option value="17:00">17:00</option>
                                            <option value="17:30">17:30</option>
                                            <option value="18:00">18:00</option>
                                            <option value="18:30">18:30</option>
                                            <option value="19:00">19:00</option>
                                            <option value="19:30">19:30</option>
                                            <option value="20:00">20:00</option>
                                            <option value="20:30">20:30</option>
                                            <option value="21:00">21:00</option>
                                            <option value="21:30">21:30</option>
                                            <option value="22:00">22:00</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Lưu thay đổi',
                    cancelButtonText: 'Hủy',
                    confirmButtonColor: '#FEA116',
                    cancelButtonColor: '#6c757d',
                    didOpen: () => {
                        // Set giá trị ngày giờ từ thông tin đặt bàn
                        const ngayDatBan = new Date(booking.NgayDatBan);
                        const ngay = ngayDatBan.toISOString().split('T')[0];
                        const gio = ngayDatBan.toTimeString().substring(0, 5);
                        
                        document.getElementById('edit_ngay').value = ngay;
                        document.getElementById('edit_gio').value = gio;
                    },
                    preConfirm: () => {
                        const email = document.getElementById('edit_email').value;
                        const tenKH = document.getElementById('edit_tenKH').value;
                        const sodienthoai = document.getElementById('edit_sodienthoai').value;
                        const ngay = document.getElementById('edit_ngay').value;
                        const gio = document.getElementById('edit_gio').value;
                        
                        if (!email || !tenKH || !sodienthoai || !ngay || !gio) {
                            Swal.showValidationMessage('Vui lòng điền đầy đủ thông tin');
                            return false;
                        }
                        
                        // Kiểm tra ngày không được trong quá khứ
                        const selectedDate = new Date(ngay + ' ' + gio);
                        const now = new Date();
                        if (selectedDate <= now) {
                            Swal.showValidationMessage('Ngày giờ đặt bàn phải trong tương lai');
                            return false;
                        }
                        
                        return { email, tenKH, sodienthoai, ngay, gio };
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Gửi dữ liệu cập nhật
                        const formData = new FormData();
                        formData.append('madatban', bookingId);
                        formData.append('email', result.value.email);
                        formData.append('tenKH', result.value.tenKH);
                        formData.append('sodienthoai', result.value.sodienthoai);
                        formData.append('ngaydatban', result.value.ngay + ' ' + result.value.gio);
                        
                        fetch('page/profile/update_booking.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Thành công!',
                                    text: 'Thông tin đặt bàn đã được cập nhật',
                                    icon: 'success',
                                    timer: 2000
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Lỗi!',
                                    text: data.message || 'Có lỗi xảy ra khi cập nhật thông tin',
                                    icon: 'error'
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                title: 'Lỗi!',
                                text: 'Có lỗi xảy ra khi kết nối server',
                                icon: 'error'
                            });
                        });
                    }
                });
            } else {
                Swal.fire({
                    title: 'Lỗi!',
                    text: 'Không thể tải thông tin đặt bàn',
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                title: 'Lỗi!',
                text: 'Có lỗi xảy ra khi tải thông tin',
                icon: 'error'
            });
        });
}

// Sửa món ăn
function editMenu(bookingId) {
    window.location.href = 'index.php?page=edit_menu&madatban=' + bookingId;
}

// Hủy đặt bàn
function cancelBooking(bookingId) {
    Swal.fire({
        title: 'Xác nhận hủy đặt bàn',
        text: 'Bạn có chắc chắn muốn hủy đặt bàn này?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Có, hủy đặt bàn!',
        cancelButtonText: 'Không'
    }).then((result) => {
        if (result.isConfirmed) {
            // Gửi yêu cầu hủy đặt bàn
            const formData = new FormData();
            formData.append('madatban', bookingId);
            fetch('page/profile/cancel_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Đã hủy!',
                        text: data.message || 'Đặt bàn đã được hủy thành công.',
                        icon: 'success',
                        timer: 2000
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Lỗi!',
                        text: data.message || 'Có lỗi khi hủy đặt bàn.',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Lỗi!',
                    text: 'Không thể kết nối server.',
                    icon: 'error'
                });
            });
        }
    });
}

// Sửa bàn đặt bàn
function editTables(bookingId) {
    window.location.href = 'index.php?page=booking&action=edit_tables&madatban=' + bookingId;
}

// Tiến hành thanh toán
function proceedPayment(bookingId) {
    Swal.fire({
        title: 'Xác nhận thanh toán',
        text: 'Bạn có chắc chắn muốn tiến hành thanh toán cho đặt bàn này?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Có, thanh toán',
        cancelButtonText: 'Hủy'
    }).then((result) => {
        if (result.isConfirmed) {
            startPaymentStatusWatcher(bookingId);
            const paymentUrl = 'index.php?page=payment_online&madatban=' + bookingId;
            const paymentWindow = window.open(paymentUrl, '_blank');
            if (!paymentWindow || paymentWindow.closed || typeof paymentWindow.closed === 'undefined') {
                window.location.href = paymentUrl;
            } else {
                Swal.fire({
                    title: 'Đã mở trang thanh toán',
                    text: 'Vui lòng hoàn tất thanh toán trong tab mới. Trang này sẽ tự động chuyển khi thanh toán thành công.',
                    icon: 'info',
                    timer: 4000,
                    showConfirmButton: false
                });
            }
        }
    });
}

function initOrderRatings() {
    const ratingBlocks = document.querySelectorAll('.rating-stars');
    ratingBlocks.forEach(block => {
        const orderId = parseInt(block.dataset.orderId || '0', 10);
        if (!orderId) {
            return;
        }

        let currentRating = parseInt(block.dataset.currentRating || '0', 10);
        const buttons = block.querySelectorAll('.star-btn');
        const ratingWrapper = block.closest('.order-rating');
        const hint = ratingWrapper ? ratingWrapper.querySelector('.rating-hint') : null;

        const updateStars = (value) => {
            buttons.forEach(btn => {
                const starValue = parseInt(btn.dataset.value || '0', 10);
                btn.classList.toggle('active', starValue <= value);
            });
        };

        updateStars(currentRating);

        buttons.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                const hoverValue = parseInt(btn.dataset.value || '0', 10);
                updateStars(hoverValue);
            });

            btn.addEventListener('mouseleave', () => {
                updateStars(currentRating);
            });

            btn.addEventListener('click', () => {
                if (block.dataset.loading === '1') {
                    return;
                }
                const ratingValue = parseInt(btn.dataset.value || '0', 10);
                if (!ratingValue || ratingValue < 1 || ratingValue > 5) {
                    return;
                }

                block.dataset.loading = '1';
                const formData = new FormData();
                formData.append('idDH', orderId);
                formData.append('rating', ratingValue);

                fetch('page/profile/save_rating.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentRating = ratingValue;
                        block.dataset.currentRating = String(ratingValue);
                        updateStars(ratingValue);
                        if (hint) {
                            hint.textContent = 'Cảm ơn bạn đã đánh giá đơn hàng!';
                            hint.classList.add('text-success');
                        }
                    } else {
                        Swal.fire({
                            title: 'Không thể lưu đánh giá',
                            text: data.message || 'Vui lòng thử lại sau.',
                            icon: 'error'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        title: 'Lỗi',
                        text: 'Không thể kết nối tới máy chủ.',
                        icon: 'error'
                    });
                })
                .finally(() => {
                    block.dataset.loading = '0';
                });
            });
        });
    });
}

// Thêm hiệu ứng hover cho các nút
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.action-buttons .btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    initOrderRatings();
});

</script>

<script>
let paymentStatusInterval = null;
const PAYMENT_CHECK_INTERVAL = 5000;

function startPaymentStatusWatcher(bookingId) {
    if (!bookingId) {
        return;
    }
    if (paymentStatusInterval) {
        clearInterval(paymentStatusInterval);
    }
    sessionStorage.setItem('pendingPaymentWatch', bookingId);
    const encodedId = encodeURIComponent(bookingId);
    const checkStatus = () => {
        fetch('page/payment_callback.php?action=check_status&madatban=' + encodedId, {
            method: 'GET',
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.payment_status === 'paid') {
                clearInterval(paymentStatusInterval);
                sessionStorage.removeItem('pendingPaymentWatch');
                window.location.href = 'page/payment_callback.php?action=process_success&madatban=' + encodedId;
            }
        })
        .catch(() => {});
    };
    checkStatus();
    paymentStatusInterval = setInterval(checkStatus, PAYMENT_CHECK_INTERVAL);
}

document.addEventListener('DOMContentLoaded', function() {
    const watchId = sessionStorage.getItem('pendingPaymentWatch');
    if (watchId) {
        startPaymentStatusWatcher(watchId);
    }
});

window.addEventListener('beforeunload', function() {
    if (paymentStatusInterval) {
        clearInterval(paymentStatusInterval);
    }
});
</script>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
