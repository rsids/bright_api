<?php
namespace fur\bright\entities;
/**
 * This class defines the Folder object, used by the files classes
 * @author bs10
 * @version 2.0
 * @package Bright
 * @subpackage objects
 */
class OFolder {
	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OFolder';
	
	/**
	 * @var string The name of the folder
	 */
	public $label = '';
	/**
	 * @var string The (relative) path to the folder
	 */
	public $path = '';
	/**
	 * @var string The number of subfolders
	 */
	public $numChildren = -1;
	
	public $children;
	
}