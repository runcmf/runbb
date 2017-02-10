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

Container::get('hooks')->fire('view.admin.language.mailtemplates.start');

?>
    <div class="block">

        <form class="panel panel-primary" action="<?= Router::pathFor('adminLanguages.showmailtpls') ?>" method="post">
            <div class="panel-heading">
                <h3 class="panel-title">Language: <?= $name ?></h3>
            </div>
            <input type="hidden" name="csrf_name" value="<?= $csrf_name ?>">
            <input type="hidden" name="csrf_value" value="<?= $csrf_value ?>">
            <input type="hidden" name="lng" value="<?= $lng ?>">

            <div class="list-group">
                <?php foreach ($templates as $row) { ?>
                <div class="list-group-item list-striped">
                    <p class="list-group-item-heading">file: <u><?= $row['file'] ?></u></p>
                    <textarea name="mailTemplateText[<?= $row['id'] ?>]" class="form-control"><?= $row['text'] ?></textarea>
                </div>
                <?php } ?>
            </div>
            <div class="panel-footer">
                <input type="submit" class="btn btn-success btn-xs" />
            </div>
        </form>

    </div>

    <div class="clearer"></div>
    <script>
        // auto expand textarea
        window.onload = function(){
            $('textarea').each(function () {
                this.setAttribute('style', 'height:' + (this.scrollHeight) + 'px;overflow-y:hidden;');
            }).on('input', function () {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        };
    </script>
</div>

<?php
Container::get('hooks')->fire('view.admin.language.mailtemplates.end');