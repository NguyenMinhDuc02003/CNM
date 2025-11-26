<?php

/**
 * Helper for resolving the currently logged-in employee or customer
 * from the main restaurant system so the chat module can transparently
 * reuse existing identities.
 */
require_once dirname(__DIR__) . '/config/chat.php';

// Both Admin and User modules declare the same connect_db class name.
// Only load their definition when it hasn't been defined yet to avoid
// "Cannot declare class connect_db" fatals when the Admin portal already
// included its own clsconnect.php beforehand.
if (!class_exists('connect_db')) {
    $adminConnect = dirname(__DIR__) . '/../Admin/kaiadmin-lite-1.2.0/class/clsconnect.php';
    $userConnect  = dirname(__DIR__) . '/../User/restoran-1.0.0/class/clsconnect.php';

    if (file_exists($adminConnect)) {
        require_once $adminConnect;
    } elseif (file_exists($userConnect)) {
        require_once $userConnect;
    } else {
        throw new RuntimeException('Không tìm thấy file clsconnect.php để kết nối CSDL.');
    }
}

class ChatIdentity
{
    /**
     * Ensure the PHP session is initialised before accessing $_SESSION.
     */
    public static function bootstrapSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Return the current identity metadata or null if the visitor has not
     * signed in via the Admin (employee) or User (customer) portals.
     *
     * @return array{
     *     type: string,
     *     external_id: int,
     *     email: string,
     *     name: string,
     *     avatar: string
     * }|null
     */
    public static function resolve(): ?array
    {
        self::bootstrapSession();

        $db = new connect_db();

        if (!empty($_SESSION['nhanvien_id'])) {
            $rows = $db->xuatdulieu_prepared(
                "SELECT idnv, HoTen, Email, HinhAnh FROM nhanvien WHERE idnv = ? LIMIT 1",
                [$_SESSION['nhanvien_id']]
            );

            if (!empty($rows)) {
                return self::formatIdentity($rows[0], 'employee');
            }
        }

        if (!empty($_SESSION['khachhang_id'])) {
            $rows = $db->xuatdulieu_prepared(
                "SELECT idKH, tenKH, email, hinhanh FROM khachhang WHERE idKH = ? LIMIT 1",
                [$_SESSION['khachhang_id']]
            );

            if (!empty($rows)) {
                return self::formatIdentity($rows[0], 'customer');
            }
        }

        return null;
    }

    /**
     * Display-friendly label that we can show in the UI.
     */
    public static function describeIdentity(array $identity): string
    {
        $type = $identity['type'] === 'employee' ? 'Nhân viên' : 'Khách hàng';
        return sprintf('%s (%s)', $identity['name'], $type);
    }

    /**
     * Build a consistent avatar path for employees or customers.
     */
    private static function formatIdentity(array $row, string $type): array
    {
        if ($type === 'employee') {
            $email = $row['Email'] ?? '';
            return [
                'type' => 'employee',
                'external_id' => (int) ($row['idnv'] ?? 0),
                'email' => $email,
                'name' => $row['HoTen'] ?? 'Nhân viên',
                'avatar' => self::buildAvatarPath($row['HinhAnh'] ?? '', $email, $type),
            ];
        }

        $email = $row['email'] ?? '';
        return [
            'type' => 'customer',
            'external_id' => (int) ($row['idKH'] ?? 0),
            'email' => $email,
            'name' => $row['tenKH'] ?? 'Khách hàng',
            'avatar' => self::buildAvatarPath($row['hinhanh'] ?? '', $email, $type),
        ];
    }

    /**
     * Generate a relative path (if an avatar exists) or fallback to Gravatar.
     */
    private static function buildAvatarPath(string $fileName, string $email, string $type): string
    {
        $cleanFile = trim($fileName);
        if ($cleanFile !== '') {
            if ($type === 'employee') {
                return '../Admin/kaiadmin-lite-1.2.0/assets/img/' . $cleanFile;
            }

            return '../User/restoran-1.0.0/img/' . $cleanFile;
        }

        $hash = md5(strtolower(trim($email)));
        return 'https://www.gravatar.com/avatar/' . $hash . '?d=mp';
    }
}
