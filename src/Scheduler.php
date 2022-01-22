<?php

declare(strict_types=1);

namespace GingTeam\Rusty;

use Workerman\Timer;

class Scheduler
{
    /**
     * Repeat a callback every $interval seconds.
     */
    public static function repeat(int $interval, callable $callback): int|bool
    {
        return Timer::add($interval, $callback);
    }

    /**
     * Run a callback after $interval seconds.
     */
    public static function delay(int $interval, callable $callback): int|bool
    {
        return Timer::add($interval, $callback, persistent: false);
    }

    /**
     * Cancel a $id callback.
     */
    public static function cancel(int $id): bool
    {
        return Timer::del($id);
    }

    /**
     * Remove all callbacks.
     */
    public static function reset(): void
    {
        Timer::delAll();
    }
}
