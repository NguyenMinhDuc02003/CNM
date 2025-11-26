<?php
include_once 'includes/config_permission.php';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}
// Kiểm tra quyền truy cập
if (!hasPermission('sua lich lam viec', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}
// Trang: Sửa lịch làm việc nhân viên
$message = '';
$messageType = '';

$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

$today = new DateTime();
$today->setTime(0, 0, 0);
$currentMonday = clone $today;
$currentMonday->modify('Monday this week');

$viewMonday = clone $currentMonday;
if ($weekOffset !== 0) {
    $viewMonday->modify(($weekOffset > 0 ? '+' : '') . $weekOffset . ' week');
}

$selectedDateValue = null;
$targetDateParam = null;
if (isset($_GET['date'])) {
    $targetDateParam = $_GET['date'];
} elseif (isset($_GET['ngay']) && !isset($_GET['week'])) {
    $targetDateParam = $_GET['ngay'];
}

if ($targetDateParam) {
    $targetDate = DateTime::createFromFormat('Y-m-d', $targetDateParam);
    if ($targetDate) {
        $targetDate->setTime(0, 0, 0);
        $targetMonday = clone $targetDate;
        $targetMonday->modify('Monday this week');
        $viewMonday = $targetMonday;
        $selectedDateValue = $targetDate->format('Y-m-d');
        $secondsDiff = $targetMonday->getTimestamp() - $currentMonday->getTimestamp();
        $weekOffset = (int) floor($secondsDiff / 604800);
    }
}

$viewSunday = clone $viewMonday;
$viewSunday->modify('+6 days');

$startDate = $viewMonday->format('Y-m-d');
$endDate = $viewSunday->format('Y-m-d');
$weekRangeText = $viewMonday->format('d/m/Y') . ' - ' . $viewSunday->format('d/m/Y');
$isEditableWeek = $weekOffset >= 0;

$datesOfWeek = [];
for ($i = 0; $i < 7; $i++) {
    $date = clone $viewMonday;
    $date->modify('+' . $i . ' days');
    $datesOfWeek[$i + 1] = $date;
}
$selectedDateValue = $selectedDateValue ?? $viewMonday->format('Y-m-d');

$prefillDate = $startDate;
if ($selectedDateValue >= $startDate && $selectedDateValue <= $endDate) {
    $prefillDate = $selectedDateValue;
}
if (isset($_GET['ngay'])) {
    $candidate = DateTime::createFromFormat('Y-m-d', $_GET['ngay']);
    if ($candidate) {
        $candidateStr = $candidate->format('Y-m-d');
        if ($candidateStr >= $startDate && $candidateStr <= $endDate) {
            $prefillDate = $candidateStr;
        }
    }
}
$prefillShift = isset($_GET['ca']) ? (int)$_GET['ca'] : 0;
$selectedEmployee = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        if (!$isEditableWeek) {
            $message = 'Tuần đã chọn chỉ cho phép xem, không thể chỉnh sửa.';
            $messageType = 'warning';
        } else {
            $idnv = isset($_POST['idnv']) ? (int)$_POST['idnv'] : 0;
            $idca = isset($_POST['idca']) ? (int)$_POST['idca'] : 0;
            $ngay = $_POST['ngay'] ?? '';

            $prefillDate = $ngay;
            $prefillShift = $idca;
            $selectedEmployee = $idnv;

            if ($idnv <= 0 || $idca <= 0 || empty($ngay)) {
                $message = 'Vui lòng chọn đầy đủ thông tin trước khi thêm.';
                $messageType = 'danger';
            } else {
                $dateObj = DateTime::createFromFormat('Y-m-d', $ngay);
                if (!$dateObj) {
                    $message = 'Ngày không hợp lệ.';
                    $messageType = 'danger';
                } else {
                    $dateStr = $dateObj->format('Y-m-d');
                    if ($dateStr < $startDate || $dateStr > $endDate) {
                        $message = 'Ngày được chọn nằm ngoài tuần đang xem.';
                        $messageType = 'warning';
                    } else {
                        $checkStmt = $conn->prepare('SELECT 1 FROM lichlamviec WHERE idnv = ? AND idca = ? AND ngay = ? LIMIT 1');
                        if ($checkStmt) {
                            $checkStmt->bind_param('iis', $idnv, $idca, $dateStr);
                            $checkStmt->execute();
                            $checkStmt->store_result();
                            if ($checkStmt->num_rows > 0) {
                                $message = 'Nhân viên đã có lịch trong ca này.';
                                $messageType = 'warning';
                            } else {
                                $insertStmt = $conn->prepare('INSERT INTO lichlamviec (idnv, idca, ngay) VALUES (?, ?, ?)');
                                if ($insertStmt) {
                                    $insertStmt->bind_param('iis', $idnv, $idca, $dateStr);
                                    if ($insertStmt->execute()) {
                                        $message = 'Thêm lịch làm việc thành công.';
                                        $messageType = 'success';
                                    } else {
                                        $message = 'Lỗi khi thêm lịch làm việc: ' . $conn->error;
                                        $messageType = 'danger';
                                    }
                                    $insertStmt->close();
                                } else {
                                    $message = 'Không thể chuẩn bị câu lệnh thêm lịch làm việc.';
                                    $messageType = 'danger';
                                }
                            }
                            $checkStmt->close();
                        } else {
                            $message = 'Không thể kiểm tra trùng lịch.';
                            $messageType = 'danger';
                        }
                    }
                }
            }
        }
    } elseif ($action === 'delete') {
        if (!$isEditableWeek) {
            $message = 'Tuần đã chọn chỉ cho phép xem, không thể chỉnh sửa.';
            $messageType = 'warning';
        } else {
            $maLLV = isset($_POST['maLLV']) ? (int)$_POST['maLLV'] : 0;
            if ($maLLV <= 0) {
                $message = 'Không tìm thấy lịch làm việc cần xóa.';
                $messageType = 'danger';
            } else {
                $dateCheck = null;
                $checkStmt = $conn->prepare('SELECT ngay FROM lichlamviec WHERE maLLV = ?');
                if ($checkStmt) {
                    $checkStmt->bind_param('i', $maLLV);
                    $checkStmt->execute();
                    $checkStmt->bind_result($foundDate);
                    if ($checkStmt->fetch()) {
                        $dateCheck = $foundDate;
                    }
                    $checkStmt->close();

                    if (!$dateCheck) {
                        $message = 'Không tìm thấy lịch làm việc cần xóa.';
                        $messageType = 'warning';
                    } elseif ($dateCheck < $startDate || $dateCheck > $endDate) {
                        $message = 'Bản ghi không thuộc tuần đang xem.';
                        $messageType = 'warning';
                    } else {
                        $deleteStmt = $conn->prepare('DELETE FROM lichlamviec WHERE maLLV = ?');
                        if ($deleteStmt) {
                            $deleteStmt->bind_param('i', $maLLV);
                            if ($deleteStmt->execute()) {
                                $message = 'Xóa lịch làm việc thành công.';
                                $messageType = 'success';
                            } else {
                                $message = 'Lỗi khi xóa lịch làm việc: ' . $conn->error;
                                $messageType = 'danger';
                            }
                            $deleteStmt->close();
                        } else {
                            $message = 'Không thể chuẩn bị câu lệnh xóa.';
                            $messageType = 'danger';
                        }
                    }
                } else {
                    $message = 'Không thể kiểm tra lịch làm việc cần xóa.';
                    $messageType = 'danger';
                }
            }
        }
    }
    elseif ($action === 'copy') {
        // Copy current week's schedule to next week (+7 days). Only allowed for current week (weekOffset === 0).
        if ($weekOffset !== 0) {
            $message = 'Chức năng sao chép chỉ khả dụng khi đang xem tuần hiện tại.';
            $messageType = 'warning';
        } else {
            // Fetch all schedule rows in the source week
            $copyStmt = $conn->prepare('SELECT idnv, idca, ngay FROM lichlamviec WHERE ngay BETWEEN ? AND ? ORDER BY ngay, idca, idnv');
            if ($copyStmt) {
                $copyStmt->bind_param('ss', $startDate, $endDate);
                if ($copyStmt->execute()) {
                    $res = $copyStmt->get_result();
                    $toInsert = [];
                    while ($r = $res->fetch_assoc()) {
                        $srcDate = DateTime::createFromFormat('Y-m-d', $r['ngay']);
                        if (!$srcDate) continue;
                        $srcDate->modify('+7 days');
                        $newDate = $srcDate->format('Y-m-d');
                        $toInsert[] = [ (int)$r['idnv'], (int)$r['idca'], $newDate ];
                    }
                    $copyStmt->close();

                    if (empty($toInsert)) {
                        $message = 'Không có lịch nào để sao chép.';
                        $messageType = 'info';
                    } else {
                        $conn->begin_transaction();
                        try {
                            $checkStmt = $conn->prepare('SELECT 1 FROM lichlamviec WHERE idnv = ? AND idca = ? AND ngay = ? LIMIT 1');
                            $insertStmt = $conn->prepare('INSERT INTO lichlamviec (idnv, idca, ngay) VALUES (?, ?, ?)');
                            $inserted = 0;
                            $skipped = 0;
                            foreach ($toInsert as $row) {
                                [$idnv, $idca, $ndate] = $row;
                                $exists = false;
                                if ($checkStmt) {
                                    $checkStmt->bind_param('iis', $idnv, $idca, $ndate);
                                    $checkStmt->execute();
                                    $checkStmt->store_result();
                                    if ($checkStmt->num_rows > 0) $exists = true;
                                }
                                if ($exists) { $skipped++; continue; }
                                if ($insertStmt) {
                                    $insertStmt->bind_param('iis', $idnv, $idca, $ndate);
                                    if ($insertStmt->execute()) {
                                        $inserted++;
                                    }
                                }
                            }
                            if ($checkStmt) $checkStmt->close();
                            if ($insertStmt) $insertStmt->close();
                            $conn->commit();
                            $message = 'Sao chép xong: ' . $inserted . ' bản ghi được thêm';
                            if ($skipped > 0) $message .= ', ' . $skipped . ' bản ghi bị bỏ qua (đã tồn tại).';
                            $messageType = 'success';
                        } catch (Throwable $th) {
                            $conn->rollback();
                            $message = 'Lỗi khi sao chép lịch: ' . $th->getMessage();
                            $messageType = 'danger';
                        }
                    }
                } else {
                    $message = 'Lỗi khi truy vấn lịch cần sao chép.';
                    $messageType = 'danger';
                    $copyStmt->close();
                }
            } else {
                $message = 'Không thể chuẩn bị câu lệnh sao chép.';
                $messageType = 'danger';
            }
        }
    }
}

$employees = [];
$employeeResult = $conn->query('SELECT idnv, HoTen, ChucVu FROM nhanvien ORDER BY HoTen');
if ($employeeResult && $employeeResult->num_rows > 0) {
    while ($row = $employeeResult->fetch_assoc()) {
        $employees[] = $row;
    }
}

$shifts = [];
$shiftResult = $conn->query('SELECT idca, tenca, giobatdau, gioketthuc FROM calamviec ORDER BY giobatdau, idca');
if ($shiftResult && $shiftResult->num_rows > 0) {
    while ($row = $shiftResult->fetch_assoc()) {
        $shifts[(int)$row['idca']] = $row;
    }
}

$scheduleData = [];
$scheduleStmt = $conn->prepare('SELECT llv.maLLV, llv.ngay, llv.idca, nv.HoTen, nv.ChucVu, clv.giobatdau
                                FROM lichlamviec llv
                                JOIN calamviec clv ON llv.idca = clv.idca
                                JOIN nhanvien nv ON llv.idnv = nv.idnv
                                WHERE llv.ngay BETWEEN ? AND ?
                                ORDER BY llv.ngay, clv.giobatdau, nv.HoTen');
if ($scheduleStmt) {
    $scheduleStmt->bind_param('ss', $startDate, $endDate);
    if ($scheduleStmt->execute()) {
        $result = $scheduleStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rowDate = new DateTime($row['ngay']);
            $dayOfWeek = (int)$rowDate->format('N');
            $caId = (int)$row['idca'];

            if (!isset($scheduleData[$dayOfWeek])) {
                $scheduleData[$dayOfWeek] = [];
            }
            if (!isset($scheduleData[$dayOfWeek][$caId])) {
                $scheduleData[$dayOfWeek][$caId] = [];
            }

            $scheduleData[$dayOfWeek][$caId][] = [
                'id' => (int)$row['maLLV'],
                'name' => $row['HoTen'],
                'role' => $row['ChucVu'],
                'date' => $rowDate->format('d/m/Y')
            ];
        }
    }
    $scheduleStmt->close();
}

$dayLabels = [
    1 => 'Thứ 2',
    2 => 'Thứ 3',
    3 => 'Thứ 4',
    4 => 'Thứ 5',
    5 => 'Thứ 6',
    6 => 'Thứ 7',
    7 => 'Chủ nhật'
];
?>

<div class="container">
    <div class="m-3">
        <div class="d-flex align-items-center justify-content-between">
            <h3 class="mt-5">Quản lý lịch làm việc</h3>
            <a class="btn btn-outline-primary" href="index.php?page=lichlamviec">Quay lại</a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show mt-3" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Thêm lịch làm việc</h5>
                <?php if (!$isEditableWeek): ?>
                    <span class="badge bg-secondary text-uppercase">Chỉ xem</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if ($isEditableWeek): ?>
                    <form method="POST" action="index.php?page=sualichlamviec&week=<?php echo $weekOffset; ?>">
                        <input type="hidden" name="action" value="add">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Nhân viên</label>
                                <select class="form-select" name="idnv" required>
                                    <option value="">Chọn nhân viên</option>
                                    <?php foreach ($employees as $employee): ?>
                                        <option value="<?php echo (int)$employee['idnv']; ?>" <?php echo ($selectedEmployee === (int)$employee['idnv']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($employee['HoTen']); ?> - <?php echo htmlspecialchars($employee['ChucVu']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ca làm việc</label>
                                <select class="form-select" name="idca" required>
                                    <option value="">Chọn ca</option>
                                    <?php foreach ($shifts as $shiftId => $shift): ?>
                                        <option value="<?php echo $shiftId; ?>" <?php echo ($prefillShift === $shiftId) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($shift['tenca']); ?> (<?php echo htmlspecialchars($shift['giobatdau']); ?> - <?php echo htmlspecialchars($shift['gioketthuc']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Ngày</label>
                                <input type="date"
                                       class="form-control"
                                       name="ngay"
                                       value="<?php echo htmlspecialchars($prefillDate); ?>"
                                       min="<?php echo $startDate; ?>"
                                       max="<?php echo $endDate; ?>"
                                       required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Thêm</button>
                            </div>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="text-muted mb-0">Tuần trước chỉ cho phép xem. Chọn "Tuần sau" để quay lại tuần hiện tại hoặc tuần tương lai để phân công.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="mb-0">Lịch làm việc theo tuần</h5>
                <span class="ms-auto small text-muted">Tuần hiển thị: <span id="weekRangeLabel"><?php echo htmlspecialchars($weekRangeText); ?></span></span>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                    <button class="btn btn-outline-secondary btn-sm" onclick="changeWeek(-1)">Tuần trước</button>
                    <div class="input-group input-group-sm week-range-group">
                        <span class="input-group-text bg-light fw-semibold" id="weekRange">
                            <?php echo htmlspecialchars($weekRangeText); ?>
                        </span>
                        <input type="date"
                               class="form-control"
                               id="weekPicker"
                               value="<?php echo htmlspecialchars($selectedDateValue); ?>"
                               title="Chọn ngày để xem lịch theo tuần">
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="changeWeek(1)">Tuần sau</button>
                    <?php if (!$isEditableWeek): ?>
                        <span class="badge bg-secondary ms-auto">Chế độ xem</span>
                    <?php else: ?>
                        <span class="badge bg-success ms-auto">Cho phép chỉnh sửa</span>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="scheduleTable">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="min-width: 200px;">Ngày</th>
                                <?php if (!empty($shifts)): ?>
                                    <?php foreach ($shifts as $shift): ?>
                                        <th>
                                            <?php echo htmlspecialchars($shift['tenca']); ?><br>
                                            <span class="small text-muted"><?php echo htmlspecialchars($shift['giobatdau']); ?> - <?php echo htmlspecialchars($shift['gioketthuc']); ?></span>
                                        </th>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <th class="text-muted">Chưa cấu hình ca làm việc</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dayLabels as $dayIndex => $dayLabel): ?>
                                <tr>
                                    <td style="width: 220px;">
                                        <div class="fw-semibold"><?php echo htmlspecialchars($dayLabel); ?></div>
                                        <div class="small text-muted" id="row-date-d<?php echo $dayIndex; ?>">
                                            <?php echo $datesOfWeek[$dayIndex]->format('d/m/Y'); ?>
                                        </div>
                                    </td>
                                    <?php if (!empty($shifts)): ?>
                                        <?php foreach ($shifts as $shiftId => $shift): ?>
                                            <td class="p-2 schedule-cell">
                                                <?php if (!empty($scheduleData[$dayIndex][$shiftId])): ?>
                                                    <?php foreach ($scheduleData[$dayIndex][$shiftId] as $item): ?>
                                                        <div class="schedule-item mb-1">
                                                            <div>
                                                                <span class="schedule-name"><?php echo htmlspecialchars($item['name']); ?></span>
                                                                <small class="text-muted">(<?php echo htmlspecialchars($item['role']); ?>)</small>
                                                            </div>
                                                            <?php if ($isEditableWeek): ?>
                                                                <form method="POST" action="index.php?page=sualichlamviec&week=<?php echo $weekOffset; ?>" class="d-inline">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="maLLV" value="<?php echo (int)$item['id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Bạn có chắc muốn xóa lịch này?');" title="Xóa">
                                                                        <i class="fas fa-times"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="text-muted small">--</div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <td class="text-center text-muted" colspan="1">Không có dữ liệu ca làm việc.</td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let weekOffset = <?php echo $weekOffset; ?>;
    const baseMonday = new Date('<?php echo $currentMonday->format('Y-m-d'); ?>T00:00:00');

    function formatDate(d) {
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yyyy = d.getFullYear();
        return `${dd}/${mm}/${yyyy}`;
    }

    function renderWeekRange() {
        const targetMonday = new Date(baseMonday);
        targetMonday.setDate(baseMonday.getDate() + (weekOffset * 7));
        const targetSunday = new Date(targetMonday);
        targetSunday.setDate(targetMonday.getDate() + 6);
        const rangeText = `${formatDate(targetMonday)} - ${formatDate(targetSunday)}`;

        const rangeEl = document.getElementById('weekRange');
        if (rangeEl) {
            rangeEl.textContent = rangeText;
        }

        const labelEl = document.getElementById('weekRangeLabel');
        if (labelEl) {
            labelEl.textContent = rangeText;
        }

        for (let i = 0; i < 7; i++) {
            const day = new Date(targetMonday);
            day.setDate(targetMonday.getDate() + i);
            const span = document.getElementById(`row-date-d${i + 1}`);
            if (span) {
                span.textContent = formatDate(day);
            }
        }
    }

    function changeWeek(delta) {
        const newOffset = weekOffset + delta;
        window.location.href = `index.php?page=sualichlamviec&week=${newOffset}`;
    }

    function initWeekPicker() {
        const picker = document.getElementById('weekPicker');
        if (!picker) {
            return;
        }
        picker.addEventListener('change', function () {
            if (this.value) {
                window.location.href = `index.php?page=sualichlamviec&date=${this.value}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        renderWeekRange();
        initWeekPicker();
    });
</script>

<style>
    .table td,
    .table th {
        vertical-align: middle;
    }

    .schedule-cell {
        min-height: 70px;
        background-color: #fff;
    }

    .schedule-item {
        background-color: #f1f5fb;
        border: 1px solid #d9e3f0;
        border-radius: 6px;
        padding: 6px 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    .schedule-item .btn {
        padding: 2px 6px;
    }

    .schedule-name {
        font-weight: 500;
    }

    .week-range-group {
        min-width: 260px;
        max-width: 320px;
    }

    .week-range-group .input-group-text {
        min-width: 160px;
        justify-content: center;
    }

    .week-range-group .form-control {
        min-width: 140px;
    }

    @media (max-width: 992px) {
        thead th {
            font-size: 12px;
        }

        tbody td {
            font-size: 12px;
        }

        .schedule-item {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>
