<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('them ton kho', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Lấy danh sách loại tồn kho
$queryLoaiTK = "SELECT idloaiTK, tenloaiTK FROM loaitonkho ORDER BY tenloaiTK";
$resultLoaiTK = mysqli_query($conn, $queryLoaiTK);

// Lấy danh sách nhà cung cấp
$queryNCC = "SELECT idncc, tennhacungcap FROM nhacungcap ORDER BY tennhacungcap";
$resultNCC = mysqli_query($conn, $queryNCC);



// Xử lý thêm tồn kho
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tentonkho = $_POST['tentonkho'];
    $idloaiTK = $_POST['loaiTK'];
    $idncc = $_POST['ncc'];
    $soluong = $_POST['soluong'];
    $DonViTinh = $_POST['DonViTinh'];

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
        $stmt = $conn->prepare("INSERT INTO tonkho (tentonkho, soluong, DonViTinh, idloaiTK, idncc, hinhanh) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sisiis", $tentonkho, $soluong, $DonViTinh, $idloaiTK, $idncc, $hinhanh);
    } else {
        $stmt = $conn->prepare("INSERT INTO tonkho (tentonkho, soluong, DonViTinh, idloaiTK, idncc) 
                           VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisii", $tentonkho, $soluong, $DonViTinh, $idloaiTK, $idncc);
    }
    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Thêm tồn kho thành công!'); window.location.href='index.php?page=dstonkho';</script>";
    } else {
        echo "<script>alert('Lỗi khi thêm tồn kho: " . mysqli_error($conn) . "');</script>";
    }
    mysqli_stmt_close($stmt);
}
?>

<div class="container mb-5">
    <div class="text-center">
        <h1><b>Thêm tồn kho</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="hinhanh">Hình ảnh</label>
                    <input type="file" class="form-control" id="hinhanh" name="hinhanh" />
                </div>
                <div class="form-group">
                    <label for="tentonkho">Tên tồn kho</label>
                    <input type="text" class="form-control" id="tentonkho" name="tentonkho" required />
                </div>
                <div class="form-group">
                    <label for="soluong">Số lượng</label>
                    <input type="number" class="form-control" id="soluong" name="soluong" required />
                </div>
                <div class="form-group">
                    <label for="DonViTinh">Đơn vị</label>
                    <input type="text" class="form-control" id="DonViTinh" name="DonViTinh" required />
                </div>
                <div class="form-group">
                    <label for="loaiTK">Loại tồn kho</label>
                    <select class="form-select" id="loaiTK" name="loaiTK" required>
                        <option value="">Chọn loại</option>
                        <?php
                        while ($dm = $resultLoaiTK->fetch_assoc()) {
                            echo '<option value="' . $dm['idloaiTK'] . '">' . htmlspecialchars($dm['tenloaiTK']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ncc">Nhà cung cấp</label>
                    <select class="form-select" id="ncc" name="ncc" required>
                        <option value="">Chọn nhà cung cấp</option>
                        <?php
                        while ($dm = $resultNCC->fetch_assoc()) {
                            echo '<option value="' . $dm['idncc'] . '">' . htmlspecialchars($dm['tennhacungcap']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="themtonkho">Thêm</button>
                    <a href="index.php?page=dstonkho" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
            <?php
            // if (isset($_POST['themtonkho'])) {
            //     $tentonkho = $_POST['tentonkho'];
            //     $soluong = $_POST['soluong'];
            //     $DonViTinh = $_POST['DonViTinh'];
            //     $loaiTK = $_POST['loaiTK'];
            //     $ncc = $_POST['ncc'];
            //     $stmt = $conn->prepare("INSERT INTO tonkho (tentonkho, soluong, DonViTinh, idloaiTK, idncc) VALUES (?, ?, ?, ?, ?)");
            //     $stmt->bind_param("sisii", $tentonkho, $soluong, $DonViTinh, $loaiTK, $ncc);
            //     if ($stmt->execute()) {
            //         echo "<script>alert('Thêm thành công'); window.location.href='index.php?page=dstonkho';</script>";
            //     } else {
            //         echo "<script>alert('Thêm thất bại: " . addslashes($conn->error) . "'); window.location.href='index.php?page=themtonkho';</script>";
            //     }
            // }
            ?>
        </div>
    </div>
</div>
<?php mysqli_close($conn); ?>