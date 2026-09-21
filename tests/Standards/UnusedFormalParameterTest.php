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
 * 378, 400, 411, 423, 439, 446, 453, 463, 476, 492, 504, 522, 541 and 554 —
 * fifty-four findings, naming $unusedA through $unusedBB (with $unusedJ
 * absent, since that one is read) plus $id. This sniff reproduces all
 * fifty-four, on the same lines, naming the same parameters.
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
 * On divergences.php PHPMD reports four of the eleven this sniff reports. The
 * seven it skips are the three constructs PDepend never surfaces to a
 * MethodAware rule, a child of a class whose nested anonymous class uses a
 * trait — which PDepend attributes to the enclosing class, so PHPMD reads the
 * child as an override — a first-class callable, which it reads as the call
 * that syntax only resembles, and the two names that merely spell `compact`:
 * PHPMD matches that one by suffix, so a qualified `Vendor\Package\compact()`
 * and a bare name a `use function` redirects to it both satisfy it, while this
 * sniff resolves them through CleanCode\Helpers\FunctionCalls and finds
 * somebody else's function. Re-measured on a live PHPMD 2.15.0 run over this
 * exact file while adding those two (#320): four reported, both new lines
 * silent.
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
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;
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
 *   an interface reached via the parent's own `implements` clause, onto a
 *   method the parent draws from a trait, and — for the two class-likes that
 *   reach it through a token of their own — from inside an anonymous class and
 *   from inside an enum. Those last two are the whole coverage of T_ANON_CLASS
 *   and T_ENUM in the sniff's CLASS_LIKE list: every other anonymous class and
 *   enum in the fixture set declares no ancestor, so it exercises the reported
 *   half of the exemption and never the exempt half. PHPMD is silent on both,
 *   though only the enum for the same reason this sniff is — it reports
 *   failing.php's ReportingEnum, so PDepend does surface an enum's methods,
 *   while an anonymous class it never surfaces at all;
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
 * - all seven fixed-signature magic methods;
 * - an interface extending two same-file interfaces, and a class implementing
 *   it that overrides a method from each. The second of the two is the shape
 *   PHP_CodeSniffer's own findExtendedClassName() cannot see, since its
 *   collection set ends at the comma — read the ancestry that way again and
 *   $tenth is reported while $ninth stays silent, which is the asymmetry that
 *   names the defect.
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
 * - line 522, a bare global function named `__get`. The fixed-signature
 *   exemption belongs to a method: outside a class nothing imposes the
 *   signature, so the parameter is the author's own dead weight. PHPMD reports
 *   it too — measured, not assumed, on the same 2.15.0 run. This is the
 *   `enclosingClass() === null` arm of hasFixedSignature(); the exemption's
 *   other arm is pinned from the compliant side by passing.php's
 *   FixedSignatures, which carries all seven magic methods as real methods.
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
 *     | let a bare function claim the fixed-signature exemption | 522                  |
 *     | collect every T_STRING of a name instead of its last    | 554                  |
 *
 * The decisions that are a keeping rather than a dropping are checked from the
 * other side, on passing.php, where each mutation reddens the compliant
 * fixture: removing T_DOUBLE_QUOTED_STRING from the searched text reddens its
 * three interpolation spellings and one half of escapedBackslashRead(),
 * removing T_HEREDOC reddens heredocRead() and the other half, dropping the
 * T_STRING_VARNAME read reddens shellStringRead()'s `${name}` spelling,
 * collecting a `compact()` argument only at the call's own depth reddens
 * compactNestedNames(), dropping T_ANON_CLASS or T_ENUM from CLASS_LIKE
 * reddens $seventh or $eighth respectively, and reading the ancestry through
 * PHP_CodeSniffer's own findExtendedClassName() again reddens $tenth — each of
 * those moving its own parameter and nothing else, here or in any other
 * fixture.
 *
 * Line 541 has no mutation row of its own on purpose. It is the qualifier
 * `Fixtures` made to resolve — a class this fixture declares so that the
 * ancestor lookup has something to find — and both tools report it for the
 * ordinary reason that its parameter is dead. Line 554 is the one that pins
 * the decision, and it is the child whose method that qualifier would have
 * exempted.
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
        ['line' => 522, 'column' => 23, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 541, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 554, 'column' => 36, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
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
 * three of these are the *only* place closures, arrow functions and
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
 * - line 158, `\func_get_args(...)`. PHPMD is silent: it reads PHP 8.1's
 *   first-class callable syntax as the call it resembles and grants the
 *   whole-signature exemption. Nothing is called — the syntax builds a Closure
 *   that throws whenever it is invoked — so this sniff reports. The qualified
 *   spelling is deliberate: an unqualified call inside a namespace is a
 *   separate divergence of PHPMD's own, and would decide this line for the
 *   wrong reason.
 * - line 168, `\compact(...)`. Both tools report, by different routes: PHPMD
 *   because the call it thinks it sees names no parameter, this sniff because
 *   it is not a call at all. It is here as the second door into the same
 *   misreading, so that closing one cannot leave the other open.
 *
 * `shadowedName()` and `spreadFuncGetArgs()` are absent from this list on
 * purpose, and assert by *not* appearing: both tools stay silent on the first,
 * and on the second because `\func_get_args(...[])` is a genuine spread of
 * zero arguments — the same call `\func_get_args()` is, which still exempts.
 * It is the control that keeps the first-class-callable check from swallowing
 * every ellipsis, and it is load-bearing: widen that check to any leading
 * ellipsis and line 179 is reported, moving nothing else.
 *
 * Lines 158 and 168 were mutation-checked the other way. Dropping the
 * first-class-callable guard drops 158 alone — 168 does not move, because
 * `\compact(...)` names no parameter whether it is read as a call or not. It
 * is kept as the pinned second spelling rather than as a discriminator, so
 * that a later change granting `compact(...)` an exemption has something to
 * redden.
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
        ['line' => 158, 'column' => 39, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 168, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 194, 'column' => 34, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 212, 'column' => 33, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
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
 * when the memoization landed at 0.58s for 250 methods, 1.47s for 500, 5.01s
 * for 1,000 and better than 3x per doubling throughout. Accessors are exactly
 * the shape that reaches it — this repo's own TooManyMethods and
 * TooManyPublicMethods sniffs exempt `get*`/`set*`/`is*`/`has*` by
 * ignorepattern, so a class may carry any number of them and no other rule
 * objects — which is why the fixture below is built from them.
 *
 * The claim is counted, not timed. buildDeclarations() records how often it
 * built the index and how often its key check answered from the index already
 * built, and this test reads both as a delta around one process() run. The
 * earlier wall-clock form stated the same claim only as far as a shared runner
 * allowed: the 250-method baseline is ~13ms, small enough that ordinary CI
 * jitter carried the measured cross-size ratio past its 8x bound on two runs
 * out of three with no code change between them (#321). A count cannot fail
 * that way — it is the mechanism itself rather than a shadow of it — and it
 * needs no headroom, so it also states the claim more tightly than any ratio.
 *
 * Three things are asserted per size, and they answer different questions:
 *
 * - Every method is still read, and the one unused parameter still reported.
 *   An index that had stopped resolving would count right for the wrong
 *   reason.
 * - The index is built exactly once for the file, whatever n is. That is the
 *   whole of the memoization claim, stated without reference to elapsed time.
 * - Every other read answers from it. Each of the n+1 declarations consults
 *   the index twice — declarationsByName() and inheritedNames() each ask — so
 *   the reads total 2n+2, of which one builds and 2n+1 hit. Pinning that
 *   keeps the build count from passing vacuously: a sniff that stopped
 *   consulting the index at all would report 0 builds and 0 hits.
 *
 * Mutation-checked by deleting the `$this->declarationsKey === $key` guard
 * from buildDeclarations(), so every read rebuilds. `composer test` then fails
 * on this test at the smallest size measured, n=250, where the counts read 502
 * builds / 0 hits against the 1 / 501 asserted here; n=500 reads 1,002 / 0
 * against 1 / 1,001, and n=1,000 reads 2,002 / 0 against 1 / 2,001. The
 * builds are one past the hits the guard buys, because the read that built the
 * index is a read the guard never had to answer.
 */
it('indexes same-file ancestors once per file, not once per method', function (): void {
    $delta = static function (array $before, array $after): array {
        $counted = [];

        foreach ($after as $counter => $count) {
            $counted[$counter] = $count - $before[$counter];
        }

        return $counted;
    };

    // buildRuleset() memoises the ruleset, and so the sniff instance, per
    // sniff-code key: this is the same instance every other test in this file
    // runs. Its counters are therefore cumulative across all of them, which is
    // why each size below is read as a delta rather than as a total.
    [$config, $ruleset] = buildRuleset([UNUSED_FORMAL_PARAMETER]);
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[UNUSED_FORMAL_PARAMETER]];

    foreach ([250, 500, 1000] as $size) {
        $accessors = '';

        for ($index = 0; $index < $size; $index++) {
            $accessors .= "    public function getThing{$index}(): int\n"
                . "    {\n        return {$index};\n    }\n\n";
        }

        $source = "<?php\n\nclass Big\n{\n" . $accessors
            . "    public function unusedOne(int \$unused): int\n    {\n        return 1;\n    }\n}\n";

        $path = sys_get_temp_dir() . '/' . uniqid('cleancode-ufp-scale-', true) . '.php';
        file_put_contents($path, $source);
        $before = $sniff->cacheCounts();

        try {
            $file = new LocalFile($path, $ruleset, $config);
            $file->process();
            $reported = $file->getErrorCount();
        } finally {
            unlink($path);
        }

        $counted = $delta($before, $sniff->cacheCounts());

        expect($reported)->toBe(1, "n={$size} still reports the one unused parameter")
            ->and($counted['declarations.builds'])->toBe(
                1,
                "n={$size}: the index is built once for the file, not once per method"
            )
            ->and($counted['declarations.hits'])->toBe(
                (2 * $size) + 1,
                "n={$size}: every read after the first answers from the index already built"
            );
    }
});

/**
 * The same claim for the two indexes the *ancestor walk* keeps, which the test
 * above cannot make.
 *
 * That one's fixture is a lone `class Big`, so inheritedNames() hands back an
 * empty queue and the walk never runs a step: it pins buildDeclarations() and
 * nothing beyond it. Everything the walk itself re-reads was left uncovered,
 * and both of the file-wide scans it makes were still being made once per
 * descendant method:
 *
 * - methodNames(), which reads an ancestor's method list, and
 * - traitNames(), which reads the traits that ancestor uses.
 *
 * The two are reached by *different* shapes, which is why both are counted
 * here. The walk stops at the first ancestor declaring the method it is asked
 * about, so a class whose methods all override their parent's returns before
 * traitNames() is ever called; only a class whose method the parent does *not*
 * declare drains the queue and reaches it. Memoising methodNames() alone leaves
 * that second shape — a class that adds methods rather than replacing them,
 * which is the ordinary one — quadratic through the other door, measured when
 * that memoization landed, with the first index already in place, at 0.11s for
 * 250 methods, 0.46s for 500, 2.21s for 1,000 and 9.38s for 2,000: a clean 4x
 * per doubling.
 *
 * Both shapes are asserted the same way the test above asserts its own, and for
 * the same reasons: a report count per size, so an index that had stopped
 * resolving cannot count right by answering wrongly, and build/hit counts read
 * as a delta around one process() run, which state the memoization directly
 * rather than through elapsed time. Nothing here is timed. The wall-clock form
 * these assertions replace flaked on CI for the reason #321 records: at these
 * sizes a single reading is 6-15ms, small enough that one contended moment on
 * a shared runner moved it ~40% and decided the cross-size ratio on its own.
 *
 * Each ancestor is counted separately, keyed by its own pointer, which is what
 * "once per ancestor" is stated against: both shapes here resolve exactly one
 * ancestor, `Base`, so each index shows one entry at one build. Those
 * per-ancestor counts are read straight rather than as a delta, and can be:
 * buildDeclarations() clears them in the same branch that discards the indexes
 * themselves, so they describe the token stream those indexes describe, which
 * for every size below is the file just processed. The pointer would otherwise
 * be ambiguous — every size here holds `Base` at the same pointer, and two
 * files' counts would merge under it.
 *
 * The report counts are what make the two shapes discriminating rather than
 * merely counted: the overriding shape must report *nothing* — every parameter
 * is dead and every method exempt — and the extending shape must report
 * *every* one of them. An exemption that stopped resolving would redden the
 * first, and one that started over-resolving would redden the second. That is
 * also what keeps the overriding shape worth its runtime: the extending shape
 * alone would let the exemption break silently.
 *
 * Mutation-checked one cache at a time, by deleting its `isset()` guard and
 * re-running `composer test`. The two are not symmetric, because the walk
 * reaches them by different paths. Counts below are that cache's own build/hit
 * pair at the smallest size measured, n=250, against the 1 build and 249 hits
 * this test asserts wherever the index is reached at all — n=500 and n=1,000
 * read 500 and 1,000 builds against 1 the same way:
 *
 *     | Cache dropped  | overriding        | extending         |
 *     |----------------|-------------------|-------------------|
 *     | methodNames()  | reddens, 250/0    | reddens, 250/0    |
 *     | traitNames()   | passes, 0/0       | reddens, 250/0    |
 *
 * methodNames() is read on the way to both, so dropping it reddens both.
 * traitNames() is read only after the walk fails to match, so the extending
 * shape is the only thing in the suite that holds it — drop that shape and
 * memoising traitNames() could be reverted with every test still green. The
 * overriding shape's own assertion that traitNames() is never reached (0
 * builds, 0 hits) is what keeps that asymmetry pinned rather than assumed.
 */
it('indexes an ancestor once per file, not once per descendant method', function (): void {
    $shapes = [
        // Every method overrides its parent's, so the walk matches on the first
        // ancestor and returns: methodNames() is the index it re-reads, and
        // traitNames() is never reached at all.
        'overriding' => ['name' => 'getThing', 'reports' => false, 'traits' => false],
        // No method overrides anything, so the walk drains the queue and asks
        // the ancestor for its traits too: traitNames() is the second index.
        'extending' => ['name' => 'ownThing', 'reports' => true, 'traits' => true],
    ];

    $delta = static function (array $before, array $after): array {
        $counted = [];

        foreach ($after as $counter => $count) {
            $counted[$counter] = $count - $before[$counter];
        }

        return $counted;
    };

    [$config, $ruleset] = buildRuleset([UNUSED_FORMAL_PARAMETER]);
    $sniff = $ruleset->sniffs[$ruleset->sniffCodes[UNUSED_FORMAL_PARAMETER]];

    foreach ($shapes as $shape => $spec) {
        foreach ([250, 500, 1000] as $size) {
            $base = '';
            $derived = '';

            for ($index = 0; $index < $size; $index++) {
                $base .= "    public function getThing{$index}(): int\n"
                    . "    {\n        return {$index};\n    }\n\n";
                $derived .= "    public function {$spec['name']}{$index}(int \$unused{$index}): int\n"
                    . "    {\n        return {$index};\n    }\n\n";
            }

            $source = "<?php\n\nclass Base\n{\n" . $base . "}\n\n"
                . "class Derived extends Base\n{\n" . $derived . "}\n";

            $path = sys_get_temp_dir() . '/' . uniqid('cleancode-ufp-ancestor-', true) . '.php';
            file_put_contents($path, $source);
            $before = $sniff->cacheCounts();

            try {
                $file = new LocalFile($path, $ruleset, $config);
                $file->process();
                $reported = $file->getErrorCount();
            } finally {
                unlink($path);
            }

            $counted = $delta($before, $sniff->cacheCounts());
            $byAncestor = $sniff->cacheCountsByClass();

            // The first of the n descendant methods builds the ancestor's
            // index and the other n-1 answer from it. A walk that went back to
            // reading the ancestor once per descendant method reports n builds
            // and no hits. The overriding shape never reaches traitNames() at
            // all, which is why its expectation there is no read of either kind.
            $traitReads = $spec['traits'] === true
                ? ['builds' => 1, 'hits' => $size - 1]
                : ['builds' => 0, 'hits' => 0];

            expect($reported)->toBe(
                $spec['reports'] === true ? $size : 0,
                "{$shape} n={$size}: the override exemption still resolves"
            )
                ->and($counted['methodNames.builds'])->toBe(
                    1,
                    "{$shape} n={$size}: the ancestor's method list is read once for the file"
                )
                ->and($counted['methodNames.hits'])->toBe(
                    $size - 1,
                    "{$shape} n={$size}: every later descendant method answers from that read"
                )
                ->and(array_values($byAncestor['methodNames']))->toBe(
                    [['builds' => 1, 'hits' => $size - 1]],
                    "{$shape} n={$size}: one ancestor, its method list built once"
                )
                ->and($counted['traitNames.builds'])->toBe(
                    $traitReads['builds'],
                    "{$shape} n={$size}: the ancestor's trait list is read once, or never reached"
                )
                ->and($counted['traitNames.hits'])->toBe(
                    $traitReads['hits'],
                    "{$shape} n={$size}: every later descendant method answers from that read"
                )
                ->and(array_values($byAncestor['traitNames']))->toBe(
                    $spec['traits'] === true ? [['builds' => 1, 'hits' => $size - 1]] : [],
                    "{$shape} n={$size}: one ancestor, its trait list built once, or never reached"
                );
        }
    }
});

/**
 * The class-like index this sniff builds once per token stream must not answer
 * one analysis with another analysis's pointers (#343).
 *
 * The index used to be keyed by file name, token count and fixer-loop counter.
 * Two sources analysed as STDIN report the same name, so two of them that also
 * tokenise to the same count shared one key — and a single `Ruleset` reused
 * across several analyses, which is what buildRuleset()'s memoisation gives
 * every call below, hands them one sniff instance and one index.
 *
 * The two sources here tokenise to 67 tokens each — the ancestor's method is
 * named `run` in A and `walk` in B, one token either way — and differ in
 * exactly what the index records: the ancestor's method list. In A, `Kid::run()`
 * overrides `Base::run()`, so its unread `$alpha` is exempt; in B nothing named
 * `run` exists to override, so `$alpha` is dead. Under the old key B read A's
 * method list, inherited the override exemption, and reported nothing.
 *
 * The third call is what separates a working key from no cache at all: it
 * re-analyses A and requires its silence back, which a sniff that had simply
 * stopped caching would also give — but a sniff whose index leaked between
 * streams would not, since B's stream would by then have overwritten it.
 */
it('keeps its class-like index from answering another STDIN analysis', function (): void {
    $sourceA = <<<'PHP'
        <?php

        class Base
        {
            public function run($alpha)
            {
                return $alpha;
            }
        }

        class Kid extends Base
        {
            public function run($alpha)
            {
                return 1;
            }
        }

        PHP;

    $sourceB = <<<'PHP'
        <?php

        class Base
        {
            public function walk($alpha)
            {
                return $alpha;
            }
        }

        class Kid extends Base
        {
            public function run($alpha)
            {
                return 1;
            }
        }

        PHP;

    $first = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $sourceA);
    $second = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $sourceB);
    $third = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $sourceA);

    expect(count($first->getTokens()))->toBe(count($second->getTokens()))
        ->and(tuplesFromMessages($second->getErrors()))->toBe([
            ['line' => 13, 'column' => 25, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ])
        ->and(violationMessagesByLine($second->getErrors()))->toBe([
            13 => [
                'The method run() never reads its parameter $alpha; remove it from the '
                    . 'signature, or mark the method as an override with #[\\Override] or '
                    . '@inheritdoc if the signature is imposed from outside '
                    . '(see docs/phpmd/unusedcode-unusedformalparameter.md)',
            ],
        ])
        ->and(tuplesFromMessages($third->getErrors()))->toBe([]);
});

/**
 * The class-like index is built once for a token stream and read from for the
 * rest of it, when the analysis is a DummyFile rather than a file on disk
 * (#343).
 *
 * The scale test above makes the same claim for a LocalFile, and made it before
 * this sniff's key named the token stream by the File object's identity rather
 * than by its name, token count and fixer loop. Two DummyFile analyses report
 * the same name, which is the collision #343 is about, and the fix has to close
 * it without costing the reuse the index exists for. The test above that one
 * proves the first half; this proves the second on the very shape the fix
 * changed, and neither can be shown from outside the sniff: a sniff that
 * rebuilt the index on every read would report exactly the same violations,
 * only slower.
 *
 * Both numbers are pinned, and each rules out a different failure:
 *
 * - one build per stream, at any size, is the claim itself;
 * - 2n-1 hits keeps it from passing vacuously, since a sniff that stopped
 *   consulting the index at all would report one build and no hits. Each method
 *   below reaches the index twice — once from qualifiedNames(), once from
 *   declarationsByName() — so n methods total 2n reads, of which one builds and
 *   2n-1 hit. The build is one the guard never had to answer.
 *
 * Mutation-checked by deleting the `$this->declarationsKey === $key` guard, so
 * every read rebuilds: `composer test` then fails here at the smallest size,
 * n=2, reading 4 builds / 0 hits against the 1 / 3 asserted; n=4 reads 8 / 0
 * against 1 / 7, and n=8 reads 16 / 0 against 1 / 15.
 */
it('builds its class-like index once per STDIN stream, not once per read', function (): void {
    $sniff = sniffInstance(UNUSED_FORMAL_PARAMETER);

    foreach ([2, 4, 8] as $size) {
        $methods = '';

        for ($index = 0; $index < $size; $index++) {
            $methods .= "    public function take{$index}(int \$unused{$index}): int\n    {\n"
                . "        return {$index};\n    }\n\n";
        }

        $source = "<?php\n\nclass Big\n{\n" . $methods . "}\n";

        $before = $sniff->cacheCounts();
        $file = analyzeStdinSource([UNUSED_FORMAL_PARAMETER], $source);
        $counted = cacheCountsDelta($before, $sniff->cacheCounts());

        expect($file->getErrorCount())->toBe($size, "n={$size}: every unused parameter is still reported")
            ->and($counted['declarations.builds'])->toBe(
                1,
                "n={$size}: the index is built once for the stream, not once per read"
            )
            ->and($counted['declarations.hits'])->toBe(
                (2 * $size) - 1,
                "n={$size}: every read after the first answers from the index already built"
            );
    }
});

/**
 * The namespace in force is the block's, not the file's. `compact()` is what
 * makes that observable: reaching PHP's own compact() exempts the parameter its
 * argument names, and reaching another namespace's function of the same short
 * name does not, so namespace-blocks.php gives the identical line opposite
 * verdicts in an unnamed block and a named one.
 *
 * The unnamed block has no unbraced spelling — only a braced block can be
 * unnamed — so this case cannot be folded into passing.php or divergences.php,
 * for the same reason namespaces.php exists.
 *
 * Mutation-confirmed against the pre-#320 sniff itself: restored from `main`, it
 * reports nothing in this file. Its hand-rolled copy stepped over the qualifier
 * and matched the short name exactly as PHPMD does, so it read both lines as
 * PHP's own compact() and exempted both, losing line 40. The unnamed block's
 * silence is the other half: it is what stops the fix being "no `namespace\`
 * name ever exempts".
 */
it('resolves a namespace-relative exempting call against the enclosing block', function (): void {
    $file = analyzeFixture(UNUSED_FORMAL_PARAMETER, 'namespace-blocks.php');

    expect(violationTuples($file))->toBe([
        // Only the named block's parameter. The unnamed block's `namespace\compact()`
        // is PHP's own, so it exempts $unusedA and nothing is reported there.
        ['line' => 40, 'column' => 50, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
    ])->and($file->getWarnings())->toBe([]);
});

/**
 * interpolatedNames() collects the variable names a string interpolates, and a
 * parameter named by one of them is a parameter in use. A failed read that is
 * not guarded reads `$matches['name']` off an untouched $matches — null at
 * every call site here — and hands the result to array_fill_keys(), which
 * declared it takes an array.
 *
 * The empty list is the guard's exit, and it is the same answer the sniff
 * reaches without the guard on a runtime failure, so the verdict alone cannot
 * tell them apart. The read is what differs, and PHP announces it: see
 * withPhpDiagnostics().
 */
it('collects no interpolated name when the string cannot be read', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_match_all',
            static fn (): array => violationSourcesByLine(
                analyzeFixture(UNUSED_FORMAL_PARAMETER, 'failing.php')->getErrors()
            ),
            static fn (string $pattern): bool => str_contains($pattern, '(?P<name>')
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});
