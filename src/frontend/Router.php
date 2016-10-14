<?php
namespace fur\bright\frontend;
use fur\bright\api\calendar\Calendar;
use fur\bright\api\maps\Maps;
use fur\bright\api\tree\Tree;
use fur\bright\api\user\User;
use fur\bright\entities\OTreeNode;

/**
 * @package Frontend
 */
class Router {
	
	public static $PAGE_PARSER = 1;
	public static $CALENDAR_PARSER = 2;
	public static $USER_PARSER = 3;
	public static $MARKER_PARSER = 4;
	public static $ELEMENT_PARSER = 5;


    /**
     * @param array $treeNodes
     * @return OTreeNode|int
     * @throws \Exception
     */
    public function getView($treeNodes) {
		$bright = new Bright();
		$tree = new Tree();
		$cal = new Calendar();
		$maps = new Maps();
		$user = new User();
		$root = $bright -> getRoot();
		$numTreeNodes = count($treeNodes);
		$groups = array();
		
		if ($numTreeNodes > 0) {
			
			$child = $root;//new OTreeNode();
			for ($i = 0; $i < $numTreeNodes; $i++) {
					
				// Check if an alternative parser is required
				if($child && isset($child -> parser) && (int)$child -> parser > 1) {
					$child -> parser = (int)$child -> parser;
					switch($child -> parser) {
						case Router::$CALENDAR_PARSER:
							// Must be last item
							if($i < $numTreeNodes -1)
								return 404;
								
							$event = $cal -> getEventByLabel($treeNodes[$i]);
							if(!$event)
								return 404;
							
							$c = new OTreeNode();
							$c -> treeId = $child -> treeId; 
							$c -> page = $event;
							$c -> path = join('/', $treeNodes);
							return $c;
							break;
						case Router::$MARKER_PARSER:
							// Must be last item
							if($i < $numTreeNodes -1)
								return 404;
								
							$marker = $maps ->getMarkerByLabel($treeNodes[$i]);
							if(!$marker)
								return 404;
							
							$result = new OTreeNode();
							$result -> parentId = $child -> treeId;
							$result -> page = $marker;
							$result -> path = join('/', $treeNodes);
							return $result;
							break;
						case Router::$USER_PARSER:
							$userPage = $user -> getUserByLabel($treeNodes[$i]);
							if(!$userPage)
								return 404;
							$child = new OTreeNode();
							$child -> page = $userPage;
							$child -> path = join('/', $treeNodes);
							return $child;
							break;
					}
				} else {
					$child = $tree -> getChildByLabel($child -> treeId, $treeNodes[$i]);
				}
				
				if(!$child)
					return 404;
				
				if($child -> loginrequired) {
					$groups = array_merge($groups, $child -> requiredgroups);
				}
					
			}
			// Check if we're member of the required groups
			$hasAccess = true;
			if(count($groups) > 0) {
				$authenticatedUser = $user -> getAuthUser();
				if($authenticatedUser) {
					$missing = array_diff($groups, $authenticatedUser -> usergroups);
					
					if(count($missing) > 0) {
						//insufficient rights
						$hasAccess = false;
					} 
				} else {
					$hasAccess = false;
				}
				
			}
			
			if($hasAccess === false) {
				// Redirect to login
				$path = BASEURL;
				$path .= (USEPREFIX) ? $_SESSION['prefix'] :'';
				$path .= LOGINPAGE;
				// Include treeId, so we can redirect back when login successful
				header('Location:' . $path . '?tid=' . $child -> treeId);
				exit();
			}
			// Build path (no need to get it from the db, we just checked it, it exists :D)
			$child = $bright -> getChild($child -> treeId);
			$child -> path = join('/', $treeNodes);
			return $child;
			
		} 
		//ROOT
		return $root;
	}
	
	
	public function getTreeNodes($path) {
		if(strpos($path, BASEURL) === false) {
			$path = BASEURL . $path;
			$path = str_replace('//', '/', $path);	
			$path = str_replace(':/', '://', $path);	
		}
		$_SESSION['prefix'] = '';
		if(USEPREFIX) {
			$lang = substr($path, strlen(BASEURL));
			$urlSegments = explode('/', $lang);
			
			$availableLanguages = AVAILABLELANG;
			$availableLanguages = explode(',', $availableLanguages);
			if(strlen($urlSegments[0]) > 0) {
				if(in_array($urlSegments[0], $availableLanguages, true)) {
					$_SESSION['language'] = $urlSegments[0];
					setcookie('language', $_SESSION['language'], strtotime('+1 year'), '/');
					$_SESSION['prefix'] = $_SESSION['language'] . '/';

				} else if(isset($_GET['lang']) && in_array($_GET['lang'], $availableLanguages, true)) {

					$_SESSION['language'] = $_GET['lang'];
					setcookie('language', $_SESSION['language'], strtotime('+1 year'), '/');
				} else {

					$_SESSION['language'] = $availableLanguages[0];
					setcookie('language', $_SESSION['language'], strtotime('+1 year'), '/');
					return array('404');
				}
			} else if(!isset($_SESSION['language'])) {
				$_SESSION['language'] = $availableLanguages[0];
				setcookie('language', $_SESSION['language'], strtotime('+1 year'), '/');
				$_SESSION['prefix'] = $_SESSION['language'] . '/';
			}
		} 
		
		$prefix = BASEURL . $_SESSION['prefix'];
		//Remove Prefix
		if(strpos($path, $prefix) == 0) {
			$path = substr($path, strlen($prefix));
		}
		
		// als het laatste karakter van de url een / is
		if (substr($path, (strlen($path)-1), strlen($path)) == '/') {
			// haal die er dan vanaf
			$path = substr($path, 0, (strlen($path)-1));
		}
		// als het eerste karakter van de url een / is
		if (substr($path, 0, 1) == '/') {
			// haal die er dan vanaf
			$path = substr($path, 1, strlen($path));
		}
		$aTreenodes = explode('/', $path);
		if (strlen($path) == 0) 
			array_shift($aTreenodes);
		
		return $aTreenodes; 
	}
}