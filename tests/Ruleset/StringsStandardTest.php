<?php

declare(strict_types=1);

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
