<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem nhan vien', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
// Lấy id thực đơn
$idnv = isset($_GET['idnv']) ? intval($_GET['idnv']) : 0;

$nv = null;
if ($conn && $idnv > 0) {
    $stmt = $conn->prepare("SELECT n.*, v.tenvaitro FROM nhanvien n LEFT JOIN vaitro v ON n.idvaitro = v.idvaitro WHERE n.idnv = ?");
    if ($stmt) {
        $stmt->bind_param("i", $idnv);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $nv = $result->fetch_assoc();
        }
        $stmt->close();
    }
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-10">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <a href="index.php?page=dsnhanvien" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>
                
            </div>

            <div class="card shadow-sm border-0">
                <?php if (!$nv) { ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-2x text-muted mb-3"></i>
                    <p class="text-muted mb-0">Không tìm thấy nhân viên.</p>
                </div>
                <?php } else { ?>
                <div class="card-body p-4">
                    <div class="row g-3 align-items-start mb-3 ">
                        <div class="col-4 col-sm-3 text-center d-flex justify-content-center">
                            <img src="assets/img/<?php echo htmlspecialchars($nv['HinhAnh']); ?>" alt="Hình ảnh nhân viên" class="mx-auto d-block" style="width:200px;height:200px;object-fit:cover;border-radius:8px;">
                        </div>
                        <div class="col-8 col-sm-9">
                            <div class="mb-3">
                                <label class="form-label">Họ tên</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nv['HoTen']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Giới tính</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nv['GioiTinh']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Chức vụ</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars(isset($nv['tenvaitro']) && $nv['tenvaitro'] !== '' ? $nv['tenvaitro'] : ($nv['ChucVu'] ?? '')); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Số điện thoại</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nv['SoDienThoai']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nv['Email']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Địa chỉ</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nv['DiaChi']); ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Lương</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($nv['Luong']); ?>" readonly>
                            </div>
                            <a href="index.php?page=suanv&idnv=<?php echo (int)$nv['idnv']; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-pencil-alt" style="font-size: 18px;"></i>
                                <span class="ms-1">Sửa</span>
                            </a>
                        </div>
                    </div>
                </div>
                <?php } ?>
            
            </div>
        </div>
    </div>
</div>