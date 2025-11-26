<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/auth.php';
admin_auth_bootstrap_session();
$currentAdmin = admin_auth_current_user();

require_once __DIR__ . '/../../class/clsconnect.php';
$db = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db ? $GLOBALS['admin_db'] : new connect_db();
$conn = $db->getConnection();

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($currentAdmin)) throw new Exception('Bạn chưa đăng nhập.');
        $adminId = $currentAdmin['idnv'] ?? ($currentAdmin['id'] ?? null);
        if (empty($adminId)) throw new Exception('Không xác định được tài khoản.');

        $current_pass = isset($_POST['current_password']) ? (string)$_POST['current_password'] : '';
        $new_pass = isset($_POST['new_password']) ? (string)$_POST['new_password'] : '';
        $confirm_pass = isset($_POST['confirm_password']) ? (string)$_POST['confirm_password'] : '';

        if ($current_pass === '' || $new_pass === '' || $confirm_pass === '') throw new Exception('Vui lòng điền đầy đủ thông tin.');
        if ($new_pass !== $confirm_pass) throw new Exception('Mật khẩu mới và xác nhận không khớp.');
        if (strlen($new_pass) < 4) throw new Exception('Mật khẩu mới quá ngắn (ít nhất 4 ký tự).');

        $sql = "SELECT password FROM nhanvien WHERE idnv = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) throw new Exception('Lỗi truy vấn.');
        mysqli_stmt_bind_param($stmt, 'i', $adminId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        if (!$row) throw new Exception('Không tìm thấy tài khoản.');

        $stored = (string)$row['password'];
        $verified = false;
        if ((strpos($stored, '$2y$') === 0) || (strpos($stored, '$2a$') === 0)) {
            if (password_verify($current_pass, $stored)) $verified = true;
        } elseif (strlen($stored) === 32) {
            if (md5($current_pass) === $stored) $verified = true;
        } else {
            if ($current_pass === $stored) $verified = true;
        }
        if (!$verified) throw new Exception('Mật khẩu hiện tại không đúng.');

        // Lưu MD5 theo yêu cầu
        $new_hashed = md5($new_pass);
        $u = mysqli_prepare($conn, "UPDATE nhanvien SET password = ? WHERE idnv = ?");
        if (!$u) throw new Exception('Lỗi cập nhật.');
        mysqli_stmt_bind_param($u, 'si', $new_hashed, $adminId);
        if (!mysqli_stmt_execute($u)) { mysqli_stmt_close($u); throw new Exception('Không thể cập nhật mật khẩu.'); }
        mysqli_stmt_close($u);

        $_SESSION['admin_flash'] = ['type' => 'success', 'message' => 'Đổi mật khẩu thành công.'];
        // Cannot call header() here because header.php has already sent output.
        // Use client-side redirect so the flash (stored in session) is shown on reload.
        echo '<script>window.location.href = "index.php?page=change_password";</script>';
        exit;
    } catch (Throwable $e) {
        $flash = ['type' => 'danger', 'message' => $e->getMessage()];
    }
}

// Render page (simple layout)
?>
<div class="container mt-4">
    <div class="card mx-auto" style="max-width:600px;">
        <div class="card-header">Đổi mật khẩu</div>
        <div class="card-body">
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>"><?php echo htmlspecialchars($flash['message']); ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Mật khẩu hiện tại</label>
                    <input type="password" name="current_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu mới</label>
                    <input type="password" name="new_password" class="form-control" minlength="4" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Xác nhận mật khẩu mới</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                <button class="btn btn-primary" type="submit">Lưu</button>
                <a href="index.php" class="btn btn-secondary ms-2">Hủy</a>
            </form>
        </div>
    </div>
</div>
