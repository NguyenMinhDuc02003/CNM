<?php

require_once __DIR__ . '/helpers/ChatIdentity.php';
ChatIdentity::bootstrapSession();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông báo</title>
    <link href="vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 60vh;">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h4 class="mb-3">Đăng ký chat</h4>
                        <p class="text-muted">
                            Chức năng đăng ký đã được chuyển sang hệ thống chính (Admin &amp; Website khách hàng).
                            Vui lòng sử dụng tài khoản hiện có để truy cập <strong>CNM Chat</strong>.
                        </p>
                        <a href="index.php" class="btn btn-primary">Về trang chat</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
