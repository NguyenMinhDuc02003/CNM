<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Include class connect_db
require_once 'class/clsconnect.php';

// Khởi tạo đối tượng connect_db
$db = new connect_db();

if (!isset($_GET['page'])) {
    $page = 'about';
} else {
    $page = $_GET['page'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Về Chúng Tôi - Restoran</title>
</head>

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
                <h1 class="display-3 text-white mb-3 animated slideInDown">Về Chúng Tôi</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <li class="breadcrumb-item"><a href="#" class="text-warning">Trang</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Về Chúng Tôi</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!-- Navbar & Hero End -->

        <!-- About Start -->
        <div class="container-xxl py-5">
            <div class="container">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-6">
                        <div class="row g-3">
                            <div class="col-6 text-start">
                                <img class="img-fluid rounded w-100 wow zoomIn" data-wow-delay="0.1s" src="img/about-1.jpg" alt="Nhà hàng">
                            </div>
                            <div class="col-6 text-start">
                                <img class="img-fluid rounded w-75 wow zoomIn" data-wow-delay="0.3s" src="img/about-2.jpg" style="margin-top: 25%;" alt="Món ăn">
                            </div>
                            <div class="col-6 text-end">
                                <img class="img-fluid rounded w-75 wow zoomIn" data-wow-delay="0.5s" src="img/about-3.jpg" alt="Đầu bếp">
                            </div>
                            <div class="col-6 text-end">
                                <img class="img-fluid rounded w-100 wow zoomIn" data-wow-delay="0.7s" src="img/about-4.jpg" alt="Không gian">
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <h5 class="section-title ff-secondary text-start text-primary fw-normal">Về Chúng Tôi</h5>
                        <h1 class="mb-4">Chào Mừng Đến Với <i class="fa fa-utensils text-primary me-2"></i>Restoran</h1>
                        <p class="mb-4">Tại Restoran, chúng tôi không chỉ phục vụ những món ăn ngon mà còn mang đến trải nghiệm ẩm thực đẳng cấp. Với hơn 15 năm kinh nghiệm, chúng tôi tự hào là điểm đến lý tưởng cho những ai yêu thích ẩm thực.</p>
                        <p class="mb-4">Đội ngũ đầu bếp tài năng của chúng tôi luôn sáng tạo và tận tâm, mang đến những món ăn độc đáo được chế biến từ nguyên liệu tươi ngon nhất. Không gian sang trọng, ấm cúng cùng dịch vụ chuyên nghiệp sẽ mang đến cho quý khách những khoảnh khắc đáng nhớ.</p>
                        <div class="row g-4 mb-4">
                            <?php
                            try {
                                $sql = "SELECT COUNT(*) as total FROM nhanvien WHERE ChucVu = ?";
                                $result = $db->xuatdulieu_prepared($sql, ['Đầu bếp']);
                                $totalChefs = $result[0]['total'] ?? 0;
                                ?>
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center border-start border-5 border-primary px-3">
                                        <h1 class="flex-shrink-0 display-5 text-primary mb-0" data-toggle="counter-up">15</h1>
                                        <div class="ps-4">
                                            <p class="mb-0">Năm</p>
                                            <h6 class="text-uppercase mb-0">Kinh Nghiệm</h6>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center border-start border-5 border-primary px-3">
                                        <h1 class="flex-shrink-0 display-5 text-primary mb-0" data-toggle="counter-up"><?php echo $totalChefs; ?></h1>
                                        <div class="ps-4">
                                            <p class="mb-0">Đầu Bếp</p>
                                            <h6 class="text-uppercase mb-0">Chuyên Nghiệp</h6>
                                        </div>
                                    </div>
                                </div>
                                <?php
                            } catch (Exception $e) {
                                echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                            }
                            ?>
                        </div>
                        <a class="btn btn-primary py-3 px-5 mt-2" href="">Tìm Hiểu Thêm</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- About End -->

        <!-- Team Start -->
        <div class="container-xxl pt-5 pb-3">
            <div class="container">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Đội Ngũ</h5>
                    <h1 class="mb-5">Đầu Bếp Của Chúng Tôi</h1>
                </div>
                <div class="row g-4">
                    <?php
                    try {
                        $sql = "SELECT * FROM nhanvien WHERE ChucVu = ?";
                        $result = $db->xuatdulieu_prepared($sql, ['Đầu bếp']);
                        $delay = 0.1;
                        foreach ($result as $row) {
                            ?>
                            <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                                <div class="team-item text-center rounded overflow-hidden">
                                    <div class="rounded-circle overflow-hidden m-4">
                                        <img class="img-fluid" src="img/<?php echo htmlspecialchars($row['HinhAnh'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($row['HoTen']); ?>">
                                    </div>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($row['HoTen']); ?></h5>
                                    <small><?php echo htmlspecialchars($row['ChucVu']); ?></small>
                                    <div class="d-flex justify-content-center mt-3">
                                        <a class="btn btn-square btn-primary mx-1" href=""><i class="fab fa-facebook-f"></i></a>
                                        <a class="btn btn-square btn-primary mx-1" href=""><i class="fab fa-twitter"></i></a>
                                        <a class="btn btn-square btn-primary mx-1" href=""><i class="fab fa-instagram"></i></a>
                                    </div>
                                </div>
                            </div>
                            <?php
                            $delay += 0.2;
                        }
                    } catch (Exception $e) {
                        echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                    }
                    ?>
                </div>
            </div>
        </div>
        <!-- Team End -->
    </div>
</body>
</html>