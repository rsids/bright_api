<?php
namespace fur\bright;
use fur\bright\core\Connection;
use fur\bright\core\Log;
use fur\bright\entities\OAdministratorObject;

/**
 * Base class of the Bright backend. Almost every class extends this class, mostly to check the permissions
 * @author Fur
 * @version 1.7
 * @package Bright
 */
class Permissions {
	
	/**
	 * @var boolean Indicates whether the user is authenticated
	 */
	protected $IS_AUTH = false;
	/**
	 * @var boolean Indicates whether the administrator may create, update and delete other administrators
	 */
	protected $MANAGE_ADMIN = false;
	/**
	 * @var boolean Indicates whether the administrator may create or delete users
	 */
	protected $MANAGE_USER = false;
	/**
	 * @var boolean When true, a administrator can create new content
	 */
	protected $CREATE_PAGE = false;
	/**
	 * @var boolean This permission is needed to delete pages (from both tree and from database)
	 */
	protected $DELETE_PAGE = false;
	/**
	 * @var boolean Indicates whether a administrator can edit existing content
	 */
	protected $EDIT_PAGE = false;
	/**
	 * @var boolean Indicates whether a administrator may move pages in the tree
	 */
	protected $MOVE_PAGE = false;
	/**
	 * @var boolean Indicates whether a administrator can upload files to the server
	 */
	protected $UPLOAD_FILE = false;
	/**
	 * @var boolean Indicates whether a administrator can delete files from the server. Only files uploaded with the cms can be deleted
	 */
	protected $DELETE_FILE = false;
	/**
	 * @var boolean Indicates whether a administrator can edit templates. Only developers should have this permission
	 */
	protected $MANAGE_TEMPLATE = false;
	 /**
	 * @var boolean Indicates whether a administrator can edit settings
	 * @since 1.2 - 19 feb 2010
	 */
	protected $MANAGE_SETTINGS = false;
	/**
	 * @var boolean Indicates whether a administrator is allowed to create and send mailings
	 * @since 1.3 - 23 jun 2010
	 */
	protected $MANAGE_MAILINGS = false;

	/**
	 * @var boolean Indicates whether a administrator is allowed to create and update calendars
	 * @since 1.4 - 19 oct 2010
	 */
	protected $MANAGE_CALENDARS = false;
	/**
	 * @var boolean Indicates whether a administrator is allowed to create and update elements
	 * @since 1.4 - 29 oct 2010
	 */
	protected $MANAGE_ELEMENTS = false;
	/**
	 * @var boolean Indicates whether a administrator is allowed to create and update maps
	 * @since 1.5 - 18 nov 2010
	 */
	protected $MANAGE_MAPS = false;
	
	/**
	 * @var array An indexbased array of exceptions. Call throwExceptions with the corresponding ID to access it.
	 * @version 1.1
	 */
	private $_exceptions = array();

	function __construct() {

		if(isset($_SESSION['IS_AUTH']) && $_SESSION['IS_AUTH'] == true) $this -> IS_AUTH = true;
		if(isset($_SESSION['MANAGE_ADMIN']) && $_SESSION['MANAGE_ADMIN'] == true) $this -> MANAGE_ADMIN = true;
		if(isset($_SESSION['MANAGE_USER']) && $_SESSION['MANAGE_USER'] == true) $this -> MANAGE_USER = true;
		if(isset($_SESSION['CREATE_PAGE']) && $_SESSION['CREATE_PAGE'] == true) $this -> CREATE_PAGE = true;
		if(isset($_SESSION['DELETE_PAGE']) && $_SESSION['DELETE_PAGE'] == true) $this -> DELETE_PAGE = true;
		if(isset($_SESSION['EDIT_PAGE']) && $_SESSION['EDIT_PAGE'] == true) $this -> EDIT_PAGE = true;
		if(isset($_SESSION['MOVE_PAGE']) && $_SESSION['MOVE_PAGE'] == true) $this -> MOVE_PAGE = true;
		if(isset($_SESSION['DELETE_FILE']) && $_SESSION['DELETE_FILE'] == true) $this -> DELETE_FILE = true;
		if(isset($_SESSION['MANAGE_TEMPLATE']) && $_SESSION['MANAGE_TEMPLATE'] == true) $this -> MANAGE_TEMPLATE = true;
		if(isset($_SESSION['UPLOAD_FILE']) && $_SESSION['UPLOAD_FILE'] == true) $this -> UPLOAD_FILE = true;
		if(isset($_SESSION['MANAGE_SETTINGS']) && $_SESSION['MANAGE_SETTINGS'] == true) $this -> MANAGE_SETTINGS = true;
		if(isset($_SESSION['MANAGE_MAILINGS']) && $_SESSION['MANAGE_MAILINGS'] == true) $this -> MANAGE_MAILINGS = true;
		if(isset($_SESSION['MANAGE_CALENDARS']) && $_SESSION['MANAGE_CALENDARS'] == true) $this -> MANAGE_CALENDARS = true;
		if(isset($_SESSION['MANAGE_ELEMENTS']) && $_SESSION['MANAGE_ELEMENTS'] == true) $this -> MANAGE_ELEMENTS = true;
		if(isset($_SESSION['MANAGE_MAPS']) && $_SESSION['MANAGE_MAPS'] == true) $this -> MANAGE_MAPS = true;
		
		$ar = array();
		$ar[1000] = 'An error occurred';
		
		$ar[1001] = 'No administrator was authenticated';
		$ar[1002] = 'You are not allowed to create administrators';
		$ar[1003] = 'Could not insert the administrator into the database';
		$ar[1004] = 'You cannot delete yourself';
		$ar[1005] = 'You are not allowed to update administrator accounts';
		$ar[1006] = 'A administrator with that e-mail address already exists';
		
		$ar[2001] = 'No slashes allowed';
		$ar[2002] = 'Incorrect parameter type #0, must be an integer';
		$ar[2003] = 'Incorrect parameter type #0, must be a string';
		$ar[2004] = 'Incorrect parameter type #0, must be a double';
		$ar[2005] = 'Incorrect parameter type #0, must be a boolean';
		$ar[2006] = 'Incorrect parameter type #0, must be a valid e-mail address';
		$ar[2007] = 'Incorrect parameter type #0, must be an array';
		$ar[2008] = 'Incorrect parameter type #0, must be an object';
		
		$ar[3001] = 'Unknown variable';
		$ar[3002] = 'You are not allowed to manage settings';
		
		$ar[4001] = 'Folder not found';
		$ar[4002] = 'Parent folder not found';
		$ar[4003] = 'Could not create folder. (Duplicate name?)';
		$ar[4004] = 'Could not delete folder. Is it empty?';
		$ar[4005] = 'File not found';
		$ar[4006] = 'A file with the same name already exists in the target folder';
		$ar[4007] = 'You are not allowed to delete files';
		$ar[4008] = 'Could not create folder.';
		
		$ar[5001] = 'You are not allowed to delete pages';
		$ar[5002] = 'You are not allowed to remove pages';
		$ar[5003] = 'Cannot delete this page since it\'s still present in the navigation tree:' . "\n- #0";
		$ar[5004] = 'Backup not found';
		
		$ar[6001] = 'The selected node has a maximum of #0 children.';
		$ar[6002] = 'Cannot unlock this node because it has locked children.';
		$ar[6003] = 'Cannot add the page, it already exists in the target node';
		$ar[6004] = 'The object must have a title';
		$ar[6005] = 'Cannot create sitemap';
	
		$ar[7001] = 'You are not allowed to edit templates.';
		$ar[7002] = 'You are not allowed to set the maximum children.';
		$ar[7003] = 'You are not allowed to set the lifetime of a template.';
		$ar[7004] = 'A template with that name already exists.';
		$ar[7005] = 'Invalid template name.';
		$ar[7006] = 'Failed to insert the template.';
		$ar[7007] = 'Cannot delete the template, it\'s still in use by some pages.';
		$ar[7008] = 'The page must be based on a Mail Template';
		$ar[7009] = 'The plugin directory could not be found. It should be in bright/cms/assets/plugins/';
		$ar[7010] = 'Template #0 not found.';
		
		$ar[8001] = 'You are not allowed to manage users';
		$ar[8002] = 'A user with that e-mail address already exists';
		$ar[8003] = 'Could not insert the user into the database';
		$ar[8004] = 'No user was authenticated';
		$ar[8005] = 'The usergroup already exists';
		$ar[8006] = 'Missing property \'groupname\'';
		$ar[8007] = 'Cannot open csv file';
		$ar[8008] = 'Invalid csv file';
		$ar[8009] = 'No user was found';
		
		
		$ar[9001] = 'The class #0 does not exist';
		$ar[9002] = 'The method #0 does not exist in class #1';
		$ar[9003] = 'The Swift mailing package is not installed on this server, include the Swift package in the /bright/externallibs/Swift folder';
		$ar[9004] = 'The TCPDF package is not installed on this server, include the TCPDF package in the /bright/externallibs/tcpdf folder';
		$ar[9005] = 'The Sphider search package is not installed on this server, include the Sphider search package in the /bright/externallibs/sphider folder';
		
		$this -> _exceptions = $ar;
	}
	
	/**
	 * Returns an array with the permissions of the administrator
	 * @return array An array with permissions
	 */
	protected function getPermissions() {
		$permissions = array();
		if($this -> IS_AUTH) $permissions[] = 'IS_AUTH';
		if($this -> MANAGE_ADMIN) $permissions[] = 'MANAGE_ADMIN';
		if($this -> MANAGE_USER) $permissions[] = 'MANAGE_USER';
		if($this -> CREATE_PAGE) $permissions[] = 'CREATE_PAGE';
		if($this -> DELETE_PAGE) $permissions[] = 'DELETE_PAGE';
		if($this -> EDIT_PAGE) $permissions[] = 'EDIT_PAGE';
		if($this -> MOVE_PAGE) $permissions[] = 'MOVE_PAGE';
		if($this -> DELETE_FILE) $permissions[] = 'DELETE_FILE';
		if($this -> MANAGE_TEMPLATE) $permissions[] = 'MANAGE_TEMPLATE';
		if($this -> MANAGE_SETTINGS) $permissions[] = 'MANAGE_SETTINGS';
		if($this -> UPLOAD_FILE) $permissions[] = 'UPLOAD_FILE';
		if($this -> MANAGE_MAILINGS) $permissions[] = 'MANAGE_MAILINGS';
		if($this -> MANAGE_CALENDARS) $permissions[] = 'MANAGE_CALENDARS';
		if($this -> MANAGE_ELEMENTS) $permissions[] = 'MANAGE_ELEMENTS';
		if($this -> MANAGE_MAPS) $permissions[] = 'MANAGE_MAPS';
		
		return $permissions;
	}
	
	/**
	 * Authenticates a administrator by e-mail and password
	 * @param string $email The e-mail address of the administrator
	 * @param string $password An SHA1 hash of the password
	 * @return array The rows with matching administratorid's
	 */
	protected function auth($email,$password) {
		
		$query = "SELECT u.*, up.permission " .
				"FROM administrators u, administratorpermissions up " .
				"WHERE u.email = '" . Connection::getInstance() -> escape_string($email) . "' " .
				"AND u.password = '" . Connection::getInstance() -> escape_string($password) . "' " .
				'AND u.id = up.administratorId';
		$co = Connection::getInstance();
		
		$result = $co -> getRows($query);
		return $result;
	}
	
	/**
	 * Sets the permissions of a administrator
	 * @param array $permissions An array containing the permissions of a administrator
	 */
	private function _setPermissions($permissions) {
		foreach($permissions as $permission) {
			$this -> $permission = $_SESSION[$permission] = true;		
		}
	}
	
	/**
	 * Places the administrator in the session
	 * @param OAdministratorObject $administrator The administrator to set
	 */
	protected function setAdministrator(OAdministratorObject $administrator) {
		$_SESSION['administratorId'] = $administrator -> id;
		$this -> _setPermissions($administrator -> permissions);
	}
	
	/**
	 * Gets the currently authenticated administrator
	 * @return OAdministratorObject The authenticated administrator
	 */
	public function getAdministrator() {
		$administrator = new OAdministratorObject();
		if(isset( $_SESSION['administratorId']))
			$administrator -> id = $_SESSION['administratorId'];
		$administrator -> sessionId = session_id();
		$administrator -> permissions = $this -> getPermissions();
		return $administrator;
	}
	
	/**
	 * Gets the settings for the currently authenticated administrator
	 * @return Object
	 */
	public function getSettings() {
		if(!isset($_SESSION['administratorId']))
			return null;
		$sql = 'SELECT `settings` FROM `administrators` WHERE `id`=' . (int) $_SESSION['administratorId'];
		$current = Connection::getInstance() -> getField($sql);
		
		if($current) {
			$current = json_decode($current);
		} else {
			$current = new \stdClass();
		}
		
		return $current;
	}
	
	/**
	 * Resets the session
	 */
	protected function resetAll() {
		$this -> resetPermissions();
		$_SESSION = array();			
		session_destroy();
		session_start();
	}
	
	/**
	 * Updates the permissions of the administrator<br/>
	 * Only the permissions in the session are updated, the database is not updated
	 * @param array $permissions An array of permissions
	 */
	protected function updatePermissions($permissions) {
		$this -> resetPermissions();
		$this -> _setPermissions($permissions);
	}
	
	/**
	 * Sets all the permissions to false
	 */
	protected function resetPermissions() {
		$this -> IS_AUTH = $_SESSION['IS_AUTH'] = false;
		$this -> MANAGE_ADMIN = $_SESSION['MANAGE_ADMIN'] = false;
		$this -> MANAGE_USER = $_SESSION['MANAGE_USER'] = false;
		$this -> CREATE_PAGE = $_SESSION['CREATE_PAGE'] = false;
		$this -> DELETE_PAGE = $_SESSION['DELETE_PAGE'] = false;
		$this -> EDIT_PAGE = $_SESSION['EDIT_PAGE'] = false;
		$this -> APPROVE_ISSUE = $_SESSION['APPROVE_ISSUE'] = false;
		$this -> MOVE_PAGE = $_SESSION['MOVE_PAGE'] = false;
		$this -> DELETE_FILE = $_SESSION['DELETE_FILE'] = false;
		$this -> MANAGE_TEMPLATE = $_SESSION['MANAGE_TEMPLATE'] = false;
		$this -> MANAGE_SETTINGS = $_SESSION['MANAGE_SETTINGS'] = false;
		$this -> UPLOAD_FILE = $_SESSION['UPLOAD_FILE'] = false;
		$this -> MANAGE_MAILINGS = $_SESSION['MANAGE_MAILINGS'] = false;
		$this -> MANAGE_CALENDARS = $_SESSION['MANAGE_CALENDARS'] = false;
		$this -> MANAGE_ELEMENTS = $_SESSION['MANAGE_ELEMENTS'] = false;
		$this -> MANAGE_MAPS = $_SESSION['MANAGE_MAPS'] = false;
	}
	
	public function throwException($id, $vars = null) {
		Log::addToLog("Error $id ". print_r($vars, true)) ;
		if(array_key_exists($id, $this -> _exceptions)) {
			$exc = $this -> _exceptions[$id];
			if($vars) {
				if(!is_array($vars))
					$vars = array($vars);
					
				for($i = 0; $i < count($vars); $i++)
					$exc = str_replace('#'.$i, $vars[$i], $exc);
			}
			return new \Exception($exc, $id);
		}
		return new \Exception('An unspecified error occured', $id);
	}
}
