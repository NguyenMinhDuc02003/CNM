<?php 
if (isset($_GET['page']) && $_GET['page'] == 'chat') {
    $page = 1;
} else {
    $page = 0;
}
require_once __DIR__ . '/helpers/ChatIdentity.php';
require_once __DIR__ . '/config/chat.php';
require_once __DIR__ . '/database/ChatUser.php';
require_once __DIR__ . '/database/ChatRooms.php';

ChatIdentity::bootstrapSession();

if(!isset($_SESSION['user_data']) || empty($_SESSION['user_data']))
{
	$identity = ChatIdentity::resolve();
	if(!$identity)
	{
		echo '<div style="padding:20px;font-family:Arial,sans-serif;">Vui lòng đăng nhập hệ thống trước khi mở trang chat.</div>';
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
		'id'    => $user_id,
		'name'  => $identity['name'],
		'profile' => $identity['avatar'],
		'token' => $user_token,
		'type'  => $identity['type'],
		'email' => $identity['email']
	];
}

// Hiển thị phần đính kèm của tin nhắn theo định dạng ảnh/đường dẫn tải về.
function render_chat_attachment($path, $name, $mime)
{
	if(empty($path))
	{
		return '';
	}

	$safe_path = htmlspecialchars($path);
	$label = htmlspecialchars($name !== '' ? $name : basename($path));
	$is_image = is_string($mime) && strpos($mime, 'image/') === 0;

	if($is_image)
	{
		return '<div class="mt-2"><img src="'.$safe_path.'" alt="'.$label.'" class="img-fluid rounded border"></div>';
	}

	return '<div class="mt-2"><a href="'.$safe_path.'" target="_blank" rel="noopener">'. $label .'</a></div>';
}

$chat_object = new ChatRooms;

$chat_data = $chat_object->get_all_chat_data();

$user_object = new ChatUser;

$user_data = $user_object->get_user_all_data();

$session_users = array_values($_SESSION['user_data']);
$active_session_user = $session_users[0];
$login_user_id = $active_session_user['id'];
$token = $active_session_user['token'];
$active_user_name = $active_session_user['name'];
$active_user_profile = $active_session_user['profile'];
$active_user_type = $active_session_user['type'] ?? '';
$websocket_url = chat_build_websocket_url($token);

?>

<!DOCTYPE html>
<html>
<head>
	<title>Chat application in php using web scocket programming</title>
	<!-- Bootstrap core CSS -->
    <link href="vendor-front/bootstrap/bootstrap.min.css" rel="stylesheet">

    <link href="vendor-front/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

    <link rel="stylesheet" type="text/css" href="vendor-front/parsley/parsley.css"/>

    <!-- Bootstrap core JavaScript -->
    <script src="vendor-front/jquery/jquery.min.js"></script>
    <script src="vendor-front/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="vendor-front/jquery-easing/jquery.easing.min.js"></script>

    <script type="text/javascript" src="vendor-front/parsley/dist/parsley.min.js"></script>
	<style type="text/css">
		html,
		body {
		  height: 100%;
		  width: 100%;
		  margin: 0;
		}
		#wrapper
		{
			display: flex;
		  	flex-flow: column;
		  	height: 100%;
		}
		#remaining
		{
			flex-grow : 1;
		}
		#messages {
			height: 200px;
			background: whitesmoke;
			overflow: auto;
		}
		#chat-room-frm {
			margin-top: 10px;
		}
		#user_list
		{
			height:450px;
			overflow-y: auto;
		}

		#messages_area
		{
			height: 650px;
			overflow-y: auto;
			background-color:#e6e6e6;
		}

	</style>
</head>
<body>
	<div class="container">
		<br />
        <h3 class="text-center">Realtime One to One Chat App using Ratchet WebSockets with PHP Mysql - Online Offline Status - 8</h3>
        <br />
		<div class="row">
			
			<div class="col-lg-8">
				<div class="card">
					<div class="card-header">
						<div class="row">
							<div class="col col-sm-6">
								<h3>Chat Room</h3>
							</div>
						</div>
					</div>
					<div class="card-body" id="messages_area">
					<?php
					foreach($chat_data as $chat)
					{
						if(isset($_SESSION['user_data'][$chat['userid']]))
						{
							$from = 'Me';
							$row_class = 'row justify-content-end';
							$background_class = 'alert-success text-dark';
						}
						else
						{
							$from = $chat['user_name'];
							$row_class = 'row justify-content-start';
							$background_class = 'text-dark alert-light';
						}

						$attachment_html = render_chat_attachment($chat['attachment_path'] ?? '', $chat['attachment_name'] ?? '', $chat['attachment_mime'] ?? '');
						$message_content = trim($chat["msg"]) !== '' ? $chat["msg"] : '<em>Đã gửi tệp đính kèm</em>';

						echo '
						<div class="'.$row_class.'">
							<div class="col-sm-10">
								<div class="shadow-sm alert '.$background_class.'">
									<b>'.$from.' - </b>'.$message_content.'
									'.$attachment_html.'
									<br />
									<div class="text-right">
										<small><i>'.$chat["created_on"].'</i></small>
									</div>
								</div>
							</div>
						</div>
						';
					}
					?>
					</div>
				</div>

				<form method="post" id="chat_form" data-parsley-errors-container="#validation_error">
					<div class="input-group mb-3">
						<textarea class="form-control" id="chat_message" name="chat_message" placeholder="Nhập nội dung cần gửi" data-parsley-maxlength="1000"></textarea>
						<div class="input-group-append">
							<button type="submit" name="send" id="send" class="btn btn-primary"><i class="fa fa-paper-plane"></i></button>
						</div>
					</div>
					<div class="mb-2">
						<label class="small font-weight-bold mb-1">Đính kèm ảnh hoặc tệp (tối đa 10MB)</label>
						<input type="file" class="form-control-file" id="chat_attachment" accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip,application/x-rar-compressed,text/plain">
						<small id="attachment_status" class="form-text text-muted">Chưa chọn tệp.</small>
						<input type="hidden" id="attachment_details" value="">
					</div>
					<div id="validation_error"></div>
				</form>
			</div>
			<div class="col-lg-4">
				<input type="hidden" name="login_user_id" id="login_user_id" value="<?php echo $login_user_id; ?>" />
				<div class="mt-3 mb-3 text-center">
					<img src="<?php echo htmlspecialchars($active_user_profile); ?>" width="150" class="img-fluid rounded-circle img-thumbnail" />
					<h3 class="mt-2"><?php echo htmlspecialchars($active_user_name); ?></h3>
					<p class="text-muted mb-1"><?php echo $active_user_type === 'employee' ? 'Nhân viên' : 'Khách hàng'; ?></p>
					<input type="button" class="btn btn-primary mt-2 mb-2" name="logout" id="logout" value="Thoát chat" />
				</div>

				<div class="card mt-3">
					<div class="card-header">User List</div>
					<div class="card-body" id="user_list">
						<div class="list-group list-group-flush">
						<?php
						if(count($user_data) > 0)
						{
							foreach($user_data as $key => $user)
							{
								$icon = '<i class="fa fa-circle text-danger"></i>';

								if($user['user_login_status'] == 'Login')
								{
									$icon = '<i class="fa fa-circle text-success"></i>';
								}

								if($user['user_id'] != $login_user_id)
								{
									echo '
									<a class="list-group-item list-group-item-action">
										<img src="'.$user["user_profile"].'" class="img-fluid rounded-circle img-thumbnail" width="50" />
										<span class="ml-1"><strong>'.$user["user_name"].'</strong></span>
										<span class="badge badge-light ml-2">'.($user["user_type"] === 'employee' ? 'Nhân viên' : 'Khách hàng').'</span>
										<span class="mt-2 float-right">'.$icon.'</span>
									</a>
									';
								}

							}
						}
						?>
						</div>
					</div>
				</div>

			</div>
		</div>
	</div>
</body>
<script type="text/javascript">
(function($){
	var conn = null;
	var connReady = false;
	var sendQueue = [];
	var reconnectTimer = null;
	var pendingAttachment = null;
	var uploadingAttachment = false;

	// Render phần đính kèm trên giao diện realtime.
	function buildAttachmentHtml(attachment)
	{
		if(!attachment || !attachment.path)
		{
			return '';
		}

		var mime = attachment.mime || '';
		var label = attachment.name || 'Tệp đính kèm';

		if(mime.indexOf('image/') === 0)
		{
			return "<div class='mt-2'><img src='"+attachment.path+"' alt='"+label+"' class='img-fluid rounded border'></div>";
		}

		return "<div class='mt-2'><a href='"+attachment.path+"' target='_blank' rel='noopener'>"+label+"</a></div>";
	}

	// Dọn trạng thái input sau khi gửi/tải tệp.
	function resetAttachmentState(message)
	{
		pendingAttachment = null;
		$('#chat_attachment').val('');
		$('#attachment_details').val('');
		$('#attachment_status').text(message || 'Chưa chọn tệp.');
	}

	// Gửi tệp lên máy chủ trước khi nhắn qua websocket.
	function uploadAttachment(file)
	{
		if(uploadingAttachment)
		{
			return;
		}

		var formData = new FormData();
		formData.append('attachment', file);

		uploadingAttachment = true;
		$('#attachment_status').text('Đang tải tệp lên...');

		$.ajax({
			url:"upload_attachment.php",
			method:"POST",
			data:formData,
			dataType:"json",
			processData:false,
			contentType:false,
			success:function(response)
			{
				if(response.success)
				{
					pendingAttachment = response.data;
					$('#attachment_details').val(JSON.stringify(response.data));
					$('#attachment_status').text('Đã sẵn sàng: ' + response.data.name);
				}
				else
				{
					resetAttachmentState(response.message || 'Không thể tải tệp.');
				}
			},
			error:function()
			{
				resetAttachmentState('Không thể tải tệp. Vui lòng thử lại.');
			},
			complete:function()
			{
				uploadingAttachment = false;
			}
		});
	}

	function flushQueue()
	{
		while(connReady && sendQueue.length > 0)
		{
			conn.send(JSON.stringify(sendQueue.shift()));
		}
	}

	function removePending(tempId)
	{
		if(!tempId){ return; }
		$('#messages_area .pending-message[data-temp-id="'+tempId+'"]').remove();
	}

	function sendPayload(payload)
	{
		if(connReady && conn && conn.readyState === WebSocket.OPEN)
		{
			conn.send(JSON.stringify(payload));
		}
		else
		{
			sendQueue.push(payload);
		}
	}

	function scheduleReconnect()
	{
		if(reconnectTimer)
		{
			return;
		}

		reconnectTimer = setTimeout(function(){
			reconnectTimer = null;
			conn = new WebSocket('<?php echo $websocket_url; ?>');
			bindSocketEvents();
		}, 2000);
	}

	function bindSocketEvents()
	{
		conn.onopen = function() {
			connReady = true;
			flushQueue();
		};

		conn.onclose = function() {
			connReady = false;
			scheduleReconnect();
		};

		conn.onerror = function() {
			connReady = false;
			scheduleReconnect();
		};

		conn.onmessage = function(e) {
			var data = JSON.parse(e.data);

			if(data.status_type){
				return;
			}

			removePending(data.temp_id);

			var row_class = (data.from === 'Me') ? 'row justify-content-end' : 'row justify-content-start';
			var background_class = (data.from === 'Me') ? 'alert-primary text-white' : 'text-dark alert-light';
			var messageText = (data.msg && data.msg.trim() !== '') ? data.msg : '<em>Đã gửi tệp đính kèm</em>';
			var attachmentHtml = buildAttachmentHtml(data.attachment);

			var html_data = "<div class='"+row_class+"'><div class='col-sm-10'><div class='shadow-sm alert "+background_class+"'><b>"+data.from+" - </b>"+messageText+attachmentHtml+"<br /><div class='text-right'><small><i>"+(data.dt || data.datetime || '')+"</i></small></div></div></div></div>";

			$('#messages_area').append(html_data);

			$("#chat_message").val("");
			resetAttachmentState();
		};
	}

	$(document).ready(function(){

		conn = new WebSocket('<?php echo $websocket_url; ?>');
		bindSocketEvents();

		$('#chat_form').parsley();

		$('#messages_area').scrollTop($('#messages_area')[0].scrollHeight);

		$('#chat_form').on('submit', function(event){

			event.preventDefault();

			var user_id = $('#login_user_id').val();
			var message = $('#chat_message').val();

			if($.trim(message) === '' && !pendingAttachment)
			{
				$('#validation_error').html('<div class="text-danger">Vui lòng nhập nội dung hoặc chọn tệp đính kèm.</div>');
				return;
			}

			$('#validation_error').html('');

			var tempId = 'tmp_' + Date.now();
			var data = {
				userId : user_id,
				msg : message,
				attachment : pendingAttachment,
				temp_id : tempId
			};

			var previewRow = "<div class='row justify-content-end pending-message' data-temp-id='"+tempId+"'><div class='col-sm-10'><div class='shadow-sm alert alert-primary text-white'><b>Me - </b>"+(message || '<em>Đang gửi...</em>')+buildAttachmentHtml(pendingAttachment)+"<br /><div class='text-right'><small><i>Đang gửi...</i></small></div></div></div></div>";
			$('#messages_area').append(previewRow);

			sendPayload(data);

			$('#messages_area').scrollTop($('#messages_area')[0].scrollHeight);
			$('#chat_message').val('');
			resetAttachmentState();
		});

		$('#chat_attachment').on('change', function(){
			var file = this.files[0];
			if(!file)
			{
				resetAttachmentState('Chưa chọn tệp.');
				return;
			}
			uploadAttachment(file);
		});
		
		$('#logout').click(function(){

			var user_id = $('#login_user_id').val();

			$.ajax({
				url:"action.php",
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
						location = 'index.php';
					}
				}
			})

		});

	});

})(jQuery);
</script>
</html>
