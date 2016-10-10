<?php
namespace fur\bright\entities;

/**
 * This class defines the Layer object
 * @author bs10
 * @version 1.0
 * @package Bright
 * @subpackage objects
 */
class OLayer {
	
	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> layerId = (int) $this -> layerId;
		$this -> modificationdate = (int) $this -> modificationdate;
		$this -> color = (double) $this -> color;
		$this -> content = (object) $this -> content;
		$this -> index = (double) $this -> index;
		$this -> deleted = ($this -> deleted == 1);
	}
	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OLayer';
	

	public $layerId = 0;
	public $color = 0;
	public $index = 0;
	public $modificationdate = 0;
	public $label = '';
	public $content;
	public $deleted = false;
	
}