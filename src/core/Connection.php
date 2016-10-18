<?php
namespace fur\bright\core;

/**
 * This class manages the database connection.<br/>This class is a singleton and can be accessed like this:
 * <code>
 * $conn = Connection::getInstance();
 * $conn -> getRow('sql statement');
 * </code>
 * Version history:
 * 2.4 20120802:
 * - Added benchmark
 * 2.3 20111213:
 * - Added reconnect
 *
 * @author Ids Klijnsma - Fur
 * @version 2.4
 * @package Bright
 * @subpackage db
 */
class Connection
{

    /**
     * @static var Connection The instance of this class
     */
    static private $instance;

    /**
     * @var resource The mysql-database connection
     */
    private $connection;


    private $db;

    public $logQueries = false;

    /**
     * Constructor, opens the mysqldb connection
     */
    private function __construct()
    {
        $host = DB_HOST;
        $port = ini_get("mysqli.default_port");
        if (strpos($host, ':') !== false) {
            $ha = explode(':', $host);
            $host = $ha[0];
            $port = $ha[1];
        }
        $this->connection = mysqli_connect($host,
            DB_USER,
            DB_PASSWORD,
            DB_DATABASE,
            $port);

        if (!$this->connection) {
            if (!LIVESERVER)
                echo "Cannot connect to database";

            exit;
        }


        $this->db = DB_DATABASE;
        mysqli_select_db($this->connection, $this->db);
        mysqli_query($this->connection, 'SET NAMES utf8');
    }

    /**
     * Gets a single instance of the connection class
     * @static
     * @return Connection An instance of the connection class
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $object = __CLASS__;
            self::$instance = new $object;
        }
        return self::$instance;
    }

    public function escape_string($val)
    {
        return mysqli_real_escape_string($this->connection, $val);
    }

    /**
     * Closes the current connection and creates a new one
     * @param string $host
     * @param string $user
     * @param string $pw
     * @param string $db
     */
    public function reconnect($host, $user, $pw, $db)
    {
        if ($this->connection)
            mysqli_close($this->connection);

        $port = ini_get("mysqli.default_port");
        if (strpos($host, ':') !== false) {
            $ha = explode(':', $host);
            $host = $ha[0];
            $port = $ha[1];
        }

        $this->connection = mysqli_connect($host,
            $user,
            $pw, $db);


        $this->db = $db;
        mysqli_select_db($this->connection, $this->db);
        mysqli_query($this->connection, 'SET NAMES utf8');
    }


    /**
     * Performs the actual query
     * @param string $query The query to execute
     * @throws \Exception
     * @return \mysqli_result The result of the query
     */
    private function performQuery($query)
    {
// 		mysqli_select_db($this->connection, $this -> db);
        $s = microtime(true);
        $result = mysqli_query($this->connection, $query);
        $e = microtime(true);
        if (BENCHMARK) {
//            $t = $e - $s;
//            $this->_benchmark($t, $query);
        }
        if ($this->logQueries) {
            Log::addToLog($query);
        }

        if (mysqli_errno($this->connection) > 0) {
            Log::addToLog($query . "\n" . mysqli_error($this->connection));
            if (!LIVESERVER) {
                throw new \Exception("Error in query;\nQuery : " . $query . "\nError: " . mysqli_error($this->connection), 2000);
            }
        }
        return $result;
    }

    /**
     * Gets one single row from the database
     * @param string $query The query to execute
     * @param string $objectType The type of objects to return.
     * @return object The row
     */
    public function getRow($query, $objectType = '\stdClass')
    {
        if($objectType !== '\stdClass') {
            $objectType = '\\fur\\bright\\entities\\' . $objectType;
        }
        $result = $this->performQuery($query);
        $row = mysqli_fetch_object($result, $objectType);
        mysqli_free_result($result);
        return $row;
    }

    /**
     * Gets one single field (the first column of the first row) from the database (easy for COUNT operations)
     * @param string $query The query to executed
     * @param string $objectType The type of variable to return
     * @return string The contents of the field
     * @since 2.1 - 11 feb 2010
     */
    public function getField($query, $objectType = 'string')
    {
        $result = $this->performQuery($query);
        $row = mysqli_fetch_array($result);
        mysqli_free_result($result);
        if ($row[0] === null)
            return null;
        try {
            settype($row[0], $objectType);
        } catch (\Exception $ex) {
            echo "$objectType is not a valid type.\r\n<br/>Query: $query";
        }
        return $row[0];
    }

    /**
     * Gets an array of fields (the first column of all rows, with no index)
     * @param string $query The query to executed
     * @param string $objectType The type of variable to return
     * @return array The contents of the first column
     * @since 2.1 - 11 feb 2010
     */
    public function getFields($query, $objectType = 'string')
    {
        $result = $this->performQuery($query);
        $ret = array();
        while ($row = mysqli_fetch_array($result)) {
            settype($row[0], $objectType);
            $ret[] = $row[0];
        }
        mysqli_free_result($result);
        return $ret;
    }

    /**
     * Gets multiple rows from the database
     * @param string $query The query to execute
     * @param string $objectType The type of objects to return.
     * @return array The rows
     */
    public function getRows($query, $objectType = '\stdClass')
    {
        if($objectType !== '\stdClass') {
            $objectType = '\\fur\\bright\\entities\\' . $objectType;
        }

        $result = $this->performQuery($query);
        $rows = array();
        while ($row = mysqli_fetch_object($result, $objectType)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }

    /**
     * Gets multiple rows as multidimensional array from the database
     * @param string $query The query to execute
     * @return array The rows
     */
    public function getRowsArray($query)
    {
        $result = $this->performQuery($query);
        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }

    /**
     * Gets multiple rows as index multidimensional array from the database
     * @param string $query The query to execute
     * @return array The rows
     */
    public function getRowsIndexedArray($query)
    {
        $result = $this->performQuery($query);
        $rows = array();
        while ($row = mysqli_fetch_array($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        return $rows;
    }

    /**
     * Inserts a row in the database
     * @param string $query The query to execute
     * @return int The id of the inserted query
     */
    public function insertRow($query)
    {
        $this->performQuery($query);
        $insertId = mysqli_insert_id($this->connection);
        return $insertId;
    }

    /**
     * Updates a row
     * @param string $query The query to execute
     * @return boolean Success
     */
    public function updateRow($query)
    {
        $this->performQuery($query);
        return mysqli_affected_rows($this->connection) > 0;
    }

    /**
     * Deletes a row from the database
     * @param string $query The query to execute
     * @return boolean Success
     */
    public function deleteRow($query)
    {
        $this->performQuery($query);
        return mysqli_affected_rows($this->connection);
    }

    /**
     * Changes id's with value 0 (zero) to null
     * @param \stdClass $item The item to insert in the database
     * @param array $fields The fields to nullify
     */
    public function nullify(&$item, $fields)
    {
        foreach ($fields as $field) {
            if (!isset($item->{$field}) || (int)$item->{$field} == 0) {
                $item->{$field} = 'null';
            } else {
                $item->{$field} = (int)$item->{$field};
            }
        }
    }

    /**
     * Escapes string values of an object
     * @param \stdClass $item The item to insert in the database
     * @param array $fields The fields to escape
     */
    public function escape(&$item, $fields)
    {
        foreach ($fields as $field) {
            if (isset($item->{$field})) {
                if ($item->{$field} === 'undefined') {
                    $item->{$field} = '';
                } else {
                    $stripped = strip_tags($item->{$field});
                    $item->{$field} = Connection::getInstance()->escape_string($stripped);
                }
            }

        }
    }

    /**
     * Destructor
     */
    function __destruct()
    {
        //Disconnect
        if (!$this->connection)
            return;
        mysqli_close($this->connection);
    }
}