<?php

if (!function_exists('admin_booking_build_flow_from_snapshot')) {
    function admin_booking_build_flow_from_snapshot(array $snapshot) {
        $booking = $snapshot['booking'] ?? [];
        $meta = $snapshot['meta'] ?? [];
        $tables = $snapshot['tables'] ?? [];
        $menuItems = $snapshot['menu_items'] ?? [];

        $flow = [];
        $channel = $meta['booking_channel'] ?? 'walkin';
        $flow['type'] = in_array($channel, ['walkin', 'phone'], true) ? $channel : 'walkin';
        $flow['customer'] = [
            'name' => $booking['tenKH'] ?? '',
            'phone' => $booking['sodienthoai'] ?? '',
            'email' => $booking['email'] ?? '',
            'note' => $meta['note'] ?? ''
        ];
        $flow['customer_id'] = $booking['idKH'] ?? null;
        $flow['payment_method'] = in_array($meta['payment_method'] ?? 'cash', ['cash', 'transfer'], true)
            ? $meta['payment_method']
            : 'cash';
        $flow['created_by'] = $meta['created_by'] ?? null;

        $flow['booking'] = [
            'datetime' => $booking['NgayDatBan'] ?? date('Y-m-d H:i:s'),
            'khuvuc' => null,
            'tables' => [],
            'total_surcharge' => 0,
            'people_count' => (int)($booking['SoLuongKhach'] ?? 0)
        ];

        foreach ($tables as $table) {
            $tableId = isset($table['idban']) ? (int)$table['idban'] : 0;
            if ($tableId <= 0) {
                continue;
            }
            if ($flow['booking']['khuvuc'] === null && isset($table['MaKV'])) {
                $flow['booking']['khuvuc'] = (int)$table['MaKV'];
            }
            $surcharge = isset($table['phuthu']) ? (float)$table['phuthu'] : (float)($table['default_phuthu'] ?? 0);
            $flow['booking']['tables'][] = [
                'idban' => $tableId,
                'soban' => $table['SoBan'] ?? '',
                'capacity' => (int)($table['soluongKH'] ?? 0),
                'phuthu' => $surcharge
            ];
            $flow['booking']['total_surcharge'] += $surcharge;
        }

        if ($flow['booking']['khuvuc'] === null) {
            $flow['booking']['khuvuc'] = 0;
        }

        $menuMode = $meta['menu_mode'] ?? 'none';
        $snapshotMenu = [];
        if (!empty($meta['menu_snapshot'])) {
            $decoded = json_decode($meta['menu_snapshot'], true);
            if (is_array($decoded)) {
                $snapshotMenu = $decoded;
            }
        }

        $itemsPayload = [];
        $menuTotal = 0;
        foreach ($menuItems as $item) {
            $id = isset($item['idmonan']) ? (int)$item['idmonan'] : 0;
            $qty = isset($item['SoLuong']) ? (int)$item['SoLuong'] : 0;
            $price = isset($item['DonGia']) ? (float)$item['DonGia'] : 0.0;
            if ($id <= 0 || $qty <= 0) {
                continue;
            }
            $itemsPayload[] = [
                'idmonan' => $id,
                'tenmonan' => $item['tenmonan'] ?? '',
                'DonGia' => $price,
                'soluong' => $qty
            ];
            $menuTotal += $price * $qty;
        }

        $flow['menu'] = [
            'mode' => $menuMode,
            'items' => [],
            'set' => null,
            'total' => 0
        ];

        if ($menuMode === 'items') {
            $flow['menu']['items'] = $itemsPayload;
            $flow['menu']['total'] = $menuTotal;
        } elseif ($menuMode === 'set') {
            $setData = $snapshotMenu['set'] ?? null;
            if (!is_array($setData) || empty($setData)) {
                $setData = null;
            }
            // Chuẩn hóa tên/thông tin thực đơn nếu chỉ có trong thucdon_info
            if ($setData !== null) {
                if (empty($setData['tenthucdon']) && isset($setData['thucdon_info']['tenthucdon'])) {
                    $setData['tenthucdon'] = $setData['thucdon_info']['tenthucdon'];
                }
                if (empty($setData['name']) && !empty($setData['tenthucdon'])) {
                    $setData['name'] = $setData['tenthucdon'];
                }
                if (!isset($setData['idthucdon']) && isset($setData['id_thucdon'])) {
                    $setData['idthucdon'] = $setData['id_thucdon'];
                } elseif (!isset($setData['id_thucdon']) && isset($setData['idthucdon'])) {
                    $setData['id_thucdon'] = $setData['idthucdon'];
                } elseif (isset($setData['thucdon_info']['idthucdon'])) {
                    $setData['idthucdon'] = $setData['thucdon_info']['idthucdon'];
                    $setData['id_thucdon'] = $setData['thucdon_info']['idthucdon'];
                }
            }
            $flow['menu']['set'] = $setData;
            if ($setData !== null) {
                if (isset($setData['tongtien']) && (float)$setData['tongtien'] > 0) {
                    $flow['menu']['total'] = (float)$setData['tongtien'];
                } elseif (isset($setData['monan']) && is_array($setData['monan'])) {
                    $total = 0;
                    foreach ($setData['monan'] as $dish) {
                        $price = isset($dish['DonGia']) ? (float)$dish['DonGia'] : 0;
                        $qty = isset($dish['soluong']) ? (int)$dish['soluong'] : 0;
                        $total += $price * $qty;
                    }
                    $flow['menu']['total'] = $total;
                } else {
                    $flow['menu']['total'] = $menuTotal;
                }
            } else {
                $flow['menu']['total'] = $menuTotal;
            }
        } else {
            $flow['menu']['total'] = 0;
        }

        if ($flow['menu']['mode'] !== 'set') {
            $flow['menu']['set'] = $snapshotMenu['set'] ?? null;
        }
        if ($flow['menu']['mode'] !== 'items') {
            $flow['menu']['items'] = $flow['menu']['items'] ?? $itemsPayload;
        }

        $flow['financial'] = [
            'estimated_food' => $flow['menu']['total'] ?? 0,
            'total_amount' => (float)($booking['TongTien'] ?? 0)
        ];
        $flow['admin_note'] = $meta['note'] ?? '';
        $flow['auto_confirm'] = ($booking['TrangThai'] ?? '') === 'confirmed';
        $flow['original_status'] = $booking['TrangThai'] ?? 'pending';
        $flow['original_payment_expires'] = $booking['payment_expires'] ?? null;
        $flow['original_expiry_hold'] = $booking['ThoiGianHetHan'] ?? null;

        return $flow;
    }
}

if (!function_exists('admin_booking_snapshot_to_open_table_flow')) {
    function admin_booking_snapshot_to_open_table_flow(array $snapshot): array
    {
        $fullFlow = admin_booking_build_flow_from_snapshot($snapshot);
        $booking = $snapshot['booking'] ?? [];
        $tables = $snapshot['tables'] ?? [];
        $meta = $snapshot['meta'] ?? [];

        $flow = [
            'booking' => [
                'datetime' => $booking['NgayDatBan'] ?? date('Y-m-d H:i:s'),
                'khuvuc' => null,
                'tables' => [],
                'total_surcharge' => 0,
                'people_count' => (int)($booking['SoLuongKhach'] ?? 0),
                'table_label' => '',
                'source_booking_id' => $booking['madatban'] ?? null,
            ],
            'customer' => [
                'name' => $booking['tenKH'] ?? '',
                'phone' => $booking['sodienthoai'] ?? '',
                'email' => $booking['email'] ?? '',
            ],
            'note' => $meta['note'] ?? null,
            'area' => null,
        ];

        $tableLabels = [];
        $totalCapacity = 0;
        foreach ($tables as $table) {
            $tableId = isset($table['idban']) ? (int)$table['idban'] : 0;
            if ($tableId <= 0) {
                continue;
            }

            $capacity = (int)($table['soluongKH'] ?? 0);
            $surcharge = isset($table['phuthu']) ? (float)$table['phuthu'] : (float)($table['default_phuthu'] ?? 0);
            $areaId = isset($table['MaKV']) ? (int)$table['MaKV'] : null;
            $areaName = $table['TenKV'] ?? '';

            if ($flow['booking']['khuvuc'] === null && $areaId !== null) {
                $flow['booking']['khuvuc'] = $areaId;
            }
            if ($flow['area'] === null && $areaId !== null) {
                $baseSurcharge = isset($table['default_phuthu']) ? (float)$table['default_phuthu'] : $surcharge;
                $flow['area'] = [
                    'id' => $areaId,
                    'name' => $areaName,
                    'surcharge' => $baseSurcharge,
                ];
            }

            $flow['booking']['tables'][] = [
                'idban' => $tableId,
                'soban' => $table['SoBan'] ?? '',
                'capacity' => $capacity,
                'phuthu' => $surcharge,
                'khuvuc' => $areaId,
                'zone' => $table['zone'] ?? null,
                'TenKV' => $areaName,
            ];

            $flow['booking']['total_surcharge'] += $surcharge;
            $totalCapacity += $capacity;
            $tableLabels[] = 'Bàn ' . ($table['SoBan'] ?? $tableId);
        }

        if ($flow['booking']['people_count'] <= 0) {
            $flow['booking']['people_count'] = $totalCapacity;
        }

        if ($flow['booking']['khuvuc'] === null) {
            $flow['booking']['khuvuc'] = 0;
        }

        $flow['booking']['table_label'] = implode(', ', $tableLabels);
        $flow['step'] = 2;
        if (isset($fullFlow['menu'])) {
            $flow['menu'] = $fullFlow['menu'];
        } else {
            $flow['menu'] = [
                'mode' => 'none',
                'items' => [],
                'set' => null,
                'total' => 0
            ];
        }

        return $flow;
    }
}
