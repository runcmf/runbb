[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Software License][ico-license]][link-license]  


## About

RunBB is a fork of [FeatherBB](https://github.com/featherbb/featherbb) which, at the time of the fork (2017-01-09), 
was slowly falling into abandon. Main objective build easy configurable library instead hardcoded project. I proceeded to rebuild and remove all the useless (to me) cruft:
* Remove: [db-layer](https://github.com/featherbb/db-layer) and use [Idiorm](https://github.com/j4mie/idiorm) from package instead  
* Remove: Core\gettext and use [gettext](https://github.com/oscarotero/Gettext) from package instead  
* Remove: Core\View and separate to [runbb-ext-renderer](https://github.com/runcmf/runbb-ext-renderer) extension. Now [Twig](https://github.com/twigphp/Twig), [Fenom](https://github.com/fenom-template/fenom) and PHP renderers. **Note:** only error and index pages for PHP and Fenom  
* Rebuild: plugins system. Plugins load by separated composer packages.
* Add: Markdown instead of BBCodes. Now use [s9e/text-formatter](https://github.com/s9e/TextFormatter) with [SimpleMDE](https://github.com/NextStepWebs/simplemde-markdown-editor) as plugin [runbb-ext-simplemde](https://github.com/runcmf/runbb-ext-simplemde) and [markItUp!](http://markitup.jaysalvat.com/home/) with [elFinder](https://github.com/Studio-42/elFinder) as plugin [runbb-ext-markitup](https://github.com/runcmf/runbb-ext-markitup) 
* Add: ability to work with translations/email templates online. (install/export/add new)
* Add: install translations by click  
* Add: install extensions (plugins) by click   
* Add: bootstrap SB Admin 2  
* Replace: Helpers\Set to Slim\Collection  



## Install
```php
$ composer require runcmf/runbb:dev-master
```

## init
**1.** read [example](docs/howto/install_with_slim_skeleton.md) install with [slim-skeleton](https://github.com/slimphp/Slim-Skeleton)  
**2.** read [example](docs/howto/install_plugin.md) install [markitup](https://github.com/runcmf/runbb-ext-markitup) plugin  


## Requirements

* A webserver
* PHP 5.6.0 or later with mbstring, curl
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

[ico-version]: https://img.shields.io/packagist/v/runcmf/runbb.svg
[ico-license]: https://img.shields.io/badge/license-Apache%202-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/runcmf/runbb.svg

[link-packagist]: https://packagist.org/packages/runcmf/runbb
[link-license]: http://www.apache.org/licenses/LICENSE-2.0
[link-downloads]: https://github.com/runcmf/runbb
