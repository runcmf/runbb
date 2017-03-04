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

class Permissions
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Permissions();
        Lang::load('admin-common');
        Lang::load('admin-permissions');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.permissions.display');

        // Update permissions
        if (Request::isPost()) {
            return $this->model->updatePermissions();
        }

        AdminUtils::generateAdminMenu('permissions');

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Permissions')],
            'active_page' => 'admin',
            'admin_console' => true,
        ])->display('@forum/admin/permissions');
    }
}
