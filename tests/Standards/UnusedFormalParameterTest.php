<?php

/**
 * Tests the custom CleanCode.DeadCode.UnusedFormalParameter sniff (PHPMD
 * UnusedCode UnusedFormalParameter, #120). Fixtures live in
 * tests/fixtures/UnusedFormalParameterSniff/, and the mapping is documented in
 * docs/phpmd/unusedcode-unusedformalparameter.md.
 *
 * Every expectation below was cross-checked against a live PHPMD 2.15.0 run
 * over these very fixture files, with a ruleset enabling only
 * rulesets/unusedcode.xml/UnusedFormalParameter:
 *
 *     phpmd tests/fixtures/UnusedFormalParameterSniff/failing.php text only-ufp.xml
 *
 * On failing.php PHPMD reports lines 23, 30, 35, 42, 48, 59, 65, 71, 77, 83,
 * 91, 112, 122, 128, 136, 144, 154, 164, 175, 184, 192, 198, 211, 222, 243,
 * 252, 260, 270, 278, 287, 294, 307, 317, 325, 333, 344, 349, 354, 362, 369,
 * 378, 400, 411, 423, 439, 446, 453, 463, 476, 492 and 504 — fifty-one
 * findings, naming $unusedA through $unusedAY (with $unusedJ absent, since
 * that one is read) plus $id. This sniff reproduces all fifty-one, on the same
 * lines, naming the same parameters.
 *
 * On namespaces.php both are silent, which is the point of that fixture: every
 * method in it overrides one declared in its own namespace, and both tools
 * resolve the ancestor rather than the same-named class in the other namespace
 * block.
 *
 * On passing.php this sniff is silent and PHPMD is not: it reports $eta,
 * $theta and $iota (its func_get_args() exemption misses an unqualified call
 * inside a namespace) and $fourth, $fifth and $sixth (it does not honour
 * #[\Override] at all). Both are rows in the divergence table in
 * docs/phpmd/unusedcode-unusedformalparameter.md.
 *
 * On divergences.php PHPMD reports three of the seven this sniff reports. The
 * four it skips are the three constructs PDepend never surfaces to a
 * MethodAware rule, and a child of a class whose nested anonymous class uses a
 * trait — which PDepend attributes to the enclosing class, so PHPMD reads the
 * child as an override.
 *
 * That parity is the whole point of #120: the rule exists so that `phpmd` no
 * longer has to run, and a shape this sniff stays silent on where PHPMD speaks
 * would put it straight back in the pipeline. Two earlier candidate wirings
 * were rejected for exactly that, and the tests below pin the shapes that
 * caught them — `emptyBody()` and `commentOnlyBody()` (which
 * Generic.CodeAnalysis.UnusedFunctionParameter exempts), `__unserialize()`
 * (whose magic-method list is wider than PHPMD's), and `ownOnly()` (which the
 * excluded-code approach silenced along with its overriding neighbours).
 *
 * The rule is detection-only in both tools: deleting a parameter changes the
 * signature and breaks every caller, so there is no autofixed fixture, and the
 * fixer is asserted to be a no-op rather than assumed to be one.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const UNUSED_FORMAL_PARAMETER = 'CleanCode.DeadCode.UnusedFormalParameter';

const UNUSED_FORMAL_PARAMETER_ERROR = UNUSED_FORMAL_PARAMETER . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(UNUSED_FORMAL_PARAMETER);
});

/**
 * The compliant fixture is deliberately more than "code with no dead
 * parameters". It carries one instance of every exemption the sniff
 * implements, in the spelling that exercises it, so that deleting an exemption
 * from the sniff reddens this test rather than passing unnoticed:
 *
 * - the three interpolation spellings and a multi-line heredoc, whose body is
 *   tokenized one token per physical line;
 * - an escaped backslash in front of a read, in a double-quoted string and in a
 *   heredoc. A backslash cancels the interpolation that follows it, but a
 *   backslash can itself be escaped, so what cancels a read is one left spare
 *   once the run is paired off — both runs here are even, and both names are
 *   read. PHPMD and PHP agree;
 * - a read that happens only inside a nested closure, and one only inside a
 *   nested arrow function;
 * - `func_get_args()` both directly and from inside a nested closure;
 * - `compact('kappa')` naming the parameter;
 * - bodyless interface and abstract declarations;
 * - same-file override resolution, at one level, transitively at two, through
 *   an interface reached via the parent's own `implements` clause, and onto a
 *   method the parent draws from a trait;
 * - `@inheritdoc` bare, braced and mixed-case, and `#[\Override]` both alone,
 *   below a second attribute, and in the lowercase spelling PHP resolves to
 *   the same attribute;
 * - a constructor's own unpromoted parameter, read in the body, and promoted
 *   constructor properties, by visibility and by `readonly`;
 * - a closure and an arrow function reading the parameters they declare
 *   themselves — the compliant half of the two constructs PHPMD cannot see;
 * - a `compact()` call that is not the first one in its body, one whose
 *   argument is written in double quotes, and one naming a parameter from
 *   inside a nested array, which `compact()` accepts and PHPMD reads too;
 * - a read from inside a shell string, in the `$name`, `{$name}` and `${name}`
 *   spellings — the compliant half of failing.php's shell-string case, which
 *   proves the sniff discards a shell string's *text* and not the reads inside
 *   it. PHPCS tokenizes the first two as T_VARIABLE and the third as
 *   T_STRING_VARNAME, so between them they pin both branches of the walk;
 * - all seven fixed-signature magic methods.
 *
 * `prefixNearMiss()` is the one shape that is not an exemption: it reads
 * `$lambdaExtra`, which contains `$lambda` as a prefix, and reads `$lambda`
 * itself on its own line. Delete that second line and matching a name by
 * anything looser than the whole of it is the only thing standing between
 * silence and a correct report.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every reported shape, on the line and column of the parameter itself.
 *
 * Anchoring to the parameter rather than to the `function` keyword is what
 * gives each finding in a multi-parameter signature its own column —
 * `partiallyUsed()` on line 83 reports at column 46, the second parameter,
 * and says nothing about the first. A report anchored to the declaration
 * could not tell the two apart.
 *
 * The four shapes that failed the earlier candidate wirings:
 *
 * - line 30, an empty body, and line 35, a comment-only body. PHPMD reports
 *   both; Generic.CodeAnalysis.UnusedFunctionParameter exempts both outright.
 * - line 122, `__unserialize()`. PHPMD's fixed-signature list does not carry
 *   it, and neither does this sniff's.
 * - line 112, `ownOnly()`, a method that is not an override inside a class
 *   that both extends and implements. Its two neighbours in the same class
 *   *are* overrides and are correctly silent, which is precisely the
 *   distinction the excluded-code approach could not make: it decided the
 *   exemption per class, so silencing the overrides silenced this too.
 *
 * And the shapes that pin the narrower judgements:
 *
 * - line 42, `func_num_args()`. It does not exempt the signature, in either
 *   tool — only `func_get_args()` does.
 * - line 48, `compact('other')`. compact() exempts the parameter it names and
 *   not its siblings.
 * - line 59, a parameter mentioned only in the docblock.
 * - lines 65, 71 and 77: a variadic, a by-reference parameter, and one with a
 *   default. None of the three is a read.
 * - line 128, `__invoke()`, which is magic but whose signature is the
 *   author's own — in both tools.
 * - line 164, `$id`, where the body reads `$idleTimer`. This is the shape that
 *   makes whole-name matching load-bearing: match a parameter against anything
 *   a read merely starts with and this finding disappears silently.
 *   Mutation-checked — matching by prefix drops this line and nothing else.
 * - line 175, a constructor's own, unpromoted parameter. Promotion is what
 *   exempts a constructor parameter; declaring one plainly is not.
 * - lines 184, 192 and 198: the name appears only in a line comment, only in a
 *   single-quoted string, and only in inline HTML. None of the three is code,
 *   so none is a read — reading the body as unfiltered text would silence all
 *   three, which is coverage PHPMD has and this sniff would not.
 * - line 211, `// @inheritdoc` written as a line comment. Only a docblock
 *   carries the annotation, in this sniff and in PHPMD.
 * - line 222, `Override` named inside another attribute's argument list. It is
 *   a class reference, not the `#[\Override]` attribute.
 * - line 243, a class whose own trait declares a method of the same name. PHP
 *   gives the class's own declaration precedence over the trait's, so the
 *   method overrides nothing.
 *
 * - lines 252 to 317, one per construct that carries text rather than code: a
 *   heredoc, an interpolated string, a shell string, a nowdoc and a plain
 *   quoted string. A `func_get_args()` or a `compact('name')` written inside
 *   one of them is printed and not run, so it exempts nothing, and PHPMD —
 *   which matches a call node rather than a substring — reports through every
 *   one of them.
 * - lines 325 and 333, a name behind a backslash, in a double-quoted string and
 *   in a heredoc. Both interpolate, so both are searched for reads, and the
 *   backslash is what cancels this one: PHP prints the name instead of reading
 *   it, and PHPMD reports the parameter. The other direction — that an *even*
 *   run of backslashes leaves the read live — is pinned from the compliant side
 *   by passing.php's escapedBackslashRead().
 * - lines 344 to 369, a name that is not a call to the global function it
 *   spells. Four are calls to something else — a method, a nullsafe method, a
 *   static method, and a constructor of a class named `Compact`, which PHP
 *   resolves case-insensitively — and the fifth is a method named
 *   `func_get_args()`, which exempts nothing, not even its own signature. PHPMD
 *   matches a FunctionPostfix and reports through all five.
 * - line 378, the name `func_get_args` with no parenthesis after it: a constant
 *   of that name, which is a read of nothing. Without the parenthesis
 *   requirement this one name would exempt the whole signature.
 * - line 400, a bare `Override` inside another attribute's argument list. The
 *   comma before it separates that attribute's arguments, not one attribute
 *   name from the next, so it is a constant and not the `#[\Override]`
 *   attribute.
 * - lines 222 and 307, the two other shapes that spell `Override` where it is
 *   not the attribute: as a class reference in an argument list, and inside an
 *   argument's string.
 * - lines 411 to 476, the remaining ways a name can be spelled where it is not
 *   a call to the global function. Two are *declarations* of the name — a
 *   method of an anonymous class may be called `func_get_args()` or
 *   `compact()` freely, where a bare global function may not be redeclared, so
 *   declaring one runs nothing. Three are qualified references —
 *   `new \Compact()`, `new \Vendor\Package\Compact()` and
 *   `new \Func_get_args()` — which PHP_CodeSniffer splits into separators and
 *   names, putting a separator rather than the deciding `new` in front of the
 *   matched segment. Two are attribute names, `#[Compact('...')]` written
 *   alone and written second in its group, where the token in front is the
 *   comma that also separates a genuine call's arguments. PHPMD matches a
 *   FunctionPostfix and reports through all seven.
 * - lines 492 and 504, a declaration of the name that returns by reference.
 *   The `&` stands between the name and the `function` that says it is a
 *   declaration, so it is stepped over rather than listed: the same `&` also
 *   stands in `$mask & compact('x')` and `$ref = &compact('x')`, which are
 *   genuine calls, and only the token behind it tells the three apart.
 *
 * Lines 184 to 243 were each mutation-checked, one defect at a time: taking the
 * body as unfiltered text drops 184, 192 and 198; walking `Tokens::$emptyTokens`
 * for the docblock drops 211; the unanchored `Override` match drops 222 and
 * flags passing.php's lowercase spelling instead; seeding the ancestor queue
 * with the class's own traits drops 243. Each mutation moved those lines and no
 * others.
 *
 * The lines that pin the sniff's own decisions were mutation-checked the same
 * way, one decision at a time. Each mutation moved the lines named and no
 * others:
 *
 *     | Mutation applied to the sniff                          | Lines dropped        |
 *     |--------------------------------------------------------|----------------------|
 *     | read a name by prefix rather than whole                 | 164                  |
 *     | find `compact()` by searching a quoted string's text    | 317                  |
 *     | drop the escape-pair run from the interpolation pattern | 325, 333             |
 *     | drop the preceding-token guard from the call test       | 344, 349, 354, 362, 369 |
 *     | drop the parenthesis requirement from the call test     | 378                  |
 *     | stop skipping an attribute's argument list              | 400                  |
 *     | drop T_FUNCTION from the call test's preceder list      | 411, 423             |
 *     | drop the qualified-name hop from the call test          | 439, 446, 453        |
 *     | drop the attribute-group guard from the call test       | 463, 476             |
 *     | drop the by-reference `&` hop from the call test        | 492, 504             |
 *
 * The decisions that are a keeping rather than a dropping are checked from the
 * other side, on passing.php, where each mutation reddens the compliant
 * fixture: removing T_DOUBLE_QUOTED_STRING from the searched text reddens its
 * three interpolation spellings and one half of escapedBackslashRead(),
 * removing T_HEREDOC reddens heredocRead() and the other half, dropping the
 * T_STRING_VARNAME read reddens shellStringRead()'s `${name}` spelling, and
 * collecting a `compact()` argument only at the call's own depth reddens
 * compactNestedNames().
 */
it('flags every unused formal parameter in the failing fixture', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 29, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 30, 'column' => 27, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 35, 'column' => 33, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 42, 'column' => 32, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 48, 'column' => 38, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 59, 'column' => 37, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 65, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 71, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 77, 'column' => 33, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 83, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 91, 'column' => 38, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 112, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 122, 'column' => 41, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 128, 'column' => 37, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 136, 'column' => 48, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 144, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 154, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 164, 'column' => 34, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 175, 'column' => 40, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 184, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 192, 'column' => 42, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 198, 'column' => 39, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 211, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 222, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 243, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 252, 'column' => 44, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 260, 'column' => 40, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 270, 'column' => 50, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 278, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 287, 'column' => 48, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 294, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 307, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 317, 'column' => 44, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 325, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 333, 'column' => 40, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 344, 'column' => 43, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 349, 'column' => 45, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 354, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 362, 'column' => 42, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 369, 'column' => 43, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 378, 'column' => 45, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 400, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 411, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 423, 'column' => 42, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 439, 'column' => 50, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 446, 'column' => 46, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 453, 'column' => 54, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 463, 'column' => 45, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 476, 'column' => 51, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 492, 'column' => 47, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 504, 'column' => 51, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
    ]);
});

/**
 * Same-file override resolution across more than one namespace block.
 *
 * The parity fixtures answer what the sniff must report; this one answers what
 * it must not. Two class-likes named `Origin` and two named `Contract` are
 * declared in one file, one of each per namespace, and every method here is an
 * override of the one in its *own* namespace.
 *
 * Indexed by short name, the second `Origin` overwrote the first, so `Child`
 * resolved to a class declaring no `handle()`, the override exemption broke,
 * and correct code was reported. Mutation-checked: dropping the namespace from
 * the declaration key and from the lookup reddens this test at lines 44 and 51
 * — the two First\Child methods whose ancestor is shadowed — and moves nothing
 * in failing.php or passing.php.
 *
 * This cannot live in passing.php: two namespaces in one file require the
 * braced form, and that fixture's declarations sit under a single unbraced
 * namespace.
 */
it('resolves a same-file ancestor within its own namespace', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'namespaces.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The divergence fixture, with the same line-and-column precision, because
 * three of these six are the *only* place closures, arrow functions and
 * anonymous-class methods are reported at all — the parity fixture has none,
 * since PHPMD cannot see them.
 *
 * - lines 21, 26 and 32: a closure, an arrow function, and a method of an
 *   anonymous class. PHPMD is silent on all three; each is the same defect in
 *   a construct PDepend never hands to a MethodAware rule, so the extra
 *   reports are kept and this sniff stays a superset.
 * - line 62, a child of a class whose nested anonymous class uses a trait.
 *   PHPMD is silent: PDepend attributes the trait import to the enclosing
 *   class, so the child's method reads as an override of it. It overrides
 *   nothing, and the parameter is dead, so this sniff reports it.
 * - line 89, a method of a class extending a base declared in another file.
 *   Both tools report it *here*, because PHPMD running over one file cannot
 *   see that base either.
 * - line 114, the second parameter of a signature spread across three lines.
 *   Column 12 is what proves the anchor is the parameter and not the
 *   declaration: the `function` keyword is two lines above, at column 1.
 * - line 124, a dynamic read. Neither tool resolves `${'unusedG'}`.
 *
 * `shadowedName()` is the sixth shape and is absent from this list on purpose:
 * both tools stay silent on it, so it asserts by *not* appearing.
 */
it('reports the documented divergences, and only those', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 21, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 26, 'column' => 27, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 32, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 62, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 89, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 114, 'column' => 12, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 124, 'column' => 29, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
    ]);
});

/**
 * The message names the construct and the parameter, and points at the two
 * annotations that legitimately silence it — so a report over a whole codebase
 * says which signature to open and what the two correct answers are.
 *
 * The construct wording follows what the declaration actually is, which
 * matters because three of the constructs this sniff reports have no name to
 * print at all. A closure and an arrow function are named as such; a method
 * and a function are told apart by their innermost enclosing scope, the same
 * distinction CleanCode.Functions.ExcessiveParameterList makes.
 */
it('names the construct and the parameter in the message', function (): void {
    $errors = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php')->getErrors();

    expect($errors[23][29][0]['message'])
        ->toContain('function plainUnused()')
        ->toContain('$unusedA')
        ->toContain('#[\\Override]')
        ->toContain('@inheritdoc');

    expect($errors[91][38][0]['message'])->toContain('method ownMethod()');
});

it('names a closure and an arrow function as what they are', function (): void {
    $errors = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'divergences.php')->getErrors();

    expect($errors[21][35][0]['message'])->toContain('The closure never reads');
    expect($errors[26][27][0]['message'])->toContain('The arrow function never reads');
});

/**
 * Detection only, measured rather than assumed. Removing a parameter changes
 * the signature and breaks every caller, so no violation carries a fixer hook
 * and running the real fixer over the failing fixture must return it
 * byte-identical.
 */
it('reports every violation as unfixable', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php');

    expect(violationFixableFlags($file))->each->toBeFalse();
});

it('leaves the failing fixture untouched when the fixer runs', function (): void {
    $fixture = __DIR__ . '/../fixtures/UnusedFormalParameterSniff/failing.php';
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php');

    expect(autofixedContents($file))->toBe(file_get_contents($fixture));
});

/**
 * The same-file ancestor index is built once per token stream, not once per
 * method.
 *
 * It used to be rebuilt inside every override check, and each rebuild walked
 * the whole file, so a class of n methods scanned the file n times: measured
 * here at 0.58s for 250 methods, 1.47s for 500, 5.01s for 1,000 and better
 * than 3x per doubling throughout. Accessors are exactly the shape that
 * reaches it — this repo's own TooManyMethods and TooManyPublicMethods sniffs
 * exempt `get*`/`set*`/`is*`/`has*` by ignorepattern, so a class may carry any
 * number of them and no other rule objects — which is why the fixture below is
 * built from them.
 *
 * Two things are asserted per size, and they answer different questions:
 *
 * - Every method is still read, and the one unused parameter still reported.
 *   An index that had stopped resolving would run fast for the wrong reason.
 * - The sniff costs less than twice PHP_CodeSniffer's own parse of the same
 *   file. That is the scale-free half: the parse is the work the file
 *   inherently needs, so a sniff that stays within a constant factor of it at
 *   every size is not walking anything quadratic. The sibling
 *   CleanCode.Arrays.ArrayAccessors scale tests make the same claim the same
 *   way.
 *
 * Mutation-checked by deleting the memoization guard from buildDeclarations():
 * the rebuild returns and this test reddens at the smallest size measured,
 * n=250, where the sniff costs 0.66s against a parse of 0.02s — thirty times
 * the parse, against a bound of twice it. Measured the same way outside the
 * harness, the rebuild runs a file of 1,000 methods in 10.76s where the index
 * built once runs it in 0.37s.
 */
it('indexes same-file ancestors once per file, not once per method', function (): void {
    $sizes = [250, 500, 1000];
    $sniffedBySize = [];

    foreach ($sizes as $size) {
        $accessors = '';

        for ($index = 0; $index < $size; $index++) {
            $accessors .= "    public function getThing{$index}(): int\n"
                . "    {\n        return {$index};\n    }\n\n";
        }

        $source = "<?php\n\nclass Big\n{\n" . $accessors
            . "    public function unusedOne(int \$unused): int\n    {\n        return 1;\n    }\n}\n";

        [$config, $ruleset] = buildRuleset([UNUSED_FORMAL_PARAMETER]);
        $path = sys_get_temp_dir() . '/' . uniqid('cleancode-ufp-scale-', true) . '.php';
        file_put_contents($path, $source);

        try {
            $file = new LocalFile($path, $ruleset, $config);

            $parseAt = hrtime(true);
            $file->parse();
            $parsed = (hrtime(true) - $parseAt) / 1e9;

            $sniffAt = hrtime(true);
            $file->process();
            $sniffed = (hrtime(true) - $sniffAt) / 1e9;
            $reported = $file->getErrorCount();
        } finally {
            unlink($path);
        }

        $sniffedBySize[$size] = $sniffed;

        expect($reported)->toBe(1, "n={$size} still reports the one unused parameter")
            ->and($sniffed)->toBeLessThan(
                ($parsed * 2.0),
                "n={$size}: sniff {$sniffed}s against a parse of {$parsed}s"
            );
    }

    // The growth half, which the parse-relative bound above cannot state on its
    // own. Four times the methods costs four times the work when the index is
    // built once and sixteen when it is rebuilt per method, so the bound sits
    // between the two: 8x is twice the headroom linear growth needs and half of
    // what the rebuild spends. Measured at 4.8x with the index in place.
    expect($sniffedBySize[1000])->toBeLessThan(
        ($sniffedBySize[250] * 8.0),
        "1000 methods took {$sniffedBySize[1000]}s against 250 at {$sniffedBySize[250]}s"
    );
});
