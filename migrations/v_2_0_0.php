<?php
/**
 *
 * @package ulogin
 *
 */

namespace uloginteam\ulogin\migrations;

class v_2_0_0 extends \phpbb\db\migration\migration
{
	private $ulogin_group_id;

	public function effectively_installed()
	{
		return isset($this->config['ulogin_version']) && version_compare($this->config['ulogin_version'], '2.0.0', '>=');
	}

	static public function depends_on()
	{
			return array('\phpbb\db\migration\data\v310\dev');
	}

	public function update_schema()
	{
		if (!$this->db_tools->sql_table_exists($this->table_prefix . 'ulogin'))
		{
			return array(
				'add_tables'	=> array(
					$this->table_prefix . 'ulogin'	=> array(
						'COLUMNS'	=> array(
							'id'						=> array('UINT', null, 'auto_increment'),
							'user_id'					=> array('UINT', null),
							'identity'					=> array('VCHAR', ''),
							'network'					=> array('VCHAR:50', ''),
						),
						'PRIMARY_KEY'	=> 'id',
						'INDEX'	=> array('user_id', 'identity'),
					),
				),
			);
		}

		return array();
	}

	public function revert_schema()
	{
		return array();
	}

	public function update_data()
	{
		// Add user group
		$ulogin_group_id = $this->insert_ulogin_group();

		return array(
			// Add configs
			array('config.add', array('ulogin_id1', '')),
			array('config.add', array('ulogin_id2', '')),
			array('config.add', array('ulogin_group_id', $this->ulogin_group_id)),

			// Current version
			array('config.add', array('ulogin_version', '2.0.0')),

			// Add ACP modules
			array('module.add', array('acp', 'ACP_CAT_DOT_MODS', 'ACP_ULOGIN')),
			array('module.add', array('acp', 'ACP_ULOGIN', array(
					'module_basename'	=> '\uloginteam\ulogin\acp\ulogin_module',
					'module_langname'	=> 'ACP_ULOGIN_EXPLAIN',
					'module_mode'		=> 'config_ulogin',
					'module_auth'		=> 'acl_a_board',
			))),


			array('module.add', array('ucp', 'UCP_PROFILE', array(
				'module_basename'	=> '\uloginteam\ulogin\ucp\ulogin_module',
				'module_langname'	=> 'UCP_ULOGIN_USER_PANEL',
				'module_mode'		=> 'user_panel',
				'module_auth'		=> '',
			))),
		);
	}


	// Add user group
	public function insert_ulogin_group(){

		$sql = "SELECT group_id
					FROM " . GROUPS_TABLE . "
					WHERE group_name = 'REGISTERED_ULOGIN'";

		$result = $this->db->sql_query($sql);
		if ($result->num_rows) {
			$row = $this->db->sql_fetchrow($result);
			$this->ulogin_group_id = $row['group_id'];
			$this->db->sql_freeresult();
			return;
		}

		$this->db->sql_freeresult();
		$sql = "SELECT *
					FROM " . GROUPS_TABLE . "
					WHERE group_name = 'REGISTERED'";

		$result = $this->db->sql_query($sql);
		$row = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult();

		unset($row['group_id']);
		$row['group_name'] = 'REGISTERED_ULOGIN';

		$sql = 'INSERT INTO ' . GROUPS_TABLE . ' ' . $this->db->sql_build_array('INSERT', $row);
		$this->db->sql_query($sql);

		$res = $this->db->sql_nextid();

		$this->ulogin_group_id = $res > 0 ? $res : 0;
	}
}
