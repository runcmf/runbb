<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

use RunBB\Core\Url;
use RunBB\Core\Utils;

// Make sure no one attempts to run this script "directly"
if (!isset($feather)) {
    exit;
}

Container::get('hooks')->fire('view.post.start');
?>

<div class="linkst">
    <div class="inbox">
        <ul class="crumbs">
            <li><a href="<?= Url::base() ?>"><?= __('Index') ?></a></li>
            <li><span>»&#160;</span><a href="<?= Router::pathFor('Forum', ['id' => $cur_posting['id'], 'name' => $url_forum]) ?>"><?= Utils::escape($cur_posting['forum_name']) ?></a></li>
<?php if (Input::post('req_subject')) :
?>            <li><span>»&#160;</span><?= Utils::escape(Input::post('req_subject')) ?></li>
<?php endif; ?>
<?php if (isset($cur_posting['subject'])) :
?>            <li><span>»&#160;</span><a href="<?= Router::pathFor('Topic', ['id' => $tid, 'name' => $url_topic]) ?>"><?= Utils::escape($cur_posting['subject']) ?></a></li>
<?php endif; ?>            <li><span>»&#160;</span><strong><?= $action ?></strong></li>
        </ul>
    </div>
</div>

<?php

// If there are errors, we display them
if (!empty($errors)) {
    ?>
<div id="posterror" class="block">
    <h2><span><?= __('Post errors') ?></span></h2>
    <div class="box">
        <div class="inbox error-info">
            <p><?= __('Post errors info') ?></p>
            <ul class="error-list">
<?php

foreach ($errors as $cur_error) {
    echo "\t\t\t\t".'<li><strong>'.$cur_error.'</strong></li>'."\n";
}
    ?>
            </ul>
        </div>
    </div>
</div>

<?php
} elseif (Input::post('preview')) {
    $preview_message = Container::get('parser')->parse_message($post['message'], $post['hide_smilies']);
?>
<div id="postpreview" class="blockpost">
    <h2><span><?= __('Post preview') ?></span></h2>
    <div class="box">
        <div class="inbox">
            <div class="postbody">
                <div class="postright">
                    <div class="postmsg">
                        <?= $preview_message."\n" ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
}

$cur_index = 1;
?>
<div id="postform" class="blockform">
    <h2><span><?= $action ?></span></h2>
    <div class="box">
        <?= $form."\n" ?>
            <div class="inform">
                <fieldset>
                    <legend><?= __('Write message legend') ?></legend>
                    <div class="infldset txtarea">
                        <input type="hidden" name="form_sent" value="1" />
                                                <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>"><input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
<?php
if (User::get()->is_guest) {
    $email_label = (ForumSettings::get('p_force_guest_email') == '1') ? '<strong>'.__('Email').' <span>'.__('Required').'</span></strong>' : __('Email');
    $email_form_name = (ForumSettings::get('p_force_guest_email') == '1') ? 'req_email' : 'email';
    ?>
                        <label class="conl required"><strong><?= __('Guest name') ?> <span><?= __('Required') ?></span></strong><br /><input type="text" name="req_username" value="<?php if (Input::post('req_username')) {
                            echo Utils::escape($post['username']);
}
    ?>" size="25" maxlength="25" tabindex="<?= $cur_index++ ?>" /><br /></label>
                        <label class="conl<?php echo(ForumSettings::get('p_force_guest_email') == '1') ? ' required' : '' ?>"><?= $email_label ?><br /><input type="text" name="<?= $email_form_name ?>" value="<?php if (Input::post($email_form_name)) {
                            echo Utils::escape($post['email']);
}
    ?>" size="50" maxlength="80" tabindex="<?= $cur_index++ ?>" /><br /></label>
                        <div class="clearer"></div>
<?php
}
if ($fid) : ?>
                        <label class="required"><strong><?= __('Subject') ?> <span><?= __('Required') ?></span></strong><br /><input class="longinput" type="text" name="req_subject" value="<?php if (Input::post('req_subject')) {
                            echo Utils::escape($post['subject']);
} ?>" size="80" maxlength="70" tabindex="<?= $cur_index++ ?>" /><br /></label>
<?php endif; ?>                        <label class="required"><strong><?= __('Message') ?> <span><?= __('Required') ?></span></strong><br />
                        <textarea name="req_message" id="req_message" rows="20" tabindex="<?= $cur_index++ ?>"><?php echo(Input::post('req_message')) ? Utils::linebreaks(Utils::trim(Utils::escape(Input::post('req_message')))) : (isset($quote) ? $quote : ''); ?></textarea><br /></label>
                        <ul class="bblinks">
                            <li><span><a href="<?= Router::pathFor('help').'#bbcode' ?>" onclick="window.open(this.href); return false;"><?= __('BBCode') ?>ok</a> <?php echo(ForumSettings::get('p_message_bbcode') == '1') ? __('on') : __('off'); ?></span></li>
                            <li><span><a href="<?= Router::pathFor('help').'#url' ?>" onclick="window.open (this.href); return false;"><?= __('url tag') ?></a> <?php echo(ForumSettings::get('p_message_bbcode') == '1' && User::get()->g_post_links == '1') ? __('on') : __('off'); ?></span></li>
                            <li><span><a href="<?= Router::pathFor('help').'#img' ?>" onclick="window.open(this.href); return false;"><?= __('img tag') ?></a> <?php echo(ForumSettings::get('p_message_bbcode') == '1' && ForumSettings::get('p_message_img_tag') == '1') ? __('on') : __('off'); ?></span></li>
                            <li><span><a href="<?= Router::pathFor('help').'#smilies' ?>" onclick="window.open(this.href); return false;"><?= __('Smilies') ?></a> <?php echo(ForumSettings::get('o_smilies') == '1') ? __('on') : __('off'); ?></span></li>
                        </ul>
                    </div>
                </fieldset>
<?php
if (!empty($checkboxes)) {
    ?>
            </div>
            <div class="inform">
                <fieldset>
                    <legend><?= __('Options') ?></legend>
                    <div class="infldset">
                        <div class="rbox">
                            <?= implode("\n\t\t\t\t\t\t\t", $checkboxes)."\n" ?>
                        </div>
                    </div>
                </fieldset>
<?php
}
?>
            </div>
            <?php if (User::get()->is_guest) : ?>
            <div class="inform">
                <fieldset>
                    <legend><?= __('Robot title') ?></legend>
                    <div class="infldset">
                        <p><?= __('Robot info')    ?></p>
                        <label class="required"><strong><?php
                             $question = array_keys($lang_antispam_questions);
                             $qencoded = md5($question[$index_questions]);
                             echo sprintf(__('Robot question'), $question[$index_questions]);?>
                             <span><?= __('Required') ?></span></strong>
                             <br />
                             <input    name="captcha" id="captcha"    type="text"    size="10" maxlength="30" /><input name="captcha_q" value="<?= $qencoded ?>" type="hidden" /><br />
                        </label>
                    </div>
                </fieldset>
            </div>
            <?php endif; ?>
            <p class="buttons">
                <input type="submit" name="submit" value="<?= __('Submit') ?>" tabindex="<?= $cur_index++ ?>" accesskey="s" />
                <!--<input type="submit" name="preview" value="<?= __('Preview') ?>" tabindex="<?= $cur_index++ ?>" accesskey="p" />-->
                <a href="javascript:history.go(-1)"><?= __('Go back') ?></a>
            </p>
        </form>
    </div>
</div>


<?php
if ($tid && ForumSettings::get('o_topic_review') != '0') :
?>
<div id="postreview">
    <h2><span><?= __('Topic review') ?></span></h2>

    <?php
    // Set background switching on
    $post_count = 0;

    foreach ($post_data as $post) {
        ++$post_count;
        ?>
    <div class="blockpost">
    <div class="box<?php echo($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?>">
        <div class="inbox">
            <div class="postbody">
                <div class="postleft">
                    <dl>
                        <dt><strong><?= Utils::escape($post['poster']) ?></strong></dt>
                        <dd><span><?= Utils::format_time($post['posted']) ?></span></dd>
                    </dl>
                </div>
                <div class="postright">
                    <div class="postmsg">
                        <?= $post['message']."\n" ?>
                    </div>
                </div>
            </div>
            <div class="clearer"></div>
        </div>
    </div>
</div>
    <?php
    }
    ?>

</div>
<?php endif;
Container::get('hooks')->fire('view.post.end');