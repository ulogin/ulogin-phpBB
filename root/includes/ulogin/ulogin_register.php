<?php

/** 
 * Auth via uLogin.ru
 * @package phpBB
 * @subpackage uLogin MOD
 * @author uLogin team@ulogin.ru http://ulogin.ru/
 * @license GPL3
 */

if (!defined('IN_PHPBB'))
{
	exit();
}

class ulogin_register
{
	function main($id, $mode)
	{
		global $config, $db, $user, $table_prefix, $auth, $template, $phpbb_root_path, $phpEx;
		
		define('TABLE_PREFIX', $table_prefix);

		require_once('class_ulogin.php');
		
		$uLogin = new uLogin($db);
		
		if ($config['require_activation'] == USER_ACTIVATION_DISABLE)
		{
			trigger_error('UCP_REGISTER_DISABLE');
		}
		
		if (!$user_id = $uLogin->auth())
		{
			$user_id = $uLogin->register();
		}

        if ($user_id){

		    $session = $user->session_create($user_id, 0, 1);

        }
		
		if (!$session)
		{
			page_header($user->lang['LOGIN'], false);
			$template->set_filenames(array('body' => 'login_body.html'));
			make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));
			page_footer();
			exit();
		}
		
		$redirect = request_var('redirect', "{$phpbb_root_path}index.$phpEx");
		$message = $user->lang['LOGIN_REDIRECT'];
		$l_redirect = (($redirect === "{$phpbb_root_path}index.$phpEx" || $redirect === "index.$phpEx") ? $user->lang['RETURN_INDEX'] : $user->lang['RETURN_PAGE']);
		$redirect = reapply_sid($redirect);
		
		if (defined('IN_CHECK_BAN') && $session['user_row']['user_type'] != USER_FOUNDER)
		{
			return false;
		}
		
		$redirect = meta_refresh(3, $redirect);
		trigger_error($message . '<br /><br />' . sprintf($l_redirect, '<a href="' . $redirect . '">', '</a>'));
	}
}

?>
