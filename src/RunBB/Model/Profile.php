<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model;

use RunBB\Core\Interfaces\Lang;
use RunBB\Exception\RunBBException;
use RunBB\Core\Random;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use RunBB\Model\Auth as AuthModel;

class Profile
{

    public function changePass($id)
    {
        $id = Container::get('hooks')->fire('model.profile.change_pass_start', $id);

        if (Input::query('key')) {
            $key = Input::query('key');
            $key = Container::get('hooks')->fire('model.profile.change_pass_key', $key);

            // If the user is already logged in we shouldn't be here :)
            if (!User::get()->is_guest) {
                return Router::redirect(Router::pathFor('home'));
            }

            $cur_user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->where('id', $id);
            $cur_user = Container::get('hooks')->fireDB('model.profile.change_pass_user_query', $cur_user);
            $cur_user = $cur_user->find_one();

            if ($key == '' || $key != $cur_user['activate_key']) {
                throw new  RunBBException(__('Pass key bad').' <a href="mailto:'.
                    Utils::escape(ForumSettings::get('o_admin_email')).'">'.
                    Utils::escape(ForumSettings::get('o_admin_email')).'</a>.', 400);
            } else {
                $query = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->where('id', $id)
                    ->find_one()
                    ->set('password', $cur_user['activate_string'])
                    ->set_expr('activate_string', 'NULL')
                    ->set_expr('activate_key', 'NULL');
                $query = Container::get('hooks')->fireDB('model.profile.change_pass_activate_query', $query);
                $query = $query->save();

                return Router::redirect(Router::pathFor('home'), __('Pass updated'));
            }
        }

        // Make sure we are allowed to change this user's password
        if (User::get()->id != $id) {
            $id = Container::get('hooks')->fire('model.profile.change_pass_key_not_id', $id);

            if (!User::get()->is_admmod) { // A regular user trying to change another user's password?
                throw new  RunBBException(__('No permission'), 403);
            } elseif (User::get()->g_moderator == '1') {
                // A moderator trying to change a user's password?

                $user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->table_alias('u')
                    ->select_many(['u.group_id', 'g.g_moderator'])
                    ->inner_join(ORM_TABLE_PREFIX.'groups', ['g.g_id', '=', 'u.group_id'], 'g')
                    ->where('u.id', $id);
                $user = Container::get('hooks')->fireDB('model.profile.change_pass_user_query', $user);
                $user = $user->find_one();

                if (!$user) {
                    throw new  RunBBException(__('Bad request'), 404);
                }

                if (User::get()->g_mod_edit_users == '0' || User::get()->g_mod_change_passwords == '0' ||
                    $user['group_id'] == ForumEnv::get('FEATHER_ADMIN') || $user['g_moderator'] == '1') {
                    throw new  RunBBException(__('No permission'), 403);
                }
            }
        }

        if (Request::isPost()) {
            $old_password = Input::post('req_old_password') ? Utils::trim(Input::post('req_old_password')) : '';
            $new_password1 = Utils::trim(Input::post('req_new_password1'));
            $new_password2 = Utils::trim(Input::post('req_new_password2'));

            if ($new_password1 != $new_password2) {
                throw new  RunBBException(__('Pass not match'), 400);
            }
            if (Utils::strlen($new_password1) < 6) {
                throw new  RunBBException(__('Pass too short'), 400);
            }

            $cur_user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->where('id', $id);
            $cur_user = Container::get('hooks')->fireDB('model.profile.change_pass_find_user', $cur_user);
            $cur_user = $cur_user->find_one();

            $authorized = false;

            if (!empty($cur_user['password'])) {
                $old_password_hash = Random::hash($old_password);

                if ($cur_user['password'] == $old_password_hash || User::get()->is_admmod) {
                    $authorized = true;
                }
            }

            if (!$authorized) {
                throw new  RunBBException(__('Wrong pass'), 403);
            }

            $new_password_hash = Random::hash($new_password1);

            $update_password = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->where('id', $id)
                ->find_one()
                ->set('password', $new_password_hash);
            $update_password = Container::get('hooks')->fireDB('model.profile.change_pass_query', $update_password);
            $update_password = $update_password->save();

            if (User::get()->id == $id) {
                $expire = time() + ForumSettings::get('o_timeout_visit');
                $jwt = AuthModel::generateJwt(User::get(), $expire);
                AuthModel::setCookie('Bearer '.$jwt, $expire);
            }

            Container::get('hooks')->fire('model.profile.change_pass');
            return Router::redirect(
                Router::pathFor('profileSection', ['id' => $id, 'section' => 'essentials']),
                __('Pass updated redirect')
            );
        }
    }

    public function changeEmail($id)
    {
        $id = Container::get('hooks')->fire('model.profile.change_email_start', $id);

        // Make sure we are allowed to change this user's email
        if (User::get()->id != $id) {
            $id = Container::get('hooks')->fire('model.profile.change_email_not_id', $id);

            if (!User::get()->is_admmod) { // A regular user trying to change another user's email?
                throw new  RunBBException(__('No permission'), 403);
            } elseif (User::get()->g_moderator == '1') {
                // A moderator trying to change a user's email?
                $user['select'] = ['u.group_id', 'g.g_moderator'];

                $user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->table_alias('u')
                    ->select_many($user['select'])
                    ->inner_join(ORM_TABLE_PREFIX.'groups', ['g.g_id', '=', 'u.group_id'], 'g')
                    ->where('u.id', $id);
                $user = Container::get('hooks')->fireDB('model.profile.change_email_not_id_query', $user);
                $user = $user->find_one();

                if (!$user) {
                    throw new  RunBBException(__('Bad request'), 404);
                }

                if (User::get()->g_mod_edit_users == '0' ||
                    User::get()->g_mod_change_passwords == '0' ||
                    $user['group_id'] == ForumEnv::get('FEATHER_ADMIN') ||
                    $user['g_moderator'] == '1') {
                    throw new  RunBBException(__('No permission'), 403);
                }
            }
        }

        if (Input::query('key')) {
            $key = Input::query('key');
            $key = Container::get('hooks')->fire('model.profile.change_email_key', $key);

            $new_email_key = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->select('activate_key')
                ->where('id', $id);
            $new_email_key = Container::get('hooks')->fireDB('model.profile.change_email_key_query', $new_email_key);
            $new_email_key = $new_email_key->find_one();

            if ($key == '' || $key != $new_email_key->activate_key) {
                throw new  RunBBException(__('Email key bad').' <a href="mailto:'.
                    Utils::escape(ForumSettings::get('o_admin_email')).'">'.
                    Utils::escape(ForumSettings::get('o_admin_email')).'</a>.', 400);
            } else {
                $update_mail = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                    ->where('id', $id)
                    ->find_one()
                    ->set_expr('email', 'activate_string')
                    ->set_expr('activate_string', 'NULL')
                    ->set_expr('activate_key', 'NULL');
                $update_mail = Container::get('hooks')->fireDB('model.profile.change_email_query', $update_mail);
                $update_mail = $update_mail->save();

                return Router::redirect(Router::pathFor('home'), __('Email updated'));
            }
        } elseif (Request::isPost()) {
            Container::get('hooks')->fire('model.profile.change_email_post');

            if (Random::hash(Input::post('req_password')) !== User::get()->password) {
                throw new  RunBBException(__('Wrong pass'));
            }

            // Validate the email address
            $new_email = strtolower(Utils::trim(Input::post('req_new_email')));
            $new_email = Container::get('hooks')->fire('model.profile.change_email_new_email', $new_email);
            if (!Container::get('email')->isValidEmail($new_email)) {
                throw new  RunBBException(__('Invalid email'), 400);
            }

            // Check if it's a banned email address
            if (Container::get('email')->isBannedEmail($new_email)) {
                if (ForumSettings::get('p_allow_banned_email') == '0') {
                    throw new  RunBBException(__('Banned email'), 403);
                } elseif (ForumSettings::get('o_mailing_list') != '') {
                    // Load the "banned email change" template
//                    $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.
//                        User::get()->language.'/mail_templates/banned_email_change.tpl'));
                    $mail_tpl = Lang::getMailTemplate('banned_email_change')->text;
                    $mail_tpl = Container::get('hooks')->fire('model.profile.change_email_mail_tpl', $mail_tpl);

                    // The first row contains the subject
                    $first_crlf = strpos($mail_tpl, "\n");
                    $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                    $mail_subject = Container::get('hooks')
                        ->fire('model.profile.change_email_mail_subject', $mail_subject);

                    $mail_message = trim(substr($mail_tpl, $first_crlf));
                    $mail_message = str_replace('<username>', User::get()->username, $mail_message);
                    $mail_message = str_replace('<email>', $new_email, $mail_message);
                    $mail_message = str_replace(
                        '<profile_url>',
                        Router::pathFor('userProfile', ['id' => $id]),
                        $mail_message
                    );
                    $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);
                    $mail_message = Container::get('hooks')
                        ->fire('model.profile.change_email_mail_message', $mail_message);

                    Container::get('email')
                        ->dispatchMail(ForumSettings::get('o_mailing_list'), $mail_subject, $mail_message);
                }
            }

            // Check if someone else already has registered with that email address
            $result['select'] = ['id', 'username'];

            $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->select_many($result['select'])
                ->where('email', $new_email);
            $result = Container::get('hooks')->fireDB('model.profile.change_email_check_mail', $result);
            $result = $result->find_many();

            if ($result) {
                if (ForumSettings::get('p_allow_dupe_email') == '0') {
                    throw new  RunBBException(__('Dupe email'), 400);
                } elseif (ForumSettings::get('o_mailing_list') != '') {
                    foreach ($result as $cur_dupe) {
                        $dupe_list[] = $cur_dupe['username'];
                    }

                    // Load the "dupe email change" template
//                    $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.
//                        User::get()->language.'/mail_templates/dupe_email_change.tpl'));
                    $mail_tpl = Lang::getMailTemplate('dupe_email_change')->text;
                    $mail_tpl = Container::get('hooks')->fire('model.profile.change_email_mail_dupe_tpl', $mail_tpl);

                    // The first row contains the subject
                    $first_crlf = strpos($mail_tpl, "\n");
                    $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
                    $mail_subject = Container::get('hooks')
                        ->fire('model.profile.change_email_mail_dupe_subject', $mail_subject);

                    $mail_message = trim(substr($mail_tpl, $first_crlf));
                    $mail_message = str_replace('<username>', User::get()->username, $mail_message);
                    $mail_message = str_replace('<dupe_list>', implode(', ', $dupe_list), $mail_message);
                    $mail_message = str_replace(
                        '<profile_url>',
                        Router::pathFor('userProfile', ['id' => $id]),
                        $mail_message
                    );
                    $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);
                    $mail_message = Container::get('hooks')
                        ->fire('model.profile.change_email_mail_dupe_message', $mail_message);

                    Container::get('email')
                        ->dispatchMail(ForumSettings::get('o_mailing_list'), $mail_subject, $mail_message);
                }
            }


            $new_email_key = Random::pass(8);
            $new_email_key = Container::get('hooks')->fire('model.profile.change_email_new_email_key', $new_email_key);

            // Update the user
            unset($user);
            $user['update'] = [
                'activate_string' => $new_email,
                'activate_key'  => $new_email_key,
            ];
            $user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->where('id', tid)
                ->find_one()
                ->set($user['update']);
            $user = Container::get('hooks')->fireDB('model.profile.change_email_user_query', $user);
            $user = $user->save();

            // Load the "activate email" template
//            $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.
//                User::get()->language.'/mail_templates/activate_email.tpl'));
            $mail_tpl = Lang::getMailTemplate('activate_email')->text;
            $mail_tpl = Container::get('hooks')->fire('model.profile.change_email_mail_activate_tpl', $mail_tpl);

            // The first row contains the subject
            $first_crlf = strpos($mail_tpl, "\n");
            $mail_subject = trim(substr($mail_tpl, 8, $first_crlf-8));
            $mail_subject = Container::get('hooks')
                ->fire('model.profile.change_email_mail_activate_subject', $mail_subject);

            $mail_message = trim(substr($mail_tpl, $first_crlf));
            $mail_message = str_replace('<username>', User::get()->username, $mail_message);
            $mail_message = str_replace('<base_url>', Url::base(), $mail_message);
            $mail_message = str_replace(
                '<activation_url>',
                Router::pathFor('profileAction', ['id' => $id, 'action' => 'change_email']).'?key='.
                $new_email_key,
                $mail_message
            );
            $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);
            $mail_message = Container::get('hooks')
                ->fire('model.profile.change_email_mail_activate_message', $mail_message);

            Container::get('email')->dispatchMail($new_email, $mail_subject, $mail_message);

            Container::get('hooks')->fire('model.profile.change_email_sent');

            throw new  RunBBException(__('Activate email sent').' <a href="mailto:'.
                Utils::escape(ForumSettings::get('o_admin_email')).'">'.
                Utils::escape(ForumSettings::get('o_admin_email')).'</a>.', true);
        }
        Container::get('hooks')->fire('model.profile.change_email');
    }

    public function uploadAvatar($id, $files_data)
    {
        $files_data = Container::get('hooks')->fire('model.profile.upload_avatar_start', $files_data, $id);

        if (!isset($files_data['req_file'])) {
            throw new  RunBBException(__('No file'));
        }

        $uploaded_file = $files_data['req_file'];

        // Make sure the upload went smooth
        if (isset($uploaded_file['error'])) {
            switch ($uploaded_file['error']) {
                case 1: // UPLOAD_ERR_INI_SIZE
                case 2: // UPLOAD_ERR_FORM_SIZE
                    throw new  RunBBException(__('Too large ini'));
                    break;

                case 3: // UPLOAD_ERR_PARTIAL
                    throw new  RunBBException(__('Partial upload'));
                    break;

                case 4: // UPLOAD_ERR_NO_FILE
                    throw new  RunBBException(__('No file'));
                    break;

                case 6: // UPLOAD_ERR_NO_TMP_DIR
                    throw new  RunBBException(__('No tmp directory'));
                    break;

                default:
                    // No error occured, but was something actually uploaded?
                    if ($uploaded_file['size'] == 0) {
                        throw new  RunBBException(__('No file'));
                    }
                    break;
            }
        }

        if (is_uploaded_file($uploaded_file['tmp_name'])) {
            $uploaded_file = Container::get('hooks')
                ->fire('model.profile.upload_avatar_is_uploaded_file', $uploaded_file);

            // Preliminary file check, adequate in most cases
            $allowed_types = ['image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png'];
            if (!in_array($uploaded_file['type'], $allowed_types)) {
                throw new  RunBBException(__('Bad type'));
            }

            // Make sure the file isn't too big
            if ($uploaded_file['size'] > ForumSettings::get('o_avatars_size')) {
                throw new  RunBBException(__('Too large').' '.
                    Utils::numberFormat(ForumSettings::get('o_avatars_size')).' '.__('bytes').'.');
            }

            // Move the file to the avatar directory. We do this before checking the width/height to
            // circumvent open_basedir restrictions
//            if (!@move_uploaded_file($uploaded_file['tmp_name'],
// ForumEnv::get('FORUM_ROOT').ForumSettings::get('o_avatars_dir').'/'.$id.'.tmp')) {
            if (!@move_uploaded_file($uploaded_file['tmp_name'], ForumEnv::get('WEB_ROOT').
                ForumSettings::get('o_avatars_dir').'/'.$id.'.tmp')) {
                throw new  RunBBException(__('Move failed').' <a href="mailto:'.
                    Utils::escape(ForumSettings::get('o_admin_email')).'">'.
                    Utils::escape(ForumSettings::get('o_admin_email')).'</a>.');
            }

//            list($width, $height, $type, ) = @getimagesize(ForumEnv::get('FORUM_ROOT').
//ForumSettings::get('o_avatars_dir').'/'.$id.'.tmp');
            list($width, $height, $type, ) = @getimagesize(ForumEnv::get('WEB_ROOT').
                ForumSettings::get('o_avatars_dir').'/'.$id.'.tmp');

            // Determine type
            if ($type == IMAGETYPE_GIF) {
                $extension = '.gif';
            } elseif ($type == IMAGETYPE_JPEG) {
                $extension = '.jpg';
            } elseif ($type == IMAGETYPE_PNG) {
                $extension = '.png';
            } else {
                // Invalid type
                @unlink(ForumEnv::get('WEB_ROOT').ForumSettings::get('o_avatars_dir').'/'.$id.'.tmp');
                throw new  RunBBException(__('Bad type'));
            }
//FIXME config path!!!
            // Now check the width/height
            if (empty($width) ||
                empty($height) ||
                $width > ForumSettings::get('o_avatars_width') ||
                $height > ForumSettings::get('o_avatars_height')) {
                @unlink(ForumEnv::get('WEB_ROOT').ForumSettings::get('o_avatars_dir').'/'.$id.'.tmp');
                throw new  RunBBException(__('Too wide or high').' '.
                    ForumSettings::get('o_avatars_width').'x'.
                    ForumSettings::get('o_avatars_height').' '.__('pixels').'.');
            }

            // Delete any old avatars and put the new one in place
            $this->deleteAvatar($id);
            @rename(
                ForumEnv::get('WEB_ROOT').ForumSettings::get('o_avatars_dir').'/'.$id.'.tmp',
                ForumEnv::get('WEB_ROOT').ForumSettings::get('o_avatars_dir').'/'.$id.$extension
            );
            @chmod(ForumEnv::get('WEB_ROOT').ForumSettings::get('o_avatars_dir').'/'.$id.$extension, 0644);
        } else {
            throw new  RunBBException(__('Unknown failure'));
        }

        $uploaded_file = Container::get('hooks')->fire('model.profile.upload_avatar', $uploaded_file);

        return Router::redirect(
            Router::pathFor('profileSection', ['id' => $id, 'section' => 'personality']),
            __('Avatar upload redirect')
        );
    }

    //
    // Deletes any avatars owned by the specified user ID
    //
    public function deleteAvatar($user_id)
    {
        $filetypes = ['jpg', 'gif', 'png'];

        // Delete user avatar
        foreach ($filetypes as $cur_type) {
            if (file_exists(ForumEnv::get('WEB_ROOT').ForumSettings::get('o_avatars_dir').'/'.$user_id.'.'.$cur_type)) {
                @unlink(ForumEnv::get('WEB_ROOT').ForumSettings::get('o_avatars_dir').'/'.$user_id.'.'.$cur_type);
            }
        }
    }

    public function updateGroupMembership($id)
    {
        $id = Container::get('hooks')->fire('model.profile.update_group_membership_start', $id);

        $new_group_id = intval(Input::post('group_id'));

        $old_group_id = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->select('group_id')
            ->where('id', $id);
        $old_group_id = Container::get('hooks')
            ->fireDB('model.profile.update_group_membership_old_group', $old_group_id);
        $old_group_id = $old_group_id->find_one();

        $update_group = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->where('id', $id)
            ->find_one()
            ->set('group_id', $new_group_id);
        $update_group = Container::get('hooks')
            ->fireDB('model.profile.update_group_membership_update_group', $update_group);
        $update_group = $update_group->save();

        // Regenerate the users info cache
        if (!Container::get('cache')->isCached('users_info')) {
            Container::get('cache')->store('users_info', Cache::getUsersInfo());
        }

        $stats = Container::get('cache')->retrieve('users_info');

        if ($old_group_id->group_id == ForumEnv::get('FEATHER_ADMIN') ||
            $new_group_id == ForumEnv::get('FEATHER_ADMIN')) {
            Container::get('cache')->store('admin_ids', Cache::getAdminIds());
        }

        $new_group_mod = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
            ->select('g_moderator')
            ->where('g_id', $new_group_id);
        $new_group_mod = Container::get('hooks')
            ->fireDB('model.profile.update_group_membership_new_mod', $new_group_mod);
        $new_group_mod = $new_group_mod->find_one();

        // If the user was a moderator or an administrator, we remove him/her f
        //rom the moderator list in all forums as well
        if ($new_group_id != ForumEnv::get('FEATHER_ADMIN') && $new_group_mod->g_moderator != '1') {
            // Loop through all forums
            $result = $this->loopModForums();

            foreach ($result as $cur_forum) {
                $cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : [];

                if (in_array($id, $cur_moderators)) {
                    $username = array_search($id, $cur_moderators);
                    unset($cur_moderators[$username]);

                    $update_forums = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
                        ->where('id', $cur_forum['id'])
                        ->find_one();

                    if (!empty($cur_moderators)) {
                        $update_forums = $update_forums->set('moderators', serialize($cur_moderators));
                    } else {
                        $update_forums = $update_forums->set_expr('moderators', 'NULL');
                    }
                    $update_forums = Container::get('hooks')
                        ->fireDB('model.profile.update_group_membership_mod_forums', $update_forums);
                    $update_forums = $update_forums->save();
                }
            }
        }

        $id = Container::get('hooks')->fire('model.profile.update_group_membership', $id);

        return Router::redirect(Router::pathFor(
            'profileSection',
            ['id' => $id, 'section' => 'admin']
        ), __('Group membership redirect'));
    }

    public function getUsername($id)
    {
        // Get the username of the user we are processing
        $username = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->select('username')
            ->where('id', $id)
            ->find_one();

        return Container::get('hooks')->fire('model.profile.get_username', $username->username);
    }

    public function loopModForums()
    {
        $result['select'] = ['id', 'moderators'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
            ->select_many($result['select']);
        $result = Container::get('hooks')->fireDB('model.profile.loop_mod_forums', $result);
        $result = $result->find_many();

        return $result;
    }

    public function updateModForums($id)
    {
        $username = $this->getUsername($id);

        $moderator_in = (Input::post('moderator_in')) ? array_keys(Input::post('moderator_in')) : [];

        // Loop through all forums
        $result = $this->loopModForums();

        foreach ($result as $cur_forum) {
            $cur_moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : [];
            // If the user should have moderator access (and he/she doesn't already have it)
            if (in_array($cur_forum['id'], $moderator_in) && !in_array($id, $cur_moderators)) {
                $cur_moderators[$username] = $id;
                uksort($cur_moderators, 'utf8_strcasecmp');

                $update_forums = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
                    ->where('id', $cur_forum['id'])
                    ->find_one()
                    ->set('moderators', serialize($cur_moderators));
                $update_forums = Container::get('hooks')
                    ->fireDB('model.profile.update_mod_forums_query', $update_forums);
                $update_forums = $update_forums->save();
            } // If the user shouldn't have moderator access (and he/she already has it)
            elseif (!in_array($cur_forum['id'], $moderator_in) && in_array($id, $cur_moderators)) {
                unset($cur_moderators[$username]);

                $update_forums = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
                    ->where('id', $cur_forum['id'])
                    ->find_one();

                if (!empty($cur_moderators)) {
                    $update_forums = $update_forums->set('moderators', serialize($cur_moderators));
                } else {
                    $update_forums = $update_forums->set_expr('moderators', 'NULL');
                }
                $update_forums = Container::get('hooks')
                    ->fireDB('model.profile.update_mod_forums_query', $update_forums);
                $update_forums = $update_forums->save();
            }
        }

        $id = Container::get('hooks')->fire('model.profile.update_mod_forums', $id);

        return Router::redirect(Router::pathFor(
            'profileSection',
            ['id' => $id, 'section' => 'admin']
        ), __('Update forums redirect'));
    }

    public function banUser($id)
    {
        $id = Container::get('hooks')->fire('model.profile.ban_user_start', $id);

        // Get the username of the user we are banning
        $username = $this->getUsername($id);

        // Check whether user is already banned
        $ban_id = \ORM::for_table(ORM_TABLE_PREFIX.'bans')
            ->select('id')
            ->where('username', $username)
            ->order_by_expr('expire IS NULL DESC')
            ->order_by_desc('expire');
        $ban_id = Container::get('hooks')->fireDB('model.profile.ban_user_query', $ban_id);
        $ban_id = $ban_id->find_one();

        if ($ban_id && $ban_id->id) {
            return Router::redirect(Router::pathFor('editBan', ['id' => $ban_id->id]), __('Ban redirect'));
        } else {
            return Router::redirect(Router::pathFor('addBan', ['id' => $id]), __('Ban redirect'));
        }
    }

    public function promoteUser($id)
    {
        $id = Container::get('hooks')->fire('model.profile.promote_user_start', $id);

        $pid = Input::query('pid') ? intval(Input::query('pid')) : 0;

        // Find the group ID to promote the user to
        $next_group_id = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
            ->select('g.g_promote_next_group')
            ->table_alias('g')
            ->inner_join(ORM_TABLE_PREFIX.'users', ['u.group_id', '=', 'g.g_id'], 'u')
            ->where('u.id', $id);
        $next_group_id = Container::get('hooks')->fireDB('model.profile.promote_user_group_id', $next_group_id);
        $next_group_id = $next_group_id->find_one();

        if (!$next_group_id->g_promote_next_group) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        // Update the user
        $update_user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->where('id', $id)
            ->find_one()
            ->set('group_id', $next_group_id->g_promote_next_group);
        $update_user = Container::get('hooks')->fireDB('model.profile.promote_user_query', $update_user);
        $update_user = $update_user->save();

        $pid = Container::get('hooks')->fire('model.profile.promote_user', $pid);

        return Router::redirect(Router::pathFor('viewPost', ['pid' => $pid]).'#p'.$pid, __('User promote redirect'));
    }

    public function deleteUser($id)
    {
        $id = Container::get('hooks')->fire('model.profile.delete_user_start', $id);

        // Get the username and group of the user we are deleting
        $result['select'] = ['group_id', 'username'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->where('id', $id)
            ->select_many($result['select']);
        $result = Container::get('hooks')->fireDB('model.profile.delete_user_username', $result);
        $result = $result->find_one();

        $group_id = $result['group_id'];
        $username = $result['username'];

        if ($group_id == ForumEnv::get('FEATHER_ADMIN')) {
            throw new  RunBBException(__('No delete admin message'));
        }

        if (Input::post('delete_user_comply')) {
            // If the user is a moderator or an administrator, we remove him/her from the moderator
            // list in all forums as well
            $group_mod = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                ->select('g_moderator')
                ->where('g_id', $group_id);
            $group_mod = Container::get('hooks')->fireDB('model.profile.delete_user_group_mod', $group_mod);
            $group_mod = $group_mod->find_one();

            if ($group_id == ForumEnv::get('FEATHER_ADMIN') || $group_mod->g_moderator == '1') {
                // Loop through all forums
                $result = $this->loopModForums();

                foreach ($result as $cur_forum) {
                    $cur_moderators = ($cur_forum['moderators'] != '') ?
                        unserialize($cur_forum['moderators']) : [];

                    if (in_array($id, $cur_moderators)) {
                        unset($cur_moderators[$username]);

                        $update_forums = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
                            ->where('id', $cur_forum['id'])
                            ->find_one();

                        if (!empty($cur_moderators)) {
                            $update_forums = $update_forums->set('moderators', serialize($cur_moderators));
                        } else {
                            $update_forums = $update_forums->set_expr('moderators', 'NULL');
                        }
                        $update_forums = Container::get('hooks')
                            ->fireDB('model.profile.update_mod_forums_query', $update_forums);
                        $update_forums = $update_forums->save();
                    }
                }
            }

            // Delete any subscriptions
            $delete_subscriptions = \ORM::for_table(ORM_TABLE_PREFIX.'topic_subscriptions')
                ->where('user_id', $id);
            $delete_subscriptions = Container::get('hooks')
                ->fireDB('model.profile.delete_user_subscriptions_topic', $delete_subscriptions);
            $delete_subscriptions = $delete_subscriptions->delete_many();
            unset($delete_subscriptions);
            $delete_subscriptions = \ORM::for_table(ORM_TABLE_PREFIX.'forum_subscriptions')
                ->where('user_id', $id);
            $delete_subscriptions = Container::get('hooks')
                ->fireDB('model.profile.delete_user_subscriptions_forum', $delete_subscriptions);
            $delete_subscriptions = $delete_subscriptions->delete_many();

            // Remove him/her from the online list (if they happen to be logged in)
            $delete_online = \ORM::for_table(ORM_TABLE_PREFIX.'online')
                ->where('user_id', $id);
            $delete_online = Container::get('hooks')->fireDB('model.profile.delete_user_online', $delete_online);
            $delete_online = $delete_online->delete_many();

            // Should we delete all posts made by this user?
            if (Input::post('delete_posts')) {
                // Hold on, this could take some time!
                @set_time_limit(0);

                Container::get('hooks')->fire('model.profile.delete_user_posts');

                // Find all posts made by this user
                unset($result);
                $result['select'] = ['p.id', 'p.topic_id', 't.forum_id'];

                $result = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                    ->table_alias('p')
                    ->select_many($result['select'])
                    ->inner_join(ORM_TABLE_PREFIX.'topics', ['t.id', '=', 'p.topic_id'], 't')
                    ->inner_join(ORM_TABLE_PREFIX.'forums', ['f.id', '=', 't.forum_id'], 'f')
                    ->where('p.poster_id', $id);
                $result = Container::get('hooks')->fireDB('model.profile.delete_user_posts_first_query', $result);
                $result = $result->find_many();

                if ($result) {
                    foreach ($result as $cur_post) {
                        // Determine whether this post is the "topic post" or not
                        $result2 = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                            ->select('id')
                            ->where('topic_id', $cur_post['topic_id'])
                            ->order_by_expr('posted');
                        $result2 = Container::get('hooks')
                            ->fireDB('model.profile.delete_user_posts_second_query', $result2);
                        $result2 = $result2->find_one();

                        if ($result2->id == $cur_post['id']) {
                            \ORM::forTable(ORM_TABLE_PREFIX.'topics')
                                ->where('id', $cur_post['topic_id'])
                                ->findOne()
                                ->delete();
                        } else {
                           \ORM::forTable(ORM_TABLE_PREFIX.'posts')
                               ->where('id', $cur_post['id'])
                               ->where('topic_id', $cur_post['topic_id'])
                               ->findOne()
                               ->delete();
                        }

                        Forum::update($cur_post['forum_id']);
                    }
                }
            } else {
                // Set all his/her posts to guest
                $update_guest = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                    ->where_in('poster_id', '1')
                    ->find_one();
                $update_guest = Container::get('hooks')
                    ->fireDB('model.profile.delete_user_posts_guest_query', $update_guest);
                $update_guest->set(['poster_id' => $id])
                    ->save();
            }

            // Delete the user
            $delete_user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                            ->where('id', $id);
            $delete_user = $delete_user->delete_many();

            // Delete user avatar
            $this->deleteAvatar($id);

            // Regenerate the users info cache
            Container::get('cache')->store('users_info', Cache::getUsersInfo());

            $stats = Container::get('cache')->retrieve('users_info');

            if ($group_id == ForumEnv::get('FEATHER_ADMIN')) {
                Container::get('cache')->store('admin_ids', Cache::getAdminIds());
            }

            Container::get('hooks')->fire('model.profile.delete_user');

            return Router::redirect(Router::pathFor('home'), __('User delete redirect'));
        }
    }

    public function fetchUserGroup($id)
    {
        $info = [];

        $info['select'] = [
            'old_username' => 'u.username',
            'group_id' => 'u.group_id',
            'is_moderator' => 'g.g_moderator'
        ];

        $info = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->table_alias('u')
            ->select_many($info['select'])
            ->left_outer_join(ORM_TABLE_PREFIX.'groups', ['g.g_id', '=', 'u.group_id'], 'g')
            ->where('u.id', $id);
        $info = Container::get('hooks')->fireDB('model.profile.fetch_user_group', $info);
        $info = $info->find_one();

        if (!$info) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        return $info;
    }

    public function updateProfile($id, $info, $section)
    {
        $info = Container::get('hooks')->fire('model.profile.update_profile_start', $info, $id, $section);

        $username_updated = false;

        $section = Container::get('hooks')->fire('model.profile.update_profile_section', $section, $id, $info);

        // Validate input depending on section
        switch ($section) {
            case 'essentials':
                $form = [
                    'timezone'        => floatval(Input::post('form_timezone')),
                    'dst'            => Input::post('form_dst') ? '1' : '0',
                    'time_format'    => intval(Input::post('form_time_format')),
                    'date_format'    => intval(Input::post('form_date_format')),
                ];

                // Make sure we got a valid language string
                if (Input::post('form_language')) {
                    $languages = Lang::getList();
                    $form['language'] = Utils::trim(Input::post('form_language'));
                    if (!Utils::recursiveArraySearch($form['language'], $languages)) {
                        throw new  RunBBException('Language not exists', 404);
                    }
                }

                if (User::get()->is_admmod) {
                    $form['admin_note'] = Utils::trim(Input::post('admin_note'));

                    // Are we allowed to change usernames?
                    if (User::get()->g_id == ForumEnv::get('FEATHER_ADMIN') ||
                        (User::get()->g_moderator == '1' && User::get()->g_mod_rename_users == '1')) {
                        $form['username'] = Utils::trim(Input::post('req_username'));

                        if ($form['username'] != $info['old_username']) {
                            $errors = '';
                            $errors = $this->checkUsername($form['username'], $errors, $id);
                            if (!empty($errors)) {
                                throw new  RunBBException($errors[0]);
                            }

                            $username_updated = true;
                        }
                    }

                    // We only allow administrators to update the post count
                    if (User::get()->g_id == ForumEnv::get('FEATHER_ADMIN')) {
                        $form['num_posts'] = intval(Input::post('num_posts'));
                    }
                }

                if (ForumSettings::get('o_regs_verify') == '0' || User::get()->is_admmod) {
                    // Validate the email address
                    $form['email'] = strtolower(Utils::trim(Input::post('req_email')));
                    if (!Container::get('email')->isValidEmail($form['email'])) {
                        throw new  RunBBException(__('Invalid email'));
                    }
                }

                break;


            case 'personal':
                $form = [
                    'realname'     => Input::post('form_realname') ? Utils::trim(Input::post('form_realname')) : '',
                    'url'          => Input::post('form_url') ? Utils::trim(Input::post('form_url')) : '',
                    'location'     => Input::post('form_location') ? Utils::trim(Input::post('form_location')) : '',
                ];

                // Add http:// if the URL doesn't contain it already (while allowing https://, too)
                if (User::get()->g_post_links == '1') {
                    if ($form['url'] != '') {
                        $url = Url::isValidUrl($form['url']);

                        if ($url === false) {
                            throw new  RunBBException(__('Invalid website URL'));
                        }

                        $form['url'] = $url['url'];
                    }
                } else {
                    if (!empty($form['url'])) {
                        throw new  RunBBException(__('Website not allowed'));
                    }

                    $form['url'] = '';
                }

                if (User::get()->g_id == ForumEnv::get('FEATHER_ADMIN')) {
                    $form['title'] = Utils::trim(Input::post('title'));
                } elseif (User::get()->g_set_title == '1') {
                    $form['title'] = Utils::trim(Input::post('title'));

                    if ($form['title'] != '') {
                        // A list of words that the title may not contain
                        // If the language is English, there will be some duplicates, but it's not the end of the world
                        $forbidden = ['member', 'moderator', 'administrator', 'banned', 'guest',
                            utf8_strtolower(__('Member')),
                            utf8_strtolower(__('Moderator')),
                            utf8_strtolower(__('Administrator')),
                            utf8_strtolower(__('Banned')),
                            utf8_strtolower(__('Guest'))];

                        if (in_array(utf8_strtolower($form['title']), $forbidden)) {
                            throw new  RunBBException(__('Forbidden title'));
                        }
                    }
                }

                break;

            case 'messaging':
                $form = [
                    'jabber'        => Utils::trim(Input::post('form_jabber')),
                    'icq'            => Utils::trim(Input::post('form_icq')),
                    'msn'            => Utils::trim(Input::post('form_msn')),
                    'aim'            => Utils::trim(Input::post('form_aim')),
                    'yahoo'            => Utils::trim(Input::post('form_yahoo')),
                ];

                // If the ICQ UIN contains anything other than digits it's invalid
                if (preg_match('%[^0-9]%', $form['icq'])) {
                    throw new  RunBBException(__('Bad ICQ'));
                }

                break;


            case 'personality':
                $form = [];

                // Clean up signature from POST
                if (ForumSettings::get('o_signatures') == '1') {
                    $form['signature'] = Utils::linebreaks(Utils::trim(Input::post('signature')));

                    // Validate signature
                    if (Utils::strlen($form['signature']) > ForumSettings::get('p_sig_length')) {
                        throw new  RunBBException(sprintf(
                            __('Sig too long'),
                            ForumSettings::get('p_sig_length'),
                            Utils::strlen($form['signature']) - ForumSettings::get('p_sig_length')
                        ));
                    } elseif (substr_count($form['signature'], "\n") > (ForumSettings::get('p_sig_lines')-1)) {
                        throw new  RunBBException(sprintf(__('Sig too many lines'), ForumSettings::get('p_sig_lines')));
                    } elseif ($form['signature'] &&
                        ForumSettings::get('p_sig_all_caps') == '0' &&
                        Utils::isAllUppercase($form['signature']) &&
                        !User::get()->is_admmod) {
                        $form['signature'] = utf8_ucwords(utf8_strtolower($form['signature']));
                    }

                    // Validate BBCode syntax
                    if (ForumSettings::get('p_sig_bbcode') == '1') {
                        $errors = [];

                        $form['signature'] = Container::get('parser')
                            ->parseForSave($form['signature'], $errors, true);

                        if (count($errors) > 0) {
                            throw new  RunBBException('<ul><li>'.implode('</li><li>', $errors).'</li></ul>');
                        }
                    }
                }

                break;

            case 'display':
                $form = [
                    'disp_topics'        => Utils::trim(Input::post('form_disp_topics')),
                    'disp_posts'        => Utils::trim(Input::post('form_disp_posts')),
                    'show_smilies'        => Input::post('form_show_smilies') ? '1' : '0',
                    'show_img'            => Input::post('form_show_img') ? '1' : '0',
                    'show_img_sig'        => Input::post('form_show_img_sig') ? '1' : '0',
                    'show_avatars'        => Input::post('form_show_avatars') ? '1' : '0',
                    'show_sig'            => Input::post('form_show_sig') ? '1' : '0',
                ];

                // empty for live blank and use default
//                if ($form['disp_topics'] !== '') {
                if (!empty($form['disp_topics'])) {
                    $form['disp_topics'] = (int)$form['disp_topics'];
                    if ($form['disp_topics'] < 3) {
                        $form['disp_topics'] = 3;
                    } elseif ($form['disp_topics'] > 75) {
                        $form['disp_topics'] = 75;
                    }
                } else {
                    $form['disp_topics'] = null;
                }

//                if ($form['disp_posts'] !== '') {
                if (!empty($form['disp_posts'])) {
                    $form['disp_posts'] = (int)$form['disp_posts'];
                    if ($form['disp_posts'] < 3) {
                        $form['disp_posts'] = 3;
                    } elseif ($form['disp_posts'] > 75) {
                        $form['disp_posts'] = 75;
                    }
                } else {
                    $form['disp_posts'] = null;
                }

                // Make sure we got a valid style string
                if (Input::post('form_style')) {
                    $styles = \RunBB\Core\Lister::getStyles();
                    $form['style'] = Utils::trim(Input::post('form_style'));
                    if (!in_array($form['style'], $styles)) {
                        throw new  RunBBException(__('Bad request'), 404);
                    }
                }

                break;

            case 'privacy':
                $form = [
                    'email_setting'            => intval(Input::post('form_email_setting')),
                    'notify_with_post'        => Input::post('form_notify_with_post') ? '1' : '0',
                    'auto_notify'            => Input::post('form_auto_notify') ? '1' : '0',
                ];

                if ($form['email_setting'] < 0 || $form['email_setting'] > 2) {
                    $form['email_setting'] = ForumSettings::get('o_default_email_setting');
                }

                break;

            default:
                throw new  RunBBException(__('Bad request'), 404);
        }

        $form = Container::get('hooks')->fire('model.profile.update_profile_form', $form, $section, $id, $info);

        // Single quotes around non-empty values and nothing for empty values
        $temp = [];
        foreach ($form as $key => $input) {
            $temp[$key] = $input;
        }

        if (empty($temp)) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        $update_user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->where('id', $id)
            ->find_one()
            ->set($temp);
        $update_user = Container::get('hooks')->fireDB('model.profile.update_profile_query', $update_user);
        $update_user = $update_user->save();

        // If we changed the username we have to update some stuff
        if ($username_updated) {
            $bans_updated = \ORM::for_table(ORM_TABLE_PREFIX.'bans')
                ->where('username', $info['old_username'])
                ->find_one();
            $bans_updated = Container::get('hooks')
                ->fireDB('model.profile.update_profile_bans_updated', $bans_updated);
            $bans_updated->set(['username' => $form['username']])
                ->save();

            $update_poster_id = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                ->where('poster_id', $id)
                ->find_one();
            $update_poster_id = Container::get('hooks')
                ->fireDB('model.profile.update_profile_poster_id', $update_poster_id);
            $update_poster_id->set(['poster' => $form['username']])
                ->save();

            $update_posts = \ORM::for_table(ORM_TABLE_PREFIX.'posts')
                ->where('edited_by', $info['old_username'])
                ->find_one();
            $update_posts = Container::get('hooks')->fireDB('model.profile.update_profile_posts', $update_posts);
            $update_posts->set(['edited_by' => $form['username']])
                ->save();

            $update_topics_poster = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                ->where('poster', $info['old_username'])
                ->find_one();
            $update_topics_poster = Container::get('hooks')
                ->fireDB('model.profile.update_profile_topics_poster', $update_topics_poster);
            $update_topics_poster->set(['poster' => $form['username']])
                ->save();

            $update_topics_last_poster = \ORM::for_table(ORM_TABLE_PREFIX.'topics')
                ->where('last_poster', $info['old_username'])
                ->find_one();
            $update_topics_last_poster = Container::get('hooks')
                ->fireDB('model.profile.update_profile_topics_last_poster', $update_topics_last_poster);
            $update_topics_last_poster->set(['last_poster' => $form['username']])
                ->save();

            $update_forums = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
                ->where('last_poster', $info['old_username'])
                ->find_one();
            $update_forums = Container::get('hooks')->fireDB('model.profile.update_profile_forums', $update_forums);
            $update_forums->set(['last_poster' => $form['username']])
                ->save();

            $update_online = \ORM::for_table(ORM_TABLE_PREFIX.'online')
                ->where('ident', $info['old_username'])
                ->find_one();
            $update_online = Container::get('hooks')->fireDB('model.profile.update_profile_online', $update_online);
            $update_online->set(['ident' => $form['username']])
                ->save();

            // If the user is a moderator or an administrator we have to update the moderator lists
            $group_id = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->select('group_id')
                ->where('id', $id);
            // TODO: restore hook
            $group_id = Container::get('hooks')->fireDB('model.profile.update_profile_group_id', $group_id);
            $group_id = $group_id->find_one();

            $group_mod = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
                ->select('g_moderator')
                ->where('g_id', $group_id->group_id);
            $group_mod = Container::get('hooks')->fireDB('model.profile.update_profile_group_mod', $group_mod);
            $group_mod = $group_mod->find_one();

            if ($group_id->group_id == ForumEnv::get('FEATHER_ADMIN') || $group_mod->g_moderator == '1') {
                // Loop through all forums
                $result = $this->loopModForums();

                foreach ($result as $cur_forum) {
                    $cur_moderators = ($cur_forum['moderators'] != '') ?
                        unserialize($cur_forum['moderators']) : [];

                    if (in_array($id, $cur_moderators)) {
                        unset($cur_moderators[$info['old_username']]);
                        $cur_moderators[$form['username']] = $id;
                        uksort($cur_moderators, 'utf8_strcasecmp');

                        $update_mods = \ORM::for_table(ORM_TABLE_PREFIX.'forums')
                            ->where('id', $cur_forum['id'])
                            ->find_one()
                            ->set('moderators', serialize($cur_moderators));
                        $update_mods = Container::get('hooks')
                            ->fireDB('model.profile.update_profile_mods', $update_mods);
                        $update_mods = $update_mods->save();
                    }
                }
            }

            // Regenerate the users info cache
            if (!Container::get('cache')->isCached('users_info')) {
                Container::get('cache')->store('users_info', Cache::getUsersInfo());
            }

            $stats = Container::get('cache')->retrieve('users_info');

            // Check if the bans table was updated and regenerate the bans cache when needed
            if ($bans_updated) {
                Container::get('cache')->store('bans', Cache::getBans());
            }
        }

        $section = Container::get('hooks')->fireDB('model.profile.update_profile', $section, $id);

        return Router::redirect(Router::pathFor(
            'profileSection',
            ['id' => $id, 'section' => $section]
        ), __('Profile redirect'));
    }

    public function getUserInfo($id)
    {
        $user['select'] = [
            'u.id',
            'u.username',
            'u.email',
            'u.title',
            'u.realname',
            'u.url',
            'u.jabber',
            'u.icq',
            'u.msn',
            'u.aim',
            'u.yahoo',
            'u.location',
            'u.signature',
            'u.disp_topics',
            'u.disp_posts',
            'u.email_setting',
            'u.notify_with_post',
            'u.auto_notify',
            'u.show_smilies',
            'u.show_img',
            'u.show_img_sig',
            'u.show_avatars',
            'u.show_sig',
            'u.timezone',
            'u.dst',
            'u.language', 'u.style', 'u.num_posts', 'u.last_post', 'u.registered', 'u.registration_ip',
            'u.admin_note', 'u.date_format', 'u.time_format', 'u.last_visit', 'g.g_id', 'g.g_user_title',
            'g.g_moderator'
        ];

        $user = \ORM::for_table(ORM_TABLE_PREFIX.'users')
            ->table_alias('u')
            ->select_many($user['select'])
            ->left_outer_join(ORM_TABLE_PREFIX.'groups', ['g.g_id', '=', 'u.group_id'], 'g')
            ->where('u.id', $id);
        $user = Container::get('hooks')->fireDB('model.profile.get_user_info', $user);
        $user = $user->find_one();

        if (!$user) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        return $user;
    }

    public function parseUserInfo($user)
    {
        $user_info = [];

        $user_info = Container::get('hooks')->fire('model.profile.parse_user_info_start', $user_info, $user);

        $user_info['personal'][] = '<dt>'.__('Username').'</dt>';
        $user_info['personal'][] = '<dd>'.Utils::escape($user->username).'</dd>';

        $user_title_field = Utils::getTitle($user->title);
        $user_info['personal'][] = '<dt>'.__('Title').'</dt>';
        $user_info['personal'][] = '<dd>'.((ForumSettings::get('o_censoring') == '1') ?
                Utils::censor($user_title_field) : $user_title_field).'</dd>';

        if ($user->realname != '') {
            $user_info['personal'][] = '<dt>'.__('Realname').'</dt>';
            $user_info['personal'][] = '<dd>'.Utils::escape((ForumSettings::get('o_censoring') == '1') ?
                    Utils::censor($user->realname) : $user->realname).'</dd>';
        }

        if ($user->location != '') {
            $user_info['personal'][] = '<dt>'.__('Location').'</dt>';
            $user_info['personal'][] = '<dd>'.Utils::escape((ForumSettings::get('o_censoring') == '1') ?
                    Utils::censor($user->location) : $user->location).'</dd>';
        }

        if ($user->url != '') {
            $user->url = Utils::escape((ForumSettings::get('o_censoring') == '1') ?
                Utils::censor($user->url) : $user->url);
            $user_info['personal'][] = '<dt>'.__('Website').'</dt>';
            $user_info['personal'][] = '<dd><span class="website"><a href="'.$user->url.'" rel="nofollow">'.
                $user->url.'</a></span></dd>';
        }

        if ($user->email_setting == '0' && !User::get()->is_guest && User::get()->g_send_email == '1') {
            $user->email_field = '<a href="mailto:'.Utils::escape($user->email).'">'.Utils::escape($user->email).'</a>';
        } elseif ($user->email_setting == '1' && !User::get()->is_guest && User::get()->g_send_email == '1') {
            $user->email_field = '<a href="'.Router::pathFor('email', ['id' => $user->id]).'">'.__('Send email').'</a>';
        } else {
            $user->email_field = '';
        }
        if ($user->email_field != '') {
            $user_info['personal'][] = '<dt>'.__('Email').'</dt>';
            $user_info['personal'][] = '<dd><span class="email">'.$user->email_field.'</span></dd>';
        }

        if ($user->jabber != '') {
            $user_info['messaging'][] = '<dt>'.__('Jabber').'</dt>';
            $user_info['messaging'][] = '<dd>'.Utils::escape((ForumSettings::get('o_censoring') == '1') ?
                    Utils::censor($user->jabber) : $user->jabber).'</dd>';
        }

        if ($user->icq != '') {
            $user_info['messaging'][] = '<dt>'.__('ICQ').'</dt>';
            $user_info['messaging'][] = '<dd>'.$user->icq.'</dd>';
        }

        if ($user->msn != '') {
            $user_info['messaging'][] = '<dt>'.__('MSN').'</dt>';
            $user_info['messaging'][] = '<dd>'.Utils::escape((ForumSettings::get('o_censoring') == '1') ?
                    Utils::censor($user->msn) : $user->msn).'</dd>';
        }

        if ($user->aim != '') {
            $user_info['messaging'][] = '<dt>'.__('AOL IM').'</dt>';
            $user_info['messaging'][] = '<dd>'.Utils::escape((ForumSettings::get('o_censoring') == '1') ?
                    Utils::censor($user->aim) : $user->aim).'</dd>';
        }

        if ($user->yahoo != '') {
            $user_info['messaging'][] = '<dt>'.__('Yahoo').'</dt>';
            $user_info['messaging'][] = '<dd>'.Utils::escape((ForumSettings::get('o_censoring') == '1') ?
                    Utils::censor($user->yahoo) : $user->yahoo).'</dd>';
        }

        if (ForumSettings::get('o_avatars') == '1') {
            $avatar_field = Utils::generateAvatarMarkup($user->id);
            if ($avatar_field != '') {
                $user_info['personality'][] = '<dt>'.__('Avatar').'</dt>';
                $user_info['personality'][] = '<dd>'.$avatar_field.'</dd>';
            }
        }

        if (ForumSettings::get('o_signatures') == '1') {
            if (isset($parsed_signature)) {
                $user_info['personality'][] = '<dt>'.__('Signature').'</dt>';
                $user_info['personality'][] = '<dd><div class="postsignature postmsg">'.$parsed_signature.'</div></dd>';
            }
        }

        $posts_field = '';
        if (ForumSettings::get('o_show_post_count') == '1' || User::get()->is_admmod) {
            $posts_field = Utils::numberFormat($user['num_posts']);
        }
        if (User::get()->g_search == '1') {
            $quick_searches = [];
            if ($user->num_posts > 0) {
                $quick_searches[] = '<a href="'.Router::pathFor('search').
                    '?action=show_user_topics&amp;user_id='.$user->id.'">'.__('Show topics').'</a>';
                $quick_searches[] = '<a href="'.Router::pathFor('search').
                    '?action=show_user_posts&amp;user_id='.$user->id.'">'.__('Show posts').'</a>';
            }
            if (User::get()->is_admmod && ForumSettings::get('o_topic_subscriptions') == '1') {
                $quick_searches[] = '<a href="'.Router::pathFor('search').
                    '?action=show_subscriptions&amp;user_id='.$user->id.'">'.__('Show subscriptions').'</a>';
            }

            if (!empty($quick_searches)) {
                $posts_field .= (($posts_field != '') ? ' - ' : '').implode(' - ', $quick_searches);
            }
        }
        if ($posts_field != '') {
            $user_info['activity'][] = '<dt>'.__('Posts').'</dt>';
            $user_info['activity'][] = '<dd>'.$posts_field.'</dd>';
        }

        if ($user->num_posts > 0) {
            $user_info['activity'][] = '<dt>'.__('Last post').'</dt>';
            $user_info['activity'][] = '<dd>'.Utils::timeFormat($user->last_post).'</dd>';
        }

        $user_info['activity'][] = '<dt>'.__('Registered').'</dt>';
        $user_info['activity'][] = '<dd>'.Utils::timeFormat($user->registered, true).'</dd>';

        $user_info = Container::get('hooks')->fire('model.profile.parse_user_info', $user_info);

        return $user_info;
    }

    public function editEssentials($id, $user)
    {
        $user_disp = [];

        $user_disp = Container::get('hooks')->fire('model.profile.edit_essentials_start', $user_disp, $id, $user);

        if (User::get()->is_admmod) {
            if (User::get()->g_id == ForumEnv::get('FEATHER_ADMIN') || User::get()->g_mod_rename_users == '1') {
                $user_disp['username_field'] = '<label class="required">'.__('Username').' <span>'.
                    __('Required').'</span><br /><input class="form-control" type="text" name="req_username" value="'.
                    Utils::escape($user->username).'" size="25" maxlength="25" /></label>'."\n";
            } else {
                $user_disp['username_field'] = '<p>'.sprintf(
                    __('Username info'),
                    Utils::escape($user->username)
                ).'</p>'."\n";
            }

            $user_disp['email_field'] = '<label class="required">'.__('Email').' <span>'.
                __('Required').'</span><br /><input class="form-control" type="email" name="req_email" value="'.
                Utils::escape($user->email).
                '" maxlength="80" /><br /></label><p><span class="email"><a href="'
                .Router::pathFor('email', ['id' => $id]).'">'.__('Send email').'</a></span></p>'."\n";
        } else {
            $user_disp['username_field'] = '<p>'.__('Username').': '.Utils::escape($user->username).'</p>'."\n";

            if (ForumSettings::get('o_regs_verify') == '1') {
                $user_disp['email_field'] = '<p>'.sprintf(__('Email info'), Utils::escape($user->email).' - <a href="'.
                        Router::pathFor('profileAction', ['id' => $id, 'action' => 'change_email']).'">'.
                        __('Change email').'</a>').'</p>'."\n";
            } else {
                $user_disp['email_field'] = '<label class="required">'.__('Email').' <span>'.__('Required').
                    '</span><br /><input class="form-control" type="text" name="req_email" value="'.$user->email.
                    '"maxlength="80" /></label>'."\n";
            }
        }

        $user_disp['posts_field'] = '';
        $posts_actions = [];

        if (User::get()->g_id == ForumEnv::get('FEATHER_ADMIN')) {
            $user_disp['posts_field'] .= '<label>'.__('Posts').'<br /><input type="text" name="num_posts" value="'.
                $user->num_posts.'" size="8" maxlength="8" /><br /></label>';
        } elseif (ForumSettings::get('o_show_post_count') == '1' || User::get()->is_admmod) {
            $posts_actions[] = sprintf(__('Posts info'), Utils::numberFormat($user->num_posts));
        }

        if (User::get()->g_search == '1' || User::get()->g_id == ForumEnv::get('FEATHER_ADMIN')) {
            $posts_actions[] = '<a href="'.Router::pathFor('search').'?action=show_user_topics&amp;user_id='.$id.'">'.
                __('Show topics').'</a>';
            $posts_actions[] = '<a href="'.Router::pathFor('search').'?action=show_user_posts&amp;user_id='.$id.'">'.
                __('Show posts').'</a>';

            if (ForumSettings::get('o_topic_subscriptions') == '1') {
                $posts_actions[] = '<a href="'.Router::pathFor('search').'?action=show_subscriptions&amp;user_id='.$id.
                    '">'.__('Show subscriptions').'</a>';
            }
        }

        $user_disp['posts_field'] .= (!empty($posts_actions) ? '<p class="actions">'.implode(' - ', $posts_actions).
                '</p>' : '')."\n";

        $user_disp = Container::get('hooks')->fire('model.profile.edit_essentials', $user_disp);

        return $user_disp;
    }

    public function getGroupList($user)
    {
        $output = '';

        $user = Container::get('hooks')->fire('model.profile.get_group_list_start', $user);

        $result['select'] = ['g_id', 'g_title'];

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'groups')
            ->select_many($result['select'])
            ->where_not_equal('g_id', ForumEnv::get('FEATHER_GUEST'))
            ->order_by_expr('g_title');
        $result = Container::get('hooks')->fireDB('model.profile.get_group_list_query', $result);
        $result = $result->find_many();

        foreach ($result as $cur_group) {
            if ($cur_group['g_id'] == $user->g_id ||
                ($cur_group['g_id'] == ForumSettings::get('o_default_user_group') && $user->g_id == '')) {
                $output .= "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'" selected="selected">'.
                    Utils::escape($cur_group['g_title']).'</option>'."\n";
            } else {
                $output .= "\t\t\t\t\t\t\t\t".'<option value="'.$cur_group['g_id'].'">'.
                    Utils::escape($cur_group['g_title']).'</option>'."\n";
            }
        }

        $output = Container::get('hooks')->fire('model.profile.get_group_list', $output);

        return $output;
    }

    public function getForumList($id)
    {
        $output = '';

        $id = Container::get('hooks')->fire('model.profile.get_forum_list_start', $id);

        $result['select'] = ['cid' => 'c.id', 'c.cat_name', 'fid' => 'f.id', 'f.forum_name', 'f.moderators'];
        $result['order_by'] = 'c.disp_position, c.id, f.disp_position';

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'categories')
            ->table_alias('c')
            ->select_many($result['select'])
            ->inner_join(ORM_TABLE_PREFIX.'forums', ['c.id', '=', 'f.cat_id'], 'f')
            ->where_null('f.redirect_url')
            ->order_by_expr($result['order_by']);
        $result = Container::get('hooks')->fireDB('model.profile.get_forum_list', $result);
        $result = $result->find_many();

        $cur_category = 0;
        foreach ($result as $cur_forum) {
            if ($cur_forum['cid'] != $cur_category) {
                // A new category since last iteration?
                if ($cur_category) {
                    $output .= "\n\t\t\t\t\t\t\t\t".'</div>';
                }

                if ($cur_category != 0) {
                    $output .= "\n\t\t\t\t\t\t\t".'</div>'."\n";
                }

                $output .= "\t\t\t\t\t\t\t".'<div class="conl">'."\n\t\t\t\t\t\t\t\t".'<p><strong>'.
                    Utils::escape($cur_forum['cat_name']).'</strong></p>'."\n\t\t\t\t\t\t\t\t".'<div class="rbox">';
                $cur_category = $cur_forum['cid'];
            }

            $moderators = ($cur_forum['moderators'] != '') ? unserialize($cur_forum['moderators']) : [];

            $output .= "\n\t\t\t\t\t\t\t\t\t".'<label><input type="checkbox" name="moderator_in['.$cur_forum['fid'].
                ']" value="1"'.((in_array($id, $moderators)) ? ' checked="checked"' : '').' />'.
                Utils::escape($cur_forum['forum_name']).'<br /></label>'."\n";
        }

        $output = Container::get('hooks')->fire('model.profile.get_forum_list', $output);

        return $output;
    }

    //
    // Check username
    //
    public function checkUsername($username, $errors, $exclude_id = null)
    {
        // Include UTF-8 function
        require_once ForumEnv::get('FORUM_ROOT').'Helpers/utf8/strcasecmp.php';

        Lang::load('register');
        Lang::load('prof_reg');

        // Convert multiple whitespace characters into one (to prevent people from registering with
        // indistinguishable usernames)
        $username = preg_replace('%\s+%s', ' ', $username);

        // Validate username
        if (Utils::strlen($username) < 2) {
            $errors[] = __('Username too short');
        } elseif (Utils::strlen($username) > 25) { // This usually doesn't happen since the form element only
            // accepts 25 characters
            $errors[] = __('Username too long');
        } elseif (!strcasecmp($username, 'Guest') || !utf8_strcasecmp($username, __('Guest'))) {
            $errors[] = __('Username guest');
        } elseif (filter_var($username, FILTER_VALIDATE_IP)) {
            $errors[] = __('Username IP');
        } elseif ((strpos($username, '[') !== false || strpos($username, ']') !== false) &&
            strpos($username, '\'') !== false && strpos($username, '"') !== false) {
            $errors[] = __('Username reserved chars');
        } elseif (preg_match('%(?:\[/?(?:b|u|s|ins|del|em|i|h|colou?r|quote|code|img|url|email|list|\*|topic'.
            '|post|forum|user)\]|\[(?:img|url|quote|list)=)%i', $username)) {
            $errors[] = __('Username BBCode');
        }

        // Check username for any censored words
        if (ForumSettings::get('o_censoring') == '1' && Utils::censor($username) != $username) {
            $errors[] = __('Username censor');
        }

        // Check that the username (or a too similar username) is not already registered
        $query = (!is_null($exclude_id)) ? ' AND id!='.$exclude_id : '';

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'online')->raw_query('SELECT username FROM '.
            ForumSettings::get('db_prefix').'users 
            WHERE (UPPER(username)=UPPER(:username1) 
            OR UPPER(username)=UPPER(:username2)) 
            AND id>1'.$query, [
                ':username1' => $username,
                ':username2' => Utils::ucpPregReplace('%[^\p{L}\p{N}]%u', '', $username)
            ])->find_one();

        if ($result) {
            $busy = $result['username'];
            $errors[] = __('Username dupe 1').' '.Utils::escape($busy).'. '.__('Username dupe 2');
        }

        // Check username for any banned usernames
        foreach (Container::get('bans') as $cur_ban) {
            if ($cur_ban['username'] != '' && utf8_strtolower($username) == utf8_strtolower($cur_ban['username'])) {
                $errors[] = __('Banned username');
                break;
            }
        }

        return $errors;
    }

    public function getInfoMail($recipient_id)
    {
        $recipient_id = Container::get('hooks')->fire('model.profile.get_info_mail_start', $recipient_id);

        $mail['select'] = ['username', 'email', 'email_setting'];

        $mail = \ORM::for_table(ORM_TABLE_PREFIX.'users')
                ->select_many($mail['select'])
                ->where('id', $recipient_id);
        $mail = Container::get('hooks')->fireDB('model.profile.get_info_mail_query', $mail);
        $mail = $mail->find_one();

        if (!$mail) {
            throw new  RunBBException(__('Bad request'), 404);
        }

        $mail['recipient'] = $mail['username'];
        $mail['recipient_email'] = $mail['email'];

        $mail = Container::get('hooks')->fireDB('model.profile.get_info_mail', $mail);

        return $mail;
    }

    public function sendEmail($mail)
    {
        $mail = Container::get('hooks')->fire('model.profile.send_email_start', $mail);

        // Clean up message and subject from POST
        $subject = Utils::trim(Input::post('req_subject'));
        $message = Utils::trim(Input::post('req_message'));

        if ($subject == '') {
            throw new  RunBBException(__('No email subject'), 400);
        } elseif ($message == '') {
            throw new  RunBBException(__('No email message'), 400);
        } // Here we use strlen() not Utils::strlen() as we want to limit the post to
        // FEATHER_MAX_POSTSIZE bytes, not characters
        elseif (strlen($message) > ForumEnv::get('FEATHER_MAX_POSTSIZE')) {
            throw new  RunBBException(__('Too long email message'), 400);
        }

        if (User::get()->last_email_sent != '' && (time() -
                User::get()->last_email_sent) < User::get()->g_email_flood &&
            (time() - User::get()->last_email_sent) >= 0) {
            throw new  RunBBException(sprintf(
                __('Email flood'),
                User::get()->g_email_flood,
                User::get()->g_email_flood - (time() - User::get()->last_email_sent)
            ), 429);
        }

        // Load the "form email" template
//        $mail_tpl = trim(file_get_contents(ForumEnv::get('FORUM_ROOT').'lang/'.User::get()->language.
//            '/mail_templates/form_email.tpl'));
        $mail_tpl = Lang::getMailTemplate('form_email')->text;

        $mail_tpl = Container::get('hooks')->fire('model.profile.send_email_mail_tpl', $mail_tpl);

        // The first row contains the subject
        $first_crlf = strpos($mail_tpl, "\n");
        $mail_subject = Utils::trim(substr($mail_tpl, 8, $first_crlf-8));
        $mail_message = Utils::trim(substr($mail_tpl, $first_crlf));

        $mail_subject = str_replace('<mail_subject>', $subject, $mail_subject);
        $mail_message = str_replace('<sender>', User::get()->username, $mail_message);
        $mail_message = str_replace('<board_title>', ForumSettings::get('o_board_title'), $mail_message);
        $mail_message = str_replace('<mail_message>', $message, $mail_message);
        $mail_message = str_replace('<board_mailer>', ForumSettings::get('o_board_title'), $mail_message);

        $mail_message = Container::get('hooks')->fire('model.profile.send_email_mail_message', $mail_message);

        // TODO check result
        $result = Container::get('email')->dispatchMail(
            $mail['recipient_email'],
            $mail_subject,
            $mail_message,
            User::get()->email,
            User::get()->username
        );

        $update_last_mail_sent = \ORM::forTable(ORM_TABLE_PREFIX.'users')
            ->where('id', User::get()->id)
            ->findOne()
            ->set('last_email_sent', time());
        $update_last_mail_sent = Container::get('hooks')->fireDB(
            'model.profile.send_email_update_last_mail_sent',
            $update_last_mail_sent
        );
        $update_last_mail_sent = $update_last_mail_sent->save();

        // Try to determine if the data in redirect_url is valid (if not, we redirect to index.php
        // after the email is sent) TODO
        //$redirect_url = validate_redirect(Input::post('redirect_url'), 'index.php');

        Router::redirect(Router::pathFor('home'), __('Email sent redirect'));
    }

    public function displayIpInfo($ip)
    {
        $ip = Container::get('hooks')->fire('model.profile.display_ip_info', $ip);
        throw new  RunBBException(sprintf(__('Host info 1'), $ip).'<br />'.sprintf(
            __('Host info 2'),
            @gethostbyaddr($ip)
        ).'<br /><br /><a href="'. Router::pathFor('usersIpShow', ['ip' => $ip]).'">'. __('Show more users').'</a>');
    }

    public function changeStyle()
    {
        $user = \ORM::forTable(ORM_TABLE_PREFIX.'users')
            ->find_one(User::get()->id);
        $user->style = Input::post('styleToChange');
        $user->save();
    }

    public function changeLanguage()
    {
        $user = \ORM::forTable(ORM_TABLE_PREFIX.'users')
            ->find_one(User::get()->id);
        $user->language = Input::post('languageToChange');
        $user->save();
    }
}
