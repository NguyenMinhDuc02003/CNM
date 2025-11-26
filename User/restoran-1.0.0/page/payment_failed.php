<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Đảm bảo session được khởi tạo
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error_message = $_SESSION['payment_error'] ?? 'Có lỗi xảy ra trong quá trình thanh toán.';
unset($_SESSION['payment_error']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán thất bại - Restoran</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 50px auto;
            max-width: 600px;
        }
        .error-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .error-body {
            padding: 40px;
        }
        .error-icon {
            font-size: 4rem;
            color: #e74c3c;
            margin-bottom: 20px;
        }
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .suggestions {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            color: #0d47a1;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        .btn-outline-danger {
            border: 2px solid #e74c3c;
            color: #e74c3c;
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .btn-outline-danger:hover {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="error-container">
            <!-- Header -->
            <div class="error-header">
                <div class="error-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h1>Thanh toán thất bại</h1>
                <p class="mb-0">Đã có lỗi xảy ra trong quá trình thanh toán</p>
            </div>

            <!-- Body -->
            <div class="error-body">
                <!-- Thông báo lỗi -->
                <div class="error-message">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Chi tiết lỗi</h5>
                    <p class="mb-0"><?php echo htmlspecialchars($error_message); ?></p>
                </div>

                <!-- Gợi ý khắc phục -->
                <div class="suggestions">
                    <h5><i class="fas fa-lightbulb me-2"></i>Gợi ý khắc phục</h5>
                    <ul class="mb-0">
                        <li>Kiểm tra kết nối internet của bạn</li>
                        <li>Đảm bảo tài khoản ngân hàng có đủ số dư</li>
                        <li>Thử lại với phương thức thanh toán khác</li>
                        <li>Liên hệ ngân hàng để kiểm tra tài khoản</li>
                        <li>Thử lại sau vài phút</li>
                    </ul>
                </div>

                <!-- Thông tin hỗ trợ -->
                <div class="alert alert-info">
                    <h6><i class="fas fa-headset me-2"></i>Hỗ trợ khách hàng</h6>
                    <p class="mb-2">Nếu bạn gặp khó khăn, vui lòng liên hệ:</p>
                    <ul class="mb-0">
                        <li><strong>Hotline:</strong> 0123 456 789</li>
                        <li><strong>Email:</strong> support@restoran.com</li>
                        <li><strong>Thời gian:</strong> 8:00 - 22:00 hàng ngày</li>
                    </ul>
                </div>

                <!-- Nút hành động -->
                <div class="text-center mt-4">
                    <a href="index.php?page=trangchu" class="btn btn-primary me-3">
                        <i class="fas fa-home me-2"></i>Về trang chủ
                    </a>
                    <button type="button" class="btn btn-outline-danger" onclick="history.back()">
                        <i class="fas fa-redo me-2"></i>Thử lại
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
</body>
</html>