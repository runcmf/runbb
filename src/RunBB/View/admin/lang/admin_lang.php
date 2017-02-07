<?php
if (!isset($feather)) {
exit;
}

Container::get('hooks')->fire('view.admin.languages.start');
?>

<div class="blockform">

    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">Languages list
                <div class="pull-right">
                    <a href="<?= Router::pathFor('adminLanguages.import') ?>" class="btn btn-info btn-xs" role="button">import</a>
                </div>
            </h3>
        </div>
        <div class="list-group">
            <div class="list-group-item list-group-item-info">
                <div class="row">
                    <div class="col-sm-4">Name</div>
                    <div class="col-sm-2">Code</div>
                    <div class="col-sm-2">Locale</div>
                    <div class="col-sm-4 text-right">Actions</div>
                </div>
            </div>
            <?php foreach ($langList as $lang) { ?>
            <div class="list-group-item">
                <div class="row">
                    <div class="col-sm-4">
                        <a href="<?= Router::pathFor('adminLanguages.showlangfiles') ?>?lng=<?= $lang['id']; ?>" class="btn btn-success btn-xs"><?= $lang['name']; ?></a>
                    </div>
                    <div class="col-sm-2"><?= $lang['code']; ?></div>
                    <div class="col-sm-2"><?= $lang['locale']; ?></div>
                    <div class="col-sm-4 text-right">
                        <a href="<?= Router::pathFor('adminLanguages.showmailtpls') ?>?lng=<?= $lang['id']; ?>" class="btn btn-info btn-xs">mail tpls</a>
                        <a href="<?= Router::pathFor('adminLanguages.export') ?>?lng=<?= $lang['id']; ?>" class="btn btn-warning btn-xs">export</a>
                        &nbsp; &nbsp; &nbsp;
                        <a href="<?= Router::pathFor('adminLanguages.delete') ?>?lng=<?= $lang['id']; ?>" class="btn btn-danger btn-xs">del</a>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>


    <form class="panel panel-primary" method="post" action="<?= Router::pathFor('adminLanguages.build') ?>">
        <input type="hidden" name="csrf_name" value="<?= $csrf_name; ?>">
        <input type="hidden" name="csrf_value" value="<?= $csrf_value; ?>">
        <div class="panel-heading">
            <h3 class="panel-title">Create new translations</h3>
        </div>
        <div class="list-group">
            <div class="list-group-item">
                <div class="row">
                    <div class="col-sm-5 form-inline">
                        <input type="text" name="code" size="3" value="" maxlength="2" placeholder="code" />
                        <input type="text" name="locale" size="5" value="" maxlength="5" placeholder="locale" />
                        <input type="text" name="name" size="15" value="" maxlength="16" placeholder="LangName" />
                    </div>
                    <div class="col-sm-4">
                        <div class="form-group form-inline">
                            <label for="lid">Fill from:</label>
                            <select name="lid" class="form-control" id="lid">
                                <?php foreach ($langList as $lang) { ?>
                                <option value="<?= $lang['id']; ?>">&nbsp;<?= $lang['name']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <input type="checkbox" name="iknow" value="1" />
                        <input type="submit" href="<?= Router::pathFor('adminLanguages.build') ?>" class="btn btn-warning btn-xs">Go</input>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<div class="clearer"></div>
</div>

<?php
Container::get('hooks')->fire('view.admin.languages.end');
