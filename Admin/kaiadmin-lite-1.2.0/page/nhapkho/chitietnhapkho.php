<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem nhap kho', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

$nhapkho = null;
$chiTietNhapKho = [];
$formatCurrency = function ($value) {
    if ($value === null || $value === '') {
        return '0';
    }
    return number_format((float)$value, 0, ',', '.');
};

//Lấy thông tin đơn nhập kho 
if (isset($_GET['manhapkho'])) {
    $manhapkho = $_GET['manhapkho'];
    $query = "SELECT nk.*, ctnk.*, ncc.tennhacungcap, tk.tentonkho FROM nhapkho nk 
            JOIN chitietnhapkho ctnk ON nk.manhapkho = ctnk.manhapkho 
            JOIN nhacungcap ncc ON nk.idncc = ncc.idncc
            JOIN tonkho tk ON ctnk.matonkho = tk.matonkho
            WHERE nk.manhapkho = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $manhapkho);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $result->num_rows > 0) {
        $nhapkho = null;
        $chiTietNhapKho = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if ($nhapkho === null) {
                $nhapkho = $row;
            }
            $chiTietNhapKho[] = $row;
        }
    } else {
        echo "<script>alert('Không tìm thấy đơn nhập kho!'); window.location.href='index.php?page=nhapkho';</script>";
        exit;
    }
    mysqli_stmt_close($stmt);
} 
?>

<div class="container mb-5">
<div class="d-flex align-items-center justify-content-between mb-3">
                <a href="index.php?page=nhapkho" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
                </a>     
            </div>
  <div class="card shadow m-5">
  <div class="row g-4">
      <div class="col-12 col-lg-8">
        <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Đơn nhập kho</h3>
      </div>
    <div class="card-body">
      <form action="" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="ma_don" class="form-label">Mã đơn nhập kho</label>
          <input type="text" id="ma_don" name="ma_don" class="form-control" value="<?php echo $nhapkho['tennhapkho']; ?>" readonly />
        </div>
        <div class="mb-3">
          <label for="ngay_nhap" class="form-label">Ngày nhập</label>
          <input type="date" id="ngay_nhap" name="ngay_nhap" class="form-control" value="<?php echo $nhapkho['ngaynhap']; ?>" required />
        </div>
        <div class="mb-3">
          <label for="ncc" class="form-label">Nhà cung cấp</label>
          <input type="text" id="ncc" name="ncc" class="form-control" value="<?php echo $nhapkho['tennhacungcap'] ?>" required />
        </div>

        <div class="table-responsive mb-2">
          <table class="table table-bordered align-middle" id="tblNhapKho">
            <thead class="table-light">
              <tr>
                <th style="width:60px" class="text-center">STT</th>
                <th style="min-width:260px">Tên</th>
                <th style="width:140px" class="text-end">Số lượng</th>
                <th style="width:160px" class="text-end">Số lượng thực tế</th>
                <th style="width:160px" class="text-end">Đơn giá (VND)</th>
                <th style="width:180px" class="text-end">Thành tiền (VND)</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $stt = 1;
            if (!empty($chiTietNhapKho)) {
                foreach ($chiTietNhapKho as $chiTiet) {
                    echo '<tr>
                    <th style="width:60px" class="text-center">'.$stt.'</th>
                    <th style="min-width:260px">'. htmlspecialchars($chiTiet['tentonkho']) .'</th>
                    <th style="width:140px" class="text-end">'. htmlspecialchars($chiTiet['soluongdat']) .'</th>
                    <th style="width:160px" class="text-end">'. htmlspecialchars($chiTiet['soluongthucte']) .'</th>
                    <th style="width:160px" class="text-end">'. htmlspecialchars($formatCurrency($chiTiet['dongia'])) .'</th>
                    <th style="width:180px" class="text-end">'. htmlspecialchars($formatCurrency($chiTiet['thanhtien'])) .'</th>
                  </tr>';
                  $stt++;
                }
            }
            ?>
            
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-end me-1 mb-3">
          <div>
            <div class="text-muted small text-end">Tổng tiền (VND)</div>
            <div class="fs-5 fw-bold text-end" id="tongTienText"><?php echo htmlspecialchars($formatCurrency($nhapkho['tongtien'])); ?></div>
          </div>
        </div>

        <div class="mb-3">
          <label for="ghichu" class="form-label">Ghi chú</label>
          <textarea id="ghichu" name="ghichu" class="form-control" rows="3" readonly><?php echo htmlspecialchars($nhapkho['ghichu']); ?></textarea>
        </div>
      </form>
    </div>
  </div>
  <div class="col-4">
      <h3 class="card-header">Chứng từ</h3>
      <div class="card-body text-center">
        <img src='assets/img/<?php echo $nhapkho['hinhanh']?>' style='width:500px'>
      </div>
    </div>
  </div>
</div>

<?php mysqli_close($conn); ?>

