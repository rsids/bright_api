<?php

namespace fur\bright\utils;

use fur\bright\api\tree\Tree;
use fur\bright\core\Connection;
use fur\bright\exceptions\UploadException;
use fur\bright\frontend\Serve;

class BrightUtils {
	public static $SQLDate = 'Y-m-d';
	public static $SQLDateTime = 'Y-m-d H:i:s';
	
	
	/**
	 * Gets the values defined by $key from the objects in the array $array
	 * @param array $array The array with objects
	 * @param string $key The name of the variable in the object
	 * @return array An array with the values of the objects
	 */
	public static function array_getObjectValues($array, $key) {
		$arr = array();
		foreach($array as $item) {
			if(isset($item -> {$key})) {
				$arr[] = $item -> {$key};
			}
		}
		return $arr;
	}
	
	/**
	 * Takes an array with key => value pairs and return an array of the values (in other words: the keys are ditched)
	 * @param array $arr The array to clean
	 * @return array The cleaned array
	 */
	public static function cleanArray($arr) {
		$ret = array();
		foreach($arr as $key => $value)
			$ret[] = $value;
	
		return $ret;
	}
	
	public static function createSearchString($obj) {
		$str = '';
		if($obj) {
			foreach($obj as $value) {
				if(is_scalar($value)) {
					$str .= strip_tags($value) . " ";
				} else {
					$str .= BrightUtils::createSearchString($value);
				}
			}
		}
		return $str;
	}
	
	
	public static function DOMinnerHTML($element) {
		$innerHTML = "";
		$children = $element -> childNodes;
		
		foreach ($children as $child) {
			$tmp_dom = new \DOMDocument();
			$tmp_dom -> appendChild($tmp_dom -> importNode($child, true));
			$innerHTML .= trim($tmp_dom -> saveHTML());
		}
		return $innerHTML;
	}

	/**
	 * Escapes string values of an object. Also, HTML tags are stripped and the string is trimmed
	 * @param mixed|\stdClass $item The item to insert in the database
	 * @param array $fields The fields to escape
	 */
	public static function escape(&$item, $fields) {
		if(is_array($item)) {
			foreach($fields as $field) {
				if(array_key_exists($field, $item))
					$item[$field] = Connection::getInstance() -> escape_string(strip_tags(trim($item[$field])));
			}

		} else {
			foreach($fields as $field) {
				if(isset($item -> {$field}))
					$item -> {$field} = Connection::getInstance() -> escape_string(strip_tags(trim($item -> {$field})));
			}
		}
	}

	/**
	 * Escapes date values of an object, set to 'null' if date is nonexistant
	 * @param \stdClass $item The item to insert in the database
	 * @param array $fields The fields to escape
	 */
	public static function escapeDate(&$item, $fields) {
		foreach($fields as $field) {
			if(!isset($item -> {$field}) || (int)$item -> {$field} == null || (int)$item -> {$field} == 0) {
				$item -> {$field} = 'null';
			} else {
				$item -> {$field} = "'" . Connection::getInstance() -> escape_string(filter_var($item -> {$field}, FILTER_SANITIZE_STRING)) . "'";
			}
		}
	}

    /**
     * Escapes a single string values
     * @param string $item The item to insert in the database
     * @return string
     */
	public static function escapeHtml($item) {
		return Connection::getInstance() -> escape_string(trim($item));
	}

    /**
     * Escapes a single string values
     * @param string $item The item to insert in the database
     * @return string
     */
	public static function escapeSingle($item) {
		return Connection::getInstance() -> escape_string(strip_tags(trim($item)));
	}
	
	/**
	 * Opens a file using / forcing utf-8 encoding
	 * @param string $fn The path to the filename
	 * @return string The utf-8 encoded contents
	 */
	public static function file_get_contents_utf8($fn) {
		$content = file_get_contents($fn);
		return mb_convert_encoding($content, 'UTF-8',
				mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}
	
	/**
	 * Finds the nearest DateObject to the given timestamp
	 * @param int $timestamp 
	 * @param array $dateobjects An array of dateobjects
	 * @return mixed <NULL, DateObject>
	 */
	public static function findNearestDateObject($timestamp, $dateobjects) {
		$diff = 999999999;
		$nearest = null;
		foreach($dateobjects as $do) {
			if($do -> starttime > $timestamp && $do -> starttime - $timestamp < $diff) {
				$diff = $do -> starttime - $timestamp;
				$nearest = $do;
			}
		}
		return $nearest;
	}

	public static function forceDouble(&$item, $fields) {
		foreach($fields as $field) {
			if(isset($item -> {$field})) {
				$item -> $field = filter_var ($item -> $field, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				if(!$item -> $field)
					$item -> $field = 0;
			} else {
				$item -> $field = 0;
			}
		}
	}

	public static function forceInt(&$item, $fields, $setNull = false) {
		foreach($fields as $field) {
			if(isset($item -> {$field})) {
				$item -> $field = filter_var ($item -> $field, FILTER_SANITIZE_NUMBER_INT);
			} else {
				$item -> $field = 0;
			}
			if($setNull && !$item -> $field) {
				$item -> $field = 'null';
			}
		}
	}

    /**
     * Method to check whether the script runs in a browser
     * @param bool $throwException
     * @return bool
     */
	public static function inBrowser($throwException = true) {
		if (substr(php_sapi_name(), 0, 3) == 'cgi') {
			$checkEnvVars = array('HTTP_USER_AGENT', 'HTTP_HOST', 'SERVER_NAME', 'REMOTE_ADDR', 'REMOTE_PORT', 'SERVER_PROTOCOL');
			foreach ($checkEnvVars as $var) {
				if (array_key_exists($var, $_SERVER)) {
					if($throwException) {
						echo 'This script cannot be used within your browser!';
						error_log('This script cannot be used within your browser!');
						exit;
					} else {
						return true;
					}
				}
			}
			unset($checkEnvVars);

			ini_set('html_errors', 0);
			ini_set('implicit_flush', 1);
			ini_set('max_execution_time', 0);
			if (!ini_get('register_argc_argv')) {
				$argv = $_SERVER['argv'];
				$argc = $_SERVER['argc'];
			}

		} elseif (php_sapi_name() != 'cli') {
			if($throwException) {
				echo 'This script cannot be used within your browser!';
				error_log('This script cannot be used within your browser!');
				exit;
			} else {
				return true;
			}
		}
		return false;
	}

    /**
     * Checks if the given file is an image
     * @param mixed $path
     * @return bool
     */
	public static function isImage($path) {
		$a = getimagesize($path);
		$image_type = $a[2];
			
		if(in_array($image_type , array(IMAGETYPE_GIF , IMAGETYPE_JPEG ,IMAGETYPE_PNG , IMAGETYPE_BMP))) {
			return true;
		}
		return false;
	}

	/**
	 * Changes dates with timestamp 0 (zero) to null values
	 * @param \stdClass $item The item to insert in the database
	 * @param array $fields The fields to nullify
	 */
	public static function nullifydate(&$item, $fields) {
		foreach($fields as $field) {
			if(!isset($item -> {$field}) || (int)$item -> {$field} == null || (int)$item -> {$field} == 0) {
				$item -> {$field} = 'null';
			} else {
				$item -> {$field} = "'" . Connection::getInstance() -> escape_string($item -> {$field}) . "'";
			}

		}
	}


    /**
     * Changes id's with value 0 (zero) to null
     * @param \stdClass $item The item to insert in the database
     * @return int|\stdClass|string
     */
	public static function nullifyItem(&$item) {
		if($item == 0 || $item == '') {
			$item = 'null';
		} else {
			$item = (int) $item;
		}
		return $item;
	}
	
	/**
	 * Redirects to the given treeId
	 * @param int $treeId
	 */
	public static function redirect($treeId) {
		$t = new Tree();
		$path = $t -> getPath((int)$treeId);
		$url = BASEURL;
		if($path === null) {
			$bs = new Serve();
			$bs -> serveSpecial(Serve::$SPECIAL_404);
			exit;
		}
		if(USEPREFIX) {
			if(isset($_SESSION) && isset($_SESSION['language'])) {
				$url .= $_SESSION['language'] . '/';
			} else {
				$l = explode(',',AVAILABLELANG);
				$url .= $l[0] . '/';
			}
		}
		$url .= $path;
		header("Location: $url");
		exit;
	}

	/**
	 * Trims the text to the given length in a smart way. Does not break inside words, or even inside sentences
	 * @param string $text The text to trim
	 * @param int $maxchars The number of characters to trim the text to
	 * @param bool $removeh1 When true, h1 tags are removed from the text
	 * @param bool $removeall When true, all html markup is removed from the text
	 * @param bool $adddots When true, triple dots are appended to the text
	 * @param bool $splitonspace When true, the text will also split on spaces, not just on whole sentences
	 * @return mixed|string
	 */
	public static function trimText($text, $maxchars, $removeh1 = false, $removeall = false, $adddots = false, $splitonspace = false) {
		if($removeh1)
			$text = preg_replace("/<h1>.*?<\/h1>/", '', $text);

		$text = ($removeall) ? strip_tags($text) : strip_tags($text, "<p><b><i><em><span><div>");

		if(strlen($text) <= $maxchars)
			return $text;

		$text = str_replace('&nbsp;', ' ', $text);

		$separator = ($splitonspace) ? ' ' : '.';
		$arr = explode($separator, $text);
		$chars = 0;
		$retstr = "";
		$current = 0;
		while($chars < $maxchars && $current < count($arr)) {
			$retstr .= $arr[$current] . $separator;
			$chars = strlen($retstr);
			$current ++;
		}
		$numopenp = count(explode("<p>", strtolower($retstr)));
		$numclosep = count(explode("</p>", strtolower($retstr)));
		if($adddots) {
			$retstr .= '&hellip;';
			$retstr = str_replace(array('.&hellip;', '. &hellip;', ' &hellip;'),
									array('&hellip;', '&hellip;', '&hellip;'),
									$retstr);
		}
		while($numclosep < $numopenp) {
			$retstr .="</p>";
			$numclosep++;
		}
		return trim($retstr);
	}


    /**
     * Check if haystack starts with needle
     * @param String $haystack
     * @param String $needle
     * @return bool
     */
	public static function startsWith($haystack, $needle) {
		return !strncmp($haystack, $needle, strlen($needle));
	}

    /**
     * Check if haystack ends with needle
     * @param String $haystack
     * @param String $needle
     * @return bool
     */
	public static function endsWith($haystack, $needle) {
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}
	
		return (substr($haystack, -$length) === $needle);
	}
	
	/**
	 * Upload a file to the server
	 * @param mixed $file The file blob
	 * @param string $name The name of the file on the server
	 * @param string $dest The path to the file, relative to UPLOADFOLDER
	 * @param int $max The maximum file size, in bytes
	 * @param array $extensions An array of allowed extensions
	 * @param boolean $imageOnly When true, an extra check is done to see if the file is an image
	 * @throws UploadException
	 * @return string|boolean
	 */
	public static function uploadFile($file, $name, $dest, $max, $extensions, $imageOnly = false) {
		$farr = explode('.',$file['name']);
		$ext = array_pop($farr);
		$extension = strtolower($ext);

		if(!in_array($extension,$extensions)) {
			throw new UploadException(UploadException::getErrorMessage(UploadException::INVALID_EXTENSION), UploadException::INVALID_EXTENSION);
		}

		$size = filesize ($file['tmp_name']);

		if($size > $max) {
			throw new UploadException(UploadException::getErrorMessage(UploadException::FILE_TOO_BIG), UploadException::FILE_TOO_BIG);
		}

		if($imageOnly === true && !getimagesize($file['tmp_name'])) {
			throw new UploadException(UploadException::getErrorMessage(UploadException::NOT_AN_IMAGE), UploadException::NOT_AN_IMAGE);
		}

		$uploaddir = BASEPATH . UPLOADFOLDER . $dest;
		if(!is_dir($uploaddir))
			throw new UploadException(UploadException::getErrorMessage(UploadException::INVALID_PATH), UploadException::INVALID_PATH);

		/*
		 * Just to be REALLY sure... Prevent stupid users from doing very stupid
		 * things and prevent bad users from doing very bad things...
		 * Cannot be careful enough with user-uploaded data.
		 */
		$filename = $name . '_' . time().'_' . rand(0,100) . '.' .$extension;
		$uploadfile = $uploaddir.$filename;
		if (move_uploaded_file($file['tmp_name'], $uploadfile)) {
			/* Paranoid? Eh, you never know... */
			chmod($uploadfile, 0444);
			return  $dest . $filename;
		}

		
		throw new UploadException(UploadException::getErrorMessage(UploadException::UPLOAD_FAILED), UploadException::UPLOAD_FAILED);
	}
}