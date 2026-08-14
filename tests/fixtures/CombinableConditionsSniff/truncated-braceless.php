<?php

declare(strict_types=1);

/**
 * A brace-less guard cut off mid-statement, for
 * CleanCode.Conditionals.CombinableConditions. The second body never reaches a
 * semicolon, so its extent cannot be measured and the pair must not be
 * reported.
 */

final class TruncatedBraceless
{
    public function guards(?string $name, ?string $email): void
    {
        if ($name === null) return;
        if ($email === null) retu
