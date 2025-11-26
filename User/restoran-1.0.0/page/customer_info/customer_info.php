<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');
require_once('xuly.php');
?>
<body>
    <div class="container-xxl bg-white p-0">
        <!-- Spinner -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Đang tải...</span>
            </div>
        </div>

        <!-- Hero Header -->
        <div class="container-xxl py-5 bg-dark hero-header mb-5">
            <div class="container text-center my-5 pt-5 pb-4">
                <h1 class="display-3 text-white mb-3 animated slideInDown">Thông Tin Khách Hàng</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Thông Tin</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Nội dung form -->
        <div class="container my-5">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow-lg">
                        <div class="card-header bg-primary text-white text-center">
                            <h4 class="mb-0"><i class="fas fa-user-edit me-2"></i>Thông Tin Khách Hàng</h4>
                            <?php if (isset($_SESSION['khachhang_id'])): ?>
                                <small class="text-light">
                                    <i class="fas fa-check-circle me-1"></i>Thông tin đã được tự động điền từ tài khoản của bạn
                                </small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="index.php?page=customer_info" id="customerForm">
                                <input type="hidden" name="maban" value="<?php echo htmlspecialchars($_POST['maban']); ?>">
                                <input type="hidden" name="total_tien" value="<?php echo htmlspecialchars($_POST['total_tien']); ?>">
                                <input type="hidden" name="payment_method" value="<?php echo htmlspecialchars($payment_method); ?>">
                                
                                <?php if (isset($_SESSION['khachhang_id'])): ?>
                                    <!-- Quick booking section for logged-in users -->
                                    <div class="alert alert-info d-flex align-items-center mb-4">
                                        <i class="fas fa-info-circle fa-2x me-3"></i>
                                        <div>
                                            <h6 class="mb-1">Đăng nhập thành công!</h6>
                                            <p class="mb-2">Thông tin của bạn đã được tự động điền. Bạn có thể:</p>
                                            <div class="d-flex gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('editMode').style.display='block'; this.parentElement.parentElement.parentElement.style.display='none'">
                                                    <i class="fas fa-edit me-1"></i>Chỉnh sửa thông tin
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div id="editMode" <?php echo isset($_SESSION['khachhang_id']) ? 'style="display:none"' : ''; ?>>
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="form-floating mb-4">
                                                <input type="text" class="form-control" id="tenKH" name="tenKH" 
                                                       value="<?php echo htmlspecialchars($user_info['tenKH']); ?>" 
                                                       required placeholder="Nhập họ tên đầy đủ">
                                                <label for="tenKH"><i class="fas fa-user me-2"></i>Họ và Tên</label>
                                            </div>
                                        </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-floating mb-4">
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($user_info['email']); ?>" 
                                                   required placeholder="example@email.com">
                                            <label for="email"><i class="fas fa-envelope me-2"></i>Email</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-floating mb-4">
                                            <input type="tel" class="form-control" id="soDienThoai" name="soDienThoai" 
                                                   value="<?php echo htmlspecialchars($user_info['soDienThoai']); ?>" 
                                                   required placeholder="0123456789">
                                            <label for="soDienThoai"><i class="fas fa-phone me-2"></i>Số Điện Thoại</label>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="order-summary-section mb-4">
                                    <h5 class="mb-3"><i class="fas fa-receipt me-2"></i>Tóm Tắt Đơn Hàng</h5>
                                    <div class="summary-box p-3 bg-light rounded">
                                        <!-- Booking details: tables, datetime, people count -->
                                        <div class="mb-2">
                                            <strong>Bàn đặt:</strong>
                                            <div>
                                                <?php
                                                    $tables_display = [];
                                                    $posted_tables = [];
                                                    if (!empty($_POST['maban'])) {
                                                        $posted_tables = json_decode($_POST['maban'], true);
                                                    }
                                                    if (is_array($posted_tables) && !empty($posted_tables)) {
                                                        foreach ($posted_tables as $t) {
                                                            $tables_display[] = 'Bàn ' . ($t['soban'] ?? $t['maban']);
                                                        }
                                                    }
                                                    echo !empty($tables_display) ? implode(', ', $tables_display) : 'Không có';
                                                ?>
                                            </div>
                                        </div>
                                        <div class="mb-2 d-flex justify-content-between">
                                            <span><strong>Thời gian:</strong></span>
                                            <span><?php echo htmlspecialchars($booking['datetime']); ?></span>
                                        </div>
                                        <div class="mb-2 d-flex justify-content-between">
                                            <span><strong>Số khách:</strong></span>
                                            <span><?php echo htmlspecialchars($_SESSION['booking']['people_count']); ?></span>
                                        </div>
                                        <hr>
                                        <!-- Items / Set-menu preview -->
                                        <div class="mb-2">
                                            <strong>Chọn món / Thực đơn:</strong>
                                            <div class="mt-2">
                                                <?php
                                                    // Prefer selected_thucdon if present
                                                    if (isset($_SESSION['selected_thucdon']) && !empty($_SESSION['selected_thucdon'])) {
                                                        $st = $_SESSION['selected_thucdon'];
                                                        $td_info = isset($st['thucdon_info']) ? $st['thucdon_info'] : (isset($st['thucdon']) ? $st['thucdon'] : null);
                                                        echo '<div class="small text-primary mb-1">';
                                                        if ($td_info && isset($td_info['tenthucdon'])) {
                                                            echo htmlspecialchars($td_info['tenthucdon']);
                                                        } elseif ($td_info && isset($td_info['idthucdon'])) {
                                                            echo 'Thực đơn #' . intval($td_info['idthucdon']);
                                                        } else {
                                                            echo 'Thực đơn đã chọn';
                                                        }
                                                        if ($td_info && isset($td_info['tongtien'])) {
                                                            echo ' — <span class="text-success">' . number_format($td_info['tongtien']) . ' VND</span>';
                                                        }
                                                        echo '</div>';
                                                        if (!empty($st['monan'])) {
                                                            echo '<ul class="mb-0 ms-3">';
                                                            foreach ($st['monan'] as $m) {
                                                                $name = htmlspecialchars($m['tenmonan'] ?? ($m['ten'] ?? 'Món'));
                                                                $qty = intval($m['soluong'] ?? ($m['quantity'] ?? 0));
                                                                echo "<li class=\"small\">{$name} <span class=\"text-muted\">(x{$qty})</span></li>";
                                                            }
                                                            echo '</ul>';
                                                        }
                                                    } elseif (isset($_SESSION['selected_monan']) && !empty($_SESSION['selected_monan'])) {
                                                        echo '<ul class="mb-0 ms-3">';
                                                        foreach ($_SESSION['selected_monan'] as $m) {
                                                            $name = htmlspecialchars($m['tenmonan'] ?? ($m['ten'] ?? 'Món'));
                                                            $qty = intval($m['soluong'] ?? ($m['quantity'] ?? 0));
                                                            echo "<li class=\"small\">{$name} <span class=\"text-muted\">(x{$qty})</span></li>";
                                                        }
                                                        echo '</ul>';
                                                    } else {
                                                        echo '<div class="small text-muted">Không có món hoặc thực đơn được chọn.</div>';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Phương thức thanh toán:</span>
                                            <span class="fw-bold">
                                                <?php if ($payment_method === 'online'): ?>
                                                    <i class="fas fa-credit-card text-primary"></i> Thanh toán online
                                                <?php else: ?>
                                                    <i class="fas fa-money-bill-wave text-success"></i> Tiền mặt
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Tổng tiền:</span>
                                            <span class="h5 text-success mb-0"><?php echo number_format($total_tien); ?> VND</span>
                                        </div>
                                        
                                        <?php if ($payment_method === 'online'): ?>
                                            <div class="alert alert-warning mt-3 mb-0">
                                                <i class="fas fa-clock me-2"></i>
                                                <strong>Thời hạn thanh toán:</strong> 6 giờ kể từ khi xác nhận.
                                                Sau thời gian này, đơn hàng sẽ tự động bị hủy.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <?php
                                        $selected_monan_json = isset($_SESSION['selected_monan']) ? json_encode($_SESSION['selected_monan']) : '';
                                        $selected_thucdon_json = isset($_SESSION['selected_thucdon']) ? json_encode($_SESSION['selected_thucdon']) : '';
                                    ?>
                                    <button type="button" id="backToConfirm" class="btn btn-outline-secondary w-100 mb-3"
                                        data-selected-monan='<?php echo htmlspecialchars($selected_monan_json, ENT_QUOTES); ?>'
                                        data-selected-thucdon='<?php echo htmlspecialchars($selected_thucdon_json, ENT_QUOTES); ?>'
                                    >
                                        <i class="fas fa-arrow-left me-2"></i>Quay lại xác nhận đặt bàn
                                    </button>
                                    <button type="submit" class="btn btn-primary w-100 btn-lg" id="submitBtn">
                                        <i class="fas fa-check me-2"></i>
                                        <?php if ($payment_method === 'online'): ?>
                                            <?php if (isset($_SESSION['khachhang_id'])): ?>
                                                <span class="submit-text">Tiến hành thanh toán</span>
                                                <span class="loading-text" style="display:none">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...
                                                </span>
                                            <?php else: ?>
                                                Tiến hành thanh toán
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if (isset($_SESSION['khachhang_id'])): ?>
                                                <span class="submit-text">Xác nhận đặt bàn</span>
                                                <span class="loading-text" style="display:none">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...
                                                </span>
                                            <?php else: ?>
                                                Xác nhận đặt bàn
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('customerForm');
            const submitBtn = document.getElementById('submitBtn');
            
            // Handle form submission with loading state
            form.addEventListener('submit', function(e) {
                const submitText = submitBtn.querySelector('.submit-text');
                const loadingText = submitBtn.querySelector('.loading-text');
                
                if (submitText && loadingText) {
                    submitText.style.display = 'none';
                    loadingText.style.display = 'inline';
                    submitBtn.disabled = true;
                }
            });
            
            // Auto-focus on first empty field
            const inputs = form.querySelectorAll('input[required]');
            for (let input of inputs) {
                if (!input.value.trim()) {
                    input.focus();
                    break;
                }
            }

            // Back to confirm: tạo form POST động
            const backBtn = document.getElementById('backToConfirm');
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    const selectedMonan = this.dataset.selectedMonan || '';
                    const selectedThucdon = this.dataset.selectedThucdon || '';
                    const maban = form.querySelector('input[name="maban"]').value || '';
                    const totalTien = form.querySelector('input[name="total_tien"]').value || '';

                    const f = document.createElement('form');
                    f.method = 'POST';
                    f.action = 'index.php?page=confirm_booking';

                    const iMaban = document.createElement('input'); iMaban.type='hidden'; iMaban.name='maban'; iMaban.value = maban; f.appendChild(iMaban);
                    const iTotal = document.createElement('input'); iTotal.type='hidden'; iTotal.name='total_tien'; iTotal.value = totalTien; f.appendChild(iTotal);
                    if (selectedMonan) { const im = document.createElement('input'); im.type='hidden'; im.name='selected_monan'; im.value = selectedMonan; f.appendChild(im); }
                    if (selectedThucdon) { const it = document.createElement('input'); it.type='hidden'; it.name='selected_thucdon'; it.value = selectedThucdon; f.appendChild(it); }

                    document.body.appendChild(f);
                    f.submit();
                });
            }
        });
    </script>
</body>
