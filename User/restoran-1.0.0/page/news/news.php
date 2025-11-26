<?php
// Thiết lập mã hóa UTF-8 cho nội dung trang
header('Content-Type: text/html; charset=utf-8');

require_once 'class/clsconnect.php';

$db = new connect_db();

$newsItems = [
    [
        'title' => 'Khai trương không gian Rooftop Lounge',
        'date' => '2025-09-18',
        'category' => 'Sự kiện đặc biệt',
        'summary' => 'Thưởng thức ẩm thực cao cấp bên cạnh quầy bar ngoài trời mới, với tầm nhìn toàn cảnh thành phố và bộ sưu tập cocktail theo mùa.',
        'image' => 'img/menu_5.jpg'
    ],
    [
        'title' => 'Ra mắt thực đơn Thu Đông 2025',
        'date' => '2025-09-01',
        'category' => 'Ẩm thực',
        'summary' => 'Không gian ấm áp cùng các món ăn lấy cảm hứng từ hương vị châu Âu, kết hợp nguyên liệu bản địa như vịt quay sốt cam, salad bốn mùa và panna cotta bơ.',
        'image' => 'img/vitquay.jpg'
    ],
    [
        'title' => 'Workshop “Chef\'s Table” cùng bếp trưởng',
        'date' => '2025-08-24',
        'category' => 'Trải nghiệm',
        'summary' => 'Buổi workshop giới hạn 20 khách, nơi bếp trưởng Minh Vũ chia sẻ bí quyết chế biến món tráng miệng chuẩn fine-dining và kỹ thuật trình bày ấn tượng.',
        'image' => 'img/pannacotta_bơ.jpg'
    ],
];

$highlights = [
    [
        'time' => '19/09',
        'title' => 'Đêm nhạc Jazz “Autumn Whisper”',
        'description' => 'Thưởng thức ban nhạc jazz sống kết hợp thực đơn pairing 5 món do bếp trưởng lựa chọn.'
    ],
    [
        'time' => '25/09',
        'title' => 'Ưu đãi khách đoàn',
        'description' => 'Giảm 15% cho nhóm từ 8 khách, tặng kèm set bánh ngọt theo mùa.'
    ],
    [
        'time' => '01/10',
        'title' => 'Ra mắt ứng dụng đặt bàn',
        'description' => 'Đặt bàn nhanh chóng, quản lý lịch sử và nhận thông báo ưu đãi trực tiếp trên điện thoại.'
    ],
];

function formatDateDisplay(string $date): string
{
    try {
        $dt = new DateTime($date);
        return $dt->format('d/m/Y');
    } catch (Exception $e) {
        return $date;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tin tức &amp; Sự kiện - Restoran</title>
</head>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Hero Start -->
        <div class="container-xxl py-5 bg-dark hero-header mb-5">
            <div class="container text-center my-5 pt-5 pb-4">
                <h1 class="display-3 text-white mb-3 animated slideInDown">Tin tức &amp; Sự kiện</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="#" class="text-warning">Khám phá</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Tin tức</li>
                    </ol>
                </nav>
                <p class="text-white-50 mx-auto w-75">Cập nhật những câu chuyện, bí quyết ẩm thực và sự kiện nổi bật tại Restoran để không bỏ lỡ những trải nghiệm mới nhất dành cho bạn.</p>
            </div>
        </div>
        <!-- Hero End -->

        <!-- News List Start -->
        <div class="container-xxl py-5">
            <div class="container">
                <div class="row g-5">
                    <div class="col-lg-8 wow fadeInUp" data-wow-delay="0.1s">
                        <h5 class="section-title ff-secondary text-start text-primary fw-normal">Bài viết mới</h5>
                        <h2 class="mb-4">Đồng hành cùng hành trình trải nghiệm ẩm thực</h2>

                        <?php foreach ($newsItems as $index => $item): ?>
                            <article class="card border-0 shadow-sm overflow-hidden mb-4">
                                <div class="row g-0">
                                    <div class="col-md-5">
                                        <div class="h-100 bg-cover" style="background-image: url('<?php echo htmlspecialchars($item['image']); ?>'); min-height: 220px;"></div>
                                    </div>
                                    <div class="col-md-7">
                                        <div class="card-body d-flex flex-column h-100">
                                            <div class="d-flex align-items-center text-muted mb-2">
                                                <span class="badge bg-primary me-2"><?php echo htmlspecialchars($item['category']); ?></span>
                                                <small><i class="far fa-calendar-alt me-1"></i><?php echo formatDateDisplay($item['date']); ?></small>
                                            </div>
                                            <h3 class="card-title h4 text-dark"><?php echo htmlspecialchars($item['title']); ?></h3>
                                            <p class="card-text flex-grow-1"><?php echo htmlspecialchars($item['summary']); ?></p>
                                            <a href="#" class="text-primary mt-2 fw-semibold">
                                                Đọc thêm<i class="fas fa-arrow-right ms-2"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="col-lg-4 wow fadeInUp" data-wow-delay="0.3s">
                        <div class="bg-light rounded p-4 mb-4">
                            <h5 class="ff-secondary text-primary">Điểm nhấn trong tháng</h5>
                            <ul class="list-unstyled mb-0 mt-3">
                                <?php foreach ($highlights as $highlight): ?>
                                    <li class="d-flex mb-3">
                                        <div class="flex-shrink-0 text-center bg-primary text-white rounded-3 py-2 px-3 me-3">
                                            <strong><?php echo htmlspecialchars($highlight['time']); ?></strong>
                                        </div>
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($highlight['title']); ?></h6>
                                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($highlight['description']); ?></p>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="bg-dark text-white rounded p-4">
                            <h5 class="ff-secondary text-warning">Nhận bản tin</h5>
                            <p class="text-white-50 mb-3">Đăng ký email để nhận ưu đãi độc quyền, thực đơn mới và thông tin sự kiện sớm nhất.</p>
                            <form class="newsletter-form">
                                <div class="mb-3">
                                    <label for="newsletterEmail" class="form-label">Email của bạn</label>
                                    <input type="email" id="newsletterEmail" class="form-control" placeholder="name@example.com" required>
                                </div>
                                <button type="submit" class="btn btn-warning w-100 text-dark fw-semibold">Đăng ký ngay</button>
                            </form>
                            <small class="d-block text-white-50 mt-3">Chúng tôi tôn trọng quyền riêng tư và chỉ gửi nội dung hữu ích.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- News List End -->

        <!-- Video Feature Start -->
        <div class="container-xxl py-5 bg-light">
            <div class="container">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-6 wow fadeInUp" data-wow-delay="0.1s">
                        <h5 class="section-title ff-secondary text-start text-primary fw-normal">Behind the Scenes</h5>
                        <h2 class="mb-4">Hậu trường tạo nên trải nghiệm Restoran</h2>
                        <p class="mb-4 text-muted">Theo chân đội ngũ Restoran trong một ngày bận rộn: từ việc chọn lựa nguyên liệu vào buổi sáng, chuẩn bị không gian đến khoảnh khắc phục vụ những món ăn tâm huyết cho khách.</p>
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check text-primary me-2"></i>Gặp gỡ đội ngũ bếp trưởng đầy sáng tạo</li>
                            <li class="mb-2"><i class="fas fa-check text-primary me-2"></i>Khám phá quy trình chọn nguyên liệu tươi</li>
                            <li class="mb-2"><i class="fas fa-check text-primary me-2"></i>Câu chuyện phía sau món đặc trưng của mùa</li>
                        </ul>
                        <a class="btn btn-primary py-3 px-5 mt-3" href="index.php?page=contact">Đặt bàn ngay</a>
                    </div>
                    <div class="col-lg-6 wow fadeInRight" data-wow-delay="0.3s">
                        <div class="position-relative rounded overflow-hidden shadow-lg">
                            <img class="img-fluid w-100" src="img/video.jpg" alt="Video giới thiệu Restoran">
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <a class="btn btn-lg btn-primary btn-lg-square" href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" target="_blank" rel="noopener">
                                    <i class="fa fa-play text-white"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Video Feature End -->
    </div>
</body>

</html>
