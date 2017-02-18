<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * Parser (C) 2011 Jeff Roberson (jmrware.com)
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model\Admin;

class Parser
{
    // Helper public function returns array of smiley image files
    //   stored in the style/img/smilies directory.
    public function getSmileyFiles()
    {
        $imgfiles = [];
        $filelist = scandir(ForumEnv::get('WEB_ROOT').'assets/img/smilies');
        $filelist = Container::get('hooks')->fire('model.admin.parser.get_smiley_files.filelist', $filelist);
        foreach ($filelist as $file) {
            if (preg_match('/\.(?:png|gif|jpe?g)$/', $file)) {
                $imgfiles[] = $file;
            }
        }
        $imgfiles = Container::get('hooks')->fire('model.admin.parser.get_smiley_files.imgfiles', $imgfiles);
        return $imgfiles;
    }

    public function resetPlugins()
    {
        $arry = [];
        $list = $this->getPluginsList();
        $grpModel = new \RunBB\Model\Admin\Groups();

        // fill array
        foreach ($list as $name => $vars) {
            if (!empty($vars['groups'])) {
                foreach ($vars['groups'] as $grp) {
                    $arry[$grp][] = $name;
                }
            }
        }
        // save to db
        foreach ($arry as $key => $vars) {
            $grpModel->setParserPlugins($key, $vars);
        }
    }

    /**
     * Plugins list with default groups
     *
     * 'FEATHER_UNVERIFIED' => 0,
     * 'FEATHER_ADMIN' => 1,
     * 'FEATHER_MOD' => 2,
     * 'FEATHER_GUEST' => 3,
     * 'FEATHER_MEMBER' => 4,
     * @return array
     */
    public function getPluginsList()
    {
        return [
            'Autoemail' => [
                'info' => 'This plugin converts plain-text emails into clickable <code>mailto:</code> links.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'Autoimage' => [
                'info' => 'This plugin converts plain-text image URLs into actual images. Only URLs starting with '.
                    '<code>http://</code> or <code>https://</code> and ending with <code>.gif</code>, '.
                    '<code>.jpeg</code>, <code>.jpg</code> or <code>.png</code> are converted.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'Autolink' => [
                'info' => 'This plugin converts plain-text URLs into clickable links. Only URLs starting with '.
                    'a scheme (e.g. "http://") are converted. Note that by default, the only allowed schemes '.
                    'are <code>http</code> and <code>https</code>.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'Autovideo' => [
                'info' => 'This plugin converts plain-text video URLs into playable videos. Only URLs starting '.
                    'with <code>http://</code> or <code>https://</code> and ending with <code>.mp4</code>, '.
                    '<code>.ogg</code> or <code>.webm</code> are converted.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'BBCodes' => [
                'info' => 'This plugin handles a very flexible flavour of the <code>BBCode</code> syntax.',
                'groups' => []
            ],

            'Censor' => [
                'info' => 'This plugin censors text based on a configurable list of words',
                'groups' => [
                    ForumEnv::get('FEATHER_GUEST'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'Emoji' => [
                'info' => 'Emoji are a standardized set of pictographs. They exists as Unicode characters '.
                    'and ASCII shortcodes.',
                'groups' => []
            ],

            'Emoticons' => [
                'info' => 'This plugin performs simple replacements, best suited for handling emoticons. '.
                    'Matching is case-sensitive.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_GUEST'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'Escaper' => [
                'info' => 'This plugin defines the backslash character <code>\</code> as an escape character.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'FancyPants' => [
                'info' => 'This plugin provides enhanced typography, aka "fancy Unicode symbols." '.
                    'It is inspired by <a href="http://daringfireball.net/projects/smartypants/">SmartyPants</a> and '.
                    '<a href="http://redcloth.org/textile/writing-paragraph-text/#typographers-quotes">'.
                    'RedCloth\'s Textile</a>.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'HTMLComments' => [
                'info' => 'This plugins allows HTML comments to be used. Internet Explorer\'s conditional '.
                    'comments are explicitly disabled because they could pose a security risk as they could be used '.
                    'to bypass the built-in template security.<br />The characters <code><</code> or <code>></code> '.
                    'are removed from the comments\' contents, as well as the illegal sequence <code>--</code>.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN')
                ]
            ],

            'HTMLElements' => [
                'info' => 'This plugin enables a whitelist of HTML elements to be used. By default, no HTML '.
                    'elements and no attributes are allowed. For each HTML element, a whitelist of attributes can '.
                    'be set. Unsafe elements such as <code><b><</b>script<b>></b></code> and unsafe attributes '.
                    'such as <code>onclick</code> must be set using a different method that safe elements and '.
                    'attributes.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN')
                ]
            ],

            'HTMLEntities' => [
                'info' => 'By default, s9e\TextFormatter escapes HTML entities. This plugins allows HTML '.
                    'entities to be used.<br>Note: while numeric entities such as <code>& #160;</code> are always '.
                    'available, the list of named entities such as <code>& hearts;</code> depends on PHP\'s '.
                    'internal table.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN')
                ]
            ],

            'Keywords' => [
                'info' => 'This plugin serves to capture keywords in plain text and render them as a rich '.
                    'element of your choosing such as a link, a popup or a widget.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD')
                ]
            ],

            'Litedown' => [
                'info' => 'This plugin implements a Markdown-like syntax, inspired by modern flavors of Markdown.<br>'.
                    'A more detailed description of the syntax in available in <a href="http://s9etextformatter.'.
                    'readthedocs.io/Plugins/Litedown/Syntax/">Syntax</a>.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_GUEST'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'MediaEmbed' => [
                'info' => 'This plugin allows the user to embed content from allowed sites using a '.
                    '<code>[media]</code> BBCode, site-specific BBCodes such as <code>[youtube]</code>, or from '.
                    'simply posting a supported URL in plain text.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'PipeTables' => [
                'info' => 'This plugin implements a type of ASCII-style tables inspired by '.
                    'GitHub-flavored Markdown, Pandoc\'s pipe tables and PHP Markdown Extra\'s simple tables.'.
                    'See its <a href="http://s9etextformatter.readthedocs.io/Plugins/PipeTables/Syntax/">Syntax</a>.',
                'groups' => [
                    ForumEnv::get('FEATHER_ADMIN'),
                    ForumEnv::get('FEATHER_MOD'),
                    ForumEnv::get('FEATHER_GUEST'),
                    ForumEnv::get('FEATHER_MEMBER')
                ]
            ],

            'Preg' => [
                'info' => 'This plugin performs generic, regexp-based replacements. The values in the named '.
                    'capturing subpatterns in the matching regexp are available as attributes in the XSL replacement.',
                'groups' => []
            ]
        ];
    }

    public function getSmilies()
    {
        return [
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
                    'alt=":D" title="Very Happy" />',
            ],
            '=D' => [
                'file' => 'lol.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/lol.png" '.
                    'alt="=D" title="Very Happy" />',
            ],
            ':o' => [
                'file' => 'surprised.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/surprised.png" '.
                    'alt=":o" title="Surprised" />',
            ],
            ':O' => [
                'file' => 'surprised.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/surprised.png" '.
                    'alt=":O" title="Surprised" />',
            ],
            ';)' => [
                'file' => 'wink.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/wink.png" '.
                    'alt=";)" title="Wink" />',
            ],
            ':/' => [
                'file' => 'hmm.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/confused.png" '.
                    'alt="Hmm" title="Confused" />',
            ],
            ':?' => [
                'file' => 'hmm.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/confused.png" '.
                    'alt="Hmm" title="Confused" />',
            ],
            ':P' => [
                'file' => 'tongue.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/tongue.png" '.
                    'alt=":P" title="Tongue" />',
            ],
            ':p' => [
                'file' => 'tongue.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/tongue.png" '.
                    'alt=":p" title="Tongue" />',
            ],
            ':lol:' => [
                'file' => 'lol.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/lol.png" '.
                    'alt=":lol:" title="Laughing" />',
            ],
            ':mad:' => [
                'file' => 'mad.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/mad.png" '.
                    'alt=":mad:" title="Mad" />',
            ],
            ':x' => [
                'file' => 'mad.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/mad.png" '.
                    'alt=":x" title="Mad" />',
            ],
            ':cool:' => [
                'file' => 'cool.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/cool.png" '.
                    'alt=":cool:" title="Cool" />',
            ],
            '8)' => [
                'file' => 'cool.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/cool.png" '.
                    'alt="8)" title="Cool" />',
            ],
            ':8' => [
                'file' => 'desire.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/desire.png" '.
                    'alt=":8" title="Desire" />',
            ],
            ':cry:' => [
                'file' => 'cry.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/cry.png" '.
                    'alt=":cry:" title="Crying or Very Sad" />',
            ],
            ':oops:' => [
                'file' => 'oops.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/oops.png" '.
                    'alt=":oops:" title="Embaressed" />',
            ],
            ':evil:' => [
                'file' => 'evil.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/evil.png" '.
                    'alt=":evil:" title="Evil or Very Mad" />',
            ],
            ':pint:' => [
                'file' => 'pint.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/pint.png" '.
                    'alt=":pint:" title="Another pint of beer" />',
            ],
            ':blah:' => [
                'file' => 'blah.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/blah.png" '.
                    'alt=":blah:" title="Blah!!!" />',
            ],
            ':stop:' => [
                'file' => 'stop.png',
                'html' => '<img width="25" height="25" src="/assets/img/smilies/stop.png" '.
                    'alt=":stop:" title="Stop" />',
            ]
        ];
    }
}
