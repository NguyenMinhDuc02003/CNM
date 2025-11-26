<?php
if (!function_exists('admin_auth_bootstrap_session')) {
    require_once __DIR__ . '/../../includes/auth.php';
}
if (!class_exists('connect_db')) {
    require_once __DIR__ . '/../../class/clsconnect.php';
}

admin_auth_bootstrap_session();

$currentUserId = $_SESSION['nhanvien_id'] ?? null;
if (!$currentUserId) {
    echo '<div class="container py-5"><div class="alert alert-danger">Vui lòng đăng nhập để tiếp tục.</div></div>';
    return;
}

$db = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db
    ? $GLOBALS['admin_db']
    : new connect_db();

$employeeRows = $db->xuatdulieu_prepared(
    "SELECT n.idnv, n.HinhAnh, n.HoTen, n.GioiTinh, n.SoDienThoai, n.Email, n.DiaChi, n.ChucVu, v.tenvaitro
     FROM nhanvien n
     LEFT JOIN vaitro v ON n.idvaitro = v.idvaitro
     WHERE n.idnv = ?
     LIMIT 1",
    [(int) $currentUserId]
);
$employee = $employeeRows[0] ?? null;

if (!$employee) {
    echo '<div class="container py-5"><div class="alert alert-warning">Không tìm thấy thông tin nhân viên.</div></div>';
    return;
}

$today = new DateTime('today');
$weekStart = (clone $today)->modify('Monday this week');
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $day = (clone $weekStart)->modify("+{$i} days");
    $weekDates[$day->format('N')] = $day;
}

$scheduleRows = $db->xuatdulieu_prepared(
    "SELECT llv.ngay, clv.tenca, clv.giobatdau, clv.gioketthuc
     FROM lichlamviec llv
     INNER JOIN calamviec clv ON llv.idca = clv.idca
     WHERE llv.idnv = ? AND llv.ngay BETWEEN ? AND ?
     ORDER BY llv.ngay, clv.giobatdau",
    [(int) $currentUserId, $weekStart->format('Y-m-d'), $weekStart->modify('+6 days')->format('Y-m-d')]
);

$scheduleByDay = [];
foreach ($scheduleRows as $row) {
    $dayIndex = (int) (DateTime::createFromFormat('Y-m-d', $row['ngay']) ?: new DateTime($row['ngay']))->format('N');
    $scheduleByDay[$dayIndex][] = [
        'label' => $row['tenca'] ?? 'Chưa xác định',
        'start' => substr((string) $row['giobatdau'], 0, 5),
        'end' => substr((string) $row['gioketthuc'], 0, 5),
    ];
}

$dayLabels = [
    1 => 'Thứ 2',
    2 => 'Thứ 3',
    3 => 'Thứ 4',
    4 => 'Thứ 5',
    5 => 'Thứ 6',
    6 => 'Thứ 7',
    7 => 'Chủ nhật',
];

$profileImage = 'assets/img/profile.jpg';
if (!empty($employee['HinhAnh'])) {
    $customPath = 'assets/img/' . ltrim($employee['HinhAnh'], '/');
    $absolutePath = realpath(__DIR__ . '/../../' . $customPath);
    $basePath = realpath(__DIR__ . '/../../');
    if ($absolutePath && $basePath && strpos($absolutePath, $basePath) === 0 && file_exists($absolutePath)) {
        $profileImage = $customPath;
    }
}

$gender = $employee['GioiTinh'] ?? 'Chưa cập nhật';

$contactItems = [
    'Số điện thoại' => $employee['SoDienThoai'] ?? '',
    'Email' => $employee['Email'] ?? '',
    'Địa chỉ' => $employee['DiaChi'] ?? '',
];
?>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card profile-card shadow-sm border-0 text-center h-100">
                <div class="card-body px-4 py-5">
                    <div class="profile-avatar mx-auto mb-3">
                        <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Avatar"
                             class="rounded-circle" style="width: 140px; height: 140px; object-fit: cover;">
                    </div>
                    <h3 class="fw-bold mb-0"><?php echo htmlspecialchars($employee['HoTen'] ?? ''); ?></h3>
                    <p class="text-muted mb-4">
                        <?php
                        $role = $employee['tenvaitro'] ?? $employee['ChucVu'] ?? '';
                        echo htmlspecialchars($role !== '' ? $role : 'Nhân viên');
                        ?>
                    </p>
                    <div class="profile-info">
                        <div class="info-item">
                            <span class="text-muted">Mã nhân viên</span>
                            <strong><?php echo htmlspecialchars((string) $employee['idnv']); ?></strong>
                        </div>
                        <div class="info-item">
                            <span class="text-muted">Giới tính</span>
                            <strong><?php echo htmlspecialchars($gender !== '' ? $gender : 'Chưa cập nhật'); ?></strong>
                        </div>
                    </div>
                    <div class="contact-info mt-4 text-start">
                        <h6 class="text-uppercase text-muted mb-3">Thông tin liên hệ</h6>
                        <?php foreach ($contactItems as $label => $value): ?>
                            <div class="d-flex align-items-center mb-2">
                                <span class="text-muted" style="min-width: 120px;"><?php echo htmlspecialchars($label); ?></span>
                                <span><?php echo htmlspecialchars($value !== '' ? $value : 'Chưa cập nhật'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4">
                        <a href="index.php?page=suathongtin&idnv=<?php echo (int) $employee['idnv']; ?>" class="btn btn-warning">
                            <i class="fas fa-pencil-alt me-2"></i>Chỉnh sửa thông tin
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body p-4">
                    <h4 class="fw-bold mb-4">Lịch làm việc tuần này</h4>
                    <div class="row g-3">
                        <?php foreach ($weekDates as $dayIndex => $dateObj): ?>
                            <div class="col-md-6">
                                <div class="schedule-card border rounded h-100 p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="fw-semibold"><?php echo htmlspecialchars($dayLabels[$dayIndex]); ?></span>
                                        <span class="text-muted small"><?php echo htmlspecialchars($dateObj->format('d/m/Y')); ?></span>
                                    </div>
                                    <?php if (!empty($scheduleByDay[$dayIndex])): ?>
                                        <?php foreach ($scheduleByDay[$dayIndex] as $shift): ?>
                                            <div class="shift-item bg-light rounded px-3 py-2 mb-2">
                                                <div class="fw-semibold"><?php echo htmlspecialchars($shift['label']); ?></div>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars($shift['start']); ?> - <?php echo htmlspecialchars($shift['end']); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted fst-italic">Không có lịch</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
