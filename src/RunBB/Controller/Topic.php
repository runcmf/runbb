<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller;

use RunBB\Exception\RunBBException;
use RunBB\Core\Track;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Topic
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Topic();
        translate('topic');
//        translate('misc'); // To be removed
        translate('post');
    }

    public function display($req, $res, $args)
    {
        if (!isset($args['page'])) {
            $args['page'] = null;
        }

        if (!isset($args['pid'])) {
            $args['pid'] = null;
        }

        if (!isset($args['name'])) {
            $args['name'] = null;
        }

        Container::get('hooks')->fire('controller.topic.display', $args['id'], $args['name'], $args['page'], $args['pid']);

        // Antispam feature
        $lang_antispam_questions = require ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.'/antispam.php';
        $index_questions = rand(0, count($lang_antispam_questions)-1);

        // Fetch some informations about the topic
        $cur_topic = $this->model->get_info_topic($args['id']);

        // Sort out who the moderators are and if we are currently a moderator (or an admin)
        $mods_array = ($cur_topic['moderators'] != '') ? unserialize($cur_topic['moderators']) : [];
        $is_admmod = (User::get()->g_id == ForumEnv::get('FEATHER_ADMIN') || (User::get()->g_moderator == '1' &&
                array_key_exists(User::get()->username, $mods_array))) ? true : false;

        // Can we or can we not post replies?
        $post_link = $this->model->get_post_link($args['id'], $cur_topic['closed'], $cur_topic['post_replies'], $is_admmod);

        // Add/update this topic in our list of tracked topics
        if (!User::get()->is_guest) {
            $tracked_topics = Track::get_tracked_topics();
            $tracked_topics['topics'][$args['id']] = time();
            Track::set_tracked_topics($tracked_topics);
        }

        // Determine the post offset (based on $_GET['p'])
        $num_pages = ceil(($cur_topic['num_replies'] + 1) / User::get()->disp_posts);

        $p = (!isset($args['page']) || $args['page'] <= 1 || $args['page'] > $num_pages) ? 1 : intval($args['page']);
        $start_from = User::get()->disp_posts * ($p - 1);

        $url_topic = Url::url_friendly($cur_topic['subject']);
        $url_forum = Url::url_friendly($cur_topic['forum_name']);

        // Generate paging links
        $paging_links = '<span class="pages-label">'.__('Pages').' </span>'.Url::paginate($num_pages, $p, 'topic/'.$args['id'].'/'.$url_topic.'/#');

        if (ForumSettings::get('o_censoring') == '1') {
            $cur_topic['subject'] = Utils::censor($cur_topic['subject']);
        }

        $quickpost = $this->model->is_quickpost($cur_topic['post_replies'], $cur_topic['closed'], $is_admmod);
        $subscraction = $this->model->get_subscraction(($cur_topic['is_subscribed'] == User::get()->id), $args['id']);

        View::addAsset('canonical', Router::pathFor('Forum', ['id' => $args['id'], 'name' => $url_forum]));
        if ($num_pages > 1) {
            if ($p > 1) {
                View::addAsset('prev', Router::pathFor('ForumPaginate', ['id' => $args['id'], 'name' => $url_forum, 'page' => intval($p-1)]));
            }
            if ($p < $num_pages) {
                View::addAsset('next', Router::pathFor('ForumPaginate', ['id' => $args['id'], 'name' => $url_forum, 'page' => intval($p+1)]));
            }
        }

        if (ForumSettings::get('o_feed_type') == '1') {
            View::addAsset('feed', 'extern.php?action=feed&amp;fid='.$args['id'].'&amp;type=rss', ['title' => __('RSS forum feed')]);
        } elseif (ForumSettings::get('o_feed_type') == '2') {
            View::addAsset('feed', 'extern.php?action=feed&amp;fid='.$args['id'].'&amp;type=atom', ['title' => __('Atom forum feed')]);
        }

        View::setPageInfo([
            'title' => [
                Utils::escape(ForumSettings::get('o_board_title')),
                Utils::escape($cur_topic['forum_name']),
                Utils::escape($cur_topic['subject'])
            ],
            'active_page' => 'Topic',
            'page_number'  =>  $p,
            'paging_links'  =>  $paging_links,
            'is_indexed' => true,
            'id' => $args['id'],
            'pid' => $args['pid'],
            'tid' => $args['id'],
            'fid' => $cur_topic['forum_id'],
            'post_data' => $this->model->print_posts($args['id'], $start_from, $cur_topic, $is_admmod),
            'cur_topic'    =>    $cur_topic,
            'subscraction'    =>    $subscraction,
            'post_link' => $post_link,
            'start_from' => $start_from,
            'quickpost'        =>    $quickpost,
            'index_questions'        =>    $index_questions,
            'lang_antispam_questions'        =>    $lang_antispam_questions,
            'url_forum'        =>    $url_forum,
            'url_topic'        =>    $url_topic,
        ])->addTemplate('topic.php')->display();

        // Increment "num_views" for topic
        $this->model->increment_views($args['id']);
    }

    public function viewpost($req, $res, $args)
    {
        $args['pid'] = Container::get('hooks')->fire('controller.topic.viewpost', $args['pid']);

        $post = $this->model->redirect_to_post($args['pid']);

        $args['id'] = $post['topic_id'];
        $args['page'] = $post['get_p'];

        $this->display($req, $res, $args);
    }

    public function subscribe($req, $res, $args)
    {
        $args['id'] = Container::get('hooks')->fire('controller.topic.subscribe', $args['id']);

        return $this->model->subscribe($args['id']);
    }

    public function unsubscribe($req, $res, $args)
    {
        $args['id'] = Container::get('hooks')->fire('controller.topic.unsubscribe', $args['id']);

        return $this->model->unsubscribe($args['id']);
    }

    public function close($req, $res, $args)
    {
        $args['id'] = Container::get('hooks')->fire('controller.topic.close', $args['id']);

        $topic = $this->model->setClosed($args['id'], 1);
        return Router::redirect(Router::pathFor('Topic', ['id' => $args['id'], 'name' => Url::url_friendly($topic['subject'])]), __('Close topic redirect'));
    }

    public function open($req, $res, $args)
    {
        $args['id'] = Container::get('hooks')->fire('controller.topic.open', $args['id']);

        $topic = $this->model->setClosed($args['id'], 0);
        return Router::redirect(Router::pathFor('Topic', ['id' => $args['id'], 'name' => Url::url_friendly($topic['subject'])]), __('Open topic redirect'));
    }

    public function stick($req, $res, $args)
    {
        $args['id'] = Container::get('hooks')->fire('controller.topic.stick', $args['id']);

        $topic = $this->model->setSticky($args['id'], 1);
        return Router::redirect(Router::pathFor('Topic', ['id' => $args['id'], 'name' => Url::url_friendly($topic['subject'])]), __('Stick topic redirect'));
    }

    public function unstick($req, $res, $args)
    {
        $args['id'] = Container::get('hooks')->fire('controller.topic.unstick', $args['id']);

        $topic = $this->model->setSticky($args['id'], 0);
        return Router::redirect(Router::pathFor('Topic', ['id' => $args['id'], 'name' => Url::url_friendly($topic['subject'])]), __('Unstick topic redirect'));
    }

    // Move a single topic
    public function move($req, $res, $args)
    {
        $args['tid'] = Container::get('hooks')->fire('controller.topic.move', $args['tid']);

        if ($new_fid = Input::post('move_to_forum')) {
            $this->model->move_to($args['fid'], $new_fid, $args['tid']);
            return Router::redirect(Router::pathFor('Topic', ['id' => $args['tid'], 'name' => $args['name']]), __('Move topic redirect'));
        }

        // Check if there are enough forums to move the topic
        if (!$this->model->check_move_possible()) {
            throw new  RunBBException(__('Nowhere to move'), 403);
        }

        View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Moderate')],
                'active_page' => 'moderate',
                'action'    =>    'single',
                'topics'    =>    $args['tid'],
                'list_forums'   => $this->model->get_forum_list_move($args['fid']),
            ])->addTemplate('moderate/move_topics.php')->display();
    }

    public function moderate($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.topic.moderate');

        // Make sure that only admmods allowed access this page
        $forumModel = new \RunBB\Model\Forum();
        $moderators = $forumModel->get_moderators($args['id']);
        $mods_array = ($moderators != '') ? unserialize($moderators) : [];

        if (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') && (User::get()->g_moderator == '0' || !array_key_exists(User::get()->username, $mods_array))) {
            throw new  RunBBException(__('No permission'), 403);
        }

        $cur_topic = $this->model->get_topic_info($args['fid'], $args['id']);

        // Determine the post offset (based on $_GET['p'])
        $num_pages = ceil(($cur_topic['num_replies'] + 1) / User::get()->disp_posts);

        $p = (!isset($args['page']) || $args['page'] <= 1 || $args['page'] > $num_pages) ? 1 : intval($args['page']);

        $start_from = User::get()->disp_posts * ($p - 1);

        // Delete one or more posts
        if (Input::post('delete_posts_comply')) {
            return $this->model->delete_posts($args['id'], $args['fid']);
        } elseif (Input::post('delete_posts')) {
                $posts = $this->model->delete_posts($args['id'], $args['fid']);

                View::setPageInfo([
                        'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Moderate')],
                        'active_page' => 'moderate',
                        'posts' => $posts,
                    ])->addTemplate('moderate/delete_posts.php')->display();
        } elseif (Input::post('split_posts_comply')) {
            return $this->model->split_posts($args['id'], $args['fid'], $p);
        } elseif (Input::post('split_posts')) {
            View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Moderate')],
                    'focus_element' => ['subject','new_subject'],
                    'page' => $p,
                    'active_page' => 'moderate',
                    'id' => $args['id'],
                    'posts' => $this->model->split_posts($args['id'], $args['fid'], $p),
                    'list_forums' => $this->model->get_forum_list_split($args['fid']),
                ])->addTemplate('moderate/split_posts.php')->display();
        } else {
            // Show the moderate posts view

            // Used to disable the Move and Delete buttons if there are no replies to this topic
            $button_status = ($cur_topic['num_replies'] == 0) ? ' disabled="disabled"' : '';

            /*if (isset($_GET['action']) && $_GET['action'] == 'all') {
                    User::get()->disp_posts = $cur_topic['num_replies'] + 1;
            }*/

            if (ForumSettings::get('o_censoring') == '1') {
                $cur_topic['subject'] = Utils::censor($cur_topic['subject']);
            }

            View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), Utils::escape($cur_topic['forum_name']), Utils::escape($cur_topic['subject'])],
                    'page' => $p,
                    'active_page' => 'moderate',
                    'cur_topic' => $cur_topic,
                    'url_topic' => Url::url_friendly($cur_topic['subject']),
                    'url_forum' => Url::url_friendly($cur_topic['forum_name']),
                    'fid' => $args['fid'],
                    'id' => $args['id'],
                    'paging_links' => '<span class="pages-label">' . __('Pages') . ' </span>' . Url::paginate($num_pages, $p, 'topic/moderate/' . $args['id'] . '/forum/' . $args['fid'] . '/#'),
                    'post_data' => $this->model->display_posts_moderate($args['id'], $start_from),
                    'button_status' => $button_status,
                    'start_from' => $start_from,
                ])->addTemplate('moderate/posts_view.php')->display();
        }
    }

    public function action($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.topic.action');

        return $this->model->handle_actions($args['id'], $args['action']);
    }
}
