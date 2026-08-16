<?php

declare(strict_types=1);

/**
 * A braced chain cut off before its closing brace, for
 * CleanCode.Conditionals.CombinableConditions.
 *
 * PHP_CodeSniffer tokenizes a file PHP itself would refuse, so the walk still
 * reaches this. Every clause here would qualify if it were closed, which is
 * what makes the file a test of termination rather than of silence by
 * accident.
 */

final class TruncatedBraced
{
    public function guards(?string $name, ?string $email): void
    {
        if ($name === null) {
            return;
        }

        if ($email === null) {
            return;
