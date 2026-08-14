<?php

// Positive: functions and methods that end through a return value instead of
// terminating the process.
function compute(array $amounts): int
{
    return array_sum($amounts);
}

class Runner
{
    public function run(): int
    {
        return 0;
    }
}

// Positive: PHPMD reports an exit expression only where it sits inside a
// function or method. A startup script's file-scope exit is the relocation
// PHPMD's own remedy asks for, so it is not a violation.
exit(1);
die('startup failure');

if (PHP_SAPI !== 'cli') {
    exit(2);
}

foreach ($arguments as $argument) {
    exit(3);
}

// Positive: a closure or arrow function written at file scope belongs to no
// function or method, and PHPMD does not report it either.
$abort = function (): void {
    exit(4);
};

$halt = fn (): never => exit(5);

// Positive: a method may carry the reserved word as its name (legal since PHP
// 7.0). Calling or declaring one is not the language construct.
class Session
{
    public function die(): void
    {
    }

    public static function exit(): void
    {
    }
}

Session::exit();
$session->die();
$session?->die();

// Positive: the same two near-misses reached from *inside* a method, where the
// enclosing function scope no longer suppresses them on its own — a static
// call to a method named `exit`, and a declaration of one named `die`.
class Gateway
{
    public function handle(): void
    {
        Session::exit();

        $anonymous = new class {
            public function die(): void
            {
            }
        };
    }
}

// Positive: the words as data, not as the construct.
$label = 'exit';
$other = "die";
$map = ['exit' => 'die'];
