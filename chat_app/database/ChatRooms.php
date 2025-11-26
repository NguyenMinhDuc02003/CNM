<?php 
	
class ChatRooms
{
	private $chat_id;
	private $user_id;
	private $message;
	private $created_on;
	private $attachment_name;
	private $attachment_path;
	private $attachment_mime;
	private $attachment_size;
	protected $connect;
	private static $schemaEnsured = false;

	public function setChatId($chat_id)
	{
		$this->chat_id = $chat_id;
	}

	function getChatId()
	{
		return $this->chat_id;
	}

	function setUserId($user_id)
	{
		$this->user_id = $user_id;
	}

	function getUserId()
	{
		return $this->user_id;
	}

	function setMessage($message)
	{
		$this->message = $message;
	}

	function getMessage()
	{
		return $this->message;
	}

	function setCreatedOn($created_on)
	{
		$this->created_on = $created_on;
	}

	function getCreatedOn()
	{
		return $this->created_on;
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

	public function __construct()
	{
		require_once("Database_connection.php");

		$database_object = new Database_connection;

		$this->connect = $database_object->connect();

		if(!self::$schemaEnsured)
		{
			$this->ensureSchema();
			self::$schemaEnsured = true;
		}
	}

	function save_chat()
	{
		$query = "
		INSERT INTO chatrooms 
			(userid, msg, created_on, attachment_name, attachment_path, attachment_mime, attachment_size) 
			VALUES (:userid, :msg, :created_on, :attachment_name, :attachment_path, :attachment_mime, :attachment_size)
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':userid', $this->user_id);

		$statement->bindParam(':msg', $this->message);

		$statement->bindParam(':created_on', $this->created_on);

		$statement->bindParam(':attachment_name', $this->attachment_name);

		$statement->bindParam(':attachment_path', $this->attachment_path);

		$statement->bindParam(':attachment_mime', $this->attachment_mime);

		$statement->bindParam(':attachment_size', $this->attachment_size);

		$statement->execute();

		return $this->connect->lastInsertId();
	}

	function get_all_chat_data()
	{
		$query = "
		SELECT * FROM chatrooms 
			INNER JOIN chat_user_table 
			ON chat_user_table.user_id = chatrooms.userid 
			ORDER BY chatrooms.id ASC
		";

		$statement = $this->connect->prepare($query);

		$statement->execute();

		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	private function ensureSchema()
	{
		// Đảm bảo bảng chat_user_table tồn tại trước khi tạo chatrooms (do FK phụ thuộc).
		if(!class_exists('ChatUser'))
		{
			$chatUserPath = __DIR__ . '/ChatUser.php';
			if(file_exists($chatUserPath))
			{
				require_once $chatUserPath;
			}
		}

		if(class_exists('ChatUser'))
		{
			try
			{
				new \ChatUser;
			}
			catch (\Throwable $e)
			{
				error_log('[Chat][Schema][ChatRooms] Không thể khởi tạo ChatUser trước khi tạo chatrooms: '.$e->getMessage());
			}
		}

		$query = <<<SQL
CREATE TABLE IF NOT EXISTS `chatrooms` (
	`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`userid` INT UNSIGNED NOT NULL,
	`msg` TEXT,
	`created_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`attachment_name` VARCHAR(255) DEFAULT NULL,
	`attachment_path` VARCHAR(255) DEFAULT NULL,
	`attachment_mime` VARCHAR(255) DEFAULT NULL,
	`attachment_size` INT UNSIGNED DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_chatrooms_user` (`userid`),
	CONSTRAINT `fk_chatrooms_user` FOREIGN KEY (`userid`) REFERENCES `chat_user_table` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

		try
		{
			$this->connect->exec($query);
		}
		catch (\PDOException $e)
		{
			error_log('[Chat][Schema][ChatRooms] '.$e->getMessage());
			throw $e;
		}
	}
}
	
?>
