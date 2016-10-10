<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 9/23/16
 * Time: 11:30 AM
 */

namespace fur\bright\api;


use fur\bright\entities\OTreeNode;
use fur\bright\exceptions\ParameterException;

class Tree
{
    /**
     * Used for search<br/>Converts OPages to OTreenodes
     * This method checks if pages are published and if the user has access to them
     * @param array $pages An array of pages
     * @return array An array of OTreeNodes
     */
    public function getNodesByPageIds($pages)
    {
        $ids = array();
        $indexed = array();
        $result = array();
        $page = new Page();
        $cl = new User();

        foreach ($pages as $opage) {
            $ids[] = $opage->pageId;
            $indexed[$opage->pageId] = $opage;
        }

        $loginreq = ($cl->isLoggedIn()) ? '' : 'AND t.loginrequired=0 ';

        $strids = join(' OR t.pageId=', $ids);
        $sql = <<<SQL
            SELECT DISTINCT (t.pageId), t.*
                FROM tree t 
                JOIN page p on t.pageId = p.pageId 
                WHERE (t.pageId=%s) %s 
                AND ((UNIX_TIMESTAMP(p.publicationdate) <= %d
                    AND UNIX_TIMESTAMP(p.expirationdate) >= %d)
                    OR p.alwayspublished = 1)
                    GROUP BY t.pageId

SQL;

        $sql = sprintf($sql, $strids, $loginreq, time(), time());
        $children = $this->_conn->getRows($sql);
        $root = $this->getRoot();
        foreach ($children as $child) {
            $ot = new OTreeNode();

            $tid = $child->treeId;

            if ($child->shortcut > 0)
                $tid = $child->shortcut;

            $ot->path = $this->getPath($tid, $root->treeId);

            $ot->page = $indexed[$child->pageId];
            $ot->page = $page->getContent($ot->page);
            foreach ($child as $key => $value) {

                if (isset($ot->$key)) {
                    $ot->$key = $value;
                }
            }
            $result[] = $ot;
            unset($ot);
        }
        return $result;
    }

    /**
     * Gets the path of the child
     * @param $treeId
     * @param int $rootId treeId The id of the childnode
     * @return string
     * @throws ParameterException
     */
    public function getPath($treeId, $rootId = 1)
    {
        if (!is_numeric($treeId) || !is_numeric($rootId)) {
            throw new ParameterException(ParameterException::INTEGER_EXCEPTION);
        }

        $path = '';

        $originalTreeId = $treeId;
        $limiter = 1000;
        while ($treeId !== $rootId && $treeId !== null && $treeId > 0 && --$limiter > 0) {
            $sql = <<<SQL
              SELECT t.parentId, p.label 
              FROM tree t 
              JOIN page p on t.pageId = p.pageId 
              WHERE t.treeId= %d
SQL;
            $row = $this->_conn->getRow(sprintf($sql, $treeId));

            if (!$row || $row->parentId == $treeId) {
                return null;
            }

            $treeId = $row->parentId;
            $path = $row->label . '/' . $path;
            if ($limiter == 1) {
                error_log("Cannot find path for $originalTreeId / $rootId, current tid: $treeId, path: $path");
            }
        }
        return $path;
    }
}