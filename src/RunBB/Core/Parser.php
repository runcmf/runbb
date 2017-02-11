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

    public $pd = [];

    public function __construct()
    {
        $this->cacheDir = ForumEnv::get('FORUM_CACHE_DIR').'parser';

        if (is_file($this->cacheDir.'/s9eparser.php') && is_file($this->cacheDir.'/s9erenderer.php')) {
            $this->parser = unserialize(file_get_contents($this->cacheDir.'/s9eparser.php'));
            $this->renderer = unserialize(file_get_contents($this->cacheDir.'/s9erenderer.php'));
        } else {
            $this->configureParser();
        }
        // $this->pd temporary for help show only !!!
        $this->pd = $this->getSmilies();
    }

    /**
     * TODO Build bundles depend forum config and user group rights
     */
    private function configureParser()
    {
        $renderer = $parser = null;
        $configurator = new \s9e\TextFormatter\Configurator;
        $configurator->plugins->load('Autoemail');//Fatdown & Forum default
        $configurator->plugins->load('Autolink');//Fatdown & Forum default
        $configurator->plugins->load('Escaper');//Fatdown default
        $configurator->plugins->load('FancyPants');//Fatdown default
        $configurator->plugins->load('HTMLComments');//Fatdown default
        $configurator->plugins->load('HTMLElements');//Fatdown default
        $configurator->plugins->load('HTMLEntities');//Fatdown default
        $configurator->plugins->load('Litedown');//Fatdown default
        $configurator->plugins->load('MediaEmbed');//Fatdown & Forum default
        $configurator->plugins->load('PipeTables');//Fatdown default
//        $configurator->plugins->load('BBCodes');//Forum default
//        $configurator->plugins->load('Emoji');//Forum default
        $configurator->plugins->load('Emoticons');//Forum default

        $configurator->Emoticons->add(':)', '<img src="/assets/img/smilies/smile.png" alt=":)" title="Smile">');
        $configurator->Emoticons->add('=)', '<img src="/assets/img/smilies/smile.png" alt="=)" title="Smile">');
        $configurator->Emoticons->add(':|', '<img src="/assets/img/smilies/neutral.png" alt=":|" title="Neutral">');
        $configurator->Emoticons->add('=|', '<img src="/assets/img/smilies/neutral.png" alt="=|" title="Neutral">');
        $configurator->Emoticons->add(':(', '<img src="/assets/img/smilies/sad.png" alt=":(" title="Sad">');
        $configurator->Emoticons->add('=(', '<img src="/assets/img/smilies/sad.png" alt="=(" title="Sad">');
        $configurator->Emoticons->add(':D', '<img src="/assets/img/smilies/happy.png" alt=":D" title="Very Happy">');
        $configurator->Emoticons->add('=D', '<img src="/assets/img/smilies/happy.png" alt="=D" title="Very Happy">');
        $configurator->Emoticons->add(':o', '<img src="/assets/img/smilies/surprised.png" alt=":o" title="Surprised">');
        $configurator->Emoticons->add(':O', '<img src="/assets/img/smilies/surprised.png" alt=":O" title="Surprised">');
        $configurator->Emoticons->add(';)', '<img src="/assets/img/smilies/wink.png" alt=";)" title="Wink">');
        $configurator->Emoticons->add(':/', '<img src="/assets/img/smilies/confused.png" alt=":/" title="Confused">');
        $configurator->Emoticons->add(':?', '<img src="/assets/img/smilies/confused.png" alt=":?" title="Confused">');
        $configurator->Emoticons->add(':P', '<img src="/assets/img/smilies/tongue.png" alt=":P" title="Tongue">');
        $configurator->Emoticons->add(':p', '<img src="/assets/img/smilies/tongue.png" alt=":p" title="Tongue">');
        $configurator->Emoticons->add(':lol:', '<img src="/assets/img/smilies/lol.png" alt=":lol:" title="Laughing">');
        $configurator->Emoticons->add(':mad:', '<img src="/assets/img/smilies/mad.png" alt=":mad:" title="Mad">');
        $configurator->Emoticons->add(':x', '<img src="/assets/img/smilies/mad.png" alt=":x" title="Mad">');
        $configurator->Emoticons->add(':cool:', '<img src="/assets/img/smilies/cool.png" alt=":cool:" title="Cool">');
        $configurator->Emoticons->add('8)', '<img src="/assets/img/smilies/cool.png" alt="8)" title="Cool">');
        $configurator->Emoticons->add(':8', '<img src="/assets/img/smilies/desire.png" alt=":8" title="Desire">');
        $configurator->Emoticons->add(
            ':cry:',
            '<img src="/assets/img/smilies/cry.png" alt=":cry:" title="Crying or Very Sad">'
        );
        $configurator->Emoticons->add(
            ':oops:',
            '<img src="/assets/img/smilies/oops.png" alt=":oops:" title="Embaressed">'
        );
        $configurator->Emoticons->add(
            ':evil:',
            '<img src="/assets/img/smilies/evil.png" alt=":evil:" title="Evil or Very Mad">'
        );
        $configurator->Emoticons->add(
            ':pint:',
            '<img src="/assets/img/smilies/pint.png" alt=":pint:" title="Another pint of beer">'
        );
        $configurator->Emoticons->add(
            ':blah:',
            '<img src="/assets/img/smilies/bl   ah.png" alt=":blah:" title="Blah!!!">'
        );
        $configurator->Emoticons->add(':stop:', '<img src="/assets/img/smilies/stop.png" alt=":stop:" title="Stop">');

        $configurator->registeredVars['cacheDir'] = ForumEnv::get('FORUM_CACHE_DIR');

        // Get an instance of the parser and the renderer
        extract($configurator->finalize());

        // We save the parser and the renderer to the disk for easy reuse
        Utils::checkDir($this->cacheDir);

        file_put_contents($this->cacheDir.'/s9eparser.php', serialize($parser));
        file_put_contents($this->cacheDir.'/s9erenderer.php', serialize($renderer));

        $this->parser = $parser;
        $this->renderer = $renderer;
    }

    /**
     * Parse post or signature message text.
     *
     * @param string &$text
     * @param integer $hide_smilies
     * @return string
     */
    public function parseBBcode(&$text, $hide_smilies = 0)
    {
        if ($hide_smilies) {
            $this->parser->disablePlugin('Emoticons');
        }

        $xml  = $this->parser->parse($text);
        $html = $this->renderer->render($xml);
        return $html;
        // FIXME
//        if (ForumSettings::get('o_censoring') === '1')
//        {
//            $text = Utils::censor($text);
//        }
//        // Convert [&<>] characters to HTML entities (but preserve [""''] quotes).
//        $text = htmlspecialchars($text, ENT_NOQUOTES);
//
//        // Parse BBCode if globally enabled.
//        if (ForumSettings::get('p_message_bbcode'))
//        {
//            $text = preg_replace_callback($this->pd['re_bbcode'], array($this, '_parse_bbcode_callback'), $text);
//        }
//        // Set $smile_on flag depending on global flags and whether or not this is a signature.
//        if ($this->pd['in_signature'])
//        {
//            $smile_on = (ForumSettings::get('o_smilies_sig') &&
// User::get()['show_smilies'] && !$hide_smilies) ? 1 : 0;
//        }
//        else
//        {
//            $smile_on = (ForumSettings::get('o_smilies') && User::get()['show_smilies'] && !$hide_smilies) ? 1 : 0;
//        }
    }

    /**
     * Parse message text
     *
     * @param string $text
     * @param integer $hide_smilies
     * @return string
     */
    public function parseMessage($text, $hide_smilies)
    {
        if ($hide_smilies) {
            $this->parser->disablePlugin('Emoticons');
        }
        if (ForumSettings::get('p_message_img_tag') !== '1' || User::get()['show_img'] !== '1') {
            $this->parser->disablePlugin('Autoimage');// enable after parsing?
            $this->parser->disablePlugin('Autovideo');
        }

        $xml  = $this->parser->parse($text);
        $html = $this->renderer->render($xml);
        return $html;
        // FIXME

//        $this->pd['in_signature'] = false;
//        // Disable images via the $bbcd['in_post'] flag if globally disabled.
//        if (ForumSettings::get('p_message_img_tag') !== '1' || User::get()['show_img'] !== '1')
//            if (isset($this->pd['bbcd']['img']))
//                $this->pd['bbcd']['img']['in_post'] = false;
//        return $this->parseBBcode($text, $hide_smilies);
    }

    /**
     * Parse signature text
     *
     * @param string $text
     * @return string
     */
    public function parseSignature($text)
    {
        // FIXME check length
        if (ForumSettings::get('p_sig_img_tag') !== '1' || User::get()['show_img_sig'] !== '1') {
            $this->parser->disablePlugin('Autoimage');// enable after parsing?
            $this->parser->disablePlugin('Autovideo');
        }

        $xml  = $this->parser->parse($text);
        $html = $this->renderer->render($xml);

        return $html;

//        $this->pd['in_signature'] = true;
//        // Disable images via the $bbcd['in_sig'] flag if globally disabled.
//        if (ForumSettings::get('p_sig_img_tag') !== '1' || User::get()['show_img_sig'] !== '1')
//            if (isset($this->pd['bbcd']['img']))
//                $this->pd['bbcd']['img']['in_sig'] = false;
//        return $this->parseBBcode($text);
    }

    public function parseForSave($text, &$errors)
    {
        $xml  = $this->parser->parse($text);
        $html = $this->renderer->render($xml);

        // TODO check nestingLimit
//        $parserErrors = $this->parser->getLogger()->get();
//        if (!empty($parserErrors)) {
//tdie($parserErrors);
//        }
        return \s9e\TextFormatter\Unparser::unparse($xml);
    }
    /**
     * Pre-process text containing BBCodes. Check for integrity,
     * well-formedness, nesting, etc. Flag errors by wrapping offending
     * tags in a special [err] tag.
     *
     * @param string $text
     * @param array &$errors
     * @param integer $is_signature
     * @return string
     */
    public function preparseBBcode($text, &$errors, $is_signature = false)
    {
        // FIXME some as parseForSave ???

        // TODO check $is_signature limits
        $xml  = $this->parser->parse($text);
        $html = $this->renderer->render($xml);
        // TODO check nestingLimit
//        $parserErrors = $this->parser->getLogger()->get();
//        if (!empty($parserErrors)) {
//tdie($parserErrors);
//        }
        return \s9e\TextFormatter\Unparser::unparse($xml);
/*
        $this->pd['new_errors'] = []; // Reset the parser error message stack.
        $this->pd['in_signature'] = ($is_signature) ? true : false;
        $this->pd['ipass'] = 1;
        $newtext = preg_replace_callback($this->pd['re_bbcode'], array($this, '_preparse_bbcode_callback'), $text);
        if ($newtext === null)
        { // On error, preg_replace_callback returns NULL.
            // Error #1: '(%s) Message is too long or too complex. Please shorten.'
            $errors[] = sprintf(__('BBerr pcre'), $this->preg_error());
            return $text;
        }
        $newtext = str_replace("\3", '[', $newtext); // Fixup CODE sections.
        $parts = explode("\1", $newtext); // Hidden chunks pre-marked like so: "\1\2<code.../code>\1"
        for ($i = 0, $len = count($parts); $i < $len; ++$i)
        { // Loop through hidden and non-hidden text chunks.
            $part = &$parts[$i]; // Use shortcut alias
            if (empty($part))
                continue; // Skip empty string chunks.
            if ($part[0] !== "\2")
            { // If not hidden, process this normal text content.
                // Mark erroneous orphan tags.
                $part = preg_replace_callback($this->pd['re_bbtag'], array($this, '_orphan_callback'), $part);
                // Process do-clickeys if enabled.
                if (ForumSettings::get('o_make_links'))
                    $part = $this->linkify($part);

                // Process textile syntax tag shortcuts.
                if ($this->pd['config']['textile'])
                {
                    // Do phrase replacements.
                    $part = preg_replace_callback($this->pd['re_textile'],
array($this, '_textile_phrase_callback'), $part);
                    // Do lists.
                    $part = preg_replace_callback('/^([*#]) .*+(?:\n\1 .*+)++$/Sm',
array($this, '_textile_list_callback'), $part);
                }
                $part = preg_replace('/^[ \t]++$/m', '', $part); // Clear "white" lines of spaces and tabs.
            }
            else
                $part = substr($part, 1); // For hidden chunks, strip \2 marker byte.
        }
        $text = implode("", $parts); // Put hidden and non-hidden chunks back together.
        $this->pd['ipass'] = 2; // Run a second pass through parser to clean changed content.
        $text = preg_replace_callback($this->pd['re_bbcode'], array($this, '_preparse_bbcode_callback'), $text);
        $text = str_replace("\3", '[', $text); // Fixup CODE sections.
        if (!empty($this->pd['new_errors']))
        {
            foreach ($this->pd['new_errors'] as $errmsg)
            {
                $errors[] = $errmsg; // Push all new errors on global array.
            }
        }
        return $text;
*/
    }

    private function getSmilies()
    {
        return [
            'smilies' => [
                ':)' => [
                    'file' => 'smile.png',
                    'html' => '<img width="25" height="25" src="/assets/img/smilies/smile.png" '.
                        'alt="Smile" title="Smile" />',
                ],
                '=)' => [
                    'file' => 'smile.png',
                    'html' => '<img width="25" height="25" src="/assets/img/smilies/smile.png" '.
                        'alt="Smile" title="Smile" />',
                ],
                ':|' => [
                        'file' => 'neutral.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/neutral.png" '.
                            'alt="Neutral" title="Neutral" />',
                    ],
                '=|' => [
                        'file' => 'neutral.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/neutral.png" '.
                            'alt="Neutral" title="Neutral" />',
                    ],
                ':(' => [
                        'file' => 'sad.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/sad.png" '.
                            'alt="Sad" title="Sad" />',
                    ],
                '=(' => [
                        'file' => 'sad.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/sad.png" '.
                            'alt="Sad" title="Sad" />',
                    ],
                ':D' => [
                        'file' => 'lol.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/lol.png" '.
                            'alt="Lol" title="Lol" />',
                    ],
                '=D' => [
                        'file' => 'lol.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/lol.png" '.
                            'alt="Lol" title="Lol" />',
                    ],
                ':o' => [
                        'file' => 'surprised.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/surprised.png" '.
                            'alt="Surprised" title="Surprised" />',
                    ],
                ':O' => [
                        'file' => 'surprised.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/surprised.png" '.
                            'alt="Surprised" title="Surprised" />',
                    ],
                ';)' => [
                        'file' => 'wink.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/wink.png" '.
                            'alt="Wink" title="Wink" />',
                    ],
                ':/' => [
                        'file' => 'hmm.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/hmm.png" '.
                            'alt="Hmm" title="Hmm" />',
                    ],
                ':?' => [
                        'file' => 'hmm.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/hmm.png" '.
                            'alt="Hmm" title="Hmm" />',
                    ],
                ':P' => [
                        'file' => 'tongue.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/tongue.png" '.
                            'alt="Tongue" title="Tongue" />',
                    ],
                ':p' => [
                        'file' => 'tongue.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/tongue.png" '.
                            'alt="Tongue" title="Tongue" />',
                    ],
                ':lol:' => [
                        'file' => 'lol.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/lol.png" '.
                            'alt="Lol" title="Lol" />',
                    ],
                ':mad:' => [
                        'file' => 'mad.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/mad.png" '.
                            'alt="Mad" title="Mad" />',
                    ],
                ':x' => [
                        'file' => 'mad.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/mad.png" '.
                            'alt="Mad" title="Mad" />',
                    ],
                ':cool:' => [
                        'file' => 'cool.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/cool.png" '.
                            'alt="Cool" title="Cool" />',
                    ],
                ':8' => [
                        'file' => 'desire.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/desire.png" '.
                            'alt="Desire" title="Desire" />',
                    ],
                ':cry:' => [
                        'file' => 'cry.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/cry.png" '.
                            'alt="Cry" title="Cry" />',
                    ],
                ':oops:' => [
                        'file' => 'oops.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/oops.png" '.
                            'alt="Oops" title="Oops" />',
                    ],
                ':evil:' => [
                        'file' => 'evil.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/evil.png" '.
                            'alt="Evil" title="Evil" />',
                    ],
                ':pint:' => [
                        'file' => 'pint.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/pint.png" '.
                            'alt="Pint" title="Pint" />',
                    ],
                ':blah:' => [
                        'file' => 'blah.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/blah.png" '.
                            'alt="Blah" title="Blah" />',
                    ],
                ':stop:' => [
                        'file' => 'stop.png',
                        'html' => '<img width="25" height="25" src="/assets/img/smilies/stop.png" '.
                            'alt="Stop" title="Stop" />',
                    ]
            ]
        ];
    }
}
