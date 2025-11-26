<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Bắt đầu session nếu chưa được bắt đầu
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra nếu đã đăng nhập
if (isset($_SESSION['khachhang_id'])) {
    header("Location: index.php");
    exit();
}

require_once('../../User/restoran-1.0.0/class/clsconnect.php');

// Xử lý đăng ký
$error = "";
$success = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenKH = trim($_POST['tenKH']);
    $email = trim($_POST['email']);
    $sodienthoai = trim($_POST['sodienthoai']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $gioitinh = $_POST['gioitinh'];
    $ngaysinh = $_POST['ngaysinh'];

    // Kiểm tra mật khẩu xác nhận
    if ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp.";
    } else {
        try {
            $db = new connect_db();
            
            // Kiểm tra email đã tồn tại chưa
            $sql_check = "SELECT * FROM khachhang WHERE email = ?";
            $result = $db->xuatdulieu_prepared($sql_check, [$email]);
            
            if (!empty($result)) {
                $error = "Email đã được sử dụng.";
            } else {
                // Thêm khách hàng mới với mật khẩu được mã hóa bằng BCRYPT
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $sql_insert = "INSERT INTO khachhang (tenKH, sodienthoai, email, password, ngaysinh, gioitinh) VALUES (?, ?, ?, ?, ?, ?)";
                $params = [$tenKH, $sodienthoai, $email, $hashed_password, $ngaysinh, $gioitinh];
                
                if ($db->tuychinh($sql_insert, $params)) {
                    $success = "Đăng ký thành công! Vui lòng đăng nhập.";
                } else {
                    $error = "Có lỗi xảy ra. Vui lòng thử lại.";
                }
            }
        } catch (Exception $e) {
            $error = "Lỗi: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Nhà hàng</title>
    <!-- Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../User/restoran-1.0.0/lib/animate/animate.min.css" rel="stylesheet">
    <link href="../../User/restoran-1.0.0/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="../../User/restoran-1.0.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="../../User/restoran-1.0.0/css/style.css" rel="stylesheet">
    
    <style>
        .register-container {
            max-width: 500px;
            margin: 200px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        .btn-primary {
            background-color: #FEA116;
            border-color: #FEA116;
        }
        .btn-primary:hover {
            background-color: #b17012;
            border-color: #b17012;
        }
        .navbar {
            display: none !important;
        }
        .hero-header {
            display: none !important;
        }
    </style>
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
        <?php include('../../User/restoran-1.0.0/layout/header.php'); ?>
        <!-- Navbar & Hero End -->

        <div class="register-container">
            <h2 class="text-center mb-4">Đăng ký tài khoản</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="tenKH" class="form-label">Họ tên</label>
                    <input type="text" class="form-control" id="tenKH" name="tenKH" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="sodienthoai" class="form-label">Số điện thoại</label>
                    <input type="tel" class="form-control" id="sodienthoai" name="sodienthoai" pattern="[0-9]+" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="mb-3">
                    <label for="ngaysinh" class="form-label">Ngày sinh</label>
                    <input type="date" class="form-control" id="ngaysinh" name="ngaysinh" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Giới tính</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gioitinh" id="nam" value="Nam" required>
                            <label class="form-check-label" for="nam">Nam</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="gioitinh" id="nu" value="Nữ" required>
                            <label class="form-check-label" for="nu">Nữ</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Đăng ký</button>
                <div class="text-center">
                    <p>Đã có tài khoản? <a href="index.php?page=login">Đăng nhập</a></p>
                </div>
            </form>
        </div>


        <!-- Back to Top -->
        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/wow/wow.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/easing/easing.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/waypoints/waypoints.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/counterup/counterup.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/tempusdominus/js/moment.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/tempusdominus/js/moment-timezone.min.js"></script>
    <script src="../../User/restoran-1.0.0/lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>

    <!-- Template Javascript -->
    <script src="../../User/restoran-1.0.0/js/main.js"></script>
</body>
</html>
