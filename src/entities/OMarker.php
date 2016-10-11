<?php
namespace fur\bright\entities;
/**
 * This class defines the Marker object
 * Version history:
 * 2.2, 20120622:
 * - Added address fields
 * 2.1, 20120521:
 * - Added enabled
 * 2.0, 20120416:
 * - Added search
 * @author fur
 * @version 2.0
 * @package Bright
 * @subpackage objects
 */
class OMarker extends OPage {

	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> markerId = (int) $this -> markerId;
		$this -> layer = (double) $this -> layer;
		$this -> lat = (double) $this -> lat;
		$this -> lng = (double) $this -> lng;
		$this -> color = (int) $this -> color;
		$this -> iconsize = (int) $this -> iconsize;
		$this -> enabled = ($this -> enabled == 1);
		$this -> uselayercolor = ($this -> uselayercolor == 1);
		$this -> deleted = ($this -> deleted == 1);
		parent::__construct();
	}

	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OMarker';


	public $lat = 0;
	public $lng = 0;
	public $markerId = 0;
	public $layer = 0;
	public $color = 0;
	public $iconsize = 16;
	public $icon = '';
	/**
	 * Enabled
	 * @var boolean
	 * @since 2.1 - 20120521
	 */
	public $enabled = true;
	public $uselayercolor = true;
	public $deleted = false;

	/**
	 * Fulltext search
	 * @var string
	 * @since 2.0 - 20120416
	 */
	public $search = null;
	
	public $street;
	public $number;
	public $city;
	public $zip;
	public $country;

}