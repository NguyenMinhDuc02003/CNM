<?php

require_once __DIR__ . '/helpers/ChatIdentity.php';
require_once __DIR__ . '/config/chat.php';

// API nhỏ để lưu tệp đính kèm trước khi phát đi bằng websocket.

ChatIdentity::bootstrapSession();

header('Content-Type: application/json');

if(empty($_SESSION['user_data']))
{
	echo json_encode([
		'success' => false,
		'message' => 'Bạn cần đăng nhập để sử dụng chức năng này.'
	]);
	exit;
}

if(!isset($_FILES['attachment']))
{
	echo json_encode([
		'success' => false,
		'message' => 'Không tìm thấy tệp cần tải lên.'
	]);
	exit;
}

$file = $_FILES['attachment'];

if($file['error'] !== UPLOAD_ERR_OK)
{
	echo json_encode([
		'success' => false,
		'message' => 'Tải tệp lên thất bại (mã lỗi '.$file['error'].').'
	]);
	exit;
}

if($file['size'] > CHAT_MAX_UPLOAD_SIZE)
{
	echo json_encode([
		'success' => false,
		'message' => 'Tệp vượt quá dung lượng cho phép.'
	]);
	exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMime = array_merge(CHAT_ALLOWED_IMAGE_TYPES, CHAT_ALLOWED_FILE_TYPES);

if(!in_array($detectedMime, $allowedMime, true))
{
	echo json_encode([
		'success' => false,
		'message' => 'Định dạng tệp không được hỗ trợ.'
	]);
	exit;
}

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$safeExtension = $extension ? '.'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $extension)) : '';
$newName = uniqid('chat_', true) . $safeExtension;

if(!is_dir(CHAT_UPLOAD_DIRECTORY))
{
	mkdir(CHAT_UPLOAD_DIRECTORY, 0777, true);
}

$destination = rtrim(CHAT_UPLOAD_DIRECTORY, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;

if(!move_uploaded_file($file['tmp_name'], $destination))
{
	echo json_encode([
		'success' => false,
		'message' => 'Không thể lưu tệp trên máy chủ.'
	]);
	exit;
}

$relativePath = rtrim(CHAT_UPLOAD_RELATIVE_PATH, '/') . '/' . $newName;

echo json_encode([
	'success' => true,
	'data' => [
		'name' => basename($file['name']),
		'path' => $relativePath,
		'mime' => $detectedMime,
		'size' => (int) $file['size']
	]
]);
