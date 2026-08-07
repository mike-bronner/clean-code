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

/**
 * The two violation sources the PSR12-clean dataset below pins repeatedly.
 * allViolationSourcesByLine() sorts each line's sources, and 'CleanCode…' sorts
 * before 'VariableAnalysis…', so ACCESSOR always precedes UNDEFINED on a line
 * carrying both.
 */
const ACCESSOR = 'CleanCode.Arrays.ArrayAccessors.DirectPropertyAccess';

const UNDEFINED = 'VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable';

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
    'inline HTML mixed with a class declaration' => ['mixed-html.php', [2 => 1], [1 => 1]],
    // The four opening-tag fixtures below are additionally pinned by source in
    // the next test. Both assertions are load-bearing: this one is the only
    // place the error-vs-warning split is asserted (alternative-php-tags.php
    // reports the ASP tag as a warning and the script tag as an error, and
    // allViolationSourcesByLine() merges the two lists), and the next one is
    // the only place the emitting sniff is named.
    'short open tag' => ['short-open-tag.php', [1 => 1], []],
    'alternative PHP tags' => ['alternative-php-tags.php', [2 => 1], [1 => 1]],
    'trailing closing tag in a pure-PHP file' => ['closing-tag.php', [9 => 1], []],
    'code sharing the opening tag line' => ['open-tag-not-alone.php', [1 => 2], []],
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

/**
 * The four opening-tag sniffs the master ruleset activates through
 * <rule ref="PSR12"/> without naming them. No other fixture in the suite
 * reports any of the four, so before these an <exclude> slipped into rules.xml
 * would have disabled opening-tag enforcement silently.
 *
 * These assert the violation *source*, not just a per-line count: each fixture
 * is shaped to report nothing but its own concern, so a count alone would still
 * pass if the named sniff went away and some other sniff started reporting on
 * the same line.
 */
it('pins the PSR opening-tag sniffs', function (string $fixture, array $expected) use ($integrationFixture): void {
    $file = $integrationFixture($fixture);

    expect(allViolationSourcesByLine($file))->toBe($expected, 'Violations in ' . $fixture);
})->with([
    // Only a bare `<?` is reported. `<?=` is valid PHP regardless of the
    // short_open_tag ini setting, so the sniff leaves it alone — a `<?=`
    // fixture here would assert nothing.
    'short open tag' => [
        'short-open-tag.php',
        [1 => ['Generic.PHP.DisallowShortOpenTag.Found']],
    ],
    // Both codes the sniff emits. `Maybe…` is the ASP-tag branch: asp_tags was
    // removed in PHP 7, so the sniff can only report the tag as a probable one.
    'alternative PHP tags' => [
        'alternative-php-tags.php',
        [
            1 => ['Generic.PHP.DisallowAlternativePHPTags.MaybeASPOpenTagFound'],
            2 => ['Generic.PHP.DisallowAlternativePHPTags.ScriptOpenTagFound'],
        ],
    ],
    'trailing closing tag in a pure-PHP file' => [
        'closing-tag.php',
        [9 => ['PSR2.Files.ClosingTag.NotAllowed']],
    ],
    // Not one of the three sniffs the issue named, but the same case: an
    // opening-tag sniff PSR12 pulls in implicitly, reachable and otherwise
    // unpinned. Code on the opening-tag line always breaks the header-spacing
    // rule too, so both sources are expected here.
    'code sharing the opening tag line' => [
        'open-tag-not-alone.php',
        [
            1 => [
                'PSR12.Files.FileHeader.SpacingAfterBlock',
                'PSR12.Files.OpenTag.NotAlone',
            ],
        ],
    ],
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
    // The undefined-variable rule (#85) reports here too, and correctly: this
    // fixture is bare procedural code that reads $user, $items, $first,
    // $second, $range, $prop, $name, $obj, $builder and $this without ever
    // assigning them. It is the fixer's own output for failing.php, byte-
    // compared by tests/Contract/SniffContractTest.php, so it cannot be seeded
    // with assignments the way tests/Integration/fixtures/operator-rules.php
    // is — the seeding would have to land identically in failing.php and shift
    // every line pinned in tests/Standards/OneThoughtPerLineTest.php. Pinning
    // each report instead keeps the fixture untouched and still fails on any
    // *new* violation, which is what this test is for.
    'one-thought-per-line chain style is PSR12-clean' => [
        fixturePath('OneThoughtPerLineSniff', 'autofixed.php'),
        [
            3 => [ACCESSOR, UNDEFINED],
            4 => [ACCESSOR, UNDEFINED],
            9 => [ACCESSOR, UNDEFINED, UNDEFINED],
            10 => [UNDEFINED],
            11 => [ACCESSOR, ACCESSOR],
            12 => [ACCESSOR, ACCESSOR, UNDEFINED, UNDEFINED],
            20 => [UNDEFINED],
            23 => [UNDEFINED],
            25 => [ACCESSOR, UNDEFINED],
            30 => [ACCESSOR, UNDEFINED],
            38 => [UNDEFINED],
            41 => [ACCESSOR, UNDEFINED],
            45 => [UNDEFINED, UNDEFINED],
            47 => [UNDEFINED],
            49 => [UNDEFINED, UNDEFINED],
            52 => [UNDEFINED],
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
