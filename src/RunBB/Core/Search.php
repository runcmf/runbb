<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// The contents of this file are very much inspired by the file functions_search.php
// from the phpBB Group forum software phpBB2 (http://www.phpbb.com)

namespace RunBB\Core;

class Search
{
    public function __construct()
    {
        // Make a regex that will match CJK or Hangul characters
        defined('FEATHER_CJK_HANGUL_REGEX') || define('FEATHER_CJK_HANGUL_REGEX', '[' .
            '\x{1100}-\x{11FF}' .        // Hangul Jamo                            1100-11FF        (http://www.fileformat.info/info/unicode/block/hangul_jamo/index.htm)
            '\x{3130}-\x{318F}' .        // Hangul Compatibility Jamo            3130-318F        (http://www.fileformat.info/info/unicode/block/hangul_compatibility_jamo/index.htm)
            '\x{AC00}-\x{D7AF}' .        // Hangul Syllables                        AC00-D7AF        (http://www.fileformat.info/info/unicode/block/hangul_syllables/index.htm)

            // Hiragana
            '\x{3040}-\x{309F}' .        // Hiragana                                3040-309F        (http://www.fileformat.info/info/unicode/block/hiragana/index.htm)

            // Katakana
            '\x{30A0}-\x{30FF}' .        // Katakana                                30A0-30FF        (http://www.fileformat.info/info/unicode/block/katakana/index.htm)
            '\x{31F0}-\x{31FF}' .        // Katakana Phonetic Extensions            31F0-31FF        (http://www.fileformat.info/info/unicode/block/katakana_phonetic_extensions/index.htm)

            // CJK Unified Ideographs    (http://en.wikipedia.org/wiki/CJK_Unified_Ideographs)
            '\x{2E80}-\x{2EFF}' .        // CJK Radicals Supplement                2E80-2EFF        (http://www.fileformat.info/info/unicode/block/cjk_radicals_supplement/index.htm)
            '\x{2F00}-\x{2FDF}' .        // Kangxi Radicals                        2F00-2FDF        (http://www.fileformat.info/info/unicode/block/kangxi_radicals/index.htm)
            '\x{2FF0}-\x{2FFF}' .        // Ideographic Description Characters    2FF0-2FFF        (http://www.fileformat.info/info/unicode/block/ideographic_description_characters/index.htm)
            '\x{3000}-\x{303F}' .        // CJK Symbols and Punctuation            3000-303F        (http://www.fileformat.info/info/unicode/block/cjk_symbols_and_punctuation/index.htm)
            '\x{31C0}-\x{31EF}' .        // CJK Strokes                            31C0-31EF        (http://www.fileformat.info/info/unicode/block/cjk_strokes/index.htm)
            '\x{3200}-\x{32FF}' .        // Enclosed CJK Letters and Months        3200-32FF        (http://www.fileformat.info/info/unicode/block/enclosed_cjk_letters_and_months/index.htm)
            '\x{3400}-\x{4DBF}' .        // CJK Unified Ideographs Extension A    3400-4DBF        (http://www.fileformat.info/info/unicode/block/cjk_unified_ideographs_extension_a/index.htm)
            '\x{4E00}-\x{9FFF}' .        // CJK Unified Ideographs                4E00-9FFF        (http://www.fileformat.info/info/unicode/block/cjk_unified_ideographs/index.htm)
            '\x{20000}-\x{2A6DF}' .        // CJK Unified Ideographs Extension B    20000-2A6DF        (http://www.fileformat.info/info/unicode/block/cjk_unified_ideographs_extension_b/index.htm)
            ']');
    }


    //
    // "Cleans up" a text string and returns an array of unique words
    // This function depends on the current locale setting
    //
    public function split_words($text, $idx)
    {
        // Remove BBCode
        $text = preg_replace('%\[/?(b|u|s|ins|del|em|i|h|colou?r|quote|code|img|url|email|list|topic|post|forum|user)(?:\=[^\]]*)?\]%', ' ', $text);

        // Remove any apostrophes or dashes which aren't part of words
        $text = substr(Utils::ucp_preg_replace('%((?<=[^\p{L}\p{N}])[\'\-]|[\'\-](?=[^\p{L}\p{N}]))%u', '', ' ' . $text . ' '), 1, -1);

        // Remove punctuation and symbols (actually anything that isn't a letter or number), allow apostrophes and dashes (and % * if we aren't indexing)
        $text = Utils::ucp_preg_replace('%(?![\'\-' . ($idx ? '' : '\%\*') . '])[^\p{L}\p{N}]+%u', ' ', $text);

        // Replace multiple whitespace or dashes
        $text = preg_replace('%(\s){2,}%u', '\1', $text);

        // Fill an array with all the words
        $words = array_unique(explode(' ', $text));

        // Remove any words that should not be indexed
        foreach ($words as $key => $value) {
            // If the word shouldn't be indexed, remove it
            if (!$this->validate_search_word($value, $idx)) {
                unset($words[$key]);
            }
        }

        return $words;
    }


    //
    // Checks if a word is a valid searchable word
    //
    public function validate_search_word($word, $idx)
    {
        static $stopwords;

        // If the word is a keyword we don't want to index it, but we do want to be allowed to search it
        if ($this->is_keyword($word)) {
            return !$idx;
        }

        if (!isset($stopwords)) {
            if (!Container::get('cache')->isCached('stopwords')) {
                Container::get('cache')->store('stopwords', \RunBB\Model\Cache::get_config(), '+1 week');
            }
            $stopwords = Container::get('cache')->retrieve('stopwords');
        }

        // If it is a stopword it isn't valid
        if (in_array($word, $stopwords)) {
            return false;
        }

        // If the word is CJK we don't want to index it, but we do want to be allowed to search it
        if ($this->is_cjk($word)) {
            return !$idx;
        }

        // Exclude % and * when checking whether current word is valid
        $word = str_replace(['%', '*'], '', $word);

        // Check the word is within the min/max length
        $num_chars = Utils::strlen($word);
        return $num_chars >= ForumEnv::get('FEATHER_SEARCH_MIN_WORD') && $num_chars <= ForumEnv::get('FEATHER_SEARCH_MAX_WORD');
    }


    //
    // Check a given word is a search keyword.
    //
    public function is_keyword($word)
    {
        return $word == 'and' || $word == 'or' || $word == 'not';
    }


    //
    // Check if a given word is CJK or Hangul.
    //
    public function is_cjk($word)
    {
        return preg_match('%^' . FEATHER_CJK_HANGUL_REGEX . '+$%u', $word) ? true : false;
    }


    //
    // Strip [img] [url] and [email] out of the message so we don't index their contents
    //
    public function strip_bbcode($text)
    {
        static $patterns;

        if (!isset($patterns)) {
            $patterns = [
                '%\[img=([^\]]*+)\]([^[]*+)\[/img\]%' => '$2 $1',    // Keep the url and description
                '%\[(url|email)=([^\]]*+)\]([^[]*+(?:(?!\[/\1\])\[[^[]*+)*)\[/\1\]%' => '$2 $3',    // Keep the url and text
                '%\[(img|url|email)\]([^[]*+(?:(?!\[/\1\])\[[^[]*+)*)\[/\1\]%' => '$2',        // Keep the url
                '%\[(topic|post|forum|user)\][1-9]\d*\[/\1\]%' => ' ',        // Do not index topic/post/forum/user ID
            ];
        }

        return preg_replace(array_keys($patterns), array_values($patterns), $text);
    }


    //
    // Updates the search index with the contents of $post_id (and $subject)
    //
    public function update_search_index($mode, $post_id, $message, $subject = null)
    {
        $message = utf8_strtolower($message);
        $subject = utf8_strtolower($subject);

        // Remove any bbcode that we shouldn't index
        $message = $this->strip_bbcode($message);

        // Split old and new post/subject to obtain array of 'words'
        $words_message = $this->split_words($message, true);
        $words_subject = ($subject) ? $this->split_words($subject, true) : [];

        if ($mode == 'edit') {
            $select_update_search_index = ['w.id', 'w.word', 'm.subject_match'];
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'search_words')->table_alias('w')
                ->select_many($select_update_search_index)
                ->inner_join(ORM_TABLE_PREFIX.'search_matches', ['w.id', '=', 'm.word_id'], 'm')
                ->where('m.post_id', $post_id)
                ->find_many();

            // Declare here to stop array_keys() and array_diff() from complaining if not set
            $cur_words['post'] = [];
            $cur_words['subject'] = [];

            foreach ($result as $row) {
                $match_in = ($row['subject_match']) ? 'subject' : 'post';
                $cur_words[$match_in][$row['word']] = $row['id'];
            }

            $pdo = \ORM::get_db();
            $pdo = null;

            $words['add']['post'] = array_diff($words_message, array_keys($cur_words['post']));
            $words['add']['subject'] = array_diff($words_subject, array_keys($cur_words['subject']));
            $words['del']['post'] = array_diff(array_keys($cur_words['post']), $words_message);
            $words['del']['subject'] = array_diff(array_keys($cur_words['subject']), $words_subject);
        } else {
            $words['add']['post'] = $words_message;
            $words['add']['subject'] = $words_subject;
            $words['del']['post'] = [];
            $words['del']['subject'] = [];
        }

        unset($words_message);
        unset($words_subject);

        // Get unique words from the above arrays
        $unique_words = array_unique(array_merge($words['add']['post'], $words['add']['subject']));

        if (!empty($unique_words)) {
            $select_unique_words = ['id', 'word'];
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'search_words')->select_many($select_unique_words)
                ->where_in('word', $unique_words)
                ->find_many();

            $word_ids = [];
            foreach ($result as $row) {
                $word_ids[$row['word']] = $row['id'];
            }

            $pdo = \ORM::get_db();
            $pdo = null;

            $new_words = array_values(array_diff($unique_words, array_keys($word_ids)));

            unset($unique_words);

            if (!empty($new_words)) {
                switch (ForumSettings::get('db_type')) {
                    case 'mysql':
                    case 'mysqli':
                    case 'mysql_innodb':
                    case 'mysqli_innodb':
                        // Quite dirty, right? :-)
                        $placeholders = rtrim(str_repeat('(?), ', count($new_words)), ', ');
                        \ORM::for_table(ORM_TABLE_PREFIX.'search_words')
                            ->raw_execute('INSERT INTO ' . ForumSettings::get('db_prefix') . 'search_words (word) VALUES ' . $placeholders, $new_words);
                        break;

                    default:
                        foreach ($new_words as $word) {
                            $word_insert['word'] = $word;
                            \ORM::for_table(ORM_TABLE_PREFIX.'search_words')
                                ->create()
                                ->set($word_insert)
                                ->save();
                        }
                        break;
                }
            }

            unset($new_words);
        }

        // Delete matches (only if editing a post)
        foreach ($words['del'] as $match_in => $wordlist) {
            $subject_match = ($match_in == 'subject') ? 1 : 0;

            if (!empty($wordlist)) {
                $sql = [];
                foreach ($wordlist as $word) {
                    $sql[] = $cur_words[$match_in][$word];
                }

                \ORM::for_table(ORM_TABLE_PREFIX.'search_matches')
                    ->where_in('word_id', $sql)
                    ->where('post_id', $post_id)
                    ->where('subject_match', $subject_match)
                    ->delete_many();
            }
        }

        // Add new matches
        foreach ($words['add'] as $match_in => $wordlist) {
            $subject_match = ($match_in == 'subject') ? 1 : 0;

            if (!empty($wordlist)) {
                $wordlist = array_values($wordlist);
                $placeholders = rtrim(str_repeat('?, ', count($wordlist)), ', ');
                \ORM::for_table(ORM_TABLE_PREFIX.'search_words')
                    ->raw_execute('INSERT INTO ' . ForumSettings::get('db_prefix') . 'search_matches (post_id, word_id, subject_match) SELECT ' . $post_id . ', id, ' . $subject_match . ' FROM ' . ForumSettings::get('db_prefix') . 'search_words WHERE word IN (' . $placeholders . ')', $wordlist);
            }
        }

        unset($words);
    }


    //
    // Strip search index of indexed words in $post_ids
    //
    public function strip_search_index($post_ids)
    {
        if (!is_array($post_ids)) {
            $post_ids_sql = explode(',', $post_ids);
        } else {
            $post_ids_sql = $post_ids;
        }

        switch (ForumSettings::get('db_type')) {
            case 'mysql':
            case 'mysqli':
            case 'mysql_innodb':
            case 'mysqli_innodb': {
                $result = \ORM::for_table(ORM_TABLE_PREFIX.'search_matches')->select('word_id')
                    ->where_in('post_id', $post_ids_sql)
                    ->group_by('word_id')
                    ->find_many();

                if ($result) {
                    $word_ids = '';
                    foreach ($result as $row) {
                        $word_ids[] = $row['word_id'];
                    }

                    $result = \ORM::for_table(ORM_TABLE_PREFIX.'search_matches')->select('word_id')
                        ->where_in('word_id', $word_ids)
                        ->group_by('word_id')
                        ->having_raw('COUNT(word_id)=1')
                        ->find_many();

                    if ($result) {
                        $word_ids = '';
                        foreach ($result as $row) {
                            $word_ids[] = $row['word_id'];
                        }

                        \ORM::for_table(ORM_TABLE_PREFIX.'search_words')
                            ->where_in('id', $word_ids)
                            ->delete_many();
                    }
                }
                break;
            }

            default:
                \ORM::for_table(ORM_TABLE_PREFIX.'search_matches')
                    ->where_raw('id IN(SELECT word_id FROM ' . ForumSettings::get('db_prefix') . 'search_matches WHERE word_id IN(SELECT word_id FROM ' . ForumSettings::get('db_prefix') . 'search_matches WHERE post_id IN(' . $post_ids . ') GROUP BY word_id) GROUP BY word_id HAVING COUNT(word_id)=1)')
                    ->delete_many();
                break;
        }

        \ORM::for_table(ORM_TABLE_PREFIX.'search_matches')
            ->where_in('post_id', $post_ids_sql)
            ->delete_many();
    }
}
