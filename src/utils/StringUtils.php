<?php

namespace fur\bright\utils;

class StringUtils {
	
	private $_s;
	
	function __construct($string) {
		$this -> _s = $string;
		return $this;
	}
	
	public function toLowerCase() {
		$this -> _s = strtolower($this -> _s);
		return $this; 
	}
	
	public function toUpperCase() {
		$this -> _s = strtoupper($this -> _s);
		return $this; 
	}
	
	public function indexOf($search, $offset = null) {
		if($offset !== null)
			return strpos($this -> _s, $search, $offset);
		return strpos($this -> _s, $search);
	}
	
	public function lastIndexOf($search, $offset = null) {
		if($offset !== null)
			return strrpos($this -> _s, $search, $offset);
		return strrpos($this -> _s, $search);
	}
	
	public function subString($start, $length = null) {
		if($length !== null)
			return new StringUtils(substr($this -> _s, $start, $length));
		return new StringUtils(substr($this -> _s, $start));
	}
	
	public function startsWith($needle) {
		return !strncmp($this -> _s, $needle, strlen($needle));
	}
	
	public function endsWith($needle) {
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
	
		return (substr($this -> _s, -$length) === $needle);
	}
	
	public function charAt($idx) {
		return new StringUtils(substr($this -> _s, $idx, 1));
	}
	
	public function length() {
		return strlen($this -> _s);
	}
		
	public function __toString() {
		return $this -> _s;
	}
}
