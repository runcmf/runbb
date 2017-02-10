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

use RunBB\Core\Utils;

if (!isset($feather)) {
    exit;
}
?>

<!-- Modal -->
<div class="modal fade" id="langInfo" tabindex="-1" role="dialog"
     aria-labelledby="langInfoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form role="form" method="post">
                <!-- Modal Header -->
                <div class="modal-header">
                    <button type="button" class="close"
                            data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                        <span class="sr-only">Close</span>
                    </button>
                    <h4 class="modal-title" id="langInfoLabel">
                        Modal title
                    </h4>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">

                    <input type="hidden" name="csrf_name" value="<?= $csrf_name ?>">
                    <input type="hidden" name="csrf_value" value="<?= $csrf_value ?>">
                    <input type="hidden" name="langId" value="<?= $info->id ?>">

                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-addon"> &nbsp;Code</div>
                            <input type="text" class="form-control" id="code" name="code" maxlength="2"
                                   value="<?= $info->code ?>" placeholder="Enter 2 sym. code">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-addon">Locale</div>
                            <input type="text" class="form-control" id="locale" name="locale" maxlength="8"
                                   value="<?= $info->locale ?>" placeholder="Enter locale code"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-addon">Name</div>
                            <input type="text" class="form-control" id="name" name="name" maxlength="16"
                                   value="<?= $info->name ?>" placeholder="Enter version name"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-addon">Version</div>
                            <input type="text" class="form-control" id="version" name="version" maxlength="16"
                                   value="<?= $info->version ?>" placeholder="Translation Version"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-addon">Image</div>
                            <input type="text" class="form-control" id="image" name="image" maxlength="16"
                                   value="<?= $info->image ?>" placeholder="Enter Image Name"/>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="input-group">
                            <div class="input-group-addon">Author</div>
                            <input type="text" class="form-control" id="author" name="author" maxlength="255"
                                   value="<?= Utils::escape($info->author) ?>" placeholder="Enter Author info"/>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        Close
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Save changes
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>
<!-- /Modal -->

