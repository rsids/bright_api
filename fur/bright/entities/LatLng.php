<?php
namespace fur\bright\entities;

/**
 * Makes sure that lat & lng are doubles
 */
class LatLng {
	function __construct() {
		$this -> lat = (double) $this -> lat;
		$this -> lng = (double) $this -> lng;
	}	

	public $lat = 0;
	public $lng = 0;
}