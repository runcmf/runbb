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

Container::get('hooks')->fire('view.admin.editlang.start');

?>

<div class="block">

    <form class="panel panel-primary" action="<?= Router::pathFor('adminLanguages.editlang') ?>" method="post">
        <div class="panel-heading">
            <h3 class="panel-title">Language: <?= $langinfo['name'] ?>, cat: <?= $grp ?></h3>
        </div>
        <input type="hidden" name="csrf_name" value="<?= $csrf_name ?>">
        <input type="hidden" name="csrf_value" value="<?= $csrf_value ?>">
        <input type="hidden" name="lng" value="<?= $lng ?>">
        <input type="hidden" name="grp" value="<?= $grp ?>">

        <div class="list-group">

            <?php foreach ($translateList as $row) { ?>
            <div class="list-group-item" style="max-height: 12em;">
                <p class="list-group-item-heading">msgid: <u><?= $row['msgid'] ?></u></p>

                <?php if (isset($row['msgstrwith'])) { ?>
                <div class="well well-sm">en: <?= $row['msgstrwith'] ?></div>
                <?php } ?>
                <div class="form-inline"><?= $langinfo['code'] ?>
                    <textarea name="transtr[<?= $row['id'] ?>]" class="list-group-item-text" style="width: 97%"><?= $row['msgstr'] ?></textarea>
                </div>
            </div>

            <?php } ?>

        </div>
        <div class="panel-footer">
            <input type="submit" class="btn btn-success btn-xs" />
        </div>
    </form>

</div>

<div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.admin.editlang.end');
