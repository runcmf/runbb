<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Core;

use RunBB\Model\Cache;

class Utils
{
    /**
     * Return current timestamp (with microseconds) as a float
     * @return float
     */
    public static function get_microtime()
    {
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * Replace four-byte characters with a question mark
     *
     * As MySQL cannot properly handle four-byte characters with the default utf-8
     * charset up until version 5.5.3 (where a special charset has to be used), they
     * need to be replaced, by question marks in this case.
     * @param $str
     * @return string
     */
    public static function strip_bad_multibyte_chars($str)
    {
        $result = '';
        $length = self::strlen($str);

        for ($i = 0; $i < $length; $i++) {
            // Replace four-byte characters (11110www 10zzzzzz 10yyyyyy 10xxxxxx)
            $ord = ord($str[$i]);
            if ($ord >= 240 && $ord <= 244) {
                $result .= '?';
                $i += 3;
            } else {
                $result .= $str[$i];
            }
        }

        return $result;
    }

    /**
     * A wrapper for PHP's number_format function
     * @param $number
     * @param int $decimals
     * @return string
     */
    public static function forum_number_format($number, $decimals = 0)
    {
        return is_numeric($number) ? number_format(
            $number,
            $decimals,
            __('lang_decimal_point'),
            __('lang_thousands_sep')) : $number;
    }

    /**
     * Format a time string according to $time_format and time zones
     *
     * @param $timestamp
     * @param bool $date_only
     * @param null $date_format
     * @param null $time_format
     * @param bool $time_only
     * @param bool $no_text
     * @return false|string
     */
    public static function format_time($timestamp, $date_only = false,
                                       $date_format = null, $time_format = null,
                                       $time_only = false, $no_text = false)
    {
        if ($timestamp == '') {
            return __('Never');
        }

        $diff = (User::get()->timezone + User::get()->dst) * 3600;
        $timestamp += $diff;
        $now = time();

        if (is_null($date_format)) {
            $date_format = Container::get('forum_date_formats')[User::get()->date_format];
        }

        if (is_null($time_format)) {
            $time_format = Container::get('forum_time_formats')[User::get()->time_format];
        }

        $date = gmdate($date_format, $timestamp);
        $today = gmdate($date_format, $now+$diff);
        $yesterday = gmdate($date_format, $now+$diff-86400);

        if (!$no_text) {
            if ($date == $today) {
                $date = __('Today');
            } elseif ($date == $yesterday) {
                $date = __('Yesterday');
            }
        }

        if ($date_only) {
            return $date;
        } elseif ($time_only) {
            return gmdate($time_format, $timestamp);
        } else {
            return $date.' '.gmdate($time_format, $timestamp);
        }
    }

    /**
     * Calls htmlspecialchars with a few options already set
     * @param $str
     * @return string
     */
    public static function escape($str)
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * A wrapper for utf8_strlen for compatibility
     * @param $str
     * @return int
     */
    public static function strlen($str)
    {
        return utf8_strlen($str);
    }

    /**
     * Convert \r\n and \r to \n
     * @param $str
     * @return mixed
     */
    public static function linebreaks($str)
    {
        return str_replace(array("\r\n", "\r"), "\n", $str);
    }

    /**
     * A wrapper for utf8_trim for compatibility
     * @param $str
     * @param bool $charlist
     * @return string
     */
    public static function trim($str, $charlist = false)
    {
        return is_string($str) ? utf8_trim($str, $charlist) : '';
    }

    /**
     * A wrapper for utf8_trim for compatibility
     * @param $string
     * @return bool
     */
    public static function is_all_uppercase($string)
    {
        return utf8_strtoupper($string) == $string && utf8_strtolower($string) != $string;
    }

    /**
     * Replace string matching regular expression
     * This function takes care of possibly disabled unicode properties in PCRE builds
     * @param $pattern
     * @param $replace
     * @param $subject
     * @param bool $callback
     * @return mixed
     */
    public static function ucp_preg_replace($pattern, $replace, $subject, $callback = false)
    {
        if ($callback) {
            $replaced = preg_replace_callback($pattern, create_function('$matches', 'return '.$replace.';'), $subject);
        } else {
            $replaced = preg_replace($pattern, $replace, $subject);
        }

        // If preg_replace() returns false, this probably means unicode support is not built-in,
        // so we need to modify the pattern a little
        if ($replaced === false) {
            if (is_array($pattern)) {
                foreach ($pattern as $cur_key => $cur_pattern) {
                    $pattern[$cur_key] = str_replace('\p{L}\p{N}', '\w', $cur_pattern);
                }

                $replaced = preg_replace($pattern, $replace, $subject);
            } else {
                $replaced = preg_replace(str_replace('\p{L}\p{N}', '\w', $pattern), $replace, $subject);
            }
        }

        return $replaced;
    }

    /**
     * Converts the file size in bytes to a human readable file size
     * @param $size
     * @return string
     */
    public static function file_size($size)
    {
        $units = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB');

        for ($i = 0; $size > 1024; $i++) {
            $size /= 1024;
        }

        return sprintf(__('Size unit '.$units[$i]), round($size, 2));
    }

    /**
     * Generate browser's title
     * @param $page_title
     * @param null $p
     * @return string
     */
    public static function generate_page_title($page_title, $p = null)
    {
        if (!is_array($page_title)) {
            $page_title = array($page_title);
        }

        $page_title = array_reverse($page_title);

        if ($p > 1) {
            $page_title[0] .= ' ('.sprintf(__('Page'), self::forum_number_format($p)).')';
        }

        $crumbs = implode(__('Title separator'), $page_title);

        return $crumbs;
    }

    /**
     * Generate breadcrumbs on top of page
     * @var $crumbs: array('optionnal/url' => 'Text displayed')
     * @var $rightCrumb: array('link' => 'url/of/action', 'text' => 'Text displayed')
     *
     * @return text
     */
    public static function generateBreadcrumbs(array $crumbs = array(), array $rightCrumb = array())
    {
        \View::setPageInfo(array(
            'rightCrumb'    =>    $rightCrumb,
            'crumbs'    =>    $crumbs,
            ), 1
        )->addTemplate('breadcrumbs.php');
    }

    /**
     * Determines the correct user title
     *
     * @param string $title
     * @param string $name
     * @param string $groupTitle
     * @param int $gid
     * @return string
     */
    public static function get_title($title='', $name='', $groupTitle='', $gid=0)
    {
        static $ban_list;

        // If not already built in a previous call, build an array of lowercase banned usernames
        if (empty($ban_list)) {
            $ban_list = array();
            foreach (Container::get('bans') as $cur_ban) {
                $ban_list[] = utf8_strtolower($cur_ban['username']);
            }
        }

        // If the user has a custom title
        if ($title != '') {
            $user_title = self::escape($title);
        }
        // If the user is banned
        elseif (in_array(utf8_strtolower($name), $ban_list)) {
            $user_title = __('Banned');
        }
        // If the user group has a default user title
        elseif ($groupTitle != '') {
            $user_title = self::escape($groupTitle);
        }
        // If the user is a guest
        elseif ($gid == ForumEnv::get('FEATHER_GUEST')) {
            $user_title = __('Guest');
        }
        // If nothing else helps, we assign the default
        else {
            $user_title = __('Member');
        }

        return $user_title;
    }

    /**
     * Replace censored words in $text
     * @param $text
     * @return string
     */
    public static function censor($text)
    {
        static $search_for, $replace_with;

        if (!Container::get('cache')->isCached('search_for')) {
            Container::get('cache')->store('search_for', Cache::get_censoring('search_for'));
        }
        $search_for = Container::get('cache')->retrieve('search_for');

        if (!Container::get('cache')->isCached('replace_with')) {
            Container::get('cache')->store('replace_with', Cache::get_censoring('replace_with'));
        }
        $replace_with = Container::get('cache')->retrieve('replace_with');

        if (!empty($search_for) && !empty($replace_with)) {
            return substr(self::ucp_preg_replace($search_for, $replace_with, ' '.$text.' '), 1, -1);
        } else {
            return $text;
        }
    }

    /**
     * Fetch admin IDs
     * @return mixed
     */
    public static function get_admin_ids()
    {
        // Get Slim current session
        if (!Container::get('cache')->isCached('admin_ids')) {
            Container::get('cache')->store('admin_ids', Cache::get_admin_ids());
        }

        return Container::get('cache')->retrieve('admin_ids');
    }

    /**
     * Outputs markup to display a user's avatar
     * @param $user_id
     * @return string
     */
    public static function generate_avatar_markup($user_id)
    {
        $filetypes = array('jpg', 'gif', 'png');
        $avatar_markup = '';

        foreach ($filetypes as $cur_type) {
            $path = ForumSettings::get('o_avatars_dir').'/'.$user_id.'.'.$cur_type;

            if (file_exists(ForumEnv::get('WEB_ROOT').$path) &&
                $img_size = getimagesize(ForumEnv::get('WEB_ROOT').$path)) {
                $avatar_markup = '<img class="avatar" src="'.
                    \RunBB\Core\Utils::escape(Container::get('url')->base().$path.'?m='.
                        filemtime(ForumEnv::get('WEB_ROOT').$path)).'" '.$img_size[3].' alt="" />';
                break;
            }
        }

        return $avatar_markup;
    }

    /**
     * Get IP Address
     * @return mixed
     */
    public static function getIp()
    {
        if (isset(Request::getServerParams()['HTTP_CLIENT_IP'])) {
            $client = Request::getServerParams()['HTTP_CLIENT_IP'];
        }
        if (isset(Request::getServerParams()['HTTP_X_FORWARDED_FOR'])) {
            $forward = Request::getServerParams()['HTTP_X_FORWARDED_FOR'];
        }

        $remote = Request::getServerParams()['REMOTE_ADDR'];

        if (isset($client) && filter_var($client, FILTER_VALIDATE_IP)) {
            return $client;
        }
        elseif(isset($forward) && filter_var($forward, FILTER_VALIDATE_IP)) {
            return $forward;
        }

        return $remote;
    }

    /**
     * http://php.net/manual/ru/function.copy.php#91010
     * @param $src
     * @param $dst
     */
    public static function recurseCopy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    self::recurseCopy($src . '/' . $file, $dst . '/' . $file);
                }
                else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    /**
     * http://php.net/manual/ru/function.rmdir.php#110489
     * @param $dir
     * @return bool
     */
    public static function recurseDelete($dir) {
        $files = array_diff(scandir($dir), ['.','..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? self::recurseDelete("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    public static function arrayToList($data=false, $flatten=false){
        $response = '<ul>';
        if(false !== $data) {
            foreach($data as $key=>$val) {
                $response.= '<li>';
                if(!is_array($val)) {
                    if (is_object($val)) {
                        $response .= json_encode((array)$val);
                    } else {
                        $response.= $val;
                    }
                } else {
                    if(!$flatten){
                        $response.= self::arrayToList($val);
                    } else {
                        // pulls the sub array into the current list context
                        $response.= substr($response,0,strlen($response)-5) . self::arrayToList($val);
                    }
                }
                $response.= '</li>';
            }
        }
        $response.= '</ul>';
        return $response;
    }
}
