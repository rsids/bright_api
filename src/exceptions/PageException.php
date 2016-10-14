<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 14-10-16
 * Time: 11:23
 */

namespace fur\bright\exceptions;


class PageException extends GenericException
{
    const DELETE_PAGE_NOT_ALLOWED = 5001;
    const REMOVE_PAGE_NOT_ALLOWED = 5002;
    const PAGE_STILL_IN_TREE = 5003;
    const BACKUP_NOT_FOUND = 5004;

    const MAX_CHILDREN = 6001;
    const UNLOCK_EXCEPTION = 6002;
    const DUPLICATE_PAGE = 6003;
    const MISSING_TITLE = 6004;
    const SITEMAP_CREATE = 6005;
}