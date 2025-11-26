
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
                <h1 class="display-3 text-white mb-3 animated slideInDown">Chọn Thực Đơn</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb justify-content-center text-uppercase">
                        <li class="breadcrumb-item"><a href="index.php?page=trangchu" class="text-warning">Trang Chủ</a></li>
                        <li class="breadcrumb-item"><a href="index.php?page=booking" class="text-warning">Đặt Bàn</a></li>
                        <li class="breadcrumb-item text-white active" aria-current="page">Chọn Thực Đơn</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Nội dung chọn thực đơn -->
        <div class="container my-5">
            <?php if (!isset($_SESSION['booking'])): ?>
                <div class="alert alert-warning">
                    Vui lòng hoàn tất bước đặt bàn trước khi chọn thực đơn. <a href="index.php?page=booking">Quay lại đặt bàn</a>.
                </div>
            <?php else: ?>
                <div class="menu-container">
                    <!-- Danh sách thực đơn -->
                    <div class="menu-left">
                        <h3>Tất cả thực đơn</h3>
                        <p>Phù hợp cho <?php echo $booking['people_count']; ?> người</p>
                        <?php if ($no_suggestion): ?>
                            <div class="no-suggestion">Không có thực đơn trong hệ thống.</div>
                        <?php endif; ?>
                        <?php if (empty($thucdonList)): ?>
                            <p>Chưa có thực đơn nào trong hệ thống.</p>
                        <?php else: ?>
                            <?php foreach ($thucdonList as $thucdon): ?>
                                <div class="thucdon-item d-flex mb-3 p-3 border rounded">
                                    <?php
                                    $cleaned_image = cleanImageName($thucdon['hinhanh']);
                                    $image_path = 'img/' . $cleaned_image;
                                    $image_exists = file_exists($image_path) && !empty($thucdon['hinhanh']);
                                    if (!$image_exists) {
                                        $image_path = 'img/default_thucdon.jpg';
                                    }
                                    
                                    // Lấy chi tiết món ăn trong thực đơn
                                    $sql_monan = "SELECT m.tenmonan, m.DonGia, m.iddm, dm.tendanhmuc FROM chitietthucdon ct 
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
                                        <p class="text-muted mb-2"><?php echo htmlspecialchars($thucdon['mota']); ?></p>
                                        
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
                                Số người: <?php echo $booking['people_count']; ?> • 
                                Tối đa: <?php echo $max_mon; ?> món
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
                                <span>Tổng tiền thực đơn:</span>
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
                        
                        <form method="POST" action="index.php?page=confirm_booking" class="mt-4">
                            <input type="hidden" name="maban" value="<?php echo htmlspecialchars(json_encode($tables)); ?>">
                            <input type="hidden" name="selected_thucdon" id="selected_thucdon">
                            <input type="hidden" name="total_tien" id="total_tien_hidden">
                            <button type="submit" class="btn btn-primary w-100" id="submitButton" disabled>
                                <i class="fas fa-arrow-right me-2"></i>Tiếp theo
                            </button>
                        </form>
                        <form method="POST" action="index.php?page=choose_order_type" class="mt-2">
                            <input type="hidden" name="maban" value="<?php echo htmlspecialchars(json_encode($tables)); ?>">
                            <button type="submit" class="btn btn-back w-100">
                                <i class="fas fa-arrow-left me-2"></i>Quay lại 
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>
        let selectedThucDon = <?php echo json_encode($selected_thucdon); ?>;
        if (typeof selectedThucDon !== 'object' || selectedThucDon === null) {
            selectedThucDon = {};
        }
        if (!Array.isArray(selectedThucDon.monan)) {
            selectedThucDon.monan = [];
        }
        if (!selectedThucDon.thucdon_info) {
            selectedThucDon.thucdon_info = null;
        }
        const maxMon = <?php echo $max_mon; ?>;
        const totalPhuThu = <?php echo $total_phuthu; ?>;
        const baseUrl = 'index.php?page=book_thucdon&'; // Đường dẫn đúng cho AJAX
        const selectedMenuNameEl = document.getElementById('selected-menu-name');

        function selectThucDon(id_thucdon) {
            console.log('Selecting thucdon:', id_thucdon); // Debug
            
            // Hiển thị loading
            document.getElementById('error-message').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang tải thực đơn...';
            document.getElementById('error-message').className = 'text-info';
            
            fetch(baseUrl + 'action=select_thucdon', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_thucdon=' + encodeURIComponent(id_thucdon)
            })
            .then(response => {
                console.log('Response status:', response.status); // Debug
                if (!response.ok) {
                    throw new Error('HTTP error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('AJAX response:', data); // Debug
                if (data.status === 'success') {
                    selectedThucDon = data.selected_thucdon;
                    if (typeof selectedThucDon !== 'object' || selectedThucDon === null) {
                        selectedThucDon = {};
                    }
                    if (!Array.isArray(selectedThucDon.monan)) {
                        selectedThucDon.monan = [];
                    }
                    if (!selectedThucDon.thucdon_info) {
                        selectedThucDon.thucdon_info = null;
                    }
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
                    console.error('Error selecting thucdon:', data.message);
                    document.getElementById('error-message').innerHTML = '<i class="fas fa-exclamation-circle"></i> Lỗi khi chọn thực đơn: ' + data.message;
                    document.getElementById('error-message').className = 'error';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error.message); // Debug
                document.getElementById('error-message').textContent = 'Lỗi kết nối khi chọn thực đơn: ' + error.message;
            });
        }

        function addMonAn(idmonan, tenmonan, DonGia, tendanhmuc = 'Khác') {
            const totalSoluong = selectedThucDon.monan.reduce((sum, m) => sum + m.soluong, 0);
            if (totalSoluong >= maxMon) {
                document.getElementById('error-message').textContent = `Bạn chỉ có thể chọn tối đa ${maxMon} món cho <?php echo $booking['people_count']; ?> người.`;
                return;
            }
            const existing = selectedThucDon.monan.find(m => m.idmonan === idmonan);
            if (existing) {
                existing.soluong++;
                if (!existing.tendanhmuc) {
                    existing.tendanhmuc = tendanhmuc;
                }
            } else {
                selectedThucDon.monan.push({ idmonan, tenmonan, DonGia, soluong: 1, tendanhmuc });
            }
            updateSummary();
            saveThucDon();
        }

        function removeMonAn(idmonan) {
            const index = selectedThucDon.monan.findIndex(m => m.idmonan === idmonan);
            if (index !== -1) {
                selectedThucDon.monan[index].soluong--;
                if (selectedThucDon.monan[index].soluong === 0) {
                    selectedThucDon.monan.splice(index, 1);
                }
            }
            updateSummary();
            saveThucDon();
        }

        function updateSummary() {
            console.log('Updating summary:', selectedThucDon); // Debug
            const summary = document.querySelector('#order-summary tbody');
            // If a set-menu is selected and has a tongtien, prefer that as total
            let total = 0;
            const menuTongTien = (selectedThucDon && selectedThucDon.thucdon_info && Number(selectedThucDon.thucdon_info.tongtien)) ? Number(selectedThucDon.thucdon_info.tongtien) : null;
            summary.innerHTML = '';
            if (selectedThucDon.monan && selectedThucDon.monan.length > 0) {
                if (selectedMenuNameEl) {
                    if (selectedThucDon.thucdon_info && selectedThucDon.thucdon_info.tenthucdon) {
                        selectedMenuNameEl.textContent = selectedThucDon.thucdon_info.tenthucdon;
                    } else {
                        selectedMenuNameEl.textContent = 'Chưa chọn thực đơn';
                    }
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
                // If menu total provided, use it; otherwise compute from items
                if (menuTongTien !== null && !isNaN(menuTongTien) && menuTongTien > 0) {
                    total = menuTongTien;
                    console.log('Using menu tongtien as total:', total);
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
            document.getElementById('selected_thucdon').value = JSON.stringify(selectedThucDon);
            document.getElementById('total_tien_hidden').value = total + totalPhuThu;
            document.getElementById('error-message').textContent = '';
        }

        function saveThucDon() {
            console.log('Saving thucdon:', selectedThucDon.monan); // Debug
            fetch(baseUrl + 'action=update_thucdon', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'selected_monan=' + encodeURIComponent(JSON.stringify(selectedThucDon.monan))
            })
            .then(response => {
                console.log('Save response status:', response.status); // Debug
                if (!response.ok) {
                    throw new Error('HTTP error: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.status !== 'success') {
                    console.error('Error saving thucdon:', data.message);
                    document.getElementById('error-message').textContent = 'Lỗi khi lưu thực đơn: ' + data.message;
                }
            })
            .catch(error => {
                console.error('Fetch error:', error.message); // Debug
                document.getElementById('error-message').textContent = 'Lỗi kết nối khi lưu thực đơn: ' + error.message;
            });
        }

        window.addEventListener('DOMContentLoaded', () => {
            updateSummary();
        });
    </script>
</body>
</html>
