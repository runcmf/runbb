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

Container::get('hooks')->fire('view.admin.repoList.start');

?>

<div class="block">

    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">Repo Languages list</h3>
        </div>
        <div class="list-group">
            <div class="list-group-item list-group-item-info">
                <div class="row">
                    <div class="col-sm-4">Image</div>
                    <div class="col-sm-8">Info</div>
                </div>
            </div>
            <?php foreach ($langList as $lang) { ?>
            <div class="list-group-item">
                <div class="row">
                    <div class="col-sm-4">
                        <img src="https://raw.githubusercontent.com/runcmf/runbb-languages/master/img/<?= $lang->image ?>" class="img-rounded" title="<?= $lang->name ?>" alt="<?= $lang->name ?>" width="300" height="250">
                    </div>
                    <div class="col-sm-8">
                        Lang: <?= $lang->name ?>, ver: <?= $lang->version ?><br>
                        Code: <?= $lang->code ?><br>
                        Locale: <?= $lang->locale ?><br>
                        Author: <?= $lang->author ?><br>

                        <a href="<?= Router::pathFor('adminLanguages.import') ?>?lng=<?= $lang->code ?>" class="btn btn-success btn-xs" onclick="return confirm('Are you sure?');">
                            install
                        </a>
                        &nbsp; <?php ($lang->isInstalled == true) ? '<strong>* Installed</strong>' : '' ?>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
        <div class="panel-footer">
            <small>TODO: check/compare version</small>
        </div>
    </div>

</div>
<div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.admin.repoList.end');
