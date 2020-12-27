<?php
/**
 *
 * @package ACP User Notifications
 * @copyright (c) 2020 david63
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace david63\acpusernotifications\migrations;

use phpbb\db\migration\migration;

class acp_modules extends migration
{
	/**
	 * Add the ACP module
	 *
	 * @return array Array update data
	 * @access public
	 */
	public function update_data()
	{
		return [
			['module.add', [
				'acp',
				'ACP_CAT_USERS',
				[
					'module_basename'	=> 'acp_users',
					'module_langname'	=> 'USER_NOTIFICATIONS',
					'module_mode'		=> 'usernotify',
					'module_display'	=> false,
					'module_auth'		=> 'ext_david63/acpusernotifications && acl_a_user',
				],
			]],
		];
	}
}
