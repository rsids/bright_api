<?php
namespace fur\bright\entities;
/**
 * @package Bright
 * @subpackage objects
 */
class ArrayCollection {
	var $_explicitType;
	var $source;

	function ArrayCollection() {
		$this -> _explicitType = 'flex.messaging.io.ArrayCollection';
		$this -> source = array();
	}
}