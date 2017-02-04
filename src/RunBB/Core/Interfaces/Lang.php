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

namespace RunBB\Core\Interfaces;

use RunBB\Core\Statical\BaseProxy;
use Gettext\Translations;
use Gettext\Translator;

class Lang extends BaseProxy
{
    private static $domain;
    private static $translator;

    public static function construct($domain = 'RunBB')
    {
        self::$domain = $domain;
        self::$translator = new Translator();
        self::$translator->defaultDomain($domain);
        self::$translator->register();
    }

    public static function load($file, $domain = 'RunBB', $path = false)
    {
        $lng = (!User::get(null)) ? 'English' : User::get()->language;
        // FIXME while debug .po used
        if (!$path) {
            $file = ForumEnv::get('FORUM_ROOT') . 'lang/' . $lng . '/' . $file . '.po';
        } else {
            $file = $path.'/'.$lng.'/'.$file.'.po';
        }
        self::$translator->loadTranslations(
            Translations::fromPoFile($file)->setDomain($domain)
//            Translations::fromMoFile($file)->setDomain($domain)
        );
    }
}
