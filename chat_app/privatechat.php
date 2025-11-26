<?php 

require_once __DIR__ . '/helpers/ChatIdentity.php';
require_once __DIR__ . '/config/chat.php';
require_once __DIR__ . '/database/ChatUser.php';

ChatIdentity::bootstrapSession();

$chatBootstrapError = '';
$currentChatUserId = null;
$chat_base_path = CHAT_APP_BASE_PATH;
$chat_base_url = CHAT_APP_BASE_URL;
$asset_base = htmlspecialchars($chat_base_path, ENT_QUOTES, 'UTF-8');

$identity = ChatIdentity::resolve();

if($identity)
{
	try {
		$identityUser = new ChatUser;
		$identityUser->setExternalId($identity['external_id'] ?? null);
		$identityUser->setUserType($identity['type']);
		$identityUser->setUserEmail($identity['email']);
		$identityUser->setUserName($identity['name']);
		$identityUser->setUserProfile($identity['avatar']);

		$user_id = $identityUser->sync_system_identity();
		$currentChatUserId = $user_id;

		$user_token = bin2hex(random_bytes(20));
		$identityUser->setUserId($user_id);
		$identityUser->setUserLoginStatus('Login');
		$identityUser->setUserToken($user_token);
		$identityUser->update_user_login_data();

		$_SESSION['user_data'][$user_id] = [
			'id' => $user_id,
			'name' => $identity['name'],
			'profile' => $identity['avatar'],
			'token' => $user_token,
			'type' => $identity['type'],
			'email' => $identity['email']
		];

		$_SESSION['active_chat_user_id'] = $user_id;
	} catch (\Throwable $e) {
		$chatBootstrapError = 'Không thể khởi tạo phiên chat: ' . $e->getMessage();
	}
}
else
{
	$chatBootstrapError = 'Vui lòng đăng nhập vào hệ thống trước khi mở cửa sổ chat.';
}

$session_users = [];
$active_session_user = null;
$login_user_id = null;
$token = '';
$active_user_name = '';
$active_user_profile = '';
$active_user_type = '';
$user_data = [];
$default_support_user = null;
$websocket_url = chat_build_websocket_url();
$is_customer_mode_request = isset($_GET['mode']) && $_GET['mode'] === 'customer';

if(isset($_SESSION['user_data']) && !empty($_SESSION['user_data']))
{
	if(isset($_SESSION['active_chat_user_id']) && isset($_SESSION['user_data'][$_SESSION['active_chat_user_id']]))
	{
		$active_session_user = $_SESSION['user_data'][$_SESSION['active_chat_user_id']];
	}
	else
	{
		$firstEntry = reset($_SESSION['user_data']);
		$active_session_user = $firstEntry !== false ? $firstEntry : null;
	}

	if(!$active_session_user && isset($currentChatUserId) && isset($_SESSION['user_data'][$currentChatUserId]))
	{
		$active_session_user = $_SESSION['user_data'][$currentChatUserId];
	}

	if(!$active_session_user)
	{
		$is_customer_mode = false;
		$chatBootstrapError = $chatBootstrapError ?: 'Không xác định được phiên chat hiện tại.';
	}

	if($active_session_user)
	{
		$login_user_id = $active_session_user['id'];
		$token = $active_session_user['token'];
		$active_user_name = $active_session_user['name'];
		$active_user_profile = $active_session_user['profile'];
		$active_user_type = $active_session_user['type'] ?? '';
		$websocket_url = chat_build_websocket_url($token);

		$is_customer_mode = $is_customer_mode_request && $active_user_type === 'customer';

		$user_object = new ChatUser;
		$user_object->setUserId($login_user_id);
		$user_data = $user_object->get_user_all_data_with_status_count();
		if(!$is_customer_mode)
		{
			$user_data = array_values(array_filter($user_data, function($user) use ($active_user_type){
				return $user['user_type'] !== $active_user_type;
			}));
		}
		else
		{
			$user_data = array_values(array_filter($user_data, function($user){
				return $user['user_type'] === 'employee';
			}));
			if(count($user_data) > 0) {
				$default_support_user = $user_data[0];
			}
		}
	}
}
else
{
	$is_customer_mode = false;
}

function build_unread_badge($count)
{
	if((int)$count > 0)
	{
		return '<span class="badge badge-danger badge-pill">'.$count.'</span>';
	}

	return '';
}

?>

<!DOCTYPE html>
<html>
<head>
	<title>Private Chat</title>
	<link href="<?php echo $asset_base; ?>vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $asset_base; ?>vendor-front/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
	<link rel="stylesheet" type="text/css" href="<?php echo $asset_base; ?>vendor-front/parsley/parsley.css"/>
    <script src="<?php echo $asset_base; ?>vendor-front/jquery/jquery.min.js"></script>
    <script src="<?php echo $asset_base; ?>vendor-front/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $asset_base; ?>vendor-front/jquery-easing/jquery.easing.min.js"></script>
    <script type="text/javascript" src="<?php echo $asset_base; ?>vendor-front/parsley/dist/parsley.min.js"></script>
	<style type="text/css">
		body {
			background-color: #f9fafc;
		}
		#user_list {
			height: 450px;
			overflow-y: auto;
		}
		#messages_area {
			height: 70vh;
			overflow-y: auto;
		}
	</style>
</head>
<body>
	<?php if(!$active_session_user): ?>
	<div class="container py-5">
		<div class="card shadow-sm">
			<div class="card-body text-center">
				<i class="fa fa-exclamation-triangle fa-2x mb-3 text-warning"></i>
				<p class="mb-1"><?php echo htmlspecialchars($chatBootstrapError ?: 'Không thể xác thực người dùng chat hiện tại.'); ?></p>
				<p class="text-muted mb-0">Vui lòng tải lại trang sau khi đăng nhập lại vào hệ thống.</p>
			</div>
		</div>
	</div>
	<?php else: ?>
	<div class="container-fluid">
		<div class="row">
			<div class="col-lg-3 col-md-4 col-sm-12" style="background-color: #f1f1f1; min-height: 100vh; border-right:1px solid #ccc;">
				<input type="hidden" name="login_user_id" id="login_user_id" value="<?php echo $login_user_id; ?>" />
				<input type="hidden" id="conversation_id" value="" />
				<input type="hidden" name="is_active_chat" id="is_active_chat" value="No" />
				<?php if($is_customer_mode && $default_support_user): ?>
					<input type="hidden" id="default_support_user_id" value="<?php echo $default_support_user['user_id']; ?>" data-name="<?php echo htmlspecialchars($default_support_user['user_name']); ?>">
				<?php endif; ?>
				<div class="mt-3 mb-3 text-center">
					<img src="<?php echo htmlspecialchars($active_user_profile); ?>" class="img-fluid rounded-circle img-thumbnail" width="140" />
					<h4 class="mt-2"><?php echo htmlspecialchars($active_user_name); ?></h4>
					<p class="text-muted mb-1"><?php echo $active_user_type === 'employee' ? 'Nhân viên' : 'Khách hàng'; ?></p>
					<input type="button" class="btn btn-primary mt-2 mb-2" id="logout" name="logout" value="ThoÃ¡t chat" />
				</div>
				<?php if($is_customer_mode): ?>
					<div class="alert alert-info text-center mx-3">
						Bạn đang chat với đội ngũ hỗ trợ nhà hàng.
					</div>
				<?php endif; ?>
				<div class="list-group <?php echo $is_customer_mode ? 'd-none' : ''; ?>" id="user_list">
					<?php foreach($user_data as $user): ?>
						<?php if($user['user_id'] == $login_user_id) { continue; } ?>
						<?php
							$icon = $user['user_login_status'] == 'Login' ? '<i class="fa fa-circle text-success"></i>' : '<i class="fa fa-circle text-danger"></i>';
							$total_unread_message = build_unread_badge($user['count_status']);
						?>
						<a class="list-group-item list-group-item-action select_user" id="select_user_<?php echo $user['user_id']; ?>" style="cursor:pointer" data-userid="<?php echo $user['user_id']; ?>">
							<div class="d-flex align-items-center">
								<img src="<?php echo htmlspecialchars($user["user_profile"]); ?>" class="img-fluid rounded-circle img-thumbnail" width="50" />
								<div class="ml-2 flex-grow-1">
									<strong id="list_user_name_<?php echo $user["user_id"]; ?>"><?php echo htmlspecialchars($user['user_name']); ?></strong>
									<span class="badge badge-light ml-1"><?php echo $user['user_type'] === 'employee' ? 'Nhân viên' : 'Khách hàng'; ?></span>
									<div id="userid_<?php echo $user['user_id']; ?>"><?php echo $total_unread_message; ?></div>
								</div>
								<span class="mt-2" id="userstatus_<?php echo $user['user_id']; ?>"><?php echo $icon; ?></span>
							</div>
						</a>
					<?php endforeach; ?>
				</div>
				<?php if($is_customer_mode && !$default_support_user): ?>
					<div class="card mt-3">
						<div class="card-body text-center text-muted">
							Hiện chưa có nhân viên nào sẵn sàng chat. Vui lòng thử lại sau.
						</div>
					</div>
				<?php endif; ?>
			</div>
			<div class="col-lg-9 col-md-8 col-sm-12">
				<div class="py-3 text-center">
					<h3>Chat riÃªng thá»i gian thá»±c</h3>
					<p class="text-muted mb-0">
						<?php if($is_customer_mode): ?>
							Bạn đang trò chuyện với nhà hàng.
						<?php else: ?>
							Chọn một người ở danh sách bên trái để bắt đầu trò chuyện.
						<?php endif; ?>
					</p>
				</div>
				<div id="chat_area">
					<div class="card">
						<div class="card-body text-center text-muted">
							<i class="fa fa-comments fa-2x mb-3"></i>
							<p>Chưa có cuộc trò chuyện nào được chọn.</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php endif; ?>
</body>
<?php if($active_session_user): ?>
<script type="text/javascript">
(function($){
	var chatBasePath = '<?php echo $chat_base_path; ?>';
	var chatBaseUrl = '<?php echo $chat_base_url; ?>';
	var receiver_userid = '';
	var conn = new WebSocket('<?php echo $websocket_url; ?>');
	var pendingPrivateAttachment = null;
	var uploadingPrivateAttachment = false;
	var customerMode = <?php echo $is_customer_mode ? 'true' : 'false'; ?>;
	var conversationField = $('#conversation_id');
	var activeConversationId = conversationField.length && conversationField.val() ? parseInt(conversationField.val(), 10) : null;

	function setActiveConversation(id)
	{
		if(id)
		{
			activeConversationId = parseInt(id, 10);
			if(conversationField.length)
			{
				conversationField.val(activeConversationId);
			}
		}
		else
		{
			activeConversationId = null;
			if(conversationField.length)
			{
				conversationField.val('');
			}
		}
	}
	// Render nội dung đính kèm (ảnh/tệp) trong khung chat.
	function buildAttachmentHtml(attachment)
	{
		if(!attachment || !attachment.path)
		{
			return '';
		}

		var mime = attachment.mime || '';
		var label = attachment.name || 'Tá»‡p';

		if(mime.indexOf('image/') === 0)
		{
			return "<div class='mt-2'><img src='"+attachment.path+"' alt='"+label+"' class='img-fluid rounded border'></div>";
		}

		return "<div class='mt-2'><a href='"+attachment.path+"' target='_blank' rel='noopener'>"+label+"</a></div>";
	}
	// Dọn trạng thái upload sau khi gửi hoặc khi người dùng đổi tệp.
	function resetPrivateAttachment(message)
	{
		pendingPrivateAttachment = null;
		$('#private_chat_attachment').val('');
		$('#private_attachment_details').val('');
		$('#private_attachment_status').text(message || 'Chưa chọn tệp.');
	}

	// Äáº©y tá»‡p lÃªn server trÆ°á»›c khi gá»­i qua websocket.
	function uploadPrivateAttachment(file)
	{
		if(uploadingPrivateAttachment)
		{
			return;
		}

		uploadingPrivateAttachment = true;
		$('#private_attachment_status').text('Đang tải tệp lên...');

		var formData = new FormData();
		formData.append('attachment', file);

		$.ajax({
			url: chatBasePath + 'upload_attachment.php',
			method:"POST",
			data:formData,
			dataType:"json",
			processData:false,
			contentType:false,
			success:function(response)
			{
				if(response.success)
				{
					pendingPrivateAttachment = response.data;
					$('#private_attachment_details').val(JSON.stringify(response.data));
					$('#private_attachment_status').text('Đã sẵn sàng: ' + response.data.name);
				}
				else
				{
					resetPrivateAttachment(response.message || 'Không thể tải tệp.');
				}
			},
			error:function()
			{
				resetPrivateAttachment('Không thể tải tệp. Vui lòng thử lại.');
			},
			complete:function()
			{
				uploadingPrivateAttachment = false;
			}
		});
	}

	function make_chat_area(user_name)
	{
		var html = `
		<div class="card">
			<div class="card-header">
				<div class="row">
					<div class="col-sm-6">
						<b>Đang chat với <span class="text-danger" id="chat_user_name">`+user_name+`</span></b>
					</div>
					<div class="col-sm-6 text-right">
						<a href="<?php echo $asset_base; ?>chatroom.php" class="btn btn-success btn-sm">Phòng chung</a>
						<button type="button" class="close" id="close_chat_area" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
				</div>
			</div>
			<div class="card-body" id="messages_area"></div>
		</div>
		<form id="chat_form" method="POST" data-parsley-errors-container="#validation_error">
			<div class="input-group mb-2">
				<textarea class="form-control" id="chat_message" name="chat_message" placeholder="Nhập nội dung" data-parsley-maxlength="1000"></textarea>
				<div class="input-group-append">
					<button type="submit" name="send" id="send" class="btn btn-primary"><i class="fa fa-paper-plane"></i></button>
				</div>
			</div>
			<div class="mb-2">
				<label class="small font-weight-bold mb-1">Đính kèm (tối đa 10MB)</label>
				<input type="file" class="form-control-file" id="private_chat_attachment" accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/x-rar-compressed,text/plain">
				<small id="private_attachment_status" class="form-text text-muted">Chưa chọn tệp.</small>
				<input type="hidden" id="private_attachment_details" value="">
			</div>
			<div id="validation_error"></div>
		</form>
		`;

		$('#chat_area').html(html);
		$('#chat_form').parsley();
		resetPrivateAttachment();
	}

	conn.onopen = function()
	{
		console.log('Connection Established');
	};

	conn.onmessage = function(event)
	{
		var data = JSON.parse(event.data);

		if(data.status_type === 'Online')
		{
			$('#userstatus_'+data.user_id_status).html('<i class="fa fa-circle text-success"></i>');
			return;
		}

		if(data.status_type === 'Offline')
		{
			$('#userstatus_'+data.user_id_status).html('<i class="fa fa-circle text-danger"></i>');
			return;
		}

		var messageConversationId = data.conversation_id ? parseInt(data.conversation_id, 10) : null;
		var isActiveChat = $('#is_active_chat').val() === 'Yes';

		if(isActiveChat && !activeConversationId && messageConversationId)
		{
			setActiveConversation(messageConversationId);
		}

		var conversationMatches = true;
		if(messageConversationId)
		{
			if(!activeConversationId || messageConversationId !== activeConversationId)
			{
				conversationMatches = false;
			}
		}
		else if(!(receiver_userid == data.userId || data.from === 'Me'))
		{
			conversationMatches = false;
		}

		if(isActiveChat && conversationMatches)
		{
			var isEmployeeMessage = (data.from_user_type === 'employee') || (data.from === 'Me');
			var row_class = isEmployeeMessage ? 'row justify-content-end' : 'row justify-content-start';
			var background_class = isEmployeeMessage ? 'alert-primary' : 'alert-success';
			var messageText = (data.msg && data.msg.trim() !== '') ? data.msg : '<em>Đã gửi tập tin đính kèm</em>';
			var attachmentHtml = buildAttachmentHtml(data.attachment);

			var html_data = `
			<div class="`+row_class+`">
				<div class="col-sm-10">
					<div class="shadow alert `+background_class+`">
						<b>`+(data.from || 'Hệ thống')+` - </b>
						`+messageText+`
						`+attachmentHtml+`
						<div class="text-right">
							<small><i>`+data.datetime+`</i></small>
						</div>
					</div>
				</div>
			</div>
			`;

			$('#messages_area').append(html_data);
			$('#messages_area').scrollTop($('#messages_area')[0].scrollHeight);
			$('#chat_message').val('');
			resetPrivateAttachment();
		}
		else
		{
			var targetUserId;
			if(customerMode)
			{
				targetUserId = data.userId;
			}
			else
			{
				targetUserId = data.conversation_customer_id || data.userId;
			}

			if(targetUserId)
			{
				var badgeNode = $('#userid_'+targetUserId);
				if(badgeNode.length)
				{
					var count_chat = badgeNode.text();
					var currentCount = parseInt(count_chat) || 0;
					currentCount++;
					badgeNode.html('<span class="badge badge-danger badge-pill">'+currentCount+'</span>');
				}
			}
		}
	};

	conn.onclose = function()
	{
		console.log('connection close');
	};

		$(document).on('click', '.select_user', function(){
		receiver_userid = $(this).data('userid');
		var from_user_id = $('#login_user_id').val();
		var receiver_user_name = $('#list_user_name_'+receiver_userid).text();
		setActiveConversation(null);

		$('.select_user.active').removeClass('active');
		$(this).addClass('active');

		make_chat_area(receiver_user_name);

		$('#is_active_chat').val('Yes');

		$.ajax({
			url: chatBasePath + 'action.php',
			method:"POST",
			data:{action:'fetch_chat', to_user_id:receiver_userid, from_user_id:from_user_id},
			dataType:"JSON",
			success:function(response)
			{
				var messages = (response && response.messages) ? response.messages : [];
				if(response && response.conversation_id)
				{
					setActiveConversation(response.conversation_id);
				}

				var html_data = '';

				for(var count = 0; count < messages.length; count++)
				{
					var message = messages[count];
					var isEmployee = (message.from_user_type === 'employee');
					var row_class = isEmployee ? 'row justify-content-end' : 'row justify-content-start';
					var background_class = isEmployee ? 'alert-primary' : 'alert-success';
					var user_name = (message.from_user_id == from_user_id) ? 'Me' : message.from_user_name;
					var messageText = (message.chat_message && message.chat_message.trim() !== '') ? message.chat_message : '<em>Đã gửi tập tin đính kèm</em>';
					var attachmentHtml = buildAttachmentHtml({
						path: message.attachment_path,
						name: message.attachment_name,
						mime: message.attachment_mime
					});

					html_data += `
					<div class="`+row_class+`">
						<div class="col-sm-10">
							<div class="shadow alert `+background_class+`">
								<b>`+user_name+` - </b>
								`+messageText+`
								`+attachmentHtml+`
								<div class="text-right">
									<small><i>`+message.timestamp+`</i></small>
								</div>
							</div>
						</div>
					</div>
					`;
				}

				$('#userid_'+receiver_userid).html('');

				if(html_data === '')
				{
					html_data = '<div class="text-center text-muted py-3"><em>Chưa có tin nhắn nào.</em></div>';
				}

				$('#messages_area').html(html_data);

				$('#messages_area').scrollTop($('#messages_area')[0].scrollHeight);
			}
		})

	});

	$(document).on('click', '#close_chat_area', function(){
		$('#chat_area').html('<div class="card"><div class="card-body text-center text-muted"><i class="fa fa-comments fa-2x mb-3"></i><p>Chưa có cuộc trò chuyện nào được chọn.</p></div></div>');
		$('.select_user.active').removeClass('active');
		$('#is_active_chat').val('No');
		receiver_userid = '';
		setActiveConversation(null);
	});

	$(document).on('submit', '#chat_form', function(event){
		event.preventDefault();

		var user_id = parseInt($('#login_user_id').val());
		var message = $('#chat_message').val();

		if($.trim(message) === '' && !pendingPrivateAttachment)
		{
			$('#validation_error').html('<div class="text-danger">Vui lòng nhập nội dung hoặc gửi file</div>');
			return;
		}

		$('#validation_error').html('');

		var data = {
			userId: user_id,
			msg: message,
			receiver_userid: receiver_userid,
			command:'private',
			attachment: pendingPrivateAttachment
		};

		if(activeConversationId)
		{
			data.conversation_id = activeConversationId;
		}

		conn.send(JSON.stringify(data));
		$('#chat_message').val('');
		resetPrivateAttachment();
	});

	$(document).on('change', '#private_chat_attachment', function(){
		var file = this.files[0];
		if(!file)
		{
			resetPrivateAttachment('Chưa chọn tệp.');
			return;
		}

		uploadPrivateAttachment(file);
	});

	$('#logout').click(function(){

		var user_id = $('#login_user_id').val();

	$.ajax({
			url: chatBasePath + 'action.php',
			method:"POST",
			data:{user_id:user_id, action:'leave'},
			success:function(data)
			{
				var response = JSON.parse(data);
				if(response.status == 1)
				{
					if(conn)
					{
						conn.close();
					}
					location = chatBasePath + 'index.php';
				}
			}
		})

	});

	var defaultSupportId = $('#default_support_user_id').val();
	if(customerMode){
		if(defaultSupportId){
			setTimeout(function(){
				$('#select_user_'+defaultSupportId).trigger('click');
			}, 300);
		}else{
			$('#chat_area').html('<div class="card"><div class="card-body text-center text-muted"><i class="fa fa-info-circle fa-2x mb-3"></i><p>Hiện chưa có nhân viên hỗ trợ trực tuyến.</p></div></div>');
		}
	}

})(jQuery);
</script>
<?php endif; ?>
</html>







