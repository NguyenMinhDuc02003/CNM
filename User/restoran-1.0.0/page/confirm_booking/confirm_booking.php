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
                <h1 class="display-3 text-white mb-3 animated slideInDown">Xác Nhận Đặt Bàn</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Xác Nhận</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Nội dung xác nhận -->
        <div class="container my-5">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <!-- Thông tin đặt bàn -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-primary text-white">
                            <h4 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Tóm Tắt Đặt Bàn</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                        <strong>Khu vực:</strong> <?php echo htmlspecialchars($tenKhuVuc); ?>
                                    </div>
                                    <div class="info-item mb-3">
                                        <i class="fas fa-clock text-primary me-2"></i>
                                        <strong>Thời gian:</strong> <?php echo date('d/m/Y H:i', strtotime($booking['datetime'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item mb-3">
                                        <i class="fas fa-users text-primary me-2"></i>
                                        <strong>Số lượng người:</strong> <?php echo htmlspecialchars($booking['people_count']); ?>
                                    </div>
                                    <div class="info-item mb-3">
                                        <i class="fas fa-chair text-primary me-2"></i>
                                        <strong>Bàn đã chọn:</strong> <?php echo implode(', ', array_column($tables, 'soban')); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            
                    <!-- Danh sách món ăn -->
                    <?php if (!empty($selected_monan)): ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <h4 class="mb-0"><i class="fas fa-utensils me-2"></i>Danh Sách Món Ăn</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th><i class="fas fa-utensils me-1"></i>Món ăn</th>
                                                <th><i class="fas fa-hashtag me-1"></i>Số lượng</th>
                                                <th><i class="fas fa-money-bill-wave me-1"></i>Đơn giá</th>
                                                <th><i class="fas fa-calculator me-1"></i>Thành tiền</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($selected_monan as $mon): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($mon['tenmonan']); ?></td>
                                                    <td><span class="badge bg-primary"><?php echo $mon['soluong']; ?></span></td>
                                                    <td><?php echo number_format($mon['DonGia']); ?> VND</td>
                                                    <td class="fw-bold text-success"><?php echo number_format($mon['DonGia'] * $mon['soluong']); ?> VND</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot class="table-warning">
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold">Tổng tiền:</td>
                                                <td class="fw-bold text-success"><?php echo number_format($total_monan); ?> VND</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card shadow-sm mb-4">
                            <div class="card-body text-center text-muted">
                                <i class="fas fa-utensils fa-3x mb-3"></i>
                                <h5>Chưa chọn món ăn</h5>
                                <p>Bạn đã chọn đặt bàn không kèm món ăn</p>
                            </div>
                        </div>
                    <?php endif; ?>
            
                    <!-- Phụ phí khu vực -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-info text-white">
                            <h4 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Phụ Phí Khu Vực</h4>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th><i class="fas fa-chair me-1"></i>Bàn</th>
                                            <th><i class="fas fa-money-bill-wave me-1"></i>Phụ phí</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tables as $table): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($table['soban']); ?></td>
                                                <td class="text-success fw-bold"><?php echo number_format($table['phuthu']); ?> VND</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-info">
                                        <tr>
                                            <td class="text-end fw-bold">Tổng phụ phí:</td>
                                            <td class="fw-bold text-success"><?php echo number_format($total_phuthu); ?> VND</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Tổng cộng và phương thức thanh toán -->
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4 position-sticky" style="top: 20px;">
                        <div class="card-header bg-warning text-dark">
                            <h4 class="mb-0"><i class="fas fa-receipt me-2"></i>Tổng Hóa Đơn</h4>
                        </div>
                        <div class="card-body">
                            <div class="summary-section">
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Tổng tiền thực đơn đã chọn:</span>
                                    <span class="fw-bold"><?php echo number_format($total_monan); ?> VND</span>
                                </div>
                                <div class="d-flex justify-content-between mb-3">
                                    <span>Phụ phí khu vực:</span>
                                    <span class="fw-bold text-info"><?php echo number_format($total_phuthu); ?> VND</span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="h5 mb-0">Tổng cộng:</span>
                                    <span class="h4 mb-0 text-success"><?php echo number_format($total_tien); ?> VND</span>
                                </div>
                            </div>
                            
                            <!-- Đặt cọc online -->
                            <div class="payment-method-section mt-4">
                                <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Đặt Cọc Online</h5>
                                <form method="POST" action="index.php?page=customer_info" id="bookingForm">
                                    <input type="hidden" name="maban" value="<?php echo htmlspecialchars($tables_json); ?>">
                                    <input type="hidden" name="total_tien" value="<?php echo $total_tien; ?>">
                                    <input type="hidden" name="payment_method" id="payment_method" value="online">
                                    
                                    <div class="payment-options">
                                        <div class="form-check payment-option mb-3 border-warning bg-warning-light">
                                            <input class="form-check-input" type="radio" name="payment_type" id="online_payment" value="online" checked>
                                            <label class="form-check-label" for="online_payment">
                                                <div class="payment-card">
                                                    <i class="fas fa-credit-card text-primary fa-2x mb-2"></i>
                                                    <h6>Đặt cọc online</h6>
                                                    <small class="text-muted">QR Code</small>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-warning mt-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>Lưu ý:</strong> 
                                        <ul class="mb-0 mt-2">
                                            <li>Bạn cần đặt cọc <strong>50%</strong> tổng giá trị đơn hàng để xác nhận đặt bàn</li>
                                            <li>Bàn của bạn sẽ được giữ trong <strong>6 giờ</strong> để chờ thanh toán cọc</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="action-buttons mt-4">
                                        <?php 
                                        // Xác định trang quay lại dựa vào cách đặt món
                                        $back_page = 'choose_order_type';
                                        $back_text = 'Quay lại chọn cách đặt món';

                                        // Nếu user vừa POST maban mà không kèm selected_monan/selected_thucdon,
                                        // nghĩa là họ đã chọn 'Không' (không chọn món) -> quay lại trang chọn bàn
                                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maban']) && empty($_POST['selected_monan']) && empty($_POST['selected_thucdon'])) {
                                            $back_page = 'booking';
                                            $back_text = 'Quay lại chọn bàn';
                                        } elseif (!empty($selected_monan)) {
                                            // Kiểm tra xem có phải từ thực đơn không
                                            if (isset($_SESSION['selected_thucdon']) && !empty($_SESSION['selected_thucdon'])) {
                                                $back_page = 'book_thucdon';
                                                $back_text = 'Quay lại chọn thực đơn';
                                            } else {
                                                $back_page = 'book_menu';
                                                $back_text = 'Quay lại chọn món';
                                            }
                                        }
                                        ?>
                                        <!-- JS-driven button: tránh nested forms (tránh submit nhầm bookingForm -> customer_info) -->
                                        <button type="button" id="backButton" class="btn btn-outline-secondary w-100 mb-3"
                                            data-back-page="<?php echo $back_page; ?>"
                                            data-maban='<?php echo htmlspecialchars($tables_json, ENT_QUOTES); ?>'
                                            data-selected-monan='<?php echo htmlspecialchars(!empty($selected_monan)?json_encode($selected_monan):'', ENT_QUOTES); ?>'
                                            data-selected-thucdon='<?php echo htmlspecialchars(isset($_SESSION['selected_thucdon'])?json_encode($_SESSION['selected_thucdon']):'', ENT_QUOTES); ?>'
                                            data-khuvuc='<?php echo htmlspecialchars($booking['khuvuc'] ?? '', ENT_QUOTES); ?>'
                                            data-datetime='<?php echo htmlspecialchars($booking['datetime'] ?? '', ENT_QUOTES); ?>'
                                        >
                                            <i class="fas fa-arrow-left me-2"></i><?php echo $back_text; ?>
                                        </button>
                                        <button type="submit" class="btn btn-primary w-100 btn-lg">
                                            <i class="fas fa-arrow-right me-2"></i>Tiếp theo
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Xử lý form submission với payment method (chỉ đặt cọc online)
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethodInput = document.getElementById('payment_method');
            
            // Đảm bảo payment method luôn là 'online'
            paymentMethodInput.value = 'online';
        });
        
        // Xử lý nút Quay lại: tạo form POST động để tránh nested form submit
        document.addEventListener('DOMContentLoaded', function() {
            const backBtn = document.getElementById('backButton');
            if (!backBtn) return;

            backBtn.addEventListener('click', function() {
                const backPage = this.dataset.backPage || 'choose_order_type';
                const maban = this.dataset.maban || '';
                const selectedMonan = this.dataset.selectedMonan || '';
                const selectedThucdon = this.dataset.selectedThucdon || '';
                const khuvuc = this.dataset.khuvuc || '';
                const datetime = this.dataset.datetime || '';

                // Tạo form động
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'index.php?page=' + encodeURIComponent(backPage);

                const inputMaban = document.createElement('input');
                inputMaban.type = 'hidden';
                inputMaban.name = 'maban';
                inputMaban.value = maban;
                form.appendChild(inputMaban);

                if (selectedMonan) {
                    const inputMonan = document.createElement('input');
                    inputMonan.type = 'hidden';
                    inputMonan.name = 'selected_monan';
                    inputMonan.value = selectedMonan;
                    form.appendChild(inputMonan);
                }

                if (selectedThucdon) {
                    const inputThucdon = document.createElement('input');
                    inputThucdon.type = 'hidden';
                    inputThucdon.name = 'selected_thucdon';
                    inputThucdon.value = selectedThucdon;
                    form.appendChild(inputThucdon);
                }

                // Nếu quay lại trang booking, đính kèm khuvuc và datetime để restore state
                if (backPage === 'booking') {
                    const inputKhu = document.createElement('input');
                    inputKhu.type = 'hidden';
                    inputKhu.name = 'khuvuc';
                    inputKhu.value = khuvuc;
                    form.appendChild(inputKhu);

                    const inputDt = document.createElement('input');
                    inputDt.type = 'hidden';
                    inputDt.name = 'datetime';
                    inputDt.value = datetime;
                    form.appendChild(inputDt);
                }

                document.body.appendChild(form);
                form.submit();
            });
        });
    </script>
</body>
