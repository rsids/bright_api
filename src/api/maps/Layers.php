<?php
namespace fur\bright\api\maps;
use fur\bright\core\Connection;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\ParameterException;
use fur\bright\Permissions;

/**
 * Handles the creating, updating and returning of layers.
 * @author Fur
 * @version 1.0
 * @package Bright
 * @subpackage maps
 */
class Layers extends Permissions  {
	
	function __construct() {
		parent::__construct();
		
		$this -> _conn = Connection::getInstance();
	}
	
	private $_conn;


    /**
     * Gets all the available layers
     * @param bool $updatesOnly
     * @return array
     */
	public function getLayers($updatesOnly = false) {
		//ALTER TABLE  `gm_layers` ADD  `modificationdate` INT( 11 ) NOT NULL
		$sql = 'SELECT * FROM gm_layers ' .
				'WHERE `deleted`=0 ';
			
		if($updatesOnly && isset($_SESSION['lastlayerssupdate']) &&  $_SESSION['lastlayerssupdate'] != '') {
			$sql .= 'AND modificationdate > ' . $_SESSION['lastlayerssupdate'] . ' ';
		}

		$sql .=	'ORDER BY `index`';

		$_SESSION['lastlayerssupdate'] = time();
		
		$layers = $this -> _conn -> getRows($sql, 'OLayer');
		foreach($layers as $layer) {
			$layer = $this -> _getContent($layer);
		}
		return $layers;
	}

    /**
     * Gets a layer by it's ID
     * @param int $id The id of the layer
     * @return object
     * @throws\ Exception
     */
	public function getLayer($id) {
		if(!is_numeric($id)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }
			
		$sql = 'SELECT * ' .
				'FROM gm_layers gl ' .
				'WHERE `deleted`=0 AND gl.layerId=' . $id;
		$layer = $this -> _conn -> getRow($sql, 'OLayer');
		if($layer)
			$layer = $this -> _getContent($layer);
		return $layer;
	}

    /**
     * Creates or updates a layer
     * @param OLayer|stdClass $layer A layer object
     * @return \stdClass An object containing layer, The saved layer, and layers, an array of updated layers
     * @throws \Exception
     */
	public function setLayer(OLayer $layer) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
				
		if(!is_numeric($layer -> layerId) || !is_numeric($layer -> color) || !is_numeric($layer -> index))
			throw $this -> throwException(ParameterException::INTEGER_EXCEPTION);
		
		if($layer -> index < 0) {
			$layer -> index = 'IFNULL((SELECT mi FROM (SELECT MAX(`index`)+1 as `mi` FROM `gm_layers`) as `mi`), 0)';
		}
		$id = $layer -> layerId;
		$page = new Page();
		$langs = explode(',', AVAILABLELANG);
		
		$layer -> content = (object) $layer -> content;
		if(!isset($layer -> content -> title)) {
			throw $this -> throwException(6004);
		}
		$layer -> content -> title = (object) $layer -> content -> title;
		
		foreach($langs as $lang) {
			if(isset($layer -> content -> title -> {$lang})) {
				$layer -> label = $layer -> content -> title -> {$lang};
				break;
			}
				
		}
		$layer -> label = $page -> generateLabel($layer -> label);
		if($layer -> layerId == 0) {
			$sql = 'INSERT INTO `gm_layers` (`index`, `label`, `color`, `modificationdate`) VALUES (' . $layer -> index . ', \'' . Connection::getInstance() -> escape_string($layer -> label) . '\', ' . $layer -> color . ', ' . time() . ')';
			$id = $this -> _conn -> insertRow($sql);
		
		} else {
			$sql = 'UPDATE `gm_layers` ' .
					'SET `label`=\'' . Connection::getInstance() -> escape_string($layer -> label) . '\', ' .
					'`color`=' . $layer -> color . ', ' .
					'`index`=' . $layer -> index . ', ' .
					'`modificationdate`= ' . time() . ' ' .
					'WHERE `layerId`=' . $layer -> layerId;
			$this -> _conn -> updateRow($sql);
			
			$sql = 'UPDATE `gm_markers` SET `color`=' . $layer -> color . ' WHERE `uselayercolor`=1 AND `layer`=' . $layer -> layerId;
			$this -> _conn -> updateRow($sql);
		}
		$this -> _setContent($layer);
		
		
		$ret = new \stdClass();
		$ret -> layer = $this -> getLayer($id);
		$ret -> layers = $this -> getLayers(true);
		return $ret;
		
	}
	
	public function deleteLayer($layerId) {
		if(!$this -> IS_AUTH) {
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);
        }
				
		if(!is_numeric($layerId)) {
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);
        }
			
		$sql = 'UPDATE `gm_layers` SET `deleted`=1 WHERE layerId=' . $layerId;
		$this -> _conn -> deleteRow($sql);
		return true; 
	}
	
	/**
	 * Gets the (language specific) content of a layer
	 * @param stdClass $layer The layer object
	 * @return stdClass The layer object filled with the content
	 */
	private function _getContent($layer) {
		$sql = 'SELECT * FROM gm_layer_content WHERE layerId=' . (int) $layer -> layerId;
		$content = $this -> _conn -> getRows($sql);
		$layer -> content = new \stdClass();
		foreach($content as $row) {
			$field = $row -> field;
			$lang = $row -> lang;
				
			if(!isset($layer -> content -> $field)) {
				$layer -> content -> $field = new stdClass();
			}
			$layer -> content -> $field -> $lang = $row -> value;
			
		}
		
		return $layer;
	}
	
	/**
	 * Saves the (language specific) content of a layer
	 * @param stdClass $layer The layer object
	 */
	private function _setContent($layer) {
		$sql = 'SELECT MAX(`contentId`) FROM `gm_layer_content` WHERE `layerId`=' . (int) $layer -> layerId;
		$oldId = $this -> _conn -> getField($sql);
		if(!$oldId)
			$oldId = 0;
			
		
		$sql = 'INSERT INTO `gm_layer_content` (`layerId`, `lang`, `field`, `value`) VALUES ';
		foreach($layer -> content as $field => $langs) {
			
			foreach($langs as $lang => $val) { 
				$sql .= '(' . $layer -> layerId . ', ' .
						"'" . Connection::getInstance() -> escape_string($lang) . "', " .
						"'" . Connection::getInstance() -> escape_string($field) . "', " .
						"'" . Connection::getInstance() -> escape_string($val) ."'), ";
					
			
			}
		}
		
		$sql = substr($sql, 0, strlen($sql) - 2);
		$result = $this -> _conn -> insertRow($sql);
		if($result !== false && $result > 0) {
			// All is well, clean up old data
			$sql = 'DELETE FROM `gm_layer_content` WHERE `contentId` <= ' . $oldId . ' AND  `layerId`=' . (int) $layer -> layerId;
			$this -> _conn -> deleteRow($sql);
		}
	}
	
}