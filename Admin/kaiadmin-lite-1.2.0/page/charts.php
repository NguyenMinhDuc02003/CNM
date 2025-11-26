<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

session_start();

// Kết nối CSDL
$conn = mysqli_connect('localhost', 'hceeab2b55_chung9atm', 'Chung2002!', 'hceeab2b55_restaurant');
if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8");

// Lấy doanh thu theo ngày trong tháng hiện tại
$queryMonthlyRevenue = "SELECT 
    DATE(h.Ngay) as date,
    SUM(h.TongTien) as total
FROM hoadon h
JOIN donhang d ON h.idDH = d.idDH
WHERE d.TrangThai = 'Đã thanh toán'
AND MONTH(h.Ngay) = MONTH(CURRENT_DATE())
AND YEAR(h.Ngay) = YEAR(CURRENT_DATE())
GROUP BY DATE(h.Ngay)
ORDER BY date ASC";
$resultMonthlyRevenue = mysqli_query($conn, $queryMonthlyRevenue);
$monthlyData = [];
while($row = mysqli_fetch_assoc($resultMonthlyRevenue)) {
    $monthlyData[] = $row;
}

// Lấy doanh thu theo ngày trong 7 ngày gần nhất
$queryDailyRevenue = "SELECT 
    DATE(h.Ngay) as date,
    SUM(h.TongTien) as total
FROM hoadon h
JOIN donhang d ON h.idDH = d.idDH
WHERE d.TrangThai = 'Đã thanh toán'
AND DATE(h.Ngay) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(h.Ngay)
ORDER BY date DESC";
$resultDailyRevenue = mysqli_query($conn, $queryDailyRevenue);
$dailyData = [];
while($row = mysqli_fetch_assoc($resultDailyRevenue)) {
    $dailyData[] = $row;
}

// Lấy số đơn hàng theo ngày trong 7 ngày gần nhất
$queryDailyOrders = "SELECT 
    DATE(NgayDatHang) as date,
    COUNT(*) as total
FROM donhang 
WHERE TrangThai = 'Đã thanh toán'
AND DATE(NgayDatHang) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(NgayDatHang)
ORDER BY date DESC";
$resultDailyOrders = mysqli_query($conn, $queryDailyOrders);
$dailyOrdersData = [];
while($row = mysqli_fetch_assoc($resultDailyOrders)) {
    $dailyOrdersData[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biểu đồ doanh thu và đơn hàng</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .chart-container {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background: white;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        canvas {
            max-height: 400px;
        }
    </style>
</head>
<body>
    <?php
    // Debug data
    if(isset($_GET['debug'])) {
        echo '<div style="margin: 20px; padding: 20px; background: #f5f5f5;">';
        echo '<h3>Debug Data:</h3>';
        echo '<pre>';
        echo "Monthly Data:\n";
        print_r($monthlyData);
        echo "\nDaily Revenue Data:\n";
        print_r($dailyData);
        echo "\nDaily Orders Data:\n";
        print_r($dailyOrdersData);
        echo '</pre>';
        echo '</div>';
    }
    ?>

    <div class="chart-container">
        <h2>Doanh thu theo tháng</h2>
        <canvas id="monthlyChart"></canvas>
    </div>

    <div class="chart-container">
        <h2>Doanh thu trong ngày (7 ngày gần nhất)</h2>
        <canvas id="dailyRevenueChart"></canvas>
    </div>

    <div class="chart-container">
        <h2>Đơn hàng trong ngày (7 ngày gần nhất)</h2>
        <canvas id="dailyOrdersChart"></canvas>
    </div>

    <script>
    // Hàm format tiền tệ VND
    function formatVND(value) {
        return new Intl.NumberFormat('vi-VN', {
            style: 'currency',
            currency: 'VND'
        }).format(value);
    }

    // Hàm format ngày
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.getDate() + '/' + (date.getMonth() + 1);
    }

    // Biểu đồ doanh thu theo tháng
    var monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    var monthlyData = <?php echo json_encode($monthlyData); ?>;
    
    if (monthlyData && monthlyData.length > 0) {
        var monthlyLabels = monthlyData.map(item => formatDate(item.date));
        var monthlyValues = monthlyData.map(item => parseFloat(item.total));

        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Doanh thu',
                    data: monthlyValues,
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.2)',
                    borderWidth: 2,
                    pointBackgroundColor: '#4CAF50',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Doanh thu: ' + formatVND(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatVND(value);
                            }
                        }
                    }
                }
            }
        });
    } else {
        document.getElementById('monthlyChart').parentElement.innerHTML += '<p class="text-center">Không có dữ liệu doanh thu trong tháng này</p>';
    }

    // Biểu đồ doanh thu theo ngày
    var dailyRevenueCtx = document.getElementById('dailyRevenueChart').getContext('2d');
    var dailyData = <?php echo json_encode(array_reverse($dailyData)); ?>;
    
    if (dailyData && dailyData.length > 0) {
        var dailyLabels = dailyData.map(item => formatDate(item.date));
        var dailyValues = dailyData.map(item => parseFloat(item.total));

        new Chart(dailyRevenueCtx, {
            type: 'bar',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Doanh thu',
                    data: dailyValues,
                    backgroundColor: '#2196F3',
                    borderColor: '#2196F3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Doanh thu: ' + formatVND(context.raw);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatVND(value);
                            }
                        }
                    }
                }
            }
        });
    } else {
        document.getElementById('dailyRevenueChart').parentElement.innerHTML += '<p class="text-center">Không có dữ liệu doanh thu trong 7 ngày gần đây</p>';
    }

    // Biểu đồ số đơn hàng theo ngày
    var dailyOrdersCtx = document.getElementById('dailyOrdersChart').getContext('2d');
    var dailyOrdersData = <?php echo json_encode(array_reverse($dailyOrdersData)); ?>;
    
    if (dailyOrdersData && dailyOrdersData.length > 0) {
        var orderLabels = dailyOrdersData.map(item => formatDate(item.date));
        var orderValues = dailyOrdersData.map(item => parseInt(item.total));

        new Chart(dailyOrdersCtx, {
            type: 'bar',
            data: {
                labels: orderLabels,
                datasets: [{
                    label: 'Số đơn hàng',
                    data: orderValues,
                    backgroundColor: '#FF9800',
                    borderColor: '#FF9800',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    } else {
        document.getElementById('dailyOrdersChart').parentElement.innerHTML += '<p class="text-center">Không có dữ liệu đơn hàng trong 7 ngày gần đây</p>';
    }
    </script>
</body>
</html> 