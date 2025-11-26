<?php
// Thiết lập UTF-8
header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../class/clsconnect.php';

$error = '';
$success = '';
$tokenValid = false;
$email = $_GET['email'] ?? ($_POST['email'] ?? '');
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$tenKH = '';

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

try {
    $db = new connect_db();
    ensurePasswordResetTable($db);
} catch (Exception $e) {
    $error = 'Không thể kết nối database. Vui lòng thử lại sau.';
}

/**
 * Kiểm tra token hợp lệ và trả về bản ghi reset + tên khách hàng.
 */
function fetchValidReset($db, $email, $token)
{
    if (!$email || !$token) {
        return null;
    }

    $tokenHash = hash('sha256', $token);
    $sql = "SELECT pr.*, kh.tenKH 
            FROM password_resets pr 
            JOIN khachhang kh ON kh.idKH = pr.idKH
            WHERE pr.email = ? AND pr.expires_at >= NOW()
            ORDER BY pr.created_at DESC 
            LIMIT 1";

    $rows = $db->xuatdulieu_prepared($sql, [$email]);
    if (empty($rows)) {
        return null;
    }

    $reset = $rows[0];
    if (!hash_equals($reset['token_hash'], $tokenHash)) {
        return null;
    }

    return $reset;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $newPassword = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Vui lòng nhập đầy đủ mật khẩu.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Mật khẩu xác nhận không khớp.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Mật khẩu cần tối thiểu 6 ký tự.';
    } else {
        try {
            $reset = fetchValidReset($db, $email, $token);
            if ($reset === null) {
                $error = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                $db->tuychinh("UPDATE khachhang SET password = ? WHERE idKH = ?", [$newHash, (int)$reset['idKH']]);
                $db->tuychinh("DELETE FROM password_resets WHERE email = ?", [$email]);
                $success = 'Đổi mật khẩu thành công. Bạn có thể đăng nhập lại.';
                $tokenValid = false;
            }
        } catch (Exception $e) {
            $error = 'Có lỗi xảy ra. Vui lòng thử lại.';
            error_log('Reset password error: ' . $e->getMessage());
        }
    }
}

// Với GET hoặc nếu chưa có lỗi khi POST, kiểm tra token để hiển thị form
if (!$success && !$error && $db ?? null) {
    $reset = fetchValidReset($db, $email, $token);
    if ($reset !== null) {
        $tokenValid = true;
        $tenKH = $reset['tenKH'];
    } else {
        $error = 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu - Nhà hàng</title>
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
        .reset-container {
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

        <div class="reset-container">
            <h2 class="text-center mb-3">Đặt lại mật khẩu</h2>
            <?php if ($tenKH): ?>
                <p class="text-center text-muted mb-4">Xin chào <?php echo htmlspecialchars($tenKH); ?>, hãy tạo mật khẩu mới cho tài khoản của bạn.</p>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($tokenValid): ?>
                <form method="POST" action="">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="mb-3">
                        <label for="password" class="form-label">Mật khẩu mới</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Xác nhận mật khẩu</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3">Cập nhật mật khẩu</button>
                </form>
            <?php endif; ?>

            <div class="text-center">
                <p><a href="index.php?page=login">Quay lại đăng nhập</a></p>
            </div>
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
