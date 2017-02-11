<?php

/**
* Copyright (C) 2015-2016 FeatherBB
* based on code by (C) 2008-2015 FluxBB
* and Rickard Andersson (C) 2002-2008 PunBB
* License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
*/

namespace RunBB\Core;

use RunBB\Exception\RunBBException;

class Preferences
{
    protected $preferences = [];

    public function __construct()
    {
    }

    // Add / Update

    public function setUser($user = null, array $prefs = [])
    {
        list($uid, $gid) = $this->getInfosFromUser($user);

        foreach ($prefs as $pref_name => $pref_value) {
            $pref_name = (string) $pref_name;
            $pref_value = (string) $pref_value;

            if ((int) $pref_name > 0) {
                throw new RunBBException('Internal error : preference name cannot be an integer', 500);
            }
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                        ->where('preference_name', $pref_name)
                        ->where('user', $uid)
                        ->find_one();
            if ($result) {
                \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                    ->find_one($result->id())
                    ->set(['preference_value' => $pref_value])
                    ->save();
            } else {
                \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                    ->create()
                    ->set([
                        'preference_name' => $pref_name,
                        'preference_value' => $pref_value,
                        'user' => $uid
                    ])
                    ->save();
            }
            $this->preferences[$gid][$uid][$pref_name] = $pref_value;
        }
        return $this;
    }

    public function setGroup($gid = null, array $prefs = [])
    {
        $gid = (int) $gid;
        if ($gid < 1) {
            throw new RunBBException('Internal error : Unknown gid', 500);
        }
        foreach ($prefs as $pref_name => $pref_value) {
            if ((int) $pref_name > 0) {
                throw new RunBBException('Internal error : preference name cannot be an integer', 500);
            }
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                        ->where('preference_name', (string) $pref_name)
                        ->where('group', $gid)
                        ->find_one();
            if ($result) {
                \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                    ->find_one($result->id())
                    ->set(['preference_value' => (string) $pref_value])
                    ->save();
            } else {
                \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                    ->create()
                    ->set([
                        'preference_name' => (string) $pref_name,
                        'preference_value' => (string) $pref_value,
                        'group' => $gid
                    ])
                    ->save();
            }
            unset($this->preferences[$gid]);
        }
        return $this;
    }

    public function set(array $prefs) // Default
    {
        foreach ($prefs as $pref_name => $pref_value) {
            if ((int) $pref_name > 0) {
                throw new RunBBException('Internal error : preference name cannot be an integer', 500);
            }
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                        ->where('preference_name', (string) $pref_name)
                        ->where('default', 1)
                        ->find_one();
            if ($result) {
                \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                    ->find_one($result->id())
                    ->set(['preference_value' => (string) $pref_value])
                    ->save();
            } else {
                \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                    ->create()
                    ->set([
                        'preference_name' => (string) $pref_name,
                        'preference_value' => (string) $pref_value,
                        'default' => 1
                    ])
                    ->save();
            }
            unset($this->preferences);
        }
        return $this;
    }

    // Delete

    public function delUser($user = null, $prefs = null)
    {
        list($uid, $gid) = $this->getInfosFromUser($user);
        $prefs = (array) $prefs;
        foreach ($prefs as $pref_id => $pref_name) {
            $pref_name = (string) $pref_name;

            if ((int) $pref_name > 0) {
                throw new RunBBException('Internal error : preference name cannot be an integer', 500);
            }
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                        ->where('preference_name', $pref_name)
                        ->where('user', $uid)
                        ->find_one();
            if ($result) {
                $result->delete();
                unset($this->preferences[$gid][$uid][$pref_name]);
            } else {
                throw new RunBBException('Internal error : Unknown preference name', 500);
            }
        }
        return $this;
    }

    public function delGroup($gid = null, $prefs = null)
    {
        $gid = (int) $gid;
        if ($gid < 1) {
            throw new RunBBException('Internal error : Unknown gid', 500);
        }
        $prefs = (array) $prefs;

        foreach ($prefs as $pref_id => $pref_name) {
            $pref_name = (string) $pref_name;

            if ((int) $pref_name > 0) {
                throw new RunBBException('Internal error : preference name cannot be an integer', 500);
            }
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                        ->where('preference_name', $pref_name)
                        ->where('group', $gid)
                        ->find_one();
            if ($result) {
                $result->delete();
            } else {
                throw new RunBBException('Internal error : Unknown preference name', 500);
            }
        }
        unset($this->preferences[$gid]);
        return $this;
    }

    public function del($prefs = null) // Default
    {
        $prefs = (array) $prefs;
        foreach ($prefs as $pref_id => $pref_name) {
            if ((int) $pref_name > 0) {
                throw new RunBBException('Internal error : preference name cannot be an integer', 500);
            }
            $result = \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                        ->where('preference_name', (string) $pref_name)
                        ->where('default', 1)
                        ->find_one();
            if ($result) {
                $result->delete();
            } else {
                throw new RunBBException('Internal error : Unknown preference name', 500);
            }
        }
        unset($this->preferences);
        return $this;
    }

    // Getters

    public function get($user = null, $pref = null)
    {
        list($uid, $gid) = $this->getInfosFromUser($user);

        if (!isset($this->preferences[$gid][$uid])) {
            $this->loadPrefs($user);
        }
        if (empty($pref)) {
            return $this->preferences[$gid][$uid];
        }
        return (isset($this->preferences[$gid][$uid][(string) $pref])) ?
            $this->preferences[$gid][$uid][(string) $pref] : null;
    }

    // Utils

    protected function loadPrefs($user = null)
    {
        list($uid, $gid) = $this->getInfosFromUser($user);

        $result = \ORM::for_table(ORM_TABLE_PREFIX.'preferences')
                    ->table_alias('p')
                    ->where_any_is([
                        ['p.user' => $uid],
                        ['p.group' => $gid],
                        ['p.default' => 1],
                    ])
                    ->order_by_desc('p.default')
                    ->order_by_asc('p.user')
                    ->find_array();

        $this->preferences[$gid][$uid] = [];
        foreach ($result as $pref) {
            $this->preferences[$gid][$uid][(string) $pref['preference_name']] = $pref['preference_value'];
        }
        return $this->preferences[$gid][$uid];
    }

    protected function getInfosFromUser($user = null)
    {
        if (is_object($user)) {
            $uid = $user->id;
            $gid = $user->group_id;
        } elseif ((int) $user > 0) {
            $data = \ORM::for_table(ORM_TABLE_PREFIX.'users')->find_one($user);
            if (!$data) {
                throw new RunBBException('Internal error : Unknown user ID', 500);
            }
            $uid = $data['id'];
            $gid = $data['group_id'];
        } else {
            throw new RunBBException('Internal error : wrong user object type', 500);
        }
        return [(int) $uid, (int) $gid];
    }
}
