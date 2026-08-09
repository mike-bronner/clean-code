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

/**
 * The missing-import rule (#84) reports on the two exception fixtures below.
 * Both are namespace-less files full of fully qualified exception names, so
 * nearly every catch and throw in them trips it. 'PSR1…' sorts before
 * 'SlevomatCodingStandard…', which is why the one line carrying both lists the
 * PSR1 source first.
 */
const INLINE_FQN = 'SlevomatCodingStandard.Namespaces.ReferenceUsedNamesOnly.ReferenceViaFullyQualifiedName';

const INLINE_FQN_NO_NAMESPACE = INLINE_FQN . 'WithoutNamespace';

$integrationFixture = static fn (string $fixture) => analyzeWithMasterRuleset(
    __DIR__ . '/fixtures/' . $fixture
);

/**
 * Generic.PHP.DisallowShortOpenTag reports `<?` in one of two ways, and which
 * one is decided by the runtime short_open_tag setting: its register() listens
 * for T_OPEN_TAG when the setting is on, and for T_INLINE_HTML when it is off.
 *
 * - on  — `<?` is a real opening tag, reported as a Found *error*.
 * - off — `<?` is inline HTML, reported as a PossibleFound *warning*, and only
 *         when the file also holds a matching `?>` (hence the closer in
 *         short-open-tag.php; without it the sniff stays silent entirely).
 *
 * PHP defaults the setting to off and CI leaves it there, but a local php.ini
 * turning it on is common enough that assuming either way would make this
 * fixture pass for the wrong reason on half the machines that run it. Both
 * branches pin the same sniff on the same line, so neither is a soft assertion.
 */
$shortOpenTagIsOn = (bool) ini_get('short_open_tag');

$shortOpenTagSource = $shortOpenTagIsOn === true
    ? 'Generic.PHP.DisallowShortOpenTag.Found'
    : 'Generic.PHP.DisallowShortOpenTag.PossibleFound';

it('reports the expected violations', function (
    string $fixture,
    array $expectedErrors,
    array $expectedWarnings
) use ($integrationFixture): void {
    $file = $integrationFixture($fixture);

    expect(violationCountsByLine($file->getErrors()))->toBe($expectedErrors, 'Errors in ' . $fixture)
        ->and(violationCountsByLine($file->getWarnings()))->toBe($expectedWarnings, 'Warnings in ' . $fixture);
})->with([
    // compliant.php raises no *error* from the whole ruleset. Its warnings are
    // the guard clause on line 21 — AvoidConditionals (#12) warns once per
    // branch, guard clauses included — and the `2` and `3` of the `[1, 2, 3]`
    // literal on line 25, which DisallowMagicNumbers (#136) reads as two
    // unnamed numbers (`1` is on that sniff's shipped ignore list). So "clean
    // PSR-12 code", "free of conditionals", and "free of magic numbers" are
    // now three different claims. Recorded rather than edited away — rewriting
    // the fixture to dodge the warnings would hide the most visible
    // consequence of adding those sniffs to the master ruleset.
    'compliant class produces no errors' => ['compliant.php', [], [21 => 1, 25 => 2]],
    'compliant abstract class produces zero violations' => ['compliant-abstract.php', [], []],
    'side effects mixed with declarations' => ['side-effects.php', [], [1 => 1]],
    'inline HTML mixed with a class declaration' => ['mixed-html.php', [2 => 1], [1 => 1]],
    // The four opening-tag fixtures below are additionally pinned by source in
    // the next test. Both assertions are load-bearing: this one is the only
    // place the error-vs-warning split is asserted (alternative-php-tags.php
    // reports the ASP tag as a warning and the script tag as an error, and
    // allViolationSourcesByLine() merges the two lists), and the next one is
    // the only place the emitting sniff is named.
    // Error when short_open_tag is on, warning when it is off — see the note
    // above $shortOpenTagIsOn. Either way it is one report on line 1.
    'short open tag' => [
        'short-open-tag.php',
        $shortOpenTagIsOn === true ? [1 => 1] : [],
        $shortOpenTagIsOn === true ? [] : [1 => 1],
    ],
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
    // The line-10 warning is DisallowMagicNumbers (#136) on that fixture's
    // `$tabbed = 2;`, sitting alongside the indentation error the line exists
    // to trip. Line 9 assigns `1`, which is on the sniff's ignore list, so the
    // two visually identical lines report differently.
    'incorrect and tab indentation' => ['indentation.php', [9 => 1, 10 => 1], [10 => 1]],
    'braces not on their required lines' => ['braces.php', [5 => 1, 6 => 1], []],
    // The line-9 warning is AvoidConditionals on that fixture's `if`, sitting
    // alongside the two PSR-12 errors the fixture exists to trip.
    // 12 => 1 is the else branch this fixture uses to exercise PSR-12's brace
    // placement. The PHPMD ElseExpression replacement (#77) reports every else,
    // so the two standards now both speak about this fixture: PSR-12 about
    // where the keyword sits (line 11), CleanCode.Conditionals.DisallowElse
    // about the branch existing at all (line 12). Not a conflict — the fixture
    // keeps its else because moving it would stop exercising brace placement,
    // and its `.fixed.php` counterpart is unaffected (the else sniff has no
    // fixer).
    'malformed control structures' => ['control-structures.php', [9 => 2, 11 => 1, 12 => 1], [9 => 1]],
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
        [1 => [$shortOpenTagSource]],
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
    // The missing-import rule (#84) reports on both exception fixtures below,
    // and correctly: neither declares a namespace, and both write every
    // exception name out fully (`catch (\RuntimeException $e)`), which is the
    // defect that rule exists to catch. In a namespace-less file its remedy is
    // to drop the leading backslash — nothing PSR12 disagrees with, so this is
    // not a conflict to carve out of the PSR12 reference.
    //
    // The reports are pinned per line rather than edited out of the fixtures,
    // for the same reason as the one-thought-per-line entry above: both files
    // are their sniff's own fixer output, byte-compared by
    // tests/Contract/SniffContractTest.php, so dropping the backslashes here
    // would mean dropping them from the matching failing.php and re-pinning
    // every line in tests/Standards/ReferenceThrowableOnlyTest.php and
    // tests/Standards/RequireNonCapturingCatchTest.php. Pinning each report
    // leaves the fixtures untouched and still fails on any *new* violation.
    'throwable-only catches are PSR12-clean' => [
        fixturePath('ReferenceThrowableOnlySniff', 'autofixed.php'),
        [
            1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
            6 => [INLINE_FQN_NO_NAMESPACE],
            13 => [INLINE_FQN_NO_NAMESPACE],
            20 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            27 => [INLINE_FQN_NO_NAMESPACE],
            // A namespaced exception name, so the sniff asks for a use
            // statement here instead of just dropping the backslash.
            34 => [INLINE_FQN],
            41 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            49 => [INLINE_FQN_NO_NAMESPACE],
            51 => [INLINE_FQN_NO_NAMESPACE],
            56 => [INLINE_FQN_NO_NAMESPACE],
            62 => [INLINE_FQN_NO_NAMESPACE],
            65 => [INLINE_FQN_NO_NAMESPACE],
            73 => [INLINE_FQN_NO_NAMESPACE],
            79 => ['PSR1.Classes.ClassDeclaration.MissingNamespace', INLINE_FQN_NO_NAMESPACE],
            84 => [INLINE_FQN_NO_NAMESPACE],
        ],
    ],
    'non-capturing catches are PSR12-clean' => [
        fixturePath('RequireNonCapturingCatchSniff', 'autofixed.php'),
        [
            1 => ['PSR1.Files.SideEffects.FoundWithSymbols'],
            6 => [INLINE_FQN_NO_NAMESPACE],
            13 => [INLINE_FQN_NO_NAMESPACE],
            20 => [INLINE_FQN_NO_NAMESPACE],
            27 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            34 => [INLINE_FQN_NO_NAMESPACE, INLINE_FQN_NO_NAMESPACE],
            42 => [INLINE_FQN_NO_NAMESPACE],
            45 => [INLINE_FQN_NO_NAMESPACE],
            53 => [INLINE_FQN_NO_NAMESPACE],
            61 => [INLINE_FQN_NO_NAMESPACE],
            69 => [INLINE_FQN_NO_NAMESPACE],
            78 => [INLINE_FQN_NO_NAMESPACE],
            88 => [INLINE_FQN_NO_NAMESPACE],
            97 => [INLINE_FQN_NO_NAMESPACE],
        ],
    ],
]);
