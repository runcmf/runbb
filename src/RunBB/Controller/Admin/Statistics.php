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
use RunBB\Core\Utils;

class Statistics
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Statistics();
        Lang::load('admin-common');
        Lang::load('admin-index');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.statistics.display');

        AdminUtils::generateAdminMenu('index');

        $total = $this->model->getTotalSize();

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Server statistics')],
            'active_page' => 'admin',
            'admin_console' => true,
            'server_load' => $this->model->getServerLoad(),
            'num_online' => $this->model->getNumOnline(),
            'total_size' => $total['size'],
            'total_records' => $total['records'],
            'php_accelerator' => $this->model->getPhpAccelerator(),
            'php_os' => PHP_OS,
            'phpversion' => phpversion()
        ])->addTemplate('@forum/admin/statistics')->display();
    }


    public function phpinfo($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.statistics.phpinfo');

        // Show phpinfo() output
        // Is phpinfo() a disabled function?
        if (strpos(strtolower((string)ini_get('disable_functions')), 'phpinfo') !== false) {
            throw new  RunBBException(__('PHPinfo disabled message'), 404);
        }

        phpinfo();
    }
}
