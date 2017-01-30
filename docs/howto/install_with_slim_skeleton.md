# Install RunBB with [slim-skeleton](https://github.com/slimphp/Slim-Skeleton)

installation process will create a directory
```php
install_root/public/style
install_root/public/ext
install_root/var
```
`style` for assets, `ext` for extensions assets and `var` for cache.  
cache dir you can change in config below.

####Well, let's  begin  

1. install slim/slim-skeleton to root:
```sh
$ composer create-project slim/slim-skeleton . 
```
1.1. or to dir `myproject`:
```sh
$ composer create-project slim/slim-skeleton myproject
```

2. install RunBB
```sh
$ composer require runcmf/runbb:dev-master 
```

3. add to `src/settings.php`
```php
        'runbb' => [
            'config_file' => 'config.php',
            'cache_dir' => DIR . 'var/cache/RunBB/',
            'web_root' => DIR . 'public/',
            'tplEngine' => '',// live empty for php or `twig`
            'debug' => 'info',
            // 3 levels : false, info (only execution time and number of queries),
            // and all (display info + queries)
            'plugins' => [// register plugins as NameSpace\InitInfoClass
            ]
        ]
```

result `src/settings.php` is:
```php
<?php
defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('DIR') || define('DIR', realpath(__DIR__ . '/../') . DS);
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
        ],

        // Monolog settings
        'logger' => [
            'name' => 'slim-app',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG,
        ],
        'runbb' => [
            'config_file' => 'config.php',
            'cache_dir' => DIR . 'var/cache/RunBB/',
            'web_root' => DIR . 'public/',
            'tplEngine' => '',// live empty for php or `twig`
            'debug' => 'info',
            // 3 levels : false, info (only execution time and number of queries),
            // and all (display info + queries)
            'plugins' => [// register plugins as NameSpace\InitInfoClass
            ]
        ]
    ],
];
```
4. add in public/index.php `(new \RunBB\Init($app))->init();` before `// Run app`
result is:
```php
<?php
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Register routes
require __DIR__ . '/../src/routes.php';

(new \RunBB\Init($app))->init();

// Run app
$app->run();
```
5. IMPORTANT: 
`comment or delete route from slim-skeleton example install with `$app->get('/[{name}]'` from `src/routes.php``

6. create data base, then F5 on installed site and you must in RunBB install page.
    * fill database info.
    * admin details (Note: pass length minimum 6 symbols)
    

