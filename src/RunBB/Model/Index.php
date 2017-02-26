<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Core\Track;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Index
{
    // Returns page head
    public function getPageHead()
    {
        Container::get('hooks')->fire('model.index.get_page_head_start');

        if (ForumSettings::get('o_feed_type') == '1') {
            $page_head = ['feed' => '<link rel="alternate" type="application/rss+xml" 
            href="'.Router::pathFor('extern').'?action=feed&amp;type=rss" title="'.
                __('RSS active topics feed').'" />'];
        } elseif (ForumSettings::get('o_feed_type') == '2') {
            $page_head = ['feed' => '<link rel="alternate" type="application/atom+xml" 
            href="'.Router::pathFor('extern').'?action=feed&amp;type=atom" title="'.
                __('Atom active topics feed').'" />'];
        }

        $page_head = Container::get('hooks')->fire('model.index.get_page_head', $page_head);

        return $page_head;
    }

    // Returns forum action
    public function getForumActions()
    {
        Container::get('hooks')->fire('model.index.get_forum_actions_start');

        $forum_actions = [];

        // Display a "mark all as read" link
        if (!User::get()->is_guest) {
            $forum_actions[] = '<a class="btn btn-primary btn-sm" href="'.
                Router::pathFor('markRead').'">'.__('Mark all as read').'</a>';
        }

        $forum_actions = Container::get('hooks')->fire('model.index.get_forum_actions', $forum_actions);

        return $forum_actions;
    }

    // Detects if a "new" icon has to be displayed
    protected function getNewPosts()
    {
        Container::get('hooks')->fire('model.index.get_new_posts_start');

        $query['select'] = ['f.id', 'f.last_post'];
        $query['where'] = [
            ['fp.read_forum' => 'IS NULL'],
            ['fp.read_forum' => '1']
        ];
        $query = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
            ->table_alias('f')
            ->select_many($query['select'])
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(`fp`.`forum_id`=`f`.`id` AND `fp`.`group_id`='.User::get()->g_id.')',
                'fp'
            )
//            ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//            ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.group_id', '=',
// User::get()->g_id), null, true)
//            ->where_any_is($query['where'])
            ->where_raw('(fp.read_forum IS NULL OR fp.read_forum = 1)')
            ->where_gt('f.last_post', User::get()->last_visit);

        $query = Container::get('hooks')->fireDB('model.index.query_get_new_posts', $query);

        $query = $query->find_result_set();

        $forums = $new_topics = [];
        $tracked_topics = Track::getTrackedTopics();

        foreach ($query as $cur_forum) {
            if (!isset($tracked_topics['forums'][$cur_forum->id]) ||
                $tracked_topics['forums'][$cur_forum->id] < $cur_forum->last_post) {
                $forums[$cur_forum->id] = $cur_forum->last_post;
            }
        }

        if (!empty($forums)) {
            if (empty($tracked_topics['topics'])) {
                $new_topics = $forums;
            } else {
                $query['select'] = ['forum_id', 'id', 'last_post'];

                $query = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                    ->select_many($query['select'])
                    ->where_in('forum_id', array_keys($forums))
                    ->where_gt('last_post', User::get()->last_visit)
                    ->where_null('moved_to');

                $query = Container::get('hooks')->fireDB('model.index.get_new_posts_query', $query);

                $query = $query->find_result_set();

                foreach ($query as $cur_topic) {
                    if (!isset($new_topics[$cur_topic->forum_id]) &&
                        (!isset($tracked_topics['forums'][$cur_topic->forum_id]) ||
                            $tracked_topics['forums'][$cur_topic->forum_id] < $forums[$cur_topic->forum_id]) &&
                        (!isset($tracked_topics['topics'][$cur_topic->id]) ||
                            $tracked_topics['topics'][$cur_topic->id] < $cur_topic->last_post)) {
                        $new_topics[$cur_topic->forum_id] = $forums[$cur_topic->forum_id];
                    }
                }
            }
        }

        $new_topics = Container::get('hooks')->fire('model.index.get_new_posts', $new_topics);

        return $new_topics;
    }

    // Returns the elements needed to display categories and their forums
    public function printCategoriesForums()
    {
        Container::get('hooks')->fire('model.index.print_categories_forums_start');

        // Get list of forums and topics with new posts since last visit
        if (!User::get()->is_guest) {
            $new_topics = $this->getNewPosts();
        }

        $query['select'] = [
            'cid' => 'c.id',
            'c.cat_name',
            'fid' => 'f.id',
            'f.forum_name',
            'f.forum_desc',
            'f.redirect_url',
            'f.moderators',
            'f.num_topics',
            'f.num_posts',
            'f.last_post',
            'f.last_post_id',
            'f.last_poster'
        ];
//        $query['where'] = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );
//        $query['order_by'] = array('c.disp_position', 'c.id', 'f.disp_position');

        $query = \ORM::for_table(ORM_TABLE_PREFIX.'categories')
            ->table_alias('c')
            ->select_many($query['select'])
            ->inner_join(ORM_TABLE_PREFIX.'forums', ['c.id', '=', 'f.cat_id'], 'f')
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(`fp`.`forum_id`=`f`.`id` AND `fp`.`group_id`='. User::get()->g_id.')',
                'fp'
            )
//            ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//            ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.group_id', '=',
// User::get()->g_id), null, true)
//            ->where_any_is($query['where'])
            ->where_raw('(`fp`.`read_forum` IS NULL OR `fp`.`read_forum`=1)')
            ->orderByExpr('`c`.`disp_position`, `c`.`id`, `f`.`disp_position`');
//            ->order_by_many($query['order_by']);

        $query = Container::get('hooks')->fireDB('model.index.query_print_categories_forums', $query);

        $query = $query->find_result_set();

        $index_data = [];
        $i = 0;
        foreach ($query as $cur_forum) {
            if ($i == 0) {
                $cur_forum->cur_category = 0;
                $cur_forum->forum_count_formatted = 0;
            }

            if (isset($cur_forum->cur_category)) {
                $cur_cat = $cur_forum->cur_category;
            } else {
                $cur_cat = 0;
            }

            if ($cur_forum->cid != $cur_cat) {
                // A new category since last iteration?
                $cur_forum->forum_count_formatted = 0;
                $cur_forum->cur_category = $cur_forum->cid;
            }

            ++$cur_forum->forum_count_formatted;

            $cur_forum->item_status = ($cur_forum->forum_count_formatted % 2 == 0) ? 'roweven' : 'rowodd';
            $forum_field_new = '';
            $cur_forum->icon_type = 'icon';

            // Are there new posts since our last visit?
            if (isset($new_topics[$cur_forum->fid])) {
                $cur_forum->item_status .= ' inew';
                $forum_field_new = '<span class="newtext">[ <a href="'.
                    Router::pathFor('quickSearch', ['show' => 'new&amp;fid='.$cur_forum->fid]).'">'.
                    __('New posts').'</a> ]</span>';
                $cur_forum->icon_type = 'icon icon-new';
            }

            // Is this a redirect forum?
            if ($cur_forum->redirect_url != '') {
                $cur_forum->forum_field = '<span class="redirtext">'.__('Link to').'</span> <a href="'.
                    Utils::escape($cur_forum->redirect_url).'" title="'.__('Link to').' '.
                    Utils::escape($cur_forum->redirect_url).'">'.
                    Utils::escape($cur_forum->forum_name).'</a>';
                $cur_forum->num_topics_formatted = $cur_forum->num_posts_formatted = '-';
                $cur_forum->item_status .= ' iredirect';
                $cur_forum->icon_type = 'icon';
            } else {
                $forum_name = Url::slug($cur_forum->forum_name);
                $cur_forum->forum_field = '<a href="'.
                    Router::pathFor('Forum', ['id' => $cur_forum->fid, 'name' => $forum_name]).'">'.
                    Utils::escape($cur_forum->forum_name).'</a>'.(!empty($forum_field_new) ? ' '.
                        $forum_field_new : '');
                $cur_forum->num_topics_formatted = $cur_forum->num_topics;
                $cur_forum->num_posts_formatted = $cur_forum->num_posts;
            }

            if ($cur_forum->forum_desc != '') {
                $cur_forum->forum_field .= "\n\t\t\t\t\t\t\t\t".'<div class="forumdesc">'.
                    $cur_forum->forum_desc.'</div>';
            }

            // If there is a last_post/last_poster
            if ($cur_forum->last_post != '') {
                $cur_forum->last_post_formatted = '<a href="'.
                    Router::pathFor('viewPost', ['pid' => $cur_forum->last_post_id]).'#p'.
                    $cur_forum->last_post_id.'">'.
                    Container::get('utils')->timeFormat($cur_forum->last_post).'</a><br /><span class="byuser">'.
                    __('by').' '.Utils::escape($cur_forum->last_poster).'</span>';
            } elseif ($cur_forum->redirect_url != '') {
                $cur_forum->last_post_formatted = '- - -';
            } else {
                $cur_forum->last_post_formatted = __('Never');
            }

            if ($cur_forum->moderators != '') {
                $mods_array = unserialize($cur_forum->moderators);
                $moderators = [];

                foreach ($mods_array as $mod_username => $mod_id) {
                    if (User::get()->g_view_users == '1') {
                        $moderators[] = '<a href="'.Router::pathFor('userProfile', ['id' => $mod_id]).'">'.
                            Utils::escape($mod_username).'</a>';
                    } else {
                        $moderators[] = Utils::escape($mod_username);
                    }
                }

                $cur_forum->moderators_formatted = "\t\t\t\t\t\t\t\t".'<p class="modlist">(<em>'.
                    __('Moderated by').'</em> '.implode(', ', $moderators).')</p>'."\n";
            } else {
                $cur_forum->moderators_formatted = '';
            }

//            $index_data[] = $cur_forum;
            $index_data[$cur_forum->cid][] = $cur_forum;
            ++$i;
        }

        $index_data = Container::get('hooks')->fire('model.index.print_categories_forums', $index_data);

        return $index_data;
    }

    // Returns the elements needed to display stats
    public function collectStats()
    {
        Container::get('hooks')->fire('model.index.collect_stats_start');

        // Collect some statistics from the database
        if (!Container::get('cache')->isCached('users_info')) {
            Container::get('cache')->store('users_info', Cache::getUsersInfo());
        }

        $stats = Container::get('cache')->retrieve('users_info');

        $query = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
            ->select_expr('SUM(num_topics)', 'total_topics')
            ->select_expr('SUM(num_posts)', 'total_posts');

        $query = Container::get('hooks')->fireDB('model.index.collect_stats_query', $query);

        $query = $query->find_one();

        $stats['total_topics'] = Utils::numberFormat((int)$query['total_topics']);
        $stats['total_posts'] = Utils::numberFormat((int)$query['total_posts']);

        if (User::get()->g_view_users == '1') {
            $stats['newest_user'] = '<a href="'.
                Router::pathFor('userProfile', ['id' => $stats['last_user']['id']]).'">'.
                Utils::escape($stats['last_user']['username']).'</a>';
        } else {
            $stats['newest_user'] = Utils::escape($stats['last_user']['username']);
        }
        $stats['total_users'] = Utils::numberFormat((int)$stats['total_users']);

        $stats = Container::get('hooks')->fire('model.index.collect_stats', $stats);

        return $stats;
    }

    // Returns the elements needed to display users online
    public function fetchUsersOnline()
    {
        Container::get('hooks')->fire('model.index.fetch_users_online_start');

        // Fetch users online info and generate strings for output
        $online = [];
        $online['num_guests'] = 0;

        $query['select'] = ['user_id', 'ident'];
        $query['where'] = ['idle' => '0'];
//        $query['order_by'] = array('ident');

        $query = \ORM::for_table(ORM_TABLE_PREFIX.'online')
            ->select_many($query['select'])
            ->where($query['where'])
            ->orderByExpr('ident');
//            ->order_by_many($query['order_by']);

        $query = Container::get('hooks')->fireDB('model.index.query_fetch_users_online', $query);

        $query = $query->find_result_set();

        foreach ($query as $user_online) {
            if ($user_online->user_id > 1) {
                if (User::get()->g_view_users == '1') {
                    $online['users'][] = "\n\t\t\t\t".'<dd><a href="'.
                        Router::pathFor('userProfile', ['id' => $user_online->user_id]).'">'.
                        Utils::escape($user_online->ident).'</a>';
                } else {
                    $online['users'][] = "\n\t\t\t\t".'<dd>'.Utils::escape($user_online->ident);
                }
            } else {
                ++$online['num_guests'];
            }
        }

        if (isset($online['users'])) {
            $online['num_users'] = count($online['users']);
        } else {
            $online['num_users'] = 0;
        }

        $online = Container::get('hooks')->fire('model.index.fetch_users_online', $online);

        return $online;
    }
}
