<?php
require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsdatban.php';

$db = new connect_db();
$datban = new datban($db);

// Kiểm tra madatban
if (!isset($_GET['madatban']) || empty($_GET['madatban'])) {
    header('Location: index.php?page=profile');
    exit;
}

$madatban = (int)$_GET['madatban'];

// Lấy thông tin đặt bàn
$booking_info = $db->xuatdulieu_prepared("SELECT * FROM datban WHERE madatban = ?", [$madatban]);
if (empty($booking_info)) {
    $_SESSION['error'] = 'Không tìm thấy thông tin đặt bàn.';
    header('Location: index.php?page=profile');
    exit;
}
$booking_info = $booking_info[0];
if (($booking_info['TrangThai'] ?? '') !== 'pending') {
    $_SESSION['error'] = 'Chỉ có thể chỉnh sửa món khi đơn hàng đang chờ xác nhận.';
    header('Location: index.php?page=profile#bookings');
    exit;
}

// Lấy danh mục món ăn
$danhmuc_data = $db->xuatdulieu("SELECT * FROM danhmuc ORDER BY tendanhmuc");

// Lấy món ăn đã chọn từ DB
$selectedMonAn = [];
$monan_data = $db->xuatdulieu_prepared(
    "SELECT m.idmonan as mamon, m.tenmonan as tenmon, m.DonGia as gia, cma.SoLuong as soluong 
     FROM chitietdatban cma 
     JOIN monan m ON cma.idmonan = m.idmonan 
     WHERE cma.madatban = ?",
    [$madatban]
);

foreach ($monan_data as $item) {
    $selectedMonAn[] = [
        'mamon' => $item['mamon'],
        'tenmon' => $item['tenmon'],
        'gia' => (int)$item['gia'],
        'soluong' => (int)$item['soluong']
    ];
}

// Lấy tất cả món ăn
$all_monan = $db->xuatdulieu("SELECT * FROM monan ORDER BY tenmonan");

// Tính tổng phụ phí từ bàn đã chọn
$total_phuthu = 0;
$tables_data = $db->xuatdulieu_prepared(
    "SELECT cbd.phuthu FROM chitiet_ban_datban cbd 
     JOIN ban b ON cbd.idban = b.idban 
     WHERE cbd.madatban = ?",
    [$madatban]
);

foreach ($tables_data as $table) {
    $total_phuthu += (int)$table['phuthu'];
}
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
                <h1 class="display-3 text-white mb-3 animated slideInDown">Sửa Món Ăn Lẻ</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=profile" class="text-warning">Profile</a></li>
                        <li class="breadcrumb-item"><a href="index.php?page=edit_menu&madatban=<?php echo $madatban; ?>" class="text-warning">Sửa Món</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Sửa Món Lẻ</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Nội dung sửa món ăn -->
        <div class="container my-5">
            <div class="menu-container">
                <!-- Danh sách món ăn -->
                <div class="menu-left">
                    <form id="menu-filter-form" class="mb-4" onsubmit="event.preventDefault(); filterMenu();">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <select id="danhmuc" class="form-select">
                                    <option value="">Tất cả danh mục</option>
                                    <?php foreach ($danhmuc_data as $dm): ?>
                                        <option value="<?php echo $dm['iddm']; ?>">
                                            <?php echo htmlspecialchars($dm['tendanhmuc']); ?>
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
                        <?php if (empty($all_monan)): ?>
                            <p>Không tìm thấy món ăn nào.</p>
                        <?php else: ?>
                            <?php foreach ($all_monan as $mon): ?>
                                <div class="menu-item" data-mamon="<?php echo $mon['idmonan']; ?>" data-iddm="<?php echo $mon['iddm']; ?>">
                                    <img src="img/<?php echo htmlspecialchars($mon['hinhanh'] ?: 'default.jpg'); ?>" alt="<?php echo htmlspecialchars($mon['tenmonan']); ?>">
                                    <div class="menu-item-details">
                                        <div>
                                            <strong class="text-primary"><?php echo htmlspecialchars($mon['tenmonan']); ?></strong><br>
                                            <p class="text-muted mb-2"><?php echo htmlspecialchars($mon['mota'] ?: 'Không có mô tả'); ?></p>
                                            <strong class="text-success">Giá: <?php echo number_format($mon['DonGia']); ?> VND</strong>
                                        </div>
                                        <button class="btn-choose" onclick="addMonAn(<?php echo $mon['idmonan']; ?>, '<?php echo addslashes($mon['tenmonan']); ?>', <?php echo $mon['DonGia']; ?>)">
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
                            Đặt bàn #<?php echo $madatban; ?> • 
                            Số người: <?php echo $booking_info['SoLuongKhach']; ?>
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
                            <strong class="text-info"><?php echo number_format($total_phuthu); ?> VND</strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between">
                            <span class="fw-bold">Tổng cộng:</span>
                            <strong id="total-all" class="text-success fs-5">0 VND</strong>
                        </div>
                    </div>
                    
                    <div id="error-message" class="error mt-3"></div>
                    
                    <div class="mt-4">
                        <button type="button" class="btn btn-success w-100" onclick="saveMenuChanges()">
                            <i class="fas fa-save me-2"></i>Lưu thay đổi
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearAllItems()">
                            <i class="fas fa-trash-alt me-2"></i>Xóa tất cả
                        </button>
                        <a href="index.php?page=edit_menu&madatban=<?php echo $madatban; ?>" class="btn btn-back w-100 mt-2">
                            <i class="fas fa-arrow-left me-2"></i>Quay lại chọn loại món
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.10.2/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let selectedMonAn = <?php echo json_encode($selectedMonAn); ?>;
        const totalPhuThu = <?php echo $total_phuthu; ?>;
        const madatban = <?php echo $madatban; ?>;

        function addMonAn(idmonan, tenmonan, DonGia) {
            const existing = selectedMonAn.find(m => m.mamon == idmonan);
            if (existing) {
                existing.soluong++;
            } else {
                selectedMonAn.push({ mamon: idmonan, tenmon: tenmonan, gia: DonGia, soluong: 1 });
            }
            updateSummary();
        }

        function removeMonAn(idmonan) {
            const index = selectedMonAn.findIndex(m => m.mamon == idmonan);
            if (index !== -1) {
                selectedMonAn[index].soluong--;
                if (selectedMonAn[index].soluong === 0) {
                    selectedMonAn.splice(index, 1);
                }
            }
            updateSummary();
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
                    const subtotal = mon.gia * mon.soluong;
                    total += subtotal;
                    summary.innerHTML += `
                        <tr>
                            <td>${mon.tenmon}</td>
                            <td>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="fw-bold">${mon.soluong}</span>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-danger btn-quantity" onclick="removeMonAn(${mon.mamon})">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-success btn-quantity" onclick="addMonAn(${mon.mamon}, '${mon.tenmon.replace(/'/g, "\\'")}', ${mon.gia})">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>${subtotal.toLocaleString()} VND</td>
                        </tr>`;
                });
            }
            
            document.getElementById('total-tien').textContent = total.toLocaleString() + ' VND';
            document.getElementById('total-all').textContent = (total + totalPhuThu).toLocaleString() + ' VND';
        }

        function filterMenu() {
            const categoryId = document.getElementById('danhmuc').value;
            const searchTerm = document.getElementById('search').value.toLowerCase();

            document.querySelectorAll('.menu-item').forEach(item => {
                const itemCategory = item.dataset.iddm;
                const itemName = item.querySelector('strong').textContent.toLowerCase();

                const categoryMatch = categoryId === '' || itemCategory === categoryId;
                const searchMatch = itemName.includes(searchTerm);

                if (categoryMatch && searchMatch) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function clearAllItems() {
            Swal.fire({
                title: 'Xác nhận xóa tất cả?',
                text: "Bạn có chắc chắn muốn xóa tất cả món ăn đã chọn?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Có, xóa tất cả!',
                cancelButtonText: 'Không'
            }).then((result) => {
                if (result.isConfirmed) {
                    selectedMonAn = [];
                    updateSummary();
                    Swal.fire('Đã xóa!', 'Tất cả món ăn đã được xóa.', 'success');
                }
            });
        }

        function saveMenuChanges() {
            // Prepare data for AJAX
            const dataToSend = {
                madatban: madatban,
                menu_type: 'items',
                selected_items: selectedMonAn.map(item => ({
                    mamon: item.mamon,
                    soluong: item.soluong
                }))
            };

            // Gửi AJAX request trực tiếp đến file save_menu_edit.php
            fetch('page/edit_menu/save_menu_edit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dataToSend),
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Thành công!',
                        text: data.message || 'Cập nhật món ăn thành công.',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = 'index.php?page=profile#bookings';
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
                console.error('Error:', error);
                Swal.fire({
                    title: 'Lỗi!',
                    text: 'Đã xảy ra lỗi khi lưu thay đổi.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        window.addEventListener('DOMContentLoaded', () => {
            updateSummary();
            const danhMucSelect = document.getElementById('danhmuc');
            const searchInput = document.getElementById('search');

            if (danhMucSelect) {
                danhMucSelect.addEventListener('change', filterMenu);
            }

            if (searchInput) {
                searchInput.addEventListener('input', filterMenu);
            }

            filterMenu();
        });
    </script>
</body>
</html>
