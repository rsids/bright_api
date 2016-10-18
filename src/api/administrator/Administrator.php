<?php
namespace fur\bright\api\administrator;

use fur\bright\core\Connection;
use fur\bright\core\Update;
use fur\bright\entities\OAdministratorObject;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\Permissions;

/**
 * Handles the authentication and creation / updating of administrators. Administrators are people with access to the CMS
 * @author Fur
 * @version 2.3
 * @package Bright
 * @subpackage administrators
 */
class Administrator extends Permissions
{

    function __construct()
    {
        parent::__construct();
        $this->_conn = Connection::getInstance();
    }

    /**
     * @var stdClass A reference to the Connection instance
     */
    private $_conn;

    /**
     * Authenticates a administrator by e-mail and password
     * @param string $email The e-mail address of the administrator
     * @param string $password An SHA1 hash of the password
     * @return OAdministratorObject A OAdministratorObject holding the properties of the administrator. Returns null when no administrator was found
     */
    public function authenticate($email, $password)
    {
        if ($this->IS_AUTH)
            $this->logoutcmd();

        $result = $this->auth($email, $password);
        $administrator = new OAdministratorObject();
        foreach ($result as $row) {
            $administrator->id = $row->id;
            $administrator->name = $row->name;
            $administrator->email = $row->email;
            if (isset($row->settings)) {
                $administrator->settings = json_decode($row->settings);
            }

            $administrator->permissions[] = $row->permission;
        }


        if ($administrator->id > 0) {
            $this->setAdministrator($administrator);
            return $administrator;
        }

        return null;
    }

    /**
     * Creates a administrator<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_ADMIN</li>
     * </ul>
     * @param OAdministratorObject $administrator The administrator to create
     * @return array An array of OAdministrators
     * @throws \Exception
     */
    public function createAdministrator($administrator)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!$this->MANAGE_ADMIN)
            throw $this->throwException(1002);

        $result = $this->_conn->getRow('SELECT count(id) as ids ' .
            'FROM administrators ' .
            'WHERE email = \'' . Connection::getInstance()->escape_string($administrator->email) . '\'');
        if ($result->ids > 0)
            throw $this->throwException(1006);

        $insert = $this->_conn->insertRow("INSERT INTO administrators (name, email, password) " .
            "VALUES ('" . Connection::getInstance()->escape_string($administrator->name) . "'," .
            "'" . Connection::getInstance()->escape_string($administrator->email) . "'," .
            "'" . Connection::getInstance()->escape_string($administrator->password) . "')");

        $administratorId = 0;
        if ($insert > 0) {
            $administrator->id = $insert;
            $this->_setPermissionsToDb($administrator);
            return $this->getAdministrators();
        } else {
            throw $this->throwException(1003);
        }
    }

    /**
     * Deletes a administrator<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_ADMIN</li>
     * </ul>
     * @param OAdministratorObject $administrator The administrator to delete
     * @return array An array of OAdministrators
     * @throws \Exception
     */
    public function deleteAdministrator($administrator)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(1001);

        if (!$this->MANAGE_ADMIN)
            throw $this->throwException(1002);

        $curAdministrator = $this->getAdministrator();
        if ($curAdministrator->id == $administrator->id)
            throw $this->throwException(1004);


        $this->_unsetPermissions($administrator);

        $sql = 'DELETE FROM `administrators` WHERE `id`=' . (int)$administrator->id;
        $this->_conn->deleteRow($sql);
        return $this->getAdministrators();
    }

    /**
     * Gets the administrator from a dataset<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param array $data The data object containing the administrator fields
     * @return OAdministratorObject The administratorobject
     * @throws \Exception
     */
    public function getAdministratorFromData(array $data)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(1001);
        $administrator = new OAdministratorObject();
        $administrator->id = $data['administratorId'];
        $administrator->name = $data['name'];
        $administrator->email = $data['email'];
        return $administrator;
    }

    /**
     * Gets a list of all administrators (without their permissions)<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @return array An array of administrators
     * @throws \Exception
     */
    public function getAdministrators()
    {
        if (!$this->IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

        $result = $this->_conn->getRows('SELECT * FROM administrators');

        $resultarray = array();
        foreach ($result as $row) {
            $administrator = new OAdministratorObject();
            $administrator->id = $row->id;
            $administrator->name = $row->name;
            $administrator->email = $row->email;
            $administrator->permissions = $this->getPermissionsForAdministrator($administrator);
            $resultarray[] = $administrator;
            unset($administrator);
        }

        return $resultarray;
    }

    /**
     * Gets the permissions of a administrator<br/>
     * Since 2.2 is the parameter optional, if it is omitted, the permissions for the currently logged in administrator
     * are returned
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param OAdministratorObject $administrator The administrator to get the permissions for
     * @return array An array of permissions
     * @throws \Exception
     */
    public function getPermissionsForAdministrator($administrator = null)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(1001);

        if ($administrator == null)
            return $this->getPermissions();

        $result = $this->_conn->getRows('SELECT permission ' .
            'FROM administratorpermissions u ' .
            'WHERE u.administratorId = ' . $administrator->id);
        $resultarray = array();
        foreach ($result as $row) {
            $resultarray[] = $row->permission;
        }

        return $resultarray;
    }


    /**
     * Checks if a administrator is authenticated, if so, it returns the administrator
     * @param string $version The current version of the Frontend, used to check for updates
     * @return Administrator The authenticated administrator, or false if none was found
     */
    public function isAuth($version = null)
    {
        if ($version) {
            $update = new Update();
            $update->check($version);
        }

        if (!$this->IS_AUTH)
            return false;
        $administrator = $this->getAdministrator();
        if ((int)$administrator->id == 0)
            return false;

        $query = "SELECT * 
				FROM administrators 
				WHERE id = " . $administrator->id;
        $row = $this->_conn->getRow($query);
        $administrator->name = $row->name;
        $administrator->email = $row->email;
        if (isset($row->settings)) {
            $administrator->settings = json_decode($row->settings);
        }

        return $administrator;
    }

    /**
     * Logs out a administrator
     */
    public function logoutcmd()
    {
        $this->resetAll();
    }

    /**
     * Sets the settings for the current administrator
     * @param \stdClass $value
     * @return Object
     * @throws \Exception
     * @since 2.3 10 oct 2011
     */
    public function setSettings($value)
    {

        if (!$this->IS_AUTH)
            throw $this->throwException(1001);

        // Bug in AmfPHP which does not correctly deserialize flex.messaging.io.objectproxy to php stdClass objects
        if (isset($value->_externalizedData)) {
            $value = $value->_externalizedData;
        }

        $administrator = $this->getAdministrator();

        $current = $this->_conn->getField("SELECT settings FROM administrators WHERE id=" . (int)$administrator->id);
        if ($current) {
            $current = json_decode($current);
        } else {
            $current = new \stdClass();
        }
        foreach ($value as $key => $val) {
            $current->$key = $val;
        }
        try {
            unset($current->_externalizedData);
            unset($current->_explicitType);
        } catch (\Exception $ex) {/*Swallow it*/
        }

        $query = 'UPDATE administrators ' .
            "SET settings='" . Connection::getInstance()->escape_string(json_encode($current)) . "' " .
            'WHERE id=' . (int)$administrator->id;

        $this->_conn->updateRow($query);
        return $this->getSettings();
    }


    public function resetSettings()
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(1001);


        $curAdministrator = $this->getAdministrator();

        $this->_conn->updateRow("UPDATE administrators SET settings='' WHERE id=" . (int)$curAdministrator->id);
    }

    /**
     * Updates a administrator<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_ADMIN</li>
     * </ul>
     * @param OAdministratorObject $administrator The administrator to update
     * @return array An array of OAdministrators
     * @throws \Exception
     */
    public function updateAdministrator($administrator)
    {
        if (!$this->IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }


        $curAdministrator = $this->getAdministrator();

        if (!$this->MANAGE_ADMIN && $curAdministrator->id != $administrator->id)
            throw $this->throwException(1005);

        $result = $this->_conn->getRow("SELECT count(id) as ids " .
            "FROM administrators " .
            "WHERE email = '" . Connection::getInstance()->escape_string($administrator->email) . "' " .
            "AND administrators.id <> " . (int)$administrator->id);
        if ($result->ids > 0)
            throw $this->throwException(1006);

        $curAdministrator = $this->getAdministrator();
        // Update the administrator
        $query = "UPDATE administrators " .
            "SET email = '" . Connection::getInstance()->escape_string($administrator->email) . "', " .
            "name = '" . Connection::getInstance()->escape_string($administrator->name) . "' ";

        if ($curAdministrator->id == $administrator->id)
            $query .= ", password='" . Connection::getInstance()->escape_string($administrator->password) . "' ";

        $query .= "WHERE id = " . (int)$administrator->id;

        $update = $this->_conn->updateRow($query);

        //Only update permissions when manage_administrator is set
        if ($this->MANAGE_ADMIN)
            $this->_setPermissionsToDb($administrator);

        // Is the updated administrator the current administrator?
        if ($curAdministrator->id == $administrator->id) {
            $this->updatePermissions($administrator->permissions);
        }

        return $this->getAdministrators();
    }


    /**
     * Stores the permissions of a administrator
     * @param OAdministratorObject $administrator The administratorobject
     */
    private function _setPermissionsToDb($administrator)
    {
        // Remove permissions and projects
        $this->_unsetPermissions($administrator);

        // Insert permissions
        $query = "INSERT INTO administratorpermissions (administratorId, permission) VALUES ";
        foreach ($administrator->permissions as $permission) {
            $query .= "(" . $administrator->id . ", '" . $permission . "'), ";
        }
        // Strip ', '
        $query = substr($query, 0, -2);

        $insert = $this->_conn->insertRow($query);
    }

    /**
     * Removes the permissions of a administrator
     * @param OAdministratorObject $administrator The administrator which loses his permissions
     */
    private function _unsetPermissions($administrator)
    {
        $this->_conn->deleteRow("DELETE FROM administratorpermissions WHERE administratorId = " . (int)$administrator->id);
    }
}