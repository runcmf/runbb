<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
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

return [
    'themeTemplates' => false,//where get templates,
    // `true` inside in this theme in `view` folder or
    // `false` - templates from RunBB package, here used only js/css
    'css' => [
        'assets/css/bootstrap.min.css',
        'assets/css/jquery-ui.min.css',// elFinder depend
        'assets/css/font-awesome.min.css',
        'assets/css/github.css',// highlight.js theme
        'assets/css/jquery.fancybox.css'
    ],
    'js' => [
        'assets/js/jquery-3.1.1.min.js',
        'assets/js/jquery-ui.min.js',// elFinder depend
        'assets/js/bootstrap.min.js',
        'assets/js/highlight.pack.js',
        'assets/js/jquery.fancybox.pack.js',
        'assets/js/common.js',
    ],
    'jshead' => [
    ],
    'jsraw' => '
        hljs.initHighlightingOnLoad();// init highlight.js
        $(document).ready(function() {
            $(".fancybox").fancybox();
        });
',
];
