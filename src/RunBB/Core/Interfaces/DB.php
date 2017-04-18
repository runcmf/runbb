<?php
/**
 * Copyright 2017 1f7.wizard@gmail.com
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

namespace RunBB\Core\Interfaces;

/**
 * Idiorm wrapper
 * Class DB
 * @package RunBB\Core\Interfaces
 */
class DB extends SlimSugar
{
    /**
     * Replace \ORM::forTable. Also work with multi-connections
     * @param null $name
     * @return \ORM
     */
    public static function forTable($name=null, $connName = \ORM::DEFAULT_CONNECTION)
    {
        return \ORM::forTable(\ORM::getConfig('tablePrefix', $connName) . $name, $connName);
    }

    public static function prefix($connName = \ORM::DEFAULT_CONNECTION)
    {
        return \ORM::getConfig('tablePrefix', $connName);
    }

    public static function init(array $config, $connName = \ORM::DEFAULT_CONNECTION)
    {
        $config['db_prefix'] = (!empty($config['db_prefix'])) ? $config['db_prefix'] : '';
        switch ($config['db_type']) {
            case 'mysql':
                if (!extension_loaded('pdo_mysql')) {
                    throw new \RunBB\Exception\RunBBException('Driver pdo_mysql not installed.', 500);
                }
                \ORM::configure('mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'], null, $connName);
                \ORM::configure('driver_options', [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'], $connName);
                break;
            case 'sqlite':
            case 'sqlite3':
                if (!extension_loaded('pdo_sqlite')) {
                    throw new \RunBB\Exception\RunBBException('Driver pdo_mysql not installed.', 500);
                }
                \ORM::configure('sqlite:./' . $config['db_name'], null, $connName);
                break;
            case 'pgsql':
                if (!extension_loaded('pdo_pgsql')) {
                    throw new \RunBB\Exception\RunBBException('Driver pdo_mysql not installed.', 500);
                }
                \ORM::configure('pgsql:host=' . $config['db_host'] . 'dbname=' . $config['db_name'], null, $connName);
                break;
        }
        \ORM::configure('username', $config['db_user'], $connName);
        \ORM::configure('password', $config['db_pass'], $connName);
        \ORM::configure('id_column_overrides', [$config['db_prefix'] . 'groups' => 'g_id'], $connName);
        // use magic for set table prefix value
        \ORM::configure('tablePrefix', $config['db_prefix'], $connName);
    }

    public function __call($name, $arguments)
    {
        $method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

        if (method_exists('ORM', $method)) {
            return call_user_func_array(['ORM', $method], $arguments);
        } else {
            throw new \RunBB\Exception\RunBBException("Idiorm Method $name() does not exist", 500);
        }
    }

    public static function __callStatic($name, $arguments)
    {
        $method = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));

//        return call_user_func_array(['ORM', $method], $arguments);
        if (method_exists('ORM', $method)) {
            return call_user_func_array(['ORM', $method], $arguments);
        } else {
            throw new \RunBB\Exception\RunBBException("Idiorm Method $name() does not exist", 500);
        }
    }
}
