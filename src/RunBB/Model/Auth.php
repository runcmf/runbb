<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Core\Random;
use RunBB\Core\Utils;
use Firebase\JWT\JWT;

class Auth
{
    public static function loadUser($user_id)
    {
        $user_id = (int) $user_id;
        $result['select'] = ['u.*', 'g.*', 'o.logged', 'o.idle'];
        $result['where'] = ['u.id' => $user_id];
        $result['join'] = ($user_id == 1) ? Utils::getIp() : 'u.id';

//        $result = DB::forTable('users')
        $result = DB::forTable('users')
                ->tableAlias('u')
                ->selectMany($result['select'])
                ->innerJoin(DB::prefix().'groups', ['u.group_id', '=', 'g.g_id'], 'g')
                ->rawJoin('LEFT JOIN '.DB::prefix().'online', 'o.user_id=?', 'o', [1 => $result['join']])
                ->where($result['where'])
                ->findOne();
        return $result;
    }

    public static function deleteOnlineByIp($ip)
    {
        $delete_online = DB::forTable('online')->where('ident', $ip);
        $delete_online = Container::get('hooks')->fireDB('delete_online_login', $delete_online);
        return $delete_online->deleteMany();
    }

    public static function deleteOnlineById($user_id)
    {
        // Remove user from "users online" list
        $delete_online = DB::forTable('online')->where('user_id', $user_id);
        $delete_online = Container::get('hooks')->fireDB('delete_online_logout', $delete_online);
        return $delete_online->deleteMany();
    }

    public static function getUserFromName($username)
    {
        $user = DB::forTable('users')->where('username', $username);
        $user = Container::get('hooks')->fireDB('find_user_login', $user);
        return $user->findOne();
    }

    public static function getUserFromEmail($email)
    {
        $result['select'] = ['id', 'username', 'last_email_sent'];
        $result = DB::forTable('users')
            ->selectMany($result['select'])
            ->where('email', $email);
        $result = Container::get('hooks')->fireDB('password_forgotten_query', $result);
        return $result->findOne();
    }

    public static function updateGroup($user_id, $group_id)
    {
        $update_usergroup = DB::forTable('users')->where('id', $user_id)
            ->findOne()
            ->set('group_id', $group_id);
        $update_usergroup = Container::get('hooks')->fireDB('update_usergroup_login', $update_usergroup);
        return $update_usergroup->save();
    }

    public static function setLastVisit($user_id, $last_visit)
    {
        $update_last_visit = DB::forTable('users')->where('id', (int) $user_id)
            ->findOne()
            ->set('last_visit', (int) $last_visit);
        $update_last_visit = Container::get('hooks')->fireDB('update_online_logout', $update_last_visit);
        return $update_last_visit->save();
    }

    public static function setNewPassword($pass, $key, $user_id)
    {
        $query['update'] = [
            'activate_string' => hash('sha256', $pass),// FIXME check algo
            'activate_key'    => $key,
            'last_email_sent' => time(),
        ];

        $query = DB::forTable('users')
                    ->where('id', $user_id)
                    ->findOne()
                    ->set($query['update']);
        $query = Container::get('hooks')->fireDB('password_forgotten_mail_query', $query);
        return $query->save();
    }

    public static function generateJwt($user, $expire)
    {
        $issuedAt   = time();
        $tokenId    = base64_encode(Random::key(32));
        $serverName = Config::get('serverName');

        /*
        * Create the token as an array
        */
        $data = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'iss'  => $serverName,       // Issuer
            'exp'  => $expire,           // Expire after 30 minutes of idle or 14 days if "remember me"
            'data' => [                  // Data related to the signer user
                'userId'   => $user->id, // userid from the users table
                'userName' => $user->username, // User name
            ]
        ];

        /*
        * Extract the key, which is coming from the config file.
        *
        * Generated with base64_encode(openssl_random_pseudo_bytes(64));
        */
        $secretKey = base64_decode(ForumSettings::get('jwt_token'));

        /*
        * Extract the algorithm from the config file too
        */
        $algorithm = ForumSettings::get('jwt_algorithm');

        /*
        * Encode the array to a JWT string.
        * Second parameter is the key to encode the token.
        *
        * The output string can be validated at http://jwt.io/
        */
        $jwt = JWT::encode(
            $data,      //Data to be encoded in the JWT
            $secretKey, // The signing key
            // Algorithm used to sign the token,
            // see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
            $algorithm
        );

        return $jwt;
    }

    public static function setCookie($jwt, $expire)
    {
        // Store cookie to client storage
        setcookie(ForumSettings::get('cookie_name'), $jwt, $expire, '/', '', false, true);
    }
}
