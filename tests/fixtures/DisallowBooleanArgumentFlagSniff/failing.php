<?php

declare(strict_types=1);

class ReportRenderer
{
    public function __construct(private bool $verbose = false)
    {
    }

    public function render($withHeader = true)
    {
    }

    public function export($compress = FALSE)
    {
    }

    public function archive(bool $force)
    {
    }

    public function purge(?bool $force)
    {
    }

    public function reset(bool|null $force)
    {
    }

    public function restore(null|bool $force)
    {
    }

    public static function build(string $name, bool $strict = true)
    {
    }

    public function schedule(bool $now, bool $notify)
    {
    }

    public function defer()
    {
        $callback = function (bool $immediate) {
        };
        $arrow = fn (bool $immediate): bool => $immediate;

        return [$callback, $arrow];
    }
}

interface Publisher
{
    public function publish(bool $draft);
}

abstract class Base
{
    abstract public function flush(bool $hard);
}

function bootstrap($debug = true)
{
}
