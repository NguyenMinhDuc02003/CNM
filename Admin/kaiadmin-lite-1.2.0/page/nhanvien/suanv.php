<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';
include_once 'includes/role_helper.php';

// Kiểm tra quyền truy cập
if (!hasPermission('sua nhan vien', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Lấy thông tin nhân viên cần sửa
if (isset($_GET['idnv'])) {
    $idnv = $_GET['idnv'];
    $sql = "SELECT * FROM nhanvien JOIN vaitro ON nhanvien.idvaitro = vaitro.idvaitro WHERE idnv = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $idnv);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $result->num_rows > 0) {
        $nhanvien = mysqli_fetch_assoc($result);
    } else {
        echo "<script>alert('Không tìm thấy nhân viên!'); window.location.href='index.php?page=dsnhanvien';</script>";
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    echo "<script>alert('Thiếu thông tin nhân viên!'); window.location.href='index.php?page=dsnhanvien';</script>";
    exit;
}

// Lấy danh sách vai trò
$sql_vaitro = "SELECT idvaitro, tenvaitro FROM vaitro";
$result_vaitro = mysqli_query($conn, $sql_vaitro);

// Xử lý cập nhật nhân viên
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $hoten = $_POST['hoten'];
    $gioitinh = $_POST['gioitinh'];
    $vaitro = $_POST['idvaitro'];
    $sodienthoai = $_POST['sodienthoai'];
    $email = $_POST['email'];
    $diachi = $_POST['diachi'];
    $luong = $_POST['luong'];
    $chucVu = resolveRoleLabel($conn, (int) $vaitro);
    
    // Xử lý upload hình ảnh mới
    if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
        $file_hinh = $_FILES['hinhanh']['name'];
        $destination = 'assets/img/' . $file_hinh;
        
        if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $destination)) {
            // Cập nhật với hình ảnh mới
            $sql_update = "UPDATE nhanvien SET HinhAnh = ?, HoTen=?, GioiTinh=?, ChucVu=?, idvaitro=?, SoDienThoai=?, Email=?, DiaChi=?, Luong=? WHERE idnv=?";
            $stmt_update = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt_update, "ssssssssss", $file_hinh, $hoten, $gioitinh, $chucVu, $vaitro, $sodienthoai, $email, $diachi, $luong, $idnv);
        } else {
            echo "<script>alert('Lỗi khi upload hình ảnh!');</script>";
            exit;
        }
    } else {
        // Cập nhật không có hình ảnh mới
        $sql_update = "UPDATE nhanvien SET HoTen=?, GioiTinh=?, ChucVu=?, idvaitro=?, SoDienThoai=?, Email=?, DiaChi=?, Luong=? WHERE idnv=?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sssssssss", $hoten, $gioitinh, $chucVu, $vaitro, $sodienthoai, $email, $diachi, $luong, $idnv);
    }
    
    if (mysqli_stmt_execute($stmt_update)) {
        echo "<script>alert('Cập nhật nhân viên thành công!'); window.location.href='index.php?page=dsnhanvien';</script>";
    } else {
        echo "<script>alert('Lỗi khi cập nhật nhân viên: " . mysqli_error($conn) . "');</script>";
    }
    mysqli_stmt_close($stmt_update);
}
?>
<div class="container mb-5">
    <div class="text-center">
        <h1><b>Sửa thông tin nhân viên</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form class="" action="" method="POST" enctype="multipart/form-data">
                <div class="text-center">
                    <img src='assets/img/<?php echo htmlspecialchars($nhanvien['HinhAnh']); ?>' style='width:100px' alt="Hình ảnh nhân viên">
                </div>

                <div class="form-group">
                    <label for="hinhanh">Hình ảnh</label>
                    <input type="file" class="form-control-file" id="hinhanh" name="hinhanh" accept="image/*" />
                </div>
                <div class="form-group">
                    <label for="hoten">Họ tên</label>
                    <input type="text" class="form-control" id="hoten" name="hoten"
                        value="<?php echo htmlspecialchars($nhanvien['HoTen']); ?>" required />
                </div>

                <div class="form-group">
                    <label>Giới tính</label><br />
                    <div class="d-flex">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNam" value="Nam"
                                <?php if ($nhanvien['GioiTinh'] == "Nam") echo "checked"; ?> required />
                            <label class="form-check-label" for="gioitinhNam">Nam</label>
                        </div>
                        <div class="form-check ms-3">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNu" value="Nữ"
                                <?php if ($nhanvien['GioiTinh'] == "Nữ") echo "checked"; ?> required />
                            <label class="form-check-label" for="gioitinhNu">Nữ</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="chucvu">Chức vụ</label>
                    <select class="form-select" id="chucvu" name="idvaitro" required>
                        <?php
                        mysqli_data_seek($result_vaitro, 0);
                        while ($dm = mysqli_fetch_assoc($result_vaitro)) {
                            $selected = ($nhanvien['idvaitro'] == $dm['idvaitro']) ? 'selected' : '';
                            echo '<option value="' . $dm['idvaitro'] . '" ' . $selected . '>' . htmlspecialchars($dm['tenvaitro']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="sdt">Số điện thoại</label>
                    <input type="tel" class="form-control" id="sdt" name="sodienthoai" placeholder="Số điện thoại"
                        value="<?php echo htmlspecialchars($nhanvien['SoDienThoai']); ?>" required />
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="abc@gmail.com"
                        value="<?php echo htmlspecialchars($nhanvien['Email']); ?>" required />
                </div>
                <div class="form-group">
                    <label for="diachi">Địa chỉ</label>
                    <input type="text" class="form-control" id="diachi" name="diachi"
                        placeholder="Nhập địa chỉ nhân viên" value="<?php echo htmlspecialchars($nhanvien['DiaChi']); ?>"
                        required />
                </div>
                <div class="form-group">
                    <label for="luong">Lương</label>
                    <input type="number" class="form-control" id="luong" name="luong" placeholder="Nhập lương nhân viên"
                        value="<?php echo htmlspecialchars($nhanvien['Luong']); ?>" required />
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="suaNV">Sửa</button>
                    <a href="index.php?page=dsnhanvien" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
