<?php
namespace fur\bright\entities;

class OBaseObject {
	public function __sleep() {
		return array_keys(get_object_vars($this));
	}
}