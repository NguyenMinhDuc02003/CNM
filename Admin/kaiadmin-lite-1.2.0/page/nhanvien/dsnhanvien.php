<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';
include_once 'includes/face_service.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem nhan vien', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
// Lấy danh sách nhân viên
$query = "SELECT n.*, v.tenvaitro 
          FROM nhanvien n 
          JOIN vaitro v ON n.idvaitro = v.idvaitro 
          ORDER BY n.idnv DESC";
$result = mysqli_query($conn, $query);

// Xử lý xóa nhân viên
if (isset($_POST['delete_idnv'])) {
    $idnv = (int) $_POST['delete_idnv'];

    $stmtFace = mysqli_prepare($conn, "DELETE FROM nhanvien_face WHERE idnv = ?");
    if ($stmtFace) {
        mysqli_stmt_bind_param($stmtFace, "i", $idnv);
        mysqli_stmt_execute($stmtFace);
        mysqli_stmt_close($stmtFace);
    }

    $deleteQuery = "DELETE FROM nhanvien WHERE idnv = ?";
    $stmt = mysqli_prepare($conn, $deleteQuery);
    mysqli_stmt_bind_param($stmt, "i", $idnv);

    if (mysqli_stmt_execute($stmt)) {
        $employeeCode = format_employee_code($idnv);
        $deleteResult = face_service_delete($employeeCode);

        $message = 'Xóa nhân viên thành công!';
        if (!$deleteResult['success']) {
            $message .= "\\nLưu ý: không thể xóa dữ liệu khuôn mặt - " . ($deleteResult['message'] ?? 'Không rõ lỗi');
        } elseif (!empty($deleteResult['message'])) {
            $message .= "\\n" . $deleteResult['message'];
        }

        $messageSafe = addslashes($message);
        echo "<script>alert('{$messageSafe}'); window.location.reload();</script>";
    } else {
        echo "<script>alert('Lỗi khi xóa nhân viên!');</script>";
    }
    mysqli_stmt_close($stmt);
}
?>
<div class="container mb-3">
    <div class="mt-4">
        <div class="d-flex align-items-center justify-content-end mb-3 pe-5">
          
                <a href="index.php?page=themnv" class="d-flex align-items-center text-decoration-none">
                    <p class="mb-0 me-2"><b>Thêm</b></p>
                    <i class="fas fa-plus fs-4"></i>
                </a>
           
        </div>
    </div>

    <div style="overflow-x: auto; max-height: 100%">
        <table class="table table-head-bg-primary table-hover ms-3 me-3">
            <thead>
                <tr>
                    <th scope="col">Mã nhân viên</th>
                    <th scope="col">Hình ảnh</th>
                    <th scope="col">Họ tên</th>
                    <th scope="col">Giới tính</th>
                    <th scope="col">Chức vụ</th>
                    <th scope="col">Số điện thoại</th>
                    <th scope="col">Email</th>
                    <th scope="col">Địa chỉ</th>
                    <th scope="col">Lương</th>
                    <th scope="col">Tùy chọn</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $str = "SELECT * FROM nhanvien JOIN vaitro ON nhanvien.idvaitro = vaitro.idvaitro ORDER BY nhanvien.idnv ASC";
                $result = $conn->query($str);
                if ($result->num_rows > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr class='row-clickable' data-idnv='{$row["idnv"]}' style='cursor: pointer;'>";
                        echo "<td>" . $row['idnv'] . "</td>";
                        echo "<td><img src='assets/img/{$row['HinhAnh']}' style='width:100px'></td>";
                        echo "<td>" . $row['HoTen'] . "</td>";
                        echo "<td>" . $row['GioiTinh'] . "</td>";
                        echo "<td>" . $row['tenvaitro'] . "</td>";
                        echo "<td>" . $row['SoDienThoai'] . "</td>";
                        echo "<td>" . $row['Email'] . "</td>";
                        echo "<td>" . $row['DiaChi'] . "</td>";
                        echo "<td>" . $row['Luong'] . "</td>";
                        echo "<td>";
                      
                            echo "<a href='index.php?page=suanv&idnv={$row["idnv"]}' class='btn btn-warning btn-sm' onclick=\"event.stopPropagation();\">
                                    <i class='fas fa-pencil-alt' style='color:white; font-size:17px'></i>
                              </a>";
                        
                        
                            echo "<button 
                                    type='button' 
                                    class='btn btn-danger btn-sm btn-delete' 
                                    data-idnv='{$row["idnv"]}' 
                                    data-hoten='" . htmlspecialchars($row["HoTen"]) . "'
                                    data-bs-toggle='modal' 
                                    data-bs-target='#deleteModal'
                                    onclick=\"event.stopPropagation();\">
                                    <i class='fas fa-trash-alt' style='color:white; font-size:17px'></i>
                                </button>";
                        echo "</td>";
                        echo "</tr>";
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
                    <h5 class="modal-title">Xác nhận xóa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmText">Bạn có chắc muốn xóa nhân viên này?</p>
                    <input type="hidden" name="delete_idnv" id="delete_idnv">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- click trang chi tiết nhân viên -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('.row-clickable');
        rows.forEach(row => {
            row.addEventListener('click', function() {
                const idnv = this.getAttribute('data-idnv');
                window.location.href = `index.php?page=chitietnv&idnv=${idnv}`;
            });
        });
    });
</script>
<!-- Script xử lý xác nhận -->
<script>
    const deleteButtons = document.querySelectorAll('.btn-delete');
    const confirmText = document.getElementById('confirmText');
    const deleteInput = document.getElementById('delete_idnv');
    const deleteForm = document.getElementById('deleteForm');

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const idnv = btn.getAttribute('data-idnv');
            const hoten = btn.getAttribute('data-hoten');
            confirmText.textContent = `Bạn có chắc muốn xóa nhân viên "${hoten}" không?`;
            deleteInput.value = idnv;
        });
    });

    deleteForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(deleteForm);
        fetch('', {
            method: 'POST',
            body: formData
        }).then(response => {
            alert('Xóa nhân viên thành công!');
            window.location.reload();
        });
    });
</script>
<?php mysqli_close($conn); ?>
