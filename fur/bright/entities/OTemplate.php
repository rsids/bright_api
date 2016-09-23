<?php
namespace fur\bright\entities;
/**
 * This class defines the Template object, used by the template classes
 * @author bs10
 * @version 2.3
 * @package Bright
 * @subpackage objects
 */
class OTemplate {
	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OTemplate';
	
	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> id = (int) $this -> id;	
		$this -> visible = ((int)$this -> visible == 1);	
		$this -> templatetype = (int)$this -> templatetype;	
		$this -> parser = (int)$this -> parser;	
		$this -> priority = (float) $this -> priority;	
		$this -> maxchildren = (double) $this -> maxchildren;
	}
	
	/**
	 * @var int The id of the Template
	 */
	public $id = 0;
	/**
	 * @var string The name of the template
	 */
	public $itemtype = '';
	/**
	 * @var string The userfriendly name of the template
	 * @since 2.1 - 18 mrt 2010
	 */
	public $templatename = '';

	/**
	 * @var boolean Indicates whether the template is visible, and thus can be created in Bright CMS
	 */
	public $visible;
	/**
	 * @var string Path to the icon file
	 */
	public $icon = '';
	/**
	 * @var string Indicates how long a page of this type may be cached on the server (eg. 0 hours)
	 */
	public $lifetime = '0 hours';
	/**
	 * @var double Priority of the template, used in sitemap(.xml) generation. Valid values: Anything between 0 and 1
	 */
	public $priority = .5;
	/**
	 * @var double The maximum number of children, where -1 is unlimited
	 */
	public $maxchildren = -1;
	/**
	 * @var array And array of template specific fields
	 */
	public $fields = array();
	
	/**
	 * @var int Specifies the kind of template (0 = page, 1 = mail template, 2 = subpart / listitem)
	 * @since 2.2 - 15 jun 2010
	 */
	public $templatetype = 0;
	/**
	 * @var int Specifies the parser to use (0 = default / page, 1 = calendar)
	 * @since 2.3 - 7 dec 2010
	 */
	public $parser = 0;
}