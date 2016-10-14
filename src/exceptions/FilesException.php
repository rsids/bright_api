<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 13-10-16
 * Time: 19:50
 */

namespace fur\bright\exceptions;


class FilesException extends GenericException
{
    const FOLDER_NOT_FOUND = 4001;
    const PARENT_NOT_FOUND = 4002;
    const FOLDER_CREATE_FAILED_DUP = 4003;
    const FOLDER_DELETE_FAILED = 4004;
    const FILE_NOT_FOUND = 4005;
    const DUPLICATE_FILENAME = 4006;
    const FILE_DELETE_NOT_ALLOWED = 4007;
    const FOLDER_CREATE_FAILED = 4008;
    const UPLOAD_FAILED = 4009;
    const FILE_NOT_WRITABLE = 4010;
}