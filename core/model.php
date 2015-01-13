<?php

/**
 *
 * @package uLogin Extension
 *
 */

namespace uloginteam\ulogin\core;

class model
{
	public $ulogin_table;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\user */
	protected $user;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config                 $config           Config object
	 * @param \phpbb\db\driver\driver_interface    $db               DBAL object
	 * @param \phpbb\auth\auth                     $auth             User object
	 * @param \phpbb\user                          $user             User object
	 * @param string                               $phpbb_root_path  phpbb_root_path
	 * @param string                               $php_ext          phpEx
	 * @return \rxu\PostsMerging\event\listener
	 * @access public
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\auth\auth $auth, \phpbb\user $user, $phpbb_root_path, $php_ext, $table_prefix)
	{
		$this->config = $config;
		$this->db = $db;
		$this->auth = $auth;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;

		$this->table_prefix = $table_prefix;
//		global $table_prefix;
		define('ULOGIN_TABLE', $table_prefix.'ulogin');
	}


/* ==================================================================================================== */
	/**
	 * Проверка, есть ли пользователь с указанным id в базе
	 * @param $u_id
	 * @return bool
	 */
	public function checkUserId ($u_id) {
		$sql = "SELECT user_id
				FROM " . USERS_TABLE . "
				WHERE user_id = '" . $u_id . "'";

		$result = $this->db->sql_query($sql);
		$result = $this->db->sql_fetchrow($result) !== false ? true : false;
		$this->db->sql_freeresult();

		if (!$result) { return false; }

		return $result;
	}


//--------------------
	/**
	 * Проверка, есть ли пользователь с указанным username в базе
	 * @param string $username
	 * @return bool
	 */
	public function checkUserName ($username = '') {
		$sql = "SELECT user_id
				FROM " . USERS_TABLE . "
				WHERE username_clean LIKE '" . $this->db->sql_escape($username) . "'";

		$result = $this->db->sql_query($sql);
		$result = $this->db->sql_fetchrow($result) !== false ? true : false;
		$this->db->sql_freeresult();

		if (!$result) { return false; }

		return $result;
	}


//--------------------
	/**
	 * Получение id пользователя по email
	 * @param string $email
	 * @return int|bool
	 */
	public function getUserIdByEmail ($email = '') {
		$sql_array = array(
			'SELECT' => 'user_id',
			'FROM' => array(USERS_TABLE => 'u'),
			'WHERE' => "user_email = '{$this->db->sql_escape($email)}'",
		);

		$sql = $this->db->sql_build_query('SELECT', $sql_array);

		$result = $this->db->sql_query_limit($sql, 1);
		$result =  $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult();

		return isset($result['user_id']) ? $result['user_id'] : false;
	}


//--------------------
	/**
	 * Получение данных о пользователе из таблицы ulogin_users
	 * @param array $fields
	 * @return bool|mixed
	 */
	public function getUloginUserItem ($fields = array()) {
		if (!is_array($fields) || empty($fields)) { return false; }

		$sql = "SELECT *
				FROM ". ULOGIN_TABLE;

		$sql .= $this->addWhere($fields);

		$result = $this->db->sql_query_limit($sql, 1);
		$result =  $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult();
		return $result;
	}

//--------------------
	/**
	 * Получение массива соцсетей пользователя по значению поля $user_id
	 * @param int $user_id
	 * @return array
	 */
	public function getUloginUserNetworks ($user_id = 0) {
		$sql = "SELECT network
				FROM " . ULOGIN_TABLE . "
				WHERE user_id = {$user_id}";

		$result = $this->db->sql_query($sql);

		if (!$result) {
			$this->db->sql_freeresult();
			return false; 
		}

		while ($row = $this->db->sql_fetchrow($result))
		{
			$networks[] = $row["network"];
		}

		$this->db->sql_freeresult();

		return $networks;
	}


//--------------------
	/**
	 * Получение ID группы пользователей
	 * @param string $group_name - по умолчанию REGISTERED
	 * @return mixed
	 */
	public function getGroupId ($group_name = 'REGISTERED') {
		$sql = "SELECT group_id
					FROM " . GROUPS_TABLE . "
					WHERE group_name = '" . $this->db->sql_escape($group_name) . "'";

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult();

		return $row['group_id'];
	}

//--------------------
	/**
	 * Удаление данных о пользователе из таблицы ulogin_user
	 * @param int $user_id
	 * @return bool
	 */
	public function deleteUloginUser ($data = array()) {
		if (is_numeric($data)) {
			$where = $this->addWhere(array('id' => $data));
		} elseif (is_array($data)) {
			$where = $this->addWhere($data);
		}

		if ($where == '') return false;
		$sql = 'DELETE FROM ' . ULOGIN_TABLE . $where;

		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult();

		return $result;
	}


//--------------------
	/**
	 * Добавление данных о пользователе в таблицы ulogin_user
	 * @param array $data
	 * @return mixed
	 */
	public function addUloginAccount ($data = array()) {
		$sql = 'INSERT INTO ' . ULOGIN_TABLE . ' ' . $this->db->sql_build_array('INSERT', $data);
		$result = $this->db->sql_query($sql);
		$this->db->sql_freeresult();
		return $result;
	}


//--------------------
	/**
	 * Получение данных о пользователе
	 * @param $u_id
	 * @return mixed
	 */
	public function getUserData ($u_id) {
		$sql = "SELECT *
				FROM " . USERS_TABLE . "
				WHERE user_id = '" . $u_id . "'";

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult();

		return $row;
	}


//--------------------
	/**
	 * Получение данных о пользователе
	 * @param $u_id
	 * @return mixed
	 */
	public function updateUserData ($u_id, $data) {
		$sql = "UPDATE " . USERS_TABLE . "
			SET " . $this->db->sql_build_array("UPDATE", $data) . "
			WHERE user_id = '" . $u_id . "'";

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult();

		return $row;
	}

//--------------
//--------------------------------------
	/** Получение условия where для массива данных $fields
	 * @param array $fields
	 * @return string
	 */
	private function addWhere ($fields = array(), $withWhereWord = true) {
		$i = 0;
		$sql = '';

		if (!is_array($fields) || empty($fields)) { return ''; }

		foreach ($fields as $field => $value) {

			if ($i == 0) {
				$sql .= $withWhereWord ? " WHERE " : "";
			} else {
				$sql .= " AND ";
			}

			$sql .= "$field = '$value'";
			$i++;

		}

		return $sql;
	}


//------------------------------------------------------------------------
	/**
	 * Получение аватара для профиля пользователя
	 * @param string $file_url
	 * @param int $user_id
	 * @return bool
	 */
	public function uploadAvatar ($file_url = '', $user_id = 0) {
		if (empty($file_url) || !$this->config['allow_avatar'] || $user_id == 0) return false;

		list($width,$height,$image_type) = getimagesize( $file_url );

		switch ( $image_type ) {
			case IMAGETYPE_GIF:
				$file_ext = '.gif';
				$source = imagecreatefromgif($file_url);
				break;
			case IMAGETYPE_JPEG:
				$file_ext = '.jpg';
				$source = imagecreatefromjpeg($file_url);
				break;
			case IMAGETYPE_PNG:
				$file_ext = '.png';
				$source = imagecreatefrompng($file_url);
				break;
			default:
				$file_ext = '.jpg';
				$source = imagecreatefromjpeg($file_url);
				break;
		}

		$db_name = $user_id . '_' . time() . $file_ext;
		$name = $this->config['avatar_salt'] . '_' . $user_id . $file_ext;
		$path = $this->config['avatar_path'];
		$file = rtrim($path, '/') . '/' . $name;

		if (!is_dir($path) || !is_writable($path) || $width == 0 || $height == 0 || $source == false)
		{
			return false;
		}

		$avatar_max_width = $this->config['avatar_max_width'];
		$avatar_max_height = $this->config['avatar_max_height'];
		$avatar_min_width = $this->config['avatar_min_width'];
		$avatar_min_height = $this->config['avatar_min_height'];

		if ($width*$avatar_max_height >= $avatar_max_width*$height
		    && $width > $avatar_max_width) {
			$ratio = $avatar_max_width/$width;
			$width = $avatar_max_width;
			$height = $height*$ratio;
		} elseif ($width*$avatar_max_height < $avatar_max_width*$height
		          && $height > $avatar_max_height) {
			$ratio = $avatar_max_height/$height;
			$height = $avatar_max_height;
			$width = $width*$ratio;
		} elseif ($width*$avatar_min_height >= $avatar_min_width*$height
		          && $width < $avatar_min_width) {
			$ratio = $avatar_min_width/$width;
			$width = $avatar_min_width;
			$height = $height*$ratio;
		} elseif ($width*$avatar_min_height < $avatar_min_width*$height
		          && $height < $avatar_min_height) {
			$ratio = $avatar_min_height/$height;
			$height = $avatar_min_height;
			$width = $width*$ratio;
		}

		$image = imagecreatetruecolor($width, $height);

		imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
		imagedestroy( $source );

		switch ($image_type) {
			case IMAGETYPE_GIF:
				imagegif( $image, $file );
				break;
			case IMAGETYPE_JPEG:
				imagejpeg( $image, $file );
				break;
			case IMAGETYPE_PNG:
				imagepng( $image, $file );
				break;
			default:
				imagejpeg( $image, $file );
				break;
		}

		imagedestroy( $image );

		$this->db->sql_query("UPDATE `" . USERS_TABLE . "` " .
		                     "SET user_avatar = '" . $db_name . "', " .
		                     "user_avatar_type = '1', " .
		                     "user_avatar_width = '" . $width . "', " .
		                     "user_avatar_height = '" . $height . "' " .
		                     "WHERE user_id = " . $user_id);

		return true;
	}

}