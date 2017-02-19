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

namespace RunBB\Controller\Admin;

use RunBB\Core\Interfaces\Container;

class Compose
{
    private $c;

    public function __construct(\Slim\Container $c)
    {
        $this->c = $c;
        $this->model = new \RunBB\Model\Admin\Plugins($c);
    }

    public function display($req, $res, $args)
    {
        $func = isset($_GET['function']) ? $_GET['function'] : $_POST['function'];

        switch ($func) {
            case 'getStatus':
                $this->getStatus();
                break;
            case 'downloadComposer':
                $this->downloadComposer();
                break;
            case 'extractComposer':
                $this->extractComposer();
                break;
            case 'command':
                $this->command();
                break;
            default:
                die('Wrong function');
        }
    }

    protected function getStatus()
    {
        $output = [
            'composer' => file_exists(ForumEnv::get('APP_ROOT') . 'composer.phar'),
            'composer_extracted' => file_exists(ForumEnv::get('APP_ROOT') . 'extracted/vendor'),
            'installer' => file_exists(ForumEnv::get('APP_ROOT') . 'installer.php'),
            'csrf_name' => Container::get('template')->get('csrf_name'),
            'csrf_value' => Container::get('template')->get('csrf_value'),
        ];
        header("Content-Type: text/json; charset=utf-8");
        echo json_encode($output);
    }

    protected function downloadComposer()
    {
        putenv('COMPOSER_HOME=' . ForumEnv::get('APP_ROOT') . 'extracted');

        $installerURL = 'https://getcomposer.org/installer';
        $installerFile = ForumEnv::get('APP_ROOT') . 'installer.php';
        if (!file_exists($installerFile)) {
            echo 'Downloading ' . $installerURL . PHP_EOL;
            flush();
            $ch = curl_init($installerURL);
            curl_setopt($ch, CURLOPT_CAINFO, ForumEnv::get('FORUM_ROOT') . 'Helpers/cacert.pem');
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_FILE, fopen($installerFile, 'w+'));
            if (curl_exec($ch)) {
                echo 'Success downloading ' . $installerURL . PHP_EOL;
            } else {
                echo 'Error downloading ' . $installerURL . PHP_EOL;
                die();
            }
            flush();
        }
        echo 'Installer found : ' . $installerFile . PHP_EOL;
        echo 'Starting installation...' . PHP_EOL;
        flush();

        global $argv;
        $argv = [
            '--install-dir=' . ForumEnv::get('APP_ROOT'),
            '--force',
//            '--prefer-source'
//            '--filename=' . $installerFile
        ];
        include $installerFile;
        flush();
    }

    protected function extractComposer()
    {
        if (file_exists(ForumEnv::get('APP_ROOT') . 'composer.phar')) {
            echo 'Extracting composer.phar ...' . PHP_EOL;

            $composer = new \Phar(ForumEnv::get('APP_ROOT') . 'composer.phar');
            $composer->extractTo(ForumEnv::get('APP_ROOT') . 'extracted');
            echo 'Extraction complete.' . PHP_EOL;
            flush();
        } else {
            echo 'composer.phar does not exist';
        }
    }

    protected function command()
    {
        command:
        set_time_limit(-1);
        // if RAM <= 128M then try set to minimum 256
        if ($this->getMemLimit() !== 0 && $this->getMemLimit() <= 128 * 1024 * 1024) {
            ini_set('memory_limit', '256M');
        }
        putenv('COMPOSER_HOME=' . ForumEnv::get('APP_ROOT') . 'extracted');
        // discard any local made changes
        if ($_POST['command'] === 'update') {
            putenv('COMPOSER_DISCARD_CHANGES=true');
        }

        if (file_exists(ForumEnv::get('APP_ROOT') . 'extracted')) {
            require_once(ForumEnv::get('APP_ROOT') . 'extracted/vendor/autoload.php');

            // -v Increased verbosity of messages
            // -vv Informative non essential messages
            // -vvv Debug messages
            // --no-interaction (-n): Do not ask any interactive question.
            // --working-dir (-d): If specified, use the given directory as working directory.
            // --profile: Display timing and memory usage information
            $input = new \Symfony\Component\Console\Input\StringInput(
                $_POST['command'].' --profile -n -vvv -d '.ForumEnv::get('APP_ROOT')
            );
            $output = new \Symfony\Component\Console\Output\StreamOutput(fopen('php://output', 'w'));
            $conApp = new \Composer\Console\Application();
            $conApp->setAutoExit(false); // prevent `$conApp->run` method from exitting the script
            $conApp->run($input, $output);

            // add extension info to db
            if (isset($_POST['pluginInfo']) && strstr($_POST['command'], 'require')) {
                $result = $this->model->addInfo(json_decode(base64_decode($_POST['pluginInfo']), true));
                echo 'DB result: '.$result;
            } elseif (isset($_POST['pluginInfo']) && strstr($_POST['command'], 'remove')) {
                // complete remove extension
                $plug = json_decode(base64_decode($_POST['pluginInfo']), true);
                $this->model->uninstall($plug['key']);
                // rebuild cache
                $result = $this->model->getList(true);
                echo 'DB result: '.var_export($result, true);
            }
        } else {
            echo 'Composer not extracted.';
            $this->extractComposer();
            goto command;
        }
    }

    protected function getMemLimit()
    {
        if (function_exists('ini_get')) {
            $value = trim(ini_get('memory_limit'));
            $unit = strtolower(substr($value, -1, 1));
            $value = (int) $value;

            switch ($unit) {
                case 'g':
                    $value *= 1024 * 1024 * 1024;
                    break;
                case 'm':
                    $value *= 1024 * 1024;
                    break;
                case 'k':
                    $value *= 1024;
                    break;
            }

            return $value;
        }
        return 0;
    }
}
