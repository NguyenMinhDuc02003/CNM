<?php
require_once __DIR__ . '/clsconnect.php';

class clsPayroll extends connect_db
{
    private const STATUS_DRAFT = 'draft';
    private const STATUS_FINAL = 'finalized';

    /**
     * Tạo bảng lương nếu chưa có.
     */
    public function ensureSchema(): void
    {
        $this->executeRaw("
            CREATE TABLE IF NOT EXISTS pay_periods (
                id INT AUTO_INCREMENT PRIMARY KEY,
                period_code VARCHAR(20) NOT NULL UNIQUE, -- YYYY-MM
                from_date DATE NOT NULL,
                to_date DATE NOT NULL,
                status ENUM('draft','finalized') DEFAULT 'draft',
                note TEXT NULL,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                finalized_at DATETIME NULL,
                INDEX idx_period_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        $this->executeRaw("
            CREATE TABLE IF NOT EXISTS pay_salary_lines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                period_id INT NOT NULL,
                staff_id INT NOT NULL,
                base_salary DOUBLE NOT NULL DEFAULT 0,
                paid_days INT NOT NULL DEFAULT 0,
                working_hours DOUBLE NOT NULL DEFAULT 0,
                overtime_hours DOUBLE NOT NULL DEFAULT 0,
                overtime_amount DOUBLE NOT NULL DEFAULT 0,
                allowance_total DOUBLE NOT NULL DEFAULT 0,
                deduction_total DOUBLE NOT NULL DEFAULT 0,
                net_pay DOUBLE NOT NULL DEFAULT 0,
                detail_json LONGTEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_period_staff (period_id, staff_id),
                CONSTRAINT fk_pay_line_period FOREIGN KEY (period_id) REFERENCES pay_periods(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    /**
     * Tạo hoặc lấy kỳ lương (YYYY-MM).
     */
    public function getOrCreatePeriod(string $periodCode, string $fromDate, string $toDate, ?int $userId = null): array
    {
        $this->ensureSchema();
        $periodCode = trim($periodCode);
        $sql = "SELECT * FROM pay_periods WHERE period_code = ? LIMIT 1";
        $rows = $this->xuatdulieu_prepared($sql, [$periodCode]);
        if (!empty($rows)) {
            return $rows[0];
        }

        $insert = "INSERT INTO pay_periods (period_code, from_date, to_date, status, created_by) VALUES (?, ?, ?, 'draft', ?)";
        $this->tuychinh($insert, [$periodCode, $fromDate, $toDate, $userId]);
        $rows = $this->xuatdulieu_prepared($sql, [$periodCode]);
        return $rows[0] ?? [];
    }

    /**
     * Tính lương tháng dựa trên chấm công & lương cơ bản nhân viên.
     */
    public function calculateSalaryForPeriod(string $periodCode, ?int $userId = null): array
    {
        $periodCode = trim($periodCode);
        if (!preg_match('/^\d{4}-\d{2}$/', $periodCode)) {
            throw new Exception('Mã kỳ lương không hợp lệ (định dạng YYYY-MM).');
        }
        $fromDate = $periodCode . '-01';
        $toDate = date('Y-m-t', strtotime($fromDate));
        $period = $this->getOrCreatePeriod($periodCode, $fromDate, $toDate, $userId);
        if (empty($period)) {
            throw new Exception('Không tạo được kỳ lương.');
        }
        $periodId = (int)$period['id'];

        // Lấy tất cả chấm công trong kỳ để tính OT theo luật VN (150%/200%/300% + night 20%)
        $attRows = $this->xuatdulieu_prepared(
            "SELECT nv.idnv AS staff_id, nv.HoTen, nv.ChucVu, COALESCE(nv.Luong, 0) AS base_salary,
                    c.ngay, c.checkin_at, c.checkout_at
             FROM nhanvien nv
             LEFT JOIN chamcong c ON c.idnv = nv.idnv AND c.ngay BETWEEN ? AND ?
             WHERE c.checkin_at IS NOT NULL",
            [$fromDate, $toDate]
        );

        $deleted = $this->tuychinh("DELETE FROM pay_salary_lines WHERE period_id = ?", [$periodId]);
        unset($deleted);

        $lines = [];
        $byStaff = [];
        foreach ($attRows as $row) {
            $sid = (int)$row['staff_id'];
            if (!isset($byStaff[$sid])) {
                $byStaff[$sid] = [
                    'staff_id' => $sid,
                    'staff_name' => $row['HoTen'] ?? '',
                    'position' => $row['ChucVu'] ?? '',
                    'base_salary' => (float)$row['base_salary'],
                    'paid_days' => 0,
                    'working_hours' => 0,
                    'overtime_hours' => 0,
                    'overtime_amount' => 0,
                    'night_bonus' => 0,
                    'seen_days' => [],
                ];
            }

            $checkin = strtotime($row['checkin_at']);
            $checkout = strtotime($row['checkout_at']);
            if (!$checkin || !$checkout || $checkout <= $checkin) {
                continue;
            }

            $day = date('Y-m-d', $checkin);
            $isHoliday = $this->isVietnamHoliday($day);
            $multiplier = $isHoliday ? 3.0 : 1.0;
            if (!isset($byStaff[$sid]['seen_days'][$day])) {
                $byStaff[$sid]['seen_days'][$day] = $multiplier;
                $byStaff[$sid]['paid_days']++;
            } else {
                // Nếu cùng ngày có nhiều bản ghi, lấy hệ số lớn nhất (ưu tiên ngày lễ)
                $byStaff[$sid]['seen_days'][$day] = max($byStaff[$sid]['seen_days'][$day], $multiplier);
            }

            $hours = max(0, ($checkout - $checkin) / 3600);
            $byStaff[$sid]['working_hours'] += $hours;
        }

        foreach ($byStaff as $sid => $data) {
            $dailyRate = $data['base_salary'] > 0 ? $data['base_salary'] / 26 : 0;
            $totalMultiplier = array_sum($data['seen_days'] ?? []);
            $net = $dailyRate * $totalMultiplier; // Ngày lễ nhân 3x, ngày thường 1x
            $detail = [
                'staff_name' => $data['staff_name'],
                'position' => $data['position'],
                'base_salary' => $data['base_salary'],
                'night_bonus' => 0,
                'note' => 'Không tính OT; ngày lễ nhân 300%. Lương = (lương cơ bản/26) * (tổng hệ số ngày).',
            ];

            $this->tuychinh(
                "INSERT INTO pay_salary_lines 
                    (period_id, staff_id, base_salary, paid_days, working_hours, overtime_hours, overtime_amount, allowance_total, deduction_total, net_pay, detail_json) 
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $periodId,
                    $sid,
                    $data['base_salary'],
                    $data['paid_days'],
                    $data['working_hours'],
                    $data['overtime_hours'],
                    $data['overtime_amount'],
                    0,
                    0,
                    $net,
                    json_encode($detail, JSON_UNESCAPED_UNICODE)
                ]
            );

            $lines[] = [
                'staff_id' => $sid,
                'staff_name' => $data['staff_name'],
                'base_salary' => $data['base_salary'],
                'paid_days' => $data['paid_days'],
                'working_hours' => $data['working_hours'],
                'overtime_hours' => $data['overtime_hours'],
                'overtime_amount' => $data['overtime_amount'],
                'net_pay' => $net,
                'detail' => $detail,
            ];
        }

        return [
            'period' => $period,
            'lines' => $lines,
        ];
    }

    public function listPeriodLines(string $periodCode): array
    {
        $sql = "
            SELECT p.id AS period_id, p.period_code, p.from_date, p.to_date, p.status,
                   l.*, nv.HoTen
            FROM pay_periods p
            LEFT JOIN pay_salary_lines l ON l.period_id = p.id
            LEFT JOIN nhanvien nv ON nv.idnv = l.staff_id
            WHERE p.period_code = ?
            ORDER BY nv.HoTen ASC
        ";
        return $this->xuatdulieu_prepared($sql, [$periodCode]);
    }

    private function isVietnamHoliday(string $date): bool
    {
        // Định dạng Y-m-d
        $ts = strtotime($date);
        $year = date('Y', $ts);
        $md = date('m-d', $ts);

        // Lịch chuẩn (dương lịch, cố định), cộng ngày liền kề cho 2/9
        $fixedMd = ['01-01', '04-30', '05-01', '09-02', '09-01', '09-03'];
        if (in_array($md, $fixedMd, true)) {
            return true;
        }

        // Ngày lễ âm lịch (tự động chuyển sang dương)
        $solarFromLunar = $this->lunarHolidaysToSolar((int)$year);
        if (in_array(date('Y-m-d', $ts), $solarFromLunar, true)) {
            return true;
        }

        // Cho phép override ngày lễ (bao gồm Tết âm lịch, Giỗ Tổ) trong file includes/holiday_override.php
        // File trả về array các ngày dạng Y-m-d hoặc m-d (áp dụng mọi năm)
        $customDates = [];
        $override = __DIR__ . '/../includes/holiday_override.php';
        if (file_exists($override)) {
            $loaded = include $override;
            if (is_array($loaded)) {
                $customDates = $loaded;
            }
        }
        foreach ($customDates as $d) {
            $d = trim($d);
            if ($d === '') continue;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && $d === date('Y-m-d', $ts)) {
                return true;
            }
            if (preg_match('/^\d{2}-\d{2}$/', $d) && $d === $md) {
                return true;
            }
        }
        return false;
    }

    /**
     * Chuyển danh sách ngày lễ âm sang dương cho một năm.
     * Mặc định gồm: 01/01, 02/01, 03/01 (Tết), 10/03 (Giỗ Tổ), 05/05, 15/08 (Rằm Trung Thu).
     *
     * @param int $year Dương lịch (ví dụ 2025)
     * @return array Danh sách ngày dương dạng Y-m-d
     */
    private function lunarHolidaysToSolar(int $year): array
    {
        static $cache = [];
        if (isset($cache[$year])) {
            return $cache[$year];
        }

        $lunarDays = [
            ['d' => 1, 'm' => 1],
            ['d' => 2, 'm' => 1],
            ['d' => 3, 'm' => 1],
            ['d' => 10, 'm' => 3], // Giỗ Tổ
            ['d' => 5, 'm' => 5],
            ['d' => 15, 'm' => 8], // Trung thu
        ];

        $result = [];
        foreach ($lunarDays as $item) {
            $dt = $this->convertLunarToSolarDate($item['d'], $item['m'], $year, false);
            if ($dt instanceof DateTime) {
                $result[] = $dt->format('Y-m-d');
            }
        }
        $cache[$year] = $result;
        return $result;
    }

    /**
     * Chuyển ngày âm lịch sang đối tượng DateTime dương lịch (múi giờ VN).
     */
    private function convertLunarToSolarDate(int $lunarDay, int $lunarMonth, int $lunarYear, bool $isLeap): ?DateTime
    {
        [$d, $m, $y] = $this->convertLunar2Solar($lunarDay, $lunarMonth, $lunarYear, $isLeap, 7.0);
        if ($d === 0 || $m === 0 || $y === 0) {
            return null;
        }
        $str = sprintf('%04d-%02d-%02d', $y, $m, $d);
        return DateTime::createFromFormat('Y-m-d', $str) ?: null;
    }

    /**
     * Các hàm chuyển đổi âm-dương (tham khảo thuật toán lịch Việt).
     */
    private function jdFromDate(int $dd, int $mm, int $yy): int
    {
        $a = intdiv(14 - $mm, 12);
        $y = $yy + 4800 - $a;
        $m = $mm + 12 * $a - 3;
        $jd = $dd + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
        if ($jd < 2299161) {
            $jd = $dd + intdiv(367 * $mm - 362, 12) + $yy + 1720994;
            if ($mm > 2) {
                $jd += $this->isLeapYearJulian($yy) ? -1 : -2;
            }
        }
        return $jd;
    }

    private function jdToDate(int $jd): array
    {
        if ($jd > 2299160) {
            $a = $jd + 32044;
            $b = intdiv(4 * $a + 3, 146097);
            $c = $a - intdiv(146097 * $b, 4);
        } else {
            $b = 0;
            $c = $jd + 32082;
        }
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);
        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = 100 * $b + $d - 4800 + intdiv($m, 10);
        return [$day, $month, $year];
    }

    private function isLeapYearJulian(int $year): bool
    {
        return $year % 4 === 0;
    }

    private function NewMoon(int $k, float $timeZone): int
    {
        $T = $k / 1236.85;
        $T2 = $T * $T;
        $T3 = $T2 * $T;
        $dr = M_PI / 180;
        $Jd1 = 2415020.75933 + 29.53058868 * $k + 0.0001178 * $T2 - 0.000000155 * $T3;
        $Jd1 += 0.00033 * sin((166.56 + 132.87 * $T - 0.009173 * $T2) * $dr);
        $M = 359.2242 + 29.10535608 * $k - 0.0000333 * $T2 - 0.00000347 * $T3;
        $Mpr = 306.0253 + 385.81691806 * $k + 0.0107306 * $T2 + 0.00001236 * $T3;
        $F = 21.2964 + 390.67050646 * $k - 0.0016528 * $T2 - 0.00000239 * $T3;
        $C1 = (0.1734 - 0.000393 * $T) * sin($M * $dr) + 0.0021 * sin(2 * $M * $dr);
        $C1 -= 0.4068 * sin($Mpr * $dr) + 0.0161 * sin(2 * $Mpr * $dr);
        $C1 -= 0.0004 * sin(3 * $Mpr * $dr);
        $C1 += 0.0104 * sin(2 * $F * $dr) - 0.0051 * sin(($M + $Mpr) * $dr);
        $C1 -= 0.0074 * sin(($M - $Mpr) * $dr) + 0.0004 * sin((2 * $F + $M) * $dr);
        $C1 -= 0.0004 * sin((2 * $F - $M) * $dr) - 0.0006 * sin((2 * $F + $Mpr) * $dr);
        $C1 += 0.0010 * sin((2 * $F - $Mpr) * $dr) + 0.0005 * sin(($M + 2 * $Mpr) * $dr);
        $deltaT = ($T < -11) ? 0.001 + 0.000839 * $T + 0.0002261 * $T2 - 0.00000845 * $T3 - 0.000000081 * $T2 * $T2 : -0.000278 + 0.000265 * $T + 0.000262 * $T2;
        $JdNew = $Jd1 + $C1 - $deltaT;
        return (int)floor($JdNew + 0.5 + $timeZone / 24);
    }

    private function SunLongitude(float $jdn, float $timeZone): float
    {
        $T = ($jdn - 2451545.5 - $timeZone / 24) / 36525;
        $T2 = $T * $T;
        $dr = M_PI / 180;
        $M = 357.52910 + 35999.05030 * $T - 0.0001559 * $T2 - 0.00000048 * $T * $T2;
        $L0 = 280.46645 + 36000.76983 * $T + 0.0003032 * $T2;
        $DL = (1.914600 - 0.004817 * $T - 0.000014 * $T2) * sin($dr * $M);
        $DL += (0.019993 - 0.000101 * $T) * sin(2 * $dr * $M) + 0.000290 * sin(3 * $dr * $M);
        $L = $L0 + $DL;
        $L *= $dr;
        $L -= M_PI * 2 * floor($L / (M_PI * 2));
        return $L;
    }

    private function getSunLongitude(int $dayNumber, float $timeZone): int
    {
        return (int)floor($this->SunLongitude($dayNumber, $timeZone) / M_PI * 6);
    }

    private function getLunarMonth11(int $yy, float $timeZone): int
    {
        $off = $this->jdFromDate(31, 12, $yy) - 2415021;
        $k = (int)floor($off / 29.530588853);
        $nm = $this->NewMoon($k, $timeZone);
        $sunLong = $this->getSunLongitude($nm, $timeZone);
        if ($sunLong >= 9) {
            $nm = $this->NewMoon($k - 1, $timeZone);
        }
        return $nm;
    }

    private function getLeapMonthOffset(int $a11, float $timeZone): int
    {
        $k = (int)floor(($a11 - 2415021.076998695) / 29.530588853 + 0.5);
        $last = 0;
        $i = 1;
        $arc = $this->getSunLongitude($this->NewMoon($k + $i, $timeZone), $timeZone);
        do {
            $last = $arc;
            $i++;
            $arc = $this->getSunLongitude($this->NewMoon($k + $i, $timeZone), $timeZone);
        } while ($arc !== $last && $i < 14);
        return $i - 1;
    }

    /**
     * @return array{int,int,int} [day, month, year]
     */
    private function convertLunar2Solar(int $lunarDay, int $lunarMonth, int $lunarYear, bool $lunarLeap, float $timeZone): array
    {
        $a11 = $this->getLunarMonth11($lunarYear, $timeZone);
        $b11 = $this->getLunarMonth11($lunarYear + 1, $timeZone);
        $off = $lunarMonth - 11;
        if ($off < 0) {
            $off += 12;
        }
        $leapOff = $this->getLeapMonthOffset($a11, $timeZone);
        $leapMonth = $leapOff - 2;
        if ($leapMonth < 0) {
            $leapMonth += 12;
        }
        if ($lunarLeap && $lunarMonth !== $leapMonth) {
            return [0, 0, 0];
        }
        if ($lunarLeap || $off >= $leapOff) {
            $off += 1;
        }
        $k = (int)floor(0.5 + ($a11 - 2415021.076998695) / 29.530588853);
        $monthStart = $this->NewMoon($k + $off, $timeZone);
        return $this->jdToDate($monthStart + $lunarDay - 1);
    }
    private function calculateNightHours(int $checkinTs, int $checkoutTs): float
    {
        // Khoảng đêm 22:00 - 06:00 (qua ngày)
        $nightHours = 0.0;
        $start = $checkinTs;
        while ($start < $checkoutTs) {
            $dayStart = strtotime(date('Y-m-d', $start) . ' 00:00:00');
            $nightStart = $dayStart + 22 * 3600;
            $nightEnd = $dayStart + 24 * 3600 + 6 * 3600; // 06:00 ngày hôm sau
            $segmentStart = max($start, $nightStart);
            $segmentEnd = min($checkoutTs, $nightEnd);
            if ($segmentEnd > $segmentStart) {
                $nightHours += ($segmentEnd - $segmentStart) / 3600;
            }
            $start = $nightEnd; // nhảy sang sau 06:00 ngày hôm sau
        }
        return $nightHours;
    }
}
?>
