<?php

declare(strict_types=1);

namespace GingTeam\Rusty;

use Workerman\Worker as WorkermanWorker;

class Worker extends WorkermanWorker
{
    protected static function displayUI()
    {
    }

    protected static function installSignal()
    {
        parent::installSignal();
        $handle = [Worker::class, 'signalHandler'];

        \pcntl_signal(\SIGINT, $handle, false);
        \pcntl_signal(\SIGTERM, $handle, false);
    }

    /**
     * @param int $signal
     *
     * @return void
     */
    public static function signalHandler($signal)
    {
        switch ($signal) {
            case \SIGINT:
            case \SIGTERM:
                static::$_gracefulStop = false;
                static::stopAll();
                break;
        }
    }

    public static function safeEcho($msg, $decorated = false)
    {
        return false;
    }
}
