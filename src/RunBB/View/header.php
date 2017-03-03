<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

use RunBB\Core\Random;
use RunBB\Core\Url;
use RunBB\Core\Utils;

// Make sure no one attempts to run this script "directly"
if (!defined('INBB')) {
    exit;
}

Container::get('hooks')->fire('view.header.start');
?>
<!doctype html>
<html lang="<?= __('lang_identifier') ?>">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1">
<?php if ($is_indexed) { ?>
    <meta name="robots" content="noindex, follow">
<?php } ?>
    <title><?= Utils::generatePageTitle($title, $page_number) ?></title>
    <link rel="shortcut icon" href="<?= Url::baseStatic() ?>/assets/img/favicon.png" />
    <!-- Theme -->
    <link rel="stylesheet" type="text/css" href="<?= Url::baseStatic() ?>/themes/<?= View::get('style') ?>/style.css">
<?php

foreach ($assets as $type => $items) {
    if ($type == 'js') {
        continue;
    }
    echo "\t".'<!-- '.ucfirst($type).' -->'."\n";
    foreach ($items as $item) {
        $isJs = false;
        if (isset($item['params']['type']) && $item['params']['type'] === 'text/javascript') {
            $isJs = true;
        }
        if ($isJs) {
            echo "\t" . '<script ';
        } else {
            echo "\t" . '<link ';
        }
        foreach ($item['params'] as $key => $value) {
            echo $key.'="'.$value.'" ';
        }
        if ($isJs) {
            echo 'src="' . Url::baseStatic() . '/' . $item['file'] . '" /></script>' . "\n";
        } else {
            echo 'href="' . Url::baseStatic() . '/' . $item['file'] . '">' . "\n";
        }
    }
}
if ($admin_console) {
    if (file_exists(ForumEnv::get('WEB_ROOT').'themes/'.View::get('style').'/base_admin.css')) {
        echo "\t".'<link rel="stylesheet" type="text/css" href="'.Url::baseStatic().'/themes/'.View::get('style').'/base_admin.css" />'."\n";
    } else {
        echo "\t".'<link rel="stylesheet" type="text/css" href="'.Url::baseStatic().'/imports/base_admin.css" />'."\n";
    }
}
if (isset($required_fields)) :
    // Output JavaScript to validate form (make sure required fields are filled out)

    ?>

    <script type="text/javascript">
        /* <![CDATA[ */
        function process_form(the_form)
        {
            var required_fields = {
                <?php
                    // Output a JavaScript object with localised field names
                    $tpl_temp = count($required_fields);
                foreach ($required_fields as $elem_orig => $elem_trans) {
                    echo "\"".$elem_orig.'": "'.addslashes(str_replace('&#160;', ' ', $elem_trans));
                    if (--$tpl_temp) {
                        echo "\", ";
                    } else {
                        echo "\"\n\t\t\t\t};\n";
                    }
                }
                    ?>
            if (document.all || document.getElementById)
            {
                for (var i = 0; i < the_form.length; ++i)
                {
                    var elem = the_form.elements[i];
                    if (elem.name && required_fields[elem.name] && !elem.value && elem.type && (/^(?:text(?:area)?|password|file)$/i.test(elem.type)))
                    {
                        alert('"' + required_fields[elem.name] + '" <?= __('required field') ?>');
                        elem.focus();
                        return false;
                    }
                }
            }
            return true;
        }
        /* ]]> */
    </script>
    <?php
endif;
if (!empty($page_head)) :
    echo implode("\n", $page_head)."\n";
endif;

Container::get('hooks')->fire('view.header.before.head.tag');
//<body id="pun<?= $active_page ? >"< ? = ($focus_element ? ' onload="document.getElementById(\''.$focus_element[0].'\').elements[\''.$focus_element[1].'\'].focus();"' : '')? >>
?>
</head>

<body id="pun<?= $active_page ?>">
    <header>
        <nav>
            <div class="container">
                <div class="phone-menu" id="phone-button">
                    <a class="button-phone"></a>
                </div>
                <div id="phone">
                    <div id="brdmenu" class="inbox">
                        <ul>
<?php
//$hooksLinks = Container::get('hooks')->fire('view.header.navlinks', []);
foreach ($navlinks as $row) {
    if (is_array($row)) {
        echo "\t\t\t\t\t\t\t" . '<li id="' . $row['id'] . '"' . $row['active'] . '><a href="' . $row['href'] . '">' . $row['text'] . '</a></li>' . "\n";
    } else {
        echo "\t\t\t\t\t\t\t" . $row . "\n";
    }
}
?>
                        </ul>
                    </div>
                    <div class="navbar-right">
                        <form method="get" action="<?= Router::pathFor('search'); ?>" class="nav-search">
                            <input type="hidden" name="action" value="search">
                            <input type="text" name="keywords" size="20" maxlength="100" placeholder="<?= __('Search') ?>">
                        </form>
                    </div>
                </div>
            </div>
        </nav>

        <div class="container">
            <div class="container-title-status">
                <h1 class="title-site">
                    <a href="<?= Url::base() ?>/forum" title="" class="site-name">
                        <p><?= Utils::escape(ForumSettings::get('o_board_title')) ?></p>
                    </a>
                    <div id="brddesc"><?= htmlspecialchars_decode(ForumSettings::get('o_board_desc')) ?></div>
                </h1>
                <div class="status-avatar">
                    <div id="brdwelcome" class="inbox">
<?php
if (User::get()->is_guest) { ?>
                        <p class="conl"><?= __('Not logged in')?></p>
<?php } else {
    echo "\t\t\t".'<ul class="conl">';
    echo "\n\t\t\t\t".'<li><span>'.__('Logged in as').' <strong>'.Utils::escape(User::get()->username).'</strong></span></li>'."\n";
    echo "\t\t\t\t".'<li><span>'.__('Last visit') .' '. Container::get('utils')->timeFormat(User::get()->last_visit).'</span></li>'."\n";

    if (User::get()->is_admmod) {
        if (ForumSettings::get('o_report_method') == '0' || ForumSettings::get('o_report_method') == '2') {
            if ($has_reports) {
                echo "\t\t\t\t".'<li class="reportlink"><span><strong><a href="'.Router::pathFor('adminReports').'">'.__('New reports').'</a></strong></span></li>'."\n";
            }
        }
        if (ForumSettings::get('o_maintenance') == '1') {
            echo "\t\t\t\t".'<li class="maintenancelink"><span><strong><a href="'.Router::pathFor('adminMaintenance').'">'.__('Maintenance mode enabled').'</a></strong></span></li>'."\n";
        }
    }
    $headerToplist = Container::get('hooks')->fire('header.toplist', []);
    echo implode("\t\t\t\t", $headerToplist);
    echo "\t\t\t".'</ul>'."\n";
}

if (User::get()->g_read_board == '1' && User::get()->g_search == '1') {
    echo "\t\t\t".'<ul class="conr">'."\n";
    echo "\t\t\t\t".'<li><span>'.__('Topic searches').' ';
    if (!User::get()->is_guest) {
        echo '<a href="'.Router::pathFor('quickSearch', ['show' => 'replies']).'" title="'.__('Show posted topics').'">'.__('Posted topics').'</a> | ';
        echo '<a href="'.Router::pathFor('quickSearch', ['show' => 'new']).'" title="'.__('Show new posts').'">'.__('New posts header').'</a> | ';
    }
    echo '<a href="'.Router::pathFor('quickSearch', ['show' => 'recent']).'" title="'.__('Show active topics').'">'.__('Active topics').'</a> | ';
    echo '<a href="'.Router::pathFor('quickSearch', ['show' => 'unanswered']).'" title="'.__('Show unanswered topics').'">'.__('Unanswered topics').'</a>';
    echo '</li>'."\n";
    echo "\t\t\t".'</ul>'."\n";
}

Container::get('hooks')->fire('view.header.brdwelcome');
?>
                    <div class="clearer"></div>
                    </div>
                </div>
                <div class="clear"></div>
            </div>
<?php if (User::get()->g_read_board == '1' && ForumSettings::get('o_announcement') == '1') : ?>
            <div id="announce" class="block">
                <div class="hd"><h2><span><?= __('Announcement') ?></span></h2></div>
                <div class="box">
                    <div id="announce-block" class="inbox">
                        <div class="usercontent"><?= ForumSettings::get('o_announcement_message') ?></div>
                    </div>
                </div>
            </div>
<?php endif; ?>
<?php if (!empty(Container::get('flash')->getMessages())) : ?>
            <script type="text/javascript">
                window.onload = function() {
                    var flashMessage = document.getElementById('flashmsg');
                    flashMessage.className = 'flashmsg '+flashMessage.getAttribute('data-type')+' show';
                    setTimeout(function () {
                        flashMessage.className = 'flashmsg '+flashMessage.getAttribute('data-type');
                    }, 10000);
                    return false;
                }
            </script>
<?php foreach (Container::get('flash')->getMessages() as $type => $message) { ?>
            <div class="flashmsg info" data-type="<?= $type; ?>" id="flashmsg">
                <h2><?php __('Info') ?><span style="float:right;cursor:pointer" onclick="document.getElementById('flashmsg').className = 'flashmsg';">&times;</span></h2>
                <p><?= Utils::escape($message[0]) ?></p>
            </div>
<?php } ?>
<?php endif; ?>
        </div>
    </header>

    <section class="container">
        <div id="brdmain">
<?php
Container::get('hooks')->fire('view.header.end');
