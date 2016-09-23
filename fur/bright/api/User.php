<?php
/**
 * Created by PhpStorm.
 * User: ids
 * Date: 9/23/16
 * Time: 11:31 AM
 */

namespace fur\bright\api;


class User
{
    /**
     * @var boolean Indicates whether the user is logged in or not. For now, this is the only possible permission for a user
     */
    public $IS_CLIENT_AUTH = false;

    public function isLoggedIn() {
        return $this -> IS_CLIENT_AUTH;
    }
}