<?php
include("class/clsconnect.php");

include_once 'includes/config_permission.php';

// function redirectToMonAnList()
// {
//     $target = "index.php?page=dsmonan";
//     if (!headers_sent()) {
//         header("Location: $target");
//     } else {
//         echo "<script>window.location.href='$target';</script>";
//         echo "<noscript><meta http-equiv='refresh' content=\"0;url=$target\"></noscript>";
//     }
//     exit;
// }
// Kiểm tra quyền truy cập
if (!hasPermission('xem mon an', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}


if (isset($_GET['idmonan'])) {
    $idmonan = $_GET['idmonan'];
}
// Giá trị mặc định
$lower = '';

// Xử lý Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'approve' && isset($_POST['idmonan'])) {
        $id = (int)$_POST['idmonan'];
        $stmt = $conn->prepare("UPDATE monan SET TrangThai = 'approved' WHERE idmonan = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo "<script>alert('Xác nhận thành công'); window.location.href='index.php?page=dsmonan'</script>";
    }
    if (isset($_POST['action']) && $_POST['action'] === 'reject' && isset($_POST['idmonan'])) {
        $id = (int)$_POST['idmonan'];
        $reason = trim($_POST['reason'] ?? '');

        // Check if column GhiChu exists
        $colExists = false;
        $check = $conn->query("SHOW COLUMNS FROM monan LIKE 'GhiChu'");
        if ($check && $check->num_rows > 0) {
            $colExists = true;
        } else {
            // try lowercase
            $check2 = $conn->query("SHOW COLUMNS FROM monan LIKE 'ghichu'");
            if ($check2 && $check2->num_rows > 0) {
                $colExists = true;
            }
        }

        if ($colExists) {
            $stmt = $conn->prepare("UPDATE monan SET TrangThai = 'rejected', GhiChu = ? WHERE idmonan = ?");
            $stmt->bind_param("si", $reason, $id);
            $stmt->execute();
        } else {
            // Fallback: append reason to mota field
            $stmt = $conn->prepare("UPDATE monan SET TrangThai = 'rejected', mota = CONCAT(mota, '\n\nLý do từ chối: ', ?) WHERE idmonan = ?");
            $stmt->bind_param("si", $reason, $id);
            $stmt->execute();
        }


        echo "<script>alert('Lưu thành công'); window.location.href='index.php?page=dsmonan'</script>";
    }
}

$sql = "SELECT m.*, d.tendanhmuc FROM monan m JOIN danhmuc d ON m.iddm = d.iddm WHERE idmonan = $idmonan";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hinhanh = $row['hinhanh'];
        $tenmonan = $row['tenmonan'];
        $mota = $row['mota'];
        $gia = $row['DonGia'];
        $donvitinh = $row['DonViTinh'] ?? '';
        $tendanhmuc = $row['tendanhmuc'];
        $ghichu = $row['ghichu'];
                // Lấy trạng thái để quyết định hiển thị nút (pending -> hiển thị)
        $lower = strtolower(trim($row['TrangThai'] ?? ''));
    }
}
?>
<div class="container mb-5 ">
    <div class="m-2">
        <a href="index.php?page=dsmonan" class="btn btn-secondary flex-fill">
            <i class="fas fa-arrow-left me-2"></i>Quay lại
        </a>
    </div>
                            
                        
    <div class="row m-3 justify-content-center">
        <div class="col-7">
        
            <div class="card shadow h-100">
                <div class="text-end m-2">
                     <?php if ($lower === 'pending' || $lower === 'approved') { ?>
                        <a href="index.php?page=suamonan&idmonan=<?php echo $idmonan; ?>" style='color:orange'>
                            <i class="fas fa-pencil-alt" style="font-size: 25px;"></i>
                        </a>
                    <?php } ?>
                </div>
                <div class="card-body">
                    <form>
                        <!-- Tên món ăn -->
                        <div class="text-center mb-4">
                            <h1 class="text-primary fw-bold"><?php echo htmlspecialchars($tenmonan, ENT_QUOTES, 'UTF-8'); ?></h4>
                        </div>

                        <!-- Hình ảnh -->
                        <div class="text-center mb-4">
                            <img src='assets/img/<?php echo $hinhanh ?>' 
                                 class="img-fluid rounded" 
                                 style='max-width: 300px; height: 200px; object-fit: cover;'
                                 alt="<?php echo htmlspecialchars($tenmonan, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <!-- Danh mục -->
                        <div class="mb-3">
                            <label for="danhmuc" class="form-label fw-semibold">Danh mục</label>
                            <input type="text" class="form-control" 
                                   id="danhmuc" name="danhmuc" 
                                   value="<?php echo htmlspecialchars($tendanhmuc, ENT_QUOTES, 'UTF-8'); ?>" 
                                   readonly />
                        </div>

                        <!-- Đơn vị tính -->
                        <div class="mb-3">
                            <label for="DVT" class="form-label fw-semibold">Đơn vị tính</label>
                            <input type="text" class="form-control" 
                                   id="DVT" name="DVT" 
                                   value="<?php echo htmlspecialchars($donvitinh, ENT_QUOTES, 'UTF-8'); ?>" 
                                   readonly />
                        </div>

                        <!-- Giá -->
                        <div class="mb-3">
                            <label for="gia" class="form-label fw-semibold">Giá</label>
                            <input type="text" class="form-control" 
                                   id="gia" name="gia" 
                                   value="<?php echo number_format($gia, 0, ',', '.') . ' VNĐ'; ?>" 
                                   readonly />
                        </div>

                        <!-- Mô tả -->
                        <div class="mb-4">
                            <label for="mota" class="form-label fw-semibold">Mô tả</label>
                            <textarea class="form-control" 
                                      id="mota" name="mota" rows="4" 
                                      readonly><?php echo htmlspecialchars($mota, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                       
                    </form>
                </div>
                 
            </div>

                <!-- Modal lý do từ chối -->
                <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="post" id="rejectForm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Lý do từ chối</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="reject_reason" class="form-label">Lý do</label>
                                        <textarea class="form-control" id="reject_reason" name="reason" rows="4" required></textarea>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="idmonan" value="<?= htmlspecialchars($idmonan) ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                                    <button type="submit" class="btn btn-danger">Lưu</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
        </div>
        <div class="col-5">
            <div class="card shadow ">
                <div class="card-body">
                
                    <form>
                        <div class="text-center mb-4">
                            <h2 class="">Thành phần</h4>
                        </div>
                        
                        <div class="mb-3">
                            <?php
                            $sql_thanhphan = "SELECT tk.tentonkho, tp.dinhluong 
                                             FROM thanhphan tp 
                                             JOIN monan m ON tp.idmonan = m.idmonan 
                                             JOIN tonkho tk ON tp.matonkho = tk.matonkho 
                                             WHERE m.idmonan = $idmonan";
                            $result_thanhphan = $conn->query($sql_thanhphan);
                            
                            if ($result_thanhphan && $result_thanhphan->num_rows > 0) {
                                echo "<div class='list-group d-flex flex-column align-items-center'>";
                                while ($row_thanhphan = mysqli_fetch_assoc($result_thanhphan)) {
                                    echo "<div class='list-group-item d-flex justify-content-between align-items-center' style='width: 70%; margin: 5px 0;'>";
                                    echo "<span class='fw-semibold' style='font-size:20px;'>" . htmlspecialchars($row_thanhphan['tentonkho'], ENT_QUOTES, 'UTF-8') . "</span>";
                                    echo "<span class='rounded-pill' style='font-size:20px'>" . $row_thanhphan['dinhluong'] . " gam</span>";
                                    echo "</div>";
                                }
                                echo "</div>";
                            } else {
                                echo "<div class='alert alert-info text-center'>";
                                echo "<i class='fas fa-info-circle me-2'></i>";
                                echo "Chưa có thông tin thành phần nguyên liệu";
                                echo "</div>";
                            }
                            ?>
                        </div>
                    </form>
                </div>
                
            </div>
            <div class="card shadow ">
                <div class="card-body">
                    <?php
                        // Nếu trạng thái là bị từ chối, hiển thị ghi chú (GhiChu)
                        $isRejected = (strpos($lower, 'rejected') !== false) ;
                        if ($isRejected) {
                            if ($ghichu !== '') {
                                echo '<div class="mb-3">';
                                echo '<label class="form-label fw-semibold">Lý do</label>';
                                echo '<textarea class="form-control" rows="3" readonly>' . htmlspecialchars($ghichu, ENT_QUOTES, 'UTF-8') . '</textarea>';
                                echo '</div>';
                            }
                        }
                    ?>
                </div>
            </div>
            <div class ="text-center mt-3">
                <?php if (strpos($lower, 'pending') !== false): ?>
                    <form method="post" style="display:inline-block; margin-right:8px;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="idmonan" value="<?= htmlspecialchars($idmonan) ?>">
                        <!-- Anchor bao quanh để fallback redirect về dsmonan nếu JS bị tắt; onclick submit form khi JS bật -->
                        <a href="index.php?page=dsmonan" class="btn btn-primary" onclick="event.preventDefault(); this.closest('form').submit();">Xác nhận</a>
                    </form>

                    <!-- Reject button opens modal to enter reason -->
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Từ chối</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
