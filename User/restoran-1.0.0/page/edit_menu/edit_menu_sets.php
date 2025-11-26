<?php
require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsdatban.php';

$db = new connect_db();
$datban = new datban($db);

// Kiểm tra madatban
if (!isset($_GET['madatban']) || !is_numeric($_GET['madatban'])) {
    $_SESSION['error'] = 'Mã đặt bàn không hợp lệ.';
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

// Lấy thực đơn đã chọn từ DB (tạm thời để trống vì chưa có bảng thực đơn trong schema)
$selectedThucDon = [];
$thucdon_data = []; // Placeholder for actual data from chitietdatban or a new chitiet_thucdon_datban table

foreach ($thucdon_data as $item) {
    $selectedThucDon[] = [
        'mathucdon' => $item['mathucdon'],
        'tenthucdon' => $item['tenthucdon'],
        'gia' => (int)$item['gia'],
        'soluong' => (int)$item['soluong']
    ];
}

// Lấy tất cả thực đơn
$all_thucdon = $db->xuatdulieu("SELECT * FROM thucdon ORDER BY tenthucdon");

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

// Function để clean image name
function cleanImageName($filename) {
    if (empty($filename)) return 'default_thucdon.jpg';
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
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
                <h1 class="display-3 text-white mb-3 animated slideInDown">Sửa Thực Đơn</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=profile" class="text-warning">Profile</a></li>
                        <li class="breadcrumb-item"><a href="index.php?page=edit_menu&madatban=<?php echo $madatban; ?>" class="text-warning">Sửa Món</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Sửa Thực Đơn</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Nội dung sửa thực đơn -->
        <div class="container my-5">
            <div class="menu-container">
                <!-- Danh sách thực đơn -->
                <div class="menu-left">
                    <h3>Tất cả thực đơn</h3>
                    <p>Phù hợp cho <?php echo $booking_info['SoLuongKhach']; ?> người</p>
                    
                    <?php if (empty($all_thucdon)): ?>
                        <p>Chưa có thực đơn nào trong hệ thống.</p>
                    <?php else: ?>
                        <?php foreach ($all_thucdon as $thucdon): ?>
                            <div class="thucdon-item d-flex mb-3 p-3 border rounded">
                                <?php
                                $cleaned_image = cleanImageName($thucdon['hinhanh'] ?? '');
                                $image_path = 'img/' . $cleaned_image;
                                $image_exists = file_exists($image_path) && !empty($thucdon['hinhanh']);
                                if (!$image_exists) {
                                    $image_path = 'img/default_thucdon.jpg';
                                }
                                
                                // Lấy chi tiết món ăn trong thực đơn theo danh mục
                                $sql_monan = "SELECT m.idmonan, m.tenmonan, m.DonGia, m.iddm, dm.tendanhmuc FROM chitietthucdon ct 
                                              JOIN monan m ON ct.idmonan = m.idmonan 
                                              LEFT JOIN danhmuc dm ON m.iddm = dm.iddm
                                              WHERE ct.idthucdon = ? AND m.TrangThai = 'approved' 
                                              ORDER BY dm.tendanhmuc, m.tenmonan";
                                $monan_in_thucdon = $db->xuatdulieu_prepared($sql_monan, [$thucdon['idthucdon']]);
                                ?>
                                <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($thucdon['tenthucdon']); ?>" 
                                     class="me-3" style="width: 120px; height: 120px; object-fit: cover; border-radius: 8px;">
                                <div class="flex-grow-1">
                                    <h5 class="mb-2 text-primary" style="cursor: pointer;" 
                                        onclick="selectThucDon(<?php echo $thucdon['idthucdon']; ?>)">
                                        <?php echo htmlspecialchars($thucdon['tenthucdon']); ?>
                                    </h5>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($thucdon['mota'] ?? 'Thực đơn đặc biệt'); ?></p>
                                    
                                    <?php if (!empty($monan_in_thucdon)): ?>
                                        <?php
                                        $grouped_monan = [];
                                        foreach ($monan_in_thucdon as $mon) {
                                            $category_name = !empty($mon['tendanhmuc']) ? $mon['tendanhmuc'] : 'Khác';
                                            if (!isset($grouped_monan[$category_name])) {
                                                $grouped_monan[$category_name] = [];
                                            }
                                            $grouped_monan[$category_name][] = $mon;
                                        }
                                        ?>
                                        <div class="mb-2">
                                            <strong>Các món trong thực đơn:</strong>
                                            <?php foreach ($grouped_monan as $category => $dishes): ?>
                                                <p class="fw-semibold text-dark mt-2 mb-1">
                                                    <?php echo htmlspecialchars($category); ?>
                                                </p>
                                                <ul class="list-unstyled ms-3">
                                                    <?php foreach ($dishes as $mon): ?>
                                                        <li class="small text-secondary">
                                                            • <?php echo htmlspecialchars($mon['tenmonan']); ?> 
                                                            (<?php echo number_format($mon['DonGia']); ?> VND)
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                               
                                    <p class="mb-0"><strong class="text-success">Giá: <?php echo number_format($thucdon['tongtien']); ?> VND</strong></p>
                                    <div class="d-flex gap-2 mt-2">
                                        <button class="btn btn-outline-primary btn-sm flex-grow-1" 
                                                onclick="selectThucDon(<?php echo $thucdon['idthucdon']; ?>)">
                                            <i class="fas fa-utensils me-1"></i>Chọn thực đơn
                                        </button>
                                        <a href="index.php?page=menu_detail&id=<?php echo $thucdon['idthucdon']; ?>" 
                                           class="btn btn-outline-info btn-sm" target="_blank" title="Xem chi tiết">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Tóm tắt thực đơn -->
                <div class="menu-right">
                    <h3><i class="fas fa-receipt me-2"></i>Tóm tắt đơn hàng</h3>
                    
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Đặt bàn #<?php echo $madatban; ?> • 
                            Số người: <?php echo $booking_info['SoLuongKhach']; ?>
                        </small>
                    </div>

                    <div class="selected-menu-info border rounded p-3 mb-3 bg-light">
                        <small class="text-muted d-block mb-1"><i class="fas fa-book-open me-1"></i>Thực đơn đã chọn</small>
                        <span id="selected-menu-name" class="fw-semibold text-dark">Chưa chọn thực đơn</span>
                    </div>

                    <table class="summary-table" id="order-summary">
                        <thead>
                            <tr>
                                <th><i class="fas fa-utensils me-1"></i>Món ăn</th>
                                <th><i class="fas fa-hashtag me-1"></i>SL</th>
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
                    
                    <div id="error-message" class="error"></div>
                    
                    <div class="mt-4">
                        <button type="button" class="btn btn-success w-100" onclick="saveMenuChanges()" id="submitButton">
                            <i class="fas fa-save me-2"></i>Lưu thay đổi
                        </button>
                        <button type="button" class="btn btn-outline-secondary w-100 mt-2" onclick="clearAllSets()">
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
        let selectedThucDonSets = []; // Array để lưu nhiều thực đơn (dự phòng)
        let selectedThucDon = {
            idthucdon: null,
            tenthucdon: '',
            tongtien: 0,
            thucdon_info: null,
            monan: []
        };
        const totalPhuThu = <?php echo $total_phuthu; ?>;
        const madatban = <?php echo $madatban; ?>;
        const selectedMenuNameEl = document.getElementById('selected-menu-name');
        const thucdonData = <?php echo json_encode($all_thucdon); ?>;
        const monanData = <?php
            $all_monan_data = [];
            foreach ($all_thucdon as $thucdon) {
                $monan_in_thucdon = $db->xuatdulieu_prepared(
                    "SELECT m.idmonan, m.tenmonan, m.DonGia, m.iddm, dm.tendanhmuc FROM chitietthucdon ct 
                     JOIN monan m ON ct.idmonan = m.idmonan 
                     LEFT JOIN danhmuc dm ON m.iddm = dm.iddm
                     WHERE ct.idthucdon = ? AND m.TrangThai = 'approved' 
                     ORDER BY dm.tendanhmuc, m.tenmonan",
                    [$thucdon['idthucdon']]
                );
                $all_monan_data[$thucdon['idthucdon']] = $monan_in_thucdon;
            }
            echo json_encode($all_monan_data);
        ?>;

        function selectThucDon(id_thucdon) {
            console.log('Selecting thucdon:', id_thucdon);
            const selectedThucDonData = thucdonData.find(td => td.idthucdon == id_thucdon);
            
            if (selectedThucDonData) {
                const monanInThucdon = monanData[id_thucdon] || [];
                
                selectedThucDon = {
                    idthucdon: Number(selectedThucDonData.idthucdon),
                    tenthucdon: selectedThucDonData.tenthucdon,
                    tongtien: Number(selectedThucDonData.tongtien),
                    thucdon_info: {
                        idthucdon: selectedThucDonData.idthucdon,
                        tenthucdon: selectedThucDonData.tenthucdon,
                        tongtien: selectedThucDonData.tongtien
                    },
                    monan: monanInThucdon.map(mon => ({
                        idmonan: Number(mon.idmonan),
                        tenmonan: mon.tenmonan,
                        DonGia: Number(mon.DonGia),
                        soluong: 1,
                        tendanhmuc: (mon.tendanhmuc && mon.tendanhmuc.trim() !== '') ? mon.tendanhmuc : 'Khác'
                    }))
                };
                
                if (!selectedThucDon.monan || selectedThucDon.monan.length === 0) {
                    document.getElementById('error-message').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Thực đơn này không có món ăn nào.';
                    document.getElementById('error-message').className = 'error';
                } else {
                    document.getElementById('error-message').innerHTML = '<i class="fas fa-check-circle"></i> Đã chọn thực đơn thành công!';
                    document.getElementById('error-message').className = 'text-success';
                    setTimeout(() => {
                        document.getElementById('error-message').textContent = '';
                    }, 3000);
                }
                updateSummary();
            } else {
                document.getElementById('error-message').innerHTML = '<i class="fas fa-exclamation-circle"></i> Không tìm thấy thực đơn.';
                document.getElementById('error-message').className = 'error';
            }
        }

        function updateSummary() {
            console.log('Updating summary:', selectedThucDon);
            const summary = document.querySelector('#order-summary tbody');
            let total = 0;
            const menuTongTien = selectedThucDon && !isNaN(Number(selectedThucDon.tongtien)) && Number(selectedThucDon.tongtien) > 0
                ? Number(selectedThucDon.tongtien)
                : null;
            summary.innerHTML = '';
            
            if (selectedThucDon.monan && selectedThucDon.monan.length > 0) {
                if (selectedMenuNameEl) {
                    selectedMenuNameEl.textContent = selectedThucDon.tenthucdon || 'Chưa chọn thực đơn';
                }
                const groupedDishes = selectedThucDon.monan.reduce((acc, mon) => {
                    const categoryName = (mon.tendanhmuc && mon.tendanhmuc.trim() !== '') ? mon.tendanhmuc : 'Khác';
                    if (!acc[categoryName]) {
                        acc[categoryName] = [];
                    }
                    acc[categoryName].push(mon);
                    return acc;
                }, {});
                const sortedCategories = Object.keys(groupedDishes).sort((a, b) => a.localeCompare(b, 'vi', { sensitivity: 'base' }));
                let summaryHtml = '';
                sortedCategories.forEach(category => {
                    summaryHtml += `
                        <tr class="category-row">
                            <td colspan="2" class="fw-semibold text-primary pt-3">${category}</td>
                        </tr>`;
                    groupedDishes[category].forEach(mon => {
                        summaryHtml += `
                            <tr class="dish-row">
                                <td class="ps-3">${mon.tenmonan}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold">${mon.soluong}</span>
                                    </div>
                                </td>
                            </tr>`;
                    });
                });
                summary.innerHTML = summaryHtml;

                if (menuTongTien !== null) {
                    total = menuTongTien;
                } else {
                    total = selectedThucDon.monan.reduce((sum, mon) => sum + (Number(mon.DonGia) * Number(mon.soluong)), 0);
                }
                document.getElementById('submitButton').disabled = false;
            } else {
                if (selectedMenuNameEl) {
                    selectedMenuNameEl.textContent = 'Chưa chọn thực đơn';
                }
                summary.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4"><i class="fas fa-utensils fa-2x mb-2 d-block"></i>Chưa chọn thực đơn nào<br><small>Vui lòng chọn một thực đơn từ danh sách bên trái</small></td></tr>';
                document.getElementById('submitButton').disabled = true;
            }
            
            document.getElementById('total-tien').textContent = total.toLocaleString() + ' VND';
            document.getElementById('total-all').textContent = (total + totalPhuThu).toLocaleString() + ' VND';
        }

        function clearAllSets() {
            Swal.fire({
                title: 'Xác nhận xóa tất cả?',
                text: "Bạn có chắc chắn muốn xóa tất cả thực đơn đã chọn?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Có, xóa tất cả!',
                cancelButtonText: 'Không'
            }).then((result) => {
                if (result.isConfirmed) {
                    selectedThucDon = {
                        idthucdon: null,
                        tenthucdon: '',
                        tongtien: 0,
                        thucdon_info: null,
                        monan: []
                    };
                    updateSummary();
                    Swal.fire('Đã xóa!', 'Tất cả thực đơn đã được xóa.', 'success');
                }
            });
        }

        function saveMenuChanges() {
            if (!selectedThucDon.monan || selectedThucDon.monan.length === 0) {
                Swal.fire({
                    title: 'Lỗi!',
                    text: 'Vui lòng chọn ít nhất một thực đơn.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return;
            }

            // Prepare data for AJAX
            const dataToSend = {
                madatban: madatban,
                menu_type: 'sets',
                selected_sets: [{
                    mathucdon: selectedThucDon.idthucdon,
                    soluong: 1
                }]
            };

            // Gửi AJAX request trực tiếp đến file save_menu_edit.php
            fetch('page/edit_menu/save_menu_edit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dataToSend),
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                try {
                    const data = JSON.parse(text);
                    return data;
                } catch (e) {
                    console.error('JSON parse error:', e);
                    throw new Error('Response is not valid JSON: ' + text.substring(0, 200));
                }
            })
            .then(data => {
                console.log('Parsed data:', data);
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Thành công!',
                        text: data.message || 'Cập nhật thực đơn thành công.',
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
        });
    </script>
</body>
</html>
