<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('sua khach hang', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

mysqli_set_charset($conn, "utf8mb4");

// Lấy thông tin khach hang cần sửa
if (isset($_GET['idKH'])) {
    $idkh = $_GET['idKH'];
    $query = "SELECT * FROM khachhang WHERE idKH = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $idkh);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && $result->num_rows > 0) {
        $khachhang = mysqli_fetch_assoc($result);
    } else {
        echo "<script>alert('Không tìm thấy khách hàng!'); window.location.href='index.php?page=dskhachhang';</script>";
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Xử lý cập nhật khach hang
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenKH = $_POST['tenKH'];
    $sodienthoai = $_POST['sdt'];
    $email = $_POST['email'];
    $ngaysinh = $_POST['ngaysinh'];
    $gioitinh = $_POST['gioitinh'];

// Xử lý upload hình ảnh mới
if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
    $file_hinh = $_FILES['hinhanh']['name'];
    $destination = 'assets/img/' . $file_hinh;
    
    if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $destination)) {
        // Cập nhật với hình ảnh mới
        $sql_update = "UPDATE khachhang SET hinhanh=?, tenKH=?, sodienthoai=?, email=?, ngaysinh=?, gioitinh=? WHERE idKH=?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "ssssssi", $file_hinh,$tenKH, $sodienthoai, $email, $ngaysinh, $gioitinh, $idkh);
    } else {
        echo "<script>alert('Lỗi khi upload hình ảnh!');</script>";
        exit;
    }
} else {
    // Cập nhật không có hình ảnh mới
    $sql_update = "UPDATE khachhang SET tenKH=?, sodienthoai=?, email=?, ngaysinh=?, gioitinh=? WHERE idKH=?";
        $stmt_update = mysqli_prepare($conn, $sql_update);
        mysqli_stmt_bind_param($stmt_update, "sssssi", $tenKH, $sodienthoai, $email, $ngaysinh, $gioitinh, $idkh);
}

if (mysqli_stmt_execute($stmt_update)) {
    echo "<script>alert('Cập nhật khách hàng thành công!'); window.location.href='index.php?page=dskhachhang';</script>";
} else {
    echo "<script>alert('Lỗi khi cập nhật khách hàng: " . mysqli_error($conn) . "');</script>";
}
mysqli_stmt_close($stmt_update);
}
?>

    

?>
<div class="container mb-5">
    <div class="text-center">
        <h1><b>Sửa thông tin khách hàng</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form class="" action="" method="POST" enctype="multipart/form-data">
                <div class="text-center">
                    <img src='assets/img/<?php echo htmlspecialchars($khachhang['hinhanh']); ?>' style='width:100px' alt="Hình ảnh khách hàng">
                </div>
                <div class="form-group">
                    <label for="hinhanh">Hình ảnh</label>
                    <input type="file" class="form-control-file" id="hinhanh" name="hinhanh" accept="image/*" />
                </div>
                <div class="form-group">
                    <label for="tenKH">Họ tên</label>
                    <input type="tenKH" class="form-control" id="tenKH" name="tenKH" value="<?php echo $khachhang['tenKH'] ?>"
                        required />
                    <!-- <small id="emailHelp2" class="form-text text-muted">We'll never share your email with anyone
                    else.</small> -->
                </div>
                <div class="form-group">
                    <label for="sdt">Số điện thoại </label>
                    <input type="tel" class="form-control" id="sdt" name="sdt" placeholder="Số điện thoại"
                        value="<?php echo $khachhang['sodienthoai'] ?>"  required />
                </div>

                <div class="form-group">
                    <label for="email">Email </label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="abc@gmail.com"
                        value="<?php echo $khachhang['email'] ?>" required />
                </div>
                <div class="form-group">
                    <label for="ngaysinh">Ngày sinh </label>
                    <input type="date" class="form-control" id="ngaysinh" name="ngaysinh"
                        value="<?php echo $khachhang['ngaysinh'] ?>" required />
                </div>
                <div class="form-group">
                    <label>Giới tính </label><br />
                    <div class="d-flex">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNam" value="Nam"
                                <?php if ($khachhang['gioitinh'] == "Nam")
                                    echo "checked"; ?> />
                            <label class="form-check-label" for="gioitinhNam">
                                Nam
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNu" value="Nữ"
                                <?php if ($khachhang['gioitinh'] == "Nữ")
                                    echo "checked"; ?> />
                            <label class="form-check-label" for="gioitinhNu">
                                Nữ
                            </label>
                        </div>
                    </div>
                </div>


                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="suaKH">Sửa </button>
                    <a href="index.php?page=dskhachhang" class="btn btn-secondary" name="huy">Hủy</a>

                </div>
            </form>
            
        </div>
    </div>
</div>