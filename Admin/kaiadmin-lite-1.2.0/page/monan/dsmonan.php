<?php

include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem mon an', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}




// Xử lý xoá nếu có yêu cầu
if (isset($_POST['delete_idmonan'])) {
    // if (!$canDelete) {
    //     echo "<script>alert('Bạn không có quyền xóa món ăn!'); window.location.href='index.php?page=dsmonan';</script>";
    //     exit;
    // }
    $idmonan = $_POST['delete_idmonan'];
    
    // Xóa thành phần nguyên liệu trước
    $stmt1 = $conn->prepare("DELETE FROM thanhphan WHERE idmonan = ?");
    $stmt1->bind_param("i", $idmonan);
    $stmt1->execute();
    
    // Xóa món ăn
    $stmt2 = $conn->prepare("DELETE FROM monan WHERE idmonan = ?");
    $stmt2->bind_param("i", $idmonan);
    
    if ($stmt2->execute()) {
        echo "<script>
                alert('Xóa món ăn thành công!');
                window.location.href = 'index.php?page=dsmonan';
              </script>";
    } else {
        echo "<script>
                alert('Lỗi khi xóa món ăn!');
                window.location.href = 'index.php?page=dsmonan';
              </script>";
    }
    exit;
}

// Xử lý chuyển trạng thái hoạt động (Mở / Khóa)
if (isset($_POST['toggle_idmonan']) && isset($_POST['new_state'])) {
    $tid = (int)$_POST['toggle_idmonan'];
    $newState = $_POST['new_state'] === 'active' ? 'active' : 'inactive';
    $stmtToggle = $conn->prepare("UPDATE monan SET hoatdong = ? WHERE idmonan = ?");
    if ($stmtToggle) {
        $stmtToggle->bind_param("si", $newState, $tid);
        $stmtToggle->execute();
    }
    echo "<script>window.location.href='index.php?page=dsmonan';</script>";
    exit;
}
?>

<div class="container mb-3">
    <div class="mt-4">
        <div class="d-flex align-items-center justify-content-end mb-3 pe-5">
            
                <a href="index.php?page=themmonan" class="d-flex align-items-center text-decoration-none">
                    <p class="mb-0 me-2"><b>Thêm</b></p>
                    <i class="fas fa-plus fs-4"></i>
                </a>
            
        </div>
    </div>

    <div style="overflow-x: auto; max-height: 100%">
        <?php
        // Category filter: get selected category id from GET
        $filterId = isset($_GET['iddm']) ? (int)$_GET['iddm'] : 0;
        ?>
        <div class="mb-3">
            <form method="get" class="d-flex align-items-center">
                <input type="hidden" name="page" value="dsmonan">
                <label for="iddm" class="me-2 mb-0">Danh mục:</label>
                <select id="iddm" name="iddm" class="form-select me-2" style="width:250px;" onchange="this.form.submit()">
                    <option value="0" <?php echo $filterId === 0 ? 'selected' : ''; ?>>Tất cả</option>
                    <?php
                    // Load categories
                    $catRes = $conn->query("SELECT iddm, tendanhmuc FROM danhmuc ORDER BY tendanhmuc ASC");
                    if ($catRes && $catRes->num_rows > 0) {
                        while ($cat = mysqli_fetch_assoc($catRes)) {
                            $cid = (int)$cat['iddm'];
                            $cname = htmlspecialchars($cat['tendanhmuc']);
                            $sel = ($filterId === $cid) ? ' selected' : '';
                            echo "<option value=\"{$cid}\"{$sel}>{$cname}</option>";
                        }
                    }
                    ?>
                </select>
                <noscript><button type="submit" class="btn btn-primary btn-sm">Lọc</button></noscript>
            </form>
        </div>
        <table class="table table-head-bg-primary  table-hover ms-3 me-3 ">
            <thead>
                <tr>
                    <th scope="col">Mã món ăn </th>
                    <th scope="col">Hình ảnh</th>
                    <th scope="col">Tên món ăn </th>
                    <th scope="col">Mô tả </th>
                    <th scope="col">Giá</th>
                    <th scope="col">Danh mục</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col">Hoạt động</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($conn) {
                    // Build query with optional category filter
                    $where = '';
                    if (!empty($filterId) && $filterId > 0) {
                        $fid = (int)$filterId;
                        $where = " WHERE m.iddm = {$fid} ";
                    }
                    $str = "SELECT m.*, d.tendanhmuc FROM monan m JOIN danhmuc d ON m.iddm=d.iddm" . $where . " ORDER BY m.idmonan ASC";
                    $result = $conn->query($str);
                    if ($result && $result->num_rows > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr class='row-clickable' data-idmonan='{$row["idmonan"]}' onmouseover=\"this.style.backgroundColor='rgb(39, 35, 35)'\" onmouseout=\"this.style.backgroundColor=''\" style='cursor: pointer;'>";
                            echo "<td>" . $row['idmonan'] . "</td>";
                            echo "<td><img src='assets/img/{$row['hinhanh']}' style='width:100px'></td>";
                            echo "<td>" . $row['tenmonan'] . "</td>";
                            echo "<td>" . $row['mota'] . "</td>";
                            echo "<td>" . number_format($row['DonGia']) . "đ</td>";
                            echo "<td>" . $row['tendanhmuc'] . "</td>";
                            // Trạng thái duyệt
                            $statusRaw = $row['TrangThai'] ?? '';
                            $lower = strtolower(trim($statusRaw));
                            $statusMap = [
                                'approved' => 'Đã duyệt',
                                'pending'  => 'Chờ duyệt',
                                'rejected' => 'Từ chối',
                            ];
                            $statusText = $statusMap[$lower] ?? $statusRaw;
                            echo "<td>" . htmlspecialchars($statusText) . "</td>";
                            // Trạng thái hoạt động
                            $activeRaw = $row['hoatdong'] ?? '';
                            $lowerActive = strtolower(trim($activeRaw));

                            $activeMap = [
                                'active'   => 'Hoạt động',
                                'inactive' => 'Khóa',
                            ];
                            $activeText = $activeMap[$lowerActive] ?? $activeRaw;
                            echo "<td>" .htmlspecialchars($activeText) . "</td>";
                            echo "<td>";
                                if (strpos($lower, 'pending') !== false) {
                                    echo "<button 
                                        type='button' 
                                        class='btn btn-danger btn-sm btn-delete' 
                                        data-idmonan='{$row["idmonan"]}' 
                                        data-tenmonan='" . htmlspecialchars($row["tenmonan"]) . "' 
                                        data-bs-toggle='modal' 
                                        data-bs-target='#deleteModal' 
                                        onclick='event.stopPropagation()'>
                                        <i class='fas fa-trash-alt' style='color:white; font-size:17px'></i>
                                    </button>";
                                }
                                elseif ($lower === 'approved') {
                                        // Hiển thị nút tùy theo trạng thái hoạt động
                                        if ($lowerActive=='inactive') {
                                            // Form POST để chuyển hoatdong -> active
                                            echo "<form method='post' style='display:inline-block; margin:0; padding:0;' onsubmit='event.stopPropagation();'>
                                                    <input type='hidden' name='toggle_idmonan' value='" . htmlspecialchars($row['idmonan']) . "'>
                                                    <input type='hidden' name='new_state' value='active'>
                                                    <button type='submit' class='btn btn-success btn-sm' onclick='event.stopPropagation();'>Mở</button>
                                                </form>";
                                        } elseif ($lowerActive == 'active') {
                                            // Form POST để chuyển hoatdong -> inactive
                                            echo "<form method='post' style='display:inline-block; margin:0; padding:0;' onsubmit='event.stopPropagation();'>
                                                    <input type='hidden' name='toggle_idmonan' value='" . htmlspecialchars($row['idmonan']) . "'>
                                                    <input type='hidden' name='new_state' value='inactive'>
                                                    <button type='submit' class='btn btn-danger btn-sm' onclick='event.stopPropagation();'>Khóa</button>
                                            </form>";
                                    } 
                                }
                            echo "</td>";
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
                    <p id="confirmText">Bạn có chắc muốn xoá món ăn này?</p>
                    <input type="hidden" name="delete_idmonan" id="delete_idmonan">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                    <button type="submit" class="btn btn-danger">Xoá</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- click trang chi tiết món ăn -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('.row-clickable');
        rows.forEach(row => {
            row.addEventListener('click', function() {
                const idmonan = this.getAttribute('data-idmonan');
                window.location.href = `index.php?page=chitietmonan&idmonan=${idmonan}`;
            });
        });
    });
</script>

<!-- Script xử lý xác nhận -->
<script>
    const deleteButtons = document.querySelectorAll('.btn-delete');
    const confirmText = document.getElementById('confirmText');
    const deleteInput = document.getElementById('delete_idmonan');
    const deleteForm = document.getElementById('deleteForm'); // Lấy form xóa

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const idmonan = btn.getAttribute('data-idmonan');
            const tenmon = btn.getAttribute('data-tenmonan');
            confirmText.textContent = `Bạn có chắc muốn xoá món ăn "${tenmon}" không?`;
            deleteInput.value = idmonan;
        });
    });

    // Thêm sự kiện submit form khi người dùng nhấn "Xoá" trong modal
    deleteForm.addEventListener('submit', (event) => {
        // Không cần preventDefault, để form submit bình thường
        // PHP sẽ xử lý và redirect
    });
</script>