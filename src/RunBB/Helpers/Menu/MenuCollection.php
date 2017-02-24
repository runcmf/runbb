<?php
/**
 * Copyright 2016 1f7.wizard@gmail.com
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace RunBB\Helpers\Menu;

use Slim\Collection;

class MenuCollection extends Collection
{
    protected $active;
    protected $name;

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setActiveMenu($menu)
    {
        $this->active = $menu;

        foreach ($this->data as $item) {
            $this->seekAndActivate($item, $menu);
        }
    }

    protected function seekAndActivate(MenuItem $item, $menu)
    {
        if ($item->getName() === $menu) {
            $item->setActive(true);
        } else if ($item->hasChildren()) {
            foreach ($item->getChildren() as $child) {
                $this->seekAndActivate($child, $menu);
            }
        } else {
            $item->setActive(false);
        }
    }

    public function getActiveMenu()
    {
        return $this->active;
    }

    /**
     * Add new item to menuCollection
     *
     * @param String $name
     * @param MenuItem $item
     */
    public function addItem($name, MenuItem $item)
    {
        $this->data[$name] = $item;
    }

    public function getItem($name)
    {
        return isset($this->data[$name]) ? $this->data[$name] : null;
    }

    /**
     * Create Menu Item
     *
     * @param  String $name
     * @param  String $option
     * @return MenuItem
     */
    public function createItem($name, $option)
    {
        return new MenuItem($name, $option);
    }
}