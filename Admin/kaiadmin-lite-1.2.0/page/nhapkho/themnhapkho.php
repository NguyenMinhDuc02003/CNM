<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('them nhap kho', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
// Lấy danh sách nhà cung cấp
$queryNCC = "SELECT idncc, tennhacungcap FROM nhacungcap ORDER BY tennhacungcap";
$resultNCC = mysqli_query($conn, $queryNCC);
$suppliers = [];
if ($resultNCC) {
	while ($row = mysqli_fetch_assoc($resultNCC)) { $suppliers[] = $row; }
}

// Lấy danh sách tồn kho để chọn nguyên liệu
$queryTK = "SELECT matonkho, tentonkho, DonViTinh FROM tonkho ORDER BY tentonkho";
$resultTK = mysqli_query($conn, $queryTK);
$items = [];
if ($resultTK) {
	while ($row = mysqli_fetch_assoc($resultTK)) { $items[] = $row; }
}
//Hàm chuyển đổi chuỗi thành số thực (float)
function parseNumber($v) {
	$s = trim((string)$v);
	if ($s === '') return 0.0;
	$s = str_replace(' ', '', $s);
	if (strpos($s, ',') !== false && strpos($s, '.') === false) {
		$s = str_replace('.', '', $s);
		$s = str_replace(',', '.', $s);
	} else {
		$s = str_replace(',', '', $s);
	}
	return is_numeric($s) ? (float)$s : 0.0;
}
function toFloat($v) { return parseNumber($v); }
function toInt($v) { return (int)parseNumber($v); }

// Xử lý lưu DB
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$maDon = $_POST['ma_don'] ?? '';
	$ngayNhap = $_POST['ngay_nhap'] ?? date('Y-m-d');
	$idncc = toInt($_POST['ncc'] ?? 0);
	$ghiChu = $_POST['ghichu'] ?? '';
	$rows = $_POST['rows'] ?? [];
// Xử lý upload hình ảnh
$hinhanh = '';
if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
    $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $filename = $_FILES['hinhanh']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {
        $newname = uniqid() . '.' . $ext;
        // Đường dẫn thư mục tuyệt đối tới assets/img trong Admin/kaiadmin-lite-1.2.0
        $uploadDir = dirname(__DIR__, 2) . '/assets/img/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $destination = $uploadDir . $newname;

        if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $destination)) {
            $hinhanh = $newname;
        } else {
            echo "<script>alert('Lỗi khi upload hình ảnh! Vui lòng kiểm tra lại quyền thư mục.');</script>";
            exit;
        }
    } else {
        echo "<script>alert('Chỉ cho phép upload file ảnh có định dạng: jpg, jpeg, png, gif');</script>";
        exit;
    }
}
	// Debug: In ra dữ liệu nhận được (có thể xóa sau khi test xong)
	// error_log("Debug - Dữ liệu form: " . print_r($_POST, true));
	// error_log("Debug - Rows data: " . print_r($rows, true));
	// error_log("Debug - Count rows: " . count($rows));

	if (!$idncc) {
		echo "<script>alert('Vui lòng chọn nhà cung cấp.'); history.back();</script>";
		exit;
	}

	mysqli_begin_transaction($conn);
	try {
		// Tính tổng tiền trong lúc duyệt chi tiết
		$tongtien = 0.0;

		// Insert nhapkho: thêm tennhapkho = ma_don (tạm thời tongtien=0, lát nữa cập nhật)
		$trangthai = 'Chua xac nhan';
		$sqlNK = "INSERT INTO nhapkho (tennhapkho, idncc, ngaynhap, tongtien, trangthai, ghichu, hinhanh) VALUES (?, ?, ?, ?, ?, ?, ?)";
		$stmt = mysqli_prepare($conn, $sqlNK);
		if (!$stmt) { throw new Exception('Lỗi chuẩn bị nhapkho: '.mysqli_error($conn)); }
		if (!mysqli_stmt_bind_param($stmt, "sisdsss", $maDon, $idncc, $ngayNhap, $tongtien, $trangthai, $ghiChu, $hinhanh)) { throw new Exception('Lỗi bind nhapkho'); }
		if (!mysqli_stmt_execute($stmt)) { throw new Exception('Lỗi thêm nhapkho: '.mysqli_stmt_error($stmt)); }
		mysqli_stmt_close($stmt);     
		$manhapkhoId = mysqli_insert_id($conn);
		if (!$manhapkhoId) {
			// Fallback: lấy id theo tennhapkho vừa chèn
			$stmtGet = mysqli_prepare($conn, "SELECT manhapkho FROM nhapkho WHERE tennhapkho = ? ORDER BY manhapkho DESC LIMIT 1");
			if (!$stmtGet) { throw new Exception('Lỗi chuẩn bị lấy mã nhập kho: '.mysqli_error($conn)); }
			mysqli_stmt_bind_param($stmtGet, "s", $maDon);
			mysqli_stmt_execute($stmtGet);
			mysqli_stmt_bind_result($stmtGet, $manhapkhoId);
			mysqli_stmt_fetch($stmtGet);
			mysqli_stmt_close($stmtGet);
			if (!$manhapkhoId) { throw new Exception('Không lấy được mã nhập kho vừa tạo'); }
		}

		// Insert chi tiết + cập nhật tồn kho
		$sqlCT = "INSERT INTO chitietnhapkho (manhapkho, matonkho, soluongdat, soluongthucte, dongia, thanhtien) VALUES (?, ?, ?, ?, ?, ?)";
		$ctStmt = mysqli_prepare($conn, $sqlCT);
		if (!$ctStmt) { throw new Exception('Lỗi chuẩn bị chitiet: '.mysqli_error($conn)); }
		$sqlUpd = "UPDATE tonkho SET soluong = soluong + ? WHERE matonkho = ?";
		$updStmt = mysqli_prepare($conn, $sqlUpd);
		if (!$updStmt) { throw new Exception('Lỗi chuẩn bị cập nhật tồn kho: '.mysqli_error($conn)); }

		$insertedAny = false;
		foreach ($rows as $r) {
			$matonkho = toInt($r['matonkho'] ?? 0);
			$soluongdat = toFloat($r['soluongdat'] ?? 0);
			$soluongtt = toFloat($r['soluongthucte'] ?? 0);
			if ($soluongtt <= 0) { $soluongtt = $soluongdat; }
			$dongia = toFloat($r['dongia'] ?? 0);
			$thanhtien = $soluongtt * $dongia;
			
			
			if ($matonkho <= 0) { continue; }
			mysqli_stmt_bind_param($ctStmt, "iidddd", $manhapkhoId, $matonkho, $soluongdat, $soluongtt, $dongia, $thanhtien);
			if (!mysqli_stmt_execute($ctStmt)) { throw new Exception('Lỗi thêm chitiet: '.mysqli_stmt_error($ctStmt)); }
			mysqli_stmt_bind_param($updStmt, "di", $soluongtt, $matonkho);
			if (!mysqli_stmt_execute($updStmt)) { throw new Exception('Lỗi cập nhật tồn kho: '.mysqli_stmt_error($updStmt)); }
			$tongtien += $thanhtien;
			$insertedAny = true;
		}
		mysqli_stmt_close($ctStmt);
		mysqli_stmt_close($updStmt);

		if (!$insertedAny) { throw new Exception('Chưa có dòng chi tiết hợp lệ để lưu.'); }

		// Cập nhật lại tổng tiền vào nhapkho
		$updNK = mysqli_prepare($conn, "UPDATE nhapkho SET tongtien = ? WHERE manhapkho = ?");
		if (!$updNK) { throw new Exception('Lỗi chuẩn bị cập nhật tổng tiền: '.mysqli_error($conn)); }
		mysqli_stmt_bind_param($updNK, "di", $tongtien, $manhapkhoId);
		if (!mysqli_stmt_execute($updNK)) { throw new Exception('Lỗi cập nhật tổng tiền: '.mysqli_stmt_error($updNK)); }
		mysqli_stmt_close($updNK);

		mysqli_commit($conn);
		echo "<script>alert('Đã lưu đơn nhập kho thành công!'); window.location.href='index.php?page=nhapkho';</script>";
		exit;
	} catch (Exception $e) {
		mysqli_rollback($conn);
		echo "<script>alert('".addslashes($e->getMessage())."'); history.back();</script>";
		exit;
	}
}
?>

<div class="container mb-5">
  <div class="card shadow m-5">
  <div class="row g-4">
      <div class="col-12 col-lg-8">
        <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="mb-0">Đơn nhập kho</h3>
      </div>
    <div class="card-body">
      <form id="formNhapKho" action="" method="POST" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="ma_don" class="form-label">Mã đơn nhập kho</label>
          <input type="text" id="ma_don" name="ma_don" class="form-control" placeholder="VD: NK-2025-001" readonly />
        </div>
        <div class="mb-3">
          <label for="ngay_nhap" class="form-label">Ngày nhập</label>
          <input type="date" id="ngay_nhap" name="ngay_nhap" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
        </div>
        <div class="mb-3">
          <label for="ncc" class="form-label">Nhà cung cấp</label>
          <select id="ncc" name="ncc" class="form-select" required>
            <option value="">-- Chọn nhà cung cấp --</option>
            <?php if (!empty($suppliers)) { foreach ($suppliers as $dm) { ?>
              <option value="<?php echo $dm['idncc']; ?>"><?php echo htmlspecialchars($dm['tennhacungcap']); ?></option>
            <?php } } else { ?>
              <option value="" disabled>(Chưa có nhà cung cấp)</option>
            <?php } ?>
          </select>
        </div>

        <div class="table-responsive mb-2">
          <table class="table table-bordered align-middle" id="tblNhapKho">
            <thead class="table-light">
              <tr>
                <th style="width:60px" class="text-center">STT</th>
                <th style="min-width:260px">Tên</th>
                <th style="width:140px" class="text-end">Số lượng</th>
                <th style="width:160px" class="text-end">Số lượng thực tế</th>
                <th style="width:160px" class="text-end">Đơn giá (VND)</th>
                <th style="width:180px" class="text-end">Thành tiền (VND)</th>
                <th style="width:60px"></th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="d-flex justify-content-end me-1 mb-3">
          <div>
            <div class="text-muted small text-end">Tổng tiền (VND)</div>
            <div class="fs-5 fw-bold text-end" id="tongTienText">0</div>
          </div>
        </div>
        <div class="mb-3">
          <button type="button" id="btnAddRow" class="btn btn-outline-primary">
            <i class="fas fa-plus"></i> Thêm dòng
          </button>
        </div>

        <div class="mb-3">
          <label for="ghichu" class="form-label">Ghi chú</label>
          <textarea id="ghichu" name="ghichu" class="form-control" rows="3" placeholder="Ghi chú cho đơn nhập..."></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2">
          <a href="index.php?page=nhapkho" class="btn btn-light"><i class="fas fa-times"></i> Hủy</a>
          <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Xác nhận</button>
        </div>
      </form>
    </div>
  </div>
  <div class="col-4">
      <h3 class="card-header">Chứng từ</h3>
      <div class="card-body">
        <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*" form="formNhapKho" />
      </div>
    </div>
    </div>
</div>

<script>
  const tbody = document.querySelector('#tblNhapKho tbody');
  const btnAddRow = document.getElementById('btnAddRow');
  const maDonInput = document.getElementById('ma_don');
  const totalText = document.getElementById('tongTienText');
  const ITEMS = <?php echo json_encode($items, JSON_UNESCAPED_UNICODE); ?>;

  function updateSTT() {
    const rows = tbody.querySelectorAll('tr');
    rows.forEach((tr, idx) => {
      tr.querySelector('.col-stt').textContent = String(idx + 1);
    });
  }

  function formatCurrency(val) {
    if (isNaN(val) || !isFinite(val)) return '0';
    return new Intl.NumberFormat('vi-VN').format(val);
  }

  function recomputeTotal() {
    let sum = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const realQty = parseFloat(tr.querySelector('.input-realqty')?.value || '0');
      const price = parseFloat(tr.querySelector('.input-price')?.value || '0');
      if (isFinite(realQty) && isFinite(price)) sum += realQty * price;
    });
    totalText.textContent = formatCurrency(sum);
  }

  function recalcRow(tr) {
    const realQtyInput = tr.querySelector('.input-realqty');
    const priceInput = tr.querySelector('.input-price');
    const totalCell = tr.querySelector('.cell-total');

    const realQty = parseFloat(realQtyInput.value || '0');
    const price = parseFloat(priceInput.value || '0');
    const total = (isFinite(realQty) ? realQty : 0) * (isFinite(price) ? price : 0);
    totalCell.textContent = formatCurrency(total);
    recomputeTotal();
  }

  let rowIndex = 0;
  
  function addRow(defaults = {}) {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="col-stt text-center"></td>
      <td>
        <select name="rows[${rowIndex}][matonkho]" class="form-select" required>
          <option value="">-- Chọn nguyên liệu --</option>
          ${ITEMS.map(i => `<option value="${i.matonkho}">${i.tentonkho}</option>`).join('')}
        </select>
      </td>
      <td>
        <input type="number" min="0" step="1" name="rows[${rowIndex}][soluongdat]" class="form-control text-end input-qty" placeholder="0" value="${defaults.soluongdat || ''}" required />
      </td>
      <td>
        <input type="number" min="0" step="1" name="rows[${rowIndex}][soluongthucte]" class="form-control text-end input-realqty" placeholder="0" value="${defaults.soluongthucte || ''}" required />
      </td>
      <td>
        <input type="number" min="0" step="1000" name="rows[${rowIndex}][dongia]" class="form-control text-end input-price" placeholder="0" value="${defaults.dongia || ''}" required />
      </td>
      <td class="text-end cell-total"></td>
      <td class="text-center">
        <button type="button" class="btn btn-outline-danger btn-sm btn-remove" title="Xoá dòng">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    `;
    
    rowIndex++;

    tr.querySelectorAll('.input-realqty, .input-price').forEach(el => {
      el.addEventListener('input', () => recalcRow(tr));
    });

    tr.querySelector('.btn-remove').addEventListener('click', () => {
      tr.remove();
      updateSTT();
      recomputeTotal();
    });

    tbody.appendChild(tr);
    updateSTT();
    recalcRow(tr);
  }

  function generateCode() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const rand = Math.floor(1000 + Math.random() * 9000); // 4 digits
    return `NK-${y}${m}${d}-${rand}`;
  }

  document.addEventListener('DOMContentLoaded', () => {
    maDonInput.value = generateCode();
  });

  btnAddRow.addEventListener('click', () => addRow());
  addRow();
</script>

<?php mysqli_close($conn); ?>