<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');
require_once('xuly.php')
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
                <h1 class="display-3 text-white mb-3 animated slideInDown">
                    <?php echo isset($_SESSION['edit_mode']) && $_SESSION['edit_mode'] ? 'Sửa Bàn' : 'Đặt Bàn'; ?>
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <?php if (isset($_SESSION['edit_mode']) && $_SESSION['edit_mode']): ?>
                        <li class="breadcrumb-item"><a href="index.php?page=profile" class="text-warning">Profile</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Sửa Bàn</li>
                        <?php else: ?>
                        <li class="breadcrumb-item text-white active" aria-current="page">Đặt Bàn</li>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Nội dung chọn bàn -->
        <div class="container my-5">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>
           
            
            <div class="text-center mb-4">
                <form method="POST" id="khuvucForm" action="index.php?page=booking">
                    <input type="hidden" name="datetime" value="<?php echo htmlspecialchars($datetime); ?>">
                    <?php if (isset($_SESSION['edit_mode']) && $_SESSION['edit_mode']): ?>
                    <input type="hidden" name="action" value="edit_tables">
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="madatban" value="<?php echo (int)$_SESSION['madatban']; ?>">
                    <?php endif; ?>
                    <label for="khuvuc">Chọn khu vực: </label>
                    <select name="khuvuc" id="khuvuc" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                        <option value="">-- Chọn khu vực bàn --</option>
                        <?php echo $selectKhuVuc; ?>
                    </select>
                </form>
                <h4 class="mt-3">Khu vực hiện tại: <strong id="tenKhuVuc"><?php echo htmlspecialchars($tenKhuVuc); ?></strong></h4>
            </div>

            <div class="selected-tables d-none" id="selectedTablesInfo">
                <h5>Bàn đã chọn:</h5>
                <div id="selectedTablesList"></div>
                <div class="mt-2">
                    Tổng số chỗ ngồi: <span class="total-capacity">0</span><br>
                    Tổng phụ phí: <span class="total-surcharge">0 VND</span>
                </div>
            </div>

            <div class="floor-layout">
                <h4 class="text-center mb-4"><?php echo htmlspecialchars($tenKhuVuc); ?></h4>

                <!-- Khu vực bàn nhỏ -->
                <div class="zone-container">
                    <?php
                    $smallZones = ['A', 'B'];
                    foreach ($smallZones as $zone):
                        $zoneTables = array_filter($dsBan, function($table) use ($zone) {
                            return $table['zone'] === $zone;
                        });
                    ?>
                    <div class="zone small-tables">
                        <div class="zone-title">Zone <?php echo $zone; ?></div>
                        <div class="table-grid">
                            <?php foreach ($zoneTables as $b): 
                                $isBooked = in_array($b['idban'], $dsBanDaDat);
                                $isMine = isset($dsBanCuaToi) && in_array($b['idban'], $dsBanCuaToi);
                                $disabled = $isBooked ? 'disabled' : '';
                                $title = $isBooked ? 'Bàn này đã được đặt hoặc tạm giữ' : 'Nhấn để chọn/bỏ chọn bàn';
                            ?>
                                <button class="table-btn <?php echo $b['soluongKH'] > 6 ? 'table-large' : 'table-small'; ?><?php echo $isMine ? ' selected selected-owned' : ''; ?><?php echo $isBooked ? ' booked' : ''; ?>" 
                                        data-maban="<?php echo $b['idban']; ?>"
                                        data-capacity="<?php echo $b['soluongKH']; ?>"
                                        data-soban="<?php echo htmlspecialchars($b['SoBan']); ?>"
                                        data-phuthu="<?php echo $phuThu; ?>"
                                        <?php echo $disabled; ?>
                                        title="<?php echo $title; ?>">
                                    <span class="table-type">
                                        <?php echo $b['soluongKH'] > 6 ? 'Bàn lớn' : 'Bàn nhỏ'; ?>
                                    </span>
                                    <span class="table-number"><?php echo htmlspecialchars($b['SoBan']); ?></span>
                                    <span class="capacity">
                                        <i class="fas fa-users"></i> <?php echo $b['soluongKH']; ?> người
                                    </span>
                                    <span class="surcharge">
                                        <i class="fas fa-money-bill"></i> <?php echo number_format($phuThu); ?>đ
                                    </span>
                                    <div class="capacity-icons">
                                        <?php for($i = 0; $i < min($b['soluongKH'], 5); $i++): ?>
                                            <i class="fas fa-user"></i>
                                        <?php endfor; ?>
                                        <?php if($b['soluongKH'] > 5): ?>
                                            <span>+<?php echo $b['soluongKH'] - 5; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($isBooked): ?>
                                        <span class="badge bg-danger">Đã đặt</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Khu vực bàn lớn -->
          
                <div class="zone-container">
                    <?php
                    $largeZones = ['C', 'D'];
                    foreach ($largeZones as $zone):
                        $zoneTables = array_filter($dsBan, function($table) use ($zone) {
                            return $table['zone'] === $zone;
                        });
                    ?>
                    <div class="zone large-tables">
                        <div class="zone-title">Zone <?php echo $zone; ?></div>
                        <div class="table-grid">
                            <?php foreach ($zoneTables as $b): 
                                $isBooked = in_array($b['idban'], $dsBanDaDat);
                                $isMine = isset($dsBanCuaToi) && in_array($b['idban'], $dsBanCuaToi);
                                $disabled = $isBooked ? 'disabled' : '';
                                $title = $isBooked ? 'Bàn này đã được đặt hoặc tạm giữ' : 'Nhấn để chọn/bỏ chọn bàn';
                            ?>
                                <button class="table-btn<?php echo $isMine ? ' selected selected-owned' : ''; ?><?php echo $isBooked ? ' booked' : ''; ?>" 
                                        data-maban="<?php echo $b['idban']; ?>"
                                        data-capacity="<?php echo $b['soluongKH']; ?>"
                                        data-soban="<?php echo htmlspecialchars($b['SoBan']); ?>"
                                        data-phuthu="<?php echo $phuThu; ?>"
                                        <?php echo $disabled; ?>
                                        title="<?php echo $title; ?>">
                                    <span class="table-number"><?php echo htmlspecialchars($b['SoBan']); ?></span>
                                    <span class="capacity">
                                        <i class="fas fa-users"></i> <?php echo $b['soluongKH']; ?> người
                                    </span>
                                    <span class="surcharge">
                                        <i class="fas fa-money-bill"></i> <?php echo number_format($phuThu); ?>đ
                                    </span>
                                    <div class="capacity-icons">
                                        <?php for($i = 0; $i < min($b['soluongKH'], 5); $i++): ?>
                                            <i class="fas fa-user"></i>
                                        <?php endfor; ?>
                                        <?php if($b['soluongKH'] > 5): ?>
                                            <span>+<?php echo $b['soluongKH'] - 5; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if($isBooked): ?>
                                        <span class="badge bg-danger">Đã đặt</span>
                                    <?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="error-message" class="error-message"></div>
            <form method="POST" action="index.php?page=choose_order_type" id="banForm">
                <input type="hidden" name="maban" id="mabanHidden">
                <input type="hidden" name="khuvuc" value="<?php echo htmlspecialchars($maKhuVuc); ?>">
                <input type="hidden" name="datetime" value="<?php echo htmlspecialchars($datetime); ?>">
                <!-- people_count will be set dynamically from selected tables -->
                <input type="hidden" name="people_count" id="peopleCountHidden" value="">
                <?php if (isset($_SESSION['edit_mode']) && $_SESSION['edit_mode']): ?>
                <input type="hidden" name="action" value="save_edit_tables">
                <input type="hidden" name="madatban" value="<?php echo (int)$_SESSION['madatban']; ?>">
                <?php endif; ?>
                <div class="mt-4 text-center">
                    <button type="button" class="btn btn-secondary me-2" onclick="handleBack()">Quay lại</button>
                    <?php if (isset($_SESSION['edit_mode']) && $_SESSION['edit_mode']): ?>
                        <button type="button" class="btn btn-primary" id="confirmButton" disabled onclick="submitForm('page/profile/save_tables_edit.php')">Lưu thay đổi</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" id="confirmButton" disabled data-bs-toggle="modal" data-bs-target="#selectMenuModal">Xác Nhận Đặt Bàn</button>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Modal hỏi chọn món -->
            <div class="modal fade" id="selectMenuModal" tabindex="-1" aria-labelledby="selectMenuModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="selectMenuModalLabel">Bạn có muốn đặt món ăn kèm không?</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Bàn của bạn đang được tạm giữ trong 15 phút để hoàn tất đơn hàng.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="submitForm('index.php?page=confirm_booking')">Không đặt món</button>
                            <button type="button" class="btn btn-primary" onclick="submitForm('index.php?page=choose_order_type')">Có đặt món</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const selectedTables = [];
                const confirmButton = document.getElementById('confirmButton');
                const selectedTablesInfo = document.getElementById('selectedTablesInfo');
                const selectedTablesList = document.getElementById('selectedTablesList');
                const totalCapacitySpan = document.querySelector('.total-capacity');
                const totalSurchargeSpan = document.querySelector('.total-surcharge');
                const errorMessage = document.getElementById('error-message');
                const mabanHidden = document.getElementById('mabanHidden');
                // people_count will be derived from selected tables; initialize to 0
                let requiredCapacity = 0;

                // Preselect các bàn của đơn đang sửa
                <?php if (isset($_SESSION['edit_mode']) && $_SESSION['edit_mode'] && !empty($dsBanCuaToi)): ?>
                const preselected = <?php echo json_encode(array_values($dsBanCuaToi)); ?>;
                document.querySelectorAll('.table-btn').forEach(btn => {
                    const id = parseInt(btn.dataset.maban);
                    if (preselected.includes(id)) {
                        const capacity = parseInt(btn.dataset.capacity);
                        const soban = btn.dataset.soban;
                        const phuthu = parseInt(btn.dataset.phuthu);
                        selectedTables.push({ maban: String(id), capacity, soban, phuthu });
                        btn.classList.add('selected');
                    }
                });
                updateSelectedTables();
                <?php endif; ?>

                function updateSelectedTables() {
                    let totalCapacity = 0;
                    let totalSurcharge = 0;
                    let tableInfo = [];
                    
                    selectedTables.forEach(table => {
                        totalCapacity += table.capacity;
                        totalSurcharge += table.phuthu;
                        tableInfo.push(`${table.soban} (${table.capacity} người, Phụ phí: ${table.phuthu.toLocaleString()}đ)`);
                    });

                    selectedTablesList.innerHTML = tableInfo.join(', ');
                    totalCapacitySpan.textContent = totalCapacity;
                    totalSurchargeSpan.textContent = totalSurcharge.toLocaleString() + ' VND';
                    selectedTablesInfo.classList.toggle('d-none', selectedTables.length === 0);


                    // Kiểm tra tối thiểu: chỉ cần chọn ít nhất một bàn. Số khách sẽ được tính dựa trên tổng sức chứa.
                    if (selectedTables.length === 0) {
                        errorMessage.textContent = 'Vui lòng chọn ít nhất một bàn.';
                        confirmButton.disabled = true;
                    } else {
                        errorMessage.textContent = '';
                        confirmButton.disabled = false;
                    }

                    // Cập nhật input hidden maban và cập nhật hidden people_count
                    mabanHidden.value = JSON.stringify(selectedTables);
                    const totalCapacityInput = document.getElementById('peopleCountHidden');
                    if (totalCapacityInput) {
                        totalCapacityInput.value = totalCapacity; // tổng chỗ ngồi = số khách
                        requiredCapacity = totalCapacity;
                    }
                }

                document.querySelectorAll('.table-btn').forEach(button => {
                    button.addEventListener('click', function () {
                        if (this.disabled) return;

                        const maban = this.dataset.maban;
                        const capacity = parseInt(this.dataset.capacity);
                        const soban = this.dataset.soban;
                        const phuthu = parseInt(this.dataset.phuthu);

                        const index = selectedTables.findIndex(t => t.maban === maban);
                        if (index === -1) {
                            selectedTables.push({ maban, capacity, soban, phuthu });
                            this.classList.add('selected');
                        } else {
                            selectedTables.splice(index, 1);
                            this.classList.remove('selected');
                        }

                        updateSelectedTables();
                    });
                });

                // Hàm submit form với action tùy chọn
                window.submitForm = function(action) {
                    console.log('submitForm called with action:', action);
                    console.log('Edit mode:', <?php echo isset($_SESSION['edit_mode']) && $_SESSION['edit_mode'] ? 'true' : 'false'; ?>);
                    
                    // Ensure people_count hidden input is set from requiredCapacity before submitting
                    const peopleCountInput = document.getElementById('peopleCountHidden');
                    if (peopleCountInput) {
                        peopleCountInput.value = requiredCapacity || peopleCountInput.value || 0;
                    }
                    
                    <?php if (isset($_SESSION['edit_mode']) && $_SESSION['edit_mode']): ?>
                    // Xử lý AJAX cho edit mode
                    console.log('Processing edit mode...');
                    const form = document.getElementById('banForm');
                    const formData = new FormData(form);
                    
                    // Debug form data
                    console.log('Form data:');
                    for (let [key, value] of formData.entries()) {
                        console.log(key, value);
                    }
                    
                    fetch(action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.success) {
                            Swal.fire({
                                title: 'Thành công!',
                                text: data.message || 'Cập nhật bàn thành công.',
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                window.location.href = data.redirect || 'index.php?page=profile#bookings';
                            });
                        } else {
                            Swal.fire({
                                title: 'Lỗi!',
                                text: data.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        Swal.fire({
                            title: 'Lỗi!',
                            text: 'Có lỗi xảy ra khi kết nối server',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    });
                    <?php else: ?>
                    // Xử lý bình thường cho booking mode
                    console.log('Processing normal booking mode...');
                    const form = document.getElementById('banForm');
                    form.action = action;
                    form.submit();
                    <?php endif; ?>
                };

                window.handleBack = function() {
                    <?php if (isset($_SESSION['edit_mode']) && $_SESSION['edit_mode']): ?>
                        window.location.href = 'index.php?page=profile#bookings';
                    <?php else: ?>
                        window.location.href = 'index.php?page=trangchu';
                    <?php endif; ?>
                }

                // Cập nhật hiển thị ngay nếu có preselect
                updateSelectedTables();
            });
        </script>
        <style>
            .table-btn.booked { background-color: #f8d7da; border-color: #f5c6cb; cursor: not-allowed; }
            .table-btn.selected { background-color: #d4edda; border-color: #c3e6cb; }
            .table-btn.selected-owned { box-shadow: 0 0 0 3px rgba(40,167,69,.35) inset; }
        </style>
    </div>
</body>
