<?php

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}


$lang = array_merge($lang, array(
	'TITLE'                  => 'Социальные сети',
	'ADD_ACCOUNT'            => 'Социальные сети',
	'ADD_ACCOUNT_EXPLAIN'    => 'Привяжите аккаунты соцсетей, кликнув по значку',
	'DELETE_ACCOUNT'         => 'Привязанные аккаунты',
	'DELETE_ACCOUNT_EXPLAIN' => 'Удалите привязку к аккаунту, кликнув по значку',
	'UCP_ULOGIN_USER_PANEL'  => 'Социальные сети',
));