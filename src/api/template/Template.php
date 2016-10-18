<?php
namespace fur\bright\api\template;
use fur\bright\api\cache\Cache;
use fur\bright\core\Connection;
use fur\bright\entities\OTemplate;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\ParameterException;
use fur\bright\exceptions\TemplateException;
use fur\bright\Permissions;

/**
 * Handles all the actions for the template.
 * @author Fur - Ids Klijnsma
 * @version 2.6
 * @package Bright
 * @subpackage template
 */
class Template extends Permissions  {
	
	/**
	 * @var \stdClass A reference to the Cache class
	 */
	private $_cache;
	
	function __construct() {
		parent::__construct();
		$this -> _cache = new Cache();	
		$this -> _conn = Connection::getInstance();
	}
	
	/**
	 * @var \stdClass A reference to the Connection instance
	 */
	private $_conn;


    /**
     * Deletes a template. only templates which are not in use can be deleted.<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_TEMPLATE</li>
     * </ul>
     * @since 2.1 - 16 feb 2010
     * @param int $templateId The id of the template to delete
     * @return array An array of template definitions
     * @throws \Exception
     */
	public function deleteTemplate($templateId) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }

		if(!$this -> MANAGE_TEMPLATE) {
            throw $this->throwException(TemplateException::TEMPLATE_CREATE);
        }
		
		if(!is_numeric($templateId))
			throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);
			
		$this -> _cache -> deleteCache('bright_templateDefinitions');
			
		$sql = 'SELECT COUNT(`pageId`) as `pages` FROM `page` WHERE `itemType`=' . (int) $templateId;
		
		$numpages = $this -> _conn -> getField($sql);
		if((int)$numpages > 0) {
            throw $this->throwException(TemplateException::TEMPLATE_IN_USE);
        }
			
		$sql = 'DELETE FROM `itemtypes` WHERE `itemId`=' . (int) $templateId;
		$this -> _conn -> deleteRow($sql);
			
		$sql = 'DELETE FROM `itemdefinitions` WHERE `itemType`=' . (int) $templateId;
		$this -> _conn -> deleteRow($sql);
		
		return $this -> getTemplateDefinitions();
	}

	/**
	 * Gets the lifetime of a cached page
	 * @param string $itemLabel The label of the itemtype
	 * @return string The lifetime of the page
	 */
	public function getLifeTime($itemLabel) {
		$sql = "SELECT lifetime FROM itemtypes WHERE label = '" . Connection::getInstance() -> escape_string($itemLabel) ."'";
		$lifetime = $this -> _conn -> getRow($sql);
		return $lifetime -> lifetime;
	}
	
	/**
	 * Gets all the parsers
	 */
	public function getParsers() {
		return $this -> _conn -> getRows('SELECT * FROM parsers');
	}


    /**
     * Gets all the plugins by browsing the filesystem<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @param string $type either core or custum (since 2.6)
     * @return array An array of strings
     * @throws \Exception
     */
	public function getPlugins($type = 'core') {
		if(!$this -> IS_AUTH) 
			throw $this -> throwException(AuthenticationException::NO_USER_AUTH);
			
		$dir = ($type == 'core') ? CMSFOLDER . 'assets/plugins/' : 'bright/site/plugins/';
		$plugins = array();
		
		if(is_dir(BASEPATH . $dir)) {
			
			$files = scandir(BASEPATH . $dir);
			foreach($files as $file) {
				$extensionIndex = strlen($file) - 4;
				if(strpos($file, '.swf') === $extensionIndex && strpos($file, 'plugin') === 0) {
					// We've got ourselves a plugin
					$pl = explode('.', $file);
					$file = $pl[0];
					$file = substr($file, 7);
					$plugins[] = $file;
				}
			}
			
			asort($plugins);
		} else {
			if($type == 'core') {
				throw $this -> throwException(7009);
			}
		}
			
		return $plugins;
	}

    /**
     * Gets the definitions of a single template
     * @param int $templateType The id of the template
     * @param boolean $fieldsOnly Return only the fields of the template
     * @return \stdClass The definition
     * @throws \Exception
     */
	public function getTemplateDefinition($templateType, $fieldsOnly = false) {
		if(!is_numeric($templateType))
			throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);
		
		if(LIVESERVER) {
			$cached = $this -> _cache -> getCache('bright_template_' . $templateType . '_' . $fieldsOnly);
			if($cached) {
                return $cached;
            }
		}
				
		$sql = <<<'FUR'
SELECT it.itemId, it.label as `itemtype`, it.displaylabel as `templatename`, it.visible, it.icon, it.lifetime, it.maxchildren,   
				id.label, id.index, it.parser, it.templatetype, id.searchable, id.data, id.displaylabel, 
				id.fieldType as `type` , id.contenttype 
				FROM itemtypes it 
				LEFT JOIN `itemdefinitions` id ON id.itemType = it.itemId
				WHERE it.itemId = 
FUR
 . $templateType . ' 
				ORDER BY it.label, id.index ASC';
		
		$rows = $this -> _conn -> getRows($sql);
		if(!$rows) {
			return null;
        }

		$def = $this -> _createDefinition($rows);
		
		
		if(!$fieldsOnly) {
			$this -> _cache -> setCache($def, 'bright_template_' . $templateType . '_' . $fieldsOnly, time() + 31556926);
			return $def;
		} 
		
		$simplified = new \stdClass();
		foreach($def -> fields as $field) {
			$field -> data = json_decode($field -> data);
			// Just to be compatible with the simplified definition returned by getTemplateDefinitionByPageId
			$field -> type = $field -> contenttype;
			$simplified -> {$field -> label} = $field;
		}
		
		$this -> _cache -> setCache($simplified, 'bright_template_' . $templateType . '_' . $fieldsOnly, time() + 31556926);
		return $simplified;
		
	}

    /**
     * Gets all the templatedefinitions<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * </ul>
     * @return array An array of definitions
     * @throws \Exception
     */
	public function getTemplateDefinitions() {
		if(!$this -> IS_AUTH) 
			throw new AuthenticationException(AuthenticationException::NO_USER_AUTH);
		
		$cached = $this -> _cache -> getCache('bright_templateDefinitions');
		if($cached)
			return $cached;
		
		$sql = <<<'FUR'
SELECT it.itemId, it.label as `itemtype`, it.displaylabel as `templatename`, it.icon, it.lifetime, it.maxchildren,   
    id.label, it.parser, it.visible, it.templatetype, id.index, id.data, id.displaylabel, id.searchable,
    id.fieldType as `type` , id.contenttype 
    FROM itemtypes it 
    LEFT JOIN `itemdefinitions` id ON id.itemType = it.itemId
    ORDER BY it.label, id.index ASC
FUR;
		
		$rows = $this -> _conn -> getRows($sql);
		$definitions = null;
		foreach($rows as $row) {
			$definitions[$row -> itemtype][] = $row;
		}
		
		$templateDefinitions = array();
		
		foreach($definitions as $definition) {
			$templateDefinitions[] = $this -> _createDefinition($definition);
		}
		
		$this -> _cache -> setCache($templateDefinitions, 'bright_templateDefinitions', time() + 31556926);
		
		return $templateDefinitions;
	}


    /**
     * Gets a definition by a pageId
     * @since 2.2 - 15 mrt 2010
     * @param int $pageId The id of the page
     * @param boolean $simplify When true, only name / type pairs of the fields are returned (e.g: list => array, text => string)
     * @return \stdClass The definition
     * @throws \Exception
     */
	public function getTemplateDefinitionByPageId($pageId, $simplify = false) {
		if(!is_numeric($pageId))
			throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);
			
		$sql = <<<'FUR'
SELECT it.itemId, it.label as `itemtype`, it.displaylabel as `templatename`, it.visible, it.icon, it.lifetime, it.maxchildren,   
    id.label, id.index, it.parser, it.templatetype, id.searchable, id.data, id.displaylabel, 
    id.fieldType as `type` , id.contenttype 
    FROM itemtypes it 
    LEFT JOIN `itemdefinitions` id ON id.itemType = it.itemId
    WHERE it.itemId = (SELECT p.itemType FROM page p WHERE pageId=
FUR
 . $pageId . ') 
				ORDER BY it.label, id.index ASC';
		
		$rows = $this -> _conn -> getRows($sql);
		if(!$rows)
			return null;
		
		$definition = $this -> _createDefinition($rows);
		if(!$simplify)
			return $definition;
			
		$simplified = new \stdClass();
		foreach($definition -> fields as $field) {
			$simplified -> {$field -> label} = (object) array('type' => $field -> contenttype, 'data' => json_decode($field -> data));
		}
		return $simplified;
	}
	
	/**
	 * Returns the id of a template by it's label
	 * @since 2.2 - 26 mrt 2010
	 * @param string $label The label of the template
	 * @return int The id of the template
	 */
	public function getTemplateDefinitionIdByLabel($label) {
		$sql = "SELECT itemId FROM itemtypes WHERE label='" . Connection::getInstance() -> escape_string($label) . "'";
		return (int)$this -> _conn -> getField($sql);
	}
	
	public function getTemplatesByIds($ids) {
		if(!$this -> IS_AUTH) 
			throw new AuthenticationException(AuthenticationException::NO_USER_AUTH);

		$cached = $this -> _cache -> getCache('bright_template_' . join('_', $ids));
		if($cached)
			return $cached;
		
		
		$sql = <<<'FUR'
SELECT it.itemId, it.label as `itemtype`, it.displaylabel as `templatename`, it.icon, it.lifetime, it.maxchildren,   
    id.label, it.visible, it.parser, it.templatetype, id.index, id.data, id.displaylabel, id.searchable,
    id.fieldType as `type` , id.contenttype 
    FROM itemtypes it 
    LEFT JOIN `itemdefinitions` id ON id.itemType = it.itemId
    WHERE it.itemId = 
FUR
 . join(' OR it.itemId=', $ids) . ' 
				ORDER BY it.label, id.index ASC';
		
		$rows = $this -> _conn -> getRows($sql);
		$definitions = null;
		foreach($rows as $row) {
			$definitions[$row -> itemtype][] = $row;
		}
		
		$templateDefinitions = array();
		
		foreach($definitions as $definition) {
			$templateDefinitions[] = $this -> _createDefinition($definition);
		}
		
		$rows = array();
		foreach($templateDefinitions as $definition) {
			$rows[] = $definition -> fields[0];
		}
		
		$this -> _cache -> setCache($rows, 'bright_template_' . join('_', $ids), time() + 31556926);
		
		return $rows;
	}
	
	public function getUserTemplate() {
		$sql = 'SELECT `itemId` FROM `itemtypes` WHERE `templatetype`=6';
		$id = $this -> _conn -> getField($sql);
		if(!$id)
			return null;
			
		$id = (int) $id;
		
		return $this -> getTemplateDefinition($id);
	}

    /**
     * Sets the lifetime of a cached item<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_TEMPLATE</li>
     * </ul>
     * @param int $itemId The Id of the itemtype
     * @param string $lifetime The lifetime of the item
     * @return array An array of itemdefinitions
     * @throws \Exception
     */
	public function setLifetime($itemId, $lifetime) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
		if(!$this -> MANAGE_TEMPLATE) {
            throw $this->throwException(TemplateException::TEMPLATE_LIFETIME);
        }
		
		if(!is_numeric($itemId)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }
		
		// Never trust user input
		$lifetimeArr = explode(' ', $lifetime);
		$options = array('years', 'months', 'weeks', 'days', 'hours', 'minutes');
		$timespan = $lifetimeArr[1];
		$timespans = $lifetimeArr[1] . 's';
		if(in_array($timespan, $options) || in_array($timespans, $options)) {
			$sql = "UPDATE itemtypes " .
					"SET lifetime = '" . $lifetime . "' " .
					"WHERE itemId=" . $itemId;
			$this -> _conn -> updateRow($sql);
		}
		$this -> _cache -> flushCache();
		return $this -> getTemplateDefinitions();
	}


    /**
     * Sets the maximum number of children of a certain template<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_TEMPLATE</li>
     * </ul>
     * @param int $templateId The id of the template
     * @param double $maxChildren The maximum number of children (use -1 when there is no maximum)
     * @return array An array of templatedefinitions
     * @throws \Exception
     */
	public function setMaxChildren($templateId, $maxChildren) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
		if(!$this -> MANAGE_TEMPLATE) {
            throw $this->throwException(TemplateException::SET_MAXCHILDREN);
        }
		if(!is_numeric($maxChildren)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }
		if(!is_numeric($templateId)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }
		
		$sql = 'UPDATE itemtypes SET maxchildren = ' . $maxChildren. ' WHERE itemId=' . $templateId;
		$this -> _conn -> updateRow($sql);
		$this -> _cache -> deleteCache('bright_templateDefinitions');
		return $this -> getTemplateDefinitions();
	}

    /**
     * Creates or updates a template<br/>
     * Required permissions:<br/>
     * <ul>
     * <li>IS_AUTH</li>
     * <li>MANAGE_TEMPLATE</li>
     * </ul>
     * @since 2.1 - 11 feb 2010
     * @param OTemplate $template The template to create or update
     * @param bool $forceSaveAs When true, a new template is generated, regardless of the id of the $template
     * @return array An array of templatedefinitions
     * @throws \Exception
     */
	public function setTemplate($template, $forceSaveAs = false) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
		if(!$this -> MANAGE_TEMPLATE) {
            throw $this->throwException(TemplateException::TEMPLATE_CREATE);
        }
			
		$this -> _cache -> deleteCache('bright_templateDefinitions');
		if($template -> id == null || (int)$template -> id == 0 || $forceSaveAs) {
			$this -> _createTemplate($template);
		} else {
			$this -> _updateTemplate($template);
		}
		return $this -> getTemplateDefinitions();
	}
	
	/**
	 * Creates a templateDefinition from a mysql result
	 * @param array $dbresult An array of mysqlrows
	 * @return OTemplate The generated template
	 */
	private function _createDefinition($dbresult) {
		$definition = new OTemplate();
		$definition -> templatename = $dbresult[0] -> templatename;
		$definition -> templatetype = (int)$dbresult[0] -> templatetype;
		$definition -> parser = (int)$dbresult[0] -> parser;
		$definition -> visible = (int)$dbresult[0] -> visible == 1;
		$definition -> itemtype = $dbresult[0] -> itemtype;
		$definition -> id = (double) $dbresult[0] -> itemId;
		$definition -> icon = $dbresult[0] -> icon;
		$definition -> lifetime = $dbresult[0] -> lifetime;
		$definition -> maxchildren = (double) $dbresult[0] -> maxchildren;
		$definition -> fields = array();
		foreach($dbresult as $row) {
			if($row -> label == null)
				continue;
			$field = new \stdClass();
			$field -> label = $row -> label;
			$field -> displaylabel = $row -> displaylabel;
			$field -> index = (int)$row -> index;
			$field -> data = $row -> data;
			$field -> type = $row -> type;
			$field -> contenttype = $row -> contenttype;
			$field -> searchable = (int)$row -> searchable == 1;
			$definition -> fields[] = $field;
			unset($field);			
		}
		return $definition;
	}
	
	private function _createTemplate($template) {
		$template -> itemtype = trim($template -> itemtype);
		
		if($template -> itemtype == '')
			throw $this -> throwException(7005);
			
		$sql = 'SELECT COUNT(`label`) as `labelcount` FROM `itemtypes` WHERE `label`=\'' . Connection::getInstance() -> escape_string($template -> itemtype) . '\'';
		
		if((int)$this -> _conn -> getField($sql) > 0)
			throw $this -> throwException(7004);
			
		
		$sql = 'INSERT INTO `itemtypes` (`label`, `displaylabel`,`icon`, `lifetime`, `priority`, `maxchildren`, `templatetype`, `parser`) ' .
				'VALUES (' .
				"'" . Connection::getInstance() -> escape_string($template -> itemtype) . "', " .
				"'" . Connection::getInstance() -> escape_string($template -> templatename) . "', " .
				"'" . Connection::getInstance() -> escape_string($template -> icon) . "', " .
				"'" . Connection::getInstance() -> escape_string($template -> lifetime) . "', " .
				(double) $template -> priority . ', ' .
				(double) $template -> maxchildren . ',' .
				(int) $template -> templatetype . ',' . 
				(int) $template -> parser . ')';
		$insertId = $this -> _conn -> insertRow($sql);
		if($insertId == 0)
			throw $this -> throwException(7006);
		
		$template -> id = $insertId;
		
		$this -> _setFields($template);
	}
	
	private function _updateTemplate($template) {
		$template -> itemtype = trim($template -> itemtype);
		
		if($template -> itemtype == '')
			throw $this -> throwException(TemplateException::INVALID_NAME);
		
		$sql = 'UPDATE `itemtypes` SET ' .
				"`label` = '" . Connection::getInstance() -> escape_string($template -> itemtype) . "', " .
				"`displaylabel` = '" . Connection::getInstance() -> escape_string($template -> templatename) . "', " .
				"`icon` = '" . Connection::getInstance() -> escape_string($template -> icon) . "', " .
				"`lifetime`='" . Connection::getInstance() -> escape_string($template -> lifetime) . "', " .
				'`priority`=' . (double) $template -> priority . ', ' .
				'`maxchildren`=' . (double) $template -> maxchildren . ', ' .
				'`templatetype`=' . (int) $template -> templatetype . ', ' . 
				'`parser`=' . (int) $template -> parser .
				' WHERE itemId=' . (int) $template -> id;
		$this -> _conn -> updateRow($sql);
		$this -> _setFields($template);
	}
	
	private function _setFields(OTemplate $template) {
		$sql = 'DELETE FROM `itemdefinitions` WHERE `itemType`=' . (int) $template -> id;
		$this -> _conn -> deleteRow($sql);
		$index = 0;
		if(count($template -> fields) == 0)
			return;
			
		$sql = 'INSERT INTO `itemdefinitions` (`itemType`,`label`,`displaylabel`,`index`,`fieldType`, `contenttype`, `data`,`searchable`) VALUES ';
		foreach($template -> fields as $field) {
			$field = (object) $field;
			$searchable = ($field -> searchable) ? 1 : 0;
			$sql .= "(" . $template -> id .", " .
					"'" . Connection::getInstance() -> escape_string($field -> label) . "', " .
					"'" . Connection::getInstance() -> escape_string($field -> displaylabel) . "', " .
					(int)$field -> index . ", " .
					"'" . Connection::getInstance() -> escape_string($field -> type) . "', " .
					"'" . Connection::getInstance() -> escape_string($field -> contenttype) . "', " .
					"'" . Connection::getInstance() -> escape_string($field -> data) . "', " .
					$searchable . "), ";
			$index++;
		}
		$sql = substr($sql, 0, strlen($sql) - 2);
		$this -> _conn -> insertRow($sql);
	}
}