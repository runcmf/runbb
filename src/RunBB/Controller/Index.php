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
use RunBB\Model\Auth;

class Index
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Index();
        Lang::load('index');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.index.index');

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title'))],
            'active_page' => 'index',
            'is_indexed' => true,
            'index_data' => $this->model->printCategoriesForums(),
            'stats' => $this->model->collectStats(),
            'online' => $this->model->fetchUsersOnline(),
            'forum_actions' => $this->model->getForumActions(),
            'cur_cat' => 0
        ])->addTemplate('@forum/index')->display();
    }

    public function rules()
    {
        Container::get('hooks')->fire('controller.index.rules');

        if (ForumSettings::get('o_rules') == '0' || (User::get()->is_guest &&
                User::get()->g_read_board == '0' && ForumSettings::get('o_regs_allow') == '0')
        ) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Forum rules')],
            'active_page' => 'rules'
        ])->addTemplate('@forum/misc/rules')->display();
    }

    public function markread()
    {
        Container::get('hooks')->fire('controller.index.markread');

        Auth::setLastVisit(User::get()->id, User::get()->logged);
        // Reset tracked topics
        Track::setTrackedTopics(null);
        return Router::redirect(Router::pathFor('home'), __('Mark read redirect'));
    }
}
