<?php 
/**
 * This file must be included by your index.php, if you want Bright to handle realURL and if you want to be redirected
 * to the correct view.
 * @author Ids Klijnsma - Fur
 */
if(!isset($_SESSION)) {
    session_start();
}

// Only start when this is a fresh request
if(!defined('BOOTSTRAPINIT')) {
	define('BOOTSTRAPINIT', 1);
	$bootstrap = new \fur\bright\frontend\Serve();
	$bootstrap -> init();
}