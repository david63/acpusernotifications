<?php
/**
*
* @package ACP User Notifications
* @copyright (c) 2020 david63
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace david63\acpusernotifications\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


use david63\acpusernotifications\controller\acp_user_notify_controller;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	/** @var \david63\acpusernotifications\controller\acp_user_notify_controller */
	protected $acp_user_notify_controller;

   /**
	* Constructor for listener
	*
	* @param \david63\acpusernotifications\controller\acp_user_notify_controller 	$acp_user_controller	ACP User Controller
	*
	* @access public
	*/
	public function __construct(acp_user_notify_controller $acp_user_notify_controller)
	{
		$this->acp_user_notify_controller = $acp_user_notify_controller;
	}

	/**
	* Assign functions defined in this class to event listeners in the core
	*
	* @return array
	* @static
	* @access public
	*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.acp_users_mode_add' => 'aup_acp_users',
		);
	}

	/**
	* Process the ACP user data
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function aup_acp_users($event)
	{
		if ($event['mode'] == 'usernotify')
		{
			$this->acp_user_notify_controller->acp_users_notify($event);
		}
	}
}
