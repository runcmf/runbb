<?php
if (!isset($feather)) {
exit;
}

Container::get('hooks')->fire('view.admin.languages.start');
?>

    <div class="block">

        <div class="panel panel-primary">
            <div class="panel-heading">
                <h3 class="panel-title">Languages list
                    <div class="pull-right">
                        <a href="<?= Router::pathFor('adminLanguages.repo') ?>" class="btn btn-info btn-xs" role="button">
                            repo &nbsp; <i class="fa fa-github fa-lg"></i>
                        </a>
                    </div>
                </h3>
            </div>
            <div class="list-group">
                <div class="list-group-item list-group-item-info">
                    <div class="row">
                        <div class="col-sm-3">Name</div>
                        <div class="col-sm-5">Author</div>
                        <div class="col-sm-4">Actions</div>
                    </div>
                </div>
                <?php foreach ($langList as $lang) { ?>
                <div class="list-group-item">
                    <div class="row">
                        <div class="col-sm-3">
                            <a href="#" class="btn btn-info btn-xs" onclick="RunBB.popupWindow('<?= Router::pathFor('adminLanguages.info') ?>?langinfo=<?= $lang['id'] ?>'); return false;">
                                info &nbsp; <i class="fa fa-edit fa-lg"></i>
                            </a>
                            &nbsp;
                            <a href="<?= Router::pathFor('adminLanguages.showlangfiles') ?>?lng=<?= $lang['id'] ?>" class="btn btn-success btn-xs">
                                <?= $lang['name'] ?> &nbsp; <i class="fa fa-edit fa-lg"></i>
                            </a>
                        </div>
                        <div class="col-sm-5"><?= $lang['author'] ?></div>
                        <div class="col-sm-4">
                            <a href="<?= Router::pathFor('adminLanguages.showmailtpls') ?>?lng=<?= $lang['id'] ?>&name=<?= $lang['name'] ?>" class="btn btn-info btn-xs">
                                mail tpls &nbsp; <i class="fa fa-edit fa-lg"></i>
                            </a>
                            <a href="<?= Router::pathFor('adminLanguages.export') ?>?lng=<?= $lang['id'] ?>" class="btn btn-warning btn-xs">
                                export &nbsp; <i class="fa fa-save fa-lg"></i>
                            </a>
                            &nbsp; &nbsp; &nbsp;
                            <?php if ($lang['code'] != 'en') { ?>
                            <a href="<?= Router::pathFor('adminLanguages.delete') ?>?lng=<?= $lang['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Are you sure?');">
                                <i class="fa fa-trash-o fa-lg"></i>
                            </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>

        <form class="panel panel-primary" method="post" action="<?= Router::pathFor('adminLanguages.build') ?>">
            <input type="hidden" name="csrf_name" value="<?= $csrf_name ?>">
            <input type="hidden" name="csrf_value" value="<?= $csrf_value ?>">
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
                                    <option value="<?= $lang['id'] ?>">&nbsp;<?= $lang['name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-3">
                            <input type="checkbox" name="iknow" value="1" />
                            <input type="submit" href="<?= Router::pathFor('adminLanguages.build') ?>" class="btn btn-warning btn-xs" />
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
