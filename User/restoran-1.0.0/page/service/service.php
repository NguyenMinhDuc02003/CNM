<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

if (!isset($_GET['page'])) {
    $page ='service';
} else {
    $page = $_GET['page'];
}
?>


<body>
    <div class="container-xxl bg-white p-0">
        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Đang tải...</span>
            </div>
        </div>
        <!-- Spinner End -->


        <!-- Navbar & Hero Start -->
       

            <div class="container-xxl py-5 bg-dark hero-header mb-5">
                <div class="container text-center my-5 pt-5 pb-4">
                    <h1 class="display-3 text-white mb-3 animated slideInDown">Dịch Vụ</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center text-uppercase">
                            <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                            <li class="breadcrumb-item"><a href="#" class="text-warning">Trang</a></li>
                            <li class="breadcrumb-item text-white active" aria-current="page">Dịch Vụ</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
        <!-- Navbar & Hero End -->


        <!-- Service Start -->
        <div class="container-xxl py-5">
            <div class="container">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-warning fw-normal">Dịch Vụ Của Chúng Tôi</h5>
                    <h1 class="mb-5">Khám Phá Dịch Vụ</h1>
                </div>
                <div class="row g-4">
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-calendar-check text-warning mb-4"></i>
                                <h5>Đặt Bàn Trực Tuyến</h5>
                                <p>Đặt bàn nhanh chóng, dễ dàng qua website hoặc ứng dụng di động</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-birthday-cake text-warning mb-4"></i>
                                <h5>Tổ Chức Sự Kiện</h5>
                                <p>Dịch vụ tổ chức tiệc sinh nhật, họp mặt gia đình, doanh nghiệp</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.5s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-truck text-warning mb-4"></i>
                                <h5>Giao Hàng Tận Nơi</h5>
                                <p>Giao món ăn nhanh chóng trong phạm vi 5km với phí ship hợp lý</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.7s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-gift text-warning mb-4"></i>
                                <h5>Chương Trình Khuyến Mãi</h5>
                                <p>Nhiều ưu đãi hấp dẫn cho khách hàng thân thiết và sự kiện đặc biệt</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.1s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-users text-warning mb-4"></i>
                                <h5>Phục Vụ Nhóm</h5>
                                <p>Dịch vụ phục vụ riêng cho các nhóm lớn với menu đặc biệt</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-utensils text-warning mb-4"></i>
                                <h5>Menu Theo Yêu Cầu</h5>
                                <p>Thực đơn được thiết kế riêng theo yêu cầu và sở thích của khách hàng</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.5s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-glass-cheers text-warning mb-4"></i>
                                <h5>Dịch Vụ Bar</h5>
                                <p>Phục vụ các loại rượu, cocktail và đồ uống đặc biệt</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-sm-6 wow fadeInUp" data-wow-delay="0.7s">
                        <div class="service-item rounded pt-3">
                            <div class="p-4">
                                <i class="fa fa-3x fa-music text-warning mb-4"></i>
                                <h5>Giải Trí</h5>
                                <p>Âm nhạc trực tiếp và các chương trình giải trí đặc sắc</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Service End -->
        
</body>

