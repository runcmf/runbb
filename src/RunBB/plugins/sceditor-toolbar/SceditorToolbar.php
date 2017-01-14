<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2012 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Plugins;

class SceditorToolbar
{
    protected $c;

    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
    }

    public function run()
    {
        // Add language files into javascript footer block
        Container::get('hooks')->bind('view.alter_data', [$this, 'addJs']);
        // Support default actions
        Container::get('hooks')->bind('controller.post.create', [$this, 'addToolbar']);
        Container::get('hooks')->bind('controller.post.edit', [$this, 'addToolbar']);
        Container::get('hooks')->bind('controller.topic.display', [$this, 'addToolbar']);
        // Support PMs plugin
        Container::get('hooks')->bind('conversationsPlugin.send.preview', [$this, 'addToolbar']);
        Container::get('hooks')->bind('conversationsPlugin.send.display', [$this, 'addToolbar']);
        // Profile signature edit
        Container::get('hooks')->bind('controller.profile.display', [$this, 'addToolbar']);
        // Post Report (need wysiwyg ????)
        Container::get('hooks')->bind('controller.post.report', [$this, 'addToolbar']);
    }

    public function addJs($data)
    {
        // TODO build editor depend user rights and config
        $SCEditConfig = '
        $.getScript(\'/plugins/sceditor-toolbar/assets/jquery.sceditor.bbcode.min.js\', function() {
            // Replace all textarea tags with SCEditor
            $(\'textarea\').sceditor({
                plugins: \'bbcode\',
                width: \'100%\',
                style: "/plugins/sceditor-toolbar/assets/jquery.sceditor.default.min.css"
            });
        });';

        // maybe where used
        $data['jsRAW'] = isset($data['jsRAW']) ? $data['jsRAW'] . $SCEditConfig : $SCEditConfig;
        return $data;
    }

    public function addToolbar()
    {
        //$args = func_get_args();
        View::addAsset('css', 'plugins/sceditor-toolbar/assets/themes/monocons.min.css', array('type' => 'text/css', 'rel' => 'stylesheet'));
//        View::addAsset('js', 'plugins/sceditor-toolbar/assets/jquery.sceditor.bbcode.min.js', array('type' => 'text/javascript'));
        return true;
    }

    public function install() {}
    public function remove() {}
}
