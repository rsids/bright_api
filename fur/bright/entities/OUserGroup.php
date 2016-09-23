<?php
namespace fur\bright\entities;
/**
 * This class defines the UserGroup object, used by the Users classes
 * @author Ids Klijnsma - Fur
 * @version 1.0
 * @package Bright
 * @subpackage objects
 */
class OUserGroup  {	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OUserGroup';	
	
	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> groupId = (int) $this -> groupId;	
	}
	
	/**
	 * @var int The id of the Usergroup
	 */
	public $groupId = 0;
	
	/**
	 * @var string The name of the group
	 */
	public $groupname = '';
	
}