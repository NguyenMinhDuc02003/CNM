<?php

require_once __DIR__ . '/helpers/ChatIdentity.php';
require_once __DIR__ . '/database/ChatUser.php';

ChatIdentity::bootstrapSession();

$error = '';
$statusMessage = '';

if (isset($_SESSION['user_data']) && !empty($_SESSION['user_data'])) {
    header('location:chatroom.php');
    exit;
}

$identity = ChatIdentity::resolve();

// Không có thông tin đăng nhập thì báo người dùng quay lại hệ thống chính.
if (!$identity) {
    $error = 'Vui lòng đăng nhập vào hệ thống (Admin hoặc Website khách hàng) trước khi mở trang chat.';
} else {
    try {
        // Đồng bộ thông tin nhân viên/khách hàng sang bảng chat_user_table.
        $user_object = new ChatUser;
        $user_object->setExternalId($identity['external_id']);
        $user_object->setUserType($identity['type']);
        $user_object->setUserEmail($identity['email']);
        $user_object->setUserName($identity['name']);
        $user_object->setUserProfile($identity['avatar']);

        // Đồng bộ user và nhận user_id tương ứng trong bảng chat_user_table.
        $user_id = $user_object->sync_system_identity();

        $user_token = bin2hex(random_bytes(20));
        $user_object->setUserId($user_id);
        $user_object->setUserLoginStatus('Login');
        $user_object->setUserToken($user_token);
        $user_object->update_user_login_data();

        // Lưu session theo định dạng cũ để chatroom/privatechat tái sử dụng.
		$_SESSION['user_data'][$user_id] = [
			'id' => $user_id,
			'name' => $identity['name'],
			'profile' => $identity['avatar'],
			'token' => $user_token,
			'type' => $identity['type'],
			'email' => $identity['email']
		];

		$_SESSION['active_chat_user_id'] = $user_id;

        header('location:chatroom.php');
        exit;
    } catch (Exception $e) {
        $error = 'Không thể đồng bộ tài khoản chat: ' . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CNM Chat Portal</title>
    <link href="vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="vendor-front/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <style>
        body {
            background-color: #f5f6fa;
        }
        .chat-bootstrap-card {
            max-width: 520px;
            margin: 120px auto;
        }
    </style>
</head>
<body>
    <div class="container chat-bootstrap-card">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white text-center">
                <h4>CNM Chat</h4>
            </div>
            <div class="card-body">
                <?php if ($identity): ?>
                    <div class="alert alert-info">
                        Đang liên kết tài khoản: <strong><?php echo htmlspecialchars(ChatIdentity::describeIdentity($identity)); ?></strong>. 
                        Cửa sổ sẽ tự chuyển đến phòng chat ngay khi hoàn tất.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger mb-0">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-muted text-center">
                Giữ nguyên giao diện chat gốc, chỉ thay đổi cách đăng nhập để dùng chung hệ thống.
            </div>
        </div>
    </div>

    <script src="vendor-front/jquery/jquery.min.js"></script>
    <script src="vendor-front/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
