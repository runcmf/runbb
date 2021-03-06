<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

use RunBB\Core\Utils;

// Make sure no one attempts to run this script "directly"
if (!defined('INBB')) {
    exit;
}

Container::get('hooks')->fire('view.index.start');

if (empty($index_data)) : ?>
    <div id="idx0" class="block"><div class="box"><div class="inbox"><p><?= __('Empty board') ?></p></div></div></div>
<?php endif;
foreach ($index_data as $forum) {
    ?>
    <div id="idx<?= $forum[0]->cid ?>" class="blocktable">
    <h2><span><?= Utils::escape($forum[0]->cat_name) ?></span></h2>
    <div class="box">
        <div class="inbox">
            <table>
            <thead>
                <tr>
                    <th class="tcl" scope="col"><?= __('Forum') ?></th>
                    <th class="tc2" scope="col"><?= __('Topics') ?></th>
                    <th class="tc3" scope="col"><?= __('Posts') ?></th>
                    <th class="tcr" scope="col"><?= __('Last post') ?></th>
                </tr>
            </thead>
            <tbody>
    <?php
        foreach ($forum as $cat) {
            ?>
            <tr class="<?= $cat->item_status ?>">
                <td class="tcl">
                    <div class="<?= $cat->icon_type ?>">
                        <div class="nosize"><?= Utils::numberFormat($cat->forum_count_formatted) ?></div>
                    </div>
                    <div class="tclcon">
                        <div>
                            <?= $cat->forum_field . "\n" . $cat->moderators_formatted ?>
                        </div>
                    </div>
                </td>
                <td class="tc2"><?= Utils::numberFormat($cat->num_topics_formatted) ?></td>
                <td class="tc3"><?= Utils::numberFormat($cat->num_posts_formatted) ?></td>
                <td class="tcr"><?= $cat->last_post_formatted ?></td>
            </tr>
            <?php
        }
}
    ?>
                    </tbody>
            </table>
        </div>
    </div>
    </div>
<?php

if (!empty($forum_actions)) :
?>
<div class="linksb">
    <div class="inbox crumbsplus">
        <p class="subscribelink clearb"><?= implode(' - ', $forum_actions); ?></p>
    </div>
</div>
<?php
endif;
?>
<div id="brdstats" class="block">
    <h2><span><?= __('Board info') ?></span></h2>
    <div class="box">
        <div class="inbox">
            <dl class="conr">
                <dt><strong><?= __('Board stats') ?></strong></dt>
                <dd><span><?php printf(__('No of users'), '<strong>'.Utils::numberFormat($stats['total_users']).'</strong>') ?></span></dd>
                <dd><span><?php printf(__('No of topics'), '<strong>'.Utils::numberFormat($stats['total_topics']).'</strong>') ?></span></dd>
                <dd><span><?php printf(__('No of posts'), '<strong>'.Utils::numberFormat($stats['total_posts']).'</strong>') ?></span></dd>
            </dl>
            <dl class="conl">
                <dt><strong><?= __('User info') ?></strong></dt>
                <dd><span><?php printf(__('Newest user'), $stats['newest_user']) ?></span></dd>
                <?php if (ForumSettings::get('o_users_online') == 1) : ?>
                <dd><span><?php printf(__('Users online'), '<strong>'.Utils::numberFormat($online['num_users']).'</strong>') ?></span></dd>
                <dd><span><?php printf(__('Guests online'), '<strong>'.Utils::numberFormat($online['num_guests']).'</strong>') ?></span></dd>
                <?php endif; ?>
            </dl>
            <?php
            if (ForumSettings::get('o_users_online') == 1) :
                if ($online['num_users'] > 0) {
                    echo "\t\t\t".'<dl id="onlinelist" class="clearb">'."\n\t\t\t\t".'<dt><strong>'.__('Online').' </strong></dt>'."\t\t\t\t".implode(',</dd> ', $online['users']).'</dd>'."\n\t\t\t".'</dl>'."\n";
                } else {
                    echo "\t\t\t".'<div class="clearer"></div>'."\n";
                }
            endif;

            Container::get('hooks')->fire('view.index.brdstats');
            ?>
        </div>
    </div>
</div>
<?php
Container::get('hooks')->fire('view.index.end');
