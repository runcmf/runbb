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

?>
<div class="blockform">
    <h2><span><?= __('Permissions head') ?></span></h2>
    <div class="box">
        <form method="post" action="">
            <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
            <p class="submittop"><input type="submit" name="save" value="<?= __('Save changes') ?>" /></p>
            <div class="inform">
                <input type="hidden" name="form_sent" value="1" />
                <fieldset>
                    <?php
                    foreach ($groups as $cur_group) {
                        ?>
                        <legend><?= Utils::escape($cur_group['g_title']) ?></legend>
                        <div class="infldset">
                            <table class="aligntop">
                                <tr>
                                    <th scope="row">Use private messaging</th>
                                    <td>
                                        <label class="conl"><input type="radio" name="use_pm_g<?= Utils::escape($cur_group['g_id']) ?>" value="1"
                                        <?php if ($cur_group['g_use_pm'] == 1):  echo ' checked="checked"'; endif ?>
                                                                   tabindex="39"/>&#160;<strong>Yes</strong></label>
                                        <label class="conl"><input type="radio" name="use_pm_g<?= Utils::escape($cur_group['g_id']) ?>" value="0" tabindex="40"
                                        <?php if ($cur_group['g_use_pm'] == 0):  echo ' checked="checked"'; endif ?>/>&#160;<strong>No</strong></label>
                                        <span
                                            class="clearb">Allow users in this group access to private messaging.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">Private message limit</th>
                                    <td>
                                        <input type="text" name="pm_limit_g<?= Utils::escape($cur_group['g_id']) ?>" size="5" maxlength="4"
                                               value="<?= Utils::escape($cur_group['g_pm_limit']) ?>"
                                               tabindex="42"/>

                                        <span class="clearb">The maximum number of messages users in this group may have in their inbox at any one time. Set to 0 for unlimited messages.</span>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">PM Folder limit</th>
                                    <td>
                                        <input type="text" name="pm_folder_limit_g<?= Utils::escape($cur_group['g_id']) ?>" size="5" maxlength="4"
                                               value="<?= Utils::escape($cur_group['g_pm_folder_limit']) ?>"
                                               tabindex="42"/>

                                        <span class="clearb">The maximum number of folders a user in this group may have for their private messages. Set to 0 for unlimited.</span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <?php
                    }
                    ?>
                </fieldset>
            </div>
            <?php Container::get('hooks')->fire('view.admin.plugin.private-messages.form'); ?>
            <p class="submitend"><input type="submit" name="save" value="<?= __('Save changes') ?>" /></p>
        </form>
    </div>
</div>
<div class="clearer"></div>
</div>
