<?php
// Admin/kaiadmin-lite-1.2.0/page/chamcong/webcam_test.php
$schedulePath = __DIR__ . '/../../includes/attendance_schedule.php';
$attendanceSchedule = (function () use ($schedulePath) {
    $fallback = [
        'checkin' => [
            'start' => '08:00',
            'end' => '10:00',
        ],
        'checkout' => [
            'start' => '17:00',
            'end' => '20:00',
        ],
    ];
    if (file_exists($schedulePath)) {
        $loaded = include $schedulePath;
        if (is_array($loaded)) {
            return $loaded;
        }
    }
    return $fallback;
})();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Face Attendance Test</title>
    <style>
        :root {
            color-scheme: dark;
            --glass: rgba(255, 255, 255, 0.07);
            --ring: rgba(161, 174, 255, 0.35);
            --primary: #6f7dff;
            --primary-strong: #4c5bff;
            --success: #4ade80;
            --warning: #fbbf24;
            --danger: #fb7185;
            --text-light: rgba(255, 255, 255, 0.85);
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Poppins", "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at 20% 20%, #1f2a6f, #050616);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: var(--text-light);
        }
        .screen {
            width: 100%;
            max-width: 640px;
        }
        .glass-card {
            position: relative;
            background: var(--glass);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 28px;
            padding: 1.5rem;
            backdrop-filter: blur(18px);
            box-shadow: 0 30px 80px rgba(3, 7, 37, 0.6);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .camera-shell {
            position: relative;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            background: #020411;
            aspect-ratio: 3 / 4;
        }
        video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .hud {
            position: absolute;
            top: 1rem;
            left: 1rem;
            right: 1rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.65rem 1rem;
            background: rgba(3, 7, 18, 0.7);
            border-radius: 999px;
            font-size: 0.95rem;
            line-height: 1.3;
        }
        .hud-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--warning);
            box-shadow: 0 0 12px rgba(255, 255, 255, 0.25);
            transition: background 0.2s ease, box-shadow 0.2s ease;
        }
        .hud-indicator[data-state="info"] { background: var(--primary); box-shadow: 0 0 12px rgba(111, 125, 255, 0.6); }
        .hud-indicator[data-state="ready"] { background: var(--success); box-shadow: 0 0 12px rgba(74, 222, 128, 0.65); }
        .hud-indicator[data-state="warning"] { background: var(--warning); box-shadow: 0 0 12px rgba(251, 191, 36, 0.6); }
        .hud-indicator[data-state="error"] { background: var(--danger); box-shadow: 0 0 12px rgba(251, 113, 133, 0.6); }
        .hud-indicator[data-state="muted"] { background: rgba(255, 255, 255, 0.5); box-shadow: none; }
        .rings {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }
        .rings span {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30%;
            height: 30%;
            border: 2px solid var(--ring);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 6s infinite ease-in-out;
        }
        .rings span:nth-child(2) { width: 45%; height: 45%; animation-delay: 1s; }
        .rings span:nth-child(3) { width: 60%; height: 60%; animation-delay: 2s; }
        @keyframes pulse {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.9); }
            40% { opacity: 1; }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(1.2); }
        }
        .action-bar {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .action-bar button {
            flex: 1;
            min-width: 140px;
            border: none;
            border-radius: 999px;
            padding: 0.9rem 1.2rem;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .action-bar button.primary {
            background: linear-gradient(120deg, var(--primary), var(--primary-strong));
            color: #fff;
            box-shadow: 0 10px 30px rgba(111, 125, 255, 0.3);
        }
        .action-bar button.ghost {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-light);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }
        .action-bar button:active { transform: scale(0.97); }
        .modal {
            position: fixed;
            inset: 0;
            background: rgba(2, 3, 24, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.2rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
            z-index: 99;
        }
        .modal.visible {
            opacity: 1;
            pointer-events: all;
        }
        .modal-box {
            width: min(420px, 100%);
            background: rgba(10, 12, 33, 0.95);
            border-radius: 24px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(12px);
            box-shadow: 0 25px 60px rgba(2, 3, 24, 0.65);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .modal-box[data-tone="success"] { --tone-color: var(--success); }
        .modal-box[data-tone="warning"] { --tone-color: var(--warning); }
        .modal-box[data-tone="error"] { --tone-color: var(--danger); }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        .modal-title {
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--tone-color, var(--primary));
        }
        .modal-close {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 1.2rem;
            cursor: pointer;
        }
        .modal-body {
            font-size: 0.95rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.92);
        }
        .modal-action {
            align-self: flex-end;
            background: var(--tone-color, var(--primary));
            color: #030617;
            border: none;
            border-radius: 999px;
            padding: 0.75rem 1.4rem;
            font-weight: 600;
            cursor: pointer;
        }
        @media (max-width: 640px) {
            body { padding: 1rem; }
            .glass-card { padding: 1.1rem; border-radius: 20px; }
            .camera-shell { border-radius: 16px; }
            .hud { font-size: 0.85rem; padding: 0.55rem 0.9rem; }
            .action-bar { flex-direction: column; }
            .action-bar button { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .rings span { display: none; }
            .action-bar button,
            .modal,
            .glass-card { transition: none !important; animation: none !important; }
        }
    </style>
</head>
<body>
    <div class="screen">
        <div class="glass-card">
            <div class="camera-shell">
                <video id="camera" autoplay playsinline></video>
                <div class="hud">
                    <span class="hud-indicator" id="hudIndicator" data-state="muted"></span>
                    <p class="hud-text" id="hudMessage">Đang khởi động camera...</p>
                </div>
                <div class="rings">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <div class="action-bar">
                <button class="primary" id="captureBtn">Chụp thủ công</button>
                <button class="ghost" id="stopBtn">Tạm dừng</button>
            </div>
        </div>
    </div>

    <input type="hidden" id="threshold" value="0.65" step="0.01" min="0" max="1">

    <div class="modal" id="notifyModal">
        <div class="modal-box" data-tone="info">
            <div class="modal-header">
                <div class="modal-title" id="modalTitle">Thông báo</div>
                <button class="modal-close" data-close-modal>&times;</button>
            </div>
            <div class="modal-body" id="modalMessage"></div>
            <button class="modal-action" data-close-modal>Đã hiểu</button>
        </div>
    </div>
    <script>
        const ATTENDANCE_CONFIG = <?php echo json_encode($attendanceSchedule, JSON_UNESCAPED_SLASHES); ?>;
        const video = document.getElementById('camera');
        const captureBtn = document.getElementById('captureBtn');
        const stopBtn = document.getElementById('stopBtn');
        const thresholdInput = document.getElementById('threshold');
        const hudMessageEl = document.getElementById('hudMessage');
        const hudIndicator = document.getElementById('hudIndicator');
        const modalRoot = document.getElementById('notifyModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalMessage = document.getElementById('modalMessage');
        const modalBox = modalRoot.querySelector('.modal-box');
        const scheduleText = buildWindowText();
        const FACE_API = 'http://127.0.0.1:8001/faces/recognize';
        const ATTENDANCE_API = '/CNM/Admin/kaiadmin-lite-1.2.0/api/attendance_log.php';
        const CAPTURE_INTERVAL = 3000;
        const LATE_GRACE_MINUTES = 15;
        const REDIRECT_DELAY = 3500;

        let stream = null;
        let captureInterval = null;
        let isProcessing = false;
        let cooldown = false;
        let lastWindowState = { allowed: false, mode: null, status: 'outside' };
        setHudMessage('Đang khởi động camera...', 'muted');
        setupModalEvents();

        function setupModalEvents() {
            modalRoot.addEventListener('click', (event) => {
                if (event.target === modalRoot) {
                    hideModal();
                }
            });
            document.querySelectorAll('[data-close-modal]').forEach(btn => {
                btn.addEventListener('click', hideModal);
            });
        }

        function showModal(title, message, tone = 'info') {
            modalBox.dataset.tone = tone;
            modalTitle.textContent = title;
            modalMessage.innerHTML = '';
            const lines = Array.isArray(message) ? message : String(message).split('\n');
            lines.forEach(line => {
                const p = document.createElement('p');
                p.textContent = line;
                modalMessage.appendChild(p);
            });
            modalRoot.classList.add('visible');
        }

        function hideModal() {
            modalRoot.classList.remove('visible');
        }

        function setHudMessage(message, state = 'info') {
            hudMessageEl.textContent = message;
            hudIndicator.dataset.state = state;
        }

        function parseServerMessage(raw) {
            if (!raw) {
                return 'Đã xảy ra lỗi không xác định.';
            }
            if (typeof raw === 'string') {
                try {
                    const parsed = JSON.parse(raw);
                    if (parsed?.message) {
                        return parsed.message;
                    }
                } catch (_) {
                    // ignore parse error
                }
                return raw;
            }
            if (raw?.message) {
                return raw.message;
            }
            return String(raw);
        }

        function showErrorAndReturn(message) {
            const friendly = parseServerMessage(message);
            setHudMessage(friendly, 'error');
            showModal('Không ghi được chấm công', friendly, 'error');
            setTimeout(() => {
                window.location.href = 'index.php?page=erp.php';
            }, REDIRECT_DELAY);
        }

        function buildWindowText() {
            const sections = [];
            if (ATTENDANCE_CONFIG?.checkin) {
                sections.push(`Checkin: ${formatWindow(ATTENDANCE_CONFIG.checkin)}`);
            }
            if (ATTENDANCE_CONFIG?.checkout) {
                sections.push(`Checkout: ${formatWindow(ATTENDANCE_CONFIG.checkout)}`);
            }
            return sections.join(' | ');
        }

        function formatWindow(windowDef) {
            const windows = normalizeWindows(windowDef);
            if (!windows.length) return 'Chưa cấu hình';
            return windows.map(win => `${win.start} - ${win.end}`).join(', ');
        }

        function ensureAttendanceWindow(autoTrigger) {
            lastWindowState = getAttendanceWindowState();
            if (lastWindowState.allowed) {
                if (lastWindowState.status === 'late') {
                    setHudMessage('Trong khung giờ cho phép (trễ).', 'warning');
                    if (!autoTrigger) {
                        showModal('Chấm công trễ', [
                            `Bạn vẫn được phép chấm công trong ${LATE_GRACE_MINUTES} phút kể từ giờ quy định.`,
                            scheduleText
                        ], 'warning');
                    }
                } else {
                    const label = lastWindowState.mode === 'checkout' ? 'Trong khung checkout' : 'Trong khung checkin';
                    setHudMessage(label, 'ready');
                }
                return true;
            }
            if (!autoTrigger) {
                const message = lastWindowState.message || 'Chưa tới giờ chấm công.';
                showErrorAndReturn(message);
            }
            setHudMessage(lastWindowState.message || 'Ngoài khung giờ chấm công', 'muted');
            return false;
        }

        function getAttendanceWindowState() {
            const minutesNow = new Date().getHours() * 60 + new Date().getMinutes();
            const checkinState = evaluateWindow(ATTENDANCE_CONFIG.checkin, minutesNow, true);
            if (checkinState.allowed) {
                return checkinState;
            }
            const checkoutState = evaluateWindow(ATTENDANCE_CONFIG.checkout, minutesNow, false);
            if (checkoutState.allowed) {
                return checkoutState;
            }
            return {
                allowed: false,
                mode: null,
                status: 'outside',
                message: 'Chưa tới hoặc đã quá giờ chấm công.'
            };
        }

        function evaluateWindow(windowDef, minutesNow, isCheckin) {
            const windows = normalizeWindows(windowDef);
            if (!windows.length) {
                return { allowed: false, mode: null, status: 'outside' };
            }
            let lateMatch = null;
            for (const win of windows) {
                const startMinutes = parseTimeToMinutes(win.start);
                const endMinutes = parseTimeToMinutes(win.end);
                if (startMinutes === null || endMinutes === null) {
                    continue;
                }
                if (isWithinWindowRange(minutesNow, startMinutes, endMinutes)) {
                    return {
                        allowed: true,
                        mode: isCheckin ? 'checkin' : 'checkout',
                        status: 'on_time'
                    };
                }
                if (isCheckin && isWithinLateRange(minutesNow, startMinutes, endMinutes)) {
                    lateMatch = {
                        allowed: true,
                        mode: 'checkin',
                        status: 'late',
                        message: 'Chấm công trễ (trong 15 phút cho phép).'
                    };
                }
            }
            if (lateMatch) return lateMatch;
            return { allowed: false, mode: null, status: 'outside' };
        }

        function normalizeWindows(windowDef) {
            if (!windowDef) return [];
            if (Array.isArray(windowDef)) {
                return windowDef
                    .filter(w => w && typeof w.start === 'string' && typeof w.end === 'string')
                    .map(w => ({ start: w.start, end: w.end }));
            }
            if (typeof windowDef === 'object' && windowDef.start && windowDef.end) {
                return [{ start: windowDef.start, end: windowDef.end }];
            }
            return [];
        }

        function parseTimeToMinutes(text) {
            const [h, m] = String(text).split(':').map(Number);
            if (Number.isNaN(h) || Number.isNaN(m)) return null;
            return h * 60 + m;
        }

        function isWithinWindowRange(minutesNow, startMinutes, endMinutes) {
            if (endMinutes < startMinutes) {
                return minutesNow >= startMinutes || minutesNow <= endMinutes;
            }
            return minutesNow >= startMinutes && minutesNow <= endMinutes;
        }

        function isWithinLateRange(minutesNow, startMinutes, endMinutes) {
            if (LATE_GRACE_MINUTES <= 0) return false;
            if (isWithinWindowRange(minutesNow, startMinutes, endMinutes)) return false;
            let diff = minutesNow - endMinutes;
            if (diff < 0) {
                diff += 1440;
            }
            return diff > 0 && diff <= LATE_GRACE_MINUTES;
        }

        async function initCamera() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
                setHudMessage('Webcam đã sẵn sàng.', 'ready');
                startAutoCapture();
            } catch (err) {
                setHudMessage('Không thể truy cập webcam.', 'error');
                showModal('Không thể truy cập webcam', err.message, 'error');
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
            }
            stopAutoCapture();
            setHudMessage('Đã tắt webcam.', 'muted');
        }

        function startAutoCapture() {
            if (captureInterval) return;
            captureInterval = setInterval(() => captureAndSend(true), CAPTURE_INTERVAL);
            setHudMessage('Đang tự động nhận diện...', 'info');
        }

        function stopAutoCapture() {
            if (captureInterval) {
                clearInterval(captureInterval);
                captureInterval = null;
            }
        }

        async function captureAndSend(auto = false) {
            if (!ensureAttendanceWindow(auto)) {
                return;
            }
            if (!stream || video.readyState !== 4) {
                setHudMessage('Webcam chưa sẵn sàng.', 'warning');
                return;
            }
            if (auto && (isProcessing || cooldown)) {
                return;
            }
            if (!auto && cooldown) {
                setHudMessage('Đang xử lý lượt trước...', 'warning');
                showModal('Vui lòng chờ', 'Hệ thống đang xử lý lượt trước, thử lại sau ít giây.', 'warning');
                return;
            }
            isProcessing = true;
            setHudMessage('Đang nhận diện...', 'info');

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            const threshold = parseFloat(thresholdInput.value) || 0.65;

            try {
                const response = await fetch(FACE_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ image_base64: dataUrl, threshold })
                });

                if (!response.ok) {
                    throw new Error(await response.text());
                }

                const data = await response.json();
                renderResult(data);

                if (data.matched && data.employee_id) {
                    cooldown = true;
                    setTimeout(() => cooldown = false, 7000);
                    await logAttendance(data, dataUrl);
                }
            } catch (err) {
                const friendly = parseServerMessage(err.message);
                setHudMessage(friendly, 'error');
                if (!auto) {
                    showModal('Không nhận diện được', friendly, 'error');
                }
            } finally {
                isProcessing = false;
            }
        }

        function renderResult(data) {
            if (!data?.matched) {
                setHudMessage('Không khớp gương mặt nào.', 'warning');
                return;
            }
            const label = formatEmployeeLabel(data.employee_id);
            const score = data.score ? `${(data.score * 100).toFixed(1)}%` : '-';
            setHudMessage(`Đã nhận diện ${label} (${score})`, 'ready');
        }

        function extractNumericId(employeeCode) {
            if (!employeeCode) return null;
            const numeric = parseInt(String(employeeCode).replace(/\D/g, ''), 10);
            return Number.isNaN(numeric) ? null : numeric;
        }

        function formatEmployeeLabel(employeeCode) {
            const numeric = extractNumericId(employeeCode);
            if (numeric === null) {
                return employeeCode ?? '-';
            }
            const padded = String(numeric).padStart(3, '0');
            return `NV${padded} (#${numeric})`;
        }

        function notifyAttendance(action, employeeCode, attendanceState = {}) {
            const actionText = action === 'checkout' ? 'Checkout' : 'Checkin';
            const isLate = action === 'checkin' && attendanceState.checkin_status === 'late';
            const label = formatEmployeeLabel(employeeCode);
            const message = `${actionText}${isLate ? ' (trễ)' : ''} thành công cho ${label}`;
            setHudMessage(message, 'ready');
            const detailLines = [message];
            if (attendanceState?.message) {
                detailLines.push(attendanceState.message);
            }
            showModal('Chấm công thành công', detailLines, 'success');
            if (action === 'checkin' || action === 'checkout') {
                setTimeout(() => {
                    window.location.href = 'index.php?page=erp.php';
                }, REDIRECT_DELAY);
            }
        }

        async function logAttendance(data, imageDataUrl) {
            const payload = {
                employee_id: data.employee_id,
                score: data.score,
                matched: data.matched,
                image_base64: imageDataUrl,
                source: 'webcam',
                attendance_status: lastWindowState.status,
                attendance_mode: lastWindowState.mode
            };
            try {
                const resp = await fetch(ATTENDANCE_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                if (!resp.ok) {
                    throw new Error(await resp.text());
                }
                const result = await resp.json();
                if (result.success) {
                    const actionLabel = result.action === 'checkout' ? 'checkout' : 'checkin';
                    setHudMessage(`Đã ghi nhận ${actionLabel} (log #${result.log_id}).`, 'ready');
                    notifyAttendance(result.action, data.employee_id, result.attendance_state);
                } else {
                    showErrorAndReturn(result.message || 'Không ghi được chấm công.');
                }
            } catch (err) {
                showErrorAndReturn(err.message);
            }
        }

        captureBtn.addEventListener('click', () => captureAndSend(false));
        stopBtn.addEventListener('click', () => {
            stopAutoCapture();
            stopCamera();
        });
        window.addEventListener('beforeunload', () => {
            stopAutoCapture();
            stopCamera();
        });

        initCamera();
    </script>
</body>
</html>
