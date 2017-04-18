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
 * Middleware to check if user is allowed to read the board
 */
class ReadBoard
{

    public function __invoke($request, $response, $next)
    {
        // Display error page
        if (User::get()->g_read_board == '0') {
            throw new RunBBException(__('No view'), 403);
        }
        $response = $next($request, $response);
        return $response;
    }
}
