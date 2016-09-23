<?php
namespace fur\bright\entities;
/**
 * This class defines the CalendarEvent object
 * Version history:
 * 2.2 20120709
 * - Added locationId
 * 2.1
 * - Added enabled
 * 2.0
 * - New Calendar events, decoupled from pages
 * 1.0
 *  - Old calendar events
 * @author Fur - Ids Klijnsma
 * @version 2.1
 * @package Bright
 * @subpackage objects
 */
class OCalendarEvent extends OPage {

	function __construct() {
		// Strong type vars...
		// Any normal programming language calls the constructor before filling vars....
		// ... except PHP :-)
		$this -> until = (double) $this -> until;
		$this -> enabled = $this -> enabled == 1;
		$this -> pageId =
		$this -> calendarId = (int) $this -> calendarId;
		$this -> locationId = (int) $this -> locationId;
		parent::__construct();
	}
	
	public function __toString() {
		return (String) $this -> calendarId;
	}

	/**
	 * @var int The unique identifier
	 */
	public $calendarId = 0;

	/**
	 * @var int The id of the location (optional)
	 */
	public $locationId = 0;

	/**
	 * @var int The creationdate (timestamp)
	 */
	public $creationdate;

	/**
	 * @var int The modificationdate (timestamp)
	 */
	public $modificationdate;

	/**
	 * @var int The administrator who last modified it
	 */
	public $modifiedby;

	/**
	 * @var int The administrator who created it
	 */
	public $createdby;

	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OCalendarEvent';

	/**
	 * @var String A string defining the recurrence rules of the event
	 */
	public $recur = '';

	/**
	 * @var boolean Indicates whether the event has a start & end time, or that the event lasts all day
	 */
	public $allday = false;

	/**
	 * @var double Timestamp
	 */
	public $until = 0;

	/**
	 * @var array Holds the dates set by the user
	 */
	public $dates;

	/**
	 * @var array Holds the dates set by the user or set by a recurring rule
	 */
	public $rawdates;

	/**
	 * @var boolean Enabled
	 */
	public $enabled = true;
}