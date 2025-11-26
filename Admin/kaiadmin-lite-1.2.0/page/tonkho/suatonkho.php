<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('sua ton kho', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Lấy thông tin tồn kho cần sửa
if (isset($_GET['matonkho'])) {
    $matonkho = $_GET['matonkho'];
    $query = "SELECT tk.*, ltk.tenloaiTK, n.tennhacungcap
              FROM tonkho tk 
              JOIN loaitonkho ltk ON tk.idloaiTK = ltk.idloaiTK 
              JOIN nhacungcap n ON tk.idncc = n.idncc
              WHERE tk.matonkho = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $matonkho);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $result->num_rows > 0) {
        $tonkho = mysqli_fetch_assoc($result);
    } else {
        echo "<script>alert('Không tìm thấy tồn kho!'); window.location.href='index.php?page=dstonkho';</script>";
        exit;
    }
    mysqli_stmt_close($stmt);
} 
//else {
//     echo "<script>alert('Thiếu thông tin tồn kho!'); window.location.href='index.php?page=dstonkho';</script>";
//     exit;
// }

// Lấy danh sách loại tồn kho
$queryLoaiTK = "SELECT idloaiTK, tenloaiTK FROM loaitonkho ORDER BY tenloaiTK";
$resultLoaiTK = mysqli_query($conn, $queryLoaiTK);

// Lấy danh sách nhà cung cấp
$queryNCC = "SELECT idncc, tennhacungcap FROM nhacungcap ORDER BY tennhacungcap";
$resultNCC = mysqli_query($conn, $queryNCC);

// Xử lý cập nhật tồn kho
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $tentonkho = $_POST['tentonkho'];
    $idloaiTK = $_POST['idloaiTK'];
    $idncc = $_POST['idncc'];
    $soluong = $_POST['soluong'];
    $DonViTinh = $_POST['DonViTinh'];

    // Xử lý upload hình ảnh mới
    if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
    $file_hinh = $_FILES['hinhanh']['name'];
    $destination = 'assets/img/' . $file_hinh;
    
    if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $destination)) {
        // Cập nhật với hình ảnh mới
        $updateQuery = "UPDATE tonkho SET hinhanh=?, tentonkho = ?, soluong = ?, DonViTinh = ?, idloaiTK = ?, idncc = ? WHERE matonkho = ?";
        $stmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($stmt, "ssisiii", $file_hinh, $tentonkho, $soluong, $DonViTinh, $idloaiTK, $idncc, $matonkho);
    } else {
        echo "<script>alert('Lỗi khi upload hình ảnh!');</script>";
        exit;
    }
    } else {
        // Cập nhật không có hình ảnh mới
        $updateQuery = "UPDATE tonkho SET tentonkho = ?, soluong = ?, DonViTinh = ?, idloaiTK = ?, idncc = ? WHERE matonkho = ?";
        $stmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($stmt, "sisiii", $tentonkho, $soluong, $DonViTinh, $idloaiTK, $idncc, $matonkho);
        
    }
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Cập nhật tồn kho thành công!'); window.location.href='index.php?page=dstonkho';</script>";
    } else {
        echo "<script>alert('Lỗi khi cập nhật tồn kho: " . mysqli_error($conn) . "');</script>";
    }
    mysqli_stmt_close($stmt);
}
?>

<div class="container mb-5">
    <div class="text-center">
        <h1><b>Sửa tồn kho</b></h1>
    </div>
    <div class="card-body d-flex justify-content-center">
        <div class="col-md-6 col-lg-4">
            <form action="" method="POST" enctype="multipart/form-data">
            <div class="text-center">
                    <img src='assets/img/<?php echo htmlspecialchars($tonkho['hinhanh']); ?>' style='width:100px' alt="Hình ảnh khách hàng">
                </div>
                <div class="form-group">
                    <label for="hinhanh">Hình ảnh</label>
                    <input type="file" class="form-control-file" id="hinhanh" name="hinhanh" accept="image/*" />
                </div>
                <div class="form-group">
                    <label for="tentonkho">Tên tồn kho</label>
                    <input type="text" class="form-control" id="tentonkho" name="tentonkho" 
                           placeholder="Nhập tên tồn kho" 
                           value="<?php echo htmlspecialchars($tonkho['tentonkho']); ?>" required />
                </div>
                <div class="form-group">
                    <label for="soluong">Số lượng</label>
                    <input type="number" class="form-control" id="soluong" name="soluong" 
                           value="<?php echo htmlspecialchars($tonkho['soluong']); ?>" required />
                </div>
                <div class="form-group">
                    <label for="DonViTinh">Đơn vị</label>
                    <input type="text" class="form-control" id="DonViTinh" name="DonViTinh" 
                           placeholder="cái/lon/kg..." 
                           value="<?php echo htmlspecialchars($tonkho['DonViTinh']); ?>" required />
                </div>
                <div class="form-group">
                    <label for="loaiTK">Loại tồn kho</label>
                    <select class="form-select" id="loaiTK" name="idloaiTK" required>
                        <option value="" disabled>Chọn loại</option>
                        <?php
                        mysqli_data_seek($resultLoaiTK, 0);
                        while ($dm = mysqli_fetch_assoc($resultLoaiTK)) {
                            $selected = ($tonkho['idloaiTK'] == $dm['idloaiTK']) ? 'selected' : '';
                            echo '<option value="' . $dm['idloaiTK'] . '" ' . $selected . '>' . htmlspecialchars($dm['tenloaiTK']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="ncc">Nhà cung cấp</label>
                    <select class="form-select" id="ncc" name="idncc" required>
                        <option value="" disabled>Chọn nhà cung cấp</option>
                        <?php
                        mysqli_data_seek($resultNCC, 0);
                        while ($dm = mysqli_fetch_assoc($resultNCC)) {
                            $selected = ($tonkho['idncc'] == $dm['idncc']) ? 'selected' : '';
                            echo '<option value="' . $dm['idncc'] . '" ' . $selected . '>' . htmlspecialchars($dm['tennhacungcap']) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary" name="suatonkho">Sửa</button>
                    <a href="index.php?page=dstonkho" class="btn btn-secondary">Hủy</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php mysqli_close($conn); ?>
