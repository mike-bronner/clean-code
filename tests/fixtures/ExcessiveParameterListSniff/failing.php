<?php

declare(strict_types=1);

interface Formatter
{
    public function format($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10);
}

trait Loggable
{
    public function log($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10)
    {
    }
}

enum Level
{
    public function describe($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10)
    {
    }
}

abstract class Renderer
{
    public function __construct(
        private int $a1,
        private int $a2,
        private int $a3,
        private int $a4,
        private int $a5,
        private int $a6,
        private int $a7,
        private int $a8,
        private int $a9,
        private int $a10
    ) {
    }

    public function render($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10)
    {
    }

    public function collect($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, ...$rest)
    {
    }

    abstract public function draw($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10, $a11);
}

function summarize($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10, $a11)
{
    function nested($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10)
    {
    }
}

function wrapped(
    $a1,
    $a2,
    $a3,
    $a4,
    $a5,
    $a6,
    $a7,
    $a8,
    $a9,
    $a10
) {
}

class Host
{
    public function build($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10)
    {
        function insideMethod($b1, $b2, $b3, $b4, $b5, $b6, $b7, $b8, $b9, $b10)
        {
        }
    }
}
