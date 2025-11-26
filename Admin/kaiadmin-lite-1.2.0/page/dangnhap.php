<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';

admin_auth_redirect_if_logged_in('../index.php');

$error = '';
$successMessage = '';

if (isset($_GET['status']) && $_GET['status'] === 'logged_out') {
    $successMessage = 'Đăng xuất thành công.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (admin_auth_attempt_login((string) $email, (string) $password, $error)) {
        header('Location: ../index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập nhân viên</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">Đăng nhập nhân viên</h2>
        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
