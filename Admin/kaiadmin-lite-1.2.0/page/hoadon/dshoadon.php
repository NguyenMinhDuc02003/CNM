<?php
// Thiết lập mã hóa UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Kết nối DB
require_once __DIR__ . '/../../class/clsconnect.php';
$db = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db
    ? $GLOBALS['admin_db']
    : new connect_db();
$conn = $db->getConnection();
mysqli_set_charset($conn, "utf8mb4");
?>

<div class="container mb-5">
    <div class="row mb-3">
        <div class="col-12">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="dshoadon">
                <div class="col-auto">
                    <label for="q" class="form-label">Từ khóa</label>
                    <input type="text" id="q" name="q" class="form-control" placeholder="Mã hóa đơn hoặc tên khách" value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                </div>
                <div class="col-auto">
                    <label for="date_from" class="form-label">Từ ngày</label>
                    <input type="date" id="date_from" name="date_from" class="form-control" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                </div>
                <div class="col-auto">
                    <label for="date_to" class="form-label">Đến ngày</label>
                    <input type="date" id="date_to" name="date_to" class="form-control" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Lọc</button>
                    <a href="index.php?page=dshoadon" class="btn btn-secondary ms-2">Đặt lại</a>
                </div>
            </form>
        </div>
    </div>
    <?php
    // Nếu người dùng truyền ngày và ngày bắt đầu lớn hơn ngày kết thúc thì hoán đổi để tránh kết quả không mong muốn
    $date_swap_notice = '';
    if (isset($_GET['date_from']) && isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) {
        $df = $_GET['date_from'];
        $dt = $_GET['date_to'];
        if ($df > $dt) {
            // Hoán đổi
            $tmp = $df; $df = $dt; $dt = $tmp;
            $date_swap_notice = "Ngày bắt đầu lớn hơn ngày kết thúc. Đã hoán đổi các giá trị để hiển thị kết quả phù hợp.";
            // Đặt lại biến $_GET để phần truy vấn phía dưới dùng các giá trị đã hoán đổi
            $_GET['date_from'] = $df;
            $_GET['date_to'] = $dt;
        }
    }
    if ($date_swap_notice) {
        echo '<div class="alert alert-info">' . htmlspecialchars($date_swap_notice) . '</div>';
    }
    ?>
    <div style="overflow-x: auto; max-height: 100%; margin-top:20px;">
        <table class="table table-head-bg-primary table-hover ms-3 me-3">
            <thead>
                <tr>
                    <th scope="col">Mã hóa đơn</th>
                    <th scope="col">Tên khách hàng</th>
                    <th scope="col">Ngày </th>
                    <th scope="col">Tổng tiền</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php
                if ($conn) {
                    // Build WHERE clauses based on GET filters
                    $where = [];
                    if (isset($_GET['q']) && trim($_GET['q']) !== '') {
                        $q_raw = trim($_GET['q']);
                        $q = $conn->real_escape_string($q_raw);
                        if (ctype_digit($q_raw)) {
                            // Exact match for numeric invoice id
                            $where[] = "hd.idHD = '$q'";
                        } else {
                            $where[] = "k.tenKH LIKE '%$q%'";
                        }
                    }
                    if (isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) {
                        $date_from = $conn->real_escape_string($_GET['date_from']);
                        $where[] = "DATE(hd.Ngay) >= '$date_from'";
                    }
                    if (isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) {
                        $date_to = $conn->real_escape_string($_GET['date_to']);
                        $where[] = "DATE(hd.Ngay) <= '$date_to'";
                    }

                    $str = "SELECT hd.*, k.tenKH FROM hoadon hd JOIN khachhang k ON hd.idKH = k.idKH";
                    if (!empty($where)) {
                        $str .= ' WHERE ' . implode(' AND ', $where);
                    }
                    $str .= ' ORDER BY hd.Ngay DESC, hd.idHD DESC';

                    $result = $conn->query($str);
                    if ($result && $result->num_rows > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            echo "<tr class='row-clickable' data-idHD='" . htmlspecialchars($row['idHD']) . "' onmouseover=\"this.style.backgroundColor='rgb(39, 35, 35)'\" onmouseout=\"this.style.backgroundColor=''\" style='cursor: pointer;'>";
                            echo "<td>" . htmlspecialchars($row['idHD']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['tenKH']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['Ngay']) . "</td>";
                            echo "<td>" . number_format($row['TongTien'], 0, ',', '.') . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>Không có hóa đơn nào.</td></tr>";
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const rows = document.querySelectorAll('.row-clickable');
        rows.forEach(row => {
            row.addEventListener('click', function() {
                const idHD = this.getAttribute('data-idHD');
                window.location.href = `index.php?page=chitietHD&idHD=${idHD}`;
            });
        });
    });
</script>
