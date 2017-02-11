<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller;

use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Userlist
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Userlist();
        Lang::load('userlist');
        Lang::load('search');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.userlist.display');

        if (User::get()->g_view_users == '0') {
            throw new  RunBBException(__('No permission'), 403);
        }

        // Determine if we are allowed to view post counts
        $show_post_count = (ForumSettings::get('o_show_post_count') == '1' || User::get()->is_admmod) ? true : false;

        $username = Input::query('username') && User::get()->g_search_users == '1' ?
            Utils::trim(Input::query('username')) : '';
        $show_group = Input::query('show_group') ? intval(Input::query('show_group')) : -1;
        $sort_by = Input::query('sort_by') && (in_array(Input::query('sort_by'), ['username', 'registered']) ||
            (Input::query('sort_by') == 'num_posts' && $show_post_count)) ? Input::query('sort_by') : 'username';
        $sort_dir = Input::query('sort_dir') && Input::query('sort_dir') == 'DESC' ? 'DESC' : 'ASC';

        $num_users = $this->model->fetchUserCount($username, $show_group);

        // Determine the user offset (based on $page)
        $num_pages = ceil($num_users / 50);

        $p = (!Input::query('p') || $page <= 1 || $page > $num_pages) ? 1 : intval($page);
        $start_from = 50 * ($p - 1);

        if (User::get()->g_search_users == '1') {
            $focus_element = ['userlist', 'username'];
        } else {
            $focus_element = [];
        }

        // Generate paging links
        $paging_links = '<span class="pages-label">'.__('Pages').' </span>'.
            Url::paginateOld($num_pages, $p, '?username='.urlencode($username).'&amp;show_group='.
                $show_group.'&amp;sort_by='.$sort_by.'&amp;sort_dir='.$sort_dir);

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('User list')],
            'active_page' => 'userlist',
            'page_number'  =>  $p,
            'paging_links'  =>  $paging_links,
            'focus_element' => $focus_element,
            'is_indexed' => true,
            'username' => $username,
            'show_group' => $show_group,
            'sort_by' => $sort_by,
            'sort_dir' => $sort_dir,
            'show_post_count' => $show_post_count,
            'dropdown_menu' => $this->model->generateDropdownMenu($show_group),
            'userlist_data' => $this->model->printUsers($username, $start_from, $sort_by, $sort_dir, $show_group),
        ])->addTemplate('userlist.php')->display();
    }
}
