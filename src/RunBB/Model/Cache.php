<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

class Cache
{
    public static function getConfig()
    {
        $result = \ORM::forTable(ORM_TABLE_PREFIX . 'config')
            ->findArray();
        $config = [];
        foreach ($result as $item) {
            $config[$item['conf_name']] = $item['conf_value'];
        }
        return $config;
    }

    public static function getBans()
    {
        return \ORM::forTable(ORM_TABLE_PREFIX . 'bans')
            ->findArray();
    }

    public static function getCensoring($select_censoring = 'search_for')
    {
        $result = \ORM::forTable(ORM_TABLE_PREFIX . 'censoring')
            ->selectMany($select_censoring)
            ->findArray();
        $output = [];

        foreach ($result as $item) {
            $output[] = ($select_censoring == 'search_for') ? '%(?<=[^\p{L}\p{N}])(' . str_replace(
                '\*',
                '[\p{L}\p{N}]*?',
                preg_quote($item['search_for'], '%')
            ) . ')(?=[^\p{L}\p{N}])%iu' : $item['replace_with'];
        }
        return $output;
    }

    public static function getUsersInfo()
    {
        $stats = [];
        $select_get_users_info = ['id', 'username'];
        $stats['total_users'] = \ORM::forTable(ORM_TABLE_PREFIX . 'users')
            ->whereNotEqual('group_id', ForumEnv::get('FEATHER_UNVERIFIED'))
            ->whereNotEqual('id', 1)
            ->count();
        $stats['last_user'] = \ORM::forTable(ORM_TABLE_PREFIX . 'users')
            ->selectMany($select_get_users_info)
            ->whereNotEqual('group_id', ForumEnv::get('FEATHER_UNVERIFIED'))
            ->orderByDesc('registered')
            ->limit(1)
            ->findArray()[0];
        return $stats;
    }

    public static function getAdminIds()
    {
        return \ORM::forTable(ORM_TABLE_PREFIX . 'users')
            ->select('id')
            ->where('group_id', ForumEnv::get('FEATHER_ADMIN'))
            ->findArray();
    }

    public static function getQuickjump()
    {
        $select_quickjump = ['g_id', 'g_read_board'];
        $read_perms = \ORM::forTable(ORM_TABLE_PREFIX . 'groups')
            ->selectMany($select_quickjump)
            ->where('g_read_board', 1)
            ->findArray();

        $output = [];
        foreach ($read_perms as $item) {
            $select_quickjump = ['cid' => 'c.id', 'c.cat_name', 'fid' => 'f.id', 'f.forum_name', 'f.redirect_url'];
//            $where_quickjump = array(
//                array('fp.read_forum' => 'IS NULL'),
//                array('fp.read_forum' => '1')
//            );
            $order_by_quickjump = 'c.disp_position, c.id, f.disp_position';

            $result = \ORM::forTable(ORM_TABLE_PREFIX . 'categories')
                ->tableAlias('c')
                ->selectMany($select_quickjump)
                ->innerJoin(ORM_TABLE_PREFIX . 'forums', ['c.id', '=', 'f.cat_id'], 'f')
//                        ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//                        ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms',
// array('fp.group_id', '=', $item['g_id']), null, true)
                ->leftOuterJoin(
                    ORM_TABLE_PREFIX . 'forum_perms',
                    '(fp.forum_id=f.id AND fp.group_id=' . (int)$item['g_id'] . ')',
                    'fp'
                )
//                        ->where_any_is($where_quickjump)
                ->whereRaw('(fp.read_forum IS NULL OR fp.read_forum=1)')
                ->whereNull('f.redirect_url')
                ->orderByExpr($order_by_quickjump)
                ->findMany();

            $forum_data = [];
            foreach ($result as $forum) {
                if (!isset($forum_data[$forum['cid']])) {
                    $forum_data[$forum['cid']] = ['cat_name' => $forum['cat_name'],
                        'cat_position' => $forum['cat_position'],
                        'cat_forums' => []];
                }
                $forum_data[$forum['cid']]['cat_forums'][] = ['forum_id' => $forum['fid'],
                    'forum_name' => $forum['forum_name'],
                    'position' => $forum['forum_position']];
            }
            $output[(int)$item['g_id']] = $forum_data;
        }
        return $output;
    }

    public static function getStopwords($lang_path)
    {
        $files = new \DirectoryIterator($lang_path);
        $stopwords = [];
        foreach ($files as $file) {
            if (!$file->isDot() && $file->getBasename() != '.DS_Store' && $file->isDir() &&
                file_exists($file->getPathName() . '/stopwords.txt')
            ) {
                $stopwords = array_merge($stopwords, file($file->getPathName() . '/stopwords.txt'));
            }
        }
        return array_map('trim', $stopwords);
    }
}
