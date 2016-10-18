<?php
namespace fur\bright\api\calendar;

use fur\bright\api\cache\Cache;
use fur\bright\api\config\Config;
use fur\bright\api\content\Content;
use fur\bright\api\page\Page;
use fur\bright\core\Connection;
use fur\bright\entities\OCalendarDateObject;
use fur\bright\entities\OCalendarEvent;
use fur\bright\entities\OPage;
use fur\bright\exceptions\AuthenticationException;
use fur\bright\exceptions\EventException;
use fur\bright\exceptions\ParameterException;
use fur\bright\utils\BrightUtils;

/**
 * Handles the creating, updating and returning of calendar events.<br/>
 * A calendar event is a special type of page
 * Version history:
 * 2.6 20120103
 * - getEventsByRange has an additional parameter filter
 * - getEventsByRange now uses cache
 * 2.5 20120802
 * - getEvent now uses cache
 * 2.4 20120724
 * - Added enabled to getEventsByRange
 * 2.3 20120710
 * - Implemented noend
 * 2.2 20120704
 * - Added generateLabel for calendar
 * 2.1 20120503
 * - Added $includecustomfields to getEvents
 * @author Fur - Ids Klijnsma
 * @version 2.6
 * @package Bright
 * @subpackage calendar
 */
class Calendar extends Content
{

    const ASC = 1;
    const DESC = 0;

    private $_conn;
    private $_page;


    function __construct()
    {
        parent::__construct();

        $this->_conn = Connection::getInstance();
        $this->_page = new Page();
    }

    /**
     * @see Content -> generateLabel
     * @param string $title
     * @param int $id
     * @return mixed|string
     */
    public function generateLabel($title, $id = 0, $table = 'page')
    {
        return parent::generateLabel($title, $id, 'calendarnew');
    }

    /**
     * Gets all the events
     * @since xx 20120807 This method can now be called from BE only!
     * @param int $date The timestamp of the start date, the month of this date is used (e.g. 24-12-2012 results in 01-12-2012). When -1, the current timestamp is used
     * @param int $toDate
     * @param bool $includeCustomFields When true, custom fields, defined by the currently logged in administrator, are included
     * @param null $filter
     * @return array
     * @throws \Exception
     */
    public function getEvents($date = -1, $toDate = -1, $includeCustomFields = false, $filter = null)
    {
        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        if ($date == -1)
            $date = time();

        $date = mktime(0, 0, 0, date('n', $date), date('d', $date), date('Y', $date));
        if ($toDate == -1) {
            $toDate = strtotime('+1 month', $date);
        } else {
            $toDate = mktime(0, 0, 0, date('n', $toDate), date('d', $toDate), date('Y', $toDate));
        }
        $sqlDate = date(BrightUtils::$SQLDateTime, $date);
        $sqlEndDate = date(BrightUtils::$SQLDateTime, $toDate);

        if ($includeCustomFields) {
            $settings = $this->getSettings();
            if ($settings) {
                if ($settings !== null && isset($settings->calendar) && isset($settings->calendar->visibleColumns)) {
                    foreach ($settings->calendar->visibleColumns as $col) {
                        if (!in_array($col, Config::$calendarColumns)) {
                            $additionalFields[] = $col;
                        }
                    }
                }
            }
        }

        $fieldSql = '';
        $joins = array();
        if (count($additionalFields) != 0) {
            $fields = array();
            foreach ($additionalFields as $field) {
                $fields[] = ' COALESCE(co' . Connection::getInstance()->escape_string($field) . '.value, \'\') as `' . Connection::getInstance()->escape_string($field) . '` ';
                $joins[] = 'LEFT JOIN calendarcontent co' . Connection::getInstance()->escape_string($field) . ' ON cn.calendarId = co' . Connection::getInstance()->escape_string($field) . '.calendarId AND co' . Connection::getInstance()->escape_string($field) . '.`lang`=\'nl\' AND co' . Connection::getInstance()->escape_string($field) . '.`field`=\'' . Connection::getInstance()->escape_string($field) . '\' ';
            }
            $fieldSql .= ', ' . join(', ', $fields);
        }

        if ($filter) {
            $filter = Connection::getInstance()->escape_string($filter);
            $joins[] = "INNER JOIN calendarindex ci ON ci.calendarId = cn.calendarId AND MATCH(`ci`.`search`) AGAINST('$filter') ";
        }
        $sql = "SELECT cn.*, gmm.label as `location`, cd.dateId, ce.eventId, cd.allday, cd.noend,
				UNIX_TIMESTAMP(cn.modificationdate) as `modificationdate`,
				UNIX_TIMESTAMP(cn.creationdate) as `creationdate`,
				UNIX_TIMESTAMP(cd.starttime) as `starttime`,
				UNIX_TIMESTAMP(cd.endtime) as `endtime`,
				UNIX_TIMESTAMP(ce.starttime) as `rawstarttime`,
				UNIX_TIMESTAMP(ce.endtime) as `rawendtime`,
				ce.allday as `rawallday`,
				ce.noend as `rawnoend`,
				UNIX_TIMESTAMP(cn.until) as `until` $fieldSql
				FROM `calendarnew` cn
				LEFT JOIN `gm_markers` gmm ON cn.locationId = gmm.pageId 
				LEFT JOIN `calendardates` cd ON cn.calendarId = cd.calendarId
				LEFT JOIN `calendareventsnew` ce ON cn.calendarId = ce.calendarId AND ce.starttime >= '$sqlDate' AND ce.starttime < '$sqlEndDate'";

        $sql .= join("\r\n", $joins) . "\r\n";

        $rows = $this->_conn->getRows($sql, 'OCalendarEvent');
        $result = array();
        $now = $date;
        foreach ($rows as $row) {
            if ($row->calendarId) {
                if (!array_key_exists($row->calendarId, $result)) {
                    $result[$row->calendarId] = $row;
                    $result[$row->calendarId]->diff = 999999999999;
                    $result[$row->calendarId]->dates = array();

                    // Just set it for the list in Bright
                    $result[$row->calendarId]->publicationdate = (double)$row->starttime;
                    $result[$row->calendarId]->expirationdate = (double)$row->endtime;
                }
                if (!array_key_exists($row->dateId, $result[$row->calendarId]->dates)) {

                    $ocd = new OCalendarDateObject();
                    $ocd->starttime = (double)$row->starttime;
                    $ocd->endtime = (double)$row->endtime;
                    $ocd->allday = ($row->allday == 1);
                    $ocd->noend = ($row->noend == 1);
                    $ocd->calendarId = $row->calendarId;
                    $result[$row->calendarId]->dates[$row->dateId] = $ocd;
                }

                $ocd = new OCalendarDateObject();
                $ocd->starttime = (double)$row->rawstarttime;
                $ocd->endtime = (double)$row->rawendtime;
                $ocd->calendarId = $row->calendarId;
                $ocd->allday = ($row->rawallday == 1);
                $ocd->noend = ($row->rawnoend == 1);
                if ($ocd->starttime > $now) {
                    // Try to find the nearest occurence of the event;
                    if ($ocd->starttime - $now < $result[$row->calendarId]->diff) {
                        $result[$row->calendarId]->diff = $ocd->starttime - $now;
                        $result[$row->calendarId]->publicationdate = $ocd->starttime;
                        $result[$row->calendarId]->expirationdate = $ocd->endtime;
                    }

                }

                $result[$row->calendarId]->rawdates[$row->eventId] = $ocd;


                unset($result[$row->calendarId]->deleted);
                unset($result[$row->calendarId]->starttime);
                unset($result[$row->calendarId]->endtime);
                unset($result[$row->calendarId]->rawstarttime);
                unset($result[$row->calendarId]->rawendtime);
                unset($result[$row->calendarId]->rawallday);
                unset($result[$row->calendarId]->dateId);
                unset($result[$row->calendarId]->eventId);
                unset($result[$row->calendarId]->allday);
                unset($result[$row->calendarId]->noend);
                unset($result[$row->calendarId]->rawnoend);
            }
        }
        $res = array();
        foreach ($result as $item) {
            $item->rawdates = BrightUtils::cleanArray($item->rawdates);
            $item->dates = BrightUtils::cleanArray($item->dates);
            $res[] = $item;
        }
        return $res;
    }


    /**
     * Gets events by a given range, event -> starttime is the nearest starttime of the event compared to $start
     * @param int $start A unix Timestamp of the startdate, if -1, then the current time is used, default -1
     * @param int $end A unix Timestamp of the enddate, default -1
     * @param int $limit The maximum number of events, default 0
     * @param int $offset The number of events to skip before outputting (used icw limit), default 0
     * @param boolean $countResults Indicates whether the returned object should include the total number of results (to create a pager), default false
     * @param int $order Sort order, default Calendar::ASC
     * @param boolean $enabledOnly When true, only enabled events are returned, default false
     * @param array $filter Array containing include, exclude and/or location, which are arrays of template id's to in or exclude. For location, specify the pageId(s) of the marker (not the markerId)
     *
     * @throws \Exception
     *
     * @return mixed When $countresults is true, an object {numresults:n, events:[]} is returned, otherwise array
     */
    public function getEventsByRange($start = -1, $end = -1, $limit = 0, $offset = 0, $countResults = false, $order = Calendar::ASC, $enabledOnly = false, $filter = null)
    {
        if (!is_numeric($order)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$order');
        if (!is_numeric($start)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$start');
        if (!is_numeric($end)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$end');
        if (!is_numeric($limit)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$limit');
        if (!is_numeric($offset)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$offset');

        $c = new Cache();
        $argstring = md5(json_encode(func_get_args()));
        $result = $c->getCache("calendar_getEventsByRange_$argstring");
        if ($result)
            return $result;
        $excludes = array();
        $includes = array();
        $locations = array();
        if ($filter !== null && is_array($filter)) {
            if (isset($filter['include'])) {
                if (!is_array($filter['include']))
                    $filter['include'] = array($filter['include']);

                foreach ($filter['include'] as $id) {
                    $includes[] = (int)$id;
                }
            }
            if (isset($filter['exclude'])) {
                if (!is_array($filter['exclude']))
                    $filter['exclude'] = array($filter['exclude']);

                foreach ($filter['exclude'] as $id) {
                    $excludes[] = (int)$id;
                }
            }
            if (isset($filter['location'])) {
                if (!is_array($filter['location']))
                    $filter['location'] = array($filter['location']);

                foreach ($filter['location'] as $id) {
                    $locations[] = (int)$id;
                }
            }
        }

        if ($start < 0)
            $start = time();

        $endsql = '';
        if ($end != -1) {
            if ($end < $start)
                $end = strtotime('+1 month', $start);

            $sqlenddate = date(BrightUtils::$SQLDateTime, $end);
            $endsql = "AND ce.starttime < '$sqlenddate'";
        }

        $osql = $order == Calendar::ASC ? 'ASC' : 'DESC';

        $sqldate = date(BrightUtils::$SQLDateTime, $start);
        $limitsql = '';
        if ($limit > 0)
            $limitsql = "LIMIT $offset, $limit";

        $enabledsql = ($enabledOnly) ? 'WHERE cn.enabled=1 ' : '';
        $includesql = '';
        if (count($includes) > 0) {
            $tpls = implode(',', $includes);
            $includesql .= "AND cn.itemType IN ($tpls) ";
        }
        if (count($excludes) > 0) {
            $tpls = implode(',', $excludes);
            $includesql .= "AND cn.itemType NOT IN ($tpls) ";
        }
        if (count($locations) > 0) {
            $tpls = implode(',', $locations);
            $includesql .= "AND cn.locationId IN ($tpls) ";
        }
        $sql = "SELECT DISTINCT SQL_CALC_FOUND_ROWS cn.calendarId, ce.eventId, ce.starttime, ce.endtime, ce.allday, ce.noend
			FROM `calendarnew` cn
			INNER JOIN `calendardates` cd ON cn.calendarId = cd.calendarId
			INNER JOIN `calendareventsnew` ce ON cn.calendarId = ce.calendarId
			AND ce.endtime >= '$sqldate' $endsql
			$enabledsql 
			$includesql
			ORDER BY ce.starttime $osql $limitsql";

        $ids = $this->_conn->getRows($sql);

        if ($countResults) {
            $numResults = $this->_conn->getField('SELECT FOUND_ROWS()');
        }
        $eventshm = array();
        $sorted = array();

        // Loop over results, remove double items
        foreach ($ids as $id) {

            // double items check by array key
            if (!array_key_exists($id->calendarId, $eventshm)) {
                // Not existing, get it
                $eventshm[$id->calendarId] = $this->getEvent($id->calendarId);
            }

            // Find correct starttime / endtime
            $event = clone $eventshm[$id->calendarId];

            foreach ($event->rawdates as $date) {
                if ($date->eventId == $id->eventId) {
                    $event->starttime = $date->starttime;
                    $event->endtime = $date->endtime;
                    $sorted[] = $event;
                }
            }
        }
        $result = $sorted;
        if ($countResults) {
            $result = (object)array('numresults' => $numResults, 'events' => $sorted);
        }
        if (isset($filter['singleevents']))
            return $eventshm;

        $c->setCache($result, "calendar_getEventsByRange_$argstring", strtotime('+1 year'));
        return $result;
    }

    /**
     * Gets all the events for a period of time
     * @deprecated use getEventsByRange
     * @param int $start A unix Timestamp of the startdate, if -1, then the current time is used
     * @param int $end A unix Timestamp of the enddate
     * @param int $limit The maximum number of events
     * @param int $offset The number of events to skip before outputting (used icw limit)
     * @param bool $countResults Indicates whether the returnobject should include the total number of results (to create a pager)
     * @param bool $reverse Reverses the result set, used to get the last n results of a set. Note: the resultset will NOT be reversed
     * @param array $additionalFilters An array of additional filters, each filter is an array containing a field and a value, these filters are checked against the calendarcontent table
     * @param bool $enabledOnly When true, only events which are 'enabled' are returned
     * @throws \Exception
     * @return null|\stdClass
     */
    public function getEventsByDateRange($start = -1, $end = -1, $limit = 0, $offset = 0, $countResults = false, $reverse = false, $additionalFilters = null, $enabledOnly = true)
    {
        trigger_error('This method is deprecated, use getEventsByRange', E_USER_DEPRECATED);
        if (!is_numeric($start)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$start');
        if (!is_numeric($end)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$end');
        if (!is_numeric($limit)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$limit');
        if (!is_numeric($offset)) throw $this->throwException(ParameterException::INTEGER_EXCEPTION, '$offset');

        $limitsql = '';
        if ($limit > 0)
            $limitsql = 'LIMIT ' . (int)$offset . ', ' . (int)$limit;
        if ($start == -1)
            $start = time();

        $start = date(BrightUtils::$SQLDateTime, $start);

        $filters = '';
        if ($additionalFilters) {
            $fa = array();
            $i = 0;

            $lng = isset($_SESSION['language']) ? $_SESSION['language'] : null;
            if (!$lng) {
                $al = explode(',', AVAILABLELANG);
                $lng = $al[0];
            }
            foreach ($additionalFilters as $filter) {
                $cn = '`cc' . $i++ . '`';
                BrightUtils::escape($filter, array('field', 'value', 'searchtype'));
                $st = '=';
                switch ($filter['searchtype']) {
                    case 'LIKE':
                    case 'NOT':
                        $st = $filter['searchtype'];
                        break;
                    case 'EQUALS':
                        $st = '=';
                        break;
                    default:
                        throw $this->throwException(ParameterException::ARRAY_EXCEPTION);
                }

                $fa[] = "INNER JOIN calendarcontent $cn ON $cn.calendarId=ce.calendarId AND $cn.lang='$lng' AND $cn.field='{$filter['field']}' AND $cn.value $st '{$filter['value']}'";
            }
            if (count($fa) > 0) {
                $filters = implode(",\r\n", $fa);
            }
        }

        $enabledsql = ($enabledOnly) ? 'enabled=1 AND' : '';
        $sql = "SELECT DISTINCT cn.calendarId
				FROM `calendareventsnew` ce
				RIGHT JOIN `calendarnew` cn ON cn.calendarId = ce.calendarId
				$filters
				WHERE $enabledsql `starttime` >= '$start' ";

        $endsql = "OR `endtime` >= '$start' ";
        if ($end > -1) {
            $end = date(BrightUtils::$SQLDateTime, $end);
            $endsql = "AND (`endtime` <= '$end' AND `starttime` <= '$end')";
        }
        $sql .= $endsql;

        $sql2 = $sql;


        // Get ALL event which started after $start
        $sql = "SELECT SQL_CALC_FOUND_ROWS ce.*,
				(SELECT COUNT(cep.calendarId) FROM `calendareventsnew` cep WHERE ce.eventId <> cep.eventId AND cep.`starttime` < ce.starttime) AS `prevevents`,
				(SELECT COUNT(cen.calendarId) FROM `calendareventsnew` cen WHERE ce.eventId <> cen.eventId AND cen.`starttime` >= ce.starttime) AS `nextevents`,
				c.calendarId, c.recur
				FROM `calendareventsnew` ce
				RIGHT JOIN `calendarnew` c ON c.calendarId = ce.calendarId
				WHERE $enabledsql `ce`.calendarId IN ({$sql2}) AND `starttime` >= '$start' ";

        // Get ALL event which started before $end
        $sql .= $endsql;
        $sql .= 'ORDER BY `starttime` ';
        $sql .= ($reverse) ? 'DESC ' : 'ASC ';
        $sql .= $limitsql;

        $events = $this->_conn->getRows($sql);
        if ($countResults) {
            $numresults = $this->_conn->getField('SELECT FOUND_ROWS()');
        }
        // Get events
        $pids = array_unique(BrightUtils::array_getObjectValues($events, 'calendarId'));
        if (!$pids)
            return null;

        $realevents = $this->getEventsByIds($pids, true);
        // Convert the pids to array indexes
        $indexed = array();
        foreach ($realevents as $event) {
            $indexed[$event->calendarId] = $event;
        }


        // Add the content to the event
        foreach ($events as $event) {
            $event->event = clone $indexed[$event->calendarId];
        }
        if ($countResults) {
            $ret = new \stdClass();
            $ret->numresults = $numresults;
            $ret->events = $events;
            return $ret;
        }
        return $events;
    }

    /**
     * Gets the events sorted by their id, with an optional filter
     * Required permissions:
     * <ul>
     *        <li>IS_AUTH</li>
     * </ul>
     * @param int $start
     * @param int $limit
     * @param null $filter
     * @param string $orderfield
     * @param string $order
     * @throws \Exception
     * @return mixed|object
     */
    public function getEventsByIdRange($start = 0, $limit = 20, $filter = null, $orderfield = 'calendarId', $order = 'DESC')
    {

        if (!$this->IS_AUTH)
            throw $this->throwException(AuthenticationException::NO_USER_AUTH);

        $c = new Cache();
        $argstring = md5(json_encode(func_get_args()));
//		$result = $c -> getCache("eventsByIdRange_$argstring");
//		if($result)
//			return $result;

        if ($orderfield == null || $orderfield == 'undefined')
            $orderfield = 'calendarId';

        if ($order != 'DESC' && $order != 'ASC')
            $order = 'DESC';


        switch ($orderfield) {
            case 'publicationdate':
                $orderfield = 'cee.starttime';
                break;
            case 'expirationdate':
                $orderfield = 'cee.endtime';
                break;
            default:
                if (is_numeric($orderfield) || $orderfield == 'coloredlabel')
                    $orderfield = 'calendarId';
        }

        $start = (int)$start;
        $limit = (int)$limit;
        if ($limit == 0)
            $limit = 20;

        $result = $this->_getEventsByIdRangeAlt($start, $limit, $filter, $orderfield, $order);
//		$c -> setCache($result, "eventsByIdRange_$argstring", strtotime('+1 year'));
        return $result;
    }


    /**
     * Gets an array of events based on their ID
     * @param array $ids An array of id's
     * @param bool $includeContent includeContent Whether or not the content of the event should be included
     * @throws \Exception
     * @return array an array of OCalendarEvents
     */
    public function getEventsByIds($ids, $includeContent = false)
    {
        if (!is_array($ids))
            throw $this->throwException(ParameterException::ARRAY_EXCEPTION, 'ids');

        foreach ($ids as $id) {
            if (!is_numeric($id))
                throw $this->throwException(ParameterException::INTEGER_EXCEPTION, 'calendarId');
        }

        $sql = 'SELECT cn.calendarId,
				cn.locationId,
				cn.itemType,
				cn.label,
				UNIX_TIMESTAMP(ce.starttime) as `starttime`,
				UNIX_TIMESTAMP(ce.endtime) as `endtime`,
				ce.allday,
				ce.noend,
				cn.recur,
				UNIX_TIMESTAMP(cn.until) as `until`,
				it.lifetime as `lifetime`,
				it.label as `itemLabel`,
				UNIX_TIMESTAMP(cn.modificationdate) as `modificationdate`,
				UNIX_TIMESTAMP(cn.creationdate) as `creationdate`
				FROM calendarnew cn
				INNER JOIN calendareventsnew ce ON cn.calendarId = ce.calendarId
				INNER JOIN itemtypes it ON cn.itemType = it.itemId
				WHERE cn.calendarId IN (' . join(',', $ids) . ')';

        $sql .= ' ORDER BY cn.modificationdate DESC';

        $rows = $this->conn->getRows($sql, 'OCalendarEvent');
        $arr = array();
        foreach ($rows as $row) {
            if (!array_key_exists($row->calendarId, $arr)) {
                $row->dates = array();
                $arr[$row->calendarId] = $row;
            }
            $doa = new OCalendarDateObject();
            $doa->starttime = $row->starttime;
            $doa->endtime = $row->endtime;
            $doa->allday = $row->allday;
            $doa->noend = $row->noend;
            $doa->calendarId = $row->calendarId;
            $arr[$row->calendarId]->dates[] = $doa;
        }
        $arr = BrightUtils::cleanArray($arr);

        if (!$includeContent)
            return $arr;

        foreach ($arr as &$event) {
            $event = $this->getContent($event, false, 'calendarcontent');
        }
        return $arr;
    }

    /**
     * Gets a page of type 'event'
     * @param $calendarId
     * @throws \Exception
     * @internal param \calendarId $int The id of the page / event
     * @return OCalendarEvent A CalendarEvent
     */
    public function getEvent($calendarId)
    {
        if (!is_numeric($calendarId))
            throw $this->throwException(ParameterException::INTEGER_EXCEPTION);

        $c = new Cache();
        $event = $c->getCache("calendaritem_$calendarId");
        if (!$event) {
            $sql = "SELECT cn.*, cd.allday, cd.dateId, ce.eventId, cd.noend,
					UNIX_TIMESTAMP(cd.starttime) as `starttime`,
					UNIX_TIMESTAMP(cd.endtime) as `endtime`,
					UNIX_TIMESTAMP(cn.until) as `until`,
					UNIX_TIMESTAMP(ce.starttime) as `rawstarttime`,
					UNIX_TIMESTAMP(ce.endtime) as `rawendtime`,
					ce.allday as `rawallday`,
					ce.noend as `rawnoend`,
					UNIX_TIMESTAMP(cn.creationdate) as `creationdate`,
					UNIX_TIMESTAMP(cn.modificationdate) as `modificationdate`,
					it.lifetime as `lifetime`,
					it.label as `itemLabel`,
					cd.allday
					FROM `calendarnew` cn
					INNER JOIN itemtypes it ON cn.itemType = it.itemId
					LEFT JOIN `calendardates` cd ON cn.calendarId = cd.calendarId
					LEFT JOIN `calendareventsnew` ce ON cn.calendarId = ce.calendarId
					WHERE cn.calendarId=$calendarId";

            $event = $this->_getEvent($sql);
            // @todo: Check template lifetime
            $c->setCache($event, "calendaritem_$calendarId", strtotime('+1 year'));
        }
        return $event;

    }

    /**
     * Gets an event by it's label
     * @param $label
     * @throws \Exception
     * @internal param \label $string The label of the event
     * @return event The event
     */
    public function getEventByLabel($label)
    {
        if (!is_string($label))
            throw $this->throwException(ParameterException::STRING_EXCEPTION);

        $label = BrightUtils::escapeSingle($label);

        $sql = "SELECT cn.*, cd.allday, cd.dateId, ce.eventId, cd.noend,
				UNIX_TIMESTAMP(cd.starttime) as `starttime`,
				UNIX_TIMESTAMP(cd.endtime) as `endtime`,
				UNIX_TIMESTAMP(cn.until) as `until`,
				UNIX_TIMESTAMP(ce.starttime) as `rawstarttime`,
				UNIX_TIMESTAMP(ce.endtime) as `rawendtime`,
				ce.allday as `rawallday`,
				ce.noend as `rawnoend`,
				UNIX_TIMESTAMP(cn.creationdate) as `creationdate`,
				UNIX_TIMESTAMP(cn.modificationdate) as `modificationdate`,
				it.lifetime as `lifetime`,
				it.label as `itemLabel`,
				cd.allday
				FROM `calendarnew` cn
				INNER JOIN itemtypes it ON cn.itemType = it.itemId
				LEFT JOIN `calendardates` cd ON cn.calendarId = cd.calendarId
				LEFT JOIN `calendareventsnew` ce ON cn.calendarId = ce.calendarId
				WHERE cn.label='$label'";

        return $this->_getEvent($sql);
    }

    /**
     * Creates or updates a calendar event
     * @param OCalendarEvent $event event The event to create or update
     * @param boolean $executeHook
     * @param boolean $updateContent When false, oContent is left untouched
     * @throws \Exception
     * @return bool
     */
    public function setEvent(OCalendarEvent $event, $executeHook = true, $updateContent = true)
    {
        $ch = null;

        if (!isset($event->dates) || count($event->dates) == 0) {
            throw $this->throwException(EventException::NOT_ENOUGH_DATES);
        }

        $c = new Cache();
        $c->deleteCacheByLabel("calendaritem_{$event -> calendarId}");
        $c->deleteCacheByPrefix("eventsByIdRange_");
        $c->deleteCacheByPrefix('calendar_getEventsByRange');

        // Execute hook if present
        if (class_exists('CalendarHook') && $executeHook) {
            $ch = new \CalendarHook();
            if (method_exists($ch, 'preSetEvent'))
                $event = $ch->preSetEvent($event);
        }


        // Set modification values
        $aid = isset($_SESSION['administratorId']) ? $_SESSION['administratorId'] : 0;
        $event->createdby = $aid;
        $event->modifiedby = $aid;
        $event->label = $this->generateLabel($event->label, $event->calendarId);
        BrightUtils::forceInt($event, array('calendarId', 'itemType', 'createdby', 'locationId', 'modifiedby'));
        BrightUtils::escape($event, array('recur', 'label'));
        $event->enabled = ($event->enabled === 1 || $event->enabled === true || $event->enabled === 'true') ? 1 : 0;
        $until = (double)$event->until;
        $event->until = date(BrightUtils::$SQLDateTime, $until);
        $sql = "INSERT INTO calendarnew (`calendarId`, `locationId`, `itemType`, `label`, `recur`, `until`, `enabled`, `deleted`, `creationdate`, `modificationdate`, `createdby`, `modifiedby`) VALUES (
				{$event -> calendarId},
				{$event -> locationId},
				{$event -> itemType},
				'{$event -> label}',
				'{$event -> recur}',
				'{$event -> until}',
				{$event -> enabled},
				NULL, NOW(), NOW(),
				{$event -> createdby},
				{$event -> modifiedby})
				ON DUPLICATE KEY UPDATE
				itemType = VALUES(`itemType`),
				locationId = VALUES(`locationId`),
				label = VALUES(`label`),
				recur = VALUES(`recur`),
				enabled = VALUES(`enabled`),
				until = VALUES(`until`),
				modificationdate = NOW(),
				modifiedby = VALUES(`modifiedby`),
				`calendarId`=LAST_INSERT_ID(`calendarId`)";
        $event->calendarId = $this->_conn->insertRow($sql);

        $sql = 'UPDATE `calendardates` SET `deleted`=1 WHERE `calendarId`=' . $event->calendarId;
        $this->_conn->updateRow($sql);

        $sql = 'INSERT INTO `calendardates` (`calendarId`,`starttime`, `endtime`,`allday`,`deleted`,`noend`) VALUES ';
        $sqla = array();
        // Store also for calendarevents
        $evdates = array();
        foreach ($event->dates as &$date) {
            $date = (object)$date;
            if ($date->endtime < $date->starttime) {
                // Quick fix, just add 1 day to the endtime
                // We could (or should) throw an exception here, since it's not a valid range...
                $date->endtime = strtotime('+1 day', $date->endtime);
            }

            $starttime = date(BrightUtils::$SQLDateTime, $date->starttime);
            $endtime = date(BrightUtils::$SQLDateTime, $date->endtime);

            $date->allday = ($date->allday == true) ? 1 : 0;
            $date->noend = ($date->noend == true) ? 1 : 0;
            $sqla[] = "({$event -> calendarId}, '{$starttime}','{$endtime}', {$date -> allday},0, {$date -> noend})";
            $evdates[] = "({$event -> calendarId}, '{$starttime}','{$endtime}', {$date -> allday}, 0, {$date -> noend})";
        }

        $sql .= implode(",\r\n", $sqla);
        $sql .= ' ON DUPLICATE KEY UPDATE `allday`=VALUES(`allday`),  `noend`=VALUES(`noend`), `deleted`=0';
        $this->_conn->insertRow($sql);

        $sql = "DELETE FROM calendardates WHERE deleted=1";

        $this->_conn->insertRow($sql);

        // Delete stored dates
// 		$sql = 'DELETE FROM calendarevents WHERE eventId=' . (int) $event -> calendarId;
// 		$this -> _conn -> deleteRow($sql);

        $sql = 'UPDATE calendareventsnew SET `deleted`=1 WHERE `calendarId`=' . (int)$event->calendarId;
        $this->_conn->updateRow($sql);
        $sql = 'INSERT INTO `calendareventsnew` (`calendarId`, `starttime`, `endtime`, `allday`, `deleted`,`noend`) VALUES ';
        $sqla = array();

        $earr = array();
        if ($event->recur && $event->recur != '') {
            $recur = $event->recur;
            // Recurring event, process it and add if needed
            $recarr = explode(';', $recur);
            $freq = '';
            $interval = 0;
            // If recur has trailing ;, pop last item
            if ($recarr[count($recarr) - 1] == '')
                array_pop($recarr);

            foreach ($recarr as $recitem) {
                $recitemarr = explode('=', $recitem);
                $key = $recitemarr[0];
                $val = $recitemarr[1];
                $dayarr = null;
                $monthrepeat = 'dom';

                switch ($key) {
                    case 'FREQ':
                        // Frequency, valid values are: DAILY, WEEKLY, MONTHLY, YEARLY
                        $freq = $val;
                        break;
                    case 'INTERVAL':
                        $interval = (int)$val;
                        break;
                    case 'BYDAY':
                        // Difference between monthly and weekly

                        switch ($freq) {
                            case 'WEEKLY':
                                $days = explode(',', $val);
                                $dayarr = array();
                                // Find out which days are checked
                                foreach ($days as $day) {
                                    switch ($day) {
                                        case 'SU':
                                            $dayarr[0] = 1;
                                            break;
                                        case 'MO':
                                            $dayarr[1] = 1;
                                            break;
                                        case 'TU':
                                            $dayarr[2] = 1;
                                            break;
                                        case 'WE':
                                            $dayarr[3] = 1;
                                            break;
                                        case 'TH':
                                            $dayarr[4] = 1;
                                            break;
                                        case 'FR':
                                            $dayarr[5] = 1;
                                            break;
                                        case 'SA':
                                            $dayarr[6] = 1;
                                            break;
                                    }
                                }
                                break;

                            case 'MONTHLY':
                                $monthrepeat = 'dow';
                                break;
                        }
                        break;

                    case 'BYMONTHDAY':
                        // Difference between monthly and yearly
                        /**
                         * @todo implement Is more implementation really needed, or is this switch just useless an could it be
                         * replaced with an if statement.
                         */
                        switch ($freq) {
                            case 'MONTHLY':
                                $monthrepeat = 'dom';
                                break;
                        }

                        break;
                }
            }


            // Add dates, if event recurs, calculate all dates
            $sqla[] = $evdates[0];

            $evstart = $event->dates[0]->starttime;
            $evend = $event->dates[0]->endtime;
            $startenddiff = $evend - $evstart;
            switch ($freq) {
                case 'DAILY':
                    // Easy!

                    while ($evstart < $until) {
                        $evstart += (86400 * $interval);
                        $evend += (86400 * $interval);
                        $ev = new \stdClass();
                        $ev->starttime = $evstart;
                        $ev->endtime = $evend;
                        $ev->calendarId = $event->calendarId;
                        $earr[] = $ev;
                    }
                    break;

                case 'WEEKLY':
                    // Get timestamp of the first day of the week
                    $fdow = date('w', $evstart);
                    $edow = date('w', $evend);
                    $startweek = mktime(date('H', $evstart),
                        date('i', $evstart),
                        date('s', $evstart),
                        date('n', $evstart),
                        date('j', $evstart) - $fdow,
                        date('Y', $evstart));
                    $ddow = $edow - $fdow;
                    if ($ddow < 0)
                        $ddow += 7;
                    $first = true;
                    while ($startweek < $until) {
                        // On the first week, skip the sunday, because,
                        // if the sunday is checked, it is already added before
                        $dow = ($first) ? $fdow + 1 : 0;
                        $first = false;
                        while ($dow < 7) {
                            if (array_key_exists($dow, $dayarr)) {
                                $ev = new \stdClass();
                                $ev->starttime = mktime(date('H', $evstart),
                                    date('i', $evstart),
                                    date('s', $evstart),
                                    date('n', $startweek),
                                    date('j', $startweek) + $dow,
                                    date('Y', $startweek));
// 								$edow = $dow + $ddow;
// 								if($edow < 0)
// 									$edow +=7;
                                $ev->endtime = mktime(date('H', $evend),
                                    date('i', $evend),
                                    date('s', $evend),
                                    date('n', $startweek),
                                    date('j', $startweek) + $dow + $ddow,
                                    date('Y', $startweek));
                                $ev->calendarId = $event->calendarId;
                                if ($ev->starttime < $until)
                                    $earr[] = $ev;
                            }
                            $dow++;
                        }
                        $startweek += $interval * 604800;
                    }

                    // Now add 1 * $interval weeks and start with the first available day of dayarr
                    break;

                case 'MONTHLY':
                    if ($monthrepeat == 'dom') {
                        // Day Of Month
                        while ($evstart < $until) {
                            $evstart = mktime(date('H', $evstart),
                                date('i', $evstart),
                                date('s', $evstart),
                                date('n', $evstart) + $interval,
                                date('j', $evstart),
                                date('Y', $evstart));

                            $evend = mktime(date('H', $evend),
                                date('i', $evend),
                                date('s', $evend),
                                date('n', $evend) + $interval,
                                date('j', $evend),
                                date('Y', $evend));

                            $ev = new \stdClass();
                            $ev->starttime = $evstart;
                            $ev->endtime = $evend;
                            $ev->calendarId = $event->calendarId;
                            $earr[] = $ev;
                        }
                    } else {
                        // Day of Week
                        // Get the day of the week (sun - sat)
                        $dow = date('w', $evstart);

                        // Calculate how often that day has occured in the month (eg. the 2nd monday)
                        $nd = ceil(date('j', $evstart) / 7);
                        $mon = date('n', $evstart);
                        while ($evstart < $until) {
                            // Add the interval of months
                            $a = $evstart = strtotime("+$interval month", $evstart);

                            // Check the 'new' day of the week
                            $newdow = date('w', $evstart);
                            $delta = $dow - $newdow;
                            if ($delta < 0)
                                $delta += 7;

                            // And correct it to the old dow
                            $evstart += ($delta * 86400);

                            // We've accidently moved to the next month, correct date
                            // by removing 1 week;
                            if (date('m', $evstart) > date('m', $a))
                                $evstart -= 7 * 86400;
                            // Check how often that day has occured
                            $newnd = ceil(date('j', $evstart) / 7);

                            // And correct it to the original occurence
                            while ($newnd < $nd) {
                                $evstart += 604800;
                                $newnd++;
                            }
                            while ($newnd > $nd) {
                                $evstart -= 604800;
                                $newnd--;
                            }

                            $evend = $evstart + $startenddiff;
                            $ev = new \stdClass();
                            $ev->starttime = $evstart;
                            $ev->endtime = $evend;
                            $ev->calendarId = $event->calendarId;
                            $earr[] = $ev;
                        }
                    }
                    break;

                case 'YEARLY':
                    while ($evstart < $until) {
                        $evstart = mktime(date('H', $evstart),
                            date('i', $evstart),
                            date('s', $evstart),
                            date('n', $evstart),
                            date('j', $evstart),
                            date('Y', $evstart) + $interval);

                        $evend = mktime(date('H', $evend),
                            date('i', $evend),
                            date('s', $evend),
                            date('n', $evend),
                            date('j', $evend),
                            date('Y', $evend) + $interval);

                        $ev = new \stdClass();
                        $ev->starttime = $evstart;
                        $ev->endtime = $evend;
                        $ev->calendarId = $event->calendarId;
                        $earr[] = $ev;
                    }
                    break;
            }
            $ad = $event->dates[0]->allday;
            $ne = $event->dates[0]->noend;
            foreach ($earr as $ev) {
                if ($ev->endtime < $ev->starttime) {
                    // Quick fix, just add 1 day to the endtime
                    $ev->endtime = strtotime('+1 day', $ev->endtime);
                }
                $sqla[] = "({$event -> calendarId},
							'" . date(BrightUtils::$SQLDateTime, $ev->starttime) . "',
							'" . date(BrightUtils::$SQLDateTime, $ev->endtime) . "',
							{$ad},
							0,
							{$ne})";
            }
        } else {
            $sqla = $evdates;
        }


        // Add dates to db
        $sql .= join(',', $sqla);
        $sql .= ' ON DUPLICATE KEY UPDATE starttime=VALUES(starttime), endtime=VALUES(endtime), `deleted`=0, allday=VALUES(`allday`), noend=VALUES(`noend`)';
        $this->_conn->insertRow($sql);

        $sql = 'DELETE FROM calendareventsnew WHERE `deleted`=1 AND `calendarId`=' . (int)$event->calendarId;
        $this->_conn->deleteRow($sql);

        if ($updateContent) {
            $this->setContent($event, 'calendarcontent');

            $search = BrightUtils::createSearchString($event);
            if ((int)$event->locationId > 0) {
                $search .= $this->conn->getField("SELECT search FROM gm_markers WHERE pageId={$event -> locationId}");
            }
            $search = Connection::getInstance()->escape_string($search);
            $sql = "INSERT INTO calendarindex (calendarId, search) VALUES ({$event -> calendarId}, '$search') ON DUPLICATE KEY UPDATE search='$search' ";
            $this->_conn->insertRow($sql);
        }

        if (isset($ch) && method_exists($ch, 'postSetEvent')) {
            $ch->postSetEvent($event);
        }
        return true;
    }

    /**
     * Deletes an event / Multiple events
     * @param array $ids An array of pageId's
     * @throws \Exception
     * @return bool
     * @since 1.1 - 9 dec 2010
     */
    public function deleteEvents($ids)
    {
        foreach ($ids as $calendarId) {
            if (!is_numeric($calendarId))
                throw $this->throwException(2002);

            $sql = 'DELETE FROM `calendarnew` WHERE `calendarId`=' . (int)$calendarId;
            $this->_conn->deleteRow($sql);
            $sql = 'DELETE FROM `calendareventsnew` WHERE `calendarId`=' . (int)$calendarId;
            $this->_conn->deleteRow($sql);
            $sql = 'DELETE FROM `calendardates` WHERE `calendarId`=' . (int)$calendarId;
            $this->_conn->deleteRow($sql);
            $sql = 'DELETE FROM `calendarcontent` WHERE `calendarId`=' . (int)$calendarId;
            $this->_conn->deleteRow($sql);

            //$this -> deleteEvent($calendarId);
        }
        return true;
    }

    /**
     * Gets the event
     * @param string $sql The SQL query to get the event with
     * @return null|\OPage
     */
    private function _getEvent($sql)
    {
        $rows = $this->_conn->getRows($sql, 'OCalendarEvent');
        if (!$rows)
            return null;

        $event = $rows[0];
        $event = $this->getContent($event, false, 'calendarcontent');
        $event->dates = array();
        $event->publicationdate = (double)$rows[0]->starttime;
        $event->expirationdate = (double)$rows[0]->endtime;
        foreach ($rows as $row) {

            if (!array_key_exists($row->dateId, $event->dates)) {

                $ocd = new OCalendarDateObject();
                $ocd->starttime = (double)$row->starttime;
                $ocd->endtime = (double)$row->endtime;
                $ocd->allday = ($row->allday == 1);
                $ocd->noend = ($row->noend == 1);
                $ocd->calendarId = $row->calendarId;
                $event->dates[$row->dateId] = $ocd;
            }
            $ocd = new OCalendarDateObject();
            $ocd->starttime = (double)$row->rawstarttime;
            $ocd->endtime = (double)$row->rawendtime;
            $ocd->calendarId = $row->calendarId;
            $ocd->allday = ($row->rawallday == 1);
            $ocd->noend = ($row->rawnoend == 1);
            $ocd->eventId = $row->eventId;
            $event->rawdates[$row->eventId] = $ocd;
        }

        unset($event->deleted);
        unset($event->starttime);
        unset($event->endtime);
        unset($event->rawstarttime);
        unset($event->rawendtime);
        unset($event->dateId);
        unset($event->eventId);
        unset($event->allday);
        unset($event->rawallday);
        unset($event->noend);
        unset($event->rawnoend);

        $event->rawdates = BrightUtils::cleanArray($event->rawdates);
        $event->dates = BrightUtils::cleanArray($event->dates);
        return $event;

    }

    private function _dateSort($a, $b)
    {
        if ($a->starttime == $b->starttime)
            return 0;
        return ($a->starttime < $b->starttime) ? -1 : 1;

    }

    private function _getAdditionalCalendarFields()
    {
        $settings = $this->getSettings();
        $additionalFields = array();
        if ($settings && $settings !== null && isset($settings->calendar) && isset($settings->calendar->visibleColumns)) {
            foreach ($settings->calendar->visibleColumns as $col) {
                if (!in_array($col, Config::$calendarColumns)) {
                    $additionalFields[] = $col;
                }
            }
        }
        return $additionalFields;
    }

    private function _getEventsByIdRangeAlt($start = 0, $limit = 20, $filter = null, $orderField = 'calendarId', $order = 'DESC')
    {
        $additionalFields = $this->_getAdditionalCalendarFields();

        $fieldSql = '';
        $joins = array();
        if (count($additionalFields) != 0) {
            $fields = array();
            foreach ($additionalFields as $field) {
                $field = Connection::getInstance()->escape_string($field);
                $fields[] = " COALESCE(co$field.value, '') as `$field` ";
                $joins[] = "LEFT JOIN calendarcontent co$field ON cn.calendarId = co$field.calendarId AND co$field.`lang`='nl' AND co$field.`field`='$field' ";
            }
            $fieldSql .= ', ' . join(', ', $fields);
        }

        $fromDate = 'NOW()';
        if ($filter != null) {
            if (!is_string($filter)) {
                $filter = (object)$filter;
                if (isset($filter->datestart) && isset($filter->dateend)) {
                    $dateStart = (double)$filter->datestart;
                    $dateEnd = (double)$filter->dateend;
                    $filter = $filter->filter;
                    if ($dateStart > 0 && $dateEnd > 0 && $dateEnd > $dateStart) {
                        $fromDate = "FROM_UNIXTIME($dateStart)";
                        $joins[] = "INNER JOIN calendareventsnew cen ON cen.calendarId=cn.calendarId AND cen.starttime < FROM_UNIXTIME($dateEnd) AND cen.endtime > FROM_UNIXTIME($dateStart)";

                    }
                } else {
                    // Invalid object
                    $filter = '';
                }
            }
            if ($filter != '' && $filter != null) {
                if ((int)$filter > 0) {
                    // Search by id
                    $filter = (int)$filter;
                    $joins[] = "INNER JOIN calendarnew ci ON ci.calendarId = cn.calendarId AND ci.calendarId=$filter";
                } else {
                    $filter = Connection::getInstance()->escape_string($filter);
                    if (strpos($filter, '*') === false) {
                        $filter = '*' . $filter . '*';
                    }
                    $joins[] = "INNER JOIN calendarindex ci ON ci.calendarId = cn.calendarId AND MATCH(`ci`.`search`) AGAINST('$filter' IN BOOLEAN MODE) ";
                }
            }
        }
        // Reverse the array, since the inner joins might reduce the searching set significantly,
        // so they should come first
        $joins = array_reverse($joins);
        $joinSql = join("\r\n", $joins) . "\r\n";
        $limit++;
        $sql = "SELECT
				cn.calendarId,
				cn.itemType,
				cn.label,
				cn.enabled,
				cn.modifiedby as modifiedby,
				cn.createdby as createdby,
				UNIX_TIMESTAMP(cn.creationdate) as creationdate,
				UNIX_TIMESTAMP(cn.modificationdate) as modificationdate,
				UNIX_TIMESTAMP(cee.starttime) as publicationdate,
				UNIX_TIMESTAMP(cee.endtime) as expirationdate,
				gmm.label as location
				$fieldSql
				FROM calendarnew cn

				LEFT JOIN `gm_markers` gmm ON cn.locationId = gmm.pageId
                LEFT JOIN ((SELECT calendarId, eventId, MIN(starttime) as starttime, endtime
								FROM calendareventsnew WHERE starttime > $fromDate
								GROUP BY calendarId,eventId)
						UNION
								(SELECT calendarId, eventId, MAX(starttime) as starttime, endtime
								FROM calendareventsnew WHERE starttime <= $fromDate
								GROUP BY calendarId,eventId)) AS cee ON cee.calendarId = cn.calendarId
				$joinSql
				WHERE cee.starttime IS NOT NULL
				GROUP BY cn.calendarId
				ORDER BY $orderField $order
				LIMIT $start,$limit";

        $rows = $this->_conn->getRows($sql, 'OCalendarEvent');

        $hasMoreResults = (count($rows) == $limit);
        $total = $start + count($rows);
        if ($hasMoreResults) {
            array_pop($rows);
        }

        $result = (object)array('result' => $rows, 'total' => $total);
        return $result;
    }
}