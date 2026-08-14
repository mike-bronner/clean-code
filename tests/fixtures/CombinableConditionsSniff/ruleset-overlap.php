<?php

declare(strict_types=1);

/**
 * The minimum pair of flagged shapes, free of anything else the master ruleset
 * speaks about, for CleanCode.Conditionals.CombinableConditions.
 *
 * failing.php trips several unrelated sniffs at the same lines — magic numbers,
 * duplicate blocks, mapping-array candidates — which makes it useless for
 * asking *which* sniffs speak about a combinable conditional. This file holds
 * one chain pair and one guard pair and nothing else, so the source list at
 * each flagged line is the answer to that question.
 */

final class RulesetOverlap
{
    public function chain(string $code): string
    {
        if ($code === 'a') {
            return 'same';
        } elseif ($code === 'b') {
            return 'same';
        }

        return 'other';
    }

    public function guards(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        if ($email === null) {
            return;
        }

        $this->log($name);
    }

    private function log(string $message): void
    {
    }
}
