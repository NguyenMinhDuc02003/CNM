<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');

// Include class connect_db
require_once 'class/clsconnect.php';

// Khởi tạo đối tượng connect_db
$db = new connect_db();

$comboSuggestions = [];
try {
    $sqlCombo = "
        SELECT 
            r.lhs_ids,
            r.rhs_id,
            r.confidence,
            r.support_count,
            m_lhs.tenmonan AS lhs_name,
            m_rhs.tenmonan AS rhs_name,
            m_rhs.hinhanh AS rhs_image,
            m_rhs.DonGia AS rhs_price,
            m_rhs.mota AS rhs_desc
        FROM chatbot_item_rules r
        LEFT JOIN monan m_lhs ON r.lhs_ids = m_lhs.idmonan
        LEFT JOIN monan m_rhs ON r.rhs_id = m_rhs.idmonan
        ORDER BY r.confidence DESC, r.support_count DESC, m_rhs.tenmonan ASC
        LIMIT 8
    ";
    $comboSuggestions = $db->xuatdulieu($sqlCombo);
} catch (Exception $e) {
    $comboSuggestions = [];
}

$categoryFilter = isset($_GET['category']) ? (int)$_GET['category'] : null;
$categoryNames = [];
try {
    $categoryRows = $db->xuatdulieu("SELECT iddm, tendanhmuc FROM danhmuc");
    foreach ($categoryRows as $row) {
        $categoryId = (int)$row['iddm'];
        $categoryNames[$categoryId] = $row['tendanhmuc'];
    }
} catch (Exception $e) {
    $categoryNames = [];
}

if ($categoryFilter !== null && !array_key_exists($categoryFilter, $categoryNames)) {
    $categoryFilter = null;
}

$activeCategoryName = $categoryFilter !== null ? $categoryNames[$categoryFilter] : null;
$highlightTitle = $activeCategoryName ? 'Món ' . $activeCategoryName . ' nổi bật' : 'Món Ăn Nổi Bật';

function shouldShowSection(?int $filterId, int $sectionId): bool
{
    return $filterId === null || $filterId === $sectionId;
}

if (!isset($_GET['page'])) {
    $page = 'menu';
} else {
    $page = $_GET['page'];
}
?>


<body>
    <div class="container-xxl bg-white p-0">
        <!-- Spinner Start -->
        <div id="spinner"
            class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Đang tải...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <!-- Navbar & Hero Start -->
        <div class="container-xxl py-5 bg-dark hero-header mb-5">
            <div class="container text-center my-5 pt-5 pb-4">
                <h1 class="display-3 text-white mb-3 animated slideInDown" style="font-size: 70px;"><b>THỰC ĐƠN</b></h1>
                <?php if ($activeCategoryName): ?>
                    <p class="text-white-50 fs-5">Đang xem danh mục: <?php echo htmlspecialchars($activeCategoryName); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <!-- Navbar & Hero End -->

        <!-- Menu Start -->
        <div class="container-xxl py-5">
            <div class="container pb-5">
                <?php if (!empty($comboSuggestions)): ?>
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Gợi ý kết hợp</h5>
                    <h1 class="mb-4">Món hay được gọi cùng nhau</h1>
                </div>
                <div class="row g-4 mb-5">
                    <?php foreach ($comboSuggestions as $combo): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 shadow-sm border-0">
                                <div class="card-img-top" style="height: 180px; overflow: hidden;">
                                    <img src="img/<?php echo htmlspecialchars($combo['rhs_image'] ?: 'default.jpg'); ?>" class="img-fluid w-100 h-100" style="object-fit: cover;" alt="">
                                </div>
                                <div class="card-body text-start">
                                    <h5 class="card-title mb-2">
                                        <?php echo htmlspecialchars($combo['rhs_name'] ?: 'Món được gợi ý'); ?>
                                    </h5>
                                    <p class="card-text text-muted mb-2" style="min-height: 48px;">
                                        <?php echo htmlspecialchars($combo['rhs_desc'] ?: 'Món này thường được gọi kèm trong các đơn gần đây.'); ?>
                                    </p>
                                    <p class="mb-1"><strong>Giá:</strong> <?php echo number_format((float)$combo['rhs_price'], 0, ',', '.'); ?>đ</p>
                                    <p class="mb-2 text-success">
                                        Gợi ý kèm sau khi chọn: <?php echo htmlspecialchars($combo['lhs_name'] ?: ('món #' . $combo['lhs_ids'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">Thực Đơn</h5>
                    <h1 class="mb-5"><?php echo htmlspecialchars($highlightTitle); ?></h1>
                    <hr>
                </div>
                <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                    <div class="tab-content">
                        <div id="tab-1" class="tab-pane fade show p-0 active">
                            <div class="row g-4">
                                <?php
                                try {
                                    if ($categoryFilter !== null) {
                                        $sql = "SELECT m.*, COUNT(ct.idmonan) as total_ordered
                                                FROM monan m
                                                LEFT JOIN chitiethoadon ct ON m.idmonan = ct.idmonan
                                                LEFT JOIN hoadon h ON ct.idHD = h.idHD
                                                WHERE m.iddm = ?
                                                  AND m.TrangThai = 'approved'
                                                  AND m.hoatdong = 'active'
                                                GROUP BY m.idmonan
                                                ORDER BY total_ordered DESC, m.tenmonan ASC
                                                LIMIT 6";
                                        $result = $db->xuatdulieu_prepared($sql, [$categoryFilter]);
                                        if (empty($result)) {
                                            $result = $db->xuatdulieu_prepared(
                                                "SELECT * FROM monan WHERE iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active' ORDER BY tenmonan LIMIT 6",
                                                [$categoryFilter]
                                            );
                                        }
                                    } else {
                                        $sql = "SELECT m.*, COUNT(ct.idmonan) as total_ordered 
                                                FROM monan m
                                                LEFT JOIN chitiethoadon ct ON m.idmonan = ct.idmonan
                                                LEFT JOIN hoadon h ON ct.idHD = h.idHD
                                                WHERE m.TrangThai = 'approved' AND m.hoatdong = 'active'
                                                GROUP BY m.idmonan
                                                ORDER BY total_ordered DESC, m.tenmonan ASC
                                                LIMIT 6";
                                        $result = $db->xuatdulieu($sql);
                                    }

                                    if (!empty($result)) {
                                        echo "<div class='row'>";
                                        foreach ($result as $row) {
                                            echo "
                                                <div class='col-md-6 mt-5'>
                                                    <div class='d-flex align-items-center mb-4'>
                                                        <img class='flex-shrink-0 img-fluid rounded' src='img/" . htmlspecialchars($row['hinhanh'] ?: 'default.jpg') . "' 
                                                            style='width: 150px; object-fit: cover;'>
                                                        <div class='w-100 d-flex flex-column text-start ps-4'>
                                                            <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                                <span>" . htmlspecialchars($row['tenmonan']) . "</span>
                                                                <span class='text-primary'>" . number_format($row['DonGia'], 0, ',', '.') . "đ</span>
                                                            </h5>
                                                            <small class='fst-italic'>" . htmlspecialchars($row['mota'] ?: 'Không có mô tả') . "</small>
                                                        </div>
                                                    </div>
                                                </div>
                                            ";
                                        }
                                        echo "</div>";
                                    } else {
                                        echo "<p>Hiện chưa có món ăn trong danh mục này.</p>";
                                    }
                                } catch (Exception $e) {
                                    echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (shouldShowSection($categoryFilter, 1)): ?>
                <!-- Khai vị -->
                <div class="container pt-5 pb-5">
                    <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                        <h5 class="section-title ff-secondary text-center text-primary fw-normal">Thực Đơn</h5>
                        <h1 class="mb-5">Khai vị</h1>
                        <hr>
                    </div>
                    <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                        <div class="tab-content">
                            <div id="tab-1" class="tab-pane fade show p-0 active">
                                <div class="row g-4">
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM monan WHERE iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active' ORDER BY tenmonan";
                                        $result = $db->xuatdulieu_prepared($sql, [1]);
                                        if (!empty($result)) {
                                            echo "<div class='row'>";
                                            foreach ($result as $row) {
                                                echo "
                                                    <div class='col-md-6 mt-5'>
                                                        <div class='d-flex align-items-center mb-4'>
                                                            <img class='flex-shrink-0 img-fluid rounded' src='img/" . htmlspecialchars($row['hinhanh'] ?: 'default.jpg') . "' 
                                                                style='width: 150px; object-fit: cover;'>
                                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                                    <span>" . htmlspecialchars($row['tenmonan']) . "</span>
                                                                    <span class='text-primary'>" . number_format($row['DonGia'], 0, ',', '.') . "đ</span>
                                                                </h5>
                                                                <small class='fst-italic'>" . htmlspecialchars($row['mota'] ?: 'Không có mô tả') . "</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ";
                                            }
                                            echo "</div>";
                                        } else {
                                            echo "<p>Hiện chưa có món khai vị.</p>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (shouldShowSection($categoryFilter, 2)): ?>
                <!-- Món chính -->
                <div class="container pt-5 pb-5">
                    <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                        <h5 class="section-title ff-secondary text-center text-primary fw-normal">Thực Đơn</h5>
                        <h1 class="mb-5">Món chính</h1>
                        <hr>
                    </div>
                    <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                        <div class="tab-content">
                            <div id="tab-1" class="tab-pane fade show p-0 active">
                                <div class="row g-4">
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM monan WHERE iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active' ORDER BY tenmonan";
                                        $result = $db->xuatdulieu_prepared($sql, [2]);
                                        if (!empty($result)) {
                                            echo "<div class='row'>";
                                            foreach ($result as $row) {
                                                echo "
                                                    <div class='col-md-6 mt-5'>
                                                        <div class='d-flex align-items-center mb-4'>
                                                            <img class='flex-shrink-0 img-fluid rounded' src='img/" . htmlspecialchars($row['hinhanh'] ?: 'default.jpg') . "' 
                                                                style='width: 150px; object-fit: cover;'>
                                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                                    <span>" . htmlspecialchars($row['tenmonan']) . "</span>
                                                                    <span class='text-primary'>" . number_format($row['DonGia'], 0, ',', '.') . "đ</span>
                                                                </h5>
                                                                <small class='fst-italic'>" . htmlspecialchars($row['mota'] ?: 'Không có mô tả') . "</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ";
                                            }
                                            echo "</div>";
                                        } else {
                                            echo "<p>Hiện chưa có món chính.</p>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (shouldShowSection($categoryFilter, 3)): ?>
                <!-- Tráng miệng -->
                <div class="container pt-5 pb-5">
                    <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                        <h5 class="section-title ff-secondary text-center text-primary fw-normal">Thực Đơn</h5>
                        <h1 class="mb-5">Tráng miệng</h1>
                        <hr>
                    </div>
                    <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                        <div class="tab-content">
                            <div id="tab-1" class="tab-pane fade show p-0 active">
                                <div class="row g-4">
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM monan WHERE iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active' ORDER BY tenmonan";
                                        $result = $db->xuatdulieu_prepared($sql, [3]);
                                        if (!empty($result)) {
                                            echo "<div class='row'>";
                                            foreach ($result as $row) {
                                                echo "
                                                    <div class='col-md-6 mt-5'>
                                                        <div class='d-flex align-items-center mb-4'>
                                                            <img class='flex-shrink-0 img-fluid rounded' src='img/" . htmlspecialchars($row['hinhanh'] ?: 'default.jpg') . "' 
                                                                style='width: 150px; object-fit: cover;'>
                                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                                    <span>" . htmlspecialchars($row['tenmonan']) . "</span>
                                                                    <span class='text-primary'>" . number_format($row['DonGia'], 0, ',', '.') . "đ</span>
                                                                </h5>
                                                                <small class='fst-italic'>" . htmlspecialchars($row['mota'] ?: 'Không có mô tả') . "</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ";
                                            }
                                            echo "</div>";
                                        } else {
                                            echo "<p>Hiện chưa có món tráng miệng.</p>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (shouldShowSection($categoryFilter, 4)): ?>
                <!-- Đồ uống -->
                <div class="container pt-5 pb-5">
                    <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                        <h5 class="section-title ff-secondary text-center text-primary fw-normal">Thực Đơn</h5>
                        <h1 class="mb-5">Đồ uống</h1>
                        <hr>
                    </div>
                    <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                        <div class="tab-content">
                            <div id="tab-1" class="tab-pane fade show p-0 active">
                                <div class="row g-4">
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM monan WHERE iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active' ORDER BY tenmonan";
                                        $result = $db->xuatdulieu_prepared($sql, [4]);
                                        if (!empty($result)) {
                                            echo "<div class='row'>";
                                            foreach ($result as $row) {
                                                echo "
                                                    <div class='col-md-6 mt-5'>
                                                        <div class='d-flex align-items-center mb-4'>
                                                            <img class='flex-shrink-0 img-fluid rounded' src='img/" . htmlspecialchars($row['hinhanh'] ?: 'default.jpg') . "' 
                                                                style='width: 150px; object-fit: cover;'>
                                                            <div class='w-100 d-flex flex-column text-start ps-4'>
                                                                <h5 class='d-flex justify-content-between border-bottom pb-2'>
                                                                    <span>" . htmlspecialchars($row['tenmonan']) . "</span>
                                                                    <span class='text-primary'>" . number_format($row['DonGia'], 0, ',', '.') . "đ</span>
                                                                </h5>
                                                                <small class='fst-italic'>" . htmlspecialchars($row['mota'] ?: 'Không có mô tả') . "</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ";
                                            }
                                            echo "</div>";
                                        } else {
                                            echo "<p>Hiện chưa có đồ uống.</p>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (shouldShowSection($categoryFilter, 5)): ?>
                <!-- Đặc biệt -->
                <div class="container pt-5 pb-5">
                    <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                        <h5 class="section-title ff-secondary text-center text-primary fw-normal">Thực Đơn</h5>
                        <h1 class="mb-5">Đặc biệt</h1>
                        <hr>
                    </div>
                    <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                        <div class="tab-content">
                            <div id="tab-1" class="tab-pane fade show p-0 active">
                                <div class="row g-4">
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM monan WHERE iddm = ? AND TrangThai = 'approved' AND hoatdong = 'active' ORDER BY tenmonan";
                                        $result = $db->xuatdulieu_prepared($sql, [5]);
                                        if (!empty($result)) {
                                            foreach ($result as $row) {
                                                echo "
                                                <div class='col-12'>
                                                    <div class='d-flex flex-lg-row flex-column align-items-center bg-white shadow-sm p-4 rounded-3'>
                                                        <img src='img/" . htmlspecialchars($row['hinhanh'] ?: 'default.jpg') . "' class='img-fluid rounded mb-3 mb-lg-0' style='width: 300px; height: auto; object-fit: cover;'>
                                                        <div class='ps-lg-4 text-start'>
                                                            <h4 class='mb-2 fw-bold'>" . htmlspecialchars($row['tenmonan']) . " 
                                                                <span class='text-primary float-end'>" . number_format($row['DonGia'], 0, ',', '.') . "đ</span>
                                                            </h4>
                                                            <p class='fst-italic'>" . htmlspecialchars($row['mota'] ?: 'Không có mô tả') . "</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ";
                                            }
                                        } else {
                                            echo "<p>Hiện chưa có món đặc biệt.</p>";
                                        }
                                    } catch (Exception $e) {
                                        echo "<p>Lỗi: " . htmlspecialchars($e->getMessage()) . "</p>";
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <!-- Menu End -->
        </div>
    </div>
</body>
