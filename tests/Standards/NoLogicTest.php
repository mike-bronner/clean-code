<?php

/**
 * Tests the custom CleanCode.Constructors.NoLogic sniff (Constructors: No Logic
 * in Constructors, #40). Fixtures live in tests/fixtures/NoLogicSniff/.
 *
 * The sniff is detection-only: relocating logic out of a constructor needs a
 * deliberate destination — a named constructor, a factory, or a collaborator
 * object — which no token-based fixer can invent. So there is no autofixed
 * fixture, and the tests below prove every reported violation is non-fixable.
 *
 * Each of the sniff's decisions was mutated against these fixtures and the
 * result read off the sniff's own output, rather than assumed. Every mutation
 * below was run; what it does to the reported lines is recorded exactly:
 *
 *   - drop the OO-scope gate — passing.php reddens on 33 and 35, the body of
 *     the file-scope `function __construct()`
 *   - drop the `__construct` name check — passing.php reddens on 158, 170, 180
 *     and 184: the body statements of the anonymous class's `run()` and of
 *     `__constructor()`, then the `if` and the `foreach` of `configure()`, each
 *     at its opening keyword because a block construct is reported once and its
 *     nested body is never examined separately; and failing.php gains 129, 134
 *     and 139, the body statements of the helper methods beside the constructor
 *   - match `__construct` case-sensitively — failing.php loses 180, the body
 *     of `__CONSTRUCT`
 *   - accept a call parenthesis in the assignment target — failing.php loses
 *     114, 115 and 116
 *   - stop consuming continuation clauses — failing.php gains 26, 28, 49, 51,
 *     56, 78 and 80, reporting one construct once per clause
 *   - drop the `do … while` tail branch — failing.php gains 42
 *   - drop the two-word `else if` delegation — failing.php loses 59: the
 *     `else` ends on a `;` inside its own body and the walk desynchronises
 *   - send every statement to the semicolon scan (drop the block-statement
 *     path) — failing.php loses 31, 34, 37, 40, 47, 54 and 59, each swallowed
 *     by the construct before it
 *   - drop the alternative-syntax closer handling — failing.php gains 82, 85,
 *     88, 91 and 95, the `;` after each `endif`/`endforeach`/`endfor`/
 *     `endwhile`/`endswitch`
 *   - drop the trailing-token check on the parent call — failing.php loses
 *     157 and 158
 *   - drop the argument-list requirement on the parent call — failing.php
 *     loses 159, the bare `parent::__construct` reference
 *   - drop the first-class-callable rejection on the parent call —
 *     failing.php loses 160, the `parent::__construct(...)` that builds a
 *     Closure instead of delegating
 *   - treat the ellipsis alone as the first-class-callable syntax, without
 *     checking what follows it — passing.php reddens on 99, the spread
 *     delegation `parent::__construct(...$args)`
 *   - carve out a statement that opens with `[` — failing.php loses 197, the
 *     list destructuring into properties
 *   - accept a backtick in the assignment target — failing.php loses 221
 *   - accept an interpolated string in the assignment target (drop the check
 *     outright) — failing.php loses 222, 223, 224, 225, 226, 227, 228, 229
 *     and 231
 *   - drop T_HEREDOC from the interpolatable-string list — failing.php loses
 *     231, the heredoc key
 *   - drop T_DOUBLE_QUOTED_STRING from that list — failing.php loses 222, 223,
 *     224, 225, 226, 227, 228 and 229
 *   - detect `{$…}` only — failing.php loses 227 and 228, the two keys whose
 *     call hides in a `${…}` with no `{$` anywhere in it
 *   - detect `${…}` only — failing.php loses 222, 224, 225, 226, 229 and 231
 *   - strip every backslash escape before looking, rather than only `\\` and
 *     `\$` — failing.php loses 226: `\{` is not an escape sequence, so
 *     `"\{$this->prefix}"` really does interpolate and stripping its backslash
 *     hides the `{$`
 *   - strip no escapes at all — passing.php reddens on 239, where a real
 *     interpolation (`$key`) puts the token in scope and the escaped
 *     `\${literal}` beside it is then read as the syntax that would be rejected
 *   - swap the escape strip for the `(?<!\\)` lookbehind the sibling
 *     ShortVariableSniff uses — failing.php loses 228: `\\` escapes only itself,
 *     so the `${…}` after it interpolates while the lookbehind reads that
 *     backslash as escaping the `$`
 *   - run the interpolation check over the whole statement instead of the
 *     target — passing.php reddens on 147, 154, 155 and 254, the closure, arrow
 *     function and anonymous class held on a right-hand side, and the
 *     right-hand-side interpolation that really does hold a call
 */

declare(strict_types=1);

const NO_LOGIC = 'CleanCode.Constructors.NoLogic';

const NO_LOGIC_FOUND = NO_LOGIC . '.LogicFound';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_LOGIC);
});

/**
 * passing.php carries the compliant form of the construct the sniff registers
 * on — a constructor that only assigns to its own properties, promoted or in
 * the body, with `??`/ternary defaults and subscript writes, delegating to
 * `parent::__construct(…)` in any spelling that really invokes, or empty — plus
 * every near-miss shape the sniff must stay silent on: a file-scope
 * `function __construct()`, a `__constructor()` method, an ordinary method full
 * of logic, an abstract and an interface constructor with no body, a trait
 * constructor, and logic held inside a closure, an arrow function and an
 * anonymous class on an assignment's right-hand side.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The whole expectation in one list, so a statement that stops being reported
 * and a statement that starts being reported both fail here.
 *
 * The lines absent from this list are the point of the assertion: the property
 * assignment opening each constructor, every statement nested inside a flagged
 * construct, the continuation clauses (26 `elseif`, 28 `else`, 42 the
 * `do … while` tail, 49 `catch`, 51 `finally`, 56 the two-word `else if`, 78
 * `elseif`, 80 `else`), the `;` closing each alternative-syntax construct, and
 * the exact `parent::__construct(…)` delegation in passing.php.
 */
it('flags every non-assignment statement once, at its first token', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 24, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // if … elseif … else
        ['line' => 31, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // for
        ['line' => 34, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // foreach
        ['line' => 37, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // while
        ['line' => 40, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // do … while
        ['line' => 43, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // switch
        ['line' => 47, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // try … catch … finally
        ['line' => 54, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // if … else if
        ['line' => 59, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // match
        ['line' => 62, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // free { … } block
        ['line' => 65, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // return
        ['line' => 76, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // if … endif
        ['line' => 83, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // foreach … endforeach
        ['line' => 86, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // for … endfor
        ['line' => 89, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // while … endwhile
        ['line' => 92, 'column' => 9, 'source' => NO_LOGIC_FOUND],   // switch … endswitch
        ['line' => 110, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // method call
        ['line' => 111, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // function call
        ['line' => 112, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // local-variable assignment
        ['line' => 113, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // increment
        ['line' => 114, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // call in the assignment target
        ['line' => 115, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // call in the subscript index
        ['line' => 116, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // chained call, then subscript
        ['line' => 117, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // +=
        ['line' => 118, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // .=
        ['line' => 119, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // ??=
        ['line' => 120, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // throw
        ['line' => 157, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // parent call, then `or …`
        ['line' => 158, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // parent call, then `->…()`
        ['line' => 159, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // bare `parent::__construct`
        ['line' => 160, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // first-class callable `(...)`
        ['line' => 161, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // explicit-ancestor delegation
        ['line' => 180, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // body of `__CONSTRUCT`
        ['line' => 197, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // list destructuring
        ['line' => 221, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // backtick shell execution
        ['line' => 222, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // call inside `{$…}`
        ['line' => 223, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // call inside `${$…}`
        ['line' => 224, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // `{$…}` without a call
        ['line' => 225, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // interpolation mid-string
        ['line' => 226, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // `\{` is not an escape
        ['line' => 227, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // call inside `${…}`
        ['line' => 228, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // `\\` escapes only itself
        ['line' => 229, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // multi-line string
        ['line' => 231, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // heredoc
    ]);
});

/**
 * The invoking-assignment-target family, stated as its own assertion so a
 * regression in one spelling names itself instead of drowning in the list above.
 *
 * Only the parenthesis of `$this->items[$this->key()]` (line 115) is visible to
 * a token scan. A backtick executes a shell command carrying no parenthesis at
 * all, and PHPCS collapses an interpolated string into one opaque token — one
 * per *physical line*, so line 229's interpolation lives in the fragment on 230
 * and the statement is still reported at its first token — leaving the call
 * inside it invisible. Both interpolation syntaxes are covered because either
 * one alone carries a call: `${resolveKey()}` (227) holds one with no `{$` in
 * it, and `{$this->key()}` (222) one with no `${`.
 */
it('flags every spelling of an invoking assignment target', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'failing.php')), 'line');

    expect($lines)
        ->toContain(221)   // `hostname` backtick
        ->toContain(222)   // "{$this->key()}"
        ->toContain(223)   // "${$this->key()}"
        ->toContain(224)   // "{$this->prefix}", no call to find
        ->toContain(225)   // "prefix {$this->key()} suffix"
        ->toContain(226)   // "\{$this->prefix}"
        ->toContain(227)   // "${resolveKey()}"
        ->toContain(228)   // "\\${resolveKey()}"
        ->toContain(229)   // the multi-line string, reported at its opening line
        ->not->toContain(230)  // never at the fragment the interpolation sits in
        ->toContain(231);  // the heredoc, reported at its opening line
});

/**
 * The other side of the same rule, pinned by line because "produces no
 * violations on the compliant fixture" above passes just as well against a
 * sniff that rejects every string in a target.
 *
 * A subscript key that only *reads* stays compliant, whatever it is spelled
 * with: simple interpolation admits no parentheses, and a nowdoc interpolates
 * nothing at all. Line 239 is the one that reaches the check and still has to
 * pass — `$key` interpolates for real, so PHPCS hands the token over as the
 * interpolated kind, while the `\${literal}` beside it is escaped text.
 * Line 254 keeps the rejection on the target side of the assignment operator:
 * that right-hand side really does invoke.
 */
it('leaves a reading assignment target and an invoking right-hand side alone', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'passing.php')), 'line');

    expect($lines)
        ->not->toContain(230)  // "$key"
        ->not->toContain(231)  // "$this->prefix"
        ->not->toContain(234)  // a nowdoc key
        ->not->toContain(237)  // "{\$this->key()}", the dollar escaped
        ->not->toContain(238)  // "\${key}", the dollar escaped
        ->not->toContain(239)  // "$key \${literal}", interpolated *and* escaped
        ->not->toContain(254); // "{$this->key()}" on the right-hand side
});

/**
 * The chained constructs, stated as their own assertion so a regression that
 * reported a clause separately names itself instead of drowning in the list
 * above. `endOfStatement()` is the subtlest part of the sniff, and each of
 * these clause keywords reaches it by a different route: `elseif`/`else`/
 * `catch`/`finally` through the continuation list, the `do … while` tail
 * through its own condition-only branch, `else if` through the two-word
 * delegation, and the alternative syntax through a `scope_closer` that points
 * at the *next* clause's keyword.
 */
it('reports a chained construct once, never per clause', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'failing.php')), 'line');

    expect($lines)
        ->not->toContain(26)   // elseif
        ->not->toContain(28)   // else
        ->not->toContain(42)   // the `while (…);` tail of the do … while
        ->not->toContain(49)   // catch
        ->not->toContain(51)   // finally
        ->not->toContain(56)   // the two-word `else if`
        ->not->toContain(78)   // elseif:
        ->not->toContain(80);  // else:
});

/**
 * The alternative syntax closes on `endif`/`endforeach`/`endfor`/`endwhile`/
 * `endswitch`, each followed by a `;` that belongs to the same statement.
 * Treating that `;` as a statement of its own reports a stray token and
 * desynchronises the walk, so it is pinned here by line.
 */
it('does not report the terminator of an alternative-syntax construct', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'failing.php')), 'line');

    expect($lines)
        ->not->toContain(82)   // endif;
        ->not->toContain(85)   // endforeach;
        ->not->toContain(88)   // endfor;
        ->not->toContain(91)   // endwhile;
        ->not->toContain(95);  // endswitch;
});

it('reports the failing fixture as errors, never warnings', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'failing.php');

    expect($file->getErrorCount())->toBe(44)
        ->and($file->getWarningCount())->toBe(0);
});

/**
 * Detection only. A fixable flag set anywhere would mean phpcbf believed it
 * could rewrite a constructor the sniff has no safe rewrite for. The count is
 * asserted alongside the flags so the list cannot pass by being empty.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'failing.php');

    expect($file->getErrorCount())->toBe(44)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});
