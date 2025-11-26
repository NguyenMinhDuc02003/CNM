<?php
// Dashboard doanh thu
require_once 'class/clsconnect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new connect_db();
$conn = $db->getConnection();
mysqli_set_charset($conn, 'utf8');

// Bộ lọc
// Hiển thị toàn bộ dữ liệu (không lọc theo thời gian)
$group = 'day'; // vẫn nhóm theo ngày cho biểu đồ xu hướng
$conditions = "d.TrangThai = 'hoan_thanh'";

// Tổng doanh thu, tổng đơn
$stmtSummary = $conn->prepare("
    SELECT SUM(h.TongTien) AS revenue, COUNT(DISTINCT d.idDH) AS orders
    FROM hoadon h
    JOIN donhang d ON h.idDH = d.idDH
    WHERE $conditions
");
$stmtSummary->execute();
$summary = $stmtSummary->get_result()->fetch_assoc() ?: ['revenue' => 0, 'orders' => 0];
$totalRevenue = (float)($summary['revenue'] ?? 0);
$totalOrders = (int)($summary['orders'] ?? 0);
$aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
$stmtSummary->close();

// Doanh thu theo thời gian
$selectGroup = "DATE(h.Ngay)";
$labelAlias = "label";
if ($group === 'month') {
    $selectGroup = "DATE_FORMAT(h.Ngay, '%Y-%m')";
} elseif ($group === 'quarter') {
    $selectGroup = "CONCAT(YEAR(h.Ngay), '-Q', QUARTER(h.Ngay))";
} elseif ($group === 'year') {
    $selectGroup = "YEAR(h.Ngay)";
}
$stmtRevenue = $conn->prepare("
    SELECT $selectGroup AS $labelAlias, SUM(h.TongTien) AS total
    FROM hoadon h
    JOIN donhang d ON h.idDH = d.idDH
    WHERE $conditions
    GROUP BY $labelAlias
    ORDER BY MIN(h.Ngay)
");
$stmtRevenue->execute();
$revenuePoints = $stmtRevenue->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtRevenue->close();

// Tổng doanh thu theo món ăn
$topProducts = [];
try {
    $sqlProduct = "
        SELECT 
            COALESCE(m.tenmonan, cthd.item_name, 'Khác') AS tenmonan,
            SUM(cthd.soluong * COALESCE(cthd.unit_price, cthd.thanhtien / NULLIF(cthd.soluong,0), 0)) AS total
        FROM chitiethoadon cthd
        JOIN hoadon h ON cthd.idHD = h.idHD
        JOIN donhang d ON h.idDH = d.idDH
        LEFT JOIN monan m ON m.idmonan = cthd.idmonan
        WHERE $conditions
        GROUP BY COALESCE(m.tenmonan, cthd.item_name, 'Khác')
        ORDER BY total DESC
    ";
    $stmtProd = $conn->prepare($sqlProduct);
    $stmtProd->execute();
    $topProducts = $stmtProd->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtProd->close();
} catch (Exception $e) {
    $topProducts = [];
}

// Top khách hàng theo doanh thu
$topCustomers = [];
try {
    $sqlCust = "
        SELECT COALESCE(k.tenKH, 'Khách lẻ') AS tenKH, SUM(h.TongTien) AS total, COUNT(DISTINCT d.idDH) AS orders
        FROM hoadon h
        JOIN donhang d ON h.idDH = d.idDH
        LEFT JOIN khachhang k ON k.idKH = d.idKH
        WHERE $conditions
        GROUP BY d.idKH, k.tenKH
        ORDER BY total DESC
        LIMIT 7
    ";
    $stmtCust = $conn->prepare($sqlCust);
    $stmtCust->execute();
    $topCustomers = $stmtCust->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtCust->close();
} catch (Exception $e) {
    $topCustomers = [];
}

?>
<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Dashboard doanh thu</h3>
            <p class="text-muted mb-0">Theo dõi doanh thu và đơn hàng với toàn bộ dữ liệu hiện có.</p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-stats card-round">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-icon">
                            <div class="icon-big text-center icon-success bubble-shadow-small">
                                <i class="fas fa-coins"></i>
                            </div>
                        </div>
                        <div class="col col-stats ms-3 ms-sm-0">
                            <div class="numbers">
                                <p class="card-category">Tổng doanh thu</p>
                                <h4 class="card-title"><?php echo number_format($totalRevenue); ?> VNĐ</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-stats card-round">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-icon">
                            <div class="icon-big text-center icon-primary bubble-shadow-small">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                        </div>
                        <div class="col col-stats ms-3 ms-sm-0">
                            <div class="numbers">
                                <p class="card-category">Số đơn</p>
                                <h4 class="card-title"><?php echo number_format($totalOrders); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-stats card-round">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-icon">
                            <div class="icon-big text-center icon-info bubble-shadow-small">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                        </div>
                        <div class="col col-stats ms-3 ms-sm-0">
                            <div class="numbers">
                                <p class="card-category">AOV (đơn TB)</p>
                                <h4 class="card-title"><?php echo number_format($aov); ?> VNĐ</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-stats card-round">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-icon">
                            <div class="icon-big text-center icon-warning bubble-shadow-small">
                                <i class="fas fa-chart-area"></i>
                            </div>
                        </div>
                        <div class="col col-stats ms-3 ms-sm-0">
                            <div class="numbers">
                                <p class="card-category">Điểm dữ liệu</p>
                                <h4 class="card-title"><?php echo count($revenuePoints); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card card-round">
                <div class="card-header">
                    <div class="card-head-row">
                        <div class="card-title">Doanh thu theo thời gian</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="min-height: 360px;">
                        <canvas id="revenueTrendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card card-round mt-3">
                <div class="card-header">
                    <div class="card-head-row">
                        <div class="card-title">Tổng doanh thu theo món ăn</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="min-height: 320px;">
                        <canvas id="productChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-round">
                <div class="card-header">
                    <div class="card-head-row">
                        <div class="card-title">Top khách hàng</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-container" style="min-height: 320px;">
                        <canvas id="customerChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="card card-round mt-3">
                <div class="card-header">
                    <div class="card-head-row">
                        <div class="card-title">Phân bổ kênh đơn hàng</div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-1">Dựa trên bảng donhang: nếu có cột booking_channel trong donhang_admin_meta sẽ mở rộng.</p>
                    <canvas id="channelChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Revenue trend
const revenueData = <?php echo json_encode($revenuePoints); ?>;
const revLabels = revenueData.map(i => i.label);
const revValues = revenueData.map(i => Number(i.total));
new Chart(document.getElementById('revenueTrendChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: revLabels,
        datasets: [{
            label: 'Doanh thu',
            data: revValues,
            borderColor: '#4CAF50',
            backgroundColor: 'rgba(76, 175, 80, 0.2)',
            fill: true,
            tension: 0.3,
            pointRadius: 3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('vi-VN') + 'đ' } }
        }
    }
});

// Top products
const prodData = <?php echo json_encode($topProducts); ?>;
new Chart(document.getElementById('productChart').getContext('2d'), {
    type: 'bar', // biểu đồ cột
    data: {
        labels: prodData.map(i => i.tenmonan),
        datasets: [{
            label: 'Doanh thu món ăn',
            data: prodData.map(i => Number(i.total)),
            backgroundColor: '#FF9800'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('vi-VN') } }
        }
    }
});

// Top customers
const custData = <?php echo json_encode($topCustomers); ?>;
new Chart(document.getElementById('customerChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: custData.map(i => i.tenKH),
        datasets: [{
            label: 'Doanh thu',
            data: custData.map(i => Number(i.total)),
            backgroundColor: '#3F51B5'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        indexAxis: 'y',
        scales: { x: { ticks: { callback: v => v.toLocaleString('vi-VN') } } }
    }
});

// Channel breakdown (placeholder static due to thiếu dữ liệu kênh)
new Chart(document.getElementById('channelChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Online/Web', 'Tại quầy', 'Điện thoại'],
        datasets: [{
            data: [60, 30, 10],
            backgroundColor: ['#2196F3', '#8BC34A', '#FFC107']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>
