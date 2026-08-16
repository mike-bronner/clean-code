<?php

/**
 * Compliant conditionals — no `else` and no `elseif` anywhere — plus the
 * near-miss shapes CleanCode.Conditionals.DisallowElse must stay silent on:
 * the member names PHP allows the reserved words `else` and `elseif` to carry
 * (a method or class constant since 7.0, an enum case since 8.1).
 *
 * The `elseif`/`else if` chains this file used to carry as near-misses moved
 * to failing.php with #14: both are violations now, so a compliant fixture is
 * the wrong place for them.
 */

declare(strict_types=1);

enum Branch
{
    case else;

    case elseif;
}

class PassingConditionals
{
    public const else = 'reserved words are legal class-constant names';

    public const elseif = 'and that holds for elseif too';

    public function earlyReturn(bool $flag): int
    {
        if ($flag) {
            return 1;
        }

        return 2;
    }

    public function guardClause(?string $name): string
    {
        if ($name === null) {
            throw new \InvalidArgumentException('A name is required');
        }

        return $name;
    }

    public function ternary(bool $flag): int
    {
        return $flag ? 1 : 2;
    }

    public function separateGuards(bool $flag, bool $other): int
    {
        if ($flag) {
            return 1;
        }

        if ($other) {
            return 2;
        }

        return 3;
    }

    public function alternativeSyntaxWithoutElse(bool $flag): int
    {
        $result = 0;

        if ($flag):
            $result = 1;
        endif;

        return $result;
    }

    public function else(): string
    {
        return 'a method may be named after a reserved word';
    }

    public function elseif(): string
    {
        return 'and that reserved word may be elseif';
    }

    public function memberReferences(object $subject): array
    {
        return [
            $subject->else(),
            self::else,
            Branch::else,
            self::else(),
            $subject->elseif(),
            self::elseif,
            Branch::elseif,
            self::elseif(),
        ];
    }
}
