<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 11-10-16
 * Time: 21:34
 */

namespace fur\bright\exceptions;


class GenericException extends \Exception
{
    const GENERIC_EXCEPTION = 1000;
    const CLASS_NOT_FOUND = 9001;
    const METHOD_NOT_FOUND = 9002;
    const SWIFT_NOT_FOUND = 9003;
    const TCPDF_NOT_FOUND = 9004;
    const SPHIDER_NOT_FOUND = 9005;
    const NOT_IMPLEMENTED = 9006;

}