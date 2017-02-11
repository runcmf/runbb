<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Controller\Admin;

use RunBB\Core\AdminUtils;
use RunBB\Exception\RunBBException;
use RunBB\Core\Utils;

class Groups
{
    public function __construct()
    {
        $this->model = new \RunBB\Model\Admin\Groups();
        Lang::load('admin-common');
        Lang::load('admin-groups');
    }

    public function display($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.groups.display');

        $groups = $this->model->fetchGroups();

        // Set default group
        if (Request::isPost()) {
            return $this->model->setDefaultGroup($groups);
        }

        AdminUtils::generateAdminMenu('groups');

        View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('User groups')],
                'active_page' => 'admin',
                'admin_console' => true,
                'groups' => $groups,
                'cur_index' => 5,
            ])->addTemplate('admin/groups/admin_groups.php')->display();
    }

    public function delete($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.groups.delete');

        if ($args['id'] < 5) {
            throw new  RunBBException(__('Bad request'), 403);
        }

        // Make sure we don't remove the default group
        if ($args['id'] == ForumSettings::get('o_default_user_group')) {
            throw new  RunBBException(__('Cannot remove default message'), 403);
        }

        // Check if this group has any members
        $is_member = $this->model->checkMembers($args['id']);

        // If the group doesn't have any members or if we've already selected a group to move the members to
        if (!$is_member || Input::post('del_group')) {
            if (Input::post('del_group_comply') || Input::post('del_group')) {
                return $this->model->deleteGroup($args['id']);
            } else {
                AdminUtils::generateAdminMenu('groups');

                return View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('User groups')],
                    'active_page' => 'admin',
                    'admin_console' => true,
                    'group_title'      =>  $this->model->getGroupTitle($args['id']),
                    'id'    => $args['id'],
                    ])->addTemplate('admin/groups/confirm_delete.php')->display();
            }
        }

        AdminUtils::generateAdminMenu('groups');

        return View::setPageInfo([
                'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('User groups')],
                'active_page' => 'admin',
                'admin_console' => true,
                'id'    => $args['id'],
                'group_info'      =>  $this->model->getTitleMembers($args['id']),
                'group_list_delete'      =>  $this->model->getGroupListDelete($args['id']),
            ])->addTemplate('admin/groups/delete_group.php')->display();
    }

    public function addedit($req, $res, $args)
    {
        Container::get('hooks')->fire('controller.admin.groups.addedit');

        $groups = $this->model->fetchGroups();

        // Add/edit a group (stage 2)
        if (Input::post('add_edit_group')) {
            return $this->model->addEditGroup($groups);
        } // Add/edit a group (stage 1)
        elseif (Input::post('add_group') || isset($args['id'])) {
            AdminUtils::generateAdminMenu('groups');

            $id = isset($args['id']) ? (int)$args['id'] : (int)Input::post('base_group');
            $group = $this->model->infoAddGroup($groups, $id);

            View::setPageInfo([
                    'title' => [Utils::escape(ForumSettings::get('o_board_title')), __('Admin'), __('User groups')],
                    'active_page' => 'admin',
                    'admin_console' => true,
                    'focus_element' => ['groups2', 'req_title'],
                    'required_fields' => ['req_title' => __('Group title label')],
                    'group'    =>    $group,
                    'groups'    =>    $groups,
                    'id'    => $id,//$args['id'],
                    'group_list'    => $this->model->getGroupList($groups, $group),
                ])->addTemplate('admin/groups/add_edit_group.php')->display();
        }
    }
}
