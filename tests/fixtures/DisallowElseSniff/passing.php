<?php

/**
 * Compliant conditionals — no `else` branch anywhere — plus the near-miss
 * shapes CleanCode.Conditionals.DisallowElse must stay silent on: `elseif`
 * and the two-word `else if`, both of which PHPMD leaves alone, and the member
 * names PHP allows the reserved word `else` to carry (a method or class
 * constant since 7.0, an enum case since 8.1).
 */

declare(strict_types=1);

enum Branch
{
    case else;
}

class PassingConditionals
{
    public const else = 'reserved words are legal class-constant names';

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

    public function elseIfChainWithoutElse(bool $flag, bool $other): int
    {
        $result = 0;

        if ($flag) {
            $result = 1;
        } elseif ($other) {
            $result = 2;
        }

        return $result;
    }

    public function elseSpaceIfChainWithoutElse(bool $flag, bool $other): int
    {
        $result = 0;

        if ($flag) {
            $result = 1;
        } else if ($other) {
            $result = 2;
        }

        return $result;
    }

    public function alternativeSyntaxElseIfWithoutElse(bool $flag, bool $other): int
    {
        $result = 0;

        if ($flag):
            $result = 1;
        elseif ($other):
            $result = 2;
        endif;

        return $result;
    }

    public function else(): string
    {
        return 'a method may be named after a reserved word';
    }

    public function memberReferences(object $subject): array
    {
        return [
            $subject->else(),
            self::else,
            Branch::else,
            self::else(),
        ];
    }
}
