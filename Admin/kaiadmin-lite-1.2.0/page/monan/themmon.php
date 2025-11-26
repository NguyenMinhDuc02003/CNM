<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('them mon an', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

?>
<div class="container mb-5">
    <div class="text-center">
        <h1><b>Thêm món ăn</b></h1>
    </div>
    <div class="row m-3 justify-content-center">
        <div class="col-7">
            <div class="card shadow h-100">
                <div class="card-body">
                    <form class="" id="form-themmon" action="" method="POST" enctype="multipart/form-data">
                        <div class="form-group mb-3">
                            <label for="tenmon" class="form-label">Tên món </label>
                            <input type="text" class="form-control" id="tenmon" name="tenmon" placeholder="Nhập tên món ăn"
                                required />
                        </div>
                        <div class="form-group mb-3">
                            <label for="hinhanh" class="form-label">Hình ảnh </label>
                            <input type="file" class="form-control" id="hinhanh" name="hinhanh" required />
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="mota" class="form-label">Mô tả </label>
                            <textarea class="form-control" id="mota" name="mota" placeholder="Mô tả món ăn" rows="5"
                                required></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label for="DVT" class="form-label">Đơn vị tính </label>
                            <input type="text" class="form-control" id="DVT" name="DVT" placeholder="Đơn vị tính" required />
                        </div>
                        <div class="form-group mb-3">
                            <label for="gia" class="form-label">Giá </label>
                            <input type="number" class="form-control" id="gia" name="gia" placeholder="Giá" required />
                        </div>
                        <div class="form-group mb-3">
                            <label for="danhmuc" class="form-label">Danh mục </label>
                            <select class="form-select" id="danhmuc" name="danhmuc" required>
                                <option value="" disabled selected hidden>Chọn danh mục</option>
                                <?php
                                $sql = "SELECT iddm, tendanhmuc FROM danhmuc";
                                $result = mysqli_query($conn, $sql);
                                if ($result) {
                                    $danhmucList = mysqli_fetch_all($result, MYSQLI_ASSOC);
                                    foreach ($danhmucList as $dm) {
                                        echo '<option value="' . $dm['iddm'] . '">' . htmlspecialchars($dm['tendanhmuc'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary" name="themmon">Thêm </button>
                            <a href="index.php?page=dsmonan" class="btn btn-secondary" name="huy">Hủy</a>

                        </div>
                    </form>
                    <?php

                    if (isset($_POST['themmon'])) {
                        $tenmon = $_POST['tenmon'];
                        $mota = $_POST['mota'];
                        $gia = $_POST['gia'];
                        $danhmuc = $_POST['danhmuc'];
                        $donvitinh = $_POST['DVT'];
                        $hinhAnh = $_FILES['hinhanh'];
                        $file_hinh = $hinhAnh['name'] ?? '';
                        
                        // Lấy thông tin thành phần nguyên liệu
                        $thanhphan = $_POST['thanhphan'] ?? [];
                        $dinhluong = $_POST['dinhluong'] ?? [];
                        
                        if ($conn) {
                            // Bắt đầu transaction
                            mysqli_autocommit($conn, false);
                            
                            try {
                                // Upload hình ảnh
                                move_uploaded_file($hinhAnh['tmp_name'], '../assets/img/' . $file_hinh);
                                
                                // Thêm món ăn
                                $str = "insert into monan (tenmonan, mota, DonGia, iddm, hinhanh, DonViTinh)
                                                values ('$tenmon','$mota', '$gia','$danhmuc', '$file_hinh','$donvitinh')";
                                
                                if ($conn->query($str)) {
                                    $monan_id = mysqli_insert_id($conn);
                                    
                                    // Thêm thành phần nguyên liệu nếu có
                                    if (!empty($thanhphan) && !empty($dinhluong)) {
                                        for ($i = 0; $i < count($thanhphan); $i++) {
                                            if (!empty($thanhphan[$i]) && !empty($dinhluong[$i])) {
                                                $matonkho = mysqli_real_escape_string($conn, $thanhphan[$i]);
                                                $soluong = mysqli_real_escape_string($conn, $dinhluong[$i]);
                                                
                                                $str_thanhphan = "insert into thanhphan (idmonan, matonkho, dinhluong) 
                                                                values ('$monan_id', '$matonkho', '$soluong')";
                                                
                                                if (!$conn->query($str_thanhphan)) {
                                                    throw new Exception("Lỗi khi thêm thành phần: " . $conn->error);
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Commit transaction
                                    mysqli_commit($conn);
                                    echo "<script>alert('Thêm thành công'); window.location.href='index.php?page=dsmonan'</script>";
                                } else {
                                    throw new Exception("Lỗi khi thêm món ăn: " . $conn->error);
                                }
                            } catch (Exception $e) {
                                // Rollback transaction
                                mysqli_rollback($conn);
                                echo "<script>alert('Thêm thất bại: " . $e->getMessage() . "'); window.location.href='index.php?page=themmon'</script>";
                            }
                            
                            // Bật lại autocommit
                            mysqli_autocommit($conn, true);
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="col-5">
            <div class="card shadow ">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h2 class="">Thành phần</h2>
                    </div>
                   
                    <div id="nguyenlieu-container">
                        <!-- Dòng nguyên liệu đầu tiên -->
                        <div class="nguyenlieu-row mb-3">
                            <div class="row">
                                <div class="col-7">
                                    <select class="form-select" name="thanhphan[]" id="thanhphan-1" required style="height:45px" form="form-themmon">
                                        <option value="">Chọn</option>
                                        <?php
                                        $sql = "SELECT * FROM tonkho tk
                                                JOIN loaitonkho ltk on tk.idloaiTK = ltk.idloaiTK
                                                WHERE ltk.idloaiTK=1";
                                        $result = mysqli_query($conn, $sql);
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo "<option value='{$row['matonkho']}'>{$row['tentonkho']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <div class="input-group">
                                        <input type="number" class="form-control col-2" name="dinhluong[]"  min="0" step="0.1" required form="form-themmon">
                                        <span class="input-group-text">gam</span>
                                    </div>
                                </div>
                                <div class="col-1 d-flex align-items-center justify-content-center">
                                    <i class="fas fa-trash text-danger remove-nguyenlieu" style="display: none; cursor: pointer; font-size: 16px;" title="Xóa nguyên liệu"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <button type="button" class="btn btn-success" id="add-nguyenlieu">
                            <i class="fas fa-plus"></i> Thêm nguyên liệu
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#add-nguyenlieu {
    width: 100%;
}

.remove-nguyenlieu {
    transition: all 0.2s ease;
}

.remove-nguyenlieu:hover {
    transform: scale(1.05);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let nguyenlieuCounter = 1;
    
    // Lấy danh sách nguyên liệu từ select đầu tiên
    const originalOptions = Array.from(document.querySelector('#thanhphan-1').options);
    
    // Hàm tạo HTML cho một dòng nguyên liệu mới
    function createNguyenlieuRow(counter) {
        return `
            <div class="nguyenlieu-row mb-3">
                <div class="row">
                    <div class="col-7">
                        <select class="form-select" name="thanhphan[]" id="thanhphan-${counter}" required style="height:45px" form="form-themmon">
                            <option value="">Chọn </option>
                            ${originalOptions.slice(1).map(option => 
                                `<option value="${option.value}">${option.textContent}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="col-4">
                        <div class="input-group">
                            <input type="number" class="form-control" name="dinhluong[]" min="0" step="0.1" required form="form-themmon">
                            <span class="input-group-text">gam</span>
                        </div>
                    </div>
                    <div class="col-1 d-flex align-items-center justify-content-center">
                        <i class="fas fa-trash text-danger remove-nguyenlieu" style="cursor: pointer; font-size: 16px;" title="Xóa nguyên liệu"></i>
                    </div>
                </div>
            </div>
        `;
    }
    
    
    // Thêm dòng nguyên liệu mới
    document.getElementById('add-nguyenlieu').addEventListener('click', function() {
        nguyenlieuCounter++;
        const container = document.getElementById('nguyenlieu-container');
        const newRow = createNguyenlieuRow(nguyenlieuCounter);
        container.insertAdjacentHTML('beforeend', newRow);
        
        // Hiển thị nút xóa cho tất cả các dòng (trừ dòng đầu tiên nếu chỉ có 1 dòng)
        updateRemoveButtons();
    });
    
    // Xóa dòng nguyên liệu
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-nguyenlieu')) {
            e.target.closest('.nguyenlieu-row').remove();
            updateRemoveButtons();
        }
    });
    
    // Cập nhật hiển thị nút xóa
    function updateRemoveButtons() {
        const rows = document.querySelectorAll('.nguyenlieu-row');
        const removeIcons = document.querySelectorAll('.remove-nguyenlieu');
        
        // Nếu chỉ có 1 dòng, ẩn tất cả icon xóa
        if (rows.length === 1) {
            removeIcons.forEach(icon => {
                icon.style.display = 'none';
            });
        } else {
            // Nếu có nhiều hơn 1 dòng, hiển thị tất cả icon xóa
            removeIcons.forEach(icon => {
                icon.style.display = 'inline-block';
            });
        }
    }
    
    // Khởi tạo
    updateRemoveButtons();
});
</script>

        
