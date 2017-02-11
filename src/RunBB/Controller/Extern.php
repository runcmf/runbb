<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

/*-----------------------------------------------------------------------------

  INSTRUCTIONS

  This script is used to include information about your board from
  pages outside the forums and to syndicate news about recent
  discussions via RSS/Atom/XML. The script can display a list of
  recent discussions, a list of active users or a collection of
  general board statistics. The script can be called directly via
  an URL, from a PHP include command or through the use of Server
  Side Includes (SSI).

  The scripts behaviour is controlled via variables supplied in the
  URL to the script. The different variables are: action (what to
  do), show (how many items to display), fid (the ID or IDs of
  the forum(s) to poll for topics), nfid (the ID or IDs of forums
  that should be excluded), tid (the ID of the topic from which to
  display posts) and type (output as HTML or RSS). The only
  mandatory variable is action. Possible/default values are:

    action: feed - show most recent topics/posts (HTML or RSS)
            online - show users online (HTML)
            online_full - as above, but includes a full list (HTML)
            stats - show board statistics (HTML)

    type:   rss - output as RSS 2.0
            atom - output as Atom 1.0
            xml - output as XML
            html - output as HTML (<li>'s)

    fid:    One or more forum IDs (comma-separated). If ignored,
            topics from all readable forums will be pulled.

    nfid:   One or more forum IDs (comma-separated) that are to be
            excluded. E.g. the ID of a a test forum.

    tid:    A topic ID from which to show posts. If a tid is supplied,
            fid and nfid are ignored.

    show:   Any integer value between 1 and 50. The default is 15.

    order:  last_post - show topics ordered by when they were last
                        posted in, giving information about the reply.
            posted - show topics ordered by when they were first
                     posted, giving information about the original post.

-----------------------------------------------------------------------------*/

namespace RunBB\Controller;

use RunBB\Core\Cache;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Extern
{
    public function __construct()
    {
        define('FEATHER_QUIET_VISIT', 1);

// Start a session for flash messages
//        session_cache_limiter(false);
//        session_start();
//        error_reporting(E_ALL); // Let's report everything for development
//        ini_set('display_errors', 1);

// Load Slim Framework
//        require 'vendor/autoload.php';

// Instantiate Slim and add CSRF
//        $feather = new \Slim\App();
//        $feather->add(new \RunBB\Middleware\Csrf());

//        $feather_settings = ['config_file' => 'RunBB/config.php',
//            'cache_dir' => 'cache/',
//            'debug' => 'all']; // 3 levels : false, info (only execution time and number of queries),
// and all (display info + queries)
//        $feather->add(new \RunBB\Middleware\Auth());
//        $feather->add(new \RunBB\Middleware\Core($feather_settings));

// The length at which topic subjects will be truncated (for HTML output)
        if (!defined('FORUM_EXTERN_MAX_SUBJECT_LENGTH')) {
            define('FORUM_EXTERN_MAX_SUBJECT_LENGTH', 30);
        }
    }

    public function display($req, $res, $args)
    {
        // If we're a guest and we've sent a username/pass, we can try to authenticate using those details
//        if (User::get()->is_guest && isset($_SERVER['PHP_AUTH_USER'])) {
//            $this->authenticate_user($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
//        }

//        if (User::get()->g_read_board == '0') {
//            $this->httpAuthenticateUser();
//            exit(__('No view'));
//        }

        $action = isset($_GET['action']) ? strtolower($_GET['action']) : 'feed';

        // Handle a couple old formats, from FluxBB 1.2
        switch ($action) {
            case 'active':
                $action = 'feed';
                $_GET['order'] = 'last_post';
                break;

            case 'new':
                $action = 'feed';
                $_GET['order'] = 'posted';
                break;
        }

        // Show recent discussions
        if ($action == 'feed') {
            // Determine what type of feed to output
            $type = isset($_GET['type']) ? strtolower($_GET['type']) : 'html';
            if (!in_array($type, ['html', 'rss', 'atom', 'xml'])) {
                $type = 'html';
            }

            $show = isset($_GET['show']) ? intval($_GET['show']) : 15;
            if ($show < 1 || $show > 50) {
                $show = 15;
            }

            // Was a topic ID supplied?
            if (isset($_GET['tid'])) {
                $tid = intval($_GET['tid']);

                // Fetch topic subject
                $select_show_recent_topics = ['t.subject', 't.first_post_id'];
//        $where_show_recent_topics = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );

                $cur_topic = \ORM::for_table(ORM_TABLE_PREFIX . 'topics')->table_alias('t')
                    ->select_many($select_show_recent_topics)
//                        ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                        ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                    ->left_outer_join(
                        ORM_TABLE_PREFIX . 'forum_perms',
                        '(fp.forum_id=t.forum_id AND fp.group_id=' . User::get()->g_id . ')',
                        'fp'
                    )
//                        ->where_any_is($where_show_recent_topics)
                    ->where_raw('(fp.read_forum IS NULL OR fp.read_forum=1)')
                    ->where_null('t.moved_to')
                    ->where('t.id', $tid)
                    ->find_one();

                if (!$cur_topic) {
                    $this->httpAuthenticateUser();
                    exit(__('Bad request'));
                }

                if (ForumSettings::get('o_censoring') == '1') {
                    $cur_topic['subject'] = Utils::censor($cur_topic['subject']);
                }

                // Setup the feed
                $feed = [
                    'title' => ForumSettings::get('o_board_title') . __('Title separator') . $cur_topic['subject'],
                    'link' => Url::get('topic/' . $tid . '/' . Url::slug($cur_topic['subject']) . '/'),
                    'description' => sprintf(__('RSS description topic'), $cur_topic['subject']),
                    'items' => [],
                    'type' => 'posts'
                ];

                // Fetch $show posts
                $select_print_posts = [
                    'p.id',
                    'p.poster',
                    'p.message',
                    'p.hide_smilies',
                    'p.posted',
                    'p.poster_email',
                    'p.poster_id',
                    'u.email_setting',
                    'u.email'
                ];

                $result = \ORM::for_table(ORM_TABLE_PREFIX . 'posts')
                    ->table_alias('p')
                    ->select_many($select_print_posts)
                    ->inner_join(ORM_TABLE_PREFIX . 'users', ['u.id', '=', 'p.poster_id'], 'u')
                    ->where('p.topic_id', $tid)
                    ->order_by_desc('p.posted')
                    ->limit($show)
                    ->find_array();

                foreach ($result as $cur_post) {
                    $cur_post['message'] = Container::get('parser')->parseMessage(
                        $cur_post['message'],
                        $cur_post['hide_smilies']
                    );

                    $item = [
                        'id' => $cur_post['id'],
                        'title' => $cur_topic['first_post_id'] == $cur_post['id'] ? $cur_topic['subject'] :
                            __('RSS reply') . $cur_topic['subject'],
                        'link' => Url::get('post/' . $cur_post['id'] . '/#p' . $cur_post['id']),
                        'description' => $cur_post['message'],
                        'author' => [
                            'name' => $cur_post['poster'],
                        ],
                        'pubdate' => $cur_post['posted']
                    ];

                    if ($cur_post['poster_id'] > 1) {
                        if ($cur_post['email_setting'] == '0' && !User::get()->is_guest) {
                            $item['author']['email'] = $cur_post['email'];
                        }

                        $item['author']['uri'] = Url::get('user/' . $cur_post['poster_id'] . '/');
                    } elseif ($cur_post['poster_email'] != '' && !User::get()->is_guest) {
                        $item['author']['email'] = $cur_post['poster_email'];
                    }

                    $feed['items'][] = $item;
                }

                $output_func = 'output' . ucfirst($type);
                $this->$output_func($feed);
            } else {
                $order_posted = isset($_GET['order']) && strtolower($_GET['order']) == 'posted';
                $forum_name = '';

                $result = \ORM::for_table(ORM_TABLE_PREFIX . 'topics')
                    ->table_alias('t');

                // Were any forum IDs supplied?
                if (isset($_GET['fid']) && is_scalar($_GET['fid']) && $_GET['fid'] != '') {
                    $fids = explode(',', Utils::trim($_GET['fid']));
                    $fids = array_map('intval', $fids);

                    if (!empty($fids)) {
                        $result = $result->where_in('t.forum_id', $fids);
                    }

                    if (count($fids) == 1) {
                        // Fetch forum name
//                $where_show_forum_name = array(
//                    array('fp.read_forum' => 'IS NULL'),
//                    array('fp.read_forum' => '1')
//                );

                        $cur_topic = \ORM::for_table(ORM_TABLE_PREFIX . 'forums')->table_alias('f')
//                    ->left_outer_join('forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//                    ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                            ->left_outer_join(
                                ORM_TABLE_PREFIX . 'forum_perms',
                                '(fp.forum_id=f.id AND fp.group_id=' . User::get()->g_id . ')',
                                'fp'
                            )
//                    ->where_any_is($where_show_forum_name)
                            ->where_raw('(fp.read_forum IS NULL OR fp.read_forum=1)')
                            ->where('f.id', $fids[0])
                            ->select('f.forum_name')
                            ->find_one();

                        if ($cur_topic) {
                            $forum_name = __('Title separator') . $cur_topic;
                        }
                    }
                }

                // Any forum IDs to exclude?
                if (isset($_GET['nfid']) && is_scalar($_GET['nfid']) && $_GET['nfid'] != '') {
                    $nfids = explode(',', Utils::trim($_GET['nfid']));
                    $nfids = array_map('intval', $nfids);

                    if (!empty($nfids)) {
                        $result = $result->where_not_in('t.forum_id', $nfids);
                    }
                }

                $cache_id = null;
                // Only attempt to cache if caching is enabled and we have all or a single forum
                if (ForumSettings::get('o_feed_ttl') > 0 && (/*$forum_sql == '' ||*/
                        ($forum_name != '' && !isset($_GET['nfid'])))) {
                    $cache_id = 'feed' . sha1(User::get()->g_id . '|' . __('lang_identifier') . '|' .
                            ($order_posted ? '1' : '0') . ($forum_name == '' ? '' : '|' . $fids[0]));
                }

                // Load cached feed
                $cache_expire = 0;
                if (isset($cache_id) && file_exists(FORUM_CACHE_DIR . 'cache_' . $cache_id . '.php')) {
                    include FORUM_CACHE_DIR . 'cache_' . $cache_id . '.php';
                }

                $now = time();
                if (!isset($feed) || $cache_expire < $now) {
                    // Setup the feed
                    $feed = [
                        'title' => ForumSettings::get('o_board_title') . $forum_name,
                        'link' => Router::pathFor('home'),//'/index.php',
                        'description' => sprintf(__('RSS description'), ForumSettings::get('o_board_title')),
                        'items' => [],
                        'type' => 'topics'
                    ];

                    // Fetch $show topics
                    $select_print_posts = [
                        't.id',
                        't.poster',
                        't.subject',
                        't.posted',
                        't.last_post',
                        't.last_poster',
                        'p.message',
                        'p.hide_smilies',
                        'u.email_setting',
                        'u.email',
                        'p.poster_id',
                        'p.poster_email'
                    ];
//            $where_print_posts = array(
//                array('fp.read_forum' => 'IS NULL'),
//                array('fp.read_forum' => '1')
//            );

                    $result = $result->select_many($select_print_posts)
                        ->inner_join(ORM_TABLE_PREFIX . 'posts', ['p.id', '=',
                            ($order_posted ? 't.first_post_id' : 't.last_post_id')], 'p')
                        ->inner_join(ORM_TABLE_PREFIX . 'users', ['u.id', '=', 'p.poster_id'], 'u')
//                        ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                        ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX . 'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id=' . User::get()->g_id . ')',
                            'fp'
                        )
//                        ->where_any_is($where_print_posts)
                        ->where_raw('(fp.read_forum IS NULL OR fp.read_forum=1)')
                        ->where_null('t.moved_to')
                        ->order_by_expr(($order_posted ? 't.posted' : 't.last_post'))
                        ->limit((isset($cache_id) ? 50 : $show))
                        ->find_array();

                    foreach ($result as $cur_topic) {
                        if (ForumSettings::get('o_censoring') == '1') {
                            $cur_topic['subject'] = Utils::censor($cur_topic['subject']);
                        }

                        $cur_topic['message'] = Container::get('parser')->parseMessage(
                            $cur_topic['message'],
                            $cur_topic['hide_smilies']
                        );

                        $item = [
                            'id' => $cur_topic['id'],
                            'title' => $cur_topic['subject'],
                            'link' => Url::get('topic/' . $cur_topic['id'] . '/' .
                                    Url::slug($cur_topic['subject']) . '/')
//                                . ($order_posted ? '' : '/action/new/')
                            ,
                            'description' => $cur_topic['message'],
                            'author' => [
                                'name' => $order_posted ? $cur_topic['poster'] : $cur_topic['last_poster']
                            ],
                            'pubdate' => $order_posted ? $cur_topic['posted'] : $cur_topic['last_post']
                        ];

                        if ($cur_topic['poster_id'] > 1) {
                            if ($cur_topic['email_setting'] == '0' && !User::get()->is_guest) {
                                $item['author']['email'] = $cur_topic['email'];
                            }

                            $item['author']['uri'] = Url::get('user/' . $cur_topic['poster_id'] . '/');
                        } elseif ($cur_topic['poster_email'] != '' && !User::get()->is_guest) {
                            $item['author']['email'] = $cur_topic['poster_email'];
                        }

                        $feed['items'][] = $item;
                    }

                    // Output feed as PHP code
                    if (isset($cache_id)) {
//                        if (!defined('FORUM_CACHE_FUNCTIONS_LOADED')) {
//                            require FORUM_ROOT . 'Helpers/cache.php';
//                        }

                        $content = '<?php' . "\n\n" . '$feed = ' . var_export($feed, true) . ';' . "\n\n" .
                            '$cache_expire = ' . ($now + (ForumSettings::get('o_feed_ttl') * 60)) .
                            ';' . "\n\n" . '?>';
                        $this->writeCacheFile('cache_' . $cache_id . '.php', $content);
                    }
                }

                // If we only want to show a few items but due to caching we have too many
                if (count($feed['items']) > $show) {
                    $feed['items'] = array_slice($feed['items'], 0, $show);
                }

                // Prepend the current base URL onto some links. Done after caching to handle http/https correctly
                $feed['link'] = Url::base() . $feed['link'];

                foreach ($feed['items'] as $key => $item) {
                    $feed['items'][$key]['link'] = $item['link'];

                    if (isset($item['author']['uri'])) {
                        $feed['items'][$key]['author']['uri'] = $item['author']['uri'];
                    }
                }

                $output_func = 'output' . ucfirst($type);
                $this->$output_func($feed);
            }

            exit;
        } // Show users online
        elseif ($action == 'online' || $action == 'online_full') {
            // Fetch users online info and generate strings for output
            $num_guests = $num_users = 0;
            $users = [];

            $select_fetch_users_online = ['user_id', 'ident'];
            $where_fetch_users_online = ['idle' => '0'];
            $order_by_fetch_users_online = 'ident';

            $result = \ORM::for_table(ORM_TABLE_PREFIX . 'online')
                ->select_many($select_fetch_users_online)
                ->where($where_fetch_users_online)
                ->order_by_expr($order_by_fetch_users_online)
                ->find_result_set();

            foreach ($result as $feather_user_online) {
                if ($feather_user_online['user_id'] > 1) {
                    $users[] = (User::get()->g_view_users == '1') ? '<a href="' . Url::get('user/' .
                            $feather_user_online['user_id'] . '/') . '">' .
                        Utils::escape($feather_user_online['ident']) .
                        '</a>' : Utils::escape($feather_user_online['ident']);
                    ++$num_users;
                } else {
                    ++$num_guests;
                }
            }

            // Send the Content-type header in case the web server is setup to send something else
            header('Content-type: text/html; charset=utf-8');
            header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');

            echo sprintf(__('Guests online'), Utils::numberFormat($num_guests)) . '<br />' . "\n";

            if ($action == 'online_full' && !empty($users)) {
                echo sprintf(__('Users online'), implode(', ', $users)) . '<br />' . "\n";
            } else {
                echo sprintf(__('Users online'), Utils::numberFormat($num_users)) . '<br />' . "\n";
            }

            exit;
        } // Show board statistics
        elseif ($action == 'stats') {
            if (!Container::get('cache')->isCached('users_info')) {
                Container::get('cache')->store('users_info', Cache::getUsersInfo());
            }

            $stats = Container::get('cache')->retrieve('users_info');

            $stats_query = \ORM::for_table(ORM_TABLE_PREFIX . 'forums')
                ->select_expr('SUM(num_topics)', 'total_topics')
                ->select_expr('SUM(num_posts)', 'total_posts')
                ->find_one();

            $stats['total_topics'] = intval($stats_query['total_topics']);
            $stats['total_posts'] = intval($stats_query['total_posts']);

            // Send the Content-type header in case the web server is setup to send something else
            header('Content-type: text/html; charset=utf-8');
            header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');

            echo sprintf(__('No of users'), Utils::numberFormat($stats['total_users'])) . '<br />' . "\n";
            echo sprintf(__('Newest user'), ((User::get()->g_view_users == '1') ? '<a href="' . Url::get('user/' .
                        $stats['last_user']['id'] . '/') . '">' .
                    Utils::escape($stats['last_user']['username']) . '</a>' :
                    Utils::escape($stats['last_user']['username']))) . '<br />' . "\n";
            echo sprintf(__('No of topics'), Utils::numberFormat($stats['total_topics'])) . '<br />' . "\n";
            echo sprintf(__('No of posts'), Utils::numberFormat($stats['total_posts'])) . '<br />' . "\n";

            exit;
        }
        // If we end up here, the script was called with some wacky parameters
        exit(__('Bad request'));
    }

    protected function writeCacheFile($file, $content)
    {
        $fh = @fopen(FORUM_CACHE_DIR . $file, 'wb');
        if (!$fh) {
            die('Unable to write cache file ' . Utils::escape($file) .
                ' to cache directory. Please make sure PHP has write access to the directory \'' .
                Utils::escape(FORUM_CACHE_DIR) . '\'');
        }

        flock($fh, LOCK_EX);
        ftruncate($fh, 0);

        fwrite($fh, $content);

        flock($fh, LOCK_UN);
        fclose($fh);

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate(FORUM_CACHE_DIR . $file, true);
        } elseif (function_exists('apc_delete_file')) {
            @apc_delete_file(FORUM_CACHE_DIR . $file);
        }
    }

    /**
     * Converts the CDATA end sequence ]]> into ]]&gt;
     * @param $str
     * @return mixed
     */
    protected function escapeCdata($str)
    {
        return str_replace(']]>', ']]&gt;', $str);
    }

    /**
     * Fill User::get() with default values (for guests)
     */
//    function set_default_user()
//    {
//        $remote_addr = Utils::getIp();
//
//        // Fetch guest user
//        $select_set_default_user = ['u.*', 'g.*', 'o.logged', 'o.last_post', 'o.last_search'];
//        $where_set_default_user = ['u.id' => '1'];
//
//        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'users')
//            ->table_alias('u')
//            ->select_many($select_set_default_user)
//            ->inner_join(ORM_TABLE_PREFIX . 'groups', ['u.group_id', '=', 'g.g_id'], 'g')
//            ->left_outer_join(ORM_TABLE_PREFIX . 'online', ['o.ident', '=', $remote_addr], 'o', true)
//            ->where($where_set_default_user)
//            ->find_result_set();
//
//        if (!$result) {
//            exit('Unable to fetch guest information. Your database must contain both a guest
// user and a guest user group.');
//        }
//
////        foreach ($result as User::get()) ;
//
//        // Update online list
//        if (!User::get()->logged) {
//            User::get()->logged = time();
//
//            // With MySQL/MySQLi/SQLite, REPLACE INTO avoids a user having two rows in the online table
//            switch (ForumSettings::get('db_type')) {
//                case 'mysql':
//                case 'mysqli':
//                case 'mysql_innodb':
//                case 'mysqli_innodb':
//                case 'sqlite':
//                case 'sqlite3':
//                    \ORM::for_table(ORM_TABLE_PREFIX . 'online')->raw_execute('REPLACE INTO ' .
//                        ForumSettings::get('db_prefix') . 'online (user_id, ident, logged)
//  VALUES(1, :ident, :logged)',
//                        [':ident' => $remote_addr, ':logged' => User::get()->logged]);
//                    break;
//
//                default:
//                    \ORM::for_table(ORM_TABLE_PREFIX . 'online')->raw_execute('INSERT INTO ' .
//                        ForumSettings::get('db_prefix') .
//                        'online (user_id, ident, logged) SELECT 1, :ident, :logged
// WHERE NOT EXISTS (SELECT 1 FROM ' .
//                        ForumSettings::get('db_prefix') . 'online WHERE ident=:ident)',
//                        [':ident' => $remote_addr, ':logged' => User::get()->logged]);
//                    break;
//            }
//        } else {
//            \ORM::for_table(ORM_TABLE_PREFIX . 'online')->where('ident', $remote_addr)
////            ->find_one()
//                ->find_result_set()
//                ->set(['logged' => time()])
//                ->save();
//        }
//
//        User::get()->disp_topics = ForumSettings::get('o_disp_topics_default');
//        User::get()->disp_posts = ForumSettings::get('o_disp_posts_default');
//        User::get()->timezone = ForumSettings::get('o_default_timezone');
//        User::get()->dst = ForumSettings::get('o_default_dst');
//        User::get()->language = ForumSettings::get('o_default_lang');
//        User::get()->style = ForumSettings::get('o_default_style');
//        User::get()->is_guest = true;
//        User::get()->is_admmod = false;
//    }

    //
    // Authenticates the provided username and password against the user database
    // $user can be either a user ID (integer) or a username (string)
    // $password can be either a plaintext password or a password hash including salt
    // ($password_is_hash must be set accordingly)
    //
/*
    function authenticate_user($user, $password, $password_is_hash = false)
    {
        // Check if there's a user matching $user and $password
        $select_check_cookie = ['u.*', 'g.*', 'o.logged', 'o.idle'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'users')
            ->table_alias('u')
            ->select_many($select_check_cookie)
            ->inner_join(ORM_TABLE_PREFIX . 'groups', ['u.group_id', '=', 'g.g_id'], 'g')
            ->left_outer_join(ORM_TABLE_PREFIX . 'online', ['o.user_id', '=', 'u.id'], 'o');

        if (is_int($user)) {
            $result = $result->where('u.id', intval($user));
        } else {
            $result = $result->where('u.username', $user);
        }

        $result = $result->find_result_set();

//        foreach ($result as User::get()) {
//        }

        if (!isset(User::get()->id) ||
            ($password_is_hash && $password != User::get()->password) ||
            (!$password_is_hash && \RunBB\Core\Random::hash($password) != User::get()->password)
        ) {
            $this->set_default_user();
        } else {
            User::get()->is_guest = false;
        }

        Lang::load('common');
        Lang::load('index');
    }
*/

    /**
     * Sends the proper headers for Basic HTTP Authentication
     */
    protected function httpAuthenticateUser()
    {
        if (!User::get()->is_guest) {
            return;
        }

        header('WWW-Authenticate: Basic realm="' . ForumSettings::get('o_board_title') . ' External Syndication"');
        header('HTTP/1.0 401 Unauthorized');
    }

    /**
     * Output $feed as RSS 2.0
     * @param $feed array
     */
    protected function outputRss($feed)
    {
        // Send XML/no cache headers
        header('Content-Type: application/xml; charset=utf-8');
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
        echo "\t" . '<channel>' . "\n";
        echo "\t\t" . '<atom:link href="' .
            Utils::escape(Url::current()) . '" rel="self" type="application/rss+xml" />' . "\n";
        echo "\t\t" . '<title><![CDATA[' . $this->escapeCdata($feed['title']) . ']]></title>' . "\n";
        echo "\t\t" . '<link>' . Utils::escape($feed['link']) . '</link>' . "\n";
        echo "\t\t" . '<description><![CDATA[' .
            $this->escapeCdata($feed['description']) . ']]></description>' . "\n";
        echo "\t\t" . '<lastBuildDate>' . gmdate('r', count($feed['items']) ? $feed['items'][0]['pubdate'] :
                time()) . '</lastBuildDate>' . "\n";

        if (ForumSettings::get('o_show_version') == '1') {
            echo "\t\t" . '<generator>RunBB ' . ForumSettings::get('o_cur_version') . '</generator>' . "\n";
        } else {
            echo "\t\t" . '<generator>RunBB</generator>' . "\n";
        }

        foreach ($feed['items'] as $item) {
            echo "\t\t" . '<item>' . "\n";
            echo "\t\t\t" . '<title><![CDATA[' . $this->escapeCdata($item['title']) . ']]></title>' . "\n";
            echo "\t\t\t" . '<link>' . Utils::escape($item['link']) . '</link>' . "\n";
            echo "\t\t\t" . '<description><![CDATA[' .
                $this->escapeCdata($item['description']) . ']]></description>' . "\n";
            echo "\t\t\t" . '<author><![CDATA[' . (isset($item['author']['email']) ?
                    $this->escapeCdata($item['author']['email']) : 'dummy@example.com') . ' (' .
                $this->escapeCdata($item['author']['name']) . ')]]></author>' . "\n";
            echo "\t\t\t" . '<pubDate>' . gmdate('r', $item['pubdate']) . '</pubDate>' . "\n";
            echo "\t\t\t" . '<guid>' . Utils::escape($item['link']) . '</guid>' . "\n";

            echo "\t\t" . '</item>' . "\n";
        }

        echo "\t" . '</channel>' . "\n";
        echo '</rss>' . "\n";
    }

    /**
     * Output $feed as Atom 1.0
     * @param $feed array
     */
    protected function outputAtom($feed)
    {
        // Send XML/no cache headers
        header('Content-Type: application/atom+xml; charset=utf-8');
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        echo '<feed xmlns="http://www.w3.org/2005/Atom">' . "\n";

        echo "\t" . '<title type="html"><![CDATA[' . $this->escapeCdata($feed['title']) . ']]></title>' . "\n";

        echo "\t" . '<link rel="self" href="' . Utils::escape(Url::current()) . '"/>' . "\n";
        echo "\t" . '<link href="' . Utils::escape($feed['link']) . '"/>' . "\n";
        echo "\t" . '<updated>' . gmdate('Y-m-d\TH:i:s\Z', count($feed['items']) ? $feed['items'][0]['pubdate'] :
                time()) . '</updated>' . "\n";

        if (ForumSettings::get('o_show_version') == '1') {
            echo "\t" . '<generator version="' . ForumSettings::get('o_cur_version') . '">RunBB</generator>' . "\n";
        } else {
            echo "\t" . '<generator>RunBB</generator>' . "\n";
        }

        echo "\t" . '<id>' . Utils::escape($feed['link']) . '</id>' . "\n";

        $content_tag = ($feed['type'] == 'posts') ? 'content' : 'summary';

        foreach ($feed['items'] as $item) {
            echo "\t" . '<entry>' . "\n";
            echo "\t\t" . '<title type="html"><![CDATA[' . $this->escapeCdata($item['title']) . ']]></title>' . "\n";
            echo "\t\t" . '<link rel="alternate" href="' . Utils::escape($item['link']) . '"/>' . "\n";
            echo "\t\t" . '<' . $content_tag . ' type="html"><![CDATA[' .
                $this->escapeCdata($item['description']) . ']]></' .
                $content_tag . '>' . "\n";
            echo "\t\t" . '<author>' . "\n";
            echo "\t\t\t" . '<name><![CDATA[' . $this->escapeCdata($item['author']['name']) . ']]></name>' . "\n";

            if (isset($item['author']['email'])) {
                echo "\t\t\t" . '<email><![CDATA[' .
                    $this->escapeCdata($item['author']['email']) . ']]></email>' . "\n";
            }

            if (isset($item['author']['uri'])) {
                echo "\t\t\t" . '<uri>' . Utils::escape($item['author']['uri']) . '</uri>' . "\n";
            }

            echo "\t\t" . '</author>' . "\n";
            echo "\t\t" . '<updated>' . gmdate('Y-m-d\TH:i:s\Z', $item['pubdate']) . '</updated>' . "\n";

            echo "\t\t" . '<id>' . Utils::escape($item['link']) . '</id>' . "\n";
            echo "\t" . '</entry>' . "\n";
        }

        echo '</feed>' . "\n";
    }

    /**
     * Output $feed as XML
     * @param $feed array
     */
    protected function outputXml($feed)
    {
        // Send XML/no cache headers
        header('Content-Type: application/xml; charset=utf-8');
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
        echo '<source>' . "\n";
        echo "\t" . '<url>' . Utils::escape($feed['link']) . '</url>' . "\n";

        $forum_tag = ($feed['type'] == 'posts') ? 'post' : 'topic';

        foreach ($feed['items'] as $item) {
            echo "\t" . '<' . $forum_tag . ' id="' . $item['id'] . '">' . "\n";

            echo "\t\t" . '<title><![CDATA[' . $this->escapeCdata($item['title']) . ']]></title>' . "\n";
            echo "\t\t" . '<link>' . Utils::escape($item['link']) . '</link>' . "\n";
            echo "\t\t" . '<content><![CDATA[' . $this->escapeCdata($item['description']) . ']]></content>' . "\n";
            echo "\t\t" . '<author>' . "\n";
            echo "\t\t\t" . '<name><![CDATA[' . $this->escapeCdata($item['author']['name']) . ']]></name>' . "\n";

            if (isset($item['author']['email'])) {
                echo "\t\t\t" . '<email><![CDATA[' . $this->escapeCdata($item['author']['email']) .
                    ']]></email>' . "\n";
            }

            if (isset($item['author']['uri'])) {
                echo "\t\t\t" . '<uri>' . Utils::escape($item['author']['uri']) . '</uri>' . "\n";
            }

            echo "\t\t" . '</author>' . "\n";
            echo "\t\t" . '<posted>' . gmdate('r', $item['pubdate']) . '</posted>' . "\n";

            echo "\t" . '</' . $forum_tag . '>' . "\n";
        }

        echo '</source>' . "\n";
    }

    /**
     * Output $feed as HTML (using <li> tags)
     * @param $feed array
     */
    protected function outputHtml($feed)
    {
        // Send the Content-type header in case the web server is setup to send something else
        header('Content-type: text/html; charset=utf-8');
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        foreach ($feed['items'] as $item) {
            if (utf8_strlen($item['title']) > FORUM_EXTERN_MAX_SUBJECT_LENGTH) {
                $subject_truncated = Utils::escape(Utils::trim(utf8_substr(
                    $item['title'],
                    0,
                    (FORUM_EXTERN_MAX_SUBJECT_LENGTH - 5)
                ))) . ' …';
            } else {
                $subject_truncated = Utils::escape($item['title']);
            }

            echo '<li><a href="' . Utils::escape($item['link']) . '" title="' . Utils::escape($item['title']) . '">' .
                $subject_truncated . '</a></li>' . "\n";
        }
    }
}
