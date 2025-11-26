<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsDonHang.php';

$db = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db
    ? $GLOBALS['admin_db']
    : new connect_db();
$conn = $db->getConnection();

mysqli_set_charset($conn, 'utf8mb4');

$statusMap = [
    clsDonHang::ORDER_STATUS_OPEN => 'Đang phục vụ',
    clsDonHang::ORDER_STATUS_PENDING_PAYMENT => 'Chờ thanh toán',
    clsDonHang::ORDER_STATUS_DONE => 'Hoàn thành',
    clsDonHang::ORDER_STATUS_CANCELLED => 'Đã hủy'
];

$statusBadges = [
    clsDonHang::ORDER_STATUS_OPEN => 'bg-warning text-dark',
    clsDonHang::ORDER_STATUS_PENDING_PAYMENT => 'bg-info text-dark',
    clsDonHang::ORDER_STATUS_DONE => 'bg-success',
    clsDonHang::ORDER_STATUS_CANCELLED => 'bg-secondary'
];

$allowedFilters = array_merge(['all'], array_keys($statusMap));
$filter = isset($_GET['trangthai']) ? trim((string)$_GET['trangthai']) : 'all';
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

// Date filter: allow 'date' param in format YYYY-MM-DD, or 'all' to disable
$date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if ($date === 'all') {
    $date = '';
} elseif ($date !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) {
        $date = '';
    }
}

$deleteMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_idDH'])) {
    $deleteId = (int)$_POST['delete_idDH'];
    if ($deleteId > 0) {
        $orderRow = $db->xuatdulieu_prepared(
            "SELECT TrangThai FROM donhang WHERE idDH = ? LIMIT 1",
            [$deleteId]
        );
        if (!empty($orderRow)) {
            $orderStatus = $orderRow[0]['TrangThai'];
            if (in_array($orderStatus, [clsDonHang::ORDER_STATUS_OPEN, clsDonHang::ORDER_STATUS_CANCELLED], true)) {
                $db->beginTransaction();
                try {
                    $db->tuychinh("DELETE FROM chitietdonhang WHERE idDH = ?", [$deleteId]);
                    $db->tuychinh("DELETE FROM donhang WHERE idDH = ?", [$deleteId]);
                    $db->commit();
                    $deleteMessage = ['type' => 'success', 'text' => 'Đã xóa đơn hàng.'];
                } catch (Throwable $th) {
                    $db->rollback();
                    $deleteMessage = ['type' => 'danger', 'text' => 'Không thể xóa đơn hàng: ' . $th->getMessage()];
                }
            } else {
                $deleteMessage = ['type' => 'warning', 'text' => 'Chỉ có thể xóa đơn hàng đang phục vụ hoặc đã hủy.'];
            }
        }
    }
}

// Build WHERE clause from optional filters
$conditions = [];
$params = [];
if ($filter !== 'all') {
    $conditions[] = 'd.TrangThai = ?';
    $params[] = $filter;
}
if ($date !== '') {
    $conditions[] = 'DATE(d.NgayDatHang) = ?';
    $params[] = $date;
}
$whereClause = '';
if (!empty($conditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

$orderRows = $db->xuatdulieu_prepared(
    "
    SELECT 
        d.idDH,
        d.MaDonHang,
        d.NgayDatHang,
        d.TrangThai,
        d.TongTien,
        COALESCE(kh.tenKH, 'Khách tại bàn') AS tenKH,
        meta.table_label,
        meta.area_name,
        meta.payment_method AS meta_payment_method,
        meta.people_count,
        b.SoBan,
        pay.PhuongThuc AS payment_method_display,
        pay.NgayThanhToan AS paid_at
    FROM donhang d
    LEFT JOIN khachhang kh ON d.idKH = kh.idKH
    LEFT JOIN donhang_admin_meta meta ON d.idDH = meta.idDH
    LEFT JOIN ban b ON d.idban = b.idban
    LEFT JOIN (
        SELECT idDH, MAX(NgayThanhToan) AS NgayThanhToan, MAX(PhuongThuc) AS PhuongThuc
        FROM thanhtoan
        WHERE TrangThai = 'completed'
        GROUP BY idDH
    ) pay ON pay.idDH = d.idDH
    $whereClause
    ORDER BY d.idDH DESC
    ",
    $params
);

?>
<div class="container mb-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
        <div class="btn-group" role="group" aria-label="Lọc trạng thái đơn hàng">
            <a class="btn btn-outline-dark <?php echo $filter === 'all' ? 'active' : ''; ?>" href="index.php?page=dsdonhang">Tất cả</a>
            <?php foreach ($statusMap as $key => $label): ?>
                <a class="btn btn-outline-dark <?php echo $filter === $key ? 'active' : ''; ?>"
                   href="index.php?page=dsdonhang&amp;trangthai=<?php echo htmlspecialchars($key); ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
          <form method="GET" class="d-flex align-items-center" style="gap:.5rem;">
                <input type="hidden" name="page" value="dsdonhang">
                <input type="hidden" name="trangthai" value="<?php echo htmlspecialchars($filter); ?>">
                <input type="date" name="date" class="form-control form-control-sm w-50" value="<?php echo htmlspecialchars($date); ?>">
                <button type="submit" class="btn btn-sm btn-primary">Lọc theo ngày</button>
                <a href="<?php echo 'index.php?page=dsdonhang&date=' . urlencode(date('Y-m-d')); ?>" class="btn btn-sm btn-outline-success">Hôm nay</a>
                <a href="index.php?page=dsdonhang" class="btn btn-sm btn-outline-secondary">Hiển thị tất cả</a>
            </form>
        <div class="d-flex gap-2 align-items-center">
          
            <a href="index.php?page=moBan&reset=1" class="btn btn-warning">
                <i class="fas fa-plus me-2"></i>Mở đơn mới
            </a>
        </div>
    </div>

    <?php if ($deleteMessage): ?>
        <div class="alert alert-<?php echo htmlspecialchars($deleteMessage['type']); ?> mt-3">
            <?php echo htmlspecialchars($deleteMessage['text']); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mt-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Mã đơn</th>
                            <th>Bàn/Khu vực</th>
                            <th>Khách</th>
                            <th>Thời gian mở</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th class="text-end">Tùy chọn</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orderRows)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>Không tìm thấy đơn hàng nào phù hợp.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orderRows as $row): ?>
                                <?php
                                    $status = $row['TrangThai'];
                                    $badge = $statusBadges[$status] ?? 'bg-secondary';
                                    $statusLabel = $statusMap[$status] ?? ucfirst($status);
                                    $tableLabel = $row['table_label'] ?: ($row['SoBan'] ?? '');
                                    $areaLabel = $row['area_name'] ?? '';
                                    $paymentMethod = $row['payment_method_display']
                                        ?: ($row['meta_payment_method'] && $row['meta_payment_method'] !== 'none' ? $row['meta_payment_method'] : null);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($row['MaDonHang'] ?? ('DH#' . $row['idDH'])); ?></div>
                                        <div class="text-muted small">ID: <?php echo (int)$row['idDH']; ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?php echo htmlspecialchars($tableLabel ?: '—'); ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?php if ($areaLabel): ?>
                                                Khu vực: <?php echo htmlspecialchars($areaLabel); ?>
                                            <?php else: ?>
                                                Bàn: <?php echo htmlspecialchars($row['SoBan'] ?? '—'); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($row['tenKH']); ?></div>
                                        <?php if (!empty($row['people_count'])): ?>
                                            <div class="text-muted small"><?php echo (int)$row['people_count']; ?> khách</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['NgayDatHang']))); ?></div>
                                        <?php if (!empty($row['paid_at'])): ?>
                                            <div class="text-muted small">Thanh toán: <?php echo htmlspecialchars(date('d/m H:i', strtotime($row['paid_at']))); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold">
                                        <?php echo number_format((float)$row['TongTien'], 0, ',', '.'); ?>đ
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                        <?php if ($paymentMethod && $status === clsDonHang::ORDER_STATUS_DONE):
                                            $pm = (string)$paymentMethod;
                                            if (strcasecmp($pm, 'Cash') === 0) {
                                                $pmLabel = 'Tiền mặt';
                                            } elseif (strcasecmp($pm, 'Transfer') === 0) {
                                                $pmLabel = 'Chuyển khoản';
                                            } else {
                                                $pmLabel = ucfirst($pm);
                                            }
                                        ?>
                                            <div class="text-muted small">
                                                <?php echo htmlspecialchars($pmLabel); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if (in_array($status, [clsDonHang::ORDER_STATUS_OPEN, clsDonHang::ORDER_STATUS_PENDING_PAYMENT], true)): ?>
                                            <a href="index.php?page=moBan&amp;order_id=<?php echo (int)$row['idDH']; ?>" class="btn btn-outline-success btn-sm" title="Mở giao diện phục vụ">
                                                <i class="fas fa-door-open"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="index.php?page=xemDH&amp;idDH=<?php echo (int)$row['idDH']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (in_array($status, [clsDonHang::ORDER_STATUS_OPEN, clsDonHang::ORDER_STATUS_CANCELLED], true)): ?>
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteModal"
                                                    data-order-id="<?php echo (int)$row['idDH']; ?>"
                                                    data-order-code="<?php echo htmlspecialchars($row['MaDonHang'] ?? ('DH#' . $row['idDH'])); ?>">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-trash-alt text-danger me-2"></i>Xóa đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa đơn hàng <strong id="deleteOrderCode">#</strong>? Hành động này không thể hoàn tác.</p>
                <input type="hidden" name="delete_idDH" id="deleteOrderId" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" class="btn btn-danger">Xóa đơn</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const deleteModal = document.getElementById('deleteModal');
        if (!deleteModal) {
            return;
        }
        deleteModal.addEventListener('show.bs.modal', (event) => {
            const button = event.relatedTarget;
            if (!button) {
                return;
            }
            const orderId = button.getAttribute('data-order-id');
            const orderCode = button.getAttribute('data-order-code');
            deleteModal.querySelector('#deleteOrderId').value = orderId;
            deleteModal.querySelector('#deleteOrderCode').textContent = orderCode || '#';
        });
    });
</script>
