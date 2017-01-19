<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Core\Utils;

class Userlist
{
    // Counts the number of user for a specific query
    public function fetch_user_count($username, $show_group)
    {
        // Fetch user count
        $num_users = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->table_alias('u')
                        ->where_gt('u.id', 1)
                        ->where_not_equal('u.group_id', ForumEnv::get('FEATHER_UNVERIFIED'));

        if ($username != '') {
            $num_users = $num_users->where_like('u.username', str_replace('*', '%', $username));
        }
        if ($show_group > -1) {
            $num_users = $num_users->where('u.group_id', $show_group);
        }

        $num_users = $num_users->count('id');

        $num_users = Container::get('hooks')->fire('model.userlist.fetch_user_count', $num_users);

        return $num_users;
    }

    // Generates the dropdown menu containing groups
    public function generate_dropdown_menu($show_group)
    {
        $show_group = Container::get('hooks')->fire('model.userlist.generate_dropdown_menu_start', $show_group);

        $dropdown_menu = '';

        $result['select'] = ['g_id', 'g_title'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                        ->select_many($result['select'])
                        ->where_not_equal('g_id', ForumEnv::get('FEATHER_GUEST'))
                        ->order_by_expr('g_id');
        $result = Container::get('hooks')->fireDB('model.userlist.generate_dropdown_menu_query', $result);
        $result = $result->find_many();

        foreach ($result as $cur_group) {
            if ($cur_group['g_id'] == $show_group) {
                $dropdown_menu .= "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.Utils::escape($cur_group['g_title']).'</option>'."\n";
            } else {
                $dropdown_menu .= "\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.Utils::escape($cur_group['g_title']).'</option>'."\n";
            }
        }

        $dropdown_menu = Container::get('hooks')->fire('model.userlist.generate_dropdown_menu', $dropdown_menu);

        return $dropdown_menu;
    }

    // Prints the users
    public function print_users($username, $start_from, $sort_by, $sort_dir, $show_group)
    {
        $userlist_data = [];

        $username = Container::get('hooks')->fire('model.userlist.print_users_start', $username, $start_from, $sort_by, $sort_dir, $show_group);

        // Retrieve a list of user IDs, LIMIT is (really) expensive so we only fetch the IDs here then later fetch the remaining data
        $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->select('u.id')
                    ->table_alias('u')
                    ->where_gt('u.id', 1)
                    ->where_not_equal('u.group_id', ForumEnv::get('FEATHER_UNVERIFIED'));

        if ($username != '') {
            $result = $result->where_like('u.username', str_replace('*', '%', $username));
        }
        if ($show_group > -1) {
            $result = $result->where('u.group_id', $show_group);
        }

        $result = $result->order_by_expr($sort_by.' '.$sort_dir)
                         ->order_by_asc('u.id')
                         ->limit(50)
                         ->offset($start_from);

        $result = Container::get('hooks')->fireDB('model.userlist.print_users_query', $result);
        $result = $result->find_many();

        if ($result) {
            $user_ids = [];
            foreach ($result as $cur_user_id) {
                $user_ids[] = $cur_user_id['id'];
            }

            // Grab the users
            $result['select'] = ['u.id', 'u.username', 'u.title', 'u.num_posts', 'u.registered',
                'g.g_id', 'g.g_user_title'];

            $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                          ->table_alias('u')
                          ->select_many($result['select'])
                          ->left_outer_join(ORM_TABLE_PREFIX.'groups', ['g.g_id', '=', 'u.group_id'], 'g')
                          ->where_in('u.id', $user_ids)
                          ->order_by_expr($sort_by.' '.$sort_dir)
                          ->order_by_asc('u.id');
            $result = Container::get('hooks')->fireDB('model.userlist.print_users_grab_query', $result);
            $result = $result->find_many();

            foreach ($result as $user_data) {
                $userlist_data[] = $user_data;
            }
        }

        $userlist_data = Container::get('hooks')->fire('model.userlist.print_users', $userlist_data);

        return $userlist_data;
    }
}
