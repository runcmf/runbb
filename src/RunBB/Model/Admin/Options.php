<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model\Admin;

use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use RunBB\Model\Cache;

class Options
{
    public function updateOptions()
    {
        $form = [
            'board_title'            => Utils::trim(Input::post('form_board_title')),
            'board_desc'            => Utils::trim(Input::post('form_board_desc')),
            'base_url'                => Utils::trim(Input::post('form_base_url')),
            'default_timezone'        => floatval(Input::post('form_default_timezone')),
            'default_dst'            => Input::post('form_default_dst') != '1' ? '0' : '1',
            'default_lang'            => Utils::trim(Input::post('form_default_lang')),
            'default_style'            => Utils::trim(Input::post('form_default_style')),
            'time_format'            => Utils::trim(Input::post('form_time_format')),
            'date_format'            => Utils::trim(Input::post('form_date_format')),
            'timeout_visit'            => (int)(Input::post('form_timeout_visit') > 0) ?
                (int)Input::post('form_timeout_visit') : 1,
            'timeout_online'        => (int)(Input::post('form_timeout_online') > 0) ?
                (int)Input::post('form_timeout_online') : 1,
            'redirect_delay'        => (int)(Input::post('form_redirect_delay') >= 0) ?
                (int)Input::post('form_redirect_delay') : 0,
            'show_version'            => Input::post('form_show_version') != '1' ? '0' : '1',
            'show_user_info'        => Input::post('form_show_user_info') != '1' ? '0' : '1',
            'show_post_count'        => Input::post('form_show_post_count') != '1' ? '0' : '1',
            'smilies'                => Input::post('form_smilies') != '1' ? '0' : '1',
            'smilies_sig'            => Input::post('form_smilies_sig') != '1' ? '0' : '1',
            'make_links'            => Input::post('form_make_links') != '1' ? '0' : '1',
            'topic_review'            => (int)Input::post('form_topic_review') >= 0 ?
                (int)Input::post('form_topic_review') : 0,
            'disp_topics_default'    => (int)Input::post('form_disp_topics_default'),
            'disp_posts_default'    => (int)Input::post('form_disp_posts_default'),
            'indent_num_spaces'        => (int)Input::post('form_indent_num_spaces') >= 0 ?
                (int)Input::post('form_indent_num_spaces') : 0,
            'quote_depth'            => (int)Input::post('form_quote_depth') > 0 ?
                (int)Input::post('form_quote_depth') : 1,
            'quickpost'                => Input::post('form_quickpost') != '1' ? '0' : '1',
            'users_online'            => Input::post('form_users_online') != '1' ? '0' : '1',
            'censoring'                => Input::post('form_censoring') != '1' ? '0' : '1',
            'signatures'            => Input::post('form_signatures') != '1' ? '0' : '1',
            'show_dot'                => Input::post('form_show_dot') != '1' ? '0' : '1',
            'topic_views'            => Input::post('form_topic_views') != '1' ? '0' : '1',
            'quickjump'                => Input::post('form_quickjump') != '1' ? '0' : '1',
            'gzip'                    => Input::post('form_gzip') != '1' ? '0' : '1',
            'search_all_forums'        => Input::post('form_search_all_forums') != '1' ? '0' : '1',
            'additional_navlinks'    => Utils::trim(Input::post('form_additional_navlinks')),
            'feed_type'                => intval(Input::post('form_feed_type')),
            'feed_ttl'                => intval(Input::post('form_feed_ttl')),
            'report_method'            => intval(Input::post('form_report_method')),
            'mailing_list'            => Utils::trim(Input::post('form_mailing_list')),
            'avatars'                => Input::post('form_avatars') != '1' ? '0' : '1',
            'avatars_dir'            => Utils::trim(Input::post('form_avatars_dir')),
            'avatars_width'            => (int)Input::post('form_avatars_width') > 0 ?
                (int)Input::post('form_avatars_width') : 1,
            'avatars_height'        => (int)Input::post('form_avatars_height') > 0 ?
                (int)Input::post('form_avatars_height') : 1,
            'avatars_size'            => (int)Input::post('form_avatars_size') > 0 ?
                (int)Input::post('form_avatars_size') : 1,
            'admin_email'            => strtolower(Utils::trim(Input::post('form_admin_email'))),
            'webmaster_email'        => strtolower(Utils::trim(Input::post('form_webmaster_email'))),
            'forum_subscriptions'    => Input::post('form_forum_subscriptions') != '1' ? '0' : '1',
            'topic_subscriptions'    => Input::post('form_topic_subscriptions') != '1' ? '0' : '1',
            'smtp_host'                => Utils::trim(Input::post('form_smtp_host')),
            'smtp_user'                => Utils::trim(Input::post('form_smtp_user')),
            'smtp_ssl'                => Input::post('form_smtp_ssl') != '1' ? '0' : '1',
            'regs_allow'            => Input::post('form_regs_allow') != '1' ? '0' : '1',
            'regs_verify'            => Input::post('form_regs_verify') != '1' ? '0' : '1',
            'regs_report'            => Input::post('form_regs_report') != '1' ? '0' : '1',
            'rules'                    => Input::post('form_rules') != '1' ? '0' : '1',
            'rules_message'            => Utils::trim(Input::post('form_rules_message')),
            'default_email_setting'    => (int)Input::post('form_default_email_setting'),
            'announcement'            => Input::post('form_announcement') != '1' ? '0' : '1',
            'announcement_message'    => Utils::trim(Input::post('form_announcement_message')),
            'maintenance'            => Input::post('form_maintenance') != '1' ? '0' : '1',
            'maintenance_message'    => Utils::trim(Input::post('form_maintenance_message')),
        ];

        $form = Container::get('hooks')->fire('model.admin.options.update_options.form', $form);

        if ($form['board_title'] == '') {
            throw new  RunBBException(__('Must enter title message'), 400);
        }

        // Make sure base_url doesn't end with a slash
        if (substr($form['base_url'], -1) == '/') {
            $form['base_url'] = substr($form['base_url'], 0, -1);
        }

        // Convert IDN to Punycode if needed
        if (preg_match('/[^\x00-\x7F]/', $form['base_url'])) {
            if (!function_exists('idn_to_ascii')) {
                throw new  RunBBException(__('Base URL problem'), 400);
            } else {
                $form['base_url'] = idn_to_ascii($form['base_url']);
            }
        }

        $languages = \RunBB\Core\Lister::getLangs();
        if (!in_array($form['default_lang'], $languages)) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        $styles = \RunBB\Core\Lister::getStyles();
        if (!in_array($form['default_style'], $styles)) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        if ($form['time_format'] == '') {
            $form['time_format'] = 'H:i:s';
        }

        if ($form['date_format'] == '') {
            $form['date_format'] = 'Y-m-d';
        }

        if (!Container::get('email')->isValidEmail($form['admin_email'])) {
            throw new  RunBBException(__('Invalid e-mail message'), 400);
        }

        if (!Container::get('email')->isValidEmail($form['webmaster_email'])) {
            throw new  RunBBException(__('Invalid webmaster e-mail message'), 400);
        }

        if ($form['mailing_list'] != '') {
            $form['mailing_list'] = strtolower(preg_replace('%\s%S', '', $form['mailing_list']));
        }

        // Make sure avatars_dir doesn't end with a slash
        if (substr($form['avatars_dir'], -1) == '/') {
            $form['avatars_dir'] = substr($form['avatars_dir'], 0, -1);
        }

        if ($form['additional_navlinks'] != '') {
            $form['additional_navlinks'] = Utils::trim(Utils::linebreaks($form['additional_navlinks']));
        }

        // Change or enter a SMTP password
        if (Input::post('form_smtp_change_pass')) {
            $smtp_pass1 = Input::post('form_smtp_pass1') ? Utils::trim(Input::post('form_smtp_pass1')) : '';
            $smtp_pass2 = Input::post('form_smtp_pass2') ? Utils::trim(Input::post('form_smtp_pass2')) : '';

            if ($smtp_pass1 == $smtp_pass2) {
                $form['smtp_pass'] = $smtp_pass1;
            } else {
                throw new  RunBBException(__('SMTP passwords did not match'), 400);
            }
        }

        if ($form['announcement_message'] != '') {
            $form['announcement_message'] = Utils::linebreaks($form['announcement_message']);
        } else {
            $form['announcement_message'] = __('Enter announcement here');
            $form['announcement'] = '0';
        }

        if ($form['rules_message'] != '') {
            $form['rules_message'] = Utils::linebreaks($form['rules_message']);
        } else {
            $form['rules_message'] = __('Enter rules here');
            $form['rules'] = '0';
        }

        if ($form['maintenance_message'] != '') {
            $form['maintenance_message'] = Utils::linebreaks($form['maintenance_message']);
        } else {
            $form['maintenance_message'] = __('Default maintenance message');
            $form['maintenance'] = '0';
        }

        // Make sure the number of displayed topics and posts is between 3 and 75
        if ($form['disp_topics_default'] < 3) {
            $form['disp_topics_default'] = 3;
        } elseif ($form['disp_topics_default'] > 75) {
            $form['disp_topics_default'] = 75;
        }

        if ($form['disp_posts_default'] < 3) {
            $form['disp_posts_default'] = 3;
        } elseif ($form['disp_posts_default'] > 75) {
            $form['disp_posts_default'] = 75;
        }

        if ($form['feed_type'] < 0 || $form['feed_type'] > 2) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        if ($form['feed_ttl'] < 0) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        if ($form['report_method'] < 0 || $form['report_method'] > 2) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        if ($form['default_email_setting'] < 0 || $form['default_email_setting'] > 2) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        if ($form['timeout_online'] >= $form['timeout_visit']) {
            throw new  RunBBException(__('Timeout error message'), 400);
        }

        foreach ($form as $key => $input) {
            // Only update values that have changed
            if (array_key_exists('o_'.$key, Container::get('forum_settings')) &&
                ForumSettings::get('o_'.$key) != $input) {
                if ($input != '' || is_int($input)) {
                    $set = ['conf_value' => $input];
                } else {
                    $set = ['conf_value' => 'NULL'];
                }
                \ORM::for_table(ORM_TABLE_PREFIX.'config')
                    ->where('conf_name', 'o_'.$key)
                    ->find_one()
                    ->set($set)
                    ->save();
            }
        }

        // Regenerate the config cache
        Container::get('cache')->store('config', Cache::getConfig());
        $this->clearFeedCache();

        return Router::redirect(Router::pathFor('adminOptions'), __('Options updated redirect'));
    }

    public function clearFeedCache()
    {
        $d = dir(ForumEnv::get('FORUM_CACHE_DIR'));
        $d = Container::get('hooks')->fire('model.admin.options.clear_feed_cache.directory', $d);
        while (($entry = $d->read()) !== false) {
            if (substr($entry, 0, 10) == 'cache_feed' && substr($entry, -4) == '.php') {
                @unlink(ForumEnv::get('FORUM_CACHE_DIR').$entry);
            }
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate(ForumEnv::get('FORUM_CACHE_DIR').$entry, true);
            } elseif (function_exists('apc_delete_file')) {
                @apc_delete_file(ForumEnv::get('FORUM_CACHE_DIR').$entry);
            }
        }
        $d->close();
    }

    public function getStyles()
    {
        $styles = \RunBB\Core\Lister::getStyles();
        $styles = Container::get('hooks')->fire('model.admin.options.get_styles.styles', $styles);

        $output = '';

        foreach ($styles as $temp) {
            if (ForumSettings::get('o_default_style') == $temp) {
                $output .= "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.
                    str_replace('_', ' ', $temp).'</option>'."\n";
            } else {
                $output .= "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.
                    str_replace('_', ' ', $temp).'</option>'."\n";
            }
        }

        $output = Container::get('hooks')->fire('model.admin.options.get_styles.output', $output);
        return $output;
    }

    public function getLangs()
    {
        $langs = \RunBB\Core\Lister::getLangs();
        $langs = Container::get('hooks')->fire('model.admin.options.get_langs.langs', $langs);

        $output = '';

        foreach ($langs as $temp) {
            if (ForumSettings::get('o_default_lang') == $temp) {
                $output .= "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'" selected="selected">'.
                    str_replace('_', ' ', $temp).'</option>'."\n";
            } else {
                $output .= "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$temp.'">'.
                    str_replace('_', ' ', $temp).'</option>'."\n";
            }
        }

        $output = Container::get('hooks')->fire('model.admin.options.get_langs.output', $output);
        return $output;
    }

    public function getTimes()
    {
        $times = [5, 15, 30, 60];
        $times = Container::get('hooks')->fire('model.admin.options.get_times.times', $times);

        $output = '';

        foreach ($times as $time) {
            $output .= "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$time.'"'.
                (ForumSettings::get('o_feed_ttl') == $time ? ' selected="selected"' : '').'>'.
                sprintf(__('Minutes'), $time).'</option>'."\n";
        }

        $output = Container::get('hooks')->fire('model.admin.options.get_times.output', $output);
        return $output;
    }
}
