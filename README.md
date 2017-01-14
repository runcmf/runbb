# now dev only, no support!

## About

RunBB is a fork of [FeatherBB](https://github.com/featherbb/featherbb) which, at the time of the fork (2017-01-09), 
was slowly falling into abandon. I proceeded to remove all the useless (to me) cruft:
* Core\Database (old or slightly hacked Idiorm version ???) and use [Idiorm](https://github.com/j4mie/idiorm) from package instead 
* TODO
* TODO
* TODO 

## Install
add to top in dependeies
```php
use RunBB\Core\Interfaces\SlimStatic;

SlimStatic::boot($app);
// Allow static proxies to be called from anywhere in App
Statical::addNamespace('*','RunBB\\*');
```

add to dependencies
```php
$c['errorHandler'] = function ($c) {
    return function ($request, $response, $e) use ($c) {
        $error = [
            'code' => $e->getCode(),
            'message' => $e->getMessage(),
            'back' => true,
        ];

        // FIXME разделить что показывать юзеру а что админу
        // Hide internal mechanism from guest
        if (User::get()->is_guest && !($e instanceof \RunBB\Exception\RunBBException)) {
            $error['message'] = 'There was an internal error'; // TODO : translation
        } else {
            // show last 5 trace lines
            if (count($e->getTrace()) > 1) {
                $trace = $e->getTrace();
                $msg='backtrace:<br/>';
                for ($i=0; $i < 5; $i++) {
                    if(isset($trace[$i]['file'])) {
                        $msg .= '<p>' . $i . ': file: &nbsp; &nbsp; &nbsp;' .
                            str_replace(DIR, '', $trace[$i]['file']) . ' [' . $trace[$i]['line'] . ']</p>';
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

        return View::setPageInfo(array(
            'title' => array(\RunBB\Core\Utils::escape(ForumSettings::get('o_board_title')), __('Error')),
            'msg'    =>    $error['message'],
            'backlink'    => $error['back'],
        ))->addTemplate('error.php')->display();
    };
};
```
add to Settings
```php

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
define('DIR', realpath(__DIR__ . '/../../') . DS);

return [
    'settings' => [
        ... // ...
        ... // ...
        'runbbmbb' => [

            'config_file' => DIR . 'src/RunBB/yourConfigFileName.php',
            'cache_dir' => DIR . 'var/cache/RunBB/',
            'web_root' => DIR . 'web/',// public for slim-skeleton
            'debug' => 'all'
            // 3 levels : false, info (only execution time and number of queries),
            // and all (display info + queries)
        ]
    ]
];
```
init
```php
TODO
```


## Requirements

* A webserver
* PHP 5.5.0 or later
* A database such as MySQL 4.1.2 or later, PostgreSQL 7.0 or later, SQLite 2 or later

## Recommendations

* Make use of a PHP accelerator such as OPCache
* Make sure PHP has the **zlib** module installed to allow RunBB to gzip output

## Links

* 

## Contributors

* [[1f7](https://github.com/1f7)] Project leader


