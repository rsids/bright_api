<?php
namespace fur\bright\api\user;

use fur\bright\api\cache\Cache;
use fur\bright\api\config\Config;
use fur\bright\api\content\Content;
use fur\bright\core\Connection;
use fur\bright\entities\OUserObject;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\GenericException;
use fur\bright\exceptions\ParameterException;
use fur\bright\exceptions\UserException;
use fur\bright\utils\Mailer;

/**
 * This class manages the users<br/>
 * Users are users who are logged in in the frontend.
 *
 * Changelog:
 * 2.14 20130910
 * - Added getUsersOfType
 * 2.13 20130710
 * - Fix bug when checking on deleted is null, added 'OR YEAR(deleted) = 0
 * 2.12 20121204
 * - Fixed activated bug when creating a new user
 * 2.11 20120912
 * - implemented caching for 'getUsersInGroups', also prepared caching for other methods,
 *   all 'set' methods will flush cached items prefixed with 'user'
 * 2.10 20120702
 * - generatePassword is now public
 * - Added APPROVALMAIL
 * 2.9, 20120309
 * - New hook implementation
 * - Fix bug with unsubscribe mail, activationcode is now added to getUsersInGroups
 * 2.8, 20120201:
 * - deleted field of user table is now a date field
 * 2.7, 20111206:
 * - register is now deprecated, use the new method registerUser
 * 2.6:
 * - No authentication required for getUsers
 * 2.5:
 * - 3rd argument for updateUser is now optional (defaults to false)
 * - Passwords are hashed and salted
 *
 * @package Bright
 * @subpackage users
 * @version 2.14
 */
class User extends Content
{

    private $_hook;
    /**
     * @var boolean Indicates whether the user is logged in or not. For now, this is the only possible permission for a user
     */
    public $IS_CLIENT_AUTH = false;

    function __construct()
    {
        parent::__construct();
        $this->IS_CLIENT_AUTH = (isset($_SESSION['IS_CLIENT_AUTH']) && $_SESSION['IS_CLIENT_AUTH'] == true);

        if (class_exists('UserHook', true)) {
            $this->_hook = new \UserHook();
        }
    }


    /**
     * Generates a random password
     * @return string The generated password
     */
    public function generatePassword()
    {
        $haystack = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890abcdefghijklmnopqrstuvwxyz';
        $code = '';
        while (strlen($code) < 9)
            $code .= substr($haystack, rand(0, strlen($haystack) - 1), 1);

        return $code;
    }

    /**
     * Checks if a users is logged in
     * @return boolean Returns true when the user is logged in
     */
    public function isLoggedIn()
    {
        return $this->IS_CLIENT_AUTH;
    }

    /**
     * Authenticates a user by e-mail and password
     * @param string $email The e-mail address of the user
     * @param string $password The password
     * @return OUserObject A OUserObject holding the properties of the user. Returns null when no user was found
     * @throws \Exception
     */
    public function authenticate($email, $password)
    {
        if ($this->IS_CLIENT_AUTH)
            $this->logoutuser();
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw $this->throwException(ParameterException::EMAIL_EXCEPTION);
        }

        $email = Connection::getInstance()->escape_string($email);
        $this->_hashPassword($password, $email);
        $password = Connection::getInstance()->escape_string($password);
        $query = "SELECT `user`.userId 
				FROM `user`
				LEFT JOIN `userusergroups` `uug` ON `uug`.userId = `user`.userId
				LEFT JOIN `usergroups` `ug` ON `ug`.groupId = `uug`.groupId
				WHERE `user`.`email` = '{$email}' AND `user`.`password`= '$password'
				AND activated <> 0 AND (`user`.deleted IS NULL OR YEAR(`user`.deleted) = 0)
				GROUP BY `user`.userId";
        $result = $this->conn->getField($query);

        if (!$result)
            return null;

        $sql = "UPDATE `user` SET `lastlogin`=NOW() WHERE userId=" . $result;
        $this->conn->updateRow($sql);

        $user = $this->getUser($result);
        $this->_setUser($user);

        if ($user->userId > 0)
            return $user;

        return null;
    }

    /**
     * Destroys the session of the user
     */
    public function logoutuser()
    {
        unset($_SESSION['IS_CLIENT_AUTH']);
        unset($_SESSION['userId']);
        unset($_SESSION['user']);
    }

    /**
     * Gets the currently authenticated user
     * @since 2.0 - 17 jul 2010
     * @return OUserObject The currently logged in user
     */
    public function getAuthUser()
    {

        if (!isset($_SESSION['user']))
            return null;

        $user = unserialize($_SESSION['user']);
        return $user;
    }

    /**
     * Gets a user by it's id
     * @param int $id The id of the user
     * @return OUserObject The user, or null when no user was found
     */
    public function getUser($id)
    {
        $id = (int)$id;
        $sql = "SELECT u.*, it.`label` as `itemLabel` FROM user u
				LEFT JOIN itemtypes it ON it.itemId = u.itemType
				WHERE userId=$id";
        $user = $this->conn->getRow($sql, 'OUserObject');
        if ($user) {
            // Give a pageId, so the editor knows it's an existing user / page
            $user->pageId = 999999;
            $user->password = '';
            $ug = new UserGroup();
            $user->usergroups = $ug->getGroupsForUser($user);

            $user = $this->getContent($user, true, 'userfields');

        }
        return $user;

    }

    /**
     * Gets a user by it's e-mail address
     * @since 2.1
     * @param string $email
     * @return null|OUserObject
     */
    public function getUserByEmail($email)
    {
        $email = Connection::getInstance()->escape_string($email);
        $sql = "SELECT userId FROM user WHERE email='$email' AND (`deleted` IS NULL OR YEAR(`deleted`) = 0)";
        $userId = (int)$this->conn->getField($sql);
        $user = null;
        if ($userId > 0) {
            $ug = new UserGroup();
            $user = $this->getUser($userId);
            $user->password = '';
            $user->usergroups = $ug->getGroupsForUser($user);
        }

        return $user;
    }

    /**
     * Gets a user by it's label
     * @since 2.4 - 28 sep 2011
     * @param string $label The label of the user
     * @return object The user object
     */
    public function getUserByLabel($label)
    {
        $label = Connection::getInstance()->escape_string($label);
        $sql = "SELECT userId
				FROM user u
				WHERE u.label='{$label}'
				AND (`deleted` IS NULL OR YEAR(`deleted`) = 0)";
        $userId = (int)$this->conn->getField($sql);
        $user = null;
        if ($userId > 0) {
            $ug = new UserGroup();
            $user = $this->getUser($userId);
            $user->password = '';
            $user->usergroups = $ug->getGroupsForUser($user);
        }

        return $user;
    }

    /**
     * Registers a new user through the frontend
     * @since 2.7
     * @param OUserObject $user The user to register
     * @param boolean $activationRequired When true, the user has to confirm his registration by e-mail
     * @param boolean $approvalRequired When true, an administrator has to confirm the registration in Bright
     * @param string $mailTemplate The name of the mail template
     * @throws \Exception
     * @return int The new id of the user
     */
    public function registerUser(OUserObject $user, $activationRequired, $approvalRequired, $mailTemplate = null)
    {
        throw new \Exception(GenericException::NOT_IMPLEMENTED);
//		if($this -> _checkForExistance($user -> email))
//			throw $this -> throwException(UserException::DUPLICATE_EMAIL);
//
//		if(!$user -> label)
//			$user -> label = '';
//
//		$c = new Cache();
//		$c -> deleteCacheByPrefix('user');
//
//		$user -> password = $this -> _hashPassword($user -> password, $user -> email);
//		foreach(array('label', 'email', 'password') as $field) {
//			$user -> {$field} = Connection::getInstance() -> escape_string($user -> {$field});
//		}
//		$user -> itemType = (int) $user -> itemType;
//		$acode = md5(time() . $this -> generatePassword());
//		$activated = ($activationRequired || $approvalRequired) ? 0 : 1;
//		$sql = 'INSERT INTO user (`label`,`itemType`,`email`, `password`,`registrationdate`, `activationcode`, `activated`) VALUES ';
//		$sql .= "('{$user -> label}', {$user -> itemType}, '{$user -> email}', '{$this -> _hashPassword($user -> password, $user -> email)}', NOW(), '{$acode}', {$activated})";
//
//		$user -> userId = $this -> conn -> insertRow($sql);
//
//		if($user -> userId > 0) {
//			$ug = new UserGroup();
//			$ug -> setGroupsForUser($user);
//
//			if(isset($user -> content)) {
//				$this -> setContent($user, 'userfields');
//			}
//
//			if($activationRequired) {
//				$p = new Parser(null);
//				$o = new stdClass();
//				$o -> activationlink = BASEURL . 'bright/';
//				if(defined('USEACTIONDIR') && USEACTIONDIR) {
//					$o -> activationlink .= 'actions/';
//				}
//				$o -> activationlink .= 'registration.php?action=activate&code=' . $acode;
//				$mail = $p -> parseTemplate($o, $mailTemplate, '', 'ACTIVATIONMAIL');
//				$mailer = new Mailer();
//				$mailer -> sendPlainMail(MAILINGFROM, $user -> email, 'Uw activatie', $mail);
//			}
//
//			if($approvalRequired) {
//				$p = new Parser(null);
//				$o = new stdClass();
//				$o -> user = $user;
//				$mail = $p -> parseTemplate($o, $mailTemplate, '', 'ADMINISTRATORMAILNEW');
//				$mailer = new Mailer();
//				$mailer -> sendPlainMail(MAILINGFROM, APPROVALMAIL, 'Nieuwe registratie op ' . SITENAME, $mail);
//			}
//			return $user -> userId;
//
//		} else {
//			throw $this -> throwException(1000);
//		}
    }

    /**
     * Unregisteres a user
     *
     * @param string $email The email address of the user
     * @param string $mailTemplate The mail template to use for the confirmation mail;
     * @throws \Exception
     */
    public function unregister($email, $mailTemplate)
    {
        throw new \Exception(GenericException::NOT_IMPLEMENTED);
//		if(!$this -> _checkForExistance($email)) {
//            throw $this->throwException(8002);
//        }
//
//		$user = $this -> getUserByEmail($email);
//
//		$o = new \stdClass();
//		$o -> deactivationlink = BASEURL . 'bright/';
//		if(defined('USEACTIONDIR') && USEACTIONDIR) {
//			$o -> deactivationlink .= 'actions/';
//		}
//		$o -> deactivationlink .= 'registration.php?action=deactivate&email=' . $user -> email . '&code=' . $user -> activationcode;
//		$p = new Parser(null);
//		$mail = $p -> parseTemplate($o, $mailTemplate, '', 'DEACTIVATIONMAIL');
//
//		$mailer = new Mailer();
//		$mailer -> sendPlainMail(MAILINGFROM, $user -> email, 'Uw activatie', $mail);
    }

    /**
     * Gets the users from a specific group or groups<br/>
     * - Note: Only activated and non-deleted users are returned
     * - Note: Since 2.7 the return value of this method has changed, it now returns full users objects (with a content variable)
     * @param array $groups An array of group ID's
     * @param boolean $emailOnly When true, this method returns an array of emailaddresses, when false it returns the whole user
     * @param boolean $includeCustom When true, all the custom user fields are included as well
     * @return array An array of e-mailaddresses or users
     */
    public function getUsersInGroups($groups, $emailOnly = true, $includeCustom = false)
    {
        $cleangroups = array();

        $c = new Cache();
        $argstring = md5(json_encode(func_get_args()));
        $result = $c->getCache("user_getUsersInGroups_$argstring");
        if ($result)
            return $result;

        if (!is_array($groups))
            $groups = array($groups);

        foreach ($groups as $group) {
            $cleangroups[] = (int)$group;
        }

        $cleangroups = join(', ', $cleangroups);
        if ($emailOnly) {

            $sql = 'SELECT DISTINCT email ' .
                'FROM user ' .
                'WHERE user.userId IN ' .
                '(SELECT userId ' .
                'FROM userusergroups ' .
                'WHERE `groupId` IN (' . $cleangroups . ')) ' .
                'AND activated=1 AND (`deleted` IS NULL OR YEAR(`deleted`) = 0)';
            return $this->conn->getFields($sql);
        }
        $sql = "SELECT DISTINCT user.`userId`, user.itemType, user.`email`, user.`activationcode`, user.`label`, user.`registrationdate`, user.`lastlogin`, user.`modificationdate`, user.`activated`, user.`deleted`
				FROM user
				WHERE user.userId IN
					(SELECT userId
					FROM userusergroups
					WHERE `groupId` IN ({$cleangroups}))
				AND user.activated=1 AND (user.`deleted` IS NULL OR YEAR(user.`deleted`) = 0)";
        $users = $this->conn->getRows($sql, 'OUserObject');
        if ($includeCustom) {
            foreach ($users as &$user) {
                $user = $this->getContent($user, true, 'userfields');
            }
        }
        $c->setCache($users, "user_getUsersInGroups_$argstring", strtotime('+1 year'));
        return $users;

    }

    /**
     * Gets all the users of the specified template
     * @param int $type The id of the template
     * @param boolean $full When true, all content fields are retrieved
     * @return array|mixed
     */
    public function getUsersOfType($type, $full = true)
    {
        $type = (int)$type;
        $full = ($full);

        $c = new Cache();
        $argstring = md5(json_encode(func_get_args()));
        $result = $c->getCache("user_getUsersOfType_$argstring");
        if ($result)
            return $result;

        $sql = "SELECT DISTINCT user.`userId`, user.itemType, user.`email`, user.`activationcode`, user.`label`, user.`registrationdate`, user.`lastlogin`, user.`modificationdate`, user.`activated`, user.`deleted`
		FROM user
		WHERE user.itemType = $type
		AND user.activated=1 
		AND (user.`deleted` IS NULL OR YEAR(user.`deleted`) = 0)";
        $users = $this->conn->getRows($sql, 'OUserObject');
        if ($full) {
            foreach ($users as &$user) {
                $user = $this->getContent($user, true, 'userfields');
            }
        }
        $c->setCache($users, "user_getUsersInGroups_$argstring", strtotime('+1 year'));
        return $users;
    }

    /**
     * Deletes the given user from the database<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_USER</li>
     * </ul>
     * @param OUserObject $user user The user to delete
     * @throws \Exception
     * @return array An array of all the users
     */
    public function deleteUser(OUserObject $user)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if (!$this->MANAGE_USER)
            throw $this->throwException(UserException::MANAGE_USER);

        //$this -> _unsetPermissions($user);

        $c = new Cache();
        $c->deleteCacheByPrefix('user');

        $sql = 'UPDATE `user` SET `deleted`=NOW(), `user`.modificationdate=' . time() . ' WHERE `userId`=' . (int)$user->userId;
        $this->conn->updateRow($sql);
        return $this->getUsers();
    }

    /**
     * Returns all the users<br/>
     * @param boolean $updatesOnly Indicates whether only updated users should be fetched
     * @return array An array of all the users
     */
    public function getUsers($updatesOnly = false)
    {
        $settings = $this->getSettings();
        $additionalFields = array();
        if ($settings) {
            if ($settings !== null && isset($settings->user) && isset($settings->user->visibleColumns)) {
                foreach ($settings->user->visibleColumns as $col) {
                    if (!in_array($col, Config::$userColumns)) {
                        $additionalFields[] = $col;
                    }
                }

            }
        }


        $additionalfieldsql = '';
        $joins = array('RIGHT JOIN user u ON uug.userId = u.userId', 'LEFT JOIN usergroups `ug` ON ug.groupId = `uug`.groupId');
        if (count($additionalFields) != 0) {
            $fields = array();
            foreach ($additionalFields as $field) {
                $field = Connection::getInstance()->escape_string($field);
                $fields[] = " COALESCE(co{$field}.value, '') as `{$field}` ";
                $joins[] = "LEFT JOIN userfields co{$field} ON u.userId = co{$field}.userId AND co{$field}.`lang`='tpl' AND co{$field}.`field`='{$field}' ";
            }
            $additionalfieldsql .= ', ' . join(', ', $fields);
        }
        $joinsql = join("\r\n", $joins) . "\r\n";


        $sql = "SELECT u.*,
				GROUP_CONCAT(`uug`.`groupId`) as `usergroupsstr` $additionalfieldsql
				FROM `userusergroups` `uug`
				$joinsql
				GROUP BY u.userId ";

        if ($updatesOnly && isset($_SESSION['lastuserupdate']) && $_SESSION['lastuserupdate'] != '') {
            $sql .= 'HAVING `u`.modificationdate > ' . $_SESSION['lastuserupdate'];
        }


        $result = $this->conn->getRows($sql, 'OUserObject');
        $_SESSION['lastuserupdate'] = time();
        return $result;
    }

    /**
     * Creates or updates a user. Because IS_AUTH is a required permission, you can only use this method from the CMS<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_USER</li>
     * </ul>
     * @param OUserObject $user The user to create or update
     * @param bool $returnAll When true, all users are returned
     * @return array An array of all the users
     * @throws \Exception
     */
    public function setUser(OUserObject $user, $returnAll = true)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if (!$this->MANAGE_USER)
            throw $this->throwException(UserException::MANAGE_USER);

        $c = new Cache();
        $c->deleteCacheByPrefix('user');

        if (method_exists($this->_hook, 'preSetUser')) {
            $user = $this->_hook->preSetUser($user);
        }
        if (isset($user->label) || $user->label == '') {
            $user->label = 'user';
        }
        $user->label = $this->generateLabel($user->label, $user->userId, 'user');
        if ($user->userId > 0) {
            /**
             * @todo Check for security implications when allowing the password to be changed
             */
            $id = $this->_updateUser($user, $user->password && $user->password != '', true);
        } else {
            $id = $this->_createUser($user);
        }

        $user->userId = $id;

        $ug = new UserGroup();
        $ug->setGroupsForUser($user);


        if (isset($user->content) && count($user->content) && (int)$user->itemType > 0) {
            $this->setContent($user, 'userfields');
        }

        if (method_exists($this->_hook, 'postSetUser')) {
            $this->_hook->postSetUser($user);
        }

        if ($returnAll === true)
            return $this->getUsers(true);

        return $this->getUser($id);
    }

    /**
     * Generates a CSV file with all the registered users
     */
    public function downloadCSV()
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if (!$this->MANAGE_USER)
            throw $this->throwException(UserException::MANAGE_USER);

        $sql = 'SELECT DISTINCT `field` FROM `userfields`';
        $customfields = $this->conn->getFields($sql);
        $fieldnames = '';
        $sqla = array();
        if ($customfields) {
            $sqla = array();
            foreach ($customfields as $field) {
                $field = Connection::getInstance()->escape_string($field);
                $sqla[] = "LEFT JOIN `userfields` `uf_{$field}` ON `uf_{$field}`.`userId` = `user`.userId AND `uf_{$field}`.`field`='{$field}'";
                $fieldnames .= ", `uf_{$field}`.`value` AS `{$field}`";
            }
        }

        $sql = 'SELECT `user`.userId, `user`.email, `user`.registrationdate, `user`.lastlogin, FROM_UNIXTIME(`user`.modificationdate) as `modificationdate`, user.activated, user.deleted, GROUP_CONCAT(`groupname`) as `usergroups`' . $fieldnames . ', `user`.password
				FROM `user`
				LEFT JOIN `userusergroups` `uug` ON `uug`.userId = `user`.userId
				LEFT JOIN `usergroups` `ug` ON `ug`.groupId = `uug`.groupId ';
        $sql .= implode("\r\n", $sqla);
        $sql .= ' GROUP BY `user`.userId';
        $result = $this->conn->getRows($sql);
        $csv = '';
        $fname = BASEPATH . 'bright/cache/csvtemp' . time() . '.csv';
        $handle = fopen($fname, 'w');
        if ($result) {
            $vars = array_keys(get_object_vars($result[0]));
            fputcsv($handle, $vars, ';', '"');
            foreach ($result as $row) {

                fputcsv($handle, (array)$row, ';', '"');
            }
        }
        fclose($handle);
        $csv = file_get_contents($fname);
        unlink($fname);
        return $csv;

    }

    /**
     * Uploads a csv file and processes it as a list of user<br/>
     * <b>THIS METHOD IS EXPERIMENTAL! USE AT OWN RISK</b>
     * @since 2.3 11 mar 2011
     * @param \Bytearray $csv
     * @param string $mode How to handle duplicates, either 'overwrite', 'fillempty' (fills only empty fields) or 'skip'
     * @return object
     * @throws \Exception
     */
    public function uploadCSV($csv, $mode = 'overwrite')
    {

        $c = new Cache();
        $c->deleteCacheByPrefix('user');
        ini_set('auto_detect_line_endings', 1);


        $csvname = tempnam(sys_get_temp_dir(), 'list.csv');

        file_put_contents($csvname, base64_decode($csv));
        $handle = @fopen($csvname, 'r');
        $header = fgetcsv($handle, 0, ',', '"');
        if ($header === false) {
            throw $this->throwException(8008);
        }
        $header = array_flip($header);
        $sql = 'SELECT DISTINCT id.label FROM `itemdefinitions` id INNER JOIN `itemtypes` it ON it.itemId = id.itemType AND templatetype=6 ORDER BY id.index';
        $fields = $this->conn->getFields($sql);
        $ufields = '';
        $ujoins = '';

        if ($fields) {
            $afields = array();
            $ajoins = array();
            foreach ($fields as $field) {
                $afields[] = "`$field`.value AS `$field`";
                $ajoins[] = "LEFT JOIN `userfields` `$field` ON `$field`.userId = u.userId AND `$field`.`field`='$field' AND `$field`.deleted IS NULL";
            }
            $ufields = ', ' . implode(', ', $afields);
            $ujoins = implode("\r\n", $ajoins);
        }

        $usersql = 'SELECT u.userId, u.itemType, u.email, u.activated, u.deleted, GROUP_CONCAT(`ug`.`groupname`) as `usergroups` ' . $ufields . '
				FROM `user` `u`
				LEFT JOIN `userusergroups` `uug` ON `uug`.userId = `u`.userId
				LEFT JOIN `usergroups` `ug` ON `ug`.groupId = `uug`.groupId
				' . $ujoins;
        $usersql .= ' GROUP BY u.userId';
        $users = $this->conn->getRows($usersql);

        $ug = new UserGroup();
        $arr = $ug->getUserGroups();
        $groups = array();
        // Create an groupId based array
        foreach ($arr as $group) {
            $groups[(int)$group->groupId] = $group->groupname;
        }


        $updatedusers = array();
        while (($data = fgetcsv($handle, 0, ',', '"')) !== FALSE) {
            // Data could be null when an empty line is encountered
            if ($data) {

                // Check if user exists
                $i = count($users);
                while (--$i > -1) {
                    if (strtolower($users[$i]->email) == strtolower($data[$header['email']]) && ($users[$i]->deleted == 0 || $users[$i]->deleted == null)) {
                        $user = array_splice($users, $i, 1);
                        $user = $user[0];
                        $user->temp_groups = explode(',', $user->usergroups);
                        $i = 0;
                    }
                }

                $process = true;
                if (!$user) {
                    $user = new \stdClass();
                    $user->itemType = 'null';
                    $user->temp_groups = array();
                } else if ($mode == 'skip') {
                    // User exists, skip it
                    $process = false;
                }

                if ($process) {

                    $user->modificationdate = time();
                    $user->temp_group_ids = array();

                    foreach ($header as $key => $i) {

                        switch ($key) {
                            case 'id':
                            case 'userId':
                            case 'modificationdate':
                                // Skip
                                break;
                            case 'itemType':
                                $user->itemType = (int)$data[$i];
                                break;

                            case 'deleted':
                                if ($data[$i] == 1) {
                                    // Skip deleted values
                                    $process = false;
                                } else {
                                    $user->deleted = 'null';
                                }
                                break;

                            case 'email':
                                if (trim($data[$i]) == '') {
                                    $process = false;
                                } else {
                                    $user->{$key} = strtolower(trim($data[$i]));
                                }
                                break;

                            case 'registrationdate':
                            case 'lastlogin':
                            case 'activated':
                            case 'password':
                                $user->{$key} = trim($data[$i]);
                                break;

                            case 'usergroups':
                                $user->setgroups = true;
                                if ($mode == 'fill' && count($user->temp_groups) > 0) {
                                    $user->setgroups = false;
                                }


                                if ($user->setgroups) {
                                    $datagroups = (trim($data[$i]) != '') ? explode(',', $data[$i]) : array();
                                    $newgroupId = 0;

                                    foreach ($datagroups as $newgroup) {

                                        $newgroup = trim($newgroup);

                                        if ($newgroup != '') {

                                            $newgroupId = array_search($newgroup, $groups);

                                            // New group, add it
                                            if ($newgroupId === false) {
                                                $ng = $ug->setUserGroup(array('groupname' => $newgroup));

                                                //Added successfully?
                                                if ($ng) {
                                                    $groups[$ng->groupId] = $ng->groupname;
                                                    $newgroupId = (int)$ng->groupId;
                                                }
                                            }

                                            // Valid id?
                                            if ((int)$newgroupId > 0) {
                                                $user->temp_group_ids[] = (int)$newgroupId;
                                            }
                                        }
                                    }
                                }
                                break;
                            default:
                                // Add as custom field
                                if ($data[$i] && $data[$i] !== '') {
                                    if ($user->{$key} && $mode == 'fill') {
                                        // Skip it...
                                        unset($user->{$key});
                                    } else {
                                        // Fill / overwrite it
                                        $user->{$key} = $data[$i];
                                    }
                                }
                        } // end switch
                    } // end foreach

                    if ($process) {
                        //$this -> setUser($user, false);
                        $updatedusers[] = $user;
                    }

                } // end if process
            } // end if data
            unset($user);
        } // end while

        // First, insert all the new users, next we'll update the groups & custom fields
        $insertsql = 'INSERT INTO `user` (`email`, `itemType`, `password`, `lastlogin`, `modificationdate`, `activationcode`, `activated`) VALUES';
        $insertsqla = array();
        foreach ($updatedusers as &$user) {
            if (!isset($user->userId)) {
                // New one!
                if (!isset($user->activated)) {
                    $user->activated = 0;
                }
                if (!isset($user->deleted)) {
                    $user->deleted = 'null';
                }
                $insertsqla[] = '(\'' . Connection::getInstance()->escape_string(trim(strtolower($user->email))) . '\', ' .
                    $user->itemType . ', ' .
                    '\'' . Connection::getInstance()->escape_string($user->password) . '\', ' .
                    '\'0000-00-00 00:00:00\', ' .
                    time() . ', ' .
                    '\'' . md5(time() . $user->password) . '\', ' .
                    (int)$user->activated . ')';

            }
        }
        // Are there new ones?
        if (count($insertsqla)) {
            $insertsql .= implode(",\r\n", $insertsqla);
            $insertsql .= " ON DUPLICATE KEY UPDATE
							`itemType`=VALUES(`itemType`),
							`modificationdate`=VALUES(`modificationdate`),
							`activated`=VALUES(`activated`)";
            $this->conn->insertRow($insertsql);
        }

        // Now, fetch the users again, so the new users have an id
        $sql = 'SELECT userId, email FROM `user` WHERE `deleted` IS NULL';
        $newusers = $this->conn->getRows($sql);

        $moddate = 'UPDATE `user` SET `modificationdate`=' . time() . ' WHERE userId IN (';
        $modids = array();

        $groupdelete = 'UPDATE `userusergroups` SET `deleted`=1 WHERE userId IN (';
        $groupinsert = 'INSERT INTO `userusergroups` (groupId, userId, deleted) VALUES ';
        $groupinserta = array();
        $groupdeleteids = array();
        $insertsql = 'INSERT INTO `userfields` (`userId`, `field`, `value`, `deleted`) VALUES ';
        $insertsqla = array();
        // Find new users and add userId
        foreach ($updatedusers as &$user) {
            if (!isset($user->userId)) {
                $i = count($newusers);
                while (--$i > -1) {
                    if ($newusers[$i]->email == $user->email) {
                        // Remove from newuser array, should speed up the process
                        $user->userId = $newusers[$i]->userId;
                        array_splice($newusers, $i, 1);
                        $i = 0;
                    }
                }
            }
            $id = (int)$user->userId;
            $modids[] = $id;
            if ($user->setgroups) {
                // Create an array of userIds to delete from the userusergroups table,
                // After deletion, updated values will be inserted
                $groupdeleteids[] = $id;
                foreach ($user->temp_group_ids as $groupId) {
                    $groupinserta[] = '(' . $groupId . ', ' . $id . ', 0)';
                }
            }

            if ($fields) {
                foreach ($fields as $field) {
                    $field = Connection::getInstance()->escape_string($field);
                    if (isset($user->$field)) {
                        $user->$field = Connection::getInstance()->escape_string($user->$field);
                        $insertsqla[] = "({$id}, '{$field}', '{$user -> $field}', 0)";
                    } else if ($mode == 'overwrite') {
                        $insertsqla[] = "({$id}, '{$field}', '', 1)";
                    }
                }
            }

        }

        if (count($groupdeleteids) > 0) {
            $groupdelete .= implode(",", $groupdeleteids) . ')';
            $this->conn->updateRow($groupdelete);
        }

        if (count($groupinserta) > 0) {
            $groupinsert .= implode(", \r\n", $groupinserta) . ' ON DUPLICATE KEY UPDATE `deleted` = 0';
            $this->conn->insertRow($groupinsert);
            $sql = 'DELETE FROM `userusergroups` WHERE `deleted`=1';
            $this->conn->deleteRow($sql);
        }

        if (count($insertsqla) > 0) {
            $insertsql .= implode(", \r\n", $insertsqla) . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `deleted` = VALUES(`deleted`)';

            $this->conn->insertRow($insertsql);
            $sql = 'DELETE FROM `userfields` WHERE `deleted`=1';
            $this->conn->deleteRow($sql);
        }

        $moddate .= implode(',', $modids) . ')';
        $this->conn->updateRow($moddate);

        return (object)array('users' => $this->getUsers(true), 'groups' => $ug->getUserGroups());
    }

    /**
     * Changes the password of the currently logged in user<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_CLIENT_AUTH</li>
     * </ul>
     * @param string $old The old password
     * @param string $new The new password
     * @return bool true when successful
     * @throws \Exception
     */
    public function changePassword($old, $new)
    {
        if (!$this->IS_CLIENT_AUTH)
            throw $this->throwException(UserException::NO_USER_AUTH);

        $user = unserialize($_SESSION['user']);
        $sql = 'SELECT * FROM `user` WHERE email =\'' . Connection::getInstance()->escape_string($user->email) . '\' AND password=\'' . Connection::getInstance()->escape_string(sha1($old)) . '\'';
        $result = $this->conn->getRow($sql);
        if (!$result)
            return false;
        $sql = 'UPDATE `user` SET `password`=\'' . Connection::getInstance()->escape_string(sha1($new)) . '\' WHERE userId=' . (int)$user->userId;
        return $this->conn->updateRow($sql);;
    }

    /**
     * Generates a new password for an e-mail address
     * @param string $email The email address of the user to generate a new pass
     * @return string The newly generated password
     * @throws Exception
     */
    public function forgotPass($email)
    {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw $this->throwException(ParameterException::EMAIL_EXCEPTION);
        } else {
            $email = Connection::getInstance()->escape_string($email);
            $result = $this->conn->getRow("SELECT count(userId) as ids FROM user WHERE email='$email'");

            if ((int)$result->ids == 0)
                throw $this->throwException(8009);

            $newpass = $this->generatePassword();
            $uh = $newpass;

            $this->_hashPassword($newpass, $email);
            $newpass = Connection::getInstance()->escape_string($newpass);
            $sql = "UPDATE `user` SET password='$newpass' WHERE email='$email'";

            $result = $this->conn->updateRow($sql);
            return $uh;
        }
    }

    /**
     * Updates an existing user<br/>
     * Use this method to edit users from the frontend
     * @param OUserObject $user The userobject to update
     * @param boolean $updatePassword When false, the password property is ignored
     * @param boolean $updateActivated When false, the activated property is ignored
     * @return mixed
     */
    public function updateUser(OUserObject $user, $updatePassword, $updateActivated = false)
    {
        $c = new Cache();
        $c->deleteCacheByPrefix('user');
        $result = $this->_updateUser($user, $updatePassword, $updateActivated);
        if ($result && isset($user->content)) {
            $res2 = $this->setContent($user, 'userfields');
            //$this -> _setUserFields($user -> userId, $user -> content);
            return ($res2);
        }
        return $result;
    }

    /**
     * Checks if the given code matches the email address
     * @param string $email The email address
     * @param string $code The activation code
     * @return boolean True when it matches
     * @since 2.2 - 28 dec 2010
     */
    public function checkCode($email, $code)
    {
        $sql = 'SELECT COUNT(userId) FROM `user` WHERE `email` = \'' . Connection::getInstance()->escape_string($email) . '\' AND `activationcode`=\'' . Connection::getInstance()->escape_string($code) . '\'';
        return $this->conn->getField($sql) == 1;
    }

    /**
     * Activates a user by it's activation code
     * @param string $activationCode The code needed to activate
     * @since 2.2 - 28 dec 2010
     * @return boolean True when successful
     */
    public function activate($activationCode)
    {
        $c = new Cache();
        $c->deleteCacheByPrefix('user');
        $sql = "UPDATE user SET activated=1 WHERE activationcode='" . Connection::getInstance()->escape_string($activationCode) . "'";
        return $this->conn->updateRow($sql);
    }

    /**
     * Deletes a user by it's e-mail and it's activationcode
     * @param string $email The e-mail address of the user
     * @param string $activationCode The activation code of the user
     * @since 2.2 - 28 dec 2010
     * @return boolean True when successful
     */
    public function deactivate($email, $activationCode)
    {
        $c = new Cache();
        $c->deleteCacheByPrefix('user');
        $sql = 'UPDATE user ' .
            'SET deleted=NOW() ' .
            "WHERE activationcode='" . Connection::getInstance()->escape_string($activationCode) . "' " .
            "AND email='" . Connection::getInstance()->escape_string($email) . "'";
        return $this->conn->updateRow($sql);

    }

    /**
     * Creates a user<br/>
     * @param OUserObject $user The user to create
     * @param boolean $sendMail Indicates whether an email with his credentials should be send to the newly created user
     * @param string $password When $sendMail is true, you have to pass the non-hashed password to this method in order to include it in the e-mail
     * @return array An array of all the users
     * @throws \Exception
     */
    private function _createUser(OUserObject $user, $sendMail = false, $password = '')
    {
        $email = Connection::getInstance()->escape_string($user->email);
        $result = $this->conn->getRow("SELECT count(userId) as ids 
									FROM user 
									WHERE email = '$email' and (deleted IS NULL OR YEAR(deleted) = 0)");
        if ($result->ids > 0)
            throw $this->throwException(8002);
        $this->_hashPassword($user->password, $user->email);
        $activationCode = md5(time() . $user->password);
        $activated = ($user->activated) ? 1 : 0;
        $user->itemType = (int)$user->itemType;
        $insert = $this->conn->insertRow('INSERT INTO user (`label`, `itemType`, `email`, `password`, `registrationdate`, `modificationdate`, `activationcode`, `activated`) ' .
            "VALUES ('" . Connection::getInstance()->escape_string($user->label) . "',
											{$user -> itemType},
											'$email',
											'" . Connection::getInstance()->escape_string($user->password) . "',
											NOW(),
											" . time() . ",
											'$activationCode',
											$activated)");

        if ($insert > 0) {
            $user->userId = $insert;
            if ($sendMail) {
                ob_start();
                include 'registration.txt';
                $content = ob_get_contents();
                ob_end_clean();
                $content = str_replace('###sitename###', SITENAME, $content);
                $content = str_replace('###baseurl###', BASEURL, $content);
                $content = str_replace('###email###', $user->email, $content);
                $content = str_replace('###password###', $password, $content);

                $ma = new Mailer();
                $ma->sendPlainMail(MAILINGFROM, $user->email, 'Your account for ' . SITENAME . ' is ready', $content);
            }
            return $user->userId;
        }

        throw $this->throwException(UserException::INSERT_ERROR);
    }

    private function _checkForExistance($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false)
            throw $this->throwException(ParameterException::EMAIL_EXCEPTION);

        $sql = "SELECT count(`email`) FROM user WHERE `email`='" . Connection::getInstance()->escape_string($email) . "' AND (`deleted` IS NULL OR YEAR(`deleted`) = 0)";
        return (int)$this->conn->getField($sql) > 0;
    }


    private function _hashPassword(&$password, $email)
    {
        $password = hash('sha512', SITENAME . hash('sha512', $password) . $email);
        return $password;
    }

    /**
     * Store the logged in user in the session<br/>
     * @param OUserObject $user The user to store
     */
    private function _setUser(OUserObject $user)
    {
        $_SESSION['userId'] = $user->userId;
        if ($user->userId > 0) {
            $this->IS_CLIENT_AUTH = $_SESSION['IS_CLIENT_AUTH'] = true;
            session_regenerate_id();
            $_SESSION['user'] = serialize($user);

        }
    }


    /**
     * Updates a user<br/>
     * @param OUserObject $user The user to update
     * @param boolean $updatePassword When false, the password property is ignored
     * @param boolean $updateActivated When false, the activated property is ignored
     * @return int The id of the updated user, null on failure
     * @throws \Exception
     */
    private function _updateUser(OUserObject $user, $updatePassword = true, $updateActivated = true)
    {
        if (filter_var($user->email, FILTER_VALIDATE_EMAIL) === false)
            throw $this->throwException(ParameterException::EMAIL_EXCEPTION);
        $user->email = Connection::getInstance()->escape_string($user->email);
        $result = $this->conn->getRow("SELECT count(userId) as ids FROM `user`
											WHERE `email` = '{$user -> email}' 
											AND (`deleted` IS NULL OR YEAR(deleted)=0) AND user.userId <> " . (int)$user->userId);

        if ($result->ids > 0)
            throw $this->throwException(UserException::DUPLICATE_EMAIL);

        $query = 'UPDATE user ' .
            "SET `label` = '" . Connection::getInstance()->escape_string($user->label) . "',
				`email` = '" . Connection::getInstance()->escape_string($user->email) . "',
				`itemType` = " . (int)$user->itemType . ", ";

        if ($updatePassword) {
            $query .= "`password` = '" . Connection::getInstance()->escape_string($this->_hashPassword($user->password, $user->email)) . "', ";
        }

        if ($updateActivated)
            $query .= "activated = " . (int)$user->activated . ", ";

        $del = (int)$user->deleted == 0 ? 'null' : 'NOW()';
        $query .= "deleted = {$del}, modificationdate = UNIX_TIMESTAMP(NOW()) WHERE userId = " . (int)$user->userId;

        $update = $this->conn->updateRow($query);
        return $user->userId;
    }

}