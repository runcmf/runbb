<!-- userMenu -->
        <li class="dropdown">
            <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                <i class="fa fa-user-circle fa-lg"></i>
                <span class="caret"></span>
            </a>
            <ul class="dropdown-menu">
{if $.userGet('is_guest')}
                <li class="dropdown-header">{$.trans('Not logged in')}</li>
                <li class="divider"></li>
                <li><a href="#" data-toggle="modal" data-target="#loginModal" class="login"><i class="fa fa-fw fa-sign-in"></i> {$.trans('Login')}</a></li>
{else}
                <li><a href="#"> {$.trans('Logged in as')} <strong>{$.userGet('username')}</strong></a></li>
                <li class="dropdown-header">{$.trans('Last visit')} <strong>{$.formatTime( $.userGet('last_visit') )}</strong></li>
                <li class="divider"></li>
                <li><a href="{$.pathFor('userProfile', ['id' => $.userGet('id')])}" class="usercp"><i class="fa fa-fw fa-user-circle-o"></i> {$.trans('Profile')}</a></li>
                <li class="divider"></li>
        {if $.userGet('is_admmod')}
                {if $.settings('o_report_method')  == '0' || $.settings('o_report_method') == '2'}
                    {if $has_reports}
                    <li><a href="{$.pathFor('adminReports')}">{$.trans('New reports')}</a></li>
                    {/if}
                {/if}
                    {if $.settings('o_maintenance') == '1'}
                    <li><a href="{$.pathFor('adminMaintenance')}">{$.trans('Maintenance mode enabled')}</a></li>
                    {/if}
                    <li><a href="{$.pathFor('adminIndex')}" class="navadmin"><i class="fa fa-cogs" aria-hidden="true"></i> {$.trans('Admin')}</a></li>
                    <li class="divider"></li>
        {/if}
                <li><a href="{$logOutLink}" class="logout"><i class="fa fa-fw fa-sign-out"></i> {$.trans('Logout')}</a></li>
{/if}
            </ul>
        </li>
<!-- /userMenu -->
