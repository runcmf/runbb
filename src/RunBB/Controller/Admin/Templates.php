<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace RunBB\Controller\Admin;

use RunBB\Core\AdminUtils;
use RunBB\Exception\RunBBException;
use RunBB\Core\Utils;

class Templates
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Templates();
        Lang::load('admin-common');
//        translate('admin-templates');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.templates.display');

        AdminUtils::generateAdminMenu('templates');

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), 'Templates'],// TODO translate
            'active_page' => 'admin',
            'admin_console' => true,
            'php_os' => PHP_OS,
            'phpversion' => phpversion()
        ])->addTemplate('admin/statistics.php')->display();
    }
}