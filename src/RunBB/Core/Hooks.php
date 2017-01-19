<?php

/**
 * Copyright (C) 2015-2016 FeatherBB
 * based on code by (C) 2008-2015 FluxBB
 * and Rickard Andersson (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

namespace RunBB\Core;

class Hooks
{
    /**
     * @var array
     */
    protected $hooks = [
        // 'slim.before' => array(array()),
        // 'slim.before.router' => array(array()),
        // 'slim.before.dispatch' => array(array()),
        // 'slim.after.dispatch' => array(array()),
        // 'slim.after.router' => array(array()),
        // 'slim.after' => array(array())
    ];
    /**
     * Assign hook
     * @param  string   $name       The hook name
     * @param  mixed    $callable   A callable object
     * @param  int      $priority   The hook priority; 0 = high, 10 = low
     */
    public function bind($name, $callable, $priority = 10)
    {
//        if (!isset($this->hooks[$name])) {
//            $this->hooks[$name] = array(array());// why empty array?
//        }
        if (is_callable($callable)) {
            $this->hooks[$name][(int) $priority][] = $callable;
        }
    }

    /**
     * Invoke hook
     * @param  string $name The hook name
     * @param  mixed  ...   (Optional) Argument(s) for hooked functions, can specify multiple arguments
     * @return mixed
     */
    public function fire($name)
    {
        $args = func_get_args();
        array_shift($args);

        if (!isset($this->hooks[$name])) {
            //$this->hooks[$name] = array(array());
            if (isset($args[0])) {
                return $args[0];
            } else {
                return;
            }
        }
        if (!empty($this->hooks[$name])) {
            // Sort by priority, low to high, if there's more than one priority
            if (count($this->hooks[$name]) > 1) {
                ksort($this->hooks[$name]);
            }

            $output = [];
            $count = 0;

            foreach ($this->hooks[$name] as $priority) {
                if (!empty($priority)) {
                    foreach ($priority as $callable) {
                        $output[] = call_user_func_array($callable, $args);
                        ++$count;
                    }
                }
            }

            // If we only have one hook binded or the argument is not an array,
            // let's return the first output
            if ($count == 1 || !is_array($args[0])) {
                return $output[0];
            } else {
                $data = [];
                // Move all the keys to the same level
                foreach ($output as $k => $vars) {
                    if ($k === 0) {
                        $data = $vars;
                        continue;
                    }
                    // TODO нарисовать тесты на нескольких плугах с одинаковыми хуками:
                    // проверить если в массиве есть callable
//                    $data = array_merge($data, $vars);
                    $data = $data + $vars;
                }
//                array_walk_recursive($output, function ($v, $k) use (&$data) {
//                    $data[] = $v;
//                });
                return $data;
                // Remove any duplicate key
//                return array_unique($data, SORT_REGULAR);
            }
        }
    }

    /**
     * Invoke hook for DB
     * @param  string $name The hook name
     * @param  mixed  ...   Argument(s) for hooked functions, can specify multiple arguments
     * @return mixed
     */
    public function fireDB($name)
    {
        $args = func_get_args();
        array_shift($args);

        if (!isset($this->hooks[$name])) {
            //$this->hooks[$name] = array(array());
            return $args[0];
        }
        if (!empty($this->hooks[$name])) {
            // Sort by priority, low to high, if there's more than one priority
            if (count($this->hooks[$name]) > 1) {
                ksort($this->hooks[$name]);
            }

            $output = [];

            foreach ($this->hooks[$name] as $priority) {
                if (!empty($priority)) {
                    foreach ($priority as $callable) {
                        $output[] = call_user_func_array($callable, $args);
                    }
                }
            }

            return $output[0];
        }
    }

    /**
     * Get hook listeners
     *
     * Return an array of registered hooks. If `$name` is a valid
     * hook name, only the listeners attached to that hook are returned.
     * Else, all listeners are returned as an associative array whose
     * keys are hook names and whose values are arrays of listeners.
     *
     * @param  string     $name     A hook name (Optional)
     * @return array|null
     */
    public function getHooks($name = null)
    {
        if (!is_null($name)) {
            return isset($this->hooks[(string) $name]) ? $this->hooks[(string) $name] : null;
        } else {
            return $this->hooks;
        }
    }

    /**
     * Clear hook listeners
     *
     * Clear all listeners for all hooks. If `$name` is
     * a valid hook name, only the listeners attached
     * to that hook will be cleared.
     *
     * @param  string   $name   A hook name (Optional)
     */
    public function clearHooks($name = null)
    {
        if (!is_null($name) && isset($this->hooks[(string) $name])) {
            $this->hooks[(string) $name] = [[]];
        } else {
            foreach ($this->hooks as $key => $value) {
                $this->hooks[$key] = [[]];
            }
        }
    }
}
