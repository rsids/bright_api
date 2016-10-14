<?php

namespace fur\bright\utils;
/**
 * This class does some arbitrary work to prevent some basic vulnerabilities
 * in PHP and / or libraries.
 * Call this in every class, or include library/Bright/Bright.php
 * @author ids
 *
 */
class Security {
	
	public static function init() {
		if(function_exists('libxml_disable_entity_loader'))
			libxml_disable_entity_loader(true);
	}
}