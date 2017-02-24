<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller\Admin;

use RunBB\Core\AdminUtils;
use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Bans
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Bans();
        Lang::load('admin-common');
        Lang::load('admin-bans');

        if (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') && (User::get()->g_moderator != '1' ||
                User::get()->g_mod_ban_users == '0')
        ) {
            throw new  RunBBException(__('No permission'), '403');
        }
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.bans.display');

        // Display bans
        if (Input::query('find_ban')) {
            $ban_info = $this->model->findBan();

            // Determine the ban offset (based on $_GET['p'])
            $num_pages = ceil($ban_info['num_bans'] / 50);

            $p = (!Input::query('p') || Input::query('p') <= 1 || Input::query('p') > $num_pages) ?
                1 : intval(Input::query('p'));
            $start_from = 50 * ($p - 1);

            $ban_data = $this->model->findBan($start_from);

            View::setPageInfo([
                'active_page' => 'admin',
                'admin_console' => true,
                'page' => $p,
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Bans'),
                    __('Results head')],
                'paging_links' => '<span class="pages-label">' . __('Pages') . ' </span>' .
                    Url::paginateOld($num_pages, $p, '?find_ban=&amp;' . implode('&amp;', $ban_info['query_str'])),
                'ban_data' => $ban_data['data'],
            ])->addTemplate('@forum/admin/bans/search_ban')->display();
        } else {
            AdminUtils::generateAdminMenu('bans');

            View::setPageInfo([
                'active_page' => 'admin',
                'admin_console' => true,
                'focus_element' => ['bans', 'new_ban_user'],
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Bans')],
            ])->addTemplate('@forum/admin/bans/admin_bans')->display();
        }
    }

    public function add($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.bans.add');

        if (Input::post('add_edit_ban')) {
            return $this->model->insertBan();
        }

        AdminUtils::generateAdminMenu('bans');

        View::setPageInfo([
            'active_page' => 'admin',
            'admin_console' => true,
            'focus_element' => ['bans2', 'ban_user'],
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Bans')],
            'ban' => $this->model->addBanInfo($req, $res, $args),
        ])->addTemplate('@forum/admin/bans/add_ban')->display();
    }

    public function delete($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.bans.delete');

        // Remove the ban
        return $this->model->removeBan($args['id']);
    }

    public function edit($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.bans.edit');

        if (Input::post('add_edit_ban')) {
            return $this->model->insertBan();
        }

        AdminUtils::generateAdminMenu('bans');

        View::setPageInfo([
            'active_page' => 'admin',
            'admin_console' => true,
            'focus_element' => ['bans2', 'ban_user'],
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Bans')],
            'ban' => $this->model->editBanInfo($args['id']),
        ])->addTemplate('@forum/admin/bans/add_ban')->display();
    }
}
