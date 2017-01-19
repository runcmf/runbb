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

use Gettext\Translations;
use Gettext\Translator;

class Language
{
    private $domain;
    private $translator;

    public function __construct($domain='RunBB')
    {
        $this->domain = $domain;
        $this->translator = new Translator();
        $this->translator->defaultDomain($domain);
        $this->translator->register();
    }

    public function getTranslator()
    {
        return $this->translator;
    }

    public function setDomain($domain)
    {
//        $this->domain = $domain;
    }

    public function load($file, $domain)
    {
        $this->translator->loadTranslations(
            Translations::fromPoFile($file)->setDomain($domain)
//            Translations::fromMoFile($file)->setDomain($domain)
        );
    }

    public function gettext($text, $domain)
    {
        $ret= $this->translator->dgettext($domain, $text);
//dump($ret.' / '.$domain.' / '.$text);
        return $ret;
    }
}