<?php
/**
 *
 * @package ACP User Notifications
 * @copyright (c) 2020 david63
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace david63\acpusernotifications\controller;

/**
 * @ignore
 */
use phpbb\user;
use phpbb\config\config;
use phpbb\auth\auth;
use phpbb\request\request;
use phpbb\template\template;
use phpbb\language\language;
use david63\acpusernotifications\core\functions;
use phpbb\notification\manager;
use phpbb\db\driver\driver_interface;
use phpbb\log\log;

/**
 * Event listener
 */
class acp_user_notify_controller
{
	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var string phpBB root path */
	protected $root_path;

	/** @var string PHP extension */
	protected $phpEx;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\language\language */
	protected $language;

	/** @var \david63\acpusernotifications\core\functions */
	protected $functions;

	/** @var \phpbb\notification\manager */
	protected $notification_manager;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\log\log */
	protected $log;

	/** @var string phpBB tables */
	protected $tables;

	/** @var string */
	protected $ext_images_path;

	/**
	 * Constructor
	 *
	 * @param \phpbb\user                                    $user                   User object
	 * @param \phpbb\config\config                           $config                 Config object
	 * @param \phpbb\auth\auth                               $auth                   Auth object
	 * @param \phpbb\request\request                         $request                Request object
	 * @param \phpbb\db\driver\driver_interface              $db                     The db connection
	 * @param string                                         $root_path              phpBB root path
	 * @param string                                         $php_ext                php ext
	 * @param \phpbb\template\template                       $template               Template object
	 * @param \phpbb\language\language                       $language               Language object
	 * @param \david63\acpusernotifications\core\functions   $functions              Functions for the extension
	 * @param \phpbb\notification\manager                    $notification_manager   Notification manager
	 * @param \phpbb\db\driver\driver_interface              $db                     The db connection
	 * @param array                                          $tables                 phpBB db tables
	 * @param \phpbb\log\log                                 $log                    Log object
	 * @param string                                         $ext_images_path        Path to this extension's images
	 *
	 * @return \david63\acpusernotifications\controller\acp_user_notify_controller
	 * @access public
	 */
	public function __construct(user $user, config $config, auth $auth, request $request, string $root_path, string $php_ext, template $template, language $language, functions $functions, manager $notification_manager, driver_interface $db, array $tables, log $log, string $ext_images_path)
	{
		$this->user                 = $user;
		$this->config               = $config;
		$this->auth                 = $auth;
		$this->request              = $request;
		$this->root_path            = $root_path;
		$this->phpEx                = $php_ext;
		$this->template             = $template;
		$this->language             = $language;
		$this->functions            = $functions;
		$this->notification_manager = $notification_manager;
		$this->db                   = $db;
		$this->tables               = $tables;
		$this->log                  = $log;
		$this->ext_images_path      = $ext_images_path;
	}

	/**
	 * Update a user's notification preferences
	 *
	 * @return   void
	 */
	public function acp_users_notify($event)
	{
		// Add the language files
		$this->language->add_lang(['acp_users_notify', 'acp_common'], $this->functions->get_ext_namespace());

		$user_id = $event['user_id'];

		// Create a form key for preventing CSRF attacks
		$form_key = 'acp_user_notify';
		add_form_key($form_key);

		$action = append_sid("{$this->root_path}adm/index.$this->phpEx" . '?i=acp_users&amp;mode=usernotify&amp;u=' . $user_id);

		// Because of the way the notification system is written, we need to change to the actual user in order
		// to retrieve the correct types and methods for the user being viewed - this is nothing more than a HACK :shock:
		$user_data     = $this->notify_change_user($user_id);
		$subscriptions = $this->notification_manager->get_global_subscriptions($user_id);

		$this->output_notification_methods();
		$this->output_notification_types($subscriptions);

		// We are in the ACP, have to have the auths for ACP stuff
		$user_data = $this->notify_change_user($user_id, 'restore', $user_data);

		// Add/remove subscriptions
		if ($this->request->is_set_post('submit'))
		{
			if (!check_form_key($form_key))
			{
				trigger_error('FORM_INVALID');
			}

			$notification_methods = $this->notification_manager->get_subscription_methods();

			foreach ($this->notification_manager->get_subscription_types() as $group => $subscription_types)
			{
				foreach ($subscription_types as $type => $data)
				{
					foreach ($notification_methods as $method => $method_data)
					{
						if ($this->request->is_set_post(str_replace('.', '_', $type . '_' . $method_data['id'])) && (!isset($subscriptions[$type]) || !in_array($method_data['id'], $subscriptions[$type])))
						{
							$this->notification_manager->add_subscription($type, 0, $method_data['id'], $user_id);
						}
						else if (!$this->request->is_set_post(str_replace('.', '_', $type . '_' . $method_data['id'])) && isset($subscriptions[$type]) && in_array($method_data['id'], $subscriptions[$type]))
						{
							$this->notification_manager->delete_subscription($type, 0, $method_data['id'], $user_id);
						}
					}
				}
			}

			// Add settings change action to the admin log and send updated message
			$this->log->add('admin', $this->user->data['user_id'], $this->user->ip, 'LOG_ACP_USER_NOTIFY', time(), [$event['user_row']['username']]);
			trigger_error($this->language->lang('NOTIFICATIONS_UPDATED') . adm_back_link($action));
		}

		$version_data	= $this->functions->version_check();
		$valid 			= $this->functions->ext_requirements();

		$this->template->assign_vars([
			'EXT_IMAGE_PATH' 	=> $this->ext_images_path,

			'NAMESPACE' 		=> $this->functions->get_ext_namespace('twig'),

			'PHP_VALID' 		=> $valid[0],
			'PHPBB_VALID' 		=> $valid[1],

			'S_VERSION_CHECK' 	=> (array_key_exists('current', $version_data)) ? $version_data['current'] : false,
			'S_ACP_USER_NOTIFY'	=> true,

			'U_ACTION' 			=> $action,

			'VERSION_NUMBER' 	=> $this->functions->get_meta('version'),
		]);
	}

	/**
	 * Output all the notification methods to the template
	 *
	 * @param string $block
	 */
	public function output_notification_methods()
	{
		$notification_methods = $this->notification_manager->get_subscription_methods();

		foreach ($notification_methods as $method => $method_data)
		{
			$this->template->assign_block_vars('notification_methods', [
				'METHOD' => $method_data['id'],
				'NAME' => $this->language->lang($method_data['lang']),
			]);
		}
	}

	/**
	 * Output all the notification types to the template
	 *
	 * @param array  $subscriptions Array containing global subscriptions
	 * @param string $block
	 */
	public function output_notification_types($subscriptions)
	{
		$notification_methods = $this->notification_manager->get_subscription_methods();

		foreach ($this->notification_manager->get_subscription_types() as $group => $subscription_types)
		{
			$this->template->assign_block_vars('notification_types', [
				'GROUP_NAME' => $this->language->lang($group),
			]);

			foreach ($subscription_types as $type => $type_data)
			{
				$this->template->assign_block_vars('notification_types', [
					'EXPLAIN' => (isset($this->language->lang[$type_data['lang'] . '_EXPLAIN'])) ? $this->language->lang($type_data['lang'] . '_EXPLAIN') : '',
					'NAME' => $this->language->lang($type_data['lang']),
					'TYPE' => $type,
				]);

				foreach ($notification_methods as $method => $method_data)
				{
					$this->template->assign_block_vars('notification_types' . '.notification_methods', [
						'AVAILABLE' => $method_data['method']->is_available($type_data['type']),
						'METHOD' => $method_data['id'],
						'NAME' => $this->language->lang($method_data['lang']),
						'SUBSCRIBED' => (isset($subscriptions[$type]) && in_array($method_data['id'], $subscriptions[$type])) ? true : false,
					]);
				}
			}
		}

		$this->template->assign_vars([
			strtoupper('notification_types') . '_COLS' => count($notification_methods) + 1,
		]);
	}

	/**
	 *
	 * Function by RMcGirr83
	 */

	/**
	 * Swap the Admin user for the actual user
	 *
	 * @param $user_id   The user id whose notification types we are looking at
	 * @param $mode      The mode either replace or restore
	 * @param $bkup_data An array of the current user's data
	 *
	 * Changes the user in the ACP to that of the user chosen in the ACP
	 */
	public function notify_change_user($user_id, $mode = 'replace', $bkup_data = false)
	{
		switch ($mode)
		{
			// Change our user to the one being viewed
			case 'replace':
				$bkup_data = ['user_backup' => $this->user->data];

				// Sql to get the user's info
				$sql = 'SELECT *
                    FROM ' . $this->tables['users'] . '
                    WHERE user_id = ' . (int) $user_id;

				$result = $this->db->sql_query($sql);
				$row    = $this->db->sql_fetchrow($result);

				$this->db->sql_freeresult($result);

				// Reset the current user's info to that of the notify user. We do this instead of just using the sql query
				// for items such as $this->user->data['is_registered'] which isn't a table column from the users table
				$this->user->data = array_merge($this->user->data, $row);

				// Reset the user's auths
				$this->auth->acl($this->user->data);

				unset($row);

				return $bkup_data;
				break;

			// Now we restore the user's stuff
			case 'restore':
				$this->user->data = $bkup_data['user_backup'];

				// Set the auths back to normal
				$this->auth->acl($this->user->data);

				unset($bkup_data);
				break;
		}
	}
}
