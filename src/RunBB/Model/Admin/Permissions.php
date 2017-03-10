<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model\Admin;

use RunBB\Core\Url;
use RunBB\Model\Cache;

class Permissions
{
    public function updatePermissions()
    {
        $form = array_map('intval', Input::post('form'));
        $form = Container::get('hooks')->fire('model.admin.permissions.update_permissions.form', $form);

        foreach ($form as $key => $input) {
            // Make sure the input is never a negative value
            if ($input < 0) {
                $input = 0;
            }

            // Only update values that have changed
            if (array_key_exists('p_'.$key, Container::get('forum_settings')) &&
                ForumSettings::get('p_'.$key) != $input) {
                DB::forTable('config')
                    ->where('conf_name', 'p_'.$key)
                    ->find_one()
                    ->set(['conf_value' => $input])
                    ->save();
            }
        }

        // Regenerate the config cache
        Container::get('cache')->store('config', Cache::getConfig());
        // $this->clearFeedCache();

        return Router::redirect(Router::pathFor('adminPermissions'), __('Perms updated redirect'));
    }
}
