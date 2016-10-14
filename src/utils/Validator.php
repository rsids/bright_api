<?php
namespace fur\bright\utils;

/**
 * Validator for several inputs
 * @author Fur
 * @deprecated Use PHP build in validation methods (filter_var) Since 3.1: All non-static methods are deprecated
 * @version 3.1
 * @package Bright
 * @subpackage utils
 */
class Validator {


	public static function stringFilter($data) {
		$data = filter_var($data, FILTER_SANITIZE_STRING);
		if($data) {
			return strlen($data) > 0 ? $data : false;
		}
		return false;
	}

	/**
	 * Checks if a string is a valid dutch zip code (DDDDWW). Spaces are ignored
	 * @param string $zip The zip code to check
	 * @return boolean True when valid
	 */
	public static function zipFilter($zip) {
		$zip = filter_var($zip, FILTER_SANITIZE_STRING);
		if (!$zip || $zip == "")
			return false;
		$zip = trim(strtoupper(join("", explode(" ", $zip))));
		return (preg_match('/[1-9]{1}[0-9]{3}[A-Z]{2}$/', $zip) ==1) ? $zip : false;

	}

	/**
	 * Checks if a string has the specified length. Whitespace is stripped
	 * @param string $str The string to check
	 * @param int $length The minimum length of the string
	 * @return boolean True when valid
	 */
	public function checkString($str, $length) {
		return (strlen(trim($str)) >= $length);
	}

	/**
	 * Checks if a string is a valid name
	 * @param string $name The string to check
	 * @return boolean True when valid
	 */
	public function checkName($name) {
		return (strlen($name) > 1);
	}

	/**
	 * Checks if a string is a valid dutch zip code (DDDDWW). Spaces are ignored
	 * @param string $zip The zip code to check
	 * @return boolean True when valid
	 */
	public function checkZip($zip) {
		if ($zip == "")
			return false;
		$zip = trim(strtoupper(join("", explode(" ", $zip))));
		return (preg_match('/[1-9]{1}[0-9]{3}[A-Z]{2}$/', $zip)) ;

	}

	/**
	 * Checks if the variable is a number
	 * @param mixed $number The variable to check
	 * @return boolean True when valid
	 */
	public function checkNumber($number) {
		return is_numeric($number);
	}

	/**
	 * Checks if the string is a valid dutch telephone number (10 digits)
	 * @param string $string The string to check
	 * @return boolean True when valid
	 */
	public function checkPhone($string) {
		$string = trim($string);

		// Phone numbers start with 0
		if(strpos($string, '0') !== 0)
			return false;
		// Check for invalid characters
		if(preg_match('/[^\d\- ]/', $string))
			return false;

		return strlen(preg_replace("/[^\d]/", '', $string)) == 10;
	}
	/**
	 * Checks if the string is a valid password
	 * @param string $password The string to check
	 * @param int $len The minimum length
	 * @return boolean True when valid
	 */
	public function checkPassword($password, $len = 5) {
		return (strlen($password) > $len);
	}

}