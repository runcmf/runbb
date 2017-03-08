<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace RunBB\Core;

class Parser
{
    private $parser;
    private $renderer;
    private $cacheDir;

    /**
     * @link https://github.com/s9e/TextFormatter
     * Parser constructor. Must be initialized after user load
     */
    public function __construct()
    {
        $this->cacheDir = ForumEnv::get('FORUM_CACHE_DIR').'parser';

        if (is_file($this->cacheDir.'/'.User::get()->g_title.'Parser.php') &&
            is_file($this->cacheDir.'/'.User::get()->g_title.'Renderer.php')) {
            $this->parser = unserialize(file_get_contents($this->cacheDir.'/'.User::get()->g_title.'Parser.php'));
            $this->renderer = unserialize(file_get_contents($this->cacheDir.'/'.User::get()->g_title.'Renderer.php'));
        } else {
            $this->configureParser();
        }
    }

    /**
     * Build bundle for user group
     */
    private function configureParser()
    {
        $renderer = $parser = null;
        $model = new \RunBB\Model\Admin\Parser();

        $configurator = new \s9e\TextFormatter\Configurator();
        $plugins = unserialize(User::get()->g_parser_plugins);

        $useSmilies = false;
        foreach ($plugins as $k => $plugin) {
            $configurator->plugins->load($plugin);
            if ($plugin === 'Emoticons') {
                $useSmilies = true;
            }
        }

        if ($useSmilies) {
            foreach ($model->getSmilies() as $k => $v) {
                $configurator->Emoticons->add($k, $v['html']);
            }
        }

        $configurator->registeredVars['cacheDir'] = ForumEnv::get('FORUM_CACHE_DIR');

        // Get an instance of the parser and the renderer
        extract($configurator->finalize());

        // We save the parser and the renderer to the disk for easy reuse
        Utils::checkDir($this->cacheDir);

        file_put_contents($this->cacheDir.'/'.User::get()->g_title.'Parser.php', serialize($parser));
        file_put_contents($this->cacheDir.'/'.User::get()->g_title.'Renderer.php', serialize($renderer));

        $this->parser = $parser;
        $this->renderer = $renderer;
    }

    /**
     * Parse message, post or signature message text.
     *
     * @param string $text
     * @param integer $hide_smilies
     * @return string html
     */
    public function parseMessage($text, $hide_smilies = 0)
    {
        if (ForumSettings::get('o_censoring') == '1') {
            $text = Utils::censor($text);
        }

        // FIXME check text length
        if ($hide_smilies) {
            $this->parser->disablePlugin('Emoticons');
        }
        if (ForumSettings::get('p_message_img_tag') !== '1' ||
            ForumSettings::get('p_sig_img_tag') !== '1' ||
            User::get()['show_img'] !== '1' ||
            User::get()['show_img_sig'] !== '1'
        ) {
            $this->parser->disablePlugin('Autoimage');
            $this->parser->disablePlugin('Autovideo');
        }

        $xml  = $this->parser->parse($text);
        $html = $this->renderer->render($xml);
        return $html;
    }

    /**
     * Parse message, post or signature message text.
     * Check errors
     *
     * @param string $text
     * @return string
     */
    public function parseForSave($text, &$errors, $is_signature = false)
    {
        // FIXME check text length
        if ($is_signature && (ForumSettings::get('p_sig_img_tag') !== '1' || User::get()['show_img_sig'] !== '1')) {
            $this->parser->disablePlugin('Autoimage');
            $this->parser->disablePlugin('Autovideo');
        }

        $xml  = $this->parser->parse($text);
//        $html = $this->renderer->render($xml);

        // TODO check parser errors ??? not found problems in parser
        // TODO check nestingLimit ??? not found problems in parser
        // TODO check $is_signature limits
//        $parserErrors = $this->parser->getLogger()->get();
//        if (!empty($parserErrors)) {
//tdie($parserErrors);
//        }
        return \s9e\TextFormatter\Unparser::unparse($xml);
    }
}
