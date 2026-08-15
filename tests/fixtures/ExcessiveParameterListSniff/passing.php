<?php

declare(strict_types=1);

interface Formatter
{
    public function format($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9);
}

abstract class Renderer implements Formatter
{
    public function __construct(private int $width, private int $height)
    {
    }

    public function render($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9)
    {
    }

    public function noParameters()
    {
    }

    abstract public function draw($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9);

    public function collect($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, ...$rest)
    {
    }
}

function summarize($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9)
{
}

$closure = function ($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10, $a11, $a12) {
};

$arrow = fn ($a1, $a2, $a3, $a4, $a5, $a6, $a7, $a8, $a9, $a10, $a11, $a12) => $a1;

var_dump(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);
