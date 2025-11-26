<?php

require_once __DIR__ . '/helpers/ChatIdentity.php';
require_once __DIR__ . '/config/chat.php';
require_once __DIR__ . '/config/bot.php';
require_once __DIR__ . '/database/ChatUser.php';
require_once __DIR__ . '/database/PrivateChat.php';

ChatIdentity::bootstrapSession();

$chat_base_path = CHAT_APP_BASE_PATH;
$chat_base_url = CHAT_APP_BASE_URL;
$asset_base = htmlspecialchars($chat_base_path, ENT_QUOTES, 'UTF-8');

$identity = ChatIdentity::resolve();
if(!$identity)
{
	echo '<div style="padding:20px;font-family:Arial,sans-serif;">Vui lòng đăng nhập trước khi mở chat.</div>';
	exit;
}

$user_object = new ChatUser;
$user_object->setExternalId($identity['external_id']);
$user_object->setUserType($identity['type']);
$user_object->setUserEmail($identity['email']);
$user_object->setUserName($identity['name']);
$user_object->setUserProfile($identity['avatar']);

$user_id = $user_object->sync_system_identity();

$user_token = bin2hex(random_bytes(20));
$user_object->setUserId($user_id);
$user_object->setUserLoginStatus('Login');
$user_object->setUserToken($user_token);
$user_object->update_user_login_data();

$_SESSION['user_data'][$user_id] = [
	'id' => $user_id,
	'name' => $identity['name'],
	'profile' => $identity['avatar'],
	'token' => $user_token,
	'type' => $identity['type'],
	'email' => $identity['email']
];

$_SESSION['active_chat_user_id'] = $user_id;

function render_chat_attachment($path, $name, $mime)
{
	if(empty($path))
	{
		return '';
	}

	$safe_path = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
	$label = htmlspecialchars($name !== '' ? $name : basename($path), ENT_QUOTES, 'UTF-8');

	if(is_string($mime) && strpos($mime, 'image/') === 0)
	{
		return '<div class="mt-2"><img src="'.$safe_path.'" alt="'.$label.'" class="img-fluid rounded border"></div>';
	}

	return '<div class="mt-2"><a href="'.$safe_path.'" target="_blank" rel="noopener">'.$label.'</a></div>';
}

function format_message_text($text)
{
	$trimmed = trim((string)$text);
	if($trimmed === '')
	{
		return '<em>Đã gửi tệp đính kèm</em>';
	}

	return nl2br(htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'));
}

function resolve_avatar(string $path = null): string
{
	$clean = trim((string)$path);
	if($clean === '')
	{
		return 'https://www.gravatar.com/avatar/?d=mp';
	}
	return $clean;
}

$active_session_user = null;
if(isset($_SESSION['active_chat_user_id']) && isset($_SESSION['user_data'][$_SESSION['active_chat_user_id']]))
{
	$active_session_user = $_SESSION['user_data'][$_SESSION['active_chat_user_id']];
}
else
{
	$firstEntry = reset($_SESSION['user_data']);
	$active_session_user = $firstEntry !== false ? $firstEntry : null;
}

if(!$active_session_user)
{
	echo '<div style="padding:20px;font-family:Arial,sans-serif;">Không xác định được người dùng chat hiện tại.</div>';
	exit;
}
$login_user_id = $active_session_user['id'];
$token = $active_session_user['token'];
$active_user_name = $active_session_user['name'];
$active_user_profile = $active_session_user['profile'];
$active_user_type = $active_session_user['type'] ?? '';
$websocket_url = chat_build_websocket_url($token);

$chat_user_object = new ChatUser;
$default_support_user = null;
if($active_user_type === 'customer')
{
	$default_support_user = $chat_user_object->get_default_employee();
}
else
{
	$employees = $chat_user_object->get_users_by_type('employee');
	if(!empty($employees))
	{
		$default_support_user = $employees[0];
	}
}

$receiver_user_id = $default_support_user['user_id'] ?? null;
$receiver_user_name = $default_support_user['user_name'] ?? 'Nhà hàng';
$receiver_user_profile = resolve_avatar($default_support_user['user_profile'] ?? '');

$chat_history = [];
$active_conversation_id = null;
if($receiver_user_id)
{
	$status_updater = new PrivateChat;
	$status_updater->setFromUserId($receiver_user_id);
	$status_updater->setToUserId($login_user_id);
	$status_updater->change_chat_status();

	$history_loader = new PrivateChat;
	$history_loader->setFromUserId($receiver_user_id);
	$history_loader->setToUserId($login_user_id);
	$chat_history = $history_loader->get_all_chat_data();
	$active_conversation_id = $history_loader->getConversationId();

	$current_cashier_id = $history_loader->getActiveCashierUserId();
	if(!$current_cashier_id && !empty($chat_history))
	{
		foreach($chat_history as $chat_row)
		{
			if(($chat_row['from_user_type'] ?? '') === 'employee')
			{
				$current_cashier_id = (int)$chat_row['from_user_id'];
			}
		}
	}

	if($current_cashier_id)
	{
		$chat_user_object->setUserId($current_cashier_id);
		$active_cashier = $chat_user_object->get_user_data_by_id();
		if(!empty($active_cashier))
		{
			$receiver_user_id = (int)$active_cashier['user_id'];
			$receiver_user_name = $active_cashier['user_name'];
			$receiver_user_profile = resolve_avatar($active_cashier['user_profile'] ?? '');
		}
	}
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<title>CNM Chat Widget</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="<?php echo $asset_base; ?>vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">
	<link href="<?php echo $asset_base; ?>vendor-front/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
	<link rel="stylesheet" type="text/css" href="<?php echo $asset_base; ?>vendor-front/parsley/parsley.css"/>
	<script src="<?php echo $asset_base; ?>vendor-front/jquery/jquery.min.js"></script>
	<script src="<?php echo $asset_base; ?>vendor-front/bootstrap/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo $asset_base; ?>vendor-front/jquery-easing/jquery.easing.min.js"></script>
	<script type="text/javascript" src="<?php echo $asset_base; ?>vendor-front/parsley/dist/parsley.min.js"></script>
	<style>
		body {
			font-family: "Helvetica Neue", Arial, sans-serif;
			background: #f5f5f7;
			margin: 0;
			padding: 0;
		}
		.widget-shell {
			height: 100vh;
			display: flex;
			flex-direction: column;
		}
		.widget-header {
			background: #FEA116;
			color: #fff;
			padding: 12px 16px;
			display: flex;
			align-items: center;
			gap: 12px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.15);
		}
		.widget-header img {
			width: 40px;
			height: 40px;
			border-radius: 50%;
			object-fit: cover;
		}
		.widget-body {
			flex: 1;
			overflow-y: auto;
			padding: 16px;
			background: #fdfdfd;
		}
		.widget-footer {
			border-top: 1px solid #eee;
			padding: 12px 16px;
			background: #fff;
		}
		.widget-message {
			margin-bottom: 14px;
		}
		.widget-message .bubble {
			display: inline-block;
			padding: 10px 14px;
			border-radius: 16px;
			max-width: 90%;
			word-break: break-word;
			font-size: 14px;
		}
		.widget-message.me {
			text-align: right;
		}
		.widget-message.me .bubble {
			background: #E5F4FF;
			color: #03426e;
			border-bottom-right-radius: 4px;
		}
		.widget-message.other .bubble {
			background: #F2F2F2;
			color: #222;
			border-bottom-left-radius: 4px;
		}
		.widget-message small {
			display: block;
			font-size: 11px;
			color: #888;
			margin-top: 4px;
		}
		#widget_messages {
			min-height: 200px;
		}
		#attachment_status {
			font-size: 12px;
			margin-top: 4px;
		}
		.widget-placeholder {
			text-align: center;
			color: #999;
			padding: 32px 12px;
		}
	</style>
</head>
<body>
	<div class="widget-shell">
		<header class="widget-header">
			<img src="<?php echo htmlspecialchars($receiver_user_profile); ?>" alt="Support Avatar">
			<div>
				<strong><?php echo htmlspecialchars($receiver_user_name); ?></strong><br>
				<span style="font-size:12px;">Đội hỗ trợ nhà hàng</span>
			</div>
		</header>

		<div class="widget-body" id="widget_messages">
			<?php if(!$receiver_user_id): ?>
				<div class="widget-placeholder">
					<i class="fa fa-info-circle mb-2"></i>
					<p>Hiện chưa có nhân viên trực tuyến. Vui lòng thử lại sau.</p>
				</div>
			<?php elseif(empty($chat_history)): ?>
				<div class="widget-placeholder">
					<i class="fa fa-comments mb-2"></i>
					<p>Bắt đầu cuộc trò chuyện với nhà hàng.</p>
				</div>
			<?php else: ?>
				<?php foreach($chat_history as $chat): ?>
					<?php
						$is_employee_message = ($chat['from_user_type'] ?? '') === 'employee';
						$is_me = !$is_employee_message;
						$row_class = $is_me ? 'me' : 'other';
						$sender_label = $is_me ? 'Bạn' : 'Nhà hàng';
						$attachment_html = render_chat_attachment($chat['attachment_path'] ?? '', $chat['attachment_name'] ?? '', $chat['attachment_mime'] ?? '');
					?>
					<div class="widget-message <?php echo $row_class; ?>">
						<div class="bubble">
							<b><?php echo $sender_label; ?>:</b>
							<?php echo format_message_text($chat['chat_message'] ?? ''); ?>
							<?php echo $attachment_html; ?>
							<small><?php echo htmlspecialchars($chat['timestamp'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<footer class="widget-footer">
			<form id="widget_form" data-parsley-errors-container="#widget_error">
				<input type="hidden" id="login_user_id" value="<?php echo $login_user_id; ?>">
				<input type="hidden" id="receiver_user_id" value="<?php echo $receiver_user_id ?: 0; ?>">
				<input type="hidden" id="conversation_id" value="<?php echo (int)($active_conversation_id ?? 0); ?>">
				<input type="hidden" id="receiver_user_name" value="<?php echo htmlspecialchars($receiver_user_name, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="form-group mb-2">
					<textarea class="form-control" id="widget_message" placeholder="Nhập tin nhắn..." rows="2" data-parsley-maxlength="1000"></textarea>
				</div>
				<div class="form-group mb-2">
					<label class="small font-weight-bold">Đính kèm ảnh / tệp (tối đa 10MB)</label>
					<input type="file" class="form-control-file" id="widget_attachment" accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/x-rar-compressed,text/plain">
					<small id="attachment_status" class="text-muted">Chưa chọn tệp.</small>
				</div>
				<div id="widget_error" class="text-danger mb-2"></div>
				<button type="submit" class="btn btn-warning btn-block w-100">Gửi</button>
			</form>
			<?php if(!$receiver_user_id): ?>
				<div class="text-center text-muted small mt-2">
					<i class="fa fa-info-circle me-1"></i>Hiện chưa có nhân viên trực tuyến, vui lòng thử lại sau.
				</div>
			<?php endif; ?>
		</footer>
	</div>

	<script>
		(function($){
			var loginUserId = parseInt($('#login_user_id').val(), 10);
			var receiverUserId = parseInt($('#receiver_user_id').val(), 10);
			var chatBasePath = '<?php echo $chat_base_path; ?>';

			var websocketUrl = '<?php echo $websocket_url; ?>';
			var conn = receiverUserId > 0 ? new WebSocket(websocketUrl) : null;
			var connReady = false;
			var sendQueue = [];
			var pendingAttachment = null;
			var uploading = false;
			var conversationId = parseInt($('#conversation_id').val(), 10) || null;
			var reconnectTimeout = null;

			function escapeHtml(text){
				return $('<div>').text(text || '').html();
			}

			function buildAttachmentHtml(attachment){
				if(!attachment || !attachment.path){
					return '';
				}

				var safePath = escapeHtml(attachment.path);
				var safeName = escapeHtml(attachment.name || 'Tệp đính kèm');

				if((attachment.mime || '').indexOf('image/') === 0){
					return "<div class='mt-2'><img src='"+safePath+"' alt='"+safeName+"' class='img-fluid rounded border'></div>";
				}

				return "<div class='mt-2'><a href='"+safePath+"' target='_blank' rel='noopener'>"+safeName+"</a></div>";
			}

			function removePending(tempId){
				if(!tempId){ return; }
				$('.pending-message[data-temp-id="'+tempId+'"]').remove();
			}

			function markPendingAsSent(tempId){
				if(!tempId){ return; }
				var $row = $('.pending-message[data-temp-id="'+tempId+'"]');
				if($row.length){
					$row.removeClass('pending-message');
					var nowText = new Date().toLocaleString();
					$row.find('small').text(nowText);
					$row.removeAttr('data-temp-id');
				}
			}

			function updateSupportTarget(userId){
				if(userId){
					receiverUserId = parseInt(userId, 10);
					$('#receiver_user_id').val(receiverUserId);
				}
			}

			function appendMessage(data, isPending){
				if(!data || data.status_type){
					return;
				}

				if(data.conversation_id){
					var incomingConversationId = parseInt(data.conversation_id, 10);
					if(conversationId && incomingConversationId !== conversationId){
						return;
					}
					if(!conversationId && incomingConversationId){
						conversationId = incomingConversationId;
						$('#conversation_id').val(conversationId);
					}
				}

				if(data.temp_id){
					removePending(data.temp_id);
				}

				var senderRaw = (data.userId !== undefined ? data.userId : (data.from_user_id !== undefined ? data.from_user_id : data.sender_id));
				var senderId = senderRaw === undefined || senderRaw === null || senderRaw === '' ? null : parseInt(senderRaw, 10);
				if(Number.isNaN(senderId)){ senderId = null; }
				var receiverId = parseInt(data.receiver_userid ?? data.to_user_id ?? receiverUserId, 10);
				var fromUserType = data.from_user_type || '';

				if(!data.conversation_id){
					if(
						(senderId !== null && senderId !== loginUserId && senderId !== receiverUserId) ||
						(receiverId !== loginUserId && receiverId !== receiverUserId)
					){
						return;
					}
				}

				var isSupport = fromUserType === 'employee' || (senderId !== null && senderId === receiverUserId);
				var isMe = data.from === 'Me' || data.from === 'me' || (senderId !== null && senderId === loginUserId);
				if(isSupport){
					isMe = false;
					if(senderId !== receiverUserId){
						updateSupportTarget(senderId);
					}
				}
				var displayName = isMe ? 'Bạn' : 'Nhà hàng';
				var messageText = (data.msg && data.msg.trim() !== '') ? escapeHtml(data.msg).replace(/\n/g, '<br>') : '<em>Đã gửi tệp đính kèm</em>';
				var attachmentHtml = buildAttachmentHtml(data.attachment);
				var pendingClass = isPending ? ' pending-message' : '';
				var tempAttr = data.temp_id ? " data-temp-id='"+data.temp_id+"'" : '';
				var timestamp = escapeHtml(data.dt || data.datetime || (isPending ? 'Đang gửi...' : ''));

				var html = "<div class='widget-message "+(isMe ? 'me' : 'other')+pendingClass+"'"+tempAttr+">" +
					"<div class='bubble'><b>"+displayName+":</b> "+messageText+attachmentHtml+
					"<small>"+timestamp+"</small></div></div>";

				$('#widget_messages').append(html);
				$('#widget_messages').scrollTop($('#widget_messages')[0].scrollHeight);
			}

			function flushQueue(){
				while(conn && connReady && sendQueue.length > 0){
					var payload = sendQueue.shift();
					conn.send(JSON.stringify(payload));
				}
			}

			function sendPayload(payload){
				if(!conn){
					return;
				}
				if(connReady && conn.readyState === WebSocket.OPEN){
					conn.send(JSON.stringify(payload));
				}else{
					sendQueue.push(payload);
				}
			}

			function attemptReconnect(){
				if(reconnectTimeout){
					return;
				}

				reconnectTimeout = setTimeout(function(){
					reconnectTimeout = null;
					conn = new WebSocket(websocketUrl);
					bindSocketHandlers();
				}, 2000);
			}

			function bindSocketHandlers(){
				if(!conn){ return; }
				conn.onopen = function(){
					connReady = true;
					flushQueue();
				};

				conn.onclose = function(){
					connReady = false;
					attemptReconnect();
				};

				conn.onerror = function(){
					connReady = false;
					attemptReconnect();
				};

				conn.onmessage = function(event){
					var data = JSON.parse(event.data);
					appendMessage(data, false);
				};
			}

			bindSocketHandlers();

			function resetAttachmentState(message){
				pendingAttachment = null;
				$('#widget_attachment').val('');
				$('#attachment_status').text(message || 'Chưa chọn tệp.');
			}

			function uploadAttachment(file){
				if(uploading){
					return;
				}

				uploading = true;
				$('#attachment_status').text('Đang tải lên...');

				var formData = new FormData();
				formData.append('attachment', file);

				$.ajax({
					url: chatBasePath + 'upload_attachment.php',
					method:"POST",
					data:formData,
					dataType:"json",
					processData:false,
					contentType:false,
					success:function(response){
						if(response.success){
							pendingAttachment = response.data;
							$('#attachment_status').text('Đã sẵn sàng: ' + response.data.name);
						}else{
							resetAttachmentState(response.message || 'Không thể tải tệp.');
						}
					},
					error:function(){
						resetAttachmentState('Không thể tải tệp. Vui lòng thử lại.');
					},
					complete:function(){
						uploading = false;
					}
				});
			}

			$('#widget_attachment').on('change', function(){
				var file = this.files[0];
				if(!file){
					resetAttachmentState();
					return;
				}
				uploadAttachment(file);
			});

			$('#widget_form').on('submit', function(e){
				e.preventDefault();

				var message = $('#widget_message').val();
				if($.trim(message) === '' && !pendingAttachment){
					$('#widget_error').text('Vui lòng nhập nội dung hoặc chọn tệp đính kèm.');
					return;
				}

				$('#widget_error').text('');

				var tempId = 'tmp_' + Date.now();

				if(receiverUserId <= 0){
					$('#widget_error').text('Chưa có nhân viên trực tuyến. Vui lòng thử lại sau.');
					return;
				}

				var payload = {
					command: 'private',
					userId: loginUserId,
					receiver_userid: receiverUserId,
					msg: message,
					temp_id: tempId
				};

				if(conversationId){
					payload.conversation_id = conversationId;
				}

				if(pendingAttachment){
					payload.attachment = pendingAttachment;
				}

				var preview = {
					from: 'Me',
					userId: loginUserId,
					msg: message,
					attachment: pendingAttachment,
					dt: new Date().toLocaleString(),
					temp_id: tempId,
					userId: loginUserId,
					receiver_userid: receiverUserId
				};

				appendMessage(preview, true);

				sendPayload(payload);
				$('#widget_message').val('');
				resetAttachmentState();
			});

			$('#widget_messages').scrollTop($('#widget_messages')[0].scrollHeight);
		})(jQuery);
	</script>
</body>
</html>
