<?php
namespace fur\bright\entities;
/**
 * This class defines the Poly object
 * Version history:
 * 2.1, 20120521:
 * - Added enabled
 * 2.0, 20120416:
 * - Added search
 * @author fur
 * @version 2.1
 * @package Bright
 * @subpackage objects
 */
class OPoly extends OPage {

	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> polyId = (int) $this -> polyId;
		$this -> layer = (int) $this -> layer;
		$this -> color = (int) $this -> color;
		$this -> isShape = ($this -> isShape == 1);
		$this -> uselayercolor = ($this -> uselayercolor == 1);
		$this -> enabled = ($this -> enabled == 1);
		$this -> deleted = ($this -> deleted == 1);
		parent::__construct();
	}

	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OPoly';

	/**
	 * @var int The id of the poly
	 */
	public $polyId = 0;
	/**
	 * @var int The id of the layer
	 */
	public $layer = 0;
	/**
	 * @var double The color of the layer
	 */
	public $color = 0;
	/**
	 * @var boolean Indicates whether this poly has it's own color, or if it uses the color of the layer
	 */
	public $uselayercolor = true;
	/**
	 * @var boolean Indicates whether this poly is deleted
	 */
	public $deleted = false;
	/**
	 * @var boolean Indicates whether this poly is a line (false) or a shape (true)
	 */
	public $isShape = false;
	/**
	 * @var array An array of points. Each point is an object, containing a lat value and a lng value
	 */
	public $points;

	/**
	 * Fulltext search
	 * @var string
	 * @since 2.0 - 20120416
	 */
	public $search = null;
	
	
	/**
	 * Enabled
	 * @var boolean
	 * @since 2.1 - 20120521
	 */
	public $enabled = true;
}