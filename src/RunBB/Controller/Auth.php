<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller;

use RunBB\Exception\RunBBException;
use RunBB\Core\Random;
use RunBB\Core\Track;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use RunBB\Model\Auth as ModelAuth;
use RunBB\Model\Cache;

class Auth
{
    public function __construct()
    {
        Lang::load('login');
    }

    public function login($req, $res, $args)
    {
        if (!User::get()->is_guest) {
            return Router::redirect(Router::pathFor('home'), 'Already logged in');
        }

        if (Request::isPost()) {
            Container::get('hooks')->fire('controller.login');
            $form_username = Input::post('req_username');
            $form_password = Input::post('req_password');
            $save_pass = (bool)Input::post('save_pass');

            $user = ModelAuth::getUserFromName($form_username);

            if (!empty($user->password)) {
                $form_password_hash = Random::hash($form_password); // Will result in a SHA-1 hash
                if ($user->password == $form_password_hash) {
                    if ($user->group_id == ForumEnv::get('FEATHER_UNVERIFIED')) {
                        ModelAuth::updateGroup($user->id, ForumSettings::get('o_default_user_group'));
                        if (!Container::get('cache')->isCached('users_info')) {
                            Container::get('cache')->store('users_info', Cache::getUsersInfo());
                        }
                    }

                    ModelAuth::deleteOnlineByIp(Utils::getIp());
                    // Reset tracked topics
                    Track::setTrackedTopics(null);

                    $expire = ($save_pass) ? Container::get('now') + 1209600 :
                        Container::get('now') + ForumSettings::get('o_timeout_visit');
                    $expire = Container::get('hooks')->fire('controller.expire_login', $expire);

                    $jwt = ModelAuth::generateJwt($user, $expire);
                    ModelAuth::setCookie('Bearer ' . $jwt, $expire);

                    return Router::redirect(Router::pathFor('home'), __('Login redirect'));
                }// else {
                //throw new  RunBBException(__('Wrong user/pass').' <a href="'.
                //Router::pathFor('resetPassword').'">'.__('Forgotten pass').'</a>', 403);
                //}
            } //else {
            throw new  RunBBException(__('Wrong user/pass') . ' <a href="' .
                Router::pathFor('resetPassword') . '">' . __('Forgotten pass') . '</a>', 403);
            //}
        } else {
            View::setPageInfo([
                'active_page' => 'login',
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Login')],
                'required_fields' => ['req_username' => __('Username'), 'req_password' => __('Password')],
                'focus_element' => ['login', 'req_username'],
            ])->addTemplate('@forum/login/form')->display();
        }
    }

    public function logout($req, $res, $args)
    {
        $token = Container::get('hooks')->fire('controller.logout', $args['token']);

        if (User::get()->is_guest || !isset($token) ||
            $token != Random::hash(User::get()->id . Random::hash(Utils::getIp()))
        ) {
            return Router::redirect(Router::pathFor('home'), 'Not logged in');
        }

        ModelAuth::deleteOnlineById(User::get()->id);

        // Update last_visit (make sure there's something to update it with)
        if (isset(User::get()->logged)) {
            ModelAuth::setLastVisit(User::get()->id, User::get()->logged);
        }

        ModelAuth::setCookie('Bearer ', 1);
        Container::get('hooks')->fire('controller.logout_end');

        return Router::redirect(Router::pathFor('home'), __('Logout redirect'));
    }

    public function forget($req, $res, $args)
    {
        if (!User::get()->is_guest) {
            return Router::redirect(Router::pathFor('home'), 'Already logged in');
        }

        if (Request::isPost()) {
            // Validate the email address
            $email = strtolower(Utils::trim(Input::post('req_email')));
            if (!Container::get('email')->isValidEmail($email)) {
                throw new  RunBBException(__('Invalid email'), 400);
            }
            $user = ModelAuth::getUserFromEmail($email);

            if ($user) {
                // Load the "activate password" template
                $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT') . 'lang/' .
                    User::get()->language . '/mail_templates/activate_password.tpl'));
                $mail_tpl = Container::get('hooks')->fire('controller.mail_tpl_password_forgotten', $mail_tpl);

                // The first row contains the subject
                $first_crlf = strpos($mail_tpl, "\n");
                $mail_subject = trim(substr($mail_tpl, 8, $first_crlf - 8));
                $mail_message = trim(substr($mail_tpl, $first_crlf));

                // Do the generic replacements first (they apply to all emails sent out here)
                $mail_message = str_replace('<base_url>', Url::base() . '/', $mail_message);
                $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);

                $mail_message = Container::get('hooks')->fire(
                    'controller.mail_message_password_forgotten',
                    $mail_message
                );

                if ($user->last_email_sent != '' && (time() - $user->last_email_sent) < 3600 &&
                    (time() - $user->last_email_sent) >= 0
                ) {
                    throw new  RunBBException(sprintf(
                        __('Email flood'),
                        intval((3600 - (time() - $user->last_email_sent)) / 60)
                    ), 429);
                }

                // Generate a new password and a new password activation code
                $new_password = Random::pass(12);
                $new_password_key = Random::pass(8);

                ModelAuth::setNewPassword($new_password, $new_password_key, $user->id);

                // Do the user specific replacements to the template
                $cur_mail_message = str_replace('<username>', $user->username, $mail_message);
                $cur_mail_message = str_replace(
                    '<activation_url>',
                    Url::base() . Router::pathFor(
                        'profileAction',
                        ['id' => $user->id, 'action' => 'change_pass'],
                        ['key' => $new_password_key]
                    ),
                    $cur_mail_message
                );
                $cur_mail_message = str_replace('<new_password>', $new_password, $cur_mail_message);
                $cur_mail_message = Container::get('hooks')->fire(
                    'controller.cur_mail_message_password_forgotten',
                    $cur_mail_message
                );

                Container::get('email')->dispatchMail($email, $mail_subject, $cur_mail_message);

                return Router::redirect(Router::pathFor('home'), __('Forget mail') . ' <a href="mailto:' .
                    Utils::escape(ForumSettings::get('o_admin_email')) . '">' .
                    Utils::escape(ForumSettings::get('o_admin_email')) . '</a>.', 200);
            } else {
                throw new  RunBBException(__('No email match') . ' ' . Utils::escape($email) . '.', 400);
            }
        }

        View::setPageInfo([
//                'errors'    =>    $this->model->password_forgotten(),
            'active_page' => 'login',
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Request pass')],
            'required_fields' => ['req_email' => __('Email')],
            'focus_element' => ['request_pass', 'req_email'],
        ])->addTemplate('@forum/login/password_forgotten')->display();
    }
}
