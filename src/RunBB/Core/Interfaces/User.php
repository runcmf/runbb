<?php
namespace RunBB\Core\Interfaces;

use RunBB\Model\Auth as AuthModel;

class User extends \RunBB\Core\Statical\BaseProxy
{
    public static function get($id = null)
    {
        if (!$id) {
            // Get current user by default
            return Container::get('user');
        } else {
            // Load user from Db based on $id
            return AuthModel::loadUser($id);
        }
    }

    public static function getVar($var = null)
    {
        return Container::get('user')->$var;
    }
}
