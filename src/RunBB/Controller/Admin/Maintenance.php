<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller\Admin;

use RunBB\Core\AdminUtils;
use RunBB\Core\Utils;

class Maintenance
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Maintenance();
        Lang::load('admin-common');
        Lang::load('admin-maintenance');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.maintenance.display');

        $action = '';
        if (Input::post('action')) {
            $action = Input::post('action');
        } elseif (Input::query('action')) {
            $action = Input::query('action');
        }

        if ($action == 'rebuild') {
            $this->model->rebuild();

            View::setPageInfo([
                'page_title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Rebuilding search index')],
                'query_str' => $this->model->getQueryStr()
            ])->addTemplate('admin/maintenance/rebuild.php')->display();
        }

        if ($action == 'prune') {
            $prune_from = Utils::trim(Input::post('prune_from'));
            $prune_sticky = intval(Input::post('prune_sticky'));

            AdminUtils::generateAdminMenu('maintenance');

            if (Input::post('prune_comply')) {
                $this->model->pruneComply($prune_from, $prune_sticky);
            }

            View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Prune')],
                'active_page' => 'admin',
                'admin_console' => true,
                'prune_sticky' => $prune_sticky,
                'prune_from' => $prune_from,
                'prune' => $this->model->getInfoPrune($prune_sticky, $prune_from),
            ])->addTemplate('admin/maintenance/prune.php')->display();
        }

        AdminUtils::generateAdminMenu('maintenance');

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Maintenance')],
            'active_page' => 'admin',
            'admin_console' => true,
            'first_id' => $this->model->getFirstId(),
            'categories' => $this->model->getCategories(),
        ])->addTemplate('admin/maintenance/admin_maintenance.php')->display();
    }
}
