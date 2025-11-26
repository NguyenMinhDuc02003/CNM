<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem nhacungcap', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
// Xử lý xoá nếu có yêu cầu
if (isset($_POST['delete_idncc'])) {
    $idncc = $_POST['delete_idncc'];
    $stmt = $conn->prepare("DELETE FROM nhacungcap WHERE idncc = ?");
    $stmt->bind_param("i", $idncc);
    $stmt->execute();
    echo "<script>
            window.location.reload(); // Tự động tải lại trang
            alert('Xóa khách hàng thành công!'); // Thông báo xóa thành công
          </script>";
    exit;
}
?>

<!-- Thêm meta charset UTF-8 -->
<meta charset="UTF-8">

<div class="container mb-5">
<div class="mt-4">
        <div class="d-flex align-items-center justify-content-end mb-3 pe-5">
            <a href="index.php?page=themncc" class="d-flex align-items-center text-decoration-none">
                <p class="mb-0 me-2"><b>Thêm</b></p>
                <i class="fas fa-plus fs-4"></i>
            </a>
        </div>
    </div>

    <div style="overflow-x: auto; max-height: 100%">
        <table class="table table-head-bg-primary ms-3 me-3 ">
            <thead>
                <tr>
                    <th scope="col">Tên nhà cung cấp </th>
                    <th scope="col">Số điện thoại</th>
                    <th scope="col">Email</th>
                    <th scope="col">Địa chỉ</th>
                    <th scope="col">Tùy chọn</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($conn) {
                    $str = "SELECT * FROM nhacungcap";
                    $result = $conn->query($str);
                    if ($result->num_rows > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr>";
                            echo "<td>" . $row['tennhacungcap'] . "</td>";
                            echo "<td>" . $row['sodienthoai'] . "</td>";
                            echo "<td>" . $row['email'] . "</td>";
                            echo "<td>" . $row['diachi'] . "</td>";
                            echo "<td>
                                <a href='index.php?page=suancc&idncc={$row["idncc"]}' class='btn btn-warning btn-sm'>
                                    <i class='fas fa-pencil-alt' style='color:white; font-size:17px'></i>
                                </a>
                                <button 
                                    type='button' 
                                    class='btn btn-danger btn-sm btn-delete' 
                                    data-idncc='{$row["idncc"]}' 
                                    data-hoten='{$row["tennhacungcap"]}'
                                    data-bs-toggle='modal' 
                                    data-bs-target='#deleteModal'>
                                    <i class='fas fa-trash-alt' style='color:white; font-size:17px'></i>
                                </button>
                            </td>";
                            echo "</tr>";
                        }
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
                    <input type="hidden" name="delete_idncc" id="delete_idncc">
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
    const deleteInput = document.getElementById('delete_idncc');
    const deleteForm = document.getElementById('deleteForm'); // Lấy form xóa

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const idncc = btn.getAttribute('data-idncc');
            const hoten = btn.getAttribute('data-hoten');
            confirmText.textContent = `Bạn có chắc muốn xoá nhà cung cấp "${hoten}" không?`;
            deleteInput.value = idncc;
        });
    });

    // Thêm sự kiện submit form khi người dùng nhấn "Xoá" trong modal
    deleteForm.addEventListener('submit', (event) => {
        event.preventDefault(); // Ngăn chặn việc gửi form mặc định
        const formData = new FormData(deleteForm);
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        }).then(response => {
            // Thông báo xóa thành công và tải lại trang
            alert('Xóa nhà cung cấp thành công!');
            window.location.reload(); // Sau khi xóa thành công, reload trang
        });
    });
</script>