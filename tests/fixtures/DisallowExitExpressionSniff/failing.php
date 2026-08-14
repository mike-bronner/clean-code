<?php

function bareExit(): void
{
    exit;
}

function exitWithZero(): void
{
    exit(0);
}

function exitWithVariable(int $code): void
{
    exit($code);
}

function bareDie(): void
{
    die;
}

function dieWithMessage(): void
{
    die('message');
}

function upperCaseExit(): void
{
    EXIT;
}

function mixedCaseDie(): void
{
    Die('message');
}

function exitInsideConditional(int $param): void
{
    if ($param === 42) {
        exit(23);
    }
}

function exitAsFallbackExpression(?string $value): string
{
    return $value ?? exit(1);
}

class Runner
{
    public function run(): void
    {
        exit(1);
    }

    public function inClosure(): void
    {
        $abort = function (): void {
            exit(2);
        };

        $abort();
    }

    public function inArrowFunction(): callable
    {
        return fn (): never => exit(3);
    }

    public function inAnonymousClass(): void
    {
        $wrapped = new class {
            public function boom(): void
            {
                exit(5);
            }
        };
    }
}

trait Terminates
{
    public function stop(): void
    {
        die('trait');
    }
}

enum Status
{
    case Failed;

    public function halt(): void
    {
        exit(4);
    }
}
