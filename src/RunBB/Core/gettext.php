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

/**
 * @param string $mofile language file name without extension
 * @param string $domain language domain name, default 'RunBB'
 * @param bool $path if set lang directory string
 * @return bool
 */
function translate($mofile, $domain = 'RunBB', $path = false)
{
    $lng = (!User::get(null)) ? 'English' : User::get()->language;
    // FIXME while debug used .po
    if (!$path) {
//        $mofile = ForumEnv::get('FORUM_ROOT').'lang/'. $lng .'/'.$mofile.'.mo';
        $mofile = ForumEnv::get('FORUM_ROOT') . 'lang/' . $lng . '/' . $mofile . '.po';
    }
    else {
//        $mofile = $path.'/'. $lng .'/'.$mofile.'.mo';
        $mofile = $path.'/'.$lng.'/'.$mofile.'.po';
    }

    if (!is_readable($mofile)) {
        return false;
    }

    Container::get('lang')->load($mofile, $domain);

    return true;
}


// FIXME rebuild to __()
//function _e($text, $domain = 'RunBB')
//{
//    echo Container::get('lang')->gettext($text, $domain);
//}

