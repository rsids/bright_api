<?php
namespace fur\bright\entities;
/**
 * This class defines the FieldType object, used by the Template classes
 * @author bs10
 * @version 2.0
 * @package Bright
 * @subpackage objects
 */
class OFieldtype {
	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OFieldtype';
	
	function __construct() {
		$this -> id = (int) $this -> id;	
		$this -> availableproperties = json_decode($this -> availableproperties);
	}
	
	/**
	 * @var int The id of the FieldType
	 */
	public $id = 0;
	/**
	 * @var string The name of the Fieldtype
	 */
	public $type = '';
	/**
	 * @var array A array of name-value pairs, holding the available properties of the field
	 */
	public $availableproperties = '';
}