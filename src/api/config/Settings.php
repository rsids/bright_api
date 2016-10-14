<?php
namespace fur\bright\api\config;

use fur\bright\api\cache\Cache;
use fur\bright\core\Connection;
use fur\bright\core\Log;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\GenericException;
use fur\bright\Permissions;
use fur\bright\utils\Mailer;

/**
 * @author Fur
 * @version 1.0
 * @package Bright
 * @subpackage config
 */
class Settings extends Permissions
{

    /**
     * @var \stdClass A reference to the Connection instance
     */
    private $_conn;

    function __construct()
    {
        parent::__construct();

        $this->_conn = Connection::getInstance();
    }

    /**
     * Gets a setting by it's name
     * @param string $name The name of the setting
     * @return string The value of the setting
     */
    public function getSetting($name)
    {
        $sql = 'SELECT `value` FROM `settings` WHERE `name`=\'' . Connection::getInstance()->escape_string($name) . '\'';
        return $this->_conn->getField($sql);
    }

    /**
     * Gets all the custom defined settings<br/>
     */
    public function getSettings()
    {
        $cache = new Cache();
        $result = $cache->getCache('bright_settings');

        if ($result === false) {
            $sql = 'SELECT * FROM `settings`';
            $result = $this->_conn->getRows($sql);
            $cache->setCache($result, 'bright_settings', time() + 10000000);
        }
        return $result;
    }

    /**
     * Creates or updates an array of custom setting<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_SETTINGS</li>
     * </ul>
     * @param array $values An array of objects containing a name and a value string
     * @return array An array of custom settings
     * @throws \Exception
     */
    public function setSettings($values)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if (!$this->MANAGE_SETTINGS)
            throw $this->throwException(3002);

        foreach ($values as $val) {
            $oval = (object)$val;
            $this->setSetting($oval->name, $oval->value);
            unset($oval);
        }
        $cache = new Cache();
        $cache->flushCache();
        return $this->getSettings();
    }

    /**
     * Creates or updates a custom setting<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_SETTINGS</li>
     * </ul>
     * @access private
     * @param string $name The name of the setting
     * @param mixed $value The value of the setting
     * @return array An array of custom settings
     * @throws \Exception
     */
    private function setSetting($name, $value)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!$this->MANAGE_SETTINGS)
            throw $this->throwException(3002);

        $sql = 'SELECT count(`name`) as `num` FROM `settings` WHERE `name`=\'' . Connection::getInstance()->escape_string($name) . '\'';
        if ((int)$this->_conn->getField($sql) > 0) {
            $sql = 'UPDATE `settings` SET `value`=\'' . Connection::getInstance()->escape_string($value) . '\' WHERE name=\'' . Connection::getInstance()->escape_string($name) . '\'';
        } else {
            $sql = 'INSERT INTO `settings` (`name`, `value`) VALUES (\'' . Connection::getInstance()->escape_string($name) . '\', \'' . Connection::getInstance()->escape_string($value) . '\')';
        }
        $this->_conn->updateRow($sql);
    }

    /**
     * Deletes a custom setting<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_SETTINGS</li>
     * </ul>
     * @param string $name The name of the setting
     * @return array An array of custom settings
     * @throws \Exception
     */
    public function deleteSetting($name)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!$this->MANAGE_SETTINGS)
            throw $this->throwException(3002);

        $sql = 'DELETE FROM `settings` WHERE `name`=\'' . Connection::getInstance()->escape_string($name) . '\'';
        $this->_conn->deleteRow($sql);
        return $this->getSettings();
    }

    /**
     * Logs an Error, should be in Config, but that doesn't extend permissions
     * @param string $error The error stacktrace
     * @throws \Exception
     */
    public function logError($error)
    {
        if (!is_string($error))
            throw $this->throwException(2003);

        $error = strip_tags($error);

        Log::addTolog('-------------------------------------------');
        Log::addTolog(date('r') . ':');
        Log::addTolog($error);
        Log::addTolog('-------------------------------------------');
        $mailer = new Mailer();

        $message = 'An error occurred at ' . date('r') . ' on ' . SITENAME . "\r\nThe stacktrace is:\r\n" . $error;
        $mailer->sendPlainMail(SYSMAIL, SYSMAIL, 'Bright Error', $message);
    }
}
