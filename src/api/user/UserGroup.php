<?php
namespace fur\bright\api\user;
use fur\bright\api\cache\Cache;
use fur\bright\core\Connection;
use fur\bright\entities\OUserObject;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\ParameterException;
use fur\bright\exceptions\UserException;
use fur\bright\Permissions;

/**
 * Version history
 * 1.5 20120720
 * - Added deleteUserFromGroup
 * @author Fur
 * @version 1.5
 * @package Bright
 * @subpackage user
 */
class UserGroup extends Permissions
{

    /**
     * @var Connection Holds the Connection singleton
     */
    private $_conn;

    function __construct()
    {
        parent::__construct();
        $this->_conn = Connection::getInstance();
    }

    /**
     * Deletes a usergroup
     * @param int $groupId The group to remove
     * @return bool
     * @throws \Exception
     */
    public function deleteUserGroup($groupId)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if (!$this->MANAGE_USER)
            throw $this->throwException(UserException::MANAGE_USER);

        $sql = 'DELETE FROM usergroups WHERE groupId=' . (int)$groupId;
        $success = $this->_conn->deleteRow($sql);
        if ($success) {
            $sql = 'DELETE FROM userusergroups WHERE groupId=' . (int)$groupId;
            $this->_conn->deleteRow($sql);
        }
        return $success;
    }

    /**
     * Gets all usergroups
     * Since v1.3, no authentication is required
     */
    public function getUserGroups()
    {
        $sql = 'SELECT * FROM usergroups';
        return $this->_conn->getRows($sql, 'OUserGroup');

    }

    /**
     * Gets a usergroup
     * @param int $groupId The id of the group
     * @return string The name of the group
     * @throws \Exception
     * @since 1.3
     */
    public function getUserGroup($groupId)
    {
        if (!is_numeric($groupId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $sql = 'SELECT `groupname` FROM usergroups WHERE `groupId`=' . (int)$groupId;
        return $this->_conn->getField($sql);

    }

    /**
     * Gets the id of a usergroup
     * @param string $groupName The name of the group
     * @return int The id of the group
     * @since 1.3
     */
    public function getGroupIdByName($groupName)
    {

        $sql = 'SELECT `groupId` FROM usergroups WHERE `groupname`=\'' . Connection::getInstance()->escape_string($groupName) . '\'';
        return $this->_conn->getField($sql, 'int');

    }

    /**
     * Gets the usergroups of which the given user is member of
     * @param OUserObject $user
     * @return array
     */
    public function getGroupsForUser($user)
    {
        $sql = 'SELECT uu.groupId FROM userusergroups uu WHERE uu.userId=' . (int)$user->userId;
        $result = $this->_conn->getFields($sql, 'int');
        return $result;
    }

    /**
     * Sets the groups for the given user
     * @param OUserObject $user
     */
    public function setGroupsForUser($user)
    {

        $c = new Cache();
        $c->deleteCacheByPrefix('user');

        $sql = 'DELETE FROM userusergroups WHERE userId=' . (int)$user->userId;
        $this->_conn->deleteRow($sql);

        $sql = 'INSERT INTO userusergroups (`groupId`, userId) VALUES ';
        $groups = array();


        foreach ($user->usergroups as $groupId) {
            $groups[] = '(' . (int)$groupId . ', ' . $user->userId . ')';
        }

        // Prevent doubles
        $groups = array_unique($groups);

        if (count($groups) > 0) {
            $sql .= join(', ', $groups);
            $this->_conn->insertRow($sql);
        }

        $uc = new User();
        $au = $uc->getAuthUser();
        // Update session if necessary
        if ($au && $au->userId == $user->userId) {
            $_SESSION['user'] = serialize($user);
        }
    }

    /**
     * Creates a new usergroup, or renames an existing one
     * @param object $group The group to create or update
     * @return \stdClass The created / updated group
     * @throws \Exception
     * @since 1.2
     * @todo Implement update
     */
    public function setUserGroup($group)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if (!$this->MANAGE_USER)
            throw $this->throwException(UserException::MANAGE_USER);

        $group = (object)$group;

        if (!isset($group->groupname)) {
            throw $this->throwException(8006);
        }

        $sql = "SELECT groupname FROM usergroups WHERE groupname='" . Connection::getInstance()->escape_string($group->groupname) . "'";
        $res = $this->_conn->getField($sql);
        if ($res)
            throw $this->throwException(8005);

        $sql = "INSERT INTO usergroups (groupname) VALUES ('" . Connection::getInstance()->escape_string($group->groupname) . "')";
        $id = $this->_conn->insertRow($sql);

        return (object)array('groupId' => (int)$id, 'groupname' => $group->groupname);
    }

    /**
     * Removes a user from the given group
     * @since 1.5
     * @param int $userId the Id of the user
     * @param int $groupId the Id of the group
     * @return bool
     * @throws \Exception
     */
    public function removeUserFromGroup($userId, $groupId)
    {
        // No permissions required,
        // First we have to find a way to gracefully by-pass
        // the authentication system, to allow apps to manage
        // users.

// 		if(!$this -> IS_AUTH) 
// 			throw $this -> throwException(Exceptions::NO_USER_AUTH);	

// 		if(!$this -> MANAGE_USER) 
// 			throw $this -> throwException(Exceptions::MISSING_PERMISSION_USER);

        if (!is_numeric($userId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        if (!is_numeric($groupId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $c = new Cache();
        $c->deleteCacheByPrefix('user');

        $sql = "DELETE FROM `userusergroups` WHERE `groupId`=$groupId AND `userId`=$userId";
        $res = $this->_conn->deleteRow($sql) == 1;


        $uc = new User();
        $au = $uc->getAuthUser();
        // Update session if necessary
        if ($au->userId == $userId) {
            $user = $uc->getUser($userId);
            $_SESSION['user'] = serialize($user);
        }
        return $res;
    }
}
