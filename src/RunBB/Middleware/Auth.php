<?php
/**
 *
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * $app = new \Slim\Slim();
 * $app->add(new \Slim\Extras\Middleware\FeatherBBAuth());
 *
 */

namespace RunBB\Middleware;

use RunBB\Exception\RunBBException;
use RunBB\Core\Track;
use RunBB\Core\Utils;
use RunBB\Model\Cache;
use RunBB\Model\Auth as AuthModel;
use Firebase\JWT\JWT;

class Auth
{
    protected function get_cookie_data($authCookie = null)
    {
        if ($authCookie) {
            /*
             * Extract the jwt from the Bearer
             */
            list($jwt) = sscanf($authCookie, 'Bearer %s');

            if ($jwt) {
                try {
                    /*
                    * decode the jwt using the key from config
                    */
                    $secretKey = base64_decode(ForumSettings::get('jwt_token'));
                    $token = JWT::decode($jwt, $secretKey, [ForumSettings::get('jwt_algorithm')]);

                    return $token;
                } catch (\Firebase\JWT\ExpiredException $e) {
                    // TODO: (Optionnal) add flash message to say token has expired
                    return false;
                } catch (\Firebase\JWT\SignatureInvalidException $e) {
                    // If token secret has changed (config.php file removed then regenerated)
                    return false;
                }
            } else {
                // Token is not present (or invalid) in cookie
                return false;
            }
        } else {
            // Auth cookie is not present in headers
            return false;
        }
    }

    public function update_online()
    {
        // Define this if you want this visit to affect the online list and the users last visit data
        if (!defined('FEATHER_QUIET_VISIT')) {
            // Update the online list
            if (!User::get()->logged) {
                User::get()->logged = Container::get('now');

                // With MySQL/MySQLi/SQLite, REPLACE INTO avoids a user having two rows in the online table
                switch (ForumSettings::get('db_type')) {
                    case 'mysql':
                    case 'mysqli':
                    case 'mysql_innodb':
                    case 'mysqli_innodb':
                    case 'sqlite':
                    case 'sqlite3':
                        \ORM::for_table(ORM_TABLE_PREFIX.'online')->raw_execute('REPLACE INTO '.ForumSettings::get('db_prefix').'online (user_id, ident, logged) VALUES(:user_id, :ident, :logged)', [':user_id' => User::get()->id, ':ident' => User::get()->username, ':logged' => User::get()->logged]);
                        break;

                    default:
                        \ORM::for_table(ORM_TABLE_PREFIX.'online')->raw_execute('INSERT INTO '.ForumSettings::get('db_prefix').'online (user_id, ident, logged) SELECT :user_id, :ident, :logged WHERE NOT EXISTS (SELECT 1 FROM '.$this->app->db->prefix.'online WHERE user_id=:user_id)', [':user_id' => User::get()->id, ':ident' => User::get()->username, ':logged' => User::get()->logged]);
                        break;
                }

                // Reset tracked topics
                Track::set_tracked_topics(null);
            } else {
                // Special case: We've timed out, but no other user has browsed the forums since we timed out
                if (User::get()->logged < (Container::get('now')-ForumSettings::get('o_timeout_visit'))) {
                    \ORM::for_table(ORM_TABLE_PREFIX.'users')->where('id', User::get()->id)
                        ->find_one()
                        ->set('last_visit', User::get()->logged)
                        ->save();
                    User::get()->last_visit = User::get()->logged;
                }

                $idle_sql = (User::get()->idle == '1') ? ', idle=0' : '';

                \ORM::for_table(ORM_TABLE_PREFIX.'online')->raw_execute('UPDATE '.ForumSettings::get('db_prefix').'online SET logged='.Container::get('now').$idle_sql.' WHERE user_id=:user_id', [':user_id' => User::get()->id]);

                // Update tracked topics with the current expire time
                $cookie_tracked_topics = Container::get('cookie')->get(ForumSettings::get('cookie_name').'_track');
                if (isset($cookie_tracked_topics)) {
                    Track::set_tracked_topics(json_decode($cookie_tracked_topics, true));
                }
            }
        } else {
            if (!User::get()->logged) {
                User::get()->logged = User::get()->last_visit;
            }
        }
    }

    public function update_users_online()
    {
        // Fetch all online list entries that are older than "o_timeout_online"
        $select_update_users_online = ['user_id', 'ident', 'logged', 'idle'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'online')
                    ->select_many($select_update_users_online)
                    ->where_lt('logged', Container::get('now')-ForumSettings::get('o_timeout_online'))
                    ->find_many();

        foreach ($result as $cur_user) {
            // If the entry is a guest, delete it
            if ($cur_user['user_id'] == '1') {
                \ORM::for_table(ORM_TABLE_PREFIX.'online')->where('ident', $cur_user['ident'])
                    ->delete_many();
            } else {
                // If the entry is older than "o_timeout_visit", update last_visit for the user in question, then delete him/her from the online list
                if ($cur_user['logged'] < (Container::get('now')-ForumSettings::get('o_timeout_visit'))) {
                    \ORM::for_table(ORM_TABLE_PREFIX.'users')->where('id', $cur_user['user_id'])
                        ->find_one()
                        ->set('last_visit', $cur_user['logged'])
                        ->save();
                    \ORM::for_table(ORM_TABLE_PREFIX.'online')->where('user_id', $cur_user['user_id'])
                        ->delete_many();
                } elseif ($cur_user['idle'] == '0') {
                    \ORM::for_table(ORM_TABLE_PREFIX.'online')
                        ->where('user_id', $cur_user['user_id'])
                        ->find_one()
                        ->set(['idle' => 1])
                        ->save();
                }
            }
        }
    }

    public function check_bans()
    {
        // Admins and moderators aren't affected
        if (User::get()->is_admmod || !Container::get('bans')) {
            return;
        }

        // Add a dot or a colon (depending on IPv4/IPv6) at the end of the IP address to prevent banned address
        // 192.168.0.5 from matching e.g. 192.168.0.50
        $user_ip = Utils::getIp();
        $user_ip .= (strpos($user_ip, '.') !== false) ? '.' : ':';

        $bans_altered = false;
        $is_banned = false;

        foreach (Container::get('bans') as $cur_ban) {
            // Has this ban expired?
            if ($cur_ban['expire'] != '' && $cur_ban['expire'] <= time()) {
                \ORM::for_table(ORM_TABLE_PREFIX.'bans')->where('id', $cur_ban['id'])
                    ->delete_many();
                $bans_altered = true;
                continue;
            }

            if ($cur_ban['username'] != '' && utf8_strtolower(User::get()->username) == utf8_strtolower($cur_ban['username'])) {
                $is_banned = true;
            }

            if ($cur_ban['ip'] != '') {
                $cur_ban_ips = explode(' ', $cur_ban['ip']);

                $num_ips = count($cur_ban_ips);
                for ($i = 0; $i < $num_ips; ++$i) {
                    // Add the proper ending to the ban
                    if (strpos($user_ip, '.') !== false) {
                        $cur_ban_ips[$i] = $cur_ban_ips[$i].'.';
                    } else {
                        $cur_ban_ips[$i] = $cur_ban_ips[$i].':';
                    }

                    if (substr($user_ip, 0, strlen($cur_ban_ips[$i])) == $cur_ban_ips[$i]) {
                        $is_banned = true;
                        break;
                    }
                }
            }

            if ($is_banned) {
                \ORM::for_table(ORM_TABLE_PREFIX.'online')
                    ->where('ident', User::get()->username)
                    ->delete_many();
                throw new RunBBException(__('Ban message').' '.(($cur_ban['expire'] != '') ? __('Ban message 2').' '.strtolower($this->app->utils->format_time($cur_ban['expire'], true)).'. ' : '').(($cur_ban['message'] != '') ? __('Ban message 3').'<br /><br /><strong>'.Utils::escape($cur_ban['message']).'</strong><br /><br />' : '<br /><br />').__('Ban message 4').' <a href="mailto:'.Utils::escape(ForumSettings::get('o_admin_email')).'">'.Utils::escape(ForumSettings::get('o_admin_email')).'</a>.', 403);
            }
        }

        // If we removed any expired bans during our run-through, we need to regenerate the bans cache
        if ($bans_altered) {
            Container::get('cache')->store('bans', Cache::get_bans());
        }
    }

    public function maintenance_message()
    {
        // Deal with newlines, tabs and multiple spaces
        $pattern = ["\t", '  ', '  '];
        $replace = ['&#160; &#160; ', '&#160; ', ' &#160;'];
        $message = str_replace($pattern, $replace, ForumSettings::get('o_maintenance_message'));

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Maintenance')],
            'msg'    =>    $message,
            'backlink'    =>   false,
        ])->addTemplate('maintenance.php')->display();
    }

    public function __invoke($req, $res, $next)
    {
        $authCookie = Container::get('cookie')->get(ForumSettings::get('cookie_name'));

        if ($jwt = $this->get_cookie_data($authCookie)) {
            $user = AuthModel::load_user($jwt->data->userId);

            $expires = ($jwt->exp > Container::get('now') + ForumSettings::get('o_timeout_visit')) ? Container::get('now') + 1209600 : Container::get('now') + ForumSettings::get('o_timeout_visit');

            $user->is_guest = false;
            $user->is_admmod = $user->g_id == ForumEnv::get('FEATHER_ADMIN') || $user->g_moderator == '1';

            if (!$user->disp_topics) {
                $user->disp_topics = ForumSettings::get('o_disp_topics_default');
            }
            if (!$user->disp_posts) {
                $user->disp_posts = ForumSettings::get('o_disp_posts_default');
            }
            if (!file_exists(ForumEnv::get('FORUM_ROOT').'lang/'.$user->language)) {
                $user->language = ForumSettings::get('o_default_lang');
            }

            if (!file_exists(ForumEnv::get('WEB_ROOT').'style/themes/'.$user->style.'/style.css')) {
                $user->style = ForumSettings::get('o_default_style');
            }

            // Refresh cookie to avoid re-logging between idle
            $jwt = AuthModel::generate_jwt($user, $expires);
            AuthModel::feather_setcookie('Bearer '.$jwt, $expires);

            // Add user to DIC
            Container::set('user', $user);

            $this->update_online();
        } else {
            $user = AuthModel::load_user(1);

            $user->disp_topics = ForumSettings::get('o_disp_topics_default');
            $user->disp_posts = ForumSettings::get('o_disp_posts_default');
            $user->timezone = ForumSettings::get('o_default_timezone');
            $user->dst = ForumSettings::get('o_default_dst');
            $user->language = ForumSettings::get('o_default_lang');
            $user->style = ForumSettings::get('o_default_style');
            $user->is_guest = true;
            $user->is_admmod = false;

            // Update online list
            if (!$user->logged) {
                $user->logged = time();

                // With MySQL/MySQLi/SQLite, REPLACE INTO avoids a user having two rows in the online table
                switch (ForumSettings::get('db_type')) {
                    case 'mysql':
                    case 'mysqli':
                    case 'mysql_innodb':
                    case 'mysqli_innodb':
                    case 'sqlite':
                    case 'sqlite3':
                        \ORM::for_table(ORM_TABLE_PREFIX.'online')->raw_execute(
                            'REPLACE INTO '.
                            ForumSettings::get('db_prefix').'online (user_id, ident, logged) VALUES(1, :ident, :logged)',
                            [':ident' => Utils::getIp(), ':logged' => $user->logged]
                        );
                        break;

                    default:
                        \ORM::for_table(ORM_TABLE_PREFIX.'online')->raw_execute(
                            'INSERT INTO '.
                            ForumSettings::get('db_prefix').'online (user_id, ident, logged) 
                            SELECT 1, :ident, :logged WHERE NOT EXISTS (SELECT 1 FROM '.
                            ForumSettings::get('db_prefix').'online WHERE ident=:ident)',
                            [':ident' => Utils::getIp(), ':logged' => $user->logged]
                        );
                        break;
                }
            } else {
                \ORM::for_table(ORM_TABLE_PREFIX.'online')
                    ->where('ident', Utils::getIp())
                    ->find_one()
                    ->set(['logged' => time()])
                    ->save();
            }
            // $jwt = AuthModel::generate_jwt($user, Container::get('now') + 31536000);
            // AuthModel::feather_setcookie('Bearer '.$jwt, Container::get('now') + 31536000);
            // Add $user as guest to DIC
            Container::set('user', $user);
        }

        // load theme assets also set setStyle
        Container::get('template')->loadThemeAssets($user->style);

        translate('common');
        // Load bans from cache
        if (!Container::get('cache')->isCached('bans')) {
            Container::get('cache')->store('bans', Cache::get_bans());
        }

        // Add bans to the container
        Container::set('bans', Container::get('cache')->retrieve('bans'));

        // Check if current user is banned
        $this->check_bans();

        // Update online list
        $this->update_users_online();

        return $next($req, $res);
    }
}
