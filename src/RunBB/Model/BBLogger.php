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

namespace RunBB\Model;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Processor\WebProcessor;

class BBLogger extends AbstractProcessingHandler
{
    /**
     * BBLogger constructor.
     * @param int $level
     * @param bool $bubble
     */
    public function __construct($level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    /**
     * @param array $record
     */
    protected function write(array $record)
    {
        $log = DB::forTable('logs')->create();
        $log->set('channel', $record['channel']);
        $log->set('level', $record['level']);
        $log->set('level_name', $record['level_name']);
        $log->set('message', $record['formatted']);
        $log->set('time', $record['datetime']->format('U'));
        $log->set('context', json_encode($record['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $log->set('extra', json_encode($record['extra'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));//min 5.4.0
        $log->save();
    }

    public function initLogger()
    {
        $this->setFormatter(new LineFormatter('%message%'));
        $this->pushProcessor(new WebProcessor());
        if (ForumEnv::get('FEATHER_DEBUG')) {
            $this->pushProcessor(new \Monolog\Processor\MemoryUsageProcessor());
            $this->pushProcessor(new \Monolog\Processor\MemoryPeakUsageProcessor());
        }
        return $this;
    }

    public static function count()
    {
        return DB::forTable('logs')->count();
    }

    public static function getLogs($start_from = 0)
    {
        return DB::forTable('logs')
            ->orderByDesc('id')
            ->limit(User::get()->disp_topics)
            ->offset($start_from)
            ->findMany();
    }

    public static function delete(array $ids)
    {
        if(!empty($ids)) {
            return DB::forTable('logs')
                ->whereIn('id', array_keys($ids))
                ->deleteMany();
        }
        return false;
    }
}
