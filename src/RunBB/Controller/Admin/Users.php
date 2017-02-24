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

class Users
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Users();
        Lang::load('admin-common');
        Lang::load('admin-users');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.users.display');

        // Move multiple users to other user groups
        if (Input::post('move_users') || Input::post('move_users_comply')) {
            if (User::get()->g_id > ForumEnv::get('FEATHER_ADMIN')) {
                throw new  RunBBException(__('No permission'), 403);
            }

            AdminUtils::generateAdminMenu('users');//FIXME live for moderators

            return View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'),
                    __('Users'), __('Move users')],
                'active_page' => 'moderate',
                'admin_console' => true,
                'move' => $this->model->moveUsers(),
            ])->addTemplate('@forum/admin/users/move_users')->display();
        }


        if (Input::post('delete_users_comply')) {
            return $this->model->deleteUsers();
        }

        // Delete multiple users
        if (Input::post('delete_users')) {
            if (User::get()->g_id > ForumEnv::get('FEATHER_ADMIN')) {
                throw new RunBBException(__('No permission'), 403);
            }

            AdminUtils::generateAdminMenu('users');

            return View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'),
                    __('Users'), __('Delete users')],
                'active_page' => 'moderate',
                'admin_console' => true,
                'user_ids' => $this->model->deleteUsers(),
            ])->addTemplate('@forum/admin/users/delete_users')->display();
        }

        // Ban multiple users
        if (Input::post('ban_users') || Input::post('ban_users_comply')) {
            if (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') &&
                (User::get()->g_moderator != '1' || User::get()->g_mod_ban_users == '0')) {
                throw new  RunBBException(__('No permission'), 403);
            }

            AdminUtils::generateAdminMenu('users');

            return View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Users'), __('Bans')],
                'active_page' => 'moderate',
                'focus_element' => ['bans2', 'ban_message'],
                'admin_console' => true,
                'user_ids' => $this->model->banUsers(),
            ])->addTemplate('@forum/admin/users/ban_users')->display();
        }

        // Display bans
        if (Input::query('find_user')) {
            // Return conditions and query string for the URL
            $search = $this->model->getUserSearch();

            // Fetch user count
            $num_users = $this->model->getNumUsersSearch($search['conditions']);

            // Determine the user offset (based on $_GET['p'])
            $num_pages = ceil($num_users / 50);

            $p = (!Input::query('p') || Input::query('p') <= 1 || Input::query('p') > $num_pages) ?
                1 : intval(Input::query('p'));
            $start_from = 50 * ($p - 1);

            // Generate paging links
            $paging_links = '<span class="pages-label">' . __('Pages') . ' </span>' .
                Url::paginateOld($num_pages, $p, '?find_user=&amp;' . implode('&amp;', $search['query_str']));

            // Some helper variables for permissions
            $can_delete = $can_move = User::get()->g_id == ForumEnv::get('FEATHER_ADMIN');
            $can_ban = User::get()->g_id == ForumEnv::get('FEATHER_ADMIN') || (User::get()->g_moderator == '1' &&
                    User::get()->g_mod_ban_users == '1');
            $can_action = ($can_delete || $can_ban || $can_move) && $num_users > 0;
            View::addAsset('js', 'assets/js/common.js', ['type' => 'text/javascript']);

            View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'),
                    __('Users'), __('Results head')],
                'active_page' => 'admin',
                'admin_console' => true,
                'paging_links' => $paging_links,
                'search' => $search,
                'start_from' => $start_from,
                'can_delete' => $can_delete,
                'can_ban' => $can_ban,
                'can_action' => $can_action,
                'can_move' => $can_move,
                'user_data' => $this->model->printUsers(
                    $search['conditions'],
                    $search['order_by'],
                    $search['direction'],
                    $start_from
                ),
            ])->addTemplate('@forum/admin/users/find_users')->display();
        } else {
            AdminUtils::generateAdminMenu('users');

            return View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Users')],
                'active_page' => 'admin',
                'admin_console' => true,
                'focus_element' => ['find_user', 'form[username]'],
                'group_list' => $this->model->getGroupList(),
            ])->addTemplate('@forum/admin/users/admin_users')->display();
        }
    }

    // Show IP statistics for a certain user ID
    public function ipstats($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.users.ipstats');

        // Fetch ip count
        $num_ips = $this->model->getNumIp($args['id']);

        // Determine the ip offset (based on $_GET['p'])
        $num_pages = ceil($num_ips / 50);

        $p = (!Input::query('p') || Input::query('p') <= 1 || Input::query('p') > $num_pages) ?
            1 : intval(Input::query('p'));
        $start_from = 50 * ($p - 1);

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'),
                __('Users'), __('Results head')],
            'active_page' => 'admin',
            'admin_console' => true,
            'page' => $p,
            'paging_links' => '<span class="pages-label">' . __('Pages') . ' </span>' .
                Url::paginateOld($num_pages, $p, '?ip_stats=' . $args['id']),
            'start_from' => $start_from,
            'ip_data' => $this->model->getIpStats($args['id'], $start_from),
        ])->addTemplate('@forum/admin/users/search_ip')->display();
    }

    // Show IP statistics for a certain user IP
    public function showusers($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.users.showusers');

        $search_ip = Input::query('ip');

        if (!@preg_match('%^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$%', $search_ip) &&
            !@preg_match('%^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:'.
                '[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|'.
                '(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:'.
                '([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:)'.
                '{0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|'.
                '(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:)'.
                '{0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|'.
                '(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|'.
                '(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::'.
                '([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|'.
                '(([0-9A-Fa-f]{1,4}:){1,7}:))$%', $search_ip)) {
            throw new  RunBBException(__('Bad IP message'), 400);
        }

        // Fetch user count
        $num_users = $this->model->getNumUsersIp($search_ip);

        // Determine the user offset (based on $_GET['p'])
        $num_pages = ceil($num_users / 50);

        $p = (!Input::query('p') || Input::query('p') <= 1 || Input::query('p') > $num_pages) ?
            1 : intval(Input::query('p'));
        $start_from = 50 * ($p - 1);

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'),
                __('Users'), __('Results head')],
            'active_page' => 'admin',
            'admin_console' => true,
            'paging_links' => '<span class="pages-label">' . __('Pages') . ' </span>' .
                Url::paginateOld($num_pages, $p, '?ip_stats=' . $search_ip),
            'page' => $p,
            'start_from' => $start_from,
            'info' => $this->model->getInfoPoster($search_ip, $start_from),
        ])->addTemplate('@forum/admin/users/show_users')->display();
    }
}
