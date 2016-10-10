<?php
namespace fur\bright\entities;
/**
 * This class defines the User object, used by the Users classes
 * Version history:
 * 2.8 20140303:
 * - usergroupsstr is now always unset, isset returns false when usergroupsstr == NULL
 * 2.7 20120208:
 * - Deleted is now a date instead of a boolean
 * @author Ids Klijnsma - Fur
 * @version 2.8
 * @package Bright
 * @subpackage objects
 */
class OUserObject extends OPage {	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OUserObject';	
	
	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> userId = (int) $this -> userId;	
		$this -> activated = (int) $this -> activated == 1;	
		$this -> itemType = (int) $this -> itemType;
		$this -> registrationdate = (float) strtotime($this -> registrationdate);	
		$this -> lastlogin = (float) strtotime($this -> lastlogin);
		if(isset($this -> usergroupsstr)) {
			$this -> usergroups = explode(',', $this -> usergroupsstr);
			foreach($this -> usergroups as $group => $val) {
				settype($this -> usergroups[$group], 'int');
			}
		}
		unset($this -> usergroupsstr);
		parent::__construct();
	}
	
	/**
	 * @var int The id of the User
	 * @since 2.5 2011-10-20
	 */
	public $userId = 0;
	
	/**
	 * @var string The e-mailaddress
	 */
	public $email = '';
	/**
	 * @var string The password of the user (SHA1 encoded)
	 */
	public $password = '';
	/**
	 * @var int The UNIX-Timestamp of the registration date
	 */
	public $registrationdate;
	/**
	 * @var int The UNIX-Timestamp of the latest login
	 */
	public $lastlogin;
	/**
	 * @var array An array of permissions
	 * @deprecated 2.1 - 11 jun 2010, Use usergroups instead
	 */
	public $permissions = array();
	
	/**
	 * @var array Array of usergroupIds
	 */
	public $usergroups = array();
	
	/**
	 * @var boolean Indicated whether a user has activated his account (only activated users receive mailings and can login)
	 */
	public $activated;
	
	/**
	 * @since 2.4 - 6 jan 2011
	 * @since 2.7: now a datestring, null otherwise
	 * @var datetime indicates when the user is deleted
	 */
	public $deleted = null;
	
	/**
	 * @since 2.2 - 06 sep 2010
	 * @var string The activation code of the user
	 */
	public $activationcode;
	
	/**
	 * @since 2.6 - 23 nov 2011
	 * @var int The id of the template
	 */
	public $itemType;
	
	
}