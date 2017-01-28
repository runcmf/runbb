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

namespace RunBB\Core;

class RunBBTwig extends \Twig_Extension
{

    public function getName()
    {
        return 'runBB_Twig';
    }

    public function getFunctions()
    {
        return [
            /**
             * return fired RunBB hook with or without arguments
             */
            new \Twig_SimpleFunction('fireHook', function ($name) {
                if (is_array($name)) {
                    call_user_func_array([Container::get('hooks'), 'fire'], $name);
                } else {
                    Container::get('hooks')->fire($name);
                }
            }, ['is_safe' => ['html']]),

            /**
             * return RunBB settings value
             */
            new \Twig_SimpleFunction('settings', function ($name) {
                return ForumSettings::get($name);
            }, ['is_safe' => ['html']]),

            /**
             * Return the translation of a string with or without arguments
             */
            new \Twig_SimpleFunction('trans', function ($str) {
                if (is_array($str)) {
                    return call_user_func_array('__', $str);
                } else {
                    return __($str);
                }
            }, ['is_safe' => ['html']]),

            /**
             * Returns the translation of a string in a specific domain with or without arguments.
             */
            new \Twig_SimpleFunction('transd', function ($str) {// FIXME check work
                if (is_array($str)) {
                    return call_user_func_array('d__', $str);
                } else {
                    return d__($str);
                }
            }, ['is_safe' => ['html']]),

            /**
             * return Url::base_static() value
             */
            new \Twig_SimpleFunction('baseStatic', function () {
                return Url::base_static();
            }, ['is_safe' => ['html']]),

            /**
             * return Url::base() value
             */
            new \Twig_SimpleFunction('urlBase', function () {
                return Url::base();
            }, ['is_safe' => ['html']]),

            /**
             * return Url::base() value
             */
            new \Twig_SimpleFunction('urlFriendly', function ($url) {
                return Url::url_friendly($url);
            }, ['is_safe' => ['html']]),

            /**
             * return Router::pathFor() value
             */
            new \Twig_SimpleFunction('pathFor', function ($name, array $data = [], array $queryParams = []) {
                return Router::pathFor($name, $data, $queryParams);
            }, ['is_safe' => ['html']]),

            /**
             * return User::get()->value
             */
            new \Twig_SimpleFunction('userGet', function ($val) {
                return User::get()->$val;
            }, ['is_safe' => ['html']]),

            /**
             * return token
             */
            new \Twig_SimpleFunction('getToken', function () {
                return Random::hash(User::get()->id.Random::hash(Utils::getIp()));
            }, ['is_safe' => ['html']]),

            /**
             * return given type hash
             */
            new \Twig_SimpleFunction('getHash', function ($type, $var) {
                if ($type === 'md5') {
                    return md5($var);
                }// TODO add types
            }, ['is_safe' => ['html']]),

            /**
             * return preg_match_all result
             */
//            new \Twig_SimpleFunction('pregMatchAll', function ($preg, &$val) {
//                preg_match_all($preg, $val, $results);
//                return $results;
//            }, ['is_safe' => ['html']]),

            /**
             * return Container::get('utils')->format_time($var) result
             * Container::get('utils')->format_time(
             *  $timestamp, $date_only, $date_format, $time_format, $time_only, $no_text
             * )
             */
            new \Twig_SimpleFunction('formatTime', function (
                $timestamp,
                $date_only = false,
                $date_format = null,
                $time_format = null,
                $time_only = false,
                $no_text = false
            ) {
                return Container::get('utils')->format_time(
                    $timestamp, $date_only, $date_format, $time_format, $time_only, $no_text
                );
            }, ['is_safe' => ['html']]),

            /**
             * return Utils::forum_number_format($var) result
             */
            new \Twig_SimpleFunction('formatNumber', function ($var) {
                return Utils::forum_number_format($var);
            }, ['is_safe' => ['html']]),

            /**
             * return Input::post() result
             * from Request::getParsedBodyParam
             */
            new \Twig_SimpleFunction('inputPost', function ($var) {
                return Input::post($var);
            }, ['is_safe' => ['html']]),

            /**
             * Format user title
             * return Utils::get_title($title, $name, $groupTitle, $gid) result
             */
            new \Twig_SimpleFunction('formatTitle', function ($title, $name='', $groupTitle='', $gid='') {
                return Utils::get_title($title, $name, $groupTitle, $gid);
            }, ['is_safe' => ['html']]),

            /**
             * Get forum environment var
             * TODO merge with settings???
             * return ForumEnv::get($var) result
             */
            new \Twig_SimpleFunction('getEnv', function ($var) {
                return ForumEnv::get($var);
            }, ['is_safe' => ['html']]),

            /**
             * Generate breadcrumbs from an array of name and URLs
             * return AdminUtils::breadcrumbs_admin($links) result
             */
            new \Twig_SimpleFunction('breadcrumbsAdmin', function (array $links) {
                return AdminUtils::breadcrumbs_admin($links);
            }, ['is_safe' => ['html']]),

            /**
             * Generate increment
             * return incremented index
             */
            new \Twig_SimpleFunction('getIndex', function () {
                static $index = 0;
                return ++$index;
            }, ['is_safe' => ['html']]),
        ];
    }

}
