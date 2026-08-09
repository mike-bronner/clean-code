<?php

/**
 * Tests the custom CleanCode.Metrics.ExcessiveClassComplexity sniff, which
 * replicates PHPMD's CodeSize/ExcessiveClassComplexity rule
 * (docs/phpmd/codesize-excessiveclasscomplexity.md).
 *
 * Every expectation here was calibrated against a live PHPMD 2.15.0 / PDepend
 * run over the same fixtures rather than against PHPMD's documentation, which
 * describes neither the counting rules nor the comparison accurately. Two of
 * those measurements are worth stating outright, because they are the ones a
 * reader is most likely to assume the other way round:
 *
 * - PHPMD reports a class whose weighted method count is **at or above** the
 *   configured maximum (`$actual >= $threshold` in PHPMD's own
 *   Rule\Design\WeightedMethodCount), not strictly above it. So 50 is a
 *   violation at the default maximum of 50, and 49 is not.
 * - `match`, `??`, `??=`, `?->`, `xor`, `else`, `default`, `finally`, and
 *   `goto` are worth nothing. Only the constructs listed on the sniff score.
 *
 * The rule is detection-only — the fix is to split the class up — so there is
 * no autofixed fixture, and the detection-only test pins that.
 */

declare(strict_types=1);

const EXCESSIVE_CLASS_COMPLEXITY = 'CleanCode.Metrics.ExcessiveClassComplexity';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_CLASS_COMPLEXITY);
});

/**
 * passing.php carries the near miss the threshold has to stay silent on —
 * AtOneBelowTheMaximum measures exactly 49 against a maximum of 50 — next to
 * UncountedConstructs, which holds well over fifty of the constructs PDepend
 * does not score, and AbstractMethods, whose methods have no body at all. Each
 * smaller class after them pins one counting rule the near miss never reaches;
 * the measurement test below names them one by one.
 *
 * Silence alone is a weak verdict here: no single uncounted construct appears
 * fifty times, so counting one of them would raise UncountedConstructs' measure
 * without pushing it over the maximum, and this assertion would not notice.
 * The measurement test below closes that gap by pinning each class's exact
 * count; this one pins that the default threshold reports nothing.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The counting rules themselves, pinned as exact numbers rather than as a
 * verdict against a threshold. Dropping the maximum to 0 makes the sniff report
 * every class it measures, so the message carries each measurement out where a
 * test can read it — which is the only way any single counting rule becomes
 * discriminating on its own.
 *
 * - AtOneBelowTheMaximum, 49, from a varied method plus a boolean chain. Adding
 *   a construct to the counted list, or dropping one from it, moves this.
 * - UncountedConstructs, 4. It holds 23 `??`, 21 `match` arms, 5 `?->`, 3
 *   `xor`, an `??=`, a `finally`, a bare `default`, a `goto`, and two `else`
 *   branches, so counting any one of those kinds moves this number.
 * - AbstractMethods, 4: three methods with no body and one with an empty one,
 *   each worth 1. This is the only fixture reaching the sniff's bodyless-method
 *   path.
 * - KeywordBooleanOperators, 8: two `if`s, two `and`s and three `or`s. The
 *   keyword spellings are counted nowhere else in this suite — every other
 *   boolean count is written `&&` or `||` — so dropping T_LOGICAL_AND or
 *   T_LOGICAL_OR from the counted list moves this and nothing else.
 * - InlineFunctionBodies, 3: a closure's `&&` and an arrow function's ternary,
 *   both belonging to the method that holds them rather than to an artifact of
 *   their own. Skipping over either declaration lowers this, and counting the
 *   `function` or `fn` keyword itself raises it.
 * - AnonymousClassArguments, 5: make() is 3 — itself plus the ternary and the
 *   `&&` in an anonymous class's constructor arguments — and nest() is 2, the
 *   same rule one level down. Skipping an anonymous class from its `class`
 *   keyword rather than from its opening brace swallows those arguments and
 *   drops this to 2; failing to skip its body at all raises it instead.
 * - MemberDefaults, 3: a constructor and a measure() with one ternary. Its
 *   property, constant, and parameter defaults each hold a decision operator
 *   and each score nothing, so measuring a method from its `function` keyword
 *   instead of its opening brace, or scanning the class body outside its
 *   methods, raises this number.
 * - BareBlocks, 2: an `if` inside a bare block — the one brace a method can
 *   hold that owns nothing, and the only fixture reaching the ownerless branch
 *   of the sniff's brace test. Unlike the entries above, no mutation of that
 *   branch moves this number: a bare block's brace carries no scope closer, so
 *   a skip has nothing to skip to and the walk continues either way. The branch
 *   suppresses an undefined-key warning; this number pins the measurement.
 *
 * Every number below was measured against a live PHPMD 2.15.0 run over the same
 * fixture at the same maximum, not derived from the sniff.
 */
it('measures each class in the compliant fixture exactly', function (): void {
    $errors = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'passing.php',
        static function (object $sniff): void {
            $sniff->maximum = 0;
        }
    )->getErrors();

    // AbstractMethods is reported at column 10, where its `class` keyword
    // begins: the report lands on the keyword, not on the start of the line.
    expect($errors[20][1][0]['message'])
        ->toContain('Class AtOneBelowTheMaximum has a weighted method count of 49,')
        ->and($errors[82][1][0]['message'])
        ->toContain('Class UncountedConstructs has a weighted method count of 4,')
        ->and($errors[164][10][0]['message'])
        ->toContain('Class AbstractMethods has a weighted method count of 4,')
        ->and($errors[181][1][0]['message'])
        ->toContain('Class KeywordBooleanOperators has a weighted method count of 8,')
        ->and($errors[209][1][0]['message'])
        ->toContain('Class InlineFunctionBodies has a weighted method count of 3,')
        ->and($errors[237][1][0]['message'])
        ->toContain('Class AnonymousClassArguments has a weighted method count of 5,')
        ->and($errors[289][1][0]['message'])
        ->toContain('Class MemberDefaults has a weighted method count of 3,')
        ->and($errors[327][1][0]['message'])
        ->toContain('Class BareBlocks has a weighted method count of 2,')
        ->and(array_keys($errors))->toBe([20, 82, 164, 181, 209, 237, 289, 327]);
});

it('flags each over-threshold class at its declaration', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
        ['line' => 77, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * The measurement itself, not just the fact that something was reported. A
 * counting regression that still pushed both classes over the maximum would
 * satisfy the tuple assertion above while reporting the wrong number, so the
 * message is read for the exact weighted method count and the exact maximum.
 *
 * The two values are the boundary pair: 50 is the first count the default
 * maximum reports, and 63 is comfortably clear of it.
 */
it('reports the measured weighted method count and the maximum', function (): void {
    $errors = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'failing.php')->getErrors();

    expect($errors[14][1][0]['message'])
        ->toBe(
            'Class AtExactlyTheMaximum has a weighted method count of 50, at or above the '
                . 'configured maximum of 50; split it into smaller classes (see '
                . 'docs/phpmd/codesize-excessiveclasscomplexity.md)'
        )
        ->and($errors[77][1][0]['message'])
        ->toBe(
            'Class WellAboveTheMaximum has a weighted method count of 63, at or above the '
                . 'configured maximum of 50; split it into smaller classes (see '
                . 'docs/phpmd/codesize-excessiveclasscomplexity.md)'
        );
});

/**
 * PHPMD's rule is ClassAware: it speaks about named classes and nothing else.
 * Every declaration in not-a-class.php carries a weighted method count far past
 * the maximum, so a sniff that registered on any of these tokens — or that
 * folded a nested declaration's complexity into the class holding it — would
 * report here.
 *
 * The two holder classes are the interesting half. They *are* named classes, so
 * the sniff measures them; each has to come out at 1 rather than at the 60 its
 * anonymous class or nested named function carries.
 */
it('stays silent on every declaration that is not a named class', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'not-a-class.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The same file measured rather than merely found silent. The trait, the
 * interface, and the enum have to be absent altogether — a sniff that
 * registered on their tokens would report all three at a maximum of 0 — and
 * the three named classes have to come out at the numbers below, not at the 60
 * their nested declaration or their trait carries.
 *
 * UsesHugeTrait at 0 is the trait half of that: PDepend scores a using class 0,
 * because the methods belong to the trait it uses.
 */
it('measures each class in the not-a-class fixture exactly', function (): void {
    $errors = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'not-a-class.php',
        static function (object $sniff): void {
            $sniff->maximum = 0;
        }
    )->getErrors();

    expect($errors[45][1][0]['message'])
        ->toContain('Class UsesHugeTrait has a weighted method count of 0,')
        ->and($errors[135][1][0]['message'])
        ->toContain('Class HoldsHugeAnonymousClass has a weighted method count of 1,')
        ->and($errors[169][1][0]['message'])
        ->toContain('Class HoldsHugeNestedFunction has a weighted method count of 1,')
        ->and(array_keys($errors))->toBe([45, 135, 169]);
});

/**
 * A named class the tokenizer never closed. PHPCS builds no scope for it, so
 * there is no body to measure and no method recorded as belonging to it; the
 * sniff reports nothing rather than publishing a count of 0 that would mean
 * "unmeasurable". PHPMD is silent on the same file for a blunter reason — PHP
 * cannot compile it, so PDepend never parses it.
 *
 * Asserted at a maximum of 0 for the same reason as the nameless case: at the
 * default threshold a truncated class would be silent whatever the sniff did.
 */
it('passes over a class the tokenizer never closed', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'truncated.php',
        static function (object $sniff): void {
            $sniff->maximum = 0;
        }
    );

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The `maximum` property, exercised from the configured side. ModestClass
 * measures 13, so a maximum of 13 reports it and a maximum of 14 does not —
 * the same at-or-above comparison the default threshold makes, proved at a
 * value the fixture can state exactly.
 *
 * A ruleset hands a sniff its property values as the raw strings from the XML,
 * which is why `maximum` is declared untyped and cast where it is read. The
 * string case below is not a stylistic variation of the integer one: an `int`
 * declaration on the property would make it a TypeError.
 */
it('reports at the configured maximum and stays silent one above it', function (): void {
    $atThreshold = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = 13;
        }
    );

    $aboveThreshold = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = 14;
        }
    );

    expect(violationTuples($atThreshold))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ])->and($aboveThreshold->getErrors())->toBe([]);
});

it('accepts the maximum as the string a ruleset supplies', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = '13';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ]);
});

/**
 * A `maximum` PHP reads as no number at all. The cast turns it into 0, which
 * reports every class rather than switching the rule off — a configuration
 * mistake over-reports instead of going quiet, the same call rules.xml records
 * for DisallowBooleanArgumentFlag's ignorepattern.
 *
 * Without the cast this would compare an int against a non-numeric string, and
 * PHP 8 would compare them as strings ('13' < 'forty'), leaving the sniff
 * silent — so this case is what makes the cast load-bearing rather than
 * decorative.
 */
it('over-reports rather than falling silent on an unreadable maximum', function (): void {
    $file = analyzeFixture(
        EXCESSIVE_CLASS_COMPLEXITY,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maximum = 'forty';
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => EXCESSIVE_CLASS_COMPLEXITY . '.MaximumExceeded'],
    ]);
});

/**
 * The default the sniff ships with, read off the class rather than inferred
 * from a fixture. It is what makes an existing PHPMD configuration transfer
 * verbatim, so it is pinned in its own right.
 */
it('defaults the maximum to PHPMD\'s own 50', function (): void {
    [, $ruleset] = buildRuleset();

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[EXCESSIVE_CLASS_COMPLEXITY]];

    expect($sniff->maximum)->toBe(50);
});

it('reports detection-only violations', function (): void {
    $file = analyzeFixture(EXCESSIVE_CLASS_COMPLEXITY, 'failing.php');

    expect($file->getErrorCount())->toBeGreaterThan(0)
        ->and($file->getFixableCount())->toBe(0);
});
