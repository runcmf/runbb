<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2012 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Plugins;

use RunBB\Core\Plugin as BasePlugin;

class BbcodeToolbar extends BasePlugin
{

    public function run()
    {
        // Add language files into javascript footer block
        Container::get('hooks')->bind('view.alter_data', [$this, 'addLanguage']);
        // Support default actions
        Container::get('hooks')->bind('controller.post.create', [$this, 'addToolbar']);
        Container::get('hooks')->bind('controller.post.edit', [$this, 'addToolbar']);
        Container::get('hooks')->bind('controller.topic.display', [$this, 'addToolbar']);
        // Support PMs plugin
        Container::get('hooks')->bind('conversationsPlugin.send.preview', [$this, 'addToolbar']);
        Container::get('hooks')->bind('conversationsPlugin.send.display', [$this, 'addToolbar']);
    }

    public function addLanguage($data)
    {
        translate('bbeditor', 'bbcode-toolbar', false, __DIR__.'/lang');
        $lang_bbeditor = array(
            'btnBold' => __('btnBold', 'bbcode-toolbar'),
            'btnItalic' => __('btnItalic', 'bbcode-toolbar'),
            'btnUnderline' => __('btnUnderline', 'bbcode-toolbar'),
            'btnColor' => __('btnColor', 'bbcode-toolbar'),
            'btnLeft' => __('btnLeft', 'bbcode-toolbar'),
            'btnRight' => __('btnRight', 'bbcode-toolbar'),
            'btnJustify' => __('btnJustify', 'bbcode-toolbar'),
            'btnCenter' => __('btnCenter', 'bbcode-toolbar'),
            'btnLink' => __('btnLink', 'bbcode-toolbar'),
            'btnPicture' => __('btnPicture', 'bbcode-toolbar'),
            'btnList' => __('btnList', 'bbcode-toolbar'),
            'btnQuote' => __('btnQuote', 'bbcode-toolbar'),
            'btnCode' => __('btnCode', 'bbcode-toolbar'),
            'promptImage' => __('promptImage', 'bbcode-toolbar'),
            'promptUrl' => __('promptUrl', 'bbcode-toolbar'),
            'promptQuote' => __('promptQuote', 'bbcode-toolbar')
        );
        $data['jsVars']['bbcodeToolbar'] = json_encode($lang_bbeditor);
        return $data;
    }

    public function addToolbar()
    {
        View::addAsset('css', 'plugins/bbcode-toolbar/assets/bbeditor.css', array('type' => 'text/css', 'rel' => 'stylesheet'));
        View::addAsset('css', 'plugins/bbcode-toolbar/assets/colorPicker.css', array('type' => 'text/css', 'rel' => 'stylesheet'));
        View::addAsset('js', 'plugins/bbcode-toolbar/assets/bbeditor.js', array('type' => 'text/javascript'));
        View::addAsset('js', 'plugins/bbcode-toolbar/assets/colorPicker.js', array('type' => 'text/javascript'));
        return true;
    }

}
