<?php
require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsPayroll.php';

$db = new clsPayroll();
$actionMessage = '';
$messageType = 'success';

$periodCode = isset($_GET['period']) ? trim($_GET['period']) : date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodCode = isset($_POST['period_code']) ? trim($_POST['period_code']) : date('Y-m');
    try {
        $calc = $db->calculateSalaryForPeriod($periodCode, $_SESSION['nhanvien_id'] ?? null);
        $actionMessage = 'Đã tính bảng lương kỳ ' . htmlspecialchars($periodCode) . ' (' . count($calc['lines']) . ' nhân viên).';
        $messageType = 'success';
    } catch (Exception $e) {
        $actionMessage = 'Lỗi: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

$rows = $db->listPeriodLines($periodCode);
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h3 class="mb-0"><i class="fas fa-money-check-alt me-2 text-primary"></i>Bảng lương</h3>
            <small class="text-muted">Tính theo chấm công & lương cơ bản trong nhân viên.</small>
        </div>
        <form method="POST" class="d-flex align-items-end gap-2">
            <div>
                <label class="form-label mb-1">Kỳ lương (YYYY-MM)</label>
                <input type="month" name="period_code" class="form-control" value="<?php echo htmlspecialchars($periodCode); ?>" required>
            </div>
            <button type="submit" class="btn btn-warning mt-auto"><i class="fas fa-calculator me-1"></i>Tính lại</button>
        </form>
    </div>

    <?php if ($actionMessage): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Nhân viên</th>
                            <th>Lương cơ bản</th>
                            <th>Công</th>
                            <th>Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $r): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($r['HoTen'] ?? 'Nhân viên'); ?></div>
                                        <small class="text-muted">Mã NV: <?php echo (int)($r['staff_id'] ?? 0); ?></small>
                                    </td>
                                    <td><?php echo number_format((float)$r['base_salary']); ?>đ</td>
                                    <td><?php echo (int)$r['paid_days']; ?></td>
                                    <td class="fw-semibold text-success"><?php echo number_format((float)$r['net_pay']); ?>đ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Chưa có dữ liệu cho kỳ <?php echo htmlspecialchars($periodCode); ?>.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted small mb-0">Công thức rút gọn: lương ngày = lương cơ bản / 26; net = lương ngày * công (không tính OT/phụ cấp).</p>
        </div>
    </div>
</div>
