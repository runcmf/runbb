<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

use RunBB\Core\Utils;

// Make sure no one attempts to run this script "directly"
if (!isset($feather)) {
    exit;
}

Container::get('hooks')->fire('view.register.email.start');
?>

<div id="emailform" class="blockform">
    <h2><span><?= __('Send email to') ?> <?= Utils::escape($recipient) ?></span></h2>
    <div class="box">
        <form id="email" method="post" action="misc.php?email=<?= $recipient_id ?>" onsubmit="this.submit.disabled=true;if(process_form(this)){return true;}else{this.submit.disabled=false;return false;}">
            <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
            <div class="inform">
                <fieldset>
                    <legend><?= __('Write email') ?></legend>
                    <div class="infldset txtarea">
                        <input type="hidden" name="form_sent" value="1" />
                        <input type="hidden" name="redirect_url" value="<?= Utils::escape($redirect_url) ?>" />
                        <label class="required"><strong><?= __('Email subject') ?> <span><?= __('Required') ?></span></strong><br />
                        <input class="longinput" type="text" name="req_subject" size="75" maxlength="70" tabindex="1" /><br /></label>
                        <label class="required"><strong><?= __('Email message') ?> <span><?= __('Required') ?></span></strong><br />
                        <textarea name="req_message" rows="10" cols="75" tabindex="2"></textarea><br /></label>
                        <p><?= __('Email disclosure note') ?></p>
                    </div>
                </fieldset>
            </div>
            <p class="buttons"><input type="submit" name="submit" value="<?= __('Submit') ?>" tabindex="3" accesskey="s" /> <a href="javascript:history.go(-1)"><?= __('Go back') ?></a></p>
        </form>
    </div>
</div>
<?php
Container::get('hooks')->fire('view.register.email.end');
