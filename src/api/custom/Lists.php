<?php
namespace fur\bright\api\custom;

use fur\bright\core\Connection;
use fur\bright\Permissions;

class Lists extends Permissions {
	
	/**
	 * @var Connection Holds the Connection singleton
	 */
	protected $conn;
	
	static private $_exclude = array('administratorpermissions','administrators','adminlabels','backup','calendar','calendarevents','content','gm_layer_content','gm_layers','gm_markers','gm_polypoints','gm_polys','itemdefinitions','itemtypes','mailqueue','page','parsers','settings','tree','treeaccess','twitter','update','user','userfields','usergroups','userusergroups');
	
	function __construct() {
		parent::__construct();
		$this -> conn = Connection::getInstance();
	}
	
	public function addListValue($table, $labelfield, $name) {
		$table = filter_var(trim($table), FILTER_SANITIZE_STRING);
		$labelfield = filter_var(trim($labelfield), FILTER_SANITIZE_STRING);
		$name = filter_var(trim($name), FILTER_SANITIZE_STRING);
		if($table && !in_array($table, Lists::$_exclude)) {
			$table = Connection::getInstance() -> escape_string($table);
			$labelfield = Connection::getInstance() -> escape_string($labelfield);
			$name = Connection::getInstance() -> escape_string($name);
			Connection::getInstance() -> insertRow("INSERT INTO `$table` (`$labelfield`) VALUES ('$name')");
		}
		return $this -> getListValues($table);
	}
	
	public function deleteListValue($table, $identifier, $id) {
		$table = filter_var(trim($table), FILTER_SANITIZE_STRING);
		$identifier = filter_var(trim($identifier), FILTER_SANITIZE_STRING);
		$id  = (int) filter_var($id, FILTER_SANITIZE_NUMBER_INT);
		if($table && !in_array($table, Lists::$_exclude)) {
			$table = Connection::getInstance() -> escape_string($table);
			$identifier = Connection::getInstance() -> escape_string($identifier);
			Connection::getInstance() -> deleteRow("DELETE FROM $table WHERE $identifier=$id");
		}
		return $this -> getListValues($table);
	}
	
	/**
	 * Gets the values of a custom table
	 * @param string table The name of the table. Only custom tables are accepted.
	 * @return array An array of rows (object), or null on failure
	 */
	public function getListValues($table) {
		// Exclude bright tables
		$table = filter_var(trim($table), FILTER_SANITIZE_STRING);
		if($table && !in_array($table, Lists::$_exclude)) {
			$table = Connection::getInstance() -> escape_string($table);
			return $this -> conn -> getRows("SELECT * FROM `$table`");
		}
		return null;
	}
	
	public function getTableStructure($table) {
		$table = filter_var(trim($table), FILTER_SANITIZE_STRING);
		if(!$table) {
			throw $this -> throwException(1000);
		}
		$table = Connection::getInstance() -> escape_string($table);
		$sql = "SHOW COLUMNS FROM $table";
		$fields = $this -> conn -> getRows($sql);
		
		$result = array();
		foreach($fields as $field) {
			if(strstr($field -> Extra, 'auto_increment') === false) {
				// Skip autoincrement values;
				$result[] = (object)array('label' => $field -> Field, 'type' => $this -> _getType($field -> Type));
			}
		}
		
		return $result;
	}
	
	private function _getType($type) {
		if(strstr($type, 'int') === 0) {
			return 'Integer';
		}
		if(strstr($type, 'double') === 0) {
			return 'Number';
		}
		if(strstr($type, 'text') === 0) {
			return 'Text';
		}
		if(strstr($type, 'varchar') === 0) {
			return 'String';
		}
		if(strstr($type, 'date') === 0) {
			return 'Date';
		}
		if(strstr($type, 'tinyint(1)') === 0) {
			return 'Boolean';
		}
	}
}