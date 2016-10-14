<?php

namespace fur\bright\exceptions;

class UserException extends GenericException
{
    const MANAGE_USER = 8001;
    const DUPLICATE_EMAIL = 8002;
    const INSERT_ERROR = 8003;
    const NO_USER_AUTH = 8004;
    const DUPLICATE_USERGROUP = 8005;
    const GROUPNAME_MISSING = 8006;
    const ERROR_CSV_OPEN = 8007;
    const ERROR_CSV_STRUCTURE = 8008;
    const USER_NOT_FOUND = 8009;

}