[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]][link-license]  

## now dev only, no support!

## About

RunBB is a fork of [FeatherBB](https://github.com/featherbb/featherbb) which, at the time of the fork (2017-01-09), 
was slowly falling into abandon. I proceeded to rebuild and remove all the useless (to me) cruft:
* Removed: Core\Database (slightly hacked [Idiorm](https://github.com/j4mie/idiorm) version) and use [Idiorm](https://github.com/j4mie/idiorm) from package instead  
* Removed: Core\gettext and use [gettext](https://github.com/oscarotero/Gettext) from package instead  
* Rebuild: plugins system. Plugins load by composer packages. Old system temporary exist but deprecated.
* Add: Markdown instead of BBCodes. Now use [s9e/text-formatter](https://github.com/s9e/TextFormatter) with [SimpleMDE](https://github.com/NextStepWebs/simplemde-markdown-editor) as plugin [runbb-ext-simplemde](https://github.com/runcmf/runbb-ext-simplemde) and [markItUp!](http://markitup.jaysalvat.com/home/) with [elFinder](https://github.com/Studio-42/elFinder) as plugin [runbb-ext-markitup](https://github.com/runcmf/runbb-ext-markitup) 
* Add: ability to use [Twig](https://github.com/twigphp/Twig) template engine.
* Add: ability to work with translations online.



## Install
```php
$ composer require runcmf/runbb:dev-master
```

add to Settings
```php

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
define('DIR', realpath(__DIR__ . '/../../') . DS);

return [
    'settings' => [
        ... // ...
        ... // ...
        'runbb' => [
            'config_file' => 'NameFileAsYouWant.php',
            'cache_dir' => DIR . 'var/cache/RunBB/',
            'web_root' => DIR . 'web/',// public for slim-skeleton
            'debug' => 'all',
            // 3 levels : false, info (only execution time and number of queries),
            // and all (display info + queries)
            'plugins' => [// register plugins as NameSpace\InitInfoClass
//                'simplemde' => 'SimpleMDE\SimpleMDE'
            ]
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
* PHP 5.6.0 or later
* DB: all supported by [Idiorm](https://github.com/j4mie/idiorm)

## Recommendations

* highly recommended **php 7**


---
## Tests
```bash
$ cd vendor/runcmf/runbb
$ composer update
$ vendor/bin/phpunit
```
---  
## Security  

If you discover any security related issues, please email to 1f7.wizard( at )gmail.com instead of using the issue tracker.  

---
## Credits

* [FeatherBB](https://github.com/featherbb/featherbb)
* [1f7](https://github.com/1f7)
* [runetcms.ru](http://runetcms.ru)
* [runcmf.ru](http://runcmf.ru)  

---
## License
 
```
Copyright 2017 1f7.wizard@gmail.com

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```

[ico-version]: https://img.shields.io/packagist/v/runcmf/runbb.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-Apache%202-green.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/runcmf/runbb.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/runcmf/runbb
[link-license]: http://www.apache.org/licenses/LICENSE-2.0
[link-downloads]: https://github.com/runcmf/runbb
