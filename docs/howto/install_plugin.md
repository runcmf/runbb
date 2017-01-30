# Example install [runbb-ext-markitup](https://github.com/runcmf/runbb-ext-markitup) plugin 

**1.** install plugin
```php
$ composer require runcmf/runbb-ext-markitup:dev-master
```  

**2.**
add to setting.php `'markitup' => 'RunMarkItup\markItUp'` into `'plugins'` section.  
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
                'markitup' => 'RunMarkItup\markItUp'
            ]
        ]
```

**3.**
goto `Profile` -> `Display` and change style to `tryOne`  
this add css and js for plugin.
or add to `public/styles/themes/FeatherBB/` some as in `tryOne`

**4.** goto administration -> Plugins -> markItUp Toolbar -> Activate

![exaample](runbb-ext-markitup_ss.png "markitup example")

