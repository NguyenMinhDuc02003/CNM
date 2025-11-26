<?php
require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsdatban.php';

$db = new connect_db();
$datban = new datban($db);

// Kiểm tra madatban
if (!isset($_GET['madatban']) || empty($_GET['madatban'])) {
    header('Location: index.php?page=profile');
    exit;
}

$madatban = (int)$_GET['madatban'];

// Lấy thông tin đặt bàn
$booking_info = $db->xuatdulieu_prepared("SELECT * FROM datban WHERE madatban = ?", [$madatban]);
if (empty($booking_info)) {
    $_SESSION['error'] = 'Không tìm thấy thông tin đặt bàn.';
    header('Location: index.php?page=profile');
    exit;
}
$booking_info = $booking_info[0];
$status = $booking_info['TrangThai'] ?? '';
if ($status !== 'pending') {
    $_SESSION['error'] = 'Chỉ có thể chỉnh sửa món khi đơn hàng đang chờ xác nhận.';
    header('Location: index.php?page=profile#bookings');
    exit;
}

// Lấy món đã chọn từ DB
$selectedMonAn = [];
$selectedThucDon = [];

// Lấy món lẻ đã chọn
$monan_data = $db->xuatdulieu_prepared(
    "SELECT m.idmonan as mamon, m.tenmonan as tenmon, m.DonGia as gia, cma.SoLuong as soluong 
     FROM chitietdatban cma 
     JOIN monan m ON cma.idmonan = m.idmonan 
     WHERE cma.madatban = ?",
    [$madatban]
);

foreach ($monan_data as $item) {
    $selectedMonAn[] = [
        'mamon' => $item['mamon'],
        'tenmon' => $item['tenmon'],
        'gia' => (int)$item['gia'],
        'soluong' => (int)$item['soluong']
    ];
}

// Lấy thực đơn đã chọn (tạm thời để trống vì chưa có bảng thực đơn trong schema)
$thucdon_data = [];

foreach ($thucdon_data as $item) {
    $selectedThucDon[] = [
        'mathucdon' => $item['mathucdon'],
        'tenthucdon' => $item['tenthucdon'],
        'gia' => (int)$item['gia'],
        'soluong' => (int)$item['soluong']
    ];
}

// Xác định loại món hiện tại
$current_type = 'none';
if (!empty($selectedMonAn)) {
    $current_type = 'items';
} elseif (!empty($selectedThucDon)) {
    $current_type = 'sets';
}
?>


<body>
    <div class="main-container">
        <div class="choice-card">
            <h1 class="choice-title">
                <i class="fas fa-edit me-3"></i>Sửa Món Ăn
            </h1>
            <p class="choice-subtitle">Chọn loại món ăn để chỉnh sửa</p>
            
            <!-- Thông tin đặt bàn -->
            <div class="booking-info">
                <h6><i class="fas fa-info-circle me-2"></i>Thông Tin Đặt Bàn</h6>
                <div class="booking-details">
                    <div class="detail-item">
                        <i class="fas fa-hashtag"></i>
                        <span>Đặt bàn #<?php echo $madatban; ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-users"></i>
                        <span><?php echo $booking_info['SoLuongKhach']; ?> người</span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date('d/m/Y H:i', strtotime($booking_info['NgayDatBan'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Tổng tiền: <?php echo number_format($booking_info['TongTien']); ?>đ</span>
                    </div>
                </div>
            </div>

            <!-- Thông tin món hiện tại -->
            <?php if ($current_type !== 'none'): ?>
            <div class="current-selection">
                <h6><i class="fas fa-shopping-cart me-2"></i>Món ăn hiện tại</h6>
                <div class="current-details">
                    <?php if ($current_type === 'items'): ?>
                        <div class="detail-item">
                            <i class="fas fa-list-ul"></i>
                            <span>Loại: Chọn từng món lẻ</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-utensils"></i>
                            <span>Số món: <?php echo count($selectedMonAn); ?> món</span>
                        </div>
                    <?php else: ?>
                        <div class="detail-item">
                            <i class="fas fa-book"></i>
                            <span>Loại: Thực đơn có sẵn</span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clipboard-list"></i>
                            <span>Số thực đơn: <?php echo count($selectedThucDon); ?> thực đơn</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="options-container">
                <!-- Chọn từng món -->
                <button type="button" onclick="chooseAndProceed('items')" class="option-card" id="card-items">
                    <i class="fas fa-list-ul option-icon"></i>
                    <div class="option-title">Chọn Từng Món</div>
                    <div class="option-description">
                        Tự do lựa chọn các món ăn riêng lẻ theo sở thích của bạn. 
                        Linh hoạt điều chỉnh số lượng và loại món.
                    </div>
                </button>

                <!-- Chọn thực đơn -->
                <button type="button" onclick="chooseAndProceed('sets')" class="option-card" id="card-sets">
                    <i class="fas fa-book option-icon"></i>
                    <div class="option-title">Chọn Thực Đơn</div>
                    <div class="option-description">
                        Chọn từ các thực đơn được thiết kế sẵn. 
                        Tiết kiệm thời gian và đảm bảo sự hài hòa.
                    </div>
                </button>
            </div>
            
            <div class="mt-4">
                <a href="index.php?page=profile" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Quay Lại Profile
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script>
        let selectedType = '<?php echo $current_type; ?>';
        
        // Đánh dấu loại hiện tại
        if (selectedType !== 'none') {
            document.getElementById('card-' + selectedType).classList.add('selected');
        }

        function selectMenuType(type) {
            // Bỏ chọn tất cả (used when you want to select without immediate redirect)
            document.querySelectorAll('.option-card').forEach(card => {
                card.classList.remove('selected');
            });
            document.getElementById('card-' + type).classList.add('selected');
            selectedType = type;
        }

        // New helper: choose and immediately navigate to the edit page
        function chooseAndProceed(type) {
            // For visual feedback, mark selected
            selectMenuType(type);
            // Short delay for UX so user sees selection highlight, then redirect
            setTimeout(() => {
                if (type === 'items') {
                    window.location.href = 'index.php?page=edit_menu_items&madatban=<?php echo $madatban; ?>';
                } else if (type === 'sets') {
                    window.location.href = 'index.php?page=edit_menu_sets&madatban=<?php echo $madatban; ?>';
                }
            }, 150);
        }
    </script>
</body>
</html>
