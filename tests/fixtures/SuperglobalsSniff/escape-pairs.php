<?php

/**
 * The interpolation escape guard, isolated to a run of backslashes.
 *
 * A backslash before the `$` cancels the interpolation, but a backslash can
 * itself be escaped, so what decides the outcome is whether the run in front of
 * the `$` is odd or even. An odd run leaves one backslash spare to cancel the
 * `$`; an even run is entirely consumed by escaped backslashes and the
 * interpolation stays live. The sniff's pattern counts complete pairs for this
 * reason, and only a fixture carrying both parities can tell the counting apart
 * from a blanket "any preceding backslash cancels".
 *
 * Both directions are measured, not read off the documentation. Each line below
 * was run through the PHP interpreter, through PHPMD 2.15.0, and through this
 * sniff; all three agree, so the fixture claims exact parity the way failing.php
 * does:
 *
 *   backslashes   PHP reads the superglobal   PHPMD   this sniff
 *   1             no                          no      no
 *   2             yes                         yes     yes
 *   3             no                          no      no
 *   4             yes                         yes     yes
 *
 * The even-run lines are the half that nothing else in the suite reaches:
 * failing.php and passing.php each carry a single-backslash line only, so
 * without this fixture an off-by-one that treated any leading backslash as
 * cancelling would keep the whole suite green while silently ceasing to report
 * a live read.
 *
 * The escaped names are deliberately all different, so the assertion can name
 * which parity reported rather than counting anonymous hits.
 */

function escapePairs(): array
{
    // One backslash: it cancels the `$`, so nothing is read.
    $odd = "one \$_GET";

    // Two backslashes: the first escapes the second, leaving a literal
    // backslash followed by a live interpolation of $_POST.
    $even = "two \\$_POST";

    // Three: one pair plus the spare that cancels the `$`.
    $oddLonger = "three \\\$_FILES";

    // Four: two complete pairs, so $_COOKIE interpolates.
    $evenLonger = "four \\\\$_COOKIE";

    return [$odd, $even, $oddLonger, $evenLonger];
}

function escapePairsInHeredoc(): string
{
    // The guard lives in the shared pattern, so it has to hold on the heredoc
    // branch as well as the double-quoted-string branch.
    return <<<TXT
        cancelled \$_SESSION
        live \\$_REQUEST
        TXT;
}
