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
        'style/themes/tryOne/css/bootstrap.min.css',
        'style/themes/tryOne/css/jquery-ui.min.css',// elFinder depend
        'style/themes/tryOne/css/font-awesome.min.css',
        'style/themes/tryOne/css/github.css',// highlight.js theme
        'style/themes/tryOne/css/jquery.fancybox.css'
    ],
    'js' => [
        'style/themes/tryOne/js/jquery-3.1.1.min.js',
        'style/themes/tryOne/js/jquery-ui.min.js',// elFinder depend
        'style/themes/tryOne/js/bootstrap.min.js',
        'style/themes/tryOne/js/highlight.pack.js',
        'style/themes/tryOne/js/jquery.fancybox.pack.js',
        'style/themes/tryOne/js/common.js',
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
