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
use RunBB\Core\Interfaces\Input;
use RunBB\Core\Utils;
use RunBB\Model\BBLogger;

class Logs
{
    public function __construct()
    {
        Lang::load('admin-common');
    }

    public function display($req, $res, $args)
    {
        AdminUtils::generateAdminMenu('logs');

        $delIds = Input::post('logIds', []);
        if(!empty($delIds)) {
            if(BBLogger::delete($delIds)) {
                return Router::redirect(Router::pathFor('adminLogs'), ['success', 'Logs deleted']);
            } else {
                return Router::redirect(Router::pathFor('adminLogs'), ['error', 'ERROR delete Logs :(']);
            }
        }

        return View::setPageInfo([
            'active_page' => 'admin',
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), 'Logs'],// TODO translate
            'admin_console' => true,
            'logs' => BBLogger::getLogs()
        ])->display('@forum/admin/logs');
    }
}
