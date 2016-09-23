<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 9/23/16
 * Time: 11:42 AM
 */

namespace fur\bright\exceptions;


class ParameterException extends \Exception
{
    const INTEGER_EXCEPTION = 2002;
    const STRING_EXCEPTION = 2003;
    const DOUBLE_EXCEPTION = 2004;
    const BOOLEAN_EXCEPTION = 2005;
    const EMAIL_EXCEPTION = 2006;
    const ARRAY_EXCEPTION = 2007;
    const OBJECT_EXCEPTION = 2008;

}