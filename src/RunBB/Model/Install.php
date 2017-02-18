<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Core\Random;
use RunBB\Core\Utils;

class Install
{
    protected $database_scheme = [
        'bans' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `username` varchar(200) DEFAULT NULL,
            `ip` varchar(255) DEFAULT NULL,
            `email` varchar(80) DEFAULT NULL,
            `message` varchar(255) DEFAULT NULL,
            `expire` int(10) unsigned DEFAULT NULL,
            `ban_creator` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `bans_username_idx` (`username`(25))
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'categories' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `cat_name` varchar(80) NOT NULL DEFAULT 'New Category',
            `disp_position` int(10) NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'censoring' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `search_for` varchar(60) NOT NULL DEFAULT '',
            `replace_with` varchar(60) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'config' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `conf_name` varchar(255) NOT NULL DEFAULT '',
            `conf_value` text,
            PRIMARY KEY (`id`),
            UNIQUE KEY `conf_name_idx` (`conf_name`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'forum_perms' => "CREATE TABLE IF NOT EXISTS %t% (
            `group_id` int(10) NOT NULL DEFAULT '0',
            `forum_id` int(10) NOT NULL DEFAULT '0',
            `read_forum` tinyint(1) NOT NULL DEFAULT '1',
            `post_replies` tinyint(1) NOT NULL DEFAULT '1',
            `post_topics` tinyint(1) NOT NULL DEFAULT '1',
            PRIMARY KEY (`group_id`,`forum_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'forum_subscriptions' => "CREATE TABLE IF NOT EXISTS %t% (
            `user_id` int(10) unsigned NOT NULL DEFAULT '0',
            `forum_id` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`user_id`,`forum_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'forums' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `forum_name` varchar(80) NOT NULL DEFAULT 'New forum',
            `forum_desc` text,
            `redirect_url` varchar(100) DEFAULT NULL,
            `moderators` text,
            `num_topics` mediumint(8) unsigned NOT NULL DEFAULT '0',
            `num_posts` mediumint(8) unsigned NOT NULL DEFAULT '0',
            `last_post` int(10) unsigned DEFAULT NULL,
            `last_post_id` int(10) unsigned DEFAULT NULL,
            `last_poster` varchar(200) DEFAULT NULL,
            `sort_by` tinyint(1) NOT NULL DEFAULT '0',
            `disp_position` int(10) NOT NULL DEFAULT '0',
            `cat_id` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'groups' => "CREATE TABLE  IF NOT EXISTS %t% (
            `g_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `g_title` varchar(50) NOT NULL DEFAULT '',
            `g_user_title` varchar(50) DEFAULT NULL,
            `g_promote_min_posts` int(10) unsigned NOT NULL DEFAULT '0',
            `g_promote_next_group` int(10) unsigned NOT NULL DEFAULT '0',
            `g_moderator` tinyint(1) NOT NULL DEFAULT '0',
            `g_mod_edit_users` tinyint(1) NOT NULL DEFAULT '0',
            `g_mod_rename_users` tinyint(1) NOT NULL DEFAULT '0',
            `g_mod_change_passwords` tinyint(1) NOT NULL DEFAULT '0',
            `g_mod_ban_users` tinyint(1) NOT NULL DEFAULT '0',
            `g_mod_promote_users` tinyint(1) NOT NULL DEFAULT '0',
            `g_read_board` tinyint(1) NOT NULL DEFAULT '1',
            `g_view_users` tinyint(1) NOT NULL DEFAULT '1',
            `g_post_replies` tinyint(1) NOT NULL DEFAULT '1',
            `g_post_topics` tinyint(1) NOT NULL DEFAULT '1',
            `g_edit_posts` tinyint(1) NOT NULL DEFAULT '1',
            `g_delete_posts` tinyint(1) NOT NULL DEFAULT '1',
            `g_delete_topics` tinyint(1) NOT NULL DEFAULT '1',
            `g_post_links` tinyint(1) NOT NULL DEFAULT '1',
            `g_set_title` tinyint(1) NOT NULL DEFAULT '1',
            `g_search` tinyint(1) NOT NULL DEFAULT '1',
            `g_search_users` tinyint(1) NOT NULL DEFAULT '1',
            `g_send_email` tinyint(1) NOT NULL DEFAULT '1',
            `g_post_flood` smallint(6) NOT NULL DEFAULT '30',
            `g_search_flood` smallint(6) NOT NULL DEFAULT '30',
            `g_email_flood` smallint(6) NOT NULL DEFAULT '60',
            `g_report_flood` smallint(6) NOT NULL DEFAULT '60',
            `g_parser_plugins` text,
            `inherit` text,
            PRIMARY KEY (`g_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'online' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `user_id` int(10) unsigned NOT NULL DEFAULT '1',
            `ident` varchar(200) NOT NULL DEFAULT '',
            `logged` int(10) unsigned NOT NULL DEFAULT '0',
            `idle` tinyint(1) NOT NULL DEFAULT '0',
            `last_post` int(10) unsigned DEFAULT NULL,
            `last_search` int(10) unsigned DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `online_user_id_ident_idx` (`user_id`,`ident`(25)),
            KEY `online_ident_idx` (`ident`(25)),
            KEY `online_logged_idx` (`logged`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'permissions' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `permission_name` varchar(255) DEFAULT NULL,
            `allow` tinyint(1) DEFAULT NULL,
            `deny` tinyint(1) DEFAULT NULL,
            `user` int(11) DEFAULT NULL,
            `group` int(11) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'plugins' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(200) NOT NULL DEFAULT '',
            `class` varchar(200) NOT NULL DEFAULT '',
            `installed` tinyint(1) unsigned NOT NULL DEFAULT '1',
            `active` tinyint(1) unsigned NOT NULL DEFAULT '1',
            PRIMARY KEY (`id`),
            UNIQUE KEY `plugin_namex` (`name`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'posts' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `poster` varchar(200) NOT NULL DEFAULT '',
            `poster_id` int(10) unsigned NOT NULL DEFAULT '1',
            `poster_ip` varchar(39) DEFAULT NULL,
            `poster_email` varchar(80) DEFAULT NULL,
            `message` mediumtext,
            `hide_smilies` tinyint(1) NOT NULL DEFAULT '0',
            `posted` int(10) unsigned NOT NULL DEFAULT '0',
            `edited` int(10) unsigned DEFAULT NULL,
            `edited_by` varchar(200) DEFAULT NULL,
            `topic_id` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `posts_topic_id_idx` (`topic_id`),
            KEY `posts_multi_idx` (`poster_id`,`topic_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'preferences' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `preference_name` tinytext,
            `preference_value` tinytext,
            `user` int(11) DEFAULT NULL,
            `group` int(11) DEFAULT NULL,
            `default` tinyint(1) DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'reports' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `post_id` int(10) unsigned NOT NULL DEFAULT '0',
            `topic_id` int(10) unsigned NOT NULL DEFAULT '0',
            `forum_id` int(10) unsigned NOT NULL DEFAULT '0',
            `reported_by` int(10) unsigned NOT NULL DEFAULT '0',
            `created` int(10) unsigned NOT NULL DEFAULT '0',
            `message` text,
            `zapped` int(10) unsigned DEFAULT NULL,
            `zapped_by` int(10) unsigned DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `reports_zapped_idx` (`zapped`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'search_cache' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL DEFAULT '0',
            `ident` varchar(200) NOT NULL DEFAULT '',
            `search_data` mediumtext,
            PRIMARY KEY (`id`),
            KEY `search_cache_ident_idx` (`ident`(8))
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'search_matches' => "CREATE TABLE IF NOT EXISTS %t% (
            `post_id` int(10) unsigned NOT NULL DEFAULT '0',
            `word_id` int(10) unsigned NOT NULL DEFAULT '0',
            `subject_match` tinyint(1) NOT NULL DEFAULT '0',
            KEY `search_matches_word_id_idx` (`word_id`),
            KEY `search_matches_post_id_idx` (`post_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'search_words' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `word` varchar(20) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL DEFAULT '',
            PRIMARY KEY (`word`),
            KEY `search_words_id_idx` (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'topic_subscriptions' => "CREATE TABLE IF NOT EXISTS %t% (
            `user_id` int(10) unsigned NOT NULL DEFAULT '0',
            `topic_id` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`user_id`,`topic_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'topics' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `poster` varchar(200) NOT NULL DEFAULT '',
            `subject` varchar(255) NOT NULL DEFAULT '',
            `posted` int(10) unsigned NOT NULL DEFAULT '0',
            `first_post_id` int(10) unsigned NOT NULL DEFAULT '0',
            `last_post` int(10) unsigned NOT NULL DEFAULT '0',
            `last_post_id` int(10) unsigned NOT NULL DEFAULT '0',
            `last_poster` varchar(200) DEFAULT NULL,
            `num_views` mediumint(8) unsigned NOT NULL DEFAULT '0',
            `num_replies` mediumint(8) unsigned NOT NULL DEFAULT '0',
            `closed` tinyint(1) NOT NULL DEFAULT '0',
            `sticky` tinyint(1) NOT NULL DEFAULT '0',
            `moved_to` int(10) unsigned DEFAULT NULL,
            `forum_id` int(10) unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (`id`),
            KEY `topics_forum_id_idx` (`forum_id`),
            KEY `topics_moved_to_idx` (`moved_to`),
            KEY `topics_last_post_idx` (`last_post`),
            KEY `topics_first_post_id_idx` (`first_post_id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'users' => "CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `group_id` int(10) unsigned NOT NULL DEFAULT '3',
            `username` varchar(200) NOT NULL DEFAULT '',
            `password` varchar(40) NOT NULL DEFAULT '',
            `email` varchar(80) NOT NULL DEFAULT '',
            `title` varchar(50) DEFAULT NULL,
            `realname` varchar(40) DEFAULT NULL,
            `url` varchar(100) DEFAULT NULL,
            `jabber` varchar(80) DEFAULT NULL,
            `icq` varchar(12) DEFAULT NULL,
            `msn` varchar(80) DEFAULT NULL,
            `aim` varchar(30) DEFAULT NULL,
            `yahoo` varchar(30) DEFAULT NULL,
            `location` varchar(30) DEFAULT NULL,
            `signature` text,
            `disp_topics` tinyint(3) unsigned DEFAULT NULL,
            `disp_posts` tinyint(3) unsigned DEFAULT NULL,
            `email_setting` tinyint(1) NOT NULL DEFAULT '1',
            `notify_with_post` tinyint(1) NOT NULL DEFAULT '0',
            `auto_notify` tinyint(1) NOT NULL DEFAULT '0',
            `show_smilies` tinyint(1) NOT NULL DEFAULT '1',
            `show_img` tinyint(1) NOT NULL DEFAULT '1',
            `show_img_sig` tinyint(1) NOT NULL DEFAULT '1',
            `show_avatars` tinyint(1) NOT NULL DEFAULT '1',
            `show_sig` tinyint(1) NOT NULL DEFAULT '1',
            `timezone` float NOT NULL DEFAULT '0',
            `dst` tinyint(1) NOT NULL DEFAULT '0',
            `time_format` tinyint(1) NOT NULL DEFAULT '0',
            `date_format` tinyint(1) NOT NULL DEFAULT '0',
            `language` varchar(25) NOT NULL DEFAULT 'English',
            `style` varchar(25) NOT NULL DEFAULT 'runbb',
            `num_posts` int(10) unsigned NOT NULL DEFAULT '0',
            `last_post` int(10) unsigned DEFAULT NULL,
            `last_search` int(10) unsigned DEFAULT NULL,
            `last_email_sent` int(10) unsigned DEFAULT NULL,
            `last_report_sent` int(10) unsigned DEFAULT NULL,
            `registered` int(10) unsigned NOT NULL DEFAULT '0',
            `registration_ip` varchar(39) NOT NULL DEFAULT '0.0.0.0',
            `last_visit` int(10) unsigned NOT NULL DEFAULT '0',
            `admin_note` varchar(30) DEFAULT NULL,
            `activate_string` varchar(80) DEFAULT NULL,
            `activate_key` varchar(8) DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `users_username_idx` (`username`(25)),
            KEY `users_registered_idx` (`registered`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",];

    public function createTable($table_name, $sql)
    {
        $db = \ORM::get_db();
        $req = preg_replace('/%t%/', '`' . $table_name . '`', $sql);
        return $db->exec($req);
    }

    public function addData($table_name, array $data)
    {
        return (bool)\ORM::for_table(ORM_TABLE_PREFIX . $table_name)
            ->create()
            ->set($data)
            ->save();
    }

    public function addMockForum(array $arch)
    {
        foreach ($arch as $table_name => $data) {
            $this->addData($table_name, $data);
        }
    }

    public function saveConfig(array $data)
    {
        foreach ($data as $key => $value) {
            $this->addData('config', [
                'conf_name' => $key,
                'conf_value' => $value
            ]);
        }
    }

    public function getDatabaseScheme()
    {
        return $this->database_scheme;
    }

    public static function loadDefaultGroups()
    {
        $groups['Administrators'] = [
            'g_id' => 1,
            'g_title' => __('Administrators'),
            'g_user_title' => __('Administrator'),
            'g_moderator' => 0,
            'g_mod_edit_users' => 0,
            'g_mod_rename_users' => 0,
            'g_mod_change_passwords' => 0,
            'g_mod_ban_users' => 0,
            'g_read_board' => 1,
            'g_view_users' => 1,
            'g_post_replies' => 1,
            'g_post_topics' => 1,
            'g_edit_posts' => 1,
            'g_delete_posts' => 1,
            'g_delete_topics' => 1,
            'g_set_title' => 1,
            'g_search' => 1,
            'g_search_users' => 1,
            'g_send_email' => 1,
            'g_post_flood' => 0,
            'g_search_flood' => 0,
            'g_email_flood' => 0,
            'g_report_flood' => 0,
            'g_parser_plugins' => 'a:13:{i:0;s:9:\"Autoemail\";i:1;s:9:\"Autoimage\";i:2;s:8:\"Autolink\";i:3;s:'.
                '9:\"Autovideo\";i:4;s:9:\"Emoticons\";i:5;s:7:\"Escaper\";i:6;s:10:\"FancyPants\";i:7;s:'.
                '12:\"HTMLComments\";i:8;s:12:\"HTMLElements\";i:9;s:12:\"HTMLEntities\";i:10;s:8:\"Litedown\";i:11'.
                ';s:10:\"MediaEmbed\";i:12;s:10:\"PipeTables\";}',
            'inherit' => 'a:1:{i:0;i:2;}'
        ];
        $groups['Moderators'] = [
            'g_id' => 2,
            'g_title' => __('Moderators'),
            'g_user_title' => __('Moderator'),
            'g_moderator' => 1,
            'g_mod_edit_users' => 1,
            'g_mod_rename_users' => 1,
            'g_mod_change_passwords' => 1,
            'g_mod_ban_users' => 1,
            'g_read_board' => 1,
            'g_view_users' => 1,
            'g_post_replies' => 1,
            'g_post_topics' => 1,
            'g_edit_posts' => 1,
            'g_delete_posts' => 1,
            'g_delete_topics' => 1,
            'g_set_title' => 1,
            'g_search' => 1,
            'g_search_users' => 1,
            'g_send_email' => 1,
            'g_post_flood' => 0,
            'g_search_flood' => 0,
            'g_email_flood' => 0,
            'g_report_flood' => 0,
            'g_parser_plugins' => 'a:11:{i:0;s:9:\"Autoemail\";i:1;s:9:\"Autoimage\";i:2;s:8:\"Autolink\";i:3;s:'.
                '9:\"Autovideo\";i:4;s:9:\"Emoticons\";i:5;s:7:\"Escaper\";i:6;s:10:\"FancyPants\";i:7;s:'.
                '8:\"Keywords\";i:8;s:8:\"Litedown\";i:9;s:10:\"MediaEmbed\";i:10;s:10:\"PipeTables\";}',
            'inherit' => 'a:1:{i:0;i:4;}'
        ];
        $groups['Guests'] = [
            'g_id' => 3,
            'g_title' => __('Guests'),
            'g_user_title' => __('Guest'),
            'g_moderator' => 0,
            'g_mod_edit_users' => 0,
            'g_mod_rename_users' => 0,
            'g_mod_change_passwords' => 0,
            'g_mod_ban_users' => 0,
            'g_read_board' => 1,
            'g_view_users' => 1,
            'g_post_replies' => 0,
            'g_post_topics' => 0,
            'g_edit_posts' => 0,
            'g_delete_posts' => 0,
            'g_delete_topics' => 0,
            'g_set_title' => 0,
            'g_search' => 1,
            'g_search_users' => 1,
            'g_send_email' => 0,
            'g_post_flood' => 60,
            'g_search_flood' => 30,
            'g_email_flood' => 0,
            'g_report_flood' => 0,
            'g_parser_plugins' => 'a:4:{i:0;s:6:\"Censor\";i:1;s:9:\"Emoticons\";i:2;s:8:\"Litedown\";i:3;'.
                's:10:\"PipeTables\";}'
        ];
        $groups['Members'] = [
            'g_id' => 4,
            'g_title' => __('Members'),
            'g_user_title' => __('Member'),
            'g_moderator' => 0,
            'g_mod_edit_users' => 0,
            'g_mod_rename_users' => 0,
            'g_mod_change_passwords' => 0,
            'g_mod_ban_users' => 0,
            'g_read_board' => 1,
            'g_view_users' => 1,
            'g_post_replies' => 1,
            'g_post_topics' => 1,
            'g_edit_posts' => 1,
            'g_delete_posts' => 1,
            'g_delete_topics' => 1,
            'g_set_title' => 0,
            'g_search' => 1,
            'g_search_users' => 1,
            'g_send_email' => 1,
            'g_post_flood' => 60,
            'g_search_flood' => 30,
            'g_email_flood' => 60,
            'g_report_flood' => 60,
            'g_parser_plugins' => 'a:11:{i:0;s:9:\"Autoemail\";i:1;s:9:\"Autoimage\";i:2;s:8:\"Autolink\";i:3;s:'.
                '9:\"Autovideo\";i:4;s:6:\"Censor\";i:5;s:9:\"Emoticons\";i:6;s:7:\"Escaper\";i:7;s:'.
                '10:\"FancyPants\";i:8;s:8:\"Litedown\";i:9;s:10:\"MediaEmbed\";i:10;s:10:\"PipeTables\";}',
            'inherit' => 'a:1:{i:0;i:3;}'
        ];

        return $groups;
    }

    public static function loadDefaultUser()
    {
        return $user = [
            'group_id' => 3,
            'username' => __('Guest'),
            'password' => __('Guest'),
            'email' => __('Guest')
        ];
    }

    public static function loadAdminUser(array $data)
    {
        $now = time();
        return $user = [
            'group_id' => 1,
            'username' => $data['username'],
            'password' => Random::hash($data['password']),
            'email' => $data['email'],
            'language' => $data['default_lang'],
            'style' => $data['default_style'],
            'num_posts' => 1,
            'last_post' => $now,
            'registered' => $now,
            'registration_ip' => Utils::getIp(),
            'last_visit' => $now
        ];
    }

    public static function loadMockForumData(array $data)
    {
        $cat_name = __('Test category');
        $subject = __('Test post');
        $message = __('Message');
        $forum_name = __('Test forum');
        $forum_desc = __('This is just a test forum');
        $now = time();
        $ip = Utils::getIp();

        return $mock_data = [
            'categories' => [
                'cat_name' => $cat_name,
                'disp_position' => 1
            ],
            'forums' => [
                'forum_name' => $forum_name,
                'forum_desc' => $forum_desc,
                'num_topics' => 1,
                'num_posts' => 1,
                'last_post' => $now,
                'last_post_id' => 1,
                'last_poster' => $data['username'],
                'disp_position' => 1,
                'cat_id' => 1
            ],
            'topics' => [
                'poster' => $data['username'],
                'subject' => $subject,
                'posted' => $now,
                'first_post_id' => 1,
                'last_post' => $now,
                'last_post_id' => 1,
                'last_poster' => $data['username'],
                'forum_id' => 1
            ],
            'posts' => ['poster' => $data['username'],
                'poster_id' => 2,
                'poster_ip' => $ip,
                'message' => $message,
                'posted' => $now,
                'topic_id' => 1
            ]
        ];
    }
}
