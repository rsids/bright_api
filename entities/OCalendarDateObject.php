<?php
namespace fur\bright\entities;

/**
 * This class defines the CalendarDateObject
 * Version history:
 * 1.1 - 20120710:
 *  - Added noend
 * 1.0 
 *  - Initial version
 * @author Fur - Ids Klijnsma
 * @version 1.1
 * @package Bright
 * @subpackage objects
 */
class OCalendarDateObject {
	
	function __construct() {
		$this -> calendarId = (int) $this -> calendarId;
	}
	
	
	public function __toString() {
		return (String) $this -> calendarId;
	}
	
	/**
	 * @var int The unique identifier
	 */
	public $calendarId = 0;
	
	/**
	 * @var int The start date (timestamp)
	 */
	public $starttime;
	
	/**
	 * @var int The end date (timestamp)
	 */
	public $endtime;
	
	/**
	 * @var string The explicit Remoting type
	 */
	public $_explicitType = 'OCalendarDateObject';
	/**
	 * @var boolean Indicates whether the event has a start & end time, or that the event lasts all day
	 */
	public $allday = false;
	/**
	 * @var boolean Indicates whether the event has a start & end time, or no specific end time
	 * @since 1.1 - 20120710
	 */
	public $noend = false;
	
}