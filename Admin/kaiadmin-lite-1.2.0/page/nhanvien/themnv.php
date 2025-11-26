<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';
include_once 'includes/face_service.php';
include_once 'includes/role_helper.php';

// Kiểm tra quyền truy cập
if (!hasPermission('them nhan vien', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Xử lý thêm nhân viên
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hoten = $_POST['hoten'];
    $gioitinh = $_POST['gioitinh'];
    $vaitro = $_POST['vaitro'];
    $sodienthoai = $_POST['sodienthoai'];
    $email = $_POST['email'];
    $diachi = $_POST['diachi'];
    $luong = $_POST['luong'];
    $password = $_POST['password'];
    $passwordHashed = md5((string) $password);
    $chucVu = resolveRoleLabel($conn, (int) $vaitro);
    
    // Xử lý upload hình ảnh
    $hinhanh = '';
    if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        $filename = $_FILES['hinhanh']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $newname = uniqid() . '.' . $ext;
            // Đường dẫn lưu file vào thư mục assets/img
            $destination = 'assets/img/' . $newname;
            
            if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $destination)) {
                $hinhanh = $newname;
            }
        }
    }
    
    // Thêm nhân viên vào CSDL
    if ($hinhanh) {
        $stmt = $conn->prepare("INSERT INTO nhanvien (HoTen, GioiTinh, ChucVu, idvaitro, SoDienThoai, Email, DiaChi, Luong, HinhAnh, password) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisssdss", $hoten, $gioitinh, $chucVu, $vaitro, $sodienthoai, $email, $diachi, $luong, $hinhanh, $passwordHashed);
    } else {
        $stmt = $conn->prepare("INSERT INTO nhanvien (HoTen, GioiTinh, ChucVu, idvaitro, SoDienThoai, Email, DiaChi, Luong, password) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssisssds", $hoten, $gioitinh, $chucVu, $vaitro, $sodienthoai, $email, $diachi, $luong, $passwordHashed);
    }
    
    if ($stmt->execute()) {
        $employeeId = mysqli_insert_id($conn);
        $employeeCode = format_employee_code($employeeId);
        $faceMessage = '';

        if ($hinhanh) {
            $storedPath = 'assets/img/' . $hinhanh;
            $absolutePath = realpath(__DIR__ . '/../../' . $storedPath);

            if ($absolutePath !== false && file_exists($absolutePath)) {
                $enrollResult = face_service_enroll($employeeCode, $hoten, $absolutePath);
                if ($enrollResult['success']) {
                    $stmtFace = $conn->prepare("INSERT INTO nhanvien_face (idnv, image_path, version, active, enrolled_at)
                                                VALUES (?, ?, 1, 1, NOW())
                                                ON DUPLICATE KEY UPDATE image_path = VALUES(image_path),
                                                                        active = VALUES(active),
                                                                        enrolled_at = NOW(),
                                                                        version = version + 1");
                    if ($stmtFace) {
                        $stmtFace->bind_param('is', $employeeId, $storedPath);
                        $stmtFace->execute();
                        $stmtFace->close();
                    }
                } else {
                    $faceMessage = "\\nLưu ý: không đăng ký được khuôn mặt - " . $enrollResult['message'];
                }
            } else {
                $faceMessage = "\\nLưu ý: không tìm thấy file ảnh để đăng ký khuôn mặt.";
            }
        }

        echo "<script>alert('Thêm nhân viên thành công!" . $faceMessage . "'); window.location.href='index.php?page=dsnhanvien';</script>";
    } else {
        echo "<script>alert('Lỗi khi thêm nhân viên: " . mysqli_error($conn) . "');</script>";
    }
    $stmt->close();
}

// Lấy danh sách vai trò
$sql = "SELECT idvaitro, tenvaitro FROM vaitro";
$result = mysqli_query($conn, $sql);
?>

<div class="container mb-5">
    <div class="text-center">
        <h1><b>Thêm nhân viên</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form class="" action="" method="POST" enctype="multipart/form-data">
                <div class="form-group mb-3">
                    <label for="hoten" class="form-label">Họ tên</label>
                    <input type="text" class="form-control" id="hoten" name="hoten"
                        placeholder="Nhập họ tên nhân viên" required />
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Giới tính</label><br />
                    <div class="d-flex">
                        <div class="form-check me-3">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNam" value="Nam" />
                            <label class="form-check-label" for="gioitinhNam">Nam</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNu" value="Nữ" checked />
                            <label class="form-check-label" for="gioitinhNu">Nữ</label>
                        </div>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label for="sdt" class="form-label">Số điện thoại</label>
                    <input type="text" class="form-control" id="sdt" name="sodienthoai" placeholder="Số điện thoại" required />
                </div>
                <div class="form-group mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="abc@gmail.com" required />
                </div>
                <div class="form-group mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" class="form-control" id="password" name="password" required />
                </div>
                <div class="form-group mb-3">
                    <label for="diachi" class="form-label">Địa chỉ</label>
                    <input type="text" class="form-control" id="diachi" name="diachi"
                        placeholder="Nhập địa chỉ nhân viên" required />
                </div>
                <div class="form-group mb-3">
                    <label for="luong" class="form-label">Lương</label>
                    <input type="number" class="form-control" id="luong" name="luong"
                        placeholder="Nhập lương nhân viên" required />
                </div>
                <div class="form-group mb-3">
                    <label for="vaitro" class="form-label">Chức vụ</label>
                    <select class="form-select" id="vaitro" name="vaitro" required>
                        <option value="" disabled selected hidden>Chọn</option>
                        <?php
                        if ($result) {
                            $danhmucList = mysqli_fetch_all($result, MYSQLI_ASSOC);
                            foreach ($danhmucList as $dm) {
                                echo '<option value="' . $dm['idvaitro'] . '">' . htmlspecialchars($dm['tenvaitro'], ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group mb-3">
                    <label for="hinhanh" class="form-label">Hình ảnh</label>
                    <input type="file" class="form-control" id="hinhanh" name="hinhanh" required />
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="themNV">Thêm</button>
                    <a href="index.php?page=dsnhanvien" class="btn btn-secondary" name="huy">Hủy</a>
                </div>
            </form>
            <?php
            // if (isset($_POST['themNV'])) {
            //     $hoten = $_POST['hoten'];
            //     $gioitinh = $_POST['gioitinh'];
            //     $chucvu = $_POST['vaitro'];
            //     $sdt = $_POST['sdt'];
            //     $email = $_POST['email'];
            //     $diachi = $_POST['diachi'];
            //     $password = md5($_POST['password']); // Mã hóa mật khẩu bằng MD5
            //     $luong = $_POST['luong'];
            //     $hinhAnh = $_FILES['hinhanh'];
            //     $file_hinh = $hinhAnh['name'] ?? '';

            //     if ($conn) {
            //         move_uploaded_file($hinhAnh['tmp_name'], '../assets/img/' . $file_hinh);
            //         $stmt = $conn->prepare("INSERT INTO nhanvien (HoTen, GioiTinh, idvaitro, SoDienThoai, Email, DiaChi, Luong, HinhAnh, password)
            //                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            //         $stmt->bind_param("ssisssdss", $hoten, $gioitinh, $chucvu, $sdt, $email, $diachi, $luong, $file_hinh, $password);
            //         if ($stmt->execute()) {
            //             echo "<script>alert('Thêm thành công'); window.location.href='index.php?page=dsnhanvien'</script>";
            //         } else {
            //             echo "<script>alert('Thêm thất bại'); window.location.href='index.php?page=themnv'</script>";
            //         }
            //     }
            // }
            ?>
        </div>
    </div>
</div>
