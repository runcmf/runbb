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

        <div class="block">
            <?php
                $message_count = 1;
                foreach ($messages as $message) {
            ?>
            <div id="p<?= $message['id'] ?>" class="blockpost<?= ($message_count % 2 == 0) ? ' roweven' : ' rowodd' ?><?= ($message['id'] == $cur_conv['first_post_id']) ? ' firstpost' : ''; ?><?= ($message_count == 1) ? ' blockpost1' : ''; ?>">
                <h2><span class="conr">#<?= ($start_from + $message_count) ?></span> <a href="<?= Router::pathFor('viewPost', ['pid' => $message['id']]).'#p'.$message['id'] ?>"><?= Utils::format_time($message['sent']) ?></a></h2>
                <div class="box">
                    <div class="inbox">
                        <div class="postbody">
                            <div class="postleft">
                                <dl>
                                    <dt><strong><a href="<?= Router::pathFor('userProfile', ['id' => $message['poster_id']]) ?>"><span><?= Utils::escape($message['username'])?></span></a></strong></dt>
                                    <dd class="usertitle"><strong><?= Utils::get_title($message) ?></strong></dd>
                                </dl>
                            </div>
                            <div class="postright">
                                <h3><?php if ($message['id'] != $cur_conv['first_post_id']) { _e('Re').' '; } ?>
                                    <?= Utils::escape($cur_conv['subject']) ?>
                                </h3>
                                <div class="postmsg">
                                    <p>
                                        <?= Utils::escape($message['message'])."\n" ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="inbox">
                        <div class="postfoot clearb">
                            <div class="postfootleft">
                                <?php if ($message['poster_id'] > 1) {
                                    echo '<p>'.($message['is_online'] == $message['poster_id']) ? '<strong>'.__('Online').'</strong>' : ('<span>'.__('Offline').'</span>').'</p>';
                                } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
                ++$message_count;
            }
            ?>
        </div>
        <div class="clearer"></div>
