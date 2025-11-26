<?php
require_once __DIR__ . '/../../class/clsconnect.php';
if (!isset($conn) || !($conn instanceof mysqli)) {
    $admin_db = new connect_db();
    $conn = $admin_db->getConnection();
}



$messages = [];
$errors = [];
$selectedEmployee = '';
$selectedAction = 'checkin';
$selectedDate = date('Y-m-d');
$selectedTime = date('H:i');
$inputReason = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employeeId = isset($_POST['idnv']) ? (int) $_POST['idnv'] : 0;
    $selectedEmployee = (string) $employeeId;
    $selectedAction = $_POST['action'] === 'checkout' ? 'checkout' : 'checkin';
    $selectedDate = $_POST['ngay'] ?: date('Y-m-d');
    $selectedTime = $_POST['thoigian'] ?: date('H:i');
    $inputReason = trim($_POST['reason'] ?? '');

    if ($employeeId <= 0) {
        $errors[] = 'Vui lòng chọn nhân viên.';
    }
    if ($inputReason === '') {
        $errors[] = 'Vui lòng nhập lý do nhân viên chấm công thủ công.';
    }

    $timestamp = DateTime::createFromFormat('Y-m-d H:i', $selectedDate . ' ' . $selectedTime);
    if (!$timestamp) {
        $errors[] = 'Ngày hoặc giờ không hợp lệ.';
    }

    if (!$errors) {
        $employeeStmt = $conn->prepare('SELECT HoTen FROM nhanvien WHERE idnv = ? LIMIT 1');
        $employeeStmt->bind_param('i', $employeeId);
        $employeeStmt->execute();
        $employeeStmt->bind_result($employeeName);
        if (!$employeeStmt->fetch()) {
            $errors[] = 'Không tìm thấy nhân viên.';
        }
        $employeeStmt->close();
    }

    if (!$errors && isset($timestamp)) {
        $timestampStr = $timestamp->format('Y-m-d H:i:s');
        $workingDate = $timestamp->format('Y-m-d');

        $stmt = $conn->prepare('SELECT id, checkin_at, checkout_at FROM chamcong WHERE idnv = ? AND ngay = ? LIMIT 1');
        $stmt->bind_param('is', $employeeId, $workingDate);
        $stmt->execute();
        $stmt->bind_result($attendanceId, $checkinAt, $checkoutAt);
        $recordExists = $stmt->fetch();
        $stmt->close();

        if ($recordExists) {
            if ($selectedAction === 'checkin') {
                $update = $conn->prepare('UPDATE chamcong SET checkin_at = ?, last_score = 1 WHERE id = ?');
            } else {
                $update = $conn->prepare('UPDATE chamcong SET checkout_at = ?, last_score = 1 WHERE id = ?');
            }
            $update->bind_param('si', $timestampStr, $attendanceId);
            $update->execute();
            $update->close();
        } else {
            $checkinValue = $selectedAction === 'checkin' ? $timestampStr : null;
            $checkoutValue = $selectedAction === 'checkout' ? $timestampStr : null;
            $insert = $conn->prepare('INSERT INTO chamcong (idnv, ngay, checkin_at, checkout_at, last_score) VALUES (?, ?, ?, ?, 1)');
            $insert->bind_param('isss', $employeeId, $workingDate, $checkinValue, $checkoutValue);
            $insert->execute();
            $insert->close();
        }

        $note = sprintf('[%s] %s', strtoupper($selectedAction), $inputReason);
        $log = $conn->prepare('INSERT INTO attendance_log (idnv, matched, score, source, snapshot_path, note) VALUES (?, 1, 1, "manual", "", ?)');
        $log->bind_param('is', $employeeId, $note);
        $log->execute();
        $log->close();

        $messages[] = sprintf(
            '%s đã được ghi nhận %s thủ công vào %s.',
            htmlspecialchars($employeeName ?? ('NV' . $employeeId)),
            $selectedAction === 'checkout' ? 'checkout' : 'checkin',
            $timestampStr
        );

        $inputReason = '';
    }
}

$employees = [];
$employeeResult = $conn->query('SELECT idnv, HoTen FROM nhanvien ORDER BY HoTen ASC');
if ($employeeResult) {
    while ($row = $employeeResult->fetch_assoc()) {
        $employees[] = $row;
    }
    $employeeResult->close();
}

$todayAttendance = [];
$today = date('Y-m-d');
$stmtToday = $conn->prepare(
    'SELECT c.ngay, c.checkin_at, c.checkout_at, n.HoTen 
     FROM chamcong c 
     JOIN nhanvien n ON c.idnv = n.idnv 
     WHERE c.ngay = ? 
     ORDER BY n.HoTen ASC'
);
$stmtToday->bind_param('s', $today);
$stmtToday->execute();
$resultToday = $stmtToday->get_result();
if ($resultToday) {
    $todayAttendance = $resultToday->fetch_all(MYSQLI_ASSOC);
    $resultToday->close();
}
$stmtToday->close();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Chấm công thủ công</h2>
        <a href="index.php?page=erp" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Quay lại ERP
        </a>
    </div>

    <?php if ($messages): ?>
        <div class="alert alert-success">
            <?php foreach ($messages as $msg): ?>
                <div><?php echo $msg; ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?>
                <div><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="idnv" class="form-label">Nhân viên</label>
                        <select class="form-select" id="idnv" name="idnv" required>
                            <option value="" disabled <?php echo $selectedEmployee === '' ? 'selected' : ''; ?>>Chọn nhân viên</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo (int) $emp['idnv']; ?>" <?php echo ((string) $emp['idnv'] === $selectedEmployee) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($emp['HoTen'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $emp['idnv']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="ngay" class="form-label">Ngày</label>
                        <input type="date" class="form-control" id="ngay" name="ngay" value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="thoigian" class="form-label">Giờ</label>
                        <input type="time" class="form-control" id="thoigian" name="thoigian" value="<?php echo htmlspecialchars($selectedTime, ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="col-md-2">
                        <label for="action" class="form-label">Thao tác</label>
                        <select class="form-select" id="action" name="action">
                            <option value="checkin" <?php echo $selectedAction === 'checkin' ? 'selected' : ''; ?>>Checkin</option>
                            <option value="checkout" <?php echo $selectedAction === 'checkout' ? 'selected' : ''; ?>>Checkout</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="reason" class="form-label">Lý do (bắt buộc)</label>
                    <textarea class="form-control" id="reason" name="reason" rows="2" placeholder="Nhân viên trình bày lý do..." required><?php echo htmlspecialchars($inputReason, ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Ghi nhận
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Chấm công hôm nay (<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>)</strong>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Checkin</th>
                            <th>Checkout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($todayAttendance)): ?>
                            <tr>
                                <td colspan="3" class="text-center">Chưa có dữ liệu hôm nay.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($todayAttendance as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['HoTen'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo $row['checkin_at'] ? htmlspecialchars($row['checkin_at'], ENT_QUOTES, 'UTF-8') : '<em>--</em>'; ?></td>
                                    <td><?php echo $row['checkout_at'] ? htmlspecialchars($row['checkout_at'], ENT_QUOTES, 'UTF-8') : '<em>--</em>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
