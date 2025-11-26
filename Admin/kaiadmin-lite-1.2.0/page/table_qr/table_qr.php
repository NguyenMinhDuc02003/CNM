<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsDonHang.php';

$db = new connect_db();
$orderService = new clsDonHang();

$successMessages = [];
$errorMessages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tableId = isset($_POST['table_id']) ? (int)$_POST['table_id'] : 0;
    if ($tableId <= 0) {
        $errorMessages[] = 'Thiếu mã bàn hợp lệ.';
    } else {
        try {
            $newToken = $orderService->regenerateTableQrToken($tableId);
            $successMessages[] = 'Đã tạo mã QR mới cho bàn #' . $tableId . '.';
        } catch (Throwable $th) {
            $errorMessages[] = $th->getMessage();
        }
    }
}

$tables = $db->xuatdulieu(
    "SELECT b.idban, b.SoBan, b.soluongKH, b.zone, b.qr_token, kv.TenKV
     FROM ban b
     LEFT JOIN khuvucban kv ON b.MaKV = kv.MaKV
     ORDER BY b.SoBan ASC"
);

foreach ($tables as &$tableRef) {
    $tableRef['qr_token'] = $orderService->getOrCreateTableQrToken((int)$tableRef['idban']);
}
unset($tableRef);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$qrBasePath = '/CNM/User/restoran-1.0.0/index.php?page=qr_order&token=';
$qrBaseUrl = rtrim($scheme . $host, '/') . $qrBasePath;

?>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-qrcode text-primary me-2"></i>Mã QR gọi món theo bàn</h3>
            <p class="text-muted mb-0">In và đặt trước mỗi bàn để khách tự quét và thêm món vào đơn đang phục vụ.</p>
        </div>
        <div class="input-group input-group-sm" style="max-width: 240px;">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0" id="tableSearch" placeholder="Tìm theo tên bàn/khu vực">
            </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            
            <a href="index.php?page=moBan" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Quay lại mở bàn
            </a>
        </div>
    </div>

    <?php foreach ($successMessages as $message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errorMessages as $message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <?php if (empty($tables)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Chưa có dữ liệu bàn trong hệ thống.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($tables as $table): ?>
                <?php
                    $token = $table['qr_token'];
                    $qrUrl = $qrBaseUrl . urlencode($token);
                    $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($qrUrl);
                ?>
                <div class="col-md-6 col-lg-4 qr-card" data-search="<?php echo htmlspecialchars(strtolower(($table['SoBan'] ?? '') . ' ' . ($table['TenKV'] ?? '') . ' ban ' . ($table['idban'] ?? ''))); ?>">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($table['SoBan'] ?? ('Bàn #' . $table['idban'])); ?></h5>
                                    <small class="text-muted">
                                        Khu vực: <?php echo htmlspecialchars($table['TenKV'] ?? 'Chưa rõ'); ?> • Sức chứa: <?php echo (int)$table['soluongKH']; ?>
                                    </small>
                                </div>
                                <form method="post">
                                    <input type="hidden" name="table_id" value="<?php echo (int)$table['idban']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Tạo lại mã QR mới">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="text-center mb-3">
                                <img src="<?php echo $qrImage; ?>" alt="QR <?php echo htmlspecialchars($table['SoBan']); ?>" class="img-fluid rounded border">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small text-muted">Đường dẫn gọi món</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($qrUrl); ?>" readonly onclick="this.select();">
                                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($qrUrl, ENT_QUOTES); ?>').then(() => { this.innerHTML = '<i class=&quot;fas fa-check&quot;></i>'; setTimeout(() => { this.innerHTML = '<i class=&quot;fas fa-copy&quot;></i>'; }, 2000); });">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="small text-muted mt-auto">
                                Mã: <code><?php echo htmlspecialchars($token); ?></code>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    (function () {
        const searchInput = document.getElementById('tableSearch');
        const cards = Array.from(document.querySelectorAll('.qr-card'));
        if (!searchInput || cards.length === 0) return;
        function normalize(str) {
            return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        searchInput.addEventListener('input', function () {
            const keyword = normalize(this.value.trim());
            cards.forEach((card) => {
                const haystack = normalize(card.dataset.search || '');
                const matched = keyword === '' || haystack.includes(keyword);
                card.style.display = matched ? '' : 'none';
            });
        });
    })();
</script>
