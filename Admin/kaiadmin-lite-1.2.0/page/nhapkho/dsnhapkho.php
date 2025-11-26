
<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem nhap kho', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}


// Xử lý xóa đơn nhập kho
if (isset($_POST['delete_manhapkho'])) {
    $manhapkho = $_POST['delete_manhapkho'];
    
    // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
    mysqli_begin_transaction($conn);
    
    try {
        // Xóa chi tiết nhập kho trước
        $deleteDetailQuery = "DELETE FROM chitietnhapkho WHERE manhapkho = ?";
        $stmt1 = mysqli_prepare($conn, $deleteDetailQuery);
        mysqli_stmt_bind_param($stmt1, "i", $manhapkho);
        mysqli_stmt_execute($stmt1);
        mysqli_stmt_close($stmt1);
        
        // Xóa đơn nhập kho
        $deleteQuery = "DELETE FROM nhapkho WHERE manhapkho = ?";
        $stmt2 = mysqli_prepare($conn, $deleteQuery);
        mysqli_stmt_bind_param($stmt2, "i", $manhapkho);
        
        if (mysqli_stmt_execute($stmt2)) {
            mysqli_commit($conn); // Commit transaction
            echo "<script>alert('Xóa đơn nhập kho thành công!'); window.location.reload();</script>";
        } else {
            mysqli_rollback($conn); // Rollback nếu có lỗi
            echo "<script>alert('Xóa đơn nhập kho thất bại!');</script>";
        }
        mysqli_stmt_close($stmt2);
        
    } catch (Exception $e) {
        mysqli_rollback($conn); // Rollback nếu có exception
        echo "<script>alert('Có lỗi xảy ra: " . $e->getMessage() . "');</script>";
    }
}
?>

<div class="container mb-3">
    
    <div class="mt-4">
        <div class="d-flex align-items-center justify-content-end mb-3 pe-5">
            
                <a href="index.php?page=themnhapkho" class="d-flex align-items-center text-decoration-none">
                    <p class="mb-0 me-2"><b>Thêm</b></p>
                    <i class="fas fa-plus fs-4"></i>
                </a>
           
        </div>
    </div>

    <div style="overflow-x: auto; max-height: 100%">
        <table class="table table-head-bg-primary table-hover ms-3 me-3">
            <thead>
                <tr>
                    <th scope="col">Mã nhập kho </th>
                    <th scope="col">Tên nhà cung cấp </th>
                    <th scope="col">Ngày nhập </th>
                    <th scope="col">Tổng tiền </th>
                    <th scope="col">Trạng thái </th>
                    <th scope="col">Tùy chọn</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php
                $str = "SELECT nk.*, ncc.tennhacungcap FROM nhapkho nk 
                        JOIN nhacungcap ncc ON ncc.idncc = nk.idncc
                        ORDER BY nk.manhapkho ASC";
                $result = $conn->query($str);
                if ($result->num_rows > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr class='row-clickable' data-manhapkho='{$row["manhapkho"]}' onmouseover=\"this.style.backgroundColor='rgb(39, 35, 35)'\" onmouseout=\"this.style.backgroundColor=''\" style='cursor: pointer;'>";
                        echo "<td>" . htmlspecialchars($row['tennhapkho']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['tennhacungcap']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['ngaynhap']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['tongtien']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['trangthai']) . "</td>";
                        echo "<td>";
                           
                                echo "<a href='index.php?page=suanhapkho&manhapkho={$row["manhapkho"]}' class='btn btn-warning btn-sm me-1' onclick='event.stopPropagation();'>
                                        <i class='fas fa-pencil-alt' style='color:white; font-size:17px'></i>
                                      </a>";
                            
                           
                                echo "<button type='button' class='btn btn-danger btn-sm btn-delete' onclick='event.stopPropagation();'
                                            data-manhapkho='{$row["manhapkho"]}' 
                                            data-tennhapkho='" . htmlspecialchars($row["tennhapkho"]) . "'
                                            data-bs-toggle='modal' 
                                            data-bs-target='#deleteModal'>
                                        <i class='fas fa-trash-alt' style='color:white; font-size:17px'></i>
                                      </button>";
                            
                            echo "</td>";
                        
                        echo "</tr>";
                    }
                    
                } else {
                    echo "<tr><td>Không có dữ liệu nhập kho.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- click trang chi tiết đơn nhập kho -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('.row-clickable');
        rows.forEach(row => {
            row.addEventListener('click', function(e) {
                // Nếu click trên nút hoặc phần tử bên trong nút, bỏ qua
                if (e.target.closest('.btn')) return;
                const manhapkho = this.getAttribute('data-manhapkho');
                window.location.href = `index.php?page=chitietnhapkho&manhapkho=${manhapkho}`;
            });
        });
    });
</script>

<!-- Modal xác nhận xóa (chỉ hiển thị cho Đầu bếp) -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" id="deleteForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xác nhận xóa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmText">Bạn có chắc muốn xóa tồn kho này?</p>
                    <input type="hidden" name="delete_manhapkho" id="delete_manhapkho">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Script xử lý xác nhận (chỉ cho Đầu bếp) -->
<script>
    const deleteButtons = document.querySelectorAll('.btn-delete');
    const confirmText = document.getElementById('confirmText');
    const deleteInput = document.getElementById('delete_manhapkho');
    const deleteForm = document.getElementById('deleteForm'); // Lấy form xóa

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', (event) => {
            event.stopPropagation(); // Không cho nổi bọt lên row
            const manhapkho = btn.getAttribute('data-manhapkho');
            const tennhapkho = btn.getAttribute('data-tennhapkho');
            confirmText.textContent = `Bạn có chắc muốn xoá đơn nhập kho "${tennhapkho}" không?`;
            deleteInput.value = manhapkho;
        });
    });

    // Thêm sự kiện submit form khi người dùng nhấn "Xoá" trong modal
    deleteForm.addEventListener('submit', (event) => {
        event.preventDefault(); // Ngăn chặn việc gửi form mặc định
        
        // Lấy mã nhập kho từ input hidden
        const manhapkho = deleteInput.value;
        
        if (!manhapkho) {
            alert('Không tìm thấy mã nhập kho để xóa!');
            return;
        }
        
        // Tạo form data
        const formData = new FormData();
        formData.append('delete_manhapkho', manhapkho);
        
        // Gửi request xóa
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (response.ok) {
                alert('Xóa đơn nhập kho thành công!');
                window.location.reload();
            } else {
                alert('Có lỗi xảy ra khi xóa đơn nhập kho!');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Có lỗi xảy ra khi xóa đơn nhập kho!');
        });
    });
</script>

<?php mysqli_close($conn); ?>