<?php
/**
*
* @package ulogin
*
*/

namespace uloginteam\ulogin\ucp;

class ulogin_module
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $config, $request, $template, $user, $db, $table_prefix;

		define('ULOGIN_TABLE', $table_prefix.'ulogin');

		$this->page_title = 'TITLE';
		$this->tpl_name = 'user_panel';


		$user_id = $user->data['user_id'];

		if ($user_id == ANONYMOUS) {
			return false;
		}

		$sql = "SELECT network
				FROM " . ULOGIN_TABLE . "
				WHERE user_id = {$user_id}";

		$result = $db->sql_query($sql);

		if (!$result) {
			$db->sql_freeresult();
			return false;
		}

		while ($row = $db->sql_fetchrow($result))
		{
			$template->assign_block_vars('networks', array(
					'NETWORK' => $row["network"],
				)
			);
		}

		$db->sql_freeresult();
		return true;
	}
}
