<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Include class connect_db
require_once 'class/clsconnect.php';

// Khởi tạo đối tượng connect_db
$db = new connect_db();

if (!isset($_GET['page'])) {
    $page = 'testimonial';
} else {
    $page = $_GET['page'];
}

// Thêm dữ liệu mẫu nếu bảng phanhoi trống
$sql = "SELECT COUNT(*) as count FROM phanhoi";
$result = $db->xuatdulieu($sql);
$count = $result[0]['count'] ?? 0;

if ($count == 0) {
    $sample_data = [
        ['Nguyễn Văn A', 'vana@gmail.com', '0235467584', 'Dịch vụ', 'Nhà hàng có không gian đẹp, nhân viên phục vụ nhiệt tình. Món ăn ngon và giá cả hợp lý.'],
        ['Trần Thị B', 'tere@gmail.com', '0765849302', 'Món ăn', 'Các món ăn được chế biến cẩn thận, hương vị thơm ngon. Đặc biệt là món bò lúc lắc rất tuyệt vời.'],
        ['Lê Văn C', 'kh-c@gmail.com', '0684975432', 'Không gian', 'Không gian nhà hàng rộng rãi, thoáng mát. Phù hợp cho các buổi họp mặt gia đình và bạn bè.'],
        ['Phạm Thị D', 'phamd@gmail.com', '0285467839', 'Đánh giá chung', 'Nhà hàng có view đẹp, món ăn ngon, giá cả phải chăng. Sẽ quay lại vào lần sau.']
    ];

    foreach ($sample_data as $data) {
        $sql = "INSERT INTO phanhoi (HoTen, Email, SoDienThoai, ChuDe, NoiDung) VALUES (?, ?, ?, ?, ?)";
        $db->tuychinh($sql, $data);
    }
}

// Lấy dữ liệu phản hồi
$sql = "SELECT * FROM phanhoi ORDER BY NgayGui DESC";
$result = $db->xuatdulieu($sql);
?>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Navbar & Hero Start -->
        <div class="container-xxl py-5 bg-dark hero-header mb-5">
            <div class="container text-center my-5 pt-5 pb-4">
                <h1 class="display-3 text-white mb-3 animated slideInDown">Đánh Giá</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <li class="breadcrumb-item"><a href="#" class="text-warning">Trang</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Đánh Giá</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Navbar & Hero End -->

        <!-- Testimonial Start -->
        <div class="container-xxl py-5 wow fadeInUp" data-wow-delay="0.1s">
            <div class="container">
                <div class="text-center">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Phản Hồi</h5>
                    <h1 class="mb-5">Khách Hàng Nói Gì Về Chúng Tôi?</h1>
                </div>
                <div class="owl-carousel testimonial-carousel">
                    <?php foreach ($result as $row) { ?>
                    <div class="testimonial-item bg-transparent border rounded p-4">
                        <i class="fa fa-quote-left fa-2x text-primary mb-3"></i>
                        <p><?php echo htmlspecialchars($row['NoiDung']); ?></p>
                        <div class="d-flex align-items-center">
                            <img class="img-fluid flex-shrink-0 rounded-circle" src="img/testimonial-<?php echo rand(1,4); ?>.jpg" style="width: 50px; height: 50px;">
                            <div class="ps-3">
                                <h5 class="mb-1"><?php echo htmlspecialchars($row['HoTen']); ?></h5>
                                <small><?php echo htmlspecialchars($row['ChuDe']); ?></small>
                            </div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <!-- Testimonial End -->
    </div>
</body>
