<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Conditionals.AvoidConditionals.
 *
 * One instance of each of the four violation codes, in the plainest shape.
 * The variant shapes of the same constructs — brace-less, alternative syntax,
 * `else if`, short and nested ternaries — live in shapes.php, so this file
 * stays the contract sweep's simple floor.
 */

final class Ledger
{
    public function classify(int $amount): string
    {
        if ($amount > 100) {
            return 'large';
        } elseif ($amount > 10) {
            return 'medium';
        } else {
            return 'small';
        }
    }

    public function sign(int $amount): string
    {
        return $amount < 0 ? 'debit' : 'credit';
    }

    public function currency(string $country): string
    {
        switch ($country) {
            case 'US':
                return 'USD';
            default:
                return 'EUR';
        }
    }
}
