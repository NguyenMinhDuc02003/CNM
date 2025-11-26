<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('sua nhacungcap', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
// Lấy thông tin nhà cung cấp cần sửa
if (isset($_GET['idncc'])) {
    $idncc = $_GET['idncc'];
    $query = "SELECT * FROM nhacungcap WHERE idncc = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $idncc);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result && $result->num_rows > 0) {
        $nhacungcap = mysqli_fetch_assoc($result);
    } else {
        echo "<script>alert('Không tìm thấy nhà cung cấp!'); window.location.href='index.php?page=dsnhacungcap';</script>";
        exit;
    }
    mysqli_stmt_close($stmt);
}

// Xử lý cập nhật nhà cung cấp
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tenncc = $_POST['tenncc'];
    $sodienthoai = $_POST['sdt'];
    $email = $_POST['email'];
    $diachi = $_POST['diachi'];

    $sql_update = "UPDATE nhacungcap SET tennhacungcap=?, sodienthoai=?, email=?, diachi=? WHERE idncc=?";
    $stmt_update = mysqli_prepare($conn, $sql_update);
    mysqli_stmt_bind_param($stmt_update, "ssssi", $tenncc, $sodienthoai, $email, $diachi, $idncc);

    if (mysqli_stmt_execute($stmt_update)) {
        echo "<script>alert('Cập nhật nhà cung cấp thành công!'); window.location.href='index.php?page=dsnhacungcap';</script>";
    } else {
        echo "<script>alert('Lỗi khi cập nhật nhà cung cấp: " . mysqli_error($conn) . "');</script>";
    }
    mysqli_stmt_close($stmt_update);
}

?>
<div class="container mb-5">
    <div class="text-center">
        <h1><b>Sửa thông tin nhà cung cấp</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form class="" action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="tenncc">Tên nhà cung cấp</label>
                    <input type="text" class="form-control" id="tenncc" name="tenncc" value="<?php echo $nhacungcap['tennhacungcap'] ?>"
                        required />
                </div>
                <div class="form-group">
                    <label for="sdt">Số điện thoại </label>
                    <input type="tel" class="form-control" id="sdt" name="sdt" placeholder="Số điện thoại"
                        value="<?php echo $nhacungcap['sodienthoai'] ?>"  required />
                </div>

                <div class="form-group">
                    <label for="email">Email </label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="abc@gmail.com"
                        value="<?php echo $nhacungcap['email'] ?>" required />
                </div>
                <div class="form-group">
                    <label for="diachi"> Địa chỉ </label>
                    <input type="text" class="form-control" id="diachi" name="diachi"
                        value="<?php echo $nhacungcap['diachi'] ?>" required />
                </div>


                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="suancc">Sửa </button>
                    <a href="index.php?page=dsnhacungcap" class="btn btn-secondary" name="huy">Hủy</a>

                </div>
            </form>
            
        </div>
    </div>
</div>