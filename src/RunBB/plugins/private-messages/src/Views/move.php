<?php
use RunBB\Core\Utils;

?>
        <div class="blockform">
            <h2><span><?= __('Move conversations', 'private_messages') ?></span></h2>
            <div class="box">
                <form method="post" action="">
                    <input type="hidden" name="topics" value="<?= implode(",",$topics); ?>" />
                    <input name="move_comply" value="1" type="hidden" />
                    <input name="action" value="move" type="hidden" />
                    <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
                    <div class="inform">
                        <fieldset>
                            <legend><?= __('Move legend'); ?></legend>
                            <div class="infldset">
                                <label><?= __('Move to'); ?><br>
                                    <select name="move_to">
                                        <?php foreach ($inboxes as $key => $inbox): if($key == 1) continue; ?>
                                            <option value="<?= $key ?>"><?= Utils::escape($inbox['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                        </fieldset>
                    </div>
                    <p class="buttons"><input type="submit" name="move" value="<?= __('Move'); ?>" /> <a href="javascript:history.go(-1)"><?= __('Go back') ?></a></p>
                </form>
            </div>
        </div>

        <div class="clearer"></div>
