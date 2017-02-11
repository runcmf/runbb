<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller;

use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;

class Register
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Register();
        Lang::load('register');
        Lang::load('prof_reg');
        Lang::load('antispam');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.register.display');

        if (!User::get()->is_guest) {
            return Router::redirect(Router::pathFor('home'));
        }

        // Antispam feature
        $lang_antispam_questions = require ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.'/antispam.php';
        $index_questions = rand(0, count($lang_antispam_questions)-1);

        // Display an error message if new registrations are disabled
        // If $_REQUEST['username'] or $_REQUEST['password'] are filled, we are facing a bot
        if (ForumSettings::get('o_regs_allow') == '0' || Input::post('username') || Input::post('password')) {
            throw new  RunBBException(__('No new regs'), 403);
        }

        $user['timezone'] = isset($user['timezone']) ? $user['timezone'] : ForumSettings::get('o_default_timezone');
        $user['dst'] = isset($user['dst']) ? $user['dst'] : ForumSettings::get('o_default_dst');
        $user['email_setting'] = isset($user['email_setting']) ? $user['email_setting'] :
            ForumSettings::get('o_default_email_setting');
        $user['errors'] = '';

        if (Request::isPost()) {
            $user = $this->model->checkForErrors();

            // Did everything go according to plan? Insert the user
            if (empty($user['errors'])) {
                return $this->model->insertUser($user);
            }
        }

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Register')],
            'focus_element' => ['register', 'req_user'],
            'required_fields' => [
                'req_user' => __('Username'),
                'req_password1' => __('Password'),
                'req_password2' => __('Confirm pass'),
                'req_email1' => __('Email'),
                'req_email2' => __('Email').' 2',
                'captcha' => __('Robot title')
            ],
            'active_page' => 'register',
            'is_indexed' => true,
            'errors' => $user['errors'],
            'index_questions'    =>    $index_questions,
            'languages' => \RunBB\Core\Lister::getLangs(),
            'question' => array_keys($lang_antispam_questions),
            'qencoded' => md5(array_keys($lang_antispam_questions)[$index_questions]),
        ])->addTemplate('register/form.php')->display();
    }

    public function cancel($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.register.cancel');

        return Router::redirect(Router::pathFor('home'));
    }

    public function rules($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.register.rules');

        // If we are logged in, we shouldn't be here
        if (!User::get()->is_guest) {
            return Router::redirect(Router::pathFor('home'));
        }

        // Display an error message if new registrations are disabled
        if (ForumSettings::get('o_regs_allow') == '0') {
            throw new  RunBBException(__('No new regs'), 403);
        }

        if (ForumSettings::get('o_rules') != '1') {
            return Router::redirect(Router::pathFor('register'));
        }

        View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Register'), __('Forum rules')],
                'active_page' => 'register',
            ])->addTemplate('register/rules.php')->display();
    }
}
