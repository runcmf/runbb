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
// Make sure no one attempts to run this script "directly"
if (!isset($feather)) {
    exit;
}

Container::get('hooks')->fire('view.admin.pluginsRepo.start');
?>

    <div class="block">
        <h2>Plugins Repository</h2>
        <div class="box">
            <table class="table">
                <caption><?= __('Available plugins') ?></caption>
                <?php foreach ($repoList as $category => $extensions) : ?>
                <table class="table">
                    <tr>
                        <td class="blockpost"><h2>Category: <strong><?= $category ?></strong></h2></td>
                    </tr>
                    <?php foreach ($extensions as $ext) : ?>
                        <?php if (!isset($ext['name'])) { continue; } ?>
                        <tr>
                            <td>
                                <strong><?= $ext['name'] ?></strong> &nbsp;
                                    <?php if ($ext['isInstalled']) { ?>
                                        <mark>installed</mark>
                                    <?php } else { ?>
                                        <mark style="background-color: lightgrey">Not installed</mark>
                                    <?php } ?>
                                <div class="pull-right">
                                    <a href="#" class="btn btn-info btn-xs" onclick="RunBB.popupWindow('<?= Router::pathFor('downloadPlugin', ['name' => $ext['key']]) ?>'); return false;">
                                        composer &nbsp; <i class="fa fa-play fa-lg"></i>
                                    </a>
                                </div>
                                <br> <small><?= $ext['package'] ?></small><br>
                                <a class="fancybox" href="https://raw.githubusercontent.com/runcmf/runbb-languages/master/extimg/<?= $ext['info'] ?>.png">
                                    <img src="https://raw.githubusercontent.com/runcmf/runbb-languages/master/extimg/<?= $ext['info'] ?>.png" style="height: 100px !important; width: 200px" />
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.admin.pluginsRepo.end');
