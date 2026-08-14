<?php

/**
 * A variable interpolated on the second line of a double-quoted string.
 *
 * PHPCS tokenises a multi-line double-quoted string one line at a time, just
 * as it does a heredoc, so the token the report lands on is the line the name
 * is written on rather than the line the string opens on. phpmd 2.15 reports
 * the same name on the same line, so this is parity, not a divergence — it is
 * pinned because a sniff that reported at the opening quote instead would
 * still report the right name, the right count and the right file.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

function spansLines(): string
{
    return "first line
        second line $ml
        third line";
}
