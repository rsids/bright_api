<?php
namespace  fur\bright\api\config;
/**
 * Use this file to get settings from the inifile
 * version history:
 * 2.6 - 20140616
 * - Added imageModes to filesettings
 * 2.5 - 20130606
 * - Added elementColumns
 * 2.4 - 20120704
 * - Added title fields to columns
 * - Added markerId to mapsColumns
 * 2.3 - 20120622
 * - Added new fields to mapsColumns
 * 2.2 - 20120418
 * - Maptype is now send with getCMSConfig
 * @version 2.2
 * @author fur
 * @package Bright
 * @subpackage config
 */
class Config {

	private $_perm;

	public static $mapsColumns = array('icon','color','markerId','pageId','title','layer', 'street','number','zip','city', 'lat','lng','label','publicationdate','expirationdate','modificationdate','alwayspublished');

	public static $userColumns = array('icon','userId','label', 'email', 'registrationdate','modificationdate','activated','deleted','usergroupsForDisplay');
	public static $pageColumns = array('coloredlabel','title','icon','pageId','label', 'usecount', 'modificationdate','publicationdate','expirationdate','alwayspublished','showinnavigation','creationdate','createdby','modifiedby');
	public static $elementColumns = array('icon','pageId','label', 'modificationdate','publicationdate','expirationdate','creationdate','createdby','modifiedby');
	public static $calendarColumns = array('coloredlabel','title','icon','calendarId','location','label', 'modificationdate','publicationdate','expirationdate','enabled','createdby','modifiedby');

	public function Config() {
//		$this -> _perm = new Permissions();
	}

	/**
	 * Gets all the required settings for Bright CMS
	 * @return \StdClass An object with the settings
	 */
	public function getCMSConfig() {
		$retObj = new \StdClass();
		$retObj -> filesettings = $this -> getFileSettings();
		$retObj -> filesettings -> filepath = '';
		$retObj -> general = (object)array('sitename' => SITENAME,
				'siteurl' => BASEURL,
				'cmsfolder' => CMSFOLDER);
		if(defined('GOOGLEMAPSAPIKEY'))
			$retObj -> general -> googlemapsapikey = GOOGLEMAPSAPIKEY;
		if(defined('MAPTYPE'))
			$retObj -> general -> maptype = MAPTYPE;
		if(defined('HEADERBAR'))
			$retObj -> general -> headerbar = HEADERBAR;
		if(defined('ADDITIONALMODULES'))
			$retObj -> general -> additionalmodules = explode(',', ADDITIONALMODULES);

		$retObj -> general -> logo = LOGO;
		$retObj -> general -> useprefix = (USEPREFIX);
		$retObj -> general -> languages = explode(',', AVAILABLELANG);

		$retObj -> columns = (object) array('maps' => Config::$mapsColumns, 'page' => Config::$pageColumns, 'user' => Config::$userColumns, 'calendar' => Config::$calendarColumns, 'element' => Config::$elementColumns);

		if(defined('ADDITIONALOVERVIEWFIELDS') && ADDITIONALOVERVIEWFIELDS != null) {
			$retObj -> general -> overviewfields = explode(',', ADDITIONALOVERVIEWFIELDS);
		}
		return $retObj;
	}

	/**
	 * Gets the fields in the localization array
	 * @return array an array of fields
	 */
//	public function getLocalizableFields() {
//
//		if(!defined('LOCALIZABLE') || LOCALIZABLE == null)
//			return null;
//
//		$str = str_replace(' ', '', LOCALIZABLE);
//
//
//		$arr = explode(',', $str);
//
//		$languages = explode(',', AVAILABLELANG);
//
//		$return = new \StdClass();
//		foreach($languages as $lang) {
//			$return -> {$lang} = array();
//			foreach($arr as $field) {
//				$return -> {$lang}[] = (object) array('field' => $field, 'value' =>  Resources::getResource($field, $lang, true));
//			}
//		}
//		return $return;
//	}

//	public function setLocalizablefields($langs) {
//
//		$declarations = '';
//		foreach($langs as $lang => $fields) {
//			foreach($fields as $field) {
//				$declarations .= 'private static $' . strtoupper($lang) . '_' . $field -> field .' = \'' . addslashes($field -> value) . '\';' . "\r\n";
//			}
//		}
//
//		$file = file_get_contents(dirname(__FILE__) . '/Resources.php.txt');
//		$file = str_replace('###RESOURCES###', $declarations, $file);
//		file_put_contents(BASEPATH . 'bright/site/config/Resources.php', $file);
//	}

	public function getLogo() {
		return LOGO;
	}

	/**
	 * Gets all the required settings for the files and upload classes<br/>
	 * The object contains the following settings:<br/>
	 * <ul><li>fileurl: The url of the uploadfolder<li>
	 * <li>filepath: The path of the uploadfolder</li>
	 * <li>uploadpath: The url of the uploadscript</li>
	 * <li>uploadfolder: The foldername (relative to the documentroot) of the uploadfolder</li>
	 * @return \StdClass An object with the settings
	 */
	public function getFileSettings() {
		$retObj = new \StdClass();
		$retObj -> fileurl = BASEURL . UPLOADFOLDER;
		$retObj -> baseurl = BASEURL;
		$retObj -> filepath = BASEPATH . UPLOADFOLDER;
		$retObj -> uploadpath = BASEURL . CMSFOLDER . 'Upload.php';
		$retObj -> uploadfolder = UPLOADFOLDER;
		$retObj -> imageModes = array();
		$retObj -> sessionId = session_id();

		if(defined('IMAGE_MODES')) {
			$modes = unserialize(IMAGE_MODES);
				
			foreach($modes as $key => $mode) {
				if(isset($mode['desc'])) {
					$mode = (object) $mode;
					$mode -> name = $key;
					$retObj -> imageModes[] = $mode;
				}
			}
		}
//		Connection::getInstance() -> addTolog($retObj);
		return $retObj;
	}
}