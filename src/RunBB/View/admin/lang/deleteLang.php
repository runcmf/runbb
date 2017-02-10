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

if (!isset($feather)) {
    exit;
}

Container::get('hooks')->fire('view.admin.language.delete.start');
?>

    <div class="blockform">
        <h2><span>Confirm delete language</span></h2>
        <div class="box">
            <form method="post" action="<?= Router::pathFor('adminLanguages.delete') ?>">
                <input type="hidden" name="csrf_name" value="<?= $csrf_name ?>">
                <input type="hidden" name="csrf_value" value="<?= $csrf_value ?>">
                <input type="hidden" name="lid" value="<?= $lid ?>">
                <div class="inform">
                    <fieldset>
                        <legend class="info">Important! Read before deleting</legend>
                        <div class="infldset">
                            <p>Are you sure that you want to delete the lang:  <strong><?= $language ?></strong></p>
                            <p class="warntext">WARNING! Deleting a lang will delete all translations and email templates!</p>
                            <label><input class="checkbox-inline" type="checkbox" name="agree" value="1">I know</label>
                        </div>
                    </fieldset>
                </div>
                <p class="buttons"><input type="submit" name="del_forum_comply" value="<?= __('Delete') ?>" />
                    <a href="javascript:history.go(-1)"><?= __('Go back') ?></a></p>
            </form>
        </div>
    </div>
    <div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.admin.language.delete.end');

