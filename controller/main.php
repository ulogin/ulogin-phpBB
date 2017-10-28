<?php

/**
 *
 * @package uLogin Extension
 *
 */

namespace uloginteam\ulogin\controller;

class main
{
	protected $u_data;
	protected $currentUserId;
	protected $isUserLogined;
	protected $doRedirect;
	protected $token;
	protected $redirect;


	/** @var \uloginteam\ulogin\core\model */
	protected $model;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\controller\helper */
	protected $helper;

	/** @var string */
	protected $root_path;
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth $auth                Auth object
	 * @param \phpbb\cache\service $cache           Cache object
	 * @param \phpbb\config\config $config          Config object
	 * @param \phpbb\db\driver\factory $db          Database object
	 * @param \phpbb\request\request $request       Request object
	 * @param \phpbb\template\template $template    Template object
	 * @param \phpbb\user $user                     User object
	 * @param \phpbb\controller\helper $helper      Controller helper object
	 * @param $root_path                            phpBB root path
	 * @param $php_ext                              phpEx
	 */

	public function __construct(
		\uloginteam\ulogin\core\model $model,
		\phpbb\auth\auth $auth,
		\phpbb\cache\service $cache,  // -
		\phpbb\config\config $config,
		\phpbb\db\driver\factory $db,  // -
		\phpbb\request\request $request,
		\phpbb\template\template $template,  // -
		\phpbb\user $user,
		\phpbb\controller\helper $helper,
		\phpbb\plupload\plupload $plupload,
		$root_path,
		$php_ext)
	{
		$this->model = $model;
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->helper = $helper;
		$this->plupload = $plupload;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;

		if (!class_exists('bbcode'))
		{
			include($this->root_path . 'includes/bbcode.' . $this->php_ext);
		}
		if (!function_exists('get_user_rank'))
		{
			include($this->root_path . 'includes/functions_display.' . $this->php_ext);
		}
	}



	/**
	 * login controller to be accessed with the URL /ulogin/login
	 */
	public function login()
	{

		$title = '';
		$msg = '';

		if ($this->request->is_ajax()) {
			$this->doRedirect = false;
		} else {
			$this->doRedirect = true;
		}

		$this->currentUserId = $this->user->data['user_id'];
		$this->isUserLogined = $this->currentUserId != ANONYMOUS ? true : false;

		if ($this->isUserLogined){
			$msg = $this->user->lang['ULOGIN_ADD_ACCOUNT_SUCCESS'];//'Аккаунт успешно добавлен';
		}

		$this->uloginLogin($title, $msg);

		if ($this->request->is_ajax()) {
			exit;
		}

		return;

	}

//-------------------------------------------------------------------------

	/**
	 * login controller to be accessed with the URL /ulogin/delete_account
	 */
	public function delete_account()
	{
		$this->currentUserId = $this->user->data['user_id'];
		$this->isUserLogined = $this->currentUserId != ANONYMOUS ? true : false;

		$this->deleteAccount();
		return;
	}



//==========================================================================

	protected function uloginLogin ($title = '', $msg = '') {
		$this->u_data = $this->uloginParseRequest();
		if ( !$this->u_data ) {
			return;
		}

		try {
			$u_user_db = $this->model->getUloginUserItem(array('identity' => $this->u_data['identity']));
			$user_id = 0;

			if ( $u_user_db ) {

				if ($this->model->checkUserId($u_user_db['user_id'])) {
					$user_id = $u_user_db['user_id'];
				}

				if ( intval( $user_id ) > 0 ) {
					if ( !$this->checkCurrentUserId( $user_id ) ) {
						// если $user_id != ID текущего пользователя
						return;
					}
				} else {
					// данные о пользователе есть в ulogin_table, но отсутствуют в users. Необходимо переписать запись в ulogin_table и в базе users.
					$user_id = $this->newUloginAccount( $u_user_db );
				}

			} else {
				// пользователь НЕ обнаружен в ulogin_table. Необходимо добавить запись в ulogin_table и в базе users.
				$user_id = $this->newUloginAccount();
			}

			// обновление данных и Вход
			if ( $user_id > 0 ) {
				$this->loginUser( $user_id );

				$networks = $this->model->getUloginUserNetworks( $user_id );
				$this->sendMessage( array(
					'title' => $title,
					'msg' => $msg,
					'networks' => $networks,
					'type' => 'success',
				) );
			}
			return;
		}

		catch (Exception $e){
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_DB_ERROR'],//"Ошибка при работе с БД.",
				'msg' => "Exception: " . $e->getMessage(),
				'type' => 'error'
			));
			return;
		}
	}


	/**
	 * Отправляет данные как ответ на ajax запрос, если код выполняется в результате вызова callback функции,
	 * либо добавляет сообщение в сессию для вывода в режиме redirect
	 * @param array $params
	 */
	protected function sendMessage ($params = array()) {
		$params = array(
			'title' => isset($params['title']) ? $params['title'] : '',
			'msg' => isset($params['msg']) ? $params['msg'] : '',
			'type' => isset($params['type']) ? $params['type'] : '',
			'script' => isset($params['script']) ? $params['script'] : '',
			'networks' => isset($params['networks']) ? $params['networks'] : '',
		);

		if ($this->doRedirect){
			$redirect = urldecode($this->request->variable('redirect', '', false, \phpbb\request\request_interface::GET));

			// append/replace SID (may change during the session for AOL users)
			if ($params['type'] == 'success') {
				$redirect = reapply_sid($redirect);
				redirect($redirect);
			}

			if ($params['type'] == 'error') {
				$type = E_USER_WARNING;
			} else {
				$type = E_USER_NOTICE;
			}

			$message = (!empty($params['title']) ? '<strong>' . $params['title']  . '</strong><br/>' : '') . $params['msg'];

			$message .= "<p><a href='$redirect' class='back-url'>&lt;- " . $this->user->lang['ULOGIN_BACK_URL'] . "</a></p>";

			if (!empty($params['script'])) {
				$token = !empty($params['script']['token']) ? $params['script']['token'] : '';
				$identity = !empty($params['script']['identity']) ? $params['script']['identity'] : '';
				$s = '';

                if  ($token && $identity) {
	                $s = "uLogin.mergeAccounts('$token', '$identity');";
                } else if ($token) {
	                $s = "uLogin.mergeAccounts('$token');";
                }

				if ($s) {
					$message .= "<script type=\"text/javascript\" src=\"//ulogin.ru/js/ulogin.js\"></script>" .
					            "<script type=\"text/javascript\">$s</script>";
				}
			}

			trigger_error($message, $type);

		} else {
			$json_response = new \phpbb\json_response();
			$json_response->send($params);
			exit;
		}
	}


	/**
	 * Добавление в таблицу uLogin
	 * @param $u_user_db - при непустом значении необходимо переписать данные в таблице uLogin
	 */
	protected function newUloginAccount($u_user_db = ''){
		$u_data = $this->u_data;

		if ($u_user_db) {
			// данные о пользователе есть в ulogin_user, но отсутствуют в users => удалить их
			$this->model->deleteUloginUser(array('id' => $u_user_db['id']));
		}

		$CMSuserId = $this->model->getUserIdByEmail($u_data['email']);

		// $emailExists == true -> есть пользователь с таким email
		$user_id = 0;
		$emailExists = false;
		if ($CMSuserId) {
			$user_id = $CMSuserId; // id юзера с тем же email
			$emailExists = true;
		}

		// $isUserLogined == true -> пользователь онлайн
		$currentUserId = $this->currentUserId;
		$isUserLogined = $this->isUserLogined;

		if (!$emailExists && !$isUserLogined) {
			// отсутствует пользователь с таким email в базе -> регистрация в БД
			$user_id = $this->regUser();
			$this->addUloginAccount($user_id);
		} else {
			// существует пользователь с таким email или это текущий пользователь
			if (intval($u_data["verified_email"]) != 1){
				// Верификация аккаунта

				$this->sendMessage(
					array(
						'title' => $this->user->lang['ULOGIN_VERIFY'],//'Подтверждение аккаунта.',
						'msg' => $this->user->lang['ULOGIN_VERIFY_TEXT'],
						'script' => array('token' => $this->token),
					)
				);
				return false;
			}

			$user_id = $isUserLogined ? $currentUserId : $user_id;

			$other_u = $this->model->getUloginUserItem(array(
				'user_id' => $user_id,
			));

			if ($other_u) {
				// Синхронизация аккаунтов
				if(!$isUserLogined && !isset($u_data['merge_account'])){
					$this->sendMessage(
						array(
							'title' => $this->user->lang['ULOGIN_SYNCH'],//'Синхронизация аккаунтов.',
							'msg' => $this->user->lang['ULOGIN_SYNCH_TEXT'],
							'script' => array('token' => $this->token, 'identity' => $other_u['identity']),
						)
					);
					return false;
				}
			}

			$this->addUloginAccount($user_id);
		}

		return $user_id;
	}



	/**
	 * Регистрация пользователя в БД users
	 * @return mixed
	 */
	protected function regUser(){

		$u_data = $this->u_data;
		$config = $this->config;
		$user = $this->user;
		$root_path = $this->root_path;
		$php_ext = $this->php_ext;
		global $phpbb_container;

		$u_data["verified_email"] = isset($u_data["verified_email"]) ? $u_data["verified_email"] : -1;


		if ($config['require_activation'] == USER_ACTIVATION_DISABLE
		    || ((
			        ($config['require_activation'] == USER_ACTIVATION_SELF && $u_data["verified_email"] == -1)
			        || $config['require_activation'] == USER_ACTIVATION_ADMIN)
		        && !$config['email_enable']))
		{
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_REG_ERROR'],//"Ошибка при регистрации.",
				'msg' => $user->lang['UCP_REGISTER_DISABLE'],
				'type' => 'error'
			));
			return false;
		}

		// DNSBL check
		if ($config['check_dnsbl'])
		{
			if (($dnsbl = $user->check_dnsbl('register')) !== false)
			{
				$this->sendMessage (array(
					'title' => $this->user->lang['ULOGIN_REG_ERROR'],//"Ошибка при регистрации.",
					'msg' => sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]),
					'type' => 'error'
				));
				return false;
			}
		}

		$group_id = $this->model->getGroupId();

		if ((
			    ($config['require_activation'] == USER_ACTIVATION_SELF && $u_data["verified_email"] == -1)
			    || $config['require_activation'] == USER_ACTIVATION_ADMIN)
		    && $config['email_enable'])
		{
			$user_type = USER_INACTIVE;
			$user_actkey = gen_rand_string(mt_rand(6, 10));
			$user_inactive_reason = INACTIVE_REGISTER;
			$user_inactive_time = time();
		}
		else
		{
			$user_type = USER_NORMAL;
			$user_actkey = '';
			$user_inactive_reason = 0;
			$user_inactive_time = 0;
		}

		// Instantiate passwords manager
		$passwords_manager = $phpbb_container->get('passwords.manager');

		$login = $this->generateNickname(
			isset($u_data['first_name']) ? $u_data['first_name'] : '',
			isset($u_data['last_name']) ? $u_data['last_name'] : '',
			isset($u_data['nickname']) ? $u_data['nickname'] : '',
			isset($u_data['bdate']) ? $u_data['bdate'] : ''
		);
		$password = md5($u_data['identity'].time().rand());
		$password = substr($password, 0, 12);

		$user_row = array(
			'username'				=> $login,
			'user_password'			=> $passwords_manager->hash($password),
			'user_email'			=> $u_data['email'],
			'user_birthday'         => isset($u_data['bdate']) ? date('d-m-Y', strtotime($u_data['bdate'])) : '',
			'group_id'				=> (int) $group_id,
			'user_timezone'			=> $this->config['board_timezone'],
			'user_lang'				=> basename($user->lang_name),
			'user_ip'				=> $user->ip,
			'user_regdate'			=> time(),
			'user_type'				=> $user_type,
			'user_actkey'			=> $user_actkey,
			'user_inactive_reason'	=> $user_inactive_reason,
			'user_inactive_time'	=> $user_inactive_time,
		);

		if ($config['new_member_post_limit'])
		{
			$user_row['user_new'] = 1;
		}


		// Register user...
		include_once($root_path . 'includes/functions_user.' . $php_ext);
		$user_id = user_add($user_row);

		// This should not happen, because the required variables are listed above...
		if ($user_id === false)
		{
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_REG_ERROR'],//"Ошибка при регистрации.",
				'msg' => $this->user->lang['ULOGIN_REG_ERROR_TEXT'],//"Произошла ошибка при регистрации пользователя.",
				'type' => 'error'
			));
			return false;
		}


		// Adds a user to the specified group
		include_once($root_path . 'includes/functions_convert.' . $php_ext);
		add_user_group($config['ulogin_group_id'], $user_id);


		if ($config['require_activation'] == USER_ACTIVATION_SELF
		    && $u_data["verified_email"] == -1
		    && $config['email_enable'])
		{
			$message = $user->lang['ACCOUNT_INACTIVE'];
			$email_template = 'user_welcome_inactive';
		}
		else if ($config['require_activation'] == USER_ACTIVATION_ADMIN
		         && $config['email_enable'])
		{
			$message = $user->lang['ACCOUNT_INACTIVE_ADMIN'];
			$email_template = 'admin_welcome_inactive';
		}
		else
		{
			$message = $user->lang['ACCOUNT_ADDED'];
			$email_template = 'user_welcome';
		}

		if ($config['email_enable'])
		{
			include_once($root_path . 'includes/functions_messenger.' . $php_ext);

			$messenger = new \messenger(false);

			$template_lang = basename($user->lang_name);
			$template_path = dirname(dirname(__FILE__)) . '/language/';
			$template_path .= $template_lang . '/email';

			if (!file_exists ($template_path . '/' . $email_template . '.txt')) {
				$template_lang = 'en';
				$template_path = dirname(dirname(__FILE__)) . '/language/';
				$template_path .= $template_lang . '/email';
			}

			$messenger->template($email_template, $template_lang, $template_path);

			$messenger->to($u_data['email'], $login);

			$messenger->anti_abuse_headers($config, $user);

			$server_url = generate_board_url();

			$messenger->assign_vars(array(
					'WELCOME_MSG'	=> htmlspecialchars_decode(sprintf($user->lang['WELCOME_SUBJECT'], $config['sitename'])),
					'USERNAME'		=> htmlspecialchars_decode($login),
					'PASSWORD'		=> htmlspecialchars_decode($password),
					'U_ACTIVATE'	=> "$server_url/ucp.$php_ext?mode=activate&u=$user_id&k=$user_actkey")
			);

			$messenger->send(NOTIFY_EMAIL);
		}

		if ($config['require_activation'] == USER_ACTIVATION_ADMIN)
		{
			$phpbb_notifications = $phpbb_container->get('notification_manager');
			$phpbb_notifications->add_notifications('notification.type.admin_activate_user', array(
				'user_id'		=> $user_id,
				'user_actkey'	=> $user_row['user_actkey'],
				'user_regdate'	=> $user_row['user_regdate'],
			));
		}

		if ($user_type == USER_INACTIVE)
		{
			$this->sendMessage (array(
				'title' => "",
				'msg' => $message,
				'type' => 'success'
			));
			return false;
		}

		return $user_id;
	}



	/**
	 * Добавление записи в таблицу ulogin_user
	 * @param $user_id
	 * @return bool
	 */
	protected function addUloginAccount($user_id){
		$user = $this->model->addUloginAccount(array(
			'user_id' => $user_id,
			'identity' => strval($this->u_data['identity']),
			'network' => $this->u_data['network'],
		));

		if (!$user) {
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
				'msg' => $this->user->lang['ULOGIN_ADD_ACCOUNT_ERROR'],//"Не удалось записать данные об аккаунте.",
				'type' => 'error'
			));
			return false;
		}

		return true;
	}



	/**
	 * Выполнение входа пользователя в систему по $user_id
	 * @param $u_user
	 * @param int $user_id
	 */
	protected function loginUser($user_id = 0){
		if(!$this->model->checkUserId($user_id)) {
			$this->sendMessage(
				array(
					'title' => '',
					'msg' => $this->user->lang['ULOGIN_AUTH_ERROR'],//'Произошла ошибка при авторизации.',
					'type' => 'error',
				)
			);
			return false;
		}

		$user = $this->user;
		$auth = $this->auth;
		$root_path = $this->root_path;
		$php_ext = $this->php_ext;
		$u_data = $this->u_data;

		//обновление данных
		$user_data = $this->model->getUserData($user_id);

		if (empty($user_data['user_birthday']) && isset($u_data['bdate'])) {
			$this->model->updateUserData(
				$user_id,
				array(
					'user_birthday' => date('d-m-Y', strtotime($u_data['bdate'])),
				));
		}

		$file_url = (!empty($u_data['photo_big']))
			? $u_data['photo_big']
			: (!empty( $u_data['photo'] ) ? $u_data['photo'] : '');

		// подгрузка аватара
		if (empty($user_data['user_avatar']) && !empty($file_url)){
			$this->model->uploadAvatar($file_url, $user_id);
		}


		if (!$this->isUserLogined) {
			$user->session_kill();
			$user->session_begin();
			$auth->acl($user->data);
			$user->setup('viewforum');

			$user->session_create($user_id);
		}

		return true;
	}



	/**
	 * Проверка текущего пользователя
	 * @param $user_id
	 */
	protected function checkCurrentUserId($user_id){
		$currentUserId = $this->currentUserId;
		if($this->isUserLogined) {
			if ($currentUserId == $user_id) {
				return true;
			}
			$this->sendMessage (
				array(
					'title' => '',
					'msg' => $this->user->lang['ULOGIN_ACCOUNT_NOT_AVAILABLE'],
					'type' => 'error',
				)
			);
			return false;
		}
		return true;
	}



	/**
	 * Обработка ответа сервера авторизации
	 */
	protected function uloginParseRequest(){

		$this->token = $this->request->variable('token', '', false, \phpbb\request\request_interface::POST);

		if (!$this->token) {
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
				'msg' => $this->user->lang['ULOGIN_NO_TOKEN_ERROR'],//"Не был получен токен uLogin.",
				'type' => 'error'
			));
			return false;
		}

		$s = $this->getUserFromToken();

		if (!$s){
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
				'msg' => $this->user->lang['ULOGIN_NO_USER_DATA_ERROR'],//"Не удалось получить данные о пользователе с помощью токена.",
				'type' => 'error'
			));
			return false;
		}

		$this->u_data = json_decode($s, true);

		if (!$this->checkTokenError()){
			return false;
		}

		return $this->u_data;
	}


	/**
	 * "Обменивает" токен на пользовательские данные
	 */
	protected function getUserFromToken() {
		$response = false;
		if ($this->token){
			$host = $this->request->variable('HTTP_HOST', '', false, \phpbb\request\request_interface::SERVER);
			$request = 'http://ulogin.ru/token.php?token=' . $this->token . '&host=' . $host;
			if(in_array('curl', get_loaded_extensions())){
				$c = curl_init($request);
				curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
				$response = curl_exec($c);
				curl_close($c);

			}elseif (function_exists('file_get_contents') && ini_get('allow_url_fopen')){
				$response = file_get_contents($request);
			}
		}
		return $response;
	}


	/**
	 * Проверка пользовательских данных, полученных по токену
	 */
	protected function checkTokenError(){
		if (!is_array($this->u_data)){
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
				'msg' => $this->user->lang['ULOGIN_WRONG_USER_DATA_ERROR'],//"Данные о пользователе содержат неверный формат.",
				'type' => 'error'
			));
			return false;
		}

		if (isset($this->u_data['error'])){
			$strpos = strpos($this->u_data['error'],'host is not');
			if ($strpos){
				$this->sendMessage (array(
					'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
					'msg' => sprintf($this->user->lang['ULOGIN_HOST_ADDRESS_ERROR'], sub($this->u_data['error'],intval($strpos)+12)),//"<i>ERROR</i>: адрес хоста не совпадает с оригиналом " . sub($this->u_data['error'],intval($strpos)+12),
					'type' => 'error'
				));
				return false;
			}
			switch ($this->u_data['error']){
				case 'token expired':
					$this->sendMessage (array(
						'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
						'msg' => $this->user->lang['ULOGIN_TOKEN_EXPIRED_ERROR'],//"<i>ERROR</i>: время жизни токена истекло",
						'type' => 'error'
					));
					break;
				case 'invalid token':
					$this->sendMessage (array(
						'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
						'msg' => $this->user->lang['ULOGIN_INVALID_TOKEN_ERROR'],//"<i>ERROR</i>: неверный токен",
						'type' => 'error'
					));
					break;
				default:
					$this->sendMessage (array(
						'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
						'msg' => "<i>ERROR</i>: " . $this->u_data['error'],
						'type' => 'error'
					));
			}
			return false;
		}
		if (!isset($this->u_data['identity'])){
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
				'msg' => sprintf($this->user->lang['ULOGIN_NO_VARIABLE_ERROR'], 'identity'),//"В возвращаемых данных отсутствует переменная <b>identity</b>.",
				'type' => 'error'
			));
			return false;
		}
		if (!isset($this->u_data['email'])){
			$this->sendMessage (array(
				'title' => $this->user->lang['ULOGIN_AUTH_ERROR'],//"Произошла ошибка при авторизации.",
				'msg' => sprintf($this->user->lang['ULOGIN_NO_VARIABLE_ERROR'], 'email'),//"В возвращаемых данных отсутствует переменная <b>email</b>",
				'type' => 'error'
			));
			return false;
		}
		return true;
	}


	/**
	 * Гнерация логина пользователя
	 * в случае успешного выполнения возвращает уникальный логин пользователя
	 * @param $first_name
	 * @param string $last_name
	 * @param string $nickname
	 * @param string $bdate
	 * @param array $delimiters
	 * @return string
	 */
	protected function generateNickname($first_name, $last_name="", $nickname="", $bdate="", $delimiters=array('.', '_')) {
		$delim = array_shift($delimiters);

		$first_name = $this->translitIt($first_name);
		$first_name_s = substr($first_name, 0, 1);

		$variants = array();
		if (!empty($nickname))
			$variants[] = $nickname;
		$variants[] = $first_name;
		if (!empty($last_name)) {
			$last_name = $this->translitIt($last_name);
			$variants[] = $first_name.$delim.$last_name;
			$variants[] = $last_name.$delim.$first_name;
			$variants[] = $first_name_s.$delim.$last_name;
			$variants[] = $first_name_s.$last_name;
			$variants[] = $last_name.$delim.$first_name_s;
			$variants[] = $last_name.$first_name_s;
		}
		if (!empty($bdate)) {
			$date = explode('.', $bdate);
			$variants[] = $first_name.$date[2];
			$variants[] = $first_name.$delim.$date[2];
			$variants[] = $first_name.$date[0].$date[1];
			$variants[] = $first_name.$delim.$date[0].$date[1];
			$variants[] = $first_name.$delim.$last_name.$date[2];
			$variants[] = $first_name.$delim.$last_name.$delim.$date[2];
			$variants[] = $first_name.$delim.$last_name.$date[0].$date[1];
			$variants[] = $first_name.$delim.$last_name.$delim.$date[0].$date[1];
			$variants[] = $last_name.$delim.$first_name.$date[2];
			$variants[] = $last_name.$delim.$first_name.$delim.$date[2];
			$variants[] = $last_name.$delim.$first_name.$date[0].$date[1];
			$variants[] = $last_name.$delim.$first_name.$delim.$date[0].$date[1];
			$variants[] = $first_name_s.$delim.$last_name.$date[2];
			$variants[] = $first_name_s.$delim.$last_name.$delim.$date[2];
			$variants[] = $first_name_s.$delim.$last_name.$date[0].$date[1];
			$variants[] = $first_name_s.$delim.$last_name.$delim.$date[0].$date[1];
			$variants[] = $last_name.$delim.$first_name_s.$date[2];
			$variants[] = $last_name.$delim.$first_name_s.$delim.$date[2];
			$variants[] = $last_name.$delim.$first_name_s.$date[0].$date[1];
			$variants[] = $last_name.$delim.$first_name_s.$delim.$date[0].$date[1];
			$variants[] = $first_name_s.$last_name.$date[2];
			$variants[] = $first_name_s.$last_name.$delim.$date[2];
			$variants[] = $first_name_s.$last_name.$date[0].$date[1];
			$variants[] = $first_name_s.$last_name.$delim.$date[0].$date[1];
			$variants[] = $last_name.$first_name_s.$date[2];
			$variants[] = $last_name.$first_name_s.$delim.$date[2];
			$variants[] = $last_name.$first_name_s.$date[0].$date[1];
			$variants[] = $last_name.$first_name_s.$delim.$date[0].$date[1];
		}
		$i=0;

		$exist = true;
		while (true) {
			if ($exist = $this->userExist($variants[$i])) {
				foreach ($delimiters as $del) {
					$replaced = str_replace($delim, $del, $variants[$i]);
					if($replaced !== $variants[$i]){
						$variants[$i] = $replaced;
						if (!$exist = $this->userExist($variants[$i]))
							break;
					}
				}
			}
			if ($i >= count($variants)-1 || !$exist)
				break;
			$i++;
		}

		if ($exist) {
			while ($exist) {
				$nickname = $first_name.mt_rand(1, 100000);
				$exist = $this->userExist($nickname);
			}
			return $nickname;
		} else
			return $variants[$i];
	}


	/**
	 * Проверка существует ли пользователь с заданным логином
	 */
	protected function userExist($login){
		if (!$this->model->checkUserName(strtolower($login))){
			return false;
		}
		return true;
	}


	/**
	 * Транслит
	 */
	protected function translitIt($str) {
		$tr = array(
			"А"=>"a","Б"=>"b","В"=>"v","Г"=>"g",
			"Д"=>"d","Е"=>"e","Ж"=>"j","З"=>"z","И"=>"i",
			"Й"=>"y","К"=>"k","Л"=>"l","М"=>"m","Н"=>"n",
			"О"=>"o","П"=>"p","Р"=>"r","С"=>"s","Т"=>"t",
			"У"=>"u","Ф"=>"f","Х"=>"h","Ц"=>"ts","Ч"=>"ch",
			"Ш"=>"sh","Щ"=>"sch","Ъ"=>"","Ы"=>"yi","Ь"=>"",
			"Э"=>"e","Ю"=>"yu","Я"=>"ya","а"=>"a","б"=>"b",
			"в"=>"v","г"=>"g","д"=>"d","е"=>"e","ж"=>"j",
			"з"=>"z","и"=>"i","й"=>"y","к"=>"k","л"=>"l",
			"м"=>"m","н"=>"n","о"=>"o","п"=>"p","р"=>"r",
			"с"=>"s","т"=>"t","у"=>"u","ф"=>"f","х"=>"h",
			"ц"=>"ts","ч"=>"ch","ш"=>"sh","щ"=>"sch","ъ"=>"y",
			"ы"=>"y","ь"=>"","э"=>"e","ю"=>"yu","я"=>"ya"
		);
		if (preg_match('/[^A-Za-z0-9\_\-]/', $str)) {
			$str = strtr($str,$tr);
			$str = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', $str);
		}
		return $str;
	}



	/**
	 * Удаление привязки к аккаунту соцсети в таблице ulogin_user для текущего пользователя
	 */
	protected function deleteAccount() {
		if (!$this->request->is_ajax()) {
			$redirect = "{$this->root_path}index.$this->php_ext";
			$redirect = append_sid($redirect);
			redirect($redirect);
		}

		if(!$this->isUserLogined) {exit;}

		$user_id = $this->currentUserId;

		$network = $this->request->variable('network', '', false, \phpbb\request\request_interface::POST);

		if ($user_id > 0 && $network != '') {
			try {
				$this->model->deleteUloginUser( array('user_id' => $user_id, 'network' => $network) );
				$json_response = new \phpbb\json_response();
				$json_response->send(array(
					'title' => '',
					'msg' => sprintf($this->user->lang['ULOGIN_DELETE_ACCOUNT_SUCCESS'], $network), //"Удаление аккаунта $network успешно выполнено",
					'type' => 'success'
				));
				exit;
			} catch (Exception $e) {
				$json_response = new \phpbb\json_response();
				$json_response->send(array(
					'title' => $this->user->lang['ULOGIN_DELETE_ACCOUNT_ERROR'], //"Ошибка при удалении аккаунта",
					'msg' => "Exception: " . $e->getMessage(),
					'type' => 'error'
				));
				exit;
			}
		}
		exit;
	}
}