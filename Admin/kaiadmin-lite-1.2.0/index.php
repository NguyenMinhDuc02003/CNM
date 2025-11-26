<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';

if (isset($_GET['page']) && $_GET['page'] === 'dangxuat') {
    admin_auth_logout();
    header('Location: page/dangnhap.php?status=logged_out');
    exit;
}

admin_auth_require_login();

$page = $_GET['page'] ?? '';

// Handle AJAX actions that need a JSON response before any layout output
if ($page === 'dskhachhang'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_idKH'])) {
    include('page/khachhang/dskhachhang.php');
    exit;
}

$layoutlessPages = ['chamcong'];
$useLayoutShell = !in_array($page, $layoutlessPages, true);

if ($useLayoutShell) {
    include("layout/header.php");
}
//Nhân viên
if (isset($_GET['page']) && $_GET['page'] == 'dsnhanvien') {
    include('page/nhanvien/dsnhanvien.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themnv') {
    include('page/nhanvien/themnv.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'chitietnv') {
    include('page/nhanvien/chitietnv.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suanv') {
    include('page/nhanvien/suanv.php');
//Khách hàng
} else if (isset($_GET['page']) && $_GET['page'] == 'dskhachhang') {
    include('page/khachhang/dskhachhang.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themkh') {
    include('page/khachhang/themkh.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suakh') {
    include('page/khachhang/suakh.php');
//Món ăn
} else if (isset($_GET['page']) && $_GET['page'] == 'dsmonan') {
    include('page/monan/dsmonan.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'chitietmonan') {
    include('page/monan/chitietmonan.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themmonan') {
    include('page/monan/themmon.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suamonan') {
    include('page/monan/suamon.php');
    // } else if (isset($_GET['page']) && $_GET['page'] == 'timkiem') {
//     include('page/timkiem.php');
//Tồn kho
} else if (isset($_GET['page']) && $_GET['page'] == 'dstonkho') {
    include('page/tonkho/dstonkho.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themtonkho') {
    include('page/tonkho/themtonkho.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suatonkho') {
    include('page/tonkho/suatonkho.php');
//Nhập kho
} else if (isset($_GET['page']) && $_GET['page'] == 'nhapkho') {
    include('page/nhapkho/dsnhapkho.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'chitietnhapkho') {
    include('page/nhapkho/chitietnhapkho.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themnhapkho') {
    include('page/nhapkho/themnhapkho.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suanhapkho') {
    include('page/nhapkho/suanhapkho.php');
// Đơn hàng
} else if (isset($_GET['page']) && $_GET['page'] == 'dsdonhang') {
    include('page/donhang/dsdonhang.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'xemDH') {
    include('page/donhang/chitietdonhang.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themDH') {
    include('page/donhang/themDH.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suaDH') {
    include('page/donhang/suaDH.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'bepdonhang') {
    include('page/donhang/bep.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'moBan') {
    include('page/donhang/moBan.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'table_qr') {
    include('page/table_qr/table_qr.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'order_payment') {
    include('page/donhang/order_payment.php');
// Đơn đặt bàn
} else if (isset($_GET['page']) && $_GET['page'] == 'dsdatban') {
    include('page/datban/dsdatban.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'chitietdondatban') {
    include('page/datban/chitietdondatban.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'admin_booking') {
    include('page/datban/admin_booking.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'admin_payment') {
    include('page/datban/admin_payment.php');

// Hóa đơn  
} else if (isset($_GET['page']) && $_GET['page'] == 'dshoadon') {
    include('page/hoadon/dshoadon.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'chitietHD') {
    include('page/hoadon/chitiethoadon.php');
//Thanh toán    
} else if (isset($_GET['page']) && $_GET['page'] == 'payment') {
    include('page/thanhtoan/payment.php');
//Nhà cung cấp
} else if (isset($_GET['page']) && $_GET['page'] == 'dsnhacungcap') {
    include('page/nhacungcap/dsnhacungcap.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themncc') {
    include('page/nhacungcap/themncc.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suancc') {
    include('page/nhacungcap/suancc.php');

//Lịch làm việc
} else if (isset($_GET['page']) && $_GET['page'] == 'lichlamviec') {
    include('page/lichlamviec/lichlamviec.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'sualichlamviec') {
    include('page/lichlamviec/sualichlamviec.php');
//Chấm công
} else if (isset($_GET['page']) && $_GET['page'] == 'chamcong') {
    include('page/chamcong/webcam_test.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'chamcong_tay') {
    include('page/chamcong/manual_attendance.php');

//Xem thông tin cá nhân
} else if (isset($_GET['page']) && $_GET['page'] == 'xemthongtin') {
    include('page/thongtincanhan/xemthongtin.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suathongtin') {
    include('page/thongtincanhan/suathongtin.php');

//Thực đơn
} else if (isset($_GET['page']) && $_GET['page'] == 'dsthucdon') {
    include('page/thucdon/dsthucdon.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'chitietthucdon') {
    include('page/thucdon/chitietthucdon.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'themthucdon') {
    include('page/thucdon/themthucdon.php');
} else if (isset($_GET['page']) && $_GET['page'] == 'suathucdon') {
    include('page/thucdon/suathucdon.php');
//thay doi password
} else if (isset($_GET['page']) && $_GET['page'] == 'change_password') {
    include('page/change_password/change_password.php');
//Chatbox
} else if (isset($_GET['page']) && $_GET['page'] == 'chat') {
    include('../../chat_app/privatechat.php');
//Phân quyền
} else if (isset($_GET['page']) && $_GET['page'] == 'phanquyen') {
    include('page/phanquyen/phanquyen.php');

//Bảng lương
} else if (isset($_GET['page']) && $_GET['page'] == 'bangluong') {
    include('page/bangluong/bangluong.php');

} else if (isset($_GET['page']) && $_GET['page'] == 'quanly') {
    include('page/quanly.php');
} else {
    include("page/erp.php");
}
if ($useLayoutShell) {
    include("layout/footer.php");
}

?>
