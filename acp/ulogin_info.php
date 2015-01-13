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
			'version'	=> '0.0.2',
			'modes'		=> array(
				'config_ulogin'		=> array('title' => 'ACP_ULOGIN_CONFIG', 'auth' => 'ext_uloginteam/ulogin && acl_a_board', 'cat' => array('ACP_ULOGIN_CONFIG')),
			),
		);
	}
}
