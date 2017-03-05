{extends 'layout.html.tpl'}

{block 'content'}
{$.fireHook('view.index.start')}

{foreach $index_data as $cat}

    <div id="idx{$cat.0.cid}" class="panel panel-primary">
        <div class="panel-heading">
            <div class="row">
                <div class="col-xs-7 col-sm-7">
                    <h3 class="panel-title">{$cat.0.cat_name}</h3>
                </div>
                <div class="hidden-xs col-sm-1 text-center text-nowrap">
                    <span class="hidden-xs hidden-sm">{$.trans('Topics')}</span>
                </div>
                <div class="hidden-xs col-sm-1 text-center text-nowrap">
                    <span class="hidden-xs hidden-sm">{$.trans('Posts')}</span>
                </div>
                <div class="col-sm-2 text-center text-nowrap">
                    {$.trans('Last post')}
                </div>
            </div>
        </div>
    {if $cat is not empty}
        {foreach $cat as $forum}
        <div class="list-group">
            <div class="list-group-item {$forum.item_status}">
                <div class="row">
                    <div class="col-xs-7 col-sm-7">
                        <div class="media">
                            <div class="media-left media-middle img-responsive {$forum.icon_type}">
                            </div>
                            <div class="media-body">
                                {*<h4 class="media-heading">*}
                                    {raw $forum.forum_field}
                                {*</h4>*}
                                {raw $forum.moderators_formatted}
                            </div>
                        </div>
                    </div>
                    <div class="hidden-xs col-sm-1 text-center text-nowrap">
                        {$.formatNumber($forum.num_topics_formatted)}
                    </div>
                    <div class="hidden-xs col-sm-1 text-center text-nowrap">
                        {$.formatNumber($forum.num_posts_formatted)}
                    </div>
                    <div class="col-sm-2">
                        {raw $forum.last_post_formatted}
                    </div>
                </div>
            </div>
        </div>
        {/foreach}
    {else}
        <div class="list-group">
            <div class="list-group-item {$forum.item_status}">
                <div class="row">
                    <div class="col-xs-12 col-sm-12 text-center">
                        {$.trans('Empty board')}
                    </div>
                </div>
            </div>
        </div>
    {/if}
    </div>

{/foreach}

    {if $forum_actions is not empty}
    <div class="linksb">
        <div class="inbox crumbsplus">
            <p class="subscribelink clearb">{raw $forum_actions|join:' '}</p>
        </div>
    </div>
    {/if}

    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">
                <i class="fa fa-toggle-on fa-lg toggler pull-right" aria-hidden="true"
                   role="button" data-toggle="collapse" href="#brdstats"
                   aria-expanded="false" aria-controls="brdstats"></i>
                <i class="fa fa-info-circle fa-lg" aria-hidden="true"></i>
            </h3>
        </div>
        <div id="brdstats" class="collapse">
            <div class="list-group-item">
                <div class="row">
                    <div class="col-lg-6 pull-right">
                        <div><strong>{$.trans('Board stats')}</strong></div>
                        <div>{$.trans('No of users', "<strong>{$stats.total_users}</strong>")}</div>
                        <div>{$.trans('No of topics', "<strong>{$stats.total_topics}</strong>")}</div>
                        <div>{$.trans('No of posts', "<strong>{$stats.total_posts}</strong>")}</div>
                    </div>
                    <div class="col-lg-6 pull-left">
                        <div><strong>{$.trans('User info')}</strong></div>
                        <div>{$.trans('Newest user', $stats.newest_user)}</div>
                        {if $.settings('o_users_online') == 1}
                        <div>{$.trans('Users online', "<strong>{$online.num_users}</strong>")}</div>
                        <div>{$.trans('Guests online', "<strong>{$online.num_guests}</strong>")}</div>
                        {/if}
                    </div>

                    {if $.settings('o_users_online') == 1 && $online.num_users > 0}
                    <div class="col-lg-12 pull-left">
                        <dl id="onlinelist" class="clearb">
                            <dt>{$.trans('Online')} </dt>
                            {raw $online.users|join:',</dd> '}</dd>
                        </dl>
                    </div>
                    {/if}

                    {$.fireHook('view.index.brdstats')}
                </div>
            </div>
        </div>
    </div>

{$.fireHook('view.index.end')}
{/block}
