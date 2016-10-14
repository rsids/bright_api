<?php
namespace fur\bright\api\page;
use fur\bright\api\cache\Cache;
use fur\bright\api\config\Config;
use fur\bright\api\content\Content;
use fur\bright\api\tree\Tree;
use fur\bright\core\Connection;
use fur\bright\entities\OPage;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\PageException;
use fur\bright\exceptions\ParameterException;
use fur\bright\utils\BrightUtils;

/**
 * Handles the creating, updating and returning of pages.<br/>
 * A Page is an object with some predefined fields (See OPage), and some Template specific fields, which are stored in the content property
 * Version history
 * 2.10 20130121
 * - Added getPagesByTemplateId
 * 2.9 20120307:
 * - _setContent became deprecated, use setContent
 * @author fur
 * @version 2.10
 * @package Bright
 * @subpackage page
 */
class Page extends Content  {

	function __construct() {
		parent::__construct();
	}

    /**
     * Gets a page by it's id
     * @param int $id The id of the page
     * @param bool $useCount
     * @param bool $tpllang
     * @return OPage The page
     */
	public function getPageById($id, $useCount = false, $tpllang = false) {
		if(!is_numeric($id)) {
            return null;
        }

		$ucsql = '';
		if($useCount) {
            $ucsql = ', (SELECT COUNT(t.pageId) FROM tree t WHERE t.pageId = p.pageId) AS `usecount` ';
        }

		$sql = "SELECT p.*,
				it.lifetime as `lifetime`, 
				it.label as `itemLabel`, 
				UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`, 
				UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`, 
				UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`,
				UNIX_TIMESTAMP(p.creationdate) as `creationdate`
				$ucsql
				FROM page p, itemtypes it 
				WHERE p.itemType = it.itemId 
				AND p.pageId=$id";
		$page = $this -> conn -> getRow($sql, 'OPage');

		if(!$page) {
            return null;
        }

		$page = $this -> getContent($page, $tpllang);
		return $page;
	}

    /**
     * Gets all the backups for a page
     * @param int $pageId The id of the page
     * @return array An array of backups
     * @throws \Exception
     * @since 2.7 - 24 dec 2010
     */
	public function getBackups($pageId) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

		if(!is_numeric($pageId)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }

		$sql = 'SELECT content, UNIX_TIMESTAMP(`date`) as `date` FROM `backups` WHERE `pageId`=' . (int)$pageId . ' ORDER BY `date` DESC';
		$backups = $this -> conn -> getRows($sql);

		$retarr = array();
		foreach($backups as $backup) {
			$retarr[] = (object) array('page' => unserialize($backup -> content), 'date' => $backup -> date);
		}
	}

	/**
	 * Gets a page by it's treeId
	 * @param int $treeId The treeId of the page
	 * @return OPage The page
	 */
	public function getPageByTreeId($treeId) {
		if(!is_numeric($treeId))
			return null;

		$sql = 'SELECT pageId FROM tree WHERE treeId=' . $treeId;
		$row = $this -> conn -> getRow($sql);
		if(!$row)
			return null;

		return $this -> getPageById($row -> pageId);
	}

	/**
	 * Gets all the pages of the specific type, whether they reside in the tree or not
	 * @param string $itemType The name of the template
	 * @return array An array of OPages
	 */
	public function getPagesByType($itemType) {
		$sql = 'SELECT p.pageId, ' .
				'p.itemType, ' .
				'p.label, ' .
				'it.lifetime as `lifetime`, ' .
				'it.label as `itemLabel`, ' .
				'UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`, ' .
				'UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`, ' .
				'UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`, ' .
				'p.alwayspublished, ' .
				'p.showinnavigation ' .
				'FROM page p, itemtypes it ' .
				'WHERE p.itemType = it.itemId ' .
				'AND it.label=\'' . Connection::getInstance() -> escape_string($itemType) . '\'';
		$pages = $this -> conn -> getRows($sql, 'OPage');

		if(!$pages)
			return null;

		foreach($pages as &$page)
			$page = $this -> getContent($page);

		return $pages;
	}

	/**
	 * Gets all the pages of the specific type, whether they reside in the tree or not
	 * @param int $templateId The id template
	 * @return array An array of OPages
	 */
	public function getPagesByTemplateId($templateId) {
		$templateId = (int)$templateId;
		$c = new Cache();
		$cname = 'pages-getPagesByTemplateId-' . $templateId;
		$pages = $c -> getCache($cname);
		$sql = "SELECT p.pageId, 
				p.itemType, 
				p.label, 
				it.lifetime as `lifetime`, 
				it.label as `itemLabel`, 
				UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`, 
				UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`, 
				UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`, 
				p.alwayspublished,
				p.showinnavigation 
				FROM page p, itemtypes it 
				WHERE p.itemType = $templateId AND p.itemType = it.itemId";
		
		$pages = $this -> conn -> getRows($sql, 'OPage');

		if(!$pages)
			return null;

		foreach($pages as &$page)
			$page = $this -> getContent($page);

		$c -> setCache($pages, $cname, strtotime('+1 year'));
		return $pages;
	}

	/**
	 * Gets a page by it's unique label<br/>
	 * This method does not check whether the page is actually in the tree
	 * @param string $label The label of the page
	 * @return OPage The Page with the label $label
	 */
	public function getPageByLabel($label) {
		$label = Connection::getInstance() -> escape_string($label);
		$sql = "SELECT p.pageId,
				p.itemType,
				p.label,
				it.lifetime as `lifetime`,
				it.label as `itemLabel`,
				UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`,
				UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`,
				UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`,
				p.alwayspublished,
				p.showinnavigation
				FROM page p, itemtypes it
				WHERE p.itemType = it.itemId
				AND p.label='$label'";

		$page = $this -> conn -> getRow($sql, 'OPage');

		if(!$page)
			return null;

		$page = $this -> getContent($page);
		return $page;
	}

    /**
     * Gets a list of all pages<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param int $templateType The type of page to return, 0 for pages, 1 for mailings, 2 for lists, 3 for calendars
     * @param array $additionalFields Specifies which fields from the content table should also be returned. They will return the value for the default (first) language
     * @param boolean $updatesOnly When true, only updated pages are returned
     * @return array An array of pages
     * @throws \Exception
     */
	public function getPages($templateType = 0, $additionalFields = null, $updatesOnly = false) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);

		if(!is_numeric($templateType))
			throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);

		$sql = 'SELECT p.pageId,
				p.itemType,
				p.label,
				it.lifetime as `lifetime`,
				it.label as `itemLabel`,
				UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`,
				UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`,
				UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`,
				p.alwayspublished,
				p.showinnavigation,
				(SELECT COUNT(t.pageId) FROM tree t WHERE t.pageId = p.pageId) AS `usecount` ';
		if($additionalFields == null) {
			$settings = $this -> getSettings();
			if($settings) {
				if($settings !== null && isset($settings -> page) && isset($settings -> page -> visibleColumns)) {
					$additionalFields = array();
					foreach($settings -> page -> visibleColumns as $col) {
						if(!in_array($col, Config::$pageColumns) || $col == 'title') {
							$additionalFields[] = $col;
						}
					}
						
				}
			}
		}
		if($additionalFields != null) {
			$lang = explode(',',AVAILABLELANG);
			$lang = array_shift($lang);
			$fields = array();
			$joins = array();
			foreach($additionalFields as $field) {
				$field = Connection::getInstance() -> escape_string($field);
				$fields[] = " COALESCE(co{$field}.value, '') as `{$field}` ";
				$joins[] = "LEFT JOIN content co{$field} ON p.pageId = co{$field}.pageId AND co{$field}.`lang`='$lang' AND co{$field}.`field`='{$field}' ";
			}
			$sql .= ', ' . join(', ', $fields);
			$sql .= 'FROM page p ';
			$sql .= join('', $joins);
		} else {
			
			$sql .= 'FROM page p ';
		}

		$sql .= 'INNER JOIN itemtypes it ON p.itemType = it.itemId AND it.templatetype = ' . $templateType;

		if($updatesOnly &&  isset($_SESSION['lastpagesupdate' . $templateType]) &&  $_SESSION['lastpagesupdate' . $templateType] != '') {
			$sql .= ' WHERE UNIX_TIMESTAMP(p.modificationdate) > ' . $_SESSION['lastpagesupdate' . $templateType] . ' ';
		}

		$sql .=	' ORDER BY p.modificationdate DESC';

		$_SESSION['lastpagesupdate' . $templateType] = time();
		$type = 'OPage';
		switch($templateType) {
			case 3:
				$type='OCalendarEvent';
				break;
		}
		$rows = $this -> conn -> getRows($sql, $type);

		return $rows;
	}

	/**
	 * Gets all the updated pages since the last request
	 * Since 2.3 it uses the getPages method
	 * @since 2.1 - 18 feb 2010
	 *
	 */
	public function getUpdatedPages() {
		if(defined('ADDITIONALOVERVIEWFIELDS') && ADDITIONALOVERVIEWFIELDS != null)
			return $this -> getPages(0, explode(',', ADDITIONALOVERVIEWFIELDS), true);

		return $this -> getPages(0, null, true);
	}

    /**
     * Gets an array of pages based on their ID
     * @param array $ids An array of id's
     * @param boolean $includeContent Whether or not the content of the page should be included
     * @return array an array of OPages (without content)
     * @throws \Exception
     */
	public function getPagesByIds($ids, $includeContent = false) {
		if(!is_array($ids))
			throw $this -> throwException(2007);

		foreach($ids as $id) {
			if(!is_numeric($id))
				throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);
		}

		$sql = 'SELECT p.pageId,
				p.itemType,
				p.label,
				it.lifetime as `lifetime`,
				it.label as `itemLabel`,
				UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`,
				UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`,
				UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`,
				p.alwayspublished,
				p.showinnavigation,
				(SELECT COUNT(t.pageId) FROM tree t WHERE t.pageId = p.pageId) AS `usecount`
				FROM page p
				INNER JOIN itemtypes it ON p.itemType = it.itemId
				WHERE pageId IN (' . join(',', $ids) .')';


		$sql .=	'ORDER BY p.modificationdate DESC';

		$rows = $this -> conn -> getRows($sql, 'OPage');
		if(!$includeContent)
			return $rows;

		foreach($rows as $page) {
			$page = $this -> getContent($page);
		}
		return $rows;
	}

    /**
     * Creates or updates a page<br/>
     * <b>As of version 2.1, this method returns ALL changed pages instead of a single one!</b><br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param OPage $page The page to create or update
     * @param boolean $returnAll When true, all changed pages are returned, when false, only the updated / created page is returned.
     * @param boolean $executeHook Indicates whether the pagehook should be executed
     * @return OPage The created or updated page
     * @throws \Exception
     */
	public function setPage(OPage $page, $returnAll = true, $executeHook = true) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);
		$ph = null;
		$cache = new Cache();
		$cache -> deleteCacheByPrefix('pages');
		if($executeHook && class_exists('PageHook')) {
			$ph = new \PageHook();
			if(method_exists($ph, 'preSetPage'))
				$page = $ph -> preSetPage($page);
		}

		$p = null;
		if($page -> pageId == 0) {
			// New Page
			$p = $this -> _createPage($page);
		} else {
			$cache -> deleteCacheByLabel($page -> label);
			$p = $this -> _updatePage($page);
		}

		if($ph != null && method_exists($ph, 'postSetPage')) {
			$ph -> postSetPage($page);
		}

		if($returnAll) {
			return $this -> getUpdatedPages();
		}

		return $p;
	}

    /**
     * Deletes a page<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>DELETE_PAGE</li>
     * </ul>
     * @param int $id The id of the page
     * @return bool True when successful
     * @throws \Exception
     */
	public function deletePage($id) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);
		if(!$this -> DELETE_PAGE)
			throw $this -> throwException( 5001);

		if(!is_numeric($id))
			throw $this -> throwException( ParameterException::INTEGER_EXCEPTION);
		
		$cache = new Cache();
		$cache -> deleteCacheByPrefix('pages');
		
		//First check if the page is present in the tree.
		$sql = 'SELECT `treeId` FROM tree WHERE `pageId`=' . (int) $id;
		$tids = $this -> conn -> getFields($sql);
		if(count($tids) > 0) {
			$tree = new Tree();
			$paths = array();
			foreach($tids as $tid) {
				$paths[] = $tree -> getPath($tid);
			}
			throw $this -> throwException( 5003, array(join("\n- ", $paths)));
		}

		$sql = 'DELETE FROM `page` WHERE `pageId` = ' . (int) $id;
		$this -> conn -> deleteRow($sql);
		$sql = 'DELETE FROM `content` WHERE `pageId` = ' . (int) $id;
		$this -> conn -> deleteRow($sql);
		return true;
	}

    /**
     * Deletes  multiple pages<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>DELETE_PAGE</li>
     * </ul>
     * @param array $ids The id's of the pages
     * @return bool True when successfull
     * @throws \Exception
     */
	public function deletePages($ids) {
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);
		if(!$this -> DELETE_PAGE)
			throw $this -> throwException(PageException::DELETE_PAGE_NOT_ALLOWED);

		foreach($ids as $id) {
			$this -> deletePage($id);
		}
		return true;
	}

    /**
     * A simple search mechanism
     * @since 2.2 Search now only returns pages which are actually in the tree
     * @since 2.8 Added boolean mode for queries containing a -
     * @param string $query The string to match
     * @param int $offset The record to start from (for paging)
     * @param int $limit The maximum number of records to return (for paging)
     * @param bool $idsOnly
     * @param bool $published
     * @return array An array of pages with content matching $query
     */
	public function search($query, $offset = 0, $limit = 10, $idsOnly = false, $published = false) {
		// Replace comma's by spaces
		$query = str_replace(',', ' ', $query);

		$inTree = '';

		if(!$idsOnly) {
			$inTree = ' AND p.pageId IN (SELECT pageId FROM tree) ';
		}
		$pubstr = '';
		if($published) {
			$pubstr = ' AND (p.alwayspublished = 1 OR (publicationdate < NOW() AND expirationdate > NOW())) ';
		}
		$query = Connection::getInstance() -> escape_string($query);

		$bmode = '';
		if(strstr($query, '-')) {
			$bmode = " IN BOOLEAN MODE";
			$query = '"' . $query . '"';
		}
		$sql = "SELECT SQL_CALC_FOUND_ROWS p.pageId,
			p.itemType,
			p.label,
			it.lifetime as `lifetime`,
			it.label as `itemLabel`,
			c.lang,
			b.maxrelevance,
			UNIX_TIMESTAMP(p.modificationdate) as `modificationdate`,
			UNIX_TIMESTAMP(p.publicationdate) as `publicationdate`,
			UNIX_TIMESTAMP(p.expirationdate) as `expirationdate`,
			p.alwayspublished,
			(MATCH(`value`) AGAINST('$query' $bmode)) AS rel,
			ROUND((MATCH(`value`) AGAINST('$query' $bmode))/b.maxrelevance * 100) as relevanceperc
			FROM `content` c, page p, itemtypes it, (SELECT MAX(MATCH(`value`) AGAINST('$query' $bmode)) as maxrelevance FROM content LIMIT 1) b
			WHERE MATCH(`value`) AGAINST('$query' $bmode)
			AND p.itemType = it.itemId
			$pubstr
			AND p.pageId = c.pageId
			AND c.searchable = 1 $inTree
			GROUP BY p.pageId
			ORDER BY rel DESC ";

		if($limit > 0)
			$sql .= 'LIMIT ' . $offset . ',' . $limit;

		if($idsOnly)
			return $this -> conn -> getFields($sql);

		$result = $this -> conn -> getRows($sql, 'OPage');
		if(count($result) == 0)
			return null;

		$numresults = $this -> conn -> getField('SELECT FOUND_ROWS()');

		$tree = new Tree();
		$resultobj = new \stdClass();
		$resultobj -> page = $offset / $limit;
		$resultobj -> total = $numresults;
		$resultobj -> results = $tree -> getNodesByPageIds($result);
		return $resultobj;
	}

	/**
	 * Creates a page
	 * @param OPage $page The page to create
	 * @return OPage The created page
	 */
	private function _createPage(OPage $page) {
		$ap = ($page -> alwayspublished) ? 1 : 0;
		$sn = ($page -> showinnavigation) ? 1 : 0;
		
		if(isset($_SESSION['administratorId'])) {
			$page -> createdby = $_SESSION['administratorId'];
		} else {
			$page -> createdby = 0;
			
		}
		BrightUtils::forceInt($page, array('publicationdate', 'expirationdate', 'itemType', 'createdby'));
		$page -> label = Connection::getInstance() -> escape_string($this-> generateLabel($page -> label));
		$sql = "INSERT INTO page (itemType, label, publicationdate, expirationdate, alwayspublished, showinnavigation, creationdate, createdby)
				VALUES 
				({$page -> itemType}, 
				'{$page -> label}', 
				FROM_UNIXTIME({$page -> publicationdate}), 
				FROM_UNIXTIME({$page -> expirationdate}), 
				$ap,
				$sn,
				NOW(),
				{$page -> createdby})";
		$id = $this -> conn -> insertRow($sql);
		$page -> pageId = $id;
		$this -> setContent($page);
		return $this -> getPageById($page -> pageId);
	}

	/**
	 * Updates a page
	 * @param OPage page The page to update
	 * @return OPage The updated page
	 */
	private function _updatePage(OPage $page) {

		$ap = ($page -> alwayspublished) ? 1 : 0;
		$sn = ($page -> showinnavigation) ? 1 : 0;
		$cachebleChanged = $this -> _cachebleChanged($page);
		
		$page -> label = Connection::getInstance() -> escape_string($this-> generateLabel($page -> label, $page -> pageId));
		$page -> modifiedby = isset($_SESSION['administratorId']) ? $_SESSION['administratorId'] : 0;
		BrightUtils::forceInt($page, array('publicationdate', 'expirationdate', 'itemType', 'pageId'));
		
		$sql = "UPDATE page 
				SET label='{$page -> label}',
				itemType='{$page -> itemType}', 
				publicationdate=FROM_UNIXTIME({$page -> publicationdate}),
				expirationdate=FROM_UNIXTIME({$page -> expirationdate}), 
				alwayspublished=$ap,
				showinnavigation=$sn,
				modificationdate=NOW(),
				modifiedby={$page -> modifiedby}
				WHERE pageId={$page -> pageId}";
		$this -> conn -> updateRow($sql);
		$this -> setContent($page);

		if($cachebleChanged) {
			// Flush cache
			$cache = new Cache();
			$cache -> flushCache();
			$tree = new Tree();
			$tree -> generateSitemap();
		}
		return $this -> getPageById($page -> pageId, true);
	}

    /**
     * Checks if a label is changed
     * @param OPage $page The page
     * @return bool true when the label is different and the page is present in the navigationtree
     * @throws \Exception
     */
	private function _cachebleChanged($page) {

		if(!is_numeric($page -> pageId))
			throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);

		$sql = 'SELECT COUNT(p.pageId)
				FROM `page` p
				RIGHT JOIN tree t ON t.pageId=p.pageId
				WHERE p.pageId=' . $page -> pageId . '
				AND (`label`<>\'' . Connection::getInstance() -> escape_string($page -> label) . '\'
				OR `showinnavigation` <> '. (int) $page -> showinnavigation .'
				OR `alwayspublished` <> '. (int) $page -> alwayspublished .'
				OR UNIX_TIMESTAMP(`publicationdate`) <> '. (int) $page -> publicationdate .'
				OR UNIX_TIMESTAMP(`expirationdate`) <> '. (int) $page -> expirationdate .')';

		$res = $this -> conn -> getField($sql);

		return ((int)$res > 0);
	}
}