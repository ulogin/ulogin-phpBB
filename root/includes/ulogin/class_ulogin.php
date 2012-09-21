<?php

/** 
 * Auth via uLogin.ru
 * @package phpBB
 * @subpackage uLogin MOD
 * @author uLogin team@ulogin.ru http://ulogin.ru/
 * @license GPL3
 */

require_once('class_JSON.php'); // http://pear.php.net/pepr/pepr-proposal-show.php?id=198

class uLogin
{
	private $db = NULL; // database class
	private $token = NULL; // uLogin token
	private $user = NULL; // uLogin user data
	
	private $max_level = 5; // max nesting level (method: __fetch_random_name)
	private $image_ext = 'jpg'; // avatar extension

	public function __construct($db = NULL)
	{
		$this->db = $db;
		
		if ($_POST['token'])
		{
			$this->token = $_POST['token'];
		}
		
		$this->__get_user();
	}
	
	/**
	 * Get current user email or generate random
	 * 
	 * @access 	private
	 * @param 	bool 		$random		if true will generate random email
	 * @return 	string				return email
	 */
	private function __fetch_random_email($random = false)
	{
		if (!$random && $this->user['email'])
		{
			if ($user = $this->__get_first("SELECT * FROM `" . USERS_TABLE . "` WHERE user_email = '" . $this->db->sql_escape($this->user['email']) . "'"))
			{
				return $this->__fetch_random_email(true);
			}
			
			return $this->user['email'];
		}
		
		return $this->user['identity'] . '@' . $this->user['network'];
	}
	
	/**
	 * Get current user name or generate random
	 * 
	 * @access 	private
	 * @param 	string 		$name		if set will append random string
	 * @param	int		$level		the higher the value the more random string will be in result
	 * @return 	string				return user name
	 */
	private function __fetch_random_name($name = '', $level = 0)
	{
		if ($level == $this->max_level)
		{
			return '';
		}
		
		if ($name)
		{
			$name = $name . $this->__random(1);
		}
		else if ($this->user['first_name'] && $this->user['last_name'])
		{
			$name = $this->user['last_name'] . ' ' . $this->user['first_name'];
		}
		else if ($this->user['first_name'])
		{
			$name = $this->user['first_name'];
		}
		else if ($this->user['last_name'])
		{
			$name = $this->user['last_name'];
		}
		else
		{
			$name = 'uLogin' . $this->__random(5);
		}
		
		if ($user = $this->__get_first("SELECT * FROM `" . USERS_TABLE . "` WHERE username = '" . $this->db->sql_escape($name) . "'"))
		{
			return $this->__fetch_random_name($name, ($level + 1));
		}
		
		return $name;
	}
	
	/**
	 * Get current user location (city/country)
	 * 
	 * @access 	private
	 * @return 	string				return user location
	 */
	private function __fetch_user_from()
	{
		if ($this->user['country'] && $this->user['city'])
		{
			return ucfirst(strtolower($this->user['country'])) . ', ' . ucfirst(strtolower($this->user['city']));
		}
		else if ($this->user['country'])
		{
			return ucfirst(strtolower($this->user['country']));
		}
		else if ($this->user['city'])
		{
			return ucfirst(strtolower($this->user['city']));
		}
		
		return '';
	}
	
	/**
	 * Get first row from db
	 * 
	 * @access 	private
	 * @param	string		$query		query to database
	 * @return 	array				return db row
	 */
	private function __get_first($query = '')
	{
		if (!$query)
		{
			return false;
		}
		
		$result = $this->db->sql_query($query);
		$row = $this->db->sql_fetchrow($result);
		
		if ($row)
		{
			return $row;
		}
		
		return false;
	}

    /**
     * Read response with available wrapper
     *
     * @access private
     * @return string
     */
   private function __get_response($url = ""){

       $s = array("error" => "file_get_contents or curl required");

       if (in_array('curl', get_loaded_extensions())) {

           $request = curl_init($url);
           curl_setopt($request, CURLOPT_RETURNTRANSFER, 1);
           curl_setopt($request, CURLOPT_BINARYTRANSFER, 1);
           $result = curl_exec($request);
           $s = $result ? $result : $s;

       }elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')){

           $result = file_get_contents($url);
           $s = $result ? $result : $s;

       }

       return $s;

   }



    /**
	 * Get user from ulogin.ru by token
	 * 
	 * @access 	private
	 * @return 	mixed				if token expired or some errors occurred will return NULL else will return user data
	 */
	private function __get_user()
	{
		if ($this->user)
		{
			return $this->user;
		}
		
		if ($this->token)
		{
			$info = $this->__get_response('http://ulogin.ru/token.php?token=' . $this->token);

            $data = array();

			if (function_exists('json_decode'))
			{
                $this->user = json_decode($info, true);
			}
			else
			{
				$json = new Services_JSON();
				$this->user = $json->decode($info, true);
			}

            return $this->user;

		}
		
		return null;
	}
	
	/**
	 * Generate random string
	 * 
	 * @access 	private
	 * @param	int		$length		length of generating string
	 * @return 	string				return generated string
	 */
	private function __random($length = 10)
	{
		$random = '';
		
		for ($i = 0; $i < $length; $i++)
		{
			$random += chr(rand(48, 57));
		}
		
		return $random;
	}
	
	/**
	 * Upload current user avatar to server
	 * 
	 * @access 	private
	 * @return 	bool				return TRUE if avatar set, else return FALSE
	 */
	private function __upload_avatar($user_id)
	{
		global $config;
		
		if (!$this->user['photo'] || !$config['allow_avatar'] || !$user_id)
		{
			return false;
		}
			
		$db_name = $user_id . '_' . time() . '.' . $this->image_ext;
		$name = $config['avatar_salt'] . '_' . $user_id . '.' . $this->image_ext;
		$path = $config['avatar_path'];
		$file = rtrim($path, '/') . '/' . $name;
		
		if (!is_dir($path) || !is_writable($path))
		{
			return false;
		}
		
		$avatar = $this->__get_response($this->user['photo']);

		$fp = fopen($file, "w+");
		fwrite($fp, $avatar);
		fclose($fp);
		
		if (file_exists($file))
		{
			list($width, $height) = getimagesize($file);
			
			$this->db->sql_query("UPDATE `" . USERS_TABLE . "` SET user_avatar = '" . $db_name . "', user_avatar_type = '1', user_avatar_width = '" . $width . "', user_avatar_height = '" . $height . "' WHERE user_id = " . $user_id);
			return true;
		}
		
		return false;
	}
	
	/**
	 * Auth user
	 * 
	 * @access 	public
	 * @return 	bool				if user authorized return true, else return false
	 */
	public function auth()
	{

		if (!$user = $this->__get_first("SELECT * FROM " . TABLE_PREFIX . "ulogin WHERE identity = '" . $this->db->sql_escape($this->user['identity']) . "'"))
		{
			return false;
		}
		
		if (!$this->__get_first("SELECT * FROM `" . USERS_TABLE . "` WHERE user_id = " . $user['userid']))
		{
			$this->db->sql_query("DELETE FROM " . TABLE_PREFIX . "ulogin WHERE identity = '" . $this->db->sql_escape($this->user['identity']) . "'");
		
			return false;
		}
		
		return $user['userid'];
	}
	
	/**
	 * Register user
	 * 
	 * @access 	public
	 */
	public function register()
	{
		global $config, $user, $phpbb_root_path, $phpEx;

        if (!$this->user || isset($this->user['error']))
        {
            return false;
        }

		
		$data = array(
			'username'		=> utf8_normalize_nfc($this->__fetch_random_name()),
			'user_password'		=> phpbb_hash($this->__random(15)),
			'user_email'		=> strtolower($this->__fetch_random_email()),
			'user_birthday'		=> ($this->user['bdate'] ? date('d-m-Y', strtotime($this->user['bdate'])) : ''),
			'user_from' 		=> $this->__fetch_user_from(),
			'user_timezone'		=> $config['board_timezone'],
			'user_dst'		=> $config['board_dst'],
			'user_lang'		=> basename($user->lang_name),
			'user_type'		=> USER_NORMAL,
			'user_actkey'		=> '',
			'user_ip'		=> $user->ip,
			'user_regdate'		=> time(),
			'user_inactive_reason'	=> 0,
			'user_inactive_time'	=> 0
		);
		
		$error = array();
		
		if ($config['check_dnsbl'])
		{
			if (($dnsbl = $user->check_dnsbl('register')) !== false)
			{
				$error[] = sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]);
			}
		}
		
		if ($error)
		{
			trigger_error (implode('', $error));
			return false;
		}
		
		$server_url = generate_board_url();
		
		if (!$row = $this->__get_first("SELECT group_id FROM " . GROUPS_TABLE . " WHERE group_name = '" . $this->db->sql_escape('REGISTERED') . "' AND group_type = " . GROUP_SPECIAL)) {
			trigger_error('NO_GROUP');
		}
		
		$data['group_id'] = (int)$row['group_id'];
		
		if ($config['new_member_post_limit'])
		{
			$data['user_new'] = 1;
		}
		
		if (!$user_id = user_add($data))
		{
			trigger_error('NO_USER', E_USER_ERROR);
		}
		
		$this->__upload_avatar($user_id);
		
		$this->db->sql_query("INSERT INTO " . TABLE_PREFIX . "ulogin VALUES (NULL, " . $user_id . ", '" . $this->db->sql_escape($this->user['identity']) . "')");
		
		$email_template = 'user_welcome';
		
		if ($config['email_enable'])
		{
			require_once($phpbb_root_path . 'includes/functions_messenger.' . $phpEx);
			$messenger = new messenger(false);
			$messenger->template($email_template, $data['lang']);
			$messenger->to($data['email'], $data['username']);
			$messenger->headers('X-AntiAbuse: Board servername - ' . $config['server_name']);
			$messenger->headers('X-AntiAbuse: User_id - ' . $user->data['user_id']);
			$messenger->headers('X-AntiAbuse: Username - ' . $user->data['username']);
			$messenger->headers('X-AntiAbuse: User IP - ' . $user->ip);
			$messenger->assign_vars(array( 'WELCOME_MSG' => htmlspecialchars_decode(sprintf($user->lang['WELCOME_SUBJECT'], $config['sitename'])), 'USERNAME' => htmlspecialchars_decode($data['username']), 'PASSWORD' => htmlspecialchars_decode($gen_password) ));
			$messenger->send(NOTIFY_EMAIL);
		}
		
		return $user_id;
	}
}

?>
