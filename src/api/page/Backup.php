<?php
namespace fur\bright\api\page;

use fur\bright\core\Connection;
use fur\bright\entities\OPage;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;

/**
 * Handles the restoring of backups.<br/>
 * @author bs10
 * @version 1.0
 * @package Bright
 * @subpackage page
 */
class Backup extends Permissions
{

    function __construct()
    {
        parent::__construct();

        $this->_conn = Connection::getInstance();
    }

    private $_conn;

    public function getBackupsForPage($pageId)
    {

        if (!is_numeric($pageId)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }

        $sql = 'SELECT * FROM `backup` WHERE `pid`=' . (int)$pageId . ' ORDER BY `date` DESC';
        $rows = $this->_conn->getRows($sql);
        foreach ($rows as $i => $row) {

            try {
                $row->content = base64_decode($row->content);
            } catch (\Exception $ex) {
                // not encoded
            }
            $row->content = json_decode($row->content);
            $rows[$i] = $row;
        }
        return $rows;
    }

    public function restoreBackup($backupId, $fields)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(1001);

        if (!is_numeric($backupId))
            throw $this->throwException(2002);

        if (!is_array($fields))
            throw $this->throwException(2007);

        $backup = $this->getBackupById($backupId);

        if (!$backup)
            throw $this->throwException(5004);
        $page = new Page();
        $current = $page->getPageById($backup->content->pageId);
        if ($current) {

            foreach ($fields as $field) {
                // Sanitize input
                $field = addslashes($field);
                if (!isset($current->content)) {
                    $current->content = new \stdClass();
                }
                // Remove current property
                if (isset($current->content->{$field}))
                    unset($current->content->{$field});

                // Restore backed-up property (if present)
                // THIS MEANS THAT IT WILL BE NULL IF ITS NOT PRESENT IN THE BACKUP
                if (isset($backup->content->content->{$field}))
                    $current->content->{$field} = $backup->content->content->{$field};

            }

            $page->setPage($current, false, false);
        }
    }

    public function getBackupById($backupId)
    {
        $sql = 'SELECT * FROM `backup` WHERE `id`=' . (int)$backupId;
        $row = $this->_conn->getRow($sql);
        if ($row) {
            $row->json = $row->content;
            try {
                $row->content = base64_decode($row->content);
            } catch (\Exception $ex) {
                // Not encoded
            }
            $row->content = json_decode($row->content);
        }

        return $row;
    }

    /**
     * Creates a new entry in the backup
     * @param OPage $page
     * @param string $table
     */
    public function setBackup($page, $table)
    {
        try {
            $p = Connection::getInstance()->escape_string(base64_encode(@json_encode($page)));
            $pid = (int)$page->pageId;
            $sql = "INSERT INTO `backup` (`pid`, `table`, `content`, `date`) VALUES ($pid, '$table', '$p', NOW())";
            $this->_conn->insertRow($sql);

            $sql = 'DELETE FROM `backup` WHERE `date` < (NOW() - INTERVAL 2 MONTH)';
            $this->_conn->deleteRow($sql);
        } catch (\Exception $e) {
            // Cannot json encode or other error
            mail(SYSMAIL, "Backup error in " . SITENAME, "Cannot create backup, reason:\r\n" . $e->getTraceAsString());
        }
    }
}
