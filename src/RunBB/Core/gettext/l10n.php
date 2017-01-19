<?php
/**
 * Load a .mo file into the text domain $domain.
 *
 * If the text domain already exists, the translations will be merged. If both
 * sets have the same string, the translation from the original value will be taken.
 *
 * On success, the .mo file will be placed in the $l10n global by $domain
 * and will be a MO object.
 *
 * @param    string     $domain Text domain. Unique identifier for retrieving translated strings.
 * @param    string     $mofile Path to the .mo file.
 *
 * @return   boolean    True on success, false on failure.
 *
 * Inspired from Luna <http://getluna.org>
 */
function translate($mofile, $domain = 'RunBB', $path = false)
{

    global $l10n;

    if (!$path) {
        $mofile = ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.'/'.$mofile.'.mo';
    } else {
//        $mofile = ForumEnv::get('FORUM_ROOT').'lang/'.$language.'/'.$mofile.'.mo';
        $mofile = $path.'/'.User::get()->language.'/'.$mofile.'.mo';
    }

    if (!is_readable($mofile)) {
        return false;
    }

    $mo = new MO();
    if (!$mo->import_from_file($mofile)) {
        return false;
    }

    if (isset($l10n[$domain])) {
        $mo->merge_with($l10n[$domain]);
    }

    $l10n[$domain] = &$mo;

    return true;
}

function __($text, $domain = 'RunBB')
{
//    return translation($text);
    $text = translation($text);

    if (func_num_args() === 1) {
        return $text;
    }

    $args = array_slice(func_get_args(), 1);

    return vsprintf($text, is_array($args[0]) ? $args[0] : $args);
}

function d__($domain = 'RunBB', $text)
{
//    return translation($text, $domain);
    $text = translation($text);

    if (func_num_args() === 1) {
        return $text;
    }

    $args = array_slice(func_get_args(), 1);

    return vsprintf($text, is_array($args[0]) ? $args[0] : $args);
}

function _e($text, $domain = 'RunBB')
{
    echo translation($text);
}

function translation($text, $domain = 'RunBB')
{

    global $l10n;

    if (!isset($l10n[$domain])) {
        require_once dirname(__FILE__) . '/Translations/NOOPTranslations.php';
        $l10n[$domain] = new NOOPTranslations;
    }

    $translations = $l10n[$domain];
    $translations = $translations->translate($text);

    return $translations;
}
