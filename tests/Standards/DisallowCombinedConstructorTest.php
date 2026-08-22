<?php

/**
 * Tests the custom CleanCode.Constructors.DisallowCombinedConstructor sniff
 * (Primary + Named Constructors, #34 — the combined-constructor slice scoped on
 * #193). Fixtures live in tests/fixtures/DisallowCombinedConstructorSniff/.
 *
 * The sniff is detection-only and reports warnings rather than errors:
 * branching in a constructor is a design smell, not always a defect, and
 * splitting one into named constructors rewrites the class's construction API
 * and every call site. So there is no autofixed fixture, and the tests below
 * prove no reported violation is fixable.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 *
 * Every guard in the sniff was mutation-checked against these fixtures, and the
 * result of each mutation is recorded here rather than assumed — each number
 * below was produced by disabling that guard and re-running the fixtures, not
 * derived, and the whole table was re-derived after the last fixture was added
 * rather than adjusted. Counts are warnings per fixture, against the baseline
 * passing 0 / failing 4 / shapes 50; a fixture whose count the mutation leaves
 * unchanged is omitted from its line.
 *
 * Scope of the walk:
 *
 *   - drop the `__construct` name check — passing 13 (the named constructor,
 *     the ordinary method, the `__constructor()` lookalike, and the `make()` of
 *     the anonymous class declared in a constructor body all start reporting.
 *     The file-scope `function __construct()` does not: the class-like-scope
 *     gate below silences it independently of this one)
 *   - drop the class-like-scope gate — passing 3 (the file-scope
 *     `function __construct()` starts reporting)
 *   - drop the nested-declaration skip entirely — passing 11 (the nested named
 *     function, the closure, the arrow function and the anonymous class's
 *     method and property all start reporting)
 *   - drop `T_FUNCTION` alone from the nested-declaration list — passing 3;
 *     `T_CLOSURE` alone — passing 3; `T_FN` alone — passing 1. One declaration
 *     kind per entry
 *   - stop skipping an anonymous class's *body* — passing 1 (the property
 *     defaulted through a constant-expression ternary is read as a mode
 *     switch). The class's *arguments* are deliberately not skipped with it,
 *     and shapes.php's AnonymousClassArguments pins that they still report
 *
 * What counts as a signal:
 *
 *   - drop the "flag or type test" requirement, reporting any parameter in a
 *     condition — passing 32, shapes 57
 *   - drop the `bool`-type leg of the flag test — failing 3, shapes 26
 *   - drop the `true`/`false`-default leg of the flag test — shapes 48 (both
 *     DefaultedModeFlags parameters stop reporting)
 *   - drop the leading-`?` strip in type normalization — shapes 49 (`?bool`
 *     stops being a flag)
 *   - drop the explicit `null` union member from type normalization —
 *     shapes 49 (`bool|null` stops being a flag)
 *   - accept any union *containing* `bool` rather than exactly `bool` —
 *     passing 1 (`bool|string` starts reporting)
 *   - drop the variadic exclusion — passing 1 (`bool ...$flags`)
 *   - drop `instanceof` detection — shapes 45; and one width of the subject at
 *     a time, since the widened subject reaches its own `instanceof`: the
 *     unwrapped subject's test — shapes 47; the widened subject's — shapes 48
 *     (both grouped `instanceof` subjects stop being recognised)
 *   - drop the member/static/`new` qualifier check on a name — passing 6; and
 *     one entry at a time, so no entry rides on a sibling: `T_OBJECT_OPERATOR`
 *     alone — passing 3; `T_DOUBLE_COLON` alone — passing 1; `T_NEW` alone —
 *     passing 1; `T_NULLSAFE_OBJECT_OPERATOR` alone — passing 1
 *   - stop reading a namespace separator — passing 2 (`App\Utils\func_get_args()`
 *     and `App\Validation\is_string()` are taken for the global functions)
 *   - treat every separator as qualifying, rather than only one with a name
 *     segment in front of it — shapes 48 (`\is_string()` and `\func_num_args()`
 *     stop being the global functions they are)
 *   - read the enclosing parentheses outermost-first rather than inside-out —
 *     failing 3, shapes 42 (every predicate applied directly to a parameter
 *     stops being recognised)
 *   - stop widening the subject through a grouping parenthesis — shapes 47 (the
 *     grouped predicate subject and both grouped `instanceof` subjects stop
 *     being recognised)
 *   - widen the subject through *any* enclosing pair rather than only one
 *     holding nothing else — passing 2 (both operands of the grouped
 *     concatenation are read as the subject of the predicate around them)
 *   - drop only the widening test's "the pair opens on the subject" clause —
 *     passing 1; only its "the pair closes on the subject" clause — passing 1
 *   - stop requiring the widened pair to be a *grouping* parenthesis, so a
 *     call's argument list widens the subject too — passing 1 (the call
 *     result's `instanceof` is read as a test of the parameter handed to it).
 *     The same guard is why dropping the bare-first-argument check below now
 *     costs one false positive fewer than it did before the guard landed: a
 *     predicate applied to a nested call's result no longer reaches the
 *     predicate at all
 *
 * Argument totality — that the parameter is the *whole* first argument, each
 * half of the test pinned on its own:
 *
 *   - drop the bare-first-argument check entirely — passing 5, shapes 52
 *   - drop only its "the opening parenthesis precedes it" clause — passing 2,
 *     shapes 52 (the two named arguments start reporting)
 *   - drop only its "a separator or the closer follows it" clause — passing 2
 *     (the property read and the subscripted array start reporting)
 *
 * Comment tolerance — every adjacency test skips `Tokens::$emptyTokens` rather
 * than `T_WHITESPACE` alone. Fourteen of the sixteen flip a verdict when
 * reverted to `T_WHITESPACE`, and each is pinned separately:
 *
 *   - the `instanceof` lookahead — shapes 49 (false negative)
 *   - the predicate-callee lookback — shapes 49 (false negative)
 *   - the grouping-preceder lookback — shapes 49 (false negative: the comment
 *     in front of the twice-grouped `instanceof` subject ends the widening)
 *   - the bare-first-argument lookback and lookahead — shapes 49 each (false
 *     negatives: the comment-wrapped subject stops being the first argument)
 *   - the argument-reader lookahead — shapes 49 (false negative)
 *   - the name-qualifier lookback — passing 2 (false positives: the member
 *     calls named `is_a` and `func_num_args` are read as the global functions)
 *   - the elvis lookahead — passing 1 (false positive: the elvis default is
 *     read as a branch)
 *   - the chain-head lookback — passing 2, and its `else` step-back —
 *     passing 1 (false positives: a mirror guard's chain is cut at the comment
 *     in front of its `elseif` / `else if`, leaving a branch that rejects
 *     nothing)
 *   - the chain's forward walk to the next link — passing 1 (false positive,
 *     the mirror of the above: the rejecting branch behind the comment is
 *     never counted)
 *   - the spaced-`else if` lookahead — passing 1 (false positive)
 *   - the `case`-body lookahead — passing 1 (false positive: a commented
 *     fall-through label is read as a branch that constructs)
 *   - the `throw` lookahead — passing 1 (false positive: a guard whose `throw`
 *     is introduced by a comment stops being a guard)
 *
 * The two that no comment can reach are recorded as observed rather than
 * assumed:
 *
 *   - the qualified-name lookback, because PHP rejects a comment inside a
 *     qualified name outright (`App /* … *\/ \is_string()` is a parse error),
 *     so no fixture can spell the case
 *   - the brace-less branch-end lookahead, because `findEndOfStatement()` is
 *     itself comment-tolerant and returns the same token whichever token it is
 *     handed. Both keep the skip for consistency with the other thirteen
 *
 * Guard clauses:
 *
 *   - drop the exemption for mode flags and type tests — passing 31, shapes 51
 *   - drop the exemption for the argument readers — passing 3 (the braced,
 *     brace-less and ternary guards of GuardedArgumentCount)
 *   - drop the "at least one branch throws" leg — shapes 43 (the empty
 *     `switch` and the two surviving-path constructs start being read as
 *     guards)
 *   - drop the "own branch throws, or one path survives" leg — shapes 47
 *   - keep only the own-branch leg, dropping the mirror — passing 17 (every
 *     guard whose `throw` is on the other side starts reporting)
 *   - keep only the mirror leg, dropping the own-branch one — shapes 51 (the
 *     rejecting `match` arm beside two survivors stops being a guard)
 *   - stop enumerating a `switch`'s cases — passing 5; a `match`'s arms —
 *     passing 5, shapes 51; a ternary's two sides — passing 6
 *   - count a nested construct's `case` labels — passing 1 — or its `match`
 *     arms — passing 1 — as the outer construct's own branches
 *   - read a `case` label as a branch of the outermost `switch` holding it
 *     rather than the innermost — passing 1
 *   - count an empty fall-through `case` as a branch — passing 1
 *   - drop the brace-less fallback to the condition's closing parenthesis —
 *     passing 3 (both brace-less guards and the brace-less argument-reader
 *     guard start reporting); drop the brace-less branch's *end* instead —
 *     passing 1 (the `else` behind it is never found)
 *   - stop walking back to the head of an `if` chain — passing 2, shapes 49;
 *     follow a spaced `else if` to its `else` rather than to the `if` that
 *     owns the condition — passing 1
 *   - ignore ternary nesting when reading a ternary's two sides — passing 1
 *     (the elvis default inside a guard's surviving side is read as the
 *     guard's own second path)
 *
 * Where an expression ends:
 *
 *   - report `?:` as a branch — passing 2 (the elvis default over a mode flag,
 *     and its comment-separated spelling)
 *   - drop `case`-label detection — shapes 49 (`case is_iterable($extra):`)
 *   - drop the match-arm selector — shapes 45
 *   - drop the parenthesised-condition scan — passing 2, failing 2, shapes 28
 *   - drop the `;` terminator — passing 5; the array `=>` terminator —
 *     passing 1; the `{` terminator — passing 1. One boundary per statement of
 *     the ExpressionEnds and NestedGroupEnds fixtures
 *
 * Which tokens the scan reads as its own — a group in the way is jumped whole,
 * and what a comma means is settled by the group holding it:
 *
 *   - drop the group jump entirely — passing 2, shapes 48; and one closer kind
 *     at a time: the `scope_closer` jump — passing 1, shapes 49 (the
 *     alternative-syntax `foreach` starts reporting; the bare `match` operand
 *     stops); the `parenthesis_closer` jump — shapes 49; the `bracket_closer`
 *     jump — passing 1
 *   - treat a comma as an unconditional terminator — shapes 43
 *   - resume at the comma itself rather than at its group's closer —
 *     passing 1 (a ternary in the *sibling* argument on line 240 is read as
 *     the flag's own branch)
 *   - resume at a `match` arm's condition-list comma from the arm list's
 *     closing brace instead of carrying on — shapes 48 (both multi-condition
 *     arms stop reporting)
 *   - stop ending an arm at the comma behind its body — passing 1
 *   - map the commas of a block as though it were an expression group —
 *     passing 1 (the statement-level comma in NestedGroupEnds runs on to the
 *     ternary in the statement after it)
 *   - stop recognising a `match` arm list among braced groups — shapes 48
 *   - drop one group opener at a time from the comma map: the brace —
 *     shapes 48; the parenthesis — shapes 47; the short array — shapes 48
 *   - read a group's end from its `parenthesis_closer` alone — shapes 46 — or
 *     from its `bracket_closer` alone — shapes 47
 *
 * Two guards report no count of their own, and both are stated as observed
 * rather than assumed:
 *
 *   - the three caches — the per-position selector answers, each construct's
 *     branch verdicts, and each `if` chain's head — change only how long the
 *     answers take to reach, so no fixture count moves. Each is pinned by its
 *     own linear-time assertion below instead, one per walk it amortizes.
 *   - removing the bodiless-declaration guard makes PHPCS abort the file with
 *     an `Internal.Exception` error ("Undefined array key scope_opener"), so
 *     passing.php reports one error and no warnings, and this test file's
 *     compliant-fixture case is marked risky by a PHP warning while the suite
 *     still exits 0. The abstract, interface and promotion-only constructors
 *     in passing.php therefore pin that the sniff stays silent and does not
 *     fall over on a declaration with no body, matching how
 *     tests/Standards/DisallowConstructorInstantiationTest pins its own.
 */

declare(strict_types=1);

const COMBINED_CONSTRUCTOR = 'CleanCode.Constructors.DisallowCombinedConstructor';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(COMBINED_CONSTRUCTOR);
});

/**
 * passing.php carries the compliant form of the construct the sniff registers
 * on — one unconditional primary constructor with a named constructor per
 * construction scenario — plus every near-miss shape the sniff must stay silent
 * on: guard clauses in each branching form and for each of the three signals,
 * coalesce defaults over a mode flag,
 * a non-boolean parameter in a condition, a predicate applied to a derived
 * value — through a property read, a subscript, and a named argument as well as
 * a nested call and a grouping parenthesis holding more than the parameter — an
 * `instanceof` applied to a call's result, where a grouping parenthesis would
 * carry the parameter itself, a
 * `bool|string` union that is not a flag, all three signals in
 * a named constructor, an ordinary method, a nested named function, a closure,
 * an arrow function and an anonymous class, member calls named like a predicate
 * and like the argument readers with a comment splitting the object operator,
 * static calls named like the argument readers, a variadic flag, every bodiless
 * constructor shape, and a statement per expression boundary the forward scan
 * must respect — including the far side of each boundary shapes.php crosses: a
 * group whose result no selector follows, a `match` operand ending its own
 * statement, a comma at statement level, and a comma-separated group feeding a
 * guard clause.
 * Dropping any one of the sniff's guards reddens this test.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * One class per signal in failing.php, so each violation code is provoked
 * independently and a code that fell silent could not be masked by its
 * siblings. Every report lands on the parameter being switched on, or on the
 * argument reader's own name — never on the branching keyword.
 */
it('warns once per mode signal, under the signal\'s own code', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect(warningTuples($file))->toBe([
        ['line' => 33, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 51, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 67, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 69, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
    ]);
});

it('reports the failing fixture as warnings, never errors', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect($file->getErrorCount())->toBe(0)
        ->and($file->getWarningCount())->toBe(4);
});

/**
 * Detection only. A fixable count above zero would mean phpcbf silently
 * rewrote a constructor the sniff has no safe rewrite for. getFixableCount() is
 * used rather than violationFixableFlags(), which reads getErrors() only and so
 * would report an empty list for this sniff whatever its fixability.
 */
it('marks no violation fixable', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'failing.php');

    expect($file->getWarningCount())->toBe(4)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * shapes.php is the guard against a token walk that only ever handled the one
 * spelling it was written against. A signal per branching form, per declaration
 * form, and per placement of the argument readers:
 *
 *   33, 35, 37 — `if`, `elseif`, and a *spaced* `else if`. The last is why the
 *                sniff keys on the `T_IF` that owns the parentheses rather than
 *                on the `T_ELSE` in front of it
 *   41         — a `switch` subject
 *   49         — a `match` subject
 *   55         — a `match` arm condition, where the test sits in the branch
 *                rather than in the head
 *   59         — a ternary condition
 *   61         — a brace-less `if`, which carries no scope opener at all
 *   63         — the alternative syntax, whose scope opener is the `:`
 *   77         — `instanceof`
 *   83, 85     — `is_array()` and `is_callable()` in an `if` and an `elseif`
 *   89         — `gettype()` compared to a type name
 *   95         — a predicate in a ternary condition
 *   98         — a predicate in a `match` arm condition
 *  103         — a predicate in a `switch` case label, the `switch` counterpart
 *                of line 98
 *  121,122,125 — the argument readers, which need no branch to be a signal: in
 *                a condition, in a nested `if` body, and spelled in upper case
 *                in a `foreach` subject, since PHP resolves function names
 *                case-insensitively
 *  141         — a constructor declared in a trait, on a *promoted* parameter,
 *                so promotion cannot hide a flag from the body scan
 *  149         — `__CONSTRUCT`, since PHP method names are case-insensitive
 *  160         — a constructor of an anonymous class
 *  176, 178    — a flag that qualifies by its `true`/`false` default rather than
 *                by a `bool` type: one untyped, one typed `mixed`
 *  194, 200    — `is_a()` and `is_subclass_of()`, the two predicates that take a
 *                second argument. Only the subject reports; the class name it is
 *                compared against never does, in either the `if` or the ternary
 *  216,222,228 — a comment standing where the walk needs adjacency: before an
 *                `instanceof`, before a predicate's call parentheses, and before
 *                an argument reader's parentheses. Each still fires, because
 *                every adjacency test skips comments as well as whitespace
 *  245, 247    — `?bool` and `bool|null`, the two spellings that normalize to
 *                plain `bool`, neither carrying a `true`/`false` default that
 *                could qualify it by the other leg instead
 *  264, 266    — a comma between the flag and its selector: the flag is an
 *                argument of another call, then an element of an array
 *                literal. The comma separates that group's elements rather
 *                than ending the expression the group's own value feeds
 *  280         — a `match` standing as an operand of the ternary's condition,
 *                whose arm list is a braced group mid-expression
 *  296         — a parenthesised group holding a selector of its own, which is
 *                jumped whole rather than read as the flag's branch
 *  313, 316    — a selector behind a comma-separated sibling the tokenizer ends
 *                with the group's *own* closing token: an arrow function last
 *                in a call's argument list, then last in an array literal
 *  333, 337    — a `match` arm listing several conditions, with the signal in
 *                front of the comma rather than last: once a flag, once a
 *                predicate
 *  352, 353    — the fully-qualified spellings `\is_string()` and
 *                `\func_num_args()`, which name the global functions themselves
 *  365         — a `switch` with no case at all: nothing in it throws, so the
 *                flag guards nothing
 *  379, 381    — two surviving construction paths beside a rejecting third,
 *                which is one throw too few to make either condition a guard
 *  401         — the type test that picks between the two surviving arms of a
 *                `match` whose flag arm rejects
 *  416         — a predicate whose subject is wrapped in comments on both
 *                sides, which is still the bare first argument
 *  431         — a flag branching in the *arguments* of an anonymous class,
 *                which this constructor evaluates however far its body is from
 *                being constructor code
 *  448         — a predicate whose subject is wrapped in a redundant grouping
 *                parenthesis, which groups the parameter and nothing else
 *  470, 472    — an `instanceof` whose subject wears the same redundant
 *                grouping, once and then twice over, the second introduced by
 *                a comment. The widening the predicate calls get is the
 *                `instanceof`'s as well, at every width the subject reaches
 */
it('warns on every branching, declaration, and argument-reader shape', function (): void {
    $file = analyzeFixture(COMBINED_CONSTRUCTOR, 'shapes.php');

    expect(warningTuples($file))->toBe([
        ['line' => 33, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 35, 'column' => 19, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 37, 'column' => 20, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 41, 'column' => 17, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 49, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 55, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 59, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 61, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 63, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 77, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 83, 'column' => 22, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 85, 'column' => 31, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 89, 'column' => 21, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 95, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 98, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 103, 'column' => 30, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 121, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 122, 'column' => 32, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 125, 'column' => 18, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 141, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 149, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 160, 'column' => 36, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 176, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 178, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 194, 'column' => 18, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 200, 'column' => 39, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 216, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 222, 'column' => 53, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 228, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 245, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 247, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 264, 'column' => 37, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 266, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 280, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 296, 'column' => 28, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 313, 'column' => 40, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 316, 'column' => 26, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 333, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 337, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 352, 'column' => 34, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 353, 'column' => 25, 'source' => COMBINED_CONSTRUCTOR . '.ArgumentCount'],
        ['line' => 365, 'column' => 17, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 379, 'column' => 13, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 381, 'column' => 19, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 401, 'column' => 23, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 416, 'column' => 51, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 431, 'column' => 37, 'source' => COMBINED_CONSTRUCTOR . '.ModeFlag'],
        ['line' => 448, 'column' => 24, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 470, 'column' => 14, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
        ['line' => 472, 'column' => 45, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
    ]);
});

/**
 * Three shapes cost a walk of the whole enclosing construct per signal found in
 * it, and each is held rather than re-derived: the forward scan's answers per
 * position, what each branch of a construct does, and where an `if` chain
 * begins. A generated file reaches every one of these easily, and the same
 * defect class the repo already fixed once in ArrayAccessorsSniff (#239).
 *
 * No fixture reddens on any of the three caches, since they change only how
 * long the answers take to reach; these are the assertions that pin them, one
 * per shape, so a cache lost from one walk cannot hide behind another. Every
 * bound below sits between the two costs measured on the machine that wrote it,
 * and each was confirmed to redden with its own cache removed and to pass with
 * it — measured, not derived:
 *
 *   repeated use in one expression  n=4000   0.09s cached, 7.7s without, bound 3s
 *   `match` arms                    n=4000   0.24s cached, 23.0s without, bound 4s
 *   `if`/`elseif` chain             n=4000   0.75s cached, 7.8s without, bound 3s
 *
 * A `switch` of the same size is deliberately not asserted on: PHP_CodeSniffer's
 * own tokenizer is quadratic on a `switch` body that large — measured at
 * 2.5s/9.4s for n=2000/4000 with *any* single sniff, this one included and
 * PSR2.ControlStructures.SwitchDeclaration alike — so a wall-clock bound there
 * would assert PHPCS's behaviour rather than the sniff's. The case walk shares
 * the per-construct cache the `match` assertion below pins, and the count
 * assertion in each test proves the walk still reports every branch.
 */
it('scans repeated uses of one parameter in linear time', function (): void {
    $size = 4000;
    $source = "<?php\n\nclass ScaleProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
        . '        $this->mode = ' . implode(' . ', array_fill(0, $size, '$flag')) . "\n"
        . "            ? new Mailer()\n            : new NullLogger();\n    }\n}\n";

    [$file, $elapsed] = analyzeSourceTimed([COMBINED_CONSTRUCTOR], $source);

    expect($file->getWarningCount())->toBe($size, 'every use is still reported')
        ->and($elapsed)->toBeLessThan(3.0, "n={$size} took {$elapsed}s");
});

it('scans a many-armed match in linear time', function (): void {
    $size = 4000;
    $arms = implode("\n", array_map(
        static fn (int $index): string => "            \$flag => new Mode{$index}(),",
        range(0, $size - 1)
    ));
    $source = "<?php\n\nclass ScaleProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
        . "        \$this->mode = match (true) {\n{$arms}\n"
        . "            default => throw new LogicException('unreachable'),\n        };\n    }\n}\n";

    [$file, $elapsed] = analyzeSourceTimed([COMBINED_CONSTRUCTOR], $source);

    expect($file->getWarningCount())->toBe($size, 'every arm condition is still reported')
        ->and($elapsed)->toBeLessThan(4.0, "n={$size} took {$elapsed}s");
});

it('scans a long if chain in linear time', function (): void {
    $size = 4000;
    $links = "        if (\$flag) {\n            \$this->mode = new Mode0();\n        }";

    for ($index = 1; $index < $size; $index++) {
        $links .= " elseif (\$flag) {\n            \$this->mode = new Mode{$index}();\n        }";
    }

    $source = "<?php\n\nclass ScaleProbe\n{\n    public function __construct(bool \$flag)\n    {\n"
        . $links . " else {\n            throw new LogicException('unreachable');\n        }\n    }\n}\n";

    [$file, $elapsed] = analyzeSourceTimed([COMBINED_CONSTRUCTOR], $source);

    expect($file->getWarningCount())->toBe($size, 'every link condition is still reported')
        ->and($elapsed)->toBeLessThan(3.0, "n={$size} took {$elapsed}s");
});

/**
 * `T_CLOSE_CURLY_BRACKET` is the one grouping preceder PHP spells two ways, and
 * both readings are pinned here rather than in the fixtures: the fixture counts
 * anchor the mutation table above, so a new reporting site in shapes.php would
 * move every number in it. These two sources are driven through the same sniff
 * instance instead, one per reading, and each was mutation-checked on its own:
 *
 *   - dropping `T_CLOSE_CURLY_BRACKET` from the grouping preceders reddens the
 *     first (0 warnings rather than 1 — the grouped subject behind a block's
 *     brace stops being widened)
 *   - admitting that brace whatever it closes, rather than a block alone,
 *     reddens the second (3 warnings rather than 0 — each dynamic member name's
 *     braces are read as a block's, so the parameter handed to the call is
 *     reported as the subject of the `instanceof` behind it)
 *
 * The predicate spelling in the second source stays silent under both, since
 * the bare-first-argument test rejects a nested call's result independently of
 * this lookback.
 */
it('reads a block\'s closing brace as a grouping preceder', function (): void {
    $source = "<?php\n\nclass BlockBraceBeforeGroupedSubject\n{\n"
        . "    public function __construct(mixed \$source)\n    {\n"
        . "        if (\$this->ready) { \$this->prepare(); }\n"
        . "        (\$source) instanceof Mailer\n"
        . "            ? \$this->transport = new Mailer()\n"
        . "            : \$this->transport = new NullLogger();\n    }\n}\n";

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect(tuplesFromMessages($file->getWarnings()))->toBe([
        ['line' => 8, 'column' => 10, 'source' => COMBINED_CONSTRUCTOR . '.TypeSwitch'],
    ]);
});

it('reads a dynamic member name\'s closing brace as the name it ends', function (): void {
    $source = "<?php\n\nclass DynamicNameCallResultInstanceofSubject\n{\n"
        . "    public function __construct(mixed \$source, string \$name)\n    {\n"
        . "        if (\$this->{'resolve'}(\$source) instanceof Mailer) {\n"
        . "            \$this->transport = new Mailer();\n"
        . "        } elseif (self::{'resolve'}(\$source) instanceof NullLogger) {\n"
        . "            \$this->transport = new NullLogger();\n"
        . "        } elseif (\$this?->{\$name}(\$source) instanceof Mailer) {\n"
        . "            \$this->transport = new Mailer();\n"
        . "        } elseif (is_string(\$this->{\$name}(\$source))) {\n"
        . "            \$this->transport = new NullLogger();\n"
        . "        }\n    }\n}\n";

    $file = analyzeStdinSource([COMBINED_CONSTRUCTOR], $source);

    expect($file->getWarningCount())->toBe(0)
        ->and($file->getErrorCount())->toBe(0);
});
