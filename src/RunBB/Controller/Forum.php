<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller;

use RunBB\Core\Interfaces\Router;
use RunBB\Exception\RunBBException;
use RunBB\Core\Track;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Forum
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Forum();
        Lang::load('forum');
    }

    public function display($req, $res, $args)
    {
        $args['fid'] = $args['id'];
        Container::get('hooks')->fire('controller.forum.display');
        // Fetch some informations about the forum
        $cur_forum = $this->model->getForumInfo($args['fid']);

        // Is this a redirect forum? In that case, redirect!
        if ($cur_forum['redirect_url'] != '') {
            return Router::redirect(Router::pathFor('Forum', ['id' => $cur_forum['redirect_url']]));
        }

        // Sort out who the moderators are and if we are currently a moderator (or an admin)
        $mods_array = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : [];
        $is_admmod = (User::get()->g_id == ForumEnv::get('FEATHER_ADMIN') ||
            (User::get()->g_moderator == '1' && array_key_exists(User::get()->username, $mods_array))) ?
            true : false;

        $sort_by = $this->model->sortForumBy($cur_forum['sort_by']);

        // Can we or can we not post new topics?
        if (($cur_forum['post_topics'] == '' && User::get()->g_post_topics == '1') ||
            $cur_forum['post_topics'] == '1' || $is_admmod) {
            $post_link = "\t\t\t".'<p class="postlink conr"><a href="'.
                Router::pathFor('newTopic', ['fid' => $args['fid']]).'">'.__('Post topic').'</a></p>'."\n";
        } else {
            $post_link = '';
        }

        // Determine the topic offset (based on $args['page'])
        $num_pages = ceil($cur_forum['num_topics'] / User::get()->disp_topics);

        $p = (!isset($args['page']) || $args['page'] <= 1 || $args['page'] > $num_pages) ? 1 : intval($args['page']);
        $start_from = User::get()->disp_topics * ($p - 1);
        $url_forum = Url::slug($cur_forum['forum_name']);

        // Generate paging links
        $paging_links = '<span class="pages-label">'.__('Pages').' </span>'.
            Url::paginate($num_pages, $p, 'forum/'.$args['fid'].'/'.$url_forum.'/#');

        $forum_actions = $this->model->getForumActions(
            $args['fid'],
            ForumSettings::get('o_forum_subscriptions'),
            ($cur_forum['is_subscribed'] == User::get()->id)
        );

        View::addAsset('canonical', Router::pathFor('Forum', ['id' => $args['fid'], 'name' => $url_forum]));
        if ($num_pages > 1) {
            if ($p > 1) {
                View::addAsset('prev', Router::pathFor('ForumPaginate', ['id' => $args['fid'],
                    'name' => $url_forum, 'page' => intval($p-1)]));
            }
            if ($p < $num_pages) {
                View::addAsset('next', Router::pathFor('ForumPaginate', ['id' => $args['fid'],
                    'name' => $url_forum, 'page' => intval($p+1)]));
            }
        }

        if (ForumSettings::get('o_feed_type') == '1') {
            View::addAsset('feed', Router::pathFor('extern') . '?action=feed&amp;fid='.
                $args['fid'].'&amp;type=rss', ['title' => __('RSS forum feed')]);
        } elseif (ForumSettings::get('o_feed_type') == '2') {
            View::addAsset('feed', Router::pathFor('extern') . '?action=feed&amp;fid='.
                $args['fid'].'&amp;type=atom', ['title' => __('Atom forum feed')]);
        }

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), Utils::escape($cur_forum['forum_name'])],
            'active_page' => 'Forum',
            'page_number'  =>  $p,
            'paging_links'  =>  $paging_links,
            'is_indexed' => true,
            'id' => $args['fid'],
            'fid' => $args['fid'],
            'forum_data' => $this->model->printTopics($args['fid'], $sort_by, $start_from),
            'cur_forum' => $cur_forum,
            'post_link' => $post_link,
            'start_from' => $start_from,
            'url_forum' => $url_forum,
            'forum_actions' => $forum_actions,
        ])->addTemplate('forum.php')->display();
    }

    public function moderate($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.forum.moderate');

        // Make sure that only admmods allowed access this page
        $moderators = $this->model->getModerators($args['fid']);
        $mods_array = ($moderators != '') ? unserialize($moderators) : [];

        if (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') &&
            (User::get()->g_moderator == '0' || !array_key_exists(User::get()->username, $mods_array))) {
            throw new  RunBBException(__('No permission'), 403);
        }

        // Fetch some info about the forum
        $cur_forum = $this->model->getForumInfo($args['fid']);

        // Is this a redirect forum? In that case, abort!
        if ($cur_forum['redirect_url'] != '') {
            throw new  RunBBException(__('Bad request'), '404');
        }

        $sort_by = $this->model->sortForumBy($cur_forum['sort_by']);

        // Determine the topic offset (based on $_GET['p'])
        $num_pages = ceil($cur_forum['num_topics'] / User::get()->disp_topics);

        $p = (!isset($args['page']) || $args['page'] <= 1 || $args['page'] > $num_pages) ?
            1 : intval($args['page']);
        $start_from = User::get()->disp_topics * ($p - 1);
        $url_forum = Url::slug($cur_forum['forum_name']);

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')),
                Utils::escape($cur_forum['forum_name'])],
            'active_page' => 'moderate',
            'page' => $p,
            'id' => $args['fid'],
            'p' => $p,
            'url_forum' => $url_forum,
            'cur_forum' => $cur_forum,
            'paging_links' => '<span class="pages-label">'.__('Pages').' </span>'.
                Url::paginate($num_pages, $p, 'forum/moderate/'.$args['fid'].'/#'),
            'topic_data' => $this->model->displayTopicsModerate($args['fid'], $sort_by, $start_from),
            'start_from' => $start_from,
            ])->addTemplate('moderate/moderator_forum.php')->display();
    }

    public function markread($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.forum.markread');

        $tracked_topics = Track::getTrackedTopics();
        $tracked_topics['forums'][$args['id']] = time();
        Track::setTrackedTopics($tracked_topics);

        return Router::redirect(Router::pathFor('Forum', ['id' => $args['id']]), __('Mark forum read redirect'));
    }

    public function subscribe($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.forum.subscribe');

        $this->model->subscribe($args['id']);
        return Router::redirect(Router::pathFor('Forum', ['id' => $args['id']]), __('Subscribe redirect'));
    }

    public function unsubscribe($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.forum.unsubscribe');

        $this->model->unsubscribe($args['id']);
        return Router::redirect(Router::pathFor('Forum', ['id' => $args['id']]), __('Unsubscribe redirect'));
    }

    public function dealposts($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.forum.dealposts');

        // Make sure that only admmods allowed access this page
        $moderators = $this->model->getModerators($args['fid']);
        $mods_array = ($moderators != '') ? unserialize($moderators) : [];

        if (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') &&
            (User::get()->g_moderator == '0' || !array_key_exists(User::get()->username, $mods_array))) {
            throw new  RunBBException(__('No permission'), 403);
        }

        $topicModel = new \RunBB\Model\Topic();

        // Move one or more topics
        if (Input::post('move_topics') || Input::post('move_topics_to')) {
            $topics = Input::post('topics') ? Input::post('topics') : [];
            if (empty($topics)) {
                throw new  RunBBException(__('No topics selected'), 400);
            }

            if ($new_fid = Input::post('move_to_forum')) {
                $topics = explode(',', $topics);
                $topicModel->moveTo($args['fid'], $new_fid, $topics);
                return Router::redirect(Router::pathFor('Forum', ['id' => $new_fid]), __('Move topics redirect'));
            }

            // Check if there are enough forums to move the topic
            if (!$topicModel->checkMovePossible()) {
                throw new  RunBBException(__('Nowhere to move'), 403);
            }

            View::setPageInfo([
                    'action'    =>    'multi',
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Moderate')],
                    'active_page' => 'moderate',
                    'id'    =>    $args['fid'],
                    'topics'    =>    implode(',', array_map('intval', array_keys($topics))),
                    'list_forums'   => $topicModel->getForumListMove($args['fid']),
                ])->addTemplate('moderate/move_topics.php')->display();
        } // Merge two or more topics
        elseif (Input::post('merge_topics') || Input::post('merge_topics_comply')) {
            if (Input::post('merge_topics_comply')) {
                $this->model->mergeTopics($args['fid']);
                return Router::redirect(
                    Router::pathFor('Forum', ['id' => $args['fid']]),
                    __('Merge topics redirect')
                );
            }

            $topics = Input::post('topics') ? Input::post('topics') : [];
            if (count($topics) < 2) {
                throw new  RunBBException(__('Not enough topics selected'), 400);
            }

            View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Moderate')],
                    'active_page' => 'moderate',
                    'id'    =>    $args['fid'],
                    'topics'    =>    $topics,
                ])->addTemplate('moderate/merge_topics.php')->display();
        } // Delete one or more topics
        elseif (Input::post('delete_topics') || Input::post('delete_topics_comply')) {
            $topics = Input::post('topics') ? Input::post('topics') : [];
            if (empty($topics)) {
                throw new  RunBBException(__('No topics selected'), 400);
            }

            if (Input::post('delete_topics_comply')) {
                $this->model->deleteTopics($topics, $args['fid']);
                return Router::redirect(
                    Router::pathFor('Forum', ['id' => $args['fid']]),
                    __('Delete topics redirect')
                );
            }

            View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Moderate')],
                    'active_page' => 'moderate',
                    'id'    =>    $args['fid'],
                    'topics'    =>    $topics,
                ])->addTemplate('moderate/delete_topics.php')->display();
        } // Open or close one or more topics
        elseif (Input::post('open') || Input::post('close')) {
            $action = (Input::post('open')) ? 0 : 1;

            // There could be an array of topic IDs in $_POST
            if (Input::post('open') || Input::post('close')) {
                $topics = Input::post('topics') ? @array_map('intval', @array_keys(Input::post('topics'))) : [];
                if (empty($topics)) {
                    throw new  RunBBException(__('No topics selected'), 400);
                }

                $this->model->closeMultipleTopics($action, $topics);

                $redirect_msg = ($action) ? __('Close topics redirect') : __('Open topics redirect');
                return Router::redirect(Router::pathFor('moderateForum', ['fid' => $args['fid'],
                    'page' => $args['page']]), $redirect_msg);
            }
        }
    }
}
