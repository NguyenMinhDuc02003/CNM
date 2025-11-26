<?php
if (!function_exists('face_service_base_url')) {
    function face_service_base_url(): string
    {
        $url = getenv('FACE_SERVICE_URL');
        if (!$url) {
            $url = 'http://127.0.0.1:8001';
        }
        return rtrim($url, '/');
    }
}

if (!function_exists('format_employee_code')) {
    function format_employee_code(int $employeeId): string
    {
        return sprintf('NV%03d', max(0, $employeeId));
    }
}

if (!function_exists('face_service_enroll')) {
    function face_service_enroll(string $employeeCode, string $employeeName, string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            return ['success' => false, 'message' => 'File ảnh không tồn tại'];
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $payload = [
            'employee_id' => $employeeCode,
            'employee_name' => $employeeName,
            'image_base64' => 'data:image/jpeg;base64,' . $imageData,
        ];

        $ch = curl_init(face_service_base_url() . '/faces/enroll');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => $error];
        }

        $decoded = json_decode($response, true);
        if ($status >= 200 && $status < 300) {
            return ['success' => true, 'data' => $decoded];
        }

        return [
            'success' => false,
            'message' => $decoded['detail'] ?? ('HTTP ' . $status),
        ];
    }
}

if (!function_exists('face_service_delete')) {
    function face_service_delete(string $employeeCode): array
    {
        $endpoint = face_service_base_url() . '/faces/' . rawurlencode($employeeCode);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => $error];
        }

        $decoded = json_decode((string) $response, true);
        if ($status >= 200 && $status < 300) {
            return [
                'success' => true,
                'message' => $decoded['status'] ?? 'Đã xóa dữ liệu khuôn mặt.',
            ];
        }
        if ($status === 404) {
            return [
                'success' => true,
                'message' => 'Không tìm thấy dữ liệu khuôn mặt để xóa.',
            ];
        }

        return [
            'success' => false,
            'message' => $decoded['detail'] ?? ('HTTP ' . $status),
        ];
    }
}
