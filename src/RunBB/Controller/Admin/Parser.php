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

class Parser
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Parser();
        Lang::load('admin-common');
        Lang::load('admin-parser');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.parser.display');

        $groups = new \RunBB\Model\Admin\Groups();

        if (Input::post('form_sent') && Input::post('save', false) !== false) {
            foreach (Input::post('parserPlugin') as $group => $plugins) {
                $p = [];
                foreach ($plugins as $k => $v) {
                    $p[] = $k;
                }
                $groups->setParserPlugins($group, $p);
            }
            return Router::redirect(Router::pathFor('adminParser'), __('save_success'));
        }

        if (Input::post('form_sent') && Input::post('reset', false) !== false) {
            $this->model->resetPlugins();
        }

        AdminUtils::generateAdminMenu('parser');

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Parser')],
            'active_page' => 'admin',
            'admin_console' => true,
            'parserPluginsList' => $this->model->getPluginsList(),
            'groups' => $groups->fetchGroups()
        ])->display('@forum/admin/parser');
    }
}
