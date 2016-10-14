<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 13-10-16
 * Time: 20:09
 */

namespace fur\bright\exceptions;


class EventException extends GenericException
{
    const NOT_ENOUGH_DATES = 5005;
    const TOO_MANY_DATES = 5006;

}