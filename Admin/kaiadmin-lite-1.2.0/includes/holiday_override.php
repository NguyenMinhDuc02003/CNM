<?php
/**
 * Override danh sách ngày lễ (dạng string) để tính OT 300% / ngày nghỉ 200%.
 * - Chấp nhận định dạng 'Y-m-d' (áp dụng đúng năm) hoặc 'm-d' (áp dụng mọi năm).
 * - Thêm các ngày Tết Âm lịch, Giỗ Tổ Hùng Vương từng năm tại đây.
 *
 * Ví dụ:
 * return [
 *     '2025-01-29', '2025-01-30', '2025-01-31', '2025-02-01', '2025-02-02', // Tết Âm lịch 2025
 *     '2025-04-08', // Giỗ Tổ 10/3 Âm lịch 2025 (chuyển đổi sang dương lịch)
 *     '09-01', '09-02', '09-03', // Quốc khánh và ngày liền kề (đã có sẵn, giữ lại nếu muốn chắc chắn)
 * ];
 */
return [];
