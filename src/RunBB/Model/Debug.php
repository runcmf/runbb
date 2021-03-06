<?php

/**
* Copyright (C) 2015-2016 FeatherBB
* based on code by (C) 2008-2015 FluxBB
* and Rickard Andersson (C) 2002-2008 PunBB
* License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
*/

namespace RunBB\Model;

use RunBB\Core\Utils;
use RunBB\Middleware\Core;

class Debug
{
    public static function getQueries()
    {
        $log = DB::getQueryLog();
        if (empty($log)) {
            return null;
        }
        $data = [];
        $data['raw'] = array_combine($log[0], $log[1]);
        $data['total_time'] = array_sum(array_keys($data['raw']));
        return $data;
    }

    public static function getInfo()
    {
        $data = ['exec_time' => (Utils::getMicrotime() - Container::get('start'))];
//        $data['nb_queries'] = (!empty(DB::getQueryLog())) ? count(DB::getQueryLog()[0]) : 'N/A';
        $data['mem_usage'] = (function_exists('memory_get_usage')) ? Utils::fileSize(memory_get_usage()) : 'N/A';
        $data['mem_peak_usage'] = (function_exists('memory_get_peak_usage')) ?
            Utils::fileSize(memory_get_peak_usage()) : 'N/A';
        return $data;
    }
}
