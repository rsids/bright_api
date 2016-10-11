<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 11-10-16
 * Time: 21:12
 */

namespace fur\bright\core;

/**
 * @author Ids Klijnsma - Fur
 * @version 1.15
 */
abstract class Constants {

    const CACHE_MODE_FILE = 1;
    const CACHE_MODE_APC = 2;

    protected $DB_HOST;
    protected $DB_USER;
    protected $DB_PASSWORD;
    protected $DB_DATABASE;

    protected $USEFTPFORFOLDERS = false;
    protected $FTPSERVER;
    protected $FTPUSER;
    protected $FTPPASS;
    protected $FTPBASEPATH;

    protected $LIVESERVER = true;

    protected $BASEURL;
    protected $BASEPATH = '';

    protected $MAILINGFROM;
    protected $MAILINGBOUNCE;
    protected $APPROVALMAIL;

    protected $SYSMAIL;

    protected $SMTP;
    protected $SMTPPORT = 25;

    protected $TRANSPORT;

    protected $HEADERBAR;

    protected $ADDITIONALMODULES;

    protected $GOOGLEMAPSAPIKEY = 'Fmjtd%7Cluua2qutnu%2C7n%3Do5-hzyg0';

    protected $UPLOADFOLDER = 'files/';
    protected $CMSFOLDER = 'bright/cms/';

    protected $SITENAME;
    protected $AVAILABLELANG;
    protected $USENEWTEMPLATE = 1;
    protected $LOGO;

    protected $LOGINPAGE;
    protected $ACTIVATIONPAGE;
    protected $DEACTIVATIONPAGE;
    protected $ADDITIONALOVERVIEWFIELDS;

    protected $SHOWTEMPLATEERRORS = false;
    protected $DISPLAYERRORS = true;

    protected $RTE_IMGURLWRAP = '';
    protected $RTE_IMGTAGWRAP = '';

    protected $LOCALIZABLE;
    protected $PHPTHUMBERRORIMAGE;

    /**
     * When true, the new 'action' directory is used for registration & twitter
     * @since 1.3
     * @var boolean
     */
    protected $USEACTIONDIR = false;

    /**
     * When true, all paths have the language prefixed (/nl/home/; /en/home/), when false, it uses the TLD to determine the language (domain.nl / domain.de)
     * @var boolean
     * @since 1.2
     */
    protected $USEPREFIX = true;

    /**
     * When true, the tld is used to determine the language. When false, you have to manage it yourself
     * @var boolean
     * @since 1.10
     */
    protected $USETLD = true;



    /**
     * When true, the header of the browser is used to determine the language. When false, you have to manage it yourself
     * @var boolean
     * @since 1.10
     */
    protected $USEHEADER = true;

    /**
     * Used by phpThumb, when image is not found.
     * @var string
     * @since 1.5
     */
    protected $ERROR_IMG = '';

    /**
     * Defines whether or not to generate a sitemap
     * @var boolean
     * @since 1.8
     *
     */
    protected $GENERATESITEMAP = true;

    /**
     * Used by phpThumb & upload script
     * @var string
     * @since 1.5
     */
    protected $IMAGE_MODES = array();

    /**
     * Used by phpThumb & upload script
     * @var string
     * @since 1.5
     */
    protected $IMAGEMAGICK_PATH = null;

    /**
     * @var array An array of custom constants
     * @since 1.7
     */
    protected $CUSTOM = array();


    /**
     * @var string The map type to use, accepted values: gmaps, osm
     * @since 1.9
     */
    protected $MAPTYPE = 'gmaps';

    /**
     * @var boolean When true, all SQL queries are benchmarked
     * @since 1.10
     */
    protected $BENCHMARK = false;


    /**
     * @var boolean When true, mails are not send
     * @since 1.12
     */
    protected $DISABLEMAIL = false;

    /**
     *
     * @var boolean When true, deprecation messages are shown (only when LIVESERVER is FALSE)
     * @since 1.14
     */
    protected $SHOWDEPRECATION = true;

    /**
     * @since 1.16
     * @var bool Some servers don't allow shell_exec, but PHP Thumb doesn't check correctly. Set this to false
     * when your server does't allow shell_exec
     */
    protected $SAFE_EXEC_ALLOWED = true;

    protected $CACHEPREFIX = 'bright';

    function __construct() {
        // Do not change from here

        $pathParts = explode(DIRECTORY_SEPARATOR, __DIR__);
        $path = array_splice($pathParts, 0, count($pathParts)-4);
        $this -> BASEPATH = implode(DIRECTORY_SEPARATOR, $path);
        $this -> _fixSlashes(array('UPLOADFOLDER', 'CMSFOLDER', 'BASEURL', 'BASEPATH'));

        if(defined('UPLOADFOLDER'))
            return;

        define('GATEWAY', $this -> BASEURL . 'bright/library/Amfphp/');

        define('UPLOADFOLDER', $this -> UPLOADFOLDER);
        define('CMSFOLDER', $this -> CMSFOLDER);

        define('SITENAME', $this -> SITENAME);
        define('AVAILABLELANG', $this -> AVAILABLELANG);
        define('USENEWTEMPLATE', $this -> USENEWTEMPLATE);
        define('LOGO', $this -> LOGO);

        define('ACTIVATIONPAGE', $this -> ACTIVATIONPAGE);
        define('DEACTIVATIONPAGE', $this -> DEACTIVATIONPAGE);
        define('ADDITIONALOVERVIEWFIELDS', $this -> ADDITIONALOVERVIEWFIELDS);
        define('LOGINPAGE', $this -> LOGINPAGE);

        define('DB_HOST', $this -> DB_HOST);
        define('DB_USER', $this -> DB_USER);
        define('DB_PASSWORD', $this -> DB_PASSWORD);
        define('DB_DATABASE', $this -> DB_DATABASE);

        define('LIVESERVER', $this -> LIVESERVER);

        define('BASEURL', $this -> BASEURL);
        define('BASEPATH', $this -> BASEPATH);

        define('MAILINGFROM', $this -> MAILINGFROM);
        define('MAILINGBOUNCE', $this -> MAILINGBOUNCE);
        define('APPROVALMAIL', $this -> APPROVALMAIL);

        define('SYSMAIL', $this -> SYSMAIL);

        define('SMTP', $this -> SMTP);
        define('SMTPPORT', $this -> SMTPPORT);

        define('TRANSPORT', $this -> TRANSPORT);
        define('GOOGLEMAPSAPIKEY', $this -> GOOGLEMAPSAPIKEY);
        define('HEADERBAR', $this -> HEADERBAR);
        define('ADDITIONALMODULES', $this -> ADDITIONALMODULES);
        define('USEPREFIX', $this -> USEPREFIX);
        define('USEHEADER', $this -> USEHEADER);
        define('USEACTIONDIR', $this -> USEACTIONDIR);
        define('LOCALIZABLE', $this -> LOCALIZABLE);

        define('RTE_IMGURLWRAP', $this -> RTE_IMGURLWRAP);
        define('RTE_IMGTAGWRAP', $this -> RTE_IMGTAGWRAP);

        define('SHOWTEMPLATEERRORS', $this -> SHOWTEMPLATEERRORS);

        define('ERROR_IMG', $this -> ERROR_IMG);
        define('IMAGEMAGICK_PATH', $this -> IMAGEMAGICK_PATH);
        define('IMAGE_MODES', serialize($this -> IMAGE_MODES));

        define('USEFTPFORFOLDERS', $this -> USEFTPFORFOLDERS);
        define('FTPSERVER', $this -> FTPSERVER);
        define('FTPUSER', $this -> FTPUSER);
        define('FTPPASS', $this -> FTPPASS);
        define('FTPBASEPATH', $this -> FTPBASEPATH);
        define('GENERATESITEMAP', $this -> GENERATESITEMAP);
        define('MAPTYPE', $this -> MAPTYPE);
        define('BENCHMARK', $this -> BENCHMARK);
        define('USETLD', $this -> USETLD);
        define('DISABLEMAIL', $this -> DISABLEMAIL);

        define('PHPTHUMBERRORIMAGE', $this -> PHPTHUMBERRORIMAGE);
        define('SHOWDEPRECATION', $this -> SHOWDEPRECATION);

        define('DISPLAYERRORS', $this -> DISPLAYERRORS);


        define('CACHE_MODE',  self::CACHE_MODE_FILE);

        define('CACHEPREFIX', $this -> CACHEPREFIX);
        define('SAFE_EXEC_ALLOWED', $this -> SAFE_EXEC_ALLOWED);

        foreach($this -> CUSTOM as $key => $value) {
            define($key, $value);
        }
    }

    /**
     * Adds trailing slashes if not present
     * @since 1.2
     * @param array $vars
     */
    private function _fixSlashes($vars) {
        foreach($vars as $var) {
            if(substr($this -> {$var}, -1) != '/') {
                $this -> {$var} .= '/';
            }
        }
    }
}