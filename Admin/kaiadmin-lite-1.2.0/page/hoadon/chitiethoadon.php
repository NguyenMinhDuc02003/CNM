<?php
// Thiết lập mã hóa UTF-8
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
error_reporting(1);
date_default_timezone_set('Asia/Ho_Chi_Minh'); // Set timezone to Vietnam

require_once __DIR__ . '/../../class/clsconnect.php';
require_once __DIR__ . '/../../class/clsDonHang.php';
$db = isset($GLOBALS['admin_db']) && $GLOBALS['admin_db'] instanceof connect_db
    ? $GLOBALS['admin_db']
    : new connect_db();
$conn = $db->getConnection();
mysqli_set_charset($conn, "utf8mb4");
$orderService = new clsDonHang();

$idHD = isset($_GET['idHD']) ? (int)$_GET['idHD'] : 0;
if ($idHD <= 0) {
    echo '<div class="container py-5"><div class="alert alert-danger">Thiếu mã hóa đơn cần hiển thị.</div></div>';
    return;
}

?>

<div class="m-5" style="font-family: 'Times New Roman', serif; font-size: 14px;">

  <?php
  // Hiển thị thông báo kết quả gửi email
  if (isset($_GET['status'])) {
    if ($_GET['status'] == 'success') {
      echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
              <strong>Thành công!</strong> Hóa đơn đã được gửi đến email khách hàng.
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    } else if ($_GET['status'] == 'error') {
      $errorMsg = isset($_GET['msg']) ? $_GET['msg'] : 'Đã xảy ra lỗi trong quá trình gửi email.';
      echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
              <strong>Lỗi!</strong> ' . htmlspecialchars($errorMsg) . '
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>';
    }
  }
  ?>

  <div style=" margin:100px 100px 30px 100px ; border: 2px solid black; box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.3);">

    <div style="text-align: center; font-weight: bold; ">

      <h2 style="margin: 5px; padding:20px;">HÓA ĐƠN BÁN HÀNG </h2>
      <?php
      $ngay = "";
      $thang = "";
      $nam = "";
      $gio = "";
      $phut = "";
      if ($conn) {
        if ($stmt = $conn->prepare("SELECT ngay FROM hoadon WHERE idHD = ?")) {
          $stmt->bind_param('i', $idHD);
          $stmt->execute();
          $result = $stmt->get_result();
          if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ngay = $row["ngay"];
            $date = new DateTime($ngay);
            $ngay = $date->format('d');
            $thang = $date->format('m');
            $nam = $date->format('Y');
            $gio = $date->format('H');
            $phut = $date->format('i');
          }
          $stmt->close();
        }
      }
      ?>
      <p>Ngày <strong><?php echo $ngay ?></strong> tháng <strong><?php echo $thang ?></strong> năm
        <strong><?php echo $nam ?></strong>
        - <strong><?php echo $gio ?>:<?php echo $phut ?></strong>
      </p>
    </div>

    <div style="float: right; padding-right: 20px;">
      <p><strong>Ký hiệu:</strong> HD<?php echo sprintf('%03d', $idHD); ?></p>
      <p><strong>Số:</strong> <?php echo date('Ymd') . sprintf('%03d', $idHD); ?></p>
    </div>

    <div style="clear: both;"></div>
    <div style="height: 1px; border: none; background-color: black; margin: 20px 0;"></div>

    <table style="border-collapse: collapse; margin: 10px; ">
      <tr>
        <td colspan="2" style="border: none; padding: 3px;"><strong>Người bán:</strong> Nhà hàng Restoran </td>
      </tr>

      <tr>
        <td colspan="2" style="border: none; padding: 3px;"><strong>Địa chỉ:</strong> 12 Nguyễn Văn Bảo,phường 4, quận
          Gò Vấp,
          TP.HCM
        </td>
      </tr>
      <tr>
        <td style="border: none; padding: 3px;"><strong>Điện thoại:</strong>0123456789</td>
      </tr>
    </table>
    <div style="height: 1px; border: none; background-color: black; margin: 20px 0;"></div>

    <table style="border-collapse: collapse; margin: 10px; ">
      <?php
      $orderIdForInvoice = 0;
      $bookingReferenceId = 0;
      if ($conn) {
        $sql = "SELECT hd.idHD, hd.idDH, kh.tenKH, kh.sodienthoai, ban.SoBan, hd.ngay, hd.TongTien, hd.hinhthucthanhtoan, kh.email, dh.MaDonHang, dh.madatban
                FROM hoadon hd 
                  JOIN khachhang kh ON hd.idKH = kh.idKH 
                  JOIN donhang dh ON hd.idDH = dh.idDH 
                  JOIN ban ON dh.idban = ban.idban 
                WHERE hd.idHD = ?";
        if ($stmt = $conn->prepare($sql)) {
          $stmt->bind_param('i', $idHD);
          $stmt->execute();
          $result = $stmt->get_result();
          if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $tenKH = $row["tenKH"];
            $sdt = $row["sodienthoai"];
            $ban = $row["SoBan"];
            $hinhthucthanhtoan = $row['hinhthucthanhtoan'];
            $email = $row["email"];
            $maDonHang = $row["MaDonHang"];
            $orderIdForInvoice = (int)$row['idDH'];
            $bookingReferenceId = isset($row['madatban']) ? (int)$row['madatban'] : 0;
          }
          $stmt->close();
        }
      }
      ?>
      <tr>
        <td style="border: none; padding: 3px;"><strong>Bàn :</strong> <?php echo $ban ?></td>
      </tr>
      <tr>
        <td style="border: none; padding: 3px;"><strong>Khách hàng :</strong> <?php echo $tenKH ?></td>
      </tr>
      <tr>
        <td style="border: none; padding: 3px;"><strong>Số điện thoại:</strong> <?php echo $sdt ?></td>
      </tr>
      <tr>
        <td style="border: none; padding: 3px;"><strong>Hình thức thanh toán:</strong> <?php echo $hinhthucthanhtoan ?>
        </td>
      </tr>
      <tr>
        <td style="border: none; padding: 3px;"><strong>Mã đơn hàng:</strong> <?php echo $maDonHang ?>
        </td>
      </tr>
    </table>

    <br>

    <table class="table table-bordered" style="width: 95%;margin: auto">
      <thead style="border:1px solid black;">
        <tr>
          <th>STT</th>
          <th>Tên hàng hóa</th>
          <th>Đơn vị tính</th>
          <th>Số lượng</th>
          <th>Đơn giá</th>
          <th>Thành tiền</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $displayItems = [];
        if ($orderIdForInvoice > 0) {
          try {
            $displayItems = $orderService->getInvoiceDisplayItems($orderIdForInvoice);
          } catch (Throwable $th) {
            $displayItems = [];
          }
        }

        $stt = 1;
        $TongTien = 0;
        if (!empty($displayItems)) {
          foreach ($displayItems as $item) {
            $TongTien += $item['total'];
            echo "<tr>";
            echo "<td>" . $stt++ . "</td>";
            echo "<td>" . htmlspecialchars($item['name']) . "</td>";
            echo "<td>" . htmlspecialchars($item['unit'] ?? '') . "</td>";
            echo "<td>" . (int)$item['quantity'] . "</td>";
            echo "<td>" . number_format($item['unit_price'], 0, ',', '.') . "</td>";
            echo "<td>" . number_format($item['total'], 0, ',', '.') . "</td>";
            echo "</tr>";
          }
        } else {
          echo '<tr><td colspan="6" style="padding: 5px; text-align: center;">Không có dữ liệu chi tiết hóa đơn.</td></tr>';
        }
        ?>
        <?php
        // Include surcharge from order meta (per-table fee) and compute grand total
        $surcharge = 0;
        $grandTotal = $TongTien;
        if ($orderIdForInvoice > 0) {
            try {
                $totals = $orderService->computeOrderTotals($orderIdForInvoice);
                $surcharge = isset($totals['surcharge']) ? (float)$totals['surcharge'] : 0;
                $grandTotal = isset($totals['total']) ? (float)$totals['total'] : $grandTotal + $surcharge;
            } catch (Throwable $th) {
                // fallback: keep existing behavior
            }
        }
        $bookingDeposit = $orderService->getBookingDepositSummary($bookingReferenceId ?? null, $grandTotal);
        $depositPaid = isset($bookingDeposit['paid']) ? (float)$bookingDeposit['paid'] : 0.0;
        $depositPending = isset($bookingDeposit['pending']) ? (float)$bookingDeposit['pending'] : 0.0;
        $amountDueAfterDeposit = max(0.0, $grandTotal - $depositPaid);
        ?>
        <tr>
          <td colspan="5" style="padding: 5px; text-align: right;"><strong>Phụ phí bàn:</strong></td>
          <td style="padding: 5px; text-align: right;">
            <strong><?php echo number_format($surcharge, 0, ',', '.') ?>đ</strong>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="padding: 5px; text-align: right; font-size:19px;"><strong>Tổng hóa đơn:</strong></td>
          <td style="padding: 5px; text-align: right;font-size:19px;">
            <strong><?php echo number_format($grandTotal, 0, ',', '.') ?>đ</strong>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="padding: 5px; text-align: right;"><strong>Đặt cọc đã thu:</strong></td>
          <td style="padding: 5px; text-align: right;">
            <strong>-<?php echo number_format($depositPaid, 0, ',', '.'); ?>đ</strong>
          </td>
        </tr>
        <tr>
          <td colspan="5" style="padding: 5px; text-align: right; font-size:19px;"><strong>Tổng tiền cần thanh toán</strong></td>
          <td style="padding: 5px; text-align: right;font-size:19px;">
            <strong><?php echo number_format($amountDueAfterDeposit, 0, ',', '.'); ?>đ</strong>
          </td>
        </tr>


      </tbody>
    </table>

    <br><br>

    <table style="width: 100%;">
      <tr>
        <td style="text-align: center; border: none;">
          <strong>Người mua hàng</strong><br>(Chữ ký số nếu có)
        </td>
        <td style="text-align: center; border: none;">
          <strong>Người bán hàng</strong><br>(Chữ ký điện tử, chữ ký số)
        </td>
      </tr>
    </table>

    <p style="text-align: center; font-style: italic;">(Cần kiểm tra, đối chiếu khi lập, nhận hóa đơn)</p>
    <p colspan="6" style="padding: 5px; text-align: center;"><strong>Xin cảm ơn và hẹn gặp lại quý khách!</strong>
    </p>
  </div>

  <!-- Thêm nút xuất hóa đơn -->
  <div class="text-center mb-4">
    <form action="page/hoadon/send_invoice_email.php" method="post">
      <input type="hidden" name="idHD" value="<?php echo $idHD; ?>">
      <input type="hidden" name="email" value="<?php echo $email; ?>">
      <button type="submit" class="btn btn-primary">Xuất hóa đơn qua Email</button>
    </form>
  </div>

</div>
