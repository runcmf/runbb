{if $nested} {$.fireHook('view.header.start')}
<!DOCTYPE html>
<html lang="{$.trans('lang_identifier')}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {if $is_indexed}
    <meta name="robots" content="noindex, follow">
    {/if}

    <title>{$pageTitle}</title>
    <link rel="shortcut icon" href="{$.call.Url::baseStatic()}/assets/img/favicon.png" />
    <!-- Theme -->
    <link rel="stylesheet" type="text/css" href="{$.call.Url::baseStatic()}/themes/{$style}/style.css">

{foreach $assets as $type => $items}
    {if $type == 'js'}{continue}{/if}

    <!-- {$type|up} -->
    {foreach $items as $item}
        {if $item.params.type == 'text/javascript'}
<script {foreach $item.params as $key => $value}{$key}={$value} {/foreach}src="{$.call.Url::baseStatic()}/{$item.file}" /></script>
        {else}
<link {foreach $item.params as $key => $value}{$key}={$value} {/foreach} href="{$.call.Url::baseStatic()}/{$item.file}">
        {/if}
    {/foreach}
{/foreach}

{if $admin_console}
{$admStyle}
{/if}

{if $required_fields?}
    <!-- Output JavaScript to validate form (make sure required fields are filled out) -->
    <script type="text/javascript">
        /* <![CDATA[ */
    function process_form(the_form)
    {
        var required_fields = {
//        Output a JavaScript object with localised field names
            {set $tpl_temp = $.php.count($required_fields)}
            {foreach $required_fields as $elem_orig => $elem_trans}
                "\""{$elem_orig}": "{$.php.addslashes($.php.str_replace('&#160;', ' ', $elem_trans))};
                {if --$tpl_temp}
                    "\", "
                {else}
                    "\"\n\t\t\t\t};\n"
                {/if}
            {/foreach}
        };

        if (document.all || document.getElementById)
        {
            for (var i = 0; i < the_form.length; ++i)
            {
                var elem = the_form.elements[i];
                if (elem.name && required_fields[elem.name] && !elem.value && elem.type && (/^(?:text(?:area)?|password|file)$/i.test(elem.type)))
                {
                    alert('"' + required_fields[elem.name] + '" {$.trans('required field')}');
                    elem.focus();
                    return false;
                }
            }
        }
        return true;
    }
/* ]]> */
</script>

{/if}

{if $page_head?}
{$page_head|join:"\n"}
{/if}

{$.fireHook('view.header.before.head.tag')}
</head>

<body id="pun{$active_page}"{if $focus_element} onload="document.getElementById('{$focus_element.0}').elements['{$focus_element.1}'].focus();"{/if}>

    <!-- Navigation -->
    <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
        <div class="container">
            <!-- Brand and toggle get grouped for better mobile display -->
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="/">
                    <img src="{$.call.Url::baseStatic()}/assets/img/logo.png" align="left" width="64" height="61" alt="RunCMF" title="RunCMF"/>
                </a>
            </div>
            <div class="navbar-collapse collapse">
                <ul class="nav navbar-nav">
                    <!-- navlinks -->
                    {foreach $navlinks as $row}
                        {if is_array($row)}
                            <li id="{$row.id}"{$row.active}><a href="{$row.href}">{$row.text}</a></li>
                        {else}
                            {raw $row}
                        {/if}
                    {/foreach}
                    <!-- /navlinks -->
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    {include 'userMenu.html.tpl'}

                    <li>
                        <form role="search" method="get" action="{$.pathFor('search')}" class="nav-search">
                            <input type="hidden" name="action" value="search">
                            <input class="form-control" type="text" name="keywords" size="20" maxlength="100" placeholder="{$.trans('Search')}">
                        </form>
                    </li>
                </ul>
            </div>
            <!-- /.navbar-collapse -->
        </div>
        <!-- /.container -->
    </nav><!-- /Navigation -->
<header>
    <div class="container">
        <div class="container-title-status">
            <h1 class="title-site">
                <a href="{$.pathFor('home')}" title="" class="site-name">
                    <p>{$.settings('o_board_title')}</p>
                </a>
                <div id="brddesc">{$.settings('o_board_desc')}</div>
            </h1>
            <div class="status-avatar">
                <div id="brdwelcome" class="inbox">

                    {if $.call.User::get('is_guest') != false}
                        <ul class="conl">
                            {if $.call.User::get('is_admmod') or $.call.User::get('isModerator')}
                                {if $.settings('o_report_method')  == '0' or $.settings('o_report_method') == '2'}
                                    {if $has_reports}
                                        <li class="reportlink"><span><strong><a href="{$.pathFor('adminReports')}">{$.trans('New reports')}</a></strong></span></li>
                                    {/if}
                                {/if}
                                {if $.settings('o_maintenance') == '1'}
                                    <li class="maintenancelink"><span><strong><a href="{$.pathFor('adminMaintenance')}">{$.trans('Maintenance mode enabled')}</a></strong></span></li>
                                {/if}
                            {/if}

                            {set $headerToplist = $.fireHook('header.toplist')}
                            {$headerToplist|join:"\n"}

                        </ul>
                    {/if}

                    {if $.call.User::get('g_read_board') == '1' && $.call.User::get('g_search') == '1'}
                        <ul class="conr">
                            <li>{$.trans('Topic searches')}
                                {if !$.call.User::get('is_guest')}
                                    <a href="{$.pathFor('quickSearch', ['show' => 'replies'])}" title="{$.trans('Show posted topics')}">{$.trans('Posted topics')}</a> |
                                    <a href="{$.pathFor('quickSearch', ['show' => 'new'])}" title="{$.trans('Show new posts')}">{$.trans('New posts header')}</a> |
                                {/if}
                                <a href="{$.pathFor('quickSearch', ['show' => 'recent'])}" title="{$.trans('Show active topics')}">{$.trans('Active topics')}</a> |
                                <a href="{$.pathFor('quickSearch', ['show' => 'unanswered'])}" title="{$.trans('Show unanswered topics')}">{$.trans('Unanswered topics')}</a>
                            </li>
                        </ul>
                    {/if}
{$.fireHook('view.header.brdwelcome')}
                </div>
            </div>
            <div class="clear"></div>
        </div>
    </div>
</header>

{if $.call.User::get('g_read_board') == '1' && $.settings('o_announcement') == '1'}
<div class="container">
    <div id="announce" class="block">
        <div class="hd"><h2><span>{$.trans('Announcement')}</span></h2></div>
        <div class="box">
            <div id="announce-block" class="inbox">
                <div class="usercontent">{$.settings('o_announcement_message')}</div>
            </div>
        </div>
    </div>
</div>
{/if}

{if $flashMessages?}
<div class="container">
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
    {foreach $flashMessages as  $type => $message}
    <div class="flashmsg info" data-type="{$type}" id="flashmsg">
        <h2>{$.trans('Info')}
            <span style="float:right;cursor:pointer"
                  onclick="document.getElementById('flashmsg').className = 'flashmsg';">&times;
                    </span>
        </h2>
        <p>{raw $message.0}</p>
    </div>
    {/foreach}
</div>
{/if}

{$.fireHook('view.header.end')}

{/if}

<!-- Page Content -->
<div class="container">
{block 'content'}{/block}
</div>
<!-- /.Page Content -->


{if $nested}
<!-- Page Footer -->
{$.fireHook('view.footer.start')}
<div class="container">

    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">
            <i class="fa fa-toggle-on fa-lg toggler pull-right" aria-hidden="true"
            role="button" data-toggle="collapse" href="#forumHelper"
            aria-expanded="false" aria-controls="forumHelper"></i>
            {*{$.trans('Board footer')}*}
            <i class="fa fa-life-ring fa-lg" aria-hidden="true"></i>
            </h3>
        </div>
        <div id="forumHelper" class="collapse">
            <div class="list-group-item">

                {if ($active_page == 'Forum' || $active_page == 'Topic') && ($.call.User::get('is_admmod') || $.call.User::get('isModerator'))}
                <div id="modcontrols" class="inbox">
                    {if $active_page == 'Forum'}
                    <dl>
                        <dt><strong>{$.trans('Mod controls')}</strong></dt>
                        <dd><span><a href="{$.pathFor('moderateForum', ['fid' => $fid, 'page' => $page_number])}">{$.trans('Moderate forum')}</a></span></dd>
                    </dl>
                    {elseif $active_page == 'Topic'}
                    <dl>
                        <dt><strong>{$.trans('Mod controls')}</strong></dt>
                        <dd><span><a href="{$.pathFor('moderateTopic', ['id' => $tid, 'fid' => $fid, 'page' => $page_number])}">{$.trans('Moderate topic')}</a></span></dd>
                        <dd><span><a href="{$.pathFor('moveTopic', ['id' => $tid, 'fid' => $fid, 'name' => $.call.Url::slug($cur_topic.subject)])}">{$.trans('Move topic')}</a></span></dd>

                        {if $cur_topic.closed == '1'}
                        <dd><span><a href="{$.pathFor('openTopic', ['id' => $tid, 'name' => $.call.Url::slug($cur_topic.subject)])}">{$.trans('Open topic')}</a></span></dd>
                        {else}
                        <dd><span><a href="{$.pathFor('closeTopic', ['id' => $tid, 'name' => $.call.Url::slug($cur_topic.subject)])}">{$.trans('Close topic')}</a></span></dd>
                        {/if}

                        {if $cur_topic.sticky == '1'}
                        <dd><span><a href="{$.pathFor('unstickTopic', ['id' => $tid, 'name' => $.call.Url::slug($cur_topic.subject)])}">{$.trans('Unstick topic')}</a></span></dd>
                        {else}
                        <dd><span><a href="{$.pathFor('stickTopic', ['id' => $tid, 'name' => $.call.Url::slug($cur_topic.subject)])}">{$.trans('Stick topic')}</a></span></dd>
                        {/if}
                    </dl>
                    {/if}

{$.fireHook('view.footer.mod.actions')}
                </div>
                {/if}

                <div class="row">

                    <div class="col-sm-9 form-inline">
                        <div class="form-group col-sm-4">
                        {if $.settings('o_quickjump') == '1' && $quickjump}
                            <form class="form-horizontal" role="form" id="qjump" method="get" action="">
                                <label class="control-label col-sm-2" for="id" title="{$.trans('Jump to')}">
                                    <i class="fa fa-fast-forward" aria-hidden="true"></i>
                                </label>
                                <select name="id" class="form-control" onchange="window.location=(this.options[this.selectedIndex].value)">
                            {foreach $quickjump[ $.call.User::get('g_id') ] as $cat_id => $cat_data}
                                    <optgroup label="{$cat_data.cat_name}">
                                {foreach $cat_data.cat_forums as $forum}
                                    <option value="{$.pathFor('Forum', ['id' => $forum.forum_id, 'name' => $.call.Url::slug($forum.forum_name)])}"{$fid == 2 ? ' selected="selected"' : ''}>{$forum.forum_name}</option>
                                {/foreach}
                                    </optgroup>
                            {/foreach}
                                </select>
                            </form>
                        {/if}
                        </div>

                        <div class="form-group col-sm-4">
                        {if $languagesQSelect|length > 1}
                            <form method="post" class="form-horizontal" action="{$.pathFor('profileAction', ['id' => 1, 'action' => 'change_lang'])}" id="lang_select">
                                <input type="hidden" name="csrf_name" value="{$csrf_name}">
                                <input type="hidden" name="csrf_value" value="{$csrf_value}">
                                <input type="hidden" name="currentPage" value="{$currentPage}">
                                <label class="control-label col-sm-5" for="languageToChange" title="{$.trans('Language')}">
                                    <i class="fa fa-language" aria-hidden="true"></i>
                                </label>
                                <select name="languageToChange" class="form-control" onchange="RunBB.changeLanguage();">
                                {foreach $languagesQSelect as $lang}
                                    <option value="{$lang.name}"{$.call.User::get('language') == $lang.name ? ' selected="selected" ' : ''}>{$lang.name}</option>
                                {/foreach}
                                </select>
                            </form>
                        {/if}
                        </div>

                        <div class="form-group col-sm-4">
                        {if $stylesQSelect|length > 1}
                            <form method="post" class="form-horizontal" action="{$.pathFor('profileAction', ['id' => 1, 'action' => 'change_style'])}" id="theme_select">
                                <input type="hidden" name="csrf_name" value="{$csrf_name}">
                                <input type="hidden" name="csrf_value" value="{$csrf_value}">
                                <input type="hidden" name="currentPage" value="{$currentPage}">
                                <label class="control-label col-sm-3" for="styleToChange">{$.trans('Styles')}</label>
                                <select name="styleToChange" class="form-control" onchange="RunBB.changeTheme();">
                                    {foreach $stylesQSelect as $style}
                                        <option value="{$style}"{$.call.User::get('style') == $style ? ' selected="selected"' : ''}>
                                            {$style|join:'_'}
                                        </option>
                                    {/foreach}
                                </select>
                            </form>
                        {/if}
                        </div>
                    </div>
                    <div class="form-inline col-sm-3 pull-right">

                        {if $active_page == 'index'}
                            {if $.settings('o_feed_type') == '1'}
                                <span class="rss"><a href="{$.pathFor('extern')}?action=feed&type=rss">{$.trans('RSS active topics feed')}</a></span>
                            {elseif $.settings('o_feed_type') == '2'}
                                <span class="atom"><a href="{$.pathFor('extern')}?action=feed&type=atom">{$.trans('Atom active topics feed')}</a></span>
                            {/if}
                        {elseif $active_page == 'Forum' || $active_page == 'Topic'}
                            {if $.settings('o_feed_type') == '1'}
                                <span class="rss"><a href="{$.pathFor('extern')}?action=feed&fid={$fid}&type=rss">{$.trans('RSS forum feed')}</a></span>
                            {elseif $.settings('o_feed_type') == '2'}
                                <span class="atom"><a href="{$.pathFor('extern')}?action=feed&fid={$fid}&type=atom">{$.trans('Atom forum feed')}</a></span>
                            {/if}
                        {/if}

{$.fireHook('view.footer.feed.links')}
                        <br />
                        <small id="poweredby">
                            {$.trans('Powered by', '<a href="https://github.com/runcmf/runbb">RunBB</a> ' ~ ($.settings('o_show_version') == '1' ? $.settings('o_cur_version') : ''))}
                            <br />
                            Based on <a href="https://github.com/featherbb/featherbb">FeatherBB</a>
                        </small>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {if $exec_info?}
    <p id="debugtime">[{$.trans('Querytime', $.php.round($exec_info.exec_time, 6))} - {$.trans('Memory usage', $exec_info.mem_usage)} {$.trans('Peak usage', $exec_info.mem_peak_usage)}]</p>
    {/if}
</div>

<!-- JS -->
{foreach $assets.js as $script}
<script {foreach $script.params as $key => $value}{$key}="{$value}"{/foreach} src="{$.call.Url::baseStatic()}/{$script.file}" /></script>
{/foreach}
<!-- JSRAW -->
<script>
    var baseUrl = '{$.call.Url::base()}',
        phpVars = '{if $jsVars is not empty}{$jsVars|json_encode}{/if}';
    {$jsraw}
</script>

{if $.php.User::get('is_guest')}
{include 'auth.html.twig'}
{/if}

{$.fireHook('view.footer.before.html.tag')}
</body>
</html>
{$.fireHook('view.footer.end')}
<!-- /Page Footer -->
{/if}
