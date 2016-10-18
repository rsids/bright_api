<?php
namespace fur\bright\api\element;
use fur\bright\api\cache\Cache;
use fur\bright\api\config\Config;
use fur\bright\api\page\Page;
use fur\bright\core\Connection;
use fur\bright\entities\OPage;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\GenericException;
use fur\bright\exceptions\PageException;
use fur\bright\Permissions;
use fur\bright\utils\BrightUtils;

/**
 * Handles the creating, updating and returning of elements.<br/>
 * An elements is a special type of page
 * Version history
 * 2.3 20130121
 * - Added getElementsByType
 * 2.2 20120620
 * - Added deleteElements
 * - Added deleteElementsHook
 * 2.1 20111229
 * - Added hooks to setElement
 * 
 * @author Ids Klijnsma - Fur
 * @version 2.3
 * @package Bright
 * @subpackage elements
 */
class Element extends Permissions  {
	
	private $_hook;
	
	private $_page;
	private $_conn;
	
	function __construct() {
		parent::__construct();
		
		$this -> _conn = Connection::getInstance();
		$this -> _page = new Page();
		if(class_exists('ElementHook', true)) {
			$this -> _hook = new \ElementHook();
		}
	}

    /**
     * Deletes one or more elements
     * @since 2.2
     * @param array $pids
     * @return bool
     * @throws \Exception
     */
	public function deleteElements($pids) {
		
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
		if(!$this -> DELETE_PAGE) {
            throw $this->throwException(PageException::DELETE_PAGE_NOT_ALLOWED);
        }
		
		if(method_exists($this -> _hook, 'preDeleteElements')) {
			$pids = $this -> _hook -> preDeleteElements($pids);
		}
		
		$res = $this -> _page -> deletePages($pids);
		
		if(method_exists($this -> _hook, 'postDeleteElements')) {
			$this -> _hook -> postDeleteElements($pids);
		}
		
		return $res;
	}
	
	public function filter($start = 0, $limit = 20, $filter = null, $orderfield = 'pageId', $order = 'DESC') {
		
		if(!$this -> IS_AUTH)
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);
		
		$c = new Cache();
		$cname = "element_filter_" . md5(json_encode(func_get_args()));
		$result = $c -> getCache($cname);
		if($result)
			return $result;
		
		if($orderfield == null || $orderfield == 'undefined')
			$orderfield = 'pageId';
		
		if($order != 'DESC' && $order != 'ASC')
			$order = 'DESC';
		
		switch($orderfield) {
			default:
				if(is_numeric($orderfield))
					$orderfield = 'pageId';
		}
		
		$start = (int)$start;
		$limit = (int)$limit;
		if($limit == 0)
			$limit = 20;
		
		$additionalfields = array();
		$settings = $this -> getSettings();
		if($settings) {
			if($settings !== null && isset($settings -> element) && isset($settings -> element -> visibleColumns)) {
				foreach($settings -> element -> visibleColumns as $col) {
					if(!in_array($col, Config::$elementColumns)) {
						$additionalfields[] = $col;
					}
				}
		
			}
		}
		
		$fieldsql = '';
		$joins = array();
		if(count($additionalfields) != 0) {
			$fields = array();
			foreach($additionalfields as $field) {
				$field =  Connection::getInstance() -> escape_string($field);
				$fields[] = " COALESCE(co$field.value, \'\') as `$field` ";
				$joins[] = "LEFT JOIN content co$field ON cn.pageId = co$field.pageId AND co$field.`lang`='tpl' AND co$field.`field`='$field' ";
			}
			$fieldsql = ', ' . join(', ', $fields);
		}
		
		$groupby = ' GROUP BY pageId ';
		$pdateselect = 'NOW()';
		if($filter != null && $filter != '') {
			$filter = Connection::getInstance() -> escape_string($filter);
			if(strpos($filter, '*') === false) {
				$filter = '*' . $filter . '*';
			}
			$joins[] = "INNER JOIN pageindex ci ON ci.pageId = cn.pageId AND MATCH(`ci`.`search`) AGAINST('$filter' IN BOOLEAN MODE) ";
		}
		$joinsql= join("\r\n", $joins) . "\r\n";
		
		$sql = "SELECT SQL_CALC_FOUND_ROWS cn.*,
		UNIX_TIMESTAMP(cn.modificationdate) as `modificationdate`,		UNIX_TIMESTAMP(cn.creationdate) as `creationdate`
		$fieldsql
		FROM `page` cn
		INNER JOIN itemtypes it ON it.itemId = cn.itemType AND it.templatetype=4
		$joinsql
		$groupby
		ORDER BY $orderfield $order
		LIMIT $start,$limit";
		
		
		$rows = $this -> _conn -> getRows($sql, 'OPage');
		$total = (int)$this -> _conn -> getField('SELECT FOUND_ROWS()');
		
		$result = (object) array('result'=>$rows, 'total' => $total);
		$c -> setCache($result, $cname, strtotime('+1 year'));
		return $result;
	}
	
	/**
	 * Shorthand function to get all updated elements
	 */
	public function getElements($updatesonly) {
		return $this -> _page -> getPages(4, null, $updatesonly);
	}

	public function getElementsById($pageIds) {
		$pages = $this -> _page -> getPagesByIds($pageIds);
		// Restore order
		$ordered = array();
		foreach($pageIds as $id) {
			foreach($pages as $page) {
				if($page -> pageId == $id) {
					$ordered[] = $page;
					break;
				}
			}
		} 
		return $ordered;
	}

    /**
     * Gets all the elements of the specified templateId
     * @param int $templateId
     * @return array
     */
	public function getElementsByType($templateId) {
		$templateId = (int) $templateId;
		$elements = $this -> _page -> getPagesByTemplateId($templateId);
		return $elements;
	}


    /**
     * Saves a element
     * @param OPage $element The element to save
     * @param bool $returnall
     * @return \stdClass An object containing element, the just saved element and elements, an array of all elements
     * @throws \Exception
     */
	public function setElement($element, $returnall = true) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
	
		if(method_exists($this -> _hook, 'preSetElement')) {
			$element = $this -> _hook -> preSetElement($element);
		}
	
		$element = $this -> _page -> setPage($element, false, false);
	
		if(method_exists($this -> _hook, 'postSetElement')) {
			$this -> _hook -> postSetElement($element);
		}
		
		$c = new Cache();
		$c -> deleteCacheByPrefix("element_filter_");
		
		$search = BrightUtils::createSearchString($element);
		$search = Connection::getInstance() -> escape_string($search);
		$sql = "INSERT INTO pageindex (pageId, search) VALUES ({$element -> pageId}, '$search') ON DUPLICATE KEY UPDATE search='$search' ";
		Connection::getInstance() -> insertRow($sql);
	
		if(!$returnall)
			return $element;
	
		return $this -> _page -> getPages(4, null, true);
	}
}