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

namespace RunBB\Model\Admin;

use RunBB\Core\Interfaces\User;
use RunBB\Core\Utils;
use RunBB\Exception\RunBBException;

class Languages
{
    protected $database_scheme = [
        'languages' => "DROP TABLE IF EXISTS %t%;
        CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `code` char(2) NOT NULL DEFAULT '',
            `locale` varchar(8) NOT NULL DEFAULT '',
            `name` varchar(16) NOT NULL DEFAULT '',
            `version` varchar(16) NOT NULL DEFAULT '',
            `image` varchar(16) NOT NULL DEFAULT '',
            `author` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'lang_trans' => "DROP TABLE IF EXISTS %t%;
        CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `lid` int(11) NOT NULL DEFAULT '0',
            `domain` varchar(32) NOT NULL DEFAULT '',
            `msgid` varchar(255) NOT NULL DEFAULT '',
            `msgstr` text NOT NULL,
            `msgid_plural` text NOT NULL,
            `reference` varchar(255) NOT NULL DEFAULT '',
            `comment` varchar(255) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            KEY `lid` (`lid`),
            KEY `domain` (`domain`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
        'lang_mailtpls' => "DROP TABLE IF EXISTS %t%;
        CREATE TABLE IF NOT EXISTS %t% (
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
            `lid` int(11) NOT NULL DEFAULT '0',
            `file` varchar(32) NOT NULL DEFAULT '',
            `text` text NOT NULL,
            PRIMARY KEY (`id`),
            KEY `lid` (`lid`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;",];

    public function getLangList()
    {
        return \ORM::forTable(ORM_TABLE_PREFIX.'languages')->findArray();
    }

    public function getLangInfo($id = 0)
    {
        return \ORM::forTable(ORM_TABLE_PREFIX.'languages')->findOne((int)$id);
    }

    public function getLangInfoByCode($code = '')
    {
        return \ORM::forTable(ORM_TABLE_PREFIX.'languages')
            ->where('code', $code)
            ->findOne();
    }

    public function getLangCount($id)
    {
        return \ORM::forTable(ORM_TABLE_PREFIX.'lang_trans')
            ->where('lid', (int)$id)
            ->count();
    }

    public function getMailTemplatesCount($id)
    {
        return \ORM::forTable(ORM_TABLE_PREFIX.'lang_mailtpls')
            ->where('lid', (int)$id)
            ->count();
    }

    public function getDomainList($id)
    {
        $list = \ORM::forTable(ORM_TABLE_PREFIX.'lang_trans')
            ->select_many('lid', 'domain')
            ->where('lid', (int)$id)
            ->groupBy('domain')
            ->select_expr('COUNT(`domain`)', 'count')
            ->findMany();

        return $list;
    }

    public function getTranslationsByDomain($id, $domain)
    {
        // now only with English compare, rebuild for any
        if ($id === 1) {
            $list = \ORM::forTable(ORM_TABLE_PREFIX . 'lang_trans')
                ->where([
                    'lid' => (int)$id,
                    'domain' => (string)$domain
                ])
                ->findArray();
        } else {
            $list = \ORM::forTable(ORM_TABLE_PREFIX . 'lang_trans')
                ->table_alias('lt')
//                ->select_many('lt.id', 'lt.domain', 'lt.msgid', 'lt.msgstr')
                ->select('lt.*')
                ->select('ltwith.msgstr', 'msgstrwith')
                ->innerJoin(ORM_TABLE_PREFIX . 'lang_trans', ['lt.msgid', '=', 'ltwith.msgid'], 'ltwith')
                ->where([
                    'lt.lid' => (int)$id,
                    'lt.domain' => (string)$domain,
                    'ltwith.lid' => 1,// TODO to any langs
                ])
                ->group_by('id')
                ->findArray();
        }
        return $list;
    }

    public function getTranslationsById($id)
    {
        $list = \ORM::forTable(ORM_TABLE_PREFIX . 'lang_trans')
            ->where('lid', (int)$id)
            ->order_by_asc('domain')
            ->findArray();
        return $list;
    }

    public function getMailTemplatesById($id)
    {
        $list = \ORM::forTable(ORM_TABLE_PREFIX . 'lang_mailtpls')
            ->where('lid', (int)$id)
            ->findArray();
        return $list;
    }

    public function updateLangInfo(array $data = [])
    {
        if (empty($data)) {
            throw new RunBBException('Data empty. Can not update language info');
        }
        $rec = \ORM::forTable(ORM_TABLE_PREFIX.'languages')
            ->findOne($data['id'])
            ->set($data);
        if (!$rec->save()) {
            throw new RunBBException('A problem was encountered while update Info for language: '.
                isset($data['name']) ? $data['name'] : 'Unknown');
        }
        return true;
    }

    public function update(array $arr = [])
    {
        if (empty($arr)) {
            throw new RunBBException('Data empty. Can not update language translations');
        }
        foreach ($arr as $key => $var) {
            $rec = \ORM::forTable(ORM_TABLE_PREFIX . 'lang_trans')
                ->findOne($key)
                ->set('msgstr', $var);
            if (!$rec->save()) {
                throw new RunBBException('A problem was encountered while update translation msgstr: '.$var);
            }
        }
        return true;
    }

    public function updateMailTemplates(array $arr = [])
    {
        if (empty($arr)) {
            throw new RunBBException('Data empty. Can not update language mail templates');
        }
        foreach ($arr as $key => $var) {
            $rec = \ORM::forTable(ORM_TABLE_PREFIX . 'lang_mailtpls')
                ->findOne($key)
                ->set('text', $var);
            if (!$rec->save()) {
                throw new RunBBException('A problem was encountered while update mail template text: '.$var);
            }
        }
        return true;
    }

    public function generateTranslationByDomain($code = 'en', $domain = '')
    {
        $lang = $this->getLangInfoByCode($code);
        $this->generateTranslation($lang->id, $domain);
    }

    public function generateTranslation($id, $domain)
    {
        $info = $this->getLangInfo($id);
        $arr = $this->getTranslationsByDomain($id, $domain);
        $dir = ForumEnv::get('FORUM_CACHE_DIR') . 'locale/' . $info->code .'/LC_MESSAGES';
        if (Utils::checkDir($dir)) {
            $translator = isset(User::get()->username) ? User::get()->username : 'RunBB System';
            $email = isset(User::get()->email) ?
                User::get()->email : Container::get('forum_settings')['o_webmaster_email'];
            $translations = new \Gettext\Translations();
            $translations->setLanguage($info->code);
            $translations->setDomain($domain);
            $translations->setHeader('Project-Id-Version', 'runcmf/runbb v.'.ForumEnv::get('FORUM_VERSION'));
            $translations->setHeader('Report-Msgid-Bugs-To', 'https://github.com/runcmf/runbb/issues');
            $translations->setHeader('Last-Translator', $translator.' / '.$email);
            $translations->setHeader('Language-Team', 'RunBB ' . $info->name);
            foreach ($arr as $var) {
                $translation = new \Gettext\Translation('', $var['msgid']);
                $translation->setTranslation($var['msgstr']);
//                $translation->setPluralTranslation('%s comentarios');
//                $translation->addReference('templates/comments/comment.php', 34);
//                $translation->addComment('To display the amount of comments in a post');
                $translations->offsetSet('', $translation);
            }
            //Save to a file
            if (!\Gettext\Generators\Po::toFile($translations, $dir . '/'.$domain.'.po')) {
                throw new RunBBException('Can not update PO file: '.$domain);
            }
            if (!\Gettext\Generators\Mo::toFile($translations, $dir . '/'.$domain.'.mo')) {
                throw new RunBBException('Can not update MO file: '.$domain);
            }
        }
    }

    public function exportLang($id)
    {
        //1 get lang info
        $info = $this->getLangInfo($id);
        $out = [
            'code' => $info->code,
            'locale' => $info->locale,
            'name' => $info->name,
            'version' => $info->version,
            'image' => $info->image,
            'author' => $info->author
        ];
        $langInfo = $out;

        //2 get translations
        $trans = $this->getTranslationsById($id);
        foreach ($trans as $var) {
            $out['translations'][] = [
                'domain' => $var['domain'],
                'msgid' => $var['msgid'],
                'msgstr' => $var['msgstr'],
                'msgid_plural' => $var['msgid_plural'],
                'reference' => $var['reference'],
                'comment' => $var['comment']
            ];
        }

        //3 get mail templates
        $mailTpls = $this->getMailTemplatesById($id);
        foreach ($mailTpls as $m) {
            $out['mailTemplates'][] = [
                'file' => $m['file'],
                'text' => $m['text']
            ];
        }
//        $file = 'runbb_translation_'.$info->code.'.php';
        $file = 'runbb_translation_'.$info->code.'.json';

        // Output the language data array to file. Convert to string first.
//        $s = "<?php\n// File: $file. Automatically generated: " .
//            date('Y-m-d h:i:s') . " by " . User::get()->username .
//            ".\n// DIRECT EDIT NOT WELCOME. USE ADMIN LANGUAGE EDITOR INSTEAD.\n";
//        $s .= "return " . var_export($out, true) . ";\n";
        $s = json_encode($out, JSON_PRETTY_PRINT);
//        $s = json_encode($out);

        if (file_put_contents(ForumEnv::get('FORUM_CACHE_DIR') . $file, $s) === false) {
            throw new RunBBException('Can not write language file : '.$file);
        }
        // save info
        $i = json_encode($langInfo, JSON_PRETTY_PRINT);
        if (file_put_contents(ForumEnv::get('FORUM_CACHE_DIR') . 'info_' . $info->code .'.json', $i) === false) {
            throw new RunBBException('Can not write language file : ' . 'info_' . $file);
        }
/*
        $zip = new \ZipArchive();
        $filename = ForumEnv::get('FORUM_CACHE_DIR') .
            substr($file, 0, -4) . '_'.date('Ymd_His').'.zip';
        if ($zip->open($filename, \ZipArchive::CREATE)!==TRUE) {
            throw new RunBBException('Can not open zip file : '.$filename);
        }
        $zip->addFile(ForumEnv::get('FORUM_CACHE_DIR') . $file, $file);
        $zip->close();
*/
        return true;
    }

    public function importLang($code = null)
    {
        if ($code === null) {
            return false;
        }

        $data = Container::get('remote')->getLang($code);
        $data = json_decode(base64_decode($data));

        // first check if code exists
        $installed = $this->getLangInfoByCode($data->code);
        if ($installed) {
            // clear table ??? FIXME rebuild logic
            $z = \ORM::for_table(ORM_TABLE_PREFIX . 'languages')->find_one($installed->id);
            $z->delete();
            // clear translations
            \ORM::for_table(ORM_TABLE_PREFIX . 'lang_trans')
                ->where_equal('lid', $installed->id)
                ->delete_many();
            // clear mail templates
            \ORM::for_table(ORM_TABLE_PREFIX . 'lang_mailtpls')
                ->where_equal('lid', $installed->id)
                ->delete_many();
        }
        $l = [
            'code' => $data->code,
            'locale' => isset($data->locale) ? $data->locale : '',
            'name' => $data->name,
            'version' => $data->version,
            'image' => $data->image,
            'author' => $data->author
        ];
        $lid = $this->addData('languages', $l);

        // fill translations
        $transcount = count($data->translations);
        foreach ($data->translations as $t) {
            $t = get_object_vars($t);
            // set lang id
            $t['lid'] = $lid;
            $id = $this->addData('lang_trans', $t);
        }

        // fill mail templates
        $mailTemplates = count($data->mailTemplates);
        foreach ($data->mailTemplates as $m) {
            $m = get_object_vars($m);
            $m['lid'] = $lid;
            $id = $this->addData('lang_mailtpls', $m);
        }
        // collect info
        $info[] = [
            'lid' => $lid,
            'code' => $data->code,
            'name' => $data->name,
            'locale' => isset($data->locale) ? $data->locale : '',
            'transcount' => $transcount,
            'mailTemplates' => $mailTemplates

        ];

        return $info;
    }

    // deprecated
//    public function importLang()
//    {
//        $files = $info = [];
//        foreach(glob(ForumEnv::get('FORUM_CACHE_DIR') . 'runbb_translation_*') as $v) {
//            $files[] = $v;
//        }
//
//        foreach ($files as $f) {
//            $vars = require $f;
//
//            // first check if code exists
//            $installed = $this->getLangInfoByCode($vars['code']);
//            if($installed) {
//                // clear table ??? FIXME rebuild logic
//                $z = \ORM::for_table(ORM_TABLE_PREFIX . 'languages')->find_one($installed->id);
//                $z->delete();
//                // clear translations
//                \ORM::for_table(ORM_TABLE_PREFIX . 'lang_trans')
//                    ->where_equal('lid', $installed->id)
//                    ->delete_many();
//                // clear mail templates
//                \ORM::for_table(ORM_TABLE_PREFIX . 'lang_mailtpls')
//                    ->where_equal('lid', $installed->id)
//                    ->delete_many();
//            }
//            $l = [
//                'code' => $vars['code'],
//                'locale' => isset($vars['locale']) ? $vars['locale'] : '',
//                'name' => $vars['name']
//            ];
//            $lid = $this->addData('languages', $l);
//
//            // fill translations
//            $transcount = count($vars['translations']);
//            foreach ($vars['translations'] as $t) {
//                // set lang id
//                $t['lid'] = $lid;
//                $id = $this->addData('lang_trans', $t);
//            }
//
//            // fill mail templates
//            $mailTemplates = count($vars['mailTemplates']);
//            foreach ($vars['mailTemplates'] as $m) {
//                $m['lid'] = $lid;
//                $id = $this->addData('lang_mailtpls', $m);
//            }
//            // collect info
//            $info[] = [
//                'lid' => $lid,
//                'code' => $vars['code'],
//                'name' => $vars['name'],
//                'locale' => isset($vars['locale']) ? $vars['locale'] : '',
//                'transcount' => $transcount,
//                'mailTemplates' => $mailTemplates
//
//            ];
//        }
//        return $info;
//    }

    public function buildLang($data, $from)
    {
        if (!is_array($data)) {
            throw new RunBBException('Cannot build lang. Empty data');
        }
        // add lang
        $lid = $this->addData('languages', $data);
        // add translations
        $trans = $this->getTranslationsById($from);
        foreach ($trans as $t) {
            // unset id
            unset($t['id']);
            // set new lang id
            $t['lid'] = $lid;
            $id = $this->addData('lang_trans', $t);
        }
        // add mail templates
        $mailTpls = $this->getMailTemplatesById($from);
        foreach ($mailTpls as $m) {
            // unset id
            unset($m['id']);
            // set new lang id
            $m['lid'] = $lid;
            $id = $this->addData('lang_mailtpls', $m);
        }
        return $lid;
    }

    public function deleteLanguage($id)
    {
        // delete lang info
        $z = \ORM::for_table(ORM_TABLE_PREFIX . 'languages')->find_one($id);
        $z->delete();
        // delete translations
        \ORM::for_table(ORM_TABLE_PREFIX . 'lang_trans')
            ->where_equal('lid', $id)
            ->delete_many();
        // delete mail templates
        \ORM::for_table(ORM_TABLE_PREFIX . 'lang_mailtpls')
            ->where_equal('lid', $id)
            ->delete_many();

        return true;
    }

    public function getDatabaseScheme()
    {
        return $this->database_scheme;
    }

//    public function createTable($table_name, $sql)
//    {
//        $db = \ORM::get_db();
//        $req = preg_replace('/%t%/', '`' . $table_name . '`', $sql);
//        return $db->exec($req);
//    }
//
//    public function addData($table_name, array $data)
//    {
//        $req = \ORM::for_table(ORM_TABLE_PREFIX . $table_name)
//            ->create()
//            ->set($data);
//        $req->save();
//        return $req->id();
//    }
//
//    public function createTables()
//    {
//        foreach ($this->getDatabaseScheme() as $table => $sql) {
//            if ($this->createTable(ORM_TABLE_PREFIX.$table, $sql) !== 0) {
//                // Error handling
//                throw new  RunBBException('A problem was encountered while creating table '.$table);
//            }
//        }
//    }
}
