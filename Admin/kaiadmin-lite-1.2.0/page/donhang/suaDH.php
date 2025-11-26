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

$allowedStatuses = [
    clsDonHang::ORDER_STATUS_OPEN => 'Đang phục vụ',
    clsDonHang::ORDER_STATUS_PENDING_PAYMENT => 'Chờ thanh toán',
    clsDonHang::ORDER_STATUS_CANCELLED => 'Đã hủy'
];

$statusMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $newStatus = isset($_POST['TrangThai']) ? trim((string)$_POST['TrangThai']) : '';
    $note = isset($_POST['note']) ? trim((string)$_POST['note']) : null;

    if (!array_key_exists($newStatus, $allowedStatuses)) {
        $statusMessage = ['type' => 'danger', 'text' => 'Trạng thái không hợp lệ.'];
    } else {
        $db->beginTransaction();
        try {
            $db->tuychinh(
                "UPDATE donhang SET TrangThai = ? WHERE idDH = ?",
                [$newStatus, $orderId]
            );
            $db->tuychinh(
                "UPDATE donhang_admin_meta SET note = ?, updated_at = NOW() WHERE idDH = ?",
                [$note, $orderId]
            );

            if ($newStatus === clsDonHang::ORDER_STATUS_CANCELLED) {
                $db->tuychinh("UPDATE ban SET TrangThai = 'empty' WHERE idban = ?", [$order['idban']]);
            }

            $db->commit();
            $statusMessage = ['type' => 'success', 'text' => 'Đã cập nhật đơn hàng.'];
            $order = $orderService->getOrderById($orderId);
        } catch (Throwable $th) {
            $db->rollback();
            $statusMessage = ['type' => 'danger', 'text' => 'Lỗi cập nhật: ' . $th->getMessage()];
        }
    }
}

?>
<div class="container py-4">
    <div class="mb-4">
        <a href="index.php?page=dsdonhang" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Quay lại danh sách
        </a>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h3 class="mb-1"><i class="fas fa-edit text-warning me-2"></i>Chỉnh sửa đơn hàng</h3>
                <div class="text-muted">
                    Mã đơn: <?php echo htmlspecialchars($order['MaDonHang'] ?? ('DH#' . $orderId)); ?> &middot;
                    Bàn: <?php echo htmlspecialchars($order['table_label'] ?? $order['SoBan'] ?? '—'); ?>
                </div>
            </div>
            <a href="index.php?page=xemDH&amp;idDH=<?php echo (int)$orderId; ?>" class="btn btn-outline-primary">
                <i class="fas fa-eye me-1"></i>Xem chi tiết
            </a>
        </div>
    </div>

    <?php if ($statusMessage): ?>
        <div class="alert alert-<?php echo htmlspecialchars($statusMessage['type']); ?>">
            <?php echo htmlspecialchars($statusMessage['text']); ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form method="post" class="row g-3">
                <div class="col-md-6">
                    <label for="TrangThai" class="form-label">Trạng thái</label>
                    <select name="TrangThai" id="TrangThai" class="form-select">
                        <?php foreach ($allowedStatuses as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($order['TrangThai'] === $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tổng tiền</label>
                    <input type="text" class="form-control" value="<?php echo number_format((float)$order['TongTien'], 0, ',', '.'); ?> đ" disabled>
                </div>
                <div class="col-12">
                    <label for="note" class="form-label">Ghi chú nội bộ</label>
                    <textarea class="form-control" name="note" id="note" rows="4" placeholder="Ghi chú phục vụ hoặc lý do cập nhật..."><?php echo htmlspecialchars($order['note'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 d-flex justify-content-between">
                    <div class="text-muted small">
                        Cập nhật lần cuối: <?php echo !empty($order['updated_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($order['updated_at']))) : '—'; ?>
                    </div>
                    <button type="submit" name="update_order" class="btn btn-warning">
                        <i class="fas fa-save me-1"></i>Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
