<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher.
 */

namespace RunBB\Core;

class Plugin
{
    protected $c;

    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
    }

    public function getActivePlugins()
    {
        $activePlugins = Container::get('cache')->isCached('activePlugins') ?
            Container::get('cache')->retrieve('activePlugins') : [];
        // try restore
        if (empty($activePlugins)) {
            $activePlugins = $this->setActivePlugins();
        }

        return $activePlugins;
    }

    public function setActivePlugins()
    {
        $activePlugins = [];
        $results = \ORM::for_table(ORM_TABLE_PREFIX . 'plugins')->select('name')->where('active', 1)->find_array();
        foreach ($results as $plugin) {
            $activePlugins[] = $plugin['name'];
        }
        Container::get('cache')->store('activePlugins', $activePlugins);

        return $activePlugins;
    }

    /**
     * Run activated plugins
     */
    public function loadPlugins()
    {
        $activePlugins = $this->getActivePlugins();
        foreach ($activePlugins as $plugin) {
            if ($class = $this->load($plugin)) {
                $class->run();
            }
        }
    }

    public function getAdminMenu($items)
    {
        if (method_exists($this, 'adminMenu')) {
            $items[] = $this::adminMenu();
        }

        return $items;
    }

    public function getName($items)
    {
        // Split name
        $classNamespace = explode('\\', get_class($this));
        $className = end($classNamespace);

        // Prettify and return name of child class
        preg_match_all('%[A-Z]*[a-z]+%m', $className, $result, PREG_PATTERN_ORDER);

        $items[] = Utils::escape(implode(' ', $result[0]));

        return $items;
    }

    /**
     * Activate a plugin
     */
    public function activate($name, $needInstall = true)
    {
        // Check if plugin name is valid
        if ($class = $this->load($name)) {
            // Do we need to run extra code for installation ?
            if ($needInstall) {
                $class->install();
            }
        }
    }

    /**
     * Default empty install function to avoid errors when activating.
     * Daughter classes may override this method for custom install.
     */
    public function install()
    {
    }

    /**
     * Default empty install function to avoid erros when deactivating.
     * Daughter classes may override this method for custom deactivation.
     */
    public function deactivate()
    {
    }

    public function uninstall($name)
    {
        // Check if plugin name is valid
        if ($class = $this->load($name)) {
            // Do we need to run extra code for installation ?
            if (method_exists($class, 'remove')) {
                $class->remove();
            }
        }
    }

    public function run()
    {
        // Daughter classes HAVE TO override this method for custom run
    }

    protected function load($plugin)
    {
        static $plugins = false;

        // "Complex" plugins which need to register namespace via bootstrap.php
        if (file_exists($file = ForumEnv::get('FORUM_ROOT') . 'plugins/' . $plugin . '/bootstrap.php')) {
            $className = require $file;

            $class = new $className($this->c);
            return $class;
        }
        // Simple plugins, only a featherbb.json and the main class
        if (file_exists($file = $this->checkSimple($plugin))) {
            require $file;
            $className = '\\RunBB\\Plugins\\' . $this->getNamespace($plugin);
            $class = new $className($this->c);
            return $class;
        }
        // check new system
        if (!$plugins) {// TODO rebuild with model
            $plugins = \ORM::for_table(ORM_TABLE_PREFIX . 'plugins')->findArray();
        }
        $key = Utils::recursiveArraySearch($plugin, $plugins);
        if ($key !== false) {
            $class = $plugins[$key]['class'];
            // check class registered
            if (class_exists($class)) {
                return new $class($this->c);
            }
        }
        // Invalid plugin
        return false;
    }

    // Clean a Simple Plugin's parent folder name to load it
    protected function getNamespace($path)
    {
        return str_replace(" ", "", str_replace("/", "\\", ucwords(str_replace(
            '-',
            ' ',
            str_replace('/ ', '/', ucwords(str_replace('/', '/ ', $path)))
        ))));
    }

    // For plugins that don't need to provide a Composer autoloader, check if it can be loaded
    protected function checkSimple($plugin)
    {
        return ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $plugin .
            DIRECTORY_SEPARATOR . $this->getNamespace($plugin) . '.php';
    }
}
