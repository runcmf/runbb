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

class MenuManager
{
    protected $menuCollection;
    private $sc; // Slim Container

    public function __construct($c)
    {
        $this->sc = $c;
        $this->menuCollection = new Collection;
    }

    public function create($name)
    {
        $this->menuCollection[$name] = new MenuCollection;
        $this->menuCollection[$name]->setName($name);

        return $this->menuCollection[$name];
    }

    public function get($name)
    {
        return $this->menuCollection[$name];
    }

    public function render($menu, $tag = 'ul', $options)
    {
        return $this->renderMenu(
            $this->menuCollection[$menu],
            $tag,
            $options,
            $this->menuCollection[$menu]->getActiveMenu(),
            0
        );
    }

    protected function renderMenu(MenuCollection $menu, $tag = 'ul', $options, $active = '', $level = 0)
    {
        switch ($tag) {
            case 'ul':
                $childTag = 'li';
                break;
            case 'div':
                $childTag = 'div';
                break;
            default:
                $childTag = 'div';
                break;
        }

        /** convert array attribute to "attr"="value" "attr2"="value2" format */
        $attribute = '';
        if (isset($options['attributes'])) {
            foreach ($options['attributes'] as $key => $value) {
                $attribute .= "$key=\"$value\" ";
            }
        }
        $parentTagFormat = "<$tag $attribute>%s</$tag>\n";
        $childTagFormat = "<$childTag %s>%s <a href=\"%s\" %s>%s %s</a>%s %s</$childTag>\n\t\t";
        $childTag = '';
        $activeOption = isset($options['active']) ? $options['active'] : array();

        foreach ($menu as $menuItem) {
            /** append active class when node is active */
            if ($active == $menuItem->getName() || $menuItem->isActive()) {
                $class = $menuItem->getAttribute('class');
                $menuItem->setAttribute('class', $class . ' active');
            }

            if ($active == $menuItem->getName()) {
                $class = $menuItem->getAttribute('class');

                $menuItem->setAttribute('class', isset($activeOption['class']) ? $class . ' ' . $activeOption['class'] : '');
                $menuItem->prependString(isset($activeOption['prepend']) ? $activeOption['prepend'] : '');
                $menuItem->appendString(isset($activeOption['append']) ? $activeOption['append'] : '');
            }

            if ($menuItem->hasChildren()) {
                $submenu = $this->renderMenu($menuItem->getChildren(), $tag,
                    [
                        'attributes' => $menuItem->getAttribute(),
                        'active' => $activeOption
                    ], $active, ++$level);
            } else {
                $submenu = '';
            }

            $childTag .= sprintf(
                $childTagFormat,
                $menuItem->getStringAttribute(),
                $menuItem->getPrependedString(),
                $this->generateUrl($menuItem->getUrl()),
                $menuItem->getLinkStringAttribute(),
                $menuItem->getIcon() ? '<i class="fa fa-fw fa-' . $menuItem->getIcon() . '"></i>' : '',
                $menuItem->getLabel(),
                $menuItem->getAppendedString(),
                $submenu
            );
        }
        return sprintf($parentTagFormat, $childTag);
    }

    /**
     * generate base URL
     */
    protected function generateUrl($urlpath)
    {
        $baseUrl = $this->sc->get('request')->getUri()->getBasePath();
//        $baseUrl = trim($baseUrl, '/');
//        $urlpath = trim($urlpath, '/');
        return $baseUrl . $urlpath;
    }
}