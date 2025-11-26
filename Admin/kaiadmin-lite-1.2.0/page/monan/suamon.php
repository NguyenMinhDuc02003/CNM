<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('sua mon an', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

if (isset($_GET['idmonan'])) {
    $idmonan = $_GET['idmonan'];
}

$sql = "SELECT m.*, d.tendanhmuc
FROM monan m
JOIN danhmuc d ON m.iddm = d.iddm
WHERE m.idmonan = $idmonan";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $hinhanh = $row['hinhanh'];
        $tenmonan = $row['tenmonan'];
        $mota = $row['mota'];
        $gia = $row['DonGia'];
        $iddm_selected = $row['iddm'];
        $donvitinh = $row['DonViTinh'] ?? '';
    }
}

// Lấy danh sách toàn bộ nguyên liệu (tonkho)
$tonkhoList = [];
$rsTonKho = mysqli_query($conn, "SELECT matonkho, tentonkho FROM tonkho tk
                                    JOIN loaitonkho ltk on tk.idloaiTK=ltk.idloaiTK 
                                    where ltk.idloaiTK=1");
if ($rsTonKho) {
    $tonkhoList = mysqli_fetch_all($rsTonKho, MYSQLI_ASSOC);
}

// Lấy các thành phần hiện có của món
$ingredients = [];
$rsTp = mysqli_query($conn, "SELECT matonkho, dinhluong FROM thanhphan WHERE idmonan = " . (int)$idmonan);
if ($rsTp) {
    $ingredients = mysqli_fetch_all($rsTp, MYSQLI_ASSOC);
}
?>
<div class="container mb-5">
    <div class="m-2">
        <a href="index.php?page=dsmonan" class="btn btn-secondary flex-fill">
            <i class="fas fa-arrow-left me-2"></i>Quay lại
        </a>
    </div>
    <div class="row m-3 justify-content-center">
        <div class="col-7">
            <div class="card shadow h-100">
                <div class="card-body">
                    <form id="form-suamon" method="post" enctype="multipart/form-data">
                        <!-- Tên món ăn -->
                        <div class="form-group mb-3">
                            <label for="tenmon" class="form-label">Tên món </label>
                            <input type="text" class="form-control" id="tenmon" name="tenmon"
                                value="<?php echo htmlspecialchars($tenmonan, ENT_QUOTES, 'UTF-8'); ?>" required />
                        </div>

                        <!-- Hình ảnh -->
                        <div class="text-center mb-3">
                            <img src='assets/img/<?php echo $hinhanh ?>' style='width:300px'>
                        </div>
                        <div class="form-group mb-3">
                            <label for="hinhanh" class="form-label">Hình ảnh </label>
                            <input type="file" class="form-control" id="hinhanh" name="hinhanh" />
                        </div>

                        <!-- Danh mục -->
                        <div class="form-group mb-3">
                            <label for="danhmuc" class="form-label">Danh mục </label>
                            <select class="form-select" id="danhmuc" name="danhmuc" required>
                                <option value="" disabled>Chọn danh mục</option>
                                <?php
                                $sql = "SELECT iddm, tendanhmuc FROM danhmuc";
                                $result = mysqli_query($conn, $sql);
                                if ($result) {
                                    $danhmucList = mysqli_fetch_all($result, MYSQLI_ASSOC);
                                    foreach ($danhmucList as $dm) {
                                        $selected = ($dm['iddm'] == $iddm_selected) ? 'selected' : '';
                                        echo '<option value="' . $dm['iddm'] . '" ' . $selected . '>' . htmlspecialchars($dm['tendanhmuc'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Đơn vị tính -->
                        <div class="form-group mb-3">
                            <label for="DVT" class="form-label">Đơn vị tính </label>
                            <input type="text" class="form-control" id="DVT" name="DVT" 
                                value="<?php echo htmlspecialchars($donvitinh, ENT_QUOTES, 'UTF-8'); ?>" required />
                        </div>

                        <!-- Giá -->
                        <div class="form-group mb-3">
                            <label for="gia" class="form-label">Giá </label>
                            <input type="number" class="form-control" id="gia" name="gia" 
                                value="<?php echo $gia ?>" required />
                        </div>

                        <!-- Mô tả -->
                        <div class="form-group mb-3">
                            <label for="mota" class="form-label">Mô tả </label>
                            <textarea class="form-control" id="mota" name="mota" rows="5" required><?php echo htmlspecialchars($mota, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        
                    </form>
                   
                </div>
            </div>
        </div>
        <div class="col-4">
            <div class="card shadow ">
                <div class="card-body">
                    <form>
                        <div class="text-center mb-4">
                            <h2 class="">Thành phần</h4>
                        </div>
                       
                        <div id="nguyenlieu-container">
                        <?php
                            if (!empty($ingredients)) {
                                $rowIndex = 0;
                                foreach ($ingredients as $ing) {
                                    $rowIndex++;
                                    echo '<div class="nguyenlieu-row mb-3">';
                                    echo '  <div class="row">';
                                    echo '    <div class="col-7">';
                                    echo '      <select class="form-select" name="thanhphan[]" id="thanhphan-' . $rowIndex . '" required style="height:45px" form="form-suamon">';
                                    echo '        <option value="" disabled hidden>Chọn</option>';
                                    foreach ($tonkhoList as $tk) {
                                        $selected = ((int)$ing['matonkho'] === (int)$tk['matonkho']) ? 'selected' : '';
                                        echo '<option value="' . $tk['matonkho'] . '" ' . $selected . '>' . htmlspecialchars($tk['tentonkho'], ENT_QUOTES, 'UTF-8') . '</option>';
                                    }
                                    echo '      </select>';
                                    echo '    </div>';
                                    echo '    <div class="col-4">';
                                    echo '      <div class="input-group">';
                                    echo '        <input type="number" class="form-control" name="dinhluong[]" value="' . htmlspecialchars($ing['dinhluong'], ENT_QUOTES, 'UTF-8') . '" min="0" step="0.1" required form="form-suamon">';
                                    echo '        <span class="input-group-text">gam</span>';
                                    echo '      </div>';
                                    echo '    </div>';
                                    echo '    <div class="col-1 d-flex align-items-center justify-content-center">';
                                    echo '      <i class="fas fa-trash text-danger remove-nguyenlieu" style="cursor: pointer; font-size: 16px;" title="Xóa nguyên liệu"></i>';
                                    echo '    </div>';
                                    echo '  </div>';
                                    echo '</div>';
                                }
                            } else {
                                // Không có thành phần -> render một dòng trống như mặc định
                                echo '<div class="nguyenlieu-row mb-3">';
                                echo '  <div class="row">';
                                echo '    <div class="col-7">';
                                echo '      <select class="form-select" name="thanhphan[]" id="thanhphan-1" required style="height:45px" form="form-suamon">';
                                echo '        <option value="" disabled selected hidden>Chọn</option>';
                                foreach ($tonkhoList as $tk) {
                                    echo '<option value="' . $tk['matonkho'] . '">' . htmlspecialchars($tk['tentonkho'], ENT_QUOTES, 'UTF-8') . '</option>';
                                }
                                echo '      </select>';
                                echo '    </div>';
                                echo '    <div class="col-4">';
                                echo '      <div class="input-group">';
                                echo '        <input type="number" class="form-control col-2" name="dinhluong[]" min="0" step="0.1" required form="form-suamon">';
                                echo '        <span class="input-group-text">gam</span>';
                                echo '      </div>';
                                echo '    </div>';
                                echo '    <div class="col-1 d-flex align-items-center justify-content-center">';
                                echo '      <i class="fas fa-trash text-danger remove-nguyenlieu" style="display: none; cursor: pointer; font-size: 16px;" title="Xóa nguyên liệu"></i>';
                                echo '    </div>';
                                echo '  </div>';
                                echo '</div>';
                            }
                        ?>
                        </div>

                        <div class="mb-4">
                            <button type="button" class="btn btn-success" id="add-nguyenlieu">
                                <i class="fas fa-plus"></i> Thêm nguyên liệu
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        <div class="mt-4">
                            <button type="submit" class="btn btn-primary" name="suamon" form="form-suamon">Sửa </button>
                            <a href="index.php?page=chitietmonan&idmonan=<?php echo $idmonan; ?>" class="btn btn-secondary" name="huy">Hủy</a>

                        </div>
                        <?php
                            if (isset($_POST['suamon'])) {
                                $tenmon = $_POST['tenmon'];
                                $mota = $_POST['mota'];
                                $gia = $_POST['gia'];
                                $danhmuc = $_POST['danhmuc'];
                                $donvitinh = $_POST['DVT'] ?? '';
                                $hinhanh = $_FILES['hinhanh'];
                                $file_hinh = $hinhanh['name'] ?? '';

                                // Thành phần
                                $thanhphan = $_POST['thanhphan'] ?? [];
                                $dinhluong = $_POST['dinhluong'] ?? [];

                                if ($conn) {
                                    mysqli_autocommit($conn, false);
                                    try {
                                        // Cập nhật món ăn
                                        if ($file_hinh !== '') {
                                            move_uploaded_file($hinhanh['tmp_name'], '../assets/img/' . $file_hinh);
                                            $str = "UPDATE monan SET hinhanh = '$file_hinh', tenmonan = '$tenmon', mota = '$mota', 
                                                    DonGia = '$gia', iddm='$danhmuc', DonViTinh='$donvitinh' WHERE idmonan= $idmonan";
                                        } else {
                                            $str = "UPDATE monan SET tenmonan = '$tenmon', mota = '$mota', DonGia = '$gia',
                                                    iddm='$danhmuc', DonViTinh='$donvitinh' WHERE idmonan= $idmonan";
                                        }

                                        if (!$conn->query($str)) {
                                            throw new Exception('Không thể cập nhật món ăn: ' . $conn->error);
                                        }

                                        // Xóa thành phần cũ
                                        if (!$conn->query("DELETE FROM thanhphan WHERE idmonan = " . (int)$idmonan)) {
                                            throw new Exception('Không thể xóa thành phần cũ: ' . $conn->error);
                                        }

                                        // Thêm lại thành phần nếu có
                                        if (!empty($thanhphan) && !empty($dinhluong)) {
                                            for ($i = 0; $i < count($thanhphan); $i++) {
                                                $matonkho = trim((string)($thanhphan[$i] ?? ''));
                                                $soluong = trim((string)($dinhluong[$i] ?? ''));
                                                if ($matonkho !== '' && $soluong !== '') {
                                                    $matonkhoEsc = mysqli_real_escape_string($conn, $matonkho);
                                                    $soluongEsc = mysqli_real_escape_string($conn, $soluong);
                                                    $ins = "INSERT INTO thanhphan (idmonan, matonkho, dinhluong) VALUES ('" . $idmonan . "', '" . $matonkhoEsc . "', '" . $soluongEsc . "')";
                                                    if (!$conn->query($ins)) {
                                                        throw new Exception('Không thể lưu thành phần: ' . $conn->error);
                                                    }
                                                }
                                            }
                                        }

                                        mysqli_commit($conn);
                                        echo "<script>alert('Sửa thành công'); window.location.href='index.php?page=dsmonan'</script>";
                                    } catch (Exception $e) {
                                        mysqli_rollback($conn);
                                        echo "<script>alert('Sửa thất bại: " . $e->getMessage() . "'); window.location.href='index.php?page=suamonan&idmonan=" . $idmonan . "'</script>";
                                    } finally {
                                        mysqli_autocommit($conn, true);
                                    }
                                }
                            }

                        ?>
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
                        <select class="form-select" name="thanhphan[]" id="thanhphan-${counter}" required style="height:45px" form="form-suamon">
                            <option value="">Chọn </option>
                            ${originalOptions.slice(1).map(option => 
                                `<option value="${option.value}">${option.textContent}</option>`
                            ).join('')}
                        </select>
                    </div>
                    <div class="col-4">
                        <div class="input-group">
                            <input type="number" class="form-control" name="dinhluong[]" min="0" step="0.1" required form="form-suamon">
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