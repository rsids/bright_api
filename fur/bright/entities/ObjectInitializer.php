<?php
namespace fur\bright\entities;
/**
 * This files defines all objects before the session is<br/>
 * started, so we can put every object in the session if we<br/>
 * want. 
 * @package Bright
 * @subpackage objects
 */

require_once('Bright/services/objects/OAdministratorObject.php');
// require_once('Bright/objects/ArrayCollection.php');

class ObjectInitializer {
	//Stub class for amfphp
}
if(!isset($_SESSION))
	session_start();