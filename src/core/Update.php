<?php
namespace fur\bright\core;
use fur\bright\api\calendar\Calendar;
use fur\bright\api\element\Element;
use fur\bright\api\page\Page;
use fur\bright\entities\OCalendarDateObject;
use fur\bright\Permissions;
use fur\bright\utils\BrightUtils;

/**
 * Handles the creating, updating and returning of calendar events.<br/>
 * A calendar event is a special type of page
 * @author Fur
 * @version 1.0
 * @package Bright
 * @subpackage db
 */
class Update extends Permissions  {

	function __construct() {
		parent::__construct();

		$this -> _conn = Connection::getInstance();
	}

	private $_conn;

	/**
	 * Checks if database updates are needed
	 * @param string $version The version string from the Frontend
	 */
	public function check($version) {
		$permissions = $this -> getPermissions();
		$this -> updatePermissions(array('IS_AUTH', 'MANAGE_ADMIN', 'MANAGE_USER', 'CREATE_PAGE', 'DELETE_PAGE', 'EDIT_PAGE', 'MOVE_PAGE', 'DELETE_FILE', 'MANAGE_TEMPLATE', 'MANAGE_SETTINGS', 'UPLOAD_FILE', 'MANAGE_MAILINGS', 'MANAGE_CALENDARS', 'MANAGE_ELEMENTS', 'MANAGE_MAPS'));
		$varr = explode(' ', $version);
		$build = (int)array_pop($varr);

		if(file_exists(BASEPATH . 'bright/site/hooks/UpdateHook.php')) {
			require_once(BASEPATH . 'bright/site/hooks/UpdateHook.php');
			$ch = new \UpdateHook();
			if(method_exists($ch, 'update')) {
				$ch -> update($build);
            }
		}

        $prevbuild = $build-1;
        $this -> _conn ->updateRow("UPDATE `update` SET `build`=$prevbuild WHERE `build`=99999");

		$prevbuild = (int) $this -> _conn -> getField('SELECT MAX(`build`) FROM `update`');
		if($prevbuild >= $build ) {
			return;
        }

		$sqla[] = 'CREATE TABLE IF NOT EXISTS `treeaccess` (
				  `treeId` int(11) NOT NULL,
				  `groupId` int(11) NOT NULL,
				  KEY `treeId` (`treeId`,`groupId`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;';

		$sqla[] = 'CREATE TABLE IF NOT EXISTS `mailqueue` (
					  `id` int(11) NOT NULL AUTO_INCREMENT,
					  `pageId` int(11) NOT NULL,
					  `groups` varchar(255) CHARACTER SET utf8 NOT NULL,
					  `dateadded` datetime NOT NULL,
					  `issend` tinyint(4) NOT NULL DEFAULT \'0\',
					  PRIMARY KEY (`id`)
					) ENGINE=MyISAM  DEFAULT CHARSET=utf8;';

		$sqla[] = 'CREATE TABLE IF NOT EXISTS `parsers` (
					`parserId` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`label` VARCHAR( 255 ) NOT NULL ,
					UNIQUE (`label`)
					) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;';

		
		$colcheck = "SHOW COLUMNS FROM `user` WHERE `field`='deleted'";
		$field = $this -> _conn -> getRow($colcheck);
		if(strpos($field -> Type, 'tinyint') !== false) {
			$sqla[] = "ALTER TABLE  `user` CHANGE  `deleted`  `deleted` TINYINT( 1 ) NULL DEFAULT  '0'";
			$sqla[] = "UPDATE `user` SET `deleted`= null WHERE `deleted`=0";
			$sqla[] = "ALTER TABLE  `user` CHANGE  `deleted`  `deleted` VARCHAR( 50 ) NULL DEFAULT  NULL";
			$sqla[] = "UPDATE `user` SET `deleted`= NOW() WHERE `deleted`='1'";
			$sqla[] = "ALTER TABLE  `user` CHANGE  `deleted`  `deleted` DATETIME NULL DEFAULT NULL";
			$sqla[] = "ALTER TABLE  `user` ADD UNIQUE (`email` ,`deleted`)";
		}

		$colcheck = "SHOW COLUMNS FROM `userfields` WHERE `field`='lang'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {
			$sqla[] = "ALTER TABLE  `userfields` ADD  `lang` VARCHAR( 3 ) NOT NULL DEFAULT  'tpl' AFTER  `userId`";
			$sqla[] = "ALTER TABLE  `userfields` ADD  `index` TINYINT( 1 ) NOT NULL DEFAULT  '1' AFTER  `value`";
		}
        $colcheck = "SHOW COLUMNS FROM `page` WHERE `field`='alwayspublished'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {
			$sqla[] = "ALTER TABLE  `page` CHANGE  `allwayspublished`  `alwayspublished` TINYINT( 1 ) NOT NULL ;";
			$sqla[] = "UPDATE administrators SET settings = REPLACE(settings, 'allwayspublished', 'alwayspublished') WHERE settings LIKE '%allwayspublished%';";
		}

		$colcheck = "SHOW COLUMNS FROM `content` WHERE `field`='deleted'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {

			$sqla[] = "ALTER TABLE  `content` ADD UNIQUE (`pageId` ,`lang` ,`field` ,`index`);";
			$sqla[] = "ALTER TABLE  `userfields` ADD UNIQUE (`userId` ,`lang` ,`field` ,`index`);";
			$sqla[] = "ALTER TABLE  `content` ADD  `deleted` TINYINT( 1 ) NOT NULL DEFAULT  '0'";
		}

		$sqla[] = "CREATE TABLE IF NOT EXISTS `calendarnew` (
					  `calendarId` int(11) NOT NULL AUTO_INCREMENT,
					  `itemType` int(11) NOT NULL,
					  `label` varchar(255) NOT NULL,
					  `recur` varchar(255) DEFAULT NULL,
					  `until` datetime DEFAULT NULL,
					  `deleted` datetime DEFAULT NULL,
					  `creationdate` timestamp NULL DEFAULT NULL,
					  `modificationdate` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					  `createdby` int(11) DEFAULT NULL,
					  `modifiedby` int(11) DEFAULT NULL,
					  PRIMARY KEY (`calendarId`)
					) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";

		$sqla[] = "CREATE TABLE IF NOT EXISTS `calendardates` (
				  `dateId` int(11) NOT NULL AUTO_INCREMENT,
				  `calendarId` int(11) NOT NULL,
				  `starttime` TIMESTAMP NULL DEFAULT NULL,
				  `endtime` TIMESTAMP NULL DEFAULT NULL,
				  `allday` tinyint(1) NOT NULL DEFAULT '0',
				  `deleted` tinyint(1) NOT NULL DEFAULT '0',
				  PRIMARY KEY (`dateId`),
				  UNIQUE KEY `calendarId` (`calendarId`,`starttime`,`endtime`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8";


		$sqla[] = "CREATE TABLE IF NOT EXISTS `calendarcontent` (
				  `contentId` int(11) NOT NULL AUTO_INCREMENT,
				  `calendarId` int(11) NOT NULL,
				  `lang` varchar(3) NOT NULL DEFAULT 'ALL',
				  `field` varchar(20) NOT NULL,
				  `value` longtext NOT NULL,
				  `index` int(11) NOT NULL DEFAULT '0',
				  `deleted` tinyint(1) NOT NULL DEFAULT '0',
				  `searchable` tinyint(1) NOT NULL DEFAULT '0',
				  PRIMARY KEY (`contentId`),
				  UNIQUE KEY `callangfield` (`calendarId`,`lang`,`field`, `index`),
				  KEY `lang` (`lang`,`field`),
				  FULLTEXT KEY `value` (`value`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

		$sqla[] = "CREATE TABLE IF NOT EXISTS `calendareventsnew` (
				  `eventId` int(11) NOT NULL AUTO_INCREMENT,
				  `calendarId` int(11) NOT NULL,
				  `starttime` TIMESTAMP NULL DEFAULT NULL,
				  `endtime` TIMESTAMP NULL DEFAULT NULL,
				  `deleted` tinyint(1) NOT NULL,
				  PRIMARY KEY (`eventId`),
 				  `allday` TINYINT( 1 ) NOT NULL DEFAULT  '0',
				  UNIQUE KEY `calendarId` (`calendarId`,`starttime`,`endtime`),
				  KEY `calendarId2` (`calendarId`),
				  KEY `starttime` (`starttime`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
		
		$this -> _performQueries($sqla);
		$tblcheck = "show tables like 'calendareventsnew'";
		if($this -> _conn -> getField($tblcheck)) {
			$colcheck = "SHOW COLUMNS FROM `calendareventsnew` WHERE `field`='allday'";
			$hasField = $this -> _conn -> getField($colcheck);

			if($hasField == null) {
				$sqla[] = "ALTER TABLE  `calendareventsnew` ADD  `allday` TINYINT( 1 ) NOT NULL DEFAULT  '0'";
				$sqla[] = "ALTER TABLE  `calendareventsnew` CHANGE  `starttime`  `starttime` TIMESTAMP NULL DEFAULT NULL ,CHANGE  `endtime`  `endtime` TIMESTAMP NULL DEFAULT NULL";
			}
			$colcheck = "SHOW COLUMNS FROM `calendareventsnew` WHERE `field`='noend'";
			$hasField = $this -> _conn -> getField($colcheck);

			if($hasField == null) {
				$sqla[] = "ALTER TABLE  `calendareventsnew` ADD  `noend` TINYINT( 1 ) NOT NULL DEFAULT  '0'";
			}
		}
		$tblcheck = "show tables like 'calendardates'";
		if($this -> _conn -> getField($tblcheck)) {
			$colcheck = "SHOW COLUMNS FROM `calendardates` WHERE `field`='noend'";
			$hasField = $this -> _conn -> getField($colcheck);

			if($hasField == null) {
				$sqla[] = "ALTER TABLE  `calendardates` ADD  `noend` TINYINT( 1 ) NOT NULL DEFAULT  '0'";
			}
		}

		$colcheck = "SHOW COLUMNS FROM `calendarnew` WHERE `field`='enabled'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {
			$sql = "ALTER TABLE  `calendarnew` ADD  `enabled` TINYINT( 1 ) NOT NULL DEFAULT  '1' AFTER  `until` , ADD INDEX (  `enabled` )";
			$this -> _conn -> updateRow($sql);

		}
		$colcheck = "SHOW COLUMNS FROM `calendarnew` WHERE `field`='locationId'";
		$hasField = $this -> _conn -> getField($colcheck);

		if($hasField == null) {
			$sqla[] = "ALTER TABLE  `calendarnew` ADD  `locationId` INT( 11 ) NULL DEFAULT NULL AFTER  `calendarId`, ADD INDEX (  `locationId` )";
		}
		
		$colcheck = "SHOW COLUMNS FROM `gm_markers` WHERE `field`='enabled'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {
			$sql = "ALTER TABLE  `gm_markers` ADD  `enabled` TINYINT( 1 ) NOT NULL DEFAULT  '1' AFTER  `deleted` , ADD INDEX (  `enabled` )";
			$this -> _conn -> updateRow($sql);
			$sql = "ALTER TABLE  `gm_polys` ADD  `enabled` TINYINT( 1 ) NOT NULL DEFAULT  '1' AFTER  `deleted` , ADD INDEX (  `enabled` )";
			$this -> _conn -> updateRow($sql);

		}
		
		$colcheck = "SHOW COLUMNS FROM `gm_markers` WHERE `field`='street'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {
			$sql = "ALTER TABLE  `gm_markers` ADD  `street` VARCHAR( 255 ) NULL DEFAULT NULL ,
						ADD  `number` VARCHAR( 255 ) NULL DEFAULT NULL ,
						ADD  `zip` VARCHAR( 255 ) NULL DEFAULT NULL ,
						ADD  `city` VARCHAR( 255 ) NULL DEFAULT NULL ,
						ADD  `country` INT( 11 ) NULL DEFAULT NULL";
			$this -> _conn -> updateRow($sql);

		}

		$colcheck = "SHOW COLUMNS FROM `gm_polys` WHERE `field`='search'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {
			$sql = "ALTER TABLE  `gm_polys` ADD  `search` LONGTEXT NULL , ADD FULLTEXT (`search`)";
			$this -> _conn -> updateRow($sql);

		}
		$colcheck = "SHOW COLUMNS FROM `gm_markers` WHERE `field`='search'";
		$hasField = $this -> _conn -> getField($colcheck);
		if($hasField == null) {
			$sqla[] = "ALTER TABLE  `gm_polys` CHANGE  `pageId`  `pageId` INT( 11 ) NULL DEFAULT NULL ,
CHANGE  `label`  `label` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL";
			$sql = "ALTER TABLE  `gm_markers` ADD  `search` LONGTEXT NULL , ADD FULLTEXT (`search`)";
			$this -> _conn -> updateRow($sql);
			$maps = new Maps();
			$lay = new Layers();
			$layers = $lay -> getLayers();
			$markers = $this -> _conn -> getRows("SELECT markerId, pageId FROM gm_markers");
			foreach($markers as $marker) {
				if($marker -> pageId) {
					$sql = "SELECT `value` FROM content WHERE pageId = {$marker -> pageId}";
					$rows = $this -> _conn -> getFields($sql);
					$search = implode("\r\n", $rows);
					$search = Connection::getInstance() -> escape_string($search);
					$sql = "UPDATE gm_markers SET `search`='$search' WHERE markerId={$marker -> markerId}";
					$this -> _conn -> updateRow($sql);
				}

			}
		}
		
		
		$colcheck = "SHOW COLUMNS FROM `page` WHERE `field`='creationdate'";
		if($this -> _conn -> getField($colcheck) == null) {
			$sqla[] = "ALTER TABLE  `page` ADD  `creationdate` TIMESTAMP NULL DEFAULT NULL ,
						ADD  `createdby` INT( 11 ) NULL DEFAULT NULL ,
						ADD  `modifiedby` INT( 11 ) NULL DEFAULT NULL";
		}
		
		$colcheck = "SHOW COLUMNS FROM `backup` WHERE `field`='content'";
		$c = $this -> _conn -> getRow($colcheck);
		if($c -> Type == 'text') {
			$sqla[] = "ALTER TABLE  `backup` CHANGE  `content`  `content` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL";
		}
		
		$tblcheck = "SHOW TABLES LIKE 'calendarindex'";
		if(!$this -> _conn -> getField($tblcheck)) {
			$this -> _conn -> insertRow("CREATE TABLE IF NOT EXISTS `calendarindex` (
										  `calendarId` int(11) NOT NULL DEFAULT '0',
										  `search` text,
										  PRIMARY KEY (`calendarId`),
										  FULLTEXT KEY `search` (`search`)
										) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
			$cal = new Calendar();
			$ids = $this -> _conn -> getFields("SELECT calendarId FROM calendarnew");
			$sqlc = "INSERT INTO calendarindex (calendarId, search) VALUES";
			$sqlca = array();
			foreach($ids as $id) {
				$ev = $cal -> getEvent($id);
				$search = BrightUtils::createSearchString($ev);
				if((int)$ev -> locationId > 0) {
					$search .= $this -> _conn -> getField("SELECT search FROM gm_markers WHERE pageId={$ev -> locationId}");
				}
				$search = Connection::getInstance() -> escape_string($search);
				$sqlca[] = "({$ev -> calendarId}, '$search')";
			}
			if(count($sqlca) > 0) {
				$sqlc .= implode(",\r\n", $sqlca);
				$sqla[] = $sqlc;
			}
			$sqla[] = "ALTER TABLE  `calendareventsnew` ADD INDEX (  `starttime` )";
		}
		
		$tblcheck = "SHOW TABLES LIKE 'pageindex'";
		if(!$this -> _conn -> getField($tblcheck)) {
			$this -> _conn -> insertRow("CREATE TABLE IF NOT EXISTS `pageindex` (
										  `pageId` int(11) NOT NULL DEFAULT '0',
										  `search` text,
										  PRIMARY KEY (`pageId`),
										  FULLTEXT KEY `search` (`search`)
										) ENGINE=MyISAM DEFAULT CHARSET=utf8;");
			$el = new Element();
			$page = new Page();
			$ids = $el -> getElements(false);
			
			$sqle = "INSERT INTO pageindex (pageId, search) VALUES";
			$sqlea = array();
			foreach($ids as $elm) {
				$ev = $page -> getPageById($elm -> pageId);
				$search = BrightUtils::createSearchString($ev);
				
				$search = Connection::getInstance() -> escape_string($search);
				$sqlea[] = "({$ev -> pageId}, '$search')";
			}
			if(count($sqlea) > 0) {
				$sqle .= implode(",\r\n", $sqlea);
				$sqla[] = $sqle;
			}
		}
		
		if($prevbuild < 7098) {
			// Update user settings, this fixes a bug with AmfPHP 2.x,
			// which does not correctly deserialize flex.messaging.io.objectproxy to php stdClass objects
			$rows = Connection::getInstance() -> getRows("SELECT id, settings FROM `administrators`");
			foreach($rows as $row) {
				$settings = json_decode($row -> settings);
				if($settings) {
					if(isset($settings -> _externalizedData)) {
						$settings = $settings -> _externalizedData;
					}
					// Clean up settings object
					foreach($settings as $key => $value) {
						if(strpos($key, 'pageDivider_') === 0) {
							unset($settings -> $key);
						}
					}
					$settings = Connection::getInstance() -> escape_string(json_encode($settings));
					$sql = "UPDATE administrators SET settings='$settings' WHERE id={$row -> id}";
					Connection::getInstance() -> updateRow($sql);
				}
			}
		}

		// Update to latest version
		$sqla[] = 'TRUNCATE `update`';
		$sqla[] = 'INSERT INTO `update` (`build`) VALUES (' . $build . ')';

		$this -> _performQueries($sqla);

		$sql = "SHOW TABLES LIKE 'calendar'";
		$rows = $this -> _conn -> getRow($sql);
		if($rows) {
			$sql = 'SELECT * FROM calendar';
			$rows = $this -> _conn -> getRows($sql);
			if($rows) {
				$page = new Page();
				$cal = new Calendar();
				$ids = array();
				foreach($rows as $row) {
					$ids[] = $row -> pageId;
					$ev = $page -> getPageById($row -> pageId);
	
	
					$cdo = new OCalendarDateObject();
					$cdo -> starttime = $ev -> publicationdate;
					$cdo -> endtime = $ev -> expirationdate;
					$cdo -> allday = date('d-m-Y', $cdo -> starttime) != date('d-m-Y', $cdo -> endtime)  || $row -> allday;
					if(date('H',$cdo ->starttime) == 22) {
						$cdo -> starttime += 7200;
						$cdo -> endtime += 7200;
						$cdo -> allday = 1;
					}
					if(date('H',$cdo ->starttime) == 23) {
						$cdo -> starttime += 3600;
						$cdo -> endtime += 3600;
						$cdo -> allday = 1;
					}
					if(date('H',$cdo ->endtime) == 22) {
						$cdo -> starttime += 7200;
						$cdo -> endtime += 7200;
						$cdo -> allday = 1;
					}
					if(date('H',$cdo ->endtime) == 23) {
						$cdo -> starttime += 3600;
						$cdo -> endtime += 3600;
						$cdo -> allday = 1;
					}
	
					$cestring = serialize($ev);
					$cestring = str_replace('O:5:"OPage"','O:14:"OCalendarEvent"', $cestring);
					$cestring = str_replace('s:13:"_explicitType";s:5:"OPage"','s:13:"_explicitType";s:14:"OCalendarEvent"', $cestring);
					$ev = unserialize($cestring);
	
					$ev -> dates = array($cdo);
	
					$cal -> setEvent($ev);
				}
				$page -> deletePages($ids);
				$sql = 'DELETE FROM calendar';
				$rows = $this -> _conn -> deleteRow($sql);
				$sql = 'DELETE FROM calendarevents';
				$rows = $this -> _conn -> deleteRow($sql);
			}
		}
		
		$this -> updatePermissions($permissions);
	}
	
	private function _performQueries(&$sqla) {
		while(count($sqla) > 0) {
			$this -> _conn -> insertRow(array_shift($sqla));	
		}
	}
}