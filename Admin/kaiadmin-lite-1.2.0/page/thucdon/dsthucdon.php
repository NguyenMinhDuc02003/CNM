<?php

include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem thuc don', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Xử lý xoá nếu có yêu cầu
if (isset($_POST['delete_idthucdon'])) {
    header('Content-Type: application/json; charset=utf-8');
    $idthucdon = (int)$_POST['delete_idthucdon'];
    $ok = false;
    if ($conn && $idthucdon > 0) {
        // Xoá chi tiết trước để tránh ràng buộc khoá ngoại
        $stmt1 = $conn->prepare("DELETE FROM chitietthucdon WHERE idthucdon = ?");
        if ($stmt1) {
            $stmt1->bind_param("i", $idthucdon);
            $stmt1->execute();
            $stmt1->close();
        }
        // Xoá thực đơn
        $stmt2 = $conn->prepare("DELETE FROM thucdon WHERE idthucdon = ?");
        if ($stmt2) {
            $stmt2->bind_param("i", $idthucdon);
            if ($stmt2->execute()) { $ok = true; }
            $stmt2->close();
        }
    }
    exit;
}
// Xử lý chuyển trạng thái hoạt động (Mở / Khóa)
if (isset($_POST['toggle_idthucdon']) && isset($_POST['new_state'])) {
    $tid = (int)$_POST['toggle_idthucdon'];
    $newState = $_POST['new_state'] === 'active' ? 'active' : 'inactive';
    $stmtToggle = $conn->prepare("UPDATE thucdon SET hoatdong = ? WHERE idthucdon = ?");
    if ($stmtToggle) {
        $stmtToggle->bind_param("si", $newState, $tid);
        $stmtToggle->execute();
    }
    echo "<script>window.location.href='index.php?page=dsthucdon';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <style>
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <div class="mt-4">
            <div class="d-flex align-items-center justify-content-end mb-3 pe-5">

                <a href="index.php?page=themthucdon" class="d-flex align-items-center text-decoration-none">
                    <p class="mb-0 me-2"><b>Thêm</b></p>
                    <i class="fas fa-plus fs-4"></i>
                </a>

            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-12 d-flex flex-wrap justify-content-center">
                <?php
                if ($conn) {
                    $str = "SELECT * FROM thucdon ORDER BY idthucdon ASC;";
                    $result = $conn->query($str);
                    if ($result->num_rows > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            // Normalize and translate status text to Vietnamese
                            $statusRaw = $row['trangthai'] ?? '';
                            $lower = strtolower(trim($statusRaw));
                            $statusMap = [
                                'approved' => 'Đã duyệt',
                                'pending'  => 'Chờ duyệt',
                                'rejected' => 'Từ chối',
                            ];
                            $statusText = $statusMap[$lower] ?? $statusRaw;
                            

                            echo "<div class='card m-3' style='width: 300px; min-height: 200px; cursor: pointer;' onclick=\"window.location.href='index.php?page=chitietthucdon&idthucdon={$row["idthucdon"]}'\">";
                            echo "<div class='card-body d-flex flex-column justify-content-between'>";
                            
                            // Nội dung card
                            echo "<div class='mb-3 flex-grow-1'>
                                    <div class='bg-secondary text-white px-1 d-flex justify-content-center rounded' style='width: 120px'>" . $statusText . "</div>
                                    <div class='d-flex justify-content-between align-items-center mb-2'>
                                        <h5 class='card-title text-primary mb-0'>" . htmlspecialchars($row['tenthucdon']) . "</h5>
                                    </div>
                                    <p class='card-text text-muted'>" . htmlspecialchars($row['mota']) . "</p>
                                </div>";
                            
                            // Giá và nút xóa cố định ở cuối
                            echo "<div class='d-flex justify-content-between align-items-center'>";
                            echo "<p class='card-text mb-0'><strong>Giá: " . number_format($row['tongtien']) . " VNĐ</strong></p>";
                            
                            
                            if (strpos($lower, 'pending') !== false) {
                            echo "<a href='#' 
                                    class='btn-delete text-danger' 
                                    data-idthucdon='{$row["idthucdon"]}' 
                                    data-tenthucdon='" . htmlspecialchars($row["tenthucdon"]) . "'
                                    data-bs-toggle='modal' 
                                    data-bs-target='#deleteModal'
                                    onclick=\"event.stopPropagation();\">
                                    <i class='fas fa-trash-alt'></i>
                                </a>";
                            }
                            echo "</div>";

                            // Trạng thái hoạt động
                            $activeRaw = $row['hoatdong'] ?? '';
                            $lowerActive = strtolower(trim($activeRaw));

                            $activeMap = [
                                'active'   => 'Hoạt động',
                                'inactive' => 'Khóa',
                            ];
                            $activeText = $activeMap[$lowerActive] ?? $activeRaw;
                            
                            if ($lower === 'approved') {
                                echo "<div class='mt-2 d-flex justify-content-between align-items-center'>";
                                        // Hiển thị trạng thái hoạt động
                                         echo "<span class='fw-semibold'>" . htmlspecialchars($activeText) . "</span>";

                                        if ($lowerActive=='inactive') {
                                            // Form POST để chuyển hoatdong -> active
                                            echo "<form method='post' style='display:inline-block; margin:0; padding:0;' onsubmit='event.stopPropagation();'>
                                                    <input type='hidden' name='toggle_idthucdon' value='" . htmlspecialchars($row['idthucdon']) . "'>
                                                    <input type='hidden' name='new_state' value='active'>
                                                    <button type='submit' class='btn btn-success btn-sm' onclick='event.stopPropagation();'>Mở</button>
                                                </form>";
                                        } elseif ($lowerActive == 'active') {
                                            // Form POST để chuyển hoatdong -> inactive
                                            echo "<form method='post' style='display:inline-block; margin:0; padding:0;' onsubmit='event.stopPropagation();'>
                                                    <input type='hidden' name='toggle_idthucdon' value='" . htmlspecialchars($row['idthucdon']) . "'>
                                                    <input type='hidden' name='new_state' value='inactive'>
                                                    <button type='submit' class='btn btn-danger btn-sm' onclick='event.stopPropagation();'>Khóa</button>
                                            </form>";
                                         } 
                                echo "</div>";
                                       
                                }
                            
                            
                            
                            echo "</div>";
                            echo "</div>";
                        }
                    } else {
                        echo "<div class='col-12 text-center'>";
                        echo "<p class='text-muted'>Chưa có thực đơn nào.</p>";
                        echo "</div>";
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>

</html>

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
                    <input type="hidden" name="delete_idthucdon" id="delete_idthucdon">
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
    const deleteInput = document.getElementById('delete_idthucdon');
    const deleteForm = document.getElementById('deleteForm'); // Lấy form xóa

    deleteButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const idthucdon = btn.getAttribute('data-idthucdon');
            const tenthucdon = btn.getAttribute('data-tenthucdon');
            confirmText.textContent = `Bạn có chắc muốn xoá thực đơn "${tenthucdon}" không?`;
            deleteInput.value = idthucdon;
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
            alert('Xóa thực đơn thành công!');
            window.location.reload(); // Sau khi xóa thành công, reload trang
        });
    });
</script>
