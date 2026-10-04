<?php flush(); ?>
<?php flush(); ?>
<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * A call that opens a statement is logic, even when every token on its line is
 * a name, a literal, or punctuation. The test runs this with a threshold of one
 * line, so each pair of same-shaped calls below is reported. Each pair opens
 * its statements after a different token: an open tag, an opening brace, a
 * semicolon, a closing brace, and a colon. Every other line has a shape of its
 * own.
 */
function afterOpeningBrace(): void
{
    alpha(1);
}

function afterOpeningBraceAgain($first): void
{
    alpha(2);
}

function afterSemicolon(int $first): void
{
    $ready = true;
    beta('one');
    $done = $ready;
    // A comment between the two statements does not hide the boundary.
    beta('two');
}

function afterClosingBrace(int $first, bool $flag): void
{
    if ($flag) {
        $flag = false;
    }
    gamma([]);
    while ($flag) {
        $flag = !$flag;
    }
    gamma([]);
}

function afterColon(int $first, int $second, int $mode): int
{
    switch ($mode) {
        case 1:
            delta(1.5);
            break;
        default:
            delta(2.5);
    }

    return $mode;
}
