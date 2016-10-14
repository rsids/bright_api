<?php
namespace fur\bright\api\content;
use fur\bright\api\cache\Cache;
use fur\bright\api\maps\Layers;
use fur\bright\api\maps\Maps;
use fur\bright\api\page\Backup;
use fur\bright\api\page\Page;
use fur\bright\api\template\Template;
use fur\bright\core\Connection;
use fur\bright\entities\OPage;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;
use fur\bright\utils\BrightUtils;
use fur\bright\utils\Conversions;

/**
 * Base class for everything that has content (pages, users, calendar events, elements).<br/>
 * Version history
 * 1.2 - 20130725
 *  - Added fix for unknown fields in content object (fields which do exist in object but not in template).
 * 1.1 - 20111207:
 * - Moved get/setContent to Content Class
 * @author fur
 * @version 1.1
 * @package Bright
 * @subpackage content
 */
class Content extends Permissions  {

	/**
	 * @var stdClass Holds the Connection singleton
	 */
	protected $conn;


	function __construct() {
		parent::__construct();
		$this -> conn = Connection::getInstance();
	}

    /**
     * Generates an URL-friendly unique label for the given title.<br/>
     * All spaces are converted to '-'. Special characters are transformed to non-special characters. When the label exists, a suffix is added<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param string $title The title or label to check
     * @param int $id The id of the page. This id is excluded from the search for duplicate labels
     * @param string $table The table to check it's existence in
     * @return string A generated label
     */
	public function generateLabel($title, $id = 0, $table = 'page') {

		$conv = new Conversions();

		$title = strip_tags($title);
		// Preserve escaped octets.
		$title = preg_replace('|%([a-fA-F0-9][a-fA-F0-9])|', '---$1---', $title);
		// Remove percent signs that are not part of an octet.
		$title = str_replace('%', '', $title);
		// Restore octets.
		$title = preg_replace('|---([a-fA-F0-9][a-fA-F0-9])---|', '%$1', $title);

		$title = $conv -> remove_accents($title);
		if ($conv -> seems_utf8($title)) {
			if (function_exists('mb_strtolower')) {
				$title = mb_strtolower($title, 'UTF-8');
			}
			$title = $conv -> utf8_uri_encode($title, 200);
		}

		$title = strtolower($title);
		$title = preg_replace('/&.+?;/', '', $title); // kill entities
		$title = preg_replace('/[^%a-z0-9 _-]/', '', $title);
		$title = preg_replace('/\s+/', '-', $title);
		$title = preg_replace('|-+|', '-', $title);
		$title = trim($title, '-');
		$titlearr = explode('-', $title);
		$num = 1;

		if(is_numeric($titlearr[count($titlearr) - 1]))
			$num = array_pop($titlearr);

		$cleantitle = join('-', $titlearr);
		$identifier = ($table != 'calendarnew') ? $table . 'Id' : 'calendarId';
		while($this -> _checkLabelForExistance($title, $id, $table, $identifier)) {
			$title = $cleantitle . '-' . $num;
			$num++;
		}

		return $title;
	}

	/**
	 * Gets the content of a page
	 * @param OPage $page The page without content
	 * @param boolean $tpllang Defines whether or not the tpl lang should be included (For CMS true, for frontend false)
	 * @param string $table The table with the content
	 * @return OPage The page with content
	 */
	public function getContent(OPage $page, $tpllang = false, $table = 'content') {
		$pid = 'pageId';
		switch($table) {
			case 'content':
				$pid = 'pageId';
				break;
			case 'userfields':
				$pid = 'userId';
				break;
			case 'calendarcontent':
				$pid = 'calendarId';
				break;
		}
		$id = (int) $page -> {$pid};
		$table = Connection::getInstance() -> escape_string($table);
		$sql = "SELECT * FROM `$table` WHERE {$pid}=$id ORDER BY `field`, `index`";
		$content = $this -> conn -> getRows($sql);
		$page -> content = new OContent();
		$template = new Template();
		// Just to be sure, the old method used getTemplateDefinitionByPageId, but since a user doesn't have a page id,
		// we'll have to cut some corners
		if($page -> itemType > 0) {
			$definition = $template -> getTemplateDefinition($page -> itemType, true);

		} else {
			$definition = $template -> getTemplateDefinitionByPageId($page -> pageId, true);
		}
		$gm_maps = new Maps();
		$gm_layers = new Layers();
		foreach($content as $row) {
			$field = $row -> field;
			// Skip fields which aren't present anymore (template has changed)
			if(!isset($definition -> {$field}) && $field != 'title')
				continue;

			$lang = $row -> lang;
			$isnew = true;

			if(isset($page -> content -> $field))
				$isnew = false;

			if(!isset($page -> content -> $field)) {
				if((!$tpllang && $lang != 'tpl') || $tpllang)
					$page -> content -> $field = new \stdClass();
			}

			if(($lang != 'tpl' && !$tpllang) && isset($page -> content -> $field -> $lang)) {
				$isnew = false;
			} else if($lang != 'tpl' && !isset($page -> content -> $field -> $lang)) {
				$isnew = true;
			}

			if($isnew) {

				if($field == 'title') {
					$value = $row -> value;

				} else {
					$value = $this -> _processValue($row -> value, $definition, $field);
				}
				if($lang == 'tpl' && !$tpllang) {
					$page -> content -> $field = $value;
				} else {
					$page -> content -> $field -> $lang = $value;

				}

			} else {
				$raw = $row -> value;
				$val = json_decode($row -> value);
				if($val == null && ($raw != null && $raw != '')) {
					// Value is not a json string
					$val = $raw;
				}
				if($lang == 'tpl' && !$tpllang) {
					$page -> content -> {$field}[] = $val;
				} else {
					array_push($page -> content -> $field -> $lang, $val);
				}

			}
		}
		return $page;
	}

	/**
	 * Inserts content into the database
	 * @param OPage $page
	 * @param string $table
	 */
	protected function setContent(OPage $page, $table = 'content') {
		$pid = 'pageId';
		switch($table) {
			case 'content':
				$pid = 'pageId';
				break;
			case 'userfields':
				$pid = 'userId';
				break;
			case 'calendarcontent':
				$pid = 'calendarId';
				break;
		}
		$id = (int) $page -> {$pid};
		$table = Connection::getInstance() -> escape_string($table);
		$b = new Backup();
		$b -> setBackup($page, $table);

		if(!isset($page -> content) || $page -> content == null) {
            throw new ParameterException(ParameterException::OBJECT_EXCEPTION);
        }

		$sql = "UPDATE `{$table}` SET `deleted`=1 WHERE `{$pid}`=$id";
		$this -> conn -> updateRow($sql);

		$it = new Template();
		$def = $it -> getTemplateDefinition($page -> itemType, true);
		$def -> title = new \stdClass();
		$def -> title -> contenttype = 'string';
		$def -> title -> searchable = 1;
		$sql = "INSERT INTO `{$table}` (`{$pid}`, `lang`, `field`, `value`,`index`,`searchable`) VALUES ";
		$sqla = array();
		foreach($page -> content as $field => $langs) {
			$searchable = 0;

			if($field == 'title') {
				$searchable = 1;

				if($table == 'content' && $this -> _titleChanged($langs, $page -> {$pid})) {
					$cache = new Cache();
					$cache -> flushCache();
				}

			} else {
				$searchable = (isset($def -> {$field}) && $def -> {$field} -> searchable) ? 1 : 0;
			}
			foreach($langs as $lang => $val) {
				// 20130725 Fix for unknown fields
				$contenttype = isset($def -> {$field}) && isset($def -> {$field} -> contenttype) ? $def -> {$field} -> contenttype : 'string'; 
				switch($contenttype) {
					case 'array':
						$index = 0;
						if(!is_array($val))
							$val = array($val);
						foreach($val as $listval) {
							$lang = BrightUtils::escapeSingle($lang);
							$field = BrightUtils::escapeSingle($field);
							if(!is_string($listval)) {
								//throw $this -> throwException(Exceptions::INCORRECT_PARAM_STRING, '$listval: ' . print_r($listval, true));
								$listval = json_encode($listval);
							}
							$listval = BrightUtils::escapeHtml($listval);
							$sqlid = $page -> {$pid};
							$sqla[] = "($sqlid,
										'$lang',
										'$field',
										'$listval', $index, $searchable) ";
							$index++;
						}
						break;

					default:
						$lang = BrightUtils::escapeSingle($lang);
						$field = BrightUtils::escapeSingle($field);
						if($val !== null && $val !== false && !is_scalar($val)) {
							$val = json_encode($val);
							if($val == '{}')
								$val = '';
							//throw $this -> throwException(Exceptions::INCORRECT_PARAM_STRING, 'val: ' . print_r($val, true));
						}
						$val = BrightUtils::escapeHtml($val);
						if($val != '') {
							$sqlid = $page -> {$pid};
							$sqla[] = "($sqlid,
										'$lang',
										'$field',
										'$val', 0, $searchable) ";
						}

				}
			}
		}
		if(count($sqla) > 0) {
			$sql .= implode(", \r\n", $sqla);
			$sql .= " ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `deleted`=0";
			$result = $this -> conn -> insertRow($sql);
		} else {
			// Delete old content? uncomment line
			// $result = 1;
		}
		if($result !== false && $result > 0) {
			// All is well, clean up old data
			$sql = "DELETE FROM `$table` WHERE `deleted`= 1 AND  `$pid`=$id";
			$this -> conn -> deleteRow($sql);
		}
		return $result;
	}

	/**
	 * Checks if a label exists
	 * @param string $label The label to check
	 * @param int $exceptId The id to exclude in the search
	 * @param string $table The name of the table to search in
	 * @param string $identifier The name of the unique identifier of the table
	 * @return boolean True when the label already exists
	 */
	private function _checkLabelForExistance($label, $exceptId = 0, $table, $identifier) {
		$label = Connection::getInstance() -> escape_string($label);
		$table = Connection::getInstance() -> escape_string($table);
		$exceptId = (int)$exceptId;
		$sql = "SELECT COUNT(label) as `numLabels` FROM {$table} p  WHERE p.label = '$label'";

		if($exceptId > 0) {
			$sql.= " AND p.{$identifier} <> $exceptId";
		}

		$result = $this -> conn -> getRow($sql);
		return ($result -> numLabels > 0);
	}


	/**
	 * Processes a value from a content row
	 * @param string $value The value to process
	 * @param string $definition The definition of the template
	 * @param string $field The name of the value
	 * @return mixed The processed value
	 */
	private function _processValue($value, $definition, $field) {
		switch($definition -> {$field} -> type) {
			case 'array':
				$raw = $value;
				$return = json_decode($value);
				if($return == null && ($raw != null && $raw != '')) {
					// Value is not a json string
					$return = $raw;
				}
				$return = array($return);
				break;

			case 'json':
				try {
					$return = json_decode($value);
				} catch(\Exception $ex) {
					$return = $value;
				}
				break;

			case 'gmaps':
				try {
					$return = json_decode($value);
					$layers = array();
					$markers = array();
					$polys = array();
					$gm_layers = new Layers();
					$gm_maps = new Maps();
					foreach($return -> layers as $layer) {
						$l = $gm_layers -> getLayer($layer -> layerId);
						if($l) {
							$l -> visible = $layer -> visible;
							$layers[] = $l;
							$m = $gm_maps -> getMarkers($layer -> layerId);
							$p = $gm_maps -> getPolys($layer -> layerId);
							$markers = array_merge($m, $markers);
							$polys = array_merge($p, $polys);
						}
					}
					$return -> layers = $layers;
					$return -> markers = $markers;
					$return -> polys = $polys;
				} catch(\Exception $ex) {
					$return = $value;
				}
				break;

			case 'elements':
				$ids = explode(',', $value);
				$elements = array();
				foreach($ids as $id) {
					$page = new Page();
					$el = $page -> getPageById($id);
					if($el)
						$elements[] = (object)$el;
				}
				$return = $elements;
				break;

			default:
				$return = $value;

		}
		return $return;
	}



	/**
	 * Checks if a label is changed
	 * @param array $titles An array of lang > titles
	 * @param int $pageId The Id of the page
	 * @return boolean true when (any of) the title(s) is / are different and the page is present in the navigationtree
	 */
	private function _titleChanged($titles, $pageId) {
		$joins = '';
		$i = 0;
		foreach($titles as $lang => $value) {
			$joins.= 'RIGHT JOIN content c' . $i . ' ON c' . $i . '.pageId=p.pageId AND c' . $i . '.`field`=\'title\' AND c' . $i . '.`value` <>\'' . Connection::getInstance() -> escape_string($value) . '\'' . "\r\n";
			$i++;
		}

		$sql = 'SELECT COUNT(p.pageId)
				FROM `page` p'
				. "\r\n" . $joins .
				'RIGHT JOIN tree t ON t.pageId=p.pageId
				WHERE p.pageId=' . $pageId;
		$res = $this -> conn -> getField($sql);
		return ((int)$res > 0);
	}

}