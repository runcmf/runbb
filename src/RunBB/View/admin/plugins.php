<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure no one attempts to run this script "directly"
if (!isset($feather)) {
    exit;
}

Container::get('hooks')->fire('view.admin.plugins.start');
?>

<div class="block">
    <h2>Plugins
        <div class="pull-right">
            <a href="<?= Router::pathFor('pluginsRepoList') ?>" class="btn btn-info btn-xs" role="button">
                repo &nbsp; <i class="fa fa-github fa-lg"></i>
            </a>
        </div>
    </h2>
    <div class="box">
        <div class="inbox">
            <table class="table">
                <caption><?= __('Available plugins') ?></caption>
                <thead>
                    <tr>
                        <th><?= __('Extension') ?></th>
                        <th><?= __('Description') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($availablePlugins as $plugin) : ?>
                        <tr>
                            <td>
                                <strong><?= $plugin->title; ?></strong> <small><?= $plugin->version; ?></small>
                                <div class="plugin-actions">
                                    <?php if (in_array($plugin->name, $activePlugins)) { ?>
                                        <a href="<?= Router::pathFor('deactivatePlugin', ['name' => $plugin->name]) ?>"><?= __('Deactivate') ?></a>
                                    <?php } else { ?>
                                        <a href="<?= Router::pathFor('activatePlugin', ['name' => $plugin->name]) ?>"><?= __('Activate') ?></a> <br>
                                        <a href="<?= Router::pathFor('uninstallPlugin', ['name' => $plugin->name]) ?>"><?= __('Uninstall') ?></a>
                                    <?php } ?>
                                </div>
                            </td>
                            <td>
                                <?= $plugin->description; ?>
                                <div class="plugin-details">
                                    By <?= $plugin->author->name; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align:right"><?= count($availablePlugins) ?> éléments</p>
        </div>
        <div class="inbox">
            <table class="table">
                <caption><?= __('Available plugins') ?></caption>
                <thead>
                <tr>
                    <th><?= __('Extension') ?></th>
                    <th><?= __('Description') ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($officialPlugins as $plugin) : ?>
                    <tr>
                        <td>
                            <strong><?= $plugin->title; ?></strong> <small><?= $plugin->version; ?></small>
                            <div class="plugin-actions">
                                <a href="<?= Router::pathFor('downloadPlugin', ['name' => $plugin->name, 'version' => $plugin->version]) ?>">Download</a>
                            </div>
                        </td>
                        <td>
                            <?= $plugin->description; ?>
                            <div class="plugin-details">
                                By <?= $plugin->author->name; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="text-align:right"><?= count($officialPlugins) ?> éléments</p>
        </div>
    </div>
</div>

    <div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.admin.plugins.end');
