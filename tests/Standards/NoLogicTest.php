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
 *   - drop the `__construct` name check — passing.php reddens on 158, 170, 180,
 *     184 and 306: the body statements of the anonymous class's `run()` and of
 *     `__constructor()`, then the `if` and the `foreach` of `configure()`, each
 *     at its opening keyword because a block construct is reported once and its
 *     nested body is never examined separately; and failing.php gains 129, 134,
 *     139, 238 and 277, the body statements of the helper methods beside the
 *     constructors
 *   - match `__construct` case-sensitively — failing.php loses 180, the body
 *     of `__CONSTRUCT`
 *   - accept every parenthesis in the assignment target (drop the call check
 *     outright) — failing.php loses 114, 115, 116, 267, 268, 269, 270, 271 and
 *     272
 *   - reject every parenthesis, grouping or not — passing.php reddens on 269
 *     through 286, every statement of the grouping-parenthesis constructor
 *   - drop any single entry from GROUPING_PARENTHESIS_PRECEDERS — its own probe
 *     in "accepts a grouping parenthesis after every listed preceder" reddens,
 *     and nothing else does. Run for all 47 entries; all 47 killed, so the
 *     enumeration carries no member the suite leaves untested. Dropping
 *     T_OPEN_SQUARE_BRACKET, the one entry the fixtures also reach, additionally
 *     reddens passing.php 269 and 270, the two subscript keys whose grouping
 *     parenthesis sits immediately after the `[`
 *   - accept a write in the assignment target (drop the rejection outright) —
 *     failing.php loses 302, 303, 304, 305, 306 and 307, and every dataset of
 *     "flags every writing assignment target" reddens
 *   - drop T_INC and T_DEC from WRITING_TOKENS — failing.php loses 303 and 304,
 *     the two increments no assignment operator covers
 *   - stop exempting T_DOUBLE_ARROW — passing.php reddens on 285 and 333, the
 *     two array literals dereferenced for a subscript key, and the
 *     T_DOUBLE_ARROW grouping probe reddens with them
 *   - test the write rejection before the statement's own `=` rather than after
 *     — passing.php reddens on 47 lines and failing.php gains 5, because every
 *     property assignment there is rejected on its own operator; the ordering is
 *     what separates the target from the assignment
 *   - drop INVOKING_TOKENS entirely — failing.php loses 221 and 265
 *   - drop T_NEW and T_CLONE from it — failing.php loses 265, the `clone` whose
 *     only parenthesis is a grouping one, so the call check cannot see it.
 *     264, the anonymous class, is *not* lost: its body carries `public int $k
 *     = 1;`, a write inside the target, which the write rejection catches on
 *     its own. The keyword is pinned by its own probe instead — see
 *     INVOKING_PROBES, where each spelling stands alone
 *   - drop any single entry from INVOKING_TOKENS — its own probe in "flags
 *     every invoking token in an assignment target" reddens, and no other
 *     dataset does. Run for all 12 entries; all 12 killed
 *   - drop the call-parenthesis rejection — "flags eval in an assignment target
 *     through the call scan" reddens, which is what pins `eval` staying flagged
 *     after it left INVOKING_TOKENS
 *   - drop any single entry from BLOCK_STATEMENT_TOKENS — its own probe in
 *     "ends a block statement at its own structure" reddens. Two entries redden
 *     more than their own, because other probes are built on them: T_IF also
 *     reddens T_ELSEIF and T_ELSE, T_TRY also reddens T_CATCH and T_FINALLY.
 *     Run for all 13; 12 killed and one equivalent —
 *   - T_DO — **survives**: groupCloser() jumps the loop body by the same
 *     `scope_closer` the block path reads, then the semicolon scan lands on the
 *     `;` after `while (…)`, which is where the block path ends too. Measured
 *     against four shapes (braced, brace-less, nested, and holding an
 *     alternative-syntax construct); none separates them. Recorded as an
 *     equivalent mutation rather than a killed one, so the absence of a
 *     reddening probe is not mistaken for missing coverage. The entry stays
 *     because `do … while` is a block statement by classification
 *   - T_CLASS, T_INTERFACE, T_TRAIT and T_ENUM left BLOCK_STATEMENT_TOKENS
 *     rather than gaining probes: PHP rejects each of them inside a class
 *     member with "Class declarations may not be nested" (read off `php -l`,
 *     not assumed), and this sniff only inspects a constructor inside an OO
 *     container, so no source can put one at a statement it walks. `new class
 *     { … }` is T_ANON_CLASS, not T_CLASS. A nested *function* is legal there,
 *     and keeps its entry and its probe
 *   - drop any single entry from ALTERNATIVE_SYNTAX_CLOSERS — its own probe
 *     reddens and no other. Run for all 6, including T_ENDDECLARE; all killed
 *   - drop any single entry from CONTINUATION_KEYWORDS — its own probe reddens
 *     (T_CATCH also reddens T_TRY, whose probe carries a catch clause). All 4
 *     killed
 *   - drop any single entry from BRACKET_OPENERS or BRACKET_CLOSERS — the
 *     bracket-depth probe of that bracket kind reddens, opener and closer
 *     alike, because either one unbalances the count. The square-bracket pair
 *     reddens all eight datasets, every probe being a subscript. All 8 killed
 *   - drop either entry from INTERPOLATABLE_STRING_TOKENS — its own probe in
 *     "flags a call hidden in every interpolatable string token" reddens
 *   - add a token list to the sniff without an entry in NO_LOGIC_ENUMERATIONS —
 *     "claims every hand-enumerated token list in the sniff" reddens
 *   - append an attribute name no token carries to GROUP_CLOSER_KEYS —
 *     "carries only group-closer attributes the tokeniser emits" reddens, and
 *     nothing else does. Run with 'bogus_closer'; killed
 *   - empty GROUP_CLOSER_KEYS — the same test reddens on its non-empty
 *     assertion, alongside the fixture and package-source tests the emptied
 *     jump breaks
 *   - drop a single entry from GROUP_CLOSER_KEYS — measured one at a time:
 *     `bracket_closer` reddens "flags every non-assignment statement once, at
 *     its first token" and the membership check below it; `scope_closer`
 *     reddens the membership check alone, no report moving; `parenthesis_closer`
 *     reddens nothing, the one equivalent mutation of the three. Which key
 *     carries a report is the sniff's own docblock subject
 *   - rename an entry of GROUP_CLOSER_KEYS — a renamed key is carried by no
 *     token, so "carries only group-closer attributes the tokeniser emits"
 *     reddens for all three, and whatever the dropped spelling also reddens
 *     comes with it: `bracket_closer` three tests, `scope_closer` two,
 *     `parenthesis_closer` this one alone
 *   - remove a probe from any probe set — "probes every member of every
 *     hand-enumerated token list" reddens for the list that set covers
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
 *   - drop T_BACKTICK from INVOKING_TOKENS — failing.php loses 221
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
 *   - drop the `(?<!\\)` lookbehind on `{$` — passing.php reddens on 240 and
 *     241, the one- and three-backslash runs PHP does not interpolate
 *   - strip no escapes at all — passing.php reddens on 239, where a real
 *     interpolation (`$key`) puts the token in scope and the escaped
 *     `\${literal}` beside it is then read as the syntax that would be rejected
 *   - swap the escape strip for a `(?<!\\)` lookbehind on both syntaxes, the
 *     way the sibling ShortVariableSniff matches — failing.php loses 226 and
 *     228: `\\` escapes only itself, so both openers after it interpolate,
 *     while a lookbehind with no strip reads that backslash as escaping them
 *   - broaden the escape strip from `\\`/`\$` to every `\X` pair — **survives**:
 *     with the lookbehind in place the two spellings agree on every fixture and
 *     on the one-to-four-backslash runtime probes, because the only pair the
 *     broader class adds before an opener is `\{`, which the lookbehind already
 *     rejects. Recorded as an equivalent mutation rather than a killed one, so
 *     the absence of a reddening fixture is not mistaken for missing coverage.
 *     The narrow class stays because it is what PHP's escape rules say.
 *   - scan past the assignment operator, inspecting the right-hand side too —
 *     passing.php reddens on 147, 154, 155 and 301, the closure, arrow function
 *     and anonymous class held on a right-hand side, and the right-hand-side
 *     interpolation that really does hold a call
 *   - count the accesses in the assignment target and reject the second hop —
 *     passing.php reddens on 360 and 361, and "leaves a multi-hop assignment
 *     target to the chained-fetch sniff" reddens with it. Allowing exactly two
 *     hops instead reddens 361 alone, which is why both depths are in the
 *     fixture
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Constructors\NoLogicSniff;
use PHP_CodeSniffer\Util\Tokens;

const NO_LOGIC = 'CleanCode.Constructors.NoLogic';

const NO_LOGIC_FOUND = NO_LOGIC . '.LogicFound';

/**
 * The sibling sniff this one defers a multi-hop assignment target to. Spelled
 * here rather than read from DisallowChainedPropertyFetchTest.php: a constant
 * that file declares is only defined once PHPUnit has loaded it, and nothing
 * orders these two suites.
 */
const NO_LOGIC_CHAINED = 'CleanCode.Models.DisallowChainedPropertyFetch';

const NO_LOGIC_CHAINED_ERROR = NO_LOGIC_CHAINED . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_LOGIC);
});

/**
 * passing.php carries the compliant form of the construct the sniff registers
 * on — a constructor that only assigns to its own properties, promoted or in
 * the body, with `??`/ternary defaults, subscript writes, and subscript keys
 * that compute without writing, delegating to
 * `parent::__construct(…)` in any spelling that really invokes, or empty — plus
 * every near-miss shape the sniff must stay silent on: a file-scope
 * `function __construct()`, a `__constructor()` method, an ordinary method full
 * of logic, an abstract and an interface constructor with no body, a trait
 * constructor, logic held inside a closure, an arrow function and an anonymous
 * class on an assignment's right-hand side, and a multi-hop assignment target,
 * which the target scan accepts because it rejects tokens rather than shapes —
 * see "leaves a multi-hop assignment target to the chained-fetch sniff" below,
 * which pins both halves of that deferral.
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
        ['line' => 264, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // new anonymous class
        ['line' => 265, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // clone
        ['line' => 266, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // new, named class
        ['line' => 267, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // call on a grouped property
        ['line' => 268, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // call on an array element
        ['line' => 269, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // dynamic method name
        ['line' => 270, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // match
        ['line' => 271, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // immediately-invoked arrow fn
        ['line' => 272, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // isset
        ['line' => 302, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // += in the subscript
        ['line' => 303, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // postfix ++ in the subscript
        ['line' => 304, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // prefix -- in the subscript
        ['line' => 305, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // nested = in the subscript
        ['line' => 306, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // <<= in the subscript
        ['line' => 307, 'column' => 9, 'source' => NO_LOGIC_FOUND],  // ??= in the subscript
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
        ->toContain(226)   // "\\{$this->key()}", two backslashes, so still complex
        ->toContain(227)   // "${resolveKey()}"
        ->toContain(228)   // "\\${resolveKey()}"
        ->toContain(229)   // the multi-line string, reported at its opening line
        ->not->toContain(230)  // never at the fragment the interpolation sits in
        ->toContain(231);  // the heredoc, reported at its opening line
});

/**
 * Backslash parity, both directions, because only one of them is obvious.
 *
 * A backslash before `{$` suppresses the complex opener: PHP reads
 * `"\{$this->prefix}"` as a literal `\{`, the *simple* interpolation
 * `$this->prefix`, and a literal `}` — the call in `"\{$this->key()}"` never
 * runs. So the parity of the run of backslashes decides, and both parities are
 * pinned here rather than one: the even counts stay flagged in failing.php, the
 * odd counts stay silent in passing.php. Each expectation was read off the PHP
 * runtime at one through four backslashes before asserting it.
 *
 * `${…}` is governed by `\$` instead, which is why the compliant `"\${key}"`
 * and the flagged `"\\${resolveKey()}"` sit on opposite sides of the same rule.
 */
it('reads backslash parity the way PHP does, in both directions', function (
    string $key,
    bool $interpolates
): void {
    $source = "<?php\nclass ParityProbe {\nprivate array \$items;\n"
        . "public function __construct(\$value) {\n\$this->items[$key] = \$value;\n}\n"
        . "private function key(): string { return 'k'; } }\n";

    expect(isset(analyzeStdinSource([NO_LOGIC], $source)->getErrors()[5]))->toBe($interpolates);
})->with([
    '{$…}, no backslash'      => ['"{$this->key()}"', true],
    '{$…}, one backslash'     => ['"\\{$this->key()}"', false],
    '{$…}, two backslashes'   => ['"\\\\{$this->key()}"', true],
    '{$…}, three backslashes' => ['"\\\\\\{$this->key()}"', false],
    '{$…}, four backslashes'  => ['"\\\\\\\\{$this->key()}"', true],
    '${…}, no backslash'      => ['"${resolveKey()}"', true],
    '${…}, one backslash'     => ['"\\${resolveKey()}"', false],
    '${…}, two backslashes'   => ['"\\\\${resolveKey()}"', true],
    '${…}, three backslashes' => ['"\\\\\\${resolveKey()}"', false],
]);

/**
 * One probe per entry in GROUPING_PARENTHESIS_PRECEDERS, keyed by the token it
 * covers, so the enumeration cannot carry an entry no test exercises.
 *
 * Each value is the subscript index of `$this->items[…] = $value;`, except the
 * curly brace, whose grouping position is a dynamic property name rather than
 * an index — it is spelled as a whole target below.
 *
 * @var array<string, string>
 */
const GROUPING_PROBES = [
    'T_PLUS' => '+($this->a)',
    'T_MINUS' => '-($this->a)',
    'T_MULTIPLY' => '$this->a * ($this->b)',
    'T_DIVIDE' => '$this->a / ($this->b)',
    'T_MODULUS' => '$this->a % ($this->b)',
    'T_POW' => '$this->a ** ($this->b)',
    'T_BITWISE_AND' => '$this->a & ($this->b)',
    'T_BITWISE_OR' => '$this->a | ($this->b)',
    'T_BITWISE_XOR' => '$this->a ^ ($this->b)',
    'T_BITWISE_NOT' => '~($this->a)',
    'T_SL' => '$this->a << ($this->b)',
    'T_SR' => '$this->a >> ($this->b)',
    'T_STRING_CONCAT' => '$this->prefix . ($this->a)',
    'T_IS_EQUAL' => '$this->a == ($this->b)',
    'T_IS_NOT_EQUAL' => '$this->a != ($this->b)',
    'T_IS_IDENTICAL' => '$this->a === ($this->b)',
    'T_IS_NOT_IDENTICAL' => '$this->a !== ($this->b)',
    'T_IS_GREATER_OR_EQUAL' => '$this->a >= ($this->b)',
    'T_IS_SMALLER_OR_EQUAL' => '$this->a <= ($this->b)',
    'T_GREATER_THAN' => '$this->a > ($this->b)',
    'T_LESS_THAN' => '$this->a < ($this->b)',
    'T_SPACESHIP' => '$this->a <=> ($this->b)',
    'T_BOOLEAN_AND' => '$this->a && ($this->b)',
    'T_BOOLEAN_OR' => '$this->a || ($this->b)',
    'T_BOOLEAN_NOT' => '!($this->a)',
    'T_LOGICAL_AND' => '$this->a and ($this->b)',
    'T_LOGICAL_OR' => '$this->a or ($this->b)',
    'T_LOGICAL_XOR' => '$this->a xor ($this->b)',
    'T_INLINE_THEN' => '$this->a ? ($this->a) : 0',
    'T_INLINE_ELSE' => '$this->a ? 0 : ($this->b)',
    'T_COALESCE' => '$this->a ?? ($this->b)',
    'T_INSTANCEOF' => '$this->other instanceof ($this->prefix)',
    'T_ASPERAND' => '@($this->a)',
    'T_INT_CAST' => '(int) ($this->a)',
    'T_DOUBLE_CAST' => '(float) ($this->a)',
    'T_STRING_CAST' => '(string) ($this->a)',
    'T_ARRAY_CAST' => '(array) ($this->a)',
    'T_OBJECT_CAST' => '(object) ($this->a)',
    'T_BOOL_CAST' => '(bool) ($this->a)',
    'T_UNSET_CAST' => '(unset) ($this->a)',
    'T_BINARY_CAST' => '(binary) ($this->a)',
    'T_OPEN_PARENTHESIS' => '(($this->a))',
    'T_OPEN_SQUARE_BRACKET' => '($this->a)',
    'T_OPEN_SHORT_ARRAY' => '[($this->a)][0]',
    'T_COMMA' => '[$this->a, ($this->b)][0]',
    'T_DOUBLE_ARROW' => '[1 => ($this->a)][1]',
    'T_OPEN_CURLY_BRACKET' => '{($this->prefix)}',
];

/**
 * A parenthesis that only groups invokes nothing, so the write stays a plain
 * property assignment. Removing any single entry from the enumeration reddens
 * its own probe here and nothing else — measured entry by entry, all 47.
 *
 * No assignment operator is probed because none can be reached: the scan ends at
 * the statement's own `=` and rejects a nested one, so neither ever reaches the
 * parenthesis check. `T_EQUAL` was listed and probed until the writing-target
 * rejection made both unreachable; `[$k = ($this->a)]`, the probe it had, is now
 * a violation and is asserted as one under "flags every writing assignment
 * target" below.
 */
it('accepts a grouping parenthesis after every listed preceder', function (string $probe): void {
    $target = str_starts_with($probe, '{') ? "\$this->$probe" : "\$this->items[$probe]";
    $source = "<?php\nclass GroupingProbe { private array \$items; private int \$a = 1;\n"
        . "private int \$b = 2; private string \$prefix = 'p'; private \$other;\n"
        . "public function __construct(\$value) {\n$target = \$value;\n} }\n";

    expect(analyzeStdinSource([NO_LOGIC], $source)->getErrors())->toBe([]);
})->with(GROUPING_PROBES);

/**
 * The enumeration and its probes are kept in step here, so an entry added to
 * GROUPING_PARENTHESIS_PRECEDERS without a probe fails rather than riding along
 * untested — the failure mode the sniff's own docblock warns about.
 */
it('probes every entry in the grouping-preceder enumeration', function (): void {
    $enumerated = (new ReflectionClass(NoLogicSniff::class))
        ->getConstant('GROUPING_PARENTHESIS_PRECEDERS');
    $probed = array_map(constant(...), array_keys(GROUPING_PROBES));

    sort($enumerated);
    sort($probed);

    expect($probed)->toBe($enumerated);
});

/**
 * One probe per token the target scan rejects as a write, keyed by the token it
 * covers, spelled inside a subscript because that is where a write hides from a
 * scan that only looks for the statement's own top-level operator.
 *
 * `T_INC` is probed postfix and `T_DEC` prefix, so the pair covers both
 * positions. `>>>=` (`T_ZSR_EQUAL`) is absent: PHP has no such operator — it
 * comes from PHPCS's JavaScript tokeniser — so no PHP source can spell it.
 *
 * @var array<string, string>
 */
const WRITING_PROBES = [
    'T_EQUAL' => '$this->a = $this->b',
    'T_PLUS_EQUAL' => '$this->a += 1',
    'T_MINUS_EQUAL' => '$this->a -= 1',
    'T_MUL_EQUAL' => '$this->a *= 2',
    'T_DIV_EQUAL' => '$this->a /= 2',
    'T_MOD_EQUAL' => '$this->a %= 2',
    'T_POW_EQUAL' => '$this->a **= 2',
    'T_CONCAT_EQUAL' => '$this->prefix .= "x"',
    'T_AND_EQUAL' => '$this->a &= 1',
    'T_OR_EQUAL' => '$this->a |= 1',
    'T_XOR_EQUAL' => '$this->a ^= 1',
    'T_SL_EQUAL' => '$this->a <<= 1',
    'T_SR_EQUAL' => '$this->a >>= 1',
    'T_COALESCE_EQUAL' => '$this->a ??= 1',
    'T_INC' => '$this->a++',
    'T_DEC' => '--$this->a',
];

/**
 * A write in the target runs on every instantiation, wherever it sits. Each
 * probe is the compliant `$this->items[…] = $value;` shape with only the
 * subscript varied, so the assertion turns on the operator alone.
 */
it('flags every writing assignment target', function (string $probe): void {
    $source = "<?php\nclass WritingProbe { private array \$items; private int \$a = 1;\n"
        . "private int \$b = 2; private string \$prefix = 'p';\n"
        . "public function __construct(\$value) {\n\$this->items[$probe] = \$value;\n} }\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([['line' => 5, 'column' => 1, 'source' => NO_LOGIC_FOUND]]);
})->with(WRITING_PROBES);

/**
 * The rejection set and its probes are kept in step here. The sniff reads
 * PHPCS's assignment family live rather than respelling it, so a token PHP adds
 * later starts being rejected on its own — this fails the day that happens,
 * rather than letting it ride untested, which is the failure mode the repo has
 * paid for before in hand-maintained token lists.
 */
it('probes every token the target scan rejects as a write', function (): void {
    $sniff = new ReflectionClass(NoLogicSniff::class);

    $rejected = array_diff(
        array_merge(
            array_values(Tokens::$assignmentTokens),
            $sniff->getConstant('WRITING_TOKENS')
        ),
        $sniff->getConstant('NON_WRITING_ASSIGNMENT_TOKENS'),
        // PHPCS carries `>>>=` for JavaScript; no PHP source produces it.
        [T_ZSR_EQUAL]
    );
    $probed = array_map(constant(...), array_keys(WRITING_PROBES));

    sort($rejected);
    sort($probed);

    expect($probed)->toBe(array_values($rejected));
});

/**
 * One probe per entry in INVOKING_TOKENS, keyed by the token it covers. Each is
 * a subscript in the otherwise-compliant `$this->items[…] = $value;`, so the
 * assertion turns on the keyword alone.
 *
 * Every entry is spelled without a parenthesis, which is the whole reason the
 * list exists — a spelling that carries one is rejected by the call scan
 * instead, and would pass this probe whether or not the token were listed.
 * `eval` has no parenthesis-free form and is therefore not a member; the probe
 * below it asserts it stays flagged all the same.
 *
 * @var array<string, string>
 */
const INVOKING_PROBES = [
    'T_BACKTICK' => '`hostname`',
    'T_NEW' => 'new InvokingProbeSeed',
    'T_CLONE' => 'clone $this->other',
    'T_EXIT' => 'exit',
    'T_PRINT' => 'print $this->prefix',
    'T_THROW' => 'throw $this->other',
    'T_YIELD' => 'yield $this->prefix',
    'T_YIELD_FROM' => 'yield from $this->items',
    'T_INCLUDE' => 'include $this->prefix',
    'T_INCLUDE_ONCE' => 'include_once $this->prefix',
    'T_REQUIRE' => 'require $this->prefix',
    'T_REQUIRE_ONCE' => 'require_once $this->prefix',
];

/**
 * A keyword that runs code carries no parenthesis for the call scan to
 * classify, so the target scan has to recognise the keyword itself.
 */
it('flags every invoking token in an assignment target', function (string $probe): void {
    $source = "<?php\nclass InvokingProbe { private array \$items; private string \$prefix = 'p';\n"
        . "private \$other;\n"
        . "public function __construct(\$value) {\n\$this->items[$probe] = \$value;\n} }\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([['line' => 5, 'column' => 1, 'source' => NO_LOGIC_FOUND]]);
})->with(INVOKING_PROBES);

/**
 * `eval` is the one invoking keyword PHP gives no parenthesis-free spelling, so
 * it is not in INVOKING_TOKENS. It is still flagged — by the call scan, because
 * `T_EVAL` is not a grouping preceder — and that is pinned here rather than
 * assumed, since removing the entry would otherwise be an unobserved change.
 */
it('flags eval in an assignment target through the call scan', function (): void {
    $source = "<?php\nclass EvalProbe { private array \$items; private string \$prefix = 'p';\n"
        . "private \$other;\n"
        . "public function __construct(\$value) {\n\$this->items[eval(\$this->prefix)] = \$value;\n} }\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([['line' => 5, 'column' => 1, 'source' => NO_LOGIC_FOUND]]);
});

/**
 * One probe per entry in BLOCK_STATEMENT_TOKENS, keyed by the token it covers.
 *
 * Each is a construct whose body holds a semicolon of its own, so a scan that
 * ended the statement at the next `;` would stop inside it and desynchronise
 * the walk — which is exactly what the assertion catches, by requiring the
 * statement *after* the construct to still be reported on its own line.
 *
 * The four continuation keywords are probed through the construct they
 * continue, because that is the only way a statement reaches them.
 *
 * @var array<string, string>
 */
const BLOCK_STATEMENT_PROBES = [
    'T_IF' => "if (\$value) {\n    \$this->x();\n}",
    'T_ELSEIF' => "if (\$value) {\n    \$this->x();\n} elseif (\$value) {\n    \$this->y();\n}",
    'T_ELSE' => "if (\$value) {\n    \$this->x();\n} else {\n    \$this->y();\n}",
    'T_FOR' => "for (\$i = 0; \$i < 1; \$i++) {\n    \$this->x();\n}",
    'T_FOREACH' => "foreach ([] as \$v) {\n    \$this->x();\n}",
    'T_WHILE' => "while (\$value) {\n    \$this->x();\n}",
    'T_DO' => "do {\n    \$this->x();\n} while (\$value);",
    'T_SWITCH' => "switch (\$value) {\n    default:\n        \$this->x();\n}",
    'T_TRY' => "try {\n    \$this->x();\n} catch (Throwable \$e) {\n    \$this->y();\n}",
    'T_CATCH' => "try {\n    \$this->x();\n} catch (Throwable \$e) {\n    \$this->y();\n}",
    'T_FINALLY' => "try {\n    \$this->x();\n} finally {\n    \$this->y();\n}",
    'T_DECLARE' => "declare(ticks=1) {\n    \$this->x();\n}",
    'T_FUNCTION' => "function blockProbeHelper()\n{\n    echo 1;\n}",
];

/**
 * The alternative syntax of the same constructs, one probe per entry in
 * ALTERNATIVE_SYNTAX_CLOSERS. PHPCS records the `endif`/`endforeach`/… keyword
 * as the clause's `scope_closer`, and PHP requires a `;` after it that belongs
 * to the same statement — treating that `;` as a statement of its own reports a
 * stray token, which the assertion catches as an extra reported line.
 *
 * @var array<string, string>
 */
const ALTERNATIVE_SYNTAX_PROBES = [
    'T_ENDIF' => "if (\$value):\n    \$this->x();\nendif;",
    'T_ENDFOR' => "for (\$i = 0; \$i < 1; \$i++):\n    \$this->x();\nendfor;",
    'T_ENDFOREACH' => "foreach ([] as \$v):\n    \$this->x();\nendforeach;",
    'T_ENDWHILE' => "while (\$value):\n    \$this->x();\nendwhile;",
    'T_ENDSWITCH' => "switch (\$value):\n    default:\n        \$this->x();\nendswitch;",
    'T_ENDDECLARE' => "declare(ticks=1):\n    \$this->x();\nenddeclare;",
];

/**
 * A block statement is reported once, at its opening keyword, and ends where
 * its own structure ends — so the statement after it is reported separately
 * rather than swallowed.
 *
 * Two lines and exactly two: the construct at line 6, and `$this->tail();` on
 * the line after the construct's last. A statement that ended early reports
 * stray tokens in between; one that ended late loses the tail.
 */
it('ends a block statement at its own structure, not at a semicolon inside it', function (
    string $probe
): void {
    $tail = 6 + substr_count($probe, "\n") + 1;
    $body = '        ' . str_replace("\n", "\n        ", $probe);
    $source = "<?php\nclass BlockProbe\n{\n    public function __construct(\$value)\n    {\n"
        . $body . "\n        \$this->tail();\n    }\n\n"
        . "    private function x(): void {}\n\n    private function y(): void {}\n\n"
        . "    private function tail(): void {}\n}\n";

    expect(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()))
        ->toBe([
            ['line' => 6, 'column' => 9, 'source' => NO_LOGIC_FOUND],
            ['line' => $tail, 'column' => 9, 'source' => NO_LOGIC_FOUND],
        ]);
})->with(BLOCK_STATEMENT_PROBES + ALTERNATIVE_SYNTAX_PROBES);

/**
 * One probe per entry in BRACKET_OPENERS and BRACKET_CLOSERS, keyed by the token
 * it covers. The two lists only maintain the bracket depth at which the
 * statement's own `=` is recognised, so an opener and its closer share a probe:
 * dropping either unbalances the count, and the compliant target below is then
 * read as having no top-level `=` and reported.
 *
 * @var array<string, string>
 */
const BRACKET_DEPTH_PROBES = [
    'T_OPEN_SQUARE_BRACKET' => "\$this->items['k']",
    'T_CLOSE_SQUARE_BRACKET' => "\$this->items['k']",
    'T_OPEN_SHORT_ARRAY' => '$this->items[[1, 2][0]]',
    'T_CLOSE_SHORT_ARRAY' => '$this->items[[1, 2][0]]',
    'T_OPEN_PARENTHESIS' => '$this->items[($this->a)]',
    'T_CLOSE_PARENTHESIS' => '$this->items[($this->a)]',
    'T_OPEN_CURLY_BRACKET' => '$this->{$this->prefix}',
    'T_CLOSE_CURLY_BRACKET' => '$this->{$this->prefix}',
];

it('counts bracket depth so a compliant target keeps its top-level operator', function (
    string $target
): void {
    $source = "<?php\nclass BracketProbe { private array \$items; private int \$a = 1;\n"
        . "private string \$prefix = 'p';\n"
        . "public function __construct(\$value) {\n$target = \$value;\n} }\n";

    expect(analyzeStdinSource([NO_LOGIC], $source)->getErrors())->toBe([]);
})->with(BRACKET_DEPTH_PROBES);

/**
 * One probe per entry in INTERPOLATABLE_STRING_TOKENS, keyed by the token it
 * covers. PHPCS hands each of these over as opaque text, so the call spelled
 * inside carries no parenthesis and the token type is what puts the string in
 * scope for the text check.
 *
 * @var array<string, string>
 */
const INTERPOLATION_PROBES = [
    'T_DOUBLE_QUOTED_STRING' => '"{$this->key()}"',
    'T_HEREDOC' => "<<<KEY\n{\$this->key()}\nKEY",
];

it('flags a call hidden in every interpolatable string token', function (string $probe): void {
    $source = "<?php\nclass InterpolationProbe { private array \$items;\n"
        . "public function __construct(\$value) {\n\$this->items[$probe] = \$value;\n"
        . "}\nprivate function key(): string { return 'k'; } }\n";

    expect(array_column(tuplesFromMessages(analyzeStdinSource([NO_LOGIC], $source)->getErrors()), 'line'))
        ->toBe([4]);
})->with(INTERPOLATION_PROBES);

/**
 * Every hand-enumerated token list in the sniff, mapped to the probe set that
 * exercises its members one by one.
 *
 * A member with no probe is the defect shape this file has been bounced for
 * repeatedly: the list keeps working by accident, and an entry that stops
 * mattering — or never did — rides along unnoticed. The two tests below turn
 * that into a failure: one requires every list to appear here, the other
 * requires every member of every list to have a probe.
 *
 * Several lists share a probe set, because one probe covers a member of each:
 * the continuation keywords are reached through the block statements that carry
 * them, `T_INC`/`T_DEC` sit in the write-rejection set beside the assignment
 * family, and `T_DOUBLE_ARROW` is exempted from that set by the grouping probe
 * that reads an array literal.
 *
 * @var array<string, string>
 */
const NO_LOGIC_ENUMERATIONS = [
    'ALTERNATIVE_SYNTAX_CLOSERS' => 'ALTERNATIVE_SYNTAX_PROBES',
    'BLOCK_STATEMENT_TOKENS' => 'BLOCK_STATEMENT_PROBES',
    'BRACKET_CLOSERS' => 'BRACKET_DEPTH_PROBES',
    'BRACKET_OPENERS' => 'BRACKET_DEPTH_PROBES',
    'CONTINUATION_KEYWORDS' => 'BLOCK_STATEMENT_PROBES',
    'GROUPING_PARENTHESIS_PRECEDERS' => 'GROUPING_PROBES',
    'INTERPOLATABLE_STRING_TOKENS' => 'INTERPOLATION_PROBES',
    'INVOKING_TOKENS' => 'INVOKING_PROBES',
    'NON_WRITING_ASSIGNMENT_TOKENS' => 'GROUPING_PROBES',
    'WRITING_TOKENS' => 'WRITING_PROBES',
];

/**
 * A list added to the sniff without an entry above fails here, so the guard
 * cannot be outgrown. A list is recognised by its contents — every member the
 * value of a defined `T_…` constant — rather than by its name, so renaming one
 * does not slip it past.
 *
 * GROUP_CLOSER_KEYS is the one array constant that holds token *attribute*
 * names rather than token types. It is named explicitly so that it stays the
 * only one: a second such list would fail here and have to be accounted for.
 * Its own members get the same per-member treatment two tests below, in
 * "carries only group-closer attributes the tokeniser emits" — a probe set of
 * `T_…` constants cannot reach an attribute name, so the guard is a separate
 * test rather than another NO_LOGIC_ENUMERATIONS entry.
 */
it('claims every hand-enumerated token list in the sniff', function (): void {
    $tokenValues = array_filter(
        get_defined_constants(),
        static fn (string $name): bool => str_starts_with($name, 'T_'),
        ARRAY_FILTER_USE_KEY
    );

    $lists = ['tokens' => [], 'other' => []];

    foreach ((new ReflectionClass(NoLogicSniff::class))->getReflectionConstants() as $constant) {
        $value = $constant->getValue();

        if (is_array($value) === false) {
            continue;
        }

        $isTokenList = $value !== [] && array_diff($value, array_values($tokenValues)) === [];
        $lists[$isTokenList ? 'tokens' : 'other'][] = $constant->getName();
    }

    sort($lists['tokens']);

    expect($lists['tokens'])->toBe(array_keys(NO_LOGIC_ENUMERATIONS))
        ->and($lists['other'])->toBe(['GROUP_CLOSER_KEYS']);
});

/**
 * Every member of every list has a probe. The probe set may be larger than the
 * list it covers — one set covers two lists in three cases — so the assertion
 * is that nothing is left unprobed, not that the two are equal. The two lists
 * with a set of their own keep their exact-equality tests above.
 */
it('probes every member of every hand-enumerated token list', function (
    string $enumeration,
    string $probeSet
): void {
    $members = (new ReflectionClass(NoLogicSniff::class))->getConstant($enumeration);
    $probed = array_map(constant(...), array_keys(constant($probeSet)));

    expect($members)->not->toBe([])
        ->and(array_values(array_diff($members, $probed)))->toBe([]);
})->with(array_map(
    static fn (string $enumeration, string $probeSet): array => [$enumeration, $probeSet],
    array_keys(NO_LOGIC_ENUMERATIONS),
    array_values(NO_LOGIC_ENUMERATIONS)
));

/**
 * The corpus both group-closer tests read: every group-opening shape an
 * assignment can carry — a closure in an argument list, a `match`, an anonymous
 * class, an arrow function, and a `for` header.
 *
 * @var callable(): string
 */
$groupCloserProbeSource = static function (): string {
    return "<?php\nclass CloserProbe { private array \$items; private \$other;\n"
        . "public function __construct(\$value) {\n"
        . "\$this->items = array_map(function (\$i) { \$x = \$i; return \$x; }, [1, 2]);\n"
        . "\$this->other = match (\$value) { default => 1 };\n"
        . "\$this->anon = new class { public function m() { \$y = 1; return \$y; } };\n"
        . "\$this->fn = fn (\$i) => \$i;\n"
        . "for (\$i = 0; \$i < 1; \$i++) { \$this->items[] = \$i; }\n} }\n";
};

/**
 * GROUP_CLOSER_KEYS carries two attributes that change no reported line today —
 * measured, by dropping each and running this suite. Dropping `scope_closer`
 * does redden this test, on the membership check below rather than on any
 * report; the sniff's own docblock records which key carries a line.
 * They are kept because each
 * is the attribute its own kind of token carries, and the redundancy belongs to
 * PHPCS's tokeniser rather than to this sniff. This pins the two guarantees the
 * redundancy rests on, so a tokeniser change reopens the question here instead
 * of silently making a dropped key matter:
 *
 * - a scope-owning `{` carries a `bracket_closer` equal to its `scope_closer`,
 *   which is why `scope_closer` is never the key that jumps a body;
 * - a `;` inside a parenthesis group is also inside such a `{`, unless the
 *   group is a `for` header — which a block statement's own scope ends before
 *   the semicolon scan is ever reached.
 *
 * The corpus is $groupCloserProbeSource above. Both counts are asserted
 * non-zero, so neither loop can pass by finding nothing to check.
 *
 * The two keys the guarantees speak about are read out of the constant rather
 * than named only in this prose, so removing or renaming either one reddens
 * here instead of leaving an invariant pinned for a key the sniff no longer
 * consults.
 */
it('keeps the group-closer keys honest about what the tokeniser guarantees', function () use (
    $groupCloserProbeSource
): void {
    $keys = (new ReflectionClass(NoLogicSniff::class))->getConstant('GROUP_CLOSER_KEYS');

    expect($keys)->toContain('bracket_closer')
        ->and($keys)->toContain('scope_closer');

    $tokens = analyzeStdinSource([NO_LOGIC], $groupCloserProbeSource())->getTokens();
    $scopedBraces = 0;
    $nestedSemicolons = 0;

    $bracedBetween = static function (array $tokens, int $from, int $to): bool {
        for ($ptr = $from + 1; $ptr < $to; $ptr++) {
            if (
                $tokens[$ptr]['code'] === T_OPEN_CURLY_BRACKET
                && ($tokens[$ptr]['bracket_closer'] ?? 0) > $to
            ) {
                return true;
            }
        }

        return false;
    };

    foreach ($tokens as $ptr => $token) {
        if ($token['code'] === T_OPEN_CURLY_BRACKET && isset($token['scope_closer'])) {
            $scopedBraces++;

            expect($token['bracket_closer'] ?? null)->toBe($token['scope_closer']);
        }

        if ($token['code'] !== T_SEMICOLON || ($token['nested_parenthesis'] ?? []) === []) {
            continue;
        }

        $nestedSemicolons++;
        $opener = array_key_last($token['nested_parenthesis']);

        if ($bracedBetween($tokens, $opener, $ptr) === true) {
            continue;
        }

        expect($tokens[$tokens[$opener]['parenthesis_owner'] ?? $opener]['code'])->toBe(T_FOR);
    }

    expect($scopedBraces)->toBeGreaterThan(0)
        ->and($nestedSemicolons)->toBeGreaterThan(0);
});

/**
 * Every member of GROUP_CLOSER_KEYS names an attribute PHPCS really emits, and
 * emits as a *forward* pointer — the only shape groupCloser() can act on.
 *
 * The test above pins what the tokeniser guarantees about the two keys that
 * change no reported line. It says nothing about a key no token carries at all,
 * and neither does any fixture: groupCloser() skips an unknown key through the
 * same isset() that skips a known one on a token opening no group, so an
 * attribute name appended to the list is invisible to the whole suite. That is
 * the hole this closes from the other side — each member has to be carried,
 * pointing forward, by at least one token of the corpus.
 *
 * The members are read off the constant by reflection and counted one at a
 * time, so a busy key cannot cover for a dead one, and the list is asserted
 * non-empty so an emptied constant cannot pass by having nothing to count.
 *
 * This is the guard NO_LOGIC_ENUMERATIONS gives the token lists. It cannot be
 * that same guard: these are attribute names rather than token types, so no
 * probe set of `T_…` constants can cover them.
 */
it('carries only group-closer attributes the tokeniser emits', function () use (
    $groupCloserProbeSource
): void {
    $keys = (new ReflectionClass(NoLogicSniff::class))->getConstant('GROUP_CLOSER_KEYS');
    $tokens = analyzeStdinSource([NO_LOGIC], $groupCloserProbeSource())->getTokens();
    $carriers = array_fill_keys($keys, 0);

    foreach ($tokens as $pointer => $token) {
        foreach ($keys as $key) {
            if (isset($token[$key]) === true && $token[$key] > $pointer) {
                $carriers[$key]++;
            }
        }
    }

    expect($keys)->not->toBe([]);

    foreach ($carriers as $key => $carried) {
        expect($carried)->toBeGreaterThan(0, "no token in the corpus carries {$key} forward");
    }
});

/**
 * The reading side of the same rule, pinned by line: computing a key reads and
 * discards, which is not a write, and `=>` separates operands inside an array
 * literal rather than writing anything. Each line sits one operator away from a
 * rejected spelling, so a rejection widened from "writes" to "any operator"
 * reddens here.
 */
it('leaves a reading assignment target alone', function (): void {
    $lines = array_column(violationTuples(analyzeFixture(NO_LOGIC, 'passing.php')), 'line');

    expect($lines)
        ->not->toContain(330)  // [$this->total + 1], beside += 1
        ->not->toContain(331)  // [$this->total << 1], beside <<= 1
        ->not->toContain(332)  // [$this->total ?? 2], beside ??= 2
        ->not->toContain(333); // [[1 => $this->a][1]], the `=>` exemption
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
 * Line 301 keeps the rejection on the target side of the assignment operator:
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
        ->not->toContain(301); // "{$this->key()}" on the right-hand side
});

/**
 * A multi-hop assignment target, pinned from both sides.
 *
 * The target scan rejects tokens — calls, writes, invoking keywords — never
 * shapes, so it never counts the accesses in a chain: this sniff is silent on
 * `$this->inner->value = …` and `$this->a->b->c = …` alike. That is the
 * behaviour the sniff's own docblock now describes, and the first half below
 * holds it: a scan that counted hops and rejected the second reddens on 360
 * and 361, one that allowed exactly two reddens on 361 alone.
 *
 * The second half is what keeps the silence honest rather than a hole. The
 * docblock claims the shape is covered by
 * `CleanCode.Models.DisallowChainedPropertyFetch` under Models: Relationship
 * Properties, so that claim is asserted rather than stated: the same two lines
 * report `NO_LOGIC_CHAINED_ERROR` when the composed ruleset runs. That sniff is
 * test-path-excluded in `CleanCode/ruleset.xml`, so the fixture is staged outside the
 * repository first — processed where it lives, it would report nothing and the
 * assertion would prove nothing.
 */
it('leaves a multi-hop assignment target to the chained-fetch sniff', function (): void {
    $staged = stageFixtureOutsideTests(fixturePath('NoLogicSniff', 'passing.php'));
    $sources = allViolationSourcesByLine(analyzeWithSniffs([NO_LOGIC, NO_LOGIC_CHAINED], $staged));

    expect($sources[360] ?? [])->toBe([NO_LOGIC_CHAINED_ERROR])  // $this->inner->value
        ->and($sources[361] ?? [])->toBe([NO_LOGIC_CHAINED_ERROR]); // $this->a->b->c
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

    expect($file->getErrorCount())->toBe(59)
        ->and($file->getWarningCount())->toBe(0);
});

/**
 * Detection only. A fixable flag set anywhere would mean phpcbf believed it
 * could rewrite a constructor the sniff has no safe rewrite for. The count is
 * asserted alongside the flags so the list cannot pass by being empty.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(NO_LOGIC, 'failing.php');

    expect($file->getErrorCount())->toBe(59)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});
