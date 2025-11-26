<?php
require_once 'class/clsconnect.php';
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem khach hang', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

$db = new connect_db();
$conn = $db->getConnection();

if (isset($_POST['delete_idKH'])) {
    $idKH = (int) $_POST['delete_idKH'];
    $stmt = $conn->prepare("DELETE FROM khachhang WHERE idKH = ?");
    $stmt->bind_param("i", $idKH);
    $stmt->execute();
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
?>

<!-- Thêm meta charset UTF-8 -->
<meta charset="UTF-8">

<div class="container mb-5">
<div class="mt-4">
        <div class="d-flex align-items-center justify-content-end mb-3 pe-5">
            <a href="index.php?page=themkh" class="d-flex align-items-center text-decoration-none">
                <p class="mb-0 me-2"><b>Thêm</b></p>
                <i class="fas fa-plus fs-4"></i>
            </a>
        </div>
    </div>

    <div style="overflow-x: auto; max-height: 100%">
        <table class="table table-head-bg-primary ms-3 me-3 ">
            <thead>
                <tr>
                    <th scope="col">Mã khách hàng </th>
                    <th scope="col">Hình ảnh</th>
                    <th scope="col">Tên khách hàng </th>
                    <th scope="col">Số điện thoại</th>
                    <th scope="col">Email</th>
                    <th scope="col">Ngày sinh </th>
                    <th scope="col">Giới tính</th>
                    <th scope="col">Tùy chọn</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($conn) {
                    $str = "SELECT * FROM khachhang";
                    if ($result = $conn->query($str)) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . $row['idKH'] . "</td>";
                            echo "<td><img src='assets/img/{$row['hinhanh']}' style='width:100px'></td>";
                            echo "<td>" . $row['tenKH'] . "</td>";
                            echo "<td>" . $row['sodienthoai'] . "</td>";
                            echo "<td>" . $row['email'] . "</td>";
                            echo "<td>" . $row['ngaysinh'] . "</td>";
                            echo "<td>" . $row['gioitinh'] . "</td>";
                            echo "<td>
                                <a href='index.php?page=suakh&idKH={$row["idKH"]}' class='btn btn-warning btn-sm'>
                                    <i class='fas fa-pencil-alt' style='color:white; font-size:17px'></i>
                                </a>
                                <button 
                                    type='button' 
                                    class='btn btn-danger btn-sm btn-delete' 
                                    data-idKH='{$row["idKH"]}' 
                                    data-hoten='{$row["tenKH"]}'
                                    data-bs-toggle='modal' 
                                    data-bs-target='#deleteModal'>
                                    <i class='fas fa-trash-alt' style='color:white; font-size:17px'></i>
                                </button>
                            </td>";
                            echo "</tr>";
                        }
                        $result->free();
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal xác nhận xóa -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="deleteForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận xoá</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmText">Bạn có chắc muốn xoá khách hàng này?</p>
                    <input type="hidden" name="delete_idKH" id="delete_idKH">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-danger">Xoá</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Script xử lý xác nhận -->
<script>
    const deleteButtons = document.querySelectorAll('.btn-delete');
    const confirmText = document.getElementById('confirmText');
    const deleteInput = document.getElementById('delete_idKH');
    const deleteForm = document.getElementById('deleteForm'); // Lấy form xóa

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const idKH = btn.getAttribute('data-idKH');
            const hoten = btn.getAttribute('data-hoten');
            confirmText.textContent = `Bạn có chắc muốn xoá khách hàng "${hoten}" không?`;
            deleteInput.value = idKH;
        });
    });

    // Thêm sự kiện submit form khi người dùng nhấn "Xoá" trong modal
    deleteForm.addEventListener('submit', (event) => {
        event.preventDefault(); // Ngăn chặn việc gửi form mặc định
        const formData = new FormData(deleteForm);
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                alert('Xóa khách hàng thành công!');
                window.location.reload();
            } else {
                alert('Có lỗi xảy ra, vui lòng thử lại.');
            }
        })
        .catch(() => alert('Có lỗi xảy ra, vui lòng thử lại.'));
    });
</script>
