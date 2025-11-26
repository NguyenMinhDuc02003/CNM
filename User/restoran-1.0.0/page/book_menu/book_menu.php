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
                <h1 class="display-3 text-white mb-3 animated slideInDown">Chọn Món Ăn</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <li class="breadcrumb-item"><a href="index.php?page=booking" class="text-warning">Đặt Bàn</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Chọn Món</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Nội dung chọn món ăn -->
        <div class="container my-5">
            <?php if (!isset($_SESSION['booking'])): ?>
                <div class="alert alert-warning">
                    Vui lòng hoàn tất bước đặt bàn trước khi chọn món. <a href="index.php?page=booking">Quay lại đặt bàn</a>.
                </div>
            <?php else: ?>
                <div class="menu-container">
                    <!-- Danh sách món ăn -->
                    <div class="menu-left">
                        <form id="menu-filter-form" class="mb-4" onsubmit="event.preventDefault(); filterMenu();">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <select id="danhmuc" class="form-select">
                                        <option value="">Tất cả danh mục</option>
                                        <?php foreach ($danhMucList as $dm): ?>
                                            <option value="<?= $dm['iddm'] ?>">
                                                <?= htmlspecialchars($dm['tendanhmuc']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" id="search" class="form-control" placeholder="Tìm kiếm món ăn...">
                                    <button type="submit" class="btn btn-primary mt-2">Tìm</button>
                                </div>
                            </div>
                        </form>

                        <div id="menu-list">
                            <?php if (empty($monAnList)): ?>
                                <p>Không tìm thấy món ăn nào.</p>
                            <?php else: ?>
                                <?php foreach ($monAnList as $mon): ?>
                                    <div class="menu-item">
                                        <img src="img/<?= htmlspecialchars($mon['hinhanh'] ?: 'default.jpg') ?>" alt="<?= htmlspecialchars($mon['tenmonan']) ?>">
                                        <div class="menu-item-details">
                                            <div>
                                                <strong class="text-primary"><?= htmlspecialchars($mon['tenmonan']) ?></strong><br>
                                                <p class="text-muted mb-2"><?= htmlspecialchars($mon['mota'] ?: 'Không có mô tả') ?></p>
                                                <strong class="text-success">Giá: <?= number_format($mon['DonGia']) ?> VND</strong>
                                            </div>
                                            <button class="btn-choose" onclick="addMonAn(<?= $mon['idmonan'] ?>, '<?= addslashes($mon['tenmonan']) ?>', <?= $mon['DonGia'] ?>)">
                                                <i class="fas fa-utensils me-2"></i>Chọn món ăn
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tóm tắt đơn hàng -->
                    <div class="menu-right">
                        <h3><i class="fas fa-receipt me-2"></i>Tóm tắt đơn hàng</h3>
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Số người: <?php echo $booking['people_count']; ?> • 
                                Tối đa: <?php echo $max_mon; ?> món
                            </small>
                        </div>

                        <table class="summary-table" id="order-summary">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-utensils me-1"></i>Món ăn</th>
                                    <th><i class="fas fa-hashtag me-1"></i>SL</th>
                                    <th><i class="fas fa-money-bill-wave me-1"></i>Giá</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>

                        <div class="total-section">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Tổng tiền món:</span>
                                <strong id="total-tien" class="text-primary">0 VND</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Phụ phí khu vực:</span>
                                <strong class="text-info"><?php echo number_format(array_sum(array_column($tables, 'phuthu'))); ?> VND</strong>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">Tổng cộng:</span>
                                <strong id="total-all" class="text-success fs-5">0 VND</strong>
                            </div>
                        </div>
                        
                        <div id="error-message" class="error mt-3"></div>
                        <form method="POST" action="index.php?page=confirm_booking">
                            <input type="hidden" name="maban" value="<?= htmlspecialchars($_POST['maban']) ?>">
                            <input type="hidden" name="selected_monan" id="selected_monan">
                            <button type="submit" class="btn btn-primary w-100">Tiếp theo</button>
                        </form>
                        <form method="POST" action="index.php?page=choose_order_type" class="mt-2">
                            <input type="hidden" name="maban" value="<?= htmlspecialchars($_POST['maban']) ?>">
                            <button type="submit" class="btn btn-back w-100">Quay lại</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js"></script>
    <script>
        let selectedMonAn = <?php echo isset($_SESSION['selected_monan']) ? json_encode($_SESSION['selected_monan']) : '[]'; ?>;
        const maxMon = <?php echo $max_mon; ?>;

        function addMonAn(idmonan, tenmonan, DonGia) {
            const totalSoluong = selectedMonAn.reduce((sum, m) => sum + m.soluong, 0);
            if (totalSoluong >= maxMon) {
                document.getElementById('error-message').textContent = `Bạn chỉ có thể chọn tối đa ${maxMon} món cho <?php echo $booking['people_count']; ?> người.`;
                return;
            }
            const existing = selectedMonAn.find(m => m.idmonan === idmonan);
            if (existing) {
                existing.soluong++;
            } else {
                selectedMonAn.push({ idmonan, tenmonan, DonGia, soluong: 1 });
            }
            updateSummary();
            saveOrder();
        }

        function removeMonAn(idmonan) {
            const index = selectedMonAn.findIndex(m => m.idmonan === idmonan);
            if (index !== -1) {
                selectedMonAn[index].soluong--;
                if (selectedMonAn[index].soluong === 0) {
                    selectedMonAn.splice(index, 1);
                }
            }
            updateSummary();
            saveOrder();
        }

        function updateSummary() {
            const summary = document.querySelector('#order-summary tbody');
            let total = 0;
            summary.innerHTML = '';
            
            if (selectedMonAn.length === 0) {
                summary.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            <i class="fas fa-utensils fa-2x mb-2 d-block"></i>
                            Chưa chọn món ăn nào<br>
                            <small>Vui lòng chọn món ăn từ danh sách bên trái</small>
                        </td>
                    </tr>`;
            } else {
                selectedMonAn.forEach(mon => {
                    const subtotal = mon.DonGia * mon.soluong;
                    total += subtotal;
                    summary.innerHTML += `
                        <tr>
                            <td>${mon.tenmonan}</td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-bold">${mon.soluong}</span>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-danger btn-quantity" onclick="removeMonAn(${mon.idmonan})">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-quantity" onclick="addMonAn(${mon.idmonan}, '${mon.tenmonan.replace(/'/g, "\\'")}', ${mon.DonGia})">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>${subtotal.toLocaleString()} VND</td>
                        </tr>`;
                });
            }
            
            const phuThu = <?php echo array_sum(array_column($tables, 'phuthu')); ?>;
            document.getElementById('total-tien').textContent = total.toLocaleString() + ' VND';
            document.getElementById('total-all').textContent = (total + phuThu).toLocaleString() + ' VND';
            document.getElementById('selected_monan').value = JSON.stringify(selectedMonAn);
            document.getElementById('error-message').textContent = '';
        }

        function saveOrder() {
            fetch('index.php?page=book_menu&action=update_order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'selected_monan=' + encodeURIComponent(JSON.stringify(selectedMonAn))
            })
            .then(response => response.json())
            .then(data => {
                if (data.status !== 'success') {
                    console.error('Error saving order:', data.message);
                }
            })
            .catch(error => console.error('Fetch error:', error));
        }

        function filterMenu() {
            const danhmuc = document.getElementById('danhmuc').value;
            const search = document.getElementById('search').value;
            const menuList = document.getElementById('menu-list');

            fetch('index.php?page=book_menu&action=filter_menu', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'danhmuc=' + encodeURIComponent(danhmuc) + '&search=' + encodeURIComponent(search)
            })
            .then(response => response.text())
            .then(html => {
                menuList.innerHTML = html;
            })
            .catch(error => {
                console.error('Fetch error:', error);
                menuList.innerHTML = '<p>Lỗi khi tải danh sách món ăn.</p>';
            });
        }

        document.getElementById('danhmuc').addEventListener('change', () => {
            document.getElementById('search').value = '';
            filterMenu();
        });

        document.getElementById('menu-filter-form').addEventListener('submit', (e) => {
            e.preventDefault();
            filterMenu();
        });

        window.addEventListener('DOMContentLoaded', () => {
            updateSummary();
        });
    </script>
</body>
</html>
