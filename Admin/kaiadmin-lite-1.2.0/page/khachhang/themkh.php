<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('them khach hang', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}


mysqli_set_charset($conn, "utf8mb4");

// Xử lý thêm tồn kho
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenKH = $_POST['tenKH'];
    $sodienthoai = $_POST['sdt'];
    $email = $_POST['email'];
    $gioitinh = $_POST['gioitinh'];
    $ngaysinh = $_POST['ngaysinh'];

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
            } else {
                echo "<script>alert('Lỗi khi upload hình ảnh! Vui lòng kiểm tra lại quyền thư mục.');</script>";
                exit;
            }
        } else {
            echo "<script>alert('Chỉ cho phép upload file ảnh có định dạng: jpg, jpeg, png, gif');</script>";
            exit;
        }
    }
    // Thêm tồn kho vào CSDL
    if ($hinhanh) {
        $stmt = $conn->prepare("INSERT INTO khachhang (hinhanh, tenKH, sodienthoai, email, ngaysinh, gioitinh) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $hinhanh, $tenKH, $sodienthoai, $email, $ngaysinh, $gioitinh);

    } else {
        $stmt = $conn->prepare("INSERT INTO khachhang (tenKH, sodienthoai, email, ngaysinh, gioitinh) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $tenKH, $sodienthoai, $email, $ngaysinh, $gioitinh);

    }
    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Thêm khách hàng thành công!'); window.location.href='index.php?page=dskhachhang';</script>";
    } else {
        echo "<script>alert('Lỗi khi thêm khách hàng: " . mysqli_error($conn) . "');</script>";
    }
    mysqli_stmt_close($stmt);
}
?>
<div class="container mb-5">
    <div class="text-center">
        <h1><b>Thêm khách hàng</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form class="" action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="hinhanh">Hình ảnh</label>
                    <input type="file" class="form-control" id="hinhanh" name="hinhanh" />
                </div>
                <div class="form-group">
                    <label for="tenKH">Họ tên</label>
                    <input type="tenKH" class="form-control" id="tenKH" name="tenKH" required />
                </div>
                <div class="form-group">
                    <label for="sdt">Số điện thoại </label>
                    <input type="tel" class="form-control" id="sdt" name="sdt" placeholder="Số điện thoại"
                        pattern="[0-9]+" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required />
                </div>

                <div class="form-group">
                    <label for="email">Email </label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="abc@gmail.com"
                        required />
                </div>
                <div class="form-group">
                    <label for="ngaysinh">Ngày sinh </label>
                    <input type="date" class="form-control" id="ngaysinh" name="ngaysinh" required />
                </div>
                <div class="form-group">
                    <label>Giới tính </label><br />
                    <div class="d-flex">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNam" value="Nam" />
                            <label class="form-check-label" for="gioitinhNam">
                                Nam
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="gioitinh" id="gioitinhNu" value="Nữ"
                                checked />
                            <label class="form-check-label" for="gioitinhNu">
                                Nữ
                            </label>
                        </div>
                    </div>
                </div>


                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="themKH">Thêm </button>
                    <a href="index.php?page=dskhachhang" class="btn btn-secondary" name="huy">Hủy</a>

                </div>
            </form>
            
        </div>
    </div>
</div>