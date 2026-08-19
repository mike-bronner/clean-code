<?php

/**
 * Tests the custom CleanCode.Pattern.TooManyInterfaceMethods sniff (Pattern:
 * SOLID — Interface Segregation, #132). Fixtures live in
 * tests/fixtures/TooManyInterfaceMethodsSniff/.
 *
 * The subject is a count, so every fixture here is written one signature per
 * declaration and each sits *on* a boundary rather than comfortably past it:
 * an assertion of silence is then never satisfied by a sniff that has simply
 * fallen quiet, because moving one signature across the line the fixture pins
 * makes the same file report.
 *
 * The rule is detection-only, so there is no autofixed fixture: splitting a
 * contract means deciding which client needs which signature, which no
 * mechanical rewrite can do.
 *
 * rules.xml does not path-scope this sniff, so the fixtures are processed
 * where they live. The sniff is isolated from the rest of the master ruleset
 * (loaded, then $ruleset->sniffs is narrowed to it) so these assertions stay
 * stable as sibling standards land in rules.xml.
 */

declare(strict_types=1);

const TOO_MANY_INTERFACE_METHODS = 'CleanCode.Pattern.TooManyInterfaceMethods';

const TOO_MANY_INTERFACE_METHODS_WARNING = TOO_MANY_INTERFACE_METHODS . '.MaxExceeded';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(TOO_MANY_INTERFACE_METHODS);
});

/**
 * The master ruleset configures the threshold explicitly, so a consumer
 * reading rules.xml sees the value it is running under.
 *
 * Both halves of that are asserted, because neither alone pins the block. The
 * parsed `<property>` element comes first: the sniff is picked up by the
 * ./CleanCode/ruleset.xml reference whether or not rules.xml says anything
 * about it, and the shipped value equals the class default, so deleting the
 * whole block would leave a test that only read the sniff instance green. The
 * instance is then read too, since the element carries the value as the string
 * `'5'` and only the assignment proves it reaches a typed `int $maxMethods`
 * as the number 5.
 */
it('carries the shipped threshold through the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->ruleset[TOO_MANY_INTERFACE_METHODS]['properties']['maxMethods'] ?? null)
        ->toBe(['value' => '5', 'scope' => 'sniff']);

    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[TOO_MANY_INTERFACE_METHODS]];

    expect($sniff->maxMethods)->toBe(5);
});

/**
 * Everything the sniff must leave alone, in one file:
 *
 * - line 14, `ExactlyAtTheMaximum` — 5 signatures. The threshold is exclusive,
 *   so an interface holding exactly the maximum is compliant; this is the
 *   silent half of that boundary and `failing.php` is the other.
 * - line 31, `SurroundedByNonMethods` — the same 5 signatures wrapped in three
 *   parent interfaces, two constants and two PHP 8.4 property hooks. Count any
 *   one of those seven and the interface reaches 6 and reports, so silence
 *   here pins the AC's "constants, extended interfaces and property hooks are
 *   not miscounted" down to a single member.
 * - line 58, `WideClass`; line 73, `WideTrait`; line 88, `WideEnum`; line 105,
 *   an anonymous class — each holding 6 methods, one past the threshold. None
 *   is an interface. PHPCS gives each its own token, so registering T_CLASS,
 *   T_TRAIT, T_ENUM or T_ANON_CLASS beside T_INTERFACE would make this fixture
 *   report four times.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * 6 signatures — one past the maximum — earns exactly one warning, on the
 * interface declaration (line 11, column 1) rather than on any single
 * signature. The defect is the width of the whole contract, so it has no
 * statement line of its own.
 */
it('flags an interface one signature past the maximum on its declaration', function (): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 11, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ])->and($file->getErrors())->toBe([]);
});

/**
 * The message names the interface, both numbers, and the principle the count
 * stands in for — so a report over a whole application says which interface to
 * open, how far past the line it is, and what the remedy is. The AC asks for
 * the principle and the recommendation by name, and nothing else in the report
 * carries them.
 */
it('names the interface, the counts and the principle in the message', function (): void {
    $warnings = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'failing.php')->getWarnings();

    expect($warnings[11][1][0]['message'])
        ->toContain('OneOverTheMaximum')
        ->toContain('6')
        ->toContain('5')
        ->toContain('Interface Segregation')
        ->toContain('split it into narrower interfaces');
});

/**
 * `SurroundedByNonMethods` sits on the boundary from the compliant side, so
 * its silence above already fails the moment a constant, a parent interface or
 * a property hook is counted. Silence alone cannot see the *other* direction,
 * though — a count that went low would stay just as quiet — so this pins the
 * number itself. Lowering the ceiling to 0 forces both interfaces in
 * `passing.php` to report and puts each count in its message, where a miscount
 * either way is visible as a number rather than inferred from a threshold.
 */
it('counts only the signatures the interface body declares', function (): void {
    $file = analyzeFixture(
        TOO_MANY_INTERFACE_METHODS,
        'passing.php',
        static function (object $sniff): void {
            $sniff->maxMethods = 0;
        }
    );

    expect(warningTuples($file))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
        ['line' => 31, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);

    $warnings = $file->getWarnings();

    expect($warnings[14][1][0]['message'])->toContain('declares 5 method signatures')
        ->and($warnings[31][1][0]['message'])->toContain('declares 5 method signatures');
});

/**
 * `maxMethods` is a public sniff property, so a project that tolerates wider
 * contracts can raise it and a stricter one can lower it. Both directions are
 * pinned, because a property that never reached the sniff would leave the
 * raised run identical to the default one and a threshold hard-wired at 5
 * would leave the lowered run identical too:
 *
 * - `configured.php`'s 6 signatures report under the shipped default and fall
 *   silent at 6.
 * - `passing.php`'s `ExactlyAtTheMaximum` is compliant at the shipped default
 *   and reports at 4, so the boundary moves with the configured value rather
 *   than only the verdict at one fixed count.
 */
it('exposes a configurable maximum', function (): void {
    expect(warningTuples(analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'configured.php')))->toBe([
        ['line' => 11, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);

    $raised = analyzeFixture(
        TOO_MANY_INTERFACE_METHODS,
        'configured.php',
        static function (object $sniff): void {
            $sniff->maxMethods = 6;
        }
    );

    expect($raised->getWarnings())->toBe([]);

    $lowered = analyzeFixture(
        TOO_MANY_INTERFACE_METHODS,
        'passing.php',
        static function (object $sniff): void {
            $sniff->maxMethods = 4;
        }
    );

    expect(warningTuples($lowered))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
        ['line' => 31, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);
});

/**
 * The AC asks for the threshold to be configurable *via a ruleset property*,
 * which is a different path from the assignment above: a `<property>` element
 * hands `Ruleset::setSniffProperty()` the value as a **string**, and only that
 * path can discover that a typed `int $maxMethods` rejects it. Asserting
 * through `$configure` alone would always hand over a correctly typed value
 * and could never see the failure a consumer would hit first.
 */
it('takes the threshold from a ruleset property', function (): void {
    $raised = analyzeFixtureWithRulesetProperties(
        TOO_MANY_INTERFACE_METHODS,
        'configured.php',
        ['maxMethods' => '6']
    );

    expect($raised->getWarnings())->toBe([]);

    $lowered = analyzeFixtureWithRulesetProperties(
        TOO_MANY_INTERFACE_METHODS,
        'passing.php',
        ['maxMethods' => '4']
    );

    expect(warningTuples($lowered))->toBe([
        ['line' => 14, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
        ['line' => 31, 'column' => 1, 'source' => TOO_MANY_INTERFACE_METHODS_WARNING],
    ]);
});

/**
 * Two shapes PHPCS hands a sniff mid-edit, neither of which it may fall over
 * on, and each reaching a different half of the same guard:
 *
 * - `truncated.php`, an interface holding 6 signatures whose body is never
 *   closed. The tokeniser leaves it without a scope, so there is no body to
 *   count — the count it would report is not one the file supports yet.
 * - `nameless.php`, the `interface` keyword with nothing after it, over a body
 *   PHPCS *does* resolve. The 6 signatures inside are counted; what stops the
 *   report is that there is no name to put in the message. PHP has no
 *   anonymous-interface syntax, so nothing legal reaches this guard.
 *
 * Both fixtures carry 6 signatures rather than 5 on purpose: drop either guard
 * and the sniff reports, so silence here cannot be satisfied by a count that
 * happens to sit under the threshold.
 */
it('passes over a half-written interface', function (string $fixture): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, $fixture);

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
})->with(['truncated.php', 'nameless.php']);

/**
 * Pins the two severity decisions the standard's own doc records: the report
 * is a warning, because a wide interface every implementer fully honours is
 * not an ISP violation and the count is a prompt for review rather than a
 * verdict; and it is detection-only, because splitting a contract is a design
 * decision about which client needs which signature.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(TOO_MANY_INTERFACE_METHODS, 'failing.php');

    expect($file->getWarningCount())->toBe(1)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
