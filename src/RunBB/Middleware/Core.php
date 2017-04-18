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
use RunBB\Core\Parser;
use RunBB\Core\Interfaces\Container;
use RunBB\Core\Interfaces\Lang;
use RunBB\Core\Plugin;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use BBRenderer\Renderer;

class Core
{
    protected $forum_env;
    protected $forum_settings;
    protected $c;
    protected $headers = [
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Content-type' => 'text/html',
        'X-Frame-Options' => 'deny'];

    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
        // Handle empty values in data
        $data = array_merge(
            [
            'config_file' => 'config.php',
            'cache_dir' => 'cache/',
            'web_root' => '',
            'debug' => false
            ],
            $c['settings']['runbb']
        );

        // Define some core variables
        $this->forum_env['FORUM_ROOT'] = realpath(dirname(__FILE__) . '/../') . '/';
        $this->forum_env['FORUM_CACHE_DIR'] = $data['cache_dir'];
        $this->forum_env['FORUM_CONFIG_FILE'] = $this->forum_env['FORUM_CACHE_DIR'] . $data['config_file'];
        $this->forum_env['FEATHER_DEBUG'] = $this->forum_env['FEATHER_SHOW_QUERIES'] = $data['debug'];
        $this->forum_env['FEATHER_SHOW_INFO'] = ($data['debug'] == 'info' || $data['debug'] == 'all');
        $this->forum_env['WEB_ROOT'] = $data['web_root'];
        $this->forum_env['APP_ROOT'] = $data['root_dir'];//ForumEnv::get('APP_ROOT')
        $this->forum_env['WEB_PLUGINS'] = 'ext';
        $this->forum_env['SLIM_SETTINGS'] = $c['settings']['runbb'];
        $this->forum_env['RENDERER'] = $data['renderer'];

        // Populate forum_env
        $this->forum_env = array_merge($this->loadDefaultForumEnv(), $this->forum_env);

        // Load debugger helper
        require $this->forum_env['FORUM_ROOT'] . 'Helpers/shortcuts.php';

        // Load IdiORM
        // TODO move to global separately forum ???
        require_once $data['root_dir'] . 'vendor/j4mie/idiorm/idiorm.php';

        // Load & init utf8 files
        require $this->forum_env['FORUM_ROOT'] . 'Helpers/utf8/utf8.php';
        initUTF8();

        // Force POSIX locale (to prevent functions such as strtolower() from messing up UTF-8 strings)
        setlocale(LC_CTYPE, 'C');
    }

    public function loadDefaultForumEnv()
    {
        return [
            'FORUM_ROOT' => '',
            'FORUM_CONFIG_FILE' => 'config.php',
            'FORUM_CACHE_DIR' => $this->forum_env['APP_ROOT'] . 'var/cache/',
            'FORUM_VERSION' => '1.0.1',
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

    public static function loadDefaultForumSettings()
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
            $res = $res->withStatus(403);
            return $next($req, $res);
        }
        // Populate Slim object with forum_env vars
        Container::set('forum_env', $this->forum_env);
        // Load utils class
        Container::set('utils', function ($container) {
            return new Utils();
        });
        // Record start time
        Container::set('start', Utils::getMicrotime());
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
        // Load permissions
        Container::set('perms', function ($container) {
            return new \RunBB\Core\Permissions();
        });
        // Load preferences
        Container::set('prefs', function ($container) {
            return new \RunBB\Core\Preferences();
        });
        // Load view
        Container::set('template', function ($container) {
            $r = new Renderer($this->forum_env['RENDERER']);
            return $r->get();
        });
        // Load url class
        Container::set('url', function ($container) {
            return new Url();
        });
        // Load remote content class
        Container::set('remote', function ($container) {
            return new \RunBB\Core\Remote();
        });
        // Load hooks
        Container::set('hooks', function ($container) {
            return new Hooks();
        });
        // Load email class
        Container::set('email', function ($container) {
            return new Email();
        });
        Container::set('parser', function ($container) {
            return new Parser();
        });
        // Set cookies
        Container::set('cookie', function ($container) {
            return new \Slim\Http\Cookies($container->get('request')->getCookieParams());
        });
        Container::set('flash', function ($c) {
            return new \Slim\Flash\Messages;
        });

        // This is the very first hook fired
        Container::get('hooks')->fire('core.start');

        // init languages
        Lang::construct();

        // check config exists or goto install
        if (!is_file(ForumEnv::get('FORUM_CONFIG_FILE'))) {
            // Reset cache
            Container::get('cache')->flush();
            $installer = new \RunBB\Controller\Install($this->c);
            return $installer->run();
        }
        // Load config from disk
        $config = include ForumEnv::get('FORUM_CONFIG_FILE');
        if (!empty($config)) {
            $this->forum_settings = array_merge(self::loadDefaultForumSettings(), $config);
        } else {
            $res->withStatus(500); // Send forbidden header
            $res->getBody()->write('Wrong config file format');
            return $next($req, $res);
        }

        // Init DB and configure Slim
        DB::init($this->forum_settings);
        Config::set('displayErrorDetails', ForumEnv::get('FEATHER_DEBUG'));

        /**
         * Init Logger after DB initialized
         * Log::info('My logger is now ready', ['user' => User::getVar('username')]);
         *
         * log($level, $message, array $context = array())
         * debug($message, array $context = array())
         * info($message, array $context = array())
         * notice($message, array $context = array())
         * warning($message, array $context = array()) Alias `warn`
         * error($message, array $context = array()) Alias `err`
         * critical($message, array $context = array()) Alias `crit`
         * alert($message, array $context = array())
         * emergency($message, array $context = array()) Alias `emerg`
         */
        Container::set('log', function () {
            $logger = new \Monolog\Logger('RunBB');
            $handler = new \RunBB\Model\BBLogger();
            $logger->pushHandler($handler->initLogger());
            return $logger;
        });

        if (!Container::get('cache')->isCached('config')) {
            Container::get('cache')->store('config', \RunBB\Model\Cache::getConfig());
        }

        // Finalize forum_settings array
        $this->forum_settings = array_merge(Container::get('cache')->retrieve('config'), $this->forum_settings);
        Container::set('forum_settings', $this->forum_settings);

        Container::set('menu', function ($c) {
            return new \RunBB\Helpers\Menu\MenuManager($c);
        });

        // Define time formats and add them to the container
        Container::set('forum_time_formats', array_unique([
            ForumSettings::get('o_time_format'),
            'H:i:s', 'H:i', 'g:i:s a', 'g:i a'
        ]));
        Container::set('forum_date_formats', array_unique([
            ForumSettings::get('o_date_format'),
            'Y-m-d', 'Y-d-m', 'd-m-Y', 'm-d-Y', 'M j Y', 'jS M Y'
        ]));

        // Run activated plugins
        self::loadPlugins();

        return $next($req, $res);
    }
}
