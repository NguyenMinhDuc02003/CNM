<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem ton kho', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Lấy danh sách tồn kho
// Category filter for loai ton kho (idloaiTK)
$filterId = isset($_GET['idloaiTK']) ? (int)$_GET['idloaiTK'] : 0;

// Lấy danh sách tồn kho (có thể có filter)
$where = '';
if ($filterId > 0) {
    $fid = (int)$filterId;
    $where = " WHERE tk.idloaiTK = {$fid} ";
}
$query = "SELECT tk.*, ltk.tenloaiTK 
          FROM tonkho tk 
          JOIN loaitonkho ltk ON tk.idloaiTK = ltk.idloaiTK" . $where . " 
          ORDER BY tk.matonkho ASC";
$result = mysqli_query($conn, $query);

// Xử lý xóa tồn kho
if (isset($_POST['delete_idTK'])) {
    $idTK = $_POST['delete_idTK'];
    $deleteQuery = "DELETE FROM tonkho WHERE matonkho = ?";
    $stmt = mysqli_prepare($conn, $deleteQuery);
    mysqli_stmt_bind_param($stmt, "i", $idTK);
    
    if (mysqli_stmt_execute($stmt)) {
        echo "<script>alert('Xóa tồn kho thành công!'); window.location.reload();</script>";
    } else {
        echo "<script>alert('Lỗi khi xóa tồn kho!');</script>";
    }
    mysqli_stmt_close($stmt);
}
?>

<div class="container mb-3">
    <div class="col-3"><a href="index.php?page=nhapkho" class="btn btn-primary">Nhập kho </a></div>
    <div class="mt-4">
        <div class="d-flex align-items-center justify-content-end mb-3 pe-5">
            
                <a href="index.php?page=themtonkho" class="d-flex align-items-center text-decoration-none">
                    <p class="mb-0 me-2"><b>Thêm</b></p>
                    <i class="fas fa-plus fs-4"></i>
                </a>
           
        </div>
    </div>

    <div style="overflow-x: auto; max-height: 100%">
        <?php
        // Category dropdown filter UI
        ?>
        <div class="mb-3">
            <form method="get" class="d-flex align-items-center">
                <input type="hidden" name="page" value="dstonkho">
                <label for="idloaiTK" class="me-2 mb-0">Loại tồn kho:</label>
                <select id="idloaiTK" name="idloaiTK" class="form-select me-2" style="width:250px;" onchange="this.form.submit()">
                    <option value="0" <?php echo $filterId === 0 ? 'selected' : ''; ?>>Tất cả</option>
                    <?php
                    $catRes = $conn->query("SELECT idloaiTK, tenloaiTK FROM loaitonkho ORDER BY tenloaiTK ASC");
                    if ($catRes && $catRes->num_rows > 0) {
                        while ($cat = mysqli_fetch_assoc($catRes)) {
                            $cid = (int)$cat['idloaiTK'];
                            $cname = htmlspecialchars($cat['tenloaiTK']);
                            $sel = ($filterId === $cid) ? ' selected' : '';
                            echo "<option value=\"{$cid}\"{$sel}>{$cname}</option>";
                        }
                    }
                    ?>
                </select>
                <noscript><button type="submit" class="btn btn-primary btn-sm">Lọc</button></noscript>
            </form>
        </div>
        <table class="table table-head-bg-primary ms-3 me-3">
            <thead>
                <tr>
                    <th scope="col">Mã tồn kho</th>
                    <th scope="col">Hình ảnh</th>
                    <th scope="col">Tên tồn kho</th>
                    <th scope="col">Số lượng</th>
                    <th scope="col">Đơn vị</th>
                    <th scope="col">Loại tồn kho</th>
                    <th scope="col">Nhà cung cấp</th>
                    <th scope="col">Tùy chọn</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php
                // Build simplified query with optional filter and join supplier
                $where2 = '';
                if (!empty($filterId) && $filterId > 0) {
                    $fid = (int)$filterId;
                    $where2 = " WHERE t.idloaiTK = {$fid} ";
                }
                $str = "SELECT t.matonkho, t.tentonkho, t.soluong, t.DonViTinh, t.hinhanh, l.tenloaiTK, n.tennhacungcap 
                        FROM tonkho t 
                        JOIN loaitonkho l ON t.idloaiTK = l.idloaiTK 
                        LEFT JOIN nhacungcap n ON t.idncc = n.idncc" . $where2 . "
                        ORDER BY t.matonkho ASC";
                $result = $conn->query($str);
                if ($result && $result->num_rows > 0) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['matonkho']) . "</td>";
                        echo "<td><img src='assets/img/{$row['hinhanh']}' style='width:100px'></td>";
                        echo "<td>" . htmlspecialchars($row['tentonkho']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['soluong']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['DonViTinh']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['tenloaiTK']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['tennhacungcap']) . "</td>";
                        echo "<td>";
                           
                                echo "<a href='index.php?page=suatonkho&matonkho={$row["matonkho"]}' class='btn btn-warning btn-sm me-1'>
                                        <i class='fas fa-pencil-alt' style='color:white; font-size:17px'></i>
                                      </a>";
                            
                           
                                echo "<button type='button' class='btn btn-danger btn-sm btn-delete' 
                                            data-idtonkho='{$row["matonkho"]}' 
                                            data-tentonkho='" . htmlspecialchars($row["tentonkho"]) . "'
                                            data-bs-toggle='modal' 
                                            data-bs-target='#deleteModal'>
                                        <i class='fas fa-trash-alt' style='color:white; font-size:17px'></i>
                                      </button>";
                            }
                            echo "</td>";
                        
                        echo "</tr>";
                    
                } else {
                    echo "<tr><td>Không có dữ liệu tồn kho.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

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
                    <input type="hidden" name="delete_idTK" id="delete_idTK">
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
    const deleteInput = document.getElementById('delete_idTK');
    const deleteForm = document.getElementById('deleteForm'); // Lấy form xóa

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const idtonkho = btn.getAttribute('data-idtonkho');
            const tentonkho = btn.getAttribute('data-tentonkho');
            confirmText.textContent = `Bạn có chắc muốn xoá tồn kho"${tentonkho}" không?`;
            deleteInput.value = idtonkho;
        });
    });

    // Thêm sự kiện submit form khi người dùng nhấn "Xoá" trong modal
    deleteForm.addEventListener('submit', (event) => {
        event.preventDefault(); // Ngăn chặn việc gửi form mặc định
        const formData = new FormData(deleteForm);
        fetch('', {
            method: 'POST',
            body: formData
        }).then(response => {
            // Thông báo xóa thành công và tải lại trang
            alert('Xóa tồn kho thành công!');
            window.location.reload(); // Sau khi xóa thành công, reload trang
        });
    });
</script>


<?php mysqli_close($conn); ?>