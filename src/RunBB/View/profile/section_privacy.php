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

Container::get('hooks')->fire('view.profile.section_privacy.start');
?>
<div class="blockform">
    <h2><span><?= Utils::escape($user->username).' - '.__('Section privacy') ?></span></h2>
    <div class="box">
        <form id="profile6" method="post" action="<?= Router::pathFor('profileSection', ['id' => $id, 'section' => 'privacy']) ?>">
            <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
            <div class="inform">
                <fieldset>
                    <legend><?= __('Privacy options legend') ?></legend>
                    <div class="infldset">
                        <input type="hidden" name="form_sent" value="1" />
                        <p><?= __('Email setting info') ?></p>
                        <div class="rbox">
                            <label><input type="radio" name="form_email_setting" value="0"<?php if ($user->email_setting == '0') {
                                echo ' checked="checked"';
} ?> /><?= __('Email setting 1') ?><br /></label>
                            <label><input type="radio" name="form_email_setting" value="1"<?php if ($user->email_setting == '1') {
                                echo ' checked="checked"';
} ?> /><?= __('Email setting 2') ?><br /></label>
                            <label><input type="radio" name="form_email_setting" value="2"<?php if ($user->email_setting == '2') {
                                echo ' checked="checked"';
} ?> /><?= __('Email setting 3') ?><br /></label>
                        </div>
                    </div>
                </fieldset>
            </div>
<?php if (ForumSettings::get('o_forum_subscriptions') == '1' || ForumSettings::get('o_topic_subscriptions') == '1') :
?>                <div class="inform">
                <fieldset>
                    <legend><?= __('Subscription legend') ?></legend>
                    <div class="infldset">
                        <div class="rbox">
                            <label><input type="checkbox" name="form_notify_with_post" value="1"<?php if ($user->notify_with_post == '1') {
                                echo ' checked="checked"';
} ?> /><?= __('Notify full') ?><br /></label>
<?php if (ForumSettings::get('o_topic_subscriptions') == '1') :
?>                                <label><input type="checkbox" name="form_auto_notify" value="1"<?php if ($user->auto_notify == '1') {
    echo ' checked="checked"';
} ?> /><?= __('Auto notify full') ?><br /></label>
<?php endif; ?>
                        </div>
                    </div>
                </fieldset>
            </div>
<?php endif; ?>                <p class="buttons"><input type="submit" name="update" value="<?= __('Submit') ?>" /> <?= __('Instructions') ?></p>
        </form>
    </div>
</div>
    <div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.profile.section_privacy.end');
