<?php

/**
 * How the four CleanCode.Strings.* sniffs of the Strings standard (#25) behave
 * together with CleanCode.Strings.MultilineStrings, which landed separately for
 * the "Code Style: Multiline Strings (HEREDOC)" standard and registers on the
 * same string tokens.
 *
 * The overlap is real and deliberate, so it is pinned here rather than left to
 * be rediscovered: a multi-line quoted string carrying markup is the one input
 * both standards have an opinion about. MultilineStrings owns the *shape*
 * (any multi-line quoted string, markup or not) and auto-fixes it to a
 * HEREDOC; RequireHeredocForMarkup owns the *content* (markup in a quoted
 * string, multi-line or not) and is detection-only. Neither subsumes the
 * other — a single-line `"<p>x</p>"` is invisible to the first, and a
 * multi-line SQL string is invisible to the second — so both reporting on the
 * intersection is correct, not a duplicate diagnostic to be suppressed.
 */

declare(strict_types=1);

/**
 * The intersection: both sniffs report, each under its own code.
 *
 * Note the different *cardinality*, which is the behaviour worth pinning here.
 * PHP_CodeSniffer splits a multi-line quoted string into one token per physical
 * line, and the two sniffs react to that differently by design:
 *
 * - MultilineStrings reports the shape **once**, on the first fragment, because
 *   the violation is the string spanning lines at all.
 * - RequireHeredocForMarkup reports **per fragment that carries markup** —
 *   lines 3, 4 and 5 here — because it asks a question of the content, and each
 *   fragment it is handed genuinely contains a tag. It never sees the string
 *   whole, so it cannot collapse them.
 *
 * That is verbose on a long markup block, and it is a known limitation rather
 * than a defect: every line it names does contain markup that belongs in a
 * HereDoc, and the fix for all of them is the single edit MultilineStrings
 * already auto-applies. Recorded so a future change to either sniff has to be
 * deliberate about it.
 */
it('reports the markup and the multi-line shape separately', function (): void {
    $file = analyzeStdinSource(
        ['CleanCode.Strings.RequireHeredocForMarkup', 'CleanCode.Strings.MultilineStrings'],
        "<?php\n\n\$x = \"<ul>\n    <li>item</li>\n</ul>\";\n"
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        3 => [
            'CleanCode.Strings.RequireHeredocForMarkup.MarkupInString',
            'CleanCode.Strings.MultilineStrings.QuotedString',
        ],
        4 => ['CleanCode.Strings.RequireHeredocForMarkup.MarkupInString'],
        5 => ['CleanCode.Strings.RequireHeredocForMarkup.MarkupInString'],
    ]);
});

/**
 * Neither sniff subsumes the other. Single-line markup is the
 * RequireHeredocForMarkup-only case; a multi-line string with no markup in it
 * is the MultilineStrings-only case. Without these two, the assertion above
 * would hold just as well for a pair of sniffs that always fired together.
 */
it('keeps each sniff to the input only it owns', function (string $source, array $expected): void {
    $file = analyzeStdinSource(
        ['CleanCode.Strings.RequireHeredocForMarkup', 'CleanCode.Strings.MultilineStrings'],
        $source
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([3 => $expected]);
})->with([
    'single-line markup' => [
        "<?php\n\n\$x = \"<p>only markup</p>\";\n",
        ['CleanCode.Strings.RequireHeredocForMarkup.MarkupInString'],
    ],
    'multi-line without markup' => [
        "<?php\n\n\$x = \"SELECT *\n    FROM t\";\n",
        ['CleanCode.Strings.MultilineStrings.QuotedString'],
    ],
]);

/**
 * The HEREDOC both standards ask for satisfies both at once: converting the
 * intersection case by hand leaves every Strings sniff silent. This is what
 * makes the overlap benign — the two diagnostics do not pull in different
 * directions.
 */
it('is satisfied for every Strings sniff once the markup is a HereDoc', function (): void {
    $file = analyzeStdinSource(
        [
            'CleanCode.Strings.EscapeNestedQuotes',
            'CleanCode.Strings.HtmlAttributeQuotes',
            'CleanCode.Strings.MultilineStrings',
            'CleanCode.Strings.RequireHeredocForMarkup',
            'CleanCode.Strings.RequireStringInterpolation',
        ],
        "<?php\n\n\$x = <<<HTML\n<ul>\n    <li class=\"item\">item</li>\n</ul>\nHTML;\n"
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([]);
});
