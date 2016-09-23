<?php
namespace fur\bright\entities;

/**
 * This class defines the Aministrator object, used by the administrator classes
 * @author Fur
 * @version 2.4
 * @package Bright
 * @subpackage objects
 */
class OAdministratorObject {

	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OAdministratorObject';

	/**
	 * @var int The id of the Administrator
	 */
	public $id = 0;
	/**
	 * @var string The administratorname
	 */
	public $name = '';
	/**
	 * @var string The emailaddress
	 */
	public $email = '';
	/**
	 * @var string The SHA1-hashed password (but most of the times empty, only set when updating the administrator)
	 */
	public $password = '';
	/**
	 * @var string The sessionId of the currently logged in administrator, used for uploading
	 * @since 2.1 - 26 mrt 2010
	 */
	public $sessionId = '';

	/**
	 * @var array An array of permissions. For available permissions, see the Permissions class
	 */
	public $permissions = array();

	/**
	 *
	 * @var Object An object holding the settings of the administrator (default sort, visible columns, default map mode)
	 * @since 2.4 - 10 oct 2011
	 */
	public $settings;
}