<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 14-10-16
 * Time: 11:41
 */

namespace fur\bright\exceptions;


class TemplateException extends GenericException
{
    const TEMPLATE_CREATE = 7001;
    const SET_MAXCHILDREN = 7002;
    const TEMPLATE_LIFETIME = 7003;
    const DUPLICATE_NAME = 7004;
    const INVALID_NAME = 7005;
    const INSERT_ERROR = 7006;
    const TEMPLATE_IN_USE = 7007;
    const NOT_A_MAIL_TEMPLATE = 7008;
    const PLUGIN_DIRECTORY_NOT_FOUND = 7009;
    const TEMPLATE_NOT_FOUND = 7010;
}