<?php
include_once 'includes/config_permission.php';
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Kiểm tra quyền truy cập
if (!hasPermission('xem lich lam viec', $permissions)) {
    echo "<script>alert('Bạn không có quyền truy cập chức năng này!'); window.location.href='index.php';</script>";
    exit();
}

// Kiểm tra quyền thêm, sửa, xóa 
// $canAdd = hasPermission('them lich lam viec', $permissions);
//$canEdit = hasPermission('sua lich lam viec', $permissions);
// $canDelete = hasPermission('Xoa mon an', $permissions);

// Trang: Lịch làm việc nhân viên
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
if (isset($_GET['date'])) {
    $selectedDate = DateTime::createFromFormat('Y-m-d', $_GET['date']);
    if ($selectedDate) {
        $selectedDate->setTime(0, 0, 0);
        $selectedMonday = clone $selectedDate;
        $selectedMonday->modify('Monday this week');
        $viewMonday = $selectedMonday;
        $selectedDateValue = $selectedDate->format('Y-m-d');
        $diffSeconds = $selectedMonday->getTimestamp() - $currentMonday->getTimestamp();
        $weekOffset = intdiv($diffSeconds, 604800);
    }
}

$viewSunday = clone $viewMonday;
$viewSunday->modify('+6 days');

$startDate = $viewMonday->format('Y-m-d');
$endDate = $viewSunday->format('Y-m-d');
$weekRangeText = $viewMonday->format('d/m/Y') . ' - ' . $viewSunday->format('d/m/Y');

$datesOfWeek = [];
for ($i = 0; $i < 7; $i++) {
    $date = clone $viewMonday;
    $date->modify('+' . $i . ' days');
    $datesOfWeek[$i + 1] = $date;
}
$selectedDateValue = $selectedDateValue ?? $viewMonday->format('Y-m-d');

$caInfo = [];
$caQuery = "SELECT idca, tenca, giobatdau, gioketthuc FROM calamviec ORDER BY giobatdau, idca";
$caResult = $conn->query($caQuery);
if ($caResult && $caResult->num_rows > 0) {
    while ($ca = $caResult->fetch_assoc()) {
        $caInfo[(int)$ca['idca']] = [
            'tenca' => $ca['tenca'],
            'giobatdau' => $ca['giobatdau'],
            'gioketthuc' => $ca['gioketthuc']
        ];
    }
}

$scheduleData = [];
if (!empty($caInfo)) {
    $scheduleQuery = "SELECT llv.ngay, llv.idca, nv.HoTen, nv.ChucVu, clv.giobatdau
                      FROM lichlamviec llv
                      JOIN calamviec clv ON llv.idca = clv.idca
                      JOIN nhanvien nv ON llv.idnv = nv.idnv
                      WHERE llv.ngay BETWEEN ? AND ?
                      ORDER BY llv.ngay, clv.giobatdau, nv.HoTen";
    $stmt = $conn->prepare($scheduleQuery);
    if ($stmt) {
        $stmt->bind_param('ss', $startDate, $endDate);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
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
                    'name' => $row['HoTen'],
                    'role' => $row['ChucVu']
                ];
            }
        }
        $stmt->close();
    }
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
        <div class="d-flex align-items-center">
            <h3 class="mt-5">Lịch làm việc</h3>
        </div>
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
                       title="Ch��?n ngA�y �`��� xem l��<ch theo tu��n">
            </div>
            <button class="btn btn-outline-secondary btn-sm" onclick="changeWeek(1)">Tuần sau</button>
            
            <a class="btn btn-primary ms-auto" href="index.php?page=sualichlamviec">Phân công</a>
            <?php if ($weekOffset === 0): ?>
                <form method="POST" action="index.php?page=sualichlamviec&week=0" onsubmit="return confirm('Bạn có chắc muốn sao chép lịch tuần này sang tuần sau?');" class="ms-2">
                    <input type="hidden" name="action" value="copy">
                    <button type="submit" class="btn btn-outline-success">Sao chép sang tuần sau</button>
                </form>
            <?php endif; ?>
            
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <th style="min-width: 200px;">Ngày</th>
                        <?php if (!empty($caInfo)): ?>
                            <?php foreach ($caInfo as $caData): ?>
                                <th>
                                    <?php echo htmlspecialchars($caData['tenca']); ?><br>
                                    <span class="small text-muted">
                                        <?php echo htmlspecialchars($caData['giobatdau']); ?> - <?php echo htmlspecialchars($caData['gioketthuc']); ?>
                                    </span>
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
                            <?php if (!empty($caInfo)): ?>
                                <?php foreach ($caInfo as $caId => $caData): ?>
                                    <td class="p-2">
                                        <?php if (!empty($scheduleData[$dayIndex][$caId])): ?>
                                            <?php foreach ($scheduleData[$dayIndex][$caId] as $person): ?>
                                                <div class="small mb-1">
                                                    <?php echo htmlspecialchars($person['name']); ?>
                                                    <span class="text-muted">(<?php echo htmlspecialchars($person['role']); ?>)</span>
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

<script>
    let weekOffset = <?php echo $weekOffset; ?>;
    const baseMonday = new Date('<?php echo $currentMonday->format('Y-m-d'); ?>T00:00:00');

    function getMonday(date) {
        const d = new Date(date);
        const day = d.getDay(); // 0 CN, 1 T2, ... 6 T7
        const diff = (day === 0 ? -6 : 1) - day;
        d.setDate(d.getDate() + diff);
        d.setHours(0, 0, 0, 0);
        return d;
    }

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

        const el = document.getElementById('weekRange');
        if (el) {
            el.textContent = `${formatDate(targetMonday)} - ${formatDate(targetSunday)}`;
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
        window.location.href = `index.php?page=lichlamviec&week=${newOffset}`;
    }

    function initWeekPicker() {
        const picker = document.getElementById('weekPicker');
        if (!picker) {
            return;
        }
        picker.addEventListener('change', function () {
            if (this.value) {
                window.location.href = `index.php?page=lichlamviec&date=${this.value}`;
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

    .badge {
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
    }
</style>
