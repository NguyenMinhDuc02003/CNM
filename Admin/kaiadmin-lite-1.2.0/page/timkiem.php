<?php
// Thiết lập mã hóa UTF-8
header('Content-Type: text/html; charset=utf-8');
?>

<div class="container mt-5 mb-3 ml-5 mr-5">
    <?php
    $keyword = $_GET['keyword'] ?? ''; // Lấy từ khóa tìm kiếm từ URL
    
    // Kiểm tra kết nối cơ sở dữ liệu
    $conn = mysqli_connect('localhost', 'hceeab2b55_chung9atm', 'Chung2002!', 'hceeab2b55_restaurant');
    if (!$conn) {
        die("Kết nối cơ sở dữ liệu thất bại: " . mysqli_connect_error());
    }

    // Kiểm tra xem từ khóa có được nhập hay không
    if (empty($keyword)) {
        echo "<p>Vui lòng nhập từ khóa tìm kiếm.</p>";
        return;
    }

    // Chuẩn bị từ khóa tìm kiếm cho prepared statement
    $keywordLike = "%" . $keyword . "%";

    // 1. Tìm trong món ăn
    $sql_monan = "SELECT * FROM monan WHERE tenmonan LIKE ?";
    $stmt_monan = $conn->prepare($sql_monan);
    $stmt_monan->bind_param("s", $keywordLike);
    $stmt_monan->execute();
    $res_monan = $stmt_monan->get_result();

    // 2. Tìm trong nhân viên
    $sql_nv = "SELECT * FROM nhanvien WHERE HoTen LIKE ?";
    $stmt_nv = $conn->prepare($sql_nv);
    $stmt_nv->bind_param("s", $keywordLike);
    $stmt_nv->execute();
    $res_nv = $stmt_nv->get_result();

    // 3. Tìm trong khách hàng
    $sql_kh = "SELECT * FROM khachhang WHERE tenKH LIKE ?";
    $stmt_kh = $conn->prepare($sql_kh);
    $stmt_kh->bind_param("s", $keywordLike);
    $stmt_kh->execute();
    $res_kh = $stmt_kh->get_result();

    // 4. Tìm trong hóa đơn
    $sql_hd = "SELECT * FROM hoadon WHERE idHD LIKE ?";
    $stmt_hd = $conn->prepare($sql_hd);
    $stmt_hd->bind_param("s", $keywordLike);
    $stmt_hd->execute();
    $res_hd = $stmt_hd->get_result();

    // 5. Tìm trong đơn hàng
    $sql_dh = "SELECT * FROM donhang WHERE idDH LIKE ?";
    $stmt_dh = $conn->prepare($sql_dh);
    $stmt_dh->bind_param("s", $keywordLike);
    $stmt_dh->execute();
    $res_dh = $stmt_dh->get_result();
    ?>
    <div class="mt-5">
        <h2>Kết quả tìm kiếm cho: <b><?php echo htmlspecialchars($keyword); ?></b></h2>

        <!-- Món ăn -->
        <?php if ($res_monan->num_rows > 0): ?>
            <h4>Món ăn</h4>
            <ul>
                <?php while ($row = $res_monan->fetch_assoc()): ?>
                    <li><?php echo htmlspecialchars($row['tenmonan']); ?> - <?php echo number_format($row['DonGia']); ?>đ</li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <!-- <p>Không tìm thấy món ăn nào.</p> -->
        <?php endif; ?>

        <!-- Nhân viên -->
        <?php if ($res_nv->num_rows > 0): ?>
            <h4>Nhân viên</h4>
            <ul>
                <?php while ($row = $res_nv->fetch_assoc()): ?>
                    <li><?php echo htmlspecialchars($row['HoTen']); ?> - <?php echo htmlspecialchars($row['ChucVu']); ?></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <!-- <p>Không tìm thấy nhân viên nào.</p> -->
        <?php endif; ?>

        <!-- Khách hàng -->
        <?php if ($res_kh->num_rows > 0): ?>
            <h4>Khách hàng</h4>
            <ul>
                <?php while ($row = $res_kh->fetch_assoc()): ?>
                    <li><?php echo htmlspecialchars($row['tenKH']); ?> - <?php echo htmlspecialchars($row['sodienthoai']); ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <!-- <p>Không tìm thấy khách hàng nào.</p> -->
        <?php endif; ?>

        <!-- Hóa đơn -->
        <?php if ($res_hd->num_rows > 0): ?>
            <h4>Hóa đơn</h4>
            <ul>
                <?php while ($row = $res_hd->fetch_assoc()): ?>
                    <li>Mã: <?php echo htmlspecialchars($row['idHD']); ?> - Tổng:
                        <?php echo number_format($row['TongTien']); ?>đ
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <!-- <p>Không tìm thấy hóa đơn nào.</p> -->
        <?php endif; ?>

        <!-- Đơn hàng -->
        <?php if ($res_dh->num_rows > 0): ?>
            <h4>Đơn hàng</h4>
            <ul>
                <?php while ($row = $res_dh->fetch_assoc()): ?>
                    <li>Mã: <?php echo htmlspecialchars($row['idDH']); ?> - Trạng thái:
                        <?php echo htmlspecialchars($row['TrangThai']); ?>
                    </li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <!-- <p>Không tìm thấy đơn hàng nào.</p> -->
        <?php endif; ?>
    </div>
    <?php
    // Đóng các statement và kết nối DB
    $stmt_monan->close();
    $stmt_nv->close();
    $stmt_kh->close();
    $stmt_hd->close();
    $stmt_dh->close();
    mysqli_close($conn);
    ?>
</div>