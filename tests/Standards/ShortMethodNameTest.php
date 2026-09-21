<?php

/**
 * Tests the custom CleanCode.Naming.ShortMethodName sniff, which replicates
 * PHPMD's Naming/ShortMethodName (#111) —
 * docs/phpmd/naming-shortmethodname.md.
 *
 * Fixtures live in tests/fixtures/ShortMethodNameSniff/. The sniff is
 * detection-only, so the contract's third fixture (autofixed.php) does not
 * apply; passing.php and failing.php are joined by divergences.php, which
 * carries the one shape this sniff reports and phpmd cannot see.
 *
 * Every line asserted against failing.php was cross-checked against a live
 * phpmd 2.15 run of rulesets/naming.xml/ShortMethodName at its defaults, from
 * a cold pdepend cache: phpmd reports the same eleven lines, and reports
 * nothing at all on passing.php or divergences.php.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Ruleset;

const SHORT_METHOD_NAME = 'CleanCode.Naming.ShortMethodName';

const SHORT_METHOD_NAME_TOO_SHORT = SHORT_METHOD_NAME . '.TooShort';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(SHORT_METHOD_NAME);
});

it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The exact line and column of every report, one per offending declaration.
 *
 * The column is the *name* token, not the `function` keyword, which is what
 * makes the by-reference case (38) and the global-function case (21) worth
 * asserting separately: an implementation that reported at the keyword, or
 * that mistook the `&` for the name, would still report the right lines.
 *
 * The shapes, in order: a global function (21), an interface method (27), an
 * abstract method (32), a static method (34), a by-reference method (38), a
 * semi-reserved word used as a method name (44), a concrete implementation of
 * the abstract method (51), a one-character method (55), a function declared
 * inside a method body (63), a trait method (71) and an enum method (78).
 */
it('flags every short declaration at the name token', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 21, 'column' => 10, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 27, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 32, 'column' => 30, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 34, 'column' => 28, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 38, 'column' => 22, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 44, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 51, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 55, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 63, 'column' => 18, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 71, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 78, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
    ]);
});

/**
 * Renaming a function means finding every call site, every interface it
 * satisfies and anything reaching it by string, so there is no safe
 * mechanical rewrite — matching PHPMD, which does not fix this rule either.
 */
it('reports without offering a fix', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'failing.php');

    expect($file->getErrorCount())->toBe(11)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

/**
 * The message quotes the offending name and the threshold that rejected it,
 * so a raised `minimum` explains itself in the report rather than leaving the
 * reader to guess which value applied.
 */
it('names the declaration and the threshold in the message', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'failing.php');

    $messages = $file->getErrors()[55][21];

    expect($messages[0]['message'])
        ->toBe('Avoid using short method names like a(). The configured minimum method name length is 3.');
});

/**
 * PHPMD's comparison is `strlen($name) >= $threshold`, so the boundary sits
 * *at* the minimum: a three-character name passes and a two-character one
 * fails. Driving the same fixture at two thresholds pins both halves — the
 * line asserted at each threshold is the same `abc()` declaration, so only
 * the comparison can be what changes the outcome.
 */
it('passes a name exactly at the minimum and fails one character shorter', function (): void {
    $atMinimum = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 3;
        }
    );

    $oneAbove = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect($atMinimum->getErrors())->toBe([])
        ->and(array_keys($oneAbove->getErrors()))->toContain(77);
});

/**
 * Byte length, not character length. `añ` is two characters but three bytes,
 * and PHPMD measures it with strlen(), so both tools accept it at the default
 * minimum of three. Raising the minimum to four proves the declaration is
 * reached at all — without that half, a sniff that simply skipped non-ASCII
 * names would pass the first assertion too.
 */
it('measures the name in bytes, as PHPMD does', function (): void {
    $atDefault = analyzeFixture(SHORT_METHOD_NAME, 'passing.php');

    $raised = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 4;
        }
    );

    expect(array_keys($atDefault->getErrors()))->not->toContain(83)
        ->and(array_keys($raised->getErrors()))->toContain(83);
});

/**
 * PHPMD has no magic-method carve-out, and neither does this sniff. At the
 * default threshold the distinction is invisible, because the shortest magic
 * method (__get, five characters) already clears three — so the only way to
 * observe it is to raise the minimum past them, which is exactly what a
 * consumer tightening the rule would do. Confirmed against live phpmd: at
 * minimum=12 it reports __get, __set, __construct and __call.
 */
it('reports magic methods below the threshold, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 12;
        }
    );

    // __construct (57), __get (61), __set (66) and __call (70) — every name
    // the claim above covers, so a carve-out for any one of them is red here.
    expect(array_keys($file->getErrors()))
        ->toContain(57)
        ->toContain(61)
        ->toContain(66)
        ->toContain(70);
});

/**
 * Closures and arrow functions are unnamed, so there is nothing to measure.
 * PHPCS gives them their own token types (T_CLOSURE, T_FN) rather than
 * T_FUNCTION, which is why no explicit guard is needed — this pins that it
 * stays true. The threshold is raised well past every name in the fixture so
 * that a closure being reached would have to show up.
 */
it('never reports an unnamed declaration', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = 40;
        }
    );

    expect(array_keys($file->getErrors()))->not->toContain(109, 112);
});

/**
 * The exceptions list exempts a name whatever its length. `ct` is declared
 * twice in failing.php (abstract at 32, concrete at 51); both go quiet, which
 * an exemption keyed to the declaration rather than the name would not do.
 */
it('never reports a name in the exceptions list', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ct';
        }
    );

    expect(array_keys($file->getErrors()))
        ->not->toContain(32)
        ->not->toContain(51)
        ->toContain(34);
});

/**
 * PHPMD explodes the raw property on commas and compares strictly, without
 * trimming the parts, so "ct, st" exempts `ct` and ` st` — never `st`. This
 * sniff reproduces that rather than being kinder, because trimming would
 * exempt names phpmd still reports, and a ruleset that exists to replace
 * phpmd must not fall silent where phpmd speaks.
 *
 * Line 34 is the `st()` declaration: still reported despite appearing in the
 * property.
 */
it('does not trim the exceptions list, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'ct, st';
        }
    );

    expect(array_keys($file->getErrors()))
        ->toContain(34)
        ->not->toContain(32);
});

/**
 * The exceptions comparison is strict and case-sensitive, matching PHPMD's
 * in_array(..., true). PHP method names are case-insensitive, so `CT` and
 * `ct` are the same method — but PHPMD exempts only the spelling listed, and
 * so does this.
 */
it('matches exceptions case-sensitively, matching PHPMD', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff): void {
            $sniff->exceptions = 'CT';
        }
    );

    expect(array_keys($file->getErrors()))->toContain(32, 51);
});

/**
 * PHPCS hands ruleset properties over as raw strings and converts an empty
 * value to null before assigning it. This is what makes the null branch in
 * the sniff reachable from a plain
 * <property name="minimum" value=""/> — asserted here against PHPCS itself,
 * so the fallback below is pinned to real behaviour rather than to a
 * assumption about it.
 */
it('receives null from PHPCS for an empty ruleset property', function (): void {
    [, $ruleset] = buildRuleset([SHORT_METHOD_NAME], true);
    $sniffClass = $ruleset->sniffCodes[SHORT_METHOD_NAME];

    $ruleset->setSniffProperty($sniffClass, 'minimum', ['scope' => 'sniff', 'value' => '']);

    expect($ruleset->sniffs[$sniffClass]->minimum)->toBeNull();
})->skip(
    method_exists(Ruleset::class, 'setSniffProperty') === false,
    'This PHPCS release does not expose setSniffProperty().'
);

/**
 * An unusable `minimum` falls back to PHPMD's default rather than being cast.
 * This is the fail-closed half of the property handling: (int) null and
 * (int) 'abc' are both 0, and a threshold of 0 passes every name, so a cast
 * would silently switch the rule off on a typo'd ruleset instead of carrying
 * on enforcing. Every value here therefore has to keep reporting exactly what
 * the default reports.
 *
 * `null` is what PHPCS assigns for value="" (pinned above); the rest are the
 * shapes a hand-edited ruleset produces.
 */
it('falls back to the default minimum when the configured one is unusable', function (mixed $configured): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'failing.php',
        static function (object $sniff) use ($configured): void {
            $sniff->minimum = $configured;
        }
    );

    expect($file->getErrorCount())->toBe(11);
})->with([
    'null (PHPCS empty property)' => [null],
    'empty string' => [''],
    'non-numeric' => ['abc'],
    'zero' => ['0'],
    'negative' => ['-1'],
    'float-ish' => ['2.5'],
]);

/**
 * A usable string threshold is honoured, which is the ordinary ruleset path —
 * PHPCS never assigns an int. Without this, the fallback test above would
 * hold just as well against a sniff that ignored the property entirely.
 */
it('honours a numeric string threshold from a ruleset', function (): void {
    $file = analyzeFixture(
        SHORT_METHOD_NAME,
        'passing.php',
        static function (object $sniff): void {
            $sniff->minimum = '4';
        }
    );

    expect(array_keys($file->getErrors()))->toContain(77, 83);
});

/**
 * A method declared without a parameter list gives PHPCS no
 * `parenthesis_opener` to bound the name search with, so there is nothing
 * proving the next token found is the name rather than something further
 * down the file. The sniff refuses to guess and stays silent on it.
 *
 * The assertion needs both halves. `ok()` on line 26 is well-formed and short
 * and must still be reported — without it, a sniff that had simply given up
 * on the whole file would satisfy the silence on line 30 just as well.
 */
it('refuses to name a declaration it cannot bound', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'malformed-declaration.php');

    expect(violationTuples($file))->toBe([
        ['line' => 26, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
    ]);
});

/**
 * The one shape this sniff reports and phpmd does not: methods of an
 * anonymous class, which pdepend builds no method node for. Verified against
 * a live phpmd 2.15 run from a cold cache — it reports nothing on this
 * fixture, at any threshold, while both declarations are real violations of
 * the rule as written.
 */
it('reports anonymous-class methods that PHPMD cannot see', function (): void {
    $file = analyzeFixture(SHORT_METHOD_NAME, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 29, 'column' => 21, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
        ['line' => 39, 'column' => 29, 'source' => SHORT_METHOD_NAME_TOO_SHORT],
    ]);
});
