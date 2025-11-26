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

$orderService = new clsDonHang();

$orderId = isset($_GET['idDH']) ? (int)$_GET['idDH'] : 0;
if ($orderId <= 0) {
    echo '<div class="container py-5"><div class="alert alert-danger">Không tìm thấy đơn hàng.</div></div>';
    return;
}

$order = $orderService->getOrderById($orderId);
if (!$order) {
    echo '<div class="container py-5"><div class="alert alert-danger">Không tìm thấy đơn hàng.</div></div>';
    return;
}

$orderItems = $orderService->getOrderItems($orderId);
$totals = $orderService->computeOrderTotals($orderId);
$payments = $db->xuatdulieu_prepared(
    "SELECT SoTien, PhuongThuc, NgayThanhToan, TrangThai, MaGiaoDich
     FROM thanhtoan
     WHERE idDH = ?
     ORDER BY NgayThanhToan DESC",
    [$orderId]
);

$statusLabels = [
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

$itemStatusLabels = [
    clsDonHang::ITEM_STATUS_PREPARING => 'Đang chế biến',
    clsDonHang::ITEM_STATUS_READY => 'Sẵn sàng',
    clsDonHang::ITEM_STATUS_SERVED => 'Đã phục vụ',
    clsDonHang::ITEM_STATUS_CANCELLED => 'Đã hủy'
];

$itemBadges = [
    clsDonHang::ITEM_STATUS_PREPARING => 'badge bg-warning text-dark',
    clsDonHang::ITEM_STATUS_READY => 'badge bg-info text-dark',
    clsDonHang::ITEM_STATUS_SERVED => 'badge bg-success',
    clsDonHang::ITEM_STATUS_CANCELLED => 'badge bg-secondary'
];

$status = $order['TrangThai'] ?? '';
$statusBadgeClass = $statusBadges[$status] ?? 'bg-secondary';
$statusLabel = $statusLabels[$status] ?? ucfirst($status);

?>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">
                <i class="fas fa-receipt text-warning me-2"></i>Đơn hàng
                <?php echo htmlspecialchars($order['MaDonHang'] ?? ('DH#' . $orderId)); ?>
            </h2>
            <div class="text-muted">
                Mã hệ thống: <?php echo (int)$orderId; ?> &middot;
                Ngày mở: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['NgayDatHang']))); ?>
            </div>
        </div>
        <span class="badge <?php echo $statusBadgeClass; ?> px-3 py-2 fs-6">
            <?php echo htmlspecialchars($statusLabel); ?>
        </span>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-chair text-warning me-2"></i>Thông tin bàn</h5>
                    <ul class="list-unstyled mb-0">
                        <li><strong>Bàn:</strong> <?php echo htmlspecialchars($order['table_label'] ?? $order['SoBan'] ?? '—'); ?></li>
                        <li><strong>Khu vực:</strong> <?php echo htmlspecialchars($order['area_name'] ?? '—'); ?></li>
                        <li><strong>Số khách dự kiến:</strong> <?php echo (int)($order['people_count'] ?? 0); ?></li>
                        <?php if (!empty($order['booking_time'])): ?>
                            <li><strong>Đặt lúc:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['booking_time']))); ?></li>
                        <?php endif; ?>
                        <li><strong>Nhân viên mở:</strong> <?php echo !empty($order['opened_by']) ? ('#' . (int)$order['opened_by']) : '—'; ?></li>
                        <?php if (!empty($order['closed_by'])): ?>
                            <li><strong>Nhân viên đóng:</strong> <?php echo '#' . (int)$order['closed_by']; ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-user text-warning me-2"></i>Khách hàng</h5>
                    <ul class="list-unstyled mb-0">
                        <li><strong>Tên:</strong> <?php echo htmlspecialchars($order['tenKH'] ?? 'Khách tại bàn'); ?></li>
                        <?php if (!empty($order['email'])): ?>
                            <li><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></li>
                        <?php endif; ?>
                        <?php if (!empty($order['sodienthoai'])): ?>
                            <li><strong>SĐT:</strong> <?php echo htmlspecialchars($order['sodienthoai']); ?></li>
                        <?php endif; ?>
                        <?php if (!empty($order['note'])): ?>
                            <li><strong>Ghi chú:</strong> <?php echo nl2br(htmlspecialchars($order['note'])); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-cash-register text-warning me-2"></i>Tổng kết</h5>
                    <ul class="list-unstyled mb-0">
                        <li><strong>Tạm tính món:</strong> <?php echo number_format($totals['subtotal'], 0, ',', '.'); ?>đ</li>
                        <li><strong>Phụ thu:</strong> <?php echo number_format($totals['surcharge'], 0, ',', '.'); ?>đ</li>
                        <li><strong>Tổng thanh toán:</strong> <span class="fw-bold text-primary"><?php echo number_format($totals['total'], 0, ',', '.'); ?>đ</span></li>
                        <?php if (!empty($order['payment_method']) && $order['payment_method'] !== 'none'): ?>
                            <li><strong>Hình thức:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></li>
                        <?php endif; ?>
                        <?php if (!empty($order['closed_at'])): ?>
                            <li><strong>Hoàn tất lúc:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['closed_at']))); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-utensils text-warning me-2"></i>Danh sách món</h5>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Món</th>
                            <th class="text-center">SL</th>
                            <th class="text-end">Đơn giá</th>
                            <th class="text-end">Thành tiền</th>
                            <th>Trạng thái</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orderItems)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Chưa có món nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($orderItems as $item): ?>
                                <?php
                                    $itemStatus = $item['TrangThai'] ?? clsDonHang::ITEM_STATUS_PREPARING;
                                    $itemBadge = $itemBadges[$itemStatus] ?? 'badge bg-secondary';
                                    $itemStatusLabel = $itemStatusLabels[$itemStatus] ?? ucfirst($itemStatus);
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($item['tenmonan'] ?? ('Món #' . $item['idmonan'])); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($item['DonViTinh'] ?? ''); ?></div>
                                    </td>
                                    <td class="text-center"><?php echo (int)$item['SoLuong']; ?></td>
                                    <td class="text-end"><?php echo number_format((float)$item['DonGia'], 0, ',', '.'); ?>đ</td>
                                    <td class="text-end"><?php echo number_format((float)$item['DonGia'] * (int)$item['SoLuong'], 0, ',', '.'); ?>đ</td>
                                    <td><span class="<?php echo $itemBadge; ?>"><?php echo htmlspecialchars($itemStatusLabel); ?></span></td>
                                    <td><?php echo htmlspecialchars($item['GhiChu'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="mb-3"><i class="fas fa-money-bill-wave text-warning me-2"></i>Lịch sử thanh toán</h5>
            <?php if (empty($payments)): ?>
                <div class="text-muted">Chưa có giao dịch thanh toán.</div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($payments as $payment): ?>
                        <div class="border-start ps-3 mb-3">
                            <div class="fw-semibold">
                                <?php echo number_format((float)$payment['SoTien'], 0, ',', '.'); ?>đ
                                &middot; <?php echo htmlspecialchars(ucfirst($payment['PhuongThuc'])); ?>
                            </div>
                            <div class="text-muted small">
                                Vào lúc <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($payment['NgayThanhToan']))); ?>
                                &middot; Trạng thái: <?php echo htmlspecialchars($payment['TrangThai']); ?>
                                <?php if (!empty($payment['MaGiaoDich'])): ?>
                                    <br>Mã giao dịch: <?php echo htmlspecialchars($payment['MaGiaoDich']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
