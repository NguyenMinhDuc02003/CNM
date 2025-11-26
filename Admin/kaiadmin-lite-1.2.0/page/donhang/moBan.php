<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsdatban.php';
require_once __DIR__ . '/../../class/clsDonHang.php';

$db = new connect_db();
$bookingModel = new datban();
$orderService = new clsDonHang();

$errors = [];
$successMessage = '';
$step = 1;
$defaultDateTime = date('Y-m-d H:i:s');
$debugMoBan = false;

$flow = isset($_SESSION['open_table_flow']) && is_array($_SESSION['open_table_flow'])
    ? $_SESSION['open_table_flow']
    : [];
// Are we in an editing-selection flow (user clicked "Chọn lại bàn")?
$editingSelection = !empty($flow['editing_selection']);

if (!function_exists('admin_redirect')) {
    function admin_redirect($url)
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
            exit;
        }
        echo '<script>window.location.href = ' . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
        exit;
    }
}

$fromOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($fromOrderId > 0) {
    $prefillError = null;
    $orderData = $orderService->getOrderById($fromOrderId);
    if (!$orderData) {
        $prefillError = 'Không tìm thấy đơn hàng #' . $fromOrderId . '.';
    } elseif (!in_array($orderData['TrangThai'], [clsDonHang::ORDER_STATUS_OPEN, clsDonHang::ORDER_STATUS_PENDING_PAYMENT], true)) {
        $prefillError = 'Chỉ có thể mở lại đơn đang phục vụ hoặc chờ thanh toán.';
    } else {
        $tableId = (int)($orderData['idban'] ?? 0);
        $tableIds = [];

        if (!empty($orderData['table_ids'])) {
            $decodedIds = json_decode($orderData['table_ids'], true);
            if (is_array($decodedIds)) {
                foreach ($decodedIds as $rawId) {
                    $rawId = (int)$rawId;
                    if ($rawId > 0) {
                        $tableIds[] = $rawId;
                    }
                }
            }
        }
        if ($tableId > 0) {
            $tableIds[] = $tableId;
        }

        $tableIds = array_values(array_unique(array_filter($tableIds, static function ($value) {
            return (int)$value > 0;
        })));

        if (empty($tableIds)) {
            $prefillError = 'Đơn hàng chưa được gán bàn.';
        } else {
            $placeholders = implode(',', array_fill(0, count($tableIds), '?'));
            $tableRows = $db->xuatdulieu_prepared(
                "SELECT b.idban, b.SoBan, b.MaKV, b.soluongKH, b.zone, kv.TenKV, kv.PhuThu
                 FROM ban b
                 LEFT JOIN khuvucban kv ON b.MaKV = kv.MaKV
                 WHERE b.idban IN ($placeholders)",
                $tableIds
            );
            if (empty($tableRows)) {
                $prefillError = 'Không tìm thấy thông tin bàn cho đơn #' . $fromOrderId . '.';
            } else {
                $tableMap = [];
                foreach ($tableRows as $tableRow) {
                    $tableMap[(int)$tableRow['idban']] = $tableRow;
                }

                $tablePayloads = [];
                $combinedTableNames = [];
                $totalCapacity = 0;
                $totalSurchargeEstimate = 0.0;

                foreach ($tableIds as $selectedId) {
                    if (!isset($tableMap[$selectedId])) {
                        $prefillError = 'Không tìm thấy thông tin bàn #' . $selectedId . '.';
                        break;
                    }
                    $info = $tableMap[$selectedId];
                    $capacity = (int)($info['soluongKH'] ?? 0);
                    $surchargeEach = isset($info['PhuThu']) ? (float)$info['PhuThu'] : 0.0;
                    $tablePayloads[] = [
                        'idban' => $selectedId,
                        'soban' => $info['SoBan'] ?? ('#' . $selectedId),
                        'capacity' => $capacity,
                        'phuthu' => $surchargeEach,
                        'khuvuc' => isset($info['MaKV']) ? (int)$info['MaKV'] : 0,
                        'zone' => $info['zone'] ?? null,
                        'TenKV' => $info['TenKV'] ?? '',
                    ];
                    $combinedTableNames[] = 'Bàn ' . ($info['SoBan'] ?? $selectedId);
                    $totalCapacity += $capacity;
                    $totalSurchargeEstimate += $surchargeEach;
                }

                if ($prefillError === null) {
                    $primaryTable = $tablePayloads[0];
                    $areaId = (int)($primaryTable['khuvuc'] ?? 0);
                    $areaName = $orderData['area_name'] ?? ($primaryTable['TenKV'] ?? '');
                    $surcharge = isset($orderData['surcharge']) && $orderData['surcharge'] !== null
                        ? (float)$orderData['surcharge']
                        : $totalSurchargeEstimate;
                    $peopleCount = isset($orderData['people_count']) ? (int)$orderData['people_count'] : $totalCapacity;
                    $tableLabel = $orderData['table_label'] ?? implode(', ', $combinedTableNames);

                    $newFlow = [
                        'booking' => [
                            'khuvuc' => $areaId,
                            'datetime' => $orderData['booking_time'] ?? $orderData['NgayDatHang'] ?? date('Y-m-d H:i:s'),
                            'tables' => $tablePayloads,
                            'total_surcharge' => $surcharge,
                            'people_count' => $peopleCount,
                            'table_label' => $tableLabel,
                            'source_booking_id' => $orderData['madatban'] ?? null,
                        ],
                        'area' => [
                            'id' => $areaId,
                            'name' => $areaName,
                        'surcharge' => isset($primaryTable['phuthu']) ? (float)$primaryTable['phuthu'] : 0.0,
                        ],
                        'order_id' => $fromOrderId,
                        'note' => $orderData['note'] ?? null,
                        'step' => 2,
                    ];

                    $_SESSION['open_table_flow'] = $newFlow;
                    $_SESSION['admin_flash'] = [
                        'type' => 'info',
                        'message' => 'Đang tiếp tục phục vụ đơn #' . $fromOrderId . '.'
                    ];
                    admin_redirect('index.php?page=moBan');
                }
            }
        }
    }

    if ($prefillError !== null) {
        $_SESSION['admin_flash'] = [
            'type' => 'warning',
            'message' => $prefillError
        ];
        admin_redirect('index.php?page=dsdonhang');
    }
}

$flashMessage = null;
if (!empty($_SESSION['admin_flash']) && is_array($_SESSION['admin_flash'])) {
    $allowedAlertTypes = ['success', 'info', 'warning', 'danger', 'primary', 'secondary', 'light', 'dark'];
    $flashType = $_SESSION['admin_flash']['type'] ?? 'info';
    $flashMessage = [
        'type' => in_array($flashType, $allowedAlertTypes, true) ? $flashType : 'info',
        'message' => $_SESSION['admin_flash']['message'] ?? ''
    ];
    unset($_SESSION['admin_flash']);
}

if (!isset($flow['booking']) || !is_array($flow['booking'])) {
    $flow['booking'] = [
        'khuvuc' => 0,
        'datetime' => $defaultDateTime,
        'tables' => [],
        'total_surcharge' => 0,
        'people_count' => 0,
        'table_label' => ''
    ];
}
$sourceBookingId = isset($flow['booking']['source_booking_id']) ? (int)$flow['booking']['source_booking_id'] : null;

$storedDatetime = $flow['booking']['datetime'] ?? $defaultDateTime;
$selectedDateTimeObj = DateTime::createFromFormat('Y-m-d H:i:s', $storedDatetime);
if (!$selectedDateTimeObj) {
    $selectedDateTimeObj = DateTime::createFromFormat('Y-m-d H:i', $storedDatetime);
}
if (!$selectedDateTimeObj) {
    $selectedDateTimeObj = DateTime::createFromFormat('Y-m-d H:i:s', $defaultDateTime);
}

$selectedDate = $selectedDateTimeObj->format('Y-m-d');
$selectedTime = $selectedDateTimeObj->format('H:i');
$previousSelectedArea = isset($flow['booking']['khuvuc']) ? (string)$flow['booking']['khuvuc'] : '';
$selectedArea = $previousSelectedArea;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['khuvuc'])) {
    $selectedArea = (string)$_POST['khuvuc'];
} elseif (isset($_GET['khuvuc']) && $_GET['khuvuc'] !== '') {
    $selectedArea = (string)$_GET['khuvuc'];
}

if ($debugMoBan) {
    error_log('[moBan] Initial selection - method=' . $_SERVER['REQUEST_METHOD'] . ' selectedArea=' . var_export($selectedArea, true) . ' previous=' . var_export($previousSelectedArea, true));
}

try {
    $areas = $db->xuatdulieu("SELECT MaKV, TenKV, PhuThu FROM khuvucban WHERE TrangThai = 'active' ORDER BY TenKV");
} catch (Exception $e) {
    $areas = [];
}
if ($debugMoBan) {
    $areaSummary = array_map(static function ($area) {
        return (string)$area['MaKV'] . ':' . ($area['TenKV'] ?? '');
    }, $areas);
    error_log('[moBan] Area list: ' . implode(' | ', $areaSummary));
}

if (($selectedArea === '' || $selectedArea === null) && !empty($areas)) {
    $selectedArea = (string)$areas[0]['MaKV'];
}

$selectedAreaForQuery = is_numeric($selectedArea) ? (int)$selectedArea : ($selectedArea === '' ? '' : $selectedArea);

if (isset($_GET['reset'])) {
    unset($_SESSION['open_table_flow']);
    $flow['booking']['tables'] = [];
    $flow['booking']['total_surcharge'] = 0;
    $flow['booking']['people_count'] = 0;
    $flow['booking']['table_label'] = '';
    $selectedArea = !empty($areas) ? (string)$areas[0]['MaKV'] : '';
    $selectedAreaForQuery = is_numeric($selectedArea) ? (int)$selectedArea : ($selectedArea === '' ? '' : $selectedArea);
    $selectedDateTimeObj = DateTime::createFromFormat('Y-m-d H:i:s', $defaultDateTime);
    $selectedDate = $selectedDateTimeObj->format('Y-m-d');
    $selectedTime = $selectedDateTimeObj->format('H:i');
    $step = 1;
    admin_redirect('index.php?page=moBan');
}

$forceStep1 = false;
if (isset($_GET['change_table'])) {
    // If user chooses to change table selection, release any tables previously
    // reserved/occupied by this session flow so they become available again.
    $previousTables = isset($flow['booking']['tables']) && is_array($flow['booking']['tables']) ? $flow['booking']['tables'] : [];
    // Collect ids from session flow
    $idsToRelease = [];
    foreach ($previousTables as $pt) {
        $tid = isset($pt['idban']) ? (int)$pt['idban'] : 0;
        if ($tid > 0) $idsToRelease[] = $tid;
    }

    // If there's an order id attached to the flow, prefer to also release any
    // tables persisted in donhang_admin_meta.table_ids (in case session lost data).
    $orderIdForFlow = isset($flow['order_id']) ? (int)$flow['order_id'] : 0;
    if ($orderIdForFlow > 0) {
        try {
            $meta = $db->xuatdulieu_prepared("SELECT table_ids FROM donhang_admin_meta WHERE idDH = ? LIMIT 1", [$orderIdForFlow]);
            if (!empty($meta) && !empty($meta[0]['table_ids'])) {
                $metaIds = json_decode($meta[0]['table_ids'], true);
                if (is_array($metaIds)) {
                    foreach ($metaIds as $mid) {
                        $mid = (int)$mid;
                        if ($mid > 0) $idsToRelease[] = $mid;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[moBan] Failed to read order meta for release: ' . $e->getMessage());
        }
    }

    // If session flow had no tables but we found meta ids, populate booking.tables
    if (empty($previousTables) && !empty($metaIds) && is_array($metaIds)) {
        $reconstructed = [];
        foreach ($metaIds as $mid) {
            $mid = (int)$mid;
            if ($mid > 0) {
                $reconstructed[] = ['idban' => $mid];
            }
        }
        if (!empty($reconstructed)) {
            $flow['booking']['tables'] = $reconstructed;
        }
    }

    // Deduplicate ids and release in a single query when possible.
    $idsToRelease = array_values(array_unique(array_filter($idsToRelease, static function ($v) { return $v > 0; })));
    if (!empty($idsToRelease)) {
        try {
            $placeholders = implode(',', array_fill(0, count($idsToRelease), '?'));
            $db->tuychinh("UPDATE ban SET TrangThai = 'empty' WHERE idban IN ($placeholders)", $idsToRelease);
            $_SESSION['admin_flash'] = [
                'type' => 'info',
                'message' => 'Bàn trước đó đã được giải phóng. Vui lòng chọn lại bàn.'
            ];
        } catch (Throwable $e) {
            error_log('[moBan] Failed to release previous tables on change_table: ' . $e->getMessage());
            $_SESSION['admin_flash'] = [
                'type' => 'warning',
                'message' => 'Không thể giải phóng bàn trước đó. Vui lòng thử lại hoặc liên hệ quản trị.'
            ];
        }
    }

    // Keep the previous selection in the session so they appear checked (yellow)
    // in the selection UI, but force the user back to step 1 to allow unchecking
    // and selecting different tables. The DB rows were already released above.
    $flow['step'] = 1;
    $flow['editing_selection'] = true;
    $_SESSION['open_table_flow'] = $flow;
    $forceStep1 = true;
    admin_redirect('index.php?page=moBan');
}

$existingTables = [];
$tableMap = [];
$bookedTables = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formAction = $_POST['form_action'] ?? 'refresh';
    if ($debugMoBan) {
        error_log('[moBan] POST payload - form_action=' . $formAction . ' khuvuc=' . var_export($_POST['khuvuc'] ?? null, true));
    }
    $postedDate = $_POST['booking_date'] ?? $selectedDate;
    $postedTime = $_POST['booking_time'] ?? $selectedTime;

    $newDateTime = DateTime::createFromFormat('Y-m-d H:i', $postedDate . ' ' . $postedTime);
    if ($newDateTime === false) {
        $errors[] = 'Vui lòng chọn ngày giờ hợp lệ.';
    } else {
        $selectedDateTimeObj = $newDateTime;
        $selectedDate = $selectedDateTimeObj->format('Y-m-d');
        $selectedTime = $selectedDateTimeObj->format('H:i');
    }

    $selectedDateTimeString = $selectedDateTimeObj->format('Y-m-d H:i:s');

    $selectedAreaForQuery = is_numeric($selectedArea) ? (int)$selectedArea : $selectedArea;
    if ($debugMoBan) {
        error_log('[moBan] Resolved area after POST - selectedArea=' . var_export($selectedArea, true) . ' queryValue=' . var_export($selectedAreaForQuery, true));
    }
    if ($selectedArea !== '' && $selectedAreaForQuery !== '') {
        $existingTables = $bookingModel->getBanTheoKhuVuc($selectedAreaForQuery);
        foreach ($existingTables as $table) {
            $tableMap[(int)$table['idban']] = $table;
        }
        $bookedTables = $bookingModel->getBanDaDat($selectedAreaForQuery, $selectedDateTimeString);
        if ($debugMoBan) {
            error_log('[moBan] Loaded tables count=' . count($existingTables) . ' booked=' . count($bookedTables));
        }
    }

    if ($formAction === 'open_table') {
        if ($selectedArea === '' || $selectedAreaForQuery === '') {
            $errors[] = 'Vui lòng chọn khu vực trước khi mở bàn.';
        }

        $tableIds = isset($_POST['tables']) ? array_map('intval', (array)$_POST['tables']) : [];
        $tableIds = array_values(array_filter($tableIds));
        if (empty($tableIds)) {
            $errors[] = 'Vui lòng chọn ít nhất một bàn.';
        }

        $selectedTablesData = [];
        $totalCapacity = 0;
        $combinedTableNames = [];

        foreach ($tableIds as $tableId) {
            if (!isset($tableMap[$tableId])) {
                $errors[] = "Bàn #{$tableId} không thuộc khu vực đã chọn.";
                continue;
            }
            if (in_array($tableId, $bookedTables, true)) {
                $tableName = $tableMap[$tableId]['SoBan'] ?? $tableId;
                $errors[] = "Bàn {$tableName} đã được đặt trong khung giờ này.";
                continue;
            }

            // Nếu bàn đang ở trạng thái occupied (đang phục vụ) thì không cho mở lại
            if (isset($tableMap[$tableId]['TrangThai']) && $tableMap[$tableId]['TrangThai'] === 'occupied') {
                $tableName = $tableMap[$tableId]['SoBan'] ?? $tableId;
                $errors[] = "Bàn {$tableName} đang phục vụ và không thể mở.";
                continue;
            }

            $tableInfo = $tableMap[$tableId];
            $totalCapacity += (int)($tableInfo['soluongKH'] ?? 0);
            $selectedTablesData[] = [
                'idban' => $tableId,
                'soban' => $tableInfo['SoBan'] ?? ('#' . $tableId),
                'capacity' => (int)($tableInfo['soluongKH'] ?? 0),
                'zone' => strtoupper($tableInfo['zone'] ?? '')
            ];
            $combinedTableNames[] = $tableInfo['SoBan'] ?? ('#' . $tableId);
        }

    if (empty($errors) && !empty($selectedTablesData)) {
            $areaInfo = null;
            foreach ($areas as $areaRow) {
                if ((string)$areaRow['MaKV'] === $selectedArea) {
                    $areaInfo = $areaRow;
                    break;
                }
            }

            if ($areaInfo === null) {
                $areaInfo = [
                    'MaKV' => $selectedArea,
                    'TenKV' => '',
                    'PhuThu' => 0
                ];
            }

            $areaSurcharge = (float)($areaInfo['PhuThu'] ?? 0);

            $flow['booking']['khuvuc'] = $selectedArea;
            $flow['booking']['datetime'] = $selectedDateTimeString;
            $flow['booking']['tables'] = $selectedTablesData;
            $flow['booking']['total_surcharge'] = $areaSurcharge * count($selectedTablesData);
            $flow['booking']['people_count'] = $totalCapacity;
            $flow['booking']['table_label'] = implode(', ', $combinedTableNames);
            $flow['area'] = [
                'id' => $areaInfo['MaKV'] ?? $selectedArea,
                'name' => $areaInfo['TenKV'] ?? '',
                'surcharge' => $areaSurcharge
            ];

            $orderIdForFlow = isset($flow['order_id']) ? (int)$flow['order_id'] : 0;
            if ($orderIdForFlow > 0) {
                $tableIdsForMeta = array_values(array_filter(array_map(static function ($t) {
                    return isset($t['idban']) ? (int)$t['idban'] : 0;
                }, $selectedTablesData)));
                $primaryTableId = $tableIdsForMeta[0] ?? 0;

                if ($primaryTableId > 0) {
                    try {
                        $db->tuychinh(
                            "UPDATE donhang SET idban = ? WHERE idDH = ?",
                            [$primaryTableId, $orderIdForFlow]
                        );
                    } catch (Throwable $e) {
                        error_log('[moBan] Failed to update primary table for order #' . $orderIdForFlow . ': ' . $e->getMessage());
                    }
                }
                try {
                    $db->tuychinh(
                        "UPDATE donhang_admin_meta
                         SET table_label = ?, table_ids = ?, people_count = ?, surcharge = ?, area_name = ?, booking_time = ?, updated_at = NOW()
                         WHERE idDH = ?",
                        [
                            $flow['booking']['table_label'],
                            !empty($tableIdsForMeta) ? json_encode($tableIdsForMeta) : null,
                            $flow['booking']['people_count'],
                            $flow['booking']['total_surcharge'],
                            $flow['area']['name'] ?? null,
                            $flow['booking']['datetime'] ?? null,
                            $orderIdForFlow
                        ]
                    );
                } catch (Throwable $e) {
                    error_log('[moBan] Failed to sync admin meta for order #' . $orderIdForFlow . ': ' . $e->getMessage());
                }
            }
            // Completed selection; clear editing flag so we progress to step 2.
            $flow['step'] = 2;
            if (isset($flow['editing_selection'])) {
                unset($flow['editing_selection']);
            }
            $_SESSION['open_table_flow'] = $flow;
            // Mark all selected tables as occupied in DB so they appear locked for others
            try {
                foreach ($selectedTablesData as $t) {
                    $tid = isset($t['idban']) ? (int)$t['idban'] : 0;
                    if ($tid <= 0) continue;
                    $db->tuychinh("UPDATE ban SET TrangThai = 'occupied' WHERE idban = ?", [$tid]);
                }
            } catch (Throwable $e) {
                // Log error but don't prevent flow
                error_log('[moBan] Failed to mark tables occupied: ' . $e->getMessage());
            }
            // Redirect after marking tables occupied so the updated 'occupied' status
            // is reloaded from the database and all selected tables appear locked (red).
            // Persist flow (editing flag already cleared above).
            $_SESSION['open_table_flow'] = $flow;
            $_SESSION['admin_flash'] = [
                'type' => 'success',
                'message' => 'Đã chọn ' . count($selectedTablesData) . ' bàn. Bàn đã được khóa (occupied).'
            ];
            admin_redirect('index.php?page=moBan');
        } else {
            $step = 1;
        }
    } else {
        if ($selectedArea !== $previousSelectedArea) {
            $flow['booking']['tables'] = [];
            $flow['booking']['total_surcharge'] = 0;
            $flow['booking']['people_count'] = 0;
            $flow['booking']['table_label'] = '';
            unset($flow['area']);
        }
        $flow['booking']['khuvuc'] = $selectedArea;
        $flow['booking']['datetime'] = $selectedDateTimeString;
        $_SESSION['open_table_flow'] = $flow;
        $step = 1;
    }
}

$selectedDateTimeString = $selectedDateTimeObj->format('Y-m-d H:i:s');
$selectedAreaForQuery = is_numeric($selectedArea) ? (int)$selectedArea : ($selectedArea === '' ? '' : $selectedArea);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $selectedArea !== $previousSelectedArea) {
    $flow['booking']['khuvuc'] = $selectedArea;
    $flow['booking']['tables'] = [];
    $flow['booking']['total_surcharge'] = 0;
    $flow['booking']['people_count'] = 0;
    unset($flow['area']);
    $_SESSION['open_table_flow'] = $flow;
}

if (empty($existingTables) && $selectedArea !== '' && $selectedAreaForQuery !== '') {
    $existingTables = $bookingModel->getBanTheoKhuVuc($selectedAreaForQuery);
    foreach ($existingTables as $table) {
        $tableMap[(int)$table['idban']] = $table;
    }
}
if ($debugMoBan) {
    error_log('[moBan] Final table snapshot area=' . var_export($selectedArea, true) . ' tables=' . count($existingTables));
}

if (empty($bookedTables) && $selectedArea > 0) {
    $bookedTables = $bookingModel->getBanDaDat($selectedArea, $selectedDateTimeString);
}

$prefilledTableIds = [];
if (!empty($flow['booking']['tables']) && is_array($flow['booking']['tables'])) {
    foreach ($flow['booking']['tables'] as $table) {
        $tid = isset($table['idban']) ? (int)$table['idban'] : 0;
        if ($tid > 0) {
            $prefilledTableIds[] = $tid;
        }
    }
}

$currentAreaName = 'Vui lòng chọn khu vực';
$areaSurcharge = 0;
foreach ($areas as $area) {
    if ((string)$area['MaKV'] === $selectedArea) {
        $currentAreaName = $area['TenKV'];
        $areaSurcharge = (float)$area['PhuThu'];
        break;
    }
}

$zones = [];
foreach ($existingTables as $table) {
    $zoneLabelRaw = isset($table['zone']) ? trim((string)$table['zone']) : '';
    $zoneKey = strtoupper($zoneLabelRaw !== '' ? $zoneLabelRaw : 'A');
    $zones[$zoneKey][] = $table;
}
ksort($zones);

$preferredZoneOrder = ['A', 'B', 'C', 'D'];
$orderedZones = [];
foreach ($preferredZoneOrder as $label) {
    if (!empty($zones[$label])) {
        $orderedZones[$label] = $zones[$label];
    }
}
foreach ($zones as $label => $tablesByZone) {
    if (empty($tablesByZone)) {
        continue;
    }
    if (!isset($orderedZones[$label])) {
        $orderedZones[$label] = $tablesByZone;
    }
}
$availableZones = array_keys($orderedZones);
$zoneIcons = [
    'A' => 'fa-seedling',
    'B' => 'fa-wine-glass-alt',
    'C' => 'fa-fire-alt',
    'D' => 'fa-moon-stars'
];
$areaIconMap = [
    1 => 'fa-building',
    2 => 'fa-layer-group',
    3 => 'fa-tree',
    4 => 'fa-crown'
];
$zoneClassMap = [
    'A' => 'zone-card--warm',
    'B' => 'zone-card--warm',
    'C' => 'zone-card--cool',
    'D' => 'zone-card--cool'
];
$zoneDisplayNames = [];
foreach ($orderedZones as $zoneKey => $zoneTables) {
    $displayName = '';
    foreach ($zoneTables as $table) {
        if (!empty($table['zone'])) {
            $displayName = (string)$table['zone'];
            break;
        }
    }
    if ($displayName === '') {
        $displayName = 'Zone ' . $zoneKey;
    }
    $zoneDisplayNames[$zoneKey] = $displayName;
}
if ($debugMoBan) {
    error_log('[moBan] Zones available: ' . json_encode($zoneDisplayNames));
}

if (!$forceStep1 && !$editingSelection && $step !== 2 && !empty($flow['booking']['tables'])) {
    $step = 2;
}

$selectedTables = $flow['booking']['tables'] ?? [];
$peopleCount = (int)($flow['booking']['people_count'] ?? 0);
$totalSurcharge = (float)($flow['booking']['total_surcharge'] ?? 0);
$bookingDateTimeDisplay = '';
if (!empty($flow['booking']['datetime'])) {
    $dtDisplay = DateTime::createFromFormat('Y-m-d H:i:s', $flow['booking']['datetime']);
    if ($dtDisplay) {
        $bookingDateTimeDisplay = $dtDisplay->format('d/m/Y H:i');
    }
}

if ($step === 2 && !empty($selectedTables)) {
    define('OPEN_TABLE_FLOW_ACTIVE', true);
}

?>
<style>
    .open-table-container { max-width: 1100px; margin: 0 auto; }
    .flow-card { background: #ffffff; border-radius: 20px; padding: 32px 36px; box-shadow: 0 20px 60px rgba(15, 23, 42, 0.08); border: none; }
    .flow-card h4, .flow-card h5 { font-weight: 700; }
    .step-progress { text-transform: uppercase; letter-spacing: .1em; font-size: .8rem; color: #6c757d; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
    .step-progress .badge-step { background: #ffc107; color: #212529; border-radius: 999px; padding: 6px 12px; font-weight: 700; }
    .table-input { display: none; }
    .table-input:checked + label { background: #ffc107; border-color: #ff9800; color: #0f172a; box-shadow: 0 20px 45px rgba(255, 152, 0, 0.35); }
    .table-input:disabled + label { background: #dc3545; border-color: #dc3545; color: white; opacity: .7; cursor: not-allowed; }
    .table-input:disabled + label .badge { display: none; }
    .table-input:checked + label .capacity, .table-input:checked + label .surcharge { color: #0f172a; }
    .selected-tables { display: none; }
    .selected-tables.active { display: block; }
    .meta-card { background: #f8f9fa; border-radius: 14px; padding: 16px; display: flex; gap: 12px; align-items: center; border: 1px solid rgba(15, 23, 42, 0.05); }
    .meta-card i { width: 42px; height: 42px; border-radius: 12px; background: rgba(255, 193, 7, 0.15); color: #ff9800; display: flex; justify-content: center; align-items: center; font-size: 1.3rem; }
    .booking-meta-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .area-chip-group { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 16px; }
    .area-chip { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 14px 18px; border-radius: 16px; border: 2px solid rgba(226, 232, 240, .9); background: #ffffff; min-width: 180px; cursor: pointer; transition: all .25s ease; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.08); text-align: center; font-weight: 600; color: #475569; }
    .area-chip strong { display: flex; align-items: center; gap: 8px; font-size: 1rem; }
    .area-chip strong i { color: #f59e0b; }
    .area-chip span { font-size: 0.85rem; color: #64748b; }
    .area-chip.active { border-color: #f59e0b; background: rgba(255, 193, 7, 0.18); color: #1f2937; box-shadow: 0 12px 32px rgba(245, 158, 11, 0.35); }
    .zone-filter { display: flex; flex-wrap: wrap; gap: 12px; margin: 16px 0 22px; }
    .zone-filter .filter-chip { border: 1px solid rgba(148, 163, 184, 0.45); background: #f8fafc; border-radius: 30px; padding: 8px 18px; font-weight: 600; font-size: 0.9rem; color: #475569; cursor: pointer; transition: all .2s ease; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05); }
    .zone-filter .filter-chip i { font-size: 1rem; color: #f97316; }
    .zone-filter .filter-chip.active { background: linear-gradient(135deg, #fbbf24, #f59e0b); color: #0f172a; border-color: transparent; box-shadow: 0 12px 28px rgba(245, 158, 11, 0.35); }
    .zone-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; margin-top: 8px; }
    .zone-card { background: #ffffff; border-radius: 18px; padding: 18px 18px 22px; box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08); position: relative; border: 1px solid rgba(15, 23, 42, 0.05); overflow: hidden; }
    .zone-card--warm { border-color: rgba(242, 119, 138, 0.35); background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(255, 235, 239, 0.8)); }
    .zone-card--cool { border-color: rgba(96, 165, 250, 0.35); background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(231, 240, 255, 0.85)); }
    .zone-card--neutral { border-color: rgba(209, 213, 219, 0.6); background: linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(243, 244, 246, 0.85)); }
    .zone-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
    .zone-header h6 { margin: 0; font-weight: 700; display: flex; align-items: left; gap: 8px; }
    .zone-header h6 i { color: #f97316; }
    .zone-meta { display: flex; gap: 12px; font-size: 0.9rem; color: #475569; }
    .zone-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .table-btn { position: relative; display: block; padding: 16px; border-radius: 16px; border: 2px solid rgba(203, 213, 225, 0.9); background: rgba(248, 250, 252, 0.85); transition: all .2s ease; font-weight: 600; color: #334155; cursor: pointer; min-height: 120px; }
    .table-btn .table-number { display: block; font-size: 1.1rem; margin-bottom: 8px; }
    .table-btn .capacity, .table-btn .surcharge { display: block; font-size: 0.9rem; color: #64748b; }
    .table-btn .capacity i, .table-btn .surcharge i { margin-right: 6px; color: #f97316; }
    .table-btn .capacity-icons { margin-top: 10px; display: flex; align-items: center; gap: 4px; color: #f97316; }
    .table-btn .capacity-icons span { font-size: 0.8rem; color: #475569; }
    .table-btn.selected { border-color: #f97316; background: rgba(255, 237, 213, 0.95); color: #1f2937; }
    .summary-pill { display: inline-flex; align-items: center; gap: 8px; padding: 6px 15px; border-radius: 999px; background: rgba(255, 193, 7, 0.2); color: #92400e; font-weight: 600; }
    .summary-pill i { color: #f59e0b; }
    .book-step-actions { display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; }
    .book-step-actions .btn { min-width: 160px; font-weight: 600; }
    .step-summary-card { background: #fff7db; border-radius: 18px; padding: 20px 24px; box-shadow: inset 0 0 0 1px rgba(255, 193, 7, 0.25); display: grid; gap: 16px; }
    .step-summary-card h5 { font-weight: 700; margin-bottom: 6px; }
    .step-summary-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
    .summary-item { background: #ffffff; border-radius: 14px; padding: 16px; display: flex; gap: 12px; align-items: center; border: 1px solid rgba(255, 193, 7, 0.35); box-shadow: 0 10px 25px rgba(255, 193, 7, 0.25); }
    .summary-item i { width: 42px; height: 42px; border-radius: 50%; background: rgba(255, 193, 7, 0.2); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .summary-item span { font-weight: 600; color: #92400e; }
    .summary-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 16px; }
    @media (max-width: 768px) {
        .flow-card { padding: 24px; }
        .zone-grid { grid-template-columns: 1fr; }
        .zone-body { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .book-step-actions { flex-direction: column; align-items: stretch; }
        .summary-actions { flex-direction: column; align-items: stretch; }
    }
    @media (max-width: 576px) {
        .zone-body { grid-template-columns: 1fr; }
    }
</style>

<div class="container py-4">
   

    <?php if ($flashMessage): ?>
        <div class="alert alert-<?php echo htmlspecialchars($flashMessage['type']); ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($flashMessage['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($successMessage): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <div class="open-table-container py-4">
            <div class="d-flex justify-content-end mb-3">
                <a href="index.php?page=table_qr" class="btn btn-outline-primary" target="_blank">
                    <i class="fas fa-qrcode me-2"></i>Xem &amp; in mã QR gọi món
                </a>
            </div>
            <div class="step-progress"><span class="badge-step">Bước 1/2</span> • Chọn khu vực &amp; bàn</div>
            <form method="POST" class="flow-card" id="openTableForm">
                <input type="hidden" name="form_action" id="form_action" value="refresh">

                <div class="text-center mb-4">
                    <div class="d-inline-flex flex-wrap align-items-center gap-2">
                        <label class="fw-semibold text-muted mb-0"><i class="fas fa-chair me-2 text-warning"></i>Khu vực:</label>
                        <select name="khuvuc" class="form-select w-auto shadow-sm rounded-pill border-0 px-3" onchange="document.getElementById('form_action').value='refresh'; this.form.submit();">
                            <option value="">-- Chọn khu vực bàn --</option>
                            <?php foreach ($areas as $area): ?>
                                <?php $areaValue = (string)$area['MaKV']; ?>
                                <option value="<?php echo htmlspecialchars($areaValue); ?>" <?php echo $selectedArea === $areaValue ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area['TenKV']); ?> (phụ thu <?php echo number_format($area['PhuThu']); ?>đ)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!empty($areas)): ?>
                        <div class="area-chip-group" data-area-chip-group>
                            <?php foreach ($areas as $area): ?>
                                <?php
                                    $areaId = (string)$area['MaKV'];
                                    $activeClass = $selectedArea === $areaId ? 'active' : '';
                                    $iconKey = is_numeric($areaId) ? (int)$areaId : $areaId;
                                    $icon = $areaIconMap[$iconKey] ?? 'fa-map-marker-alt';
                                ?>
                                <button type="button" class="area-chip <?php echo $activeClass; ?>" data-area-chip data-area-id="<?php echo htmlspecialchars($areaId); ?>">
                                    <strong><i class="fas <?php echo $icon; ?>"></i><?php echo htmlspecialchars($area['TenKV']); ?></strong>
                                    <span>Phụ thu: <?php echo number_format($area['PhuThu']); ?>đ</span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <h4 class="mt-3">Khu vực hiện tại: <strong class="text-warning"><?php echo htmlspecialchars($currentAreaName); ?></strong></h4>
                    <small class="text-muted d-block mt-1">Mỗi lần mở chỉ một bàn để đồng bộ với giao diện gọi món.</small>
                </div>
                <div class="booking-meta-info mb-4">
                    <div class="meta-card">
                        <i class="fas fa-calendar-day"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Ngày phục vụ</div>
                            <input type="date" name="booking_date" class="form-control border-0 px-0 shadow-none" value="<?php echo htmlspecialchars($selectedDate); ?>" required>
                        </div>
                    </div>
                    <div class="meta-card">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Giờ phục vụ</div>
                            <input type="time" name="booking_time" class="form-control border-0 px-0 shadow-none" value="<?php echo htmlspecialchars($selectedTime); ?>" required>
                        </div>
                    </div>
                    <div class="meta-card">
                        <i class="fas fa-users"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Sức chứa tạm tính</div>
                            <div class="fw-bold text-primary summary-capacity">0</div>
                        </div>
                    </div>
                    <div class="meta-card">
                        <i class="fas fa-money-bill-wave"></i>
                        <div>
                            <div class="text-muted text-uppercase small fw-semibold">Phụ thu dự kiến</div>
                            <div class="fw-bold text-primary summary-surcharge">0đ</div>
                        </div>
                    </div>
                </div>

                <div class="selected-tables mb-4" id="selectionSummary">
                    <h6 class="fw-bold mb-2"><i class="fas fa-clipboard-list me-2"></i>Bàn đã chọn</h6>
                    <div id="summaryList" class="d-flex flex-wrap gap-2"></div>
                    <div class="mt-2 small text-muted">
                        <span>Sức chứa: <span class="summary-capacity fw-semibold text-primary">0</span> khách</span>
                        <span class="ms-3">Phụ thu: <span class="summary-surcharge fw-semibold text-primary">0đ</span></span>
                    </div>
                </div>

                <div class="floor-layout">
                    <?php if ($selectedArea <= 0): ?>
                        <div class="alert alert-info mb-3">Vui lòng chọn khu vực ở phía trên để hiển thị sơ đồ bàn.</div>
                    <?php endif; ?>

                    <?php
                        $renderZoneCard = function ($zoneLabel) use ($orderedZones, $bookedTables, $prefilledTableIds, $areaSurcharge, $zoneIcons, $zoneClassMap, $zoneDisplayNames) {
                            $zoneKey = strtoupper($zoneLabel);
                            $zoneTables = $orderedZones[$zoneKey] ?? [];
                            $zoneClass = $zoneClassMap[$zoneKey] ?? 'zone-card--neutral';
                            $tableCount = count($zoneTables);
                            $displayName = $zoneDisplayNames[$zoneKey] ?? ('Zone ' . $zoneKey);
                            ?>
                            <div class="zone-card <?php echo $zoneClass; ?>" data-zone-card="<?php echo htmlspecialchars($zoneKey); ?>">
                                <div class="zone-header">
                                    <h6><i class="fas <?php echo $zoneIcons[$zoneKey] ?? 'fa-layer-group'; ?>"></i><?php echo htmlspecialchars($displayName); ?></h6>
                                    <div class="zone-meta">
                                        <span><i class="fas fa-chair"></i><?php echo $tableCount; ?> bàn</span>
                                        <span><i class="fas fa-money-bill-wave"></i><?php echo number_format($areaSurcharge); ?>đ</span>
                                    </div>
                                </div>
                                <div class="zone-body">
                                    <?php if (!empty($zoneTables)): ?>
                                        <?php foreach ($zoneTables as $table): ?>
                                            <?php
                                                $tableId = (int)$table['idban'];
                                                $isBooked = in_array($tableId, $bookedTables, true);
                                                $isOccupied = isset($table['TrangThai']) && $table['TrangThai'] === 'occupied';
                                                $isSelected = in_array($tableId, $prefilledTableIds, true);
                                                $capacity = (int)($table['soluongKH'] ?? 0);
                                                $tableName = $table['SoBan'] ?? $tableId;
                                            ?>
                                            <input type="checkbox" class="table-input" id="table-<?php echo $tableId; ?>" name="tables[]" value="<?php echo $tableId; ?>" <?php echo $isSelected ? 'checked' : ''; ?> <?php echo ($isBooked || $isOccupied) ? 'disabled' : ''; ?>>
                                            <label for="table-<?php echo $tableId; ?>" class="table-btn<?php echo $isSelected ? ' selected' : ''; ?>" data-capacity="<?php echo $capacity; ?>" data-phuthu="<?php echo $areaSurcharge; ?>" data-name="<?php echo htmlspecialchars($tableName); ?>">
                                                <span class="table-number">Bàn <?php echo htmlspecialchars($tableName); ?></span>
                                                <span class="capacity"><i class="fas fa-users"></i> <?php echo $capacity; ?> khách</span>
                                                <span class="surcharge"><i class="fas fa-money-bill-wave"></i> <?php echo number_format($areaSurcharge); ?>đ</span>
                                                <div class="capacity-icons">
                                                    <?php for ($i = 0; $i < min($capacity, 5); $i++): ?>
                                                        <i class="fas fa-user"></i>
                                                    <?php endfor; ?>
                                                    <?php if ($capacity > 5): ?>
                                                        <span>+<?php echo $capacity - 5; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($isOccupied): ?>
                                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">Đang phục vụ</span>
                                                <?php elseif ($isBooked): ?>
                                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">Đã đặt</span>
                                                <?php endif; ?>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="zone-empty">Hiện chưa có bàn thuộc khu vực <?php echo htmlspecialchars($displayName); ?>.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php
                        };
                    ?>

                    <?php if (!empty($availableZones)): ?>
                        <div class="zone-filter" data-zone-filter>
                            <button type="button" class="filter-chip active" data-zone="all">
                                <i class="fas fa-border-all"></i>Tất cả
                            </button>
                            <?php foreach ($availableZones as $zoneKey): ?>
                                <?php $filterLabel = $zoneDisplayNames[$zoneKey] ?? ('Zone ' . $zoneKey); ?>
                                <button type="button" class="filter-chip" data-zone="<?php echo htmlspecialchars($zoneKey); ?>">
                                    <i class="fas <?php echo $zoneIcons[$zoneKey] ?? 'fa-layer-group'; ?>"></i>
                                    <?php echo htmlspecialchars($filterLabel); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($orderedZones)): ?>
                        <div class="zone-grid">
                            <?php foreach (array_keys($orderedZones) as $zoneLabel): ?>
                                <?php $renderZoneCard($zoneLabel); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">Hiện chưa có bàn khả dụng trong khu vực này.</div>
                    <?php endif; ?>
                </div>

                <div class="book-step-actions mt-4">
                    <button type="button" class="btn btn-light" data-refresh-layout>
                        <i class="fas fa-sync me-2"></i>Cập nhật sơ đồ
                    </button>
                    <?php $openBtnLabel = $editingSelection ? 'Xác nhận chọn lại' : 'Mở bàn &amp; chọn món'; ?>
                    <button type="submit" class="btn btn-warning" data-open-table>
                        <i class="fas fa-door-open me-2"></i><?php echo $openBtnLabel; ?>
                    </button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="step-summary-card mb-4">
            <h5><i class="fas fa-clipboard-check me-2 text-warning"></i>Tổng quan bàn đã mở</h5>
            <div class="step-summary-list">
                <div class="summary-item">
                    <i class="fas fa-chair"></i>
                    <span>
                        Bàn <?php echo htmlspecialchars($selectedTables[0]['soban'] ?? '—'); ?>
                        <?php if (!empty($selectedTables[0]['zone'])): ?>
                            (Zone <?php echo htmlspecialchars($selectedTables[0]['zone']); ?>)
                        <?php endif; ?>
                    </span>
                </div>
                <div class="summary-item">
                    <i class="fas fa-layer-group"></i>
                    <span>Khu vực: <?php echo htmlspecialchars($flow['area']['name'] ?? $currentAreaName); ?></span>
                </div>
                <div class="summary-item">
                    <i class="fas fa-users"></i>
                    <span>Sức chứa tạm tính: <?php echo $peopleCount; ?> khách</span>
                </div>
                <div class="summary-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Thời điểm: <?php echo htmlspecialchars($bookingDateTimeDisplay ?: '—'); ?></span>
                </div>
                <?php if ($sourceBookingId): ?>
                    <div class="summary-item">
                        <i class="fas fa-bookmark"></i>
                        <span>
                            Đặt bàn #<?php echo $sourceBookingId; ?>
                            <a href="index.php?page=chitietdondatban&madatban=<?php echo $sourceBookingId; ?>" class="ms-1">Xem chi tiết</a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="summary-actions">
                <a href="index.php?page=moBan&change_table=1" class="btn btn-outline-secondary"><i class="fas fa-chair me-2"></i>Chọn lại bàn</a>
                <a href="index.php?page=moBan&reset=1" class="btn btn-light"><i class="fas fa-ban me-2"></i>Hủy mở bàn</a>
            </div>
        </div>

        <?php include __DIR__ . '/themDH.php'; ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('openTableForm');
        if (!form) {
            return;
        }

        const actionInput = document.getElementById('form_action');
        const areaChipGroup = document.querySelector('[data-area-chip-group]');
        const zoneFilter = document.querySelector('[data-zone-filter]');
        const tableInputs = Array.from(document.querySelectorAll('.table-input'));
        const summaryBox = document.getElementById('selectionSummary');
        const summaryList = document.getElementById('summaryList');
        const capacityNodes = document.querySelectorAll('.summary-capacity');
        const surchargeNodes = document.querySelectorAll('.summary-surcharge');
        const openButton = document.querySelector('[data-open-table]');
        const refreshButton = document.querySelector('[data-refresh-layout]');

        const refreshSummary = () => {
            if (!summaryBox || !summaryList) {
                return;
            }
            summaryList.innerHTML = '';
            let totalCapacity = 0;
            let totalSurcharge = 0;

            tableInputs.forEach((input) => {
                const label = input.nextElementSibling;
                if (!label) {
                    return;
                }

                if (input.checked) {
                    label.classList.add('selected');
                    const name = label.dataset.name || input.value;
                    const capacity = parseInt(label.dataset.capacity || '0', 10) || 0;
                    const surcharge = parseFloat(label.dataset.phuthu || '0') || 0;
                    totalCapacity += capacity;
                    totalSurcharge += surcharge;

                    const pill = document.createElement('span');
                    pill.className = 'summary-pill';
                    pill.innerHTML = '<i class="fas fa-chair"></i>Bàn ' + name;
                    summaryList.appendChild(pill);
                } else {
                    label.classList.remove('selected');
                }
            });

            capacityNodes.forEach((node) => node.textContent = totalCapacity);
            surchargeNodes.forEach((node) => node.textContent = totalSurcharge.toLocaleString('vi-VN') + 'đ');
            summaryBox.classList.toggle('active', summaryList.childElementCount > 0);
            summaryBox.classList.toggle('d-none', summaryList.childElementCount === 0);
        };

        tableInputs.forEach((input) => {
            input.addEventListener('change', refreshSummary);
        });

        refreshSummary();

        if (areaChipGroup) {
            const areaChips = areaChipGroup.querySelectorAll('[data-area-chip]');
            const areaSelect = form.querySelector('select[name="khuvuc"]');

            const activateChip = (targetId) => {
                areaChips.forEach((chip) => {
                    const chipId = chip.dataset.areaId ?? '';
                    chip.classList.toggle('active', chipId === targetId);
                });
            };

            areaChips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    const areaId = chip.dataset.areaId || '';
                    if (areaSelect) {
                        areaSelect.value = areaId;
                    }
                    activateChip(areaId);
                    if (actionInput) {
                        actionInput.value = 'refresh';
                    }
                    form.submit();
                });
            });
        }

        if (zoneFilter) {
            const filterChips = zoneFilter.querySelectorAll('.filter-chip');
            const zoneCards = document.querySelectorAll('[data-zone-card]');

            const applyZoneFilter = (targetZoneRaw) => {
                const targetZone = (targetZoneRaw || 'all').toString().toUpperCase();
                zoneCards.forEach((card) => {
                    const cardZone = (card.dataset.zoneCard || '').toUpperCase();
                    const visible = targetZone === 'ALL' || cardZone === targetZone;
                    card.classList.toggle('d-none', !visible);
                });
            };

            filterChips.forEach((chip) => {
                chip.addEventListener('click', () => {
                    const targetZone = chip.dataset.zone;
                    filterChips.forEach((c) => c.classList.remove('active'));
                    chip.classList.add('active');
                    applyZoneFilter(targetZone);
                });
            });

            applyZoneFilter('all');
        }

        if (openButton) {
            openButton.addEventListener('click', () => {
                if (actionInput) {
                    actionInput.value = 'open_table';
                }
                const hasSelection = tableInputs.some((input) => input.checked);
                if (!hasSelection) {
                    alert('Vui lòng chọn một bàn trước khi mở.');
                }
            });
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', () => {
                if (actionInput) {
                    actionInput.value = 'refresh';
                }
                form.submit();
            });
        }
    });
</script>







