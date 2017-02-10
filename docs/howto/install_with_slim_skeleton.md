# Install RunBB with [slim-skeleton](https://github.com/slimphp/Slim-Skeleton)

Install procedure include some steps:
* install slim-skeleton
* install runbb
* create db
* add and change settings
* add forum init
* and install procedure in browser  

---

installation process will try create a directory
```php
install_root/public/style
install_root/public/ext
install_root/var
```
`style` for assets, `ext` for extensions assets and `var` for cache.  
cache dir you can change in config below.  

---
####Well, let's  begin  

**1.** install slim/slim-skeleton to root:
```sh
$ composer create-project slim/slim-skeleton . 
```  
**1.1.** or to dir `myproject`:  

```sh
$ composer create-project slim/slim-skeleton myproject
```

**2.** install RunBB
```sh
$ composer require runcmf/runbb:dev-master 
```

**3.** add to `src/settings.php`
```php
        'runbb' => [
            'config_file' => 'config.php',
            'cache_dir' => DIR . 'var/cache/RunBB/',
            'web_root' => DIR . 'public/',
            'tplEngine' => '',// live empty for php or `twig`
            'root_dir' => DIR,
            'debug' => 'info', // false || info (only page generate time, mem usage and peak)
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
            'root_dir' => DIR,
            'debug' => 'info', // false || info (only page generate time, mem usage and peak)
        ]
    ],
];
```  

**4.** add in public/index.php `(new \RunBB\Init($app))->init();` before `// Run app`  

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

**5.** create data base, then F5 on installed site and you redirected to install page.  
    * fill database info (Note: admin pass length minimum 6 symbols)

#### IMPORTANT: 
comment or delete example route from slim-skeleton with `$app->get('/[{name}]', function ($request, $response, $args) {` from `src/routes.php`  
or edit as `$app->get('/', function ($request, $response, $args) {`  
else you get error `Static route "/forum" is shadowed by previously defined variable route "/([^/]+)" for method "GET"`  

---
#### By default forum uri is '/forum' like `site.name/forum` but if you want simply change init such as

**1.** `(new \RunBB\Init($app))->init();` - default with path `/forum`   
**2.** `(new \RunBB\Init($app, ''))->init();` - empty for web root (subdomain, site with forum only etc.)   
**3.** `(new \RunBB\Init($app, '/discourse'))->init();` - any you want `/community`, `/discourse`, `/mylair` etc.  
