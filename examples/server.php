<?php

use GingTeam\Rusty\Scheduler;
use GingTeam\Rusty\Worker;

require_once __DIR__.'/../vendor/autoload.php';

$worker = new Worker();

$worker->name = 'Demo';

$worker->onWorkerStart = function () {
    $id = Scheduler::repeat(1, function () use (&$id) {
        static $count = 0;
        echo 'Hello, world!', PHP_EOL;
        ++$count;
        if ($count > 5) {
            Scheduler::cancel($id);
        }
    });
};

Worker::runAll();
