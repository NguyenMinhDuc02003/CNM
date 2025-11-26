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

$filterStatus = isset($_GET['trangthai']) ? trim((string)$_GET['trangthai']) : 'preparing';
$allowedItemStatuses = [
    clsDonHang::ITEM_STATUS_PREPARING => 'Đang chế biến',
    clsDonHang::ITEM_STATUS_READY => 'Sẵn sàng phục vụ'
];
if (!array_key_exists($filterStatus, $allowedItemStatuses)) {
    $filterStatus = clsDonHang::ITEM_STATUS_PREPARING;
}

$statusMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'], $_POST['target_status'])) {
    $itemId = (int)$_POST['item_id'];
    $targetStatus = trim((string)$_POST['target_status']);
    try {
        $orderService->updateItemStatus($itemId, $targetStatus, $_SESSION['nhanvien_id'] ?? null, 'kitchen');
        $statusMessage = ['type' => 'success', 'text' => 'Đã cập nhật trạng thái món.'];
    } catch (Throwable $th) {
        $statusMessage = ['type' => 'danger', 'text' => $th->getMessage()];
    }
}

$items = $db->xuatdulieu_prepared(
    "
    SELECT 
        ct.idCTDH,
        ct.idDH,
        ct.idmonan,
        ct.SoLuong,
        ct.TrangThai,
        ct.GhiChu,
        ct.sent_at,
        ct.created_at,
        d.MaDonHang,
        d.TrangThai AS order_status,
        meta.table_label,
        meta.area_name,
        m.tenmonan
    FROM chitietdonhang ct
    JOIN donhang d ON ct.idDH = d.idDH
    LEFT JOIN donhang_admin_meta meta ON d.idDH = meta.idDH
    LEFT JOIN monan m ON ct.idmonan = m.idmonan
    WHERE ct.TrangThai IN (?, ?)
      AND d.TrangThai IN (?, ?)
    ORDER BY ct.TrangThai ASC, ct.sent_at ASC, ct.idCTDH ASC
    ",
    [clsDonHang::ITEM_STATUS_PREPARING, clsDonHang::ITEM_STATUS_READY, clsDonHang::ORDER_STATUS_OPEN, clsDonHang::ORDER_STATUS_PENDING_PAYMENT]
);

$itemsByOrder = [];
foreach ($items as $item) {
    if ($filterStatus !== $item['TrangThai']) {
        continue;
    }
    $orderId = (int)$item['idDH'];
    $itemsByOrder[$orderId]['info'] = [
        'MaDonHang' => $item['MaDonHang'],
        'table_label' => $item['table_label'],
        'area_name' => $item['area_name'],
        'order_status' => $item['order_status']
    ];
    $itemsByOrder[$orderId]['items'][] = $item;
}

?>
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h2 class="mb-0"><i class="fas fa-fire-alt text-warning me-2"></i>Bếp / Bar</h2>
        <div class="btn-group" role="group">
            <?php foreach ($allowedItemStatuses as $statusKey => $label): ?>
                <a class="btn btn-outline-dark <?php echo $filterStatus === $statusKey ? 'active' : ''; ?>"
                   href="index.php?page=bepdonhang&amp;trangthai=<?php echo htmlspecialchars($statusKey); ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($statusMessage): ?>
        <div class="alert alert-<?php echo htmlspecialchars($statusMessage['type']); ?>">
            <?php echo htmlspecialchars($statusMessage['text']); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($itemsByOrder)): ?>
        <div class="alert alert-success">
            <i class="fas fa-smile-beam me-2"></i>Không có món nào trong trạng thái "<?php echo htmlspecialchars($allowedItemStatuses[$filterStatus]); ?>".
        </div>
        <?php return; ?>
    <?php endif; ?>

    <div class="row g-4">
        <?php foreach ($itemsByOrder as $orderId => $group): ?>
            <?php
                $orderInfo = $group['info'];
                $tableLabel = $orderInfo['table_label'] ?: 'Bàn #' . $orderId;
                $areaName = $orderInfo['area_name'] ?: 'Khu vực chung';
            ?>
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-0"><?php echo htmlspecialchars($tableLabel); ?></h5>
                                <div class="text-muted small"><?php echo htmlspecialchars($areaName); ?> &middot; Đơn <?php echo htmlspecialchars($orderInfo['MaDonHang'] ?? ('DH#' . $orderId)); ?></div>
                            </div>
                            <a href="index.php?page=xemDH&amp;idDH=<?php echo (int)$orderId; ?>" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <div class="list-group list-group-flush">
                            <?php foreach ($group['items'] as $item): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($item['tenmonan'] ?? ('Món #' . $item['idmonan'])); ?></div>
                                            <div class="text-muted small">
                                                SL: <?php echo (int)$item['SoLuong']; ?>
                                                <?php if (!empty($item['GhiChu'])): ?>
                                                    &middot; Ghi chú: <?php echo htmlspecialchars($item['GhiChu']); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted small">
                                                Gửi lúc: <?php echo !empty($item['sent_at']) ? htmlspecialchars(date('H:i', strtotime($item['sent_at']))) : '—'; ?>
                                            </div>
                                        </div>
                                        <div class="d-flex flex-column gap-2">
                                            <?php if ($item['TrangThai'] === clsDonHang::ITEM_STATUS_PREPARING): ?>
                                                <form method="post">
                                                    <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                    <input type="hidden" name="target_status" value="<?php echo clsDonHang::ITEM_STATUS_READY; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check me-1"></i>Hoàn tất
                                                    </button>
                                                </form>
                                            <?php elseif ($item['TrangThai'] === clsDonHang::ITEM_STATUS_READY): ?>
                                                <form method="post">
                                                    <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                    <input type="hidden" name="target_status" value="<?php echo clsDonHang::ITEM_STATUS_PREPARING; ?>">
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                        <i class="fas fa-undo me-1"></i>Trả lại
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
