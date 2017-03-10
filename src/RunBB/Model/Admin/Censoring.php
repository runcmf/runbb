<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Model\Admin;

use RunBB\Exception\RunBBException;
use RunBB\Core\Url;
use RunBB\Core\Utils;
use RunBB\Model\Cache;

class Censoring
{
    public function addWord()
    {
        $search_for = Utils::trim(Input::post('new_search_for'));
        $replace_with = Utils::trim(Input::post('new_replace_with'));

        if ($search_for == '') {
            throw new  RunBBException(__('Must enter word message'), 400);
        }

        $set_search_word = ['search_for' => $search_for,
                                'replace_with' => $replace_with];

        $set_search_word = Container::get('hooks')
            ->fire('model.admin.censoring.add_censoring_word_data', $set_search_word);

        $result = DB::forTable('censoring')
            ->create()
            ->set($set_search_word)
            ->save();

        // Regenerate the censoring cache
        Container::get('cache')->store('search_for', Cache::getCensoring('search_for'));
        Container::get('cache')->store('replace_with', Cache::getCensoring('replace_with'));

        return Router::redirect(Router::pathFor('adminCensoring'), __('Word added redirect'));
    }

    public function updateWord()
    {
        $id = intval(key(Input::post('update')));

        $search_for = Utils::trim(Input::post('search_for')[$id]);
        $replace_with = Utils::trim(Input::post('replace_with')[$id]);

        if ($search_for == '') {
            throw new  RunBBException(__('Must enter word message'), 400);
        }

        $set_search_word = ['search_for' => $search_for,
                                'replace_with' => $replace_with];

        $set_search_word = Container::get('hooks')
            ->fire('model.admin.censoring.update_censoring_word_start', $set_search_word);

        $result = DB::forTable('censoring')
            ->find_one($id)
            ->set($set_search_word)
            ->save();

        // Regenerate the censoring cache
        Container::get('cache')->store('search_for', Cache::getCensoring('search_for'));
        Container::get('cache')->store('replace_with', Cache::getCensoring('replace_with'));

        return Router::redirect(Router::pathFor('adminCensoring'), __('Word updated redirect'));
    }

    public function removeWord()
    {
        $id = intval(key(Input::post('remove')));
        $id = Container::get('hooks')->fire('model.admin.censoring.remove_censoring_word_start', $id);

        $result = DB::forTable('censoring')->find_one($id);
        $result = Container::get('hooks')->fireDB('model.admin.censoring.remove_censoring_word', $result);
        $result = $result->delete();

        // Regenerate the censoring cache
        Container::get('cache')->store('search_for', Cache::getCensoring('search_for'));
        Container::get('cache')->store('replace_with', Cache::getCensoring('replace_with'));

        return Router::redirect(Router::pathFor('adminCensoring'), __('Word removed redirect'));
    }

    public function getWords()
    {
        $word_data = DB::forTable('censoring')
                        ->order_by_asc('id');
        $word_data = Container::get('hooks')
            ->fireDB('model.admin.censoring.update_censoring_word_query', $word_data);
        $word_data = $word_data->find_array();

        return $word_data;
    }
}
