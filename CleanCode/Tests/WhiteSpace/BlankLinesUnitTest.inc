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
