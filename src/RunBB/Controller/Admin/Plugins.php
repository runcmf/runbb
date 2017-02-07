<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller\Admin;

use RunBB\Core\AdminUtils;
use RunBB\Exception\RunBBException;
use RunBB\Core\Lister;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use ZipArchive;

class Plugins
{
    private $c;

    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
        $this->model = new \RunBB\Model\Admin\Plugins($c);
        Lang::load('admin-common');
        Lang::load('admin-plugins');
    }

    /**
     * Download a plugin, unzip it and rename it
     * @param $req
     * @param $res
     * @param $args
     * @return mixed
     * @throws RunBBException
     */
    public function download($req, $res, $args)
    {
        $zipFile = ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $args['name'] . "-" . $args['version'] . '.zip';
        $zipResource = fopen($zipFile, "w");

        // Get the zip file straight from GitHub
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://codeload.github.com/featherbb/' . $args['name'] . '/zip/' . $args['version']);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_FILE, $zipResource);
        $page = curl_exec($ch);
        curl_close($ch);
        fclose($zipResource);

        if (!$page) {
            unlink(ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $args['name'] . "-" . $args['version'] . '.zip');
            throw new  RunBBException(__('Bad request'), 400);
        }

        $zip = new ZipArchive;

        if ($zip->open($zipFile) != true) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        $zip->extractTo(ForumEnv::get('FORUM_ROOT') . 'plugins');
        $zip->close();

        if (file_exists(ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $args['name'])) {
            AdminUtils::delete_folder(ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $args['name']);
        }
        rename(ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $args['name'] . "-" . $args['version'], ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $args['name']);
        unlink(ForumEnv::get('FORUM_ROOT') . 'plugins' . DIRECTORY_SEPARATOR . $args['name'] . "-" . $args['version'] . '.zip');
        return Router::redirect(Router::pathFor('adminPlugins'), 'Plugin downloaded!');
    }

    /**
     * @param $req
     * @param $res
     * @param $args
     * @throws RunBBException
     */
    public function index($req, $res, $args)
    {
        // check plugins dir exists
        Utils::checkDir($this->c['forum_env']['WEB_ROOT'] . $this->c['forum_env']['WEB_PLUGINS']);

        Container::get('hooks')->fire('controller.admin.plugins.index');

        View::addAsset('js', 'style/imports/common.js', ['type' => 'text/javascript']);

        $availablePlugins = Lister::getPlugins();
        $activePlugins = Container::get('cache')->isCached('activePlugins') ? Container::get('cache')->retrieve('activePlugins') : [];

        $officialPlugins = [];//Lister::getOfficialPlugins();

        AdminUtils::generateAdminMenu('plugins');

        View::setPageInfo([
            'admin_console' => true,
            'active_page' => 'admin',
            'availablePlugins' => $availablePlugins,
            'activePlugins' => $activePlugins,
            'officialPlugins' => $officialPlugins,
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Extension')],
        ])->addTemplate('admin/plugins.php')->display();
    }

    /**
     * @param $req
     * @param $res
     * @param $args
     * @return mixed
     * @throws RunBBException
     */
    public function activate($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.plugins.activate');

        if (!$args['name']) {
            throw new RunBBException(__('Bad request'), 400);
        }

        $this->model->activate($args['name']);
        // Plugin has been activated, confirm and redirect
        return Router::redirect(Router::pathFor('adminPlugins'), 'Plugin activated!');
    }

    /**
     * @param $req
     * @param $res
     * @param $args
     * @return mixed
     * @throws RunBBException
     */
    public function deactivate($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.plugins.deactivate');

        if (!$args['name']) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        $this->model->deactivate($args['name']);
        // Plugin has been deactivated, confirm and redirect
        return Router::redirect(Router::pathFor('adminPlugins'), ['warning', 'Plugin deactivated!']);
    }

    /**
     * @param $req
     * @param $res
     * @param $args
     * @return mixed
     * @throws RunBBException
     */
    public function uninstall($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.plugins.uninstall');

        if (!$args['name']) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        $this->model->uninstall($args['name']);
        // Plugin has been deactivated, confirm and redirect
        return Router::redirect(Router::pathFor('adminPlugins'), ['warning', 'Plugin uninstalled!']);
    }


    /**
     * Load plugin info if it exists
     * @param $req
     * @param $res
     * @param $args
     * @return mixed
     * @throws RunBBException
     */
    public function info($req, $res, $args)
    {
        $formattedPluginName =
            str_replace(' ', '', ucwords(
                str_replace(['-', '_'], ' ', $args['name'])
            ));

        $new = "\\RunBB\\Plugins\\Controller\\" . $formattedPluginName;
        if (class_exists($new)) {
            $plugin = new $new;
            if (method_exists($plugin, 'info')) {
                AdminUtils::generateAdminMenu($args['name']);
                return $plugin->info($req, $res, $args);
            } else {
                throw new  RunBBException(__('Bad request'), 400);
            }
        } else {
            throw new  RunBBException(__('Bad request'), 400);
        }
    }
}
