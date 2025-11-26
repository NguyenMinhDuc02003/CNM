<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: application/json; charset=utf-8');
session_start();

// Kết nối CSDL
$conn = mysqli_connect('localhost', 'hceeab2b55_chung9atm', 'Chung2002!', 'hceeab2b55_restaurant');
if (!$conn) {
    die(json_encode(['error' => 'Kết nối thất bại: ' . mysqli_connect_error()]));
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

// Trả về dữ liệu dưới dạng JSON
echo json_encode([
    'monthlyData' => $monthlyData,
    'dailyData' => $dailyData,
    'dailyOrdersData' => $dailyOrdersData
]); 