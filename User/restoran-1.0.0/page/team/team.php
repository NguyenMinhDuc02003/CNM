<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

require_once 'class/clsconnect.php';
$db = new connect_db();

// Lấy danh sách khu vực
$areas = $db->xuatdulieu("SELECT MaKV, TenKV, COALESCE(PhuThu,0) AS PhuThu FROM khuvucban ORDER BY TenKV");
$selectedArea = isset($_GET['khuvuc']) ? (int)$_GET['khuvuc'] : 0;
if ($selectedArea === 0 && !empty($areas)) {
    $selectedArea = (int)$areas[0]['MaKV'];
}

// Lấy thông tin khu vực đang chọn
$currentAreaName = 'Tất cả khu vực';
$currentSurcharge = 0;
foreach ($areas as $area) {
    if ((int)$area['MaKV'] === $selectedArea) {
        $currentAreaName = $area['TenKV'];
        $currentSurcharge = (float)$area['PhuThu'];
        break;
    }
}

// Lấy danh sách bàn theo khu vực
$tableParams = [];
$tableSql = "SELECT idban, SoBan, MaKV, soluongKH, COALESCE(zone, 'A') AS zone FROM ban";
if ($selectedArea > 0) {
    $tableSql .= " WHERE MaKV = ?";
    $tableParams[] = $selectedArea;
}
$tableSql .= " ORDER BY zone, SoBan";
$dsBan = $db->xuatdulieu_prepared($tableSql, $tableParams);

// Gom theo zone
$zones = [];
foreach ($dsBan as $b) {
    $zone = $b['zone'] ?? 'A';
    if (!isset($zones[$zone])) {
        $zones[$zone] = [];
    }
    $zones[$zone][] = $b;
}
?>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Spinner Start -->
        <div id="spinner"
            class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Đang tải...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Hero -->
        <div class="container-xxl py-5 bg-dark hero-header mb-5">
            <div class="container text-center my-5 pt-5 pb-4">
                <h1 class="display-3 text-white mb-3 animated slideInDown">Sơ đồ bàn</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Sơ đồ bàn</li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="container my-5">
            <div class="card shadow-sm mb-4">
                <div class="card-body d-flex flex-wrap gap-3 align-items-center">
                    <form method="GET" action="index.php" class="d-flex align-items-center gap-2">
                        <input type="hidden" name="page" value="team">
                        <label for="khuvuc" class="fw-semibold mb-0">Chọn khu vực:</label>
                        <select name="khuvuc" id="khuvuc" class="form-select w-auto" onchange="this.form.submit()">
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo (int)$area['MaKV']; ?>" <?php echo (int)$area['MaKV'] === $selectedArea ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area['TenKV']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <div class="text-muted">
                        <i class="fas fa-map-marker-alt me-1"></i> Khu vực: <strong><?php echo htmlspecialchars($currentAreaName); ?></strong>
                        <span class="ms-3"><i class="fas fa-money-bill me-1"></i>Phụ thu: <?php echo number_format($currentSurcharge); ?>đ</span>
                    </div>
                    <div class="ms-auto d-flex gap-3 align-items-center">
                        <span class="badge bg-light text-dark border"><i class="fas fa-square-full text-primary me-1"></i> Bàn nhỏ (&le; 6 khách)</span>
                        <span class="badge bg-light text-dark border"><i class="fas fa-square-full text-success me-1"></i> Bàn lớn (&gt; 6 khách)</span>
                    </div>
                </div>
            </div>

            <div class="floor-layout">
                <h4 class="text-center mb-4"><?php echo htmlspecialchars($currentAreaName); ?></h4>

                <?php if (empty($zones)): ?>
                    <div class="alert alert-info text-center">Chưa có bàn nào trong khu vực này.</div>
                <?php else: ?>
                    <div class="zone-container">
                        <?php foreach ($zones as $zoneKey => $tables): ?>
                            <div class="zone <?php echo in_array($zoneKey, ['A','B']) ? 'small-tables' : 'large-tables'; ?>">
                                <div class="zone-title">Zone <?php echo htmlspecialchars($zoneKey); ?></div>
                                <div class="table-grid">
                                    <?php foreach ($tables as $b): ?>
                                        <div class="table-btn <?php echo ($b['soluongKH'] > 6) ? 'table-large' : 'table-small'; ?>">
                                            <span class="table-type">
                                                <?php echo $b['soluongKH'] > 6 ? 'Bàn lớn' : 'Bàn nhỏ'; ?>
                                            </span>
                                            <span class="table-number"><?php echo htmlspecialchars($b['SoBan']); ?></span>
                                            <span class="capacity">
                                                <i class="fas fa-users"></i> <?php echo (int)$b['soluongKH']; ?> khách
                                            </span>
                                            <span class="surcharge">
                                                <i class="fas fa-money-bill"></i> <?php echo number_format($currentSurcharge); ?>đ
                                            </span>
                                            <div class="capacity-icons">
                                                <?php for($i = 0; $i < min((int)$b['soluongKH'], 5); $i++): ?>
                                                    <i class="fas fa-user"></i>
                                                <?php endfor; ?>
                                                <?php if((int)$b['soluongKH'] > 5): ?>
                                                    <span>+<?php echo (int)$b['soluongKH'] - 5; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CSS sơ đồ bàn -->
    <link rel="stylesheet" href="page/booking/style.css">
</body>
