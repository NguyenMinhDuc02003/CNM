<?php
include("class/clsconnect.php");
include_once 'includes/config_permission.php';

// Kiểm tra quyền truy cập
if (!hasPermission('sua nhap kho', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
$nhapkho = null;
$detailRows = [];
$manhapkho = isset($_GET['manhapkho']) ? (int)$_GET['manhapkho'] : 0;

if ($manhapkho <= 0) {
    echo "<script>alert('Thieu ma nhap kho.'); window.location.href='index.php?page=nhapkho';</script>";
    exit;
}

$query = "SELECT nk.*, ctnk.*, ncc.tennhacungcap, tk.tentonkho 
        FROM nhapkho nk 
        JOIN chitietnhapkho ctnk ON nk.manhapkho = ctnk.manhapkho 
        JOIN nhacungcap ncc ON nk.idncc = ncc.idncc
        JOIN tonkho tk ON ctnk.matonkho = tk.matonkho
        WHERE nk.manhapkho = ?";
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    echo "<script>alert('Khong the doc du lieu don nhap kho.'); window.location.href='index.php?page=nhapkho';</script>";
    exit;
}
mysqli_stmt_bind_param($stmt, "i", $manhapkho);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && $result->num_rows > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        if ($nhapkho === null) {
            $nhapkho = $row;
        }
        $detailRows[] = [
            'matonkho' => (int)$row['matonkho'],
            'tentonkho' => $row['tentonkho'],
            'soluongdat' => (float)$row['soluongdat'],
            'soluongthucte' => (float)$row['soluongthucte'],
            'dongia' => (float)$row['dongia'],
            'thanhtien' => (float)$row['thanhtien'],
        ];
    }
} else {
    echo "<script>alert('Khong tim thay don nhap kho.'); window.location.href='index.php?page=nhapkho';</script>";
    exit;
}
mysqli_stmt_close($stmt);

$queryNCC = "SELECT idncc, tennhacungcap FROM nhacungcap ORDER BY tennhacungcap";
$resultNCC = mysqli_query($conn, $queryNCC);
$suppliers = [];
if ($resultNCC) {
    while ($row = mysqli_fetch_assoc($resultNCC)) {
        $suppliers[] = $row;
    }
}

$queryTK = "SELECT matonkho, tentonkho, DonViTinh FROM tonkho ORDER BY tentonkho";
$resultTK = mysqli_query($conn, $queryTK);
$items = [];
if ($resultTK) {
    while ($row = mysqli_fetch_assoc($resultTK)) {
        $items[] = $row;
    }
}

$itemNameMap = [];
foreach ($items as $item) {
    $itemNameMap[$item['matonkho']] = $item['tentonkho'];
}

function parseNumber($value) {
    $string = trim((string)$value);
    if ($string === '') {
        return 0.0;
    }
    $string = str_replace(' ', '', $string);
    if (strpos($string, ',') !== false && strpos($string, '.') === false) {
        $string = str_replace('.', '', $string);
        $string = str_replace(',', '.', $string);
    } else {
        $string = str_replace(',', '', $string);
    }
    return is_numeric($string) ? (float)$string : 0.0;
}
function toFloat($value) {
    return parseNumber($value);
}
function toInt($value) {
    return (int)parseNumber($value);
}

$currentImage = $nhapkho['hinhanh'] ?? '';
$tongtienView = isset($nhapkho['tongtien']) ? (float)$nhapkho['tongtien'] : 0.0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ngayNhap = $_POST['ngay_nhap'] ?? date('Y-m-d');
    $idncc = toInt($_POST['ncc'] ?? 0);
    $ghiChu = $_POST['ghichu'] ?? '';
    $rows = is_array($_POST['rows'] ?? null) ? array_values($_POST['rows']) : [];
    $currentImage = $_POST['current_image'] ?? $currentImage;
    $hinhanh = $currentImage;
    $tongtienView = 0.0;

    if (isset($_FILES['hinhanh']) && is_array($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] == 0) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['hinhanh']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts, true)) {
            $newname = uniqid('', true) . '.' . $ext;
            $uploadDir = dirname(__DIR__, 2) . '/assets/img/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $destination = $uploadDir . $newname;
            if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $destination)) {
                $hinhanh = $newname;
            } else {
                echo "<script>alert('Khong the luu hinh anh len may chu.');</script>";
            }
        } else {
            echo "<script>alert('Chi chap nhan dinh dang jpg, jpeg, png, gif, webp.');</script>";
        }
    }

    if (!$idncc) {
        echo "<script>alert('Vui long chon nha cung cap.');</script>";
    } else {
        mysqli_begin_transaction($conn);
        $tongtien = 0.0;
        $delStmt = null;
        $ctStmt = null;
        $stmtNK = null;
        try {
            $delStmt = mysqli_prepare($conn, "DELETE FROM chitietnhapkho WHERE manhapkho = ?");
            if (!$delStmt) {
                throw new Exception('Khong the xoa chi tiet cu: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($delStmt, "i", $manhapkho);
            if (!mysqli_stmt_execute($delStmt)) {
                throw new Exception('Loi khi xoa chi tiet cu: ' . mysqli_stmt_error($delStmt));
            }
            mysqli_stmt_close($delStmt);
            $delStmt = null;

            $sqlCT = "INSERT INTO chitietnhapkho (manhapkho, matonkho, soluongdat, soluongthucte, dongia, thanhtien) VALUES (?, ?, ?, ?, ?, ?)";
            $ctStmt = mysqli_prepare($conn, $sqlCT);
            if (!$ctStmt) {
                throw new Exception('Khong the chuan bi cau lenh chi tiet: ' . mysqli_error($conn));
            }

            $paramManhapkho = $manhapkho;
            $paramMatonkho = 0;
            $paramSoluongdat = 0.0;
            $paramSoluongtt = 0.0;
            $paramDongia = 0.0;
            $paramThanhtien = 0.0;

            if (!mysqli_stmt_bind_param($ctStmt, "iidddd", $paramManhapkho, $paramMatonkho, $paramSoluongdat, $paramSoluongtt, $paramDongia, $paramThanhtien)) {
                throw new Exception('Khong the gan tham so cho chi tiet: ' . mysqli_stmt_error($ctStmt));
            }

            $insertedAny = false;
            foreach ($rows as $row) {
                $paramMatonkho = toInt($row['matonkho'] ?? 0);
                $paramSoluongdat = toFloat($row['soluongdat'] ?? 0);
                $paramSoluongtt = toFloat($row['soluongthucte'] ?? 0);
                if ($paramSoluongtt <= 0) {
                    $paramSoluongtt = $paramSoluongdat;
                }
                $paramDongia = toFloat($row['dongia'] ?? 0);

                if ($paramMatonkho <= 0) {
                    continue;
                }

                $paramThanhtien = $paramSoluongtt * $paramDongia;

                if (!mysqli_stmt_execute($ctStmt)) {
                    throw new Exception('Loi khi luu chi tiet: ' . mysqli_stmt_error($ctStmt));
                }

                mysqli_stmt_reset($ctStmt);

                $tongtien += $paramThanhtien;
                $insertedAny = true;
            }
            mysqli_stmt_close($ctStmt);
            $ctStmt = null;

            if (!$insertedAny) {
                throw new Exception('Chua co dong chi tiet hop le de luu.');
            }

            $trangthai = $nhapkho['trangthai'] ?? 'Chua xac nhan';
            $sqlNK = "UPDATE nhapkho SET idncc=?, ngaynhap=?, tongtien=?, trangthai=?, ghichu=?, hinhanh=? WHERE manhapkho=?";
            $stmtNK = mysqli_prepare($conn, $sqlNK);
            if (!$stmtNK) {
                throw new Exception('Khong the chuan bi cau lenh cap nhat nhap kho: ' . mysqli_error($conn));
            }
            if (!mysqli_stmt_bind_param($stmtNK, "isdsssi", $idncc, $ngayNhap, $tongtien, $trangthai, $ghiChu, $hinhanh, $manhapkho)) {
                throw new Exception('Khong the gan tham so cap nhat nhap kho.');
            }
            if (!mysqli_stmt_execute($stmtNK)) {
                throw new Exception('Loi khi cap nhat nhap kho: ' . mysqli_stmt_error($stmtNK));
            }
            mysqli_stmt_close($stmtNK);
            $stmtNK = null;

            mysqli_commit($conn);
            echo "<script>alert('Cap nhat don nhap kho thanh cong!'); window.location.href='index.php?page=nhapkho';</script>";
            exit;
        } catch (Exception $ex) {
            if ($delStmt) {
                mysqli_stmt_close($delStmt);
            }
            if ($ctStmt) {
                mysqli_stmt_close($ctStmt);
            }
            if ($stmtNK) {
                mysqli_stmt_close($stmtNK);
            }
            mysqli_rollback($conn);
            echo "<script>alert('" . addslashes($ex->getMessage()) . "');</script>";
        }
    }

    $detailRows = [];
    foreach ($rows as $row) {
        $matonkho = toInt($row['matonkho'] ?? 0);
        $soluongdat = toFloat($row['soluongdat'] ?? 0);
        $soluongtt = toFloat($row['soluongthucte'] ?? 0);
        if ($soluongtt <= 0) {
            $soluongtt = $soluongdat;
        }
        $dongia = toFloat($row['dongia'] ?? 0);
        $thanhtien = $soluongtt * $dongia;
        if ($matonkho > 0 || $soluongdat > 0 || $dongia > 0) {
            $detailRows[] = [
                'matonkho' => $matonkho,
                'tentonkho' => $itemNameMap[$matonkho] ?? '',
                'soluongdat' => $soluongdat,
                'soluongthucte' => $soluongtt,
                'dongia' => $dongia,
                'thanhtien' => $thanhtien,
            ];
            $tongtienView += $thanhtien;
        }
    }

    $nhapkho['ngaynhap'] = $ngayNhap;
    $nhapkho['idncc'] = $idncc;
    $nhapkho['ghichu'] = $ghiChu;
    $nhapkho['hinhanh'] = $hinhanh;
    $nhapkho['tongtien'] = $tongtienView;
    foreach ($suppliers as $supplier) {
        if ((int)$supplier['idncc'] === $idncc) {
            $nhapkho['tennhacungcap'] = $supplier['tennhacungcap'];
            break;
        }
    }
}

$tongTienFormatted = number_format($tongtienView, 0, ',', '.');
?>

<div class="container mb-5">
  <div class="card shadow m-5">
    <div class="row g-4">
      <div class="col-12 col-lg-8">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="mb-0">Don nhap kho</h3>
        </div>
        <div class="card-body">
          <form action="" method="POST" enctype="multipart/form-data" id="formNhapKho">
            <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($nhapkho['hinhanh'] ?? '', ENT_QUOTES); ?>">
            <div class="mb-3">
              <label class="form-label">Ma nhap kho</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars($nhapkho['manhapkho']); ?>" readonly>
            </div>
            <div class="mb-3">
              <label for="ma_don" class="form-label">Ten nhap kho</label>
              <input type="text" id="ma_don" class="form-control" value="<?php echo htmlspecialchars($nhapkho['tennhapkho']); ?>" readonly>
            </div>
            <div class="mb-3">
              <label for="ngay_nhap" class="form-label">Ngay nhap</label>
              <input type="date" id="ngay_nhap" name="ngay_nhap" class="form-control" value="<?php echo htmlspecialchars($nhapkho['ngaynhap']); ?>" required>
            </div>
            <div class="mb-3">
              <label for="ncc" class="form-label">Nha cung cap</label>
              <select id="ncc" name="ncc" class="form-select" required>
                <option value="">-- Chon nha cung cap --</option>
                <?php if (!empty($suppliers)) { foreach ($suppliers as $supplier) { ?>
                  <option value="<?php echo (int)$supplier['idncc']; ?>" <?php echo ((int)$supplier['idncc'] === (int)$nhapkho['idncc']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($supplier['tennhacungcap']); ?>
                  </option>
                <?php } } else { ?>
                  <option value="" disabled>(Chua co nha cung cap)</option>
                <?php } ?>
              </select>
            </div>

            <div class="table-responsive mb-2">
              <table class="table table-bordered align-middle" id="tblNhapKho">
                <thead class="table-light">
                  <tr>
                    <th style="width:60px" class="text-center">STT</th>
                    <th style="min-width:260px">Ten nguyen lieu</th>
                    <th style="width:140px" class="text-end">So luong dat</th>
                    <th style="width:160px" class="text-end">So luong thuc te</th>
                    <th style="width:160px" class="text-end">Don gia (VND)</th>
                    <th style="width:180px" class="text-end">Thanh tien (VND)</th>
                    <th style="width:60px"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
            <div class="d-flex justify-content-end me-1 mb-3">
              <div>
                <div class="text-muted small text-end">Tong tien (VND)</div>
                <div class="fs-5 fw-bold text-end" id="tongTienText"><?php echo $tongTienFormatted; ?></div>
              </div>
            </div>
            <div class="mb-3">
              <button type="button" id="btnAddRow" class="btn btn-outline-primary">
                <i class="fas fa-plus"></i> Them dong
              </button>
            </div>
            <div class="mb-3">
              <label for="ghichu" class="form-label">Ghi chu</label>
              <textarea id="ghichu" name="ghichu" class="form-control" rows="3"><?php echo htmlspecialchars($nhapkho['ghichu'] ?? ''); ?></textarea>
            </div>
            <div class="d-flex justify-content-end gap-2">
              <a href="index.php?page=nhapkho" class="btn btn-light"><i class="fas fa-times"></i> Huy</a>
              <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Luu</button>
            </div>
          </form>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <h3 class="card-header">Chung tu</h3>
        <div class="card-body">
          <?php if (!empty($nhapkho['hinhanh'])) { ?>
            <img src="assets/img/<?php echo htmlspecialchars($nhapkho['hinhanh']); ?>" alt="Chung tu" class="img-fluid mb-3">
          <?php } else { ?>
            <div class="text-muted mb-3">Chua co hinh anh chung tu.</div>
          <?php } ?>
          <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*" form="formNhapKho">
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const tbody = document.querySelector('#tblNhapKho tbody');
  const btnAddRow = document.getElementById('btnAddRow');
  const totalText = document.getElementById('tongTienText');
  const ITEMS = <?php echo json_encode($items, JSON_UNESCAPED_UNICODE); ?>;
  const DETAILS = <?php echo json_encode($detailRows, JSON_UNESCAPED_UNICODE); ?>;

  function updateSTT() {
    tbody.querySelectorAll('tr').forEach((tr, index) => {
      const sttCell = tr.querySelector('.col-stt');
      if (sttCell) {
        sttCell.textContent = String(index + 1);
      }
    });
  }

  function formatCurrency(value) {
    const number = Number(value) || 0;
    return new Intl.NumberFormat('vi-VN').format(number);
  }

  function recomputeTotal() {
    let sum = 0;
    tbody.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.input-realqty')?.value || '0');
      const price = parseFloat(tr.querySelector('.input-price')?.value || '0');
      if (Number.isFinite(qty) && Number.isFinite(price)) {
        sum += qty * price;
      }
    });
    totalText.textContent = formatCurrency(sum);
  }

  function recalcRow(tr) {
    const qtyInput = tr.querySelector('.input-realqty');
    const priceInput = tr.querySelector('.input-price');
    const totalCell = tr.querySelector('.cell-total');
    const qty = parseFloat(qtyInput?.value || '0');
    const price = parseFloat(priceInput?.value || '0');
    const total = (Number.isFinite(qty) ? qty : 0) * (Number.isFinite(price) ? price : 0);
    if (totalCell) {
      totalCell.textContent = formatCurrency(total);
    }
    recomputeTotal();
  }

  let rowIndex = 0;

  function buildOptions(selectedValue) {
    return ['<option value="">-- Chon nguyen lieu --</option>'].concat(
      ITEMS.map(item => {
        const value = String(item.matonkho);
        const selected = value === String(selectedValue) ? ' selected' : '';
        return `<option value="${value}"${selected}>${item.tentonkho}</option>`;
      })
    ).join('');
  }

  function addRow(defaults = {}) {
    const index = rowIndex++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td class="col-stt text-center"></td>
      <td>
        <select name="rows[${index}][matonkho]" class="form-select" required>
          ${buildOptions(defaults.matonkho ?? '')}
        </select>
      </td>
      <td>
        <input type="number" min="0" step="0.01" name="rows[${index}][soluongdat]" class="form-control text-end input-qty" value="${defaults.soluongdat ?? ''}" required>
      </td>
      <td>
        <input type="number" min="0" step="0.01" name="rows[${index}][soluongthucte]" class="form-control text-end input-realqty" value="${defaults.soluongthucte ?? ''}" required>
      </td>
      <td>
        <input type="number" min="0" step="1000" name="rows[${index}][dongia]" class="form-control text-end input-price" value="${defaults.dongia ?? ''}" required>
      </td>
      <td class="text-end cell-total">${formatCurrency(defaults.thanhtien ?? 0)}</td>
      <td class="text-center">
        <button type="button" class="btn btn-outline-danger btn-sm btn-remove" title="Xoa dong">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    `;

    tr.querySelectorAll('.input-realqty, .input-price').forEach(input => {
      input.addEventListener('input', () => recalcRow(tr));
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

  btnAddRow.addEventListener('click', () => addRow());

  if (Array.isArray(DETAILS) && DETAILS.length > 0) {
    DETAILS.forEach(detail => addRow(detail));
  } else {
    addRow();
  }
</script>

<?php mysqli_close($conn); ?>
