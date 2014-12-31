<?php
/**
*
* @package ulogin
*
*/

namespace uloginteam\ulogin\acp;

class ulogin_info
{
	function module()
	{
		return array(
			'filename'	=> '\uloginteam\ulogin\acp\ulogin_module',
			'title'		=> 'ACP_ULOGIN',
			'version'	=> '0.0.1',
			'modes'		=> array(
				'config_ulogin'		=> array('title' => 'ACP_ULOGIN_CONFIG', 'auth' => 'acl_a_board', 'cat' => array('ACP_ULOGIN_CONFIG')),
			),
		);
	}
}
