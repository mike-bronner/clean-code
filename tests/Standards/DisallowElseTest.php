<?php

/**
 * Tests the custom CleanCode.Conditionals.DisallowElse sniff. Fixtures live in
 * tests/fixtures/DisallowElseSniff/.
 *
 * The sniff landed for #77 as a replica of PHPMD's CleanCode/ElseExpression,
 * which reports the `else` scope only. #14 ("Conditionals: No else or elseif")
 * widened it: `elseif` and the two-word `else if` are violations too, and the
 * mechanically safe subset is auto-fixable. The assertions that used to pin
 * PHPMD parity on those two points are gone with it — docs/phpmd/
 * cleancode-elseexpression.md now records the mapping as stricter than PHPMD
 * rather than equal to it. Every remaining parity claim below was checked
 * against a live PHPMD 2.15.0 run over these fixtures.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const DISALLOW_ELSE = 'CleanCode.Conditionals.DisallowElse';

const DISALLOW_ELSE_FOUND = DISALLOW_ELSE . '.Found';

const DISALLOW_ELSE_IF_FOUND = DISALLOW_ELSE . '.ElseIfFound';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DISALLOW_ELSE);
});

/**
 * passing.php is deliberately discriminating: alongside the compliant early
 * exit, guard clause, ternary, and pair of separate guards, it carries the
 * member shapes PHP lets the reserved words `else` and `elseif` name — a
 * method, class-constant, and enum-case declaration of each, plus a reference
 * to each through `->`, `self::`, and `Enum::`.
 *
 * Those need no guard in the sniff: PHPCS rewrites both keywords to T_STRING
 * in each of those positions before a sniff runs, so the sniff never registers
 * on one and removing the fixture lines changes no result today. They stay
 * because that rewrite is a tokenizer detail this sniff leans on, and pinning
 * it turns a change to it into a test failure here rather than a false
 * positive in consumers' code. The `elseif` half is new with #14, which is
 * what made T_ELSEIF a registered token in the first place.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One entry per `else` and per `elseif` keyword in failing.php, at the
 * keyword's own line and column. This list is the sniff's whole detection
 * contract — it is exact, so a missed keyword and an extra report both fail
 * here, and no separate silence test could add anything it does not pin.
 *
 * Lines 15 and 86 are the two shapes where this sniff is stricter than the
 * PHPMD rule it grew out of, and both are kept: PHPMD misses the file-scope
 * else because ElseExpression is MethodAware/FunctionAware and never visits
 * code outside a function, and misses the braceless else because it matches a
 * ScopeStatement, which PDepend only builds for a braced body. Both are the
 * same avoidable branch the rule targets. Dropping either line contradicts
 * docs/phpmd/cleancode-elseexpression.md and rules.xml.
 *
 * Lines 45, 58, 244, 275, 297, 345, and 357 are the `elseif`/`else if`
 * reports #14 added. PHPMD reports none of them: its rule fires on the else
 * scope only, so an if/elseif chain with no closing else produces nothing
 * there. That divergence is deliberate and documented; it is why this sniff
 * no longer claims parity.
 *
 * The PHPMD side of both claims was measured against a live PHPMD 2.15.0 run
 * over this fixture. It cannot be asserted here — phpmd is not a dependency of
 * this package, which is the entire point of replacing the rule.
 */
it('flags every else and elseif at its own line and column', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 15, 'column' => 3, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 25, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 36, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 45, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 47, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 58, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 60, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 72, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 75, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 86, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 96, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 108, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 124, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 135, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 146, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 148, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 160, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 175, 'column' => 15, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 187, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 202, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 220, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 244, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 246, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 257, 'column' => 35, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 266, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 275, 'column' => 11, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 287, 'column' => 9, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 297, 'column' => 9, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 308, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 315, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 326, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 334, 'column' => 11, 'source' => DISALLOW_ELSE_FOUND],
        ['line' => 345, 'column' => 9, 'source' => DISALLOW_ELSE_IF_FOUND],
        ['line' => 357, 'column' => 9, 'source' => DISALLOW_ELSE_IF_FOUND],
    ]);
});

/**
 * The fixable/declined split, keyword by keyword, in the same order as the
 * tuple list above. This is the assertion each declined shape in failing.php
 * exists for: the fixer's gates are one method per guard, and failing.php
 * carries one fixture method per guard, so deleting any guard flips exactly
 * one `false` here to `true`.
 *
 * Reading the false entries from line 244 down — the chain whose first branch
 * does not terminate (244, 246), a comment before the keyword (257), between
 * `else` and its brace (266), and between `else` and `if` (275), the keyword
 * on its own line (287, 297), an inline body (308), a comment trailing the
 * body's closing brace (315), a nested construct as the branch's last
 * statement (326), an empty preceding branch (334), a braceless elseif (345),
 * and an alternative-syntax elseif (357).
 */
it('offers a fixer only for the shapes it can rewrite safely', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect(violationFixableFlags($file))->toBe([
        false, false, true, false, false, false, false, false, false, false,
        false, true, true, true, true, true, true, true, true, true,
        true, false, false, false, false, false, false, false, false, false,
        false, false, false, false,
    ]);
});

/**
 * The half of the severity contract the tuple assertion cannot see: it reads
 * getErrors() only, so it would hold just as well if the sniff *also* raised
 * a warning per keyword. A warning-level report would leave phpcs exiting 0
 * on an else, which is the outcome #77 wired this sniff in to avoid.
 */
it('raises no warnings alongside the errors', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'failing.php');

    expect($file->getWarnings())->toBe([]);
});

/**
 * The AC bullet "phpcbf correctly rewrites simple else/elseif cases to an
 * early-exit equivalent without changing runtime behavior", asserted by
 * running both sides rather than by reading the diff.
 *
 * The fixer runs live over behaviour.php here, so this is not a comparison of
 * two committed files: weaken a gate in the sniff and the fixer rewrites a
 * shape it should have declined, and the two closures start disagreeing. The
 * `$priority` shape in the fixture is the one that makes that concrete — its
 * `elseif` terminates while its `if` does not, so unwrapping its `else` turns
 * `[true, false]` from 1 into 3.
 */
it('preserves runtime behaviour through the fixer', function (bool $flag, bool $other, array $items): void {
    $original = require fixturePath(sniffFixtureDirectory(DISALLOW_ELSE), 'behaviour.php');

    $path = sys_get_temp_dir() . '/disallow-else-' . uniqid() . '.php';
    file_put_contents($path, autofixedContents(analyzeFixture(DISALLOW_ELSE, 'behaviour.php')));

    try {
        $rewritten = require $path;
    } finally {
        unlink($path);
    }

    expect($rewritten($flag, $other, $items))->toBe($original($flag, $other, $items));
})->with([
    [true, true, []],
    [true, false, [1, 2]],
    [false, true, [1, null, 2]],
    [false, false, [1, false, 2]],
    [false, false, [null, false, null]],
]);

/**
 * The committed copy of that same output, so the rewrite is reviewable in the
 * diff rather than only reproducible at run time. It is a `.fixed.php` sibling
 * rather than `autofixed.php` because `autofixed.php` is reserved for
 * failing.php's output by the fixture contract.
 */
it('rewrites the behaviour fixture into its committed fixed sibling', function (): void {
    $file = analyzeFixture(DISALLOW_ELSE, 'behaviour.php');

    expect(autofixedContents($file))
        ->toBe(file_get_contents(fixturePath(sniffFixtureDirectory(DISALLOW_ELSE), 'behaviour.fixed.php')));
});
