<?php

if(class_exists('PrivateChat'))
{
	return;
}

//PrivateChat.php

class PrivateChat
{
	private $chat_message_id;
	private $to_user_id;
	private $from_user_id;
	private $chat_message;
	private $timestamp;
	private $status;
	private $attachment_name;
	private $attachment_path;
	private $attachment_mime;
	private $attachment_size;
	private $conversation_id;
	private $conversation_customer_id;
	private $acting_cashier_user_id;
	protected $connect;

	public function __construct()
	{
		require_once('Database_connection.php');

		$db = new Database_connection();

		$this->connect = $db->connect();
	}

	function setChatMessageId($chat_message_id)
	{
		$this->chat_message_id = $chat_message_id;
	}

	function getChatMessageId()
	{
		return $this->chat_message_id;
	}

	function setToUserId($to_user_id)
	{
		$this->to_user_id = $to_user_id;
	}

	function getToUserId()
	{
		return $this->to_user_id;
	}

	function setFromUserId($from_user_id)
	{
		$this->from_user_id = $from_user_id;
	}

	function getFromUserId()
	{
		return $this->from_user_id;
	}

	function setChatMessage($chat_message)
	{
		$this->chat_message = $chat_message;
	}

	function getChatMessage()
	{
		return $this->chat_message;
	}

	function setTimestamp($timestamp)
	{
		$this->timestamp = $timestamp;
	}

	function getTimestamp()
	{
		return $this->timestamp;
	}

	function setStatus($status)
	{
		$this->status = $status;
	}

	function getStatus()
	{
		return $this->status;
	}

	function setConversationId($conversation_id)
	{
		$this->conversation_id = $conversation_id ? (int)$conversation_id : null;
		if($this->conversation_id)
		{
			$this->hydrateConversationMetadata();
		}
	}

	function getConversationId()
	{
		return $this->conversation_id;
	}

	function getConversationCustomerId()
	{
		return $this->conversation_customer_id;
	}

	function getActiveCashierUserId()
	{
		return $this->acting_cashier_user_id;
	}

	function setAttachment($attachment)
	{
		if(is_array($attachment))
		{
			$this->attachment_name = $attachment['name'] ?? null;
			$this->attachment_path = $attachment['path'] ?? null;
			$this->attachment_mime = $attachment['mime'] ?? null;
			$this->attachment_size = $attachment['size'] ?? null;
		}
		else
		{
			$this->attachment_name = null;
			$this->attachment_path = null;
			$this->attachment_mime = null;
			$this->attachment_size = null;
		}
	}

	private function hydrateConversationMetadata()
	{
		if(empty($this->conversation_id))
		{
			return;
		}

		$query = "
		SELECT conversation_id, customer_user_id, last_cashier_user_id 
		FROM chat_conversation 
		WHERE conversation_id = :conversation_id
		LIMIT 1
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':conversation_id', $this->conversation_id, \PDO::PARAM_INT);

		$statement->execute();

		$row = $statement->fetch(\PDO::FETCH_ASSOC);

		if(!$row)
		{
			$this->conversation_id = null;
			$this->conversation_customer_id = null;
			return;
		}

		$customer_id = (int)$row['customer_user_id'];

		if(!in_array($customer_id, array_filter([$this->from_user_id, $this->to_user_id]), true))
		{
			$this->conversation_id = null;
			$this->conversation_customer_id = null;
			return;
		}

		$this->conversation_customer_id = $customer_id;

		if($this->from_user_id == $customer_id)
		{
			$this->acting_cashier_user_id = $this->to_user_id;
		}
		elseif($this->to_user_id == $customer_id)
		{
			$this->acting_cashier_user_id = $this->from_user_id;
		}
		else
		{
			$this->acting_cashier_user_id = isset($row['last_cashier_user_id']) ? (int)$row['last_cashier_user_id'] : null;
		}
	}

	private function determineConversationRoles()
	{
		if(!class_exists('ChatUser'))
		{
			require_once('ChatUser.php');
		}

		$user_model = new ChatUser;
		$participants = array_values(array_filter([$this->from_user_id, $this->to_user_id]));
		$customer_user_id = null;
		$cashier_user_id = null;

		foreach($participants as $participant_id)
		{
			$user_model->setUserId($participant_id);
			$user_data = $user_model->get_user_data_by_id();
			$type = $user_data['user_type'] ?? '';

			if($customer_user_id === null && $type === 'customer')
			{
				$customer_user_id = $participant_id;
				continue;
			}

			if($cashier_user_id === null && $type === 'employee')
			{
				$cashier_user_id = $participant_id;
			}
			elseif($cashier_user_id === null && $participant_id !== $customer_user_id)
			{
				$cashier_user_id = $participant_id;
			}
		}

		if($customer_user_id === null && !empty($participants))
		{
			$customer_user_id = min($participants);
		}

		if($cashier_user_id === null)
		{
			foreach($participants as $participant_id)
			{
				if($participant_id !== $customer_user_id)
				{
					$cashier_user_id = $participant_id;
					break;
				}
			}

			if($cashier_user_id === null && !empty($participants))
			{
				 $cashier_user_id = max($participants);
			}
		}

		return [$customer_user_id, $cashier_user_id];
	}

	private function touchConversationAgent($conversation_id, $cashier_user_id)
	{
		if(empty($conversation_id) || empty($cashier_user_id))
		{
			return;
		}

		$query = "
		SELECT id FROM chat_conversation_agents 
		WHERE conversation_id = :conversation_id 
		AND cashier_user_id = :cashier_user_id 
		AND left_at IS NULL
		LIMIT 1
		";

		$statement = $this->connect->prepare($query);
		$statement->bindParam(':conversation_id', $conversation_id, \PDO::PARAM_INT);
		$statement->bindParam(':cashier_user_id', $cashier_user_id, \PDO::PARAM_INT);
		$statement->execute();

		if($statement->fetch(\PDO::FETCH_ASSOC))
		{
			return;
		}

		$insert = "
		INSERT INTO chat_conversation_agents (conversation_id, cashier_user_id, joined_at) 
		VALUES (:conversation_id, :cashier_user_id, NOW())
		";

		$inserter = $this->connect->prepare($insert);
		$inserter->bindParam(':conversation_id', $conversation_id, \PDO::PARAM_INT);
		$inserter->bindParam(':cashier_user_id', $cashier_user_id, \PDO::PARAM_INT);
		$inserter->execute();
	}

	private function ensureConversationContext()
	{
		if(!empty($this->conversation_id) && !empty($this->conversation_customer_id))
		{
			return $this->conversation_id;
		}

		if(!empty($this->conversation_id))
		{
			$this->hydrateConversationMetadata();
			if(!empty($this->conversation_id) && !empty($this->conversation_customer_id))
			{
				return $this->conversation_id;
			}
		}

		if(empty($this->from_user_id) || empty($this->to_user_id))
		{
			throw new \RuntimeException('Conversation participants are required.');
		}

		list($customer_user_id, $cashier_user_id) = $this->determineConversationRoles();

		if(empty($customer_user_id))
		{
			throw new \RuntimeException('Unable to determine customer for this conversation.');
		}

		$this->conversation_customer_id = $customer_user_id;
		$this->acting_cashier_user_id = $cashier_user_id;

		$query = "
		SELECT conversation_id, last_cashier_user_id 
		FROM chat_conversation 
		WHERE customer_user_id = :customer_user_id
		LIMIT 1
		";

		$statement = $this->connect->prepare($query);
		$statement->bindParam(':customer_user_id', $customer_user_id, \PDO::PARAM_INT);
		$statement->execute();

		$row = $statement->fetch(\PDO::FETCH_ASSOC);

		if($row)
		{
			$this->conversation_id = (int)$row['conversation_id'];
			if(!empty($row['last_cashier_user_id']))
			{
				$this->acting_cashier_user_id = (int)$row['last_cashier_user_id'];
			}

			if(!empty($cashier_user_id) && (!isset($row['last_cashier_user_id']) || (int)$row['last_cashier_user_id'] !== (int)$cashier_user_id))
			{
				$update = "
				UPDATE chat_conversation 
				SET last_cashier_user_id = :cashier_user_id 
				WHERE conversation_id = :conversation_id
				";

				$updater = $this->connect->prepare($update);
				$updater->bindParam(':cashier_user_id', $cashier_user_id, \PDO::PARAM_INT);
				$updater->bindParam(':conversation_id', $this->conversation_id, \PDO::PARAM_INT);
				$updater->execute();
			}
		}
		else
		{
			$insert = "
			INSERT INTO chat_conversation (customer_user_id, last_cashier_user_id, status, created_at, last_message_at) 
			VALUES (:customer_user_id, :cashier_user_id, 'open', NOW(), NOW())
			";

			$inserter = $this->connect->prepare($insert);
			$inserter->bindParam(':customer_user_id', $customer_user_id, \PDO::PARAM_INT);
			if($cashier_user_id)
			{
				$inserter->bindParam(':cashier_user_id', $cashier_user_id, \PDO::PARAM_INT);
			}
			else
			{
				$inserter->bindValue(':cashier_user_id', null, \PDO::PARAM_NULL);
			}
			$inserter->execute();

			$this->conversation_id = (int)$this->connect->lastInsertId();
		}

		$this->touchConversationAgent($this->conversation_id, $cashier_user_id);
		if(empty($this->acting_cashier_user_id))
		{
			$this->acting_cashier_user_id = $cashier_user_id;
		}

		return $this->conversation_id;
	}

	private function updateConversationAfterMessage($timestamp)
	{
		if(empty($this->conversation_id))
		{
			return;
		}

		if(!empty($this->acting_cashier_user_id))
		{
			$query = "
			UPDATE chat_conversation 
			SET last_cashier_user_id = :cashier_user_id,
			last_message_at = :timestamp
			WHERE conversation_id = :conversation_id
			";

			$statement = $this->connect->prepare($query);
			$statement->bindParam(':cashier_user_id', $this->acting_cashier_user_id, \PDO::PARAM_INT);
			$statement->bindParam(':timestamp', $timestamp);
			$statement->bindParam(':conversation_id', $this->conversation_id, \PDO::PARAM_INT);
			$statement->execute();
		}
		else
		{
			$query = "
			UPDATE chat_conversation 
			SET last_message_at = :timestamp
			WHERE conversation_id = :conversation_id
			";

			$statement = $this->connect->prepare($query);
			$statement->bindParam(':timestamp', $timestamp);
			$statement->bindParam(':conversation_id', $this->conversation_id, \PDO::PARAM_INT);
			$statement->execute();
		}
	}

	function get_all_chat_data()
	{
		if(empty($this->conversation_id) && (empty($this->from_user_id) || empty($this->to_user_id)))
		{
			error_log('[Chat][PrivateChat][get_all_chat_data] Missing conversation context.');
			return [];
		}

		try
		{
			$conversation_id = $this->ensureConversationContext();
		}
		catch (\Throwable $e)
		{
			error_log('[Chat][PrivateChat][get_all_chat_data] '.$e->getMessage());
			return [];
		}

		$query = "
		SELECT 
			a.user_name as from_user_name, 
			a.user_type as from_user_type,
			b.user_name as to_user_name, 
			b.user_type as to_user_type,
			chat_message, 
			timestamp, 
			status, 
			to_user_id, 
			from_user_id,
			attachment_name,
			attachment_path,
			attachment_mime,
			attachment_size,
			chat_message.conversation_id
		FROM chat_message 
		INNER JOIN chat_user_table a 
			ON chat_message.from_user_id = a.user_id 
		INNER JOIN chat_user_table b 
			ON chat_message.to_user_id = b.user_id 
		WHERE chat_message.conversation_id = :conversation_id
		ORDER BY chat_message.chat_message_id ASC
		";

		try
		{
			$statement = $this->connect->prepare($query);

			$statement->bindParam(':conversation_id', $conversation_id, \PDO::PARAM_INT);

			$statement->execute();

			return $statement->fetchAll(\PDO::FETCH_ASSOC);
		}
		catch (\PDOException $e)
		{
			error_log('[Chat][PrivateChat][get_all_chat_data] '.$e->getMessage().' | conversation_id='.$conversation_id);
			throw $e;
		}
	}

	function save_chat()
	{
		$conversation_id = $this->ensureConversationContext();

		$query = "
		INSERT INTO chat_message 
			(conversation_id, to_user_id, from_user_id, chat_message, timestamp, status, attachment_name, attachment_path, attachment_mime, attachment_size) 
			VALUES (:conversation_id, :to_user_id, :from_user_id, :chat_message, :timestamp, :status, :attachment_name, :attachment_path, :attachment_mime, :attachment_size)
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':conversation_id', $conversation_id, \PDO::PARAM_INT);

		$statement->bindParam(':to_user_id', $this->to_user_id);

		$statement->bindParam(':from_user_id', $this->from_user_id);

		$statement->bindParam(':chat_message', $this->chat_message);

		$statement->bindParam(':timestamp', $this->timestamp);

		$statement->bindParam(':status', $this->status);

		$statement->bindParam(':attachment_name', $this->attachment_name);

		$statement->bindParam(':attachment_path', $this->attachment_path);

		$statement->bindParam(':attachment_mime', $this->attachment_mime);

		$statement->bindParam(':attachment_size', $this->attachment_size);

		$statement->execute();

		$this->updateConversationAfterMessage($this->timestamp ?? date('Y-m-d H:i:s'));

		return $this->connect->lastInsertId();
	}

	function update_chat_status()
	{
		$query = "
		UPDATE chat_message 
			SET status = :status 
			WHERE chat_message_id = :chat_message_id
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':status', $this->status);

		$statement->bindParam(':chat_message_id', $this->chat_message_id);

		$statement->execute();
	}

	function change_chat_status()
	{
		if(empty($this->conversation_id) && (empty($this->from_user_id) || empty($this->to_user_id)))
		{
			return;
		}

		try
		{
			$conversation_id = $this->ensureConversationContext();
		}
		catch (\Throwable $e)
		{
			return;
		}

		$query = "
		UPDATE chat_message 
			SET status = 'Yes' 
			WHERE conversation_id = :conversation_id
			AND from_user_id = :from_user_id 
			AND to_user_id = :to_user_id 
			AND status = 'No'
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':conversation_id', $conversation_id, \PDO::PARAM_INT);

		$statement->bindParam(':from_user_id', $this->from_user_id);

		$statement->bindParam(':to_user_id', $this->to_user_id);

		$statement->execute();
	}

}



?>
