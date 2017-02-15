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

namespace RunBB\Controller\Admin;

use RunBB\Core\AdminUtils;
use RunBB\Core\Interfaces\ForumEnv;
use RunBB\Core\Interfaces\Input;
use RunBB\Exception\RunBBException;
use RunBB\Core\Utils;

class Languages
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Languages();
        Lang::load('admin-common');
    }

    public function display($req, $res, $args)
    {
//        return $this->loadLanguages();

        // from modal langinfo
        if (Input::post('langId', 0) > 0) {
            return $this->saveLangInfo(Input::post('langId', 0));
        }

        Container::get('hooks')->fire('controller.admin.languages.display');

        AdminUtils::generateAdminMenu('languages');

        return View::setPageInfo([
            'active_page' => 'admin',
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), 'Languages'],// TODO translate
            'admin_console' => true,
            'langList' => $this->model->getLangList()
        ])->addTemplate('@forum/admin/lang/admin_lang')->display();
    }

    public function langInfo()
    {
        $id = Input::query('langinfo');

        // show modal
        return View::setPageInfo([
            'info' => $this->model->getLangInfo($id)
        ])->addTemplate('@forum/admin/lang/langInfo')->display(false);
    }

    public function saveLangInfo($id)
    {
        $data = [
            'id' => $id,
            'code' => Input::post('code'),
            'locale' => Input::post('locale'),
            'name' => Input::post('name'),
            'version' => Input::post('version'),
            'image' => Input::post('image'),
            'author' => Input::post('author'),
        ];

        $this->model->updateLangInfo($data);
        return Router::redirect(
            Router::pathFor('adminLanguages'),
            ['success', 'Language Info Updated']// TODO translate
        );
    }

    public function showLangFiles()
    {
        Container::get('hooks')->fire('controller.admin.languages.showLangFiles');

        AdminUtils::generateAdminMenu('languages');

        $id = Input::query('lng');
        return View::setPageInfo([
            'active_page' => 'admin',
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), 'Languages'],// TODO translate
            'admin_console' => true,
            'langinfo' => $this->model->getLangInfo($id),
            'domainList' => $this->model->getDomainList($id)
        ])->addTemplate('@forum/admin/lang/domainList')->display();
    }

    public function showRepo()
    {
        Container::get('hooks')->fire('controller.admin.languages.showRepo');

        AdminUtils::generateAdminMenu('languages');

        $installedList = $this->model->getLangList();
        $repoList = Container::get('remote')->getLangRepoList();

        foreach ($repoList as $key => $lang) {
            $repoList[$key]->isInstalled =
                (Utils::recursiveArraySearch($lang->code, $installedList) !== false)
                    ? true : false;
        }

        return View::setPageInfo([
            'active_page' => 'admin',
            'title' => [Utils::escape(ForumSettings::get('o_board_title')),
                __('Admin'), 'Languages Repository'],// TODO translate
            'admin_console' => true,
            'langList' => $repoList
        ])->addTemplate('@forum/admin/lang/repoList')->display();
    }

    public function editLang()
    {
        Container::get('hooks')->fire('controller.admin.languages.editLang');

        AdminUtils::generateAdminMenu('languages');

        $lngId = Input::query('lng') ? Input::query('lng') : Input::post('lng');
        $grp = Input::query('grp') ? Input::query('grp') : Input::post('grp');
        $transtr = Input::post('transtr');

        if ($transtr) {
            $this->model->update($transtr);
            $this->model->generateTranslation($lngId, $grp);

            return Router::redirect(
                Router::pathFor('adminLanguages.showlangfiles', [], ['lng' => $lngId]),
                ['success', 'lang updated']// TODO translate
            );
        }

        return View::setPageInfo([
            'active_page' => 'admin',
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), 'Languages'],// TODO translate
            'admin_console' => true,
            'lng' => $lngId,
            'grp' => $grp,
            'langinfo' => $this->model->getLangInfo($lngId),
            'translateList' => $this->model->getTranslationsByDomain((int)$lngId, $grp)
        ])->addTemplate('@forum/admin/lang/editLang')->display();
    }

    public function showMailTemplates()
    {
        $text = Input::post('mailTemplateText');
        if ($text) {
            $this->model->updateMailTemplates($text);
            return Router::redirect(
                Router::pathFor('adminLanguages'),
                ['success', 'Mail templates updated']// TODO translate
            );
        }

        $id = Input::query('lng');
        $name = Input::query('name');

        AdminUtils::generateAdminMenu('languages');

        $tpls = $this->model->getMailTemplatesById($id);
        return View::setPageInfo([
            'active_page' => 'admin',
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), 'Languages'],// TODO translate
            'admin_console' => true,
            'lng' => $id,
            'name' => $name,
            'templates' => $tpls
        ])->addTemplate('@forum/admin/lang/mailTplList')->display();
    }

    public function exportLanguage()
    {
        $id = Input::query('lng') ? Input::query('lng') : Input::post('lng');

        $this->model->exportLang($id);

        return Router::redirect(
            Router::pathFor('adminLanguages'),
            ['success', 'Language exported']// TODO translate
        );
    }

    public function importLanguage()
    {
        $code = Input::query('lng');

        $out = 'Imported language: ' . "\n";
        $info = $this->model->importLang($code);

        if (!empty($info)) {
            foreach ($info as $i) {
                $out .= 'lid: ' . $i['lid'] . ', code: ' . $i['code'] . ', name: ' . $i['name'] . ",\n" .
                    'locale: ' . $i['locale'] . ', translations: ' . $i['transcount'] . ",\n" .
                    'mail templates: ' . $i['mailTemplates'] . "\n";
            }
        } else {
            $out .= 0;
        }

        return Router::redirect(
            Router::pathFor('adminLanguages'),
            ['success', $out]// TODO translate, format
        );
    }

    public function buildNewTranslation()
    {
        $agree = Input::post('iknow');
        if (!$agree) {
            return Router::redirect(
                Router::pathFor('adminLanguages'),
                ['error', 'Click checkbox']// TODO translate, format
            );
        }

        $out = 'New language id: ';

        $data = [
            'code' => Input::post('code'),
            'locale' => Input::post('locale'),
            'name' => Input::post('name'),
        ];

        $from = Input::post('lid');
        $out .= $this->model->buildLang($data, $from);

        return Router::redirect(
            Router::pathFor('adminLanguages'),
            ['success', $out]// TODO translate, format
        );
    }

    public function deleteTranslation()
    {
        if (Request::isPost()) {
            $agree = Input::post('agree');

            if (!$agree) {
                return Router::redirect(
                    Router::pathFor('adminLanguages'),
                    ['error', 'Click checkbox']// TODO translate
                );
            }
            $lid = Input::post('lid');

            $this->model->deleteLanguage($lid);

            return Router::redirect(
                Router::pathFor('adminLanguages'),
                ['success', 'Translation deleted']// TODO translate
            );
        } else { // If the user hasn't confirmed
            $lid = Input::query('lng');

            AdminUtils::generateAdminMenu('languages');

            $info = $this->model->getLangInfo($lid);

            View::setPageInfo([
                'active_page' => 'admin',
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('Forums')],
                'active_page' => 'admin',
                'admin_console' => true,
                'lid' => $lid,
                'language' => $info->name
            ])->addTemplate('@forum/admin/lang/deleteLang')->display();
        }
    }

    /**
     * Do not use
     * @internal
     */
//    private function loadLanguages()
//    {
//        $this->model->createTables();
//
//        $dir = ForumEnv::get('FORUM_ROOT') . 'lang/';
//
//        $lnDir = [];
//        foreach(glob($dir . '*', GLOB_ONLYDIR) as $v) {
//            $lnDir[] = substr($v, strlen($dir));
//        }
//
//        foreach ($lnDir as $lang) {
//            // load lang info
//            $data = [
//                'code' => strtolower(substr($lang, 0, 2)),
//                'name' => $lang
//            ];
//            // save to db
//            $id = $this->model->addData('languages', $data);
//
//            // load mail templates
//            foreach(glob($dir.$lang.'/mail_templates/*.tpl') as $t) {
//                $data = [
//                    'lid' => $id,
//                    'file' => basename($t, '.tpl'),
//                    'text' => file_get_contents($t)
//                ];
//                // save to db
//                $this->model->addData('lang_mailtpls', $data);
//            }
//
//            // load lang translations
//            $dir_iterator = new \RecursiveDirectoryIterator($dir.$lang);
//            $iterator = new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::SELF_FIRST);
//            foreach ($iterator as $file) {
//                if ($file->isFile()) {
//                    $curFile = substr($file->getPathname(), strlen($dir.$lang.'/'));
//                    $isSub = explode(DIRECTORY_SEPARATOR, $curFile);
//                    if(count($isSub) > 1) {
//                        $domain = $isSub[0].'-';
//                    } else {
//                        $domain = '';
//                    }
//                    $translations = \Gettext\Translations::fromPoFile($file->getPathname());
//                    foreach ($translations as $var) {
//                        $data = [
//                            'lid' => $id,
//                            'domain' => $domain . basename($file->getPathname(), '.po'),//$pathInfo['filename'],
//                            'msgid' => $var->getOriginal(),
//                            'msgstr' => $var->getTranslation()
//                        ];
//                        // save to db
//                        $this->model->addData('lang_trans', $data);
//                    }
//                }
//            }
//        }
//        tdie('ready. comment loader');
//    }
}
