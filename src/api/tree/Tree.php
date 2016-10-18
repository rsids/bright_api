<?php
namespace fur\bright\api\tree;

use fur\bright\api\cache\Cache;
use fur\bright\api\page\Page;
use fur\bright\api\user\User;
use fur\bright\core\Connection;
use fur\bright\entities\OPage;
use fur\bright\entities\OTreeNode;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\PageException;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;

/**
 * Manages the heart of the website, the tree. The tree is the hierarchical structure of the website
 * Version history:
 * 2.6 20130204
 * - Added includeparents param to getChildrenByIds
 * 2.5 20121203
 * - Added getcontents to getChild (default true)
 * 2.4 20120501
 * - Added returnid to setPage
 * @author fur
 * @version 2.6
 * @package Bright
 * @subpackage tree
 */
class Tree extends Permissions
{

    function __construct()
    {
        parent::__construct();

        $this->_conn = Connection::getInstance();
    }

    /**
     * @var Connection A reference to the Connection instance
     */
    private $_conn;

    /**
     * Used for search<br/>Converts OPages to OTreenodes<br/>This method checks if pages are published and if the user has access to them
     * @param array $pages An array of pages
     * @return array An array of OTreeNodes
     *
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
        $sql = 'SELECT DISTINCT (t.pageId), t.* ' .
            'FROM tree t ' .
            'JOIN page p on t.pageId = p.pageId ' .
            'WHERE (t.pageId=' . $strids . ') ' . $loginreq .
            'AND ((UNIX_TIMESTAMP(p.publicationdate) <= ' . time() . '
				AND UNIX_TIMESTAMP(p.expirationdate) >= ' . time() . ')
				OR p.alwayspublished = 1)
				GROUP BY t.pageId';
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
     * Gets the children of a specified node
     * @param int $parentId The id of the node
     * @param boolean $includePath Specifies whether the full path to the node should be included
     * @param boolean $onlyPublished Specifies whether publication rules should be taken into account
     * @param boolean $includeIcon Specifies whether the icon of the template of the page should be included
     * @param mixed $showInNav Specifies whether show in navigation rules apply. When null, rules don't apply. When false, only nodes which <b>don't</b> show in navigation are returned. When true, only nodes with show in navigation = true are returned
     * @param string $order The field to order on
     * @return array An array of OTreenodes
     * @throws \Exception
     */
    public function getChildren($parentId, $includePath = false, $onlyPublished = false, $includeIcon = false, $showInNav = null, $order = 't.index ASC')
    {
        if (!is_numeric($parentId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        if ($parentId == -1)
            $parentId = '(SELECT treeId FROM tree WHERE parentId = 0)';

        $ci = new Page();
        $published = '';
        if (!$this->IS_AUTH || $onlyPublished) {
            //Show only published
            $published = 'AND ((UNIX_TIMESTAMP(p.publicationdate) <= ' . time() . '
				AND UNIX_TIMESTAMP(p.expirationdate) >= ' . time() . ')
				OR p.alwayspublished = 1) ';
        }

        $showInNavSql = $this->_getShowInNavSql($showInNav);

        if ($order == '')
            $order = 't.index ASC';

        $icon = ($includeIcon) ? 'it.icon AS `itemicon`, ' : '';

        $sql = 'SELECT t.*, it.label AS `itemLabel`, ' . $icon .
            '(SELECT COUNT(`treeId`) ' .
            'FROM tree ' .
            'WHERE parentId=t.treeId) ' .
            'AS numChildren ' .
            'FROM itemtypes it, tree t ' .
            'JOIN page p on t.pageId = p.pageId ' .
            'WHERE t.parentId=' . $parentId . '	' .
            'AND p.itemType = it.itemId	' .
            $showInNavSql .
            $published .
            'ORDER BY ' . Connection::getInstance()->escape_string($order);
        $children = $this->_conn->getRows($sql);
        $result = array();
        $root = $this->getRoot();

        foreach ($children as $child) {
            $ot = new OTreeNode();

            if ($includePath) {
                $tid = $child->treeId;

                if ($child->shortcut > 0)
                    $tid = $child->shortcut;

                $ot->path = $this->getPath($tid, $root->treeId);
            }

            if ($includeIcon) {
                $ot->icon = $child->itemicon;
            }

            $ot->page = $ci->getPageById($child->pageId, true);
            $child->numChildren = (int)$child->numChildren;
            if ($child->numChildren > 0) {
                $ot->children = array();
            }

            foreach ($child as $key => $value) {

                if (isset($ot->$key)) {
                    $ot->$key = $value;
                }
            }

            $ot->init();
            $result[] = $ot;
            unset($ot);
        }
        return $result;

    }

    /**
     * Gets the children of a node, but does not parse it to a OTreeNode object
     * You can specify fields from the content table to include as well.
     * @param int $parentId The parentId of the node
     * @param boolean $includePath Specifies whether the full path to the node should be included (deprecated)
     * @param boolean $onlyPublished Specifies whether publication rules should be taken into account
     * @param mixed $showInNav Specifies whether show in navigation rules apply. When null, rules don't apply. When false, only nodes which <b>don't</b> show in navigation are returned. When true, only nodes with show in navigation = true are returned
     * @param array $additionalFields An array of fields from the content table to fetch as well
     * @param string $lang The language of the additional fields
     * @return array An array of objects
     */
    public function getSimplyfiedChildren($parentId, $includePath = false, $onlyPublished = false, $showInNav = null, $additionalFields = null, $lang = '')
    {
        if ($parentId == -1)
            $parentId = '(SELECT treeId FROM tree WHERE parentId = 0)';


        $published = '';
        if ($onlyPublished) {
            //Show only published
            $ts = time();
            $published = "AND ((UNIX_TIMESTAMP(p.publicationdate) <= $ts AND UNIX_TIMESTAMP(p.expirationdate) >= $ts) OR p.alwayspublished = 1) ";
        }

        $navsql = $this->_getShowInNavSql($showInNav);

        $addjoin = '';
        $straddfield = '';
        $addfield = array();
        if ($additionalFields != null) {
            $langs = explode(',', AVAILABLELANG);
            // If empty, use default language
            // Alternatively, we could use the selected language, may be better...
            if ($lang == '')
                $lang = $langs[0];

            $i = 0;
            if (count($langs) <= 1) {
                foreach ($additionalFields as $field) {
                    $addfield[] = 'c' . $i . '.`value` AS `' . Connection::getInstance()->escape_string($field) . '`';
                    $addjoin .= 'LEFT JOIN content c' . $i . ' ON c' . $i . '.pageId = p.pageId AND c' . $i . '.`lang`=\'' . Connection::getInstance()->escape_string($lang) . '\' AND c' . $i . '.`field`=\'' . Connection::getInstance()->escape_string($field) . '\'' . "\r\n";
                    $i++;
                }

            } else {
                $index = array_search($lang, $langs);
                array_splice($langs, $index, 1);
                $numlangs = count($langs);
                foreach ($additionalFields as $field) {
                    $sel = 'IFNULL(c' . $i . '.`value`,';

                    if ($numlangs > 1) {
                        // 1 additional language
                        //$sel .= 'c0'. $i . $langs[0] .'.`value`';
                        //} else {
                        // 2 or more additional languages
                        for ($j = 0; $j < $numlangs; $j++) {
                            // Skip last value
                            if ($j < $numlangs - 1) {
                                $sel .= 'IFNULL(c' . $i . $langs[$j] . '.`value`,';
                                // Create joins
                                $addjoin .= 'LEFT JOIN content c' . $i . $langs[$j] . ' ON c' . $i . $langs[$j] . '.pageId = p.pageId AND c' . $i . $langs[$j] . '.`lang`=\'' . Connection::getInstance()->escape_string($langs[$j]) . '\' AND c' . $i . $langs[$j] . '.`field`=\'' . Connection::getInstance()->escape_string($field) . '\'' . "\r\n";
                            }
                        }


                    }
                    $sel .= 'c' . $i . $langs[$numlangs - 1] . '.`value`' . str_repeat(')', $numlangs) . ' as `' . Connection::getInstance()->escape_string($field) . '`';
                    $addjoin .= 'LEFT JOIN content c' . $i . $langs[$numlangs - 1] . ' ON c' . $i . $langs[$numlangs - 1] . '.pageId = p.pageId AND c' . $i . $langs[$numlangs - 1] . '.`lang`=\'' . Connection::getInstance()->escape_string($langs[$numlangs - 1]) . '\' AND c' . $i . $langs[$numlangs - 1] . '.`field`=\'' . Connection::getInstance()->escape_string($field) . '\'' . "\r\n";

                    $addfield[] = $sel;
                    $addjoin .= 'LEFT JOIN content c' . $i . ' ON c' . $i . '.pageId = p.pageId AND c' . $i . '.`lang`=\'' . Connection::getInstance()->escape_string($lang) . '\' AND c' . $i . '.`field`=\'' . Connection::getInstance()->escape_string($field) . '\'' . "\r\n";

                    $i++;
                }

            }
            $straddfield = join(', ', $addfield) . ', ';
        }
        $u = new User();
        $user = $u->getAuthUser();
        $uid = ($user) ? (int)$user->userId : 'null';

        // Select all pages where no login is required
        // Next, select pages where login IS required, and join on the usergroups
        // Group it together and order is by index
        // @ todo: multiple groups
        $sql =
            "SELECT t.*, p.*, $straddfield (SELECT COUNT(`treeId`) FROM tree WHERE parentId=t.treeId) AS numChildren  FROM tree t
INNER JOIN `page` p ON t.pageId = p.pageId
$addjoin
WHERE parentId=$parentId AND loginrequired = 0 $navsql $published

UNION

SELECT t.*, p.*, $straddfield (SELECT COUNT(`treeId`) FROM tree WHERE parentId=t.treeId) AS numChildren FROM tree t
INNER JOIN `page` p ON t.pageId = p.pageId
INNER JOIN treeaccess ta ON t.treeId = ta.treeId
INNER JOIN userusergroups uug ON ta.groupId = uug.groupId AND uug.userId=$uid
$addjoin
WHERE parentId=$parentId AND  loginrequired = 1 $navsql $published

ORDER BY `index`";

        $children = $this->_conn->getRows($sql);

        return $children;
    }

    /**
     * Gets the children by their id's, it also returns their parent nodes, all the way up to the root<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param array $ids An array of treeIds
     * @param boolean $includeParents When false only an array of pages is returned;
     * @return Object An object with the result as array and as nested array (tree structure)
     */
    public function getChildrenByIds($ids, $includeParents = true)
    {

        $c = new Cache();
        $cname = 'tree-getChildrenByIds-' . md5(json_encode(func_get_args()));
        $ret = $c->getCache($cname);
        if ($ret)
            return $ret;

        $map = array();
        if ($includeParents === false) {
            foreach ($ids as $id) {
                $map[] = $this->getChild($id, false);
            }
            $c->setCache($map, $cname, strtotime('+1 year'));
            return $map;
        }

        $root = null;
        foreach ($ids as $id) {
            $reqid = (int)$id;
            while ($id != 0) {
                $child = $this->getChild($id, true);
                $child->children = $this->getChildren($id);
                foreach ($child->children as $node) {
                    $tid = (int)$node->treeId;
                    if (!array_key_exists($tid, $map)) {
                        $map[$tid] = $node;
                    } else {
                        $node = $map[$tid];
                    }
                }
                if ($id == $reqid) {
                    $map[$id] = $child;
                    $pid = (int)$child->parentId;
                    if (array_key_exists($pid, $map)) {
                        for ($i = 0; $i < count($map[$pid]->children); $i++) {
                            if ((int)$map[$pid]->children[$i]->treeId == (int)$id)
                                $map[$pid]->children[$i] = $child;
                        }
                    }
                } else {
                    if (!array_key_exists($id, $map))
                        $map[$id] = $child;
                }
                $id = (int)$child->parentId;
                if ($id == 0)
                    $root = $child;

            }
        }
        $ret = new \stdClass();
        $ret->arr = $map;
        $ret->tree = $map[1];
        $c->setCache($ret, $cname, strtotime('+1 year'));
        return $ret;

    }

    /**
     * Shorthand for the getChildren method. Use this in your frontend.<br/>
     * This calles the getChildren method with the correct settings for frontend access
     * @param int $parentId The id of the parentnode
     * @return array An array of OTreeNodes
     * @throws \Exception
     */
    public function getNavigation($parentId)
    {
        if (!is_numeric($parentId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $childrenrows = $this->getChildren($parentId, true, false, true, true);

        return $childrenrows;
    }

    /**
     * Returns the full navigation, both as array and as tree
     * @param boolean $includeAll Pages with showinnavigation set to false are also returned (default = false)
     * @param boolean $onlyPublished When true, unpublished pages are also returned (default = false)
     * @return \stdClass An object containing 'arr' (a plain array of OTreeNodes) & 'tree' (Multidimensional array)
     */
    public function getFullNavigation($includeAll = false, $onlyPublished = false)
    {

        $cl = new User();
        $where = '';
        $where .= ($includeAll) ? '' : 'AND p.showinnavigation = 1';
        $where .= (!$onlyPublished) ? '' : ' AND ((UNIX_TIMESTAMP(p.publicationdate) <= ' . time() . '
				AND UNIX_TIMESTAMP(p.expirationdate) >= ' . time() . ')
				OR p.alwayspublished = 1) ';

        $sql = 'SELECT t.*, p.label, it.label AS `itemLabel`, it.icon AS `itemicon`,
					(SELECT COUNT(`treeId`)
					FROM tree
					WHERE parentId=t.treeId) AS numChildren
				FROM itemtypes it, tree t
				JOIN page p on t.pageId = p.pageId
				WHERE p.itemType = it.itemId
				' . $where . '
				ORDER BY t.parentId, t.index ASC';

        // DEBUG SPEED UP!:
        //$sql .= ' LIMIT 0,1';

        $result = $this->_conn->getRows($sql);
        $page = new Page();
        $root = $this->getRoot();
        $rootid = $root->treeId;
        unset($root);
        $root = new OTreeNode();
        $treearr = array();
        foreach ($result as $row) {
            $to = new OTreeNode();
            $to->treeId = (double)$row->treeId;
            $to->parentId = (double)$row->parentId;
            $to->locked = ($row->locked == 1);
            $to->page = $page->getPageById($row->pageId);
            $to->path = $this->getPath($to->treeId, $rootid);
            $to->shortcut = (double)$row->shortcut;
            $to->numChildren = (double)$row->numChildren;
            if ($to->numChildren > 0)
                $to->children = array();

            $treearr[$to->treeId] = $to;
            if ($to->parentId == 0)
                $root = $to;
        }
        foreach ($treearr as $treenode) {
            if (array_key_exists((int)$treenode->parentId, $treearr)) {
                $node = $treearr[$treenode->parentId];
                if (!$node->loginrequired || $cl->isLoggedIn())
                    $node->children[] = $treenode;
            } else {

            }
        }

        $retObj = new \stdClass();
        $retObj->arr = $treearr;
        $retObj->tree = $root;
        return $retObj;
    }

    /**
     * Gets the child with the selected Id,
     * @param int $treeId The id of the child
     * @param boolean $includeNC Specifies whether to include the number of Children
     * @param boolean $includePath Specifies whether to include the full path
     * @param boolean $includeContents Specifies whether to include the full page
     * @return OTreeNode The child as OTreeNode
     */
    public function getChild($treeId, $includeNC = false, $includePath = false, $includeContents = true)
    {

        if ($includeNC == true) {
            $sql = 'SELECT t.*, ' .
                '(SELECT COUNT(`treeId`) ' .
                'FROM tree ' .
                'WHERE parentId=t.treeId) ' .
                'AS numChildren ' .
                'FROM tree t ' .
                'WHERE t.treeId=' . (int)$treeId;
        } else {
            $sql = 'SELECT t.* FROM tree t WHERE treeId=' . (int)$treeId;
        }

        $child = $this->_conn->getRow($sql, 'OTreeNode');
        if (!$child)
            return null;

        if ($child->shortcut > 0)
            return $this->getChild($child->shortcut, true);

        if ($includeContents === true) {
            $ci = new Page();
            $child->page = $ci->getPageById($child->pageId);
        }

        if ($includePath)
            $child->path = $this->getPath($treeId);

        return $child;
    }

    /**
     * Gets the parent of a specified node
     * @param int $treeId The id of the node
     * @return OTreeNode The OTreeNode of the parent, or null when nothing was found
     * @throws \Exception
     */
    public function getParent($treeId)
    {
        if (!is_numeric($treeId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $sql = 'SELECT t.*, ' .
            '(SELECT COUNT(`treeId`) ' .
            'FROM tree ' .
            'WHERE parentId=t.treeId) ' .
            'AS numChildren ' .
            'FROM tree t ' .
            'WHERE t.treeId=(SELECT t.parentId FROM tree t WHERE t.treeId = ' . (int)$treeId . ')';

        $parentrow = $this->_conn->getRow($sql);
        if (!$parentrow)
            return null;

        $parent = new OTreeNode();
        $ci = new Page();

        $parent->page = $ci->getPageById($parentrow->pageId);
        if ($parentrow->numChildren > 0) {
            $parent->children = null;
        }
        foreach ($parentrow as $key => $value) {
            if (isset($parent->$key)) {
                $parent->$key = $value;
            }
        }
        return $parent;
    }

    public function getNext($treeId, $orderfield = 'index', $dir = 1)
    {
        return $this->_getSibling($treeId, $orderfield, $dir, 'next');
    }

    public function getPrev($treeId, $orderfield = 'index', $dir = 1)
    {
        return $this->_getSibling($treeId, $orderfield, $dir, 'prev');
    }

    private function _getSibling($treeId, $orderfield = 'index', $orderdir = 1, $dir = 'next')
    {
        $treeId = filter_var($treeId, FILTER_VALIDATE_INT);
        $orderdir = filter_var($orderdir, FILTER_VALIDATE_INT);
        $orderfield = filter_var($orderfield, FILTER_SANITIZE_STRING);
        if (!$treeId) {
            throw new ParameterException(ParameterException::INTEGER_EXCEPTION);
        }
        if (!$orderdir) {
            throw new ParameterException(ParameterException::INTEGER_EXCEPTION);
        }


        $dir = ($dir == 'next') ? '+1' : '-1';
        $sql = "SELECT treeId 
				FROM tree 
				WHERE parentId=(SELECT parentId
								FROM tree t1 
								WHERE treeId={$treeId}) 
								AND `index`=((SELECT `index` 
											FROM tree t2 
											WHERE treeId={$treeId})$dir)";
        if ($orderfield != 'index') {
            // Order by page
            switch ($orderfield) {
                case 'publicationdate':
                case 'expirationdate':
                case 'modificationdate':
                case 'label':
                    $compator = $dir == '+1' ? '>=' : '<=';
                    $ascdesc = $dir == '+1' ? 'ASC' : 'DESC';

                    $sql = "SELECT treeId
							FROM tree t
							INNER JOIN page p ON t.pageId = p.pageId AND p.$orderfield $compator (SELECT p2.$orderfield FROM page p2 INNER JOIN tree t2 ON t2.pageId=p2.pageId AND t2.treeId={$treeId})
							WHERE parentId=(SELECT parentId FROM tree t3 WHERE t3.treeId=$treeId)
							AND t.treeId <> $treeId
							ORDER BY p.$orderfield $ascdesc		
							LIMIT 0,1";
                    break;
                default:
                    return null;
            }


        }
        $tid = $this->_conn->getField($sql);
        if ($tid) {
            return $this->getChild($tid);
        }

    }

    /**
     * Gets the root node
     * @return OTreeNode The root node
     */
    public function getRoot()
    {
        $sql = 'SELECT t.*, ' .
            '(SELECT COUNT(`treeId`) ' .
            'FROM tree ' .
            'WHERE parentId=t.treeId) ' .
            'AS numChildren ' .
            'FROM tree t ' .
            'WHERE t.parentId=0';

        $rootrow = $this->_conn->getRow($sql);

        $root = new OTreeNode();
        $ci = new Page();
        $root->page = $ci->getPageById($rootrow->pageId);
        if ($rootrow->numChildren > 0) {
            $root->children = null;
        }
        foreach ($rootrow as $key => $value) {
            if (isset($root->$key)) {
                $root->$key = $value;
            }
        }
        return $root;
    }

    /**
     * Creates a shortcut to another node in the tree<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param int $targetId The id of the target node
     * @param int $pageId The id of the page of the target (for easy label / title fetching)
     * @param int $parentId The id of the parent node of the shortcut
     * @param int $index The child index of the shortcut
     * @return array
     * @throws \Exception
     */
    public function setShortcut($targetId, $pageId, $parentId, $index)
    {
        if (!$this->IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

        if (!is_numeric($parentId) || !is_numeric($index) || !is_numeric($targetId) || !is_numeric($pageId)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }

        $this->_cleanIndexes($parentId);

        $this->_updateIndexes((int)$index, 1, (int)$parentId);

        $sql = 'INSERT INTO tree (`parentId`, `pageId`, `index`, `locked`, `shortcut`) ' .
            'VALUES ' .
            '(' . $parentId . ', ' .
            $pageId . ', ' .
            $index . ', ' .
            '0, ' .
            $targetId . ')';
        $this->_conn->insertRow($sql);
        $this->generateSitemap();

        return $this->getChildren($parentId);
    }

    public function setTreeAccess($treeId, $groups)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!is_numeric($treeId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        if (!is_array($groups))
            throw $this->throwException(ParameterException::ARRAY_EXCEPTION);

        $lr = (count($groups) == 0) ? '0' : '1';
        $sql = 'UPDATE tree SET loginrequired=' . $lr . ' WHERE treeId=' . (int)$treeId;
        $this->_conn->updateRow($sql);

        $sql = 'DELETE FROM `treeaccess` WHERE treeId=' . (int)$treeId;
        $this->_conn->deleteRow($sql);

        if ($lr == '1') {
            $sql = 'INSERT INTO `treeaccess` (`treeId`, `groupId`) VALUES ';
            $rows = array();
            foreach ($groups as $groupId) {
                $rows[] = '(' . (int)$treeId . ', ' . (int)$groupId . ')';
            }
            $sql .= join(',', $rows);
            $this->_conn->insertRow($sql);
        }

        return true;
    }

    public function getTreeAccess($treeId)
    {
        $sql = 'SELECT `groupId` FROM `treeaccess` WHERE treeId=' . $treeId;
        return $this->_conn->getFields($sql);
    }

    public function setLocked($treeId, $locked)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!is_numeric($treeId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        if (!is_bool($locked))
            throw $this->throwException(ParameterException::BOOLEAN_EXCEPTION);

        $lock = ($locked) ? 1 : 0;

        if (!$locked) {
            $sql = 'SELECT COUNT(`treeId`) FROM `tree` WHERE `parentId`=' . (int)$treeId . ' AND locked=1';
            if ((int)$this->_conn->getField($sql) > 0)
                throw $this->throwException(6002);
        }

        $sql = 'UPDATE tree SET locked=' . $lock . ' WHERE treeId=' . $treeId;
        $this->_conn->updateRow($sql);
        if ($lock == 0)
            return true;

        $sql = 'SELECT parentId FROM tree WHERE treeId=' . $treeId;
        $parentId = (int)$this->_conn->getField($sql);
        while ($parentId > 0) {
            $update = 'UPDATE tree SET locked=1 WHERE treeId=' . $parentId;
            $this->_conn->updateRow($update);

            $sql = 'SELECT parentId FROM tree WHERE treeId=' . $parentId;
            $parentId = (int)$this->_conn->getField($sql);
        }

        return true;
    }

    public function checkLock($pageId)
    {
        $sql = 'SELECT COUNT(`treeId`) FROM `tree` WHERE `pageId`=' . (int)$pageId . ' AND locked=1';
        return ((int)$this->_conn->getField($sql) > 0);
    }

    /**
     * Batch page add. This function is still in beta<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @since 2.1 - 7 jun 2010
     * @param array $pages An array of OPage objects
     * @param int $parentId The id of the parent
     * @param int $index The index of the page
     * @return OTreeNode The new treenode
     * @throws \Exception
     */
    public function setPages($pages, $parentId, $index)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!is_numeric($parentId) || !is_numeric($index))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $ncResult = $this->_getMaxChildren($parentId);
        if ((int)$ncResult->numchildren + count($pages) > (double)$ncResult->maxchildren && (double)$ncResult->maxchildren > -1)
            $this->throwException(6001, array($ncResult->maxchildren));


        $sql = 'SELECT `loginrequired` FROM tree WHERE `parentId`=' . $parentId;
        $result = $this->_conn->getRow($sql);
        $loginreq = 0;
        if ($result)
            $loginreq = (int)$result->loginrequired;

        // Do the for loop first, so we have the right amount of pages
        // to update the indexes with
        $c = 0;
        $values = array();
        $ph = class_exists('TreeHook') ? new \TreeHook() : null;

        foreach ($pages as $page) {
            // Silently skip pages which are already under the parent nodes
            if (!$this->_checkForPageExistance($page->pageId, $parentId)) {
                $values[] = '(' . $parentId . ', ' . $page->pageId . ', ' . ($index + $c) . ', ' . $loginreq . ')';
                if (isset($ph) && method_exists($ph, 'preSetPage'))
                    $page = $ph->preSetPage($page, $parentId, $index);
                $c++;
            }
        }

        if ($c == 0) {
            // All pages already exist. Probably happens when a single page is added. So throw exception
            throw $this->throwException(PageException::DUPLICATE_PAGE);
        }

        $this->_cleanIndexes($parentId);
        $this->_updateIndexes($index, $c, $parentId);
        $sql = 'INSERT INTO tree (`parentId`, `pageId`, `index`, `loginrequired`) ' .
            'VALUES ';

        $sql .= join(',', $values);
        $this->_conn->insertRow($sql);
        $cache = new Cache();
        $cache->flushCache();
        $this->generateSitemap();

        if (isset($ph) && method_exists($ph, 'postSetPage')) {
            foreach ($pages as $page) {
                $ph->postSetPage($page, $parentId);
            }
        }

        return $this->getChildren($parentId);
    }

    /**
     * Adds a new page to the tree<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param OPage $page page The page to add
     * @param int $parentId The id of the parent
     * @param int $index The index of the page
     * @param bool $returnId Return the id of the page?
     * @return array|int The new treenode
     * @throws \Exception
     */
    public function setPage($page, $parentId, $index, $returnId = false)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!is_numeric($parentId) || !is_numeric($index))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        // If page already exists, throw exist exception
        if ($this->_checkForPageExistance($page->pageId, $parentId))
            throw $this->throwException(6003);

        // Check if the number of children doesn't exceed the maximum
        $ncResult = $this->_getMaxChildren($parentId);

        if ((double)$ncResult->numchildren >= (double)$ncResult->maxchildren && (double)$ncResult->maxchildren > -1)
            $this->throwException(6001, array($ncResult->maxchildren));

        if ((int)$page->pageId == 0) {
            $pageClass = new Page();
            $page = $pageClass->setPage($page, false);
        }
        if (class_exists('TreeHook')) {
            $ph = new \TreeHook();
            if (method_exists($ph, 'preSetPage'))
                $page = $ph->preSetPage($page, $parentId, $index);
        }
        $loginreq = ($page->itemLabel == 'login') ? 1 : 0;
        // If parent is a login, set required to 1,
        // This way, we can easily exclude hidden pages from search and sitemap
        if ($loginreq == 0) {
            $sql = 'SELECT `loginrequired` FROM tree WHERE `parentId`=' . $parentId;
            $result = $this->_conn->getRow($sql);
            if ($result)
                $loginreq = (int)$result->loginrequired;
        }
        $this->_cleanIndexes($parentId);
        $this->_updateIndexes($index, 1, $parentId);
        $sql = 'INSERT INTO tree (`parentId`, `pageId`, `index`, `loginrequired`) ' .
            'VALUES ' .
            '(' . $parentId . ', ' .
            $page->pageId . ', ' .
            $index . ', ' .
            $loginreq . ')';
        $id = $this->_conn->insertRow($sql);

        $cache = new Cache();
        $cache->flushCache();

        $this->generateSitemap();


        if (isset($ph) && method_exists($ph, 'postSetPage')) {
            $ph->postSetPage($page, $parentId);
        }

        if ($returnId) {
            return $id;
        }

        return $this->getChildren($parentId);

    }

    /**
     * Moves an existing pages in the tree<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @since 2.2 - 27 jul 2010
     * @param array $items An array of objects containing the following properties: treeId, oldparentId, oldindex;
     * @param int $newParentId The id of the new parent
     * @param int $newIndex The index of the new items
     * @return \stdClass An object containing the oldparents and their children and the children of the new parent
     * @throws \Exception
     */
    public function movePages($items, $newParentId, $newIndex)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if (!is_array($items))
            throw $this->throwException(ParameterException::ARRAY_EXCEPTION);

        if (!is_numeric($newParentId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        if (!is_numeric($newIndex))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $movedwithin = 0;
        foreach ($items as $child) {
            if ($child->oldparentId == $newParentId)
                $movedwithin++;
        }

        $ncResult = $this->_getMaxChildren($newParentId);
        if ((int)$ncResult->numchildren + $movedwithin > (int)$ncResult->maxchildren && (int)$ncResult->maxchildren > -1)
            $this->throwException(6001, array($ncResult->maxchildren));

        $retObj = new \stdClass();
        $retObj->oldParents = array();

        foreach ($items as $child) {
            // Cast to object
            if (is_array($child))
                $child = (object)$child;

            if ($child->oldparentId != $newParentId) {
                $sql = 'SELECT pageId FROM tree WHERE treeId = ' . $child->treeId;
                // Silently skip duplicates
                if (!$this->_checkForPageExistance((int)$this->_conn->getField($sql), $newParentId)) {


                    $this->_cleanIndexes($child->oldparentId);
                    $this->_cleanIndexes($newParentId);
                    $this->_updateIndexes($child->oldindex, -1, $child->oldparentId);
                    $this->_updateIndexes($newIndex, 1, $newParentId);

                    $sql = 'UPDATE tree ' .
                        'SET `parentId`=' . $newParentId . ', ' .
                        '`index`=' . $newIndex . ' ' .
                        'WHERE `treeId`=' . $child->treeId;
                    $this->_conn->updateRow($sql);

                    $newIndex++;

                }

                $retObj->oldParents[(int)$child->oldparentId] = $this->getChildren($child->oldparentId);

            }

        }
        $cache = new Cache();
        $cache->flushCache();
        $this->generateSitemap();
        $retObj->newParent = $this->getChildren($newParentId);
        return $retObj;
    }

    /**
     * Moves an existing page in the tree<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param int $treeId The id of the page to move
     * @param int $oldParentId The id of the current parent
     * @param int $newParentId The id of the new parent
     * @param int $oldIndex The old index
     * @param int $newIndex The new index
     * @return array The children of the new parent
     * @throws \Exception
     */
    public function movePage($treeId, $oldParentId, $newParentId, $oldIndex, $newIndex)
    {

        if (!$this->IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

        if (!is_numeric($treeId)
            || !is_numeric($oldParentId)
            || !is_numeric($newParentId)
            || !is_numeric($oldIndex)
            || !is_numeric($newIndex)
        ) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }

        // Check if the number of children doesn't exceed the maximum
        if ((int)$oldParentId != (int)$newParentId) {
            $sql = 'SELECT `maxchildren`, (SELECT COUNT(nct.`treeId`) ' .
                'FROM `tree` nct ' .
                'WHERE nct.parentId=' . $newParentId . ') ' .
                'AS numchildren  ' .
                'FROM `itemtypes` ' .
                'WHERE `itemId`=(SELECT `itemType` ' .
                'FROM `page` p ' .
                'WHERE p.`pageId`=(SELECT t.`pageId` ' .
                'FROM `tree` t ' .
                'WHERE `treeId`=' . $newParentId . '))';
            $ncResult = $this->_conn->getRow($sql);
            if ((double)$ncResult->numchildren >= (double)$ncResult->maxchildren && (double)$ncResult->maxchildren > -1)
                throw $this->throwException(6001, array($ncResult->maxchildren));
        }


        $retObj = new \stdClass();
        $cache = new Cache();

        // Check if the page exists in the new parent,
        //only if the newparent is actually another parent
        if ((int)$oldParentId != (int)$newParentId) {
            $sql = 'SELECT pageId FROM tree WHERE treeId = ' . $treeId;
            $page = $this->_conn->getRow($sql);
            if ($this->_checkForPageExistance($page->pageId, $newParentId)) {
                $retObj->oldParent = $this->getChildren($oldParentId);
                $retObj->newParent = $this->getChildren($newParentId);
                $cache->flushCache();
                return $retObj;
            }
        }

        $this->_cleanIndexes($oldParentId);
        $this->_cleanIndexes($newParentId);
        $this->_updateIndexes($oldIndex, -1, $oldParentId);
        $this->_updateIndexes($newIndex, 1, $newParentId);

        $sql = 'UPDATE tree ' .
            'SET `parentId`=' . $newParentId . ', ' .
            '`index`=' . $newIndex . ' ' .
            'WHERE `treeId`=' . $treeId;
        $this->_conn->updateRow($sql);
        $cache->flushCache();

        $this->generateSitemap();

        $retObj->oldParent = $this->getChildren($oldParentId);
        $retObj->newParent = $this->getChildren($newParentId);
        return $retObj;
    }

    /**
     * Deletes a page from the tree<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>DELETE_PAGE</li>
     * </ul>
     * @param int $treeId The id of the node to delete
     * @return bool True when successful
     * @throws \Exception
     */
    public function removeChild($treeId)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!$this->DELETE_PAGE)
            throw $this->throwException(PageException::DELETE_PAGE_NOT_ALLOWED);
        // H@xx0rz!
        if (!is_numeric($treeId) || $treeId < 1)
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $sql = 'DELETE FROM tree WHERE treeId=' . (int)$treeId;
        $this->_conn->deleteRow($sql);
        $this->_cleanup();
        return true;
    }

    /**
     * Deletes a page from the tree<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>DELETE_PAGE</li>
     * </ul>
     * @param array $treeIds An array pf treeIds
     * @return bool True when succeeded
     * @throws \Exception
     * @since 2.1 - 7 jun 2010
     */
    public function removeChildren($treeIds)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        if (!$this->DELETE_PAGE)
            throw $this->throwException(PageException::REMOVE_PAGE_NOT_ALLOWED);

        foreach ($treeIds as $treeId) {
            if (!is_numeric($treeId) || $treeId < 1) {
                throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
            }
        }

        $sql = 'DELETE FROM tree WHERE treeId=' . join(' OR treeId= ', $treeIds);
        $this->_conn->deleteRow($sql);
        $this->_cleanup();
        return true;
    }


    /**
     * Gets a child by it's pagelabel
     * @param int $parentId The id of the parent (this is required because a page can exists on more then one place in the tree)
     * @param string $label The label of the page
     * @return OTreeNode The child
     * @throws \Exception
     */
    public function getChildByLabel($parentId, $label)
    {
        if (!is_numeric($parentId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $sql = 'SELECT t.*, i.parser ' .
            'FROM tree t ' .
            'LEFT JOIN page p on t.pageId = p.pageId ' .
            'LEFT JOIN itemtypes i ON p.itemType = i.itemId ' .
            'WHERE t.parentId=' . $parentId . '	' .
            "AND p.label = '" . Connection::getInstance()->escape_string($label) . "'	" .
            'AND ((UNIX_TIMESTAMP(p.publicationdate) <= ' . time() . '
				AND UNIX_TIMESTAMP(p.expirationdate) >= ' . time() . ')
				OR p.alwayspublished = 1) ';
        $child = $this->_conn->getRow($sql, 'OTreeNode');
        if (!$child)
            return null;

        if ($child->loginrequired) {
            $child->requiredgroups = $this->getTreeAccess($child->treeId);
        }

        return $child;
    }

    /**
     * Gets all the nodes with a specified template
     * @param string $type The name of the template
     * @param string $order The field to order the nodes. (t = TreeNode properties, p = page properties)
     * @param int $startIndex The index to begin returning with
     * @param int $maxItems The number of nodes to return
     * @return array An array of OTreeNodes
     * @throws \Exception
     */
    public function getChildrenByType($type = 'homepage', $order = 't.treeId', $startIndex = 0, $maxItems = 0)
    {
        if (!is_numeric($startIndex)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }

        if (!is_numeric($maxItems)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }

        $sql = 'SELECT t.* ' .
            'FROM tree t ' .
            'JOIN page p on t.pageId = p.pageId ' .
            "WHERE p.itemType = (SELECT it.itemId FROM itemtypes it WHERE it.label = '" . Connection::getInstance()->escape_string($type) . "') " .
            'AND ((UNIX_TIMESTAMP(p.publicationdate) <= ' . time() . '
				AND UNIX_TIMESTAMP(p.expirationdate) >= ' . time() . ')
				OR p.alwayspublished = 1) ' .
            'ORDER BY ' . Connection::getInstance()->escape_string($order);
        if ($maxItems > 0)
            $sql .= ' LIMIT ' . $startIndex . ', ' . $maxItems;
        $childrenRows = $this->_conn->getRows($sql);
        $children = array();
        $root = $this->getRoot();
        $page = new Page();
        foreach ($childrenRows as $childRow) {
            $child = new OTreeNode();
            $child->treeId = $childRow->treeId;
            $child->parentId = $childRow->parentId;
            $child->index = $childRow->index;
            $child->path = $this->getPath($child->treeId, $root->treeId);
            $child->page = $page->getPageById($childRow->pageId);
            $child->loginrequired = $childRow->loginrequired;
            $child->init();
            $children[] = $child;
            unset($child);
        }
        return $children;
    }

    /**
     * Generates a sitemap in the documentroot, named sitemap.xml. Make sure this file exists and is writable by apache
     */
    public function generateSitemap()
    {
        if ((defined('SPHIDERAVAILABLE') && SPHIDERAVAILABLE) || !GENERATESITEMAP) {
            return;
        }

        $root = $this->getRoot();

        $langarr = explode(',', AVAILABLELANG);

        $query = 'SELECT t.*, p.*, it.label AS `itemLabel`, it.lifetime, it.priority,
				UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`,
				UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`,
				UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`
				FROM itemtypes it, tree t
				JOIN page p on t.pageId = p.pageId
				WHERE p.itemType = it.itemId
				AND t.loginrequired = 0
				AND	t.shortcut = 0
				AND ((p.publicationdate >= UNIX_TIMESTAMP(' . time() . ')
							AND p.expirationdate <= UNIX_TIMESTAMP(' . time() . '))
							OR p.alwayspublished = 1)
				ORDER BY t.parentId, t.index ASC';
        $result = $this->_conn->getRows($query);
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>
					<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($result as $page) {
            $path = $this->getPath($page->treeId, $root->treeId);
            $lifearr = explode(' ', $page->lifetime);
            $life = substr($lifearr[1], 0, 3);
            $lifetime = '';

            switch ($life) {
                case 'yea':
                    $lifetime = 'yearly';
                    break;
                case 'mon':
                    $lifetime = 'monthly';
                    break;
                case 'wee':
                    $lifetime = 'weekly';
                    break;
                case 'day':
                    $lifetime = 'daily';
                    break;
                case 'hou':
                    $lifetime = 'hourly';
                    break;
                case 'min':
                    $lifetime = 'hourly';
                    break;
            }

            if ($lifearr[0] == 0 || $lifearr[0] == '0')
                $lifetime = 'always';

            foreach ($langarr as $lang) {
                $url = BASEURL;
                if (defined('USEPREFIX') && USEPREFIX == false) {
                    // Nothing
                } else {
                    $url = BASEURL . $lang . '/';
                }
                $sitemap .= '<url>
								<loc>' . $url . $path . '</loc>
								<lastmod>' . date('Y-m-d', $page->modificationdate) . '</lastmod>
								<changefreq>' . $lifetime . '</changefreq>
								<priority>' . $page->priority . '</priority>
							</url>
							';
            }

        }
        $sitemap .= '</urlset>';
        try {
            @file_put_contents(BASEPATH . 'sitemap.xml', $sitemap);
        } catch (\Exception $ex) {
            throw $this->throwException(PageException::SITEMAP_CREATE);
        }
    }

    /**
     * Gets all the parents of an item, up to the root
     * @param int $treeId The id of the item
     * @return array An array of OTreeNodes
     */
    public function getBreadCrumb($treeId)
    {
        $parentId = $treeId;
        $items = array();
        while ($parentId > 0) {
            $items[] = $this->getChild($parentId, false, true);
            $parentId = $items[count($items) - 1]->parentId;
        }

        return array_reverse($items);
    }

    /**
     * Gets the path of the child
     * @ignore
     * @param $treeId
     * @param int $rootId treeId The id of the childnode
     * @throws \Exception
     * @return string The path of the child
     */
    public function getPath($treeId, $rootId = 1)
    {
        if (!is_numeric($treeId) || !is_numeric($rootId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        $path = '';

        $otid = $treeId;
        $limiter = 1000;
        while ($treeId != $rootId && $treeId != null && $treeId > 0 && --$limiter > 0) {
            $sql = 'SELECT t.parentId, p.label ' .
                'FROM tree t ' .
                'JOIN page p on t.pageId = p.pageId ' .
                'WHERE t.treeId=' . (int)$treeId;
            $row = $this->_conn->getRow($sql);
            if (!$row)
                return null;

            if ($row->parentId == $treeId)
                return null;

            $treeId = $row->parentId;
            $path = $row->label . '/' . $path;
            if ($limiter == 1) {
                error_log("Cannot find path for $otid / $rootId, current tid: $treeId, path: $path");
            }
        }
        return $path;
    }

    /**
     * Gets all the treenodes with the specified pageId
     * @param int $pageId The id of the page
     * @return array A multidimensional array, with treeId and path
     * @throws \Exception
     * @since 2.3 - 8 sep 2010
     */
    public function getCanonical($pageId)
    {
        if (!is_numeric($pageId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $sql = 'SELECT treeId FROM tree WHERE pageId=' . (int)$pageId . ' ORDER BY treeId';
        $conn = Connection::getInstance();
        $arr = $conn->getFields($sql);
        $nodes = array();
        foreach ($arr as $treeId) {
            $nodes[] = array('treeId' => $treeId, 'path' => $this->getPath($treeId));
        }
        return $nodes;
    }

    /**
     * Removes branches with non-existing parentId's from the tree,<br/>
     * executed as long as the number of deleted rows != 0<br/>
     * Since this method is kind of harmless, no permissions are required
     */
    private function _cleanup()
    {
        $sql = 'DELETE FROM tree WHERE parentId NOT IN (SELECT treeId FROM (SELECT * FROM tree tr) as `tid`) AND parentId > 0';
        $affected = 1;
        while ($affected > 0) {
            $affected = $this->_conn->deleteRow($sql);
        }
    }

    /**
     * Checks if the page already exists in the tree
     * @param int $pageId The pageId to check
     * @param int $parentId The parentId to check in
     * @return bool True when there is already a page with the given pageId
     * @throws \Exception
     */
    private function _checkForPageExistance($pageId, $parentId)
    {
        if (!is_numeric($pageId) || !is_numeric($parentId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $sql = 'SELECT COUNT(pageId) as pagesCount ' .
            'FROM tree t ' .
            'WHERE pageId=' . $pageId . ' ' .
            'AND parentId=' . $parentId;
        $count = $this->_conn->getRow($sql);
        return ($count->pagesCount > 0);
    }


    /**
     * Sanitizes the indexes of the childs of the node<br/>It removes gaps between indexes, so 1,2,5,6,7 becomes 0,1,2,3,4
     * @param int $parentId The id of the parentNode
     */
    private function _cleanIndexes($parentId)
    {
        $sql = 'SELECT `treeId`, `index` ' .
            'FROM `tree` ' .
            'WHERE `parentId`=' . (int)$parentId . ' ' .
            'ORDER BY `index`';
        $result = $this->_conn->getRows($sql);
        $index = 0;
        foreach ($result as $row) {
            $sql = 'UPDATE tree SET `index` = ' . $index . ' WHERE `treeId`=' . $row->treeId;
            $this->_conn->updateRow($sql);
            $index++;
        }

    }

    /**
     * Updates the indexes of all child nodes under parentId
     * @param int $fromIndex The index to start updating from
     * @param int $updateWith The value to alter the index with (1 or -1)
     * @param int $parentId The id of the parent
     * @throws \Exception
     */
    private function _updateIndexes($fromIndex, $updateWith, $parentId)
    {
        if (!is_numeric($fromIndex) || !is_numeric($updateWith) || !is_numeric($parentId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $sql = 'UPDATE tree ' .
            'SET `index` = `index`+' . $updateWith . ' ' .
            'WHERE `index` >= ' . $fromIndex . ' ' .
            'AND parentId = ' . $parentId;
        $this->_conn->updateRow($sql);
    }

    /**
     * Returns an object containing maxchildren and numchildren
     * @param int $treeId The treeId
     * @return \stdClass Object containing maxchildren and numchildren
     */
    private function _getMaxChildren($treeId)
    {
        $sql = 'SELECT `maxchildren`, (SELECT COUNT(nct.`treeId`) ' .
            'FROM `tree` nct ' .
            'WHERE nct.`parentId`=' . $treeId . ') ' .
            'AS numchildren  ' .
            'FROM `itemtypes` ' .
            'WHERE `itemId`=(SELECT `itemType` ' .
            'FROM `page` p ' .
            'WHERE p.`pageId`=(SELECT t.`pageId` ' .
            'FROM `tree` t ' .
            'WHERE `treeId`=' . $treeId . '))';
        return $this->_conn->getRow($sql);
    }

    private function _getShowInNavSql($showinnav)
    {
        $navsql = '';
        if ($showinnav != null) {
            $navsql = 'AND (p.showinnavigation=';
            $navsql .= ($showinnav === true) ? 1 : 0;
            $navsql .= ' OR t.shortcut > 0) ';
        }
        return $navsql;
    }

}