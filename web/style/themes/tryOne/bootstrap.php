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
    'css' => [
        'assets/css/bootstrap.min.css',
        'assets/css/jquery-ui.min.css',// elFinder depend
        'assets/css/font-awesome.min.css',
        'assets/js/styles/github.css',// highlight.js theme
        'assets/fancybox/jquery.fancybox.css'
    ],
    'js' => [
        'assets/js/jquery-3.1.1.min.js',
        'assets/js/jquery-ui.min.js',// elFinder depend
        'assets/js/bootstrap.min.js',
        'assets/js/highlight.pack.js',
        'assets/fancybox/jquery.fancybox.pack.js',
        'style/themes/tryOne/phone.min.js'
    ],
    'jshead' => [
//        'assets/js/jquery-3.1.1.min.js',
//        'assets/js/jquery-ui.min.js',
//        'assets/js/bootstrap.min.js',
//        'assets/js/highlight.pack.js',
    ],
    'jsraw' => '
        hljs.initHighlightingOnLoad();// init highlight.js
',
];
