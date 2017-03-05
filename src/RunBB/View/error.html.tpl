{extends 'layout.html.tpl'}

{block 'content'}

    {$.fireHook('view.error.start')}

    <div id="msg" class="block error">
        <h2><span>{$.php.__('Error')}</span></h2>
        <div class="box">
            <div class="inbox">
                <p>{$msg}</p>
                {if $backlink}
                    <p><a href="javascript: history.go(-1)">{$.php.__('Go back')}</a></p>
                {/if}
            </div>
        </div>
    </div>

    {$.fireHook('view.error.end')}
{/block}
