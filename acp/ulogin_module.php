<?php
/**
*
* @package ulogin
*
*/

namespace uloginteam\ulogin\acp;

class ulogin_module
{
	var $u_action;
	var $new_config = array();

	function main($id, $mode)
	{
		global $config, $request, $template, $user, $db;

		$this->page_title = 'ACP_ULOGIN_TITLE';
		$this->tpl_name = 'acp_ulogin';

		$submit = (isset($_POST['submit'])) ? true : false;
		$form_key = 'config_ulogin';
		add_form_key($form_key);
		$display_vars = array(
			'title'	=> 'ACP_ULOGIN_TITLE',
			'vars'	=> array(
				'legend1'	    => 'ACP_ULOGIN_ID',
				'ulogin_id1'	=> array('lang' => 'ULOGIN_ID1', 'validate' => 'string', 'type' => 'text:8:8', 'explain' => true),
				'ulogin_id2'	=> array('lang' => 'ULOGIN_ID2', 'validate' => 'string', 'type' => 'text:8:8', 'explain' => true),
				'legend2'	    => 'ACP_ULOGIN_GROUP',
				'ulogin_group_id' => array('lang' => 'ULOGIN_GROUP_ID', 'validate' => 'int', 'type' => 'custom', 'method' => 'get_groups', 'explain' => true),
				'legend3'	    => 'ACP_SUBMIT_CHANGES',
			),
		);

		if (isset($display_vars['lang']))
		{
			$user->add_lang($display_vars['lang']);
		}

		$this->new_config = $config;
		$cfg_array = (isset($_REQUEST['config'])) ? utf8_normalize_nfc($request->variable('config', array('' => ''), true)) : $this->new_config;
		$error = array();

		// We validate the complete config if wished
		validate_config_vars($display_vars['vars'], $cfg_array, $error);

		if ($submit && !check_form_key($form_key))
		{
			$error[] = $user->lang['FORM_INVALID'];
		}
		// Do not write values if there is an error
		if (sizeof($error))
		{
			$submit = false;
		}

		// We go through the display_vars to make sure no one is trying to set variables he/she is not allowed to...
		foreach ($display_vars['vars'] as $config_name => $null)
		{
			if (!isset($cfg_array[$config_name]) || strpos($config_name, 'legend') !== false)
			{
				continue;
			}

			$this->new_config[$config_name] = $config_value = $cfg_array[$config_name];

			if ($submit)
			{
				$config->set($config_name, $config_value);
			}
		}

		if ($submit)
		{
			trigger_error($user->lang['CONFIG_UPDATED'] . adm_back_link($this->u_action));
		}

		$this->page_title = $display_vars['title'];

		$template->assign_vars(array(
			'L_TITLE'			=> $user->lang[$display_vars['title']],
			'L_TITLE_EXPLAIN'	=> $user->lang[$display_vars['title'] . '_EXPLAIN'],

			'S_ERROR'			=> (sizeof($error)) ? true : false,
			'ERROR_MSG'			=> implode('<br />', $error),
		));

		// Output relevant page
		foreach ($display_vars['vars'] as $config_key => $vars)
		{
			if (!is_array($vars) && strpos($config_key, 'legend') === false)
			{
				continue;
			}

			if (strpos($config_key, 'legend') !== false)
			{
				$template->assign_block_vars('options', array(
						'S_LEGEND'		=> true,
						'LEGEND'		=> (isset($user->lang[$vars])) ? $user->lang[$vars] : $vars)
				);

				continue;
			}

			$type = explode(':', $vars['type']);

			$l_explain = '';
			if ($vars['explain'] && isset($vars['lang_explain']))
			{
				$l_explain = (isset($user->lang[$vars['lang_explain']])) ? $user->lang[$vars['lang_explain']] : $vars['lang_explain'];
			}
			else if ($vars['explain'])
			{
				$l_explain = (isset($user->lang[$vars['lang'] . '_EXPLAIN'])) ? $user->lang[$vars['lang'] . '_EXPLAIN'] : '';
			}

			$content = build_cfg_template($type, $config_key, $this->new_config, $config_key, $vars);

			if (empty($content))
			{
				continue;
			}

			$template->assign_block_vars('options', array(
					'KEY'			=> $config_key,
					'TITLE'			=> (isset($user->lang[$vars['lang']])) ? $user->lang[$vars['lang']] : $vars['lang'],
					'S_EXPLAIN'		=> $vars['explain'],
					'TITLE_EXPLAIN'	=> $l_explain,
					'CONTENT'		=> $content,
				)
			);

			unset($display_vars['vars'][$config_key]);
		}
	}



	// Select box for user groups
	function get_groups($value, $key = ''){
		global $user, $db;

		$sql = 'SELECT group_id, group_name, group_type, group_founder_manage
					FROM ' . GROUPS_TABLE . '
					ORDER BY group_type DESC, group_name ASC';
		$result = $db->sql_query($sql);

		$s_group_options = '';
		while ($row = $db->sql_fetchrow($result))
		{
			$s_group_options .= '<option' . (($row['group_type'] == GROUP_SPECIAL) ? ' class="sep"' : '') .
			                    ' value="' . $row['group_id'] . '"' .
			                    (($row['group_id'] == $value) ? ' selected="selected"' : '') .'>' .
			                    (($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name']) . '</option>';
		}
		$db->sql_freeresult($result);

		return '<select id="' . $key . '" name="config[' . $key . ']">' . $s_group_options . '</select>';
	}
}
