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
 *     reddens passing.php 128, 129, 229 to 234, 237 to 241 and 269 to 286
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
 *   - drop INVOKING_TOKENS entirely — failing.php loses 221, 264 and 265
 *   - drop T_NEW and T_CLONE from it — failing.php loses 264 and 265, the two
 *     spellings whose only parenthesis is a grouping one, so the call check
 *     cannot see them
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
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Sniffs\Constructors\NoLogicSniff;
use PHP_CodeSniffer\Util\Tokens;

const NO_LOGIC = 'CleanCode.Constructors.NoLogic';

const NO_LOGIC_FOUND = NO_LOGIC . '.LogicFound';

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
