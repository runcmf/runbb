<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model\Admin;

use RunBB\Core\Utils;

class Statistics
{
    public function getServerLoad()
    {
        if (@file_exists('/proc/loadavg') && is_readable('/proc/loadavg')) {
            // We use @ just in case
            $fh = @fopen('/proc/loadavg', 'r');
            $load_averages = @fread($fh, 64);
            @fclose($fh);

            if (($fh = @fopen('/proc/loadavg', 'r'))) {
                $load_averages = fread($fh, 64);
                fclose($fh);
            } else {
                $load_averages = '';
            }

            $load_averages = @explode(' ', $load_averages);
            $load_averages = Container::get('hooks')
                ->fire('model.admin.model.statistics.get_server_load.load_averages', $load_averages);

            $server_load = isset($load_averages[2]) ? $load_averages[0].' '.$load_averages[1].' '.
                $load_averages[2] : __('Not available');
        } elseif (!in_array(PHP_OS, ['WINNT', 'WIN32']) &&
            preg_match('%averages?: ([0-9\.]+),?\s+([0-9\.]+),?\s+([0-9\.]+)%i', @exec('uptime'), $load_averages)) {
            $server_load = $load_averages[1].' '.$load_averages[2].' '.$load_averages[3];
        } else {
            $server_load = __('Not available');
        }

        $server_load = Container::get('hooks')
            ->fire('model.admin.model.statistics.get_server_load.server_load', $server_load);
        return $server_load;
    }

    public function getNumOnline()
    {
        $num_online = \ORM::for_table(ORM_TABLE_PREFIX.'online')->where('idle', 0)
                            ->count('user_id');

        $num_online = Container::get('hooks')
            ->fire('model.admin.model.statistics.get_num_online.num_online', $num_online);
        return $num_online;
    }

    public function getTotalSize()
    {
        $total = [];

        if (ForumSettings::get('db_type') == 'mysql' || ForumSettings::get('db_type') == 'mysqli' ||
            ForumSettings::get('db_type') == 'mysql_innodb' || ForumSettings::get('db_type') == 'mysqli_innodb') {
            // Calculate total db size/row count
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')->raw_query('SHOW TABLE STATUS LIKE \''.
                ForumSettings::get('db_prefix').'%\'')->find_many();
            $result = Container::get('hooks')->fire('model.admin.model.statistics.get_total_size.raw_data', $result);

            $total['size'] = $total['records'] = 0;
            foreach ($result as $status) {
                $total['records'] += $status['Rows'];
                $total['size'] += $status['Data_length'] + $status['Index_length'];
            }

            $total['size'] = Utils::fileSize($total['size']);
        }

        $total = Container::get('hooks')->fire('model.admin.model.statistics.get_total_size.total', $total);
        return $total;
    }

    public function getPhpAccelerator()
    {
        if (function_exists('mmcache')) {
            $php_accelerator = '<a href="http://'.__('Turck MMCache link').'">'.__('Turck MMCache').'</a>';
        } elseif (isset($_PHPA)) {
            $php_accelerator = '<a href="http://'.__('ionCube PHP Accelerator link').'">'.
                __('ionCube PHP Accelerator').'</a>';
        } elseif (ini_get('apc.enabled')) {
            $php_accelerator ='<a href="http://'.__('Alternative PHP Cache (APC) link').'">'.
                __('Alternative PHP Cache (APC)').'</a>';
        } elseif (ini_get('zend_optimizer.optimization_level')) {
            $php_accelerator = '<a href="http://'.__('Zend Optimizer link').'">'.__('Zend Optimizer').'</a>';
        } elseif (ini_get('eaccelerator.enable')) {
            $php_accelerator = '<a href="http://'.__('eAccelerator link').'">'.__('eAccelerator').'</a>';
        } elseif (ini_get('xcache.cacher')) {
            $php_accelerator = '<a href="http://'.__('XCache link').'">'.__('XCache').'</a>';
        } else {
            $php_accelerator = __('NA');
        }

        $php_accelerator = Container::get('hooks')
            ->fire('model.admin.model.statistics.get_php_accelerator', $php_accelerator);
        return $php_accelerator;
    }
}
