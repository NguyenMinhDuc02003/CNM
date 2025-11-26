<?php
include("class/clsconnect.php");
include("class/clsdatban.php");

if (!isset($conn) || !$conn) {
    $dbConn = new connect_db();
    $conn = $dbConn->getConnection();
} else {
    $dbConn = new connect_db();
}

$bookingModel = new datban();

// Endpoint AJAX: trả về danh sách đơn đã cọc đủ
if (isset($_GET['ajax_deposit_updates'])) {
    header('Content-Type: application/json; charset=utf-8');
    $sqlAjax = "
        SELECT db.madatban,
               db.tenKH,
               db.NgayDatBan,
               db.TongTien,
               COALESCE(pay.deposit_paid, 0) AS deposit_paid
        FROM datban db
        LEFT JOIN (
            SELECT madatban,
                   SUM(CASE WHEN TrangThai = 'completed' THEN SoTien ELSE 0 END) AS deposit_paid
            FROM thanhtoan
            GROUP BY madatban
        ) pay ON pay.madatban = db.madatban
        WHERE db.TrangThai IN ('pending','confirmed','completed')
        ORDER BY db.madatban DESC
    ";
    try {
        $rows = $dbConn->xuatdulieu($sqlAjax);
        echo json_encode([
            'status' => 'success',
            'data' => $rows,
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

$actionMessage = '';
$actionMessageType = 'success';

if ($actionMessage === '' && !empty($_SESSION['admin_flash']) && is_array($_SESSION['admin_flash'])) {
    $allowedAlertTypes = ['success', 'info', 'warning', 'danger', 'primary', 'secondary', 'light', 'dark'];
    $flashType = $_SESSION['admin_flash']['type'] ?? 'info';
    $actionMessageType = in_array($flashType, $allowedAlertTypes, true) ? $flashType : 'info';
    $actionMessage = $_SESSION['admin_flash']['message'] ?? '';
    unset($_SESSION['admin_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    $deleteId = isset($_POST['delete_booking']) ? (int)$_POST['delete_booking'] : 0;
    if ($deleteId > 0) {
        if ($bookingModel->deleteAdminBooking($deleteId)) {
            $actionMessage = "Đã xóa đơn đặt bàn #{$deleteId} thành công.";
            $actionMessageType = 'success';
        } else {
            $actionMessage = 'Không thể xóa đơn đặt bàn. Vui lòng thử lại.';
            $actionMessageType = 'danger';
        }
    } else {
        $actionMessage = 'Mã đơn đặt bàn không hợp lệ.';
        $actionMessageType = 'danger';
    }
}

$hasMetaTable = $dbConn->tableExists('datban_admin_meta');
$hasDetailTable = $dbConn->tableExists('chitiet_ban_datban');

$fromDate = isset($_GET['from']) ? trim($_GET['from']) : '';
$toDate = isset($_GET['to']) ? trim($_GET['to']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$channelFilter = isset($_GET['channel']) ? trim($_GET['channel']) : '';

$conditions = [];
$params = [];
$types = '';

if ($fromDate !== '') {
    $conditions[] = "DATE(db.NgayDatBan) >= ?";
    $params[] = $fromDate;
    $types .= 's';
}

if ($toDate !== '') {
    $conditions[] = "DATE(db.NgayDatBan) <= ?";
    $params[] = $toDate;
    $types .= 's';
}

$allowedStatus = ['pending', 'confirmed', 'completed', 'canceled'];
if ($statusFilter !== '' && in_array($statusFilter, $allowedStatus, true)) {
    $conditions[] = "db.TrangThai = ?";
    $params[] = $statusFilter;
    $types .= 's';
} else {
    $statusFilter = '';
}

$allowedChannels = ['user', 'walkin', 'phone'];
if ($hasMetaTable) {
    if ($channelFilter !== '' && in_array($channelFilter, $allowedChannels, true)) {
        $conditions[] = "COALESCE(meta.booking_channel, 'user') = ?";
        $params[] = $channelFilter;
        $types .= 's';
    } else {
        $channelFilter = '';
    }
} else {
    $channelFilter = '';
}

$filterParams = [];
if ($fromDate !== '') {
    $filterParams['from'] = $fromDate;
}
if ($toDate !== '') {
    $filterParams['to'] = $toDate;
}
if ($statusFilter !== '') {
    $filterParams['status'] = $statusFilter;
}
if ($channelFilter !== '') {
    $filterParams['channel'] = $channelFilter;
}
$currentFilterQuery = '';
if (!empty($filterParams)) {
    $currentFilterQuery = '&' . http_build_query($filterParams);
}

$today = date('Y-m-d');

$select = "
    SELECT 
        db.madatban,
        db.NgayDatBan,
        db.TrangThai,
        db.SoLuongKhach,
        db.TongTien,
        db.tenKH,
        db.sodienthoai,
        db.email,
        COALESCE(pay.deposit_paid, 0) AS deposit_paid,
        COALESCE(pay.deposit_pending, 0) AS deposit_pending";

if ($hasDetailTable) {
    $select .= ",
        GROUP_CONCAT(DISTINCT CONCAT('Bàn ', b.SoBan) ORDER BY b.SoBan SEPARATOR ', ') AS danh_sach_ban,
        COALESCE(SUM(cbd.phuthu), 0) AS TongPhuThu,
        COALESCE(MAX(kv.TenKV), 'Không xác định') AS TenKV";
    $fromClause = "
        FROM datban db
        LEFT JOIN chitiet_ban_datban cbd ON db.madatban = cbd.madatban
        LEFT JOIN ban b ON cbd.idban = b.idban
        LEFT JOIN khuvucban kv ON b.MaKV = kv.MaKV
    ";
    $groupClause = " GROUP BY db.madatban";
} else {
    $select .= ",
        'Chưa gán' AS danh_sach_ban,
        0 AS TongPhuThu,
        'Không xác định' AS TenKV";
    $fromClause = " FROM datban db ";
    $groupClause = "";
}

if ($hasMetaTable) {
    if ($hasDetailTable) {
        $select .= ",
        COALESCE(MAX(meta.booking_channel), 'user') AS booking_channel,
        COALESCE(MAX(meta.payment_method), 'cash') AS payment_method";
    } else {
        $select .= ",
        COALESCE(meta.booking_channel, 'user') AS booking_channel,
        COALESCE(meta.payment_method, 'cash') AS payment_method";
    }
    $fromClause .= " LEFT JOIN datban_admin_meta meta ON db.madatban = meta.madatban";
} else {
    $select .= ",
        'user' AS booking_channel,
        'cash' AS payment_method";
}

$fromClause .= "
    LEFT JOIN (
        SELECT 
            madatban,
            SUM(CASE WHEN TrangThai = 'completed' THEN SoTien ELSE 0 END) AS deposit_paid,
            SUM(CASE WHEN TrangThai = 'pending' THEN SoTien ELSE 0 END) AS deposit_pending
        FROM thanhtoan
        GROUP BY madatban
    ) pay ON pay.madatban = db.madatban";

$sql = $select . $fromClause;

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

if (!empty($groupClause)) {
    $sql .= $groupClause;
}

$sql .= " ORDER BY db.NgayDatBan DESC";

$stmt = mysqli_prepare($conn, $sql);
if ($stmt === false) {
    $queryError = $conn->error;
    $bookings = [];
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $bookings = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $queryError = $result ? null : $conn->error;
    $stmt->close();
}

$channelLabels = [
    'user' => ['label' => 'Website', 'badge' => 'bg-secondary'],
    'walkin' => ['label' => 'Tại nhà hàng', 'badge' => 'bg-primary'],
    'phone' => ['label' => 'Qua điện thoại', 'badge' => 'bg-info text-dark'],
];

$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'completed' => 'Hoàn tất',
    'canceled' => 'Đã hủy',
];

$statusBadges = [
    'pending' => 'bg-warning text-dark',
    'confirmed' => 'bg-success',
    'completed' => 'bg-secondary',
    'canceled' => 'bg-danger',
];

$channelStats = ['user' => 0, 'walkin' => 0, 'phone' => 0, 'total' => 0];
foreach ($bookings as $bk) {
    $channel = $bk['booking_channel'] ?? 'user';
    if (!isset($channelStats[$channel])) {
        $channelStats[$channel] = 0;
    }
    $channelStats[$channel]++;
    $channelStats['total']++;
}
?>

<div class="container mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <h3 class="mb-0"><i class="fas fa-clipboard-list me-2 text-primary"></i>Danh sách đặt bàn</h3>
        <div>
            <a href="index.php?page=admin_booking" class="btn btn-primary">
                <i class="fas fa-plus-circle me-2"></i>Tạo đặt bàn mới
            </a>
        </div>
    </div>

    <div class="row g-3 my-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <p class="text-muted mb-1">Tổng đơn</p>
                    <h4 class="mb-0"><?php echo $channelStats['total']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <p class="text-muted mb-1">Website</p>
                    <h4 class="mb-0"><?php echo $channelStats['user']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <p class="text-muted mb-1">Tại nhà hàng</p>
                    <h4 class="mb-0"><?php echo $channelStats['walkin']; ?></h4>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <p class="text-muted mb-1">Qua điện thoại</p>
                    <h4 class="mb-0"><?php echo $channelStats['phone']; ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <input type="hidden" name="page" value="dsdatban">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" name="from" value="<?php echo htmlspecialchars($fromDate); ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" name="to" value="<?php echo htmlspecialchars($toDate); ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label">Trạng thái</label>
                    <select name="status" class="form-select">
                        <option value="">Tất cả</option>
                        <?php foreach ($statusLabels as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $statusFilter === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($hasMetaTable): ?>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label">Nguồn đặt</label>
                        <select name="channel" class="form-select">
                            <option value="">Tất cả</option>
                            <option value="user" <?php echo $channelFilter === 'user' ? 'selected' : ''; ?>>Website</option>
                            <option value="walkin" <?php echo $channelFilter === 'walkin' ? 'selected' : ''; ?>>Tại nhà hàng</option>
                            <option value="phone" <?php echo $channelFilter === 'phone' ? 'selected' : ''; ?>>Qua điện thoại</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-12 text-end">
                    <a href="index.php?page=dsdatban" class="btn btn-light me-2">Đặt lại</a>
                    <a href="<?php echo 'index.php?page=dsdatban&from=' . urlencode($today) . '&to=' . urlencode($today) . '&status=confirmed'; ?>" class="btn btn-outline-success me-2">
                        <i class="fas fa-check-circle me-1"></i>Đơn đã cọc hôm nay
                    </a>
                    <a href="<?php echo 'index.php?page=dsdatban&from=' . urlencode($today) . '&to=' . urlencode($today) . '&status=canceled'; ?>" class="btn btn-outline-danger me-2">
                        <i class="fas fa-times-circle me-1"></i>Đơn đã hủy hôm nay
                    </a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Lọc</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($actionMessage !== ''): ?>
        <div class="alert alert-<?php echo $actionMessageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($queryError)): ?>
        <div class="alert alert-danger">Lỗi truy vấn dữ liệu: <?php echo htmlspecialchars($queryError); ?></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Mã đơn</th>
                    <th>Thời gian</th>
                    <th>Khách hàng</th>
                    <th>Liên hệ</th>
                    <th>Khu vực &amp; bàn</th>
                    <th>Số khách</th>
                    <th>Phụ thu</th>
                    <th>Tổng tạm tính</th>
                    <th>Đặt cọc</th>
                    <th>Trạng thái</th>
                    <th>Nguồn</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($bookings)): ?>
                        <?php foreach ($bookings as $bk): ?>
                            <?php
                                $status = $bk['TrangThai'] ?? 'pending';
                                $statusBadge = $statusBadges[$status] ?? 'bg-secondary';
                                $channel = $bk['booking_channel'] ?? 'user';
                                $channelInfo = $channelLabels[$channel] ?? $channelLabels['user'];
                                $depositPaid = (float)($bk['deposit_paid'] ?? 0);
                                $depositPending = (float)($bk['deposit_pending'] ?? 0);
                                $requiredDeposit = (float)ceil(((float)($bk['TongTien'] ?? 0)) * 0.5);
                            ?>
                            <tr data-booking-row data-id="<?php echo (int)$bk['madatban']; ?>" data-deposit-paid="<?php echo (float)$depositPaid; ?>" data-required="<?php echo (float)$requiredDeposit; ?>">
                            <td class="fw-semibold">#<?php echo (int)$bk['madatban']; ?></td>
                            <td>
                                <div><?php echo date('d/m/Y H:i', strtotime($bk['NgayDatBan'])); ?></div>
                                <small class="text-muted"><?php echo htmlspecialchars($bk['TenKV']); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($bk['tenKH'] ?? 'Khách lẻ'); ?></div>
                                <?php if (!empty($bk['email'])): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($bk['email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($bk['sodienthoai'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($bk['danh_sach_ban'] ?? 'Chưa gán bàn'); ?></td>
                            <td><?php echo (int)$bk['SoLuongKhach']; ?></td>
                            <td><?php echo number_format((float)$bk['TongPhuThu']); ?>đ</td>
                            <td><?php echo number_format((float)$bk['TongTien']); ?>đ</td>
                            <td>
                                <?php
                                    $depositNeeded = max(0, $requiredDeposit - $depositPaid);
                                ?>
                                <div class="fw-semibold <?php echo $depositPaid >= $requiredDeposit ? 'text-success' : 'text-primary'; ?>" data-deposit-paid-display>
                                    <?php echo number_format($depositPaid); ?>đ
                                </div>
                                <small class="text-muted">Yêu cầu: <span data-deposit-required><?php echo number_format($requiredDeposit); ?></span>đ</small>
                                <?php if ($depositPending > 0): ?>
                                    <div><span class="badge bg-warning text-dark mt-1">Chờ duyệt <?php echo number_format($depositPending); ?>đ</span></div>
                                <?php endif; ?>
                                <?php if ($depositNeeded > 0 && $depositPending <= 0): ?>
                                    <div><small class="text-danger">Thiếu: <?php echo number_format($depositNeeded); ?>đ</small></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars($statusLabels[$status] ?? ucfirst($status)); ?></span></td>
                            <td><span class="badge <?php echo $channelInfo['badge']; ?>"><?php echo $channelInfo['label']; ?></span></td>
                            <td>
                                <div class="btn-group">
                                    <a href="index.php?page=chitietdondatban&madatban=<?php echo (int)$bk['madatban']; ?>" class="btn btn-outline-secondary btn-sm">
                                        Chi tiết
                                    </a>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            data-delete-booking
                                            data-id="<?php echo (int)$bk['madatban']; ?>"
                                            data-customer="<?php echo htmlspecialchars($bk['tenKH'] ?? 'Khách lẻ'); ?>"
                                            data-time="<?php echo date('d/m/Y H:i', strtotime($bk['NgayDatBan'])); ?>">
                                        Xóa
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">Không có đơn đặt bàn phù hợp với điều kiện lọc.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="deleteBookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" class="modal-content" id="deleteBookingForm" action="index.php?page=dsdatban<?php echo htmlspecialchars($currentFilterQuery); ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Xóa đơn đặt bàn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bạn có chắc chắn muốn xóa đơn đặt bàn <strong id="deleteBookingLabel"></strong>?</p>
                    <p class="text-muted small mb-0">Hành động này sẽ giải phóng bàn và xóa toàn bộ món đã chọn cho đơn.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-danger">Xóa đơn</button>
                </div>
                <input type="hidden" name="delete_booking" id="deleteBookingInput" value="">
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const deleteButtons = document.querySelectorAll('[data-delete-booking]');
        const modalElement = document.getElementById('deleteBookingModal');
        if (!deleteButtons.length || !modalElement) {
            return;
        }
        const bookingInput = document.getElementById('deleteBookingInput');
        const bookingLabel = document.getElementById('deleteBookingLabel');
        let modalInstance = null;

        deleteButtons.forEach(button => {
            button.addEventListener('click', () => {
                const id = button.dataset.id || '';
                const customer = button.dataset.customer || 'Khách lẻ';
                const time = button.dataset.time || '';
                if (bookingInput) {
                    bookingInput.value = id;
                }
                if (bookingLabel) {
                    bookingLabel.textContent = `#${id} - ${customer}${time ? ' • ' + time : ''}`;
                }
                if (!modalInstance) {
                    modalInstance = new bootstrap.Modal(modalElement);
                }
                modalInstance.show();
            });
        });

        // Realtime thông báo đơn đã cọc đủ
        const rows = document.querySelectorAll('[data-booking-row]');
        const toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);

        const showToast = (message) => {
            const toastEl = document.createElement('div');
            toastEl.className = 'toast align-items-center text-bg-success border-0';
            toastEl.setAttribute('role', 'alert');
            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>`;
            toastContainer.appendChild(toastEl);
            const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
            bsToast.show();
            toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
        };

        const polling = () => {
            fetch('index.php?page=dsdatban&ajax_deposit_updates=1')
                .then(res => res.json())
                .then(json => {
                    if (!json || json.status !== 'success' || !Array.isArray(json.data)) return;
                    json.data.forEach(item => {
                        const row = document.querySelector(`[data-booking-row][data-id="${item.madatban}"]`);
                        if (!row) return;
                        const required = Number(row.dataset.required || 0);
                        const currentPaid = Number(row.dataset.depositPaid || 0);
                        const newPaid = Number(item.deposit_paid || 0);
                        if (newPaid >= required && currentPaid < required) {
                            const display = row.querySelector('[data-deposit-paid-display]');
                            if (display) {
                                display.textContent = newPaid.toLocaleString('vi-VN') + 'đ';
                                display.classList.remove('text-primary');
                                display.classList.add('text-success');
                            }
                            row.dataset.depositPaid = newPaid;
                            showToast(`Đơn #${item.madatban} đã cọc đủ (${newPaid.toLocaleString('vi-VN')}đ).`);
                        } else if (newPaid !== currentPaid) {
                            const display = row.querySelector('[data-deposit-paid-display]');
                            if (display) {
                                display.textContent = newPaid.toLocaleString('vi-VN') + 'đ';
                            }
                            row.dataset.depositPaid = newPaid;
                        }
                    });
                })
                .catch(() => {});
        };

        if (rows.length) {
            setInterval(polling, 5000);
        }
    });
</script>
