<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Đặt múi giờ Việt Nam

require_once(__DIR__ . '/../class/clsconnect.php');

$db = new connect_db();
$currentPage = $_GET['page'] ?? 'trangchu';
$activeMenuCategory = isset($_GET['category']) ? (int)$_GET['category'] : null;

$menuCategories = [];
try {
    $menuCategories = $db->xuatdulieu("SELECT iddm, tendanhmuc FROM danhmuc ORDER BY tendanhmuc");
} catch (Exception $e) {
    $menuCategories = [];
}

$bookingCount = 0;
if (isset($_SESSION['khachhang_id'])) {
    try {
        $sql = "SELECT COUNT(*) as count FROM datban WHERE idKH = ?";
        $result = $db->xuatdulieu_prepared($sql, [$_SESSION['khachhang_id']]);
        $bookingCount = (int)($result[0]['count'] ?? 0);
    } catch (Exception $e) {
        $bookingCount = 0;
    }
}
?>
<div class="container-xxl position-relative p-0">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4  py-3 py-lg-0">
        <a href="" class="navbar-brand p-0">
            <h1 class="text-warning m-0"><i class="fa fa-utensils me-3"></i>Restoran</h1>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
            <span class="fa fa-bars"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarCollapse">
            <!-- Menu chính ở giữa -->
            <div class="navbar-nav mx-auto py-0">
                <a href="index.php?page=trangchu" class="nav-item nav-link active">Trang chủ</a>
                <a href="index.php?page=about" class="nav-item nav-link">Về chúng tôi</a>
                <a href="index.php?page=service" class="nav-item nav-link">Dịch vụ</a>
                <div class="nav-item dropdown">
                    <a href="index.php?page=menu" class="nav-link dropdown-toggle<?php echo $currentPage === 'menu' ? ' active' : ''; ?>" data-bs-toggle="dropdown">Menu</a>
                    <div class="dropdown-menu m-0">
                        <a href="index.php?page=menu" class="dropdown-item<?php echo ($currentPage === 'menu' && $activeMenuCategory === null) ? ' active' : ''; ?>">Tất cả món</a>
                        <?php foreach ($menuCategories as $category): ?>
                            <?php
                                $categoryId = (int)$category['iddm'];
                                $isActiveCategory = $currentPage === 'menu' && $activeMenuCategory === $categoryId;
                            ?>
                            <a href="index.php?page=menu&category=<?php echo $categoryId; ?>" class="dropdown-item<?php echo $isActiveCategory ? ' active' : ''; ?>">
                                <?php echo htmlspecialchars($category['tendanhmuc']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="index.php?page=news" class="nav-item nav-link">Tin tức</a>
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Trang</a>
                    <div class="dropdown-menu m-0">
                        <a href="index.php?page=team" class="dropdown-item">Sơ đồ bàn</a>
                        <a href="index.php?page=testimonial" class="dropdown-item">Đánh Giá</a>
                    </div>
                </div>
                <a href="index.php?page=contact" class="nav-item nav-link">Liên Hệ</a>
            </div>

            <!-- Menu bên phải -->
            <div class="navbar-nav ms-auto py-0 pe-4">
                <?php if(isset($_SESSION['khachhang_id'])): ?>
                    <!-- Icon đơn hàng - Chỉ hiện khi đã đăng nhập -->
                    <div class="nav-item">
                        <a href="index.php?page=profile#bookings" class="nav-link position-relative">
                            <i class="fas fa-history"></i>
                            <span class="position-absolute  start-100 translate-middle badge rounded-pill bg-danger">
                                <?php echo $bookingCount; ?>
                            </span>
                        </a>
                    </div>

                    <!-- Icon tài khoản với dropdown - Đã đăng nhập -->
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle  me-2"></i>
                            <span>Xin chào, <?php echo $_SESSION['khachhang_name']; ?></span>
                        </a>
                        <div class="dropdown-menu m-0">
                            <a href="index.php?page=profile" class="dropdown-item">
                                <i class="fas fa-user me-2"></i>Thông tin cá nhân
                            </a>
                            <a href="index.php?page=profile#bookings" class="dropdown-item">
                                <i class="fas fa-history me-2"></i>Lịch sử đặt bàn
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item" id="logoutBtn">
                                <i class="fas fa-sign-out-alt me-2"></i>Đăng xuất
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Menu cho khách chưa đăng nhập -->
                    <div class="navbar-nav">
                        <a href="index.php?page=login" class="nav-link">
                            <i class="fas fa-sign-in-alt me-2"></i>Đăng nhập
                        </a>
                        <a href="index.php?page=register" class="nav-link">
                            <i class="fas fa-user-plus me-2"></i>Đăng ký
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Script xử lý đăng xuất -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const logoutBtn = document.getElementById('logoutBtn');
                    if(logoutBtn) {
                        logoutBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if(confirm('Bạn có chắc muốn đăng xuất?')) {
                                window.location.href = 'index.php?page=logout';
                            }
                        });
                    }
                });
            </script>            <!-- Modal Đặt bàn -->
            <div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true" data-bs-backdrop="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title text-white" id="reservationModalLabel">
                                <i class="fas fa-calendar-alt me-2 "></i>Đặt bàn
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <?php
                            require_once 'class/clskvban.php';
                            $kvb = new KhuVucBan();
                            ?>
                            <form method="POST" action="index.php?page=booking" id="reservationForm">
                                <div class="row g-4">
                                    <!-- Số người: removed - will derive from selected table capacities in booking -->

                                    <!-- Ngày và Giờ -->
                                    <div class="col-12">
                                        <div class="row g-3">
                                            <!-- Chọn ngày -->
                                            <div class="col-md-6">
                                                <div class="form-floating">
                                                    <input type="date" class="form-control" id="bookingDate" name="bookingDate" required
                                                           min="<?php echo date('Y-m-d'); ?>">
                                                    <label for="bookingDate">
                                                        <i class="far fa-calendar me-2"></i>Chọn ngày
                                                    </label>
                                                </div>
                                            </div>
                                            <!-- Chọn giờ -->
                                            <div class="col-md-6">
                                                <div class="form-floating">
                                                    <select class="form-select" id="bookingTime" name="bookingTime" required>
                                                        <option value="">-- Chọn giờ --</option>
                                                        <?php
                                                        // Tạo các option từ 8:00 đến 22:00, mỗi 30 phút
                                                        for($hour = 8; $hour < 22; $hour++) {
                                                            $hour_padded = str_pad($hour, 2, '0', STR_PAD_LEFT);
                                                            // Option cho giờ chẵn
                                                            echo "<option value=\"{$hour_padded}:00\">{$hour_padded}:00</option>";
                                                            // Option cho giờ rưỡi
                                                            echo "<option value=\"{$hour_padded}:30\">{$hour_padded}:30</option>";
                                                        }
                                                        // Thêm option cuối 22:00
                                                        echo "<option value=\"22:00\">22:00</option>";
                                                        ?>
                                                    </select>
                                                    <label for="bookingTime">
                                                        <i class="far fa-clock me-2"></i>Chọn giờ
                                                    </label>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Input ẩn để lưu datetime -->
                                        <input type="hidden" id="datetime" name="datetime">

                                        <script>
                                            const bookingTime = document.getElementById('bookingTime');
                                            const bookingDate = document.getElementById('bookingDate');
                                            const reservationForm = document.getElementById('reservationForm');
                                            const isLoggedIn = <?php echo isset($_SESSION['khachhang_id']) ? 'true' : 'false'; ?>;
                                            const MIN_HOURS_AHEAD = 12;

                                            function updateDateTime() {
                                                const date = bookingDate.value;
                                                const time = bookingTime.value;
                                                if (date && time) {
                                                    document.getElementById('datetime').value = `${date} ${time}`;
                                                    enforceLeadTime();
                                                }
                                            }

                                            function enforceLeadTime() {
                                                const date = bookingDate.value;
                                                const time = bookingTime.value;
                                                if (!date || !time) return;
                                                const selected = new Date(`${date}T${time}:00`);
                                                const now = new Date();
                                                const minAllowed = new Date(now.getTime() + MIN_HOURS_AHEAD * 60 * 60 * 1000);
                                                if (selected < minAllowed) {
                                                    const tomorrow = new Date(now);
                                                    tomorrow.setDate(tomorrow.getDate() + 1);
                                                    const newDate = tomorrow.toISOString().slice(0, 10);
                                                    bookingDate.value = newDate;
                                                    document.getElementById('datetime').value = `${newDate} ${time}`;
                                                    alert('Vui lòng đặt trước ít nhất 12 giờ. Hệ thống đã chuyển sang ngày mai.');
                                                }
                                            }

                                            bookingTime.addEventListener('change', updateDateTime);
                                            bookingDate.addEventListener('change', updateDateTime);

                                            // Chặn đặt bàn nếu chưa đăng nhập
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const modalElement = document.getElementById('reservationModal');
                                                if (modalElement) {
                                                    modalElement.addEventListener('show.bs.modal', function (event) {
                                                        if (!isLoggedIn) {
                                                            event.preventDefault();
                                                            alert('Vui lòng đăng nhập để đặt bàn.');
                                                            window.location.href = 'index.php?page=login';
                                                        }
                                                    });
                                                }

                                                if (reservationForm) {
                                                    reservationForm.addEventListener('submit', function(e) {
                                                        if (!isLoggedIn) {
                                                            e.preventDefault();
                                                            alert('Vui lòng đăng nhập để đặt bàn.');
                                                            window.location.href = 'index.php?page=login';
                                                            return;
                                                        }
                                                        enforceLeadTime();
                                                        if (!document.getElementById('datetime').value) {
                                                            e.preventDefault();
                                                            alert('Vui lòng chọn ngày giờ hợp lệ.');
                                                        }
                                                    });
                                                }
                                            });
                                        </script>
                                        <small class="text-muted mt-3 d-block">Giờ mở cửa: 08:00 - 22:00</small>
                                    </div>

                                    <!-- Khu vực -->
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <select class="form-select" id="khuvuc" name="khuvuc" required>
                                                <option value="">-- Chọn khu vực --</option>
                                                <?php echo $kvb->selectKvban(); ?>
                                            </select>
                                            <label for="khuvuc">
                                                <i class="fas fa-map-marker-alt me-2"></i>Khu vực
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary w-100 py-3">
                                        <i class="fas fa-check me-2"></i>Tiếp tục
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- CSS Flatpickr -->
        <!-- JS Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>

    <!-- CSS Form -->
    <link href="css/form.css" rel="stylesheet">

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // Khởi tạo Flatpickr cho input ngày giờ với cấu hình nâng cao
        flatpickr("#datetime", {
            enableTime: true, // Bật chọn giờ
            dateFormat: "Y-m-d H:i", // Định dạng ngày giờ
            minDate: "today", // Chỉ cho phép chọn từ hôm nay
            time_24hr: true, // Sử dụng giờ 24h
            defaultDate: null, // Không đặt ngày mặc định
            allowInput: false, // Tắt nhập tay để tránh lỗi định dạng
            minTime: "08:00", // Giờ mở cửa
            maxTime: "22:00", // Giờ đóng cửa
            disableMobile: true, // Tắt calendar mặc định trên mobile
            static: true, // Giữ calendar mở khi chọn giờ
            clickOpens: true, // Cho phép click để mở
            closeOnSelect: false, // Không đóng khi chọn xong
            locale: {
                firstDayOfWeek: 1,
                weekdays: {
                    shorthand: ["CN", "T2", "T3", "T4", "T5", "T6", "T7"],
                    longhand: ["Chủ Nhật", "Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy"]
                },
                months: {
                    shorthand: ["Th1", "Th2", "Th3", "Th4", "Th5", "Th6", "Th7", "Th8", "Th9", "Th10", "Th11", "Th12"],
                    longhand: ["Tháng 1", "Tháng 2", "Tháng 3", "Tháng 4", "Tháng 5", "Tháng 6", "Tháng 7", "Tháng 8", "Tháng 9", "Tháng 10", "Tháng 11", "Tháng 12"]
                }
            },
            // Đảm bảo calendar hiển thị đúng vị trí trong modal
            appendTo: document.querySelector('.modal-body'),
            onOpen: function(selectedDates, dateStr, instance) {
                // Đặt z-index cao hơn modal
                instance.calendarContainer.style.zIndex = "10000";
            },
            onChange: function(selectedDates, dateStr, instance) {
                // Kiểm tra thời gian hợp lệ
                if (selectedDates[0]) {
                    const selectedTime = selectedDates[0].getHours();
                    if (selectedTime < 8 || selectedTime >= 22) {
                        alert("Vui lòng chọn thời gian từ 8:00 đến 22:00");
                        instance.clear();
                    }
                }
            }
        });

        // Khởi tạo Modal
        const modalElement = document.getElementById('reservationModal');
        const myModal = new bootstrap.Modal(modalElement, {
            backdrop: false,  // TẮT lớp nền mờ
            keyboard: true
        });

        document.getElementById('openModalBtn').addEventListener('click', function () {
            myModal.show();
        });

     
        
      

        // Xử lý dropdown tài khoản
        const userDropdown = document.querySelector('.dropdown-toggle');
        const userNameSpan = document.getElementById('userName');
        
        // Kiểm tra session để hiển thị tên người dùng
        function checkUserSession() {
            const userName = '<?php echo isset($_SESSION["user_name"]) ? $_SESSION["user_name"] : ""; ?>';
            const guestMenu = document.getElementById('guestMenu');
            const userMenu = document.getElementById('userMenu');
            
            if (userName) {
                userNameSpan.textContent = userName;
                guestMenu.style.display = 'none';
                userMenu.style.display = 'block';
            } else {
                userNameSpan.textContent = 'Tài khoản';
                guestMenu.style.display = 'block';
                userMenu.style.display = 'none';
            }
        }
        
        // Gọi hàm kiểm tra session khi trang load
        checkUserSession();
        
        // Xử lý đăng xuất
        const logoutLink = document.querySelector('a[href="index.php?page=logout"]');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Bạn có chắc muốn đăng xuất?')) {
                    // Chuyển hướng đến trang logout
                    window.location.href = 'index.php?page=logout';
                }
            });
        }
    });
</script>

    <!-- Custom CSS -->
    <style>
        /* Modal styling */
        .modal {
            z-index: 9999 !important;
        }
        .modal-backdrop {
            z-index: 99 !important;
            opacity: 0.5;
        }
     
        /* Custom styles cho menu */
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover {
            color: #ffc107 !important;
        }
        
        .navbar-nav .nav-link i {
            font-size: 1.1rem;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 0.5rem 0;
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #ffc107;
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
        }
        
        .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .btn-primary {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #000;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #000;
        }

        /* Fix padding và margin khi mở modal */
        body.modal-open {
            overflow: auto !important;
            padding-right: 0 !important;
        }
        
        body.modal-open .navbar {
            padding-right: inherit !important;
            margin-right: inherit !important;
        }

        .modal {
            padding-right: 0 !important;
        }

        .modal-open .modal {
            overflow-x: hidden;
            overflow-y: auto;
            padding-right: 0 !important;
        }

        /* Vô hiệu hóa các style tự động của Bootstrap */
        [data-bs-padding-right] {
            padding-right: inherit !important;
        }

        [data-bs-margin-right] {
            margin-right: inherit !important;
        }
        .flatpickr-wrapper{
            width: 100%;
        }
        .flatpickr-input{
        height: 60px;
        }
    </style>
</div>
