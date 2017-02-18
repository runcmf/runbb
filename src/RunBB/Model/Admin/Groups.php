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

class Groups
{
    public function fetchGroups()
    {
        $result = \ORM::forTable(ORM_TABLE_PREFIX.'groups')->orderByExpr('g_id')->findMany();
        Container::get('hooks')->fireDB('model.admin.groups.fetch_groups_query', $result);
        $groups = [];
        foreach ($result as $cur_group) {
            $groups[$cur_group['g_id']] = $cur_group;
        }

        $groups = Container::get('hooks')->fire('model.admin.groups.fetch_groups', $groups);

        return $groups;
    }

    public function infoAddGroup($groups, $id)
    {
        $group = [];

        if (Input::post('add_group')) {
            $group['base_group'] = (int)Input::post('base_group');
            $group['base_group'] = Container::get('hooks')
                ->fire('model.admin.groups.add_user_group', $group['base_group']);
            $group['info'] = $groups[$group['base_group']];

            $group['mode'] = 'add';
        } else {
            // We are editing a group
            if (!isset($groups[$id])) {
                throw new  RunBBException(__('Bad request'), 404);
            }

            $groups[$id] = Container::get('hooks')->fire('model.admin.groups.update_user_group', $groups[$id]);

            $group['info'] = $groups[$id];

            $group['mode'] = 'edit';
        }

        $group = Container::get('hooks')->fire('model.admin.groups.info_add_group', $group);
        return $group;
    }

    public function getGroupList($groups, $group)
    {
        $output = '';

        foreach ($groups as $cur_group) {
            if (($cur_group['g_id'] != $group['info']['g_id'] || $group['mode'] == 'add') &&
                $cur_group['g_id'] != ForumEnv::get('FEATHER_ADMIN') &&
                $cur_group['g_id'] != ForumEnv::get('FEATHER_GUEST')) {
                if ($cur_group['g_id'] == $group['info']['g_promote_next_group']) {
                    $output .= "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.
                        Utils::escape($cur_group['g_title']).'</option>'."\n";
                } else {
                    $output .= "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.
                        Utils::escape($cur_group['g_title']).'</option>'."\n";
                }
            }
        }

        $output = Container::get('hooks')->fire('model.admin.groups.get_group_list', $output);
        return $output;
    }

    public function getGroupListDelete($group_id)
    {
        $group_id = Container::get('hooks')->fire('model.admin.groups.get_group_list_delete_start', $group_id);

        $select_get_group_list_delete = ['g_id', 'g_title'];
        $result = \ORM::for_table(ORM_TABLE_PREFIX.'groups')->select_many($select_get_group_list_delete)
                        ->where_not_equal('g_id', ForumEnv::get('FEATHER_GUEST'))
                        ->where_not_equal('g_id', $group_id)
                        ->orderByExpr('g_title');
        $result = Container::get('hooks')->fireDB('model.admin.groups.get_group_list_delete', $result);
        $result = $result->find_many();

        $output = '';

        foreach ($result as $cur_group) {
            if ($cur_group['g_id'] == ForumEnv::get('FEATHER_MEMBER')) {
                // Pre-select the pre-defined Members group
                $output .= "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.
                    Utils::escape($cur_group['g_title']).'</option>'."\n";
            } else {
                $output .= "\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.
                    Utils::escape($cur_group['g_title']).'</option>'."\n";
            }
        }

        $output = Container::get('hooks')->fire('model.admin.groups.get_group_list.output', $output);
        return $output;
    }

    public function addEditGroup($groups)
    {
        if (Input::post('group_id')) {
            $group_id = Input::post('group_id');
        } else {
            $group_id = 0;
        }

        $group_id = Container::get('hooks')->fire('model.admin.groups.add_edit_group_start', $group_id);

        // Is this the admin group? (special rules apply)
        $is_admin_group = (Input::post('group_id') && Input::post('group_id') == ForumEnv::get('FEATHER_ADMIN'))
            ? true : false;

        // Set group title
        $title = Utils::trim(Input::post('req_title'));
        if ($title == '') {
            throw new  RunBBException(__('Must enter title message'), 400);
        }
        $title = Container::get('hooks')->fire('model.admin.groups.add_edit_group_set_title', $title);
        // Set user title
        $user_title = Utils::trim(Input::post('user_title'));
        $user_title = ($user_title != '') ? $user_title : 'NULL';
        $user_title = Container::get('hooks')->fire('model.admin.groups.add_edit_group_set_user_title', $user_title);

        $promote_min_posts = Input::post('promote_min_posts') ? intval(Input::post('promote_min_posts')) : '0';
        if (Input::post('promote_next_group') &&
                isset($groups[Input::post('promote_next_group')]) &&
                !in_array(Input::post('promote_next_group'), [ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_GUEST')]) &&
                (Input::post('group_id') || Input::post('promote_next_group') != Input::post('group_id'))) {
            $promote_next_group = Input::post('promote_next_group');
        } else {
            $promote_next_group = '0';
        }

        $moderator = Input::post('moderator') && Input::post('moderator') == '1' ? '1' : '0';
        $mod_edit_users = $moderator == '1' && Input::post('mod_edit_users') == '1' ? '1' : '0';
        $mod_rename_users = $moderator == '1' && Input::post('mod_rename_users') == '1' ? '1' : '0';
        $mod_change_passwords = $moderator == '1' && Input::post('mod_change_passwords') == '1' ? '1' : '0';
        $mod_ban_users = $moderator == '1' && Input::post('mod_ban_users') == '1' ? '1' : '0';
        $mod_promote_users = $moderator == '1' && Input::post('mod_promote_users') == '1' ? '1' : '0';
        $read_board = (Input::post('read_board') == 0) ? Input::post('read_board') : '1';
        $view_users = (Input::post('view_users') && Input::post('view_users') == '1') || $is_admin_group ? '1' : '0';
        $post_replies = (Input::post('post_replies') == 0) ? Input::post('post_replies') : '1';
        $post_topics = (Input::post('post_topics') == 0) ? Input::post('post_topics') : '1';
        $edit_posts = (Input::post('edit_posts') == 0) ? Input::post('edit_posts') : ($is_admin_group) ? '1' : '0';
        $delete_posts = (Input::post('delete_posts') == 0) ?
            Input::post('delete_posts') : ($is_admin_group) ? '1' : '0';
        $delete_topics = (Input::post('delete_topics') == 0) ?
            Input::post('delete_topics') : ($is_admin_group) ? '1' : '0';
        $post_links = (Input::post('post_links') == 0) ? Input::post('post_links') : '1';
        $set_title = (Input::post('set_title') == 0) ? Input::post('set_title') : ($is_admin_group) ? '1' : '0';
        $search = (Input::post('search') == 0) ? Input::post('search') : '1';
        $search_users = (Input::post('search_users') == 0) ? Input::post('search_users') : '1';
        $send_email = (Input::post('send_email') && Input::post('send_email') == '1') || $is_admin_group ? '1' : '0';
        $post_flood = (Input::post('post_flood') && Input::post('post_flood') >= 0) ? Input::post('post_flood') : '0';
        $search_flood = (Input::post('search_flood') && Input::post('search_flood') >= 0) ?
            Input::post('search_flood') : '0';
        $email_flood = (Input::post('email_flood') && Input::post('email_flood') >= 0) ?
            Input::post('email_flood') : '0';
        $report_flood = (Input::post('report_flood') >= 0) ? Input::post('report_flood') : '0';

        $insert_update_group = [
            'g_title'               =>  $title,
            'g_user_title'          =>  $user_title,
            'g_promote_min_posts'   =>  $promote_min_posts,
            'g_promote_next_group'  =>  $promote_next_group,
            'g_moderator'           =>  $moderator,
            'g_mod_edit_users'      =>  $mod_edit_users,
            'g_mod_rename_users'    =>  $mod_rename_users,
            'g_mod_change_passwords'=>  $mod_change_passwords,
            'g_mod_ban_users'       =>  $mod_ban_users,
            'g_mod_promote_users'   =>  $mod_promote_users,
            'g_read_board'          =>  $read_board,
            'g_view_users'          =>  $view_users,
            'g_post_replies'        =>  $post_replies,
            'g_post_topics'         =>  $post_topics,
            'g_edit_posts'          =>  $edit_posts,
            'g_delete_posts'        =>  $delete_posts,
            'g_delete_topics'       =>  $delete_topics,
            'g_post_links'          =>  $post_links,
            'g_set_title'           =>  $set_title,
            'g_search'              =>  $search,
            'g_search_users'        =>  $search_users,
            'g_send_email'          =>  $send_email,
            'g_search_flood'        =>  $search_flood,
            'g_email_flood'         =>  $email_flood,
            'g_report_flood'        =>  $report_flood,
        ];

        $insert_update_group = Container::get('hooks')
            ->fire('model.admin.groups.add_edit_group_data', $insert_update_group);

        if (Input::post('mode') == 'add') {
            // Creating a new group
            $title_exists = \ORM::for_table(ORM_TABLE_PREFIX.'groups')->where('g_title', $title)->find_one();
            if ($title_exists) {
                throw new  RunBBException(sprintf(__('Title already exists message'), Utils::escape($title)), 400);
            }

            $add = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                        ->create();
            $add->set($insert_update_group)->save();
            $new_group_id = Container::get('hooks')
                ->fire('model.admin.groups.add_edit_group.new_group_id', (int) $add->id());

            // Set new preferences
            Container::get('prefs')->setGroup($new_group_id, ['post.min_interval' => (int) $post_flood]);

            // Now lets copy the forum specific permissions from the group which this group is based on
            $select_forum_perms = ['forum_id', 'read_forum', 'post_replies', 'post_topics'];
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'forum_perms')->select_many($select_forum_perms)
                            ->where('group_id', Input::post('base_group'));
            $result = Container::get('hooks')
                ->fireDB('model.admin.groups.add_edit_group.select_forum_perms_query', $result);
            $result = $result->find_many();

            foreach ($result as $cur_forum_perm) {
                $insert_perms = [
                    'group_id'       =>  $new_group_id,
                    'forum_id'       =>  $cur_forum_perm['forum_id'],
                    'read_forum'     =>  $cur_forum_perm['read_forum'],
                    'post_replies'   =>  $cur_forum_perm['post_replies'],
                    'post_topics'    =>  $cur_forum_perm['post_topics'],
                ];

                \ORM::for_table(ORM_TABLE_PREFIX.'forum_perms')
                        ->create()
                        ->set($insert_perms)
                        ->save();
            }
        } else {
            // We are editing an existing group
            $title_exists = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                ->where('g_title', $title)
                ->where_not_equal('g_id', Input::post('group_id'))
                ->find_one();
            if ($title_exists) {
                throw new  RunBBException(sprintf(__('Title already exists message'), Utils::escape($title)), 400);
            }
            \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                    ->find_one(Input::post('group_id'))
                    ->set($insert_update_group)
                    ->save();

            // Promote all users who would be promoted to this group on their next post
            if ($promote_next_group) {
                \ORM::for_table(ORM_TABLE_PREFIX.'users')->where('group_id', Input::post('group_id'))
                    ->where_gte('num_posts', $promote_min_posts)
                    ->find_result_set()
                    ->set(['group_id' => $promote_next_group])
                    ->save();
            }
        }

        $group_id = Input::post('mode') == 'add' ? $new_group_id : Input::post('group_id');
        $group_id = Container::get('hooks')->fire('model.admin.groups.add_edit_group.group_id', $group_id);

        // Regenerate the quick jump cache
        Container::get('cache')->store('quickjump', Cache::getQuickjump());

        if (Input::post('mode') == 'edit') {
            return Router::redirect(Router::pathFor('adminGroups'), __('Group edited redirect'));
        } else {
            return Router::redirect(Router::pathFor('adminGroups'), __('Group added redirect'));
        }
    }

    public function setDefaultGroup($groups)
    {
        $group_id = intval(Input::post('default_group'));
        $group_id = Container::get('hooks')->fire('model.admin.groups.set_default_group.group_id', $group_id);

        // Make sure it's not the admin or guest groups
        if ($group_id == ForumEnv::get('FEATHER_ADMIN') || $group_id == ForumEnv::get('FEATHER_GUEST')) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        // Make sure it's not a moderator group
        if ($groups[$group_id]['g_moderator'] != 0) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        \ORM::for_table(ORM_TABLE_PREFIX.'config')
            ->where('conf_name', 'o_default_user_group')
            ->find_result_set()
            ->set(['conf_value' => $group_id])
            ->save();

        // Regenerate the config cache
        Container::get('cache')->store('config', Cache::getConfig());

        return Router::redirect(Router::pathFor('adminGroups'), __('Default group redirect'));
    }

    public function checkMembers($group_id)
    {
        $group_id = Container::get('hooks')->fire('model.admin.groups.check_members_start', $group_id);

        $is_member = \ORM::for_table(ORM_TABLE_PREFIX.'groups')->table_alias('g')
            ->select('g.g_title')
            ->select_expr('COUNT(u.id)', 'members')
            ->inner_join(ORM_TABLE_PREFIX.'users', ['g.g_id', '=', 'u.group_id'], 'u')
            ->where('g.g_id', $group_id)
            ->group_by('g.g_id')
            ->group_by('g_title');
        $is_member = Container::get('hooks')->fireDB('model.admin.groups.check_members', $is_member);
        $is_member = $is_member->find_one();

        return (bool) $is_member;
    }

    public function deleteGroup($group_id)
    {
        if ($group_id < 5) {
            throw new RunBBException('Cannot delete core groups');
        }

        $group_id = Container::get('hooks')->fire('model.admin.groups.delete_group.group_id', $group_id);

        if (Input::post('del_group')) {
            $move_to_group = intval(Input::post('move_to_group'));
            $move_to_group = Container::get('hooks')
                ->fire('model.admin.groups.delete_group.move_to_group', $move_to_group);
            \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->where('group_id', $group_id)
//                ->find_one()
                ->find_result_set()
                ->set(['group_id' => $move_to_group])
                ->save();
        }

        // Delete the group and any forum specific permissions
        \ORM::for_table(ORM_TABLE_PREFIX.'groups')
            ->where('g_id', $group_id)
            ->delete_many();
        \ORM::for_table(ORM_TABLE_PREFIX.'forum_perms')
            ->where('group_id', $group_id)
            ->delete_many();

        // Don't let users be promoted to this group
        \ORM::for_table(ORM_TABLE_PREFIX.'groups')
            ->where('g_promote_next_group', $group_id)
//            ->find_one()
            ->find_result_set()
            ->set(['g_promote_next_group' => 0])
            ->save();

        return Router::redirect(Router::pathFor('adminGroups'), __('Group removed redirect'));
    }

    public function getGroupTitle($group_id)
    {
        $group_id = Container::get('hooks')->fireDB('model.admin.groups.get_group_title.group_id', $group_id);

        $group_title = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
            ->select('g_title')
            ->where('g_id', $group_id)
            ->find_one();

        if (!$group_title) {
            throw new RunBBException('Group ('.$group_id.') title not found. You sure group exists?');
        }
        $group_title->g_title = Container::get('hooks')
            ->fireDB('model.admin.groups.get_group_title.query', $group_title->g_title);
//        $group_title = $group_title->find_one_col('g_title');

        return $group_title->g_title;
    }

    public function getTitleMembers($group_id)
    {
        $group_id = Container::get('hooks')->fire('model.admin.groups.get_title_members.group_id', $group_id);

        $group = \ORM::for_table(ORM_TABLE_PREFIX.'groups')->table_alias('g')
                    ->select('g.g_title')
                    ->select_expr('COUNT(u.id)', 'members')
                    ->inner_join(ORM_TABLE_PREFIX.'users', ['g.g_id', '=', 'u.group_id'], 'u')
                    ->where('g.g_id', $group_id)
                    ->group_by('g.g_id')
                    ->group_by('g_title');
        $group = Container::get('hooks')->fireDB('model.admin.groups.get_title_members.query', $group);
        $group = $group->find_one();

        $group_info['title'] = $group['g_title'];
        $group_info['members'] = $group['members'];

        $group_info = Container::get('hooks')->fire('model.admin.groups.get_title_members.group_info', $group_info);
        return $group_info;
    }

    public function setParserPlugins($group_id, array $plugins)
    {
        $p = \ORM::forTable(ORM_TABLE_PREFIX.'groups')
            ->findOne($group_id)
            ->set('g_parser_plugins', serialize($plugins))
            ->save();
        return $p;
    }
}
