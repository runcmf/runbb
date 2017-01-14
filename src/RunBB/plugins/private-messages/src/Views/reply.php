<?php

/**
* Copyright (C) 2015-2016 FeatherBB
* based on code by (C) 2008-2015 FluxBB
* and Rickard Andersson (C) 2002-2008 PunBB
* License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher.
*/
use RunBB\Core\Utils;

// Make sure no one attempts to run this script "directly"
if (!isset($feather)) {
    exit;
}
?>

        <div id="postform" class="blockform">
            <h2><span><?php _e('Reply', 'private_messages') ?><?= (isset($conv['subject']) ? ' "'.$conv['subject'].'"' : '')?></span></h2>
            <div class="box">
                <form id="post" method="post" action="" onsubmit="return process_form(this)">
                    <div class="inform">
                        <fieldset>
                            <legend><?= __('Write message legend') ?></legend>
                            <div class="infldset txtarea">
                                <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
                                <label class="required"><strong><?php _e('Message') ?> <span><?= __('Required') ?></span></strong><br /></label>
                                <textarea name="req_message" id="req_message" rows="20" cols="95" tabindex="2" required autofocus><?= (isset($message) ? $message : '')?></textarea><br />
                                <ul class="bblinks">
                                    <li><span><a href="<?= Router::pathFor('help').'#bbcode' ?>" onclick="window.open(this.href); return false;"><?php _e('BBCode') ?>ok</a> <?php echo(ForumSettings::get('p_message_bbcode') == '1') ? __('on') : __('off'); ?></span></li>
                                    <li><span><a href="<?= Router::pathFor('help').'#url' ?>" onclick="window.open (this.href); return false;"><?php _e('url tag') ?></a> <?php echo(ForumSettings::get('p_message_bbcode') == '1' && User::get()->g_post_links == '1') ? __('on') : __('off'); ?></span></li>
                                    <li><span><a href="<?= Router::pathFor('help').'#img' ?>" onclick="window.open(this.href); return false;"><?php _e('img tag') ?></a> <?php echo(ForumSettings::get('p_message_bbcode') == '1' && ForumSettings::get('p_message_img_tag') == '1') ? __('on') : __('off'); ?></span></li>
                                    <li><span><a href="<?= Router::pathFor('help').'#smilies' ?>" onclick="window.open(this.href); return false;"><?php _e('Smilies') ?></a> <?php echo(ForumSettings::get('o_smilies') == '1') ? __('on') : __('off'); ?></span></li>
                                </ul>
                            </div>
                        </fieldset>
                    </div>
                    <div class="inform">
                        <fieldset>
                            <legend><?php _e('Options') ?></legend>
                            <div class="infldset">
                                <div class="rbox">
                                    <label><input type="checkbox" name="smilies" value="1" tabindex="3" /><?= __('Hide smilies') ?><br /></label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <p class="buttons"><input type="submit" name="submit" value="<?php _e('Submit') ?>" tabindex="4" accesskey="s" /> <input type="submit" name="preview" value="<?php _e('Preview') ?>" tabindex="5" accesskey="p" /> <a href="javascript:history.go(-1)"><?php _e('Go back') ?></a></p>
                </form>
            </div>
        </div>

<?php if (!empty($msg_data)): ?>
        <div id="postreview">
            <h2><span><?php _e('Conv review', 'private_messages') ?></span></h2>
<?php
    $count = 1;
    foreach ($msg_data as $msg): ?>

                <div id="p<?=$msg['id']?>" class="blockpost<?= ($count % 2 == 0) ? ' roweven' : ' rowodd' ?>">
                    <div class="box roweven">
                        <div class="inbox">
                            <div class="postbody">
                                <div class="postleft">
                                    <dl>
                                        <dt><strong><?= $msg['poster']?></strong></dt>
                                        <dd><span><?= Utils::format_time($msg['sent'])?></span></dd>
                                    </dl>
                                </div>
                                <div class="postright">
                                    <div class="postmsg">
                                        <p><?= Utils::escape($msg['message']) ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="clearer"></div>
                        </div>
                    </div>
                </div>
        <?php ++$count; endforeach; ?>

        </div>
        <?php endif; ?>
