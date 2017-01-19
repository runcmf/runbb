        <div class="blockform">
            <h2><span><?= __('Warning') ?></span></h2>
            <div class="box">
                <form method="post" action="">
                    <input type="hidden" name="topics" value="<?= implode(",", $topics); ?>" />
                    <input name="delete_comply" value="1" type="hidden" />
                    <input name="action" value="delete" type="hidden" />
                    <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
                    <div class="inform warning">
                        <div class="forminfo">
                            <p><?= __('Confirm delete', 'private_messages'); ?></p>
                        </div>
                    </div>
                    <p class="buttons"><input type="submit" name="delete" value="<?= __('Delete'); ?>" /> <a href="javascript:history.go(-1)"><?= __('Go back') ?></a></p>
                </form>
            </div>
        </div>
        <div class="clearer"></div>
