<?php

if (!defined('BOOKING_QR_SECRET')) {
    // TODO: đổi sang chuỗi bí mật riêng của bạn (tối thiểu 32 ký tự ngẫu nhiên)
    define('BOOKING_QR_SECRET', 'restoran_qr_secret_key_2024');
}

if (!defined('BOOKING_QR_BASE_URL')) {
    define('BOOKING_QR_BASE_URL', 'http://localhost/CNM/User/restoran-1.0.0/page/view_booking/view_booking.php');
}

function generate_booking_signature($bookingId, $timestamp)
{
    return hash_hmac('sha256', $bookingId . '|' . $timestamp, BOOKING_QR_SECRET);
}

function build_booking_qr_url($bookingId, $timestamp)
{
    $sig = generate_booking_signature($bookingId, $timestamp);
    return BOOKING_QR_BASE_URL . '?booking=' . urlencode($bookingId) . '&ts=' . urlencode($timestamp) . '&sig=' . urlencode($sig);
}

