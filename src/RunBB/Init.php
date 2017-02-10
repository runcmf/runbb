<?php

/**
 * Copyright 2016 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace RunBB;

use RunBB\Core\Interfaces\SlimStatic;
use RunBB\Middleware\Logged as IsLogged;
use RunBB\Middleware\ReadBoard as CanReadBoard;
use RunBB\Middleware\Admin as IsAdmin;
use RunBB\Middleware\AdminMod as IsAdmMod;

class Init
{
    private $app;
    public static $uri;

    public function __construct($app, $uri='/forum')
    {
        $this->app = $app;
        self::$uri = $uri;

        SlimStatic::boot($app);
        // Allow static proxies to be called from anywhere in App
        Statical::addNamespace('*', 'RunBB\\*');
    }

    public function init()
    {
        $this->registerMiddlewares();
//        $this->registerViews();
        $this->registerUserRoute();
        $this->registerAdminRoute();
    }

    public function getModuleName()
    {
        return 'Forum';
    }

    public static function getModuleAccessor()
    {
        return 'forum';
    }

    public static function getAdminUrl()
    {
        return '/admin/forum';
    }

    public function registerUserMenu()
    {
        $v = $this->app->getContainer()->get('view');
        return $v->fetch('@forum/menuUser.html.twig');
    }

    public static function registerAdminMenu($menu, $url)
    {
        $forumMenu = $menu->createItem('forum', [
            'label' => 'Forum',
            'icon'  => 'comments',
            'url'   => '#'
        ]);
        $forumMenu->setAttribute('class', 'nav nav-second-level');

        $dashboardMenu = $menu->createItem('forum-dashboard', [
            'label' => 'Dashboard',
            'icon'  => 'dashboard',
            'url'   => $url.'/admin/forum'
        ]);

//        $configMenu = $menu->createItem('forum-config', [
//            'label' => 'Configuration',
//            'icon'  => 'cog',
//            'url'   => $url.'/admin/forum/config'
//        ]);
//
//        $forumsMenu = $menu->createItem('forum-forums', [
//            'label' => 'Forums & Posts',
//            'icon'  => 'comments-o',
//            'url'   => $url.'/admin/forum/forum'
//        ]);
//
//        $usersMenu = $menu->createItem('forum-users', [
//            'label' => 'Users & Groups',
//            'icon'  => 'group',
//            'url'   => $url.'/admin/forum/users'
//        ]);
//
//        $styleMenu = $menu->createItem('forum-style', [
//            'label' => 'Templates',
//            'icon'  => 'puzzle-piece',
//            'url'   => $url.'/admin/forum/style'
//        ]);
//
//        $toolsMenu = $menu->createItem('forum-tools', [
//            'label' => 'Maintenance',
//            'icon'  => 'wrench',
//            'url'   => $url.'/admin/forum/tools'
//        ]);

        $forumMenu->addChildren('forum-dashboard', $dashboardMenu);
//        $forumMenu->addChildren('forum-config', $configMenu);
//        $forumMenu->addChildren('forum-forums', $forumsMenu);
//        $forumMenu->addChildren('forum-users', $usersMenu);
//        $forumMenu->addChildren('forum-style', $styleMenu);
//        $forumMenu->addChildren('forum-tools', $toolsMenu);

        $menu->addItem('forum', $forumMenu);
    }

    private function registerMiddlewares()
    {
        $c = $this->app->getContainer();

        $c['errorHandler'] = function ($c) {
            return function ($request, $response, $e) use ($c) {
                $error = [
                    'code' => $e->getCode(),
                    'message' => $e->getMessage(),
                    'back' => true,
                ];
                // Hide internal mechanism from guest
//        if (User::get()->is_guest) {
//            $error['message'] = 'There was an internal error'; // TODO : translation
//        } else
                if (isset(User::get()->is_admmod) && User::get()->is_admmod === true) {
                    // show last 5 trace lines
                    if (count($e->getTrace()) > 1) {
                        $trace = $e->getTrace();
                        $msg='backtrace:<br/>';
                        for ($i=0; $i < 5; $i++) {
                            if (isset($trace[$i]['file'])) {
                                $msg .= '<p>' . $i . ': file: &nbsp; &nbsp; &nbsp;' .
                                    str_replace(ForumEnv::get('APP_ROOT'), '', $trace[$i]['file']) . ' [' . $trace[$i]['line'] . ']</p>';
                            } else {
                                $msg .= '<p>' . $i . ': ' .
                                    'class: &nbsp;'. $trace[$i]['class'] . ' [' . $trace[$i]['function'] . ']</p>';
                            }
                        }
                        $error['message'] = $error['message'] . '<br /><br />' . $msg;
                    }
                }

                if (method_exists($e, 'hasBacklink')) {
                    $error['back'] = $e->hasBacklink();
                }

                return View::setPageInfo([
                    'title' => [\RunBB\Core\Utils::escape(ForumSettings::get('o_board_title')), __('Error')],
                    'msg'    =>    html_entity_decode($error['message']),
                    'backlink'    => $error['back'],
                ])->addTemplate('error.php')->display();
            };
        };
        $c['phpErrorHandler'] = function ($c) {
            return $c['errorHandler'];
        };
        $c['notAllowedHandler'] = function ($c) {
            return function ($req, $res, $methods) {
                return View::setPageInfo([
                    'title' => [\RunBB\Core\Utils::escape(ForumSettings::get('o_board_title')), __('Error')],
                    'msg'    =>    'Method must be one of: ' . implode(', ', $methods),
                    'backlink'    => true,
                ])->addTemplate('error.php')
                    ->display()
                    ->withHeader('Allow', implode(', ', $methods))
                    ->withStatus(405);
            };
        };

        $this->app->add(new \RunBB\Middleware\Csrf);
        $this->app->add(new \RunBB\Middleware\Auth);
        $this->app->add(new \RunBB\Middleware\Core($c, $c['settings']['runbb']));
        // Permanently redirect paths with a trailing slash
        // to their non-trailing counterpart
        $this->app->add(function ($req, $res, $next) {
            $uri = $req->getUri();
            $path = $uri->getPath();
            if ($path != '/' && substr($path, -1) == '/') {
                $uri = $uri->withPath(substr($path, 0, -1));
                return $res->withRedirect((string)$uri, 301);
            }

            return $next($req, $res);
        });
    }

    private function registerViews()
    {
        // register template path & template alias
//        $viewLoader = $this->app->getContainer()->get('view')->getLoader();
//        $viewLoader->addPath(__DIR__ . '/View', 'forum');
    }

    private function registerAdminRoute()
    {
        Route::group(self::getAdminUrl(), function () {
            // Admin index
            $this->get('[/action/{action}]', '\RunBB\Controller\Admin\Index:display')->setName('adminAction');
            Route::get('/index', '\RunBB\Controller\Admin\Index:display')->setName('adminIndex');

            // Admin bans
            Route::group('/bans', function () {
                Route::get('', '\RunBB\Controller\Admin\Bans:display')->setName('adminBans');
                Route::get('/delete/{id:[0-9]+}', '\RunBB\Controller\Admin\Bans:delete')->setName('deleteBan');
                Route::map(['GET', 'POST'], '/edit/{id:[0-9]+}',
                    '\RunBB\Controller\Admin\Bans:edit')->setName('editBan');
                Route::map(['GET', 'POST'], '/add[/{id:[0-9]+}]',
                    '\RunBB\Controller\Admin\Bans:add')->setName('addBan');
            });

            // Admin options
            Route::map(['GET', 'POST'], '/options',
                '\RunBB\Controller\Admin\Options:display')->add(new IsAdmin)->setName('adminOptions');

            // Admin categories
            Route::group('/categories', function () {
                Route::get('', '\RunBB\Controller\Admin\Categories:display')->setName('adminCategories');
                Route::post('/add', '\RunBB\Controller\Admin\Categories:add')->setName('addCategory');
                Route::post('/edit', '\RunBB\Controller\Admin\Categories:edit')->setName('editCategory');
                Route::post('/delete', '\RunBB\Controller\Admin\Categories:delete')->setName('deleteCategory');
            })->add(new IsAdmin);

            // Admin censoring
            Route::map(['GET', 'POST'], '/censoring',
                '\RunBB\Controller\Admin\Censoring:display')->add(new IsAdmin)->setName('adminCensoring');

            // Admin reports
            Route::map(['GET', 'POST'], '/reports',
                '\RunBB\Controller\Admin\Reports:display')->setName('adminReports');

            // Admin permissions
            Route::map(['GET', 'POST'], '/permissions',
                '\RunBB\Controller\Admin\Permissions:display')->add(new IsAdmin)->setName('adminPermissions');

            // Admin statistics
            Route::get('/statistics', '\RunBB\Controller\Admin\Statistics:display')->setName('statistics');
            Route::get('/phpinfo', '\RunBB\Controller\Admin\Statistics:phpinfo')->setName('phpInfo');

            // Admin forums
            Route::group('/forums', function () {
                Route::map(['GET', 'POST'], '', '\RunBB\Controller\Admin\Forums:display')->setName('adminForums');
                Route::post('/add', '\RunBB\Controller\Admin\Forums:add')->setName('addForum');
                Route::map(['GET', 'POST'], '/edit/{id:[0-9]+}',
                    '\RunBB\Controller\Admin\Forums:edit')->setName('editForum');
                Route::map(['GET', 'POST'], '/delete/{id:[0-9]+}',
                    '\RunBB\Controller\Admin\Forums:delete')->setName('deleteForum');
            })->add(new IsAdmin);

            // Admin groups
            Route::group('/groups', function () {
                Route::map(['GET', 'POST'], '', '\RunBB\Controller\Admin\Groups:display')->setName('adminGroups');
                Route::map(['GET', 'POST'], '/add', '\RunBB\Controller\Admin\Groups:addedit')->setName('addGroup');
                Route::map(['GET', 'POST'], '/edit/{id:[0-9]+}',
                    '\RunBB\Controller\Admin\Groups:addedit')->setName('editGroup');
                Route::map(['GET', 'POST'], '/delete/{id:[0-9]+}',
                    '\RunBB\Controller\Admin\Groups:delete')->setName('deleteGroup');
            })->add(new IsAdmin);

            // Admin plugins
            Route::group('/plugins', function () {
                Route::map(['GET', 'POST'], '',
                    '\RunBB\Controller\Admin\Plugins:index')->setName('adminPlugins');
                Route::map(['GET', 'POST'], '/info/{name:[\w\-]+}',
                    '\RunBB\Controller\Admin\Plugins:info')->setName('infoPlugin');
                Route::get('/activate/{name:[\w\-]+}',
                    '\RunBB\Controller\Admin\Plugins:activate')->setName('activatePlugin');
                Route::get('/download/{name:[\w\-]+}[/{version}]',
                    '\RunBB\Controller\Admin\Plugins:download')->setName('downloadPlugin');
                Route::get('/deactivate/{name:[\w\-]+}',
                    '\RunBB\Controller\Admin\Plugins:deactivate')->setName('deactivatePlugin');
                Route::get('/uninstall/{name:[\w\-]+}',
                    '\RunBB\Controller\Admin\Plugins:uninstall')->setName('uninstallPlugin');
                Route::get('/repo',
                    '\RunBB\Controller\Admin\Plugins:repoList')->setName('pluginsRepoList');
                Route::map(['GET', 'POST'], '/compose',
                    '\RunBB\Controller\Admin\Compose:display')->setName('pluginsCompose');
            });

            // Admin maintenance
            Route::map(['GET', 'POST'], '/maintenance',
                '\RunBB\Controller\Admin\Maintenance:display')->add(new IsAdmin)->setName('adminMaintenance');

            // Admin parser
            Route::map(['GET', 'POST'], '/parser',
                '\RunBB\Controller\Admin\Parser:display')->add(new IsAdmin)->setName('adminParser');

            // Admin users
            Route::group('/users', function () {
                Route::map(['GET', 'POST'], '', '\RunBB\Controller\Admin\Users:display')->setName('adminUsers');
                Route::get('/ip-stats/id/{id:[0-9]+}',
                    '\RunBB\Controller\Admin\Users:ipstats')->setName('usersIpStats');
                Route::get('/show-users', '\RunBB\Controller\Admin\Users:showusers')->setName('usersIpShow');
            });

            // Admin templates
            Route::group('/template', function () {
                Route::map(['GET', 'POST'], '',
                    '\RunBB\Controller\Admin\Templates:display')->setName('adminTemplates');
            });

            // Admin languages
            Route::group('/language', function () {
                Route::map(['GET', 'POST'], '',
                    '\RunBB\Controller\Admin\Languages:display')->setName('adminLanguages');
                Route::map(['GET', 'POST'], '/showlang',
                    '\RunBB\Controller\Admin\Languages:showLangFiles')->setName('adminLanguages.showlangfiles');
                Route::map(['GET', 'POST'], '/editlang',
                    '\RunBB\Controller\Admin\Languages:editLang')->setName('adminLanguages.editlang');
                Route::map(['GET', 'POST'], '/showmailtpls',
                    '\RunBB\Controller\Admin\Languages:showMailTemplates')->setName('adminLanguages.showmailtpls');
                Route::map(['GET', 'POST'], '/editmailtpls',
                    '\RunBB\Controller\Admin\Languages:editMailTemplates')->setName('adminLanguages.editmailtpls');
                Route::map(['GET', 'POST'], '/export',
                    '\RunBB\Controller\Admin\Languages:exportLanguage')->setName('adminLanguages.export');
                Route::map(['GET', 'POST'], '/import',
                    '\RunBB\Controller\Admin\Languages:importLanguage')->setName('adminLanguages.import');
                Route::map(['GET', 'POST'], '/build',
                    '\RunBB\Controller\Admin\Languages:buildNewTranslation')->setName('adminLanguages.build');
                Route::map(['GET', 'POST'], '/delete',
                    '\RunBB\Controller\Admin\Languages:deleteTranslation')->setName('adminLanguages.delete');
                Route::map(['GET', 'POST'], '/repo',
                    '\RunBB\Controller\Admin\Languages:showRepo')->setName('adminLanguages.repo');
                Route::map(['GET', 'POST'], '/info',
                    '\RunBB\Controller\Admin\Languages:langInfo')->setName('adminLanguages.info');
            });
        })->add(new IsAdmMod);
    }

    private function registerUserRoute()
    {
        $root = self::$uri === '' ? '/' : '';
        $this->app->group(self::$uri, function () use ($root) {
            // root routes
            // Index
            $this->get($root, '\RunBB\Controller\Index:display')->add(new CanReadBoard)->setName('home');
            // Userlist
            Route::get('/userlist', '\RunBB\Controller\Userlist:display')->add(new CanReadBoard)->setName('userList');

            Route::get('/{id:[0-9]+}[/{name:[\w\-]+}]',
                '\RunBB\Controller\Forum:display')->add(new CanReadBoard)->setName('Forum');
            Route::get('/{id:[0-9]+}/{name:[\w\-]+}/page/{page:[0-9]+}',
                '\RunBB\Controller\Forum:display')->add(new CanReadBoard)->setName('ForumPaginate');
            Route::get('/mark-read/{id:[0-9]+}[/{name:[\w\-]+}]',
                '\RunBB\Controller\Forum:markread')->add(new IsLogged)->setName('markForumRead');
            Route::get('/subscribe/{id:[0-9]+}[/{name:[\w\-]+}]',
                '\RunBB\Controller\Forum:subscribe')->add(new IsLogged)->setName('subscribeForum');
            Route::get('/unsubscribe/{id:[0-9]+}[/{name:[\w\-]+}]',
                '\RunBB\Controller\Forum:unsubscribe')->add(new IsLogged)->setName('unsubscribeForum');
            Route::get('/moderate/{fid:[0-9]+}/page/{page:[0-9]+}',
                '\RunBB\Controller\Forum:moderate')->add(new CanReadBoard)->setName('moderateForum');
            Route::post('/moderate/{fid:[0-9]+}[/page/{page:[0-9]+}]',
                '\RunBB\Controller\Forum:dealposts')->add(new CanReadBoard)->setName('dealPosts');
            $this->get('/rules', '\RunBB\Controller\Index:rules')->setName('rules');
            $this->get('/mark-read', '\RunBB\Controller\Index:markread')->add(new IsLogged)->setName('markRead');
            // Help
            Route::get('/help', '\RunBB\Controller\Help:display')->add(new CanReadBoard)->setName('help');

            // Topic
            Route::group('/topic', function () {
                Route::get('/{id:[0-9]+}[/{name:[\w\-]+}]', '\RunBB\Controller\Topic:display')->setName('Topic');
                Route::get('/{id:[0-9]+}/{name:[\w\-]+}/page/{page:[0-9]+}',
                    '\RunBB\Controller\Topic:display')->setName('TopicPaginate');
                Route::get('/{id:[0-9]+}/action/{action:[\w\-]+}',
                    '\RunBB\Controller\Topic:action')->setName('topicAction');
                Route::get('/subscribe/{id:[0-9]+}[/{name:[\w\-]+}]',
                    '\RunBB\Controller\Topic:subscribe')->add(new IsLogged)->setName('subscribeTopic');
                Route::get('/unsubscribe/{id:[0-9]+}[/{name:[\w\-]+}]',
                    '\RunBB\Controller\Topic:unsubscribe')->add(new IsLogged)->setName('unsubscribeTopic');
                Route::get('/close/{id:[0-9]+}[/{name:[\w\-]+}]',
                    '\RunBB\Controller\Topic:close')->add(new IsAdmMod)->setName('closeTopic');
                Route::get('/open/{id:[0-9]+}[/{name:[\w\-]+}]',
                    '\RunBB\Controller\Topic:open')->add(new IsAdmMod)->setName('openTopic');
                Route::get('/stick/{id:[0-9]+}[/{name:[\w\-]+}]',
                    '\RunBB\Controller\Topic:stick')->add(new IsAdmMod)->setName('stickTopic');
                Route::get('/unstick/{id:[0-9]+}[/{name:[\w\-]+}]',
                    '\RunBB\Controller\Topic:unstick')->add(new IsAdmMod)->setName('unstickTopic');
                Route::map(['GET', 'POST'], '/move/{id:[0-9]+}[/{name:[\w\-]+}/forum/{fid:[0-9]+}]',
                    '\RunBB\Controller\Topic:move')->add(new IsAdmMod)->setName('moveTopic');
                Route::map(['GET', 'POST'], '/moderate/{id:[0-9]+}/forum/{fid:[0-9]+}[/page/{page:[0-9]+}]',
                    '\RunBB\Controller\Topic:moderate')->add(new IsAdmMod)->setName('moderateTopic');
                Route::get('/{id:[0-9]+}/action/{action}', '\RunBB\Controller\Topic{action}')->setName('topicAction');
            })->add(new CanReadBoard);

            // Post routes
            Route::group('/post', function () {
                Route::get('/{pid:[0-9]+}', '\RunBB\Controller\Topic:viewpost')->setName('viewPost');
                Route::map(['GET', 'POST'], '/new-topic/{fid:[0-9]+}',
                    '\RunBB\Controller\Post:newpost')->setName('newTopic');
                Route::map(['GET', 'POST'], '/reply/{tid:[0-9]+}',
                    '\RunBB\Controller\Post:newreply')->setName('newReply');
                Route::map(['GET', 'POST'], '/reply/{tid:[0-9]+}/quote/{qid:[0-9]+}',
                    '\RunBB\Controller\Post:newreply')->setName('newQuoteReply');
                Route::map(['GET', 'POST'], '/delete/{id:[0-9]+}',
                    '\RunBB\Controller\Post:delete')->setName('deletePost');
                Route::map(['GET', 'POST'], '/edit/{id:[0-9]+}',
                    '\RunBB\Controller\Post:editpost')->setName('editPost');
                Route::map(['GET', 'POST'], '/report/{id:[0-9]+}',
                    '\RunBB\Controller\Post:report')->setName('report');
                Route::get('/get-host/{pid:[0-9]+}', '\RunBB\Controller\Post:gethost')->setName('getPostHost');
            })->add(new CanReadBoard);

            // Auth routes
            Route::group('/auth', function () {
                Route::map(['GET', 'POST'], '', '\RunBB\Controller\Auth:login')->setName('login');
                Route::map(['GET', 'POST'], '/forget', '\RunBB\Controller\Auth:forget')->setName('resetPassword');
                Route::get('/logout/token/{token}', '\RunBB\Controller\Auth:logout')->setName('logout');
            });

            // Register routes
            Route::group('/register', function () {
                Route::get('', '\RunBB\Controller\Register:rules')->setName('registerRules');
                Route::map(['GET', 'POST'], '/agree', '\RunBB\Controller\Register:display')->setName('register');
                Route::get('/cancel', '\RunBB\Controller\Register:cancel')->setName('registerCancel');
            });

            // Search routes
            Route::group('/search', function () {
                Route::get('', '\RunBB\Controller\Search:display')->setName('search');
                Route::get('/show/{show}', '\RunBB\Controller\Search:quicksearches')->setName('quickSearch');
            })->add(new CanReadBoard);

            // Profile routes
            Route::group('/user', function () {
                Route::map(['GET', 'POST'], '/{id:[0-9]+}',
                    '\RunBB\Controller\Profile:display')->setName('userProfile');
                Route::map(['GET', 'POST'], '/{id:[0-9]+}/section/{section}',
                    '\RunBB\Controller\Profile:display')->setName('profileSection');
                // TODO: Move to another route for non-authed users
                Route::map(['GET', 'POST'], '/{id:[0-9]+}/action/{action}',
                    '\RunBB\Controller\Profile:action')->setName('profileAction');
                Route::map(['GET', 'POST'], '/email/{id:[0-9]+}',
                    '\RunBB\Controller\Profile:email')->setName('email');
                Route::get('/get-host/{ip}', '\RunBB\Controller\Profile:gethostip')->setName('getHostIp');
            })->add(new IsLogged);

            // External
            Route::get('/extern', '\RunBB\Controller\Extern:display')->add(new CanReadBoard)->setName('extern');
        });
    }
}
