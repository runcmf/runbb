<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller;

use RunBB\Core\Interfaces\Input;
use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use RunBB\Model\Delete;

class Profile
{
    private $model;

    public function __construct()
    {
        $this->model = new \RunBB\Model\Profile();
        Lang::load('profile');
        Lang::load('register');
        Lang::load('prof_reg');
    }

    public function display($req, $res, $args)
    {
        // Include UTF-8 function
        require ForumEnv::get('FORUM_ROOT') . 'Helpers/utf8/substr_replace.php';
        require ForumEnv::get('FORUM_ROOT') . 'Helpers/utf8/ucwords.php'; // utf8_ucwords needs utf8_substr_replace
        require ForumEnv::get('FORUM_ROOT') . 'Helpers/utf8/strcasecmp.php';

//        $args['id'] = Container::get('hooks')->fire('controller.profile.display', $args);
        Container::get('hooks')->fire('controller.profile.display', $args);

        if (Input::post('update_group_membership')) {
            if (User::get()->g_id > ForumEnv::get('FEATHER_ADMIN')) {
                throw new  RunBBException(__('No permission'), 403);
            }

            return $this->model->updateGroupMembership($args['id']);
        } elseif (Input::post('update_forums')) {
            if (User::get()->g_id > ForumEnv::get('FEATHER_ADMIN')) {
                throw new  RunBBException(__('No permission'), 403);
            }

            return $this->model->updateModForums($args['id']);
        } elseif (Input::post('ban')) {
            if (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') &&
                (User::get()->g_moderator != '1' ||
                    User::get()->g_mod_ban_users == '0')
            ) {
                throw new  RunBBException(__('No permission'), 403);
            }

            return $this->model->banUser($args['id']);
        } elseif (Input::post('delete_user') || Input::post('delete_user_comply')) {
            if (User::get()->g_id > ForumEnv::get('FEATHER_ADMIN')) {
                throw new  RunBBException(__('No permission'), 403);
            }

            if (Input::post('delete_user_comply')) {
                return $this->model->deleteUser($args['id']);
            } else {
                return View::setPageInfo([
                    'title' => [Utils::escape(
                        ForumSettings::get('o_board_title')
                    ),
                        __('Profile'),
                        __('Confirm delete user')
                    ],
                    'active_page' => 'profile',
                    'username' => $this->model->getUsername($args['id']),
                    'id' => $args['id'],
                ])->addTemplate('@forum/profile/delete_user')->display();
            }
        } elseif (Input::post('form_sent')) {
            // Fetch the user group of the user we are editing
            $info = $this->model->fetchUserGroup($args['id']);

            if (User::get()->id != $args['id'] &&          // If we aren't the user (i.e. editing your own profile)
                (!User::get()->is_admmod ||                   // and we are not an admin or mod
                    (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') &&// or we aren't an admin and ...
                        (User::get()->g_mod_edit_users == '0' ||      // mods aren't allowed to edit users
                            $info['group_id'] == ForumEnv::get('FEATHER_ADMIN') || // or the user is an admin
                            $info['is_moderator'])))
            ) {                          // or the user is another mod
                throw new  RunBBException(__('No permission'), 403);
            }

            return $this->model->updateProfile($args['id'], $info, $args['section']);
        }

        $user = $this->model->getUserInfo($args['id']);

        if ($user->signature != '') {
            $parsed_signature = Container::get('parser')->parseMessage($user->signature);
        }

        // View or edit?
        if (User::get()->id != $args['id'] &&                // If we aren't the user (i.e. editing your own profile)
            (!User::get()->is_admmod ||                           // and we are not an admin or mod
                (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') && // or we aren't an admin and ...
                    (User::get()->g_mod_edit_users == '0' ||                // mods aren't allowed to edit users
                        $user->g_id == ForumEnv::get('FEATHER_ADMIN') ||        // or the user is an admin
                        $user->g_moderator == '1')))
        ) {                           // or the user is another mod
            $user_info = $this->model->parseUserInfo($user);

            View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')),
                    __('Users profile', Utils::escape($user->username))],
                'active_page' => 'profile',
                'user_info' => $user_info,
                'id' => $args['id']
            ]);

            View::addTemplate('@forum/profile/view_profile')->display();
        } else {
            if (!isset($args['section']) || $args['section'] == 'essentials') {
                $user_disp = $this->model->editEssentials($args['id'], $user);

                View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'),
                        __('Section essentials')],
                    'required_fields' => ['req_username' => __('Username'), 'req_email' => __('Email')],
                    'active_page' => 'profile',
                    'id' => $args['id'],
                    'page' => 'essentials',
                    'user' => $user,
                    'user_disp' => $user_disp,
                    'forum_time_formats' => Container::get('forum_time_formats'),
                    'forum_date_formats' => Container::get('forum_date_formats'),
                    'languages' => Lang::getList()
                ]);

                View::addTemplate('@forum/profile/section_essentials')->display();
            } elseif ($args['section'] == 'personal') {
                $title_field = '';
                if (User::get()->g_set_title == '1') {
                    $title_field = '<label>' . __('Title') . ' <em>(' .
                        __('Leave blank') . ')</em><br /><input type="text" name="title" value="' .
                        Utils::escape($user->title) . '" size="30" maxlength="50" /><br /></label>' . "\n";
                }

                View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'),
                        __('Section personal')],
                    'active_page' => 'profile',
                    'id' => $args['id'],
                    'page' => 'personal',
                    'user' => $user,
                    'title_field' => $title_field,
                ]);

                View::addTemplate('@forum/profile/section_personal')->display();
            } elseif ($args['section'] == 'messaging') {
                View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'),
                        __('Section messaging')],
                    'active_page' => 'profile',
                    'page' => 'messaging',
                    'user' => $user,
                    'id' => $args['id']
                ]);

                View::addTemplate('@forum/profile/section_messaging')->display();
            } elseif ($args['section'] == 'personality') {
                if (ForumSettings::get('o_avatars') == '0' && ForumSettings::get('o_signatures') == '0') {
                    throw new  RunBBException(__('Bad request'), 404);
                }

                $avatar_field = '<span><a href="' . Router::pathFor('profileAction', ['id' => $args['id'],
                        'action' => 'upload_avatar']) . '">' . __('Change avatar') . '</a></span>';

                $user_avatar = Utils::generateAvatarMarkup($args['id']);
                if ($user_avatar) {
                    $avatar_field .= ' <span><a href="' . Router::pathFor('profileAction', ['id' => $args['id'],
                            'action' => 'delete_avatar']) . '">' . __('Delete avatar') . '</a></span>';
                } else {
                    $avatar_field = '<span><a href="' . Router::pathFor('profileAction', ['id' => $args['id'],
                            'action' => 'upload_avatar']) . '">' . __('Upload avatar') . '</a></span>';
                }

                if ($user->signature != '') {
                    $signature_preview = '<p>' . __('Sig preview') . '</p>' . "\n\t\t\t\t\t\t\t" .
                        '<div class="postsignature postmsg">' . "\n\t\t\t\t\t\t\t\t" . '<hr />' .
                        "\n\t\t\t\t\t\t\t\t" . $parsed_signature . "\n\t\t\t\t\t\t\t" . '</div>' . "\n";
                } else {
                    $signature_preview = '<p>' . __('No sig') . '</p>' . "\n";
                }

                View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'),
                        __('Section personality')],
                    'active_page' => 'profile',
                    'user_avatar' => $user_avatar,
                    'avatar_field' => $avatar_field,
                    'signature_preview' => $signature_preview,
                    'page' => 'personality',
                    'user' => $user,
                    'id' => $args['id'],
                ]);

                View::addTemplate('@forum/profile/section_personality')->display();
            } elseif ($args['section'] == 'display') {
                View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'),
                        __('Section display')],
                    'active_page' => 'profile',
                    'page' => 'display',
                    'user' => $user,
                    'id' => $args['id'],
                    'styles' => \RunBB\Core\Lister::getStyles()
                ]);

                View::addTemplate('@forum/profile/section_display')->display();
            } elseif ($args['section'] == 'privacy') {
                View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'),
                        __('Section privacy')],
                    'active_page' => 'profile',
                    'page' => 'privacy',
                    'user' => $user,
                    'id' => $args['id']
                ]);

                View::addTemplate('@forum/profile/section_privacy')->display();
            } elseif ($args['section'] == 'admin') {
                if (!User::get()->is_admmod || (User::get()->g_moderator == '1' &&
                        User::get()->g_mod_ban_users == '0')
                ) {
                    throw new  RunBBException(__('Bad request'), 404);
                }

                View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'),
                        __('Section admin')],
                    'active_page' => 'profile',
                    'page' => 'admin',
                    'user' => $user,
                    'forum_list' => $this->model->getForumList($args['id']),
                    'group_list' => $this->model->getGroupList($user),
                    'id' => $args['id']
                ]);

                return View::addTemplate('@forum/profile/section_admin')->display();
            } else {
                throw new  RunBBException(__('Bad request'), 404);
            }
        }
    }

    public function action($req, $res, $args)
    {
        // Include UTF-8 function
        require ForumEnv::get('FORUM_ROOT') . 'Helpers/utf8/substr_replace.php';
        require ForumEnv::get('FORUM_ROOT') . 'Helpers/utf8/ucwords.php'; // utf8_ucwords needs utf8_substr_replace
        require ForumEnv::get('FORUM_ROOT') . 'Helpers/utf8/strcasecmp.php';

        $args['id'] = Container::get('hooks')->fire('controller.profile.action', $args['id']);

        if ($args['action'] != 'change_pass' || !Input::query('key')) {
            if (User::get()->g_read_board == '0') {
                throw new  RunBBException(__('No view'), 403);
            } elseif (User::get()->g_view_users == '0' && (User::get()->is_guest || User::get()->id != $args['id'])) {
                throw new  RunBBException(__('No permission'), 403);
            }
        }

        if ($args['action'] == 'change_pass') {
            if (Request::isPost()) {
                // TODO: Check if security "if (User::get()->id != $id)" (l.58 of Model/Profile) isn't bypassed
                // FOR ALL chained if below
                return $this->model->changePass($args['id']);
            }

            View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'), __('Change pass')],
                'active_page' => 'profile',
                'id' => $args['id'],
                'required_fields' => ['req_old_password' => __('Old pass'),
                    'req_new_password1' => __('New pass'), 'req_new_password2' => __('Confirm new pass')],
                'focus_element' => ['change_pass', ((!User::get()->is_admmod) ?
                    'req_old_password' : 'req_new_password1')],
            ]);

            View::addTemplate('@forum/profile/change_pass')->display();
        } elseif ($args['action'] == 'change_email') {
            if (Request::isPost()) {
                return $this->model->changeEmail($args['id']);
            }

            View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'), __('Change email')],
                'active_page' => 'profile',
                'required_fields' => ['req_new_email' => __('New email'), 'req_password' => __('Password')],
                'focus_element' => ['change_email', 'req_new_email'],
                'id' => $args['id'],
            ]);

            View::addTemplate('@forum/profile/change_mail')->display();
        } elseif ($args['action'] == 'upload_avatar' || $args['action'] == 'upload_avatar2') {
            if (ForumSettings::get('o_avatars') == '0') {
                throw new  RunBBException(__('Avatars disabled'), 400);
            }

            if (User::get()->id != $args['id'] && !User::get()->is_admmod) {
                throw new  RunBBException(__('No permission'), 403);
            }

            if (Request::isPost()) {
                return $this->model->uploadAvatar($args['id'], $_FILES);
            }

            View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Profile'), __('Upload avatar')],
                'active_page' => 'profile',
                'required_fields' => ['req_file' => __('File')],
                'focus_element' => ['upload_avatar', 'req_file'],
                'id' => $args['id'],
                'avatarFormattedSize' => Utils::fileSize(ForumSettings::get('o_avatars_size'))
            ]);

            View::addTemplate('@forum/profile/upload_avatar')->display();
        } elseif ($args['action'] == 'delete_avatar') {
            if (User::get()->id != $args['id'] && !User::get()->is_admmod) {
                throw new  RunBBException(__('No permission'), 403);
            }

            $this->model->deleteAvatar($args['id']);

            return Router::redirect(Router::pathFor(
                'profileSection',
                ['id' => $args['id'], 'section' => 'personality']
            ), __('Avatar deleted redirect'));
        } elseif ($args['action'] == 'promote') {
            if (User::get()->g_id != ForumEnv::get('FEATHER_ADMIN') &&
                (User::get()->g_moderator != '1' || User::get()->g_mod_promote_users == '0')
            ) {
                throw new  RunBBException(__('No permission'), 403);
            }

            $this->model->promoteUser($args['id']);
        } elseif ($args['action'] == 'change_style') {
            $this->model->changeStyle();
            return Router::redirect(Input::post('currentPage'), 'Style Changed');//FIXME translate
        } elseif ($args['action'] == 'change_lang') {
            $this->model->changeLanguage();
            return Router::redirect(Input::post('currentPage'), 'Language Changed');//FIXME translate
        } else {
            throw new  RunBBException(__('Bad request'), 404);
        }
    }

    public function email($req, $res, $args)
    {
        $args['id'] = Container::get('hooks')->fire('controller.profile.email', $args['id']);

        if (User::get()->g_send_email == '0') {
            throw new  RunBBException(__('No permission'), 403);
        }

        if ($args['id'] < 2) {
            throw new  RunBBException(__('Bad request'), 400);
        }

        $mail = $this->model->getInfoMail($args['id']);

        if ($mail['email_setting'] == 2 && !User::get()->is_admmod) {
            throw new  RunBBException(__('Form email disabled'), 403);
        }


        if (Request::isPost()) {
            $this->model->sendEmail($mail);
        }

        View::setPageInfo([
            'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Send email to') . ' ' .
                Utils::escape($mail['recipient'])],
            'active_page' => 'email',
            'required_fields' => ['req_subject' => __('Email subject'), 'req_message' => __('Email message')],
            'focus_element' => ['email', 'req_subject'],
            'id' => $args['id'],
            'mail' => $mail
        ])->addTemplate('@forum/misc/email')->display();
    }

    public function gethostip($req, $res, $args)
    {
        $args['ip'] = Container::get('hooks')->fire('controller.profile.gethostip', $args['ip']);

        $this->model->displayIpInfo($args['ip']);
    }
}
