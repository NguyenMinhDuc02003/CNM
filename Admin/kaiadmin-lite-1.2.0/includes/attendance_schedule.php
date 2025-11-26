<?php
/**
 * Configurable attendance windows (24h HH:MM).
 * Hỗ trợ nhiều khung giờ cho checkin/checkout.
 */
return [
    'checkin' => [
        // Ca sáng: 08:00 - 14:00
        ['start' => '08:00', 'end' => '14:00'],
        // Ca chiều: 14:00 - 18:00
        ['start' => '14:00', 'end' => '18:00'],
        // Ca tối: 18:00 - 22:00
        ['start' => '18:00', 'end' => '22:00'],
    ],
    'checkout' => [
        // Cho phép checkout trong toàn bộ khung giờ ca tương ứng
        ['start' => '08:00', 'end' => '14:00'],
        ['start' => '14:00', 'end' => '18:00'],
        ['start' => '18:00', 'end' => '22:00'],
    ],
];
