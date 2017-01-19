<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!isset($feather)) {
    exit;
}

Container::get('hooks')->fire('view.profile.change_pass.start');
?>

<div class="blockform">
    <h2><span><?= __('Change pass') ?></span></h2>
    <div class="box">
        <form id="change_pass" method="post" action="<?= Router::pathFor('profileAction', ['id' => $id, 'action' => 'change_pass']) ?>" onsubmit="return process_form(this)">
            <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
            <div class="inform">
                <input type="hidden" name="form_sent" value="1" />
                <fieldset>
                    <legend><?= __('Change pass legend') ?></legend>
                    <div class="infldset">
<?php if (!User::get()->is_admmod) :
?>                        <label class="required"><strong><?= __('Old pass') ?> <span><?= __('Required') ?></span></strong><br />
                        <input type="password" name="req_old_password" size="16" /><br /></label>
<?php endif; ?>                        <label class="conl required"><strong><?= __('New pass') ?> <span><?= __('Required') ?></span></strong><br />
                        <input type="password" name="req_new_password1" size="16" /><br /></label>
                        <label class="conl required"><strong><?= __('Confirm new pass') ?> <span><?= __('Required') ?></span></strong><br />
                        <input type="password" name="req_new_password2" size="16" /><br /></label>
                        <p class="clearb"><?= __('Pass info') ?></p>
                    </div>
                </fieldset>
            </div>
            <p class="buttons"><input type="submit" name="update" value="<?= __('Submit') ?>" /> <a href="javascript:history.go(-1)"><?= __('Go back') ?></a></p>
        </form>
    </div>
</div>

<?php
Container::get('hooks')->fire('view.profile.change_pass.end');
