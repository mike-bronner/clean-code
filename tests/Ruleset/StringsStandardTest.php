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
 * HEREDOC; RequireHeredocForStructuredText owns the *content* (markup in a quoted
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
 * - RequireHeredocForStructuredText reports **once, at the opening fragment**, because
 *   it joins the fragments and asks its question of the whole text. It reported
 *   per fragment until the sniff was widened past markup to every embedded
 *   language: reading the text whole is what lets it recognise a query or a
 *   config block that no single fragment carries, and reporting once is the
 *   consequence. The fix for the whole string is the single edit
 *   MultilineStrings already auto-applies.
 */
it('reports the markup and the multi-line shape separately', function (): void {
    $file = analyzeStdinSource(
        ['CleanCode.Strings.RequireHeredocForStructuredText', 'CleanCode.Strings.MultilineStrings'],
        "<?php\n\n\$x = \"<ul>\n    <li>item</li>\n</ul>\";\n"
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([
        3 => [
            'CleanCode.Strings.RequireHeredocForStructuredText.StructuredTextInString',
            'CleanCode.Strings.MultilineStrings.QuotedString',
        ],
    ]);
});

/**
 * Neither sniff subsumes the other. Single-line markup is the
 * RequireHeredocForStructuredText-only case; a multi-line string with no markup in it
 * is the MultilineStrings-only case. Without these two, the assertion above
 * would hold just as well for a pair of sniffs that always fired together.
 */
it('keeps each sniff to the slice it owns', function (string $source, array $expected): void {
    $file = analyzeStdinSource(
        ['CleanCode.Strings.RequireHeredocForStructuredText', 'CleanCode.Strings.MultilineStrings'],
        $source
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([3 => $expected]);
})->with([
    'single-line markup' => [
        "<?php\n\n\$x = \"<p>only markup</p>\";\n",
        ['CleanCode.Strings.RequireHeredocForStructuredText.StructuredTextInString'],
    ],
    'multi-line prose, no embedded language' => [
        "<?php\n\n\$x = \"Dear customer,\n    your order has shipped.\";\n",
        ['CleanCode.Strings.MultilineStrings.QuotedString'],
    ],
    'multi-line SQL is the RequireHeredocForStructuredText case, not the shape case' => [
        "<?php\n\n\$x = \"SELECT *\n    FROM t\";\n",
        [
            'CleanCode.Strings.RequireHeredocForStructuredText.StructuredTextInString',
            'CleanCode.Strings.MultilineStrings.QuotedString',
        ],
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
            'CleanCode.Strings.RequireHeredocForStructuredText',
            'CleanCode.Strings.RequireStringInterpolation',
        ],
        "<?php\n\n\$x = <<<HTML\n<ul>\n    <li class=\"item\">item</li>\n</ul>\nHTML;\n"
    );

    expect(violationSourcesByLine($file->getErrors()))->toBe([]);
});
