<?php

/**
 * Tests the custom CleanCode.Classes.TooManyPublicMethods sniff (PHPMD CodeSize
 * TooManyPublicMethods, #83). Fixtures live in
 * tests/fixtures/TooManyPublicMethodsSniff/, and the mapping is documented in
 * docs/phpmd/codesize-toomanypublicmethods.md.
 *
 * Every expectation below was cross-checked against a live PHPMD 2.15.0 run
 * over the same fixture files, because the point of the sniff is that `phpmd`
 * no longer has to run for this rule. PHPMD reports the same five classes on
 * failing.php (lines 13, 32, 51, 69, 88, each at eleven public methods), the
 * same single class on configured.php (line 14), and nothing at all on
 * passing.php. The mapping is exact in both directions: no PHPMD finding is
 * lost and none is invented.
 *
 * The rule is detection-only. Splitting a class into finer-grained objects is a
 * design change with no mechanical rewrite, so there is no autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const TOO_MANY_PUBLIC_METHODS = 'CleanCode.Classes.TooManyPublicMethods';

const TOO_MANY_PUBLIC_METHODS_ERROR = TOO_MANY_PUBLIC_METHODS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_PUBLIC_METHODS);
});

/**
 * The compliant fixture is built entirely out of near misses, each one a shape
 * a slightly wrong implementation would report:
 *
 * - `TenPublicMethods` sits exactly on the threshold. PHPMD reports only when
 *   the count is *strictly* greater than maxmethods, so relaxing the sniff's
 *   `<=` to `<` reports it.
 * - `TenPublicAmongTwenty` adds five protected and five private methods.
 *   Counting any non-public declaration takes it to twenty.
 * - `TenAfterTheIgnorePattern` holds fifteen public methods, one per
 *   alternative of the default ignore pattern (set, get, is, has, with) on top
 *   of ten plain ones. Dropping any single alternative from the default reports
 *   it, so the whole pattern is pinned rather than its get/set half.
 * - the interface, the trait, the enum, and the anonymous class each declare
 *   fifteen public methods. PHPMD's rule implements ClassAware and nothing
 *   else, so PDepend never hands it any of the four; adding T_INTERFACE,
 *   T_TRAIT, T_ENUM, or T_ANON_CLASS to register() reports whichever was added.
 * - `TenWithNestedDeclarations` hides a named function and a twelve-method
 *   anonymous class inside one of its ten methods. Searching for any enclosing
 *   class instead of the innermost enclosing scope pulls all thirteen in.
 * - `EightDeclaredOfTwentyFour` declares eight, inherits eight, and imports
 *   eight from a trait. Resolving either of the borrowed sets reports it —
 *   and PDepend cannot resolve them either, which is what keeps this a
 *   single-file check in both tools.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Each of the five classes is one public method past the default threshold, and
 * each is reported once at its own `class` keyword — column 10 on line 51
 * because `abstract` precedes it there.
 *
 * - line 13, eleven plain public methods.
 * - line 32, a constructor plus ten others. PHPMD's default pattern exempts
 *   accessor prefixes only, so `__construct` counts in both tools.
 * - line 51, five public static and six public abstract methods; neither
 *   modifier changes visibility, and the abstract half also proves the count
 *   does not need a body to scan.
 * - line 69, eleven methods declared with no visibility modifier — PHP and
 *   PDepend both read those as public.
 * - line 88, sixteen public methods of which the default pattern exempts five.
 */
it('flags every class past the threshold in the failing fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 32, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 51, 'column' => 10, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 69, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 88, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ]);
});

/**
 * The message carries the class, the count it reached, and the threshold it
 * passed, so a report over a whole codebase says which class to open and by how
 * much it is over. The wording follows PHPMD's own message for the rule.
 */
it('names the class, the count, and the threshold in the message', function (): void {
    $errors = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'failing.php')->getErrors();

    expect($errors[13][1][0]['message'])
        ->toContain('The class ElevenPublicMethods has 11 public methods')
        ->toContain('under 10');
});

/**
 * Pins the detection-only decision: splitting a class into finer-grained
 * objects is a design change, not a mechanical rewrite, so no violation is
 * offered to the fixer. PHPMD has no fix for this rule either.
 *
 * The severity is error, matching PHPMD, where a TooManyPublicMethods violation
 * fails the run. Reported as a warning, `phpcs` would exit 0 and `phpmd` would
 * still have to run for this rule.
 */
it('reports detection-only errors', function (): void {
    $file = analyzeFixture(TOO_MANY_PUBLIC_METHODS, 'failing.php');

    expect($file->getErrorCount())->toBe(5)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * The unconfigured baseline the property tests below are measured against.
 * Without it, a property value that silenced the sniff outright would be
 * indistinguishable from a working exemption.
 *
 * Only `AlwaysReported` (eleven plain public methods) is over the threshold.
 * `ExemptedToTheBoundary` holds fourteen public methods of which the default
 * pattern exempts four, landing exactly on ten; `FourPublicMethods` holds four.
 */
it('reports at PHPMD\'s default threshold of ten', function (): void {
    $file = analyzeFixtureWithRulesetProperties(TOO_MANY_PUBLIC_METHODS, 'configured.php', []);

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ]);
});

/**
 * `maxmethods` arrives from a ruleset as the string "3" and still compares as a
 * number, which is what makes the property configurable at all: PHP_CodeSniffer
 * hands every `<property>` value over as a string.
 *
 * Lowering it to three brings both other classes in, at ten and four public
 * methods. A live PHPMD 2.15.0 run over this fixture with the same property
 * reports the same three classes with the same three counts.
 */
it('applies a lowered maxmethods given as a ruleset string', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        TOO_MANY_PUBLIC_METHODS,
        'configured.php',
        ['maxmethods' => '3']
    );

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 55, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ]);
});

/**
 * The narrower pattern phpmd.org's rule page documents, `(^(set|get))i`, as
 * opposed to the `(^(set|get|is|has|with))i` PHPMD 2.15.0 actually ships and
 * this sniff defaults to.
 *
 * Under it `ExemptedToTheBoundary` keeps only its `getName`/`setName`
 * exemptions and is reported at twelve, so the property really is applied
 * rather than the sniff falling silent — and the behaviour #83's acceptance
 * criteria describe is reachable by configuring one property. A live PHPMD
 * 2.15.0 run over this fixture with the same pattern reports the same two
 * classes at the same two counts.
 */
it('applies the narrower ignore pattern phpmd.org documents', function (): void {
    $file = analyzeFixtureWithRulesetProperties(
        TOO_MANY_PUBLIC_METHODS,
        'configured.php',
        ['ignorepattern' => '(^(set|get))i']
    );

    $errors = $file->getErrors();

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ])->and($errors[34][1][0]['message'])->toContain('has 12 public methods');
});

/**
 * An empty `<property name="ignorepattern" value=""/>` exempts nothing, so all
 * fourteen of `ExemptedToTheBoundary`'s public methods count.
 *
 * Two things are pinned here. First, that the empty value survives at all:
 * PHP_CodeSniffer turns an empty property value into `null` before assigning
 * it, so a non-nullable `string` declaration would abort the entire ruleset
 * parse with a TypeError — verified against a real `phpcs --standard=…` run,
 * which is where the crash showed up. Second, that `null` means "exempt
 * nothing" rather than "exempt everything": the failure direction is to report
 * more, never to switch the rule off quietly.
 *
 * This is also the one place the sniff is configurable where PHPMD is not — an
 * empty value in PHPMD's own XML leaves its default pattern in place, so the
 * count there stays ten. Reporting more than PHPMD under a deliberately chosen
 * setting keeps `phpmd` out of the pipeline; the reverse would not.
 */
it('exempts nothing when the ignore pattern is emptied by a ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(TOO_MANY_PUBLIC_METHODS, 'configured.php', ['ignorepattern' => '']);

    $errors = $file->getErrors();

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ])->and($errors[34][1][0]['message'])->toContain('has 14 public methods');
});

/**
 * The sibling of the test above, on the threshold rather than the pattern: an
 * empty `<property name="maxmethods" value=""/>` also arrives as `null`, and
 * falls back to PHPMD's default of ten rather than to zero.
 *
 * Zero — which is what an unchecked `(int) null` would produce — would report
 * every class in the file, including `FourPublicMethods`; treating null as
 * "no threshold" would report none of them. The fallback is asserted on both
 * halves: the baseline result is unchanged, and the message still names ten.
 */
it('falls back to the default threshold when maxmethods is emptied by a ruleset', function (): void {
    $file = analyzeFixtureWithRulesetProperties(TOO_MANY_PUBLIC_METHODS, 'configured.php', ['maxmethods' => '']);

    $errors = $file->getErrors();

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
    ])->and($errors[14][1][0]['message'])->toContain('under 10');
});

/**
 * A pattern PHP cannot compile exempts nothing rather than everything.
 * `preg_match()` answers `false` on it, and the sniff compares strictly against
 * `1`, so a configuration mistake over-reports instead of silently switching
 * the rule off — the same call PHPMD makes, and the same one the sibling
 * DisallowBooleanArgumentFlag sniff makes.
 *
 * Loosening that comparison to `!== 0` would exempt every method in the file
 * and leave no violation at all. As written, `ExemptedToTheBoundary` is
 * reported at its full fourteen.
 *
 * The failure is forced the only way PHP offers — an uncompilable pattern — and
 * the emitted warning is captured so the run stays quiet *and* so the assertion
 * proves the pattern really did fail to compile rather than merely failing to
 * match.
 */
it('exempts nothing when the ignore pattern is malformed', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    }, E_WARNING);

    try {
        $file = analyzeFixtureWithRulesetProperties(
            TOO_MANY_PUBLIC_METHODS,
            'configured.php',
            ['ignorepattern' => 'not-a-pattern']
        );
    } finally {
        restore_error_handler();
    }

    $errors = $file->getErrors();

    expect($raised)->not->toBeEmpty()
        ->and($raised[0])->toContain('Delimiter must not be alphanumeric')
        ->and(violationTuples($file))->toBe([
            ['line' => 14, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
            ['line' => 34, 'column' => 1, 'source' => TOO_MANY_PUBLIC_METHODS_ERROR],
        ])
        ->and($errors[34][1][0]['message'])->toContain('has 14 public methods');
});
