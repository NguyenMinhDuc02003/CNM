<?php

require_once __DIR__ . '/helpers/ChatIdentity.php';
ChatIdentity::bootstrapSession();

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thông tin tài khoản</title>
    <link href="vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-white">
    <div class="container">
        <div class="row justify-content-center align-items-center" style="min-height: 60vh;">
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-body text-center">
                        <h4 class="mb-3">Quản lý hồ sơ</h4>
                        <p class="text-muted">
                            Hồ sơ nhân viên và khách hàng được quản lý trực tiếp tại hệ thống hiện có.
                            Mọi chỉnh sửa vui lòng thực hiện tại Admin Portal hoặc Trang khách hàng.
                        </p>
                        <a href="index.php" class="btn btn-primary">Quay lại chat</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
