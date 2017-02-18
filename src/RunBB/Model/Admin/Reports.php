<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model\Admin;

class Reports
{
    public function zapReport($zap_id)
    {
        $zap_id = Container::get('hooks')->fire('model.admin.reports.zap_report.zap_id', $zap_id);

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'reports')->where('id', $zap_id);
        $result = Container::get('hooks')->fireDB('model.admin.reports.zap_report.query', $result);
        $result = $result->select('zapped')->find_one();

        $set_zap_report = ['zapped' => time(), 'zapped_by' => User::get()->id];
        $set_zap_report = Container::get('hooks')->fire('model.admin.reports.set_zap_report', $set_zap_report);

        // Update report to indicate it has been zapped
        if ($result->zapped === null) {
            \ORM::for_table(ORM_TABLE_PREFIX.'reports')
                ->where('id', $zap_id)
                ->find_one()
                ->set($set_zap_report)
                ->save();
        }

        // Remove zapped reports to keep only last 10
        $threshold = \ORM::forTable(ORM_TABLE_PREFIX.'reports')
            ->select('zapped')
            ->whereNotNull('zapped')
            ->orderByDesc('zapped')
            ->offset(10)
            ->limit(1)
            ->findOne();

        if ($threshold) {
            \ORM::forTable(ORM_TABLE_PREFIX.'reports')
                ->whereLte('zapped', $threshold->zapped)
                ->deleteMany();
        }

        return true;
    }

    public static function hasReports()
    {
        Container::get('hooks')->fire('get_reports_start');

        $result_header = \ORM::for_table(ORM_TABLE_PREFIX.'reports')->where_null('zapped');
        $result_header = Container::get('hooks')->fireDB('get_reports_query', $result_header);

        return (bool) $result_header->find_one();
    }

    public function getReports()
    {
        $reports = [];
        $select_reports = [
            'r.id',
            'r.topic_id',
            'r.forum_id',
            'r.reported_by',
            'r.created',
            'r.message',
            'pid' => 'p.id',
            't.subject',
            'f.forum_name',
            'reporter' => 'u.username'
        ];
        $reports = \ORM::for_table(ORM_TABLE_PREFIX.'reports')
            ->table_alias('r')
            ->select_many($select_reports)
            ->left_outer_join(ORM_TABLE_PREFIX.'posts', ['r.post_id', '=', 'p.id'], 'p')
            ->left_outer_join(ORM_TABLE_PREFIX.'topics', ['r.topic_id', '=', 't.id'], 't')
            ->left_outer_join(ORM_TABLE_PREFIX.'forums', ['r.forum_id', '=', 'f.id'], 'f')
            ->left_outer_join(ORM_TABLE_PREFIX.'users', ['r.reported_by', '=', 'u.id'], 'u')
            ->where_null('r.zapped')
            ->order_by_desc('created');
        $reports = Container::get('hooks')->fireDB('model.admin.reports.get_reports.query', $reports);
        $reports = $reports->find_array();

        $reports = Container::get('hooks')->fire('model.admin.reports.get_reports', $reports);
        return $reports;
    }

    public function getZappedReports()
    {
        $zapped_reports = [];
        $select_zapped_reports = [
            'r.id',
            'r.topic_id',
            'r.forum_id',
            'r.reported_by',
            'r.message',
            'r.zapped',
            'zapped_by_id' => 'r.zapped_by',
            'pid' => 'p.id',
            't.subject',
            'f.forum_name',
            'reporter' => 'u.username',
            'zapped_by' => 'u2.username'
        ];
        $zapped_reports = \ORM::for_table(ORM_TABLE_PREFIX.'reports')
            ->table_alias('r')
            ->select_many($select_zapped_reports)
            ->left_outer_join(ORM_TABLE_PREFIX.'posts', ['r.post_id', '=', 'p.id'], 'p')
            ->left_outer_join(ORM_TABLE_PREFIX.'topics', ['r.topic_id', '=', 't.id'], 't')
            ->left_outer_join(ORM_TABLE_PREFIX.'forums', ['r.forum_id', '=', 'f.id'], 'f')
            ->left_outer_join(ORM_TABLE_PREFIX.'users', ['r.reported_by', '=', 'u.id'], 'u')
            ->left_outer_join(ORM_TABLE_PREFIX.'users', ['r.zapped_by', '=', 'u2.id'], 'u2')
            ->where_not_null('r.zapped')
            ->order_by_desc('zapped')
            ->limit(10);
        $zapped_reports = Container::get('hooks')
            ->fireDB('model.admin.reports.get_zapped_reports.query', $zapped_reports);
        $zapped_reports = $zapped_reports->find_array();

        $zapped_reports = Container::get('hooks')
            ->fire('model.admin.reports.get_zapped_reports', $zapped_reports);
        return $zapped_reports;
    }
}
