<!-- Đây là trang chủ -->
<?php
include_once 'includes/config_permission.php';
$erpCssPath = 'assets/css/erp.css';
if (file_exists($erpCssPath)) {
    echo '<link rel="stylesheet" href="' . htmlspecialchars($erpCssPath, ENT_QUOTES) . '">';
}
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 d-flex flex-wrap justify-content-center">
            <!-- Khách hàng -->
             <?php if (hasPermission('xem khach hang', $permissions)) {?>
            <a href="index.php?page=dskhachhang" class="btn btn-light erp-button">
                <i class="fas fa-users"></i>
                <span>Khách hàng</span>
            </a>
            <?php }?>
            <!-- Nhân viên -->
             <?php if (hasPermission('xem nhan vien', $permissions)) {?>
            <a href="index.php?page=dsnhanvien" class="btn btn-light erp-button">
                <i class="fas fa-user-tie"></i>
                <span>Nhân viên</span>
            </a>
            <?php }?>

            <!-- Món ăn -->
             <?php if (hasPermission('xem mon an', $permissions)) {?>
            <a href="index.php?page=dsmonan" class="btn btn-light erp-button">
                <i class="fas fa-utensils"></i>
                <span>Món ăn</span>
            </a>
            <?php }?>

            <!-- Đơn đặt bàn -->
            <a href="index.php?page=dsdatban" class="btn btn-light erp-button ">
                <i class="fas fa-calendar-check"></i>
                <span>Đơn đặt bàn</span>
            </a>

            <!-- QR bàn -->
            <a href="index.php?page=table_qr" class="btn btn-light erp-button">
                <i class="fas fa-qrcode"></i>
                <span>QR bàn</span>
            </a>

            <!-- Đơn hàng -->
            <a href="index.php?page=dsdonhang" class="btn btn-light erp-button">
                <i class="fas fa-shopping-cart"></i>
                <span>Đơn hàng</span>
             </a>

      

            <!-- Hóa đơn -->
            <a href="index.php?page=dshoadon" class="btn btn-light erp-button">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Hóa đơn</span>
            </a>

            <!-- Tồn kho -->
             <?php if (hasPermission('xem ton kho', $permissions)) {?>
            <a href="index.php?page=dstonkho" class="btn btn-light erp-button">
                <i class="fas fa-warehouse"></i>
                <span>Tồn kho</span>
            </a>
            <?php }?>

            <!-- Thống kê -->
            <a href="index.php?page=quanly" class="btn btn-light erp-button">
                <i class="fas fa-chart-bar"></i>
                <span>Thống kê</span>
            </a>
           

            <!-- Thực đơn -->
             <?php if (hasPermission('xem thuc don', $permissions)) {?>
            <a href="index.php?page=dsthucdon" class="btn btn-light erp-button">
                <i class="fas fa-book-open"></i>
                <span>Thực đơn</span>
            </a>
            <?php }?>

            <!-- Nhà cung cấp -->
             <?php if (hasPermission('xem nhacungcap', $permissions)) {?>
            <a href="index.php?page=dsnhacungcap" class="btn btn-light erp-button">
                <i class="far fa-address-book"></i>
                <span>Nhà cung cấp</span>
            </a>
            <?php }?>

            <!-- Lịch làm việc -->
             <?php if (hasPermission('xem lich lam viec', $permissions)) {?>
            <a href="index.php?page=lichlamviec" class="btn btn-light erp-button">
                <i class="fa-regular fa-calendar-days"></i>
                <span>Lịch làm việc</span>
            </a>
            <?php }?>

            <!-- Chấm công -->
            <a href="index.php?page=chamcong" class="btn btn-light erp-button">
                <i class="fa-solid fa-user-check"></i>
                <span>Chấm công</span>
            </a>

            <a href="index.php?page=chamcong_tay" class="btn btn-light erp-button">
                <i class="fa-solid fa-user-pen"></i>
                <span>Chấm công tay</span>
            </a>

            <!-- Bảng lương -->
            <a href="index.php?page=bangluong" class="btn btn-light erp-button">
                <i class="fas fa-money-check-alt"></i>
                <span>Bảng lương</span>
            </a>

             
            <a href="index.php?page=phanquyen" class="btn btn-light erp-button">
                <i class="fa-solid fa-shield"></i>
                <span>Phân Quyền</span>
            </a>
         

            <!-- Chatbox -->
            <a href="index.php?page=chat" class="btn btn-light erp-button">
                <i class="fa-regular fa-comments"></i>
                <span>Chat</span>
            </a>

        </div>
    </div>
</div>
