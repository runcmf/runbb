<?php
/**
 *
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 * $app = new \Slim\Slim();
 * $app->add(new \Slim\Extras\Middleware\RunBBAuth());
 *
 */

namespace RunBB\Middleware;

use RunBB\Exception\RunBBException;

/**
 * Middleware to check if user is logged and admin
 */
class AdminMod
{
    public function __invoke($request, $response, $next)
    {
        // Middleware to check if user is allowed to moderate, if he's not redirect to error page.
        if (!User::get()->is_admmod && !User::get()->isModerator) {
            throw new  RunBBException(__('No permission'), 403);
        }
        return $next($request, $response);
    }
}
