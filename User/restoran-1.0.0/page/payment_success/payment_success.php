<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

// Minimal debug
if (!isset($_SESSION['debug'])) $_SESSION['debug'] = [];
$_SESSION['debug'][] = ['time'=>date('Y-m-d H:i:s'),'page'=>'payment_success.php','session'=>isset($_SESSION['booking'])?$_SESSION['booking']:'no_booking'];

$booking = isset($_SESSION['booking']) ? $_SESSION['booking'] : [];
$madatban = isset($_SESSION['madatban']) ? $_SESSION['madatban'] : null;
$payment_method = isset($_SESSION['payment_method']) ? $_SESSION['payment_method'] : 'cash';
$payment_expires = isset($_SESSION['payment_expires']) ? $_SESSION['payment_expires'] : null;

$selected_thucdon = isset($_SESSION['selected_thucdon']) ? $_SESSION['selected_thucdon'] : null;
$selected_monan = isset($_SESSION['selected_monan']) ? $_SESSION['selected_monan'] : [];
$selected_tables = isset($_SESSION['selected_tables']) ? $_SESSION['selected_tables'] : [];

// Compute total_monan
$total_monan = 0;
if ($selected_thucdon && !empty($selected_thucdon)) {
    if (isset($selected_thucdon['thucdon_info']) && isset($selected_thucdon['thucdon_info']['tongtien']) && is_numeric($selected_thucdon['thucdon_info']['tongtien'])) {
        $total_monan = (float)$selected_thucdon['thucdon_info']['tongtien'];
    } else {
        $monlist = isset($selected_thucdon['monan']) ? $selected_thucdon['monan'] : [];
        foreach ($monlist as $m) {
            $total_monan += (isset($m['DonGia'])? (float)$m['DonGia'] : 0) * (isset($m['soluong'])? (int)$m['soluong'] : 0);
        }
    }
} else {
    foreach ($selected_monan as $m) {
        $total_monan += (isset($m['DonGia'])? (float)$m['DonGia'] : 0) * (isset($m['soluong'])? (int)$m['soluong'] : 0);
    }
}

// Phu thu from tables if available
$total_phuthu = 0;
if (is_array($selected_tables) && !empty($selected_tables)) {
    foreach ($selected_tables as $t) {
        if (isset($t['phuthu'])) $total_phuthu += (float)$t['phuthu'];
    }
}
$total_all = $total_monan + $total_phuthu;
?>

<body>
    <div class="container my-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="text-success"><i class="fas fa-check-circle me-2"></i>Thanh toán thành công</h2>
                <p class="lead">Cám ơn bạn đã đặt bàn. Mã đặt bàn: <strong><?php echo htmlspecialchars($madatban ?? '—'); ?></strong></p>
                <div class="mb-3">
                    <h5>Tóm tắt đặt bàn</h5>
                    <ul class="list-unstyled">
                        <li><strong>Thời gian:</strong> <?php echo htmlspecialchars($booking['datetime'] ?? '—'); ?></li>
                        <li><strong>Số khách:</strong> <?php echo htmlspecialchars($booking['people_count'] ?? '—'); ?></li>
                        <li><strong>Bàn:</strong>
                            <?php
                                if (!empty($selected_tables)) {
                                    $labels = [];
                                    foreach ($selected_tables as $t) {
                                        $labels[] = 'Bàn ' . ($t['soban'] ?? $t['maban']);
                                    }
                                    echo htmlspecialchars(implode(', ', $labels));
                                } else {
                                    echo 'Thông tin bàn sẽ được gửi vào email hoặc xem ở chi tiết đặt bàn.';
                                }
                            ?>
                        </li>
                        <li><strong>Phương thức thanh toán:</strong> <?php echo ($payment_method==='online')? 'Thanh toán online' : 'Tiền mặt'; ?></li>
                    </ul>
                </div>

                <div class="mb-3">
                    <h5>Món / Thực đơn đã đặt</h5>
                    <?php if ($selected_thucdon && !empty($selected_thucdon)): ?>
                        <?php $td = $selected_thucdon['thucdon_info'] ?? null; ?>
                        <div class="mb-2">
                            <strong><?php echo htmlspecialchars($td['tenthucdon'] ?? ('Thực đơn #' . ($selected_thucdon['id_thucdon'] ?? ($selected_thucdon['idthucdon'] ?? '')))); ?></strong>
                            <?php if ($td && isset($td['tongtien'])): ?>
                                <div class="text-success"><?php echo number_format($td['tongtien']); ?> VND</div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($selected_thucdon['monan'])): ?>
                            <ul>
                                <?php foreach ($selected_thucdon['monan'] as $m): ?>
                                    <li><?php echo htmlspecialchars($m['tenmonan'] ?? 'Món'); ?> <span class="text-muted">(x<?php echo intval($m['soluong'] ?? 0); ?>)</span></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php elseif (!empty($selected_monan)): ?>
                        <ul>
                            <?php foreach ($selected_monan as $m): ?>
                                <li><?php echo htmlspecialchars($m['tenmonan'] ?? 'Món'); ?> <span class="text-muted">(x<?php echo intval($m['soluong'] ?? 0); ?>)</span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="small text-muted">Không có món hoặc thực đơn được chọn.</div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <h5>Tổng</h5>
                    <div class="d-flex justify-content-between">
                        <div>Tổng tiền món:</div>
                        <div class="text-primary"><?php echo number_format($total_monan); ?> VND</div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>Phụ phí khu vực:</div>
                        <div class="text-info"><?php echo number_format($total_phuthu); ?> VND</div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between fw-bold">
                        <div>Tổng cộng:</div>
                        <div class="text-success"><?php echo number_format($total_all); ?> VND</div>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="index.php?page=trangchu" class="btn btn-primary me-2">Về trang chủ</a>
                    <a href="index.php?page=my_bookings" class="btn btn-outline-secondary">Xem chi tiết đặt bàn</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
