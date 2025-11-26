<?php
/**
 * Admin authentication helper.
 *
 * Centralises session bootstrap, login, logout, and guard helpers so the logic
 * can be reused across admin pages without duplicating code.
 */

require_once __DIR__ . '/../class/clsconnect.php';

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

if (!defined('ADMIN_AUTH_ENABLED')) {
    define('ADMIN_AUTH_ENABLED', true);
}

/**
 * Ensure the PHP session is started.
 */
function admin_auth_bootstrap_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!ADMIN_AUTH_ENABLED) {
        $_SESSION['nhanvien_id'] = $_SESSION['nhanvien_id'] ?? 0;
        $_SESSION['nhanvien_name'] = $_SESSION['nhanvien_name'] ?? 'Quản trị viên (demo)';
        $_SESSION['nhanvien_email'] = $_SESSION['nhanvien_email'] ?? 'demo@example.com';
    }
}

/**
 * Return true if an admin user is already logged in.
 */
function admin_auth_is_logged_in(): bool
{
    admin_auth_bootstrap_session();

    if (!ADMIN_AUTH_ENABLED) {
        return true;
    }

    return isset($_SESSION['nhanvien_id']) && is_numeric($_SESSION['nhanvien_id']);
}

/**
 * Fetch the current admin user row or null if not available.
 *
 * @return array|null
 */
function admin_auth_current_user(): ?array
{
    if (!admin_auth_is_logged_in()) {
        return null;
    }

    if (!ADMIN_AUTH_ENABLED) {
        return [
            'idnv' => $_SESSION['nhanvien_id'] ?? 0,
            'hoten' => $_SESSION['nhanvien_name'] ?? 'Quản trị viên (demo)',
            'email' => $_SESSION['nhanvien_email'] ?? 'demo@example.com',
        ];
    }

    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    try {
        $db = new connect_db();
        $sql = "SELECT idnv, hoten, email, password 
                FROM nhanvien 
                WHERE idnv = ? LIMIT 1";
        $result = $db->xuatdulieu_prepared($sql, [(int) $_SESSION['nhanvien_id']]);
        $cachedUser = $result[0] ?? null;
        return $cachedUser;
    } catch (Throwable $th) {
        error_log('admin_auth_current_user error: ' . $th->getMessage());
        return null;
    }
}

/**
 * Attempt to log the admin user in.
 *
 * @param string $email
 * @param string $password
 * @param string|null $errorMessage Populated with a human-readable error on failure.
 *
 * @return bool True when authentication succeeds.
 */
function admin_auth_attempt_login(string $email, string $password, ?string &$errorMessage = null): bool
{
    admin_auth_bootstrap_session();

    if (!ADMIN_AUTH_ENABLED) {
        return true;
    }

    $email = trim($email);
    $password = trim($password);

    if ($email === '' || $password === '') {
        $errorMessage = 'Vui lòng nhập đầy đủ email và mật khẩu.';
        return false;
    }

    try {
        $db = new connect_db();
        $sql = "SELECT idnv, hoten, email, password 
                FROM nhanvien 
                WHERE email = ? LIMIT 1";
        $rows = $db->xuatdulieu_prepared($sql, [$email]);
    } catch (Throwable $th) {
        error_log('admin_auth_attempt_login error: ' . $th->getMessage());
        $errorMessage = 'Không thể kết nối tới máy chủ. Vui lòng thử lại.';
        return false;
    }

    if (empty($rows)) {
        $errorMessage = 'Email không tồn tại.';
        return false;
    }

    $user = $rows[0];
    $storedHash = $user['password'] ?? '';

    $passwordIsValid = false;
    $hashInfo = password_get_info((string) $storedHash);
    if (($hashInfo['algo'] ?? 0) !== 0) {
        $passwordIsValid = password_verify($password, $storedHash);
    } elseif ($storedHash !== '') {
        $passwordIsValid = hash_equals($storedHash, md5($password));
    }

    if (!$passwordIsValid) {
        $errorMessage = 'Sai mật khẩu.';
        return false;
    }

    $_SESSION['nhanvien_id'] = (int) $user['idnv'];
    $_SESSION['nhanvien_email'] = $user['email'] ?? null;
    $_SESSION['nhanvien_name'] = $user['hoten'] ?? null;

    return true;
}

/**
 * Log the current admin user out.
 */
function admin_auth_logout(): void
{
    admin_auth_bootstrap_session();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

/**
 * Redirect to the admin login page when the user is not logged in.
 *
 * @param string $redirectUrl Optional redirect target.
 */
function admin_auth_require_login(string $redirectUrl = 'page/dangnhap.php'): void
{
    admin_auth_bootstrap_session();

    if (!ADMIN_AUTH_ENABLED) {
        return;
    }

    if (!admin_auth_is_logged_in()) {
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Redirect away from the login page when the user is already authenticated.
 *
 * @param string $redirectUrl The target after a successful check.
 */
function admin_auth_redirect_if_logged_in(string $redirectUrl = 'index.php'): void
{
    admin_auth_bootstrap_session();

    if (!ADMIN_AUTH_ENABLED) {
        return;
    }

    if (admin_auth_is_logged_in()) {
        header('Location: ' . $redirectUrl);
        exit();
    }
}

/**
 * Remove Vietnamese accents so permission keys can be compared reliably.
 */
function admin_auth_remove_vietnamese_accents(string $value): string
{
    static $search = [
        'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
        'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
        'ì', 'í', 'ị', 'ỉ', 'ĩ',
        'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
        'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
        'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
        'đ',
        'À', 'Á', 'Ạ', 'Ả', 'Ã', 'Â', 'Ầ', 'Ấ', 'Ậ', 'Ẩ', 'Ẫ', 'Ă', 'Ằ', 'Ắ', 'Ặ', 'Ẳ', 'Ẵ',
        'È', 'É', 'Ẹ', 'Ẻ', 'Ẽ', 'Ê', 'Ề', 'Ế', 'Ệ', 'Ể', 'Ễ',
        'Ì', 'Í', 'Ị', 'Ỉ', 'Ĩ',
        'Ò', 'Ó', 'Ọ', 'Ỏ', 'Õ', 'Ô', 'Ồ', 'Ố', 'Ộ', 'Ổ', 'Ỗ', 'Ơ', 'Ờ', 'Ớ', 'Ợ', 'Ở', 'Ỡ',
        'Ù', 'Ú', 'Ụ', 'Ủ', 'Ũ', 'Ư', 'Ừ', 'Ứ', 'Ự', 'Ử', 'Ữ',
        'Ỳ', 'Ý', 'Ỵ', 'Ỷ', 'Ỹ',
        'Đ',
    ];

    static $replace = [
        'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
        'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
        'i', 'i', 'i', 'i', 'i',
        'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
        'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
        'y', 'y', 'y', 'y', 'y',
        'd',
        'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A', 'A',
        'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E', 'E',
        'I', 'I', 'I', 'I', 'I',
        'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O', 'O',
        'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U', 'U',
        'Y', 'Y', 'Y', 'Y', 'Y',
        'D',
    ];

    return str_replace($search, $replace, $value);
}

/**
 * Convert a permission label into a canonical lower-case comparison key.
 */
function admin_auth_normalize_permission_key(string $permission): string
{
    $permission = trim($permission);
    $permission = function_exists('mb_strtolower')
        ? mb_strtolower($permission, 'UTF-8')
        : strtolower($permission);
    $permission = admin_auth_remove_vietnamese_accents($permission);
    $permission = preg_replace('/[^a-z0-9\s]/', ' ', $permission);
    $permission = preg_replace('/\s+/', ' ', $permission);
    return trim((string) $permission);
}

/**
 * Normalise a list of permission labels by trimming, deduplicating and keeping human readable values.
 *
 * @param array<int, string> $permissions
 * @return array<int, string>
 */
function admin_auth_normalize_permission_list(array $permissions): array
{
    $unique = [];

    foreach ($permissions as $permission) {
        if (!is_string($permission)) {
            continue;
        }

        $clean = preg_replace('/\s+/', ' ', trim($permission));
        if ($clean === '') {
            continue;
        }

        $key = admin_auth_normalize_permission_key($clean);
        if ($key === '') {
            continue;
        }

        $unique[$key] = $clean;
    }

    return array_values($unique);
}

/**
 * Fetch the current role (if any) of the logged in employee.
 *
 * @return array{idvaitro:int|null, tenvaitro:string|null, quyen:string|null}|null
 */
function admin_auth_current_role(): ?array
{
    if (!admin_auth_is_logged_in()) {
        return null;
    }

    static $cachedRole = false;
    if ($cachedRole !== false) {
        return $cachedRole;
    }

    try {
        $db = new connect_db();
        $sql = "SELECT v.idvaitro, v.tenvaitro, v.quyen
                FROM nhanvien n
                LEFT JOIN vaitro v ON n.idvaitro = v.idvaitro
                WHERE n.idnv = ? LIMIT 1";
        $rows = $db->xuatdulieu_prepared($sql, [(int) $_SESSION['nhanvien_id']]);
        $cachedRole = $rows[0] ?? null;
        return $cachedRole;
    } catch (Throwable $th) {
        error_log('admin_auth_current_role error: ' . $th->getMessage());
        $cachedRole = null;
        return null;
    }
}

/**
 * Return both canonical and original permission labels for the logged in user.
 *
 * @return array{canonical: array<string, string>, original: array<int, string>}
 */
function admin_auth_current_permissions(): array
{
    static $cachedPermissions = null;
    if ($cachedPermissions !== null) {
        return $cachedPermissions;
    }

    $role = admin_auth_current_role();
    if (!$role || empty($role['quyen'])) {
        $cachedPermissions = ['canonical' => [], 'original' => []];
        return $cachedPermissions;
    }

    $raw = array_filter(array_map('trim', explode(',', (string) $role['quyen'])));
    $originalList = admin_auth_normalize_permission_list($raw);

    $canonical = [];
    foreach ($originalList as $label) {
        $key = admin_auth_normalize_permission_key($label);
        if ($key === '') {
            continue;
        }
        $canonical[$key] = $label;
    }

    $cachedPermissions = [
        'canonical' => $canonical,
        'original' => $originalList,
    ];

    return $cachedPermissions;
}

/**
 * Convenience wrapper returning only the original labels.
 *
 * @return array<int, string>
 */
function admin_auth_permissions_original(): array
{
    $data = admin_auth_current_permissions();
    return $data['original'];
}

/**
 * Check if the current admin has a specific permission label.
 */
function admin_auth_has_permission(string $permission): bool
{
    $key = admin_auth_normalize_permission_key($permission);
    if ($key === '') {
        return false;
    }

    $data = admin_auth_current_permissions();
    return isset($data['canonical'][$key]);
}

/**
 * Require at least one permission from the provided list.
 */
function admin_auth_has_any_permission(array $permissions): bool
{
    foreach ($permissions as $permission) {
        if (admin_auth_has_permission($permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Render a simple permission denied message.
 */
function admin_auth_render_forbidden(string $message = 'Ban khong co quyen truy cap chuc nang nay.'): void
{
    http_response_code(403);
    echo "<div class='container mt-4'><div class='alert alert-danger' role='alert'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div></div>';
}
