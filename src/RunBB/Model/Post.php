<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Exception\RunBBException;
use RunBB\Core\Track;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Post
{
    public function __construct()
    {
        $this->search = new \RunBB\Core\Search();
    }

    //  Get some info about the post
    public function get_info_post($tid, $fid)
    {
        Container::get('hooks')->fire('model.post.get_info_post_start', $tid, $fid);

        $cur_posting['where'] = 'fp.read_forum IS NULL OR fp.read_forum=1';
//            array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );

        if ($tid) {
            $cur_posting['select'] = ['f.id', 'f.forum_name', 'f.moderators', 'f.redirect_url',
                'fp.post_replies', 'fp.post_topics', 't.subject', 't.closed', 'is_subscribed' => 's.user_id'];

            $cur_posting = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                            ->table_alias('t')
                            ->select_many($cur_posting['select'])
                            ->inner_join(ORM_TABLE_PREFIX.'forums', ['f.id', '=', 't.forum_id'], 'f')
//                            ->left_outer_join('forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//                            ->left_outer_join('forum_perms', array('fp.group_id', '=',
// User::get()->g_id), null, true)
                ->left_outer_join(
                    ORM_TABLE_PREFIX.'forum_perms',
                    '(fp.forum_id=f.id AND fp.group_id='. User::get()->g_id.')',
                    'fp'
                )
//                            ->left_outer_join('topic_subscriptions', array('t.id', '=', 's.topic_id'), 's')
//                            ->left_outer_join('topic_subscriptions', array('s.user_id', '=',
// User::get()->id), null, true)
                ->left_outer_join(
                    ORM_TABLE_PREFIX.'topic_subscriptions',
                    '(t.id=s.topic_id AND s.user_id='.User::get()->id.')',
                    's'
                )
                            ->where_raw($cur_posting['where'])
                            ->where('t.id', $tid);
        } else {
            $cur_posting['select'] = ['f.id', 'f.forum_name', 'f.moderators', 'f.redirect_url',
                'fp.post_replies', 'fp.post_topics'];

            $cur_posting = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
                            ->table_alias('f')
                            ->select_many($cur_posting['select'])
//                            ->left_outer_join('forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//                            ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
                ->left_outer_join(
                    ORM_TABLE_PREFIX.'forum_perms',
                    '(fp.forum_id=f.id AND fp.group_id='. User::get()->g_id.')',
                    'fp'
                )
                            ->where_raw($cur_posting['where'])
                            ->where('f.id', $fid);
        }

        $cur_posting = Container::get('hooks')->fireDB('model.post.get_info_post_query', $cur_posting);
        $cur_posting = $cur_posting->find_one();

        if (!$cur_posting) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        $cur_posting = Container::get('hooks')->fire('model.post.get_info_post', $cur_posting);

        return $cur_posting;
    }

    // Fetch some info about the post, the topic and the forum
    public function get_info_edit($id)
    {
        $id = Container::get('hooks')->fire('model.post.get_info_edit_start', $id);

        $cur_post['select'] = ['fid' => 'f.id', 'f.forum_name', 'f.moderators', 'f.redirect_url',
            'fp.post_topics', 'tid' => 't.id', 't.subject', 't.posted', 't.first_post_id', 't.sticky',
            't.closed', 'p.poster', 'p.poster_id', 'p.message', 'p.hide_smilies'];
//        $cur_post['where'] = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );

        $cur_post = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                    ->table_alias('p')
                    ->select_many($cur_post['select'])
                    ->inner_join(ORM_TABLE_PREFIX.'topics', ['t.id', '=', 'p.topic_id'], 't')
                    ->inner_join(ORM_TABLE_PREFIX.'forums', ['f.id', '=', 't.forum_id'], 'f')
//                    ->left_outer_join('forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//                    ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(fp.forum_id=f.id AND fp.group_id='. User::get()->g_id.')',
                'fp'
            )
//                    ->where_any_is($cur_post['where'])
                ->where_raw('(fp.read_forum IS NULL OR fp.read_forum=1)')
                    ->where('p.id', $id);

        $cur_post = Container::get('hooks')->fireDB('model.post.get_info_edit_query', $cur_post);

        $cur_post = $cur_post->find_one();

        if (!$cur_post) {
            throw new  RunBBException(__('Bad request'), 400);
        }
//echo \ORM::getLastQuery();
//tdie($cur_post);
        return $cur_post;
    }

    // Checks the post for errors before posting
    public function checkErrorsBeforePost($fid, $tid, $qid, $pid, $page, $errors)
    {
        $lang_antispam_questions = require ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.'/antispam.php';

        $fid = Container::get('hooks')->fire('model.post.check_errors_before_post_start', $fid);

        // Antispam feature
        if (User::get()->is_guest) {
            // It's a guest, so we have to validate the username
            $profile = new \RunBB\Model\Profile();
            $errors = $profile->check_username(Utils::trim(Input::post('req_username')), $errors);

            $errors = Container::get('hooks')->fire('model.post.check_errors_before_post_antispam', $errors);

            $question = Input::post('captcha_q') ? trim(Input::post('captcha_q')) : '';
            $answer = Input::post('captcha') ? strtoupper(trim(Input::post('captcha'))) : '';
            $lang_antispam_questions_array = [];

            foreach ($lang_antispam_questions as $k => $v) {
                $lang_antispam_questions_array[md5($k)] = strtoupper($v);
            }

            if (empty($lang_antispam_questions_array[$question]) ||
                $lang_antispam_questions_array[$question] != $answer)
            {
                $errors[] = __('Robot test fail');
            }
        }

        // Flood protection
        if (Input::post('preview') != '' &&
            User::get()->last_post != '' &&
            (time() - User::get()->last_post) < Container::get('prefs')->get(User::get(), 'post.min_interval')) {
            $errors[] = sprintf(
                __('Flood start'),
                Container::get('prefs')->get(User::get(), 'post.min_interval'),
                Container::get('prefs')->get(User::get(), 'post.min_interval') - (time() - User::get()->last_post)
            );
        }

        // If it's a new topic
        if ($fid) {
            $subject = Utils::trim(Input::post('req_subject'));
            $subject = Container::get('hooks')->fire('model.post.check_errors_before_new_topic_subject', $subject);

            if (ForumSettings::get('o_censoring') == '1') {
                $censored_subject = Utils::trim(Utils::censor($subject));
                $censored_subject = Container::get('hooks')
                    ->fire('model.post.check_errors_before_censored', $censored_subject);
            }

            if ($subject == '') {
                $errors[] = __('No subject');
            } elseif (ForumSettings::get('o_censoring') == '1' && $censored_subject == '') {
                $errors[] = __('No subject after censoring');
            } elseif (Utils::strlen($subject) > 70) {
                $errors[] = __('Too long subject');
            } elseif (ForumSettings::get('p_subject_all_caps') == '0' &&
                Utils::is_all_uppercase($subject) && !User::get()->is_admmod) {
                $errors[] = __('All caps subject');
            }

            $errors = Container::get('hooks')->fire('model.post.check_errors_before_new_topic_errors', $errors);
        }

        if (User::get()->is_guest) {
            $email = strtolower(Utils::trim((ForumSettings::get('p_force_guest_email') == '1') ?
                Input::post('req_email') : Input::post('email')));

            if (ForumSettings::get('p_force_guest_email') == '1' || $email != '') {
                $errors = Container::get('hooks')->fire('model.post.check_errors_before_post_email', $errors, $email);

                if (!Container::get('email')->is_valid_email($email)) {
                    $errors[] = __('Invalid email');
                }

                // Check if it's a banned email address
                // we should only check guests because members' addresses are already verified
                if (User::get()->is_guest && Container::get('email')->is_banned_email($email)) {
                    if (ForumSettings::get('p_allow_banned_email') == '0') {
                        $errors[] = __('Banned email');
                    }

                    $errors['banned_email'] = 1; // Used later when we send an alert email
                }
            }
        }

        // Clean up message from POST
        $message = Utils::linebreaks(Utils::trim(Input::post('req_message')));
        $message = Container::get('hooks')->fire('model.post.check_errors_before_post_message', $message);

        // Here we use strlen() not Utils::strlen() as we want to limit the post to
        // FEATHER_MAX_POSTSIZE bytes, not characters
        if (strlen($message) > ForumEnv::get('FEATHER_MAX_POSTSIZE')) {
            $errors[] = sprintf(__('Too long message'),
                Utils::forum_number_format(ForumEnv::get('FEATHER_MAX_POSTSIZE')));
        } elseif (ForumSettings::get('p_message_all_caps') == '0' &&
            Utils::is_all_uppercase($message) && !User::get()->is_admmod) {
            $errors[] = __('All caps message');
        }

        // Validate BBCode syntax
        if (ForumSettings::get('p_message_bbcode') == '1') {
            $message = Container::get('parser')->preparseBBcode($message, $errors);
            $message = Container::get('hooks')->fire('model.post.check_errors_before_post_bbcode', $message);
        }

        if (empty($errors)) {
            $errors = Container::get('hooks')->fire('model.post.check_errors_before_post_no_error', $errors);
            if ($message == '') {
                $errors[] = __('No message');
            } elseif (ForumSettings::get('o_censoring') == '1') {
                // Censor message to see if that causes problems
                $censored_message = Utils::trim(Utils::censor($message));

                if ($censored_message == '') {
                    $errors[] = __('No message after censoring');
                }
            }
        }

        $errors = Container::get('hooks')->fire('model.post.check_errors_before_post', $errors);

        return $errors;
    }

    public function check_errors_before_edit($can_edit_subject, $errors)
    {
        $errors = Container::get('hooks')->fire('model.post.check_errors_before_edit_start', $errors);

        // If it's a topic it must contain a subject
        if ($can_edit_subject) {
            $subject = Utils::trim(Input::post('req_subject'));

            if (ForumSettings::get('o_censoring') == '1') {
                $censored_subject = Utils::trim(Utils::censor($subject));
            }

            if ($subject == '') {
                $errors[] = __('No subject');
            } elseif (ForumSettings::get('o_censoring') == '1' && $censored_subject == '') {
                $errors[] = __('No subject after censoring');
            } elseif (Utils::strlen($subject) > 70) {
                $errors[] = __('Too long subject');
            } elseif (ForumSettings::get('p_subject_all_caps') == '0' &&
                Utils::is_all_uppercase($subject) &&
                !User::get()->is_admmod) {
                $errors[] = __('All caps subject');
            }
        }

        // Clean up message from POST
        $message = Utils::linebreaks(Utils::trim(Input::post('req_message')));

        // Here we use strlen() not Utils::strlen() as we want to limit the post to
        // FEATHER_MAX_POSTSIZE bytes, not characters
        if (strlen($message) > ForumEnv::get('FEATHER_MAX_POSTSIZE')) {
            $errors[] = sprintf(__('Too long message'),
                Utils::forum_number_format(ForumEnv::get('FEATHER_MAX_POSTSIZE')));
        } elseif (ForumSettings::get('p_message_all_caps') == '0' &&
            Utils::is_all_uppercase($message) &&
            !User::get()->is_admmod) {
            $errors[] = __('All caps message');
        }

        // Validate BBCode syntax
        if (ForumSettings::get('p_message_bbcode') == '1') {
            $message = Container::get('parser')->preparseBBcode($message, $errors);
        }

        if (empty($errors)) {
            if ($message == '') {
                $errors[] = __('No message');
            } elseif (ForumSettings::get('o_censoring') == '1') {
                // Censor message to see if that causes problems
                $censored_message = Utils::trim(Utils::censor($message));

                if ($censored_message == '') {
                    $errors[] = __('No message after censoring');
                }
            }
        }

        $errors = Container::get('hooks')->fire('model.post.check_errors_before_edit', $errors);

        return $errors;
    }

    // If the previous check went OK, setup some variables used later
    public function setup_variables($errors, $is_admmod)
    {
        $post = [];

        $post = Container::get('hooks')->fire('model.post.setup_variables_start', $post, $errors, $is_admmod);

        if (!User::get()->is_guest) {
            $post['username'] = User::get()->username;
            $post['email'] = User::get()->email;
        } // Otherwise it should be in $feather ($_POST)
        else {
            $post['username'] = Utils::trim(Input::post('req_username'));
            $post['email'] = strtolower(Utils::trim((ForumSettings::get('p_force_guest_email') == '1') ?
                Input::post('req_email') : Input::post('email')));
        }

        if (Input::post('req_subject')) {
            $post['subject'] = Utils::trim(Input::post('req_subject'));
        }

        $post['hide_smilies'] = Input::post('hide_smilies') ? '1' : '0';
        $post['subscribe'] = Input::post('subscribe') ? '1' : '0';
        $post['stick_topic'] = Input::post('stick_topic') && $is_admmod ? '1' : '0';

        $post['message']  = Utils::linebreaks(Utils::trim(Input::post('req_message')));

        // Validate BBCode syntax
        if (ForumSettings::get('p_message_bbcode') == '1') {
//            $post['message']  = Container::get('parser')->preparse_bbcode($post['message'], $errors);
            $post['message'] = Container::get('parser')->parseForSave($post['message'], $errors);
        }

        // Replace four-byte characters (MySQL cannot handle them)
        // FIXME national symbols problem
//        $post['message'] = Utils::strip_bad_multibyte_chars($post['message']);

        $post['time'] = time();

        $post = Container::get('hooks')->fire('model.post.setup_variables', $post);

        return $post;
    }

    // If the previous check went OK, setup some variables used later
    public function setup_edit_variables($cur_post, $is_admmod, $can_edit_subject, $errors)
    {
        Container::get('hooks')->fire('model.post.setup_edit_variables_start');

        $post = [];

        $post['hide_smilies'] = Input::post('hide_smilies') ? '1' : '0';
        $post['stick_topic'] = Input::post('stick_topic') ? '1' : '0';
        if (!$is_admmod) {
            $post['stick_topic'] = $cur_post['sticky'];
        }

        // Clean up message from POST
        $post['message'] = Utils::linebreaks(Utils::trim(Input::post('req_message')));

        // Validate BBCode syntax
        if (ForumSettings::get('p_message_bbcode') == '1') {
//            $post['message'] = Container::get('parser')->preparse_bbcode($post['message'], $errors);
            $post['message'] = Container::get('parser')->parseForSave($post['message'], $errors);
        }

        // Replace four-byte characters (MySQL cannot handle them)
        // FIXME national symbols problem
//        $post['message'] = Utils::strip_bad_multibyte_chars($post['message']);

        // Get the subject
        if ($can_edit_subject) {
            $post['subject'] = Utils::trim(Input::post('req_subject'));
        }

        $post = Container::get('hooks')->fire('model.post.setup_edit_variables', $post);

        return $post;
    }

    public function get_info_delete($id)
    {
        $id = Container::get('hooks')->fire('model.post.get_info_delete_start', $id);

        $query['select'] = ['fid' => 'f.id', 'f.forum_name', 'f.moderators', 'f.redirect_url',
            'fp.post_replies',  'fp.post_topics', 'tid' => 't.id', 't.subject', 't.first_post_id',
            't.closed', 'p.poster', 'p.posted', 'p.poster_id', 'p.message', 'p.hide_smilies'];
//        $query['where'] = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );

        $query = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
            ->table_alias('p')
            ->select_many($query['select'])
            ->inner_join(ORM_TABLE_PREFIX.'topics', ['t.id', '=', 'p.topic_id'], 't')
            ->inner_join(ORM_TABLE_PREFIX.'forums', ['f.id', '=', 't.forum_id'], 'f')
//            ->left_outer_join('forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//            ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(fp.forum_id=f.id AND fp.group_id='.User::get()->g_id.')',
                'fp'
            )
//            ->where_any_is($query['where'])
            ->where_raw('fp.read_forum IS NULL OR fp.read_forum=1')
            ->where('p.id', $id);

        $query = Container::get('hooks')->fireDB('model.post.get_info_delete_query', $query);

        $query = $query->find_one();

        if (!$query) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        return $query;
    }

    public function handle_deletion($is_topic_post, $id, $tid, $fid)
    {
        Container::get('hooks')->fire('model.post.handle_deletion_start', $is_topic_post, $id, $tid, $fid);

        if ($is_topic_post) {
            Container::get('hooks')->fire('model.post.model.topic.delete', $tid, $fid);

            // Delete the topic and all of its posts
            Topic::delete($tid);
            Forum::update($fid);

            return Router::redirect(Router::pathFor('Forum', ['id' => $fid]), __('Topic del redirect'));
        } else {
            Container::get('hooks')->fire('model.post.handle_deletion', $tid, $fid, $id);

            // Delete just this one post
            $this->delete($id, $tid);
            Forum::update($fid);

            // Redirect towards the previous post
            $post = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                ->select('id')
                ->where('topic_id', $tid)
                ->where_lt('id', $id)
                ->order_by_desc('id');

            $post = Container::get('hooks')->fireDB('model.post.handle_deletion_query', $post);

            $post = $post->find_one();

            return Router::redirect(Router::pathFor(
                'viewPost',
                    ['pid' => $post['id']]).'#p'.$post['id'],
                __('Post del redirect')
            );
        }
    }

    //
    // Delete a single post
    //
    public static function delete($post_id, $topic_id)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
            ->select_many('id', 'poster', 'posted')
            ->where('topic_id', $topic_id)
            ->order_by_desc('id')
            ->limit(2)
            ->find_many();

        $last_id = $i = 0;
        foreach ($result as $cur_result) {
            if ($i == 0) {
                $last_id = $cur_result['id'];
            } else {
                $second_last_id = $cur_result['id'];
                $second_poster = $cur_result['poster'];
                $second_posted = $cur_result['posted'];
            }
            ++$i;
        }

        // Delete the post
        \ORM::for_table(ORM_TABLE_PREFIX.'posts')
            ->where('id', $post_id)
            ->find_one()
            ->delete();

        $search = new \RunBB\Core\Search();
        $search->strip_search_index($post_id);

        // Count number of replies in the topic
        $num_replies = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->where('topic_id', $topic_id)->count() - 1;

        // If the message we deleted is the most recent in the topic (at the end of the topic)
        if ($last_id == $post_id) {
            // If there is a $second_last_id there is more than 1 reply to the topic
            if (isset($second_last_id)) {
                $update_topic = [
                    'last_post'  => $second_posted,
                    'last_post_id'  => $second_last_id,
                    'last_poster'  => $second_poster,
                    'num_replies'  => $num_replies,
                ];
                \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                    ->where('id', $topic_id)
                    ->find_one()
                    ->set($update_topic)
                    ->save();
            } else {
                // We deleted the only reply, so now last_post/last_post_id/last_poster
                // is posted/id/poster from the topic itself
                \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                    ->where('id', $topic_id)
                    ->find_one()
                    ->set_expr('last_post', 'posted')
                    ->set_expr('last_post_id', 'id')
                    ->set_expr('last_poster', 'poster')
                    ->set('num_replies', $num_replies)
                    ->save();
            }
        } else {
            // Otherwise we just decrement the reply counter
            \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                ->where('id', $topic_id)
                ->find_one()
                ->set('num_replies', $num_replies)
                ->save();
        }
    }

    public function edit_post($id, $can_edit_subject, $post, $cur_post, $is_admmod)
    {
        Container::get('hooks')->fire('model.post.edit_post_start');

        if ($can_edit_subject) {
            // Update the topic and any redirect topics
            $where_topic = [
                ['id' => $cur_post['tid']],
                ['moved_to' => $cur_post['tid']]
            ];

            $query['update_topic'] = [
                'subject' => $post['subject'],
                'sticky'  => $post['stick_topic']
            ];

            $query = \ORM::for_table(ORM_TABLE_PREFIX.'topics')->where_any_is($where_topic)
                                            ->find_one()
                                            ->set($query['update_topic']);

            $query = Container::get('hooks')->fireDB('model.post.edit_post_can_edit_subject', $query);

            $query = $query->save();

            // We changed the subject, so we need to take that into account when we update the search words
            $this->search->update_search_index('edit', $id, $post['message'], $post['subject']);
        } else {
            $this->search->update_search_index('edit', $id, $post['message']);
        }

        // Update the post
        unset($query);
        $query['update_post'] = [
            'message' => $post['message'],
            'hide_smilies'  => $post['hide_smilies']
        ];

        if (!Input::post('silent') || !$is_admmod) {
            $query['update_post']['edited'] = time();
            $query['update_post']['edited_by'] = User::get()->username;
        }

        $query = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->where('id', $id)
                                       ->find_one()
                                       ->set($query['update_post']);
        $query = Container::get('hooks')->fireDB('model.post.edit_post_query', $query);
        $query->save();
    }

    public function insert_report($post_id)
    {
        $post_id = Container::get('hooks')->fire('model.post.insert_report_start', $post_id);

        // Clean up reason from POST
        $reason = Utils::linebreaks(Utils::trim(Input::post('req_reason')));
        if ($reason == '') {
            throw new  RunBBException(__('No reason'), 400);
        } elseif (strlen($reason) > 65535) { // TEXT field can only hold 65535 bytes
            throw new  RunBBException(__('Reason too long'), 400);
        }

        if (User::get()->last_report_sent != '' &&
            (time() - User::get()->last_report_sent) < User::get()->g_report_flood &&
            (time() - User::get()->last_report_sent) >= 0)
        {
            throw new  RunBBException(sprintf(__('Report flood'),
                User::get()->g_report_flood,
                User::get()->g_report_flood - (time() - User::get()->last_report_sent)), 429);
        }

        // Get the topic ID
        $topic = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->select('topic_id')
                                      ->where('id', $post_id);
        $topic = Container::get('hooks')->fireDB('model.post.insert_report_topic_id', $topic);
        $topic = $topic->find_one();

        if (!$topic) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        // Get the subject and forum ID
        $report['select'] = ['subject', 'forum_id'];
        $report = \ORM::for_table(ORM_TABLE_PREFIX.'topics')->select_many($report['select'])
                                        ->where('id', $topic['topic_id']);
        $report = Container::get('hooks')->fireDB('model.post.insert_report_get_subject', $report);
        $report = $report->find_one();

        if (!$report) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        // Should we use the internal report handling?
        if (ForumSettings::get('o_report_method') == '0' || ForumSettings::get('o_report_method') == '2') {
            // Insert the report
            $query['insert'] = [
                'post_id' => $post_id,
                'topic_id'  => $topic['topic_id'],
                'forum_id'  => $report['forum_id'],
                'reported_by'  => User::get()->id,
                'created'  => time(),
                'message'  => $reason,
            ];
            $query = \ORM::for_table(ORM_TABLE_PREFIX.'reports')
                ->create()
                ->set($query['insert']);
            $query = Container::get('hooks')->fireDB('model.post.insert_report_query', $query);
            $query = $query->save();
        }

        // Should we email the report?
        if (ForumSettings::get('o_report_method') == '1' || ForumSettings::get('o_report_method') == '2') {
            // We send it to the complete mailing-list in one swoop
            if (ForumSettings::get('o_mailing_list') != '') {
                // Load the "new report" template
                $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.
                    '/mail_templates/new_report.tpl'));
                $mail_tpl = Container::get('hooks')->fire('model.post.insert_report_mail_tpl', $mail_tpl);

                // The first row contains the subject
                $first_crlf = strpos($mail_tpl, "\n");
                $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                $mail_message = trim(substr($mail_tpl, $first_crlf));

                $mail_subject = str_replace('<forum_id>', $report['forum_id'], $mail_subject);
                $mail_subject = str_replace('<topic_subject>', $report['subject'], $mail_subject);
                $mail_message = str_replace('<username>', User::get()->username, $mail_message);
                $mail_message = str_replace('<post_url>',
                    Router::pathFor('viewPost', ['pid' => $post_id]).'#p'.$post_id, $mail_message);
                $mail_message = str_replace('<reason>', $reason, $mail_message);
                $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);

                $mail_message = Container::get('hooks')->fire('model.post.insert_report_mail_message', $mail_message);

                Container::get('email')->feather_mail(ForumSettings::get('o_mailing_list'),
                    $mail_subject, $mail_message);
            }
        }

        $last_report_sent = \ORM::for_table(ORM_TABLE_PREFIX.'users')->where('id', User::get()->id)
            ->find_one()
            ->set('last_report_sent', time());
        $last_report_sent = Container::get('hooks')->fireDB('model.post.insert_last_report_sent', $last_report_sent);
        $last_report_sent = $last_report_sent->save();

        return Router::redirect(Router::pathFor('viewPost', ['pid' => $post_id]).'#p'.$post_id, __('Report redirect'));
    }

    public function get_info_report($post_id)
    {
        $post_id = Container::get('hooks')->fire('model.post.get_info_report_start', $post_id);

        $cur_post['select'] = ['fid' => 'f.id', 'f.forum_name', 'tid' => 't.id', 't.subject'];
//        $cur_post['where'] = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );

        $cur_post = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                        ->table_alias('p')
                        ->select_many($cur_post['select'])
                        ->inner_join(ORM_TABLE_PREFIX.'topics', ['t.id', '=', 'p.topic_id'], 't')
                        ->inner_join(ORM_TABLE_PREFIX.'forums', ['f.id', '=', 't.forum_id'], 'f')
//                        ->left_outer_join('forum_perms', array('fp.forum_id', '=', 'f.id'), 'fp')
//                        ->left_outer_join('forum_perms', array('fp.group_id', '=', User::get()->g_id), null, true)
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(fp.forum_id=f.id AND fp.group_id='. User::get()->g_id.')',
                'fp'
            )
                        ->where_raw('fp.read_forum IS NULL OR fp.read_forum=1')
                        ->where('p.id', $post_id);
        $cur_post = Container::get('hooks')->fireDB('model.post.get_info_report_query', $cur_post);
        $cur_post = $cur_post->find_one();

        if (!$cur_post) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        $cur_post = Container::get('hooks')->fire('model.post.get_info_report', $cur_post);

        return $cur_post;
    }

    // Insert a reply
    public function insert_reply($post, $tid, $cur_posting, $is_subscribed)
    {
        $new = [];

        $new = Container::get('hooks')->fireDB(
            'model.post.insert_reply_start',
            $new,
            $post,
            $tid,
            $cur_posting,
            $is_subscribed
        );

        if (!User::get()->is_guest) {
            $new['tid'] = $tid;

            // Insert the new post
            $query['insert'] = [
                'poster' => $post['username'],
                'poster_id' => User::get()->id,
                'poster_ip' => Utils::getIp(),
                'message' => $post['message'],
                'hide_smilies' => $post['hide_smilies'],
                'posted'  => $post['time'],
                'topic_id'  => $tid,
            ];

            $query = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                        ->create()
                        ->set($query['insert']);
            $query = Container::get('hooks')->fireDB('model.post.insert_reply_guest_query', $query);
            $query = $query->save();

            $new['pid'] = \ORM::get_db()->lastInsertId(ForumSettings::get('db_prefix').'posts');

            // To subscribe or not to subscribe, that ...
            if (ForumSettings::get('o_topic_subscriptions') == '1') {
                // ... is the question
                // Let's do it
                if (isset($post['subscribe']) && $post['subscribe'] && !$is_subscribed) {
                    $subscription['insert'] = [
                        'user_id'   =>  User::get()->id,
                        'topic_id'  =>  $tid
                    ];

                    $subscription = \ORM::for_table(ORM_TABLE_PREFIX.'topic_subscriptions')
                                        ->create()
                                        ->set($subscription['insert']);
                    $subscription = Container::get('hooks')->fireDB(
                        'model.post.insert_reply_subscription', $subscription);
                    $subscription = $subscription->save();

                // We reply and we don't want to be subscribed anymore
                } elseif ($post['subscribe'] == '0' && $is_subscribed) {
                    $unsubscription = \ORM::for_table(ORM_TABLE_PREFIX.'topic_subscriptions')
                                        ->where('user_id', User::get()->id)
                                        ->where('topic_id', $tid);
                    $unsubscription = Container::get('hooks')->fireDB(
                        'model.post.insert_reply_unsubscription', $unsubscription);
                    $unsubscription = $unsubscription->delete_many();
                }
            }
        } else {
            // It's a guest. Insert the new post
            $query['insert'] = [
                'poster' => $post['username'],
                'poster_ip' => Utils::getIp(),
                'message' => $post['message'],
                'hide_smilies' => $post['hide_smilies'],
                'posted'  => $post['time'],
                'topic_id'  => $tid,
            ];

            if (ForumSettings::get('p_force_guest_email') == '1' || $post['email'] != '') {
                $query['insert']['poster_email'] = $post['email'];
            }

            $query = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                        ->create()
                        ->set($query['insert']);
            $query = Container::get('hooks')->fireDB('model.post.insert_reply_member_query', $query);
            $query = $query->save();

            $new['pid'] = \ORM::get_db()->lastInsertId(ForumSettings::get('db_prefix').'posts');
        }

        // Update topic
        $topic['update'] = [
            'last_post' => $post['time'],
            'last_post_id'  => $new['pid'],
            'last_poster'  => $post['username'],
        ];

        $topic = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                    ->where('id', $tid)
                    ->find_one()
                    ->set($topic['update'])
                    ->set_expr('num_replies', 'num_replies+1');
        $topic = Container::get('hooks')->fireDB('model.post.insert_reply_update_query', $topic);
        $topic->save();

        $this->search->update_search_index('post', $new['pid'], $post['message']);

        Forum::update($cur_posting['id']);

        $new = Container::get('hooks')->fireDB('model.post.insert_reply', $new);

        return $new;
    }

    // Send notifications for replies
    public function send_notifications_reply($tid, $cur_posting, $new_pid, $post)
    {
        Container::get('hooks')->fire('model.post.send_notifications_reply_start', $tid, $cur_posting, $new_pid, $post);

        // Get the post time for the previous post in this topic
        $previous_post_time = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
            ->select('posted')
            ->where('topic_id', $tid)
            ->order_by_desc('id');
        $previous_post_time = Container::get('hooks')->fireDB(
            'model.post.send_notifications_reply_previous', $previous_post_time);
        $previous_post_time = $previous_post_time->find_one();
//tdie($previous_post_time->posted);
        // Get any subscribed users that should be notified (banned users are excluded)
//        $result['where'] = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );
        $result['select'] = ['u.id', 'u.email', 'u.notify_with_post', 'u.language'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->table_alias('u')
                    ->select_many($result['select'])
                    ->inner_join(ORM_TABLE_PREFIX.'topic_subscriptions', ['u.id', '=', 's.user_id'], 's')
//                    ->left_outer_join('forum_perms', array('fp.forum_id', '=', $cur_posting['id']), 'fp', true)
//                    ->left_outer_join('forum_perms', array('fp.group_id', '=', 'u.group_id'))
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(fp.group_id=u.group_id AND fp.forum_id='. $cur_posting['id'].')',
                'fp'
            )
                    ->left_outer_join(ORM_TABLE_PREFIX.'online', ['u.id', '=', 'o.user_id'], 'o')
                    ->left_outer_join(ORM_TABLE_PREFIX.'bans', ['u.username', '=', 'b.username'], 'b')
                    ->where_raw('COALESCE(o.logged, u.last_visit)>'.$previous_post_time->posted)
                    ->where_null('b.username')
                    ->where_raw('fp.read_forum IS NULL OR fp.read_forum=1')
                    ->where('s.topic_id', $tid)
                    ->where_not_equal('u.id', User::get()->id);
        $result = Container::get('hooks')->fireDB('model.post.send_notifications_reply_query', $result);
        $result = $result->find_many();

        if ($result) {
            $notification_emails = [];

            $censored_message = Utils::trim(Utils::censor($post['message']));

            if (ForumSettings::get('o_censoring') == '1') {
                $cleaned_message = Container::get('email')->bbcode2email($censored_message, -1);
            } else {
                $cleaned_message = Container::get('email')->bbcode2email($post['message'], -1);
            }

            // Loop through subscribed users and send emails
            foreach ($result as $cur_subscriber) {
                // Is the subscription email for $cur_subscriber['language'] cached or not?
                if (!isset($notification_emails[$cur_subscriber['language']])) {
                    if (file_exists(ForumEnv::get('FORUM_ROOT').'lang/'.$cur_subscriber['language'].
                        '/mail_templates/new_reply.tpl')) {
                        // Load the "new reply" template
                        $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.
                            $cur_subscriber['language'].'/mail_templates/new_reply.tpl'));
                        $mail_tpl = Container::get('hooks')->fire(
                            'model.post.send_notifications_reply_mail_tpl', $mail_tpl);

                        // Load the "new reply full" template (with post included)
                        $mail_tpl_full = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.
                            $cur_subscriber['language'].'/mail_templates/new_reply_full.tpl'));
                        $mail_tpl_full = Container::get('hooks')->fire(
                            'model.post.send_notifications_reply_mail_tpl_full', $mail_tpl_full);

                        // The first row contains the subject (it also starts with "Subject:")
                        $first_crlf = strpos($mail_tpl, "\n");
                        $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                        $mail_subject = Container::get('hooks')->fire(
                            'model.post.send_notifications_reply_mail_subject', $mail_subject);
                        $mail_message = trim(substr($mail_tpl, $first_crlf));

                        $first_crlf = strpos($mail_tpl_full, "\n");
                        $mail_subject_full = trim(substr($mail_tpl_full, 8, $first_crlf-8));
                        $mail_message_full = trim(substr($mail_tpl_full, $first_crlf));

                        $mail_subject = str_replace('<topic_subject>', $cur_posting['subject'], $mail_subject);
                        $mail_message = str_replace('<topic_subject>', $cur_posting['subject'], $mail_message);
                        $mail_message = str_replace('<replier>', $post['username'], $mail_message);
                        $mail_message = str_replace('<post_url>',
                            Router::pathFor('viewPost', ['pid' => $new_pid]).'#p'.$new_pid, $mail_message);
                        $mail_message = str_replace('<unsubscribe_url>',
                            Router::pathFor('unsubscribeTopic', ['id' => $tid]), $mail_message);
                        $mail_message = str_replace('<board_mailer>',
                            ForumSettings::get('o_board_title'), $mail_message);
                        $mail_message = Container::get('hooks')->fire(
                            'model.post.send_notifications_reply_mail_message', $mail_message);

                        $mail_subject_full = str_replace('<topic_subject>',
                            $cur_posting['subject'], $mail_subject_full);
                        $mail_message_full = str_replace('<topic_subject>',
                            $cur_posting['subject'], $mail_message_full);
                        $mail_message_full = str_replace('<replier>', $post['username'], $mail_message_full);
                        $mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
                        $mail_message_full = str_replace('<post_url>',
                            Router::pathFor('viewPost', ['pid' => $new_pid]).'#p'.$new_pid, $mail_message_full);
                        $mail_message_full = str_replace('<unsubscribe_url>',
                            Router::pathFor('unsubscribeTopic', ['id' => $tid]), $mail_message_full);
                        $mail_message_full = str_replace('<board_mailer>',
                            ForumSettings::get('o_board_title'), $mail_message_full);
                        $mail_message_full = Container::get('hooks')->fire(
                            'model.post.send_notifications_reply_mail_message_full', $mail_message_full);

                        $notification_emails[$cur_subscriber['language']][0] = $mail_subject;
                        $notification_emails[$cur_subscriber['language']][1] = $mail_message;
                        $notification_emails[$cur_subscriber['language']][2] = $mail_subject_full;
                        $notification_emails[$cur_subscriber['language']][3] = $mail_message_full;

                        $mail_subject = $mail_message = $mail_subject_full = $mail_message_full = null;
                    }
                }

                // We have to double check here because the templates could be missing
                if (isset($notification_emails[$cur_subscriber['language']])) {
                    if ($cur_subscriber['notify_with_post'] == '0') {
                        Container::get('email')->feather_mail($cur_subscriber['email'],
                            $notification_emails[$cur_subscriber['language']][0],
                            $notification_emails[$cur_subscriber['language']][1]);
                    } else {
                        Container::get('email')->feather_mail($cur_subscriber['email'],
                            $notification_emails[$cur_subscriber['language']][2],
                            $notification_emails[$cur_subscriber['language']][3]);
                    }
                }
            }

            Container::get('hooks')->fire('model.post.send_notifications_reply');

            unset($cleaned_message);
        }
    }

    // Insert a topic
    public function insert_topic($post, $fid)
    {
        $new = [];

        $new = Container::get('hooks')->fireDB('model.post.insert_topic_start', $new, $post, $fid);

        // Create the topic
        $topic['insert'] = [
            'poster' => $post['username'],
            'subject' => $post['subject'],
            'posted'  => $post['time'],
            'last_post'  => $post['time'],
            'last_poster'  => $post['username'],
            'sticky'  => $post['stick_topic'],
            'forum_id'  => $fid,
        ];

        $topic = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                    ->create()
                    ->set($topic['insert']);
        $topic = Container::get('hooks')->fireDB('model.post.insert_topic_create', $topic);
        $topic = $topic->save();

        $new['tid'] = \ORM::get_db()->lastInsertId(ForumSettings::get('db_prefix').'topics');

        if (!User::get()->is_guest) {
            // To subscribe or not to subscribe, that ...
            if (ForumSettings::get('o_topic_subscriptions') == '1' && $post['subscribe']) {
                $subscription['insert'] = [
                    'user_id'   =>  User::get()->id,
                    'topic_id'  =>  $new['tid']
                ];

                $subscription = \ORM::for_table(ORM_TABLE_PREFIX.'topic_subscriptions')
                                    ->create()
                                    ->set($subscription['insert']);
                $subscription = Container::get('hooks')->fireDB('model.post.insert_topic_subscription_member',
                    $subscription);
                $subscription = $subscription->save();
            }

            // Create the post ("topic post")
            $query['insert'] = [
                'poster' => $post['username'],
                'poster_id' => User::get()->id,
                'poster_ip' => Utils::getIp(),
                'message' => $post['message'],
                'hide_smilies' => $post['hide_smilies'],
                'posted'  => $post['time'],
                'topic_id'  => $new['tid'],
            ];

            $query = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                        ->create()
                        ->set($query['insert']);
            $query = Container::get('hooks')->fireDB('model.post.insert_topic_post_member', $query);
            $query = $query->save();
        } else {
            // It's a guest
            // Create the post ("topic post")
            $query['insert'] = [
                'poster' => $post['username'],
                'poster_ip' => Utils::getIp(),
                'message' => $post['message'],
                'hide_smilies' => $post['hide_smilies'],
                'posted'  => $post['time'],
                'topic_id'  => $new['tid'],
            ];

            if (ForumSettings::get('p_force_guest_email') == '1' || $post['email'] != '') {
                $query['poster_email'] = $post['email'];
            }

            $query = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                ->create()
                ->set($query['insert']);
            $query = Container::get('hooks')->fireDB('model.post.insert_topic_post_member', $query);
            $query = $query->save();
        }
        $new['pid'] = \ORM::get_db()->lastInsertId(ForumSettings::get('db_prefix').'topics');

        // Update the topic with last_post_id
        unset($topic);
        $topic['update'] = [
            'last_post_id'  =>  $new['pid'],
            'first_post_id' =>  $new['pid'],
        ];

        $topic = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                    ->where('id', $new['tid'])
                    ->find_one()
                    ->set($topic['update']);
        $topic = Container::get('hooks')->fireDB('model.post.insert_topic_post_topic', $topic);
        $topic->save();

        $this->search->update_search_index('post', $new['pid'], $post['message'], $post['subject']);

        Forum::update($fid);

        $new = Container::get('hooks')->fireDB('model.post.insert_topic', $new);

        return $new;
    }

    // Send notifications for new topics
    public function send_notifications_new_topic($post, $cur_posting, $new_tid)
    {
        Container::get('hooks')->fire('model.post.send_notifications_new_topic_start', $post, $cur_posting, $new_tid);

        // Get any subscribed users that should be notified (banned users are excluded)
//        $result['where'] = array(
//            array('fp.read_forum' => 'IS NULL'),
//            array('fp.read_forum' => '1')
//        );
        $result['select'] = ['u.id', 'u.email', 'u.notify_with_post', 'u.language'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->table_alias('u')
                    ->select_many($result['select'])
                    ->inner_join(ORM_TABLE_PREFIX.'forum_subscriptions', ['u.id', '=', 's.user_id'], 's')
//                    ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.forum_id', '=', $cur_posting['id']),
// 'fp', true)
//                    ->left_outer_join(ORM_TABLE_PREFIX.'forum_perms', array('fp.group_id', '=', 'u.group_id'))
            ->left_outer_join(
                ORM_TABLE_PREFIX.'forum_perms',
                '(fp.group_id=u.group_id AND fp.forum_id='.$cur_posting['id'].')',
                'fp'
            )
                    ->left_outer_join(ORM_TABLE_PREFIX.'bans', ['u.username', '=', 'b.username'], 'b')
                    ->where_null('b.username')
//                    ->where_any_is($result['where'])
                ->where_raw('fp.read_forum IS NULL OR fp.read_forum=1')
                    ->where('s.forum_id', $cur_posting['id'])
                    ->where_not_equal('u.id', User::get()->id);
        $result = Container::get('hooks')->fireDB('model.post.send_notifications_new_topic_query', $result);
        $result = $result->find_many();

        if ($result) {
            $notification_emails = [];

            $censored_message = Utils::trim(Utils::censor($post['message']));
            $censored_subject = Utils::trim(Utils::censor($post['subject']));

            if (ForumSettings::get('o_censoring') == '1') {
                $cleaned_message = Container::get('email')->bbcode2email($censored_message, -1);
            } else {
                $cleaned_message = Container::get('email')->bbcode2email($post['message'], -1);
            }

            // Loop through subscribed users and send emails
            foreach ($result as $cur_subscriber) {
                // Is the subscription email for $cur_subscriber['language'] cached or not?
                if (!isset($notification_emails[$cur_subscriber['language']])) {
                    if (file_exists(ForumEnv::get('FORUM_ROOT').'lang/'.$cur_subscriber['language'].
                        '/mail_templates/new_topic.tpl')) {
                        // Load the "new topic" template
                        $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.
                            $cur_subscriber['language'].'/mail_templates/new_topic.tpl'));
                        $mail_tpl = Container::get('hooks')->fire('model.post.send_notifications_new_topic_mail_tpl',
                            $mail_tpl);

                        // Load the "new topic full" template (with post included)
                        $mail_tpl_full = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.
                            $cur_subscriber['language'].'/mail_templates/new_topic_full.tpl'));

                        // The first row contains the subject (it also starts with "Subject:")
                        $first_crlf = strpos($mail_tpl, "\n");
                        $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                        $mail_message = trim(substr($mail_tpl, $first_crlf));

                        $first_crlf = strpos($mail_tpl_full, "\n");
                        $mail_subject_full = trim(substr($mail_tpl_full, 8, $first_crlf-8));
                        $mail_message_full = trim(substr($mail_tpl_full, $first_crlf));

                        $mail_subject = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_subject);
                        $mail_message = str_replace('<topic_subject>',
                            ForumSettings::get('o_censoring') == '1' ? $censored_subject : $post['subject'],
                            $mail_message);
                        $mail_message = str_replace('<forum_name>', $cur_posting['forum_name'], $mail_message);
                        $mail_message = str_replace('<poster>', $post['username'], $mail_message);
                        $mail_message = str_replace('<topic_url>',
                            Router::pathFor('Topic', ['id' => $new_tid]), $mail_message);
                        $mail_message = str_replace('<unsubscribe_url>',
                            Router::pathFor('unsubscribeTopic', ['id' => $cur_posting['id']]), $mail_message);
                        $mail_message = str_replace('<board_mailer>',
                            ForumSettings::get('o_board_title'), $mail_message);
                        $mail_message = Container::get('hooks')->fire(
                            'model.post.send_notifications_new_topic_mail_message', $mail_message);

                        $mail_subject_full = str_replace('<forum_name>',
                            $cur_posting['forum_name'], $mail_subject_full);
                        $mail_message_full = str_replace('<topic_subject>',
                            ForumSettings::get('o_censoring') == '1' ? $censored_subject : $post['subject'],
                            $mail_message_full);
                        $mail_message_full = str_replace('<forum_name>',
                            $cur_posting['forum_name'], $mail_message_full);
                        $mail_message_full = str_replace('<poster>', $post['username'], $mail_message_full);
                        $mail_message_full = str_replace('<message>', $cleaned_message, $mail_message_full);
                        $mail_message_full = str_replace('<topic_url>',
                            Router::pathFor('Topic', ['id' => $new_tid]), $mail_message_full);
                        $mail_message_full = str_replace('<unsubscribe_url>',
                            Router::pathFor('unsubscribeTopic', ['id' => $tid]), $mail_message_full);
                        $mail_message_full = str_replace('<board_mailer>',
                            ForumSettings::get('o_board_title'), $mail_message_full);
                        $mail_message_full = Container::get('hooks')->fire(
                            'model.post.send_notifications_new_topic_mail_message_full', $mail_message_full);

                        $notification_emails[$cur_subscriber['language']][0] = $mail_subject;
                        $notification_emails[$cur_subscriber['language']][1] = $mail_message;
                        $notification_emails[$cur_subscriber['language']][2] = $mail_subject_full;
                        $notification_emails[$cur_subscriber['language']][3] = $mail_message_full;
                    }
                }

                // We have to double check here because the templates could be missing
                if (isset($notification_emails[$cur_subscriber['language']])) {
                    if ($cur_subscriber['notify_with_post'] == '0') {
                        Container::get('email')->feather_mail($cur_subscriber['email'],
                            $notification_emails[$cur_subscriber['language']][0],
                            $notification_emails[$cur_subscriber['language']][1]);
                    } else {
                        Container::get('email')->feather_mail($cur_subscriber['email'],
                            $notification_emails[$cur_subscriber['language']][2],
                            $notification_emails[$cur_subscriber['language']][3]);
                    }
                }
            }

            Container::get('hooks')->fire('model.post.send_notifications_new_topic');

            unset($cleaned_message);
        }
    }

    // Warn the admin if a banned user posts
    public function warn_banned_user($post, $new_pid)
    {
        Container::get('hooks')->fire('model.post.warn_banned_user_start', $post, $new_pid);

        // Load the "banned email post" template
        $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.
            '/mail_templates/banned_email_post.tpl'));
        $mail_tpl = Container::get('hooks')->fire('model.post.warn_banned_user_mail_tpl', $mail_tpl);

        // The first row contains the subject
        $first_crlf = strpos($mail_tpl, "\n");
        $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
        $mail_message = trim(substr($mail_tpl, $first_crlf));

        $mail_message = str_replace('<username>', $post['username'], $mail_message);
        $mail_message = str_replace('<email>', $post['email'], $mail_message);
        $mail_message = str_replace('<post_url>', Router::pathFor('viewPost', ['pid' => $new_pid]).'#p'.
            $new_pid, $mail_message);
        $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);
        $mail_message = Container::get('hooks')->fire('model.post.warn_banned_user_mail_message', $mail_message);

        Container::get('email')->feather_mail(ForumSettings::get('o_mailing_list'), $mail_subject, $mail_message);
    }

    // Increment post count, change group if needed
    public function increment_post_count($post, $new_tid)
    {
        Container::get('hooks')->fire('model.post.increment_post_count_start', $post, $new_tid);

        if (!User::get()->is_guest) {
            $increment = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                            ->where('id', User::get()->id)
                            ->find_one()
                            ->set('last_post', $post['time'])
                            ->set_expr('num_posts', 'num_posts+1');
            $increment = Container::get('hooks')->fireDB('model.post.increment_post_count_query', $increment);
            $increment = $increment->save();

            // Promote this user to a new group if enabled
            if (User::get()->g_promote_next_group != 0 &&
                User::get()->num_posts + 1 >= User::get()->g_promote_min_posts) {
                $new_group_id = User::get()->g_promote_next_group;
                $promote = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                            ->where('id', User::get()->id)
                            ->find_one()
                            ->set('group_id', $new_group_id);
                $promote = Container::get('hooks')->fireDB('model.post.increment_post_count_query', $promote);
                $promote = $promote->save();
            }

            // Topic tracking stuff...
            $tracked_topics = Track::get_tracked_topics();
            $tracked_topics['topics'][$new_tid] = time();
            Track::set_tracked_topics($tracked_topics);
        } else {
            // Update the last_post field for guests
            $last_post = \ORM::for_table(ORM_TABLE_PREFIX.'online')
                            ->where('ident', Utils::getIp())
                            ->find_one()
                            ->set('last_post', $post['time']);
            $last_post = Container::get('hooks')->fireDB('model.post.increment_post_count_last_post', $last_post);
            $last_post = $last_post->save();
        }

        Container::get('hooks')->fire('model.post.increment_post_count');
    }

    /**
     * Split text into chunks ($inside contains all text inside $start and $end,
     * and $outside contains all text outside)
     * @param $text
     * @param $start
     * @param $end
     * @param bool $retab
     * @return array
     */
    public function split_text($text, $start, $end, $retab = true)
    {
        $result = [0 => [], 1 => []]; // 0 = inside, 1 = outside

        $result = Container::get('hooks')->fire('model.post.split_text_start', $result, $text, $start, $end, $retab);

        // split the text into parts
        $parts = preg_split('%'.preg_quote($start, '%').'(.*)'.preg_quote($end, '%').'%Us', $text, -1,
            PREG_SPLIT_DELIM_CAPTURE);
        $num_parts = count($parts);

        // preg_split results in outside parts having even indices, inside parts having odd
        for ($i = 0; $i < $num_parts; $i++) {
            $result[1 - ($i % 2)][] = $parts[$i];
        }

        if (ForumSettings::get('o_indent_num_spaces') != 8 && $retab) {
            $spaces = str_repeat(' ', ForumSettings::get('o_indent_num_spaces'));
            $result[1] = str_replace("\t", $spaces, $result[1]);
        }

        $result = Container::get('hooks')->fire('model.post.split_text_start', $result);

        return $result;
    }

    // If we are quoting a message
    public function get_quote_message($qid, $tid)
    {
        $retVal = '';
        // fire hook
        $retVal = Container::get('hooks')->fire('model.post.begin_quote_message', $retVal, $qid, $tid);

        $quote = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->select_many(['poster', 'message'])
                     ->where('id', $qid)
                     ->where('topic_id', $tid);
        $quote = Container::get('hooks')->fireDB('model.post.get_quote_message_query', $quote);
        $quote = $quote->find_one();

        if (!$quote) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        // If the message contains a code tag we have to split it up (text within [code][/code] shouldn't be touched)
//        if (strpos($quote->message, '[code]') !== false && strpos($quote->message, '[/code]') !== false) {
//            list($inside, $outside) = split_text($quote->message, '[code]', '[/code]');
//
//            $quote->message = implode("\1", $outside);
//        }

        // Remove [img] tags from quoted message
        $quote->message = preg_replace(
            '%\[img(?:=(?:[^\[]*?))?\]((ht|f)tps?://)([^\s<"]*?)\[/img\]%U',
            '\1\3',
            $quote->message
        );

        // If we split up the message before we have to concatenate it together again (code tags)
//        if (isset($inside)) {
//            $outside = explode("\1", $quote->message);
//            $quote->message = '';
//
//            $num_tokens = count($outside);
//            for ($i = 0; $i < $num_tokens; ++$i) {
//                $quote->message .= $outside[$i];
//                if (isset($inside[$i])) {
//                    $quote->message .= '[code]'.$inside[$i].'[/code]';
//                }
//            }
//
//            unset($inside);
//        }

        if (ForumSettings::get('o_censoring') == '1') {
            $quote->message = Utils::censor($quote->message);
        }

        $quote->message = Utils::escape($quote->message);

        if (ForumSettings::get('p_message_bbcode') == '1') {    // Sanitize username for inclusion within QUOTE
            // BBCode attribute.
                //   This is a bit tricky because a username can have any "special"
                //   characters such as backslash \ square brackets [] and quotes '".
//                if (preg_match('/[[\]\'"]/S', $quote->poster)) {
//                    // Check if we need to quote it.
//                    // Post has special chars. Escape escapes and quotes then wrap in quotes.
//                    if (strpos($quote->poster, '"') !== false && strpos($quote->poster, '\'') === false)
//                    { // If there are double quotes but no single quotes, use single quotes,
//                        $quote->poster = Utils::escape(str_replace('\\', '\\\\', $quote->poster));
//                        $quote->poster = '\''. $quote->poster .'#'. $qid .'\'';
//                    } else { // otherwise use double quotes.
//                        $quote->poster = Utils::escape(str_replace(array('\\', '"'), array('\\\\', '\\"'),
//                            $quote->poster));
//                        $quote->poster = '"'. $quote->poster .'#'. $qid .'"';
//                    }
//                } else {
//                    $quote->poster = $quote->poster .'#'. $qid;
//                }
//            $retVal = '[quote='. $quote->poster .']'.$quote->message.'[/quote]'."\n";

            $retVal .= '> --- **'.$quote->poster.'** *['.__('wrote').']('.
                Router::pathFor('viewPost', ['pid' => $qid]).'#p'.$qid.')*'."\n";

            // ^ - beginning of a string. m - multiline.
            $retVal .= preg_replace('/^/m', '>', $quote->message);
        } else {
            $quote->message .= preg_replace('/^/m', '>', $quote->message);
            $retVal = '> --- '.$quote->poster.' '.__('wrote')."\n".$quote->message."\n";
        }

        $retVal = Container::get('hooks')->fire('model.post.finish_quote_message', $retVal);

        return $retVal;
    }

    // Get the current state of checkboxes
    public function get_checkboxes($fid, $is_admmod, $is_subscribed)
    {
        Container::get('hooks')->fire('model.post.get_checkboxes_start', $fid, $is_admmod, $is_subscribed);

        $cur_index = 1;

        $checkboxes = [];
        if ($fid && $is_admmod) {
            $checkboxes[] = '<label><input type="checkbox" name="stick_topic" value="1" tabindex="'.
                ($cur_index++).'"'.(Input::post('stick_topic') ? ' checked="checked"' : '').' />'.
                __('Stick topic').'<br /></label>';
        }

        if (!User::get()->is_guest) {
            if (ForumSettings::get('o_smilies') == '1') {
                $checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.
                    ($cur_index++).'"'.(Input::post('hide_smilies') ? ' checked="checked"' : '').' />'.
                    __('Hide smilies').'<br /></label>';
            }

            if (ForumSettings::get('o_topic_subscriptions') == '1') {
                $subscr_checked = false;

                // If it's a preview
                if (Input::post('preview')) {
                    $subscr_checked = (Input::post('subscribe')) ? true : false;
                } // If auto subscribed
                elseif (User::get()->auto_notify) {
                    $subscr_checked = true;
                } // If already subscribed to the topic
                elseif ($is_subscribed) {
                    $subscr_checked = true;
                }

                $checkboxes[] = '<label><input type="checkbox" name="subscribe" value="1" tabindex="'.
                    ($cur_index++).'"'.($subscr_checked ? ' checked="checked"' : '').' />'.
                    ($is_subscribed ? __('Stay subscribed') : __('Subscribe')).'<br /></label>';
            }
        } elseif (ForumSettings::get('o_smilies') == '1') {
            $checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.
                ($cur_index++).'"'.(Input::post('hide_smilies') ? ' checked="checked"' : '').' />'.
                __('Hide smilies').'<br /></label>';
        }

        $checkboxes = Container::get('hooks')->fire('model.post.get_checkboxes', $checkboxes);

        return $checkboxes;
    }

    public function get_edit_checkboxes($can_edit_subject, $is_admmod, $cur_post, $cur_index)
    {
        Container::get('hooks')->fire(
            'model.post.get_checkboxes_start',
            $can_edit_subject,
            $is_admmod,
            $cur_post,
            $cur_index
        );

        $checkboxes = [];

        if ($can_edit_subject && $is_admmod) {
            if (Input::post('stick_topic') || $cur_post['sticky'] == '1') {
                $checkboxes[] = '<label><input type="checkbox" name="stick_topic" value="1" '.
                    'checked="checked" tabindex="'.($cur_index++).'" />'.__('Stick topic').'<br /></label>';
            } else {
                $checkboxes[] = '<label><input type="checkbox" name="stick_topic" value="1" tabindex="'.
                    ($cur_index++).'" />'.__('Stick topic').'<br /></label>';
            }
        }

        if (ForumSettings::get('o_smilies') == '1') {
            if (Input::post('hide_smilies') || $cur_post['hide_smilies'] == '1') {
                $checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" '.
                    'checked="checked" tabindex="'.($cur_index++).'" />'.__('Hide smilies').'<br /></label>';
            } else {
                $checkboxes[] = '<label><input type="checkbox" name="hide_smilies" value="1" tabindex="'.
                    ($cur_index++).'" />'.__('Hide smilies').'<br /></label>';
            }
        }

        if ($is_admmod) {
            if (Request::isPost() && Input::post('silent') || Request::isPost() == '') {
                $checkboxes[] = '<label><input type="checkbox" name="silent" value="1" tabindex="'.
                    ($cur_index++).'" checked="checked" />'.__('Silent edit').'<br /></label>';
            } else {
                $checkboxes[] = '<label><input type="checkbox" name="silent" value="1" tabindex="'.
                    ($cur_index++).'" />'.__('Silent edit').'<br /></label>';
            }
        }

        $checkboxes = Container::get('hooks')->fire('model.post.get_checkboxes', $checkboxes);

        return $checkboxes;
    }

    // Display the topic review if needed
    public function topic_review($tid)
    {
        $post_data = [];

        $post_data = Container::get('hooks')->fire('model.post.topic_review_start', $post_data, $tid);

        $select_topic_review = ['poster', 'message', 'hide_smilies', 'posted'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')->select_many($select_topic_review)
                    ->where('topic_id', $tid)
                    ->order_by_desc('id');
        $result = Container::get('hooks')->fire('model.post.topic_review_query', $result);
        $result = $result->find_many();

        foreach ($result as $cur_post) {
            $cur_post['message'] = Container::get('parser')->parseMessage(
                $cur_post['message'],
                $cur_post['hide_smilies']
            );
            $post_data[] = $cur_post;
        }

        $post_data = Container::get('hooks')->fire('model.post.topic_review', $post_data);

        return $post_data;
    }

    public function display_ip_address($pid)
    {
        $pid = Container::get('hooks')->fire('model.post.display_ip_address_post_start', $pid);

        $ip = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
            ->select('poster_ip')
            ->where('id', $pid);
        $ip = $ip->find_one()->poster_ip;
        $ip = Container::get('hooks')->fireDB('model.post.display_ip_address_post_query', $ip);

        if (!$ip) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        $ip = Container::get('hooks')->fire('model.post.display_ip_address_post', $ip);

        throw new  RunBBException(sprintf(__('Host info 1'), $ip).'<br />'.sprintf(__('Host info 2'),
                @gethostbyaddr($ip)).'<br /><br /><a href="'.Router::pathFor('usersIpShow', ['ip' => $ip]).'">'.
            __('Show more users').'</a>');
    }
}
