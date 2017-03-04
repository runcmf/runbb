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

class Censoring
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Censoring();
        Lang::load('admin-common');
        Lang::load('admin-censoring');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.censoring.display');

        // Add a censor word
        if (Input::post('add_word')) {
            return $this->model->addWord();
        } // Update a censor word
        elseif (Input::post('update')) {
            return $this->model->updateWord();
        } // Remove a censor word
        elseif (Input::post('remove')) {
            return $this->model->removeWord();
        }

        AdminUtils::generateAdminMenu('censoring');

        return View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Censoring')],
            'focus_element' => ['censoring', 'new_search_for'],
            'active_page' => 'admin',
            'admin_console' => true,
            'word_data' => $this->model->getWords(),
        ])->display('@forum/admin/censoring');
    }
}
