<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 9/23/16
 * Time: 11:37 AM
 */

namespace fur\bright\core;


class Log
{

    function __construct()
    {
        if (!is_dir(__DIR__ . '/../logs')) {
            @mkdir(__DIR__ . '/../logs');
        }
    }

    /**
     * Writes a string to the logclass<br/>
     * <b>debug.log Needs to be writable!</b>
     */
    public static function addToLog()
    {
        $statements = func_get_args();
        try {
            $fname = __DIR__ . '/../logs/debug.log';
            if (file_exists($fname)) {
                // Max 1 mb
                if (filesize($fname) > 1048576)
                    file_put_contents($fname, '');
            }
            $handle = fopen($fname, 'a');
            foreach ($statements as $statement) {
                if (!is_scalar($statement))
                    $statement = var_export($statement, true);

                fwrite($handle, $statement . "\n");
            }
            fclose($handle);
        } catch (\Exception $ex) {
            error_log("Cannot log, \r\n" . $ex->getMessage() . "\r\n------------\r\n" . $ex->getTraceAsString());
        }
    }

    public static function clearLog()
    {
        file_put_contents(__DIR__ . '/../logs/debug.log', '');
    }

    /**
     * Writes a string to the log class<br/>
     * <b>404.log Needs to be writable!</b>
     * @param string $statement The string to write;
     */
    public static function addTo404log($statement)
    {
        if (!is_scalar($statement))
            $statement = print_r($statement, true);

        @file_put_contents(__DIR__ . '/../logs/404.log', $statement . "\n", FILE_APPEND);
    }
}