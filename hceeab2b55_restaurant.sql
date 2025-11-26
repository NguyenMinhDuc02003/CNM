-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 25, 2025 lúc 07:58 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `hceeab2b55_restaurant`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `attendance_log`
--

CREATE TABLE `attendance_log` (
  `id` int(11) NOT NULL,
  `idnv` int(11) NOT NULL,
  `matched` tinyint(1) NOT NULL,
  `score` float NOT NULL,
  `captured_at` datetime DEFAULT current_timestamp(),
  `source` enum('webcam','mobile','manual') DEFAULT 'webcam',
  `snapshot_path` varchar(255) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `ban`
--

CREATE TABLE `ban` (
  `idban` int(11) NOT NULL,
  `SoBan` varchar(10) DEFAULT NULL,
  `soluongKH` int(11) NOT NULL,
  `TrangThai` enum('empty','pending','reserved','occupied') DEFAULT 'empty',
  `MaKV` int(11) DEFAULT NULL,
  `zone` char(1) NOT NULL DEFAULT 'A',
  `qr_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `ban`
--

INSERT INTO `ban` (`idban`, `SoBan`, `soluongKH`, `TrangThai`, `MaKV`, `zone`, `qr_token`) VALUES
(1, 'NT-A1', 4, 'empty', 4, 'A', '2e3bdbdbdb147e1c88ac3150be95ed05'),
(2, 'TT-A1', 6, 'occupied', 3, 'A', '0d481bfccaacc541a50b337882870c85'),
(3, 'T1-A1', 8, 'empty', 1, 'A', 'a7e5bfdee7e985f59f04b757c90f5e0b'),
(4, 'T2-A1', 2, 'empty', 2, 'A', '7d51e4dc4ca2af29052071b12ee78d82'),
(5, 'NT-A2', 4, 'empty', 4, 'A', '4df0a7a5fede74d64cb976c7a3f1afb1'),
(6, 'T1-A2', 6, 'empty', 1, 'A', 'f522afeee0adb7703959431a394ff4e9'),
(7, 'T1-A3', 8, 'occupied', 1, 'A', '73d724dbcf7e6162d961ec8d152a9d39'),
(8, 'T1-A4', 2, 'empty', 1, 'A', 'a27be636e16e180caf4c33926e0cd31c'),
(9, 'T2-A2', 4, 'empty', 2, 'A', '34c1857dd25dbf00b679fdb14aa4ff79'),
(10, 'T2-A3', 6, 'empty', 2, 'A', 'edcd07001ab4bd5af59f9f2bd25f0f75'),
(11, 'T2-A4', 8, 'empty', 2, 'A', '74b9bcc22e61bac05900062b62bc9ddc'),
(12, 'TT-A2', 2, 'empty', 2, 'A', '5d1a1014f8f9e612bf1f30097853acbf'),
(13, 'TT-B1', 4, 'empty', 3, 'B', '3175ed8fe148dc1313ad6eebb2f68d64'),
(14, 'TT-B2', 6, 'empty', 3, 'B', 'cf5b39346b23b60430199110a80b8d4c'),
(15, 'NT-B1', 8, 'empty', 4, 'B', '9a9fa5be6f7508c56795c3c93551d0e1'),
(16, 'NT-B2', 2, 'empty', 4, 'B', '97d00318128d79f8377c3299e2f02cee'),
(17, 'NT-B3', 4, 'empty', 4, 'B', '583091ad55530c0f7a432c73d5ceb2de'),
(18, 'NT-B4', 6, 'empty', 4, 'B', 'bd5435c2a17bcf48a282f2a2ea0ed98b'),
(19, 'NT-B5', 8, 'empty', 4, 'B', 'f667b1aa02875023d5adf5c328a6452f'),
(20, 'NT-B6', 2, 'empty', 4, 'B', '4bb85e968c52b02dd22aa034e45092ef'),
(21, 'T1-B1', 4, 'empty', 1, 'B', 'f52f8ff43e8bf0315effba47aea78bc9'),
(22, 'T1-B2', 6, 'empty', 1, 'B', '13165785debcd103bf9039e01457e73b'),
(23, 'T1-B3', 8, 'empty', 1, 'B', '307fed3f5be33648b3f227fbabcf13e0'),
(24, 'T1-B4', 2, 'empty', 1, 'B', '9a3125d08fc12252df5fff0f649d77a2'),
(25, 'T1-C1', 4, 'empty', 1, 'C', 'df978ada0f38227e6725c2acb17cf863'),
(26, 'T1-C2', 6, 'empty', 1, 'C', 'e02df0e1667b415ab9f1c5330ed538e3'),
(27, 'T1-C3', 8, 'empty', 1, 'C', '01146786123f436a82a39226f9ea5dbe'),
(28, 'T1-C4', 2, 'empty', 1, 'C', 'f9d35bb22705baa34debd166adca65b6'),
(29, 'B29', 4, 'empty', 1, 'C', '31ab82f4607497f07aa7cccefe76a7e9'),
(30, 'T2-C1', 6, 'empty', 2, 'C', 'c32d07fb48962b15e1ecae4463d3b3d0'),
(31, 'T2-C2', 8, 'empty', 2, 'C', '856292ef3af8962c9662942522f0a43f'),
(32, 'T2-C3', 2, 'empty', 2, 'C', '945008aa6cd07fe482533afa2271445f'),
(33, 'T2-C4', 4, 'empty', 2, 'C', '5cedd3bcdd16c2049c7fbf5b2a9fefb7'),
(34, 'T2-C5', 6, 'empty', 2, 'C', '86b50a2c6df7a1d94672b439b4ae1145'),
(35, 'T2-C6', 8, 'empty', 2, 'C', '6501f8fa625c072424b556dc0a11e688'),
(36, 'T2-C7', 2, 'empty', 2, 'C', '53479f68ab3157ea005e407bcf78e106'),
(37, 'T2-D1', 4, 'empty', 2, 'D', '3b4aa90e1d8677ae631e0d138eabde37'),
(38, 'T2-D2', 6, 'empty', 2, 'D', '875f0159661ca14ff768cc38dbe2ce57'),
(39, 'TT-D1', 8, 'empty', 3, 'D', '3e43d02f00c274eacf3c524cead30ac0'),
(40, 'TT-D2', 2, 'empty', 3, 'D', '234d72f802fb223ff503c3e893f2e87f'),
(41, 'TT-D3', 4, 'empty', 3, 'D', '2ed3984c998d7e98aadf25fead257034'),
(42, 'TT-D4', 6, 'empty', 3, 'D', '531c5911444a3b83f37e0617349d16f8'),
(43, 'TT-D5', 6, 'empty', 3, 'D', 'fddc0fea5975c4cfbef4732c3e862d14'),
(44, 'TT-D6', 2, 'empty', 3, 'D', '2af35233845eb0ba2ea6f7a2d8d50466'),
(45, 'TT-D7', 4, 'empty', 3, 'D', '87fe914f9de2707dd8dce7dab3d66fc5'),
(46, 'TT-D8', 6, 'empty', 3, 'D', 'cb70842ce5bfb55d764d5fd8b4403ccd'),
(47, 'TT-D9', 8, 'empty', 3, 'D', 'bb020b434fc88a139477f33421f51626'),
(48, 'T1-A5', 2, 'empty', 1, 'A', '7a9c9bee9039b4cda8d45a7d316cb81d'),
(49, 'T1-A6', 4, 'empty', 1, 'A', '835dc86863b1dd116160529b4bb34cd9'),
(50, 'T1-B5', 6, 'empty', 1, 'B', 'bbf0e037e58250119d5f720218b54ee6'),
(51, 'T1-B6', 8, 'empty', 1, 'B', '38a5a21ae39e35acef546bb2d190a3ff'),
(52, 'T1-C5', 4, 'empty', 1, 'C', '22ec43292be3dac92362b645af31859d'),
(53, 'T1-C6', 6, 'empty', 1, 'C', 'dd138d4c112f9e3fb00a84a1ab419b79'),
(54, 'T1-D1', 8, 'empty', 1, 'D', '593916d90eef74d3350543b6105b443c'),
(55, 'T1-D2', 10, 'empty', 1, 'D', '019ca195dfb7de3a9ed31ab07ac038d6'),
(56, 'T2-A5', 2, 'empty', 2, 'A', 'd60dccd19eb8bd693ab95d9980b5af24'),
(57, 'T2-A6', 4, 'empty', 2, 'A', '82b214fdcff56008fbc91d2b758de4d9'),
(58, 'T2-B1', 6, 'empty', 2, 'B', 'e921eae4a2ed422fe9104ab976859574'),
(59, 'T2-B2', 8, 'empty', 2, 'B', '3472f17ad966f9762d67f7efdc62a22b'),
(60, 'T2-C8', 4, 'empty', 2, 'C', 'ec62e0f0915fe037fc2bd49eeb58db0f'),
(61, 'T2-C9', 6, 'empty', 2, 'C', '2295fbab503e9718fda41873ff3a6412'),
(62, 'T2-D3', 8, 'empty', 2, 'D', 'dfce8700b27b29cac9b6546cb18ddbb4'),
(63, 'T2-D4', 10, 'empty', 2, 'D', '64910def1c17c4f1c7d82902ac999052'),
(64, 'TT-A3', 2, 'occupied', 3, 'A', '277cf04be49c0af473d1ae4607687906'),
(65, 'TT-A4', 4, 'occupied', 3, 'A', 'd1ce8923785cec11532312a90315b656'),
(66, 'TT-B3', 6, 'empty', 3, 'B', '8fae39a97d2e9248b7257c5a909c5e72'),
(67, 'TT-B4', 8, 'empty', 3, 'B', '678f39244a496a69bee4b88724a116b2'),
(68, 'TT-C1', 4, 'empty', 3, 'C', '721d6b7120d8277e0ba8e637907908b3'),
(69, 'TT-C2', 6, 'empty', 3, 'C', '25886edb49f28adc1c2c08104ad05653'),
(70, 'TT-D10', 8, 'empty', 3, 'D', '7ff79c7c1b9d64714f21f5dbbd22b0f4'),
(71, 'TT-D11', 10, 'empty', 3, 'D', 'a44e8a7379615a6de0976701d57f8b41'),
(72, 'NT-A3', 2, 'empty', 4, 'A', '1314cdddbfd90033325c029ea5b5300a'),
(73, 'NT-A4', 4, 'empty', 4, 'A', '503e82e29c652e7e74572ef345499dbd'),
(74, 'NT-B7', 6, 'empty', 4, 'B', '30e094c2fa20db64bfb6cd383ad2bbae'),
(75, 'NT-B8', 8, 'empty', 4, 'B', '9b78bc8b6d443e8f20e38f307de0577f'),
(76, 'NT-C1', 4, 'empty', 4, 'C', 'ec395d50a3f9192540fb81e00d46ace7'),
(77, 'NT-C2', 6, 'empty', 4, 'C', '6c18c955f86c3e8fbe00108fd5499d2b'),
(78, 'NT-D1', 8, 'empty', 4, 'D', '6024f4587d5a8968ed04941498fe24cb'),
(79, 'NT-D2', 10, 'empty', 4, 'D', 'a239844fe0434e0b2688eff1674bdceb');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `calamviec`
--

CREATE TABLE `calamviec` (
  `idca` int(11) NOT NULL,
  `tenca` text NOT NULL,
  `giobatdau` time NOT NULL,
  `gioketthuc` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `calamviec`
--

INSERT INTO `calamviec` (`idca`, `tenca`, `giobatdau`, `gioketthuc`) VALUES
(1, 'sang', '08:00:00', '14:00:00'),
(2, 'chieu', '14:00:00', '18:00:00'),
(3, 'toi', '18:00:00', '22:00:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chamcong`
--

CREATE TABLE `chamcong` (
  `id` int(11) NOT NULL,
  `idnv` int(11) NOT NULL,
  `idca` int(11) DEFAULT NULL,
  `ngay` date NOT NULL,
  `checkin_at` datetime DEFAULT NULL,
  `checkin_status` enum('on_time','late') DEFAULT 'on_time',
  `checkout_at` datetime DEFAULT NULL,
  `last_score` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chamcong`
--

INSERT INTO `chamcong` (`id`, `idnv`, `idca`, `ngay`, `checkin_at`, `checkin_status`, `checkout_at`, `last_score`) VALUES
(5, 36, NULL, '2025-09-02', '2025-09-02 08:00:03', 'on_time', '2025-09-02 17:38:03', 0.808637),
(15, 101, NULL, '2025-11-01', '2025-11-01 08:05:00', 'on_time', '2025-11-01 17:10:00', 1),
(16, 101, NULL, '2025-11-02', '2025-11-01 08:00:00', 'on_time', '2025-11-01 17:05:00', 1),
(17, 101, NULL, '2025-11-03', '2025-11-01 08:10:00', 'on_time', '2025-11-01 18:05:00', 1),
(18, 101, NULL, '2025-11-04', '2025-11-01 08:15:00', 'on_time', '2025-11-01 17:00:00', 1),
(19, 101, NULL, '2025-11-05', '2025-11-01 08:00:00', 'on_time', '2025-11-01 19:00:00', 1),
(20, 102, NULL, '2025-11-01', '2025-11-01 08:00:00', 'on_time', '2025-11-01 17:30:00', 1),
(21, 102, NULL, '2025-11-02', '2025-11-01 08:05:00', 'on_time', '2025-11-01 17:00:00', 1),
(22, 102, NULL, '2025-11-03', '2025-11-01 08:00:00', 'on_time', '2025-11-01 18:30:00', 1),
(23, 102, NULL, '2025-11-04', '2025-11-01 08:10:00', 'on_time', '2025-11-01 17:20:00', 1),
(24, 103, NULL, '2025-11-06', '2025-11-06 08:00:00', 'on_time', '2025-11-06 20:00:00', 1),
(25, 103, NULL, '2025-11-07', '2025-11-07 08:00:00', 'on_time', '2025-11-07 18:00:00', 1),
(26, 103, NULL, '2025-11-08', '2025-11-08 08:00:00', 'on_time', '2025-11-08 17:00:00', 1),
(27, 104, NULL, '2025-11-09', '2025-11-09 21:00:00', 'on_time', '2025-11-10 05:30:00', 1),
(28, 104, NULL, '2025-11-10', '2025-11-10 22:30:00', 'on_time', '2025-11-11 06:30:00', 1),
(29, 36, 2, '2025-11-22', '2025-11-22 14:04:09', 'on_time', '2025-11-22 22:04:09', 0.698251);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chatbot_item_rules`
--

CREATE TABLE `chatbot_item_rules` (
  `id` int(11) NOT NULL,
  `lhs_ids` varchar(255) NOT NULL,
  `rhs_id` int(11) NOT NULL,
  `support_count` int(11) NOT NULL,
  `lhs_count` int(11) NOT NULL,
  `confidence` decimal(8,4) NOT NULL,
  `lift` decimal(10,4) DEFAULT NULL,
  `valid_from` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chatbot_item_rules`
--

INSERT INTO `chatbot_item_rules` (`id`, `lhs_ids`, `rhs_id`, `support_count`, `lhs_count`, `confidence`, `lift`, `valid_from`, `created_at`, `updated_at`) VALUES
(5, '2', 3, 2, 3, 0.6667, 1.8889, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19'),
(6, '3', 2, 2, 6, 0.3333, 1.8889, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19'),
(7, '9', 10, 2, 3, 0.6667, 5.6667, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19'),
(8, '10', 9, 2, 2, 1.0000, 5.6667, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19'),
(9, '3', 9, 2, 6, 0.3333, 1.8889, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19'),
(10, '9', 3, 2, 3, 0.6667, 1.8889, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19'),
(11, '3', 6, 2, 6, 0.3333, 1.8889, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19'),
(12, '6', 3, 2, 3, 0.6667, 1.8889, '2025-11-24 07:16:19', '2025-11-24 13:16:19', '2025-11-24 13:16:19');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chatrooms`
--

CREATE TABLE `chatrooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `userid` int(10) UNSIGNED NOT NULL,
  `msg` text DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT current_timestamp(),
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(255) DEFAULT NULL,
  `attachment_size` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_conversation`
--

CREATE TABLE `chat_conversation` (
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `customer_user_id` int(10) UNSIGNED NOT NULL,
  `last_cashier_user_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('open','waiting','closed') NOT NULL DEFAULT 'open',
  `last_message_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `chat_conversation`
--

INSERT INTO `chat_conversation` (`conversation_id`, `customer_user_id`, `last_cashier_user_id`, `status`, `last_message_at`, `created_at`) VALUES
(1, 2, 1, 'open', '2025-11-22 12:00:01', '2025-11-10 09:35:42'),
(2, 11, 1, 'open', '2025-11-10 10:24:18', '2025-11-10 10:18:12'),
(3, 14, 124, 'open', '2025-11-17 18:30:08', '2025-11-10 10:28:14'),
(4, 1, 1, 'open', '2025-11-24 10:04:36', '2025-11-17 15:40:08'),
(5, 193, 193, 'open', '2025-11-22 11:59:34', '2025-11-22 17:59:34');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_conversation_agents`
--

CREATE TABLE `chat_conversation_agents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `cashier_user_id` int(10) UNSIGNED NOT NULL,
  `joined_at` datetime DEFAULT current_timestamp(),
  `left_at` datetime DEFAULT NULL,
  `handover_note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `chat_conversation_agents`
--

INSERT INTO `chat_conversation_agents` (`id`, `conversation_id`, `cashier_user_id`, `joined_at`, `left_at`, `handover_note`) VALUES
(1, 1, 1, '2025-11-10 09:30:00', NULL, NULL),
(2, 2, 1, '2025-11-10 10:18:00', NULL, NULL),
(3, 3, 1, '2025-11-10 10:28:00', NULL, NULL),
(4, 4, 1, '2025-11-17 15:40:08', NULL, NULL),
(5, 1, 124, '2025-11-17 16:54:42', NULL, NULL),
(6, 3, 124, '2025-11-17 18:30:08', NULL, NULL),
(7, 5, 193, '2025-11-22 17:59:34', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_message`
--

CREATE TABLE `chat_message` (
  `chat_message_id` bigint(20) UNSIGNED NOT NULL,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `to_user_id` int(10) UNSIGNED NOT NULL,
  `from_user_id` int(10) UNSIGNED NOT NULL,
  `chat_message` text DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('No','Yes') NOT NULL DEFAULT 'No',
  `attachment_name` varchar(255) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(255) DEFAULT NULL,
  `attachment_size` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `chat_message`
--

INSERT INTO `chat_message` (`chat_message_id`, `conversation_id`, `to_user_id`, `from_user_id`, `chat_message`, `timestamp`, `status`, `attachment_name`, `attachment_path`, `attachment_mime`, `attachment_size`) VALUES
(1, 1, 1, 2, 'tôi muốn được tư vấn', '2025-11-10 09:35:42', 'Yes', NULL, NULL, NULL, NULL),
(2, 1, 1, 1, 'chào', '2025-11-10 09:55:00', 'Yes', NULL, NULL, NULL, NULL),
(3, 1, 2, 1, 'chào bạn', '2025-11-10 10:13:46', 'Yes', NULL, NULL, NULL, NULL),
(4, 1, 1, 2, 'tôi muốn đặt bàn', '2025-11-10 10:14:39', 'Yes', NULL, NULL, NULL, NULL),
(5, 1, 2, 1, 'mình muốn đặt bàn cho bao nhiêu người', '2025-11-10 10:15:32', 'Yes', NULL, NULL, NULL, NULL),
(6, 2, 1, 11, 'hi shop', '2025-11-10 10:18:12', 'Yes', NULL, NULL, NULL, NULL),
(7, 2, 11, 1, 'chào bạn', '2025-11-10 10:18:40', 'Yes', NULL, NULL, NULL, NULL),
(8, 1, 1, 2, '10 người', '2025-11-10 10:18:46', 'Yes', NULL, NULL, NULL, NULL),
(9, 1, 2, 1, 'bạn cho mình xin thông tin với nhé', '2025-11-10 10:19:09', 'Yes', NULL, NULL, NULL, NULL),
(10, 2, 1, 11, 'hôm nay bên mình có chương trình gì không', '2025-11-10 10:19:45', 'Yes', NULL, NULL, NULL, NULL),
(11, 2, 11, 1, 'đợi mình xem trên website ABC giúp em nha', '2025-11-10 10:24:18', 'Yes', NULL, NULL, NULL, NULL),
(12, 3, 1, 14, 'mình muốn đặt bàn', '2025-11-10 10:28:14', 'Yes', NULL, NULL, NULL, NULL),
(13, 1, 1, 2, 'phòng vip còn bàn không', '2025-11-11 09:34:52', 'Yes', NULL, NULL, NULL, NULL),
(14, 4, 1, 1, 'helo', '2025-11-17 10:42:34', 'Yes', NULL, NULL, NULL, NULL),
(15, 1, 1, 2, 'hello\n', '2025-11-17 10:53:01', 'Yes', NULL, NULL, NULL, NULL),
(16, 1, 2, 124, 'hello', '2025-11-17 10:55:14', 'Yes', NULL, NULL, NULL, NULL),
(17, 1, 2, 124, 'chào khách\n', '2025-11-17 11:01:28', 'Yes', NULL, NULL, NULL, NULL),
(18, 1, 2, 124, 'hi', '2025-11-17 11:07:38', 'Yes', NULL, NULL, NULL, NULL),
(19, 4, 1, 124, 'chào', '2025-11-17 11:17:34', 'Yes', NULL, NULL, NULL, NULL),
(20, 4, 1, 124, 'chào', '2025-11-17 11:28:00', 'Yes', NULL, NULL, NULL, NULL),
(21, 4, 1, 1, 'HI', '2025-11-17 12:15:02', 'Yes', NULL, NULL, NULL, NULL),
(22, 1, 2, 1, 'HI', '2025-11-17 12:15:31', 'Yes', NULL, NULL, NULL, NULL),
(23, 4, 1, 1, 'xin chào', '2025-11-17 12:20:31', 'Yes', NULL, NULL, NULL, NULL),
(24, 1, 1, 2, 'cc', '2025-11-17 12:21:22', 'Yes', NULL, NULL, NULL, NULL),
(25, 1, 2, 1, 'hả', '2025-11-17 12:21:42', 'Yes', NULL, NULL, NULL, NULL),
(26, 1, 1, 2, 'Chào cậu', '2025-11-17 12:22:00', 'Yes', NULL, NULL, NULL, NULL),
(27, 1, 2, 1, 'Bạn muốn mình hỗ trợ gì', '2025-11-17 12:22:16', 'Yes', NULL, NULL, NULL, NULL),
(28, 1, 1, 2, 'Bạn giúp tôi đặt bàn cho ngày mai được không\n', '2025-11-17 12:22:37', 'Yes', NULL, NULL, NULL, NULL),
(29, 1, 2, 1, 'Không', '2025-11-17 12:22:49', 'Yes', NULL, NULL, NULL, NULL),
(30, 1, 1, 2, 'Ok vậy cảm ơn', '2025-11-17 12:22:59', 'Yes', NULL, NULL, NULL, NULL),
(31, 1, 1, 2, 'xinloividaden', '2025-11-17 12:23:19', 'Yes', NULL, NULL, NULL, NULL),
(32, 1, 2, 1, 'Cho mình xin ngày giờ,sddt ,Gmail và họ tên của bạn nhé', '2025-11-17 12:24:22', 'Yes', NULL, NULL, NULL, NULL),
(33, 1, 2, 1, 'Hoặc bạn có thể tự đặt qua website\n', '2025-11-17 12:24:34', 'Yes', NULL, NULL, NULL, NULL),
(34, 1, 1, 2, 'thôi được rồi để tôi tự đặt', '2025-11-17 12:25:10', 'Yes', NULL, NULL, NULL, NULL),
(35, 1, 2, 1, 'ok vậy đi cho khỏe', '2025-11-17 12:25:32', 'Yes', NULL, NULL, NULL, NULL),
(36, 1, 2, 124, 'Xin lỗi bạn cần mình hỗ trợ gì', '2025-11-17 12:29:24', 'Yes', NULL, NULL, NULL, NULL),
(37, 1, 1, 2, 'Nhà hàng có bán cơm tấm không\n', '2025-11-17 12:30:03', 'Yes', NULL, NULL, NULL, NULL),
(38, 1, 2, 124, 'Không bạn ạ', '2025-11-17 12:30:14', 'Yes', NULL, NULL, NULL, NULL),
(39, 1, 1, 2, 'Thế có món gì ngon', '2025-11-17 12:30:21', 'Yes', NULL, NULL, NULL, NULL),
(40, 1, 2, 124, 'có cl', '2025-11-17 12:30:47', 'Yes', NULL, NULL, NULL, NULL),
(41, 1, 1, 2, 'wtf', '2025-11-17 12:30:52', 'Yes', NULL, NULL, NULL, NULL),
(42, 1, 1, 2, 'cc', '2025-11-17 12:31:19', 'Yes', NULL, NULL, NULL, NULL),
(43, 1, 2, 124, 'cc', '2025-11-17 12:31:42', 'Yes', NULL, NULL, NULL, NULL),
(44, 1, 1, 2, 'Là sao', '2025-11-17 12:52:43', 'Yes', NULL, NULL, NULL, NULL),
(45, 1, 2, 124, 'ủa alo', '2025-11-17 12:52:56', 'Yes', NULL, NULL, NULL, NULL),
(46, 1, 1, 2, 'là sao', '2025-11-17 12:53:01', 'Yes', NULL, NULL, NULL, NULL),
(47, 1, 1, 2, 'ALO', '2025-11-17 12:57:35', 'Yes', NULL, NULL, NULL, NULL),
(48, 4, 1, 193, 'Xin chào', '2025-11-22 11:59:25', 'Yes', NULL, NULL, NULL, NULL),
(49, 5, 193, 193, 'Xin chào', '2025-11-22 11:59:34', 'Yes', NULL, NULL, NULL, NULL),
(50, 1, 1, 2, 'Xin chào', '2025-11-22 12:00:01', 'Yes', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chat_user_table`
--

CREATE TABLE `chat_user_table` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `external_id` int(10) UNSIGNED DEFAULT NULL,
  `user_type` varchar(50) NOT NULL DEFAULT 'customer',
  `user_email` varchar(191) NOT NULL DEFAULT '',
  `user_name` varchar(191) NOT NULL,
  `user_password` varchar(255) DEFAULT NULL,
  `user_profile` varchar(255) DEFAULT NULL,
  `user_status` varchar(20) NOT NULL DEFAULT 'Enable',
  `user_created_on` datetime DEFAULT current_timestamp(),
  `user_verification_code` varchar(191) DEFAULT NULL,
  `user_login_status` varchar(20) NOT NULL DEFAULT 'Logout',
  `user_token` varchar(255) DEFAULT NULL,
  `user_connection_id` varchar(255) DEFAULT NULL,
  `last_seen` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `chat_user_table`
--

INSERT INTO `chat_user_table` (`user_id`, `external_id`, `user_type`, `user_email`, `user_name`, `user_password`, `user_profile`, `user_status`, `user_created_on`, `user_verification_code`, `user_login_status`, `user_token`, `user_connection_id`, `last_seen`, `updated_at`) VALUES
(1, 7, 'employee', 'giahi@gmail.com', 'Lê Hoàng Gia Hi', NULL, '../Admin/kaiadmin-lite-1.2.0/assets/img/about-3.jpg', 'Enable', '2025-11-10 13:58:15', NULL, 'Login', '78c07d0f23b5e0a6bae8f215c03e95863c25f44c', '409', '2025-11-24 12:54:03', '2025-11-24 12:54:03'),
(2, 6, 'customer', 'tn6888295@gmail.com', 'Nguyễn Minh Đức', NULL, '../User/restoran-1.0.0/img/team-1.jpg', 'Enable', '2025-11-10 14:24:22', NULL, 'Login', '6a29bd2909439b02e0ff8e7997fea75068cb2aec', '126', '2025-11-24 13:08:37', '2025-11-24 13:08:37'),
(11, 29, 'customer', 'hanhan@gmail.com', 'Gia Hân', NULL, 'https://www.gravatar.com/avatar/429dbf378ad3cebdccba4ece1c13b153?d=mp', 'Enable', '2025-11-10 16:18:01', NULL, 'Logout', NULL, '383', '2025-11-10 16:55:48', '2025-11-17 18:52:02'),
(14, 3, 'customer', 'khc@gmail.com', 'Lê Văn C', NULL, '../User/restoran-1.0.0/img/1b086243c8d41bdd80f7f3d7416be4b7.jpg', 'Enable', '2025-11-10 16:27:50', NULL, 'Login', '871503aacab21e89313d2528d8017d4a96bac311', '311', '2025-11-10 16:35:41', '2025-11-17 18:52:02'),
(124, 4, 'employee', 'maipt@example.com', 'Phạm Thị Mai', NULL, '../Admin/kaiadmin-lite-1.2.0/assets/img/profile2.jpg', 'Enable', '2025-11-17 16:54:41', NULL, 'Login', '7531736a8e855539a17a896e08c7f1c57fdf4257', '522', '2025-11-17 18:52:46', '2025-11-17 18:52:46'),
(193, 36, 'employee', 'tn6888295@gmail.com', 'Minh Đức', NULL, '../Admin/kaiadmin-lite-1.2.0/assets/img/691ed6af42e78.jpg', 'Enable', '2025-11-20 16:46:45', NULL, 'Login', 'c4fa298e6251fd583dbc8bf036af1c79e946c94d', '91', '2025-11-25 13:21:08', '2025-11-25 13:21:08');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietdatban`
--

CREATE TABLE `chitietdatban` (
  `idChiTiet` int(11) NOT NULL,
  `madatban` int(11) NOT NULL,
  `idmonan` int(11) NOT NULL,
  `SoLuong` int(11) NOT NULL,
  `DonGia` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitietdatban`
--

INSERT INTO `chitietdatban` (`idChiTiet`, `madatban`, `idmonan`, `SoLuong`, `DonGia`) VALUES
(569, 196, 13, 1, 20000.00),
(570, 196, 6, 1, 85000.00),
(571, 196, 3, 1, 90000.00),
(572, 196, 1, 1, 50000.00),
(573, 196, 23, 1, 45000.00),
(574, 196, 9, 1, 200000.00),
(575, 196, 5, 1, 100000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietdonhang`
--

CREATE TABLE `chitietdonhang` (
  `idCTDH` int(11) NOT NULL,
  `idDH` int(11) DEFAULT NULL,
  `idmonan` int(11) DEFAULT NULL,
  `SoLuong` int(11) DEFAULT NULL,
  `DonGia` decimal(10,2) NOT NULL DEFAULT 0.00,
  `TrangThai` enum('preparing','ready','served','cancelled') DEFAULT 'preparing',
  `GhiChu` text DEFAULT NULL,
  `metadata` longtext DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `served_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitietdonhang`
--

INSERT INTO `chitietdonhang` (`idCTDH`, `idDH`, `idmonan`, `SoLuong`, `DonGia`, `TrangThai`, `GhiChu`, `metadata`, `sent_at`, `completed_at`, `served_at`, `created_at`, `updated_at`) VALUES
(228, 102, 4, 1, 95000.00, 'preparing', NULL, NULL, '2025-11-13 13:10:08', NULL, NULL, '2025-11-13 13:10:08', '2025-11-13 13:10:08'),
(229, 102, 2, 1, 75000.00, 'preparing', NULL, NULL, '2025-11-13 13:10:08', NULL, NULL, '2025-11-13 13:10:08', '2025-11-13 13:10:08'),
(231, 105, 6, 1, 85000.00, 'preparing', NULL, NULL, '2025-11-13 20:17:26', NULL, NULL, '2025-11-13 20:17:26', '2025-11-13 20:17:26'),
(232, 106, 16, 1, 25000.00, 'served', NULL, NULL, '2025-11-13 20:43:44', '2025-11-13 20:43:48', '2025-11-13 20:43:49', '2025-11-13 20:43:44', '2025-11-13 20:43:49'),
(233, 106, 1, 1, 50000.00, 'served', NULL, NULL, '2025-11-13 20:43:44', '2025-11-13 20:43:47', '2025-11-13 20:43:50', '2025-11-13 20:43:44', '2025-11-13 20:43:50'),
(234, 106, 4, 1, 95000.00, 'served', NULL, NULL, '2025-11-13 20:43:44', '2025-11-13 20:43:45', '2025-11-13 20:43:51', '2025-11-13 20:43:44', '2025-11-13 20:43:51'),
(235, 107, 3, 1, 90000.00, 'served', NULL, NULL, '2025-11-13 20:57:34', '2025-11-13 20:58:02', '2025-11-13 20:58:18', '2025-11-13 20:57:34', '2025-11-13 20:58:18'),
(236, 107, 2, 1, 75000.00, 'served', NULL, NULL, '2025-11-13 20:57:34', '2025-11-13 20:58:00', '2025-11-13 20:58:19', '2025-11-13 20:57:34', '2025-11-13 20:58:19'),
(240, 116, 3, 2, 90000.00, 'served', NULL, NULL, '2025-11-14 14:13:27', '2025-11-14 14:13:57', '2025-11-14 14:54:13', '2025-11-14 14:13:27', '2025-11-14 14:54:13'),
(241, 116, 3, 1, 90000.00, 'served', NULL, NULL, '2025-11-14 14:13:33', '2025-11-14 14:13:59', '2025-11-14 14:54:15', '2025-11-14 14:13:33', '2025-11-14 14:58:32'),
(242, 118, 10, 1, 15000.00, 'served', NULL, NULL, '2025-11-14 16:17:21', '2025-11-14 16:17:23', '2025-11-14 16:17:25', '2025-11-14 16:17:21', '2025-11-14 16:17:25'),
(243, 118, 9, 1, 200000.00, 'served', NULL, NULL, '2025-11-14 16:17:21', '2025-11-14 16:17:24', '2025-11-14 16:17:27', '2025-11-14 16:17:21', '2025-11-14 16:17:27'),
(244, 119, 2, 1, 75000.00, 'served', NULL, NULL, '2025-11-14 16:24:50', '2025-11-14 16:24:53', '2025-11-14 16:24:54', '2025-11-14 16:24:50', '2025-11-14 16:24:54'),
(245, 119, 3, 1, 90000.00, 'served', NULL, NULL, '2025-11-14 16:24:50', '2025-11-14 16:24:51', '2025-11-14 16:24:56', '2025-11-14 16:24:50', '2025-11-14 16:24:56'),
(259, 122, NULL, 1, 1000000.00, 'served', NULL, '{\"menu_set\":{\"id\":1,\"token\":\"set-1-mhyz13aq-1riv1v\",\"name\":\"Spring Night\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}', '2025-11-14 21:46:39', '2025-11-15 10:24:58', '2025-11-15 10:25:01', '2025-11-14 21:46:39', '2025-11-15 10:25:01'),
(260, 123, 17, 1, 25000.00, 'preparing', NULL, NULL, '2025-11-15 11:53:46', NULL, NULL, '2025-11-15 11:53:46', '2025-11-15 11:53:46'),
(261, 123, 11, 1, 15000.00, 'preparing', NULL, NULL, '2025-11-15 11:53:46', NULL, NULL, '2025-11-15 11:53:46', '2025-11-15 11:53:46'),
(262, 123, 11, 1, 15000.00, 'preparing', NULL, NULL, '2025-11-15 11:54:30', NULL, NULL, '2025-11-15 11:54:30', '2025-11-15 11:54:30'),
(263, 123, 9, 1, 200000.00, 'preparing', NULL, NULL, '2025-11-15 11:54:30', NULL, NULL, '2025-11-15 11:54:30', '2025-11-15 11:54:30'),
(264, 123, 21, 2, 20000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(265, 123, 9, 1, 200000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(266, 123, 21, 1, 20000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(267, 123, 9, 1, 200000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(268, 123, 21, 1, 20000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(269, 123, 9, 1, 200000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(270, 123, 21, 1, 20000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(271, 123, 9, 1, 200000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(272, 123, 21, 1, 20000.00, 'preparing', NULL, NULL, '2025-11-15 12:11:24', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:11:24'),
(273, 123, 9, 1, 200000.00, 'served', NULL, NULL, '2025-11-15 12:11:24', '2025-11-15 12:31:29', '2025-11-15 12:31:32', '2025-11-15 12:11:24', '2025-11-15 12:32:09'),
(274, 123, 17, 1, 25000.00, 'preparing', NULL, NULL, '2025-11-15 12:18:30', NULL, NULL, '2025-11-15 12:18:30', '2025-11-15 12:18:30'),
(275, 123, 11, 1, 15000.00, 'preparing', NULL, NULL, '2025-11-15 12:18:30', NULL, NULL, '2025-11-15 12:18:30', '2025-11-15 12:18:30'),
(276, 123, 3, 1, 90000.00, 'preparing', NULL, NULL, '2025-11-15 12:31:10', NULL, NULL, '2025-11-15 12:31:10', '2025-11-15 12:31:10'),
(277, 124, 3, 1, 90000.00, 'served', NULL, NULL, '2025-11-15 12:34:34', '2025-11-15 12:36:01', '2025-11-15 12:37:53', '2025-11-15 12:34:34', '2025-11-15 12:37:53'),
(279, 124, 6, 1, 85000.00, 'preparing', NULL, NULL, '2025-11-15 12:44:27', NULL, NULL, '2025-11-15 12:44:27', '2025-11-15 12:44:27'),
(280, 124, 6, 1, 85000.00, 'preparing', NULL, NULL, '2025-11-15 12:50:46', NULL, NULL, '2025-11-15 12:50:46', '2025-11-15 12:50:46'),
(294, 129, NULL, 1, 250000.00, 'served', NULL, '{\"menu_set\":{\"id\":null,\"token\":\"set-x-mi74vlqg-ij09qk\",\"name\":\"Golden Feast\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}', '2025-11-20 14:52:34', '2025-11-20 15:04:24', '2025-11-20 15:04:26', '2025-11-20 14:52:34', '2025-11-20 15:04:26'),
(295, 130, NULL, 1, 590000.00, 'preparing', NULL, '{\"menu_set\":{\"id\":2,\"token\":\"set-2-mi9zjcy9-0jg0dt\",\"name\":\"Happy Lunch\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}', '2025-11-22 14:46:18', NULL, NULL, '2025-11-22 14:46:18', '2025-11-22 14:46:18'),
(296, 132, NULL, 1, 590000.00, 'preparing', NULL, '{\"menu_set\":{\"id\":null,\"token\":\"set-x-mia2xbnx-yai0v1\",\"name\":\"Thực đơn đặt trước\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}', '2025-11-22 16:21:13', NULL, NULL, '2025-11-22 16:21:13', '2025-11-22 16:21:13'),
(297, 131, 5, 1, 100000.00, 'served', NULL, NULL, '2025-11-22 16:23:32', '2025-11-22 16:23:33', '2025-11-22 16:23:35', '2025-11-22 16:23:32', '2025-11-22 16:23:35'),
(298, 133, NULL, 1, 590000.00, 'served', NULL, '{\"menu_set\":{\"id\":2,\"token\":\"set-2-mia380qu-1a69mh\",\"name\":\"Happy Lunch\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}', '2025-11-22 16:29:32', '2025-11-22 16:30:52', '2025-11-22 16:31:00', '2025-11-22 16:29:32', '2025-11-22 16:31:00'),
(299, 133, 5, 1, 100000.00, 'served', NULL, NULL, '2025-11-22 16:29:42', '2025-11-22 16:30:49', '2025-11-22 16:30:57', '2025-11-22 16:29:42', '2025-11-22 16:30:57'),
(300, 133, 4, 1, 95000.00, 'served', NULL, NULL, '2025-11-22 16:29:42', '2025-11-22 16:30:46', '2025-11-22 16:30:54', '2025-11-22 16:29:42', '2025-11-22 16:30:54'),
(311, 134, 6, 1, 85000.00, 'served', NULL, NULL, '2025-11-22 18:35:31', '2025-11-22 18:35:58', '2025-11-22 18:35:59', '2025-11-22 18:35:31', '2025-11-22 18:35:59'),
(312, 134, 3, 1, 90000.00, 'served', NULL, NULL, '2025-11-22 18:36:03', '2025-11-22 18:36:04', '2025-11-22 18:36:06', '2025-11-22 18:36:03', '2025-11-22 18:36:06'),
(313, 134, 7, 1, 90000.00, 'served', NULL, NULL, '2025-11-24 13:09:54', '2025-11-24 13:10:01', '2025-11-24 13:10:02', '2025-11-24 13:09:54', '2025-11-24 13:10:02'),
(314, 134, 9, 1, 200000.00, 'served', NULL, NULL, '2025-11-24 13:09:54', '2025-11-24 13:09:58', '2025-11-24 13:10:00', '2025-11-24 13:09:54', '2025-11-24 13:10:00'),
(315, 134, 10, 1, 15000.00, 'served', NULL, NULL, '2025-11-24 13:09:54', '2025-11-24 13:09:55', '2025-11-24 13:09:57', '2025-11-24 13:09:54', '2025-11-24 13:09:57'),
(316, 136, NULL, 1, 590000.00, 'preparing', NULL, '{\"menu_set\":{\"id\":2,\"token\":\"set-2-micr4l05-980f1b\",\"name\":\"Happy Lunch\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}', '2025-11-24 13:14:14', NULL, NULL, '2025-11-24 13:14:14', '2025-11-24 13:14:14'),
(317, 136, 16, 1, 25000.00, 'preparing', NULL, NULL, '2025-11-24 13:14:24', NULL, NULL, '2025-11-24 13:14:24', '2025-11-24 13:14:24'),
(318, 136, 18, 1, 30000.00, 'preparing', NULL, NULL, '2025-11-24 13:14:24', NULL, NULL, '2025-11-24 13:14:24', '2025-11-24 13:14:24'),
(319, 136, 17, 1, 25000.00, 'preparing', NULL, NULL, '2025-11-24 13:14:24', NULL, NULL, '2025-11-24 13:14:24', '2025-11-24 13:14:24'),
(320, 136, 20, 1, 20000.00, 'preparing', NULL, NULL, '2025-11-24 13:14:24', NULL, NULL, '2025-11-24 13:14:24', '2025-11-24 13:14:24'),
(321, 136, 12, 1, 450000.00, 'preparing', NULL, NULL, '2025-11-24 13:14:59', NULL, NULL, '2025-11-24 13:14:59', '2025-11-24 13:14:59'),
(322, 135, 6, 1, 85000.00, 'served', NULL, NULL, '2025-11-24 13:32:15', '2025-11-24 13:33:08', '2025-11-24 13:33:12', '2025-11-24 13:32:15', '2025-11-24 13:33:12'),
(323, 135, 4, 1, 95000.00, 'served', NULL, NULL, '2025-11-24 13:32:15', '2025-11-24 13:33:09', '2025-11-24 13:33:10', '2025-11-24 13:32:15', '2025-11-24 13:33:10'),
(324, 135, 11, 3, 15000.00, 'served', NULL, NULL, '2025-11-24 13:32:39', '2025-11-24 13:33:10', '2025-11-24 13:33:11', '2025-11-24 13:32:39', '2025-11-24 13:33:11');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitiethoadon`
--

CREATE TABLE `chitiethoadon` (
  `idCTHD` int(11) NOT NULL,
  `idHD` int(11) NOT NULL,
  `idmonan` int(11) DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `soluong` double NOT NULL,
  `thanhtien` double NOT NULL,
  `metadata` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitiethoadon`
--

INSERT INTO `chitiethoadon` (`idCTHD`, `idHD`, `idmonan`, `item_name`, `unit_price`, `soluong`, `thanhtien`, `metadata`) VALUES
(121, 94, 17, 'Bánh lọt lá dứa nước cốt dừa', 25000.00, 1, 25000, NULL),
(122, 94, 11, 'Bánh flan', 15000.00, 1, 15000, NULL),
(123, 94, 11, 'Bánh flan', 15000.00, 1, 15000, NULL),
(124, 94, 9, 'Vịt quay Bắc Kinh', 200000.00, 1, 200000, NULL),
(125, 94, 21, 'Trà đào', 20000.00, 2, 40000, NULL),
(126, 94, 9, 'Vịt quay Bắc Kinh', 200000.00, 1, 200000, NULL),
(127, 94, 21, 'Trà đào', 20000.00, 1, 20000, NULL),
(128, 94, 9, 'Vịt quay Bắc Kinh', 200000.00, 1, 200000, NULL),
(129, 94, 21, 'Trà đào', 20000.00, 1, 20000, NULL),
(130, 94, 9, 'Vịt quay Bắc Kinh', 200000.00, 1, 200000, NULL),
(131, 94, 21, 'Trà đào', 20000.00, 1, 20000, NULL),
(132, 94, 9, 'Vịt quay Bắc Kinh', 200000.00, 1, 200000, NULL),
(133, 94, 21, 'Trà đào', 20000.00, 1, 20000, NULL),
(134, 94, 9, 'Vịt quay Bắc Kinh', 200000.00, 1, 200000, NULL),
(135, 94, 17, 'Bánh lọt lá dứa nước cốt dừa', 25000.00, 1, 25000, NULL),
(136, 94, 11, 'Bánh flan', 15000.00, 1, 15000, NULL),
(137, 94, 3, 'Gỏi xoài tôm khô', 90000.00, 1, 90000, NULL),
(138, 95, 3, 'Gỏi xoài tôm khô', 90000.00, 1, 90000, NULL),
(139, 95, 6, 'Canh chua cá lóc', 85000.00, 1, 85000, NULL),
(140, 95, 6, 'Canh chua cá lóc', 85000.00, 1, 85000, NULL),
(141, 96, NULL, 'Golden Feast', 250000.00, 1, 250000, '{\"menu_set\":{\"id\":null,\"token\":\"set-x-mi74vlqg-ij09qk\",\"name\":\"Golden Feast\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}'),
(142, 97, 5, 'Cơm chiên hải sản', 100000.00, 1, 100000, NULL),
(143, 98, NULL, 'Thực đơn đặt trước', 590000.00, 1, 590000, '{\"menu_set\":{\"id\":null,\"token\":\"set-x-mia2xbnx-yai0v1\",\"name\":\"Thực đơn đặt trước\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}'),
(144, 99, 6, 'Canh chua cá lóc', 85000.00, 1, 85000, NULL),
(145, 99, 3, 'Gỏi xoài tôm khô', 90000.00, 1, 90000, NULL),
(146, 99, 7, 'Tôm rang me', 90000.00, 1, 90000, NULL),
(147, 99, 9, 'Vịt quay Bắc Kinh', 200000.00, 1, 200000, NULL),
(148, 99, 10, 'Soda chanh', 15000.00, 1, 15000, NULL),
(149, 100, NULL, 'Happy Lunch', 590000.00, 1, 590000, '{\"menu_set\":{\"id\":2,\"token\":\"set-2-micr4l05-980f1b\",\"name\":\"Happy Lunch\",\"quantity\":1,\"virtual_item\":true},\"set_note\":null}'),
(150, 100, 16, 'Combo2', 25000.00, 1, 25000, NULL),
(151, 100, 18, 'Panna cotta Bơ', 30000.00, 1, 30000, NULL),
(152, 100, 17, 'Bánh lọt lá dứa nước cốt dừa', 25000.00, 1, 25000, NULL),
(153, 100, 20, 'Nước cam ép ', 20000.00, 1, 20000, NULL),
(154, 100, 12, 'Combo 1', 450000.00, 1, 450000, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietnhapkho`
--

CREATE TABLE `chitietnhapkho` (
  `maCTNK` int(11) NOT NULL,
  `manhapkho` int(11) NOT NULL,
  `matonkho` int(11) NOT NULL,
  `dongia` double NOT NULL,
  `soluongdat` float NOT NULL,
  `soluongthucte` float NOT NULL,
  `thanhtien` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietphuthu`
--

CREATE TABLE `chitietphuthu` (
  `idChiTietPhuThu` int(11) NOT NULL,
  `madatban` int(11) NOT NULL,
  `MaKV` int(11) NOT NULL,
  `SoLuongBan` int(11) NOT NULL DEFAULT 1,
  `PhuThu` decimal(10,2) NOT NULL,
  `TongPhuThu` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Bẫy `chitietphuthu`
--
DELIMITER $$
CREATE TRIGGER `update_total_amount` AFTER INSERT ON `chitietphuthu` FOR EACH ROW BEGIN
  UPDATE `datban` db
  SET db.TongTien = (
    SELECT COALESCE(SUM(ct.SoLuong * ct.DonGia), 0) + NEW.TongPhuThu
    FROM `chitietdatban` ct
    WHERE ct.madatban = NEW.madatban
  )
  WHERE db.madatban = NEW.madatban;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitietthucdon`
--

CREATE TABLE `chitietthucdon` (
  `idCTTD` int(11) NOT NULL,
  `idmonan` int(11) NOT NULL,
  `idthucdon` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Đang đổ dữ liệu cho bảng `chitietthucdon`
--

INSERT INTO `chitietthucdon` (`idCTTD`, `idmonan`, `idthucdon`) VALUES
(1, 15, 1),
(9, 4, 1),
(30, 16, 6),
(31, 6, 7),
(32, 5, 8),
(33, 17, 9),
(34, 26, 10),
(35, 13, 2),
(36, 6, 2),
(37, 3, 2),
(38, 1, 2),
(39, 23, 2),
(40, 9, 2),
(41, 5, 2),
(42, 4, 5),
(43, 6, 5),
(44, 23, 5),
(45, 17, 5),
(47, 3, 15);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `chitiet_ban_datban`
--

CREATE TABLE `chitiet_ban_datban` (
  `id` int(11) NOT NULL,
  `madatban` int(11) NOT NULL,
  `idban` int(11) NOT NULL,
  `phuthu` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `chitiet_ban_datban`
--

INSERT INTO `chitiet_ban_datban` (`id`, `madatban`, `idban`, `phuthu`) VALUES
(354, 196, 65, 100000.00),
(355, 196, 64, 100000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `danhmuc`
--

CREATE TABLE `danhmuc` (
  `iddm` int(11) NOT NULL,
  `tendanhmuc` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `danhmuc`
--

INSERT INTO `danhmuc` (`iddm`, `tendanhmuc`) VALUES
(1, 'Khai vị'),
(2, 'Món chính'),
(3, 'Tráng miệng'),
(4, 'Đồ uống'),
(5, 'Đặc biệt');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `datban`
--

CREATE TABLE `datban` (
  `madatban` int(11) NOT NULL,
  `idKH` int(11) DEFAULT NULL,
  `NgayDatBan` datetime DEFAULT NULL,
  `SoLuongKhach` int(11) DEFAULT NULL,
  `TongTien` decimal(10,2) NOT NULL,
  `TrangThai` enum('pending','confirmed','completed','canceled') NOT NULL DEFAULT 'pending',
  `tenKH` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `sodienthoai` varchar(20) DEFAULT NULL,
  `NgayTao` datetime DEFAULT current_timestamp(),
  `ThoiGianHetHan` datetime DEFAULT NULL,
  `payment_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `datban`
--

INSERT INTO `datban` (`madatban`, `idKH`, `NgayDatBan`, `SoLuongKhach`, `TongTien`, `TrangThai`, `tenKH`, `email`, `sodienthoai`, `NgayTao`, `ThoiGianHetHan`, `payment_expires`) VALUES
(196, 6, '2025-11-23 20:00:00', 6, 790000.00, 'confirmed', 'Nguyễn Minh Đức', 'tn6888295@gmail.com', '0928449664', '2025-11-22 16:25:04', '2025-11-22 16:40:04', NULL);

--
-- Bẫy `datban`
--
DELIMITER $$
CREATE TRIGGER `datban_before_update_cancel_if_expired` BEFORE UPDATE ON `datban` FOR EACH ROW BEGIN
  IF NEW.payment_expires IS NOT NULL AND NOW() > DATE_ADD(NEW.payment_expires, INTERVAL 6 HOUR) THEN
    SET NEW.TrangThai = 'canceled';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `set_expiry_time` BEFORE INSERT ON `datban` FOR EACH ROW BEGIN
  IF NEW.TrangThai = 'pending' AND NEW.ThoiGianHetHan IS NULL THEN
    SET NEW.ThoiGianHetHan = NOW() + INTERVAL 15 MINUTE;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `datban_admin_meta`
--

CREATE TABLE `datban_admin_meta` (
  `id` int(11) NOT NULL,
  `madatban` int(11) NOT NULL,
  `booking_channel` enum('user','walkin','phone') DEFAULT 'user',
  `payment_method` enum('cash','transfer','online') DEFAULT 'cash',
  `menu_mode` enum('none','items','set') DEFAULT 'none',
  `note` text DEFAULT NULL,
  `menu_snapshot` longtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `datban_admin_meta`
--

INSERT INTO `datban_admin_meta` (`id`, `madatban`, `booking_channel`, `payment_method`, `menu_mode`, `note`, `menu_snapshot`, `created_by`, `created_at`) VALUES
(21, 153, 'walkin', 'cash', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-25 16:21:10'),
(22, 154, 'phone', 'transfer', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-25 16:21:32'),
(23, 155, 'walkin', 'transfer', 'items', 'Đã cọc 50% số tiền: 295,000đ (cập nhật 25/10/2025 11:33)', '{\"mode\":\"items\",\"items\":[{\"idmonan\":4,\"tenmonan\":\"Bò lúc lắc\",\"DonGia\":95000,\"soluong\":2}],\"set\":null,\"total\":190000}', 7, '2025-10-25 16:22:01'),
(24, 156, 'walkin', 'cash', 'none', '', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-25 16:35:15'),
(25, 157, 'phone', 'transfer', 'none', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 200,000đ (cập nhật 25/10/2025 11:37)', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-25 16:35:28'),
(26, 158, 'phone', 'transfer', 'none', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 200,000đ (cập nhật 25/10/2025 11:41)', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-25 16:40:45'),
(27, 159, 'walkin', 'cash', 'none', '', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-30 14:08:25'),
(28, 162, 'walkin', 'cash', 'set', 'bàn gần cửa sổ', '{\"mode\":\"set\",\"items\":[],\"set\":{\"id_thucdon\":2,\"tenthucdon\":\"Happy Lunch\",\"tongtien\":590000,\"monan\":[{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1},{\"idmonan\":5,\"tenmonan\":\"Cơm chiên hải sản\",\"DonGia\":100000,\"soluong\":1},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1},{\"idmonan\":13,\"tenmonan\":\"Kem dâu\",\"DonGia\":20000,\"soluong\":1},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1},{\"idmonan\":1,\"tenmonan\":\"Súp hải sản\",\"DonGia\":50000,\"soluong\":1},{\"idmonan\":9,\"tenmonan\":\"Vịt quay Bắc Kinh\",\"DonGia\":200000,\"soluong\":1}]},\"total\":590000}', 7, '2025-10-30 17:40:48'),
(29, 163, 'walkin', 'cash', 'none', '', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-30 18:05:32'),
(30, 164, 'walkin', 'cash', 'none', '', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-10-30 18:06:47'),
(31, 160, 'user', 'transfer', 'none', '', NULL, 7, '2025-10-30 18:16:56'),
(32, 165, 'walkin', 'cash', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-07 13:32:47'),
(33, 166, 'walkin', 'cash', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-07 14:22:14'),
(34, 167, 'walkin', 'cash', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-07 14:55:01'),
(35, 168, 'walkin', 'cash', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-11 15:38:27'),
(36, 169, 'walkin', 'cash', 'none', '', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-11 15:42:10'),
(37, 170, 'walkin', 'transfer', 'none', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 200,000đ (cập nhật 11/11/2025 09:44)', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-11 15:43:41'),
(38, 172, 'walkin', 'cash', 'items', 'bàn gần cửa sổ', '{\"mode\":\"items\",\"items\":[{\"idmonan\":5,\"tenmonan\":\"Cơm chiên hải sản\",\"DonGia\":100000,\"soluong\":1},{\"idmonan\":17,\"tenmonan\":\"Bánh lọt lá dứa nước cốt dừa\",\"DonGia\":25000,\"soluong\":1}],\"set\":null,\"total\":125000}', 7, '2025-11-13 13:25:27'),
(39, 173, 'phone', 'transfer', 'items', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 60,000đ (cập nhật 13/11/2025 07:27)', '{\"mode\":\"items\",\"items\":[{\"idmonan\":13,\"tenmonan\":\"Kem dâu\",\"DonGia\":20000,\"soluong\":1}],\"set\":null,\"total\":20000}', 7, '2025-11-13 13:26:27'),
(40, 174, 'walkin', 'cash', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-13 13:59:08'),
(41, 175, 'walkin', 'cash', 'none', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 200,000đ (cập nhật 13/11/2025 08:04)', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-13 14:04:22'),
(42, 179, 'walkin', 'cash', 'none', 'Đã cọc 50% số tiền: 200,000đ (cập nhật 14/11/2025 07:41)', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-14 13:41:54'),
(43, 180, 'phone', 'transfer', 'none', 'bàn gần cửa sổ', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-14 16:03:12'),
(44, 182, 'walkin', 'cash', 'set', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 345,000đ (cập nhật 20/11/2025 04:58)', '{\"mode\":\"set\",\"items\":[],\"set\":{\"id_thucdon\":2,\"tenthucdon\":\"Happy Lunch\",\"tongtien\":590000,\"monan\":[{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1},{\"idmonan\":5,\"tenmonan\":\"Cơm chiên hải sản\",\"DonGia\":100000,\"soluong\":1},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1},{\"idmonan\":13,\"tenmonan\":\"Kem dâu\",\"DonGia\":20000,\"soluong\":1},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1},{\"idmonan\":1,\"tenmonan\":\"Súp hải sản\",\"DonGia\":50000,\"soluong\":1},{\"idmonan\":9,\"tenmonan\":\"Vịt quay Bắc Kinh\",\"DonGia\":200000,\"soluong\":1}]},\"total\":590000}', 7, '2025-11-20 10:58:37'),
(45, 183, 'phone', 'transfer', 'set', 'Đã cọc 50% số tiền: 495,000đ (cập nhật 20/11/2025 05:01)', '{\"mode\":\"set\",\"items\":[],\"set\":{\"id_thucdon\":2,\"tenthucdon\":\"Happy Lunch\",\"tongtien\":590000,\"monan\":[{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1},{\"idmonan\":5,\"tenmonan\":\"Cơm chiên hải sản\",\"DonGia\":100000,\"soluong\":1},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1},{\"idmonan\":13,\"tenmonan\":\"Kem dâu\",\"DonGia\":20000,\"soluong\":1},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1},{\"idmonan\":1,\"tenmonan\":\"Súp hải sản\",\"DonGia\":50000,\"soluong\":1},{\"idmonan\":9,\"tenmonan\":\"Vịt quay Bắc Kinh\",\"DonGia\":200000,\"soluong\":1}]},\"total\":590000}', 7, '2025-11-20 10:59:18'),
(46, 184, 'walkin', 'cash', 'items', 'Đã cọc 50% số tiền: 327,500đ (cập nhật 20/11/2025 05:02)', '{\"mode\":\"items\",\"items\":[{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":3}],\"set\":null,\"total\":255000}', 7, '2025-11-20 11:02:30'),
(47, 186, 'walkin', 'cash', 'set', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 325,000đ (cập nhật 20/11/2025 08:52)', '{\"mode\":\"set\",\"items\":[],\"set\":{\"id_thucdon\":5,\"tenthucdon\":\"Golden Feast\",\"tongtien\":250000,\"monan\":[{\"idmonan\":17,\"tenmonan\":\"Bánh lọt lá dứa nước cốt dừa\",\"DonGia\":25000,\"soluong\":1},{\"idmonan\":4,\"tenmonan\":\"Bò lúc lắc\",\"DonGia\":95000,\"soluong\":1},{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1}]},\"total\":250000}', 7, '2025-11-20 14:52:19'),
(48, 187, 'walkin', 'cash', 'none', 'Đã cọc 50% số tiền: 200,000đ (cập nhật 20/11/2025 08:53)', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-20 14:53:11'),
(49, 188, 'phone', 'transfer', 'items', 'bàn gần cửa sổ', '{\"mode\":\"items\",\"items\":[{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1}],\"set\":null,\"total\":135000}', 7, '2025-11-21 15:20:14'),
(50, 189, 'phone', 'transfer', 'items', 'bàn gần cửa sổ', '{\"mode\":\"items\",\"items\":[{\"idmonan\":2,\"tenmonan\":\"Cơm gói lá sen\",\"DonGia\":75000,\"soluong\":1},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1}],\"set\":null,\"total\":165000}', 7, '2025-11-21 15:23:16'),
(51, 190, 'phone', 'transfer', 'set', 'bàn gần cửa sổ', '{\"mode\":\"set\",\"items\":[],\"set\":{\"id_thucdon\":5,\"tenthucdon\":\"Golden Feast\",\"tongtien\":250000,\"monan\":[{\"idmonan\":17,\"tenmonan\":\"Bánh lọt lá dứa nước cốt dừa\",\"DonGia\":25000,\"soluong\":1},{\"idmonan\":4,\"tenmonan\":\"Bò lúc lắc\",\"DonGia\":95000,\"soluong\":1},{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1}]},\"total\":250000}', 7, '2025-11-21 15:34:35'),
(52, 191, 'phone', 'transfer', 'items', '', '{\"mode\":\"items\",\"items\":[{\"idmonan\":2,\"tenmonan\":\"Cơm gói lá sen\",\"DonGia\":75000,\"soluong\":1},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1}],\"set\":null,\"total\":165000}', 7, '2025-11-21 15:39:52'),
(53, 192, 'phone', 'transfer', 'none', 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 200,000đ (cập nhật 21/11/2025 09:47)', '{\"mode\":\"none\",\"items\":[],\"set\":null,\"total\":0}', 7, '2025-11-21 15:44:58'),
(54, 194, 'user', 'transfer', 'set', '', '{\"set\":{\"id_thucdon\":5,\"thucdon_info\":{\"idthucdon\":5,\"tenthucdon\":\"Golden Feast\",\"tongtien\":250000,\"trangthai\":\"approved\",\"hoatdong\":\"active\"},\"monan\":[{\"idmonan\":4,\"tenmonan\":\"Bò lúc lắc\",\"DonGia\":95000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":17,\"tenmonan\":\"Bánh lọt lá dứa nước cốt dừa\",\"DonGia\":25000,\"soluong\":1,\"iddm\":3,\"tendanhmuc\":\"Tráng miệng\"}]}}', 36, '2025-11-22 14:57:40'),
(55, 195, 'user', 'transfer', 'set', NULL, '{\"set\":{\"id_thucdon\":2,\"thucdon_info\":{\"idthucdon\":2,\"tenthucdon\":\"Happy Lunch\",\"tongtien\":590000,\"trangthai\":\"approved\",\"hoatdong\":\"active\"},\"monan\":[{\"idmonan\":13,\"tenmonan\":\"Kem dâu\",\"DonGia\":20000,\"soluong\":1,\"iddm\":3,\"tendanhmuc\":\"Tráng miệng\"},{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":1,\"tenmonan\":\"Súp hải sản \",\"DonGia\":50000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":9,\"tenmonan\":\"Vịt quay Bắc Kinh\",\"DonGia\":200000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":5,\"tenmonan\":\"Cơm chiên hải sản\",\"DonGia\":100000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"}]}}', NULL, '2025-11-22 16:17:49'),
(56, 196, 'user', 'transfer', 'set', NULL, '{\"set\":{\"id_thucdon\":2,\"thucdon_info\":{\"idthucdon\":2,\"tenthucdon\":\"Happy Lunch\",\"tongtien\":590000,\"trangthai\":\"approved\",\"hoatdong\":\"active\"},\"monan\":[{\"idmonan\":13,\"tenmonan\":\"Kem dâu\",\"DonGia\":20000,\"soluong\":1,\"iddm\":3,\"tendanhmuc\":\"Tráng miệng\"},{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":1,\"tenmonan\":\"Súp hải sản \",\"DonGia\":50000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":9,\"tenmonan\":\"Vịt quay Bắc Kinh\",\"DonGia\":200000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":5,\"tenmonan\":\"Cơm chiên hải sản\",\"DonGia\":100000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"}]}}', NULL, '2025-11-22 16:25:04'),
(57, 197, 'user', 'transfer', 'set', NULL, '{\"set\":{\"id_thucdon\":2,\"thucdon_info\":{\"idthucdon\":2,\"tenthucdon\":\"Happy Lunch\",\"tongtien\":590000,\"trangthai\":\"approved\",\"hoatdong\":\"active\"},\"monan\":[{\"idmonan\":13,\"tenmonan\":\"Kem dâu\",\"DonGia\":20000,\"soluong\":1,\"iddm\":3,\"tendanhmuc\":\"Tráng miệng\"},{\"idmonan\":6,\"tenmonan\":\"Canh chua cá lóc\",\"DonGia\":85000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":3,\"tenmonan\":\"Gỏi xoài tôm khô\",\"DonGia\":90000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":1,\"tenmonan\":\"Súp hải sản \",\"DonGia\":50000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":23,\"tenmonan\":\"Salad Hy Lạp\",\"DonGia\":45000,\"soluong\":1,\"iddm\":1,\"tendanhmuc\":\"Khai vị\"},{\"idmonan\":9,\"tenmonan\":\"Vịt quay Bắc Kinh\",\"DonGia\":200000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"},{\"idmonan\":5,\"tenmonan\":\"Cơm chiên hải sản\",\"DonGia\":100000,\"soluong\":1,\"iddm\":2,\"tendanhmuc\":\"Món chính\"}]}}', NULL, '2025-11-24 13:12:33');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang`
--

CREATE TABLE `donhang` (
  `idDH` int(11) NOT NULL,
  `idKH` int(11) NOT NULL,
  `idban` int(11) NOT NULL,
  `madatban` int(11) DEFAULT NULL,
  `NgayDatHang` datetime NOT NULL,
  `TongTien` decimal(10,2) DEFAULT NULL,
  `TrangThai` varchar(50) DEFAULT NULL,
  `MaDonHang` varchar(50) DEFAULT NULL,
  `SoHoaDon` varchar(20) DEFAULT NULL,
  `DanhGia` tinyint(3) UNSIGNED DEFAULT NULL COMMENT 'Rating 1-5 sao từ khách hàng'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang`
--

INSERT INTO `donhang` (`idDH`, `idKH`, `idban`, `madatban`, `NgayDatHang`, `TongTien`, `TrangThai`, `MaDonHang`, `SoHoaDon`, `DanhGia`) VALUES
(102, 28, 1, NULL, '2025-11-13 13:07:39', 570000.00, 'hoan_thanh', 'DH-251113-130739-T01-D838', '01D838', NULL),
(105, 28, 7, NULL, '2025-11-13 20:16:57', 185000.00, 'hoan_thanh', 'DH-251113-201657-T07-3BD5', '073BD5', NULL),
(106, 28, 3, NULL, '2025-11-13 20:43:35', 270000.00, 'hoan_thanh', 'DH-251113-204335-T03-AF79', '03AF79', NULL),
(107, 28, 7, NULL, '2025-11-13 20:44:17', 265000.00, 'hoan_thanh', 'DH-251113-204417-T07-DD88', '07DD88', NULL),
(116, 6, 76, NULL, '2025-11-14 14:05:20', 670000.00, 'hoan_thanh', 'DH-251114-140520-T72-A5AF', '72A5AF', NULL),
(118, 20, 74, NULL, '2025-11-14 15:50:51', 615000.00, 'hoan_thanh', 'DH-251114-155051-T74-1B89', '741B89', NULL),
(119, 20, 72, NULL, '2025-11-14 16:24:31', 565000.00, 'hoan_thanh', 'DH-251114-162431-T72-22C9', '7222C9', NULL),
(122, 20, 64, NULL, '2025-11-14 21:46:35', 1200000.00, 'hoan_thanh', 'DH-251114-214635-T64-E326', '64E326', NULL),
(123, 6, 17, NULL, '2025-11-15 11:35:27', 1905000.00, 'hoan_thanh', 'DH-251115-113527-T17-BB6B', '17BB6B', NULL),
(124, 6, 1, NULL, '2025-11-15 12:33:41', 660000.00, 'hoan_thanh', 'DH-251115-123341-T01-E437', '01E437', NULL),
(129, 6, 1, NULL, '2025-11-20 14:52:28', 650000.00, 'hoan_thanh', 'DH-251120-145228-T01-ED02', '01ED02', NULL),
(130, 28, 7, NULL, '2025-11-22 14:44:59', 690000.00, 'hoan_thanh', 'DH-251122-144459-T07-0050', '070050', NULL),
(131, 20, 1, NULL, '2025-11-22 14:45:44', 500000.00, 'hoan_thanh', 'DH-251122-144544-T01-885E', '01885E', NULL),
(132, 20, 13, NULL, '2025-11-22 16:21:07', 790000.00, 'hoan_thanh', 'DH-251122-162107-T13-01E8', '1301E8', NULL),
(133, 28, 64, 196, '2025-11-22 16:29:26', 985000.00, 'hoan_thanh', 'DH-251122-162926-T64-D9C8', '64D9C8', 5),
(134, 28, 1, NULL, '2025-11-22 17:10:55', 880000.00, 'hoan_thanh', 'DH-251122-171055-T01-1342', '011342', NULL),
(135, 6, 2, NULL, '2025-11-24 13:10:50', 425000.00, 'dang_phuc_vu', 'DH-251124-131050-T02-81FB', '0281FB', NULL),
(136, 6, 21, NULL, '2025-11-24 13:14:09', 1240000.00, 'hoan_thanh', 'DH-251124-131409-T21-67E7', '2167E7', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang_admin_meta`
--

CREATE TABLE `donhang_admin_meta` (
  `id` int(11) NOT NULL,
  `idDH` int(11) NOT NULL,
  `opened_by` int(11) DEFAULT NULL,
  `closed_by` int(11) DEFAULT NULL,
  `people_count` int(11) DEFAULT NULL,
  `surcharge` decimal(10,2) DEFAULT 0.00,
  `area_name` varchar(120) DEFAULT NULL,
  `table_label` varchar(160) DEFAULT NULL,
  `table_ids` longtext DEFAULT NULL,
  `booking_time` datetime DEFAULT NULL,
  `booking_reference` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `payment_method` enum('none','cash','transfer','mixed') DEFAULT 'none',
  `subtotal_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `opened_at` datetime DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang_admin_meta`
--

INSERT INTO `donhang_admin_meta` (`id`, `idDH`, `opened_by`, `closed_by`, `people_count`, `surcharge`, `area_name`, `table_label`, `table_ids`, `booking_time`, `booking_reference`, `note`, `payment_method`, `subtotal_amount`, `total_amount`, `opened_at`, `closed_at`, `updated_at`) VALUES
(19, 102, 7, 7, 8, 400000.00, 'Phòng VIP', 'NT-A1, NT-A2', NULL, '2025-11-13 13:06:00', NULL, NULL, 'cash', 170000.00, 570000.00, '2025-11-13 13:07:39', '2025-11-13 13:10:14', '2025-11-13 13:10:14'),
(22, 105, 7, 7, 10, 100000.00, 'Tầng 1', 'T1-A3, T1-A4', NULL, '2025-11-13 20:16:00', NULL, NULL, 'cash', 85000.00, 185000.00, '2025-11-13 20:16:57', '2025-11-13 20:17:29', '2025-11-13 20:17:29'),
(23, 106, 7, 7, 14, 100000.00, 'Tầng 1', 'T1-A1, T1-A2', '[3,6]', '2025-11-13 20:17:00', NULL, NULL, 'cash', 170000.00, 270000.00, '2025-11-13 20:43:35', '2025-11-13 20:43:59', '2025-11-13 20:43:59'),
(24, 107, 7, 7, 10, 100000.00, 'Tầng 1', 'T1-A3, T1-A4', '[7,8]', '2025-11-13 20:44:00', NULL, NULL, 'cash', 165000.00, 265000.00, '2025-11-13 20:44:17', '2025-11-13 20:58:22', '2025-11-13 20:58:22'),
(33, 116, 7, 7, 10, 400000.00, 'Phòng VIP', 'NT-C1, NT-C2', '[76,77]', '2025-11-14 12:28:00', NULL, NULL, 'transfer', 270000.00, 670000.00, '2025-11-14 14:05:20', '2025-11-14 10:16:28', '2025-11-14 10:16:28'),
(35, 118, 7, 7, 14, 400000.00, 'Phòng VIP', 'Bàn NT-B7, Bàn NT-B8', '[74,75]', '2025-11-14 20:00:00', 179, 'Đã cọc 50% số tiền: 200,000đ (cập nhật 14/11/2025 07:41)', 'transfer', 215000.00, 615000.00, '2025-11-14 15:50:51', '2025-11-14 10:24:15', '2025-11-14 16:24:15'),
(36, 119, 7, 7, 6, 400000.00, 'Phòng VIP', 'NT-A3, NT-A4', '[72,73]', '2025-11-14 16:24:00', NULL, NULL, 'transfer', 165000.00, 565000.00, '2025-11-14 16:24:31', '2025-11-14 13:32:35', '2025-11-14 19:32:35'),
(39, 122, 7, 7, 6, 200000.00, 'Sân vườn', 'TT-A3, TT-A4', '[64,65]', '2025-11-14 21:46:00', NULL, NULL, 'cash', 1000000.00, 1200000.00, '2025-11-14 21:46:35', '2025-11-15 10:25:15', '2025-11-15 10:25:15'),
(40, 123, 7, 7, 10, 400000.00, 'Phòng VIP', 'NT-B3, NT-B4', '[17,18]', '2025-11-15 11:35:00', NULL, NULL, 'cash', 1505000.00, 1905000.00, '2025-11-15 11:35:27', '2025-11-15 12:32:55', '2025-11-15 12:32:55'),
(41, 124, 7, 7, 8, 400000.00, 'Phòng VIP', 'NT-A1, NT-A2', '[1,5]', '2025-11-15 12:33:00', NULL, NULL, 'cash', 260000.00, 660000.00, '2025-11-15 12:33:41', '2025-11-20 11:04:19', '2025-11-20 11:04:19'),
(46, 129, 7, 7, 8, 400000.00, 'Phòng VIP', 'Bàn NT-A1, Bàn NT-A2', '[1,5]', '2025-11-21 18:00:00', 186, 'bàn gần cửa sổ\r\nĐã cọc 50% số tiền: 325,000đ (cập nhật 20/11/2025 08:52)', 'cash', 250000.00, 650000.00, '2025-11-20 14:52:28', '2025-11-20 15:04:30', '2025-11-20 15:04:30'),
(47, 130, 36, NULL, 10, 100000.00, 'Tầng 1', 'Bàn T1-A3, Bàn T1-A4', '[7,8]', '2025-11-25 08:30:00', 193, NULL, 'none', 590000.00, 690000.00, '2025-11-22 14:44:59', NULL, '2025-11-22 14:46:18'),
(48, 131, 36, 36, 8, 400000.00, 'Phòng VIP', 'Bàn NT-A1, Bàn NT-A2', '[1,5]', '2025-11-25 13:00:00', 190, 'bàn gần cửa sổ', 'cash', 100000.00, 500000.00, '2025-11-22 14:45:44', '2025-11-22 16:23:47', '2025-11-22 16:23:47'),
(49, 132, 36, 36, 10, 200000.00, 'Sân vườn', 'Bàn TT-B1, Bàn TT-B2', '[13,14]', '2025-11-25 13:00:00', 195, NULL, 'cash', 590000.00, 790000.00, '2025-11-22 16:21:07', '2025-11-22 16:24:23', '2025-11-22 16:24:23'),
(50, 133, 36, NULL, 6, 200000.00, 'Sân vườn', 'Bàn TT-A3, Bàn TT-A4', '[64,65]', '2025-11-23 20:00:00', 196, NULL, 'none', 785000.00, 985000.00, '2025-11-22 16:29:26', NULL, '2025-11-22 16:31:00'),
(51, 134, 36, 36, 8, 400000.00, 'Phòng VIP', 'NT-A1, NT-A2', '[1,5]', '2025-11-22 17:10:00', NULL, NULL, 'cash', 480000.00, 880000.00, '2025-11-22 17:10:55', '2025-11-24 13:10:08', '2025-11-24 13:10:08'),
(52, 135, 36, NULL, 10, 200000.00, 'Sân vườn', 'TT-A1, TT-A4', '[2,65]', '2025-11-24 13:10:00', NULL, NULL, 'none', 225000.00, 425000.00, '2025-11-24 13:10:50', NULL, '2025-11-24 13:33:30'),
(53, 136, 36, 36, 10, 100000.00, 'Tầng 1', 'Bàn T1-B1, Bàn T1-B2', '[21,22]', '2025-11-26 11:30:00', 197, NULL, 'cash', 1140000.00, 1240000.00, '2025-11-24 13:14:09', '2025-11-24 13:16:01', '2025-11-24 13:16:01');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang_ban`
--

CREATE TABLE `donhang_ban` (
  `idDH` int(11) NOT NULL,
  `idban` int(11) NOT NULL,
  `phuthu` decimal(10,2) DEFAULT NULL,
  `soghe` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang_item_status_log`
--

CREATE TABLE `donhang_item_status_log` (
  `id` int(11) NOT NULL,
  `idCTDH` int(11) NOT NULL,
  `idDH` int(11) NOT NULL,
  `old_status` varchar(32) DEFAULT NULL,
  `new_status` varchar(32) NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `changed_at` datetime DEFAULT current_timestamp(),
  `source` enum('front','kitchen','system') DEFAULT 'system'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `donhang_item_status_log`
--

INSERT INTO `donhang_item_status_log` (`id`, `idCTDH`, `idDH`, `old_status`, `new_status`, `changed_by`, `changed_at`, `source`) VALUES
(32, 234, 106, 'preparing', 'ready', 7, '2025-11-13 20:43:45', 'front'),
(33, 233, 106, 'preparing', 'ready', 7, '2025-11-13 20:43:47', 'front'),
(34, 232, 106, 'preparing', 'ready', 7, '2025-11-13 20:43:48', 'front'),
(35, 232, 106, 'ready', 'served', 7, '2025-11-13 20:43:49', 'front'),
(36, 233, 106, 'ready', 'served', 7, '2025-11-13 20:43:50', 'front'),
(37, 234, 106, 'ready', 'served', 7, '2025-11-13 20:43:51', 'front'),
(38, 236, 107, 'preparing', 'ready', 7, '2025-11-13 20:58:00', 'front'),
(39, 235, 107, 'preparing', 'ready', 7, '2025-11-13 20:58:02', 'front'),
(40, 235, 107, 'ready', 'served', 7, '2025-11-13 20:58:18', 'front'),
(41, 236, 107, 'ready', 'served', 7, '2025-11-13 20:58:19', 'front'),
(48, 240, 116, 'preparing', 'ready', 7, '2025-11-14 14:13:57', 'front'),
(49, 241, 116, 'preparing', 'ready', 7, '2025-11-14 14:13:59', 'front'),
(50, 240, 116, 'ready', 'served', 7, '2025-11-14 14:54:13', 'front'),
(51, 241, 116, 'ready', 'served', 7, '2025-11-14 14:54:15', 'front'),
(52, 241, 116, 'served', 'served', 7, '2025-11-14 14:58:32', 'front'),
(53, 242, 118, 'preparing', 'ready', 7, '2025-11-14 16:17:23', 'front'),
(54, 243, 118, 'preparing', 'ready', 7, '2025-11-14 16:17:24', 'front'),
(55, 242, 118, 'ready', 'served', 7, '2025-11-14 16:17:25', 'front'),
(56, 243, 118, 'ready', 'served', 7, '2025-11-14 16:17:27', 'front'),
(57, 245, 119, 'preparing', 'ready', 7, '2025-11-14 16:24:51', 'front'),
(58, 244, 119, 'preparing', 'ready', 7, '2025-11-14 16:24:53', 'front'),
(59, 244, 119, 'ready', 'served', 7, '2025-11-14 16:24:54', 'front'),
(60, 245, 119, 'ready', 'served', 7, '2025-11-14 16:24:56', 'front'),
(62, 259, 122, 'preparing', 'ready', 7, '2025-11-15 10:24:58', 'front'),
(63, 259, 122, 'ready', 'served', 7, '2025-11-15 10:25:01', 'front'),
(64, 273, 123, 'preparing', 'ready', 7, '2025-11-15 12:31:29', 'front'),
(65, 273, 123, 'ready', 'served', 7, '2025-11-15 12:31:32', 'front'),
(66, 273, 123, 'served', 'served', 7, '2025-11-15 12:32:09', 'front'),
(67, 277, 124, 'preparing', 'ready', 7, '2025-11-15 12:36:01', 'front'),
(68, 277, 124, 'ready', 'ready', 7, '2025-11-15 12:37:27', 'front'),
(69, 277, 124, 'ready', 'served', 7, '2025-11-15 12:37:53', 'front'),
(70, 294, 129, 'preparing', 'ready', 7, '2025-11-20 15:04:24', 'front'),
(71, 294, 129, 'ready', 'served', 7, '2025-11-20 15:04:26', 'front'),
(72, 297, 131, 'preparing', 'ready', 36, '2025-11-22 16:23:33', 'front'),
(73, 297, 131, 'ready', 'served', 36, '2025-11-22 16:23:35', 'front'),
(74, 300, 133, 'preparing', 'ready', 36, '2025-11-22 16:30:46', 'front'),
(75, 299, 133, 'preparing', 'ready', 36, '2025-11-22 16:30:49', 'front'),
(76, 298, 133, 'preparing', 'ready', 36, '2025-11-22 16:30:52', 'front'),
(77, 300, 133, 'ready', 'served', 36, '2025-11-22 16:30:54', 'front'),
(78, 299, 133, 'ready', 'served', 36, '2025-11-22 16:30:57', 'front'),
(79, 298, 133, 'ready', 'served', 36, '2025-11-22 16:31:00', 'front'),
(100, 311, 134, 'preparing', 'ready', 36, '2025-11-22 18:35:58', 'front'),
(101, 311, 134, 'ready', 'served', 36, '2025-11-22 18:35:59', 'front'),
(102, 312, 134, 'preparing', 'ready', 36, '2025-11-22 18:36:04', 'front'),
(103, 312, 134, 'ready', 'served', 36, '2025-11-22 18:36:06', 'front'),
(104, 315, 134, 'preparing', 'ready', 36, '2025-11-24 13:09:55', 'front'),
(105, 315, 134, 'ready', 'served', 36, '2025-11-24 13:09:57', 'front'),
(106, 314, 134, 'preparing', 'ready', 36, '2025-11-24 13:09:58', 'front'),
(107, 314, 134, 'ready', 'served', 36, '2025-11-24 13:10:00', 'front'),
(108, 313, 134, 'preparing', 'ready', 36, '2025-11-24 13:10:01', 'front'),
(109, 313, 134, 'ready', 'served', 36, '2025-11-24 13:10:02', 'front'),
(110, 322, 135, 'preparing', 'ready', 36, '2025-11-24 13:33:08', 'front'),
(111, 323, 135, 'preparing', 'ready', 36, '2025-11-24 13:33:09', 'front'),
(112, 324, 135, 'preparing', 'ready', 36, '2025-11-24 13:33:10', 'front'),
(113, 323, 135, 'ready', 'served', 36, '2025-11-24 13:33:10', 'front'),
(114, 324, 135, 'ready', 'served', 36, '2025-11-24 13:33:11', 'front'),
(115, 322, 135, 'ready', 'served', 36, '2025-11-24 13:33:12', 'front');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `donhang_tables`
--

CREATE TABLE `donhang_tables` (
  `id` int(11) NOT NULL,
  `idDH` int(11) NOT NULL,
  `idban` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `hoadon`
--

CREATE TABLE `hoadon` (
  `idHD` int(11) NOT NULL,
  `idKH` int(11) DEFAULT NULL,
  `idDH` int(11) NOT NULL,
  `Ngay` datetime NOT NULL,
  `hinhthucthanhtoan` varchar(100) NOT NULL,
  `TongTien` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `hoadon`
--

INSERT INTO `hoadon` (`idHD`, `idKH`, `idDH`, `Ngay`, `hinhthucthanhtoan`, `TongTien`) VALUES
(86, 28, 102, '2025-11-13 13:10:14', 'Tiền mặt', 570000),
(87, 28, 105, '2025-11-13 20:17:29', 'Tiền mặt', 185000),
(88, 28, 106, '2025-11-13 20:43:59', 'Tiền mặt', 270000),
(89, 28, 107, '2025-11-13 20:58:22', 'Tiền mặt', 265000),
(90, 6, 116, '2025-11-14 10:16:28', 'Chuyển khoản', 670000),
(91, 20, 118, '2025-11-14 10:24:15', 'Chuyển khoản', 615000),
(92, 20, 119, '2025-11-14 13:32:35', 'Chuyển khoản', 565000),
(93, 20, 122, '2025-11-15 10:25:15', 'Tiền mặt', 1200000),
(94, 6, 123, '2025-11-15 12:32:55', 'Tiền mặt', 1905000),
(95, 6, 124, '2025-11-20 11:04:19', 'Tiền mặt', 660000),
(96, 6, 129, '2025-11-20 15:04:30', 'Tiền mặt', 650000),
(97, 20, 131, '2025-11-22 16:23:47', 'Tiền mặt', 500000),
(98, 20, 132, '2025-11-22 16:24:23', 'Tiền mặt', 790000),
(99, 28, 134, '2025-11-24 13:10:08', 'Tiền mặt', 880000),
(100, 6, 136, '2025-11-24 13:16:01', 'Tiền mặt', 1240000);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `inventory_consumption_log`
--

CREATE TABLE `inventory_consumption_log` (
  `id` int(11) NOT NULL,
  `idDH` int(11) NOT NULL,
  `idCTDH` int(11) DEFAULT NULL,
  `matonkho` int(11) NOT NULL,
  `maCTNK` int(11) DEFAULT NULL,
  `so_luong` double NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khachhang`
--

CREATE TABLE `khachhang` (
  `idKH` int(11) NOT NULL,
  `hinhanh` varchar(500) NOT NULL,
  `tenKH` varchar(100) NOT NULL,
  `sodienthoai` varchar(10) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `ngaysinh` date DEFAULT NULL,
  `gioitinh` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khachhang`
--

INSERT INTO `khachhang` (`idKH`, `hinhanh`, `tenKH`, `sodienthoai`, `email`, `password`, `ngaysinh`, `gioitinh`) VALUES
(3, '1b086243c8d41bdd80f7f3d7416be4b7.jpg', 'Lê Văn C', '0684975432', 'khc@gmail.com', '$2y$10$zrzuzrnt/Ff/cP4BIoa5Yevbze75Rn2Y/7x.zwu5WeMcZgyWdUQEa', '1990-12-01', 'Nam'),
(6, 'team-1.jpg', 'Nguyễn Minh Đức', '0928449664', 'tn6888295@gmail.com', '$2y$10$cSlpOPfi7PceDY776gQDde9wfwI5iq8Po35554Cyfhsgnpxyi2kC6', '2003-06-28', 'Nam'),
(8, '', 'Huỳnh Hồ Hoài Nam', '0945786380', 'namhuynh@gmail.com', '', '2003-12-02', 'Nam'),
(20, '', 'Lê Hoàng Gia Hi', '0796123823', 'giahi0000@gmail.com', '', '2003-01-05', 'Nữ'),
(21, '1b086243c8d41bdd80f7f3d7416be4b7.jpg', 'Lê Nguyễn Gia Hân', '0796133633', 'lenguyengiahan0155@gmail.com', '', '2001-02-06', 'Nam'),
(29, 'jm_denis.jpg', 'Gia Hân', '0796133633', 'hanhan@gmail.com', '$2y$10$.SAgDBT7nObG4ZQfqs.Azu7flQM0fqYvdG4G3nCsBOZxYLMohflO2', '2005-06-15', 'Nữ'),
(31, '', 'Khách tại bàn', '', 'walkin@restaurant.local', '', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuvucban`
--

CREATE TABLE `khuvucban` (
  `MaKV` int(11) NOT NULL,
  `TenKV` varchar(100) NOT NULL,
  `MoTa` text DEFAULT NULL,
  `TrangThai` enum('active','inactive') DEFAULT 'active',
  `PhuThu` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khuvucban`
--

INSERT INTO `khuvucban` (`MaKV`, `TenKV`, `MoTa`, `TrangThai`, `PhuThu`) VALUES
(1, 'Tầng 1', 'Khu vực thông thường gần cửa chính', 'active', 50000.00),
(2, 'Tầng 2', 'Khu yên tĩnh, phù hợp nhóm gia đình', 'active', 50000.00),
(3, 'Sân vườn', 'Ngoài trời, thoáng mát, hút thuốc được', 'active', 100000.00),
(4, 'Phòng VIP', 'Riêng tư, có điều hoà, TV', 'active', 200000.00);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `khuyenmai`
--

CREATE TABLE `khuyenmai` (
  `MaKhuyenMai` varchar(20) NOT NULL,
  `MoTa` varchar(255) DEFAULT NULL,
  `GiaTri` decimal(5,2) NOT NULL,
  `LoaiGiam` enum('percent','fixed') NOT NULL,
  `NgayBatDau` date NOT NULL,
  `NgayKetThuc` date NOT NULL,
  `TrangThai` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `khuyenmai`
--

INSERT INTO `khuyenmai` (`MaKhuyenMai`, `MoTa`, `GiaTri`, `LoaiGiam`, `NgayBatDau`, `NgayKetThuc`, `TrangThai`) VALUES
('FIXED20000', 'Giảm 20,000 VND cho mọi hóa đơn', 999.99, 'fixed', '2025-05-01', '2025-05-31', 'active'),
('JUNE05', 'Giảm 5% cho hóa đơn từ tháng 6', 5.00, 'percent', '2025-06-01', '2025-06-30', 'active'),
('MAY05', 'Giảm 5% cho hóa đơn tháng 5', 5.00, 'percent', '2025-05-01', '2025-05-31', 'active'),
('SPRING15', 'Giảm 15% cho hóa đơn tháng 4', 15.00, 'percent', '2025-04-01', '2025-04-30', 'active'),
('SUMMER10', 'Giảm 10% cho hóa đơn trong tháng 5', 10.00, 'percent', '2025-05-01', '2025-05-31', 'active'),
('WINTER25', 'Giảm 25% nhưng không hoạt động', 25.00, 'percent', '2025-05-01', '2025-05-31', 'inactive');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lichlamviec`
--

CREATE TABLE `lichlamviec` (
  `maLLV` int(11) NOT NULL,
  `ngay` date NOT NULL,
  `idca` int(11) NOT NULL,
  `idnv` int(11) NOT NULL,
  `ghichu` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `lichlamviec`
--

INSERT INTO `lichlamviec` (`maLLV`, `ngay`, `idca`, `idnv`, `ghichu`) VALUES
(1, '2025-09-29', 1, 1, ''),
(2, '2025-10-13', 1, 7, ''),
(3, '2025-11-24', 1, 7, ''),
(4, '2025-11-24', 2, 7, '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `loaitonkho`
--

CREATE TABLE `loaitonkho` (
  `idloaiTK` int(11) NOT NULL,
  `tenloaiTK` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `loaitonkho`
--

INSERT INTO `loaitonkho` (`idloaiTK`, `tenloaiTK`) VALUES
(1, 'Nguyên liệu'),
(2, 'Vật dụng');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `monan`
--

CREATE TABLE `monan` (
  `idmonan` int(11) NOT NULL,
  `tenmonan` varchar(100) NOT NULL,
  `size` varchar(10) NOT NULL,
  `mota` varchar(500) NOT NULL,
  `DonGia` double NOT NULL,
  `hinhanh` varchar(200) NOT NULL,
  `iddm` int(11) NOT NULL,
  `DonViTinh` varchar(100) NOT NULL,
  `TrangThai` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `hoatdong` enum('active','inactive') NOT NULL DEFAULT 'inactive',
  `ghichu` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `monan`
--

INSERT INTO `monan` (`idmonan`, `tenmonan`, `size`, `mota`, `DonGia`, `hinhanh`, `iddm`, `DonViTinh`, `TrangThai`, `hoatdong`, `ghichu`) VALUES
(1, 'Súp hải sản ', '', 'Với sự kết hợp hoàn hảo giữa tôm, mực, nghêu và các loại rau củ tươi. Nước dùng ngọt thanh, cay nhẹ, tạo nên món khai vị hấp dẫn và bổ dưỡng.', 50000, 'menu_1.jpg', 1, 'phần', 'approved', 'active', NULL),
(2, 'Cơm gói lá sen', '', 'món ăn thanh nhã, thơm ngon với cơm được trộn cùng hạt sen, nấm, rau củ và thịt xào, sau đó được gói trong lá sen và hấp chín. Hương thơm đặc trưng của lá sen lan tỏa, hòa quyện cùng vị ngọt bùi của nguyên liệu, tạo nên món ăn vừa tinh tế vừa bổ dưỡng.', 75000, 'menu_2.jpg', 2, 'phần', 'approved', 'active', NULL),
(3, 'Gỏi xoài tôm khô', '', 'Vị chua cay ngọt hài hòa của xoài xanh kết hợp với tôm khô và đậu phộng rang.', 90000, 'menu_3.webp', 1, 'phần', 'approved', 'active', NULL),
(4, 'Bò lúc lắc', '', 'Thịt bò mềm, xào cùng rau củ, đậm đà hương vị và bắt mắt.', 95000, 'menu_4.jpg', 2, 'phần', 'approved', 'active', NULL),
(5, 'Cơm chiên hải sản', '', 'Cơm chiên vàng ruộm cùng hải sản tươi ngon và trứng gà.', 100000, 'menu_5.jpg', 2, 'phần', 'approved', 'active', NULL),
(6, 'Canh chua cá lóc', '', 'Vị chua thanh của me và thơm hòa quyện với cá lóc tươi ngọt.', 85000, 'menu_61.jpg', 2, 'phần', 'approved', 'active', NULL),
(7, 'Tôm rang me', '', 'Tôm tươi rang cùng sốt me chua ngọt đậm đà, hấp dẫn.', 90000, 'menu_71.jpg', 2, 'phần', 'approved', 'active', NULL),
(9, 'Vịt quay Bắc Kinh', '', 'Món ăn chính trong bữa ăn', 200000, 'vitquay.jpg', 2, 'phần', 'approved', 'active', NULL),
(10, 'Soda chanh', '', 'Kết hợp giữa vị chua nhẹ của chanh và độ sủi bọt mát lạnh của soda, thường được thêm đường và đá viên để tăng độ hấp dẫn', 15000, 'soda_chanh.jpg', 4, 'phần', 'approved', 'active', NULL),
(11, 'Bánh flan', '', 'Món tráng miệng mềm mịn, thơm béo từ trứng và sữa, với lớp caramel ngọt đậm phủ bên trên. Khi ăn, bánh tan nhẹ trong miệng, mang đến cảm giác mát lạnh và ngọt ngào dễ chịu', 15000, 'flan.jpg', 3, 'cái', 'approved', 'active', NULL),
(12, 'Combo 1', '', 'Combo Cá Lăng Đặc Sắc với ba món chuẩn vị truyền thống, mang đậm hương vị quê nhà. Từ món nóng đến món nguội, tất cả hòa quyện tạo nên bữa ăn tròn vị, thích hợp cho mọi dịp sum vầy.\r\n\r\n', 450000, 'special.jpg', 5, 'combo', 'approved', 'active', NULL),
(13, 'Kem dâu', '', 'Kem vani quyện sốt dâu rừng cũng với dâu tươi mọng nước và socola ngọt ngào – mát lạnh, phù hợp tráng miệng sau bữa ăn.', 20000, 'kemdau.jpg', 3, 'ly', 'approved', 'active', NULL),
(14, 'CocaCola', '', 'Nước ngọt có ga ', 15000, 'coca_cola.jpg', 4, 'lon', 'approved', 'active', NULL),
(15, 'Pepsi', '', 'Nước uống có ga', 15000, 'pepsi.jpg', 4, 'lon', 'approved', 'active', NULL),
(16, 'Combo2', '', 'Thưởng thức trọn vị miền Tây với lẩu mắm đậm đà kèm rau, hải sản tươi sống và mẹt gà 5 món thơm ngon hấp dẫn.', 25000, 'combo2.jpg', 5, 'combo', 'approved', 'active', NULL),
(17, 'Bánh lọt lá dứa nước cốt dừa', '', 'Sự kết hợp tinh tế giữa bánh lọt dai mềm làm từ lá dứa tươi, nước cốt dừa nguyên chất béo mịn và lớp đường thốt nốt thơm lừng. Món tráng miệng mát lạnh, ngọt thanh, mang đậm hương vị truyền thống.', 25000, 'banhlot.jpg', 3, 'ly', 'approved', 'active', NULL),
(18, 'Panna cotta Bơ', '', 'Lớp kem phô mai mềm mịn quyện cùng sốt bơ tươi thanh béo, trang trí cùng topping bơ tươi mát lạnh  – một món tráng miệng nhẹ nhàng nhưng đầy cuốn hút.', 30000, 'pannacotta_bơ.jpg', 3, 'ly', 'approved', 'active', NULL),
(19, 'Combo3', '', 'Cơm trưa chuẩn Việt -  cơm trắng dẻo thơm, canh hầm rau củ ngọt thanh, rau luộc xanh mướt, trứng kho đậm đà, thịt kho tộ thơm lừng, tôm rim mặn mà và đậu que xào thịt hấp dẫn – tất cả được bày biện tinh tế, đậm chất ẩm thực truyền thống', 375000, 'comtruachuanViet.jpg', 5, 'combo', 'approved', 'active', NULL),
(20, 'Nước cam ép ', '', 'Cam ép nguyên chất, mát lạnh, ngọt thanh và giàu vitamin – giải khát sảng khoái, trọn vị tươi mới.', 20000, 'camep.jpg', 4, 'ly', 'approved', 'active', NULL),
(21, 'Trà đào', '', 'Vị trà thơm nhẹ quyện cùng miếng đào giòn ngọt, thêm lát chanh chua dịu và đá viên mát rượi, đánh thức vị giác, giải nhiệt tức thì.', 20000, 'tradao.jpg', 4, 'ly', 'approved', 'active', NULL),
(22, 'Combo 3', '', 'Beefsteak thượng hạng -  miếng steak dày mọng, chín hoàn hảo, thơm ngậy vị bơ tỏi và thảo mộc, ăn kèm khoai nướng giòn rụm, măng tây, cà chua bi nướng, cùng rượu vang đỏ hảo hạng – chuẩn vị sang trọng cho bữa tối đẳng cấp', 129000, 'combo3.jpg', 5, 'combo', 'approved', 'active', NULL),
(23, 'Salad Hy Lạp', '', 'Sự hòa quyện thanh tao của dưa chuột , cà chua , ô liu đen , phô mai feta cao cấp, và húng quế tươi, rưới thêm dầu ô liu nguyên chất, mang đến trải nghiệm ẩm thực sang trọng.', 45000, 'salad4mua.jpg', 1, 'phần', 'approved', 'active', NULL),
(24, 'Combo 4', '', 'Ẩm Thực Huế -   với sự kết hợp tinh hoa của bánh bèo chén, há cảo tôm, bánh nậm mềm mịn, bánh lọc trong veo nhân tôm thịt đậm đà và Kèm theo nước chấm chua ngọt tinh tế', 120000, 'combo4.jpg', 5, 'combo', 'approved', 'active', ''),
(26, 'khủng long a', '', '123sdf', 100000, '1b086243c8d41bdd80f7f3d7416be4b7.jpg', 2, 'dĩa', 'approved', 'inactive', 'qq'),
(35, 'Cơm Chiên', '', 'Cơm chiên với thịt bò cà rốt', 50000, 'menu_2.jpg', 2, 'dĩa', 'pending', 'inactive', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nguoidung`
--

CREATE TABLE `nguoidung` (
  `idnguoidung` int(11) NOT NULL,
  `tennguoidung` varchar(100) NOT NULL,
  `idtaikhoan` int(11) NOT NULL,
  `sodienthoai` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhacungcap`
--

CREATE TABLE `nhacungcap` (
  `idncc` int(11) NOT NULL,
  `tennhacungcap` varchar(100) NOT NULL,
  `sodienthoai` varchar(10) NOT NULL,
  `email` varchar(200) NOT NULL,
  `diachi` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `nhacungcap`
--

INSERT INTO `nhacungcap` (`idncc`, `tennhacungcap`, `sodienthoai`, `email`, `diachi`) VALUES
(1, 'Công ty nguyên liệu A', '0978654675', 'company_a@gmail.com', '12345'),
(2, 'Công ty gia dụng B', '0234562879', 'company_b@gmail.com', '12345a');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhanvien`
--

CREATE TABLE `nhanvien` (
  `idnv` int(11) NOT NULL,
  `HinhAnh` varchar(100) NOT NULL,
  `HoTen` varchar(100) DEFAULT NULL,
  `GioiTinh` varchar(100) NOT NULL,
  `ChucVu` varchar(50) DEFAULT NULL,
  `SoDienThoai` varchar(20) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `password` varchar(100) NOT NULL,
  `DiaChi` varchar(100) DEFAULT NULL,
  `Luong` decimal(10,2) DEFAULT NULL,
  `idvaitro` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `nhanvien`
--

INSERT INTO `nhanvien` (`idnv`, `HinhAnh`, `HoTen`, `GioiTinh`, `ChucVu`, `SoDienThoai`, `Email`, `password`, `DiaChi`, `Luong`, `idvaitro`) VALUES
(1, 'arashmil.jpg', 'Nguyễn Văn Phúc', 'Nam', 'Phục vụ', '0912345678', 'phucnv@example.com', '1234', '123 Lê Lợi, Q.1, TP.HCM', 7000000.00, 1),
(2, 'team-1.jpg', 'Nguyễn Trung Kiên', 'Nam', 'Đầu bếp', '0987654321', 'hoatt@example.com', '123', '45 Hai Bà Trưng, Q.3, TP.HCM', 10000000.00, 3),
(3, 'testimonial-3.jpg', 'Lê Văn Khánh', 'Nam', 'Thu ngân', '0909123456', 'khanhlv@example.com', '123', '87 Nguyễn Trãi, Q.5, TP.HCM', 8000000.00, 2),
(4, 'profile2.jpg', 'Phạm Thị Mai', '', 'Phục vụ', '0977123456', 'maipt@example.com', '', '16 Trần Hưng Đạo, Q.1, TP.HCM', 7000000.00, 1),
(7, 'about-3.jpg', 'Lê Hoàng Gia Hi', 'Nữ', 'Quản lý', '0796123824', 'giahi@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', 'Dreamhome2', 10000000.00, 4),
(10, 'jm_denis.jpg', 'Huỳnh Hồ Hoài Nam', 'Nam', 'Phục vụ', '0235478965', 'namhuynh@gmail.com', '$2y$10$2SW8xCsZCt5XbK7GRnr9leDABSyfHbkduFnPP970V2mOJePn3kH/2', '123afsdfdsgff', 5000000.00, 2),
(11, 'testimonial-4.jpg', 'Nguyễn Thị Hoàng Nga', 'Nữ', NULL, '0935713677', 'hoangnga@gmail.com', '$2y$10$ViAkURtJju4dB.ArSKzIr.zcNJnPYVNDOQJDKM2Lz9V/8WZ.WCdBC', 'hjgfjdjkfa', 5000000.00, 2),
(12, 'about-1.jpg', 'Nguyễn Uyển Quyên', 'Nữ', NULL, 'sdgsdg', 'quyen@gmail.com', '123', 'sddgsdg', 12445.00, 1),
(14, 'team-3.jpg', 'Nguyễn Tiến Chung', 'Nam', 'Đầu bếp', '0944123456', 'lannt@example.com', '', '56 Lê Duẩn, Q.1, TP.HCM', 12000000.00, 3),
(15, 'team-4.jpg', 'Trần Văn Sơn', 'Nam', 'Đầu bếp', '0955123456', 'sontv@example.com', '123', '34 Đồng Khởi, Q.1, TP.HCM', 12000000.00, 3),
(36, '691ed6af42e78.jpg', 'Minh Đức', 'Nam', 'Quản lý', '0928449664', 'tn6888295@gmail.com', 'e10adc3949ba59abbe56e057f20f883e', '153 le van tho', 5000000.00, 4),
(101, '', 'Nguyễn Văn A', 'Nam', 'Phục vụ', '0901234567', 'a@example.com', '$2y$10$demo', 'HCM', 6000000.00, 1),
(102, '', 'Trần Thị B', 'Nữ', 'Thu ngân', '0902345678', 'b@example.com', '$2y$10$demo', 'HCM', 7500000.00, 3),
(103, 'team-2.jpg', 'Nguyễn Minh Đức', 'Nam', 'Đầu bếp', '0903456789', 'c@example.com', '$2y$10$demo', 'HCM', 9000000.00, 2),
(104, '', 'Phạm Thị D', 'Nữ', 'Quản lý', '0904567890', 'd@example.com', '$2y$10$demo', 'HCM', 5500000.00, 4);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhanvien_face`
--

CREATE TABLE `nhanvien_face` (
  `id` int(11) NOT NULL,
  `idnv` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `version` int(11) DEFAULT 1,
  `active` tinyint(1) DEFAULT 1,
  `enrolled_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `nhanvien_face`
--

INSERT INTO `nhanvien_face` (`id`, `idnv`, `image_path`, `version`, `active`, `enrolled_at`) VALUES
(2, 7, 'assets/img/691ed2b884fd9.jpg', 1, 1, '2025-11-20 15:35:04'),
(3, 36, 'assets/img/691ed6af42e78.jpg', 1, 1, '2025-11-20 15:51:59');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `nhapkho`
--

CREATE TABLE `nhapkho` (
  `manhapkho` int(11) NOT NULL,
  `tennhapkho` varchar(100) NOT NULL,
  `ngaynhap` date NOT NULL,
  `idncc` int(11) NOT NULL,
  `tongtien` double NOT NULL,
  `trangthai` varchar(100) NOT NULL,
  `ghichu` varchar(500) NOT NULL,
  `hinhanh` varchar(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `idKH` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `pay_periods`
--

CREATE TABLE `pay_periods` (
  `id` int(11) NOT NULL,
  `period_code` varchar(20) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `status` enum('draft','finalized') DEFAULT 'draft',
  `note` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `finalized_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `pay_periods`
--

INSERT INTO `pay_periods` (`id`, `period_code`, `from_date`, `to_date`, `status`, `note`, `created_by`, `created_at`, `finalized_at`) VALUES
(1, '2025-11', '2025-11-01', '2025-11-30', 'draft', NULL, 7, '2025-11-21 15:59:52', NULL),
(2, '2025-09', '2025-09-01', '2025-09-30', 'draft', NULL, 36, '2025-11-22 14:15:13', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `pay_salary_lines`
--

CREATE TABLE `pay_salary_lines` (
  `id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `base_salary` double NOT NULL DEFAULT 0,
  `paid_days` int(11) NOT NULL DEFAULT 0,
  `working_hours` double NOT NULL DEFAULT 0,
  `overtime_hours` double NOT NULL DEFAULT 0,
  `overtime_amount` double NOT NULL DEFAULT 0,
  `allowance_total` double NOT NULL DEFAULT 0,
  `deduction_total` double NOT NULL DEFAULT 0,
  `net_pay` double NOT NULL DEFAULT 0,
  `detail_json` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `pay_salary_lines`
--

INSERT INTO `pay_salary_lines` (`id`, `period_id`, `staff_id`, `base_salary`, `paid_days`, `working_hours`, `overtime_hours`, `overtime_amount`, `allowance_total`, `deduction_total`, `net_pay`, `detail_json`, `created_at`, `updated_at`) VALUES
(43, 1, 101, 6000000, 1, 47.833333333333336, 0, 0, 0, 0, 230769.23076923078, '{\"staff_name\":\"Nguyễn Văn A\",\"position\":\"Phục vụ\",\"base_salary\":6000000,\"night_bonus\":0,\"note\":\"Không tính OT; lương = lương cơ bản\\/26 * số ngày công.\"}', '2025-11-22 14:15:07', NULL),
(44, 1, 102, 7500000, 1, 38.08333333333333, 0, 0, 0, 0, 288461.53846153844, '{\"staff_name\":\"Trần Thị B\",\"position\":\"Thu ngân\",\"base_salary\":7500000,\"night_bonus\":0,\"note\":\"Không tính OT; lương = lương cơ bản\\/26 * số ngày công.\"}', '2025-11-22 14:15:07', NULL),
(45, 1, 103, 9000000, 3, 31, 0, 0, 0, 0, 1038461.5384615384, '{\"staff_name\":\"Lê Văn C\",\"position\":\"Thu ngân\",\"base_salary\":9000000,\"night_bonus\":0,\"note\":\"Không tính OT; lương = lương cơ bản\\/26 * số ngày công.\"}', '2025-11-22 14:15:07', NULL),
(46, 1, 104, 5500000, 2, 16.5, 0, 0, 0, 0, 423076.92307692306, '{\"staff_name\":\"Phạm Thị D\",\"position\":\"Quản lý\",\"base_salary\":5500000,\"night_bonus\":0,\"note\":\"Không tính OT; lương = lương cơ bản\\/26 * số ngày công.\"}', '2025-11-22 14:15:07', NULL),
(47, 1, 36, 5000000, 1, 8, 0, 0, 0, 0, 192307.6923076923, '{\"staff_name\":\"Minh Đức\",\"position\":\"Quản lý\",\"base_salary\":5000000,\"night_bonus\":0,\"note\":\"Không tính OT; lương = lương cơ bản\\/26 * số ngày công.\"}', '2025-11-22 14:15:07', NULL),
(50, 2, 36, 5000000, 1, 9.633333333333333, 0, 0, 0, 0, 576923.076923077, '{\"staff_name\":\"Minh Đức\",\"position\":\"Quản lý\",\"base_salary\":5000000,\"night_bonus\":0,\"note\":\"Không tính OT; ngày lễ nhân 300%. Lương = (lương cơ bản\\/26) * (tổng hệ số ngày).\"}', '2025-11-22 14:17:18', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `phanhoi`
--

CREATE TABLE `phanhoi` (
  `idPhanHoi` int(11) NOT NULL,
  `idKH` int(11) DEFAULT NULL,
  `HoTen` varchar(100) NOT NULL,
  `Email` varchar(100) NOT NULL,
  `SoDienThoai` varchar(20) NOT NULL,
  `ChuDe` varchar(200) NOT NULL,
  `NoiDung` text NOT NULL,
  `NgayGui` datetime DEFAULT current_timestamp(),
  `TrangThai` enum('Chưa đọc','Đã đọc') DEFAULT 'Chưa đọc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `phanhoi`
--

INSERT INTO `phanhoi` (`idPhanHoi`, `idKH`, `HoTen`, `Email`, `SoDienThoai`, `ChuDe`, `NoiDung`, `NgayGui`, `TrangThai`) VALUES
(1, NULL, 'Nguyễn Văn A', 'vana@gmail.com', '0235467584', 'Dịch vụ', 'Nhà hàng có không gian đẹp, nhân viên phục vụ nhiệt tình. Món ăn ngon và giá cả hợp lý.', '2025-05-19 16:47:03', 'Chưa đọc'),
(2, NULL, 'Trần Thị B', 'tere@gmail.com', '0765849302', 'Món ăn', 'Các món ăn được chế biến cẩn thận, hương vị thơm ngon. Đặc biệt là món bò lúc lắc rất tuyệt vời.', '2025-05-19 16:47:03', 'Chưa đọc'),
(3, NULL, 'Lê Văn C', 'kh-c@gmail.com', '0684975432', 'Không gian', 'Không gian nhà hàng rộng rãi, thoáng mát. Phù hợp cho các buổi họp mặt gia đình và bạn bè.', '2025-05-19 16:47:03', 'Chưa đọc'),
(4, NULL, 'Phạm Thị D', 'phamd@gmail.com', '0285467839', 'Đánh giá chung', 'Nhà hàng có view đẹp, món ăn ngon, giá cả phải chăng. Sẽ quay lại vào lần sau.', '2025-05-19 16:47:03', 'Chưa đọc');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thanhphan`
--

CREATE TABLE `thanhphan` (
  `idthanhphan` int(11) NOT NULL,
  `matonkho` int(11) NOT NULL,
  `idmonan` int(11) NOT NULL,
  `dinhluong` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Đang đổ dữ liệu cho bảng `thanhphan`
--

INSERT INTO `thanhphan` (`idthanhphan`, `matonkho`, `idmonan`, `dinhluong`) VALUES
(1, 1, 1, 10),
(14, 21, 10, 10),
(15, 7, 10, 8),
(16, 22, 13, 10),
(17, 1, 2, 50),
(24, 5, 26, 10),
(26, 1, 35, 50),
(27, 3, 35, 30),
(28, 12, 35, 30);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thanhtoan`
--

CREATE TABLE `thanhtoan` (
  `idThanhToan` int(11) NOT NULL,
  `madatban` int(11) DEFAULT NULL,
  `idDH` int(11) DEFAULT NULL,
  `SoTien` decimal(10,2) NOT NULL,
  `PhuongThuc` varchar(50) NOT NULL,
  `TrangThai` enum('pending','completed','failed') DEFAULT 'pending',
  `NgayThanhToan` datetime DEFAULT current_timestamp(),
  `MaGiaoDich` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `thanhtoan`
--

INSERT INTO `thanhtoan` (`idThanhToan`, `madatban`, `idDH`, `SoTien`, `PhuongThuc`, `TrangThai`, `NgayThanhToan`, `MaGiaoDich`) VALUES
(112, NULL, 102, 570000.00, 'cash', 'completed', '2025-11-13 13:10:14', 'PAY2511131310140102'),
(116, NULL, 105, 185000.00, 'cash', 'completed', '2025-11-13 20:17:29', 'PAY2511132017290105'),
(117, NULL, 106, 270000.00, 'cash', 'completed', '2025-11-13 20:43:59', 'PAY2511132043590106'),
(118, NULL, 107, 265000.00, 'cash', 'completed', '2025-11-13 20:58:22', 'PAY2511132058220107'),
(122, NULL, 116, 670000.00, 'transfer', 'completed', '2025-11-14 10:16:28', 'PAY2511141016280116'),
(123, NULL, 118, 615000.00, 'transfer', 'completed', '2025-11-14 10:24:15', 'PAY2511141024150118'),
(124, NULL, 119, 565000.00, 'transfer', 'completed', '2025-11-14 19:32:34', '4611637569'),
(125, NULL, 122, 1200000.00, 'cash', 'completed', '2025-11-15 10:25:15', 'PAY2511151025150122'),
(126, NULL, 123, 1905000.00, 'cash', 'completed', '2025-11-15 12:32:55', 'PAY2511151232550123'),
(130, NULL, 124, 660000.00, 'cash', 'completed', '2025-11-20 11:04:19', 'PAY2511201104190124'),
(134, NULL, 129, 325000.00, 'cash', 'completed', '2025-11-20 15:04:30', 'PAY2511201504300129'),
(142, NULL, 131, 500000.00, 'cash', 'completed', '2025-11-22 16:23:47', 'PAY2511221623470131'),
(143, NULL, 132, 790000.00, 'cash', 'completed', '2025-11-22 16:24:23', 'PAY2511221624230132'),
(144, 196, NULL, 395000.00, 'momo', 'completed', '2025-11-22 16:25:53', '4614145016'),
(145, 196, 133, 590000.00, 'momo', 'completed', '2025-11-22 17:04:45', '4613740628'),
(146, NULL, 134, 880000.00, 'cash', 'completed', '2025-11-24 13:10:08', 'PAY2511241310080134'),
(148, NULL, 136, 895000.00, 'cash', 'completed', '2025-11-24 13:16:01', 'PAY2511241316010136');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `thucdon`
--

CREATE TABLE `thucdon` (
  `idthucdon` int(11) NOT NULL,
  `tenthucdon` varchar(100) NOT NULL,
  `mota` varchar(500) NOT NULL,
  `min_khach` int(11) NOT NULL DEFAULT 2,
  `max_khach` int(11) NOT NULL DEFAULT 4,
  `tongtien` double NOT NULL,
  `idCTTD` int(11) NOT NULL,
  `hinhanh` varchar(500) NOT NULL,
  `trangthai` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `hoatdong` enum('active','inactive') NOT NULL DEFAULT 'inactive',
  `ghichu` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;

--
-- Đang đổ dữ liệu cho bảng `thucdon`
--

INSERT INTO `thucdon` (`idthucdon`, `tenthucdon`, `mota`, `min_khach`, `max_khach`, `tongtien`, `idCTTD`, `hinhanh`, `trangthai`, `hoatdong`, `ghichu`) VALUES
(1, 'Spring Night', 'Lấy cảm hứng từ những cơn gió xuân mát lành và ánh trăng dịu dàng, mỗi món ăn được chế biến tỉ mỉ để đánh thức mọi giác quan, từ thị giác, khứu giác đến vị giác', 6, 8, 1000000, 1, 'camep.jpg', 'approved', 'active', NULL),
(2, 'Happy Lunch', 'Mỗi món ăn trong thực đơn được chế biến để đáp ứng nhu cầu của thực khách tìm kiếm một bữa trưa nhanh gọn, ngon miệng, và bổ dưỡng', 4, 6, 590000, 2, 'about-4.jpg', 'approved', 'active', NULL),
(5, 'Golden Feast', 'Thực đơn cao cấp với các món ăn tinh tế từ bò Wagyu, tôm hùm, và rượu vang, lý tưởng cho tiệc cưới hoặc sự kiện doanh nghiệp sang trọng.', 6, 8, 250000, 3, 'combo4.jpg', 'approved', 'active', NULL),
(6, 'Family Joy', 'Thực đơn ấm cúng với các món Việt Nam như cá kho tộ, gà nướng, và chè đậu đỏ, phù hợp cho tiệc gia đình hoặc sinh nhật thân mật.', 2, 4, 25000, 4, 'menu_8.jpg', 'approved', 'active', NULL),
(7, 'Quick Delight', 'Thực đơn nhanh gọn với cơm chiên, mì xào, và bánh flan, dành cho tiệc buffet hoặc hội thảo cần phục vụ nhanh và ngon miệng.', 2, 4, 85000, 5, 'menu_4.jpg', 'approved', 'active', NULL),
(8, 'Kids Party', 'Thực đơn vui nhộn dành cho tiệc sinh nhật hoặc sự kiện trẻ em, với các món dễ ăn như gà rán, khoai tây chiên, và kem trái cây, mang lại niềm vui cho các bé.', 4, 6, 100000, 6, 'bg-hero.jpg', 'approved', 'active', NULL),
(9, 'Veggie Bliss', 'Thực đơn chay thanh nhẹ với rau củ hữu cơ, đậu hũ sốt nấm, và nước ép trái cây, phù hợp cho khách ăn chay hoặc tiệc nhẹ nhàng, lành mạnh.', 2, 4, 25000, 7, 'combo4.jpg', 'approved', 'active', NULL),
(10, 'Moonlit Romance', 'Thực đơn lãng mạn cho tiệc tối đôi lứa, với các món như bít tết bò sốt tiêu, salad hoa quả, và cocktail ánh trăng, tạo không khí ấm áp và tinh tế.', 2, 4, 100000, 8, 'vitquay.jpg', 'approved', 'active', NULL),
(15, 'sdfsd', 'fsdfsd', 2, 4, 90000, 0, '68b80076be826_1757067468_1763548671.jpg', 'rejected', 'inactive', 'mệt');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tonkho`
--

CREATE TABLE `tonkho` (
  `matonkho` int(11) NOT NULL,
  `hinhanh` varchar(500) NOT NULL,
  `tentonkho` varchar(100) NOT NULL,
  `soluong` int(11) NOT NULL,
  `DonViTinh` varchar(100) NOT NULL,
  `idloaiTK` int(11) NOT NULL,
  `idncc` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `tonkho`
--

INSERT INTO `tonkho` (`matonkho`, `hinhanh`, `tentonkho`, `soluong`, `DonViTinh`, `idloaiTK`, `idncc`) VALUES
(1, 'rice.jpg', 'Gạo', 22, 'kg', 1, 1),
(2, 'daonho.jpg', 'Dao nhỏ', 20, 'cái', 2, 2),
(3, 'thitbo.jpg', 'Thịt bò', 30, 'kg', 1, 1),
(5, 'cahoi.jpg', 'Cá hồi', 26, 'kg', 1, 1),
(6, 'muonginox.jpg', 'Muỗng inox', 81, 'cái', 2, 2),
(7, 'raucai.jpg', 'Rau cải', 41, 'kg', 1, 1),
(8, 'khangiay.jpg', 'Khăn giấy', 200, 'gói', 2, 2),
(9, 'fishsauce.jpg', 'Nước mắm', 61, 'lít', 1, 1),
(10, 'diasu.jpg', 'Đĩa lớn', 50, 'cái', 2, 2),
(12, 'carot.jpg', 'Cà rốt', 20, 'kg', 1, 1),
(13, 'whippingcream.jpg', 'Whipping Cream', 11, 'hộp', 1, 1),
(17, 'cachep.jpg', 'Cá chép', 1, 'kg', 1, 2),
(21, '68bfdf554865e.jpg', 'Chanh', 2, 'kg', 1, 1),
(22, '68bfec22b7e57.jpg', 'Dâu tây', 1, 'kg', 1, 1),
(23, '68bff934155a9.jpg', 'Kiwi', 1, 'kg', 1, 1),
(24, 'corn.jpg', 'Bắp', 1, 'kg', 1, 1),
(25, '68c000a0bde94.png', 'Sữa chua ', 1, 'thùng', 1, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `vaitro`
--

CREATE TABLE `vaitro` (
  `idvaitro` int(11) NOT NULL,
  `tenvaitro` varchar(100) NOT NULL,
  `quyen` varchar(1000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Đang đổ dữ liệu cho bảng `vaitro`
--

INSERT INTO `vaitro` (`idvaitro`, `tenvaitro`, `quyen`) VALUES
(1, 'phuc vu', 'xem don hang,them don hang'),
(2, 'thu ngan', 'xem khach hang,them khach hang,sua khach hang,xem don hang,them don hang,sua don hang,thanh toan don hang,xem hoa don'),
(3, 'dau bep', 'xem don hang,xem ton kho,sua ton kho,xem nhacungcap,them nhacungcap,sua nhacungcap,xoa nhacungcap,xem thuc don,them thuc don,sua thuc don,xoa thuc don'),
(4, 'quan ly', 'xem trang chu,xem nhan vien,them nhan vien,sua nhan vien,xoa nhan vien,xem khach hang,them khach hang,sua khach hang,xoa khach hang,xem mon an,them mon an,sua mon an,xoa mon an,xem don hang,xem don dat ban,them don dat ban,sua don dat ban,xoa don dat ban,xem ton kho,them ton kho,sua ton kho,xoa ton kho,xem vai tro,them vai tro,sua vai tro,xoa vai tro,xem nhacungcap,them nhacungcap,sua nhacungcap,xoa nhacungcap,xem lich lam viec,sua lich lam viec,xem thuc don,them thuc don,sua thuc don,xoa thuc don');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `attendance_log`
--
ALTER TABLE `attendance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attendance_nhanvien` (`idnv`);

--
-- Chỉ mục cho bảng `ban`
--
ALTER TABLE `ban`
  ADD PRIMARY KEY (`idban`),
  ADD UNIQUE KEY `uniq_qr_token` (`qr_token`),
  ADD KEY `FK_Ban_KhuVuc` (`MaKV`);

--
-- Chỉ mục cho bảng `calamviec`
--
ALTER TABLE `calamviec`
  ADD PRIMARY KEY (`idca`);

--
-- Chỉ mục cho bảng `chamcong`
--
ALTER TABLE `chamcong`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_chamcong` (`idnv`,`ngay`),
  ADD KEY `idx_chamcong_nv_ngay` (`idnv`,`ngay`);

--
-- Chỉ mục cho bảng `chatbot_item_rules`
--
ALTER TABLE `chatbot_item_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lhs` (`lhs_ids`),
  ADD KEY `idx_rhs` (`rhs_id`),
  ADD KEY `idx_conf` (`confidence`);

--
-- Chỉ mục cho bảng `chatrooms`
--
ALTER TABLE `chatrooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chatrooms_user` (`userid`);

--
-- Chỉ mục cho bảng `chat_conversation`
--
ALTER TABLE `chat_conversation`
  ADD PRIMARY KEY (`conversation_id`),
  ADD KEY `idx_chat_conversation_customer` (`customer_user_id`),
  ADD KEY `idx_chat_conversation_cashier` (`last_cashier_user_id`);

--
-- Chỉ mục cho bảng `chat_conversation_agents`
--
ALTER TABLE `chat_conversation_agents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_agents_conversation` (`conversation_id`),
  ADD KEY `idx_conversation_agents_cashier` (`cashier_user_id`);

--
-- Chỉ mục cho bảng `chat_message`
--
ALTER TABLE `chat_message`
  ADD PRIMARY KEY (`chat_message_id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_to_user` (`to_user_id`),
  ADD KEY `idx_from_user` (`from_user_id`);

--
-- Chỉ mục cho bảng `chat_user_table`
--
ALTER TABLE `chat_user_table`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uniq_external_type` (`user_type`,`external_id`),
  ADD UNIQUE KEY `uniq_user_token` (`user_token`),
  ADD KEY `idx_user_email` (`user_email`);

--
-- Chỉ mục cho bảng `chitietdatban`
--
ALTER TABLE `chitietdatban`
  ADD PRIMARY KEY (`idChiTiet`),
  ADD KEY `madatban` (`madatban`),
  ADD KEY `idmonan` (`idmonan`);

--
-- Chỉ mục cho bảng `chitietdonhang`
--
ALTER TABLE `chitietdonhang`
  ADD PRIMARY KEY (`idCTDH`),
  ADD KEY `idDH` (`idDH`),
  ADD KEY `idmonan` (`idmonan`);

--
-- Chỉ mục cho bảng `chitiethoadon`
--
ALTER TABLE `chitiethoadon`
  ADD PRIMARY KEY (`idCTHD`),
  ADD KEY `idmonan` (`idmonan`),
  ADD KEY `idHD` (`idHD`);

--
-- Chỉ mục cho bảng `chitietnhapkho`
--
ALTER TABLE `chitietnhapkho`
  ADD PRIMARY KEY (`maCTNK`),
  ADD KEY `manhapkho` (`manhapkho`),
  ADD KEY `matonkho` (`matonkho`);

--
-- Chỉ mục cho bảng `chitietphuthu`
--
ALTER TABLE `chitietphuthu`
  ADD PRIMARY KEY (`idChiTietPhuThu`),
  ADD KEY `madatban` (`madatban`),
  ADD KEY `MaKV` (`MaKV`);

--
-- Chỉ mục cho bảng `chitietthucdon`
--
ALTER TABLE `chitietthucdon`
  ADD PRIMARY KEY (`idCTTD`),
  ADD KEY `idmonan` (`idmonan`),
  ADD KEY `idthucdon` (`idthucdon`);

--
-- Chỉ mục cho bảng `chitiet_ban_datban`
--
ALTER TABLE `chitiet_ban_datban`
  ADD PRIMARY KEY (`id`),
  ADD KEY `madatban` (`madatban`),
  ADD KEY `idban` (`idban`);

--
-- Chỉ mục cho bảng `danhmuc`
--
ALTER TABLE `danhmuc`
  ADD PRIMARY KEY (`iddm`);

--
-- Chỉ mục cho bảng `datban`
--
ALTER TABLE `datban`
  ADD PRIMARY KEY (`madatban`),
  ADD KEY `makh` (`idKH`),
  ADD KEY `idx_datban_payment_expires` (`payment_expires`);

--
-- Chỉ mục cho bảng `datban_admin_meta`
--
ALTER TABLE `datban_admin_meta`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `donhang`
--
ALTER TABLE `donhang`
  ADD PRIMARY KEY (`idDH`),
  ADD UNIQUE KEY `MaDonHang` (`MaDonHang`),
  ADD KEY `fk_donhang_datban` (`madatban`),
  ADD KEY `donhang_ibfk_4` (`idban`);

--
-- Chỉ mục cho bảng `donhang_admin_meta`
--
ALTER TABLE `donhang_admin_meta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idDH` (`idDH`);

--
-- Chỉ mục cho bảng `donhang_ban`
--
ALTER TABLE `donhang_ban`
  ADD PRIMARY KEY (`idDH`,`idban`),
  ADD KEY `fk_dhban_ban` (`idban`);

--
-- Chỉ mục cho bảng `donhang_item_status_log`
--
ALTER TABLE `donhang_item_status_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_item_log_item` (`idCTDH`),
  ADD KEY `fk_item_log_order` (`idDH`);

--
-- Chỉ mục cho bảng `donhang_tables`
--
ALTER TABLE `donhang_tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_order_table` (`idDH`,`idban`),
  ADD KEY `idx_table` (`idban`);

--
-- Chỉ mục cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD PRIMARY KEY (`idHD`),
  ADD KEY `maKH` (`idKH`),
  ADD KEY `idKH` (`idKH`),
  ADD KEY `idmonan` (`idDH`);

--
-- Chỉ mục cho bảng `inventory_consumption_log`
--
ALTER TABLE `inventory_consumption_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_inventory_order` (`idDH`),
  ADD KEY `fk_inventory_item` (`idCTDH`),
  ADD KEY `fk_inventory_batch` (`maCTNK`);

--
-- Chỉ mục cho bảng `khachhang`
--
ALTER TABLE `khachhang`
  ADD PRIMARY KEY (`idKH`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Chỉ mục cho bảng `khuvucban`
--
ALTER TABLE `khuvucban`
  ADD PRIMARY KEY (`MaKV`);

--
-- Chỉ mục cho bảng `khuyenmai`
--
ALTER TABLE `khuyenmai`
  ADD PRIMARY KEY (`MaKhuyenMai`);

--
-- Chỉ mục cho bảng `lichlamviec`
--
ALTER TABLE `lichlamviec`
  ADD PRIMARY KEY (`maLLV`),
  ADD KEY `idca` (`idca`),
  ADD KEY `manhanvien` (`idnv`);

--
-- Chỉ mục cho bảng `loaitonkho`
--
ALTER TABLE `loaitonkho`
  ADD PRIMARY KEY (`idloaiTK`);

--
-- Chỉ mục cho bảng `monan`
--
ALTER TABLE `monan`
  ADD PRIMARY KEY (`idmonan`),
  ADD KEY `iddm` (`iddm`);

--
-- Chỉ mục cho bảng `nguoidung`
--
ALTER TABLE `nguoidung`
  ADD PRIMARY KEY (`idnguoidung`),
  ADD KEY `idtaikhoan` (`idtaikhoan`);

--
-- Chỉ mục cho bảng `nhacungcap`
--
ALTER TABLE `nhacungcap`
  ADD PRIMARY KEY (`idncc`);

--
-- Chỉ mục cho bảng `nhanvien`
--
ALTER TABLE `nhanvien`
  ADD PRIMARY KEY (`idnv`),
  ADD KEY `idvaitro` (`idvaitro`);

--
-- Chỉ mục cho bảng `nhanvien_face`
--
ALTER TABLE `nhanvien_face`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_face_idnv` (`idnv`);

--
-- Chỉ mục cho bảng `nhapkho`
--
ALTER TABLE `nhapkho`
  ADD PRIMARY KEY (`manhapkho`),
  ADD KEY `idncc` (`idncc`);

--
-- Chỉ mục cho bảng `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `fk_password_resets_khachhang` (`idKH`);

--
-- Chỉ mục cho bảng `pay_periods`
--
ALTER TABLE `pay_periods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `period_code` (`period_code`),
  ADD KEY `idx_period_status` (`status`);

--
-- Chỉ mục cho bảng `pay_salary_lines`
--
ALTER TABLE `pay_salary_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_period_staff` (`period_id`,`staff_id`);

--
-- Chỉ mục cho bảng `phanhoi`
--
ALTER TABLE `phanhoi`
  ADD PRIMARY KEY (`idPhanHoi`),
  ADD KEY `idKH` (`idKH`);

--
-- Chỉ mục cho bảng `thanhphan`
--
ALTER TABLE `thanhphan`
  ADD PRIMARY KEY (`idthanhphan`),
  ADD KEY `idtonkho` (`matonkho`),
  ADD KEY `idmonan` (`idmonan`);

--
-- Chỉ mục cho bảng `thanhtoan`
--
ALTER TABLE `thanhtoan`
  ADD PRIMARY KEY (`idThanhToan`),
  ADD KEY `madatban` (`madatban`),
  ADD KEY `fk_thanhtoan_donhang` (`idDH`);

--
-- Chỉ mục cho bảng `thucdon`
--
ALTER TABLE `thucdon`
  ADD PRIMARY KEY (`idthucdon`),
  ADD KEY `idCTTD` (`idCTTD`);

--
-- Chỉ mục cho bảng `tonkho`
--
ALTER TABLE `tonkho`
  ADD PRIMARY KEY (`matonkho`),
  ADD KEY `idloaiTK` (`idloaiTK`),
  ADD KEY `idncc` (`idncc`);

--
-- Chỉ mục cho bảng `vaitro`
--
ALTER TABLE `vaitro`
  ADD PRIMARY KEY (`idvaitro`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `attendance_log`
--
ALTER TABLE `attendance_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT cho bảng `ban`
--
ALTER TABLE `ban`
  MODIFY `idban` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT cho bảng `calamviec`
--
ALTER TABLE `calamviec`
  MODIFY `idca` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `chamcong`
--
ALTER TABLE `chamcong`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT cho bảng `chatbot_item_rules`
--
ALTER TABLE `chatbot_item_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT cho bảng `chatrooms`
--
ALTER TABLE `chatrooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `chat_conversation`
--
ALTER TABLE `chat_conversation`
  MODIFY `conversation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `chat_conversation_agents`
--
ALTER TABLE `chat_conversation_agents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `chat_message`
--
ALTER TABLE `chat_message`
  MODIFY `chat_message_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT cho bảng `chat_user_table`
--
ALTER TABLE `chat_user_table`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=354;

--
-- AUTO_INCREMENT cho bảng `chitietdatban`
--
ALTER TABLE `chitietdatban`
  MODIFY `idChiTiet` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=583;

--
-- AUTO_INCREMENT cho bảng `chitietdonhang`
--
ALTER TABLE `chitietdonhang`
  MODIFY `idCTDH` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=327;

--
-- AUTO_INCREMENT cho bảng `chitiethoadon`
--
ALTER TABLE `chitiethoadon`
  MODIFY `idCTHD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT cho bảng `chitietnhapkho`
--
ALTER TABLE `chitietnhapkho`
  MODIFY `maCTNK` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT cho bảng `chitietphuthu`
--
ALTER TABLE `chitietphuthu`
  MODIFY `idChiTietPhuThu` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `chitietthucdon`
--
ALTER TABLE `chitietthucdon`
  MODIFY `idCTTD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT cho bảng `chitiet_ban_datban`
--
ALTER TABLE `chitiet_ban_datban`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;

--
-- AUTO_INCREMENT cho bảng `danhmuc`
--
ALTER TABLE `danhmuc`
  MODIFY `iddm` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `datban`
--
ALTER TABLE `datban`
  MODIFY `madatban` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=198;

--
-- AUTO_INCREMENT cho bảng `datban_admin_meta`
--
ALTER TABLE `datban_admin_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT cho bảng `donhang`
--
ALTER TABLE `donhang`
  MODIFY `idDH` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT cho bảng `donhang_admin_meta`
--
ALTER TABLE `donhang_admin_meta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT cho bảng `donhang_item_status_log`
--
ALTER TABLE `donhang_item_status_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT cho bảng `donhang_tables`
--
ALTER TABLE `donhang_tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  MODIFY `idHD` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT cho bảng `inventory_consumption_log`
--
ALTER TABLE `inventory_consumption_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `khachhang`
--
ALTER TABLE `khachhang`
  MODIFY `idKH` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `khuvucban`
--
ALTER TABLE `khuvucban`
  MODIFY `MaKV` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `lichlamviec`
--
ALTER TABLE `lichlamviec`
  MODIFY `maLLV` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `loaitonkho`
--
ALTER TABLE `loaitonkho`
  MODIFY `idloaiTK` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `monan`
--
ALTER TABLE `monan`
  MODIFY `idmonan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT cho bảng `nguoidung`
--
ALTER TABLE `nguoidung`
  MODIFY `idnguoidung` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `nhacungcap`
--
ALTER TABLE `nhacungcap`
  MODIFY `idncc` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `nhanvien`
--
ALTER TABLE `nhanvien`
  MODIFY `idnv` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT cho bảng `nhanvien_face`
--
ALTER TABLE `nhanvien_face`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `nhapkho`
--
ALTER TABLE `nhapkho`
  MODIFY `manhapkho` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT cho bảng `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `pay_periods`
--
ALTER TABLE `pay_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `pay_salary_lines`
--
ALTER TABLE `pay_salary_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT cho bảng `phanhoi`
--
ALTER TABLE `phanhoi`
  MODIFY `idPhanHoi` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `thanhphan`
--
ALTER TABLE `thanhphan`
  MODIFY `idthanhphan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT cho bảng `thanhtoan`
--
ALTER TABLE `thanhtoan`
  MODIFY `idThanhToan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=149;

--
-- AUTO_INCREMENT cho bảng `thucdon`
--
ALTER TABLE `thucdon`
  MODIFY `idthucdon` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `tonkho`
--
ALTER TABLE `tonkho`
  MODIFY `matonkho` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT cho bảng `vaitro`
--
ALTER TABLE `vaitro`
  MODIFY `idvaitro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `attendance_log`
--
ALTER TABLE `attendance_log`
  ADD CONSTRAINT `fk_attendance_nhanvien` FOREIGN KEY (`idnv`) REFERENCES `nhanvien` (`idnv`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `ban`
--
ALTER TABLE `ban`
  ADD CONSTRAINT `FK_Ban_KhuVuc` FOREIGN KEY (`MaKV`) REFERENCES `khuvucban` (`MaKV`);

--
-- Các ràng buộc cho bảng `chamcong`
--
ALTER TABLE `chamcong`
  ADD CONSTRAINT `fk_chamcong_nhanvien` FOREIGN KEY (`idnv`) REFERENCES `nhanvien` (`idnv`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `chatrooms`
--
ALTER TABLE `chatrooms`
  ADD CONSTRAINT `fk_chatrooms_user` FOREIGN KEY (`userid`) REFERENCES `chat_user_table` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `chat_conversation_agents`
--
ALTER TABLE `chat_conversation_agents`
  ADD CONSTRAINT `fk_chat_conversation_agents_cashier` FOREIGN KEY (`cashier_user_id`) REFERENCES `chat_user_table` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_conversation_agents_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversation` (`conversation_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `chat_message`
--
ALTER TABLE `chat_message`
  ADD CONSTRAINT `fk_chat_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversation` (`conversation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_message_from` FOREIGN KEY (`from_user_id`) REFERENCES `chat_user_table` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_chat_message_to` FOREIGN KEY (`to_user_id`) REFERENCES `chat_user_table` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `chitietdatban`
--
ALTER TABLE `chitietdatban`
  ADD CONSTRAINT `chitietdatban_ibfk_1` FOREIGN KEY (`madatban`) REFERENCES `datban` (`madatban`) ON DELETE CASCADE,
  ADD CONSTRAINT `chitietdatban_ibfk_2` FOREIGN KEY (`idmonan`) REFERENCES `monan` (`idmonan`);

--
-- Các ràng buộc cho bảng `chitietdonhang`
--
ALTER TABLE `chitietdonhang`
  ADD CONSTRAINT `chitietdonhang_ibfk_1` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`),
  ADD CONSTRAINT `chitietdonhang_ibfk_2` FOREIGN KEY (`idmonan`) REFERENCES `monan` (`idmonan`);

--
-- Các ràng buộc cho bảng `chitiethoadon`
--
ALTER TABLE `chitiethoadon`
  ADD CONSTRAINT `chitiethoadon_ibfk_1` FOREIGN KEY (`idHD`) REFERENCES `hoadon` (`idHD`);

--
-- Các ràng buộc cho bảng `chitietnhapkho`
--
ALTER TABLE `chitietnhapkho`
  ADD CONSTRAINT `chitietnhapkho_ibfk_1` FOREIGN KEY (`manhapkho`) REFERENCES `nhapkho` (`manhapkho`);

--
-- Các ràng buộc cho bảng `chitietphuthu`
--
ALTER TABLE `chitietphuthu`
  ADD CONSTRAINT `chitietphuthu_ibfk_1` FOREIGN KEY (`madatban`) REFERENCES `datban` (`madatban`) ON DELETE CASCADE,
  ADD CONSTRAINT `chitietphuthu_ibfk_2` FOREIGN KEY (`MaKV`) REFERENCES `khuvucban` (`MaKV`);

--
-- Các ràng buộc cho bảng `chitietthucdon`
--
ALTER TABLE `chitietthucdon`
  ADD CONSTRAINT `chitietthucdon_ibfk_1` FOREIGN KEY (`idmonan`) REFERENCES `monan` (`idmonan`),
  ADD CONSTRAINT `chitietthucdon_ibfk_2` FOREIGN KEY (`idthucdon`) REFERENCES `thucdon` (`idthucdon`);

--
-- Các ràng buộc cho bảng `chitiet_ban_datban`
--
ALTER TABLE `chitiet_ban_datban`
  ADD CONSTRAINT `fk_chitiet_ban_datban_ban` FOREIGN KEY (`idban`) REFERENCES `ban` (`idban`),
  ADD CONSTRAINT `fk_chitiet_ban_datban_datban` FOREIGN KEY (`madatban`) REFERENCES `datban` (`madatban`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `datban`
--
ALTER TABLE `datban`
  ADD CONSTRAINT `datban_ibfk_1` FOREIGN KEY (`idKH`) REFERENCES `khachhang` (`idKH`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `donhang`
--
ALTER TABLE `donhang`
  ADD CONSTRAINT `donhang_ibfk_4` FOREIGN KEY (`idban`) REFERENCES `ban` (`idban`),
  ADD CONSTRAINT `fk_donhang_datban` FOREIGN KEY (`madatban`) REFERENCES `datban` (`madatban`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `donhang_admin_meta`
--
ALTER TABLE `donhang_admin_meta`
  ADD CONSTRAINT `fk_donhang_meta_order` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `donhang_ban`
--
ALTER TABLE `donhang_ban`
  ADD CONSTRAINT `fk_dhban_ban` FOREIGN KEY (`idban`) REFERENCES `ban` (`idban`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dhban_donhang` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `donhang_item_status_log`
--
ALTER TABLE `donhang_item_status_log`
  ADD CONSTRAINT `fk_item_log_item` FOREIGN KEY (`idCTDH`) REFERENCES `chitietdonhang` (`idCTDH`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_log_order` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `donhang_tables`
--
ALTER TABLE `donhang_tables`
  ADD CONSTRAINT `fk_donhang_tables_order` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_donhang_tables_table` FOREIGN KEY (`idban`) REFERENCES `ban` (`idban`);

--
-- Các ràng buộc cho bảng `hoadon`
--
ALTER TABLE `hoadon`
  ADD CONSTRAINT `hoadon_ibfk_3` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`);

--
-- Các ràng buộc cho bảng `inventory_consumption_log`
--
ALTER TABLE `inventory_consumption_log`
  ADD CONSTRAINT `fk_inventory_batch` FOREIGN KEY (`maCTNK`) REFERENCES `chitietnhapkho` (`maCTNK`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_item` FOREIGN KEY (`idCTDH`) REFERENCES `chitietdonhang` (`idCTDH`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventory_order` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `lichlamviec`
--
ALTER TABLE `lichlamviec`
  ADD CONSTRAINT `lichlamviec_ibfk_1` FOREIGN KEY (`idca`) REFERENCES `calamviec` (`idca`),
  ADD CONSTRAINT `lichlamviec_ibfk_2` FOREIGN KEY (`idnv`) REFERENCES `nhanvien` (`idnv`);

--
-- Các ràng buộc cho bảng `monan`
--
ALTER TABLE `monan`
  ADD CONSTRAINT `monan_ibfk_1` FOREIGN KEY (`iddm`) REFERENCES `danhmuc` (`iddm`);

--
-- Các ràng buộc cho bảng `nhanvien_face`
--
ALTER TABLE `nhanvien_face`
  ADD CONSTRAINT `fk_face_nhanvien` FOREIGN KEY (`idnv`) REFERENCES `nhanvien` (`idnv`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `nhapkho`
--
ALTER TABLE `nhapkho`
  ADD CONSTRAINT `nhapkho_ibfk_1` FOREIGN KEY (`idncc`) REFERENCES `nhacungcap` (`idncc`);

--
-- Các ràng buộc cho bảng `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_khachhang` FOREIGN KEY (`idKH`) REFERENCES `khachhang` (`idKH`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `pay_salary_lines`
--
ALTER TABLE `pay_salary_lines`
  ADD CONSTRAINT `fk_pay_line_period` FOREIGN KEY (`period_id`) REFERENCES `pay_periods` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `phanhoi`
--
ALTER TABLE `phanhoi`
  ADD CONSTRAINT `phanhoi_ibfk_1` FOREIGN KEY (`idKH`) REFERENCES `khachhang` (`idKH`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `thanhphan`
--
ALTER TABLE `thanhphan`
  ADD CONSTRAINT `thanhphan_ibfk_1` FOREIGN KEY (`idmonan`) REFERENCES `monan` (`idmonan`),
  ADD CONSTRAINT `thanhphan_ibfk_2` FOREIGN KEY (`matonkho`) REFERENCES `tonkho` (`matonkho`);

--
-- Các ràng buộc cho bảng `thanhtoan`
--
ALTER TABLE `thanhtoan`
  ADD CONSTRAINT `fk_thanhtoan_datban` FOREIGN KEY (`madatban`) REFERENCES `datban` (`madatban`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_thanhtoan_donhang` FOREIGN KEY (`idDH`) REFERENCES `donhang` (`idDH`);

--
-- Các ràng buộc cho bảng `tonkho`
--
ALTER TABLE `tonkho`
  ADD CONSTRAINT `tonkho_ibfk_1` FOREIGN KEY (`idloaiTK`) REFERENCES `loaitonkho` (`idloaiTK`),
  ADD CONSTRAINT `tonkho_ibfk_2` FOREIGN KEY (`idncc`) REFERENCES `nhacungcap` (`idncc`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
