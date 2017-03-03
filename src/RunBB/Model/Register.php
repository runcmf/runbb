<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Core\Url;
use RunBB\Exception\RunBBException;
use RunBB\Core\Random;
use RunBB\Core\Utils;
use RunBB\Model\Auth as AuthModel;

class Register
{
    public function checkForErrors()
    {
        $user = [];
        $user['errors'] = [];

        $user = Container::get('hooks')->fire('model.register.check_for_errors_start', $user);

        // Check that someone from this IP didn't register a user within the last hour (DoS prevention)
        $already_registered = \ORM::forTable(ORM_TABLE_PREFIX.'users')
                                  ->where('registration_ip', Utils::getIp())
                                  ->whereGt('registered', time() - 3600);
        $already_registered = Container::get('hooks')
            ->fireDB('model.register.check_for_errors_ip_query', $already_registered);
        $already_registered = $already_registered->findOne();

        if ($already_registered) {
            throw new RunBBException(__('Registration flood'), 429);
        }


        $user['username'] = Utils::trim(Input::post('req_user'));
        $user['email1'] = strtolower(Utils::trim(Input::post('req_email1')));

        if (ForumSettings::get('o_regs_verify') == '1') {
            $email2 = strtolower(Utils::trim(Input::post('req_email2')));

            $user['password1'] = Random::pass(12);
            $password2 = $user['password1'];
        } else {
            $user['password1'] = Utils::trim(Input::post('req_password1'));
            $password2 = Utils::trim(Input::post('req_password2'));
        }

        // Validate username and passwords
        $profile = new \RunBB\Model\Profile();
        $user['errors'] = $profile->checkUsername($user['username'], $user['errors']);

        if (Utils::strlen($user['password1']) < 6) {
            $user['errors'][] = __('Pass too short');
        } elseif ($user['password1'] != $password2) {
            $user['errors'][] = __('Pass not match');
        }

        // Antispam feature
        $lang_antispam_questions = require ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.'/antispam.php';
        $question = Input::post('captcha_q') ? trim(Input::post('captcha_q')) : '';
        $answer = Input::post('captcha') ? strtoupper(trim(Input::post('captcha'))) : '';
        $lang_antispam_questions_array = [];

        foreach ($lang_antispam_questions as $k => $v) {
            $lang_antispam_questions_array[md5($k)] = strtoupper($v);
        }
        if (empty($lang_antispam_questions_array[$question]) || $lang_antispam_questions_array[$question] != $answer) {
            $user['errors'][] = __('Robot test fail');
        }

        // Validate email
        if (!Container::get('email')->isValidEmail($user['email1'])) {
            $user['errors'][] = __('Invalid email');
        } elseif (ForumSettings::get('o_regs_verify') == '1' && $user['email1'] != $email2) {
            $user['errors'][] = __('Email not match');
        }

        // Check if it's a banned email address
        if (Container::get('email')->isBannedEmail($user['email1'])) {
            if (ForumSettings::get('p_allow_banned_email') == '0') {
                $user['errors'][] = __('Banned email');
            }
            $user['banned_email'] = 1; // Used later when we send an alert email
        }

        // Check if someone else already has registered with that email address
        $dupe_list = [];

        $dupe_mail = \ORM::forTable(ORM_TABLE_PREFIX.'users')
                        ->select('username')
                        ->where('email', $user['email1']);
        $dupe_mail = Container::get('hooks')->fireDB('model.register.check_for_errors_dupe', $dupe_mail);
        $dupe_mail = $dupe_mail->findMany();

        if ($dupe_mail) {
            if (ForumSettings::get('p_allow_dupe_email') == '0') {
                $user['errors'][] = __('Dupe email');
            }

            foreach ($dupe_mail as $cur_dupe) {
                $dupe_list[] = $cur_dupe['username'];
            }
        }

        // Make sure we got a valid language string
        if (Input::post('language')) {
            $user['language'] = preg_replace('%[\.\\\/]%', '', Input::post('language'));
            if (Utils::recursiveArraySearch($user['language'], Lang::getList()) === false) {
                throw new  RunBBException('Language you choose not exists', 500);
            }
        } else {
            $user['language'] = ForumSettings::get('o_default_lang');
        }

        $user = Container::get('hooks')->fire('model.register.check_for_errors', $user);

        return $user;
    }

    public function insertUser($user)
    {
        $user = Container::get('hooks')->fire('model.register.insert_user_start', $user);

        // Insert the new user into the database. We do this now to get the last inserted ID for later use
        $now = time();

        $intial_group_id = (ForumSettings::get('o_regs_verify') == '0') ?
            ForumSettings::get('o_default_user_group') : ForumEnv::get('FEATHER_UNVERIFIED');
        $pass = $user['password1'];

        // Add the user
        $user['insert'] = [
            'username'        => $user['username'],
            'group_id'        => $intial_group_id,
            'password'        => Random::hash($pass),
            'email'           => $user['email1'],
            'email_setting'   => ForumSettings::get('o_default_email_setting'),
            'timezone'        => ForumSettings::get('o_default_timezone'),
            'dst'             => 0,
            'language'        => $user['language'],
            'style'           => ForumSettings::get('o_default_style'),
            'registered'      => $now,
            'registration_ip' => Utils::getIp(),
            'last_visit'      => $now,
        ];

        $user = \ORM::forTable(ORM_TABLE_PREFIX.'users')
                    ->create()
                    ->set($user['insert']);
        $user = Container::get('hooks')->fireDB('model.register.insert_user_query', $user);
        $user->save();

        $new_uid = \ORM::getDb()->lastInsertId(ForumSettings::get('db_prefix').'users');

        // If the mailing list isn't empty, we may need to send out some alerts
        if (ForumSettings::get('o_mailing_list') != '') {
            // If we previously found out that the email was banned
            if (isset($user->banned_email)) {
                // Load the "banned email register" template
                $mail_tpl = Lang::getMailTemplate('banned_email_register')->text;
                $mail_tpl = Container::get('hooks')->fire('model.register.insert_user_banned_mail_tpl', $mail_tpl);

                // The first row contains the subject
                $first_crlf = strpos($mail_tpl, "\n");
                $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                $mail_subject = Container::get('hooks')
                    ->fire('model.register.insert_user_banned_mail_subject', $mail_subject);

                $mail_message = trim(substr($mail_tpl, $first_crlf));
                $mail_message = str_replace('<username>', $user->username, $mail_message);
                $mail_message = str_replace('<email>', $user->email, $mail_message);
                $mail_message = str_replace(
                    '<profile_url>',
                    Url::get(Router::pathFor('userProfile', ['id' => $new_uid])),
                    $mail_message
                );
                $mail_message = str_replace(
                    '<board_mailer>',
                    ForumSettings::get('o_board_title'),
                    $mail_message
                );
                $mail_message = Container::get('hooks')
                    ->fire('model.register.insert_user_banned_mail_message', $mail_message);

                Container::get('email')
                    ->dispatchMail(ForumSettings::get('o_mailing_list'), $mail_subject, $mail_message);
            }

            // If we previously found out that the email was a dupe
            if (!empty($dupe_list)) {
                // Load the "dupe email register" template
                $mail_tpl = Lang::getMailTemplate('dupe_email_register')->text;
                $mail_tpl = Container::get('hooks')->fire('model.register.insert_user_dupe_mail_tpl', $mail_tpl);

                // The first row contains the subject
                $first_crlf = strpos($mail_tpl, "\n");
                $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                $mail_subject = Container::get('hooks')
                    ->fire('model.register.insert_user_dupe_mail_subject', $mail_subject);

                $mail_message = trim(substr($mail_tpl, $first_crlf));
                $mail_message = str_replace('<username>', $user->username, $mail_message);
                $mail_message = str_replace('<dupe_list>', implode(', ', $dupe_list), $mail_message);
                $mail_message = str_replace(
                    '<profile_url>',
                    Url::get(Router::pathFor('userProfile', ['id' => $new_uid])),
                    $mail_message
                );
                $mail_message = str_replace(
                    '<board_mailer>',
                    ForumSettings::get('o_board_title'),
                    $mail_message
                );
                $mail_message = Container::get('hooks')
                    ->fire('model.register.insert_user_dupe_mail_message', $mail_message);

                Container::get('email')
                    ->dispatchMail(ForumSettings::get('o_mailing_list'), $mail_subject, $mail_message);
            }

            // Should we alert people on the admin mailing list that a new user has registered?
            if (ForumSettings::get('o_regs_report') == '1') {
                // Load the "new user" template
                $mail_tpl = Lang::getMailTemplate('new_user')->text;
                $mail_tpl = Container::get('hooks')->fire('model.register.insert_user_new_mail_tpl', $mail_tpl);

                // The first row contains the subject
                $first_crlf = strpos($mail_tpl, "\n");
                $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                $mail_subject = Container::get('hooks')
                    ->fire('model.register.insert_user_new_mail_subject', $mail_subject);

                $mail_message = trim(substr($mail_tpl, $first_crlf));
                $mail_message = str_replace('<username>', $user->username, $mail_message);
                $mail_message = str_replace('<base_url>', Url::get(Router::pathFor('home')), $mail_message);
                $mail_message = str_replace(
                    '<profile_url>',
                    Url::get(Router::pathFor('userProfile', ['id' => $new_uid])),
                    $mail_message
                );
                $mail_message = str_replace(
                    '<admin_url>',
                    Url::get(Router::pathFor('profileSection', ['id' => $new_uid, 'section' => 'admin'])),
                    $mail_message
                );
                $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);
                $mail_message = Container::get('hooks')
                    ->fire('model.register.insert_user_new_mail_message', $mail_message);

                Container::get('email')
                    ->dispatchMail(ForumSettings::get('o_mailing_list'), $mail_subject, $mail_message);
            }
        }

        // Must the user verify the registration or do we log him/her in right now?
        if (ForumSettings::get('o_regs_verify') == '1') {
            // Load the "welcome" template
            $mail_tpl = Lang::getMailTemplate('welcome')->text;
            $mail_tpl = Container::get('hooks')->fire('model.register.insert_user_welcome_mail_tpl', $mail_tpl);

            // The first row contains the subject
            $first_crlf = strpos($mail_tpl, "\n");
            $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
            $mail_subject = Container::get('hooks')
                ->fire('model.register.insert_user_welcome_mail_subject', $mail_subject);

            $mail_message = trim(substr($mail_tpl, $first_crlf));
            $mail_subject = str_replace('<board_title>', ForumSettings::get('o_board_title'), $mail_subject);
            $mail_message = str_replace('<base_url>', Url::get(Router::pathFor('home')), $mail_message);
            $mail_message = str_replace('<username>', $user->username, $mail_message);
            $mail_message = str_replace('<password>', $pass, $mail_message);
            $mail_message = str_replace('<login_url>', Url::get(Router::pathFor('login')), $mail_message);
            $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);
            $mail_message = Container::get('hooks')
                ->fire('model.register.insert_user_welcome_mail_message', $mail_message);

            Container::get('email')->dispatchMail($user->email, $mail_subject, $mail_message);

            return Router::redirect(Router::pathFor('home'), __('Reg email').' <a href="mailto:'.
                Utils::escape(ForumSettings::get('o_admin_email')).'">'.
                Utils::escape(ForumSettings::get('o_admin_email')).'</a>.');
        }

        $user_object = new \stdClass();
        $user_object->id = $new_uid;
        $user_object->username = $user->username;
        $expire = time() + ForumSettings::get('o_timeout_visit');
        $jwt = AuthModel::generateJwt($user_object, $expire);
        AuthModel::setCookie('Bearer '.$jwt, $expire);

        // Refresh cache
        Container::get('cache')->store('users_info', Cache::getUsersInfo());

        Container::get('hooks')->fire('model.register.insert_user');

        return Router::redirect(Router::pathFor('home'), __('Reg complete'));
    }
}
