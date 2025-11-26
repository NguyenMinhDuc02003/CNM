<?php
/**
 * Offline job: tính luật gợi ý món từ bảng chitietdonhang và lưu vào bảng chatbot_item_rules.
 * Mặc định xét dữ liệu 30 ngày gần nhất, support tối thiểu 2 đơn, confidence tối thiểu 10%.
 *
 * Chạy bằng CLI: php chat_app/bin/build_item_rules.php
 * Có thể cấu hình qua biến môi trường:
 *   RECO_DAYS=30         // số ngày gần nhất
 *   RECO_MIN_SUPPORT=2   // số đơn tối thiểu chứa cả A và B
 *   RECO_MIN_CONF=0.1    // ngưỡng confidence (0-1)
 */

require_once __DIR__ . '/../database/Database_connection.php';

function env_or_default(string $key, $default) {
    $val = getenv($key);
    return ($val === false || $val === '') ? $default : $val;
}

$daysWindow = (int)env_or_default('RECO_DAYS', 30);
$minSupport = max(1, (int)env_or_default('RECO_MIN_SUPPORT', 2));
$minConfidence = (float)env_or_default('RECO_MIN_CONF', 0.1);

if (php_sapi_name() !== 'cli') {
    echo "Script này chỉ chạy qua CLI.\n";
    exit(1);
}

try {
    $db = new Database_connection();
    $pdo = $db->connect();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Lấy danh sách món theo đơn trong cửa sổ thời gian
    $stmt = $pdo->prepare("
        SELECT idDH, idmonan
        FROM chitietdonhang
        WHERE TrangThai <> 'cancelled'
          AND created_at >= (NOW() - INTERVAL :days DAY)
    ");
    $stmt->bindValue(':days', $daysWindow, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "Không có dữ liệu chitietdonhang trong {$daysWindow} ngày gần nhất.\n";
        exit(0);
    }

    $orders = [];
    foreach ($rows as $r) {
        $orderId = (int)$r['idDH'];
        $itemId = (int)$r['idmonan'];
        if (!isset($orders[$orderId])) {
            $orders[$orderId] = [];
        }
        $orders[$orderId][$itemId] = true; // unique per order
    }

    $totalOrders = count($orders);
    if ($totalOrders === 0) {
        echo "Không có đơn hợp lệ.\n";
        exit(0);
    }

    $itemCounts = [];
    $pairCounts = [];

    foreach ($orders as $itemsMap) {
        $items = array_keys($itemsMap);
        $count = count($items);
        for ($i = 0; $i < $count; $i++) {
            $a = $items[$i];
            $itemCounts[$a] = ($itemCounts[$a] ?? 0) + 1;
            for ($j = $i + 1; $j < $count; $j++) {
                $b = $items[$j];
                $key = $a < $b ? "{$a}|{$b}" : "{$b}|{$a}";
                $pairCounts[$key] = ($pairCounts[$key] ?? 0) + 1;
            }
        }
    }

    if (empty($pairCounts)) {
        echo "Không có cặp món nào để tạo luật.\n";
        exit(0);
    }

    // Tạo bảng nếu chưa có
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_item_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lhs_ids VARCHAR(255) NOT NULL,
            rhs_id INT NOT NULL,
            support_count INT NOT NULL,
            lhs_count INT NOT NULL,
            confidence DECIMAL(8,4) NOT NULL,
            lift DECIMAL(10,4) DEFAULT NULL,
            valid_from DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_lhs (lhs_ids),
            KEY idx_rhs (rhs_id),
            KEY idx_conf (confidence)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM chatbot_item_rules");

    $insert = $pdo->prepare("
        INSERT INTO chatbot_item_rules (lhs_ids, rhs_id, support_count, lhs_count, confidence, lift, valid_from)
        VALUES (:lhs_ids, :rhs_id, :support_count, :lhs_count, :confidence, :lift, :valid_from)
    ");

    $ruleCount = 0;
    $now = (new DateTime())->format('Y-m-d H:i:s');

    foreach ($pairCounts as $key => $pairSupport) {
        if ($pairSupport < $minSupport) {
            continue;
        }
        [$a, $b] = array_map('intval', explode('|', $key));
        $countA = $itemCounts[$a] ?? 0;
        $countB = $itemCounts[$b] ?? 0;
        if ($countA > 0) {
            $confAB = $pairSupport / $countA;
            if ($confAB >= $minConfidence) {
                $liftAB = ($countB > 0 && $totalOrders > 0) ? ($pairSupport * $totalOrders) / ($countA * $countB) : null;
                $insert->execute([
                    ':lhs_ids' => (string)$a,
                    ':rhs_id' => $b,
                    ':support_count' => $pairSupport,
                    ':lhs_count' => $countA,
                    ':confidence' => $confAB,
                    ':lift' => $liftAB,
                    ':valid_from' => $now,
                ]);
                $ruleCount++;
            }
        }
        if ($countB > 0) {
            $confBA = $pairSupport / $countB;
            if ($confBA >= $minConfidence) {
                $liftBA = ($countA > 0 && $totalOrders > 0) ? ($pairSupport * $totalOrders) / ($countB * $countA) : null;
                $insert->execute([
                    ':lhs_ids' => (string)$b,
                    ':rhs_id' => $a,
                    ':support_count' => $pairSupport,
                    ':lhs_count' => $countB,
                    ':confidence' => $confBA,
                    ':lift' => $liftBA,
                    ':valid_from' => $now,
                ]);
                $ruleCount++;
            }
        }
    }

    $pdo->commit();
    echo "Đã xây dựng {$ruleCount} luật gợi ý từ {$totalOrders} đơn (cửa sổ {$daysWindow} ngày).\n";
    echo "Ngưỡng support >= {$minSupport}, confidence >= {$minConfidence}.\n";
    exit(0);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Lỗi khi xây dựng luật: " . $e->getMessage() . "\n");
    exit(1);
}
