<?php

declare(strict_types=1);

final class Guards
{
    public function bareReturn(?string $value): string
    {
        if ($value === null) {
            return;
        }

        return $value;
    }

    public function valueReturn(?string $value): string
    {
        if ($value === null) {
            return 'none';
        }

        return $value;
    }

    public function thrown(?string $value): string
    {
        if ($value === null) {
            throw new InvalidArgumentException('missing');
        }

        return $value;
    }

    public function ternaryValue(?string $value, bool $strict): string
    {
        if ($value === null) {
            return $strict ? 'strict' : 'loose';
        }

        return $value;
    }

    public function nestedInLoop(array $values): int
    {
        foreach ($values as $value) {
            if ($value === null) {
                return 0;
            }
        }

        return count($values);
    }

    public function withElse(?string $value): string
    {
        if ($value === null) {
            return 'none';
        } else {
            $value = trim($value);
        }

        return $value;
    }

    public function withElseIf(?string $value): string
    {
        if ($value === null) {
            return 'none';
        } elseif ($value === '') {
            return 'empty';
        }

        return $value;
    }

    public function withElseSpacedIf(?string $value): string
    {
        if ($value === null) {
            $value = 'none';
        } else if ($value === '') {
            return 'empty';
        }

        return $value;
    }

    public function twoStatements(?string $value): string
    {
        if ($value === null) {
            $this->log('missing');
            return 'none';
        }

        return $value;
    }

    public function lastStatement(?string $value): void
    {
        $this->log('checking');

        if ($value === null) {
            return;
        }
    }

    public function lastInLoop(array $values): void
    {
        foreach ($values as $value) {
            if ($value === null) {
                return;
            }
        }
    }

    public function notAnExit(?string $value): string
    {
        if ($value === null) {
            $value = 'none';
        }

        return $value;
    }

    public function emptyBody(?string $value): string
    {
        if ($value === null) {
        }

        return (string) $value;
    }

    public function closureGuard(): Closure
    {
        return function (?string $value): string {
            if ($value === null) {
                return 'none';
            }

            return $value;
        };
    }

    public function closureLast(): Closure
    {
        $callback = function (?string $value): void {
            if ($value === null) {
                return;
            }
        };

        return $callback;
    }

    public function alternativeSyntax(?string $value): string
    {
        if ($value === null):
            return 'none';
        endif;

        return $value;
    }

    public function braceless(?string $value): string
    {
        if ($value === null)
            return 'none';

        return $value;
    }

    public function ternaryAndSwitch(?string $value): string
    {
        $value = $value === null ? 'none' : $value;

        switch ($value) {
            case 'none':
                return 'none';
        }

        return $value;
    }

    public function exitThenMore(?string $value): string
    {
        if ($value === null) {
            throw new InvalidArgumentException('missing');
            $this->log('unreachable');
        }

        return $value;
    }
}

if (defined('LOADED') === true) {
    return;
}

define('LOADED', true);

function alternativeLoopLast(array $values): void
{
    foreach ($values as $value):
        if ($value === null) {
            return;
        }
    endforeach;
}

function exitShapes(array $values): Closure
{
    if ($values === []) {
        return fn (int $value): int => $value;
    }

    if ($values === [null]) {
        throw new InvalidArgumentException(message: 'null', code: 1);
    }

    if ($values === [1]) {
        return match (count($values)) {
            1 => fn () => 1,
            default => fn () => 2,
        };
    }

    if ($values === [2]) {
        return function () use ($values): int {
            return count($values);
        };
    }

    return fn (): int => 0;
}

function alternativeElse(?string $value): string
{
    if ($value === null):
        return 'none';
    else:
        $value = trim($value);
    endif;

    return $value;
}

function guardBeforeCatch(bool $ready): void
{
    try {
        if ($ready) {
            return;
        }
    } catch (RuntimeException $error) {
        report($error);
    }
}

function guardBeforeElse(bool $ready, bool $done): void
{
    if ($ready) {
        if ($done) {
            return;
        }
    } else {
        prepare();
    }
}

function guardBeforeElseIf(bool $ready, bool $done): void
{
    if ($ready) {
        if ($done) {
            return;
        }
    } elseif ($done) {
        prepare();
    }
}

function guardBeforeElseSpacedIf(bool $ready, bool $done): void
{
    if ($ready) {
        if ($done) {
            return;
        }
    } else if ($done) {
        prepare();
    }
}

function guardBeforeAlternativeElse(bool $ready, bool $done): void
{
    if ($ready):
        if ($done) {
            return;
        }
    else:
        prepare();
    endif;
}

function guardBeforeFinally(bool $ready): void
{
    try {
        if ($ready) {
            return;
        }
    } finally {
        cleanup();
    }
}

function guardBeforeTryEnds(bool $ready): void
{
    try {
        if ($ready) {
            return;
        }
    } catch (RuntimeException $error) {
        report($error);
    } finally {
        cleanup();
    }

    prepare();
}

function guardBeforeIfElseEnds(bool $ready, bool $done): void
{
    if ($ready) {
        if ($done) {
            return;
        }
    } elseif ($done) {
        prepare();
    } else {
        cleanup();
    }

    prepare();
}

function loopExits(array $values): int
{
    $total = 0;

    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }

        if ($value === false) {
            break;
        }

        $total++;
    }

    return $total;
}
