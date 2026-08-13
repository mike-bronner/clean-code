<?php

/**
 * Tests the custom CleanCode.Naming.DisallowMagicNumbers sniff (Naming:
 * Semantic naming principles, #18/#136). Fixtures live in
 * tests/fixtures/DisallowMagicNumbersSniff/.
 *
 * The sniff carries one token-visible slice of a Tier 3 standard: "Use
 * Searchable Names" calls out numeric constants as hard to locate, and a bare
 * numeric literal outside a declaration site is exactly that. Everything else
 * in the standard — whether a name reveals intent, misleads, or keeps one word
 * per concept — stays with code review, so these tests never assert on names.
 *
 * The rule is detection-only and reports warnings: naming a number is a
 * judgement about what it means, which neither a token stream nor a fixer can
 * make. Every "no violations" assertion below is therefore paired with a
 * fixture that does warn — a silent sniff would satisfy the negative alone.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 */

declare(strict_types=1);

const MAGIC_NUMBERS = 'CleanCode.Naming.DisallowMagicNumbers';

const MAGIC_NUMBERS_WARNING = MAGIC_NUMBERS . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(MAGIC_NUMBERS);
});

/**
 * The compliant fixture is silent, and it is silent for reasons, not for want
 * of numbers: it holds a literal at every declaration site the standard
 * exempts, plus the shipped ignore list's values in expression position.
 *
 * - line 5, a namespace-level `const RETRY_LIMIT = 3;`.
 * - line 7, `#[Deprecated(since: 2)]` on the class — a *file-level* attribute,
 *   whose literal has no enclosing scope at all. This is the one shape that
 *   pins the attribute rule on its own; the method attribute on line 20 is
 *   covered by the class-body rule as well.
 * - line 10, a class constant. Line 45 is the same for an interface.
 * - line 12, a typed property default. Lines 50 and 54 repeat it for a trait
 *   and an anonymous class, the two class-like scopes easiest to leave out of
 *   the declaration-scope list.
 * - lines 15-16, promoted constructor parameter defaults, the second wrapping
 *   its literal in a new-in-initializer (`new Money(4500)`) so the enclosing
 *   parenthesis nearest the literal is *not* the parameter list.
 * - line 21, an ordinary parameter default; lines 57 and 59 repeat it for an
 *   arrow function and a closure.
 * - lines 39-40, enum case values.
 * - lines 62-65, `0`, `-1`, `0.0`, and `0x1` used in expressions — the shipped
 *   ignore list, spelled four different ways.
 * - line 67, `declare(ticks=5);`. PHP requires a declare directive's value to
 *   be a literal, so there is no constant the sniff could be asking for. `5`
 *   is not on the ignore list, which makes this the one exemption pinned here
 *   without help from the list.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The four contexts the standard's searchability argument covers, one literal
 * each, reported at the literal's own column:
 *
 * - line 7, an expression (`RETRY_LIMIT * 12`). It also pins that the
 *   const-statement exemption stops at the previous statement's semicolon:
 *   line 5 declares a constant, and a lookback that ran past it would swallow
 *   this literal too.
 * - line 11, an expression inside a function body.
 * - line 13, a comparison.
 * - line 14, a function argument.
 * - line 17, a return statement.
 *
 * Line 5's own `3` is absent from the list, which is the const exemption.
 */
it('flags a bare literal in every use context', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 7, 'column' => 27, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 11, 'column' => 26, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 13, 'column' => 21, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 14, 'column' => 36, 'source' => MAGIC_NUMBERS_WARNING],
            ['line' => 17, 'column' => 24, 'source' => MAGIC_NUMBERS_WARNING],
        ]);
});

/**
 * The message quotes the literal as written, so a report says which number to
 * name without reopening the file.
 */
it('names the literal in the warning message', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'failing.php')->getWarnings();

    expect($warnings[13][21][0]['message'])->toContain('42');
});

/**
 * The parameter-default exemption stops at the parameter list. Each of the
 * three callable forms — arrow function, closure, and a method on an anonymous
 * class — carries an ignored-looking default (60, 80, 90) beside a magic
 * number in its body (24, 36, 48), and only the bodies are reported.
 *
 * Without this, the exemption's *width* is untested: `passing.php` proves the
 * defaults stay silent, but an implementation that skipped everything from the
 * opening parenthesis to the end of the callable would satisfy it just as
 * well. An anonymous class is in the list because its methods sit one scope
 * below a class-like declaration scope, the other exemption on this path.
 */
it('polices a callable body while exempting its parameter defaults', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'callable-bodies.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        5 => [MAGIC_NUMBERS_WARNING],
        8 => [MAGIC_NUMBERS_WARNING],
        14 => [MAGIC_NUMBERS_WARNING],
    ]);
});

/**
 * The ignore list is matched by *value*, not by spelling, and the value is
 * read in the base PHP would read it in.
 *
 * Lines 5-10 are silent because each spells 1, -1, or 0 in another base:
 * `0x1`, `0b1`, `0o1`, `01`, `0.0`, `-0x1`. Lines 12-17 warn because 31, 11,
 * 15, 15, 1000, and 2.5 are not on the list.
 *
 * The failing half is what makes this discriminating. A naive `(float)` cast
 * evaluates every one of `0x1F`, `0b1011`, and `0o17` to 0.0 — the first entry
 * on the shipped ignore list — so a sniff that skipped base conversion would
 * fall silent on all three and still pass the silent half above.
 */
it('reads every numeric base when matching the ignore list', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'numeric-bases.php');

    expect(violationSourcesByLine($file->getWarnings()))->toBe([
        12 => [MAGIC_NUMBERS_WARNING],
        13 => [MAGIC_NUMBERS_WARNING],
        14 => [MAGIC_NUMBERS_WARNING],
        15 => [MAGIC_NUMBERS_WARNING],
        16 => [MAGIC_NUMBERS_WARNING],
        17 => [MAGIC_NUMBERS_WARNING],
    ]);
});

/**
 * PHP has no negative-number token: `-1` reaches the sniff as a T_MINUS
 * followed by `1`, so the sign has to be re-attached before the ignore list is
 * consulted, and only when the minus negates rather than subtracts.
 *
 * Silent: line 5 (`-1` assigned), line 9 (`-1` inside an array literal, where
 * the preceding token is `[`), line 10 (`-1` inside parentheses), and line 6,
 * where `$unary - 1` subtracts the ignored `1`.
 *
 * Reported: line 7 twice and line 8 once. The messages are what discriminate
 * here — `100 - 5` must report `5`, not `-5`, and `-7` must report `-7`, not
 * `7`. Getting the sign rule backwards keeps the line and column numbers
 * identical, so tuples alone would not catch it.
 */
it('attaches a negating minus to the literal but not a subtracting one', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'negative-numbers.php')->getWarnings();

    expect(violationSourcesByLine($warnings))->toBe([
        7 => [MAGIC_NUMBERS_WARNING, MAGIC_NUMBERS_WARNING],
        8 => [MAGIC_NUMBERS_WARNING],
    ])
        ->and($warnings[7][15][0]['message'])->toContain('number 100 ')
        ->and($warnings[7][21][0]['message'])->toContain('number 5 ')
        ->and($warnings[8][13][0]['message'])->toContain('number -7 ');
});

/**
 * The ignore list is a public sniff property, so a consuming ruleset can add
 * the values its own domain treats as self-evident.
 *
 * One fixture pins both directions: under the shipped default both literals
 * warn, and naming 1000 silences line 5 alone. A property that was read once
 * and ignored would leave the two runs identical and fail the second
 * assertion.
 *
 * The configured entry is spelled `1000` against a literal written `1_000`,
 * because the readability underscore is not part of the value and a ruleset
 * should not have to guess how the code spells it.
 *
 * The configured run is also the only place the `declare()` exemption is
 * tested against a value the ignore list does not cover: dropping `1` from the
 * list leaves `declare(strict_types=1)` on line 3 unprotected, so a sniff
 * without that exemption reports it here and nowhere else in this file.
 */
it('exposes a configurable ignore list', function (): void {
    expect(violationSourcesByLine(analyzeFixture(MAGIC_NUMBERS, 'configured.php')->getWarnings()))->toBe([
        5 => [MAGIC_NUMBERS_WARNING],
        6 => [MAGIC_NUMBERS_WARNING],
    ]);

    $configured = analyzeFixture(
        MAGIC_NUMBERS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->ignoredNumbers = ['1000'];
        }
    );

    expect(violationSourcesByLine($configured->getWarnings()))->toBe([
        6 => [MAGIC_NUMBERS_WARNING],
    ]);
});

/**
 * The standard is advisory — "some literals are self-evident in context" — so
 * the rule must not fail a consumer's build. Asserting the failing fixture
 * raises no errors is not enough on its own: a sniff that had fallen silent
 * would satisfy it too, which is why the warning count is asserted alongside.
 */
it('reports warnings rather than errors', function (): void {
    $file = analyzeFixture(MAGIC_NUMBERS, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(5);
});

/**
 * Detection only: naming a number is a judgement about what it means, so
 * there is no mechanical rewrite to offer and no autofixed fixture. The
 * fixable flag is asserted rather than assumed, because a sniff that
 * advertised a fix it never applies would make phpcbf report unresolvable
 * violations forever.
 */
it('offers no fixer', function (): void {
    $warnings = analyzeFixture(MAGIC_NUMBERS, 'failing.php')->getWarnings();

    $fixable = [];

    foreach ($warnings as $columns) {
        foreach ($columns as $messages) {
            foreach ($messages as $message) {
                $fixable[] = $message['fixable'];
            }
        }
    }

    expect($fixable)->toBe([false, false, false, false, false]);
});
