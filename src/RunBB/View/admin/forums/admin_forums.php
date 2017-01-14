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

Container::get('hooks')->fire('view.admin.forums.admin_forums.start');
?>

    <div class="blockform">
        <h2><span><?php _e('Add forum head') ?></span></h2>
        <div class="box">
            <form method="post" action="<?= Router::pathFor('addForum') ?>">
                <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
<?php
if (!empty($cat_list)) {
    ?>
                <div class="inform">
                    <fieldset>
                        <legend><?php _e('Create new subhead') ?></legend>
                        <div class="infldset">
                            <table class="aligntop">
                                <tr>
                                    <th scope="row"><?php _e('Add forum label') ?><div><input type="submit" value="<?php _e('Add forum') ?>" tabindex="2" /></div></th>
                                    <td>
                                        <select name="cat" tabindex="1">
                                            <?php  foreach ($cat_list as $cat) {
                                                echo "\t\t\t\t\t\t\t\t\t\t\t".'<option value="'.$cat['id'].'">'.Utils::escape($cat['cat_name']).'</option>'."\n";
                                            } ?>
                                        </select>
                                        <span><?php _e('Add forum help') ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </fieldset>
                </div>
<?php

} else {
    ?>
                <div class="inform">
                    <fieldset>
                        <legend><?php _e('None') ?></legend>
                        <div class="infldset">
                            <p><?php _e('No categories exist') ?></p>
                        </div>
                    </fieldset>
                </div>
<?php

}

?>
            </form>
        </div>
<?php
if (!empty($forum_data)) {
    ?>
        <h2 class="block2"><span><?php _e('Manage forums head') ?></span></h2>
        <div class="box">
            <form id="edforum" method="post" action="<?= Router::pathFor('adminForums') ?>">
                <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
                <p class="submittop"><input type="submit" name="update_positions" value="<?php _e('Update positions') ?>" tabindex="3" /></p>
<?php
    foreach ($forum_data as $cat_id => $cat_data) {
            ?>
                <div class="inform">
                    <fieldset>
                        <legend><?php _e('Category subhead') ?> <?= Utils::escape($cat_data['cat_name']) ?></legend>
                        <div class="infldset">
                            <table>
                            <thead>
                                <tr>
                                    <th class="tcl"><?php _e('Action') ?></th>
                                    <th class="tc2"><?php _e('Position label') ?></th>
                                    <th class="tcr"><?php _e('Forum label') ?></th>
                                </tr>
                            </thead>
                            <tbody>
<?php
    foreach ($cat_data['cat_forums'] as $forum) {
        ?>
                                <tr>
                                    <td class="tcl"><a href="<?= Router::pathFor('editForum', ['id' => $forum['forum_id']]) ?>" tabindex="<?= $cur_index++ ?>"><?php _e('Edit link') ?></a> | <a href="<?= Router::pathFor('deleteForum', ['id' => $forum['forum_id']]) ?>" tabindex="<?= $cur_index++ ?>"><?php _e('Delete link') ?></a></td>
                                    <td class="tc2"><input type="text" name="position[<?= $forum['forum_id'] ?>]" size="3" maxlength="3" value="<?= $forum['position'] ?>" tabindex="<?= $cur_index++ ?>" /></td>
                                    <td class="tcr"><strong><?= Utils::escape($forum['forum_name']) ?></strong></td>
                                </tr>
<?php
    }
    ?>
                            </tbody>
                            </table>
                        </div>
                    </fieldset>
                </div>
<?php
    }
?>
                <p class="submitend"><input type="submit" name="update_positions" value="<?php _e('Update positions') ?>" tabindex="<?= $cur_index++ ?>" /></p>
            </form>
        </div>
    </div>
    <div class="clearer"></div>
</div>
<?php
    }

Container::get('hooks')->fire('view.admin.forums.admin_forums.end');
