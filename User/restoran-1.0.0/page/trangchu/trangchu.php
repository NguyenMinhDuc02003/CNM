<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'class/clsconnect.php';
$db = new connect_db();
if (!isset($_GET['page'])) {
    $page = 'trangchu';
} else {
    $page = $_GET['page'];
}
?>
<body>
    <div class="container-xxl bg-white p-0">
        <div id="spinner"
            class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Đang tải...</span>
            </div>
        </div>
        <div class="container-xxl py-5 bg-dark hero-header mb-5">
            <div class="container my-5 py-5">
                <div class="row align-items-center g-5">
                    <div class="col-lg-6 text-center text-lg-start">
                        <h1 class="display-3 text-white animated slideInLeft">Thưởng Thức Bữa Ăn Tuyệt Vời<br>Cùng Chúng
                            Tôi</h1>
                        <p class="text-white animated slideInLeft mb-4 pb-2"> Từ những món nướng đậm đà đến các món ăn
                            truyền thống tinh tế, nhà hàng của chúng tôi mang đến trải nghiệm ẩm thực khó quên.
                            Được chế biến từ nguyên liệu tươi ngon nhất, mỗi món ăn là một hành trình vị giác – đậm đà,
                            chuẩn vị, đầy cảm xúc.</p>
                        <button type="button" class="btn btn-primary py-sm-3 px-sm-5 me-3 animated slideInLeft" data-bs-toggle="modal" data-bs-target="#reservationModal">ĐẶT BÀN</button>
                    </div>
                    <div class="col-lg-6 text-center text-lg-end overflow-hidden">
                        <img class="img-fluid" src="img/hero.png" alt="">
                    </div>
                </div>
            </div>
        </div>
        <div class="container-xxl py-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-user-tie text-primary mb-4"></i>
                                <h5>Đầu Bếp Chuyên Nghiệp</h5>
                                <p>Đội ngũ đầu bếp giàu kinh nghiệm, tận tâm với nghề</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-utensils text-primary mb-4"></i>
                                <h5>Thực Phẩm Chất Lượng</h5>
                                <p>Nguyên liệu tươi ngon, đảm bảo vệ sinh an toàn thực phẩm</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.5s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-cart-plus text-primary mb-4"></i>
                                <h5>Đặt Hàng Online</h5>
                                <p>Đặt món trực tuyến, giao hàng nhanh chóng</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.7s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-headset text-primary mb-4"></i>
                                <h5>Phục Vụ 24/7</h5>
                                <p>Hỗ trợ khách hàng mọi lúc, mọi nơi, tận tình nhanh chóng </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                            $sql = "SELECT COUNT(*) as total FROM nhanvien WHERE ChucVu = 'Đầu bếp'";
                            $result = $db->xuatdulieu($sql);
                            $totalChefs = $result[0]['total'];
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
                        </div>
                        <a class="btn btn-primary py-3 px-5 mt-2" href="index.php?page=menu">Khám Phá Thực Đơn</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="container-xxl py-5">
            <div class="container pb-5">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Thực Đơn</h5>
                    <h1 class="mb-5">Món Ăn Nổi Bật</h1>
                    <hr>
                </div>

                <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                    <div class="tab-content">
                        <div id="tab-1" class="tab-pane fade show p-0 active">
                            <div class="row g-4">
                                <?php
                                $sql = "SELECT m.*, COUNT(ct.idmonan) as total_ordered 
                                        FROM monan m
                                        JOIN chitiethoadon ct ON m.idmonan = ct.idmonan
                                        JOIN hoadon h ON ct.idHD = h.idHD
                                        GROUP BY m.idmonan
                                        ORDER BY total_ordered DESC
                                        LIMIT 6";
                                $result = $db->xuatdulieu($sql);
                                if (!empty($result)) {
                                    echo "<div class='row'>";
                                    foreach ($result as $row) {
                                        echo "
                                            <div class='col-md-6 mt-5'>
                                                <div class='d-flex align-items-center mb-4'>
                                                    <img class='flex-shrink-0 img-fluid rounded' src='img/{$row['hinhanh']}' 
                                                        style='width: 150px; object-fit: cover;'>
                                                    <div class='w-100 d-flex flex-column text-start ps-4'>
                                                        <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                            <span>{$row['tenmonan']}</span>
                                                            <span class='text-primary'>" . number_format($row['DonGia'], 0, ',', '.') . "đ</span>
                                                        </h5>
                                                        <small class='fst-italic'>{$row['mota']}</small>
                                                    </div>
                                                </div>
                                            </div>
                                            ";
                                    }
                                    echo "</div>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container-xxl py-5 wow fadeInUp" data-wow-delay="0.1s">
            <div class="container">
                <div class="text-center">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Phản Hồi</h5>
                    <h1 class="mb-5">Khách Hàng Nói Gì Về Chúng Tôi?</h1>
                </div>
                <div class="owl-carousel testimonial-carousel">
                    <?php
                    $sql = "SELECT * FROM phanhoi ORDER BY NgayGui DESC LIMIT 4";
                    $result = $db->xuatdulieu($sql);
                    foreach ($result as $row) {
                        ?>
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
                        <?php
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="container-xxl pt-5 pb-3">
            <div class="container">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Đội Ngũ</h5>
                    <h1 class="mb-5">Đầu Bếp Của Chúng Tôi</h1>
                </div>
                <div class="row g-4">
                    <?php
                    $sql = "SELECT * FROM nhanvien WHERE ChucVu = 'Đầu bếp'";
                    $result = $db->xuatdulieu($sql);
                    foreach ($result as $row) {
                        ?>
                        <div class="col-lg-3 col-md-6 wow fadeInUp" data-wow-delay="0.1s">
                            <div class="team-item text-center rounded overflow-hidden">
                                <div class="rounded-circle overflow-hidden m-4">
                                    <img class="img-fluid" src="img/<?php echo htmlspecialchars($row['HinhAnh']); ?>" alt="">
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
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
     
</body>
