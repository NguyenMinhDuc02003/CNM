<?php
if (!function_exists('admin_auth_bootstrap_session')) {
    require_once __DIR__ . '/../../includes/auth.php';
}
if (!class_exists('connect_db')) {
    require_once __DIR__ . '/../../class/clsconnect.php';
}

admin_auth_bootstrap_session();

$currentUserId = $_SESSION['nhanvien_id'] ?? null;
if (!$currentUserId) {
    echo '<div class="container py-5"><div class="alert alert-danger">Vui lòng đăng nhập để tiếp tục.</div></div>';
    return;
}

$db = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db
    ? $GLOBALS['admin_db']
    : new connect_db();

$employeeRows = $db->xuatdulieu_prepared(
    "SELECT idnv, HinhAnh, HoTen, GioiTinh, SoDienThoai, Email, DiaChi
     FROM nhanvien
     WHERE idnv = ?
     LIMIT 1",
    [(int) $currentUserId]
);
$employee = $employeeRows[0] ?? null;

if (!$employee) {
    echo '<div class="container py-5"><div class="alert alert-warning">Không tìm thấy thông tin nhân viên.</div></div>';
    return;
}

$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hoTen = trim($_POST['HoTen'] ?? '');
    $gioiTinh = $_POST['GioiTinh'] ?? '';
    $soDienThoai = trim($_POST['SoDienThoai'] ?? '');
    $email = trim($_POST['Email'] ?? '');
    $diaChi = trim($_POST['DiaChi'] ?? '');
    $currentImage = $employee['HinhAnh'] ?? '';
    $newImageName = $currentImage;

    if ($hoTen === '') {
        $errors[] = 'Vui lòng nhập họ tên.';
    }

    $genderOptions = ['Nam', 'Nữ', 'Khác'];
    if (!in_array($gioiTinh, $genderOptions, true)) {
        $errors[] = 'Vui lòng chọn giới tính hợp lệ.';
    }

    if ($soDienThoai === '') {
        $errors[] = 'Vui lòng nhập số điện thoại.';
    } elseif (!preg_match('/^[0-9\+\-\s]{6,20}$/', $soDienThoai)) {
        $errors[] = 'Số điện thoại không hợp lệ.';
    }

    if ($email === '') {
        $errors[] = 'Vui lòng nhập email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }

    if ($diaChi === '') {
        $errors[] = 'Vui lòng nhập địa chỉ.';
    }

    if (!empty($_FILES['HinhAnh']['name'])) {
        $fileError = $_FILES['HinhAnh']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($fileError === UPLOAD_ERR_OK) {
            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            $tmpPath = $_FILES['HinhAnh']['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? finfo_file($finfo, $tmpPath) : null;
            if ($finfo) {
                finfo_close($finfo);
            }

            if (!$mimeType || !isset($allowedTypes[$mimeType])) {
                $errors[] = 'Vui lòng chọn ảnh hợp lệ (jpg, png, gif, webp).';
            } else {
                $uploadDir = realpath(__DIR__ . '/../../assets/img');
                if (!$uploadDir) {
                    $errors[] = 'Không tìm thấy thư mục lưu ảnh.';
                } else {
                    $extension = $allowedTypes[$mimeType];
                    $newFileName = 'nhanvien_' . $currentUserId . '_' . time() . '.' . $extension;
                    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;
                    if (!move_uploaded_file($tmpPath, $targetPath)) {
                        $errors[] = 'Không thể lưu ảnh lên máy chủ.';
                    } else {
                        $newImageName = $newFileName;
                    }
                }
            }
        } elseif ($fileError !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Tải ảnh thất bại. Vui lòng thử lại.';
        }
    }

    if (empty($errors)) {
        $updated = $db->tuychinh(
            "UPDATE nhanvien
             SET HinhAnh = ?, HoTen = ?, GioiTinh = ?, SoDienThoai = ?, Email = ?, DiaChi = ?
             WHERE idnv = ?
             LIMIT 1",
            [
                (string) $newImageName,
                $hoTen,
                $gioiTinh,
                $soDienThoai,
                $email,
                $diaChi,
                (int) $currentUserId
            ]
        );

        if ($updated === false) {
            $errors[] = 'Không thể cập nhật thông tin. Vui lòng thử lại.';
        } else {
            $successMessage = 'Cập nhật thông tin thành công.';
            $employee['HinhAnh'] = $newImageName;
            $employee['HoTen'] = $hoTen;
            $employee['GioiTinh'] = $gioiTinh;
            $employee['SoDienThoai'] = $soDienThoai;
            $employee['Email'] = $email;
            $employee['DiaChi'] = $diaChi;
        }
    }
}

$profileImage = 'assets/img/profile.jpg';
if (!empty($employee['HinhAnh'])) {
    $customPath = 'assets/img/' . ltrim($employee['HinhAnh'], '/');
    $absolutePath = realpath(__DIR__ . '/../../' . $customPath);
    $basePath = realpath(__DIR__ . '/../../');
    if ($absolutePath && $basePath && strpos($absolutePath, $basePath) === 0 && file_exists($absolutePath)) {
        $profileImage = $customPath;
    }
}

$genderValue = $employee['GioiTinh'] ?? 'Khác';

?>
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2 class="mb-1">Cập nhật thông tin cá nhân</h2>
                            <p class="text-muted mb-0">Quản lý thông tin liên hệ và ảnh đại diện của bạn.</p>
                        </div>
                        <a href="index.php?page=xemthongtin" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại
                        </a>
                    </div>

                    <?php if ($successMessage !== ''): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="row g-3">
                        <div class="col-12 text-center">
                            <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Ảnh đại diện"
                                 class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                        </div>
                        <div class="col-12">
                            <label for="avatar" class="form-label">Ảnh đại diện</label>
                            <input type="file" class="form-control" id="avatar" name="HinhAnh" accept="image/*">
                            <div class="form-text">Bỏ qua nếu bạn muốn giữ ảnh hiện tại.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="fullname" class="form-label">Họ tên</label>
                            <input type="text" class="form-control" id="fullname" name="HoTen"
                                   value="<?php echo htmlspecialchars($employee['HoTen'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Giới tính</label>
                            <select class="form-select" id="gender" name="GioiTinh">
                                <?php
                                foreach (['Nam', 'Nữ', 'Khác'] as $option) {
                                    $selected = ($genderValue === $option) ? 'selected' : '';
                                    echo '<option value="' . htmlspecialchars($option) . '" ' . $selected . '>' . htmlspecialchars($option) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Số điện thoại</label>
                            <input type="text" class="form-control" id="phone" name="SoDienThoai"
                                   value="<?php echo htmlspecialchars($employee['SoDienThoai'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="Email"
                                   value="<?php echo htmlspecialchars($employee['Email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Địa chỉ</label>
                            <textarea class="form-control" id="address" name="DiaChi" rows="3" required><?php echo htmlspecialchars($employee['DiaChi'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
