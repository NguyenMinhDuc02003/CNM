<?php

require_once __DIR__ . '/config/chat.php';
require_once __DIR__ . '/config/bot.php';
require_once __DIR__ . '/helpers/ChatIdentity.php';

ChatIdentity::bootstrapSession();

$chat_base_path = CHAT_APP_BASE_PATH;
$asset_base = htmlspecialchars($chat_base_path, ENT_QUOTES, 'UTF-8');
$bot_test_token = defined('BOT_TEST_TOKEN') ? BOT_TEST_TOKEN : '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<title>Chatbot CNM</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link href="<?php echo $asset_base; ?>vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">
	<link href="<?php echo $asset_base; ?>vendor-front/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
	<script src="<?php echo $asset_base; ?>vendor-front/jquery/jquery.min.js"></script>
	<script src="<?php echo $asset_base; ?>vendor-front/bootstrap/js/bootstrap.bundle.min.js"></script>
	<style>
		body {
			font-family: "Helvetica Neue", Arial, sans-serif;
			background: #f4f6fb;
			margin: 0;
			padding: 0;
			color: #1f2d3d;
		}
		.bot-shell {
			height: 100vh;
			display: flex;
			flex-direction: column;
		}
		.bot-header {
			background: linear-gradient(135deg, #0d6efd, #5c9dff);
			color: #fff;
			padding: 14px 18px;
			display: flex;
			align-items: center;
			gap: 12px;
			box-shadow: 0 4px 12px rgba(0,0,0,0.18);
		}
		.bot-header .icon {
			width: 42px;
			height: 42px;
			border-radius: 12px;
			background: rgba(255,255,255,0.18);
			display: grid;
			place-items: center;
			font-size: 20px;
		}
		.bot-body {
			flex: 1;
			padding: 16px;
			overflow-y: auto;
			background: #fff;
		}
		.bot-footer {
			padding: 12px 16px 18px;
			background: #f8f9fb;
			border-top: 1px solid #e6ebf1;
		}
		.message-row {
			margin-bottom: 14px;
			display: flex;
		}
		.message-row.me {
			justify-content: flex-end;
		}
		.message-bubble {
			max-width: 90%;
			padding: 10px 12px;
			border-radius: 14px;
			box-shadow: 0 6px 14px rgba(0,0,0,0.06);
			font-size: 14px;
			line-height: 1.5;
		}
		.message-row.me .message-bubble {
			background: #e6f1ff;
			color: #0b3b73;
			border-bottom-right-radius: 4px;
		}
		.message-row.bot .message-bubble {
			background: #f1f3f6;
			color: #1f2d3d;
			border-bottom-left-radius: 4px;
		}
		.message-meta {
			display: block;
			margin-top: 6px;
			font-size: 11px;
			color: #667;
		}
		.bot-placeholder {
			text-align: center;
			color: #8a94a6;
			padding: 28px 8px;
		}
	</style>
</head>
<body>
	<div class="bot-shell">
		<header class="bot-header">
			<div class="icon"><i class="fas fa-robot"></i></div>
			<div>
				<div style="font-weight:600;font-size:16px;">Chatbot CNM</div>
				<small>Trò chuyện nhanh, không cần đăng nhập</small>
			</div>
		</header>

		<div id="bot_messages" class="bot-body">
			<div class="bot-placeholder">
				<p class="mb-1">Xin chào! Tôi là chatbot của CNM.</p>
				<small>Hỏi tôi về thực đơn, giờ mở cửa, hoặc hướng dẫn đặt bàn.</small>
			</div>
		</div>

		<footer class="bot-footer">
			<form id="bot_form" class="needs-validation" novalidate>
				<div class="form-group mb-2">
					<textarea id="bot_message" class="form-control" rows="2" placeholder="Nhập câu hỏi của bạn..." required data-parsley-maxlength="1000"></textarea>
				</div>
				<div id="bot_error" class="text-danger small mb-2"></div>
				<button type="submit" class="btn btn-primary w-100">Gửi</button>
			</form>
		</footer>
	</div>

	<script>
		(function($){
			var botHistory = [];
			var chatBasePath = '<?php echo $chat_base_path; ?>';
			var botTestToken = '<?php echo htmlspecialchars($bot_test_token, ENT_QUOTES, 'UTF-8'); ?>';

			function escapeHtml(text){
				return $('<div>').text(text || '').html();
			}

			function appendMessage(row, isMine){
				var cls = isMine ? 'me' : 'bot';
				var bubble = $('<div>', { 'class': 'message-row ' + cls });
				var inner = $('<div>', { 'class': 'message-bubble', html: escapeHtml(row.msg || '') });
				var meta = $('<small>', { 'class': 'message-meta', text: row.dt || '' });
				inner.append(meta);
				bubble.append(inner);
				$('#bot_messages').append(bubble);
				$('#bot_messages').scrollTop($('#bot_messages')[0].scrollHeight);
			}

			function showError(msg){
				$('#bot_error').text(msg || '');
			}

			$('#bot_form').on('submit', function(e){
				e.preventDefault();

				var message = $('#bot_message').val();
				if($.trim(message) === ''){
					showError('Vui lòng nhập nội dung.');
					return;
				}

				showError('');
				var tempId = 'tmp_' + Date.now();
				var now = new Date().toLocaleString();

				appendMessage({ msg: message, dt: now, temp_id: tempId }, true);
				botHistory.push({ role: 'user', content: message });

				var botUrl = chatBasePath + 'bot_reply.php';
				if(botTestToken){
					botUrl += (botUrl.indexOf('?') === -1 ? '?' : '&') + 'test_token=' + encodeURIComponent(botTestToken);
				}

				$.ajax({
					url: botUrl,
					method: 'POST',
					dataType: 'json',
					data: { message: message, history: botHistory }
				})
				.done(function(res){
					var reply = (res && res.success) ? (res.reply || 'Chatbot không có phản hồi.') : (res && res.message ? res.message : 'Không thể gọi chatbot.');
					botHistory.push({ role: 'assistant', content: reply });
					appendMessage({ msg: reply, dt: new Date().toLocaleString() }, false);
				})
				.fail(function(xhr){
					var errMsg = 'Lỗi kết nối chatbot, vui lòng thử lại.';
					if(xhr && xhr.responseText){
						try {
							var parsed = JSON.parse(xhr.responseText);
							if(parsed && parsed.message){
								errMsg = parsed.message;
							}
						}catch(e){}
					}
					appendMessage({ msg: errMsg, dt: new Date().toLocaleString() }, false);
				});

				$('#bot_message').val('');
			});
		})(jQuery);
	</script>
</body>
</html>
