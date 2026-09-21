<?php

/**
 * Tests the custom CleanCode.Metrics.CyclomaticComplexity sniff, which
 * replicates PHPMD's CodeSize/CyclomaticComplexity rule
 * (docs/phpmd/codesize-cyclomaticcomplexity.md, #88).
 *
 * Every number below was measured against a live PHPMD 2.15.0 run over these
 * same fixtures rather than derived from the sniff or from phpmd.org. The
 * literal invocation, the version string, and PHPMD's verbatim output on
 * failing.php at the default report level are quoted in **failing.php's own
 * docblock**; the five measurements it reports are:
 *
 *     failing.php:46   atExactlyTheReportLevel()  10
 *     failing.php:93   booleanOperatorChain()     12
 *     failing.php:110  mergesItsClosure()         11
 *     failing.php:152  deeplyNested()             19
 *     failing.php:210  heavyStandalone()          10
 *
 * The measurements *below* the default report level come from a second run of
 * the same binary against a hand-written ruleset referencing
 * `rulesets/codesize.xml/CyclomaticComplexity` with `reportLevel` set to 1,
 * since PHPMD says nothing at all about a declaration it does not report.
 *
 * Three measurements are worth stating outright, because a reader is most
 * likely to assume each of them the other way round:
 *
 * - PHPMD reports a declaration whose complexity is **at or above**
 *   `reportLevel` (`$ccn < $threshold` in PHPMD's own Rule\CyclomaticComplexity),
 *   not strictly above it. So 10 is a violation at the default level of 10, and
 *   9 is not.
 * - A closure or arrow function is **not** measured in its own right. PDepend
 *   walks a declaration's whole subtree, so an inline closure's decision points
 *   are scored against the method that holds it, and the closure is never
 *   reported under a name of its own. #88's acceptance criteria expected the
 *   opposite; the measurement decided it. mergesItsClosure() in failing.php is
 *   the discriminating case — 4 on its own, 11 with its closure.
 * - `match`, `??`, `??=`, `?->`, `xor`, `else`, `default`, `finally`, and
 *   `goto` are worth nothing, but a ternary or a boolean operator written
 *   *inside* a match arm still scores. Only the `match` token itself is passed
 *   over, not everything nested under it.
 *
 * The rule is detection-only — the fix is to break the declaration up — so
 * there is no autofixed fixture, and the detection-only test pins that.
 */

declare(strict_types=1);

const CYCLOMATIC_COMPLEXITY = 'CleanCode.Metrics.CyclomaticComplexity';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(CYCLOMATIC_COMPLEXITY);
});

/**
 * The rule as a consumer meets it: the real vendor/bin/phpcs binary, in its own
 * process, over a violating fixture.
 *
 * Every other test in this file drives PHPCS in process through ConfigDouble,
 * which blanks the CodeSniffer.conf Composer wrote at install time and has the
 * helpers hand the installed standards back. That harness settles what the
 * sniff measures and nothing about whether the shipped package works: it
 * supplies the registration itself, so a package that never registered would
 * pass all the same — and the registration test above reads CleanCode/ruleset.xml through
 * exactly that scaffolding.
 *
 * This one uses none of it, and names both identifiers a consumer can point
 * `--standard` at, because each fails for a reason the other cannot catch.
 * Both were confirmed by mutation, not reasoned about:
 *
 * - `CleanCode/ruleset.xml` — the ruleset's own path, which resolves with no
 *   installed_paths entry at all. That is what it adds: deleting the entry
 *   leaves this case green and reddens the one below, so the two cannot both
 *   be satisfied by a broken install.
 * - `CleanCode` — the standard by *name*, which resolves through the
 *   installed_paths entry dealerdirect/phpcodesniffer-composer-installer writes
 *   on install. Deleting that entry reddens this case alone. It only discriminates
 *   because the helper runs phpcs from outside the package: from the package
 *   root the name resolves as a plain relative path to ./CleanCode/ and the
 *   deletion changes nothing, which is how this test read before it was
 *   mutation-checked.
 *
 * Nothing narrows the run to one sniff, because narrowing is what a consumer
 * does not do; the report is filtered afterwards instead. The five lines and
 * measurements are failing.php's, the same ones the in-process tests below
 * assert and the same ones failing.php's docblock quotes live PHPMD reporting.
 */
it('reports the violation end to end through the installed package', function (string $standard): void {
    $violations = installedPhpcsViolations(
        $standard,
        fixturePath('CyclomaticComplexitySniff', 'failing.php'),
        CYCLOMATIC_COMPLEXITY . '.Found'
    );

    expect(array_column($violations, 'line'))->toBe([46, 93, 110, 152, 210])
        ->and($violations[0]['message'])
        ->toContain('atExactlyTheReportLevel() has a cyclomatic complexity of 10')
        ->and($violations[1]['message'])
        ->toContain('booleanOperatorChain() has a cyclomatic complexity of 12')
        ->and($violations[2]['message'])
        ->toContain('mergesItsClosure() has a cyclomatic complexity of 11')
        ->and($violations[3]['message'])
        ->toContain('deeplyNested() has a cyclomatic complexity of 19')
        ->and($violations[4]['message'])
        ->toContain('heavyStandalone() has a cyclomatic complexity of 10');
})->with([
    'the ruleset path' => fn (): string => cleanCodeRoot() . '/CleanCode/ruleset.xml',
    'the standard by name' => 'CleanCode',
]);

/**
 * The negative control the test above cannot supply for itself, in the shape
 * tests/Contract/ShippedPackageSmokeTest.php gives every swept sniff — which is
 * what holds this sniff's entry in SHIPPED_SMOKE_EXCLUSIONS: the exclusion is
 * only worth having while the coverage it stands in for is the same coverage.
 *
 * A positive assertion on its own cannot tell the shipped package apart from a
 * harness that always reports: both directions through the same route is what
 * makes the pair a statement about the sniff. Silence and status 0 are asserted
 * together for the reason the sweep asserts them together — an empty message
 * list is also what a run that never reached the file produces, and
 * installedPhpcsRun() throwing on an unreadable report is what rules that out.
 *
 * Narrowed to this sniff with --sniffs, unlike the test above, because a status
 * belongs to the run rather than to a sniff. Not a precaution: passing.php read
 * through the whole of CleanCode/ruleset.xml reports 244 messages and exits 2 — PSR-1 and a
 * dozen sibling CleanCode rules, none of them this one — measured, so without
 * the narrowing neither assertion here could be made at all. The standard is
 * still CleanCode/ruleset.xml, still resolved from outside the package.
 */
it('stays silent on its compliant fixture through the installed package', function (): void {
    $run = installedSniffFixtureRun(CYCLOMATIC_COMPLEXITY, 'passing.php');

    expect($run['messages'])->toBe([])
        ->and($run['status'])->toBe(0);
});

/**
 * passing.php carries the near miss the report level has to stay silent on —
 * atOneBelowTheReportLevel() measures exactly 9 against a level of 10 — next to
 * a top-level closure and arrow function holding twelve decision points each,
 * which the sniff must not register on at all. Every other declaration in the
 * file pins one counting rule below the level.
 *
 * Silence alone is a weak verdict here, so the measurement test below pins each
 * declaration's exact count; this one pins that the default level reports
 * nothing.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The counting rules themselves, pinned as exact numbers rather than as a
 * verdict against a threshold. Dropping the report level to 1 makes the sniff
 * report every declaration it measures, so the message carries each measurement
 * out where a test can read it — which is the only way any single counting rule
 * becomes discriminating on its own.
 *
 * Line by line, and what each one would break if it moved:
 *
 * - describe(), 1 — an interface method, no body. PHPMD skips interfaces
 *   outright (PDepend's visitInterface is empty), so this is the sniff's one
 *   silent superset: measuring 1, it can never be reported at a usable level.
 * - baseline(), 1, and drawn(), 1 — the base every declaration carries, once
 *   with an empty body and once with no body at all.
 * - atOneBelowTheReportLevel(), 9 — eight ifs. The near miss.
 * - booleanChainRemoved(), 2 — the control for failing.php's ten-`&&` chain.
 * - uncounted(), 2 — `??`, `??=`, `?->`, `xor`, a four-armed `match`,
 *   `finally`, and `goto` between them are worth nothing; only the `if`
 *   guarding the goto scores. Counting any one of those kinds raises this.
 * - elseAndDefaultOnly(), 2 — one if, one else, one bare `default`. The else
 *   and the default are worth nothing, so counting either makes this 3 or 4.
 * - nestedInsideArms(), 3 — a `match` scores nothing, but the `&&` in its first
 *   arm and the ternary in its second each score. Skipping everything nested
 *   under `match` rather than the token alone reports 1.
 * - withDefault(), 3 — two cases plus a `default` in the same switch. Counting
 *   `default` makes this 4.
 * - stackedLabels(), 4 — three case labels, two of them sharing one body.
 *   Counting bodies rather than labels reports 3.
 * - wordForms(), 3 — `and` and `or` score, `xor` does not. `xor` was not
 *   measured during triage, so it is measured here: PDepend has
 *   visitLogicalAndExpression and visitLogicalOrExpression and no visitor for
 *   xor, and PHPMD 2.15.0 scores this method 3.
 * - ternaries(), 4 — one full ternary and two short ones, 1 each.
 * - everyLoopAndCatch(), 7 — for, foreach, while, the while of a `do … while`,
 *   and two catches, one of them multi-type. Counting `do` alongside its
 *   `while` makes this 8; scoring a multi-type catch per type makes it 8 too.
 * - elseIfSpellings(), 4 — an if, an `else if`, and an `elseif`. Both spellings
 *   are worth the same and the trailing `else` is worth nothing.
 * - hostsInlineFunctions(), 5 — the method's own if, the closure's if and `&&`,
 *   and the arrow function's ternary. Splitting either inline declaration out
 *   lowers this, and the closure and arrow function are not reported separately.
 * - hostsNamedFunction(), 2, and nested(), 9 — a named function declared inside
 *   a method is its own artifact: its eight ifs never reach the method, and it
 *   is measured and reported in its own right.
 * - makes(), 3, __construct(), 1, and inner(), 3 — an anonymous class's
 *   constructor *arguments* are expressions in the enclosing method (the
 *   ternary and the `&&` there make makes() 3), while its *body* is not.
 *   PHPMD reports neither of the anonymous class's methods; this sniff does,
 *   which is the superset divergences.php pins at a reportable count.
 * - measure(), 1 — ternaries in a parameter, property, and constant default all
 *   sit outside the body and score nothing.
 * - withBareBlock(), 2 — an if inside a bare block, the one brace a body can
 *   hold that owns nothing.
 * - fromTrait(), 2, and label(), 2 — a trait's and an enum's methods are
 *   measured like any other.
 * - standalone(), 3 — a plain function, reported in its own right.
 *
 * The declarations absent from this list matter as much as the ones in it: the
 * file's top-level closure and arrow function each hold twelve decision points
 * and appear nowhere, because the sniff registers on T_FUNCTION alone.
 */
it('measures each declaration in the compliant fixture exactly', function (): void {
    $file = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'passing.php', 'reportLevel', 1);

    expect(measuredComplexities($file))->toBe([
        'method describe()' => 1,
        'method baseline()' => 1,
        'method drawn()' => 1,
        'method atOneBelowTheReportLevel()' => 9,
        'method booleanChainRemoved()' => 2,
        'method uncounted()' => 2,
        'method elseAndDefaultOnly()' => 2,
        'method nestedInsideArms()' => 3,
        'method withDefault()' => 3,
        'method stackedLabels()' => 4,
        'method wordForms()' => 3,
        'method ternaries()' => 4,
        'method everyLoopAndCatch()' => 7,
        'method elseIfSpellings()' => 4,
        'method hostsInlineFunctions()' => 5,
        'method hostsNamedFunction()' => 2,
        'function nested()' => 9,
        'method makes()' => 3,
        'method __construct()' => 1,
        'method inner()' => 3,
        'method measure()' => 1,
        'method withBareBlock()' => 2,
        'method fromTrait()' => 2,
        'method label()' => 2,
        'function standalone()' => 3,
    ]);
});

it('flags each over-level declaration at its declaration line', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 46, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 93, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 110, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 152, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
        ['line' => 210, 'column' => 1, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The measurement itself, not merely that something was reported. A counting
 * regression that still pushed each declaration over the level would satisfy
 * the tuple assertion above while reporting the wrong number, so the message is
 * read for the exact complexity and the exact report level.
 *
 * atExactlyTheReportLevel() at 10 and heavyStandalone() at 10 are the boundary
 * pair with atOneBelowTheReportLevel() at 9 in passing.php: the first count the
 * default level reports, from both a method and a plain function.
 * booleanOperatorChain() at 12 is the boolean-operator case — one if and ten
 * `&&`, which is 12 rather than 2 only because each operator scores on its own.
 * mergesItsClosure() at 11 is the closure case, and is the reason the file is
 * not merely a list of large declarations: strip its closure out and the method
 * measures 4, which the default level passes over.
 */
it('reports the measured complexity and the report level', function (): void {
    $errors = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'failing.php')->getErrors();

    expect($errors[46][12][0]['message'])
        ->toBe(
            'The method atExactlyTheReportLevel() has a cyclomatic complexity of 10, reaching '
                . 'the report level of 10; break it into smaller declarations '
                . '(see docs/phpmd/codesize-cyclomaticcomplexity.md)'
        )
        ->and($errors[93][12][0]['message'])
        ->toContain('The method booleanOperatorChain() has a cyclomatic complexity of 12,')
        ->and($errors[110][12][0]['message'])
        ->toContain('The method mergesItsClosure() has a cyclomatic complexity of 11,')
        ->and($errors[152][12][0]['message'])
        ->toContain('The method deeplyNested() has a cyclomatic complexity of 19,')
        ->and($errors[210][1][0]['message'])
        ->toContain('The function heavyStandalone() has a cyclomatic complexity of 10,');
});

/**
 * The one shape where the sniff and PHPMD disagree, kept in its own fixture so
 * neither floor fixture claims parity it does not have. PDepend never surfaces
 * an anonymous class's methods to a MethodAware rule, so a live PHPMD 2.15.0
 * run over divergences.php at `reportLevel` 1 reports only `makes()` at 1 and
 * says nothing at all about heavy(), whatever its complexity.
 *
 * The sniff reports heavy() at 11. That keeps it a superset of PHPMD — never
 * looser, which is the one thing this mapping must not be — and suppressing it
 * would mean writing code to hide a true defect.
 */
it('reports an anonymous class method PHPMD passes over', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 20, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ])->and(measuredComplexities($file))->toBe(['method heavy()' => 11]);
});

/**
 * The `reportLevel` property, exercised from the configured side. modest()
 * measures 5 and quiet() measures 4, so a level of 5 reports the first and not
 * the second, and a level of 6 reports neither — the same at-or-above
 * comparison the default makes, proved at a value the fixture states exactly.
 */
it('reports at the configured level and stays silent one above it', function (): void {
    $atLevel = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'configured.php', 'reportLevel', 5);
    $aboveLevel = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'configured.php', 'reportLevel', 6);

    expect(violationTuples($atLevel))->toBe([
        ['line' => 16, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ])->and($aboveLevel->getErrors())->toBe([]);
});

/**
 * The same level as a consuming ruleset actually supplies it: PHPCS hands a
 * `<property>` value to a sniff as the raw string from the XML, never as an
 * int. This is not a stylistic variation of the test above — it is the path a
 * real ruleset takes, and the reason the property is not declared `int`.
 */
it('accepts the report level as the string a ruleset supplies', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        CYCLOMATIC_COMPLEXITY,
        'configured.php',
        ['reportLevel' => '5']
    );

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 12, 'source' => CYCLOMATIC_COMPLEXITY . '.Found'],
    ]);
});

/**
 * A `reportLevel` that is not a usable positive number falls back to PHPMD's
 * own default rather than being used as written. Each case below is a real
 * shape a ruleset can produce:
 *
 * - `value="ten"` — a typo. Used as written it would compare an int against a
 *   non-numeric string.
 * - `value=""` — PHPCS rewrites an empty value to null before assigning it,
 *   which is why the property's type admits null at all.
 * - `value="0"` and `value="-1"` — a level of zero or below would report every
 *   declaration in a codebase, including a one-line getter.
 *
 * All four therefore behave exactly like the untouched default: failing.php's
 * five over-level declarations and nothing from configured.php, whose two
 * methods measure 5 and 4.
 */
it('falls back to the default level on an unusable configured value', function (mixed $value): void {
    $failing = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'failing.php', 'reportLevel', $value);
    $configured = analyzeFixtureWithProperty(CYCLOMATIC_COMPLEXITY, 'configured.php', 'reportLevel', $value);

    expect(violationTuples($failing))->toHaveCount(5)
        ->and($configured->getErrors())->toBe([]);
})->with([['ten'], [''], [null], ['0'], ['-1']]);

/**
 * The same fallback down the ruleset-XML path, where an empty `<property>`
 * element arrives as null. A native `int` property would abort the whole phpcs
 * run here with a TypeError before a single file was scanned, so this case is
 * what makes the property's type load-bearing rather than decorative.
 */
it('survives an empty report-level property from a ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        CYCLOMATIC_COMPLEXITY,
        'configured.php',
        ['reportLevel' => '']
    );

    expect($file->getErrors())->toBe([]);
});

/**
 * The default the sniff ships with, read off the class rather than inferred
 * from a fixture. It is what makes an existing PHPMD configuration transfer
 * verbatim, so it is pinned in its own right.
 */
it('defaults the report level to PHPMD\'s own 10', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[CYCLOMATIC_COMPLEXITY]];

    expect($sniff->reportLevel)->toBe(10);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(CYCLOMATIC_COMPLEXITY, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The cross-check against real source rather than against fixtures this PR
 * authored: fixtures hand-built alongside a sniff can agree with it and with
 * nothing else. CleanCode/Sniffs/Functions/DisallowBooleanArgumentFlagSniff.php
 * is a file that already existed, was written for another purpose entirely, and
 * carries nine declarations spanning complexities 1 to 7.
 *
 * A live PHPMD 2.15.0 run over that file at `reportLevel` 1 reports:
 *
 *     register() 1, process() 7, isIgnoredName() 3, isExceptedClass() 1,
 *     enclosingClassName() 3, describe() 1, isBooleanFlag() 3,
 *     isBooleanType() 1, isBooleanDefault() 1
 *
 * which is exactly the map below, in that order. Asserted by declaration name
 * rather than by line so that reformatting the sniff cannot redden this test
 * for a reason that has nothing to do with the measurement; a real change to
 * that file's branching is supposed to redden it.
 */
it('matches PHPMD on a real source file in this repository', function (): void {
    $file = analyzeWithSniffs(
        [CYCLOMATIC_COMPLEXITY],
        cleanCodeRoot() . '/CleanCode/Sniffs/Functions/DisallowBooleanArgumentFlagSniff.php',
        static function (object $sniff): void {
            $sniff->reportLevel = 1;
        }
    );

    expect(measuredComplexities($file))->toBe([
        'method register()' => 1,
        'method process()' => 7,
        'method isIgnoredName()' => 3,
        'method isExceptedClass()' => 1,
        'method enclosingClassName()' => 3,
        'method describe()' => 1,
        'method isBooleanFlag()' => 3,
        'method isBooleanType()' => 1,
        'method isBooleanDefault()' => 1,
    ]);
});
