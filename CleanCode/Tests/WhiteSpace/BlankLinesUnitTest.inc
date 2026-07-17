<?php

class CompliantExample
{
    private int $count = 0;

    public function increment(): int
    {
        $this->count++;

        return $this->count;
    }

    public function emptyBody(): void
    {
    }

    public function singleStatement(): int
    {
        return 1;
    }
}

interface CompliantContract
{
    public function handle(): void;
}

trait CompliantHelper
{
    public function help(): callable
    {
        return function (): int {
            $value = 1;

            return $value;
        };
    }
}


class TooManyBlankLines
{
    public function first(): void
    {
        $alpha = 1;


        $beta = 2;
    }


    public function second(): void
    {
    }
}

class BlankAfterClassBrace
{

    public function only(): void
    {
    }
}

class BlankBeforeClassCloseBrace
{
    public function only(): void
    {
    }

}

interface PaddedInterface
{

    public function handle(): void;

}

trait PaddedTrait
{

    public function help(): void
    {
    }

}

class PaddedMethodBody
{
    public function padded(): int
    {

        $value = 1;

        return $value;

    }
}

function paddedFunction(): int
{

    return 1;

}

$closure = function (): int {

    return 2;

};

function emptyBodyWithBlankLine(): void
{

}

class MultipleBlanksAfterBrace
{


    public function noop(): void
    {
    }
}

// Trailing comment immediately followed by two blank lines.


$afterComment = 1;

$nested = function () { return function (): int {


    return 1;
}; };

enum PaddedEnum
{

    case One;

}

$anonymous = new class {

    public function noop(): void
    {
    }

};

$stackedClosers = function () { return function (): int {
    return 1;

}; };

// Multi-line blank run directly before a closing brace: exercises the
// collectRun(-1) direction over a 2+ line run, reported once.
function multipleBlanksBeforeClose(): int
{
    return 1;


}

// Control-structure braces are out of scope: a blank line right after
// an if opener (not an OO/function scope opener) must NOT be flagged.
function controlStructureScope(): int
{
    if (true) {

        return 1;
    }

    return 2;
}

// Heredoc interiors are never treated as blank: two blank lines inside a
// heredoc must NOT be flagged or altered by the fixer.
function heredocBody(): string
{
    return <<<EOT
        line one


        line four
        EOT;
}

// Inline-HTML / template regions are out of scope: blank lines inside an
// HTML block between PHP tags must NOT be flagged.
$beforeHtml = 1;
?>


<?php
$afterHtml = 2;
