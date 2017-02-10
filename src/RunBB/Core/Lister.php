<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Core;

use RunBB\Core\Interfaces\Container;

class Lister
{
    /**
     * Get all valid plugin files.
     * @param array $list
     * @return array
     */
    public static function getPlugins(array $list = [])
    {
        $plugins = [];

        if (!empty($list)) {
            foreach ($list as $plug) {
                if (class_exists($plug['class'])) {
                    $plugins[] = json_decode($plug['class']::getInfo());
                }
            }
        }

        return $plugins;
    }

    /**
     * Get all official plugins using GitHub API
     */
    public static function getOfficialPlugins()
    {
        $plugins = [];

        // Get the official list from the website
        $content = json_decode(AdminUtils::get_content('http://featherbb.org/plugins.json'));

        // If internet is available
        if (!is_null($content)) {
            foreach ($content as $plugin) {
                // Get information from each repo
                // TODO: cache
                $plugins[] = json_decode(AdminUtils::get_content('https://raw.githubusercontent.com/featherbb/'.$plugin.'/master/featherbb.json'));
            }
        }

        return $plugins;
    }

    /**
     * Get available styles
     */
    public static function getStyles()
    {
        $styles = [];

        $iterator = new \DirectoryIterator(ForumEnv::get('WEB_ROOT').'style/themes/');
        foreach ($iterator as $child) {
            if (!$child->isDot() && $child->isDir() && file_exists($child->getPathname().DIRECTORY_SEPARATOR.'style.css')) {
                // If the theme is well formed, add it to the list
                $styles[] = $child->getFileName();
            }
        }

        natcasesort($styles);
        return $styles;
    }

    /**
     * Get available langs
     */
    public static function getLangs($folder = '')
    {
        $langs = [];

        $iterator = new \DirectoryIterator(ForumEnv::get('FORUM_ROOT').'lang/');
        foreach ($iterator as $child) {
            if (!$child->isDot() && $child->isDir() && file_exists($child->getPathname().DIRECTORY_SEPARATOR.'common.po')) {
                // If the lang pack is well formed, add it to the list
                $langs[] = $child->getFileName();
            }
        }

        natcasesort($langs);
        return $langs;
    }
}
