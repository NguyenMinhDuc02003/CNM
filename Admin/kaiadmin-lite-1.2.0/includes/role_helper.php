<?php
/**
 * Helper functions for resolving display-friendly role names.
 */

if (!function_exists('formatRoleLabel')) {
    /**
     * Convert a raw role name into the label stored in nhanvien.ChucVu.
     */
    function formatRoleLabel($rawRole)
    {
        $normalized = strtolower(trim((string) $rawRole));

        // Map the legacy values stored in vaitro.tenvaitro to their proper labels.
        $roleMap = [
            'quan ly' => 'Quản lý',
            'phuc vu' => 'Phục vụ',
            'thu ngan' => 'Thu ngân',
            'dau bep' => 'Đầu bếp',
            'phu bep' => 'Phụ bếp',
        ];

        if (isset($roleMap[$normalized])) {
            return $roleMap[$normalized];
        }

        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_convert_case')) {
            return mb_convert_case($rawRole, MB_CASE_TITLE, 'UTF-8');
        }

        return ucwords($rawRole);
    }
}

if (!function_exists('resolveRoleLabel')) {
    /**
     * Fetch the role label for a given role id.
     */
    function resolveRoleLabel($conn, $roleId)
    {
        $label = '';
        $stmtRole = $conn->prepare('SELECT tenvaitro FROM vaitro WHERE idvaitro = ? LIMIT 1');

        if ($stmtRole) {
            $stmtRole->bind_param('i', $roleId);

            if ($stmtRole->execute()) {
                $resultRole = $stmtRole->get_result();
                if ($resultRole && ($roleRow = $resultRole->fetch_assoc())) {
                    $label = formatRoleLabel($roleRow['tenvaitro'] ?? '');
                }
                if ($resultRole) {
                    $resultRole->free();
                }
            }

            $stmtRole->close();
        }

        return $label;
    }
}
