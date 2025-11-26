<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('xem thuc don', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Lấy id thực đơn
$idthucdon = isset($_GET['idthucdon']) ? intval($_GET['idthucdon']) : 0;

$menuName = null;
$menuImage = null;
$items = [];
$tongtien = 0;
if ($conn && $idthucdon > 0) {
    // Theo yêu cầu: lấy tên thực đơn và danh sách món thuộc thực đơn
    $stmt = $conn->prepare("SELECT td.*, td.hinhanh AS hinhanh_td, ma.idmonan, ma.tenmonan, ma.hinhanh, ma.DonGia, ma.iddm, dm.tendanhmuc 
                            FROM chitietthucdon cttd 
                            JOIN monan ma on cttd.idmonan = ma.idmonan 
                            JOIN thucdon td ON cttd.idthucdon = td.idthucdon 
                            JOIN danhmuc dm ON ma.iddm = dm.iddm 
                            WHERE td.idthucdon = ?");

    if ($stmt) {
        $stmt->bind_param("i", $idthucdon);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if ($menuName === null && isset($row['tenthucdon'])) { $menuName = $row['tenthucdon']; }
                if ($menuImage === null && isset($row['hinhanh_td'])) { $menuImage = $row['hinhanh_td']; }
                $items[] = [
                    'idmonan' => isset($row['idmonan']) ? (int)$row['idmonan'] : null,
                    'tenmonan' => $row['tenmonan'] ?? '',
                    'hinhanh' => $row['hinhanh'] ?? '',
                    'DonGia' => $row['DonGia'] ?? 0,
                    'iddm' => isset($row['iddm']) ? (int)$row['iddm'] : null,
                    'tendanhmuc' => $row['tendanhmuc'] ?? '',
                    'mota' => '',
                    
                ];
                $ghichu= $row['ghichu'] ?? '';
                $lower = strtolower(trim($row['trangthai']));
                $tongtien += (float)($row['DonGia'] ?? 0);
            }
        }
        $stmt->close();
    }
}

// Xử lý Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&isset($_POST['action'], $_POST['idthucdon'])) {
    $id = $_POST['idthucdon'];
    $action = $_POST['action'];

    // --- APPROVE ---
    if ($action === 'approve') {
        if ($stmt = $conn->prepare("UPDATE thucdon SET trangthai = 'approved' WHERE idthucdon = ?")) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
        }
        echo "<script>alert('Xác nhận thành công'); window.location.href='index.php?page=dsthucdon';</script>";
        exit;
    }

    // --- REJECT ---
    if ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');

        if ($stmt = $conn->prepare("UPDATE thucdon SET trangthai = 'rejected', ghichu = ? WHERE idthucdon = ?")) {
            $stmt->bind_param("si", $reason, $id);
            $stmt->execute();
        }

        echo "<script>alert('Lưu thành công'); window.location.href='index.php?page=dsthucdon';</script>";
        exit;
    }
}

?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
            <a href="index.php?page=dsthucdon" class="btn btn-secondary flex-fill">
                    <i class="fas fa-arrow-left me-2"></i>Quay lại
            </a>
                
            

            <div class="card shadow-sm border-0">
                <?php if (empty($items)) { ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-2x text-muted mb-3"></i>
                    <p class="text-muted mb-0">Không tìm thấy thực đơn.</p>
                </div>
                <?php } else { ?>
                <div class="card-body p-0">
                    <?php if (!empty($menuImage)) { 
                    echo "<div class='w-100' style='height:220px;background:url(\"assets/img/" . htmlspecialchars($menuImage) . "\") center/cover no-repeat;border-top-left-radius:.375rem;border-top-right-radius:.375rem'></div>";
                    } else { 
                    echo "<div class='w-100 d-flex align-items-center justify-content-center text-muted bg-light' style='height:220px;border-top-left-radius:.375rem;border-top-right-radius:.375rem'>Hình ảnh đang cập nhật</div>";
                    } ?>
                    <div class="p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="flex-grow-1 text-center">
                            <h1 class="mb-1 text-primary"><b><?php echo htmlspecialchars($menuName ?? 'Thực đơn'); ?></b></h1>
                            <p class="mb-0 text-muted"></p>
                        </div>
                        <?php if (in_array($lower, ['pending', 'approved']) && !empty($items)) : ?>
                            <div class="text-end">
                                <a href="index.php?page=suathucdon&idthucdon=<?= (int)$idthucdon ?>" 
                                class="text-warning" title="Sửa thực đơn">
                                    <i class="fas fa-pencil-alt" style="font-size:25px;"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                
                    <?php
                    // Hàm render phần mục theo nhóm (khai vị, món chính, tráng miệng)
                    function renderSection($title, $items) {
                        ?>
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-3">
                                <h5 class="mb-0"><?php echo $title; ?></h5>
                                <div class="flex-grow-1 border-bottom ms-3" style="opacity:.3"></div>
                            </div>

                            <?php if (empty($items)) { ?>
                                <div class="p-4 border rounded-3 text-center text-muted bg-light">Đang cập nhật món ăn </div>
                            <?php } else { ?>
                                <div class="row g-3 row-cols-1 row-cols-md-2">
                                    <?php foreach ($items as $it) { ?>
                                    <div class="col">
                                        <div class="d-flex p-3 rounded-3 h-100">
                                            <div style="width:72px;height:72px;overflow:hidden;border-radius:10px" class="bg-light flex-shrink-0 d-flex align-items-center justify-content-center me-3">
                                                <?php if (!empty($it['hinhanh'])) { ?>
                                                <img src="../../User/restoran-1.0.0/img/<?php echo htmlspecialchars($it['hinhanh']); ?>" alt="img" style="max-width:100%;max-height:100%">
                                                <?php } else { ?>
                                                <i class="fas fa-image text-muted"></i>
                                                <?php } ?>
                                            </div>
                                            <div class="flex-grow-1 d-flex flex-column">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <div class="fw-semibold me-2"><?php echo htmlspecialchars($it['tenmonan']); ?></div>
                                                    
                                                </div>
                                                <?php if (!empty($it['mota'])) { ?>
                                                <div class="small text-muted"><?php echo htmlspecialchars($it['mota']); ?></div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                        <?php
                    }
                    

                    // Gom món theo danh mục, hiển thị 3 danh mục mặc định trước, sau đó tự động các danh mục mới
                    $groups = [];
                    foreach ($items as $it) {
                        $dmId = $it['iddm'] ?? null;
                        $dmName = $it['tendanhmuc'] ?? 'Danh mục khác';
                        if ($dmId === null) continue;
                        if (!isset($groups[$dmId])) {
                            $groups[$dmId] = ['name' => $dmName, 'items' => []];
                        }
                        $groups[$dmId]['items'][] = $it;
                    }

                    // Thứ tự ưu tiên: 1-Khai vị, 2-Món chính, 3-Tráng miệng
                    $preferred = [1, 2, 3];
                    foreach ($preferred as $pid) {
                        if (isset($groups[$pid])) {
                            renderSection($groups[$pid]['name'], $groups[$pid]['items']);
                            unset($groups[$pid]);
                        } else {
                            // Nếu không có dữ liệu danh mục này, vẫn hiển thị khối trống
                            $title = $pid === 1 ? 'Khai vị' : ($pid === 2 ? 'Món chính' : 'Tráng miệng');
                            renderSection($title, []);
                        }
                    }

                    // Hiển thị các danh mục mới được thêm (ví dụ: Đồ uống, Đặc biệt, ...)
                    foreach ($groups as $grp) {
                        renderSection($grp['name'], $grp['items']);
                    }
                    ?>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <div class="text-muted">Tổng tiền</div>
                        <div class="fs-5 fw-bold text-primary"><?php echo number_format((float)$tongtien); ?> VNĐ</div>
                    </div>
                    </div>
                </div>
                <?php } ?>
            </div>
            
                    <?php
                        // Nếu trạng thái là bị từ chối, hiển thị ghi chú 
                        $isRejected = (strpos($lower, 'rejected') !== false) ;
                        if ($isRejected) {
                            if ($ghichu !== '') {
                                echo"<div class='card shadow'>
                                        <div class='card-body'>
                                            <div class='mb-3'>
                                                <label class='form-label fw-semibold'>Lý do</label>
                                                <textarea class='form-control' rows='3' readonly>" . htmlspecialchars($ghichu, ENT_QUOTES, 'UTF-8') . "</textarea>
                                            </div>
                                        </div>
                                    </div>";
                            }
                        }
                    ?>
                </div>
            </div>
            <!-- Xử lý duyệt thực đơn -->
            <div class ="text-center mt-3">
                <?php if ($lower == 'pending') {?>
                    <!-- Chấp nhận -->
                    <form method="post" style="display:inline-block; margin-right:8px;">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="idthucdon" value="<?= htmlspecialchars($idthucdon) ?>">
                        <a href="index.php?page=dsthucdon" class="btn btn-primary" onclick="event.preventDefault(); this.closest('form').submit();">Xác nhận</a>
                    </form>
                    <!-- Từ chối -->
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">Từ chối</button>
                <?php }?>
            </div>

            <!-- Modal lý do từ chối -->
                <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <form method="post" id="rejectForm">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Lý do từ chối</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="reject_reason" class="form-label">Lý do</label>
                                        <textarea class="form-control" id="reject_reason" name="reason" rows="4" required></textarea>
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="idthucdon" value="<?= htmlspecialchars($idthucdon) ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Huỷ</button>
                                    <button type="submit" class="btn btn-danger">Lưu</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
        </div>
    </div>
</div>