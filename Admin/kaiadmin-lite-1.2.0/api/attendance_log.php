<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/../class/clsconnect.php';

const LATE_GRACE_MINUTES = 15;
$attendanceSchedule = loadAttendanceSchedule();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$body = file_get_contents('php://input');
$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

$employeeCode = trim($payload['employee_id'] ?? '');
$score = isset($payload['score']) ? (float) $payload['score'] : 0.0;
$matched = array_key_exists('matched', $payload) ? (bool) $payload['matched'] : true;
$imageBase64 = $payload['image_base64'] ?? null;
$source = $payload['source'] ?? 'webcam';

if ($employeeCode === '' || !preg_match('/^NV\d+$/i', $employeeCode)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mã nhân viên không hợp lệ']);
    exit;
}

$employeeId = (int) preg_replace('/\D/', '', $employeeCode);
if ($employeeId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Không xác định được nhân viên']);
    exit;
}

$db = new connect_db();
$conn = $db->getConnection();
ensureChamCongStatusColumns($conn);

$stmtCheck = $conn->prepare('SELECT COUNT(*) FROM nhanvien WHERE idnv = ?');
$stmtCheck->bind_param('i', $employeeId);
$stmtCheck->execute();
$stmtCheck->bind_result($exists);
$stmtCheck->fetch();
$stmtCheck->close();

if ((int) $exists === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Nhân viên không tồn tại']);
    exit;
}

$snapshotPath = null;
$snapshotAbsolutePath = null;
if ($imageBase64 && strpos($imageBase64, 'data:image') === 0) {
    $parts = explode(',', $imageBase64, 2);
    if (count($parts) === 2) {
        $binary = base64_decode($parts[1]);
        if ($binary !== false) {
            $employeeFolder = strtoupper($employeeCode);
            $dir = __DIR__ . '/../uploads/chamcong/' . $employeeFolder;
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $filename = sprintf('%s_%s.jpg', $employeeFolder, date('Ymd_His'));
            $fullPath = $dir . '/' . $filename;
            if (file_put_contents($fullPath, $binary) !== false) {
                $relativePath = 'uploads/chamcong/' . $employeeFolder . '/' . $filename;
                $snapshotPath = $relativePath;
                $snapshotAbsolutePath = $fullPath;
                queueSnapshotCleanup($snapshotAbsolutePath);
            }
        }
    }
}

$stmtLog = $conn->prepare('INSERT INTO attendance_log (idnv, matched, score, source, snapshot_path) VALUES (?, ?, ?, ?, ?)');
$matchedInt = $matched ? 1 : 0;
$safeSnapshot = $snapshotPath ?? '';
$stmtLog->bind_param('iidss', $employeeId, $matchedInt, $score, $source, $safeSnapshot);

if (!$stmtLog->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Không lưu được log chấm công']);
    exit;
}
$logId = $conn->insert_id;
$stmtLog->close();

$attendanceState = computeAttendanceState($attendanceSchedule);
if (!$attendanceState['allowed']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => $attendanceState['message'] ?? 'Ngoài khung giờ chấm công',
    ]);
    exit;
}

$action = 'none';
$today = date('Y-m-d');

$openAttendance = fetchOpenAttendance($conn, $employeeId, $today);

if ($attendanceState['mode'] === 'checkin') {
    if ($openAttendance) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Đã checkin, chưa checkout ca trước đó.',
            'attendance_state' => $attendanceState,
        ]);
        exit;
    }
    $checkinStatus = $attendanceState['status'] === 'late' ? 'late' : 'on_time';
    $shiftId = resolveShiftIdForNow($conn);
    $stmtInsert = $conn->prepare('INSERT INTO chamcong (idnv, idca, ngay, checkin_at, checkin_status, last_score) VALUES (?, ?, ?, NOW(), ?, ?)');
    $stmtInsert->bind_param('iissd', $employeeId, $shiftId, $today, $checkinStatus, $score);
    $stmtInsert->execute();
    $stmtInsert->close();
    $action = 'checkin';
    $attendanceState['checkin_status'] = $checkinStatus;
} elseif ($attendanceState['mode'] === 'checkout') {
    if (!$openAttendance) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Chưa checkin ca hôm nay.',
            'attendance_state' => $attendanceState,
        ]);
        exit;
    }
    $shiftId = resolveShiftIdForNow($conn);
    $stmtUpdate = $conn->prepare('UPDATE chamcong SET checkout_at = NOW(), last_score = ?, idca = COALESCE(idca, ?) WHERE id = ?');
    $stmtUpdate->bind_param('dii', $score, $shiftId, $openAttendance['id']);
    $stmtUpdate->execute();
    $stmtUpdate->close();
    $action = 'checkout';
    $attendanceState['checkin_status'] = $openAttendance['checkin_status'] ?? 'on_time';
} else {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Ngoài khung giờ chấm công',
        'attendance_state' => $attendanceState,
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'log_id' => $logId,
    'action' => $action,
    'snapshot_path' => $snapshotPath,
    'attendance_state' => $attendanceState,
]);

if ($snapshotAbsolutePath && is_file($snapshotAbsolutePath)) {
    @unlink($snapshotAbsolutePath);
    $snapshotPath = null;
}

function queueSnapshotCleanup(?string $absolutePath): void
{
    if (!$absolutePath) {
        return;
    }
    register_shutdown_function(function () use ($absolutePath) {
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    });
}

function ensureChamCongStatusColumns(mysqli $conn): void
{
    $column = $conn->query("SHOW COLUMNS FROM chamcong LIKE 'checkin_status'");
    if ($column && $column->num_rows === 0) {
        $conn->query("ALTER TABLE chamcong ADD COLUMN checkin_status ENUM('on_time','late') DEFAULT 'on_time' AFTER checkin_at");
    }
    if ($column instanceof mysqli_result) {
        $column->free();
    }

    $colIdca = $conn->query("SHOW COLUMNS FROM chamcong LIKE 'idca'");
    if ($colIdca && $colIdca->num_rows === 0) {
        $conn->query("ALTER TABLE chamcong ADD COLUMN idca INT NULL AFTER idnv");
    }
    if ($colIdca instanceof mysqli_result) {
        $colIdca->free();
    }

    $idx = $conn->query("SHOW INDEX FROM chamcong WHERE Key_name = 'idx_chamcong_nv_ngay'");
    if ($idx && $idx->num_rows === 0) {
        $conn->query("ALTER TABLE chamcong ADD INDEX idx_chamcong_nv_ngay (idnv, ngay)");
    }
    if ($idx instanceof mysqli_result) {
        $idx->free();
    }
}

function fetchOpenAttendance(mysqli $conn, int $employeeId, string $day): ?array
{
    $stmt = $conn->prepare('SELECT id, checkin_at, checkout_at, checkin_status FROM chamcong WHERE idnv = ? AND ngay = ? AND checkout_at IS NULL ORDER BY id DESC LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $employeeId, $day);
    $stmt->execute();
    $stmt->bind_result($id, $checkinAt, $checkoutAt, $checkinStatus);
    if ($stmt->fetch()) {
        $stmt->close();
        return [
            'id' => (int)$id,
            'checkin_at' => $checkinAt,
            'checkout_at' => $checkoutAt,
            'checkin_status' => $checkinStatus,
        ];
    }
    $stmt->close();
    return null;
}

function resolveShiftIdForNow(mysqli $conn): ?int
{
    $now = new DateTime();
    $minutesNow = ((int)$now->format('H')) * 60 + (int)$now->format('i');
    $shifts = loadShiftWindows($conn);
    foreach ($shifts as $shift) {
        if (isWithinRange($minutesNow, $shift['start'], $shift['end'])) {
            return $shift['idca'];
        }
    }
    return null;
}

function loadShiftWindows(mysqli $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $res = $conn->query("SELECT idca, giobatdau, gioketthuc FROM calamviec ORDER BY giobatdau, idca");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $start = timeToMinutes($row['giobatdau'] ?? '');
            $end = timeToMinutes($row['gioketthuc'] ?? '');
            if ($start === null || $end === null) {
                continue;
            }
            $cache[] = [
                'idca' => (int)$row['idca'],
                'start' => $start,
                'end' => $end,
            ];
        }
        $res->free();
    }
    return $cache;
}

function timeToMinutes(string $time): ?int
{
    if ($time === '') {
        return null;
    }
    $parts = explode(':', $time);
    if (count($parts) < 2) {
        return null;
    }
    $h = (int)$parts[0];
    $m = (int)$parts[1];
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
        return null;
    }
    return $h * 60 + $m;
}

function computeAttendanceState(array $schedule): array
{
    $now = new DateTime();
    $minutes = ((int)$now->format('H')) * 60 + (int)$now->format('i');
    $checkinState = evaluateWindowState($schedule['checkin'] ?? null, $minutes, true);
    if ($checkinState['allowed']) {
        return $checkinState;
    }

    $checkoutState = evaluateWindowState($schedule['checkout'] ?? null, $minutes, false);
    if ($checkoutState['allowed']) {
        return $checkoutState;
    }

    return [
        'allowed' => false,
        'mode' => null,
        'status' => 'outside',
        'message' => 'Chưa tới hoặc đã quá giờ chấm công',
    ];
}

function evaluateWindowState(?array $window, int $minutesNow, bool $isCheckin): array
{
    $windows = normalizeWindows($window);
    if (empty($windows)) {
        return ['allowed' => false, 'mode' => null, 'status' => 'outside'];
    }

    $lateMatch = null;
    foreach ($windows as $win) {
        [$startMinutes, $endMinutes] = parseWindow($win['start'], $win['end']);
        if ($startMinutes === null || $endMinutes === null) {
            continue;
        }

        if (isWithinRange($minutesNow, $startMinutes, $endMinutes)) {
            return [
                'allowed' => true,
                'mode' => $isCheckin ? 'checkin' : 'checkout',
                'status' => 'on_time',
            ];
        }

        if ($isCheckin && isWithinLateRange($minutesNow, $startMinutes, $endMinutes)) {
            $lateMatch = [
                'allowed' => true,
                'mode' => 'checkin',
                'status' => 'late',
                'message' => 'Chấm công trễ (trong 15 phút cho phép)',
            ];
        }
    }

    if ($lateMatch !== null) {
        return $lateMatch;
    }

    return ['allowed' => false, 'mode' => null, 'status' => 'outside'];
}

function normalizeWindows($window): array
{
    if (!is_array($window)) {
        return [];
    }
    // Single window: ['start' => '08:00', 'end' => '14:00']
    if (array_key_exists('start', $window) && array_key_exists('end', $window)) {
        return [ ['start' => $window['start'], 'end' => $window['end']] ];
    }
    // List of windows: [ ['start' => '08:00', 'end' => '14:00'], ... ]
    $windows = [];
    foreach ($window as $item) {
        if (is_array($item) && isset($item['start'], $item['end'])) {
            $windows[] = ['start' => $item['start'], 'end' => $item['end']];
        }
    }
    return $windows;
}

function parseWindow(string $start, string $end): array
{
    $partsStart = array_map('intval', explode(':', $start));
    $partsEnd = array_map('intval', explode(':', $end));
    if (count($partsStart) < 2 || count($partsEnd) < 2) {
        return [null, null];
    }
    $startMinutes = $partsStart[0] * 60 + $partsStart[1];
    $endMinutes = $partsEnd[0] * 60 + $partsEnd[1];
    return [$startMinutes, $endMinutes];
}

function isWithinRange(int $minutesNow, int $startMinutes, int $endMinutes): bool
{
    if ($endMinutes < $startMinutes) {
        return $minutesNow >= $startMinutes || $minutesNow <= $endMinutes;
    }
    return $minutesNow >= $startMinutes && $minutesNow <= $endMinutes;
}

function isWithinLateRange(int $minutesNow, int $startMinutes, int $endMinutes): bool
{
    if (LATE_GRACE_MINUTES <= 0) {
        return false;
    }
    if (isWithinRange($minutesNow, $startMinutes, $endMinutes)) {
        return false;
    }
    $diff = $minutesNow - $endMinutes;
    if ($diff < 0) {
        $diff += 1440;
    }
    return $diff > 0 && $diff <= LATE_GRACE_MINUTES;
}

function loadAttendanceSchedule(): array
{
    $fallback = [
        'checkin' => [
            'start' => '08:00',
            'end' => '10:00',
        ],
        'checkout' => [
            'start' => '17:00',
            'end' => '20:00',
        ],
    ];
    $schedulePath = __DIR__ . '/../includes/attendance_schedule.php';
    if (file_exists($schedulePath)) {
        $loaded = include $schedulePath;
        if (is_array($loaded)) {
            return $loaded;
        }
    }
    return $fallback;
}
