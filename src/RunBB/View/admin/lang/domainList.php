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

Container::get('hooks')->fire('view.admin.domainlist.start');
$total = 0;
// TODO translation for translations ;)
?>

<div class="block">

            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">Domains list for: <?= $langinfo['name']; ?></h3>
                </div>

                    <div class="list-group">
                        <div class="list-group-item list-group-item-info">
                            <div class="row">
                                <div class="col-sm-4">Domain</div>
                                <div class="col-sm-2">Count</div>
                                <div class="col-sm-6 text-right">Actions</div>
                            </div>
                        </div>
<?php
                        foreach ($domainList as $grp) {
                            $total = $total + $grp['count'];
?>
                            <div class="list-group-item list-group-item-text">
                                <div class="row">
                                    <div class="col-sm-4"><a href="#"><?= $grp['domain']; ?></a></div>
                                    <div class="col-sm-2"><span class="badge"><?= $grp['count']; ?></span></div>
                                    <div class="col-sm-6 text-right">
                                        <a href="<?= Router::pathFor('adminLanguages.editlang') ?>?lng=<?= $grp['lid']; ?>&grp=<?= $grp['domain']; ?>" class="btn btn-success btn-xs">edit</a>
                                        <!-- <a href="#?lng=<?= $grp['lid']; ?>" class="btn btn-info btn-xs">rebuil .mo</a> -->
                                            <a href="#?lng=<?= $grp['lid']; ?>" class="btn btn-danger btn-xs">delete</a>

                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                <div class="panel-footer">
                                        total: <?= $total ?>
                </div>
            </div>

    </div>

    <div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.admin.domainlist.end');