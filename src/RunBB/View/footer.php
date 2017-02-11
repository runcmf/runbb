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

Container::get('hooks')->fire('view.footer.start');
?>
        </div>

        <div id="brdfooter" class="blockform">
            <h2><span><?= __('Board footer') ?></span></h2>
            <div class="box">
<?php

if (isset($active_page) && ($active_page == 'Forum' || $active_page == 'Topic') && User::get()->is_admmod) {
    echo "\t\t".'<div id="modcontrols" class="inbox">'."\n";

    if ($active_page == 'Forum') {
        echo "\t\t\t".'<dl>'."\n";
        echo "\t\t\t\t".'<dt><strong>'.__('Mod controls').'</strong></dt>'."\n";
        echo "\t\t\t\t".'<dd><span><a href="'.Router::pathFor('moderateForum', ['fid' => $fid, 'page' => $page_number]).'">'.__('Moderate forum').'</a></span></dd>'."\n";
        echo "\t\t\t".'</dl>'."\n";
    } elseif ($active_page == 'Topic') {
        if (isset($pid)) {
            $parameter = $pid;
        } elseif (isset($page_number) && $page_number != 1) {
            $parameter = $page_number;
        } else {
            $parameter = '';
        }


        echo "\t\t\t".'<dl>'."\n";
        echo "\t\t\t\t".'<dt><strong>'.__('Mod controls').'</strong></dt>'."\n";

        echo "\t\t\t\t".'<dd><span><a href="'.Router::pathFor('moderateTopic', ['id' => $tid, 'fid' => $fid, 'page' => $page_number]).'">'.__('Moderate topic').'</a></span></dd>'."\n";
        echo "\t\t\t\t".'<dd><span><a href="'.Router::pathFor('moveTopic', ['id' => $tid, 'fid' => $fid, 'name' => Url::slug($cur_topic['subject'])]).'">'.__('Move topic').'</a></span></dd>'."\n";

        if ($cur_topic['closed'] == '1') {
            echo "\t\t\t\t".'<dd><span><a href="'.Router::pathFor('openTopic', ['id' => $tid, 'name' => Url::slug($cur_topic['subject'])]).'">'.__('Open topic').'</a></span></dd>'."\n";
        } else {
            echo "\t\t\t\t".'<dd><span><a href="'.Router::pathFor('closeTopic', ['id' => $tid, 'name' => Url::slug($cur_topic['subject'])]).'">'.__('Close topic').'</a></span></dd>'."\n";
        }

        if ($cur_topic['sticky'] == '1') {
            echo "\t\t\t\t".'<dd><span><a href="'.Router::pathFor('unstickTopic', ['id' => $tid, 'name' => Url::slug($cur_topic['subject'])]).'">'.__('Unstick topic').'</a></span></dd>'."\n";
        } else {
            echo "\t\t\t\t".'<dd><span><a href="'.Router::pathFor('stickTopic', ['id' => $tid, 'name' => Url::slug($cur_topic['subject'])]).'">'.__('Stick topic').'</a></span></dd>'."\n";
        }

        echo "\t\t\t".'</dl>'."\n";
    }

    Container::get('hooks')->fire('view.footer.mod.actions');

    echo "\t\t\t".'<div class="clearer"></div>'."\n\t\t".'</div>'."\n";
}

?>
                <div id="brdfooternav" class="inbox">
<?php
// Display the "Jump to" drop list
if (ForumSettings::get('o_quickjump') == '1' && !empty($quickjump)) { ?>
                    <div class="conl">
                        <form class="form-horizontal" id="qjump" method="get" action="">
                            <div class="form-group">
                                <label class="control-label col-sm-4" for="id"><?= __('Jump to') ?></label>
                                <div class="col-sm-8">
                                <select name="id" class="form-control" onchange="window.location=(this.options[this.selectedIndex].value)">
<?php
foreach ($quickjump[(int) User::get()->g_id] as $cat_id => $cat_data) {
    echo "\t\t\t\t\t\t\t\t\t".'<optgroup label="'.Utils::escape($cat_data['cat_name']).'">'."\n";
    foreach ($cat_data['cat_forums'] as $forum) {
        echo "\t\t\t\t\t\t\t\t\t\t".'<option value="'.Router::pathFor('Forum', ['id' => $forum['forum_id'], 'name' => Url::slug($forum['forum_name'])]).'"'.($fid == 2 ? ' selected="selected"' : '').'>'.$forum['forum_name'].'</option>'."\n";
    }
    echo "\t\t\t\t\t\t\t\t\t".'</optgroup>'."\n";
}
?>
                                </select>
                                </div>
                            </div>
                        </form>
                    </div>
<?php } ?>
                    <div class="conr">
<?php

if ($active_page == 'index') {
    if (ForumSettings::get('o_feed_type') == '1') {
        echo "\t\t\t".'<p id="feedlinks"><span class="rss"><a href="'.Router::pathFor('extern').'?action=feed&amp;type=rss">'.__('RSS active topics feed').'</a></span></p>'."\n";
    } elseif (ForumSettings::get('o_feed_type') == '2') {
        echo "\t\t\t".'<p id="feedlinks"><span class="atom"><a href="'.Router::pathFor('extern').'?action=feed&amp;type=atom">'.__('Atom active topics feed').'</a></span></p>'."\n";
    }
} elseif ($active_page == 'Forum') {
    if (ForumSettings::get('o_feed_type') == '1') {
        echo "\t\t\t".'<p id="feedlinks"><span class="rss"><a href="'.Router::pathFor('extern').'?action=feed&amp;fid='.$fid.'&amp;type=rss">'.__('RSS forum feed').'</a></span></p>'."\n";
    } elseif (ForumSettings::get('o_feed_type') == '2') {
        echo "\t\t\t".'<p id="feedlinks"><span class="atom"><a href="'.Router::pathFor('extern').'?action=feed&amp;fid='.$fid.'&amp;type=atom">'.__('Atom forum feed').'</a></span></p>'."\n";
    }
} elseif ($active_page == 'Topic') {
    if (ForumSettings::get('o_feed_type') == '1') {
        echo "\t\t\t".'<p id="feedlinks"><span class="rss"><a href="'.Router::pathFor('extern').'?action=feed&amp;tid='.$tid.'&amp;type=rss">'.__('RSS topic feed').'</a></span></p>'."\n";
    } elseif (ForumSettings::get('o_feed_type') == '2') {
        echo "\t\t\t".'<p id="feedlinks"><span class="atom"><a href="'.Router::pathFor('extern').'?action=feed&amp;tid='.$tid.'&amp;type=atom">'.__('Atom topic feed').'</a></span></p>'."\n";
    }
}

Container::get('hooks')->fire('view.footer.feed.links');

?>
                        <p id="poweredby">
                            <?php printf(__('Powered by'), '<a href="https://github.com/runcmf/runbb">RunBB</a>'.((ForumSettings::get('o_show_version') == '1') ? ' '.ForumSettings::get('o_cur_version') : '')) ?>
                            <br />Based on <a href="https://github.com/featherbb/featherbb">FeatherBB</a>
                        </p>
                    </div>
                <div class="clearer"></div>
            </div>
        </div>
    </div>
<?php

// Display debug info (if enabled/defined)
if (!empty($exec_info)) { ?>
    <p id="debugtime">[ <?= __('Querytime', round($exec_info['exec_time'], 6)).' - '.__('Memory usage', $exec_info['mem_usage']).' '.__('Peak usage', $exec_info['mem_peak_usage'])?> ]</p>
<?php }
if (!empty($queries_info)) { ?>
    <div id="debug" class="blocktable">
        <h2><span><?= __('Debug table') ?></span></h2>
        <div class="box">
                <div class="inbox">
                <table>
                    <thead>
                        <tr>
                            <th class="tcl" scope="col"><?= __('Query times') ?></th>
                            <th class="tcr" scope="col"><?= __('Query') ?></th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($queries_info['raw'] as $time => $sql) {
    echo "\t\t\t\t\t\t".'<tr>'."\n";
    echo "\t\t\t\t\t\t\t".'<td class="tcl">'.Utils::escape(round($time, 8)).'</td>'."\n";
    echo "\t\t\t\t\t\t\t".'<td class="tcr">'.Utils::escape($sql).'</td>'."\n";
    echo "\t\t\t\t\t\t".'</tr>'."\n";
} ?>
                        <tr>
                            <td class="tcl" colspan="2"><?= __('Total query time') .' '.round($queries_info['total_time'], 7). ' s' ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>
</section>

<!-- JS -->
<?php foreach ($assets['js'] as $script) {
    echo '<script ';
    foreach ($script['params'] as $key => $value) {
        echo $key.'="'.$value.'" ';
    }
    echo 'src="'.Url::baseStatic().'/'.$script['file'].'"/></script>'."\n";
} ?>
<!-- JSRAW -->
<script>
    var baseUrl = '<?= Utils::escape(Url::base()); ?>',
        phpVars = <?= isset($jsVars) ? json_encode($jsVars) : json_encode([]); ?>;
    <?= isset($jsraw) ? $jsraw : ''; ?>;
</script>
<?php Container::get('hooks')->fire('view.footer.before.html.tag'); ?>
</body>
</html>
<?php
Container::get('hooks')->fire('view.footer.end');

//tdie(Container::get('twig')->isDebug());
