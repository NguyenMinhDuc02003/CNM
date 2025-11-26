<?php
require_once 'class/clsconnect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function hasPermission($permission, $permissions): bool
{
    if (!is_string($permission) || !is_array($permissions)) {
        return false;
    }

    $normalized = array_map(
        static fn($item) => trim((string) $item),
        $permissions
    );

    return in_array(trim($permission), $normalized, true);
}

if (empty($_SESSION['nhanvien_id'])) {
    echo "<script>window.location.href='index.php?page=dangnhap';</script>";
    exit;
}

// Ensure we have a mysqli connection available
$db = $GLOBALS['admin_db'] ?? new connect_db();
$conn = $GLOBALS['conn'] ?? $db->getConnection();
$GLOBALS['conn'] = $conn;
$GLOBALS['admin_db'] = $db;

$permissions = [];
$employeeId = (int) $_SESSION['nhanvien_id'];
$queryRole = <<<'SQL'
    SELECT v.idvaitro, v.quyen
    FROM nhanvien AS n
    JOIN vaitro AS v ON n.idvaitro = v.idvaitro
    WHERE n.idnv = ?
SQL;

$stmt = mysqli_prepare($conn, $queryRole);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $employeeId);
    if (mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $roleData = $result ? mysqli_fetch_assoc($result) : null;
        if (!empty($roleData['quyen'])) {
            $permissions = array_map('trim', explode(',', (string) $roleData['quyen']));
        }
        if (isset($roleData['idvaitro'])) {
            $_SESSION['vaitro_id'] = (int) $roleData['idvaitro'];
        }
    } else {
        error_log('config_permission execute failed: ' . mysqli_error($conn));
    }
    mysqli_stmt_close($stmt);
} else {
    error_log('config_permission prepare failed: ' . mysqli_error($conn));
}
