<?php

declare(strict_types=1);

namespace App;

/**
 * PHPCS invents T_GOTO_LABEL itself: the tokenizer rewrites any T_STRING
 * followed by a single colon into a goto label unless the nearest preceding
 * `case`, `?`, or `enum` says otherwise (Tokenizers/PHP.php). Every shape below
 * is an `identifier :` sequence that is NOT a goto label, so each one is a
 * chance for that heuristic to produce a false positive. The sniff must stay
 * silent on all of them.
 *
 * The methods named `goto` are the other half: `goto` is a reserved word, but
 * PHP 7.0 onwards allows it as a method name, and those calls are not the
 * language construct.
 */
enum Suit: string
{
    case Hearts = 'H';
}

interface HasGoto
{
    public function goto(): void;
}

class Boundaries
{
    public const ONE = 1;

    public function switchLabels(int $value): int
    {
        switch ($value) {
            case ONE:
                return 1;
            case Boundaries::ONE:
                return 2;
            default:
                return 0;
        }
    }

    public function ternaries(int $value): string
    {
        $long = $value > 0 ? POSITIVE : NEGATIVE;
        $short = $long ?: NEUTRAL;

        return $short;
    }

    public function namedArguments(): void
    {
        thing(goto: 1, label: 2);
        thing(alpha: 1);
    }

    public function alternativeSyntax(int $value): void
    {
        if ($value):
            echo 'yes';
        else:
            echo 'no';
        endif;

        foreach ([1, 2] as $item):
            echo $item;
        endforeach;
    }

    public function staticAccess(): int
    {
        return self::ONE + static::ONE + Boundaries::ONE;
    }

    public function methodsNamedGoto(HasGoto $object, ?HasGoto $nullable): void
    {
        $object->goto();
        $nullable?->goto();
        Boundaries::goto();
        self::goto();
        static::goto();
    }
}
