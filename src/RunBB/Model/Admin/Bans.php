<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model\Admin;

use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use RunBB\Model\Cache;

class Bans
{
    public function addBanInfo($id = null)
    {
        $ban = [];

        $id = Container::get('hooks')->fire('model.admin.bans.add_ban_info_start', $id);

        // If the ID of the user to ban was provided through GET (a link from profile.php)
        if (is_numeric($id)) {
            $ban['user_id'] = $id;
            if ($ban['user_id'] < 2) {
                throw new  RunBBException(__('Bad request'), 404);
            }

            $select_add_ban_info = ['group_id', 'username', 'email'];
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')->select_many($select_add_ban_info)
                        ->where('id', $ban['user_id']);

            $result = Container::get('hooks')->fireDB('model.admin.bans.add_ban_info_query', $result);
            $result = $result->find_one();

            if ($result) {
                $group_id = $result['group_id'];
                $ban['ban_user'] = $result['username'];
                $ban['email'] = $result['email'];
            } else {
                throw new  RunBBException(__('No user ID message'), 404);
            }
        } else {
            // Otherwise the username is in POST

            $ban['ban_user'] = Utils::trim(Input::post('new_ban_user'));

            if ($ban['ban_user'] != '') {
                $select_add_ban_info = ['id', 'group_id', 'username', 'email'];
                $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')->select_many($select_add_ban_info)
                    ->where('username', $ban['ban_user'])
                    ->where_gt('id', 1);

                $result = Container::get('hooks')->fireDB('model.admin.bans.add_ban_info_query', $result);
                $result = $result->find_one();

                if ($result) {
                    $ban['user_id'] = $result['id'];
                    $group_id = $result['group_id'];
                    $ban['ban_user'] = $result['username'];
                    $ban['email'] = $result['email'];
                } else {
                    throw new  RunBBException(__('No user message'), 404);
                }
            }
        }

        // Make sure we're not banning an admin or moderator
        if (isset($group_id)) {
            if ($group_id == ForumEnv::get('FEATHER_ADMIN')) {
                throw new  RunBBException(sprintf(__('User is admin message'), Utils::escape($ban['ban_user'])), 403);
            }

            $is_moderator_group = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                ->select('g_moderator')
                ->where('g_id', $group_id)
                ->find_one();

            if ($is_moderator_group->g_moderator > 0) {
                throw new  RunBBException(sprintf(__('User is mod message'), Utils::escape($ban['ban_user'])), 403);
            }
        }

        // If we have a $ban['user_id'], we can try to find the last known IP of that user
        if (isset($ban['user_id'])) {
            $ban_ip = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                ->select('poster_ip')
                ->where('poster_id', $ban['user_id'])
                ->order_by_desc('posted')
                ->find_one();

            if (!$ban_ip->poster_ip) {
                $ban_ip = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->select('registration_ip')
                    ->where('id', $ban['user_id'])
                    ->find_one();
            }

            $ban['ip'] = $ban_ip->poster_ip;
        }
        $ban['mode'] = 'add';

        $ban = Container::get('hooks')->fire('model.admin.bans.add_ban_info', $ban);

        return $ban;
    }

    public function editBanInfo($id)
    {
        $ban = [];

        $id = Container::get('hooks')->fire('model.admin.bans.edit_ban_info_start', $id);

        $ban['id'] = $id;

        $select_edit_ban_info = ['username', 'ip', 'email', 'message', 'expire'];
        $result = \ORM::for_table(ORM_TABLE_PREFIX.'bans')->select_many($select_edit_ban_info)
            ->where('id', $ban['id']);

        $result = Container::get('hooks')->fireDB('model.admin.bans.edit_ban_info_query', $result);
        $result = $result->find_one();

        if ($result) {
            $ban['ban_user'] = $result['username'];
            $ban['ip'] = $result['ip'];
            $ban['email'] = $result['email'];
            $ban['message'] = $result['message'];
            $ban['expire'] = $result['expire'];
        } else {
            throw new  RunBBException(__('Bad request'), 404);
        }

        $diff = (User::get()->timezone + User::get()->dst) * 3600;
        $ban['expire'] = ($ban['expire'] != '') ? gmdate('Y-m-d', $ban['expire'] + $diff) : '';

        $ban['mode'] = 'edit';

        $ban = Container::get('hooks')->fire('model.admin.bans.edit_ban_info', $ban);

        return $ban;
    }

    public function insertBan()
    {
        $ban_user = Utils::trim(Input::post('ban_user'));
        $ban_ip = Utils::trim(Input::post('ban_ip'));
        $ban_email = strtolower(Utils::trim(Input::post('ban_email')));
        $ban_message = Utils::trim(Input::post('ban_message'));
        $ban_expire = Utils::trim(Input::post('ban_expire'));

        Container::get('hooks')
            ->fire('model.admin.bans.insert_ban_start', $ban_user, $ban_ip, $ban_email, $ban_message, $ban_expire);

        if ($ban_user == '' && $ban_ip == '' && $ban_email == '') {
            throw new  RunBBException(__('Must enter message'), 400);
        } elseif (strtolower($ban_user) == 'guest') {
            throw new  RunBBException(__('Cannot ban guest message'), 400);
        }

        // Make sure we're not banning an admin or moderator
        if (!empty($ban_user)) {
            $group_id = \ORM::forTable(ORM_TABLE_PREFIX.'users')
                ->select('group_id')
                ->where('username', $ban_user)
                ->whereGt('id', 1)
                ->findOne();

            if ($group_id !== false && $group_id->group_id) {
                if ($group_id->group_id == ForumEnv::get('FEATHER_ADMIN')) {
                    throw new  RunBBException(sprintf(__('User is admin message'), Utils::escape($ban_user)), 403);
                }

                $is_moderator_group = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                    ->select('g_moderator')
                    ->where('g_id', $group_id->group_id)
                    ->find_one();

                if ($is_moderator_group->g_moderator > 0) {
                    throw new  RunBBException(sprintf(__('User is mod message'), Utils::escape($ban_user)), 403);
                }
            }
        }

        // Validate IP/IP range (it's overkill, I know)
        if ($ban_ip != '') {
            $ban_ip = preg_replace('%\s{2,}%S', ' ', $ban_ip);
            $addresses = explode(' ', $ban_ip);
            $addresses = array_map('trim', $addresses);

            for ($i = 0; $i < count($addresses); ++$i) {
                if (strpos($addresses[$i], ':') !== false) {
                    $octets = explode(':', $addresses[$i]);

                    for ($c = 0; $c < count($octets); ++$c) {
                        $octets[$c] = ltrim($octets[$c], "0");

                        if ($c > 7 || (!empty($octets[$c]) && !ctype_xdigit($octets[$c])) ||
                            intval($octets[$c], 16) > 65535) {
                            throw new  RunBBException(__('Invalid IP message'), 400);
                        }
                    }

                    $cur_address = implode(':', $octets);
                    $addresses[$i] = $cur_address;
                } else {
                    $octets = explode('.', $addresses[$i]);

                    for ($c = 0; $c < count($octets); ++$c) {
                        $octets[$c] = (strlen($octets[$c]) > 1) ? ltrim($octets[$c], "0") : $octets[$c];

                        if ($c > 3 || preg_match('%[^0-9]%', $octets[$c]) || intval($octets[$c]) > 255) {
                            throw new  RunBBException(__('Invalid IP message'), 400);
                        }
                    }

                    $cur_address = implode('.', $octets);
                    $addresses[$i] = $cur_address;
                }
            }

            $ban_ip = implode(' ', $addresses);
        }

        if ($ban_email != '' && !Container::get('email')->isValidEmail($ban_email)) {
            if (!preg_match('%^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,63})$%', $ban_email)) {
                throw new  RunBBException(__('Invalid e-mail message'), 400);
            }
        }

        if ($ban_expire != '' && $ban_expire != 'Never') {
            $ban_expire = strtotime($ban_expire.' GMT');

            if ($ban_expire == -1 || !$ban_expire) {
                throw new  RunBBException(__('Invalid date message').' '.__('Invalid date reasons'), 400);
            }

            $diff = (User::get()->timezone + User::get()->dst) * 3600;
            $ban_expire -= $diff;

            if ($ban_expire <= time()) {
                throw new  RunBBException(__('Invalid date message').' '.__('Invalid date reasons'), 400);
            }
        } else {
            $ban_expire = NULL;
        }

        $ban_user = ($ban_user != '') ? $ban_user : NULL;
        $ban_ip = ($ban_ip != '') ? $ban_ip : NULL;
        $ban_email = ($ban_email != '') ? $ban_email : NULL;
        $ban_message = ($ban_message != '') ? $ban_message : NULL;

        $insert_update_ban = [
            'username'  =>  $ban_user,
            'ip'        =>  $ban_ip,
            'email'     =>  $ban_email,
            'message'   =>  $ban_message,
            'expire'    =>  $ban_expire,
        ];

        $insert_update_ban = Container::get('hooks')->fire('model.admin.bans.insert_ban_data', $insert_update_ban);

        if (Input::post('mode') == 'add') {
            $insert_update_ban['ban_creator'] = User::get()->id;

            $result = \ORM::forTable(ORM_TABLE_PREFIX.'bans')
                ->create()
                ->set($insert_update_ban)
                ->save();
        } else {
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'bans')
                ->where('id', Input::post('ban_id'))
                ->find_one()
                ->set($insert_update_ban)
                ->save();
        }

        // Regenerate the bans cache
        Container::get('cache')->store('bans', Cache::getBans());

        return Router::redirect(Router::pathFor('adminBans'), __('Ban edited redirect'));
    }

    public function removeBan($ban_id)
    {
        $ban_id = Container::get('hooks')->fire('model.admin.bans.remove_ban', $ban_id);

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'bans')->where('id', $ban_id)
                    ->find_one();
        $result = Container::get('hooks')->fireDB('model.admin.bans.remove_ban_query', $result);
        $result = $result->delete();

        // Regenerate the bans cache
        Container::get('cache')->store('bans', Cache::getBans());

        return Router::redirect(Router::pathFor('adminBans'), __('Ban removed redirect'));
    }

    public function findBan($start_from = false)
    {
        $ban_info = [];

        Container::get('hooks')->fire('model.admin.bans.find_ban_start');

        // trim() all elements in $form
        $ban_info['conditions'] = $ban_info['query_str'] = [];

        $expire_after = Input::query('expire_after') ? Utils::trim(Input::query('expire_after')) : '';
        $expire_before = Input::query('expire_before') ? Utils::trim(Input::query('expire_before')) : '';
        $ban_info['order_by'] = Input::query('order_by') &&
        in_array(Input::query('order_by'), ['username', 'ip', 'email', 'expire']) ?
            'b.'.Input::query('order_by') : 'b.username';
        $ban_info['direction'] = Input::query('direction') && Input::query('direction') == 'DESC' ? 'DESC' : 'ASC';

        $ban_info['query_str'][] = 'order_by='.$ban_info['order_by'];
        $ban_info['query_str'][] = 'direction='.$ban_info['direction'];

        // Build the query
        $result = \ORM::for_table(ORM_TABLE_PREFIX.'bans')->table_alias('b')
                        ->where_gt('b.id', 0);

        // Try to convert date/time to timestamps
        if ($expire_after != '') {
            $ban_info['query_str'][] = 'expire_after='.$expire_after;

            $expire_after = strtotime($expire_after);
            if ($expire_after === false || $expire_after == -1) {
                throw new  RunBBException(__('Invalid date message'), 400);
            }

            $result = $result->where_gt('b.expire', $expire_after);
        }
        if ($expire_before != '') {
            $ban_info['query_str'][] = 'expire_before='.$expire_before;

            $expire_before = strtotime($expire_before);
            if ($expire_before === false || $expire_before == -1) {
                throw new  RunBBException(__('Invalid date message'), 400);
            }

            $result = $result->where_lt('b.expire', $expire_before);
        }

        if (Input::query('username')) {
            $result = $result->where_like('b.username', str_replace('*', '%', Input::query('username')));
            $ban_info['query_str'][] = 'username=' . urlencode(Input::query('username'));
        }

        if (Input::query('ip')) {
            $result = $result->where_like('b.ip', str_replace('*', '%', Input::query('ip')));
            $ban_info['query_str'][] = 'ip=' . urlencode(Input::query('ip'));
        }

        if (Input::query('email')) {
            $result = $result->where_like('b.email', str_replace('*', '%', Input::query('email')));
            $ban_info['query_str'][] = 'email=' . urlencode(Input::query('email'));
        }

        if (Input::query('message')) {
            $result = $result->where_like('b.message', str_replace('*', '%', Input::query('message')));
            $ban_info['query_str'][] = 'message=' . urlencode(Input::query('message'));
        }

        // Fetch ban count
        if (is_numeric($start_from)) {
            $ban_info['data'] = [];
            $select_bans = [
                'b.id',
                'b.username',
                'b.ip',
                'b.email',
                'b.message',
                'b.expire',
                'b.ban_creator',
                'ban_creator_username' => 'u.username'
            ];

            $result = $result->select_many($select_bans)
                             ->left_outer_join(ORM_TABLE_PREFIX.'users', ['b.ban_creator', '=', 'u.id'], 'u')
                             ->orderByExpr($ban_info['order_by'].' '.$ban_info['direction'])
                             ->offset($start_from)
                             ->limit(50)
                             ->find_many();

            foreach ($result as $cur_ban) {
                $ban_info['data'][] = $cur_ban;
            }
        } else {
            $ban_info['num_bans'] = $result->count('id');
        }

        Container::get('hooks')->fire('model.admin.bans.find_ban', $ban_info);

        return $ban_info;
    }
}
