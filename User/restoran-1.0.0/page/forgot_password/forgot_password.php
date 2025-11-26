<?php
// Thiết lập UTF-8
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../PHPMailer/SMTP.php';
require_once __DIR__ . '/../../PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

$error = '';
$success = '';

/**
 * Tạo bảng lưu token reset mật khẩu nếu chưa tồn tại.
 */
function ensurePasswordResetTable($db)
{
    if (method_exists($db, 'tableExists') && $db->tableExists('password_resets')) {
        return;
    }

    $createSql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        idKH INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_token_hash (token_hash),
        CONSTRAINT fk_password_resets_khachhang FOREIGN KEY (idKH) REFERENCES khachhang(idKH) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->executeRaw($createSql);
}

/**
 * Trả về link tuyệt đối để reset mật khẩu.
 */
function buildResetLink($email, $token)
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $resetPath = $basePath . '/index.php?page=reset_password&token=' . urlencode($token) . '&email=' . urlencode($email);
    return $protocol . '://' . $host . $resetPath;
}

/**
 * Gửi email chứa link reset mật khẩu.
 */
function sendResetEmail($email, $tenKH, $resetLink)
{
    $mail = new PHPMailer(true);

    // SMTP giống cấu hình thanh toán MoMo
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'nhom1.9a7.2018@gmail.com';
    $mail->Password = 'rwgt urjf wpfy iirg'; // App Password 16 ký tự
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('nhom1.9a7.2018@gmail.com', 'Nhà hàng Restoran');
    $mail->addAddress($email, $tenKH);

    $mail->isHTML(true);
    $mail->Subject = 'Yêu cầu đặt lại mật khẩu';
    $mail->Body = '
        <p>Chào ' . htmlspecialchars($tenKH) . ',</p>
        <p>Chúng tôi vừa nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.</p>
        <p>Vui lòng nhấn vào nút bên dưới (hoặc dán đường link vào trình duyệt) để đặt mật khẩu mới. Liên kết này có hiệu lực trong 30 phút.</p>
        <p><a href="' . htmlspecialchars($resetLink) . '" style="background:#FEA116;color:#fff;padding:10px 18px;text-decoration:none;border-radius:6px;font-weight:bold;">Đặt lại mật khẩu</a></p>
        <p style="word-break:break-all;">Hoặc copy link: ' . htmlspecialchars($resetLink) . '</p>
        <p>Nếu bạn không yêu cầu hành động này, vui lòng bỏ qua email.</p>
        <p>Trân trọng,<br>Nhà hàng Restoran</p>
    ';

    return $mail->send();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ.';
    } else {
        try {
            $db = new connect_db();
            ensurePasswordResetTable($db);

            $userSql = "SELECT idKH, tenKH FROM khachhang WHERE email = ?";
            $user = $db->xuatdulieu_prepared($userSql, [$email]);

            if (empty($user)) {
                $error = "Email không tồn tại trong hệ thống.";
            } else {
                $idKH = (int)$user[0]['idKH'];
                $tenKH = $user[0]['tenKH'];

                // Xóa token cũ để tránh trùng lặp
                $db->tuychinh("DELETE FROM password_resets WHERE email = ?", [$email]);

                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 1800); // 30 phút

                $insertSql = "INSERT INTO password_resets (idKH, email, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, NOW())";
                $db->tuychinh($insertSql, [$idKH, $email, $tokenHash, $expiresAt]);

                $resetLink = buildResetLink($email, $token);
                sendResetEmail($email, $tenKH, $resetLink);

                $success = "Chúng tôi đã gửi đường dẫn đặt lại mật khẩu đến email của bạn.";
            }
        } catch (Exception $e) {
            $error = "Có lỗi xảy ra. Vui lòng thử lại sau.";
            error_log('Forgot password error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu - Nhà hàng</title>
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
        .forgot-container {
            max-width: 400px;
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
        .navbar, .hero-header {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="container-xxl bg-white p-0">
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>

        <?php include('../../User/restoran-1.0.0/layout/header.php'); ?>

        <div class="forgot-container">
            <h2 class="text-center mb-4">Quên mật khẩu</h2>
            <p class="text-muted">Nhập email để nhận liên kết đặt lại mật khẩu.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Gửi liên kết</button>
                <div class="text-center">
                    <p>Nhớ mật khẩu? <a href="index.php?page=login">Đăng nhập</a></p>
                </div>
            </form>
        </div>

        <a href="#" class="btn btn-lg btn-primary btn-lg-square back-to-top"><i class="bi bi-arrow-up"></i></a>
    </div>

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

    <script src="../../User/restoran-1.0.0/js/main.js"></script>
</body>
</html>
