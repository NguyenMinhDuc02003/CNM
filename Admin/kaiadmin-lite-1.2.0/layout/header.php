<?php
if (!function_exists('admin_auth_bootstrap_session')) {
    require_once __DIR__ . '/../includes/auth.php';
}

admin_auth_bootstrap_session();

$employee_name = 'Khách';
$employee_email = 'hello@example.com';

$currentAdmin = admin_auth_current_user();
if ($currentAdmin) {
    $employee_name = $currentAdmin['hoten'] ?? $employee_name;
    $employee_email = $currentAdmin['email'] ?? $employee_email;
}

$danhmucOptions = [];
$tonKhoOptions = [];

try {
    $menuDb = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db
        ? $GLOBALS['admin_db']
        : new connect_db();

    $conn = $menuDb->getConnection();
    $GLOBALS['conn'] = $conn;

    $danhmucOptions = $menuDb->xuatdulieu("SELECT iddm, tendanhmuc FROM danhmuc");
    $tonKhoOptions = $menuDb->xuatdulieu("SELECT idloaiTK, tenloaiTK FROM loaitonkho");
} catch (Throwable $th) {
    error_log('admin header menu load error: ' . $th->getMessage());
}


?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge" />

    <meta content="width=device-width, initial-scale=1.0, shrink-to-fit=no" name="viewport" />
    <link rel="icon" href="assets/img/kaiadmin/favicon.ico" type="image/x-icon" />

    <!-- Fonts và icons -->
    <script src="assets/js/plugin/webfont/webfont.min.js"></script>
    <!-- Bootstrap 5 (CSS) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Bootstrap 5 (JS + Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        WebFont.load({
            google: { families: ["Public Sans:300,400,500,600,700"] },
            custom: {
                families: [
                    "Font Awesome 5 Solid",
                    "Font Awesome 5 Regular",
                    "Font Awesome 5 Brands",
                    "simple-line-icons",
                ],
                urls: ["assets/css/fonts.min.css"],
            },
            active: function () {
                sessionStorage.fonts = true;
            },
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- CSS Files -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/plugins.min.css" />
    <link rel="stylesheet" href="assets/css/kaiadmin.min.css" />

    <!-- CSS chỉ để demo, không bao gồm trong dự án của bạn -->
    <link rel="stylesheet" href="assets/css/demo.css" />
    <!-- SweetAlert2 for modal-style flash messages -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>
<?php if (!empty($_SESSION['admin_flash']) && is_array($_SESSION['admin_flash'])):
    $af = $_SESSION['admin_flash'];
    $af_type = $af['type'] ?? 'info';
    $af_message = $af['message'] ?? '';
    unset($_SESSION['admin_flash']);
    $swal_icon = ($af_type === 'success') ? 'success' : (($af_type === 'danger') ? 'error' : (($af_type === 'warning') ? 'warning' : 'info'));
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    var message = <?php echo json_encode($af_message); ?>;
    var icon = <?php echo json_encode($swal_icon); ?>;
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: icon,
            html: message,
            confirmButtonText: 'OK'
        });
    } else {
        // fallback to bootstrap alert if SweetAlert is not available
        var container = document.createElement('div');
        container.className = 'container-fluid mt-3';
        container.innerHTML = '<div class="alert alert-' + <?php echo json_encode(htmlspecialchars($af_type)); ?> + ' alert-dismissible fade show" role="alert">' +
            message + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        document.body.insertBefore(container, document.body.firstChild);
    }
});
</script>
<?php endif; ?>

    <div class="main-panel">
        <!-- Sidebar -->
        <div class="sidebar" data-background-color="dark">
            <div class="sidebar-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="dark">
                    <a href="index.php">
                        <h1 class="m-0" style="color: #fcac3c"><i class="fa fa-utensils me-3"
                                style="color: #fcac3c"></i>Restoran
                        </h1>
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar">
                            <i class="gg-menu-right"></i>
                        </button>
                        <button class="btn btn-toggle sidenav-toggler">
                            <i class="gg-menu-left"></i>
                        </button>
                    </div>
                    <button class="topbar-toggler more">
                        <i class="gg-more-vertical-alt"></i>
                    </button>
                </div>
                <!-- End Logo Header -->
            </div>
            <div class="sidebar-wrapper scrollbar scrollbar-inner">
                <div class="sidebar-content">
                    <ul class="nav nav-secondary">
                        <?php
                        // // Kiểm tra quyền Xem trang chủ
                        // if ($permission->hasPermission('Xem trang chu')) {
                        //     echo '<li class="nav-item actives">
                        //         <a href="index.php">
                        //             <i class="fas fa-home"></i>
                        //             <p>Trang chủ</p>
                        //         </a>
                        //     </li>
                        //     <hr>';
                        // }
                        // Lấy trang hiện tại từ URL
                        $current_page = isset($_GET['page']) ? $_GET['page'] : '';
                        ?>
                        <?php if (admin_auth_has_permission('Xem trang chu')): ?>
                            <li class="nav-item actives <?php echo ($current_page == 'erp.php') ? 'active' : ''; ?>">
                                <a href="index.php?page=erp.php">
                                    <i class="fas fa-home"></i>
                                    <p>Trang chủ</p>
                                </a>
                            </li>
                        <?php endif; ?>
                        <hr>
                        <li class="nav-section">
                            <span class="sidebar-mini-icon">
                                <i class="fa fa-ellipsis-h"></i>
                            </span>
                            <h4 class="text-section"></h4>
                        </li>
                        <?php
                        // Lấy danh sách menu items từ Permission class
                        // $menuItems = $permission->getMenuItems();
                        // foreach ($menuItems as $item) {
                        //     // Kiểm tra quyền truy cập cho menu item
                        //     if ($permission->checkAccess(str_replace('index.php?page=', '', $item['url']))) {
                        //         echo '<li class="nav-item">';
                        //         if ($item['title'] == 'Món ăn' || $item['title'] == 'Tồn kho') {
                        //             echo '<div class="d-flex justify-content-between align-items-center">';
                        //             echo '<a href="' . $item['url'] . '">';
                        //             echo '<i class="' . $item['icon'] . '"></i>';
                        //             echo '<p style="display: inline;">' . $item['title'] . '</p>';
                        //             echo '</a>';
                        //             echo '<a data-bs-toggle="collapse" href="#' . strtolower(str_replace(' ', '', $item['title'])) . '" role="button" aria-expanded="false" aria-controls="' . strtolower(str_replace(' ', '', $item['title'])) . '">';
                        //             echo '<span class="caret"></span>';
                        //             echo '</a>';
                        //             echo '</div>';
                        
                        //             // Xử lý submenu cho Món ăn và Tồn kho
                        //             echo '<div class="collapse" id="' . strtolower(str_replace(' ', '', $item['title'])) . '">';
                        //             echo '<ul class="nav nav-collapse">';
                        //             if ($item['title'] == 'Món ăn') {
                        //                 $str = "SELECT * FROM danhmuc";
                        //                 $result = $conn->query($str);
                        //                 if (mysqli_num_rows($result) > 0) {
                        //                     while ($row = mysqli_fetch_assoc($result)) {
                        //                         echo "<li><a href='index.php?page=monantheodm&danhmuc=" . $row['iddm'] . "' style='text-decoration:none; margin:10px'>";
                        //                         echo "<p>" . $row['tendanhmuc'] . "</p>";
                        //                         echo "</a></li>";
                        //                     }
                        //                 }
                        //             } else if ($item['title'] == 'Tồn kho') {
                        //                 $str = "SELECT * FROM loaitonkho";
                        //                 $result = $conn->query($str);
                        //                 if (mysqli_num_rows($result) > 0) {
                        //                     while ($row = mysqli_fetch_assoc($result)) {
                        //                         echo "<li><a href='index.php?page=tonkhotheoloai&loaiTK=" . $row['idloaiTK'] . "' style='text-decoration:none; margin:10px'>";
                        //                         echo "<p>" . $row['tenloaiTK'] . "</p>";
                        //                         echo "</a></li>";
                        //                     }
                        //                 }
                        //             }
                        //             echo '</ul>';
                        //             echo '</div>';
                        //         } else {
                        //             echo '<a href="' . $item['url'] . '">';
                        //             echo '<i class="' . $item['icon'] . '"></i>';
                        //             echo '<p>' . $item['title'] . '</p>';
                        //             echo '</a>';
                        //         }
                        //         echo '</li>';
                        //     }
                        // }
                        ?>

                        <!-- Menu Khách hàng 
                        <li class="nav-item <?php echo ($current_page == 'dskhachhang') ? 'active' : ''; ?>">
                            <a href="index.php?page=dskhachhang">
                                <i class="fas fa-address-card"></i>
                                <p>Khách hàng</p>
                            </a>
                        </li>-->

                        <!-- Menu Món ăn với submenu -->
                        <li class="nav-item <?php echo ($current_page == 'dsmonan' || strpos($current_page, 'monantheodm') !== false) ? 'active' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="index.php?page=dsmonan">
                                    <i class="fas fa-utensils"></i>
                                    <p style="display: inline;">Món ăn</p>
                                </a>
                                <a data-bs-toggle="collapse" href="#monan" role="button" aria-expanded="false"
                                    aria-controls="monan">
                                    <span class="caret"></span>
                                </a>
                            </div>
                            <div class="collapse" id="monan">
                                <ul class="nav nav-collapse">
                                    <?php foreach ($danhmucOptions as $row): ?>
                                        <li>
                                            <a href="index.php?page=monantheodm&danhmuc=<?php echo $row['iddm']; ?>"
                                                style="text-decoration:none; margin:10px">
                                                <p><?php echo htmlspecialchars($row['tendanhmuc']); ?></p>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </li>

                        <!-- Menu Đơn đặt bàn -->
                        <li class="nav-item <?php echo ($current_page == 'dsdatban') ? 'active' : ''; ?>">
                            <a href="index.php?page=dsdatban">
                                <i class="far fa-address-book"></i>
                                <p>Đơn đặt bàn</p>
                            </a>
                        </li>

                        <!-- Menu Đơn hàng -->
                        <li class="nav-item <?php echo ($current_page == 'dsdonhang') ? 'active' : ''; ?>">
                            <a href="index.php?page=dsdonhang">
                                <i class="fas fa-pen-square"></i>
                                <p>Đơn hàng</p>
                            </a>
                        </li>

               

                        <!-- Menu Hóa đơn -->
                        <li class="nav-item <?php echo ($current_page == 'dshoadon') ? 'active' : ''; ?>">
                            <a href="index.php?page=dshoadon">
                                <i class="fas fa-align-right"></i>
                                <p>Hóa đơn</p>
                            </a>
                        </li>

                        <!-- Menu Tồn kho với submenu -->
                        <li class="nav-item <?php echo ($current_page == 'dstonkho' || strpos($current_page, 'tonkhotheoloai') !== false) ? 'active' : ''; ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="index.php?page=dstonkho">
                                    <i class="fa fa-warehouse"></i>
                                    <p style="display: inline;">Tồn kho</p>
                                </a>
                                <a data-bs-toggle="collapse" href="#tonkho" role="button" aria-expanded="false"
                                    aria-controls="tonkho">
                                    <span class="caret"></span>
                                </a>
                            </div>
                            <div class="collapse" id="tonkho">
                                <ul class="nav nav-collapse">
                                    <?php foreach ($tonKhoOptions as $row): ?>
                                        <li>
                                            <a href="index.php?page=tonkhotheoloai&loaiTK=<?php echo $row['idloaiTK']; ?>"
                                                style="text-decoration:none; margin:10px">
                                                <p><?php echo htmlspecialchars($row['tenloaiTK']); ?></p>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </li>

                        <!-- Menu Nhân viên -->
                        <li class="nav-item <?php echo ($current_page == 'dsnhanvien') ? 'active' : ''; ?>">
                            <a href="index.php?page=dsnhanvien">
                                <i class="fas icon-people"></i>
                                <p>Nhân viên</p>
                            </a>
                        </li>

                        <li class="nav-item <?php echo ($current_page == 'bangluong') ? 'active' : ''; ?>">
                            <a href="index.php?page=bangluong">
                                <i class="fas fa-money-check-alt"></i>
                                <p>Bảng lương</p>
                            </a>
                        </li>

                        <!-- Menu Phân quyền -->
                        <li class="nav-item <?php echo ($current_page == 'phanquyen') ? 'active' : ''; ?>">
                            <a href="index.php?page=phanquyen">
                                <i class="fas icon-wrench"></i>
                                <p>Phân quyền</p>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- End Sidebar -->
        <div class="main-header">
            <div class="main-header-logo">
                <!-- Logo Header -->
                <div class="logo-header" data-background-color="dark">
                    <a href="index.html" class="logo">
                        <img src="assets/img/kaiadmin/logo_light.svg" alt="navbar brand" class="navbar-brand"
                            height="20" />
                    </a>
                    <div class="nav-toggle">
                        <button class="btn btn-toggle toggle-sidebar">
                            <i class="gg-menu-right"></i>
                        </button>
                        <button class="btn btn-toggle sidenav-toggler">
                            <i class="gg-menu-left"></i>
                        </button>
                    </div>
                    <button class="topbar-toggler more">
                        <i class="gg-more-vertical-alt"></i>
                    </button>
                </div>
                <!-- End Logo Header -->
            </div>
            <!-- Navbar Header -->
            <nav class="navbar navbar-header navbar-header-transparent navbar-expand-lg border-bottom">
                <div class="container-fluid">
                    <!-- <nav class="navbar navbar-header-left navbar-expand-lg navbar-form nav-search p-0 d-none d-lg-flex">
                        <form action="index.php" method="GET" class="navbar-left navbar-form nav-search">
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <button type="submit" class="btn btn-search pe-1">
                                        <i class="fa fa-search search-icon"></i>
                                    </button>
                                </div>
                                <input type="hidden" name="page" value="timkiem" />
                                <input type="text" name="keyword" placeholder="Tìm kiếm ..." class="form-control" />
                            </div>
                        </form>
                    </nav> -->

                    <ul class="navbar-nav topbar-nav ms-md-auto align-items-center">
                        <li class="nav-item topbar-icon dropdown hidden-caret d-flex d-lg-none">
                            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button"
                                aria-expanded="false" aria-haspopup="true">
                                <i class="fa fa-search"></i>
                            </a>
                            <ul class="dropdown-menu dropdown-search animated fadeIn">
                                <form class="navbar-left navbar-form nav-search">
                                    <div class="input-group">
                                        <input type="text" placeholder="Tìm kiếm ..." class="form-control" />
                                    </div>
                                </form>
                            </ul>
                        </li>
                        <li class="nav-item topbar-icon dropdown hidden-caret">
                            <a class="nav-link dropdown-toggle" href="#" id="messageDropdown" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-envelope"></i>
                            </a>
                            <ul class="dropdown-menu messages-notif-box animated fadeIn"
                                aria-labelledby="messageDropdown">
                                <li>
                                    <div class="dropdown-title d-flex justify-content-between align-items-center">
                                        Tin nhắn
                                        <a href="#" class="small">Đánh dấu tất cả là đã đọc</a>
                                    </div>
                                </li>
                                <li>
                                    <div class="message-notif-scroll scrollbar-outer">
                                        <div class="notif-center">
                                            <!--<a href="#">
                                                <div class="notif-img">
                                                    <img src="assets/img/jm_denis.jpg" alt="Hình ảnh hồ sơ" />
                                                </div>
                                                <div class="notif-content">
                                                    <span class="subject">Jimmy Denis</span>
                                                    <span class="block"> Bạn khỏe không? </span>
                                                    <span class="time">5 phút trước</span>
                                                </div>
                                            </a>
                                            <a href="#">
                                                <div class="notif-img">
                                                    <img src="assets/img/chadengle.jpg" alt="Hình ảnh hồ sơ" />
                                                </div>
                                                <div class="notif-content">
                                                    <span class="subject">Chad</span>
                                                    <span class="block"> Ok, Cảm ơn! </span>
                                                    <span class="time">12 phút trước</span>
                                                </div>
                                            </a>
                                            <a href="#">
                                                <div class="notif-img">
                                                    <img src="assets/img/mlane.jpg" alt="Hình ảnh hồ sơ" />
                                                </div>
                                                <div class="notif-content">
                                                    <span class="subject">Jhon Doe</span>
                                                    <span class="block">
                                                        Sẵn sàng cho cuộc họp hôm nay...
                                                    </span>
                                                    <span class="time">12 phút trước</span>
                                                </div>
                                            </a>
                                             <a href="#">
                                                <div class="notif-img">
                                                    <img src="assets/img/talha.jpg" alt="Hình ảnh hồ sơ" />
                                                </div>
                                                <div class="notif-content">
                                                    <span class="subject">Talha</span>
                                                    <span class="block"> Xin chào, bạn khỏe không? </span>
                                                    <span class="time">17 phút trước</span>
                                                </div>
                                            </a> -->
                                        </div>
                                    </div>
                                </li>
                                <li>
                                    <a class="see-all" href="javascript:void(0);">Xem tất cả tin nhắn<i
                                            class="fa fa-angle-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <li class="nav-item topbar-icon dropdown hidden-caret">
                            <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-bell"></i>
                                <span class="notification">4</span>
                            </a>
                            <ul class="dropdown-menu notif-box animated fadeIn" aria-labelledby="notifDropdown">
                                <li>
                                    <div class="dropdown-title">
                                        Bạn có 4 thông báo mới
                                    </div>
                                </li>
                                <li>
                                    <div class="notif-scroll scrollbar-outer">
                                        <div class="notif-center">
                                            <a href="#">
                                                <div class="notif-icon notif-primary">
                                                    <i class="fa fa-user-plus"></i>
                                                </div>
                                                <div class="notif-content">
                                                    <span class="block"> Người dùng mới đã đăng ký </span>
                                                    <span class="time">5 phút trước</span>
                                                </div>
                                            </a>
                                            <a href="#">
                                                <div class="notif-icon notif-success">
                                                    <i class="fa fa-comment"></i>
                                                </div>
                                                <div class="notif-content">
                                                    <span class="block">
                                                        Rahmad đã bình luận về Admin
                                                    </span>
                                                    <span class="time">12 phút trước</span>
                                                </div>
                                            </a>
                                            <a href="#">
                                                <div class="notif-img">
                                                    <img src="assets/img/profile2.jpg" alt="Hình ảnh hồ sơ" />
                                                </div>
                                                <div class="notif-content">
                                                    <span class="block">
                                                        Reza gửi tin nhắn cho bạn
                                                    </span>
                                                    <span class="time">12 phút trước</span>
                                                </div>
                                            </a>
                                            <a href="#">
                                                <div class="notif-icon notif-danger">
                                                    <i class="fa fa-heart"></i>
                                                </div>
                                                <div class="notif-content">
                                                    <span class="block"> Farrah đã thích Admin </span>
                                                    <span class="time">17 phút trước</span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                                <li>
                                    <a class="see-all" href="javascript:void(0);">Xem tất cả thông báo<i
                                            class="fa fa-angle-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </li>
                        <!-- <li class="nav-item topbar-icon dropdown hidden-caret">
                            <a class="nav-link" data-bs-toggle="dropdown" href="#" aria-expanded="false">
                                <i class="fas fa-layer-group"></i>
                            </a>
                            <div class="dropdown-menu quick-actions animated fadeIn">
                                <div class="quick-actions-header">
                                    <span class="title mb-1">Hành động nhanh</span>
                                    <span class="subtitle op-7">Phím tắt</span>
                                </div>
                                <div class="quick-actions-scroll scrollbar-outer">
                                    <div class="quick-actions-items">
                                        <div class="row m-0">
                                            <a class="col-6 col-md-4 p-0" href="#">
                                                <div class="quick-actions-item">
                                                    <div class="avatar-item bg-danger rounded-circle">
                                                        <i class="far fa-calendar-alt"></i>
                                                    </div>
                                                    <span class="text">Lịch</span>
                                                </div>
                                            </a>
                                            <a class="col-6 col-md-4 p-0" href="#">
                                                <div class="quick-actions-item">
                                                    <div class="avatar-item bg-warning rounded-circle">
                                                        <i class="fas fa-map"></i>
                                                    </div>
                                                    <span class="text">Bản đồ</span>
                                                </div>
                                            </a>
                                            <a class="col-6 col-md-4 p-0" href="#">
                                                <div class="quick-actions-item">
                                                    <div class="avatar-item bg-info rounded-circle">
                                                        <i class="fas fa-file-excel"></i>
                                                    </div>
                                                    <span class="text">Báo cáo</span>
                                                </div>
                                            </a>
                                            <a class="col-6 col-md-4 p-0" href="#">
                                                <div class="quick-actions-item">
                                                    <div class="avatar-item bg-success rounded-circle">
                                                        <i class="fas fa-envelope"></i>
                                                    </div>
                                                    <span class="text">Email</span>
                                                </div>
                                            </a>
                                            <a class="col-6 col-md-4 p-0" href="#">
                                                <div class="quick-actions-item">
                                                    <div class="avatar-item bg-primary rounded-circle">
                                                        <i class="fas fa-file-invoice-dollar"></i>
                                                    </div>
                                                    <span class="text">Hóa đơn</span>
                                                </div>
                                            </a>
                                            <a class="col-6 col-md-4 p-0" href="#">
                                                <div class="quick-actions-item">
                                                    <div class="avatar-item bg-secondary rounded-circle">
                                                        <i class="fas fa-credit-card"></i>
                                                    </div>
                                                    <span class="text">Thanh toán</span>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li> -->

                        <li class="nav-item topbar-user dropdown hidden-caret">
                            <a class="dropdown-toggle profile-pic" data-bs-toggle="dropdown" href="#"
                                aria-expanded="false">
                                <div class="avatar-sm">
                                    <img src="assets/img/profile.jpg" alt="Hình ảnh hồ sơ"
                                        class="avatar-img rounded-circle" />
                                </div>
                                <span class="profile-username">
                                    <span class="op-7">Xin chào,</span>
                                    <span class="fw-bold"><?php echo htmlspecialchars($employee_name); ?></span>
                                </span>
                            </a>
                            <ul class="dropdown-menu dropdown-user animated fadeIn">
                                <div class="dropdown-user-scroll scrollbar-outer">
                                    <li>
                                        <div class="user-box">
                                            <div class="avatar-lg">
                                                <img src="assets/img/profile.jpg" alt="Hình ảnh hồ sơ"
                                                    class="avatar-img rounded" />
                                            </div>
                                            <div class="u-text">
                                                <h4><?php echo htmlspecialchars($employee_name); ?></h4>
                                                <p class="text-muted"><?php echo htmlspecialchars($employee_email); ?>
                                                </p>
                                                <a href="index.php?page=xemthongtin" class="btn btn-xs btn-secondary btn-sm">Xem hồ
                                                    sơ</a>
                                            </div>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#">Hồ sơ của tôi</a>
                                        <a class="dropdown-item" href="index.php?page=change_password">Đổi mật khẩu</a>
                                        <a class="dropdown-item" href="#">Hộp thư đến</a>
                                        <div class="dropdown-divider"></div>
                                        <a class="dropdown-item" href="#">Cài đặt tài khoản</a>
                                        <div class="dropdown-divider"></div>
                                        <?php if (admin_auth_is_logged_in()): ?>
                                            <a class="dropdown-item" href="index.php?page=dangxuat">Đăng xuất</a>
                                        <?php endif; ?>
                                    </li>
                                </div>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
            <!-- End Navbar -->
        </div>






