<?php
// Bắt đầu session nếu chưa được bắt đầu
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$customerId = $_SESSION['khachhang_id'] ?? null;

if ($customerId) {
    try {
        require_once __DIR__ . '../../../../../chat_app/config/chat.php';
        require_once __DIR__ . '../../../../../chat_app/database/Database_connection.php';

        $chatDb = new Database_connection();
        $pdo = $chatDb->connect();

        $stmt = $pdo->prepare("SELECT user_id FROM chat_user_table WHERE external_id = :external_id AND user_type = 'customer' LIMIT 1");
        $stmt->execute([':external_id' => $customerId]);
        $chatUserId = $stmt->fetchColumn();

        if ($chatUserId) {
            $chatActionUrl = rtrim(CHAT_APP_BASE_URL, '/') . '/action.php';
            $ch = curl_init($chatActionUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query(['action' => 'leave', 'user_id' => $chatUserId]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 2,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    } catch (Exception $e) {
        // Ghi log nếu cần nhưng không chặn logout
        error_log('[User Logout][Chat] ' . $e->getMessage());
    }
}

// Xóa tất cả các biến session
$_SESSION = array();

// Xóa cookie session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Hủy session
session_destroy();

// Chuyển hướng về trang chủ
header("Location: index.php");
exit;
?>
