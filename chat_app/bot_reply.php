<?php
// Simple proxy to gọi GPT API cho widget chat
// Yêu cầu: điền BOT_API_KEY trong config/bot.php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/helpers/ChatIdentity.php';
require_once __DIR__ . '/config/bot.php';
require_once __DIR__ . '/database/Database_connection.php';

ChatIdentity::bootstrapSession();
$identity = ChatIdentity::resolve();

// Cho phép bypass khi có BOT_TEST_TOKEN và test_token khớp (phục vụ test thủ công qua curl).
$requestTestToken = isset($_REQUEST['test_token']) ? trim((string)$_REQUEST['test_token']) : '';
if (!$identity && defined('BOT_TEST_TOKEN') && BOT_TEST_TOKEN !== '' && $requestTestToken === BOT_TEST_TOKEN) {
    $identity = [
        'external_id' => 'test-user',
        'type' => 'customer',
        'email' => 'test@example.com',
        'name' => 'Chat Tester',
        'avatar' => 'https://www.gravatar.com/avatar/?d=mp'
    ];
}

// Cho phép khách lẻ dùng chatbot không cần đăng nhập
if (!$identity) {
    $guestId = $_SESSION['bot_guest_id'] ?? bin2hex(random_bytes(8));
    $_SESSION['bot_guest_id'] = $guestId;
    $identity = [
        'external_id' => $guestId,
        'type' => 'guest',
        'email' => '',
        'name' => 'Khách vãng lai',
        'avatar' => 'https://www.gravatar.com/avatar/?d=mp'
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

$userMessage = trim($_POST['message'] ?? '');
$history = $_POST['history'] ?? [];

if ($userMessage === '') {
    echo json_encode(['success' => false, 'message' => 'Nội dung trống']);
    exit;
}

if (!defined('BOT_API_KEY') || BOT_API_KEY === '') {
    echo json_encode(['success' => false, 'message' => 'Chưa cấu hình BOT_API_KEY trong chat_app/config/bot.php']);
    exit;
}

$model = BOT_MODEL ?? 'gpt-4.1-mini';
$systemPrompt = BOT_SYSTEM_PROMPT ?? 'You are a helpful restaurant assistant.';

$messages = [
    ['role' => 'system', 'content' => $systemPrompt],
];

// Lấy danh sách món bán chạy và luật gợi ý (đã tính offline) từ chatbot_item_rules
$popularItems = [];
$popularRules = [];
try {
    $db = new Database_connection();
    $pdo = $db->connect();
    $stmt = $pdo->prepare("
        SELECT m.tenmonan AS name, COALESCE(m.DonViTinh, '') AS unit, COALESCE(m.DonGia, 0) AS price, SUM(ct.SoLuong) AS total_qty
        FROM chitietdonhang ct
        INNER JOIN monan m ON ct.idmonan = m.idmonan
        WHERE ct.TrangThai <> 'cancelled' AND ct.created_at >= (NOW() - INTERVAL 30 DAY)
        GROUP BY ct.idmonan, m.tenmonan, m.DonViTinh, m.DonGia
        ORDER BY total_qty DESC, m.tenmonan ASC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', 10, \PDO::PARAM_INT);
    $stmt->execute();
    $popularItems = $stmt->fetchAll();

    // Luật gợi ý đã tính offline (1 chiều A -> B)
    $ruleStmt = $pdo->prepare("
        SELECT r.lhs_ids, r.rhs_id, r.confidence, r.support_count, r.lift,
               m_rhs.tenmonan AS rhs_name,
               m_lhs.tenmonan AS lhs_name
        FROM chatbot_item_rules r
        INNER JOIN monan m_rhs ON r.rhs_id = m_rhs.idmonan
        LEFT JOIN monan m_lhs ON r.lhs_ids = m_lhs.idmonan
        ORDER BY r.confidence DESC, r.support_count DESC, m_rhs.tenmonan ASC
        LIMIT :ruleLimit
    ");
    $ruleStmt->bindValue(':ruleLimit', 10, \PDO::PARAM_INT);
    $ruleStmt->execute();
    $popularRules = $ruleStmt->fetchAll();
} catch (\Throwable $e) {
    error_log('bot_reply.php: fetch popular items/rules failed - ' . $e->getMessage());
}

if (!empty($popularItems)) {
    $lines = [];
    foreach ($popularItems as $item) {
        $priceStr = ((float)$item['price'] > 0) ? number_format((float)$item['price'], 0, ',', '.') . 'đ' : '';
        $unitStr = $item['unit'] !== '' ? ' (' . $item['unit'] . ')' : '';
        $lines[] = sprintf(
            '%s%s%s – đã gọi %d lần',
            $item['name'],
            $unitStr,
            $priceStr ? ' ~ ' . $priceStr : '',
            (int)$item['total_qty']
        );
    }

    $messages[] = [
        'role' => 'system',
        'content' => "Dữ liệu món bán chạy (từ bảng chitietdonhang):\n- " . implode("\n- ", $lines)
    ];
}

if (!empty($popularRules)) {
    $ruleLines = [];
    foreach ($popularRules as $rule) {
        $lhsName = $rule['lhs_name'] ?? ('Món ' . $rule['lhs_ids']);
        $rhsName = $rule['rhs_name'] ?? ('Món ' . $rule['rhs_id']);
        $ruleLines[] = sprintf(
            '%s -> %s (confidence %.0f%%, %d đơn)',
            $lhsName,
            $rhsName,
            $rule['confidence'] * 100,
            (int)$rule['support_count']
        );
    }
    $messages[] = [
        'role' => 'system',
        'content' => "Luật gợi ý đã tính sẵn (30 ngày):\n- " . implode("\n- ", $ruleLines)
    ];
}

// Nếu cần giữ ngữ cảnh, history có dạng [{role: 'user'|'assistant', content: '...'}]
if (is_array($history)) {
    foreach ($history as $h) {
        if (!isset($h['role'], $h['content'])) {
            continue;
        }
        $role = $h['role'] === 'assistant' ? 'assistant' : 'user';
        $content = trim((string)$h['content']);
        if ($content !== '') {
            $messages[] = ['role' => $role, 'content' => $content];
        }
    }
}

$messages[] = [
    'role' => 'user',
    'content' => $userMessage,
];

$payload = [
    'model' => $model,
    'messages' => $messages,
    'temperature' => 0.6,
    'max_tokens' => 400,
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . BOT_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 30,
]);

$raw = curl_exec($ch);
$err = curl_error($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($raw === false) {
    echo json_encode(['success' => false, 'message' => 'Không gọi được API: ' . $err]);
    exit;
}

$data = json_decode($raw, true);
$reply = $data['choices'][0]['message']['content'] ?? '';
if ($reply === '') {
    $reply = $data['error']['message'] ?? 'Không nhận được phản hồi từ chatbot.';
}

echo json_encode([
    'success' => true,
    'reply' => $reply,
    'raw_status' => $status,
]);
