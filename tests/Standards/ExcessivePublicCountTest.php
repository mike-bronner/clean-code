<?php

/**
 * CleanCode.Metrics.ExcessivePublicCount — PHPMD's CodeSize/ExcessivePublicCount.
 *
 * Every claim this file makes about PHPMD was measured against a live PHPMD
 * 2.15.0 run over these same fixtures, not read off phpmd.org. The two places
 * the measurement contradicts a plausible reading of the rule are pinned here:
 *
 *   - The threshold is *inclusive*. PHPMD's rule class returns early only when
 *     `$cis < $threshold`, so a type with exactly `minimum` public members is
 *     already reported. `boundaries.php` pins 44/45/46 against PHPMD's own
 *     measured output on that file.
 *   - Interfaces are never checked. PHPMD's rule is declared
 *     `implements ClassAware, TraitAware`, and PDepend's ClassLevelAnalyzer
 *     leaves visitInterface() empty with the comment "we don't want interface
 *     metrics". `passing.php` carries a 50-method interface for that.
 *
 * docs/phpmd/codesize-excessivepubliccount.md records the full mapping and the
 * measured PHPMD output for each fixture.
 */

declare(strict_types=1);

const EXCESSIVE_PUBLIC_COUNT = 'CleanCode.Metrics.ExcessivePublicCount';

const EXCESSIVE_PUBLIC_COUNT_ERROR = 'CleanCode.Metrics.ExcessivePublicCount.Found';

it('resolves through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_PUBLIC_COUNT);
});

/**
 * The three violating shapes, each reported once on its own declaration line:
 * a class at the threshold, a class reaching it through a mix of methods and
 * properties, and a trait. PHPMD 2.15.0 reports ExcessivePublicCount on this
 * file at exactly lines 14, 198, and 308.
 */
it('flags a class or trait whose public surface reaches the threshold', function (): void {
    expect(violationTuples(analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'failing.php')))->toBe([
        ['line' => 14, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 198, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 308, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

/**
 * The count belongs in the message: a metric rule that only says "too many"
 * makes the reader re-count by hand. The threshold is quoted alongside it, the
 * way PHPMD quotes both.
 */
it('names the type, its public count, and the threshold', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'failing.php')->getErrors();

    expect($errors[14][1][0]['message'])->toBe(
        'The class AtTheThreshold has 45 public methods and attributes.'
            . ' Consider reducing the number of public items to less than 45'
    );
    expect($errors[308][1][0]['message'])->toBe(
        'The trait WideTrait has 45 public methods and attributes.'
            . ' Consider reducing the number of public items to less than 45'
    );
});

/**
 * The inclusive boundary, in one file: 44 public members is silent, 45 is a
 * violation, 46 is a violation. A sniff that read the threshold exclusively
 * ("more than 45") would report only line 377 and pass every other assertion
 * in this file.
 *
 * PHPMD 2.15.0 over this same fixture reports ExcessivePublicCount on lines 193
 * and 377 and says nothing about the 44-member class on line 13.
 */
it('treats the threshold as inclusive', function (): void {
    expect(violationTuples(analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'boundaries.php')))->toBe([
        ['line' => 193, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 377, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

/**
 * What the compliant fixture is silent *about*, rather than merely that it is
 * silent. Dropping the threshold to 44 makes every type in passing.php whose
 * public count is 44 speak up, and leaves the rest quiet — so this one
 * assertion pins the exact count of every shape in the file at once:
 *
 *   line 16    JustUnderTheThreshold        44, one below the default
 *   line 196   JustUnderTheThresholdTrait   44, and traits are checked
 *   line 1063  the anonymous class          44, counted as its own scope
 *
 * and, by their absence:
 *
 *   WideInterface (50 public methods)        interfaces are never checked
 *   WideEnum (50 public methods)             nor are enums
 *   ConstantsAreNotAttributes (50 consts)    a constant is not an attribute
 *   MostlyHidden (100 non-public members)    visibility is what is counted
 *   LocalsAreNotProperties (50 locals)       a local is not a property
 *   HostOfANarrowAnonymousClass              its nested members are not its own
 *
 * Registering T_INTERFACE or T_ENUM, folding an anonymous class into its host,
 * counting constants, or ignoring visibility each adds a line here.
 */
it('counts only the public members a class or trait declares itself', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'passing.php', static function (object $sniff): void {
        $sniff->minimum = 44;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 16, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 196, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 1063, 'column' => 20, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

/**
 * The three shapes this sniff reports and PHPMD 2.15.0 does not. Running PHPMD
 * over this exact fixture yields no ExcessivePublicCount violation at all —
 * only unrelated ExcessiveParameterList and ExcessiveMethodLength reports.
 *
 * Each gap is PDepend failing to model something, not a decision PHPMD's rule
 * documents, and each hides a genuinely excessive public surface:
 *
 *   line 15   a trait of 45 public properties, which PDepend counts as 0
 *   line 68   a constructor promoting 44 public properties, counted as 1
 *   line 128  an anonymous class of 45 public methods, which PHPMD skips
 *
 * The promoted-property gap matters most here: rules.xml requires
 * SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion, so
 * promotion is this standard's mandated way to declare a public property.
 * Following PDepend would leave the rule blind to the shape the ruleset itself
 * demands.
 */
it('reports the public surface PDepend fails to model', function (): void {
    expect(violationTuples(analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'divergences.php')))->toBe([
        ['line' => 15, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 68, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
        ['line' => 128, 'column' => 20, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

/**
 * An anonymous class has no name to quote, so the message names its kind
 * instead of interpolating an empty string where the name would go.
 */
it('names an anonymous class by its kind', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'divergences.php')->getErrors();

    expect($errors[128][20][0]['message'])->toBe(
        'The anonymous class has 45 public methods and attributes.'
            . ' Consider reducing the number of public items to less than 45'
    );
});

/**
 * `minimum` is PHPMD's own property name and default. Lowering it to 3 reaches
 * the 3-member class and still spares the 2-member one, so the threshold is
 * genuinely read rather than hard-coded — and the inclusive comparison holds at
 * a configured value too, not only at the default.
 */
it('reports at a lowered minimum', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'configured.php', static function (object $sniff): void {
        $sniff->minimum = 3;
    });

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

/**
 * The far side of the same boundary: one above the largest type in the fixture
 * silences the file. Without it, "reports at a lowered minimum" would also pass
 * against a sniff that ignored `minimum` and reported every class it saw.
 */
it('stays silent above the largest public surface in the file', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'configured.php', static function (object $sniff): void {
        $sniff->minimum = 4;
    });

    expect(violationTuples($file))->toBe([]);
});

/**
 * A ruleset always supplies a property as a string — `<property name="minimum"
 * value="3"/>` — so `minimum` has to stay untyped. Declaring it `int` turns
 * this test into a TypeError, which is the mutation that makes it
 * discriminating; the `(int)` cast where it is read normalises the value for
 * the message rather than for the comparison, which PHP already performs
 * numerically on a numeric string.
 */
it('accepts a minimum supplied as a string, the way a ruleset supplies it', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'configured.php', static function (object $sniff): void {
        $sniff->minimum = '3';
    });

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 1, 'source' => EXCESSIVE_PUBLIC_COUNT_ERROR],
    ]);
});

/**
 * An unterminated class leaves PHPCS with no scope_opener on the declaration,
 * so there is no body to walk. The file is already a parse error and the sniff
 * says nothing about it — and, just as importantly, raises no PHP error of its
 * own while finding that out. The error-handler assertion is what makes this
 * discriminating: without the guard the sniff still reports nothing, but it
 * reads two undefined array keys to get there.
 */
it('stays silent on an unterminated declaration without raising a PHP error', function (): void {
    $raised = [];

    set_error_handler(static function (int $severity, string $message) use (&$raised): bool {
        $raised[] = $message;

        return true;
    });

    try {
        $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'unclosed-class.php');
    } finally {
        restore_error_handler();
    }

    expect($raised)->toBe([]);
    expect(violationTuples($file))->toBe([]);
});

/**
 * Detection only, matching PHPMD. Splitting an oversized type is a design
 * change: which members move, and to what, is not machine-derivable.
 */
it('offers no fixer', function (): void {
    $file = analyzeFixture(EXCESSIVE_PUBLIC_COUNT, 'failing.php');

    expect(violationFixableFlags($file))->toBe([false, false, false]);
    expect($file->getFixableCount())->toBe(0);
});
