<?php
namespace fur\bright\frontend;
use fur\bright\api\cache\Cache;
use fur\bright\api\tree\Tree;
use fur\bright\core\Log;

/**
 * @since 20121204 Separated bootstrap & serve
 * @author Ids
 *
 */
class Serve {
	
	public static $SPECIAL_403 = 403;
	public static $SPECIAL_404 = 404;
	
	private $_router;
	private $_cache;
	private $_servecount = 0;

	private $_cached;
	
	function __construct() {
		$this -> _router = new Router();
		$this -> _cache = new Cache();
	}
	
	public function init() {

        $this -> _setLanguage();

		if(isset($_SERVER['REDIRECT_STATUS'])) {
			// no need to check the url, serve a special page
			switch($_SERVER['REDIRECT_STATUS']) {
				case Serve::$SPECIAL_403:
				case Serve::$SPECIAL_404:
					$this -> serveSpecial($_SERVER['REDIRECT_STATUS'], __LINE__);
					return;
			}
		}


		// Fix for Windows / IIS servers
		if (!isset($_SERVER['REQUEST_URI'])) {

			if(isset($_SERVER['HTTP_X_ORIGINAL_URL'])) {
				$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_X_ORIGINAL_URL'];
			} else {
				$_SERVER['REQUEST_URI'] = '/index.php';// substr($_SERVER['PHP_SELF'],1 );
				if (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '') {
					$_SERVER['REQUEST_URI'].='?'.$_SERVER['QUERY_STRING'];
				}
			}
		}
		if($this -> _isFile()){
			$this -> serveSpecial(Serve::$SPECIAL_404, __LINE__);
			return;
		}
		
		// Request by treeId (e.g. /index.php?tid=123
		if(strpos($_SERVER['REQUEST_URI'], '/index.php') === 0 && isset($_GET['tid'])) {

			$bright_tree = new Tree();
			$bright_path = $bright_tree -> getPath($_GET['tid']);
			if($bright_path) {
				// IF path is valid, redirect...
				$url = (USEPREFIX === true) ? BASEURL . $_SESSION['language'] . '/' . $bright_path : BASEURL . $bright_path;
				header('Location: ' . $url);
				exit;
			} else {
				// Else: 404
				$this -> serveSpecial(Serve::$SPECIAL_404);
				return;
			}
		}
		
		// Normal request
		if(isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/index.php') !== 0) {
			$requestUri = $_SERVER['REQUEST_URI'];
		
			// Save get variables
			$urlParameters = explode('?', $requestUri);
			if(count($urlParameters) >= 2) {
				$requestUri = $urlParameters[0];
				$bright_gv = explode('&', $urlParameters[1]);
				foreach($bright_gv as $bright_getval) {
					$bright_gva = explode('=', $bright_getval, 2);
                    if(count($bright_gva) > 1) {
					    $_GET[$bright_gva[0]] = $bright_gva[1];
                    }
				}
			}
			// Get the path
			$treeNodes = $this -> _router -> getTreeNodes($requestUri);
		} else {
			// Just serve the homepage
			$treeNodes = $this -> _router -> getTreeNodes('');
		}
		$bright_is404 = ($treeNodes && is_numeric($treeNodes[0]) && (int) $treeNodes[0] == 404);
		// Path not found
		if($bright_is404){
			$this -> serveSpecial(Serve::$SPECIAL_404, __LINE__);
			return;
		}
		
		$this -> servepage($treeNodes);
	}
	
	public function servepage($nodes) {
		$this -> _servecount++;
		// Save the getvariables into the cached page
		$bright_qstring = '&qstring=';
		$bright_qs = '';
		foreach($_GET as $bright_key => $bright_value) {
			$bright_qs.= "&$bright_key=" . urlencode($bright_value);
		}
		$bright_qstring .= md5($bright_qs);
		
		// Set default
		if(!isset($_SESSION['language']))
			$_SESSION['language'] = 'nl';
		
		$cacheurl = 'page%' . $_SESSION['language'] . '%' . join('%', $nodes) . $bright_qstring;

		$this -> _cached = false; 
		
		if(LIVESERVER && !isset($_GET['nc']))
			$this -> _cached = $this -> _cache -> getCache($cacheurl);
		
		if($this -> _cached !== false) {
			foreach($this -> _cached -> headers as $bright_header) {
				header($bright_header);
			}
			echo $this -> _cached -> contents;
			exit;
		} else {
			
			if(array_key_exists(0, $nodes) && ($nodes[0] == 404 || $nodes[0] == 403)) {
				// No 40x page defined but is requested;
				if($this -> _servecount > 2)
					exit;
			}
			$bright_viewData = $this -> _router -> getView($nodes);
			if(is_numeric($bright_viewData) && (int) $bright_viewData == 404) {
				// turns out to be a 404 after all
				$this -> serveSpecial(Serve::$SPECIAL_404);
				return;
			}
				
			$bright_viewclass = APP_NAMESPACE . 'views\\' . $bright_viewData -> page -> itemLabel . 'View';
			$bright_view = new $bright_viewclass($bright_viewData);
			$bright_output = $bright_view -> output();
			
			if(!isset($_GET['nc']))
				$this -> _cache -> setCache($bright_output . "\n<!--Cached on " . date("r") . " by Bright CMS -->", $cacheurl, $bright_view -> expDate, headers_list());
			
			echo $bright_output;
			exit;
		}
	}
	
	
	
	
	public function serveSpecial($type) {

		switch($type) {
			case Serve::$SPECIAL_403:
				
				if(!isset($_SERVER['REDIRECT_STATUS']) || $_SERVER['REDIRECT_STATUS'] != 403) {
					$_SERVER['REDIRECT_STATUS'] = 403;
					header('Status: 403 Forbidden');
					header($_SERVER['SERVER_PROTOCOL'].' 403 Forbidden');
				}
				// Include 403
				$bright_uri = USEPREFIX ? '/' . $_SESSION['language'] . '/403': '/403';
				$nodes = $this -> _router -> getTreeNodes($bright_uri);
				$this -> servepage($nodes);
				break;
			case Serve::$SPECIAL_404:
				if(isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS'] == 403) {
					// 403 requested, but no 403 error page defined
					exit;
				}
				if(!isset($_SERVER['HTTP_REFERER']))
					$_SERVER['HTTP_REFERER'] = 'unknown';
				
				$bright_log = date('r') . ': ' . $_SERVER['REQUEST_URI'] . ' resulted in a 404, came from: ' . $_SERVER['HTTP_REFERER'];
				Log::addTo404Log($bright_log);
				
				if(!isset($_SERVER['REDIRECT_STATUS']) || $_SERVER['REDIRECT_STATUS'] != 404) {
					$_SERVER['REDIRECT_STATUS'] = 404;
					header('Status: 404 Not Found');
					header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
				}
				
				// Fix for beluga server, THIS SHOULD NEVER HAPPEN!
				if(!array_key_exists('language', $_SESSION)) {
					error_log('LANGUAGE NOT SET');
					error_log(var_export($_SESSION, true));
					$this -> _setLanguage();
				}

				// Include 404
				$bright_uri = USEPREFIX ? '/' . $_SESSION['language'] . '/404/': '/404/';
				$nodes = $this -> _router -> getTreeNodes($bright_uri);
				
				$this -> servepage($nodes);
				break;
		}
	}
	
	/**
	 * Check if the requested url is a file
	 */
	private function _isFile() {
		// Check for valid url
		$isFile = false;
		if(isset($_GET['v'])) {
		
			if(strpos($_GET['v'], '.')) {
				// Deny files!
				$isFile = true;
			} else if(strlen($_GET['v']) > 0) {
				// Check if we're inside an existing directory
				$bright_path = explode('/', $_GET['v']);
				$bright_i = 0;
				$bright_base = BASEPATH;
				while($bright_i < count($bright_path)) {
					$bright_base .= $bright_path[$bright_i] .'/';
					$isFile = is_dir($bright_base);
					$bright_i++;
				}
			}
		}
		return $isFile;
	}
	
	/**
	 * Sets the language according to the tld or, when USEPREFIX is true, according to the first segment of the url
	 */
	private function _setLanguage() {
		// Find out language
		$languages = explode(',', AVAILABLELANG);
		$bright_lang = $languages[0];
		if(!USEPREFIX) {
			if(USETLD) {
				$bright_tlda = explode('.', $_SERVER['HTTP_HOST']);
				$bright_tld = array_pop($bright_tlda);
				$bright_preferred = $bright_tld;
				// TLD Base language
				switch($bright_tld) {
					case 'uk':
					case 'com':
						$bright_preferred = 'en';
						break;
					case 'at':
						$bright_preferred = 'de';
						break;
				}
				// Check if the preferred language is available, otherwise, fallback to default language
				if(strpos(AVAILABLELANG, $bright_preferred) !== false) {
					$_SESSION['language'] = $bright_preferred;
				} else {
					$_SESSION['language'] = $bright_lang;
				}
			} else {
				if(!isset($_SESSION['language'])) {
					
					$_SESSION['language'] = $bright_lang;
				}
			}
			
		} else {
			if(isset($_COOKIE['language'])) {
				$_SESSION['language'] = $_COOKIE['language'];
			}
			if(!isset($_SESSION['language'])) {
				if(USEHEADER === true && isset($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
					
					$x = explode(',', $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
					foreach ($x as $val) {
						#check for q-value and create associative array. No q-value means 1 by rule
						if(preg_match("/(.*);q=([0-1]{0,1}\.\d{0,4})/i",$val,$matches)) {
							$lang[$matches[1]] = (float)$matches[2];
						} else {
							$lang[$val] = 1.0;
						}
					}
					#return default language (highest q-value)
					$qval = 0.0;
					$deflang = $bright_lang;
					foreach ($lang as $key => $value) {
						if(in_array($key, $languages)) {
							if ($value > $qval) {
								$qval = (float)$value;
								$deflang = $key;
							}
						}
					}
					$_SESSION['language'] = $deflang;
				}
				if(!isset($_SESSION['language']))
					$_SESSION['language'] = $bright_lang;

				setcookie('language', $_SESSION['language'], strtotime('+1 year'), '/');
			}
		}
	}
	
}