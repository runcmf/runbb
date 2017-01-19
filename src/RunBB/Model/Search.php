<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Exception\RunBBException;
use RunBB\Core\Track;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Search
{
    public function __construct()
    {
        $this->search = new \RunBB\Core\Search();
    }

    public function getSearchResults()
    {
        $search = [];

        $search = Container::get('hooks')->fire('model.search.get_search_results_start', $search);

        $action = (Input::query('action')) ? Input::query('action') : null;
        $forums = Input::query('forums') ? (is_array(Input::query('forums')) ? Input::query('forums') : array_filter(explode(',', Input::query('forums')))) : (Input::query('forums') ? [Input::query('forums')] : []);
        $sort_dir = (Input::query('sort_dir') && Input::query('sort_dir') == 'DESC') ? 'DESC' : 'ASC';

        $forums = array_map('intval', $forums);

        // Allow the old action names for backwards compatibility reasons
        if ($action == 'show_user') {
            $action = 'show_user_posts';
        } elseif ($action == 'show_24h') {
            $action = 'show_recent';
        }

        // If a search_id was supplied
        if (Input::query('search_id')) {
            $search_id = intval(Input::query('search_id'));
            if ($search_id < 1) {
                throw new RunBBException(__('Bad request'), 400);
            }
        } // If it's a regular search (keywords and/or author)
        elseif ($action == 'search') {
            $keywords = (Input::query('keywords')) ? utf8_strtolower(Utils::trim(Input::query('keywords'))) : null;
            $author = (Input::query('author')) ? utf8_strtolower(Utils::trim(Input::query('author'))) : null;

            if (preg_match('%^[\*\%]+$%', $keywords) || (Utils::strlen(str_replace(['*', '%'], '', $keywords)) < ForumEnv::get('FEATHER_SEARCH_MIN_WORD') && !$this->search->is_cjk($keywords))) {
                $keywords = '';
            }

            if (preg_match('%^[\*\%]+$%', $author) || Utils::strlen(str_replace(['*', '%'], '', $author)) < 2) {
                $author = '';
            }

            if (!$keywords && !$author) {
                throw new  RunBBException(__('No terms'), 400);
            }

            if ($author) {
                $author = str_replace('*', '%', $author);
            }

            $show_as = (Input::query('show_as') && Input::query('show_as') == 'topics') ? 'topics' : 'posts';
            $sort_by = (Input::query('sort_by')) ? intval(Input::query('sort_by')) : 0;
            $search_in = (!Input::query('search_in') || Input::query('search_in') == '0') ? 0 : ((Input::query('search_in') == '1') ? 1 : -1);
        } // If it's a user search (by ID)
        elseif ($action == 'show_user_posts' || $action == 'show_user_topics' || $action == 'show_subscriptions') {
            $user_id = (Input::query('user_id')) ? intval(Input::query('user_id')) : User::get()->id;
            if ($user_id < 2) {
                throw new  RunBBException(__('Bad request'), 404);
            }

            // Subscribed topics can only be viewed by admins, moderators and the users themselves
            if ($action == 'show_subscriptions' && !User::get()->is_admmod && $user_id != User::get()->id) {
                throw new  RunBBException(__('No permission'), 403);
            }
        } elseif ($action == 'show_recent') {
            $interval = Input::query('value') ? intval(Input::query('value')) : 86400;
        } elseif ($action == 'show_replies') {
            if (User::get()->is_guest) {
                throw new  RunBBException(__('Bad request'), 404);
            }
        } elseif ($action != 'show_new' && $action != 'show_unanswered') {
            throw new  RunBBException(__('Bad request'), 404);
        }


        // If a valid search_id was supplied we attempt to fetch the search results from the db
        if (isset($search_id)) {
            $ident = (User::get()->is_guest) ? Utils::getIp() : User::get()->username;

            $search_data = \ORM::for_table(ORM_TABLE_PREFIX.'search_cache')
                ->select('search_data')
                ->where('id', $search_id)
                ->where('ident', $ident);
            $search_data = Container::get('hooks')->fireDB('model.search.get_search_results_search_data_query', $search_data);
            $search_data = $search_data->find_one();

            if ($search_data->search_data) {
                $temp = unserialize($search_data->search_data);
                $temp = Container::get('hooks')->fire('model.search.get_search_results_temp', $temp);

                $search_ids = unserialize($temp['search_ids']);
                $num_hits = $temp['num_hits'];
                $sort_by = $temp['sort_by'];
                $sort_dir = $temp['sort_dir'];
                $show_as = $temp['show_as'];
                $search_type = $temp['search_type'];

                unset($temp);
            } else {
                throw new  RunBBException(__('No hits'), 404);
            }
        } else {
            $keyword_results = $author_results = [];

            // Search a specific forum?
            $forum_sql = (!empty($forums) || (empty($forums) && ForumSettings::get('o_search_all_forums') == '0' && !User::get()->is_admmod)) ? ' AND t.forum_id IN ('.implode(',', $forums).')' : '';

            if (!empty($author) || !empty($keywords)) {
                // Flood protection
                if (User::get()->last_search && (time() - User::get()->last_search) < User::get()->g_search_flood && (time() - User::get()->last_search) >= 0) {
                    throw new  RunBBException(sprintf(__('Search flood'), User::get()->g_search_flood, User::get()->g_search_flood - (time() - User::get()->last_search)), 429);
                }

                if (!User::get()->is_guest) {
                    $where = [
                        'table' => ORM_TABLE_PREFIX.'users',
                        'id' => 'id',
                        'val' => User::get()->id
                    ];
                } else {
                    $where = [
                        'table' => ORM_TABLE_PREFIX.'online',
                        'id' => 'ident',
                        'val' => Utils::getIp()
                    ];
                }
                $where = Container::get('hooks')->fireDB('model.search.get_search_results_update_last_search', $where);
                \ORM::for_table($where['table'])
                    ->where($where['id'], $where['val'])
                    ->find_one()
                    ->set(['last_search' => time()])
                    ->save();


                switch ($sort_by) {
                    case 1:
                        $sort_by_sql = ($show_as == 'topics') ? 't.poster' : 'p.poster';
                        $sort_type = SORT_STRING;
                        break;

                    case 2:
                        $sort_by_sql = 't.subject';
                        $sort_type = SORT_STRING;
                        break;

                    case 3:
                        $sort_by_sql = 't.forum_id';
                        $sort_type = SORT_NUMERIC;
                        break;

                    case 4:
                        $sort_by_sql = 't.last_post';
                        $sort_type = SORT_NUMERIC;
                        break;

                    default:
                        $sort_by_sql = ($show_as == 'topics') ? 't.last_post' : 'p.posted';
                        $sort_type = SORT_NUMERIC;
                        break;
                }

                $sort_by = Container::get('hooks')->fire('model.search.get_search_results_sort_by', $sort_by);

                // If it's a search for keywords
                if ($keywords) {
                    // split the keywords into words
                    $keywords_array = $this->search->split_words($keywords, false);
                    $keywords_array = Container::get('hooks')->fire('model.search.get_search_results_keywords_array', $keywords_array);

                    if (empty($keywords_array)) {
                        throw new  RunBBException(__('No hits'), 400);
                    }

                    // Should we search in message body or topic subject specifically?
                    $search_in_cond = ($search_in) ? (($search_in > 0) ? ' AND m.subject_match = 0' : ' AND m.subject_match = 1') : '';
                    $search_in_cond = Container::get('hooks')->fire('model.search.get_search_results_search_cond', $search_in_cond);

                    $word_count = 0;
                    $match_type = 'and';

                    $sort_data = [];
                    foreach ($keywords_array as $cur_word) {
                        switch ($cur_word) {
                            case 'and':
                            case 'or':
                            case 'not':
                                $match_type = $cur_word;
                                break;

                            default:
                            {
                                if ($this->search->is_cjk($cur_word)) {
                                    $where_cond = str_replace('*', '%', $cur_word);
                                    $where_cond_cjk = ($search_in ? (($search_in > 0) ? 'p.message LIKE %:where_cond%' : 't.subject LIKE %:where_cond%') : 'p.message LIKE %:where_cond% OR t.subject LIKE %:where_cond%');

                                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->raw_query('SELECT p.id AS post_id, p.topic_id, '.$sort_by_sql.' AS sort_by FROM '.ForumSettings::get('db_prefix').'posts AS p INNER JOIN '.ForumSettings::get('db_prefix').'topics AS t ON t.id=p.topic_id LEFT JOIN '.ForumSettings::get('db_prefix').'forum_perms AS fp ON (fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.') WHERE ('.$where_cond_cjk.') AND (fp.read_forum IS NULL OR fp.read_forum=1)'.$forum_sql, [':where_cond' => $where_cond]);
                                } else {
                                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->raw_query('SELECT m.post_id, p.topic_id, '.$sort_by_sql.' AS sort_by FROM '.ForumSettings::get('db_prefix').'search_words AS w INNER JOIN '.ForumSettings::get('db_prefix').'search_matches AS m ON m.word_id = w.id INNER JOIN '.ForumSettings::get('db_prefix').'posts AS p ON p.id=m.post_id INNER JOIN '.ForumSettings::get('db_prefix').'topics AS t ON t.id=p.topic_id LEFT JOIN '.ForumSettings::get('db_prefix').'forum_perms AS fp ON (fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.') WHERE w.word LIKE :where_cond'.$search_in_cond.' AND (fp.read_forum IS NULL OR fp.read_forum=1)'.$forum_sql, [':where_cond' => str_replace('*', '%', $cur_word)]);
                                }

                                $result = Container::get('hooks')->fireDB('model.search.get_search_results_search_first_query', $result);
                                $result = $result->find_many();

                                $row = [];
                                foreach ($result as $temp) {
                                    $row[$temp['post_id']] = $temp['topic_id'];

                                    if (!$word_count) {
                                        $keyword_results[$temp['post_id']] = $temp['topic_id'];
                                        $sort_data[$temp['post_id']] = $temp['sort_by'];
                                    } elseif ($match_type == 'or') {
                                        $keyword_results[$temp['post_id']] = $temp['topic_id'];
                                        $sort_data[$temp['post_id']] = $temp['sort_by'];
                                    } elseif ($match_type == 'not') {
                                        unset($keyword_results[$temp['post_id']]);
                                        unset($sort_data[$temp['post_id']]);
                                    }
                                }

                                if ($match_type == 'and' && $word_count) {
                                    foreach ($keyword_results as $post_id => $topic_id) {
                                        if (!isset($row[$post_id])) {
                                            unset($keyword_results[$post_id]);
                                            unset($sort_data[$post_id]);
                                        }
                                    }
                                }

                                ++$word_count;
                                $pdo = \ORM::get_db();
                                $pdo = null;

                                break;
                            }
                        }
                    }

                    $keyword_results = Container::get('hooks')->fire('model.search.get_search_results_search_keyword_results', $keyword_results);
                    // Sort the results - annoyingly array_multisort re-indexes arrays with numeric keys, so we need to split the keys out into a separate array then combine them again after
                    $post_ids = array_keys($keyword_results);
                    $topic_ids = array_values($keyword_results);

                    array_multisort(array_values($sort_data), $sort_dir == 'DESC' ? SORT_DESC : SORT_ASC, $sort_type, $post_ids, $topic_ids);

                    // combine the arrays back into a key => value array
                    $keyword_results = array_combine($post_ids, $topic_ids);

                    unset($sort_data, $post_ids, $topic_ids);
                }

                // If it's a search for author name (and that author name isn't Guest)
                if ($author && $author != 'guest' && $author != utf8_strtolower(__('Guest'))) {
                    $username_exists = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                                        ->select('id')
                                        ->where_like('username', $author);
                    $username_exists = Container::get('hooks')->fireDB('model.search.get_search_results_username_exists', $username_exists);
                    $username_exists = $username_exists->find_many();

                    if ($username_exists) {
                        $user_ids = [];
                        foreach ($username_exists as $row) {
                            $user_ids[] = $row['id'];
                        }

                        $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->raw_query('SELECT p.id AS post_id, p.topic_id FROM '.ForumSettings::get('db_prefix').'posts AS p INNER JOIN '.ForumSettings::get('db_prefix').'topics AS t ON t.id=p.topic_id LEFT JOIN '.ForumSettings::get('db_prefix').'forum_perms AS fp ON (fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND p.poster_id IN('.implode(',', $user_ids).')'.$forum_sql.' ORDER BY '.$sort_by_sql.' '.$sort_dir);
                        $result = Container::get('hooks')->fireDB('model.search.get_search_results_search_second_query', $result);
                        $result = $result->find_many();

                        foreach ($result as $temp) {
                            $author_results[$temp['post_id']] = $temp['topic_id'];
                        }

                        $pdo = \ORM::get_db();
                        $pdo = null;
                    }
                }

                // If we searched for both keywords and author name we want the intersection between the results
                if ($author && $keywords) {
                    $search_ids = array_intersect_assoc($keyword_results, $author_results);
                    $search_type = ['both', [$keywords, Utils::trim(Input::query('author'))], implode(',', $forums), $search_in];
                } elseif ($keywords) {
                    $search_ids = $keyword_results;
                    $search_type = ['keywords', $keywords, implode(',', $forums), $search_in];
                } else {
                    $search_ids = $author_results;
                    $search_type = ['author', Utils::trim(Input::query('author')), implode(',', $forums), $search_in];
                }

                $search_ids = Container::get('hooks')->fire('model.search.get_search_results_search_ids', $search_ids);
                $search_type = Container::get('hooks')->fire('model.search.get_search_results_search_type', $search_type);

                unset($keyword_results, $author_results);

                if ($show_as == 'topics') {
                    $search_ids = array_values($search_ids);
                } else {
                    $search_ids = array_keys($search_ids);
                }

                $search_ids = array_unique($search_ids);

                $search_ids = Container::get('hooks')->fire('model.search.get_search_results_search_ids', $search_ids);
                $search_type = Container::get('hooks')->fire('model.search.get_search_results_search_type', $search_type);

                $num_hits = count($search_ids);
                if (!$num_hits) {
                    throw new  RunBBException(__('No hits'), 400);
                }
            } elseif ($action == 'show_new' || $action == 'show_recent' || $action == 'show_replies' || $action == 'show_user_posts' || $action == 'show_user_topics' || $action == 'show_subscriptions' || $action == 'show_unanswered') {
                $search_type = ['action', $action];
                $show_as = 'topics';
                // We want to sort things after last post
                $sort_by = 0;
                $sort_dir = 'DESC';

                $result['where'] = 'fp.read_forum IS NULL OR fp.read_forum=1';
//                    array(
//                    array('fp.read_forum' => 'IS NULL'),
//                    array('fp.read_forum' => '1')
//                );

                // If it's a search for new posts since last visit
                if ($action == 'show_new') {
                    if (User::get()->is_guest) {
                        throw new  RunBBException(__('No permission'), 403);
                    }

                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                                ->table_alias('t')
                                ->select('t.id')
//                                ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                                ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX.'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.')',
                            'fp'
                        )
                                ->where_raw($result['where'])
                                ->where_gt('t.last_post', User::get()->last_visit)
                                ->where_null('t.moved_to')
                                ->order_by_desc('t.last_post');


                    if (Input::query('fid')) {
                        $result = $result->where('t.forum_id', intval(Input::query('fid')));
                    }

                    $result = Container::get('hooks')->fireDB('model.search.get_search_results_topic_query', $result);
                    $result = $result->find_many();

                    $num_hits = count($result);

                    if (!$num_hits) {
                        return Router::redirect(Router::pathFor('home'), __('No new posts'));
                    }
                } // If it's a search for recent posts (in a certain time interval)
                elseif ($action == 'show_recent') {
                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                                ->table_alias('t')
                                ->select('t.id')
//                                ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                                ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX.'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.')',
                            'fp'
                        )
                                ->where_raw($result['where'])
                                ->where_gt('t.last_post', time() - $interval)
                                ->where_null('t.moved_to')
                                ->order_by_desc('t.last_post');

                    if (Input::query('fid')) {
                        $result = $result->where('t.forum_id', intval(Input::query('fid')));
                    }

                    $result = Container::get('hooks')->fireDB('model.search.get_search_results_topic_query', $result);
                    $result = $result->find_many();

                    $num_hits = count($result);

                    if (!$num_hits) {
                        return Router::redirect(Router::pathFor('home'), __('No recent posts'));
                    }
                } // If it's a search for topics in which the user has posted
                elseif ($action == 'show_replies') {
                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                                ->table_alias('t')
                                ->select('t.id')
                                ->inner_join(ORM_TABLE_PREFIX.'posts', ['t.id', '=', 'p.topic_id'], 'p')
//                                ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                                ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX.'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.')',
                            'fp'
                        )
                                ->where_raw($result['where'])
                                ->where('p.poster_id', User::get()->id)
                                ->group_by('t.id');

                    if (ForumSettings::get('db_type') == 'pgsql') {
                        $result = $result->group_by('t.last_post');
                    }

                    $result = Container::get('hooks')->fireDB('model.search.get_search_results_topic_query', $result);
                    $result = $result->find_many();

                    $num_hits = count($result);

                    if (!$num_hits) {
                        return Router::redirect(Router::pathFor('home'), __('No user posts'));
                    }
                } // If it's a search for posts by a specific user ID
                elseif ($action == 'show_user_posts') {
                    $show_as = 'posts';

                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                                ->table_alias('p')
                                ->select('p.id')
                                ->inner_join(ORM_TABLE_PREFIX.'topics', ['p.topic_id', '=', 't.id'], 't')
//                                ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                                ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX.'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.')',
                            'fp'
                        )
                                ->where_raw($result['where'])
                                ->where('p.poster_id', $user_id)
                                ->order_by_desc('p.posted');

                    $result = Container::get('hooks')->fireDB('model.search.get_search_results_post_query', $result);
                    $result = $result->find_many();

                    $num_hits = count($result);

                    if (!$num_hits) {
                        return Router::redirect(Router::pathFor('search'), __('No user posts'));
                    }

                    // Pass on the user ID so that we can later know whose posts we're searching for
                    $search_type[2] = $user_id;
                } // If it's a search for topics by a specific user ID
                elseif ($action == 'show_user_topics') {
                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                                ->table_alias('t')
                                ->select('t.id')
                                ->inner_join(ORM_TABLE_PREFIX.'posts', ['t.first_post_id', '=', 'p.id'], 'p')
//                                ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                                ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX.'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.')',
                            'fp'
                        )
                                ->where_raw($result['where'])
                                ->where('p.poster_id', $user_id)
                                ->order_by_desc('t.last_post');

                    $result = Container::get('hooks')->fireDB('model.search.get_search_results_topic_query', $result);
                    $result = $result->find_many();

                    $num_hits = count($result);

                    if (!$num_hits) {
                        return Router::redirect(Router::pathFor('search'), __('No user topics'));
                    }

                    // Pass on the user ID so that we can later know whose topics we're searching for
                    $search_type[2] = $user_id;
                } // If it's a search for subscribed topics
                elseif ($action == 'show_subscriptions') {
                    if (User::get()->is_guest) {
                        throw new  RunBBException(__('Bad request'), 404);
                    }

                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                                ->table_alias('t')
                                ->select('t.id')
//                                ->inner_join('topic_subscriptions', array('t.id', '=', 's.topic_id'), 's')
//                                ->inner_join('topic_subscriptions', array('s.user_id', '=', $user_id), null, true)
                        ->inner_join(
                            ORM_TABLE_PREFIX.'topic_subscriptions',
                            '(t.id=s.topic_id AND s.user_id='.$user_id.')',
                            's'
                        )
//                                ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                                ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX.'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.')',
                            'fp'
                        )
                                ->where_raw($result['where'])
                                ->order_by_desc('t.last_post');

                    $result = Container::get('hooks')->fireDB('model.search.get_search_results_topic_query', $result);
                    $result = $result->find_many();

                    $num_hits = count($result);

                    if (!$num_hits) {
                        return Router::redirect(Router::pathFor('search'), __('No subscriptions'));
                    }

                    // Pass on user ID so that we can later know whose subscriptions we're searching for
                    $search_type[2] = $user_id;
                } // If it's a search for unanswered posts
                else {
                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                                ->table_alias('t')
                                ->select('t.id')
//                                ->left_outer_join('forum_perms', array('fp.forum_id', '=', 't.forum_id'), 'fp')
//                                ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                        ->left_outer_join(
                            ORM_TABLE_PREFIX.'forum_perms',
                            '(fp.forum_id=t.forum_id AND fp.group_id='.User::get()->g_id.')',
                            'fp'
                        )
                                ->where('t.num_replies', 0)
                                ->where_null('t.moved_to')
                                ->where_raw($result['where'])
                                ->order_by_desc('t.last_post');

                    $result = Container::get('hooks')->fireDB('model.search.get_search_results_topic_query', $result);
                    $result = $result->find_many();

                    $num_hits = count($result);

                    if (!$num_hits) {
                        return Router::redirect(Router::pathFor('home'), __('No unanswered'));
                    }
                }

                $search_ids = [];
                foreach ($result as $row) {
                    $search_ids[] = $row['id'];
                }

                $pdo = \ORM::get_db();
                $pdo = null;
            } else {
                throw new  RunBBException(__('Bad request'), 404);
            }


            // Prune "old" search results
            $old_searches = [];
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'online')
                        ->select('ident');
            $result = Container::get('hooks')->fireDB('model.search.get_search_results_prune_search', $result);
            $result = $result->find_many();

            if ($result) {
                foreach ($result as $row) {
                    $old_searches[] = $row['ident'];
                }

                $delete_cache = \ORM::for_table(ORM_TABLE_PREFIX.'search_cache')
                                    ->where_not_in('ident', $old_searches);
                $delete_cache = Container::get('hooks')->fireDB('model.search.get_search_results_delete_cache', $delete_cache);
                $delete_cache = $delete_cache->delete_many();
            }

            // Fill an array with our results and search properties
            $temp = serialize([
                'search_ids'        => serialize($search_ids),
                'num_hits'            => $num_hits,
                'sort_by'            => $sort_by,
                'sort_dir'            => $sort_dir,
                'show_as'            => $show_as,
                'search_type'        => $search_type
            ]);
            $search_id = mt_rand(1, 2147483647);

            $ident = (User::get()->is_guest) ? Utils::getIp() : User::get()->username;

            $cache['insert'] = [
                'id'   =>  $search_id,
                'ident'  =>  $ident,
                'search_data'  =>  $temp,
            ];

            $cache = \ORM::for_table(ORM_TABLE_PREFIX.'search_cache')
                        ->create()
                        ->set($cache['insert']);
            $cache = Container::get('hooks')->fireDB('model.search.get_search_results_update_cache', $cache);
            $cache = $cache->save();
        }

        // If we're on the new posts search, display a "mark all as read" link
        if (!User::get()->is_guest && $search_type[0] == 'action' && $search_type[1] == 'show_new') {
            $search['forum_actions'][] = '<a href="'.Router::pathFor('markRead').'">'.__('Mark all as read').'</a>';
        }

        // Fetch results to display
        if (!empty($search_ids)) {
            // We have results
            $search['is_result'] = true;

            switch ($sort_by) {
                case 1:
                    $sort_by_sql = ($show_as == 'topics') ? 't.poster' : 'p.poster';
                    break;

                case 2:
                    $sort_by_sql = 't.subject';
                    break;

                case 3:
                    $sort_by_sql = 't.forum_id';
                    break;

                default:
                    $sort_by_sql = ($show_as == 'topics') ? 't.last_post' : 'p.posted';
                    break;
            }

            // Determine the topic or post offset (based on $_GET['p'])
            $per_page = ($show_as == 'posts') ? User::get()->disp_posts : User::get()->disp_topics;
            $num_pages = ceil($num_hits / $per_page);

            $p = (!Input::query('p') || Input::query('p') <= 1 || Input::query('p') > $num_pages) ? 1 : intval(Input::query('p'));
            $start_from = $per_page * ($p - 1);
            $search['start_from'] = $start_from;

            // Generate paging links
            $search['paging_links'] = '<span class="pages-label">'.__('Pages').' </span>'.Url::paginate_old($num_pages, $p, '?search_id='.$search_id);

            // throw away the first $start_from of $search_ids, only keep the top $per_page of $search_ids
            $search_ids = array_slice($search_ids, $start_from, $per_page);

            // Run the query and fetch the results
            if ($show_as == 'posts') {
                $result['select'] = ['pid' => 'p.id', 'pposter' => 'p.poster', 'pposted' => 'p.posted', 'p.poster_id', 'p.message', 'p.hide_smilies', 'tid' => 't.id', 't.poster', 't.subject', 't.first_post_id', 't.last_post', 't.last_post_id', 't.last_poster', 't.num_replies', 't.forum_id', 'f.forum_name'];

                $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                                ->table_alias('p')
                                ->select_many($result['select'])
                                ->inner_join(ORM_TABLE_PREFIX.'topics', ['t.id', '=', 'p.topic_id'], 't')
                                ->inner_join(ORM_TABLE_PREFIX.'forums', ['f.id', '=', 't.forum_id'], 'f')
                                ->where_in('p.id', $search_ids)
                                ->order_by_expr($sort_by_sql.' '.$sort_dir);
                $result = Container::get('hooks')->fireDB('model.search.get_search_results_select_posts_query', $result);
            } else {
                $result['select'] = ['tid' => 't.id', 't.poster', 't.subject', 't.last_post', 't.last_post_id', 't.last_poster', 't.num_replies', 't.closed', 't.sticky', 't.forum_id', 'f.forum_name'];

                $result = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                                ->table_alias('t')
                                ->select_many($result['select'])
                                ->inner_join(ORM_TABLE_PREFIX.'forums', ['f.id', '=', 't.forum_id'], 'f')
                                ->where_in('t.id', $search_ids)
                                ->order_by_expr($sort_by_sql.' '.$sort_dir);
                $result = Container::get('hooks')->fireDB('model.search.get_search_results_select_topics_query', $result);
            }
            $result = $result->find_array();
            $search['search_set'] = [];
            foreach ($result as $row) {
                $search['search_set'][] = $row;
            }

            $search['crumbs_text']['show_as'] = __('Search');

            if ($search_type[0] == 'action') {
                if ($search_type[1] == 'show_user_topics') {
                    $search['crumbs_text']['search_type'] = '<a href="'.Router::pathFor('search').'?action=show_user_topics&amp;user_id='.$search_type[2].'">'.sprintf(__('Quick search show_user_topics'), Utils::escape($search['search_set'][0]['poster'])).'</a>';
                } elseif ($search_type[1] == 'show_user_posts') {
                    $search['crumbs_text']['search_type'] = '<a href="'.Router::pathFor('search').'?action=show_user_posts&amp;user_id='.$search_type[2].'">'.sprintf(__('Quick search show_user_posts'), Utils::escape($search['search_set'][0]['pposter'])).'</a>';
                } elseif ($search_type[1] == 'show_subscriptions') {
                    // Fetch username of subscriber
                    $subscriber_id = $search_type[2];
                    $subscriber_name = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                        ->select('username')
                        ->where('id', $subscriber_id);
                    $subscriber_name = Container::get('hooks')->fireDB('model.search.get_search_results_subscriber_name', $result);
                    $subscriber_name = $subscriber_name->find_one();

//FIXME rebuild all one with  ->find_one($id)

                    if (!$subscriber_name->username) {
                        throw new  RunBBException(__('Bad request'), 404);
                    }

                    $search['crumbs_text']['search_type'] = '<a href="'.Router::pathFor('search').'?action=show_subscription&amp;user_id='.$subscriber_id.'">'.sprintf(__('Quick search show_subscriptions'), Utils::escape($subscriber_name->username)).'</a>';
                } else {
                    $search_url = str_replace('_', '/', $search_type[1]);
                    $search['crumbs_text']['search_type'] = '<a href="'.Router::pathFor('search').$search_url.'">'.__('Quick search '.$search_type[1]).'</a>';
                }
            } else {
                $keywords = $author = '';

                if ($search_type[0] == 'both') {
                    list($keywords, $author) = $search_type[1];
                    $search['crumbs_text']['search_type'] = sprintf(__('By both show as '.$show_as), Utils::escape($keywords), Utils::escape($author));
                } elseif ($search_type[0] == 'keywords') {
                    $keywords = $search_type[1];
                    $search['crumbs_text']['search_type'] = sprintf(__('By keywords show as '.$show_as), Utils::escape($keywords));
                } elseif ($search_type[0] == 'author') {
                    $author = $search_type[1];
                    $search['crumbs_text']['search_type'] = sprintf(__('By user show as '.$show_as), Utils::escape($author));
                }

                $search['crumbs_text']['search_type'] = '<a href="'.Router::pathFor('search').'?action=search&amp;keywords='.urlencode($keywords).'&amp;author='.urlencode($author).'&amp;forums='.$search_type[2].'&amp;search_in='.$search_type[3].'&amp;sort_by='.$sort_by.'&amp;sort_dir='.$sort_dir.'&amp;show_as='.$show_as.'">'.$search['crumbs_text']['search_type'].'</a>';
            }
        }

        $search['show_as'] = $show_as;

        $search = Container::get('hooks')->fire('model.search.get_search_results', $search);

        return $search;
    }

    public function displaySearchResults($search)
    {
        $search = Container::get('hooks')->fire('model.search.display_search_results_start', $search);

        // Get topic/forum tracking data
        if (!User::get()->is_guest) {
            $tracked_topics = Track::get_tracked_topics();
        }

        $post_count = $topic_count = 0;

        $display = [];

        foreach ($search['search_set'] as $cur_search) {
            $forum_name = Url::url_friendly($cur_search['forum_name']);
            $forum = '<a href="'.Router::pathFor('Forum', ['id' => $cur_search['forum_id'], 'name' => $forum_name]).'">'.Utils::escape($cur_search['forum_name']).'</a>';
            $url_topic = Url::url_friendly($cur_search['subject']);

            if (ForumSettings::get('o_censoring') == '1') {
                $cur_search['subject'] = Utils::censor($cur_search['subject']);
            }

            if ($search['show_as'] == 'posts') {
                ++$post_count;
                $cur_search['icon_type'] = 'icon';

                if (!User::get()->is_guest && $cur_search['last_post'] > User::get()->last_visit && (!isset($tracked_topics['topics'][$cur_search['tid']]) || $tracked_topics['topics'][$cur_search['tid']] < $cur_search['last_post']) && (!isset($tracked_topics['forums'][$cur_search['forum_id']]) || $tracked_topics['forums'][$cur_search['forum_id']] < $cur_search['last_post'])) {
                    $cur_search['item_status'] = 'inew';
                    $cur_search['icon_type'] = 'icon icon-new';
                    $cur_search['icon_text'] = __('New icon');
                } else {
                    $cur_search['item_status'] = '';
                    $cur_search['icon_text'] = '<!-- -->';
                }

                if (ForumSettings::get('o_censoring') == '1') {
                    $cur_search['message'] = Utils::censor($cur_search['message']);
                }

                $cur_search['message'] = Container::get('parser')->parseMessage($cur_search['message'], $cur_search['hide_smilies']);
                $pposter = Utils::escape($cur_search['pposter']);

                if ($cur_search['poster_id'] > 1 && User::get()->g_view_users == '1') {
                    $cur_search['pposter_disp'] = '<strong><a href="'.Router::pathFor('userProfile', ['id' => $cur_search['poster_id']]).'">'.$pposter.'</a></strong>';
                } else {
                    $cur_search['pposter_disp'] = '<strong>'.$pposter.'</strong>';
                }
            } else {
                ++$topic_count;
                $status_text = [];
                $cur_search['item_status'] = ($topic_count % 2 == 0) ? 'roweven' : 'rowodd';
                $cur_search['icon_type'] = 'icon';

                $subject = '<a href="'.Router::pathFor('Topic', ['id' => $cur_search['tid'], 'name' => $url_topic]).'">'.Utils::escape($cur_search['subject']).'</a> <span class="byuser">'.__('by').' '.Utils::escape($cur_search['poster']).'</span>';

                if ($cur_search['sticky'] == '1') {
                    $cur_search['item_status'] .= ' isticky';
                    $status_text[] = '<span class="stickytext">'.__('Sticky').'</span>';
                }

                if ($cur_search['closed'] != '0') {
                    $status_text[] = '<span class="closedtext">'.__('Closed').'</span>';
                    $cur_search['item_status'] .= ' iclosed';
                }

                if (!User::get()->is_guest && $cur_search['last_post'] > User::get()->last_visit && (!isset($tracked_topics['topics'][$cur_search['tid']]) || $tracked_topics['topics'][$cur_search['tid']] < $cur_search['last_post']) && (!isset($tracked_topics['forums'][$cur_search['forum_id']]) || $tracked_topics['forums'][$cur_search['forum_id']] < $cur_search['last_post'])) {
                    $cur_search['item_status'] .= ' inew';
                    $cur_search['icon_type'] = 'icon icon-new';
                    $subject = '<strong>'.$subject.'</strong>';
                    $subject_new_posts = '<span class="newtext">[ <a href="'.Router::pathFor('topicAction', ['id' => $cur_search['tid'], 'action' => 'new']).'" title="'.__('New posts info').'">'.__('New posts').'</a> ]</span>';
                } else {
                    $subject_new_posts = null;
                }

                // Insert the status text before the subject
                $subject = implode(' ', $status_text).' '.$subject;

                $num_pages_topic = ceil(($cur_search['num_replies'] + 1) / User::get()->disp_posts);

                if ($num_pages_topic > 1) {// FIXME rebuild url
                    $subject_multipage = '<span class="pagestext">[ '.Url::paginate($num_pages_topic, -1, 'topic/'.$cur_search['tid'].'/'.$url_topic.'/#').' ]</span>';
                } else {
                    $subject_multipage = null;
                }

                // Should we show the "New posts" and/or the multipage links?
                if (!empty($subject_new_posts) || !empty($subject_multipage)) {
                    $subject .= !empty($subject_new_posts) ? ' '.$subject_new_posts : '';
                    $subject .= !empty($subject_multipage) ? ' '.$subject_multipage : '';
                }

                if (!isset($cur_search['start_from'])) {
                    $cur_search['start_from'] = 0;
                }

                $cur_search['topic_count'] = $topic_count;
                $cur_search['subject'] = $subject;
            }

            $cur_search['post_count'] = $post_count;
            $cur_search['forum'] = $forum;
            $cur_search['url_topic'] = $url_topic;

            $display['cur_search'][] = $cur_search;
        }
        $display = Container::get('hooks')->fire('model.search.display_search_results', $display, $search);

        return $display;
    }

    public function get_list_forums()
    {
        $output = '';

        $output = Container::get('hooks')->fire('model.search.get_list_forums_start', $output);

        $result['select'] = ['cid' => 'c.id', 'c.cat_name', 'fid' => 'f.id', 'f.forum_name', 'f.redirect_url'];
//        $result['where'] = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );
        $result['order_by'] = 'c.disp_position, c.id, f.disp_position';

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'categories')
                    ->table_alias('c')
                    ->select_many($result['select'])
                    ->inner_join(ORM_TABLE_PREFIX.'forums', ['c.id', '=', 'f.cat_id'], 'f')
//                    ->left_outer_join('forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//                    ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(fp.forum_id=f.id AND fp.group_id='.User::get()->g_id.')',
                'fp'
            )
//                    ->where_any_is($result['where'])
                ->where_raw('fp.read_forum IS NULL OR fp.read_forum=1')
                    ->where_null('f.redirect_url')
                    ->order_by_expr($result['order_by']);
        $result = Container::get('hooks')->fireDB('model.search.get_list_forums_query', $result);
        $result = $result->find_many();

        // We either show a list of forums of which multiple can be selected
        if (ForumSettings::get('o_search_all_forums') == '1' || User::get()->is_admmod) {
            $output .= "\t\t\t\t\t\t".'<div class="conl multiselect">'.__('Forum search')."\n";
            $output .= "\t\t\t\t\t\t".'<br />'."\n";
            $output .= "\t\t\t\t\t\t".'<div class="checklist">'."\n";

            $cur_category = 0;
            foreach ($result as $cur_forum) {
                if ($cur_forum['cid'] != $cur_category) {
                    // A new category since last iteration?

                    if ($cur_category) {
                        $output .= "\t\t\t\t\t\t\t\t".'</div>'."\n";
                        $output .= "\t\t\t\t\t\t\t".'</fieldset>'."\n";
                    }

                    $output .= "\t\t\t\t\t\t\t".'<fieldset><legend><span>'.Utils::escape($cur_forum['cat_name']).'</span></legend>'."\n";
                    $output .= "\t\t\t\t\t\t\t\t".'<div class="rbox">';
                    $cur_category = $cur_forum['cid'];
                }

                $output .= "\t\t\t\t\t\t\t\t".'<label><input type="checkbox" name="forums[]" id="forum-'.$cur_forum['fid'].'" value="'.$cur_forum['fid'].'" />'.Utils::escape($cur_forum['forum_name']).'</label>'."\n";
            }

            if ($cur_category) {
                $output .= "\t\t\t\t\t\t\t\t".'</div>'."\n";
                $output .= "\t\t\t\t\t\t\t".'</fieldset>'."\n";
            }

            $output .= "\t\t\t\t\t\t".'</div>'."\n";
            $output .= "\t\t\t\t\t\t".'</div>'."\n";
        } // ... or a simple select list for one forum only
        else {
            $output .= "\t\t\t\t\t\t".'<label class="conl">'.__('Forum search')."\n";
            $output .= "\t\t\t\t\t\t".'<br />'."\n";
            $output .= "\t\t\t\t\t\t".'<select id="forum" name="forum">'."\n";

            $cur_category = 0;
            foreach ($result as $cur_forum) {
                if ($cur_forum['cid'] != $cur_category) {
                    // A new category since last iteration?

                    if ($cur_category) {
                        $output .= "\t\t\t\t\t\t\t".'</optgroup>'."\n";
                    }

                    $output .= "\t\t\t\t\t\t\t".'<optgroup label="'.Utils::escape($cur_forum['cat_name']).'">'."\n";
                    $cur_category = $cur_forum['cid'];
                }

                $output .= "\t\t\t\t\t\t\t\t".'<option value="'.$cur_forum['fid'].'">'.Utils::escape($cur_forum['forum_name']).'</option>'."\n";
            }

            $output .= "\t\t\t\t\t\t\t".'</optgroup>'."\n";
            $output .= "\t\t\t\t\t\t".'</select>'."\n";
            $output .= "\t\t\t\t\t\t".'<br /></label>'."\n";
        }

        $output = Container::get('hooks')->fire('model.search.get_list_forums', $output);

        return $output;
    }
}
