<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../includes/auth.php';

admin_auth_logout();

header('Location: dangnhap.php?status=logged_out');
exit();
