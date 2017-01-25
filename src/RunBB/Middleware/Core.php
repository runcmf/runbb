<?php
/**
 *
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 *
 */

namespace RunBB\Middleware;

use RunBB\Core\Email;
use RunBB\Core\Hooks;
//use RunBB\Core\Parser;
use RunBB\Core\Interfaces\Container;
use RunBB\Core\ParserS9E;
use RunBB\Core\Plugin;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use RunBB\Core\View;

class Core
{
    protected $forum_env,
        $forum_settings, $c;
    protected $headers = [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Content-type' => 'text/html',
        'X-Frame-Options' => 'deny'];
    protected static $queryLog = [];

    public function __construct(\Slim\Container $c, array $data)
    {
        $this->c = $c;
        // Handle empty values in data
        $data = array_merge([
            'config_file' => 'config.php',
            'cache_dir' => 'cache/',
            'web_root' => '',
            'debug' => false
            ],
            $data
        );

        // Define some core variables
        $this->forum_env['FORUM_ROOT'] = realpath(dirname(__FILE__) . '/../') . '/';
        $this->forum_env['FORUM_CACHE_DIR'] = $data['cache_dir'];
        $this->forum_env['FORUM_CONFIG_FILE'] = $this->forum_env['FORUM_CACHE_DIR'] . $data['config_file'];
        $this->forum_env['FEATHER_DEBUG'] = $this->forum_env['FEATHER_SHOW_QUERIES'] = ($data['debug'] == 'all');
        $this->forum_env['FEATHER_SHOW_INFO'] = ($data['debug'] == 'info' || $data['debug'] == 'all');
        $this->forum_env['WEB_ROOT'] = $data['web_root'];
        $this->forum_env['WEB_PLUGINS'] = 'ext';
        $this->forum_env['SLIM_SETTINGS'] = $c['settings']['runbb'];

        // Populate forum_env
        $this->forum_env = array_merge(self::load_default_forum_env(), $this->forum_env);

        // Load IdiORM
        // TODO move to global separately forum ???
        require_once DIR . 'vendor/j4mie/idiorm/idiorm.php';

        // Load files
        require $this->forum_env['FORUM_ROOT'] . 'Helpers/utf8/utf8.php';
//        require $this->forum_env['FORUM_ROOT'].'Core/gettext/l10n.php';
//        require $this->forum_env['FORUM_ROOT'].'Core/gettext/MO.php';

        // Load Languages
        require $this->forum_env['FORUM_ROOT'] . 'Core/gettext.php';
        Container::set('lang', function ($container) {
            return new \RunBB\Core\Language('RunBB');
        });

        // Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
        setlocale(LC_CTYPE, 'C');
    }

    public static function load_default_forum_env()
    {
        return [
            'FORUM_ROOT' => '',
            'FORUM_CONFIG_FILE' => 'config.php',
            'FORUM_CACHE_DIR' => DIR . 'var/cache/',
            'FORUM_VERSION' => '1.0.0',
            'FORUM_NAME' => 'RunBB',
            'FORUM_DB_REVISION' => 21,
            'FORUM_SI_REVISION' => 2,
            'FORUM_PARSER_REVISION' => 2,
            'FEATHER_UNVERIFIED' => 0,
            'FEATHER_ADMIN' => 1,
            'FEATHER_MOD' => 2,
            'FEATHER_GUEST' => 3,
            'FEATHER_MEMBER' => 4,
            'FEATHER_MAX_POSTSIZE' => 32768,
            'FEATHER_SEARCH_MIN_WORD' => 3,
            'FEATHER_SEARCH_MAX_WORD' => 20,
            'FORUM_MAX_COOKIE_SIZE' => 4048,
            'FEATHER_DEBUG' => false,
            'FEATHER_SHOW_QUERIES' => false,
            'FEATHER_SHOW_INFO' => false
        ];
    }

    public static function load_default_forum_settings()
    {
        return [
            // Database
            'db_type' => 'mysqli',
            'db_host' => '',
            'db_name' => '',
            'db_user' => '',
            'db_pass' => '',
            'db_prefix' => '',
            // Cookies
            'cookie_name' => 'runbb_cookie',
            'jwt_token' => 'changeme', // MUST BE CHANGED !!!
            'jwt_algorithm' => 'HS512'
        ];
    }

    public static function init_db(array $config, $log_queries = false)
    {
        $config['db_prefix'] = (!empty($config['db_prefix'])) ? $config['db_prefix'] : '';
        switch ($config['db_type']) {
            case 'mysql':
                \ORM::configure('mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name']);
                \ORM::configure('driver_options', [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']);
                break;
            case 'sqlite';
            case 'sqlite3';
                \ORM::configure('sqlite:./' . $config['db_name']);
                break;
            case 'pgsql':
                \ORM::configure('pgsql:host=' . $config['db_host'] . 'dbname=' . $config['db_name']);
                break;
        }
        \ORM::configure('username', $config['db_user']);
        \ORM::configure('password', $config['db_pass']);
//        \ORM::configure('prefix', $config['db_prefix']);// idiorm not use prefix
        if ($log_queries) {
            \ORM::configure('logging', true);
            // Collect query info
            \ORM::configure('logger', function ($query, $time) {
                self::$queryLog[0][] = $time;
                self::$queryLog[1][] = $query;
            });
        }
        \ORM::configure('id_column_overrides', [
            $config['db_prefix'] . 'groups' => 'g_id',
        ]);

        defined('ORM_TABLE_PREFIX') || define('ORM_TABLE_PREFIX', $config['db_prefix']);
    }

    public static function getQueryLog()
    {
        return self::$queryLog;
    }

    private function loadPlugins()
    {
        $manager = new Plugin($this->c);
        $manager->loadPlugins();
    }

    // Headers
    public function setHeaders($res)
    {
        foreach ($this->headers as $label => $value) {
            $res = $res->withHeader($label, $value);
        }
        return $res->withHeader('X-Powered-By', $this->forum_env['FORUM_NAME']);
    }

    public function __invoke($req, $res, $next)
    {
        // Set headers
        $res = $this->setHeaders($res);

        // Block prefetch requests
        if ((isset($this->c->environment['HTTP_X_MOZ'])) && ($this->c->environment['HTTP_X_MOZ'] == 'prefetch')) {
            return $this->c->response->withStatus(403); // Send forbidden header
        }

        // Populate Slim object with forum_env vars
        Container::set('forum_env', $this->forum_env);

        translate('misc');// load misc lang vars

        // Load RunBB utils class
        Container::set('utils', function ($container) {
            return new Utils();
        });
        // Record start time
        Container::set('start', Utils::get_microtime());
        // Define now var
        Container::set('now', function () {
            return time();
        });
        // Load RunBB cache
        Container::set('cache', function ($container) {
            return new \RunBB\Core\Cache([
                'name' => 'runbb',
                'path' => $this->forum_env['FORUM_CACHE_DIR'],
                'extension' => '.cache'
            ]);
        });
        // Load RunBB permissions
        Container::set('perms', function ($container) {
            return new \RunBB\Core\Permissions();
        });
        // Load RunBB preferences
        Container::set('prefs', function ($container) {
            return new \RunBB\Core\Preferences();
        });
        // Load RunBB view
        Container::set('template', function ($container) {
            return new View();
        });
        // register twig
        Container::set('twig', function ($container) {
//$this->forum_env['WEB_ROOT'] add theme name
            $twig = new \Twig_Environment(
                new \Twig_Loader_Filesystem(
                    $this->forum_env['WEB_ROOT'] . 'style/themes/tryOne/view'//FIXME themeName!!!
//                    $container['settings']['view']['template_path']
                ),
//                $container['settings']['view']['twig']
                [
                    //root_dir
                    'cache' => DIR . 'var/cache/twig',
                    'debug' => true,
                ]
            );
            // extensions
//            $twig->addExtension(new \Twig_Extension_Profiler($container['twig_profile']));
            if (ForumEnv::get('FEATHER_DEBUG')) {
                $twig->addExtension(new \Twig_Extension_Debug());
            }
            $twig->addExtension(new \RunBB\Core\RunBBTwig);

            return $twig;
        });

        // Load RunBB url class
        Container::set('url', function ($container) {
            return new Url();
        });
        // Load RunBB hooks
        Container::set('hooks', function ($container) {
            return new Hooks();
        });
        // Load RunBB email class
        Container::set('email', function ($container) {
            return new Email();
        });

        Container::set('parser', function ($container) {
//            return new Parser();
            return new ParserS9E();
        });
        // Set cookies
        Container::set('cookie', function ($container) {
            $request = $container->get('request');
            return new \Slim\Http\Cookies($request->getCookieParams());
        });
        Container::set('flash', function ($c) {
            return new \Slim\Flash\Messages;
        });

        // This is the very first hook fired
        Container::get('hooks')->fire('core.start');

        if (!is_file(ForumEnv::get('FORUM_CONFIG_FILE'))) {
            // Reset cache
            Container::get('cache')->flush();
            $installer = new \RunBB\Controller\Install($this->c);
            return $installer->run();
        }

        // Load config from disk
        $config = include ForumEnv::get('FORUM_CONFIG_FILE');
        if (!empty($config)) {
            $this->forum_settings = array_merge(self::load_default_forum_settings(), $config);
        } else {
            $this->c['response']->withStatus(500); // Send forbidden header
            return $this->c['response']->getBody()->write('Wrong config file format');
        }

        // Init DB and configure Slim
        self::init_db($this->forum_settings, ForumEnv::get('FEATHER_SHOW_INFO'));
        Config::set('displayErrorDetails', ForumEnv::get('FEATHER_DEBUG'));

        if (!Container::get('cache')->isCached('config')) {
            Container::get('cache')->store('config', \RunBB\Model\Cache::get_config());
        }

        // Finalize forum_settings array
        $this->forum_settings = array_merge(Container::get('cache')->retrieve('config'), $this->forum_settings);
        Container::set('forum_settings', $this->forum_settings);

        // Set default style and assets
        Container::get('template')->setStyle(ForumSettings::get('o_default_style'));

        // Run activated plugins
        self::loadPlugins();

        // Define time formats and add them to the container
        Container::set('forum_time_formats', [
            ForumSettings::get('o_time_format'),
            'H:i:s', 'H:i', 'g:i:s a', 'g:i a'
        ]);
        Container::set('forum_date_formats', [
            ForumSettings::get('o_date_format'),
            'Y-m-d', 'Y-d-m', 'd-m-Y', 'm-d-Y', 'M j Y', 'jS M Y'
        ]);

        // Call RunBBAuth middleware
        return $next($req, $res);
    }
}
