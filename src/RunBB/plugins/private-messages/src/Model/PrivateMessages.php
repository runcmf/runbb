<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2012 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Plugins\Model;


use RunBB\Exception\RunBBException;

class PrivateMessages
{
    // Get all inboxes owned by a user
    public function getUserFolders($uid)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_folders')
            ->select('name')
            ->select('id')
            ->where_any_is([
                ['user_id' => $uid],
                ['user_id' => 1]
            ])
            ->find_array();

        $output = false;
        foreach ($result as $inbox) {
            $output[(int)$inbox['id']] = array('name' => $inbox['name']);
        }
        return $output;
    }

    public function getInboxes($uid)
    {
        if ($inboxes = $this->getUserFolders($uid)) {
            foreach ($inboxes as $iid => $data) {
                $inboxes[$iid]['nb_msg'] = $this->countMessages($iid, $uid);
            }
        } else {
            throw new  RunBBException('No inbox', 404);
        }
        return $inboxes;
    }

    // Check if current user owns the folder
    public function checkFolderOwner($fid, $uid)
    {
        return \ORM::for_table(ORM_TABLE_PREFIX . 'pms_folders')
            ->select('name')
            ->select('id')
            ->where_any_is([
                ['user_id' => $uid],
                ['user_id' => 1]
            ])
            ->where('id', $fid)
            ->find_one();
    }

    // Get messages count from context
    public function countMessages($fid, $uid)
    {
        $where[]['d.folder_id'] = $fid;
        if ($fid == 1)
            $where[]['d.viewed'] = '0';
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_conversations')
            ->select('id')
            ->table_alias('c')
            ->inner_join(ORM_TABLE_PREFIX . 'pms_data', array('c.id', '=', 'd.conversation_id'), 'd')
            ->where('d.user_id', $uid)
            ->where('d.deleted', 0)
            ->where_any_is($where);
        return $result->count();
    }

    // Get unread messages count for navbar
    public static function countUnread($uid)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
            ->select('id')
            ->where('user_id', $uid)
            ->where('viewed', 0);
        return $result->count();
    }

    public function getConversations($inboxes = null, $uid = null, $limit = 50, $start = 0)
    {
        $inboxes = (array)$inboxes;
        $where = array();
        foreach ($inboxes as $id => $inbox_id) {
            $where[]['d.folder_id'] = (int)$inbox_id;
            if ($inbox_id == 1) $where[]['d.viewed'] = '0';
        }

        $select = array(
            'c.id',
            'c.subject',
            'c.poster',
            'c.poster_id',
            'poster_gid' => 'u.group_id', 'u.email',
            'c.num_replies',
            'd.viewed',
            'c.last_post',
            'c.last_poster',
            'last_poster_id' => 'u2.id',
            'last_poster_gid' => 'u2.group_id',
            'c.last_post_id',
        );
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_conversations')
            ->select_many($select)
            ->table_alias('c')
            ->inner_join(ORM_TABLE_PREFIX . 'pms_data', array('c.id', '=', 'd.conversation_id'), 'd')
            ->left_outer_join(ORM_TABLE_PREFIX . 'users', array('u.id', '=', 'c.poster_id'), 'u')
            ->left_outer_join(ORM_TABLE_PREFIX . 'users', array('u2.username', '=', 'c.last_poster'), 'u2')
//            ->raw_join('LEFT JOIN '.ORM_TABLE_PREFIX.'users', 'u2.username=c.last_poster', 'u2')
            ->where('d.user_id', $uid)
            ->where('d.deleted', 0)
            ->where_any_is($where)
            ->order_by_desc('c.last_post')
            ->limit($limit)
            ->offset($start)
            ->find_array();

        foreach ($result as $key => $conversation) {
            $receivers = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
                ->table_alias('d')
                ->select(array('d.user_id', 'u.username'))
                ->left_outer_join(ORM_TABLE_PREFIX . 'users', array('u.id', '=', 'd.user_id'), 'u')
                ->where('d.conversation_id', $conversation['id'])
                ->find_array();
            if (is_array($receivers)) {
                foreach ($receivers as $receiver) {
                    $result[$key]['receivers'][$receiver['user_id']] = $receiver['username'];
                }
            }
        }
        return $result;
    }

    // Delete one or more messages
    public function delete($convers, $uid)
    {
        // Get the number of conversation messages and the number of replies from all conversations
        $numConvers = count($convers);
        $numReplies = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_conversations')
            ->table_alias('c')
            ->select('c.num_replies')
            ->inner_join(ORM_TABLE_PREFIX . 'pms_data', array('c.id', '=', 'cd.conversation_id'), 'cd')
            // ->inner_join('pms_data', array('cd.user_id', '=', $uid), null, true)
            ->where('cd.user_id', $uid)
            ->where_in('c.id', $convers)
            ->sum('c.num_replies');
        $numPms = ($numReplies + $numConvers);

        // Soft delete messages
        \ORM::configure('id_column', array('conversation_id', 'user_id'));
        \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
            ->where('user_id', $uid)
            ->where_in('conversation_id', $convers)
            ->find_result_set()
            ->set('deleted', 1)
            ->save();

        // Now check if anyone left in the conversation has any of these topics undeleted. If so, then we leave them. Otherwise, actually delete them.
        foreach ($convers as $cid) {
            $left = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
                ->where('conversation_id', $cid)
                ->where('deleted', 0);

            if ($left->count()) { // People are still left
                continue;
            }

            \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')->where('conversation_id', $cid)->delete_many();
            \ORM::for_table(ORM_TABLE_PREFIX . 'pms_messages')->where('conversation_id', $cid)->delete_many();
            \ORM::for_table(ORM_TABLE_PREFIX . 'pms_conversations')->where('id', $cid)->delete_many();

        }
    }

    public function move($convers, $move_to, $uid)
    {
        if (!$this->checkFolderOwner($move_to, $uid)) {
            throw new  RunBBException(__('Wrong folder owner', 'private_messages'), 403);
        }

        \ORM::configure('id_column', array('conversation_id', 'user_id'));

        return \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
            ->where('user_id', $uid)
            ->where_in('conversation_id', $convers)
            ->find_result_set()
            ->set('folder_id', $move_to)
            ->save();
    }

    // Mark a conversation as (un)read (default to true)
    public function setViewed($conv_id, $uid, $viewed = 1)
    {
        \ORM::configure('id_column', array('conversation_id', 'user_id'));

        return \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
            ->where('conversation_id', $conv_id)
            ->where('user_id', $uid)
            ->find_one()
            ->set('viewed', $viewed)
            ->save();
    }

    public function updateConversation($conv_ids, $uid, array $data)
    {
        \ORM::configure('id_column', array('conversation_id', 'user_id'));

        $conv_ids = (array)$conv_ids;
        return \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
            ->where('user_id', $uid)
            ->where_in('conversation_id', $conv_ids)
            ->find_result_set()
            ->set($data)
            ->save();
    }

    public function addConversation(array $data = array())
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_conversations')
            ->create()
            ->set($data);
        $result->save();
        return $result->id();
    }


    // Return false if the conv doesn't exist or if the user has no rights to access it
    public function getConversation($conv_id = null, $uid = null)
    {
        $select = array(
            'c.id',
            'c.subject',
            'c.poster',
            'c.poster_id',
            'poster_gid' => 'u.group_id', 'u.email',
            'c.num_replies',
            'd.viewed',
            'c.last_post',
            'c.last_poster',
            'last_poster_id' => 'u2.id',
            'last_poster_gid' => 'u2.group_id',
            'c.last_post_id',
            'c.first_post_id',
            'd.folder_id'
        );

        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_conversations')
            ->select_many($select)
            ->table_alias('c')
            ->inner_join(ORM_TABLE_PREFIX . 'pms_data', array('c.id', '=', 'd.conversation_id'), 'd')
            ->left_outer_join(ORM_TABLE_PREFIX . 'users', array('u.id', '=', 'c.poster_id'), 'u')
            ->left_outer_join(ORM_TABLE_PREFIX . 'users', array('u2.username', '=', 'c.last_poster'), 'u2')
//            ->raw_join('LEFT JOIN '.ORM_TABLE_PREFIX.'users', 'u2.username=c.last_poster', 'u2')
            ->where_any_is(array(array('c.poster_id' => $uid),
                array('d.user_id' => $uid)))
            ->where('c.id', $conv_id)
            ->find_one();

        return $result;
    }

    public function addMessage(array $data = array(), $conv_id = null, array $uid = array())
    {
        $add = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_messages')
            ->create()
            ->set($data)
            ->set('conversation_id', $conv_id);
        $add->save();

        $update_data = ['last_post_id' => $add->id()];
        // If it is a new conversation:
        if (!empty($uid)) $update_data['first_post_id'] = $add->id();
        $update = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_conversations')
            ->find_one($conv_id)
            ->set($update_data);
        // Increment replies count
        if (empty($uid)) $update->set_expr('num_replies', 'num_replies+1');
        $update = $update->save();

        \ORM::configure('id_column', array('conversation_id', 'user_id'));

        if (!empty($uid)) {
            // New conversation
            foreach ($uid as $user) {
                $notifs = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
                    ->create()
                    ->set(array(
                        'conversation_id' => $conv_id,
                        'user_id' => $user,
                        'viewed' => (($user == User::get()->id) ? '1' : '0')))
                    ->save();
            }
        } else {
            // Reply
            $notifs = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
                ->where('conversation_id', $conv_id)
                ->where_not_equal('user_id', User::get()->id)
                ->find_result_set();
            $notifs->set('viewed', 0)
                ->save();
        }

        return ($add && $update && $notifs) ? $add->id() : false;
    }

    public function getMessages($conv_id = null, $limit = 50, $start = 0)
    {
        $select = array('m.id', 'username' => 'm.poster', 'm.poster_id', 'poster_gid' => 'u.group_id', 'u.title', 'm.message', 'm.hide_smilies', 'm.sent', 'm.conversation_id', 'g.g_id', 'g.g_user_title', 'is_online' => 'o.user_id');
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_messages')
            ->table_alias('m')
            ->select_many($select)
            ->left_outer_join(ORM_TABLE_PREFIX . 'users', array('u.id', '=', 'm.poster_id'), 'u')
            ->inner_join(ORM_TABLE_PREFIX . 'groups', array('g.g_id', '=', 'u.group_id'), 'g')
            ->raw_join('LEFT OUTER JOIN ' . ForumSettings::get('db_prefix') . 'online', "o.user_id!=1 AND o.idle=0 AND o.user_id=u.id", 'o')
            ->where('m.conversation_id', $conv_id)
            ->order_by_asc('m.sent')
            ->find_array();
        return $result;
    }

    public function isAllowed($username = null)
    {
        if (!$username) {
            return false;
        }

        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'users')
            ->where('username', $username)
            ->where_gt('id', 1)
            ->find_one();
        return $result;
    }

    public function isDeleted($conv_id = null, $uid = null)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_data')
            ->where('conversation_id', $conv_id)
            ->where('user_id', $uid)
            ->where('deleted', 1)
            ->find_one();
        return (bool)$result;
    }

    public function getUserByID($id = null)
    {
        if (!$id) {
            return false;
        }
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'users')
            ->where('id', $id)
            ->find_one();
        return $result;
    }

    public function getUserByName($username)
    {
        $user = \ORM::for_table(ORM_TABLE_PREFIX . 'users')
            ->select_many(['group_id', 'id'])
            ->where('username', $username)
            ->find_one();
        return $user;
    }

    public function getMessagesFromConversation($conv_id = null, $uid = null, $limit = 50, $start = 0)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_messages')
            ->table_alias('m')
            ->where('m.conversation_id', $conv_id)
            ->order_by_desc('sent')
            ->limit($limit)
            ->find_many();
        return $result;
    }

    /**
     * Get blocked users for current user
     * @param  (int) $user_id Current user id
     * @return (object)       The database results
     */
    public function getBlocked($user_id)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_blocks')
            ->table_alias('b')
            ->select_many(['b.id', 'b.block_id', 'u.username', 'u.group_id'])
            ->inner_join(ORM_TABLE_PREFIX . 'users', array('b.block_id', '=', 'u.id'), 'u')
            ->where('b.user_id', $user_id)
            ->find_many();
        return $result;
    }

    public function checkBlock($user_id, $block_id)
    {
        // var_dump($user_id, $block_id);
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_blocks')
            // ->select('id')
            ->where('user_id', $user_id)
            ->where('block_id', $block_id)
            ->count();
        return $result;
    }

    public function addBlock(array $data = array())
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_blocks')
            ->create()
            ->set($data);
        $result->save();
        return $result->id();
    }

    public function removeBlock($user_id, $block_id)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_blocks')
            ->where('user_id', $user_id)
            ->where('block_id', $block_id)
            ->find_one();
        return $result->delete();
    }

    /**
     * Add a custom folder
     * @param  (array) $data  New folder name and owner ID
     * @return (bool)         Creation success state
     */
    public function addFolder(array $data)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_folders')
            ->create()
            ->set($data);
        $result->save();
        return $result->id();
    }

    public function updateFolder($user_id, $block_id, array $data)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_folders')
            ->find_one($block_id)
            ->where('user_id', $user_id)
            ->set($data);
        return $result->save();
    }

    public function removeFolder($user_id, $block_id)
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'pms_folders')
            ->where('id', $block_id)
            ->where('user_id', $user_id)
            ->find_one();
        return $result->delete();
    }


    public function fetch_groups()
    {
        $result = \ORM::for_table(ORM_TABLE_PREFIX . 'groups')->order_by_asc('g_id')->find_many();
        $groups = [];
        foreach ($result as $cur_group) {
            $groups[$cur_group['g_id']] = $cur_group;
        }
        return $groups;
    }

    public function update_permissions()
    {
        $form = array_map('intval', Request::getParsedBody());
        $form = Container::get('hooks')->fire('model.admin.permissions.plugins.private-messages.form', $form);
        $update = [];
        foreach ($form as $key => $input) {
            // Make sure the input is never a negative value
            if ($input < 0) {
                $input = 0;
            }
            // Get the group ID from key_gX
            $group_id = filter_var($key, FILTER_SANITIZE_NUMBER_INT);
            if ($group_id != '') {
                // Clean key without the group ID
                if ($group_id < 10) {
                    $key_cleaned = substr($key, 0, -3);
                } elseif ($group_id < 100) {
                    $key_cleaned = substr($key, 0, -4);
                } else {
                    $key_cleaned = substr($key, 0, -5);
                }
                // Build the array for the query
                $update[$group_id]['g_' . $key_cleaned] = $input;
            }
        }
        foreach ($update as $group_id => $values) {
            \ORM::for_table(ORM_TABLE_PREFIX . 'groups')
                ->find_one($group_id)
                ->set($values)
                ->save();
        }
        return Router::redirect(Router::pathFor('infoPlugin', ['name' => 'private-messages']), __('Perms updated redirect'));
    }
}
