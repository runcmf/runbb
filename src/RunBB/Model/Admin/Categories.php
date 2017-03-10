<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher.
 */

namespace RunBB\Model\Admin;

class Categories
{
    public function addCategory($cat_name)
    {
        $cat_name = Container::get('hooks')->fire('model.admin.categories.add_category', $cat_name);

        $set_add_category = ['cat_name' => $cat_name];

        return DB::forTable('categories')
                ->create()
                ->set($set_add_category)
                ->save();
    }

    public function updateCategory(array $category)
    {
        $category = Container::get('hooks')->fire('model.admin.categories.update_category', $category);

        $set_update_category = ['cat_name' => $category['name'],
                                    'disp_position' => $category['order']];

        return DB::forTable('categories')
                ->find_one($category['id'])
                ->set($set_update_category)
                ->save();
    }

    public function deleteCategory($cat_to_delete)
    {
        $cat_to_delete = Container::get('hooks')
            ->fire('model.admin.categories.delete_category_start', $cat_to_delete);

        $forums_in_cat = DB::forTable('forums')
                            ->select('id')
                            ->where('cat_id', $cat_to_delete);
        $forums_in_cat = Container::get('hooks')
            ->fireDB('model.admin.categories.delete_forums_in_cat_query', $forums_in_cat);
        $forums_in_cat = $forums_in_cat->find_many();

        foreach ($forums_in_cat as $forum) {
            // Prune all posts and topics
            $this->maintenance = new \RunBB\Model\Admin\Maintenance();
            $this->maintenance->prune($forum->id, 1, -1);

            // Delete forum
            DB::forTable('forums')
                ->find_one($forum->id)
                ->delete();
        }

        // Delete orphan redirect forums
        $orphans = DB::forTable('topics')
                    ->table_alias('t1')
                    ->left_outer_join(DB::prefix().'topics', ['t1.moved_to', '=', 't2.id'], 't2')
                    ->where_null('t2.id')
                    ->where_not_null('t1.moved_to');
        $orphans = Container::get('hooks')->fireDB('model.admin.categories.delete_orphan_forums_query', $orphans);
        $orphans = $orphans->find_many();

        if (count($orphans) > 0) {
            $orphans->delete_many();
        }

        // Delete category
        $result = DB::forTable('categories');
        $result = Container::get('hooks')->fireDB('model.admin.categories.find_forums_in_cat', $result);
        $result = $result->find_one($cat_to_delete)->delete();

        return true;
    }

    public function getCatList()
    {
        $cat_list = [];
        $select_get_cat_list = ['id', 'cat_name', 'disp_position'];

        $cat_list = DB::forTable('categories')
            ->select($select_get_cat_list)
            ->order_by_asc('disp_position');
        $cat_list = Container::get('hooks')->fireDB('model.admin.categories.get_cat_list', $cat_list);
        $cat_list = $cat_list->find_array();

        return $cat_list;
    }
}
