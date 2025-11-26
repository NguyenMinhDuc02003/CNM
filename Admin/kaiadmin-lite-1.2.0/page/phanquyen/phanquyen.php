<?php
include("class/clsconnect.php");

include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem vai tro', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
if (!function_exists('normalizePermissions')) {
    function normalizePermissions($permissions)
    {
        if (!is_array($permissions)) {
            return [];
        }
        $normalized = [];
        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                continue;
            }
            $value = preg_replace('/\s+/', ' ', trim($permission));
            if ($value === '') {
                continue;
            }
            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }
        return $normalized;
    }
}

// Danh sách quyền theo danh mục, đồng bộ với menu
$permissionCategories = [
    'trang chu' => ['xem'],
    'nhan vien' => ['xem', 'them', 'sua', 'xoa'],
    'khach hang' => ['xem', 'them', 'sua', 'xoa'],
    'mon an' => ['xem', 'them', 'sua', 'xoa'],
    'don hang' => ['xem', 'them', 'sua', 'xoa', 'thanh toan'],
    'don dat ban' => ['xem', 'them', 'sua', 'xoa'],
    'hoa don' => ['xem'],
    'ton kho' => ['xem', 'them', 'sua', 'xoa'],
    'vai tro' => ['xem', 'them', 'sua', 'xoa', 'gan', 'thu hoi'],
    'nhacungcap' => ['xem', 'them', 'sua', 'xoa'],
    'lich lam viec' => ['xem','sua'],
    'thuc don' => ['xem', 'them', 'sua', 'xoa'],
    'nhap kho' => ['xem', 'them', 'sua', 'xoa']
    
];

// Truy vấn danh sách vai trò và nhân viên
$rolesQuery = "
    SELECT v.idvaitro, v.tenvaitro, v.quyen, GROUP_CONCAT(n.HoTen SEPARATOR '<br>') AS danh_sach_nhanvien
    FROM vaitro v
    LEFT JOIN nhanvien n ON n.idvaitro = v.idvaitro
    GROUP BY v.idvaitro, v.tenvaitro, v.quyen";
$rolesResult = mysqli_query($conn, $rolesQuery);

// Truy vấn danh sách nhân viên để gán vai trò
$usersQuery = "
    SELECT idnv, HoTen 
    FROM nhanvien 
    WHERE idvaitro IS NULL OR idvaitro IS NOT NULL";
$usersResult = mysqli_query($conn, $usersQuery);

// Truy vấn danh sách nhân viên có vai trò để thu hồi
$revokeUsersQuery = "
    SELECT idnv, HoTen 
    FROM nhanvien 
    WHERE idvaitro IS NOT NULL";
$revokeUsersResult = mysqli_query($conn, $revokeUsersQuery);

// Xử lý thêm vai trò
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
    if (!hasPermission('them vai tro', $permissions)) {
        echo "<script>alert('Bạn không có quyền thêm vai trò!'); window.location.href='index.php?page=phanquyen';</script>";
        exit();
    }
    $tenvaitro = mysqli_real_escape_string($conn, $_POST['tenvaitro']);
    
    // Chuẩn hóa quyền trước khi lưu
    $permArray = isset($_POST['permissions']) ? normalizePermissions($_POST['permissions']) : [];
    $permissions = !empty($permArray) ? implode(',', $permArray) : '';
    
    if (empty($tenvaitro)) {
        echo "<script>alert('Tên vai trò không được để trống!');</script>";
    } else {
        $query = "INSERT INTO vaitro (tenvaitro, quyen) VALUES (?, ?)";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ss", $tenvaitro, $permissions);
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Thêm vai trò thành công!'); window.location.href='index.php?page=phanquyen';</script>";
        } else {
            echo "<script>alert('Lỗi thêm vai trò: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// Xử lý cập nhật vai trò
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    // if (!hasPermission('Sua vai tro', $permissions)) {
    //     echo "<script>alert('Bạn không có quyền chỉnh sửa vai trò!'); window.location.href='index.php?page=phanquyen';</script>";
    //     exit();
    // }
    $idvaitro = intval($_POST['idvaitro']);
    $tenvaitro = mysqli_real_escape_string($conn, $_POST['tenvaitro']);
    
    // Chuẩn hóa quyền trước khi lưu
    $permArray = isset($_POST['permissions']) ? normalizePermissions($_POST['permissions']) : [];
    $permissions = !empty($permArray) ? implode(',', $permArray) : '';
    
    if (empty($tenvaitro)) {
        echo "<script>alert('Tên vai trò không được để trống!');</script>";
    } else {
        $query = "UPDATE vaitro SET tenvaitro = ?, quyen = ? WHERE idvaitro = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "ssi", $tenvaitro, $permissions, $idvaitro);
        if (mysqli_stmt_execute($stmt)) {
            echo "<script>alert('Cập nhật vai trò thành công!'); window.location.href='index.php?page=phanquyen';</script>";
        } else {
            echo "<script>alert('Lỗi cập nhật vai trò: " . mysqli_error($conn) . "');</script>";
        }
    }
}

// Xử lý xóa vai trò
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    $idvaitro = intval($_POST['idvaitro']);
    $query = "DELETE FROM vaitro WHERE idvaitro = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $idvaitro);
    if (mysqli_stmt_execute($stmt)) {
        $updateQuery = "UPDATE nhanvien SET idvaitro = NULL WHERE idvaitro = ?";
        $updateStmt = mysqli_prepare($conn, $updateQuery);
        mysqli_stmt_bind_param($updateStmt, "i", $idvaitro);
        mysqli_stmt_execute($updateStmt);
        echo "<script>alert('Xóa vai trò thành công!'); window.location.href='index.php?page=phanquyen';</script>";
    } else {
        echo "<script>alert('Lỗi xóa vai trò: " . mysqli_error($conn) . "');</script>";
    }
}

// Xử lý gán vai trò cho nhân viên
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign'])) {
    $idvaitro = intval($_POST['idvaitro']);
    $idnv = intval($_POST['idnv']);
    $checkQuery = "
        SELECT idvaitro, tenvaitro 
        FROM nhanvien n
        LEFT JOIN vaitro v ON n.idvaitro = v.idvaitro
        WHERE n.idnv = ?";
    $stmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($stmt, "i", $idnv);
    mysqli_stmt_execute($stmt);
    $checkResult = mysqli_stmt_get_result($stmt);
    if ($checkRow = mysqli_fetch_assoc($checkResult)) {
        if ($checkRow['idvaitro'] != NULL && $checkRow['idvaitro'] == $idvaitro) {
            echo "<script>alert('Nhân viên đã được gán vai trò: " . htmlspecialchars($checkRow['tenvaitro']) . "!'); window.location.href='index.php?page=phanquyen';</script>";
            exit();
        } elseif ($checkRow['idvaitro'] != NULL) {
            echo "<script>alert('Nhân viên đã có vai trò: " . htmlspecialchars($checkRow['tenvaitro']) . ". Vui lòng thu hồi vai trò trước khi gán mới!'); window.location.href='index.php?page=phanquyen';</script>";
            exit();
        }
    }
    $query = "UPDATE nhanvien SET idvaitro = ? WHERE idnv = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ii", $idvaitro, $idnv);
    if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
        echo "<script>alert('Gán vai trò thành công!'); window.location.href='index.php?page=phanquyen';</script>";
    } else {
        echo "<script>alert('Lỗi gán vai trò: Không tìm thấy nhân viên hoặc vai trò không hợp lệ!');</script>";
    }
}

// Xử lý thu hồi vai trò của nhân viên
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['revoke'])) {
    $idnv = intval($_POST['idnv']);
    $idvaitro = intval($_POST['idvaitro']);
    $checkQuery = "
        SELECT n.idvaitro, v.tenvaitro 
        FROM nhanvien n
        LEFT JOIN vaitro v ON n.idvaitro = v.idvaitro
        WHERE n.idnv = ?";
    $stmt = mysqli_prepare($conn, $checkQuery);
    mysqli_stmt_bind_param($stmt, "i", $idnv);
    mysqli_stmt_execute($stmt);
    $checkResult = mysqli_stmt_get_result($stmt);
    if ($checkRow = mysqli_fetch_assoc($checkResult)) {
        if ($checkRow['idvaitro'] == NULL) {
            echo "<script>alert('Nhân viên chưa có vai trò để thu hồi!'); window.location.href='index.php?page=phanquyen';</script>";
            exit();
        } elseif ($checkRow['idvaitro'] != $idvaitro) {
            echo "<script>alert('Nhân viên không có vai trò này để thu hồi!'); window.location.href='index.php?page=phanquyen';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Không tìm thấy nhân viên!'); window.location.href='index.php?page=phanquyen';</script>";
        exit();
    }
    $query = "UPDATE nhanvien SET idvaitro = NULL WHERE idnv = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $idnv);
    if (mysqli_stmt_execute($stmt) && mysqli_affected_rows($conn) > 0) {
        echo "<script>alert('Thu hồi vai trò thành công!'); window.location.href='index.php?page=phanquyen';</script>";
    } else {
        echo "<script>alert('Lỗi thu hồi vai trò: Không tìm thấy nhân viên!');</script>";
    }
}

// Xử lý cập nhật tất cả quyền hiện có
// if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_all_permissions'])) {
//     if ($vaitro_id != 4) {
//         echo "<script>alert('Chỉ quản lý mới có quyền thực hiện chức năng này!'); window.location.href='index.php?page=phanquyen';</script>";
//         exit();
//     }
    
//     // Lấy tất cả vai trò
//     $getAllRolesQuery = "SELECT idvaitro, quyen FROM vaitro";
//     $rolesResult = mysqli_query($conn, $getAllRolesQuery);
//     $updatedCount = 0;
    
//     while ($role = mysqli_fetch_assoc($rolesResult)) {
//         $idvaitro = $role['idvaitro'];
//         $quyen = $role['quyen'];
        
//         if (!empty($quyen)) {
//             // Chuẩn hóa quyền
//             $permArray = explode(',', $quyen);
//             $normalizedPermArray = normalizePermissions($permArray);
//             $normalizedQuyen = implode(',', $normalizedPermArray);
            
//             // Cập nhật nếu có thay đổi
//             if ($normalizedQuyen !== $quyen) {
//                 $updateQuery = "UPDATE vaitro SET quyen = ? WHERE idvaitro = ?";
//                 $stmt = mysqli_prepare($conn, $updateQuery);
//                 mysqli_stmt_bind_param($stmt, "si", $normalizedQuyen, $idvaitro);
//                 if (mysqli_stmt_execute($stmt)) {
//                     $updatedCount++;
//                 }
//             }
//         }
//     }
    
//     echo "<script>alert('Đã cập nhật " . $updatedCount . " vai trò với quyền không dấu!'); window.location.href='index.php?page=phanquyen';</script>";
// }
?>

<div class="container mb-3">
    <div class="mt-4">
        <div class="d-flex align-items-center justify-content-between mb-3 pe-5">
            <div>
               
            </div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fa fa-plus"></i> Thêm vai trò
            </button>
        </div>
    </div>

    <div style="overflow-x: auto; max-height: 100%">
        <table class="table table-head-bg-primary ms-3 me-3">
            <thead>
                <tr>
                    <th scope="col">Tên vai trò</th>
                    <th scope="col">Nhân viên</th>
                    <th scope="col">Tùy chọn</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (mysqli_num_rows($rolesResult) > 0) {
                    while ($row = mysqli_fetch_assoc($rolesResult)) {
                        $idvaitro = $row['idvaitro'];
                        $tenvaitro = $row['tenvaitro'];
                        $danh_sach_nhanvien = $row['danh_sach_nhanvien'] ?: 'Chưa có nhân viên';
                        // Chuẩn hóa quyền để truyền vào data-permissions
                        $currentPermissions = !empty($row['quyen']) ? explode(",", $row['quyen']) : [];
                        $permissionsJson = json_encode($currentPermissions);
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($tenvaitro) . "</td>";
                        echo "<td>" . $danh_sach_nhanvien . "</td>";
                        echo "<td>
                                <button class='btn btn-warning btn-sm edit-btn'
                                    data-id='" . $idvaitro . "'
                                    data-tenvaitro='" . htmlspecialchars($tenvaitro) . "'
                                    data-permissions='" . htmlspecialchars($row['quyen']) . "'
                                    data-bs-toggle='modal'
                                    data-bs-target='#editModal'>
                                    <i class='fas fa-pencil-alt' style='color:white; font-size:17px'></i>
                                </button>
                                <form method='POST' style='display:inline;'>
                                    <input type='hidden' name='idvaitro' value='" . $idvaitro . "'>
                                    <button type='submit' name='delete' class='btn btn-danger btn-sm' 
                                            onclick='return confirm(\"Bạn có chắc chắn muốn xóa vai trò này?\");'>
                                        <i class='fas fa-trash-alt' style='color:white; font-size:17px'></i>
                                    </button>
                                </form>
                              </td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' class='text-muted'>Không có vai trò nào.</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal thêm vai trò -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel"><i class="fas fa-plus me-2"></i> Thêm Vai Trò</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tên Vai Trò</label>
                            <input type="text" name="tenvaitro" class="form-control" placeholder="Nhập tên vai trò" required>
                        </div>
                    </div>
                    <label class="form-label fw-bold">Phân Quyền</label>
                    <div class="permissions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 30%;text-align: start;">Phân quyền</th>
                                    <th style="width: 10%; text-align: end;">Xem</th>
                                    <th style="width: 10%; text-align: end;">Thêm</th>
                                    <th style="width: 10%; text-align: end;">Sửa</th>
                                    <th style="width: 10%; text-align: end;">Xóa</th>
                                    <th style="width: 10%; text-align: end;">Thanh toán</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissionCategories as $category => $actions): ?>
                                    <tr>
                                        <td>
                                            <div class="check-all-label">
                                                <span><?= htmlspecialchars($category) ?></span>
                                            </div>
                                        </td>
                                        <?php
                                        $actionTypes = ['xem', 'them', 'sua', 'xoa', 'thanh toan'];
                                        $displayActionTypes = ['xem', 'them', 'sua', 'xoa', 'thanh toan'];
                                        foreach ($actionTypes as $index => $actionType) {
                                            $permissionValue = "$actionType $category";
                                            $displayActionType = $displayActionTypes[$index];
                                            $hasAction = in_array($displayActionType, $actions);
                                            echo "<td>";
                                            if ($hasAction) {
                                                echo "<div class='form-check'>
                                                        <input class='form-check-input permission-checkbox' type='checkbox' name='permissions[]' value='$permissionValue' data-category='" . strtolower(str_replace(' ', '-', $category)) . "'>
                                                      </div>";
                                            }
                                            echo "</td>";
                                        }
                                        ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="add" class="btn btn-primary">Thêm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal sửa vai trò -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit me-2"></i> Sửa Vai Trò</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" id="idvaitro" name="idvaitro">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Tên Vai Trò</label>
                            <input type="text" class="form-control" id="tenvaitro" name="tenvaitro" required>
                        </div>
                    </div>
                    
                    <div class="permissions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 30%;text-align: start;">Phân quyền</th>
                                    <th style="width: 10%; text-align: end;">Xem</th>
                                    <th style="width: 10%; text-align: end;">Thêm</th>
                                    <th style="width: 10%; text-align: end;">Sửa</th>
                                    <th style="width: 10%; text-align: end;">Xóa</th>
                                    <th style="width: 10%; text-align: end;">Thanh toán</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($permissionCategories as $category => $actions): ?>
                                    <tr>
                                        <td>
                                            <div class="check-all-label">
                                                <span><?= htmlspecialchars($category) ?></span>
                                            </div>
                                        </td>
                                        <?php
                                        $actionTypes = ['xem', 'them', 'sua', 'xoa', 'thanh toan'];
                                        $displayActionTypes = ['xem', 'them', 'sua', 'xoa', 'thanh toan'];
                                        foreach ($actionTypes as $index => $actionType) {
                                            $permissionValue = "$actionType $category";
                                            $displayActionType = $displayActionTypes[$index];
                                            $hasAction = in_array($displayActionType, $actions);
                                            echo "<td>";
                                            if ($hasAction) {
                                                echo "<div class='form-check'>
                                                        <input class='form-check-input permission-checkbox' type='checkbox' name='permissions[]' value='$permissionValue' data-category='" . strtolower(str_replace(' ', '-', $category)) . "'>
                                                      </div>";
                                            }
                                            echo "</td>";
                                        }
                                        ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="update" class="btn btn-primary">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .permissions-table td:not(:first-child) {
        text-align: right;
        vertical-align: middle;
    }
    .permissions-table .form-check {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin: 0;
    }
    .table-head-bg-primary th {
        background-color: #007bff;
        color: white;
    }
    .no-users {
        color: #6c757d;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Function to normalize permission strings (remove accents)
    function normalizePermission(permission) {
        // Replace accented characters with non-accented versions
        return permission.replace(/[áàảãạâấầẩẫậăắằẳẵặ]/g, 'a')
                        .replace(/[éèẻẽẹêếềểễệ]/g, 'e')
                        .replace(/[íìỉĩị]/g, 'i')
                        .replace(/[óòỏõọôốồổỗộơớờởỡợ]/g, 'o')
                        .replace(/[úùủũụưứừửữự]/g, 'u')
                        .replace(/[ýỳỷỹỵ]/g, 'y')
                        .replace(/đ/g, 'd')
                        .replace(/[ÁÀẢÃẠÂẤẦẨẪẬĂẮẰẲẴẶ]/g, 'A')
                        .replace(/[ÉÈẺẼẸÊẾỀỂỄỆ]/g, 'E')
                        .replace(/[ÍÌỈĨỊ]/g, 'I')
                        .replace(/[ÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢ]/g, 'O')
                        .replace(/[ÚÙỦŨỤƯỨỪỬỮỰ]/g, 'U')
                        .replace(/[ÝỲỶỸỴ]/g, 'Y')
                        .replace(/Đ/g, 'D');
    }

    $(document).ready(function () {
        // Sửa vai trò
        $(".edit-btn").click(function () {
            var id = $(this).data('id');
            var tenvaitro = $(this).data('tenvaitro');
            var permissions = $(this).data('permissions');
            
            console.log('ID:', id);
            console.log('Tên vai trò:', tenvaitro);
            console.log('Quyền:', permissions);
            
            // Set giá trị cho modal
            $("#idvaitro").val(id);
            $("#tenvaitro").val(tenvaitro);
            
            // Reset tất cả checkbox về trạng thái unchecked
            $(".permission-checkbox").prop("checked", false);
            
            // Nếu có quyền, set các checkbox tương ứng
            if (permissions) {
                var permissionArray = permissions.split(",");
                permissionArray.forEach(function (perm) {
                    // Normalize both the permission from database and the checkbox value
                    var normalizedPerm = normalizePermission(perm.trim());
                    
                    $(".permission-checkbox").each(function() {
                        var checkboxValue = $(this).val();
                        var normalizedCheckboxValue = normalizePermission(checkboxValue);
                        
                        if (normalizedPerm === normalizedCheckboxValue) {
                            $(this).prop("checked", true);
                        }
                    });
                });
            }
        });
    });
</script>




