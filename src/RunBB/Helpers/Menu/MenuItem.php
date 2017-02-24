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

class MenuItem
{
    protected $hasChildren;
    protected $children;
    protected $hasParent;
    protected $parent;
    protected $options;
    protected $attributes;
    protected $linkAttributes;
    protected $prependedString;
    protected $appendedString;
    protected $name;
    protected $active;

    public function __construct($name, $options)
    {
        $this->options = [
            'label' => isset($options['label']) ? $options['label'] : '',
            'url' => isset($options['url']) ? $options['url'] : '',
            'icon' => isset($options['icon']) ? $options['icon'] : '',
        ];

        $this->hasChildren = false;
        $this->hasParent = false;
        $this->active = false;
        $this->children = new MenuCollection();
        $this->attributes = [];
        $this->linkAttributes = [];
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setLinkAttribute($key, $value)
    {
        $this->linkAttributes[$key] = $value;
    }

    public function getLinkAttribute($key = null)
    {
        if (is_null($key)) {
            return $this->linkAttributes;
        }

        return isset($this->linkAttributes[$key]) ? $this->linkAttributes[$key] : null;
    }

    public function getLinkStringAttribute()
    {
        $string = '';
        foreach ($this->linkAttributes as $key => $value) {
            $string .= $key.'="'.$value.'" ';
        }

        return $string;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function getAttribute($key = null)
    {
        if (is_null($key)) {
            return $this->attributes;
        }

        return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
    }

    public function getStringAttribute()
    {
        $string = '';
        foreach ($this->attributes as $key => $value) {
            $string .= $key.'="'.$value.'" ';
        }

        return $string;
    }

    public function getLabel()
    {
        return isset($this->options['label']) ? $this->options['label'] : null;
    }

    public function getUrl()
    {
        return isset($this->options['url']) ? $this->options['url'] : null;
    }

    public function getIcon()
    {
        return isset($this->options['icon']) ? $this->options['icon'] : null;
    }

    public function hasChildren()
    {
        return $this->hasChildren;
    }

    public function addChildren($name, MenuItem $menu)
    {
        $this->hasChildren = true;
        $this->children->set($name, $menu);
        $menu->setParent($this);
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function appendString($string)
    {
        $this->appendedString = $string;
    }

    public function getAppendedString()
    {
        return $this->appendedString;
    }

    public function prependString($string)
    {
        $this->prependedString = $string;
    }

    public function getPrependedString()
    {
        return $this->prependedString;
    }

    public function setParent(MenuItem $parent)
    {
        $this->parent = $parent;
        $this->hasParent = true;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function hasParent()
    {
        return $this->hasParent;
    }

    public function setActive($status = true)
    {
//        echo 'Set '.$this->name.' as '.($status ? 'active' : 'passive').'<br>';

        if (false == $status) {
            foreach ($this->children as $child) {
                $status = $status || $child->isActive();
            }
        }

        $this->active = $status;

        if ($this->hasParent) {
            //echo 'Set '.$this->name.'\'s parent as '.($status ? 'active' : 'passive').'<br>';
            $this->parent->setActive($status);
        }

    }

    public function isActive()
    {
        return $this->active;
    }
}