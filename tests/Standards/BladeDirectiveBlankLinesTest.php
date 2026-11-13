<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const BLADE_DIRECTIVE_BLANK_LINES = 'CleanCode.WhiteSpace.BladeDirectiveBlankLines';

const BLADE_BLANK_BEFORE = BLADE_DIRECTIVE_BLANK_LINES . '.MissingBlankLineBefore';

const BLADE_BLANK_AFTER = BLADE_DIRECTIVE_BLANK_LINES . '.MissingBlankLineAfter';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BLADE_DIRECTIVE_BLANK_LINES);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BLADE_DIRECTIVE_BLANK_LINES, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

it('flags each opening and closing directive that runs into its neighbour', function (): void {
    expect(violationTuples(analyzeFixture(BLADE_DIRECTIVE_BLANK_LINES, 'failing.php')))->toBe([
        ['line' => 3, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 5, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 8, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 10, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 13, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 15, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 16, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 20, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 21, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 23, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 24, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 26, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 27, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 29, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 30, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 32, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ]);
});

it('reports every violation as fixable', function (): void {
    $flags = violationFixableFlags(analyzeFixture(BLADE_DIRECTIVE_BLANK_LINES, 'failing.php'));

    expect($flags)->toHaveCount(16)
        ->and($flags)->each->toBeTrue();
});

it('requires the blank line when the block is the first child of an element', function (): void {
    $file = analyzeFixture(BLADE_DIRECTIVE_BLANK_LINES, 'first-child.php');

    expect(violationTuples($file))->toBe([
        ['line' => 2, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
    ])->and(autofixedContents($file))->toStartWith("<flux:callout.text>\n\n    @guest\n");
});

it('requires the blank line when the block is the last child of an element', function (): void {
    $file = analyzeFixture(BLADE_DIRECTIVE_BLANK_LINES, 'last-child.php');

    expect(violationTuples($file))->toBe([
        ['line' => 5, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ])->and(autofixedContents($file))->toContain("    @endguest\n\n</flux:callout.text>\n");
});

it('pairs a block across the inline directives inside it', function (): void {
    expect(violationTuples(analyzeFixture(BLADE_DIRECTIVE_BLANK_LINES, 'inline-directives-inside-block.php')))
        ->toBe([
            ['line' => 6, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ]);
});

it('exempts a nested first or last child but still separates siblings and markup', function (): void {
    expect(violationTuples(analyzeFixture(BLADE_DIRECTIVE_BLANK_LINES, 'nesting.php')))->toBe([
        ['line' => 9, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 10, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 12, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 14, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 16, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
    ]);
});

it('exempts no closer whose next closer belongs to a block it does not sit in', function (): void {
    $source = "<div>\n\n@if (\$a)\n@foreach (\$b as \$c)\n@endif\n@endforeach\n<p>x</p>\n";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([
        ['line' => 5, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 6, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ]);
});

it('separates a closer from an enclosing closer that follows markup', function (): void {
    $source = "<div>\n\n@if (\$a)\n    @foreach (\$b as \$c)\n        x\n    @endforeach\n</p>@endif\n\n</div>\n";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([
        ['line' => 6, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ]);
});

it('flags the PR #875 view through the master ruleset', function (): void {
    $file = analyzeWithMasterRuleset(fixturePath('BladeDirectiveBlankLinesSniff', 'upsell-message.blade.php'));

    $sources = array_filter(
            violationTuples($file),
            static fn (array $violation): bool => str_starts_with(
                    $violation['source'],
                    BLADE_DIRECTIVE_BLANK_LINES . '.'
                )
        );

    expect(array_values($sources))->toBe([
        ['line' => 4, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 10, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 11, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 13, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ]);
});

it('ignores directive text inside a region Blade does not compile as a directive', function (string $source): void {
    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([]);
})->with([
    'escaped echo' => ["<p>\n{{ implode(',', [\n@if (\$a)\n@endif\n]) }}\n</p>\n"],
    'raw echo' => ["<p>\n{!! implode(',', [\n@if (\$a)\n@endif\n]) !!}\n</p>\n"],
    'blade comment' => ["<p>\n{{-- {{ \$a }}\n@if (\$a)\n@endif\n--}}\n</p>\n"],
    'php block' => ["<p>\n\n@php\n@if (\$a)\n@endif\n@endphp\n\n</p>\n"],
    'verbatim block' => ["<p>\n\n@verbatim\n@if (\$a)\n@endif\n@endverbatim\n\n</p>\n"],
    'php tag' => ["<p>\n<?php /*\n@if (\$a)\n@endif\n*/ ?>\n</p>\n"],
    'directive after text' => ["<p>\n<span>@if (\$a) A @endif</span>\n</p>\n"],
    'alpine attribute' => ["<button\n    @click=\"open = true\"\n>Open</button>\n"],
]);

it('does not let escaped or embedded text close a block', function (string $closer): void {
    $source = "<div>\n\n@if (\$a)\n    <p>{$closer}</p>\n@endif\n<p>B</p>\n";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([
        ['line' => 5, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ]);
})->with([
    'escaped directive' => ['@@endif'],
    'email address' => ['mail@endif.dev'],
]);

it('requires no blank line before the first line or after the last line of the file', function (): void {
    $source = "@if (\$a)\n    <p>A</p>\n@endif";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([]);
});

it('does not read a directive after a masked region as starting its line', function (string $source): void {
    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([]);
})->with([
    'opener after escaped echo' => ["<p>x</p>\n{{ \$label }} @if (\$x) a @endif\n<p>y</p>\n"],
    'opener after raw echo' => ["<p>x</p>\n{!! \$label !!} @if (\$x) a @endif\n<p>y</p>\n"],
    'opener after blade comment' => ["<p>x</p>\n{{-- note --}} @if (\$x) a @endif\n<p>y</p>\n"],
    'opener after php tag' => ["<p>x</p>\n<?php echo 1; ?> @if (\$x) a @endif\n<p>y</p>\n"],
    'closer after escaped echo' => ["<p>x</p>\n\n@if (\$x)\n    a\n{{ \$label }} @endif\n<p>y</p>\n"],
    'closer after raw echo' => ["<p>x</p>\n\n@if (\$x)\n    a\n{!! \$label !!} @endif\n<p>y</p>\n"],
    'closer after blade comment' => ["<p>x</p>\n\n@if (\$x)\n    a\n{{-- note --}} @endif\n<p>y</p>\n"],
    'closer after php tag' => ["<p>x</p>\n\n@if (\$x)\n    a\n<?php echo 1; ?> @endif\n<p>y</p>\n"],
]);

it('treats a middle directive as no parent of the block after it', function (
    string $opener,
    string $middle,
    string $closer
): void {
    $source = "<p>a</p>\n\n{$opener}\nx\n{$middle}\n@foreach (\$b as \$c)\ny\n@endforeach\n\n{$closer}\n";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([
        ['line' => 6, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
    ]);
})->with([
    'else' => ['@if ($a)', '@else', '@endif'],
    'empty' => ['@forelse ($items as $item)', '@empty', '@endforelse'],
]);

it('closes a section-missing block with endif', function (): void {
    $source = "<p>a</p>\n@sectionMissing('x')\n<i>b</i>\n@endif\n<p>c</p>\n";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([
        ['line' => 2, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 4, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ]);
});

it('closes a section with each of its closing directives', function (string $closer): void {
    $source = "<p>x</p>\n@section('a')\n<b>y</b>\n@{$closer}\n<p>z</p>\n";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([
        ['line' => 2, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
        ['line' => 4, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
    ]);
})->with(['show', 'stop', 'append', 'overwrite', 'endsection']);

it('pairs a closer with the innermost open block of the same name', function (): void {
    $source = "<p>a</p>\n\n@if (\$a)\n    @if (\$b)\n        x\n    @endif\n    y\n"
        . "    @if (\$c)\n        z\n    @endif\n@endif\n\n<p>c</p>\n";

    expect(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], stageSource($source))))->toBe([
        ['line' => 6, 'column' => 1, 'source' => BLADE_BLANK_AFTER],
        ['line' => 8, 'column' => 1, 'source' => BLADE_BLANK_BEFORE],
    ]);
});

it('reports nothing when the masking pattern cannot run', function (): void {
    $path = stageSource("<p>x</p>\n@if (\$a)\n    a\n@endif\n<p>y</p>\n");

    $violations = PregFailure::during(
            'preg_replace_callback',
            static fn (): array => violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], $path))
        );

    expect($violations)->toBe([])
        ->and(violationTuples(analyzeWithSniffs([BLADE_DIRECTIVE_BLANK_LINES], $path)))->not->toBe([]);
});
