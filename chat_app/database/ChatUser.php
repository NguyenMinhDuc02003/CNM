<?php

if(class_exists('ChatUser'))
{
	return;
}

//ChatUser.php

class ChatUser
{
	private $user_id;
	private $user_name;
	private $user_email;
	private $external_id;
	private $user_type;
	private $user_password;
	private $user_profile;
	private $user_status;
	private $user_created_on;
	private $user_verification_code;
	private $user_login_status;
	private $user_token;
	private $user_connection_id;
	public $connect;
	private static $schemaEnsured = false;

	public function __construct()
	{
		require_once('Database_connection.php');

		$database_object = new Database_connection;

		$this->connect = $database_object->connect();

		if(!self::$schemaEnsured)
		{
			$this->ensureSchema();
			self::$schemaEnsured = true;
		}
	}

	function setUserId($user_id)
	{
		$this->user_id = $user_id;
	}

	function getUserId()
	{
		return $this->user_id;
	}

	function setUserName($user_name)
	{
		$this->user_name = $user_name;
	}

	function getUserName()
	{
		return $this->user_name;
	}

	function setUserEmail($user_email)
	{
		$this->user_email = $user_email;
	}

	function getUserEmail()
	{
		return $this->user_email;
	}

	function setExternalId($external_id)
	{
		$this->external_id = $external_id;
	}

	function getExternalId()
	{
		return $this->external_id;
	}

	function setUserType($user_type)
	{
		$this->user_type = $user_type;
	}

	function getUserType()
	{
		return $this->user_type;
	}

	function setUserPassword($user_password)
	{
		$this->user_password = $user_password;
	}

	function getUserPassword()
	{
		return $this->user_password;
	}

	function setUserProfile($user_profile)
	{
		$this->user_profile = $user_profile;
	}

	function getUserProfile()
	{
		return $this->user_profile;
	}

	function setUserStatus($user_status)
	{
		$this->user_status = $user_status;
	}

	function getUserStatus()
	{
		return $this->user_status;
	}

	function setUserCreatedOn($user_created_on)
	{
		$this->user_created_on = $user_created_on;
	}

	function getUserCreatedOn()
	{
		return $this->user_created_on;
	}

	function setUserVerificationCode($user_verification_code)
	{
		$this->user_verification_code = $user_verification_code;
	}

	function getUserVerificationCode()
	{
		return $this->user_verification_code;
	}

	function setUserLoginStatus($user_login_status)
	{
		$this->user_login_status = $user_login_status;
	}

	function getUserLoginStatus()
	{
		return $this->user_login_status;
	}

	function setUserToken($user_token)
	{
		$this->user_token = $user_token;
	}

	function getUserToken()
	{
		return $this->user_token;
	}

	function setUserConnectionId($user_connection_id)
	{
		$this->user_connection_id = $user_connection_id;
	}

	function getUserConnectionId()
	{
		return $this->user_connection_id;
	}

	function make_avatar($character)
	{
	    $path = "images/". time() . ".png";
		$image = imagecreate(200, 200);
		$red = rand(0, 255);
		$green = rand(0, 255);
		$blue = rand(0, 255);
	    imagecolorallocate($image, $red, $green, $blue);  
	    $textcolor = imagecolorallocate($image, 255,255,255);

	    $font = dirname(__FILE__) . '/font/arial.ttf';

	    imagettftext($image, 100, 0, 55, 150, $textcolor, $font, $character);
	    imagepng($image, $path);
	    imagedestroy($image);
	    return $path;
	}

	function get_user_data_by_email()
	{
		$query = "
		SELECT * FROM chat_user_table 
		WHERE user_email = :user_email
		AND user_type = :user_type
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_email', $this->user_email);

		$statement->bindParam(':user_type', $this->user_type);

		if($statement->execute())
		{
			$user_data = $statement->fetch(PDO::FETCH_ASSOC);
		}
		else
		{
			$user_data = array();
		}

		return $user_data;
	}

	/**
	 * Synchronise (insert/update) the chat user row with the latest data that
	 * comes from the existing restaurant system account.
	 */
	function sync_system_identity()
	{
		$query = "
		INSERT INTO chat_user_table (
			external_id,
			user_type,
			user_email,
			user_name,
			user_profile,
			last_seen
		) VALUES (
			:external_id,
			:user_type,
			:user_email,
			:user_name,
			:user_profile,
			NOW()
		)
		ON DUPLICATE KEY UPDATE
			external_id = VALUES(external_id),
			user_name = VALUES(user_name),
			user_profile = VALUES(user_profile),
			last_seen = NOW(),
			updated_at = NOW(),
			user_id = LAST_INSERT_ID(user_id)
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':external_id', $this->external_id);
		$statement->bindParam(':user_type', $this->user_type);
		$statement->bindParam(':user_email', $this->user_email);
		$statement->bindParam(':user_name', $this->user_name);
		$statement->bindParam(':user_profile', $this->user_profile);

		$statement->execute();

		return (int) $this->connect->lastInsertId();
	}

	function save_data()
	{
		$query = "
		INSERT INTO chat_user_table (user_name, user_email, user_password, user_profile, user_status, user_created_on, user_verification_code) 
		VALUES (:user_name, :user_email, :user_password, :user_profile, :user_status, :user_created_on, :user_verification_code)
		";
		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_name', $this->user_name);

		$statement->bindParam(':user_email', $this->user_email);

		$statement->bindParam(':user_password', $this->user_password);

		$statement->bindParam(':user_profile', $this->user_profile);

		$statement->bindParam(':user_status', $this->user_status);

		$statement->bindParam(':user_created_on', $this->user_created_on);

		$statement->bindParam(':user_verification_code', $this->user_verification_code);

		if($statement->execute())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function is_valid_email_verification_code()
	{
		$query = "
		SELECT * FROM chat_user_table 
		WHERE user_verification_code = :user_verification_code
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_verification_code', $this->user_verification_code);

		$statement->execute();

		if($statement->rowCount() > 0)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function enable_user_account()
	{
		$query = "
		UPDATE chat_user_table 
		SET user_status = :user_status 
		WHERE user_verification_code = :user_verification_code
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_status', $this->user_status);

		$statement->bindParam(':user_verification_code', $this->user_verification_code);

		if($statement->execute())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function update_user_login_data()
	{
		$query = "
		UPDATE chat_user_table 
		SET user_login_status = :user_login_status, 
		user_token = :user_token,
		last_seen = NOW()  
		WHERE user_id = :user_id
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_login_status', $this->user_login_status);

		if(is_null($this->user_token))
		{
			$statement->bindValue(':user_token', null, PDO::PARAM_NULL);
		}
		else
		{
			$statement->bindValue(':user_token', $this->user_token, PDO::PARAM_STR);
		}

		$statement->bindParam(':user_id', $this->user_id);

		if($statement->execute())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function get_user_data_by_id()
	{
		$query = "
		SELECT * FROM chat_user_table 
		WHERE user_id = :user_id";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_id', $this->user_id);

		$user_data = array();

		try
		{
			if($statement->execute())
			{
				$user_data = $statement->fetch(PDO::FETCH_ASSOC);
			}
		}
		catch (Exception $error)
		{
			echo $error->getMessage();
		}
		return $user_data;
	}

	function upload_image($user_profile)
	{
		$extension = explode('.', $user_profile['name']);
		$new_name = rand() . '.' . $extension[1];
		$destination = 'images/' . $new_name;
		move_uploaded_file($user_profile['tmp_name'], $destination);
		return $destination;
	}

	function update_data()
	{
		$query = "
		UPDATE chat_user_table 
		SET user_name = :user_name, 
		user_email = :user_email, 
		user_password = :user_password, 
		user_profile = :user_profile  
		WHERE user_id = :user_id
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_name', $this->user_name);

		$statement->bindParam(':user_email', $this->user_email);

		$statement->bindParam(':user_password', $this->user_password);

		$statement->bindParam(':user_profile', $this->user_profile);

		$statement->bindParam(':user_id', $this->user_id);

		if($statement->execute())
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	function get_user_all_data()
	{
		$query = "
		SELECT user_id, user_name, user_profile, user_login_status, user_type, user_email 
		FROM chat_user_table 
		ORDER BY user_name ASC
		";

		$statement = $this->connect->prepare($query);

		$statement->execute();

		$data = $statement->fetchAll(PDO::FETCH_ASSOC);

		return $data;
	}

	function get_user_all_data_with_status_count()
	{
		$query = "
		SELECT user_id, user_name, user_profile, user_login_status, user_type, user_email,
		(
			SELECT COUNT(*) FROM chat_message 
			WHERE to_user_id = :user_id 
			AND from_user_id = chat_user_table.user_id 
			AND status = 'No'
		) AS count_status 
		FROM chat_user_table
		ORDER BY user_name ASC
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_id', $this->user_id);

		$statement->execute();

		$data = $statement->fetchAll(PDO::FETCH_ASSOC);

		return $data;
	}

	function get_default_employee()
	{
		$query = "
		SELECT user_id, user_name, user_profile, user_email, user_login_status 
		FROM chat_user_table 
		WHERE user_type = 'employee' 
		ORDER BY (user_login_status = 'Login') DESC, user_name ASC 
		LIMIT 1
		";

		$statement = $this->connect->prepare($query);
		$statement->execute();

		return $statement->fetch(PDO::FETCH_ASSOC);
	}

	function get_users_by_type($user_type)
	{
		$query = "
		SELECT user_id, user_name, user_profile, user_login_status, user_type, user_email
		FROM chat_user_table
		WHERE user_type = :user_type
		ORDER BY user_name ASC
		";

		$statement = $this->connect->prepare($query);
		$statement->bindParam(':user_type', $user_type);
		$statement->execute();

		return $statement->fetchAll(PDO::FETCH_ASSOC);
	}

	function update_user_connection_id()
	{
		$query = "
		UPDATE chat_user_table 
		SET user_connection_id = :user_connection_id,
		last_seen = NOW()
		WHERE user_token = :user_token
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_connection_id', $this->user_connection_id);

		$statement->bindParam(':user_token', $this->user_token);

		$statement->execute();
	}

	function get_user_id_from_token()
	{
		$query = "
		SELECT user_id FROM chat_user_table 
		WHERE user_token = :user_token
		";

		$statement = $this->connect->prepare($query);

		$statement->bindParam(':user_token', $this->user_token);

		$statement->execute();

		$user_id = $statement->fetch(PDO::FETCH_ASSOC);

		return $user_id ? $user_id : array();
	}

	private function ensureSchema()
	{
		$chatUserTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS `chat_user_table` (
	`user_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`external_id` INT UNSIGNED DEFAULT NULL,
	`user_type` VARCHAR(50) NOT NULL DEFAULT 'customer',
	`user_email` VARCHAR(191) NOT NULL DEFAULT '',
	`user_name` VARCHAR(191) NOT NULL,
	`user_password` VARCHAR(255) DEFAULT NULL,
	`user_profile` VARCHAR(255) DEFAULT NULL,
	`user_status` VARCHAR(20) NOT NULL DEFAULT 'Enable',
	`user_created_on` DATETIME DEFAULT CURRENT_TIMESTAMP,
	`user_verification_code` VARCHAR(191) DEFAULT NULL,
	`user_login_status` VARCHAR(20) NOT NULL DEFAULT 'Logout',
	`user_token` VARCHAR(255) DEFAULT NULL,
	`user_connection_id` VARCHAR(255) DEFAULT NULL,
	`last_seen` DATETIME DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`user_id`),
	UNIQUE KEY `uniq_external_type` (`user_type`, `external_id`),
	UNIQUE KEY `uniq_user_token` (`user_token`),
	KEY `idx_user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

		$chatMessageTableSql = <<<SQL
CREATE TABLE IF NOT EXISTS `chat_message` (
	`chat_message_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
	`to_user_id` INT UNSIGNED NOT NULL,
	`from_user_id` INT UNSIGNED NOT NULL,
	`chat_message` TEXT,
	`timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`status` ENUM('No','Yes') NOT NULL DEFAULT 'No',
	`attachment_name` VARCHAR(255) DEFAULT NULL,
	`attachment_path` VARCHAR(255) DEFAULT NULL,
	`attachment_mime` VARCHAR(255) DEFAULT NULL,
	`attachment_size` INT UNSIGNED DEFAULT NULL,
	PRIMARY KEY (`chat_message_id`),
	KEY `idx_to_user` (`to_user_id`),
	KEY `idx_from_user` (`from_user_id`),
	CONSTRAINT `fk_chat_message_to` FOREIGN KEY (`to_user_id`) REFERENCES `chat_user_table`(`user_id`) ON DELETE CASCADE,
	CONSTRAINT `fk_chat_message_from` FOREIGN KEY (`from_user_id`) REFERENCES `chat_user_table`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

		try
		{
			$this->connect->exec($chatUserTableSql);
			$this->connect->exec($chatMessageTableSql);
		}
		catch (\PDOException $e)
		{
			error_log('[Chat][Schema] '.$e->getMessage());
			throw $e;
		}
	}
}



?>

