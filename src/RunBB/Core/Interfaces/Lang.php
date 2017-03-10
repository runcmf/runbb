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

    public static function load($file, $domain = 'RunBB', $path = false, $language = false)
    {
        if (!$language) {
            $language = (!User::get(null) || !User::get()->language) ? 'English' : User::get()->language;
        }
        $lng = substr(strtolower($language), 0, 2);

        // FIXME while debug .po used
        if (!$path) {
            $tfile = ForumEnv::get('FORUM_CACHE_DIR') . 'locale/' . $lng . '/LC_MESSAGES/' . $file . '.po';
        } else {
            $tfile = $path.'/'.$language.'/'.$file.'.po';
        }

        if (!is_file($tfile)) {
            // generate translation
            $model = new \RunBB\Model\Admin\Languages();
            $model->generateTranslationByDomain($lng, $file);
        }

        self::$translator->loadTranslations(
            Translations::fromPoFile($tfile)->setDomain($domain)
            //            Translations::fromMoFile($tfile)->setDomain($domain)
        );
    }

    public static function getList()
    {
        try {
            return DB::forTable('languages')->findArray();
        } catch (\Exception $e) {
            // config exists, db not exists ?
            // will be generated \RunBBException 'Unknown database' ?
            return [];
        }
    }

    public static function getMailTemplate($file = '')
    {
        return DB::forTable('lang_mailtpls')
            ->tableAlias('t')
            ->innerJoin(DB::prefix() . 'languages', ['t.lid', '=', 'l.id'], 'l')
            ->where('l.name', User::get()->language)
            ->where('t.file', $file)
            ->findOne();
    }
}
