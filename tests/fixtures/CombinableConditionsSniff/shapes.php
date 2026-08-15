<?php

declare(strict_types=1);

/**
 * Every spelling of a conditional PHP offers, for
 * CleanCode.Conditionals.CombinableConditions.
 *
 * failing.php is written entirely in the braced, merged-`elseif` layout.
 * PHP_CodeSniffer attaches scope to a different token in each of the other
 * spellings, so each is its own path through the clause walk and each is
 * exercised here. Line numbers are asserted exactly in
 * tests/Standards/CombinableConditionsTest.php.
 */

final class Shapes
{
    public function spacedElseIf(int $code): string
    {
        if ($code === 1) {
            return 'same';
        } else if ($code === 2) {
            return 'same';
        }

        return 'other';
    }

    public function elseAndIfOnSeparateLines(int $code): string
    {
        if ($code === 1) {
            return 'same';
        } else
        if ($code === 2) {
            return 'same';
        }

        return 'other';
    }

    public function alternativeChain(int $code): string
    {
        if ($code === 1):
            return 'same';
        elseif ($code === 2):
            return 'same';
        else:
            return 'other';
        endif;
    }

    public function bracelessGuards(?string $name, ?string $email): void
    {
        if ($name === null) return;
        if ($email === null) return;

        $this->log($name);
    }

    public function alternativeGuards(?string $name, ?string $email): void
    {
        if ($name === null): return; endif;
        if ($email === null): return; endif;

        $this->log($name);
    }

    public function mixedBodyFormsInOneChain(int $code): string
    {
        if ($code === 1) {
            return 'same';
        } elseif ($code === 2) return 'same';

        return 'other';
    }

    public function mixedBodyFormsBetweenGuards(?string $name, ?string $email): void
    {
        if ($name === null): return; endif;

        if ($email === null) {
            return;
        }

        $this->log($name);
    }

    public function nestedChain(int $outer, int $inner): string
    {
        if ($outer === 1) {
            if ($inner === 1) {
                return 'inner';
            } elseif ($inner === 2) {
                return 'inner';
            }

            return 'outer';
        }

        return 'none';
    }

    private function log(string $message): void
    {
    }
}
