<?php

require_once __DIR__ . '/helpers/ChatIdentity.php';
ChatIdentity::bootstrapSession();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Xác thực tài khoản</title>
    <link href="vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 60vh;">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h4 class="mb-3">Xác thực</h4>
                        <p class="text-muted">
                            Các bước xác thực hiện được thực hiện trong hệ thống chính.
                            Trang này không còn sử dụng nữa.
                        </p>
                        <a href="index.php" class="btn btn-primary">Về trang chat</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
