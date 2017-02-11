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

class Options
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Options();
        Lang::load('admin-common');
        Lang::load('admin-options');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.options.display');

        if (Request::isPost()) {
            return $this->model->updateOptions();
        }

        AdminUtils::generateAdminMenu('options');

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Options')],
            'active_page' => 'admin',
            'admin_console' => true,
            'languages' => $this->model->getLangs(),
            'styles' => $this->model->getStyles(),
            'times' => $this->model->getTimes(),
            'smtp_pass' => (!empty(ForumSettings::get('o_smtp_pass')) ?
                Random::key(Utils::strlen(ForumSettings::get('o_smtp_pass')), true) : '')
        ])->addTemplate('admin/options.php')->display();
    }
}
