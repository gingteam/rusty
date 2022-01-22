<?php

declare(strict_types=1);

namespace GingTeam\Rusty\Amphp;

use Amp\Coroutine;
use Amp\Loop\Driver;
use Amp\Loop\Watcher;
use Amp\Promise;
use function Amp\Promise\rethrow;
use React\Promise\Promise as ReactPromise;
use Swoole\Event;
use Swoole\Process;
use Swoole\Timer;

class SwooleDriver extends Driver
{
    /** @var resource[] */
    private $streams = [];

    /** @var callable[] */
    private $readCallbacks = [];

    /** @var callable[] */
    private $writeCallbacks = [];

    /** @var callable */
    private $timerCallback;

    public function __construct()
    {
        $this->timerCallback = function (int $timeId, Watcher $watcher) {
            if ($watcher->type & Watcher::DELAY) {
                Timer::clear($timeId);
            }

            try {
                $result = ($watcher->callback)($watcher->id, $watcher->data);

                if (null === $result) {
                    return;
                }

                if ($result instanceof \Generator || $result instanceof ReactPromise) {
                    $result = new Coroutine($result);
                }

                if ($result instanceof Promise) {
                    rethrow($result);
                }
            } catch (\Throwable $exception) {
                $this->error($exception);
            }
        };
    }

    public static function isSupported(): bool
    {
        return \extension_loaded('swoole') || \extension_loaded('openswoole');
    }

    protected function activate(array $watchers)
    {
        /** @var Watcher $watcher */
        foreach ($watchers as $watcher) {
            switch ($watcher->type) {
                case Watcher::READABLE:
                case Watcher::WRITABLE:
                    $callback = function () use ($watcher) {
                        try {
                            $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                            if (null === $result) {
                                return;
                            }

                            if ($result instanceof \Generator) {
                                $result = new Coroutine($result);
                            }

                            if ($result instanceof Promise || $result instanceof ReactPromise) {
                                rethrow($result);
                            }
                        } catch (\Throwable $exception) {
                            $this->error($exception);
                        }
                    };

                    $streamId = (int) $watcher->value;

                    if ($watcher->type & Watcher::READABLE) {
                        $this->readCallbacks[$streamId] = $callback;
                    } else {
                        $this->writeCallbacks[$streamId] = $callback;
                    }

                    $this->streams[$streamId] = $watcher->value;
                    $this->refreshSwoole($streamId);
                    break;

                case Watcher::REPEAT:
                case Watcher::DELAY:
                    $watcher->value = Timer::tick($watcher->value, $this->timerCallback, $watcher);
                    break;

                case Watcher::SIGNAL:
                    Process::signal($watcher->value, function () use ($watcher) {
                        try {
                            $result = ($watcher->callback)($watcher->id, $watcher->value, $watcher->data);

                            if (null === $result) {
                                return;
                            }

                            if ($result instanceof \Generator) {
                                $result = new Coroutine($result);
                            }

                            if ($result instanceof Promise || $result instanceof ReactPromise) {
                                rethrow($result);
                            }
                        } catch (\Throwable $exception) {
                            $this->error($exception);
                        }
                    });
                    break;

                default:
                    throw new \Error('Unknown watcher type');
            }
        }
    }

    protected function dispatch(bool $blocking)
    {
        Event::dispatch();
    }

    protected function deactivate(Watcher $watcher)
    {
        switch ($watcher->type) {
            case Watcher::READABLE:
            case Watcher::WRITABLE:
                $streamId = (int) $watcher->value;
                if ($watcher->type & Watcher::READABLE) {
                    unset($this->readCallbacks[$streamId]);
                } else {
                    unset($this->writeCallbacks[$streamId]);
                }
                Event::del($watcher->value);
                $this->refreshSwoole($streamId);
                break;

            case Watcher::DELAY:
            case Watcher::REPEAT:
                Timer::clear($watcher->value);
                break;

            case Watcher::SIGNAL:
                Process::signal($watcher->value, null);
                break;

            default:
                throw new \Error('Unknown watcher type');
        }
    }

    public function getHandle()
    {
        return null;
    }

    public function stop()
    {
        foreach ($this->streams as $fd) {
            Event::del($fd);
        }
        $this->streams = [];

        Event::exit();
        parent::stop();
    }

    private function refreshSwoole(int $id): void
    {
        $fd = $this->streams[$id];

        if (isset($this->readCallbacks[$id])) {
            if (Event::isset($fd, SWOOLE_EVENT_READ)) {
                Event::set($fd, $this->readCallbacks[$id], null, SWOOLE_EVENT_READ);
            } else {
                Event::add($fd, $this->readCallbacks[$id], null, SWOOLE_EVENT_READ);
            }
        }

        if (isset($this->writeCallbacks[$id])) {
            if (Event::isset($fd, SWOOLE_EVENT_WRITE)) {
                Event::set($fd, null, $this->writeCallbacks[$id], SWOOLE_EVENT_WRITE);
            } else {
                Event::add($fd, null, $this->writeCallbacks[$id], SWOOLE_EVENT_WRITE);
            }
        }
    }
}
