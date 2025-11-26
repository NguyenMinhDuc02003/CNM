<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('sua thuc don', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// AJAX: Tạo danh mục mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_dm') {
    header('Content-Type: application/json; charset=utf-8');
    $ten = trim($_POST['tendanhmuc'] ?? '');
    if ($ten === '') {
        echo json_encode(['ok' => false, 'message' => 'Tên danh mục không được rỗng']);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO danhmuc (tendanhmuc) VALUES (?)");
    if (!$stmt) { echo json_encode(['ok' => false, 'message' => 'Lỗi kết nối']); exit; }
    $stmt->bind_param('s', $ten);
    if ($stmt->execute()) {
        $iddm = $stmt->insert_id;
        $stmt->close();
        echo json_encode(['ok' => true, 'iddm' => (int)$iddm, 'tendanhmuc' => $ten]);
    } else {
        $stmt->close();
        echo json_encode(['ok' => false, 'message' => 'Không thể tạo danh mục']);
    }
    exit;
}

// Lấy id thực đơn
$idthucdon = isset($_GET['idthucdon']) ? intval($_GET['idthucdon']) : 0;

$menuName = null;
$menuImage = null;
$menuMota = null;
$items = [];
$tongtien = 0;
$selectedMonAn = [];

if ($conn && $idthucdon > 0) {
    // Lấy thông tin thực đơn
    $stmt = $conn->prepare("SELECT tenthucdon, mota, hinhanh, tongtien FROM thucdon WHERE idthucdon = ?");
    if ($stmt) {
        $stmt->bind_param("i", $idthucdon);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            $menuName = $row['tenthucdon'];
            $menuMota = $row['mota'];
            $menuImage = $row['hinhanh'];
            $tongtien = (float)$row['tongtien'];
        }
        $stmt->close();
    }

    // Lấy danh sách món đã chọn
    $stmt = $conn->prepare("SELECT idmonan FROM chitietthucdon WHERE idthucdon = ?");
    if ($stmt) {
        $stmt->bind_param("i", $idthucdon);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $selectedMonAn[] = (int)$row['idmonan'];
        }
        $stmt->close();
    }
}

// Xử lý submit cập nhật thực đơn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['idthucdon'])) {
    $idthucdon = (int)$_POST['idthucdon'];
    $tenthucdon = trim($_POST['tenthucdon'] ?? '');
    $mota = trim($_POST['mota'] ?? '');
    $selectedMonAn = $_POST['selected_monan'] ?? [];

    // Upload ảnh nếu có
    $uploadedFileName = $menuImage; // Giữ ảnh cũ
    if (isset($_FILES['hinhanh']) && is_uploaded_file($_FILES['hinhanh']['tmp_name'])) {
        $ext = pathinfo($_FILES['hinhanh']['name'], PATHINFO_EXTENSION);
        $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['hinhanh']['name'], PATHINFO_FILENAME));
        $uploadedFileName = $safeBase . '_' . time() . '.' . $ext;
        $targetDir = __DIR__ . '/../../assets/img/';
        if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
        move_uploaded_file($_FILES['hinhanh']['tmp_name'], $targetDir . $uploadedFileName);
    }

    // Tính tổng tiền từ các món đã chọn
    $tongtien = 0;
    if (!empty($selectedMonAn)) {
        $in = implode(',', array_map('intval', $selectedMonAn));
        $sqlGia = "SELECT SUM(DonGia) AS tong FROM monan WHERE idmonan IN ($in)";
        $resGia = $conn->query($sqlGia);
        if ($resGia && $rowGia = $resGia->fetch_assoc()) {
            $tongtien = (float)($rowGia['tong'] ?? 0);
        }
    }

    // Ghi đè tổng tiền nếu người dùng chỉnh tay
    if (isset($_POST['tongtien_override']) && $_POST['tongtien_override'] !== '') {
        $override = filter_var($_POST['tongtien_override'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if (is_numeric($override)) {
            $tongtien = (float)$override;
        }
    }

    // Update thucdon
    $stmtUpdate = $conn->prepare("UPDATE thucdon SET tenthucdon = ?, mota = ?, tongtien = ?, hinhanh = ? WHERE idthucdon = ?");
    $stmtUpdate->bind_param('ssdsi', $tenthucdon, $mota, $tongtien, $uploadedFileName, $idthucdon);
    if ($stmtUpdate->execute()) {
        $stmtUpdate->close();

        // Xóa chi tiết cũ
        $stmtDelete = $conn->prepare("DELETE FROM chitietthucdon WHERE idthucdon = ?");
        $stmtDelete->bind_param('i', $idthucdon);
        $stmtDelete->execute();
        $stmtDelete->close();

        // Insert chi tiết mới
        if (!empty($selectedMonAn)) {
            $stmtCT = $conn->prepare("INSERT INTO chitietthucdon (idthucdon, idmonan) VALUES (?, ?)");
            foreach ($selectedMonAn as $idmonan) {
                $idmonan = (int)$idmonan;
                $stmtCT->bind_param('ii', $idthucdon, $idmonan);
                $stmtCT->execute();
            }
            $stmtCT->close();
        }

        echo "<script>window.location.href='index.php?page=dsthucdon';</script>";
        exit;
    } else {
        $stmtUpdate->close();
        echo "<script>alert('Cập nhật thực đơn thất bại');</script>";
    }
}

// Tải danh mục và món ăn
$danhmuc = [];
$rsDm = $conn->query("SELECT iddm, tendanhmuc FROM danhmuc ORDER BY iddm ASC");
while ($rsDm && $row = $rsDm->fetch_assoc()) { $danhmuc[] = $row; }

$monAnByDm = [];
$rsMa = $conn->query("SELECT idmonan, tenmonan, DonGia, hinhanh, iddm FROM monan WHERE TrangThai='approved' ORDER BY iddm, tenmonan");
while ($rsMa && $row = $rsMa->fetch_assoc()) {
    $monAnByDm[(int)$row['iddm']][] = $row;
}
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="d-flex ">
            <form id="formUpdateMenu" method="post" enctype="multipart/form-data" class="card shadow-sm border-0" >
                <div class="card-body p-4" >
                    <div class="row g-4">
                        <div class="col-12">
                            <h5 class="mb-3">Sửa thực đơn</h5>
                            <div class="row g-3">
                                <div class="col-12">
                            <label class="form-label">Ảnh thực đơn</label>
                            <input type="file" class="form-control" name="hinhanh" id="inputHinhanh" accept="image/*">
                            <div class="form-text">Hỗ trợ: jpg, png, webp... (Để trống nếu không muốn thay đổi)</div>
                            <input type="hidden" name="idthucdon" value="<?php echo $idthucdon; ?>">
                                </div>
                                <div class="col-12">
                            <label class="form-label">Tên thực đơn</label>
                            <input type="text" class="form-control" name="tenthucdon" id="inputTenThucDon" placeholder="Nhập tên thực đơn" value="<?php echo htmlspecialchars($menuName ?? ''); ?>" required>
                                </div>
                                <div class="col-12">
                            <label class="form-label">Mô tả</label>
                            <textarea class="form-control" name="mota" id="inputMoTa" rows="3" placeholder="Mô tả ngắn..."><?php echo htmlspecialchars($menuMota ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="mt-4" id="dmSections">
                            <?php foreach ($danhmuc as $dm) { $dmId=(int)$dm['iddm']; $list=$monAnByDm[$dmId] ?? []; ?>
                            <div class="mb-4">
                                <div class="d-flex align-items-center mb-3">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($dm['tendanhmuc']); ?></h5>
                                    <div class="flex-grow-1 border-bottom ms-3" style="opacity:.3"></div>
                                </div>
                                <?php if (empty($list)) { ?>
                                <div class="p-4 border rounded-3 text-center text-muted bg-light">Chưa có món trong danh mục này</div>
                                <?php } else { ?>
                                <div class="row g-3 row-cols-1 row-cols-md-2">
                                    <?php foreach ($list as $it) { 
                                        $isSelected = in_array((int)$it['idmonan'], $selectedMonAn);
                                    ?>
                                    <div class="col">
                                        <div class="d-flex p-3 border rounded-3 h-100 align-items-center <?php echo $isSelected ? 'border-primary bg-light' : ''; ?>">
                                            <div style="width:64px;height:64px;overflow:hidden;border-radius:10px" class="bg-light flex-shrink-0 d-flex align-items-center justify-content-center me-3">
                                                <?php if (!empty($it['hinhanh'])) { ?>
                                                <img src="../../User/restoran-1.0.0/img/<?php echo htmlspecialchars($it['hinhanh']); ?>" alt="img" style="max-width:100%;max-height:100%">
                                                <?php } else { ?>
                                                <i class="fas fa-image text-muted"></i>
                                                <?php } ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div class="fw-semibold me-2"><?php echo htmlspecialchars($it['tenmonan']); ?></div>
                                                    <div class="fw-semibold text-primary ms-2 white-space-nowrap"><?php echo number_format((float)$it['DonGia']); ?> VNĐ</div>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm <?php echo $isSelected ? 'btn-success' : 'btn-primary'; ?> ms-3 btnPickMon" 
                                                data-id="<?php echo (int)$it['idmonan']; ?>"
                                                data-ten="<?php echo htmlspecialchars($it['tenmonan']); ?>"
                                                data-dongia="<?php echo (float)$it['DonGia']; ?>"
                                                data-hinh="<?php echo htmlspecialchars($it['hinhanh']); ?>"
                                                data-iddm="<?php echo (int)$it['iddm']; ?>"
                                                data-dmname="<?php echo htmlspecialchars($dm['tendanhmuc']); ?>"
                                                data-selected="<?php echo $isSelected ? 'true' : 'false'; ?>">
                                                <?php echo $isSelected ? 'Đã chọn' : 'Chọn'; ?>
                                            </button>
                                        </div>
                                    </div>
                                    <?php } ?>
                                </div>
                                <?php } ?>
                            </div>
                            <?php } ?>
                            <!-- Hidden inputs for selected items to submit -->
                            <div id="selectedHiddenInputs"></div>
                        </div>
                    </div>
                    
                </div>
                
            </form>
            <div class="card-footer d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-success d-none">Cập nhật</button>
                </div>
        
        </div>
        <div class="col-5 m-4">
            <form class="card shadow-sm border-0 mt-4" onsubmit="return false;">
                <div class="card-body p-0">
                    <div class="w-100 <?php echo !empty($menuImage) ? '' : 'd-none'; ?>" id="previewBannerWrapper" style="height:220px;background:url('assets/img/<?php echo htmlspecialchars($menuImage ?? ''); ?>') center/cover no-repeat;border-top-left-radius:.375rem;border-top-right-radius:.375rem"></div>
                    <div class="w-100 d-flex align-items-center justify-content-center text-muted bg-light <?php echo empty($menuImage) ? '' : 'd-none'; ?>" id="previewBannerPlaceholder" style="height:220px;border-top-left-radius:.375rem;border-top-right-radius:.375rem">Hình ảnh đang cập nhật</div>
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1 text-center">
                                <h1 class="mb-1 text-primary"><b id="previewTitle"><?php echo htmlspecialchars($menuName ?? 'Thực đơn'); ?></b></h1>
                                <p class="mb-0 text-muted" id="previewDesc"><?php echo htmlspecialchars($menuMota ?? ''); ?></p>
                            </div>
                        </div>
                        <div id="previewSections"></div>
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                            <div class="text-muted">Tổng tiền</div>
                            <div class="d-flex align-items-center gap-2">
                                <input form="formUpdateMenu" type="text" name="tongtien_override" id="tongTienInput" class="form-control form-control-sm text-end" style="width: 160px" placeholder="0" value="<?php echo $tongtien; ?>" />
                            </div>
                        </div>
                        <div class="text-end mt-3">
                            <button form="formUpdateMenu" type="submit" class="btn btn-success">Cập nhật</button>
                        </div>
                    </div>
                </div>
            </form>
            </div>
    </div>
</div>

<script>
const monAnByDm = <?php echo json_encode($monAnByDm, JSON_UNESCAPED_UNICODE); ?>;
const selected = new Map(); // idmonan -> { idmonan, tenmonan, DonGia, hinhanh }

// Khởi tạo selected với dữ liệu hiện tại
<?php foreach ($selectedMonAn as $idmonan) { ?>
<?php 
    // Tìm thông tin món ăn từ monAnByDm
    $monInfo = null;
    foreach ($monAnByDm as $dmId => $monList) {
        foreach ($monList as $mon) {
            if ((int)$mon['idmonan'] === (int)$idmonan) {
                $monInfo = $mon;
                break 2;
            }
        }
    }
    if ($monInfo) {
?>
selected.set(<?php echo (int)$idmonan; ?>, {
    idmonan: <?php echo (int)$idmonan; ?>,
    tenmonan: '<?php echo addslashes($monInfo['tenmonan']); ?>',
    DonGia: <?php echo (float)$monInfo['DonGia']; ?>,
    hinhanh: '<?php echo addslashes($monInfo['hinhanh']); ?>',
    iddm: <?php echo (int)$monInfo['iddm']; ?>
});
<?php } ?>
<?php } ?>

const selectedHiddenInputs = document.getElementById('selectedHiddenInputs');
const previewSections = document.getElementById('previewSections');
const tongTienText = document.getElementById('tongTienText');
const tongTienInput = document.getElementById('tongTienInput');
const inputHinhanh = document.getElementById('inputHinhanh');
const previewTitle = document.getElementById('previewTitle');
const previewDesc = document.getElementById('previewDesc');
const previewBannerWrapper = document.getElementById('previewBannerWrapper');
const previewBannerPlaceholder = document.getElementById('previewBannerPlaceholder');
const inputTenThucDon = document.getElementById('inputTenThucDon');
const inputMoTa = document.getElementById('inputMoTa');

function formatVND(n) { return new Intl.NumberFormat('vi-VN').format(n) + ' VNĐ'; }

// Preview ảnh khi chọn
inputHinhanh?.addEventListener('change', () => {
  const file = inputHinhanh.files && inputHinhanh.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    if (previewBannerWrapper) {
      previewBannerWrapper.classList.remove('d-none');
      previewBannerWrapper.style.background = `url(${e.target.result}) center/cover no-repeat`;
      previewBannerWrapper.innerHTML = '';
    }
    if (previewBannerPlaceholder) {
      previewBannerPlaceholder.classList.add('d-none');
    }
  };
  reader.readAsDataURL(file);
});

// Xử lý click chọn món
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btnPickMon');
  if (!btn) return;
  const id = parseInt(btn.dataset.id || '0', 10);
  if (!id) return;
  
  const isSelected = btn.dataset.selected === 'true';
  const parentDiv = btn.closest('.col').querySelector('.d-flex');
  
  if (isSelected) {
    // Bỏ chọn
    selected.delete(id);
    btn.dataset.selected = 'false';
    btn.textContent = 'Chọn';
    btn.className = 'btn btn-sm btn-primary ms-3 btnPickMon';
    parentDiv.classList.remove('border-primary', 'bg-light');
  } else {
    // Chọn món
    const item = {
      idmonan: id,
      tenmonan: btn.dataset.ten || '',
      DonGia: parseFloat(btn.dataset.dongia || '0'),
      hinhanh: btn.dataset.hinh || '',
      iddm: parseInt(btn.dataset.iddm || '0', 10)
    };
    selected.set(id, item);
    btn.dataset.selected = 'true';
    btn.textContent = 'Đã chọn';
    btn.className = 'btn btn-sm btn-success ms-3 btnPickMon';
    parentDiv.classList.add('border-primary', 'bg-light');
  }
  
  renderSelected();
});

function removeItem(id) { 
  selected.delete(id); 
  renderSelected(); 
  // Cập nhật UI của nút
  const btn = document.querySelector(`[data-id="${id}"]`);
  if (btn) {
    btn.dataset.selected = 'false';
    btn.textContent = 'Chọn';
    btn.className = 'btn btn-sm btn-primary ms-3 btnPickMon';
    const parentDiv = btn.closest('.col').querySelector('.d-flex');
    parentDiv.classList.remove('border-primary', 'bg-light');
  }
}

function renderSelected() {
  selectedHiddenInputs.innerHTML = '';
  previewSections.innerHTML = '';
  let sum = 0;
  
  // Tạo cache cho danh mục
  if (!window.btnCache) window.btnCache = {};
  
  // group by iddm for preview
  const groups = new Map(); // iddm -> { name, items: [] }
  for (const [id, it] of selected) {
    sum += it.DonGia || 0;
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'selected_monan[]';
    input.value = id;
    selectedHiddenInputs.appendChild(input);
    
    const key = it.iddm ?? null;
    let name = '';
    
    // Tìm tên danh mục từ monAnByDm
    for (const [dmId, monList] of Object.entries(monAnByDm)) {
      if (parseInt(dmId) === it.iddm) {
        // Tìm danh mục từ danhmuc array
        <?php foreach ($danhmuc as $dm) { ?>
        if (<?php echo (int)$dm['iddm']; ?> === it.iddm) {
          name = '<?php echo addslashes($dm['tendanhmuc']); ?>';
        }
        <?php } ?>
        break;
      }
    }
    
    if (key !== null) {
      if (!groups.has(key)) groups.set(key, { name, items: [] });
      groups.get(key).items.push({ id, ...it });
    }
  }
  
  // render sections
  const renderGroup = (title, items) => {
    const wrap = document.createElement('div');
    wrap.className = 'mb-4';
    wrap.innerHTML = `
      <div class="d-flex align-items-center mb-3">
        <h5 class="mb-0">${title || ''}</h5>
        <div class="flex-grow-1 border-bottom ms-3" style="opacity:.3"></div>
      </div>`;
    const list = document.createElement('div');
    if (!items.length) {
      list.className = 'p-4 border rounded-3 text-center text-muted bg-light';
      list.textContent = 'Chưa cấu hình món cho mục này.';
    } else {
      list.className = 'row g-2';
      for (const it of items) {
        const col = document.createElement('div');
        col.className = 'col-12';
        col.innerHTML = `
          <div class="d-flex p-3 border rounded-3 align-items-center">
            <div style="width:56px;height:56px;overflow:hidden;border-radius:8px" class="bg-light d-flex align-items-center justify-content-center me-3">
              ${it.hinhanh ? `<img src="../../User/restoran-1.0.0/img/${it.hinhanh}" style="max-width:100%;max-height:100%">` : '<i class="fas fa-image text-muted"></i>'}
            </div>
            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-center">
                <div class="fw-semibold">${it.tenmonan}</div>
                <div class="fw-semibold text-primary">${formatVND(it.DonGia||0)}</div>
              </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger ms-3" onclick="removeItem(${it.id})"><i class="fas fa-times"></i></button>
          </div>`;
        list.appendChild(col);
      }
    }
    wrap.appendChild(list);
    previewSections.appendChild(wrap);
  };
  
  // preferred groups 1,2,3 then others
  const preferred = [1,2,3];
  for (const pid of preferred) {
    const g = groups.get(pid);
    if (g) { 
      renderGroup(g.name, g.items); 
      groups.delete(pid); 
    } else { 
      renderGroup(pid===1?'Khai vị':pid===2?'Món chính':'Tráng miệng', []); 
    }
  }
  for (const [_, g] of groups) renderGroup(g.name, g.items);
  
  if (tongTienText) tongTienText.textContent = formatVND(sum);
  if (document.activeElement !== tongTienInput && tongTienInput) {
    tongTienInput.value = sum;
  }
}

// Cho phép chỉnh tay tổng tiền và phản ánh vào preview
tongTienInput?.addEventListener('input', () => {
  const v = parseFloat((tongTienInput.value || '0').toString().replace(/[^0-9.]/g, '')) || 0;
  if (tongTienText) tongTienText.textContent = formatVND(v);
});

// Live update title/description in preview
inputTenThucDon?.addEventListener('input', () => {
  previewTitle.textContent = inputTenThucDon.value || 'Thực đơn';
});
inputMoTa?.addEventListener('input', () => {
  previewDesc.textContent = inputMoTa.value || '';
});

// Khởi tạo preview với dữ liệu hiện tại
renderSelected();
</script>