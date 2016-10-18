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


/* 
 * main entry point (gateway) for service calls. instanciates the gateway class and uses it to handle the call.
 * 
 * @package Amfphp
 * @author Ariel Sommeria-klein
 */

$gateway = Amfphp_Core_HttpRequestGatewayFactory::createGateway($cfg);
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