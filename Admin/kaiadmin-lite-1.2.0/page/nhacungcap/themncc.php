<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('them nhacungcap', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
// Xử lý thêm nhà cung cấp
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenncc = $_POST['tenncc'];
    $sodienthoai = $_POST['sdt'];
    $email = $_POST['email'];
    $diachi = $_POST['diachi'];

    $stmt = $conn->prepare("INSERT INTO nhacungcap (tennhacungcap, sodienthoai, email, diachi) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $tenncc, $sodienthoai, $email, $diachi);

    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Thêm nhà cung cấp thành công!'); window.location.href='index.php?page=dsnhacungcap';</script>";
    } else {
        echo "<script>alert('Lỗi khi thêm nhà cung cấp: " . mysqli_error($conn) . "');</script>";
    }
    mysqli_stmt_close($stmt);
}

?>
<div class="container mb-5">
    <div class="text-center">
        <h1><b>Thêm nhà cung cấp</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form class="" action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="tenncc">Tên nhà cung cấp</label>
                    <input type="text" class="form-control" id="tenncc" name="tenncc" required />
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
                    <label for="diachi">Địa chỉ </label>
                    <input type="text" class="form-control" id="diachi" name="diachi" required />
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="themncc">Thêm </button>
                    <a href="index.php?page=dsnhacungcap" class="btn btn-secondary" name="huy">Hủy</a>

                </div>
            </form>
            
        </div>
    </div>
</div>