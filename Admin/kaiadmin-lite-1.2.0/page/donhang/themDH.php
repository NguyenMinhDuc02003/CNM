<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('admin_redirect')) {
    function admin_redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }
    }
require_once __DIR__ . '/../../class/clsmonan.php';
require_once __DIR__ . '/../../class/clsdanhmuc.php';
require_once __DIR__ . '/../../class/clsDonHang.php';

$db = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db
    ? $GLOBALS['admin_db']
    : new connect_db();

$isAjaxRequest = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === '1');
if ($isAjaxRequest) {
    ob_start(); // tránh xuất HTML trước khi trả JSON
}

$orderService = new clsDonHang();
$walkInCustomerId = $orderService->ensureWalkInCustomer();
$menuModel = new clsMonAn();
$categoryModel = new clsDanhMuc();

$staffId = isset($_SESSION['nhanvien_id']) ? (int)$_SESSION['nhanvien_id'] : null;
$flow = isset($_SESSION['open_table_flow']) && is_array($_SESSION['open_table_flow'])
    ? $_SESSION['open_table_flow']
    : null;
$sourceBookingId = isset($flow['booking']['source_booking_id']) ? (int)$flow['booking']['source_booking_id'] : null;

if ($flow === null || empty($flow['booking']['tables'])) {
    echo '<div class="container py-5"><div class="alert alert-warning">Vui lòng quay lại bước mở bàn trước khi thêm món. <a href="index.php?page=moBan" class="alert-link">Quay lại sơ đồ bàn</a></div></div>';
    return;
}

$selectedTables = $flow['booking']['tables'];
$primaryTable = $selectedTables[0] ?? null;
$primaryTableId = isset($primaryTable['idban']) ? (int)$primaryTable['idban'] : null;

if (!$primaryTableId) {
    echo '<div class="container py-5"><div class="alert alert-danger">Không xác định được bàn phục vụ. <a href="index.php?page=moBan" class="alert-link">Chọn lại bàn</a></div></div>';
    return;
}

$tableLabel = $flow['booking']['table_label'] ?? ($primaryTable['soban'] ?? ('#' . $primaryTableId));
$areaName = $flow['area']['name'] ?? '';
$peopleCount = isset($flow['booking']['people_count']) ? (int)$flow['booking']['people_count'] : 0;
$bookingDateTime = $flow['booking']['datetime'] ?? null;
$surchargeEstimate = isset($flow['booking']['total_surcharge']) ? (float)$flow['booking']['total_surcharge'] : 0.0;

try {
    $orderId = isset($flow['order_id']) ? (int)$flow['order_id'] : null;
    if ($orderId) {
        $existingOrder = $orderService->getOrderById($orderId);
        if (!$existingOrder || (int)$existingOrder['idban'] !== $primaryTableId) {
            $orderId = null;
        }
    }

    if (!$orderId) {
        $orderId = $orderService->openOrderForTable($primaryTableId, $flow, $staffId);
        $_SESSION['open_table_flow']['order_id'] = $orderId;
    }
} catch (Throwable $th) {
    echo '<div class="container py-5"><div class="alert alert-danger">Không thể mở đơn hàng cho bàn đã chọn: ' .
        htmlspecialchars($th->getMessage()) .
        '</div></div>';
    return;
}

$successMessages = [];
$errorMessages = [];
$inventorySummaries = [];
$shouldClearFlowAfterRender = false;
$customerSearchPhoneInput = '';
$linkedCustomer = null;

$loadCustomerForOrder = static function (?array $orderRow) use ($db) {
    if (!$orderRow || empty($orderRow['idKH'])) {
        return null;
    }
    $customerRows = $db->xuatdulieu_prepared(
        "SELECT idKH, tenKH, sodienthoai, email FROM khachhang WHERE idKH = ? LIMIT 1",
        [(int)$orderRow['idKH']]
    );
    return !empty($customerRows) ? $customerRows[0] : null;
};

$order = $orderService->getOrderById($orderId);
$bookingReferenceId = isset($order['madatban']) ? (int)$order['madatban'] : ($sourceBookingId ?? null);
$linkedCustomer = $loadCustomerForOrder($order);
$isRealCustomerLinked = $linkedCustomer && (int)$linkedCustomer['idKH'] !== $walkInCustomerId;
if ($isRealCustomerLinked) {
    $customerSearchPhoneInput = $linkedCustomer['sodienthoai'] ?? '';
} else {
    $linkedCustomer = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['order_action']) ? trim((string)$_POST['order_action']) : '';
    if ($order === null) {
        $order = $orderService->getOrderById($orderId);
        $linkedCustomer = $loadCustomerForOrder($order);
        $isRealCustomerLinked = $linkedCustomer && (int)$linkedCustomer['idKH'] !== $walkInCustomerId;
        if ($isRealCustomerLinked && $customerSearchPhoneInput === '') {
            $customerSearchPhoneInput = $linkedCustomer['sodienthoai'] ?? '';
        }
        if (!$isRealCustomerLinked) {
            $linkedCustomer = null;
        }
    }

    try {
        switch ($action) {
            case 'send_to_kitchen':
                $payloadRaw = $_POST['order_payload'] ?? '[]';
                $payload = json_decode($payloadRaw, true);
                if (!is_array($payload)) {
                    throw new InvalidArgumentException('Dữ liệu món ăn không hợp lệ.');
                }

                // Validate that none of the requested dishes have been locked/inactive
                $ids = [];
                foreach ($payload as $p) {
                    if (isset($p['idmonan']) && $p['idmonan'] !== null) {
                        $ids[] = (int)$p['idmonan'];
                    }
                }
                $ids = array_values(array_unique(array_filter($ids)));

                $lockedNames = [];
                if (!empty($ids)) {
                    // build placeholders
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $rows = $db->xuatdulieu_prepared(
                        "SELECT idmonan, tenmonan, TrangThai, hoatdong FROM monan WHERE idmonan IN ($placeholders)",
                        $ids
                    );

                    $rowsById = [];
                    foreach ($rows as $r) {
                        $rowsById[(int)$r['idmonan']] = $r;
                    }

                    foreach ($ids as $mid) {
                        if (!isset($rowsById[$mid])) {
                            // missing dish treated as invalid/locked
                            $lockedNames[] = '#'.$mid;
                            continue;
                        }
                        $r = $rowsById[$mid];
                        $rStatus = isset($r['TrangThai']) ? $r['TrangThai'] : ($r['trangthai'] ?? '');
                        $rActive = isset($r['hoatdong']) ? $r['hoatdong'] : ($r['hoatdong'] ?? '');
                        if ($rStatus !== 'approved' || $rActive !== 'active') {
                            $lockedNames[] = $r['tenmonan'] ?: ('#' . $mid);
                        }
                    }
                }

                    // Also validate menu sets included in payload (metadata.menu_set.id)
                    $menuSetIds = [];
                    foreach ($payload as $p) {
                        $meta = null;
                        if (isset($p['metadata']) && $p['metadata'] !== null && $p['metadata'] !== '') {
                            if (is_string($p['metadata'])) {
                                $meta = json_decode($p['metadata'], true);
                            } elseif (is_array($p['metadata'])) {
                                $meta = $p['metadata'];
                            }
                        }
                        if (is_array($meta) && isset($meta['menu_set']) && isset($meta['menu_set']['id'])) {
                            $menuSetIds[] = (int)$meta['menu_set']['id'];
                        }
                    }
                    $menuSetIds = array_values(array_unique(array_filter($menuSetIds)));

                    $lockedSets = [];
                    if (!empty($menuSetIds)) {
                        $phSets = implode(',', array_fill(0, count($menuSetIds), '?'));
                        // load thucdon basic info
                        $sets = $db->xuatdulieu_prepared("SELECT idthucdon, tenthucdon, trangthai, hoatdong FROM thucdon WHERE idthucdon IN ($phSets)", $menuSetIds);
                        $setsById = [];
                        foreach ($sets as $s) {
                            $setsById[(int)$s['idthucdon']] = $s;
                        }

                        // load dishes for these sets
                        $rows = $db->xuatdulieu_prepared(
                            "SELECT ctd.idthucdon, m.idmonan, m.tenmonan, m.TrangThai AS m_trangthai, m.hoatdong AS m_hoatdong
                             FROM chitietthucdon ctd
                             JOIN monan m ON ctd.idmonan = m.idmonan
                             WHERE ctd.idthucdon IN ($phSets)",
                            $menuSetIds
                        );

                        $dishesBySet = [];
                        foreach ($rows as $r) {
                            $sid = (int)$r['idthucdon'];
                            $dishesBySet[$sid][] = $r;
                        }

                        foreach ($menuSetIds as $sid) {
                            if (!isset($setsById[$sid])) {
                                $lockedSets[$sid] = ['name' => '#'.$sid, 'reason' => 'Thực đơn không tồn tại', 'bad_dishes' => []];
                                continue;
                            }
                            $s = $setsById[$sid];
                            $sStatus = isset($s['trangthai']) ? $s['trangthai'] : ($s['TrangThai'] ?? '');
                            $sActive = isset($s['hoatdong']) ? $s['hoatdong'] : ($s['hoatdong'] ?? '');
                            if ($sStatus !== 'approved' || $sActive !== 'active') {
                                $lockedSets[$sid] = ['name' => $s['tenthucdon'] ?: ('#'.$sid), 'reason' => 'Thực đơn bị khóa', 'bad_dishes' => []];
                                continue;
                            }

                            $bad = [];
                            $setDishes = $dishesBySet[$sid] ?? [];
                            foreach ($setDishes as $d) {
                                $dStatus = isset($d['m_trangthai']) ? $d['m_trangthai'] : ($d['TrangThai'] ?? '');
                                $dActive = isset($d['m_hoatdong']) ? $d['m_hoatdong'] : ($d['hoatdong'] ?? '');
                                if ($dStatus !== 'approved' || $dActive !== 'active') {
                                    $bad[] = $d['tenmonan'] ?: ('#' . (int)$d['idmonan']);
                                }
                            }
                            if (!empty($bad)) {
                                $lockedSets[$sid] = ['name' => $s['tenthucdon'] ?: ('#'.$sid), 'reason' => 'Chứa món bị khóa', 'bad_dishes' => $bad];
                            }
                        }
                    }

                    // Combine locked individual dishes and locked sets into a single error
                    $messages = [];
                    if (!empty($lockedNames)) {
                        $messages[] = 'món: ' . implode(', ', $lockedNames);
                    }
                    if (!empty($lockedSets)) {
                        $parts = [];
                        foreach ($lockedSets as $sid => $info) {
                            if (!empty($info['bad_dishes'])) {
                                $parts[] = $info['name'] . ' (món: ' . implode(', ', $info['bad_dishes']) . ')';
                            } else {
                                $parts[] = $info['name'] . ' (' . $info['reason'] . ')';
                            }
                        }
                        $messages[] = 'thực đơn: ' . implode('; ', $parts);
                    }

                    if (!empty($messages)) {
                        $errorMessages[] = 'Không thể gửi xuống bếp — các mục sau đã bị khóa: ' . implode(' | ', $messages) . '. Vui lòng chọn thực đơn  khác.';
                        break;
                    }

                $result = $orderService->addItemsToOrder($orderId, $payload, $staffId);
                $insertedCount = count($result['inserted']);
                if ($insertedCount > 0) {
                    $successMessages[] = 'Đã gửi ' . $insertedCount . ' món xuống bếp thành công.';
                }
                if (!empty($result['ignored'])) {
                    $errorMessages[] = 'Một số món không hợp lệ hoặc thiếu giá, vui lòng kiểm tra lại.';
                }
                break;

            case 'update_item_status':
                $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
                $targetStatus = isset($_POST['target_status']) ? trim((string)$_POST['target_status']) : '';
                if ($itemId <= 0) {
                    throw new InvalidArgumentException('Thiếu mã món cần cập nhật.');
                }
                $orderService->updateItemStatus($itemId, $targetStatus, $staffId, 'front');
                $successMessages[] = 'Cập nhật trạng thái món thành công.';
                break;

            case 'lookup_customer':
                $customerSearchPhoneInput = trim((string)($_POST['customer_phone'] ?? ''));
                if ($customerSearchPhoneInput === '') {
                    $errorMessages[] = 'Vui lòng nhập số điện thoại khách hàng trước khi tra cứu.';
                    break;
                }
                $customerRows = $db->xuatdulieu_prepared(
                    "SELECT idKH, tenKH, sodienthoai, email FROM khachhang WHERE sodienthoai = ? LIMIT 1",
                    [$customerSearchPhoneInput]
                );
                if (empty($customerRows)) {
                    $errorMessages[] = 'Không tìm thấy khách hàng với số điện thoại ' . $customerSearchPhoneInput . '. Vui lòng thêm khách trong mục Khách hàng.';
                    break;
                }
                $customer = $customerRows[0];
                $db->tuychinh(
                    "UPDATE donhang SET idKH = ? WHERE idDH = ?",
                    [(int)$customer['idKH'], $orderId]
                );
                $order = $orderService->getOrderById($orderId);
                $linkedCustomer = $customer;
                $isRealCustomerLinked = true;
                $customerSearchPhoneInput = $customer['sodienthoai'] ?? $customerSearchPhoneInput;
                $successMessages[] = 'Đã gắn đơn hàng với khách hàng ' . ($customer['tenKH'] ?? ('#' . $customer['idKH']));
                break;

            case 'complete_order':
                $currentCustomerId = isset($order['idKH']) ? (int)$order['idKH'] : 0;
                if ($currentCustomerId === 0 || $currentCustomerId === $walkInCustomerId) {
                    throw new RuntimeException('Vui lòng tra cứu và gắn khách hàng bằng số điện thoại trước khi thanh toán.');
                }
                $latestTotals = $orderService->computeOrderTotals($orderId);
                $bookingRefForDeposit = isset($order['madatban']) ? (int)$order['madatban'] : ($sourceBookingId ?? null);
                $depositSummaryForCompletion = $orderService->getBookingDepositSummary($bookingRefForDeposit ?: null, $latestTotals['total']);
                $amountDueNow = max(0, round($latestTotals['total'] - $depositSummaryForCompletion['paid'], 2));
                $paymentMethod = isset($_POST['payment_method']) ? trim((string)$_POST['payment_method']) : 'cash';
                if ($paymentMethod === 'transfer' && $amountDueNow > 0) {
                    $_SESSION['pending_transfer_order'] = [
                        'order_id' => $orderId,
                        'staff_id' => $staffId
                    ];
                    admin_redirect('index.php?page=order_payment&order_id=' . $orderId);
                }

                $completion = $orderService->completeOrder($orderId, $paymentMethod, $staffId);
                $inventorySummaries = $completion['inventory'] ?? [];
                unset($_SESSION['open_table_flow']);
                $_SESSION['admin_flash'] = [
                    'type' => 'success',
                    'message' => 'Đã thanh toán và hoàn tất đơn hàng.'
                ];
                admin_redirect('page/hoadon/xuatHD.php?idDH=' . $orderId . '&auto_print=1');

            case 'remove_order_item':
                $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
                if ($itemId <= 0) {
                    throw new InvalidArgumentException('Thiếu mã món cần xóa.');
                }
                $orderService->deleteOrderItem($itemId, $staffId);
                $successMessages[] = 'Đã xóa món khỏi đơn.';
                break;

            default:
                if (!empty($action)) {
                    $errorMessages[] = 'Hành động không được hỗ trợ.';
                }
        }
    } catch (Throwable $th) {
        $errorMessages[] = $th->getMessage();
    }
}

$order = $orderService->getOrderById($orderId);
$linkedCustomer = $loadCustomerForOrder($order);
$isRealCustomerLinked = $linkedCustomer && (int)$linkedCustomer['idKH'] !== $walkInCustomerId;
if ($isRealCustomerLinked) {
    $customerSearchPhoneInput = $linkedCustomer['sodienthoai'] ?? $customerSearchPhoneInput;
} else {
    $linkedCustomer = null;
}
$orderItems = $orderService->getOrderItems($orderId);
$isOrderCompleted = isset($order['TrangThai']) && $order['TrangThai'] === clsDonHang::ORDER_STATUS_DONE;

$preloadedDraftItems = [];
if (
    !$isOrderCompleted
    && empty($orderItems)
    && !empty($flow)
    && !empty($flow['menu']) && is_array($flow['menu'])
) {
    $menuDataForDraft = $flow['menu'];
    $menuModeForDraft = $menuDataForDraft['mode'] ?? 'none';

    if ($menuModeForDraft === 'set' && !empty($menuDataForDraft['set']) && is_array($menuDataForDraft['set'])) {
        $setData = $menuDataForDraft['set'];
        $setId = isset($setData['idthucdon']) ? (int)$setData['idthucdon'] : (isset($setData['id']) ? (int)$setData['id'] : null);
        $setName = $setData['tenthucdon'] ?? ($setData['name'] ?? 'Thực đơn đặt trước');
        $setPrice = null;
        if (isset($setData['tongtien']) && is_numeric($setData['tongtien'])) {
            $setPrice = (float)$setData['tongtien'];
        } elseif (isset($setData['price']) && is_numeric($setData['price'])) {
            $setPrice = (float)$setData['price'];
        } elseif (!empty($setData['monan']) && is_array($setData['monan'])) {
            $tmpTotal = 0;
            foreach ($setData['monan'] as $dish) {
                $dPrice = isset($dish['DonGia']) ? (float)$dish['DonGia'] : (isset($dish['price']) ? (float)$dish['price'] : 0);
                $dQty = isset($dish['soluong']) ? (int)$dish['soluong'] : (isset($dish['SoLuong']) ? (int)$dish['SoLuong'] : 0);
                $tmpTotal += $dPrice * max(1, $dQty);
            }
            $setPrice = $tmpTotal;
        }
        $preloadedDraftItems[] = [
            'type' => 'menu_set',
            'menuSetId' => $setId,
            'name' => $setName,
            'price' => $setPrice,
            'quantity' => 1,
        ];
    } elseif (!empty($menuDataForDraft['items']) && is_array($menuDataForDraft['items'])) {
        foreach ($menuDataForDraft['items'] as $dish) {
            $dishId = isset($dish['idmonan']) ? (int)$dish['idmonan'] : (isset($dish['id']) ? (int)$dish['id'] : 0);
            $qty = isset($dish['soluong']) ? (int)$dish['soluong'] : (isset($dish['SoLuong']) ? (int)$dish['SoLuong'] : 0);
            $price = isset($dish['DonGia']) ? (float)$dish['DonGia'] : (isset($dish['price']) ? (float)$dish['price'] : null);
            if ($dishId <= 0 || $qty <= 0) {
                continue;
            }
            $preloadedDraftItems[] = [
                'type' => 'single',
                'dishId' => $dishId,
                'name' => $dish['tenmonan'] ?? ($dish['name'] ?? ''),
                'price' => $price,
                'quantity' => $qty,
            ];
        }
    }
}

$totals = $orderService->computeOrderTotals($orderId);
$bookingDeposit = $orderService->getBookingDepositSummary($bookingReferenceId ?: null, $totals['total']);
$amountDueAfterDeposit = max(0, round($totals['total'] - ($bookingDeposit['paid'] ?? 0), 2));

$menuSetGroups = [];
$menuSetGroupOrder = [];
$standaloneOrderItems = [];

if (!empty($orderItems)) {
    foreach ($orderItems as $item) {
        $metadataRaw = $item['metadata'] ?? null;
        $metadata = [];
        if (is_string($metadataRaw) && $metadataRaw !== '') {
            $decoded = json_decode($metadataRaw, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $menuSetMeta = isset($metadata['menu_set']) && is_array($metadata['menu_set'])
            ? $metadata['menu_set']
            : null;
        $isVirtualMenuSet = $menuSetMeta && !empty($menuSetMeta['virtual_item']);

        if ($menuSetMeta && !$isVirtualMenuSet && !empty($menuSetMeta['token'])) {
            $token = (string)$menuSetMeta['token'];
            if (!isset($menuSetGroups[$token])) {
                $menuSetGroups[$token] = [
                    'token' => $token,
                    'menu_set_id' => isset($menuSetMeta['id']) ? (int)$menuSetMeta['id'] : 0,
                    'name' => $menuSetMeta['name'] ?? ('Thực đơn #' . ($menuSetMeta['id'] ?? '')),
                    'quantity' => isset($menuSetMeta['quantity']) ? max(1, (int)$menuSetMeta['quantity']) : 1,
                    'items' => [],
                    'total_price' => 0.0,
                    'note' => null,
                    'status' => null,
                    'collapse_id' => 'menuSetItems-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $token),
                ];
                $menuSetGroupOrder[] = $token;
            }

            if (!empty($metadata['set_note']) && empty($menuSetGroups[$token]['note'])) {
                $menuSetGroups[$token]['note'] = (string)$metadata['set_note'];
            } elseif (!empty($item['GhiChu']) && empty($menuSetGroups[$token]['note'])) {
                $menuSetGroups[$token]['note'] = $item['GhiChu'];
            }

            $menuSetGroups[$token]['items'][] = $item;
            $menuSetGroups[$token]['total_price'] += (float)$item['DonGia'] * (int)$item['SoLuong'];
            continue;
        }

        $item['_metadata'] = $metadata;
        $item['_menu_set_meta'] = $menuSetMeta;
        $item['_is_virtual_menu_set'] = $isVirtualMenuSet;
        $standaloneOrderItems[] = $item;
    }
}

$resolveMenuSetStatus = static function (array $components): string {
    if (empty($components)) {
        return clsDonHang::ITEM_STATUS_PREPARING;
    }

    $statuses = array_map(static function ($item) {
        return $item['TrangThai'] ?? clsDonHang::ITEM_STATUS_PREPARING;
    }, $components);

    $activeStatuses = array_values(array_filter($statuses, static function ($status) {
        return $status !== clsDonHang::ITEM_STATUS_CANCELLED;
    }));

    if (empty($activeStatuses)) {
        return clsDonHang::ITEM_STATUS_CANCELLED;
    }

    $uniqueActive = array_values(array_unique($activeStatuses));
    if (count($uniqueActive) === 1 && $uniqueActive[0] === clsDonHang::ITEM_STATUS_SERVED) {
        return clsDonHang::ITEM_STATUS_SERVED;
    }

    if (!in_array(clsDonHang::ITEM_STATUS_PREPARING, $activeStatuses, true)) {
        return clsDonHang::ITEM_STATUS_READY;
    }

    return clsDonHang::ITEM_STATUS_PREPARING;
};

foreach ($menuSetGroupOrder as $token) {
    $menuSetGroups[$token]['status'] = $resolveMenuSetStatus($menuSetGroups[$token]['items']);
}
if ($shouldClearFlowAfterRender) {
    unset($_SESSION['open_table_flow']);
}

$menuCategories = $categoryModel->getAllDanhMuc();
$menuItemsRaw = $menuModel->getAllMonAn();

$menuItems = [];
foreach ($menuItemsRaw as $item) {
    $itemStatus = isset($item['TrangThai']) ? $item['TrangThai'] : ($item['trangthai'] ?? '');
    $itemActive = isset($item['hoatdong']) ? $item['hoatdong'] : ($item['hoatdong'] ?? '');
    if ($itemStatus !== 'approved' || $itemActive !== 'active') {
        continue;
    }

    $image = isset($item['hinhanh']) && $item['hinhanh'] !== ''
        ? 'assets/img/' . $item['hinhanh']
        : 'assets/img/bg.jpg';

    if (!file_exists(__DIR__ . '/../../' . $image)) {
        $image = 'assets/img/bg.jpg';
    }

    $menuItems[] = [
        'id' => (int)$item['idmonan'],
        'name' => $item['tenmonan'],
        'price' => (float)$item['DonGia'],
        'category' => (int)$item['iddm'],
        'unit' => $item['DonViTinh'],
        'description' => $item['mota'],
        'image' => $image
    ];
}

$menuSets = [];
try {
    // Only load thucdon (menu sets) that are approved and active.
    // Also include dish status fields so we can skip inactive dishes inside sets.
    $menuSetRows = $db->xuatdulieu(
        "SELECT td.idthucdon, td.tenthucdon, td.mota, td.tongtien, td.min_khach, td.max_khach, td.hinhanh,
                ctd.idmonan, m.tenmonan, m.DonGia, m.TrangThai AS m_trangthai, m.hoatdong AS m_hoatdong
         FROM thucdon td
         LEFT JOIN chitietthucdon ctd ON td.idthucdon = ctd.idthucdon
         LEFT JOIN monan m ON ctd.idmonan = m.idmonan
         WHERE td.trangthai = 'approved' AND td.hoatdong = 'active'
         ORDER BY td.idthucdon ASC, m.tenmonan ASC"
    );
} catch (Throwable $th) {
    error_log('[themDH] Failed to load menu sets: ' . $th->getMessage());
    $menuSetRows = [];
}

foreach ($menuSetRows as $row) {
    $setId = isset($row['idthucdon']) ? (int)$row['idthucdon'] : 0;
    if ($setId <= 0) {
        continue;
    }
    if (!isset($menuSets[$setId])) {
        $imageName = $row['hinhanh'] ?? '';
        $imagePath = $imageName !== '' ? 'assets/img/' . ltrim($imageName, '/') : 'assets/img/bg.jpg';
        if (!file_exists(__DIR__ . '/../../' . $imagePath)) {
            $imagePath = 'assets/img/bg.jpg';
        }
        $menuSets[$setId] = [
            'id' => $setId,
            'name' => $row['tenthucdon'] ?? ('Thực đơn #' . $setId),
            'description' => $row['mota'] ?? '',
            'price' => isset($row['tongtien']) ? (float)$row['tongtien'] : 0.0,
            'min_guests' => isset($row['min_khach']) ? (int)$row['min_khach'] : 0,
            'max_guests' => isset($row['max_khach']) ? (int)$row['max_khach'] : 0,
            'image' => $imagePath,
            'dishes' => []
        ];
    }
    $dishId = isset($row['idmonan']) ? (int)$row['idmonan'] : 0;
    // Only include dishes that exist and are approved + active.
    $dishApproved = isset($row['m_trangthai']) ? $row['m_trangthai'] === 'approved' : false;
    $dishActive = isset($row['m_hoatdong']) ? $row['m_hoatdong'] === 'active' : false;
    if ($dishId > 0 && $dishApproved && $dishActive && !isset($menuSets[$setId]['dishes'][$dishId])) {
        $menuSets[$setId]['dishes'][$dishId] = [
            'id' => $dishId,
            'name' => $row['tenmonan'] ?? ('Món #' . $dishId),
            'price' => isset($row['DonGia']) ? (float)$row['DonGia'] : 0.0
        ];
    }
}

foreach ($menuSets as &$setRef) {
    $setRef['dishes'] = isset($setRef['dishes']) ? array_values($setRef['dishes']) : [];
}
unset($setRef);
$menuSets = array_values($menuSets);

$statusLabels = [
    clsDonHang::ITEM_STATUS_PREPARING => 'Đang chế biến',
    clsDonHang::ITEM_STATUS_READY => 'Sẵn sàng',
    clsDonHang::ITEM_STATUS_SERVED => 'Đã phục vụ',
    clsDonHang::ITEM_STATUS_CANCELLED => 'Đã hủy'
];

$statusBadges = [
    clsDonHang::ITEM_STATUS_PREPARING => 'bg-warning text-dark',
    clsDonHang::ITEM_STATUS_READY => 'bg-info text-dark',
    clsDonHang::ITEM_STATUS_SERVED => 'bg-success',
    clsDonHang::ITEM_STATUS_CANCELLED => 'bg-secondary'
];

$orderStatusLabels = [
    clsDonHang::ORDER_STATUS_OPEN => 'Đang phục vụ',
    clsDonHang::ORDER_STATUS_PENDING_PAYMENT => 'Chờ thanh toán',
    clsDonHang::ORDER_STATUS_DONE => 'Hoàn thành',
    clsDonHang::ORDER_STATUS_CANCELLED => 'Đã hủy'
];

$orderedMenuSetGroups = [];
foreach ($menuSetGroupOrder as $token) {
    $group = $menuSetGroups[$token];
    $group['badge_class'] = $statusBadges[$group['status']] ?? 'bg-secondary';
    $group['status_label'] = $statusLabels[$group['status']] ?? ucfirst($group['status']);
    $orderedMenuSetGroups[] = $group;
}
$menuSetGroups = $orderedMenuSetGroups;
$displayedServingRowCount = count($standaloneOrderItems) + count($menuSetGroups);

$orderStatusBadge = 'bg-primary';
if ($isOrderCompleted) {
    $orderStatusBadge = 'bg-success';
} elseif (isset($order['TrangThai']) && $order['TrangThai'] === clsDonHang::ORDER_STATUS_PENDING_PAYMENT) {
    $orderStatusBadge = 'bg-warning text-dark';
}

?>
<style>
@media (max-width: 768px) {
    .serving-action-row {
        touch-action: pan-y;
        transition: background-color 0.2s ease;
    }
    .serving-action-row .btn-delete-serving {
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.2s ease;
    }
    .serving-action-row.swipe-revealed {
        background-color: rgba(255,193,7,0.08);
    }
    .serving-action-row.swipe-revealed .btn-delete-serving {
        opacity: 1;
        pointer-events: auto;
    }
}
@media (min-width: 769px) {
    .serving-action-row .btn-delete-serving {
        opacity: 1;
        pointer-events: auto;
    }
}
</style>
<div class="container py-4">
    <?php foreach ($successMessages as $message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <?php foreach ($errorMessages as $message): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>

    <div id="ajaxMessages"></div>

    <?php if ($sourceBookingId): ?>
        <div class="alert alert-info">
            <i class="fas fa-bookmark me-2"></i>
            Đơn này được mở từ đặt bàn <strong>#<?php echo $sourceBookingId; ?></strong>.
            <a href="index.php?page=chitietdondatban&madatban=<?php echo $sourceBookingId; ?>" class="alert-link ms-1">Xem chi tiết đặt bàn</a>.
        </div>
    <?php endif; ?>

    <?php if (!empty($inventorySummaries)): ?>
        <?php foreach ($inventorySummaries as $item): ?>
            <?php
                $shortage = isset($item['shortage']) ? (float)$item['shortage'] : 0;
                $consumed = isset($item['consumed']) ? (float)$item['consumed'] : 0;
                $required = isset($item['required']) ? (float)$item['required'] : 0;
                $ingredientName = $item['name'] ?? ('Nguyên liệu #' . $item['matonkho']);
                $unit = $item['unit'] ?? '';
            ?>
            <?php if ($shortage > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-warehouse me-2"></i>
                    Nguyên liệu <strong><?php echo htmlspecialchars($ingredientName); ?></strong> thiếu
                    <?php echo number_format($shortage, 2); ?> <?php echo htmlspecialchars($unit); ?> (đã cần
                    <?php echo number_format($required, 2); ?>, trừ được
                    <?php echo number_format($consumed, 2); ?>).
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-warehouse me-2"></i>
                    Đã trừ kho <strong><?php echo htmlspecialchars($ingredientName); ?></strong>:
                    <?php echo number_format($consumed, 2); ?> <?php echo htmlspecialchars($unit); ?>.
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body d-flex flex-wrap gap-4 align-items-center justify-content-between">
            <div>
                <h4 class="mb-1">
                    <i class="fas fa-receipt text-warning me-2"></i>
                    Đơn hàng bàn <?php echo htmlspecialchars($tableLabel); ?>
                </h4>
                <div class="text-muted">
                    <span class="me-3"><i class="fas fa-layer-group me-1"></i>Khu vực: <?php echo htmlspecialchars($areaName ?: 'Chưa rõ'); ?></span>
                    <span class="me-3"><i class="fas fa-users me-1"></i>Số khách dự kiến: <?php echo (int)$peopleCount; ?></span>
                    <?php if ($bookingDateTime): ?>
                        <span class="me-3"><i class="fas fa-clock me-1"></i>Giờ mở: <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($bookingDateTime))); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-info-circle me-1"></i>Trạng thái:
                        <span class="badge <?php echo $orderStatusBadge; ?>">
                            <?php
                                $orderState = $order['TrangThai'] ?? '';
                                echo htmlspecialchars($orderStatusLabels[$orderState] ?? ucfirst($orderState));
                            ?>
                        </span>
                    </span>
                </div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Mã đơn hàng</div>
                <div class="fw-bold fs-5"><?php echo htmlspecialchars($order['MaDonHang'] ?? ('DH#' . $orderId)); ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h5 class="mb-1"><i class="fas fa-utensils text-warning me-2"></i>Chọn món cho khách</h5>
                            <p class="text-muted small mb-0">Chuyển đổi giữa việc chọn từng món hoặc áp dụng thực đơn định sẵn.</p>
                        </div>
                    </div>

                    <ul class="nav nav-pills mb-3" id="menuModeTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="singleMenuTab" data-bs-toggle="pill" data-bs-target="#singleMenuPane" type="button" role="tab" aria-controls="singleMenuPane" aria-selected="true">
                                <i class="fas fa-list me-1"></i>Chọn món lẻ
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <?php if (empty($menuSets)): ?>
                                <button class="nav-link disabled" type="button" tabindex="-1" aria-disabled="true">
                                    <i class="fas fa-layer-group me-1"></i>Chọn theo thực đơn
                                </button>
                            <?php else: ?>
                                <button class="nav-link" id="menuSetTab" data-bs-toggle="pill" data-bs-target="#menuSetPane" type="button" role="tab" aria-controls="menuSetPane" aria-selected="false">
                                    <i class="fas fa-layer-group me-1"></i>Chọn theo thực đơn
                                </button>
                            <?php endif; ?>
                        </li>
                    </ul>

                    <div class="tab-content" id="menuModeContent">
                        <div class="tab-pane fade show active" id="singleMenuPane" role="tabpanel" aria-labelledby="singleMenuTab">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                <div class="d-flex gap-2 flex-wrap">
                                    <div class="btn-group" role="group" aria-label="Danh mục món">
                                        <button type="button" class="btn btn-light btn-sm active" data-menu-category="all">Tất cả</button>
                                        <?php foreach ($menuCategories as $category): ?>
                                            <button type="button"
                                                    class="btn btn-light btn-sm"
                                                    data-menu-category="<?php echo (int)$category['iddm']; ?>">
                                                <?php echo htmlspecialchars($category['tendanhmuc']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="input-group input-group-sm" style="width: 220px;">
                                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                        <input type="text" class="form-control border-start-0" id="menuSearch" placeholder="Tìm kiếm món...">
                                    </div>
                                </div>
                            </div>

                            <div id="menuGrid" class="row g-3"></div>
                            <div id="menuEmpty" class="text-center py-4 text-muted" style="display: none;">
                                <i class="fas fa-info-circle me-2"></i>Không tìm thấy món ăn phù hợp.
                            </div>
                        </div>

                        <div class="tab-pane fade" id="menuSetPane" role="tabpanel" aria-labelledby="menuSetTab">
                            <?php if (!empty($menuSets)): ?>
                                <div class="row g-3">
                                    <?php foreach ($menuSets as $set): ?>
                                        <div class="col-md-6">
                                            <div class="border rounded-3 h-100 p-3 d-flex gap-3">
                                                <div class="flex-shrink-0">
                                                    <img src="<?php echo htmlspecialchars($set['image']); ?>" alt="<?php echo htmlspecialchars($set['name']); ?>" class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                                </div>
                                                <div class="flex-grow-1 d-flex flex-column">
                                                    <div class="mb-2">
                                                        <h6 class="mb-1 text-truncate"><?php echo htmlspecialchars($set['name']); ?></h6>
                                                        <p class="text-muted small mb-2" style="max-height: 60px; overflow: hidden;"><?php echo htmlspecialchars($set['description']); ?></p>
                                                    </div>
                                                    <div class="small text-muted mb-2">
                                                        <span><i class="fas fa-users me-1"></i><?php echo (int)$set['min_guests']; ?>–<?php echo (int)$set['max_guests']; ?> khách</span>
                                                        <span class="ms-3"><i class="fas fa-list-ul me-1"></i><?php echo count($set['dishes']); ?> món</span>
                                                    </div>
                                                    <div class="d-flex align-items-center justify-content-between mt-auto">
                                                        <span class="fw-semibold text-primary"><?php echo number_format($set['price'], 0, ',', '.'); ?>đ</span>
                                                        <button type="button" class="btn btn-sm btn-warning" data-apply-menu-set="<?php echo (int)$set['id']; ?>">
                                                            <i class="fas fa-bolt me-1"></i>Áp dụng
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Chưa có thực đơn định sẵn. Bạn vẫn có thể chọn từng món để phục vụ khách.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Order tạm card moved to right column (above "Món đang phục vụ") -->
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0"><i class="fas fa-user-circle text-warning me-2"></i>Thông tin khách hàng</h5>
                        <?php if ($isRealCustomerLinked && $linkedCustomer): ?>
                            <span class="badge bg-success">Đã gắn</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Chưa gắn</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($isRealCustomerLinked && $linkedCustomer): ?>
                        <ul class="list-unstyled mb-3 small">
                            <li><strong>Họ tên:</strong> <?php echo htmlspecialchars($linkedCustomer['tenKH'] ?? ''); ?></li>
                            <li><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($linkedCustomer['sodienthoai'] ?? ''); ?></li>
                            <li><strong>Email:</strong> <?php echo htmlspecialchars($linkedCustomer['email'] ?? ''); ?></li>
                        </ul>
                    <?php else: ?>
                        <div class="alert alert-light border small mb-3">
                            Chưa tìm thấy khách hàng cho đơn này. Vui lòng nhập số điện thoại để tra cứu trước khi thanh toán.
                        </div>
                    <?php endif; ?>
                    <form method="post" class="row g-2 align-items-center">
                        <input type="hidden" name="order_action" value="lookup_customer">
                        <div class="col-sm-8">
                            <input type="tel"
                                   class="form-control"
                                   name="customer_phone"
                                   value="<?php echo htmlspecialchars($customerSearchPhoneInput); ?>"
                                   placeholder="Nhập số điện thoại">
                        </div>
                        <div class="col-sm-4 col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i>Tra cứu
                            </button>
                        </div>
                    </form>
                    <div class="small text-muted mt-2">
                        Nếu chưa có thông tin, nhân viên có thể vào mục
                        <a href="index.php?page=dskhachhang">Khách hàng</a> để thêm mới.
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0"><i class="fas fa-cart-plus text-warning me-2"></i>Order tạm</h5>
                        <span class="badge bg-light text-dark"><strong id="draftCount">0</strong> món</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Món ăn</th>
                                    <th class="text-center" style="width: 140px;">Số lượng</th>
                                    <th class="text-end" style="width: 120px;">Thành tiền</th>
                                    <th style="width: 90px;">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody id="draftBody">
                                <tr id="draftEmptyRow">
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-info-circle me-2"></i>Chưa có món trong order. Chọn món từ danh sách bên trên.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Phụ thu khu vực dự kiến: <?php echo number_format($surchargeEstimate, 0, ',', '.'); ?>đ
                        </div>
                        <form id="sendToKitchenForm" method="post" class="d-flex gap-2">
                            <input type="hidden" name="order_action" id="orderActionField" value="">
                            <input type="hidden" name="order_payload" id="orderPayloadField" value="">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearDraftBtn">
                                <i class="fas fa-undo me-1"></i>Làm mới
                            </button>
                            <button type="button" class="btn btn-warning" id="sendToKitchenBtn" <?php echo $isOrderCompleted ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane me-2"></i>Gửi xuống bếp
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php ob_start(); ?>
            <div class="card border-0 shadow-sm mb-4" id="servingSection">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h5 class="mb-0"><i class="fas fa-concierge-bell text-warning me-2"></i>Món đang phục vụ</h5>
                        <span class="badge bg-light text-dark"><?php echo $displayedServingRowCount; ?> món</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Món ăn</th>
                                   <th class="text-center">SL</th>
                                    <th class="text-end">Giá</th>
                                    <th>Trạng thái</th>
                                    <th class="text-end">Tùy chọn</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($displayedServingRowCount === 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">
                                            <i class="fas fa-info-circle me-2"></i>Chưa có món nào được gửi xuống bếp.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($menuSetGroups as $group): ?>
                                        <tr class="table-light">
                                            <td>
                                                <div class="fw-semibold"><i class="fas fa-layer-group text-warning me-1"></i><?php echo htmlspecialchars($group['name']); ?></div>
                                                <div class="small text-muted">
                                                    Thực đơn định sẵn • <?php echo count($group['items']); ?> món
                                                    <?php if (!empty($group['note'])): ?>
                                                        <div>Ghi chú: <?php echo htmlspecialchars($group['note']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-center"><?php echo (int)$group['quantity']; ?></td>
                                            <td class="text-end"><?php echo number_format($group['total_price'], 0, ',', '.'); ?>đ</td>
                                            <td><span class="badge <?php echo $group['badge_class']; ?>"><?php echo htmlspecialchars($group['status_label']); ?></span></td>
                                            <td class="text-end">
                                                <button type="button"
                                                        class="btn btn-outline-secondary btn-sm"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#<?php echo $group['collapse_id']; ?>"
                                                        aria-expanded="false"
                                                        aria-controls="<?php echo $group['collapse_id']; ?>">
                                                    <i class="fas fa-list me-1"></i>Chi tiết
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="collapse" id="<?php echo $group['collapse_id']; ?>">
                                            <td colspan="5">
                                                <div class="bg-light rounded p-3">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm align-middle mb-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>Món ăn</th>
                                                                    <th class="text-center" style="width: 80px;">SL</th>
                                                                    <th class="text-end" style="width: 120px;">Giá</th>
                                                                    <th style="width: 140px;">Trạng thái</th>
                                                                    <th class="text-end" style="width: 160px;">Tùy chọn</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($group['items'] as $item): ?>
                                                                    <?php
                                                                        $itemStatus = $item['TrangThai'] ?? clsDonHang::ITEM_STATUS_PREPARING;
                                                                        $badgeClass = $statusBadges[$itemStatus] ?? 'bg-secondary';
                                                                        $statusLabel = $statusLabels[$itemStatus] ?? ucfirst($itemStatus);
                                                                        $isCancelled = $itemStatus === clsDonHang::ITEM_STATUS_CANCELLED;
                                                                        $isServed = $itemStatus === clsDonHang::ITEM_STATUS_SERVED;
                                                                        $disableActions = $isOrderCompleted || $isCancelled || $isServed;
                                                                    ?>
                                                                      <tr class="serving-action-row" data-serving-row="<?php echo (int)$item['idCTDH']; ?>">
                                                                          <td>
                                                                              <div class="fw-semibold"><?php echo htmlspecialchars($item['tenmonan'] ?? ('Món #' . $item['idmonan'])); ?></div>
                                                                              <?php if (!empty($item['GhiChu'])): ?>
                                                                                  <div class="text-muted small">Ghi chú: <?php echo htmlspecialchars($item['GhiChu']); ?></div>
                                                                              <?php endif; ?>
                                                                          </td>
                                                                          <td class="text-center"><?php echo (int)$item['SoLuong']; ?></td>
                                                                          <td class="text-end"><?php echo number_format((float)$item['DonGia'] * (int)$item['SoLuong'], 0, ',', '.'); ?>đ</td>
                                                                          <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                                                                          <td class="text-end">
                                                                              <div class="d-flex gap-1 justify-content-end">
                                                    <?php if (!$disableActions): ?>
                                                        <?php if ($itemStatus === clsDonHang::ITEM_STATUS_PREPARING): ?>
                                                            <form method="post" class="d-inline ajax-order-form">
                                                                <input type="hidden" name="order_action" value="update_item_status">
                                                                <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                                <input type="hidden" name="target_status" value="<?php echo clsDonHang::ITEM_STATUS_READY; ?>">
                                                                <button type="submit" class="btn btn-outline-info btn-sm">
                                                                    <i class="fas fa-check-circle me-1"></i>Hoàn tất
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($itemStatus === clsDonHang::ITEM_STATUS_READY): ?>
                                                            <form method="post" class="d-inline ajax-order-form">
                                                                <input type="hidden" name="order_action" value="update_item_status">
                                                                <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                                <input type="hidden" name="target_status" value="<?php echo clsDonHang::ITEM_STATUS_SERVED; ?>">
                                                                <button type="submit" class="btn btn-outline-success btn-sm">
                                                                    <i class="fas fa-utensils me-1"></i>Đã phục vụ
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if (!$isOrderCompleted): ?>
                                                        <form method="post" class="d-inline ajax-order-form" onsubmit="return confirm('Xóa món này khỏi đơn?');">
                                                            <input type="hidden" name="order_action" value="remove_order_item">
                                                            <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm btn-delete-serving">
                                                                <i class="fas fa-trash-alt me-1"></i>Xóa
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                    <?php foreach ($standaloneOrderItems as $item): ?>
                                        <?php
                                            $itemStatus = $item['TrangThai'] ?? clsDonHang::ITEM_STATUS_PREPARING;
                                            $badgeClass = $statusBadges[$itemStatus] ?? 'bg-secondary';
                                            $statusLabel = $statusLabels[$itemStatus] ?? ucfirst($itemStatus);
                                            $isCancelled = $itemStatus === clsDonHang::ITEM_STATUS_CANCELLED;
                                            $isServed = $itemStatus === clsDonHang::ITEM_STATUS_SERVED;
                                            $disableActions = $isOrderCompleted || $isCancelled || $isServed;
                                            $metadata = $item['_metadata'] ?? [];
                                            $menuSetMeta = $item['_menu_set_meta'] ?? null;
                                            $isVirtualMenuSet = !empty($item['_is_virtual_menu_set']);
                                            $displayName = $menuSetMeta['name']
                                                ?? ($item['tenmonan'] ?? ('Món #' . ($item['idmonan'] ?? '')));
                                        ?>
                                          <tr>
                                              <td>
                                                  <div class="fw-semibold d-flex align-items-center gap-2">
                                                      <?php echo htmlspecialchars($displayName); ?>
                                                      <?php if ($menuSetMeta): ?>
                                                          <span class="badge bg-warning text-dark">Thực đơn</span>
                                                      <?php endif; ?>
                                                  </div>
                                                  <?php if ($menuSetMeta && !$isVirtualMenuSet): ?>
                                                      <div class="text-muted small">Thuộc thực đơn định sẵn.</div>
                                                  <?php endif; ?>
                                                  <?php if (!empty($item['GhiChu'])): ?>
                                                      <div class="text-muted small">Ghi chú: <?php echo htmlspecialchars($item['GhiChu']); ?></div>
                                                  <?php endif; ?>
                                              </td>
                                              <td class="text-center"><?php echo (int)$item['SoLuong']; ?></td>
                                              <td class="text-end"><?php echo number_format((float)$item['DonGia'] * (int)$item['SoLuong'], 0, ',', '.'); ?>đ</td>
                                              <td><span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                                              <td class="text-end">
                                                  <div class="d-flex gap-1 justify-content-end flex-wrap">
                                                      <?php if (!$disableActions): ?>
                                                          <?php if ($itemStatus === clsDonHang::ITEM_STATUS_PREPARING): ?>
                                                               <form method="post" class="d-inline ajax-order-form">
                                                                   <input type="hidden" name="order_action" value="update_item_status">
                                                                   <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                                   <input type="hidden" name="target_status" value="<?php echo clsDonHang::ITEM_STATUS_READY; ?>">
                                                                   <button type="submit" class="btn btn-outline-info btn-sm">
                                                                       <i class="fas fa-check-circle me-1"></i>Hoàn tất
                                                                  </button>
                                                              </form>
                                                          <?php endif; ?>
                                                          <?php if ($itemStatus === clsDonHang::ITEM_STATUS_READY): ?>
                                                               <form method="post" class="d-inline ajax-order-form">
                                                                   <input type="hidden" name="order_action" value="update_item_status">
                                                                   <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                                   <input type="hidden" name="target_status" value="<?php echo clsDonHang::ITEM_STATUS_SERVED; ?>">
                                                                   <button type="submit" class="btn btn-outline-success btn-sm">
                                                                       <i class="fas fa-utensils me-1"></i>Đã phục vụ
                                                                  </button>
                                                              </form>
                                                          <?php endif; ?>
                                                      <?php endif; ?>
                                                      <?php if (!$isOrderCompleted): ?>
                                                           <form method="post" class="d-inline ajax-order-form" onsubmit="return confirm('Xóa món này khỏi đơn?');">
                                                               <input type="hidden" name="order_action" value="remove_order_item">
                                                               <input type="hidden" name="item_id" value="<?php echo (int)$item['idCTDH']; ?>">
                                                               <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                   <i class="fas fa-trash-alt me-1"></i>Xóa
                                                               </button>
                                                          </form>
                                                      <?php endif; ?>
                                                  </div>
                                              </td>
                                          </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
                $servingSectionHtml = ob_get_clean();
                echo $servingSectionHtml;
            ?>

            <?php ob_start(); ?>
            <div class="card border-0 shadow-sm" id="paymentSection">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-cash-register text-warning me-2"></i>Thanh toán</h5>
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span class="text-muted">Tạm tính món ăn</span>
                        <strong><?php echo number_format($totals['subtotal'], 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                        <span class="text-muted">Phụ thu</span>
                        <strong><?php echo number_format($totals['surcharge'], 0, ',', '.'); ?>đ</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="fw-semibold">Tổng cộng</span>
                        <span class="fw-bold fs-5 text-primary"><?php echo number_format($totals['total'], 0, ',', '.'); ?>đ</span>
                    </div>
                    <?php if (($bookingDeposit['paid'] ?? 0) > 0 || ($bookingDeposit['pending'] ?? 0) > 0 || ($bookingDeposit['required'] ?? 0) > 0): ?>
                        <div class="d-flex justify-content-between border-top pt-2 mt-2">
                            <span class="text-muted">Đã cọc</span>
                            <strong class="<?php echo ($bookingDeposit['paid'] ?? 0) >= $totals['total'] ? 'text-success' : 'text-primary'; ?>">
                                <?php echo number_format($bookingDeposit['paid'] ?? 0, 0, ',', '.'); ?>đ
                            </strong>
                        </div>
                        <?php if (($bookingDeposit['pending'] ?? 0) > 0): ?>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Chờ xác nhận</span>
                                <strong class="text-warning"><?php echo number_format($bookingDeposit['pending'], 0, ',', '.'); ?>đ</strong>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between border-top pt-2 mt-2">
                        <span class="fw-semibold">Còn phải thu</span>
                        <span class="fw-bold <?php echo $amountDueAfterDeposit <= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($amountDueAfterDeposit, 0, ',', '.'); ?>đ
                        </span>
                    </div>
                    <div class="mt-3">
                        <?php if ($isOrderCompleted): ?>
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle me-2"></i>Đơn hàng đã hoàn tất.
                            <a href="index.php?page=moBan&reset=1" class="alert-link ms-1">Mở bàn mới</a>
                            </div>
                        <?php else: ?>
                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#paymentModal" <?php echo empty($orderItems) ? 'disabled' : ''; ?>>
                                <i class="fas fa-money-bill-wave me-2"></i>Thanh toán
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
                $paymentSectionHtml = ob_get_clean();
                echo $paymentSectionHtml;
            ?>
        </div>
    </div>
    </div>
</div>

<?php
if ($isAjaxRequest) {
    ob_clean();
    $response = [
        'success' => empty($errorMessages),
        'successMessages' => $successMessages,
        'errorMessages' => $errorMessages,
        'servingHtml' => $servingSectionHtml ?? '',
        'paymentHtml' => $paymentSectionHtml ?? '',
        'orderStatus' => $order['TrangThai'] ?? null,
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    return;
}
?>

<script>
    // Gọi AJAX thẳng tới file xử lý, tránh qua layout để không dính header HTML
    const ajaxEndpoint = window.location.origin + '/CNM/Admin/kaiadmin-lite-1.2.0/page/donhang/themDH.php';
    const ajaxMessageBox = document.getElementById('ajaxMessages');

    function replaceSectionById(targetId, html) {
        if (!html) return;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();
        const nextNode = wrapper.firstElementChild;
        const current = document.getElementById(targetId);
        if (nextNode && current) {
            current.replaceWith(nextNode);
        }
    }

    function renderAjaxMessages(payload) {
        if (!ajaxMessageBox) return;
        const parts = [];
        (payload.successMessages || []).forEach((msg) => {
            parts.push(`<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`);
        });
        (payload.errorMessages || []).forEach((msg) => {
            parts.push(`<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>`);
        });
        ajaxMessageBox.innerHTML = parts.join('');
    }

    function handleAjaxResponse(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }
        renderAjaxMessages(payload);
        if (payload.servingHtml) {
            replaceSectionById('servingSection', payload.servingHtml);
        }
        if (payload.paymentHtml) {
            replaceSectionById('paymentSection', payload.paymentHtml);
        }
        bindAjaxOrderForms();
    }

    function submitAjaxForm(form) {
        const formData = new FormData(form);
        formData.append('ajax', '1');
        fetch(ajaxEndpoint, { method: 'POST', body: formData })
            .then((res) => res.json())
            .then((payload) => {
                handleAjaxResponse(payload);
            })
            .catch(() => {
                // Fallback to full reload if something went wrong
                window.location.reload();
            });
    }

    function bindAjaxOrderForms() {
        document.querySelectorAll('form.ajax-order-form').forEach((form) => {
            if (form.dataset.ajaxBound === '1') {
                return;
            }
            form.dataset.ajaxBound = '1';
            const inlineHandler = form.onsubmit;
            form.addEventListener('submit', (ev) => {
                if (typeof inlineHandler === 'function') {
                    const result = inlineHandler.call(form, ev);
                    if (result === false) {
                        ev.preventDefault();
                        return;
                    }
                }
                ev.preventDefault();
                submitAjaxForm(form);
            }, { once: false });
        });
    }

    (function () {
        const menuData = <?php echo json_encode($menuItems, JSON_UNESCAPED_UNICODE); ?>;
        const menuSetsData = <?php echo json_encode($menuSets, JSON_UNESCAPED_UNICODE); ?>;
        const menuGrid = document.getElementById('menuGrid');
        const menuEmpty = document.getElementById('menuEmpty');
        const menuSearch = document.getElementById('menuSearch');
        const categoryButtons = document.querySelectorAll('[data-menu-category]');

        const draftBody = document.getElementById('draftBody');
        const draftCount = document.getElementById('draftCount');
        const draftEmptyRow = document.getElementById('draftEmptyRow');
        const orderActionField = document.getElementById('orderActionField');
        const orderPayloadField = document.getElementById('orderPayloadField');
        const sendToKitchenBtn = document.getElementById('sendToKitchenBtn');
        const clearDraftBtn = document.getElementById('clearDraftBtn');
        const menuSetButtons = document.querySelectorAll('[data-apply-menu-set]');
        const ITEM_TYPES = {
            SINGLE: 'single',
            MENU_SET: 'menu_set',
        };

        const generateMenuSetToken = (menuSetId) => {
            return `set-${menuSetId || 'x'}-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
        };

        let currentCategory = 'all';
        let currentSearch = '';
        let draftItems = [];
        const initialDraftItems = <?php echo json_encode($preloadedDraftItems, JSON_UNESCAPED_UNICODE); ?>;
        const getDefaultKey = (dishId) => `dish-${dishId}`;

        const formatCurrency = (value) => new Intl.NumberFormat('vi-VN').format(value) + 'đ';

        const renderMenu = () => {
            menuGrid.innerHTML = '';
            const term = currentSearch.trim().toLowerCase();

            const filtered = menuData.filter((item) => {
                const matchCategory = currentCategory === 'all' || item.category === parseInt(currentCategory, 10);
                const matchSearch = term === '' || item.name.toLowerCase().includes(term);
                return matchCategory && matchSearch;
            });

            if (filtered.length === 0) {
                menuEmpty.style.display = 'block';
                return;
            }

            menuEmpty.style.display = 'none';

            filtered.forEach((item) => {
                const col = document.createElement('div');
                col.className = 'col-sm-6 col-xl-4';
                col.innerHTML = `
                    <div class="card h-100 shadow-sm border-0">
                        <img src="${item.image}" alt="${item.name}" class="card-img-top" style="height: 150px; object-fit: cover;">
                        <div class="card-body py-3">
                            <div class="fw-semibold text-truncate" title="${item.name}">${item.name}</div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="fw-bold text-primary">${formatCurrency(item.price)}</span>
                                <button type="button" class="btn btn-sm btn-warning" data-add-dish="${item.id}">
                                    <i class="fas fa-plus me-1"></i>Thêm
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                menuGrid.appendChild(col);
            });

            menuGrid.querySelectorAll('[data-add-dish]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const dishId = parseInt(btn.getAttribute('data-add-dish'), 10);
                    addDishToDraft(dishId);
                });
            });
        };

        const renderDraft = () => {
            draftBody.querySelectorAll('tr').forEach((row) => {
                if (row !== draftEmptyRow) {
                    row.remove();
                }
            });

            if (draftItems.length === 0) {
                draftEmptyRow.style.display = '';
                draftCount.textContent = '0';
                return;
            }

            draftEmptyRow.style.display = 'none';
            draftCount.textContent = String(draftItems.length);

            draftItems.forEach((item) => {
                const row = document.createElement('tr');
                const metaLabel = item.metaLabel ? `<div class="small text-muted">${item.metaLabel}</div>` : '';
                const typeBadge = item.type === ITEM_TYPES.MENU_SET
                    ? '<span class="badge bg-warning text-dark ms-2">Thực đơn</span>'
                    : '';
                row.innerHTML = `
                    <td>
                        <div class="fw-semibold d-flex align-items-center gap-2">${item.name}${typeBadge}</div>
                        ${metaLabel}
                        <input type="text" class="form-control form-control-sm mt-1" placeholder="Ghi chú"
                               value="${item.note ?? ''}" data-draft-note="${item.key}">
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" data-qty-change="${item.key}" data-delta="-1"><i class="fas fa-minus"></i></button>
                            <span class="btn btn-light px-3">${item.quantity}</span>
                            <button type="button" class="btn btn-outline-secondary" data-qty-change="${item.key}" data-delta="1"><i class="fas fa-plus"></i></button>
                        </div>
                    </td>
                    <td class="text-end fw-semibold">${formatCurrency(item.quantity * item.price)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-link text-danger btn-sm" data-remove-draft="${item.key}">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                `;
                draftBody.appendChild(row);
            });

            draftBody.querySelectorAll('[data-qty-change]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const draftKey = btn.getAttribute('data-qty-change');
                    const delta = parseInt(btn.getAttribute('data-delta'), 10);
                    changeDraftQuantity(draftKey, delta);
                });
            });

            draftBody.querySelectorAll('[data-remove-draft]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const draftKey = btn.getAttribute('data-remove-draft');
                    draftItems = draftItems.filter((item) => item.key !== draftKey);
                    renderDraft();
                });
            });

            draftBody.querySelectorAll('[data-draft-note]').forEach((input) => {
                input.addEventListener('input', () => {
                    const draftKey = input.getAttribute('data-draft-note');
                    const draftItem = draftItems.find((item) => item.key === draftKey);
                    if (draftItem) {
                        draftItem.note = input.value;
                    }
                });
            });
        };

        const addDishToDraft = (dishId, quantity = 1, meta = null, shouldRender = true) => {
            const qtyToAdd = typeof quantity === 'number' && quantity > 0 ? quantity : 1;
            const metaData = meta || {};
            const dish = menuData.find((item) => item.id === dishId);
            const dishName = dish ? dish.name : (metaData.name || `Món #${dishId}`);
            const customPrice = typeof metaData.priceOverride === 'number' ? metaData.priceOverride : null;
            const dishPrice = customPrice !== null
                ? customPrice
                : (dish ? dish.price : (typeof metaData.price === 'number' ? metaData.price : 0));
            if (!dishName) {
                return;
            }
            const key = metaData.key || getDefaultKey(dishId);
            const metaLabel = metaData.metaLabel || '';
            const itemType = metaData.type || ITEM_TYPES.SINGLE;
            const menuSetId = typeof metaData.menuSetId === 'number' ? metaData.menuSetId : null;
            const groupToken = metaData.groupToken || (itemType === ITEM_TYPES.MENU_SET ? generateMenuSetToken(menuSetId) : null);
            const existing = draftItems.find((item) => item.key === key);
            if (existing) {
                existing.quantity += qtyToAdd;
                if (customPrice !== null) {
                    existing.price = dishPrice;
                }
                if (itemType === ITEM_TYPES.MENU_SET) {
                    if (!existing.groupToken && groupToken) {
                        existing.groupToken = groupToken;
                    }
                    if (!existing.menuSetId && menuSetId) {
                        existing.menuSetId = menuSetId;
                    }
                }
            } else {
                draftItems.push({
                    key,
                    id: typeof dishId === 'number' ? dishId : null,
                    type: itemType,
                    menuSetId,
                    groupToken,
                    name: dishName,
                    price: dishPrice,
                    quantity: qtyToAdd,
                    note: metaData.note || '',
                    metaLabel: metaLabel || (itemType === ITEM_TYPES.MENU_SET ? 'Thực đơn định sẵn' : '')
                });
            }
            if (shouldRender) {
                renderDraft();
            }
        };

        const applyMenuSet = (setId) => {
            const menuSet = menuSetsData.find((set) => set.id === setId);
            if (!menuSet) {
                return;
            }
            if (!Array.isArray(menuSet.dishes) || menuSet.dishes.length === 0) {
                alert('Thực đơn này chưa có món nào để áp dụng.');
                return;
            }
            const parsedPrice = typeof menuSet.price === 'number' ? menuSet.price : parseFloat(menuSet.price);
            const setPrice = Number.isFinite(parsedPrice) ? parsedPrice : 0;

            addDishToDraft(
                null,
                1,
                {
                    key: `menu-set-${menuSet.id}`,
                    name: menuSet.name,
                    priceOverride: setPrice,
                    metaLabel: 'Thực đơn định sẵn',
                    type: ITEM_TYPES.MENU_SET,
                    menuSetId: menuSet.id,
                    groupToken: generateMenuSetToken(menuSet.id)
                }
            );
        };

        const changeDraftQuantity = (draftKey, delta) => {
            const draftItem = draftItems.find((item) => item.key === draftKey);
            if (!draftItem) {
                return;
            }
            draftItem.quantity += delta;
            if (draftItem.quantity <= 0) {
                draftItems = draftItems.filter((item) => item.key !== draftKey);
            }
            renderDraft();
        };

        categoryButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                categoryButtons.forEach((button) => button.classList.remove('active'));
                btn.classList.add('active');
                currentCategory = btn.getAttribute('data-menu-category');
                renderMenu();
            });
        });

        menuSearch.addEventListener('input', (event) => {
            currentSearch = event.target.value;
            renderMenu();
        });

        clearDraftBtn.addEventListener('click', () => {
            draftItems = [];
            renderDraft();
        });

        if (menuSetButtons.length > 0) {
            menuSetButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const targetId = parseInt(btn.getAttribute('data-apply-menu-set'), 10);
                    applyMenuSet(targetId);
                });
            });
        }

        sendToKitchenBtn.addEventListener('click', () => {
            if (draftItems.length === 0) {
                alert('Vui lòng chọn ít nhất một món trước khi gửi xuống bếp.');
                return;
            }

            const payload = [];

            draftItems.forEach((item) => {
                if (item.type === ITEM_TYPES.MENU_SET) {
                    const groupToken = item.groupToken || generateMenuSetToken(item.menuSetId);
                    payload.push({
                        idmonan: item.id ?? null,
                        soluong: item.quantity,
                        ghichu: item.note || null,
                        unit_price: typeof item.price === 'number' ? item.price : (parseFloat(item.price) || 0),
                        metadata: JSON.stringify({
                            menu_set: {
                                id: item.menuSetId,
                                token: groupToken,
                                name: item.name,
                                quantity: item.quantity,
                                virtual_item: true
                            },
                            set_note: item.note || null
                        })
                    });
                    return;
                }

                payload.push({
                    idmonan: item.id,
                    soluong: item.quantity,
                    ghichu: item.note || null,
                    unit_price: typeof item.price === 'number' ? item.price : null
                });
            });

            orderActionField.value = 'send_to_kitchen';
            orderPayloadField.value = JSON.stringify(payload);
            submitAjaxForm(sendToKitchenBtn.closest('form'));
            draftItems = [];
            renderDraft();
        });

        if (Array.isArray(initialDraftItems) && initialDraftItems.length > 0) {
            initialDraftItems.forEach((item, idx) => {
                const qty = (typeof item.quantity === 'number' && item.quantity > 0) ? item.quantity : 1;
                if ((item.type === ITEM_TYPES.MENU_SET) || (item.type === 'menu_set')) {
                    const menuSetId = typeof item.menuSetId === 'number' ? item.menuSetId : null;
                    addDishToDraft(
                        null,
                        qty,
                        {
                            key: item.key || `preload-menu-set-${menuSetId || idx}`,
                            name: item.name || 'Thực đơn đặt trước',
                            priceOverride: typeof item.price === 'number' ? item.price : null,
                            metaLabel: 'Thực đơn định sẵn',
                            type: ITEM_TYPES.MENU_SET,
                            menuSetId,
                            groupToken: generateMenuSetToken(menuSetId || idx),
                            note: item.note || ''
                        },
                        false
                    );
                } else if (item.dishId) {
                    addDishToDraft(
                        parseInt(item.dishId, 10),
                        qty,
                        {
                            key: item.key || `preload-dish-${item.dishId}-${idx}`,
                            priceOverride: typeof item.price === 'number' ? item.price : null,
                            name: item.name || undefined,
                            note: item.note || ''
                        },
                        false
                    );
                }
            });
        }

        renderMenu();
        renderDraft();
        bindAjaxOrderForms();
    })();
</script>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="order_action" value="complete_order">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel"><i class="fas fa-money-check-alt text-warning me-2"></i>Xác nhận thanh toán</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Tổng tiền: <strong><?php echo number_format($totals['total'], 0, ',', '.'); ?>đ</strong></p>
                    <?php if (($bookingDeposit['paid'] ?? 0) > 0): ?>
                        <p class="mb-2 text-success">Đã cọc: <strong><?php echo number_format($bookingDeposit['paid'], 0, ',', '.'); ?>đ</strong></p>
                    <?php endif; ?>
                    <p class="mb-3 fw-semibold">
                        Cần thu thêm: <span class="<?php echo $amountDueAfterDeposit <= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format(max(0, $amountDueAfterDeposit), 0, ',', '.'); ?>đ
                        </span>
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Hình thức thanh toán</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="paymentCash" value="cash" checked>
                            <label class="form-check-label" for="paymentCash">
                                Tiền mặt
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="payment_method" id="paymentTransfer" value="transfer">
                            <label class="form-check-label" for="paymentTransfer">
                                Chuyển khoản
                            </label>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>Sau khi xác nhận, bàn sẽ tự động chuyển về trạng thái trống và nguyên liệu sẽ được trừ kho theo FIFO.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Hoàn tất thanh toán
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
