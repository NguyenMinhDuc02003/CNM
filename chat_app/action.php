<?php

//action.php

session_start();

if(isset($_POST['action']) && $_POST['action'] == 'leave')
{
	require('database/ChatUser.php');

	$user_object = new ChatUser;

	$user_object->setUserId($_POST['user_id']);

	$user_object->setUserLoginStatus('Logout');

	$user_object->setUserToken(null);

	if($user_object->update_user_login_data())
	{
		if(isset($_SESSION['user_data'][$_POST['user_id']]))
		{
			unset($_SESSION['user_data'][$_POST['user_id']]);
		}

		if(isset($_SESSION['active_chat_user_id']) && $_SESSION['active_chat_user_id'] == $_POST['user_id'])
		{
			unset($_SESSION['active_chat_user_id']);
			if(!empty($_SESSION['user_data']))
			{
				$firstEntry = reset($_SESSION['user_data']);
				if($firstEntry !== false)
				{
					$_SESSION['active_chat_user_id'] = $firstEntry['id'];
				}
			}
		}

		echo json_encode(['status'=>1]);
	}
}

if(isset($_POST["action"]) && $_POST["action"] == 'fetch_chat')
{
	require 'database/PrivateChat.php';

	$private_chat_object = new PrivateChat;

	$private_chat_object->setFromUserId($_POST["to_user_id"]);

	$private_chat_object->setToUserId($_POST["from_user_id"]);

	if(!empty($_POST['conversation_id']))
	{
		$private_chat_object->setConversationId((int)$_POST['conversation_id']);
	}

	$private_chat_object->change_chat_status();

	$messages = $private_chat_object->get_all_chat_data();

	echo json_encode([
		'conversation_id' => $private_chat_object->getConversationId(),
		'conversation_customer_id' => $private_chat_object->getConversationCustomerId(),
		'messages' => $messages
	]);
}


?>
