<?php
/**
 *  This file is part of amfPHP
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file license.txt.
 * @package Amfphp
 */

/**
 *  includes
 *  */
require_once(__DIR__ . '/ClassLoader.php');
///bright/library/Bright/services/objects/ObjectInitializer.php
$servicesPath = __DIR__ . '/../src/api/';
include_once(__DIR__ . '/../src/entities/ObjectInitializer.php');
$cfg = new Amfphp_Core_Config();
$cfg->serviceFolderPaths[] = $servicesPath;
$cfg->serviceNames2ClassFindInfo = generateServiceNames($servicesPath);
$cfg->checkArgumentCount = true;

restore_error_handler();
restore_exception_handler();
// For AMF, it's better to just log the error
// if(!LIVESERVER) {
// 	ini_set('display_errors', '1'); // display errors in the HTML
// 	ini_set('track_errors', '1'); // creates php error variable
// 	error_reporting(E_ALL | E_STRICT);
// } else {
ini_set('display_errors', 0); // don't display errors in the HTML
ini_set('track_errors', 1); // creates php error variable
ini_set('log_errors', 1); // creates php error variable
error_reporting(E_ALL | E_STRICT);
// }
/* 
 * main entry point (gateway) for service calls. instanciates the gateway class and uses it to handle the call.
 * 
 * @package Amfphp
 * @author Ariel Sommeria-klein
 */

$gateway = Amfphp_Core_HttpRequestGatewayFactory::createGateway($cfg);

//use this to change the current folder to the services folder. Be careful of the case.
//This was done in 1.9 and can be used to support relative includes, and should be used when upgrading from 1.9 to 2.0 if you use relative includes
//chdir(dirname(__FILE__) . '/Services');

// chdir($servicesPath);
$gateway->service();
$gateway->output();


function generateServiceNames($path) {
    $allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    $phpFiles = new RegexIterator($allFiles, '/\.php$/');
    $serviceNames = array();
    foreach ($phpFiles as $phpFile) {

        $parts = explode(DIRECTORY_SEPARATOR, $phpFile);
        $className = array_slice($parts, -2);
        $className[count($className) -1] = substr($className[count($className) -1],0, -4);
        $serviceNames[join('.', $className)] = (object) array(
            'absolutePath' => $phpFile->__toString(),
            'className' => '\\fur\\bright\\api\\' . join('\\', $className)
        );
    }

    return $serviceNames;
}