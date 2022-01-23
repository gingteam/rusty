<?php

use Swow\Coroutine;
use Swow\Sync\WaitReference;

$wr = new WaitReference();

Coroutine::run(function () use ($wr) {
    sleep(1);
    echo "Hello, world!\n";
});

Coroutine::run(function () use ($wr) {
    sleep(1);
    echo "Hello, world!\n";
});

WaitReference::wait($wr);
echo 'Done!';
