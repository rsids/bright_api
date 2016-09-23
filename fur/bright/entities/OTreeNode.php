<?php
namespace fur\bright\entities;
/**
 * This class defines the TreeNode object, used by the tree classes
 * Version history:
 * 3.0 - 20120416
 * - Added OBaseObject
 * @author fur
 * @version 3.0
 * @package Bright
 * @subpackage objects
 */
class OTreeNode extends OBaseObject {
	public $_explicitType = 'OTreeNode';

	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> init();
	}

	/**
	 * Call this method to convert the types of the vars in this class to the correct types expected by flex
	 */
	public function init() {
		$this -> treeId = (double) $this -> treeId;
		$this -> parentId = (double) $this -> parentId;
		$this -> index = (double) $this -> index;
		$this -> numChildren = (double) $this -> numChildren;
		$this -> shortcut = (double) $this -> shortcut;
		$this -> locked = ((int)$this -> locked == 1);
		$this -> loginrequired = ((int)$this -> loginrequired == 1);
	}

	/**
	 * @var int The id of the node
	 */
	public $treeId = -1;

	/**
	 * @var int The id of the parentnode
	 */
	public $parentId = -1;

	/**
	 * @var int The index of the node
	 */
	public $index = -1;

	/**
	 * @var int The number of children
	 */
	public $numChildren = -1;

	/**
	 * @var OPage The pageobject of the Treenode
	 */
	public $page = null;

	/**
	 * @var string The path (url) of the node
	 */
	public $path = '';

	/**
	 * @var boolean Indicates whether the node is locked and has modification restrictions in the cms
	 */
	public $locked = false;

	/**
	 * @var int The id of the node where the shortcut points to (0 when the node is not a shortcut)
	 */
	public $shortcut = 0;

	/**
	 * @var boolean Indicated whether the administrator must be authenticated to view this node
	 */
	public $loginrequired;

	/**
	 * @var array Array of groupIds needed to view this page (AND)
	 * @since 2.1 - 13 jul 2010
	 */
	 public $requiredgroups;
	 
	 public $children;
	 
	 public $pageId;
}
