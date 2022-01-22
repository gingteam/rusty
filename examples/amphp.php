<?php

use function Amp\delay;
use Amp\Loop;
use GingTeam\Rusty\Amphp\SwooleDriver;

require_once __DIR__.'/../vendor/autoload.php';

Loop::set(new SwooleDriver());

Loop::defer(function () {
    echo 'Wellcome!', PHP_EOL;
});

Loop::repeat(1000, function ($watcherId) {
    echo 'Hello, world!', PHP_EOL;
    yield delay(1000);
    echo 'Goodbye, world!', PHP_EOL;
    Loop::cancel($watcherId);
});

Loop::delay(10000, function () {
    echo 'Bye', PHP_EOL;
    Loop::stop();
});

Loop::onReadable(STDIN, function ($watcher, $stream) {
    $chunk = \fread($stream, 8192);

    echo 'Read '.\strlen($chunk).' bytes'.PHP_EOL;
    Loop::cancel($watcher);
    echo 'Cancel', PHP_EOL;
});

Loop::onSignal(\SIGINT, function () {
    echo 'Stop...', PHP_EOL;
    Loop::stop();
});

Loop::run();
