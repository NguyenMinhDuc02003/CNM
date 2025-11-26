<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session
if (!isset($_SESSION['debug'])) {
    $_SESSION['debug'] = [];
}
$_SESSION['debug'][] = [
    'time' => date('Y-m-d H:i:s'),
    'page' => 'choose_order_type.php',
    'session_booking' => isset($_SESSION['booking']) ? $_SESSION['booking'] : 'not_set',
    'session_maban' => isset($_POST['maban']) ? $_POST['maban'] : 'not_set',
    'request' => $_SERVER['REQUEST_METHOD'],
    'post' => $_POST
];

// Kiểm tra session và POST
if (!isset($_SESSION['booking']) || !isset($_POST['maban'])) {
    $_SESSION['error'] = 'Thông tin đặt bàn không tồn tại. Vui lòng thử lại.';
    header('Location: index.php?page=trangchu');
    exit;
}

// Lấy thông tin từ session và POST
$booking = $_SESSION['booking'];
$tables = json_decode($_POST['maban'], true);

// Kiểm tra dữ liệu JSON
if (!is_array($tables) || empty($tables)) {
    $_SESSION['error'] = 'Danh sách bàn không hợp lệ. Vui lòng chọn lại.';
    header('Location: index.php?page=booking');
    exit;
}

// Kiểm tra cấu trúc của mỗi bàn
foreach ($tables as $table) {
    if (!isset($table['maban']) || !isset($table['soban']) || !isset($table['phuthu'])) {
        $_SESSION['error'] = 'Dữ liệu bàn không đầy đủ. Vui lòng chọn lại.';
        header('Location: index.php?page=booking');
        exit;
    }
}

// Lưu danh sách bàn vào session để sử dụng ở các trang tiếp theo
$_SESSION['selected_tables'] = $tables;

// Nếu session booking chưa có people_count hoặc nó <= 0, derive từ mảng bàn vừa POST
if (!isset($_SESSION['booking']['people_count']) || (int)$_SESSION['booking']['people_count'] <= 0) {
    $derived = 0;
    foreach ($tables as $t) {
        if (isset($t['capacity'])) $derived += (int)$t['capacity'];
        elseif (isset($t['soluongKH'])) $derived += (int)$t['soluongKH'];
    }
    if ($derived > 0) {
        $_SESSION['booking']['people_count'] = $derived;
        $_SESSION['debug'][] = ['time' => date('Y-m-d H:i:s'), 'page' => 'choose_order_type', 'action' => 'derived_people_count', 'derived' => $derived];
    }
}

// Refresh local $booking variable from session in case we just set people_count above
if (isset($_SESSION['booking']) && is_array($_SESSION['booking'])) {
    $booking = $_SESSION['booking'];
} else {
    // Defensive fallback
    $booking = [
        'khuvuc' => $_SESSION['booking']['khuvuc'] ?? null,
        'datetime' => $_SESSION['booking']['datetime'] ?? null,
        'people_count' => $_SESSION['booking']['people_count'] ?? 0
    ];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn Loại Đặt Món - Restoran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
       
    </style>
</head>
<body>
    <div class="main-container">
        <div class="choice-card">
            <h1 class="choice-title">
                <i class="fas fa-utensils me-3"></i>Chọn Cách Đặt Món
            </h1>
            <p class="choice-subtitle">Lựa chọn phương thức đặt món phù hợp với bạn</p>
            
            <!-- Thông tin đặt bàn -->
            <div class="booking-info">
                <h6><i class="fas fa-info-circle me-2"></i>Thông Tin Đặt Bàn</h6>
                <div class="booking-details">
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo $booking['people_count']; ?> người</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('d/m/Y H:i', strtotime($booking['datetime'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-chair"></i>
                        <span><?php echo count($tables); ?> bàn được chọn</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Phụ phí: <?php echo number_format(array_sum(array_column($tables, 'phuthu'))); ?>đ</span>
                    </div>
                </div>
            </div>

            <form method="POST" id="orderTypeForm">
                <input type="hidden" name="maban" value="<?php echo htmlspecialchars(json_encode($tables)); ?>">
                
                <div class="options-container">
                    <!-- Chọn theo món -->
                    <button type="submit" formaction="index.php?page=book_menu" class="option-card">
                        <i class="fas fa-list-ul option-icon"></i>
                        <div class="option-title">Chọn Theo Món</div>
                        <div class="option-description">
                            Tự do lựa chọn từng món ăn theo sở thích của bạn. 
                            Linh hoạt điều chỉnh số lượng và loại món.
                        </div>
                    </button>

                    <!-- Chọn theo thực đơn -->
                    <button type="submit" formaction="index.php?page=book_thucdon" class="option-card">
                        <i class="fas fa-book option-icon"></i>
                        <div class="option-title">Chọn Theo Thực Đơn</div>
                        <div class="option-description">
                            Chọn từ các combo thực đơn được thiết kế sẵn. 
                            Tiết kiệm thời gian và đảm bảo sự hài hòa.
                        </div>
                    </button>
                </div>
                
                <div class="mt-4">
                    <!-- Post back to booking with only khuvuc and datetime (do not send people_count)
                         Booking page will derive people_count from selected tables. -->
                    <button type="button" id="backToBookingBtn" class="back-button">
                        <i class="fas fa-arrow-left me-2"></i>Quay Lại Chọn Bàn
                    </button>
                    <script>
                        document.getElementById('backToBookingBtn').addEventListener('click', function() {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = 'index.php?page=booking';

                            var inputKhu = document.createElement('input');
                            inputKhu.type = 'hidden';
                            inputKhu.name = 'khuvuc';
                            inputKhu.value = '<?php echo htmlspecialchars($booking['khuvuc'], ENT_QUOTES); ?>';
                            form.appendChild(inputKhu);

                            var inputDt = document.createElement('input');
                            inputDt.type = 'hidden';
                            inputDt.name = 'datetime';
                            inputDt.value = '<?php echo htmlspecialchars($booking['datetime'], ENT_QUOTES); ?>';
                            form.appendChild(inputDt);

                            document.body.appendChild(form);
                            form.submit();
                        });
                    </script>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</body>
</html>
