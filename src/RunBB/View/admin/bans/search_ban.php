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

Container::get('hooks')->fire('view.admin.bans.search.start');
?>

<div class="linkst">
    <div class="inbox crumbsplus">
        <ul class="crumbs">
            <li><a href="<?= Router::pathFor('adminIndex') ?>"><?= __('Admin') .' '. __('Index') ?></a></li>
            <li><span>»&#160;</span><a href="<?= Router::pathFor('addBan') ?>"><?= __('Bans') ?></a></li>
            <li><span>»&#160;</span><strong><?= __('Results head') ?></strong></li>
        </ul>
        <div class="pagepost">
            <p class="pagelink"><?= $paging_links ?></p>
        </div>
        <div class="clearer"></div>
    </div>
</div>


<div id="bans1" class="blocktable">
    <h2><span><?= __('Results head') ?></span></h2>
    <div class="box">
        <div class="inbox">
            <table>
            <thead>
                <tr>
                    <th class="tcl" scope="col"><?= __('Results username head') ?></th>
                    <th class="tc2" scope="col"><?= __('Results e-mail head') ?></th>
                    <th class="tc3" scope="col"><?= __('Results IP address head') ?></th>
                    <th class="tc4" scope="col"><?= __('Results expire head') ?></th>
                    <th class="tc5" scope="col"><?= __('Results message head') ?></th>
                    <th class="tc6" scope="col"><?= __('Results banned by head') ?></th>
                    <th class="tcr" scope="col"><?= __('Results actions head') ?></th>
                </tr>
            </thead>
            <tbody>
<?php

foreach ($ban_data as $cur_ban) {
    ?>
<tr>
    <td class="tcl"><?= ($cur_ban['username'] != '') ? Utils::escape($cur_ban['username']) : '&#160;' ?></td>
    <td class="tc2"><?= ($cur_ban['email'] != '') ? Utils::escape($cur_ban['email']) : '&#160;' ?></td>
    <td class="tc3"><?= ($cur_ban['ip'] != '') ? Utils::escape($cur_ban['ip']) : '&#160;' ?></td>
    <td class="tc4"><?= Utils::timeFormat($cur_ban['expire'], true) ?></td>
    <td class="tc5"><?= ($cur_ban['message'] != '') ? Utils::escape($cur_ban['message']) : '&#160;' ?></td>
    <td class="tc6"><?= ($cur_ban['ban_creator_username'] != '') ? '<a href="'.Router::pathFor('userProfile', ['id' => $cur_ban['ban_creator']]).'">'.Utils::escape($cur_ban['ban_creator_username']).'</a>' : __('Unknown') ?></td>
    <td class="tcr"><?= '<a href="'.Router::pathFor('editBan', ['id' => $cur_ban['id']]).'">'.__('Edit').'</a> | <a href="'.Router::pathFor('deleteBan', ['id' => $cur_ban['id']]).'">'.__('Remove').'</a>' ?></td>
</tr>
<?php
}
if (empty($ban_data)) {
    echo "\t\t\t\t".'<tr><td class="tcl" colspan="7">'.__('No match').'</td></tr>'."\n";
}

?>
            </tbody>
            </table>
        </div>
    </div>
</div>

<div class="linksb">
    <div class="inbox crumbsplus">
        <div class="pagepost">
            <p class="pagelink"><?= $paging_links ?></p>
        </div>
        <ul class="crumbs">
            <li><a href="<?= Router::pathFor('adminIndex') ?>"><?= __('Admin') .' '. __('Index') ?></a></li>
            <li><span>»&#160;</span><a href="<?= Router::pathFor('adminBans') ?>"><?= __('Bans') ?></a></li>
            <li><span>»&#160;</span><strong><?= __('Results head') ?></strong></li>
        </ul>
        <div class="clearer"></div>
    </div>
</div>

<?php
Container::get('hooks')->fire('view.admin.bans.search.end');
