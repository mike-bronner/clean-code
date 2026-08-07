<?php

/**
 * Integration test for the PSR1/2/12 industry standards wired into rules.xml.
 *
 * Runs the master ruleset against the fixtures in fixtures/ via PHPCS's own
 * API and asserts the exact violations (line => count), mirroring the contract
 * the old AbstractSniffUnitTest harness expressed. Fixtures with a `.fixed.php`
 * counterpart are additionally run through the fixer and compared, verifying
 * phpcbf support.
 *
 * These fixtures stay in tests/Integration/fixtures/ rather than moving to the
 * shared tests/fixtures/ tree: they exercise the ruleset as a whole, so no one
 * sniff owns them.
 */

declare(strict_types=1);

$integrationFixture = static fn (string $fixture) => analyzeWithMasterRuleset(
    __DIR__ . '/fixtures/' . $fixture
);

it('reports the expected violations', function (
    string $fixture,
    array $expectedErrors,
    array $expectedWarnings
) use ($integrationFixture): void {
    $file = $integrationFixture($fixture);

    expect(violationCountsByLine($file->getErrors()))->toBe($expectedErrors, 'Errors in ' . $fixture)
        ->and(violationCountsByLine($file->getWarnings()))->toBe($expectedWarnings, 'Warnings in ' . $fixture);
})->with([
    'compliant class produces zero violations' => ['compliant.php', [], []],
    'compliant abstract class produces zero violations' => ['compliant-abstract.php', [], []],
    'side effects mixed with declarations' => ['side-effects.php', [], [1 => 1]],
    'inline HTML mixed with a class declaration' => ['mixed-html.php', [2 => 1, 4 => 1], [1 => 1]],
    'more than one class per file' => ['multiple-classes.php', [9 => 1], []],
    'class outside a namespace' => ['no-namespace.php', [3 => 1], []],
    // 9 => 3 / 11 => 2 fold in the TypeHints property/return-hint errors the
    // master ruleset now also flags (untyped `var $legacy` and the `run()`
    // return) alongside the PSR12 missing-visibility errors.
    'missing member visibility' => ['visibility.php', [9 => 3, 11 => 2], [7 => 1]],
    // The master ruleset's Line Length rule (#3) overrides PSR-12's soft limit:
    // with absoluteLineLimit=120 a line past 120 chars is an error, not a
    // warning. Fixture line 7 is 124 chars.
    'line exceeding the 120-character hard limit' => ['line-length.php', [7 => 1], []],
    'incorrect and tab indentation' => ['indentation.php', [9 => 1, 10 => 1], []],
    'braces not on their required lines' => ['braces.php', [5 => 1, 6 => 1], []],
    'malformed control structures' => ['control-structures.php', [9 => 2, 11 => 1], []],
]);

it('produces the expected fixer output', function (string $fixture) use ($integrationFixture): void {
    $file = $integrationFixture($fixture . '.php');

    expect(autofixedContents($file))->toBe(
        file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.fixed.php'),
        'Fixer output for ' . $fixture
    );
})->with([
    'indentation is auto-fixable' => ['indentation'],
    'brace placement is auto-fixable' => ['braces'],
    'control structures are auto-fixable' => ['control-structures'],
]);

/**
 * The clean-code standards take precedence over the industry baseline: code
 * shaped by the custom rules' own fixers must not be flagged by the PSR12
 * reference in the master ruleset. The only accepted violations are structural
 * fixture noise (procedural code sharing a file with a class), pinned exactly —
 * so any new PSR12-vs-custom conflict fails here and gets carved out of the
 * PSR12 reference in rules.xml via <exclude>.
 */
it('keeps custom-standard-shaped code PSR12-clean', function (string $path, array $expected): void {
    $file = analyzeWithMasterRuleset($path);

    expect(allViolationSourcesByLine($file))->toBe($expected, 'Violations in ' . basename($path));
})->with([
    // The chains this fixture exists to lay out are, by definition, direct
    // property reads, so the array-accessors standard (#33) flags every one of
    // them. That is the two standards agreeing, not a PSR12 conflict:
    // CleanCode.ClearCode.OneThoughtPerLine governs how a chain is broken
    // across lines, CleanCode.Arrays.ArrayAccessors says the chain should be a
    // data_get() call in the first place. Pinned per line so any *other* new
    // violation still fails here.
    'one-thought-per-line chain style is PSR12-clean' => [
        fixturePath('OneThoughtPerLineSniff', 'autofixed.php'),
        [
            3 => ['CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess'],
            4 => ['CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess'],
            9 => ['CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess'],
            11 => [
                'CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess',
                'CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess',
            ],
            12 => [
                'CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess',
                'CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess',
            ],
            25 => ['CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess'],
            30 => ['CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess'],
            41 => ['CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess'],
        ],
    ],
    'throwable-only catches are PSR12-clean' => [
        fixturePath('ReferenceThrowableOnlySniff', 'autofixed.php'),
        [
            1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
            79 => ['PSR1.Classes.ClassDeclaration.MissingNamespace'],
        ],
    ],
    'non-capturing catches are PSR12-clean' => [
        fixturePath('RequireNonCapturingCatchSniff', 'autofixed.php'),
        [
            1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
        ],
    ],
]);
