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
 * 91, 112, 122, 128, 136, 144, 154 and 164 — eighteen findings, naming $unusedA
 * through $unusedR (with $unusedJ absent, since that one is read) plus $id.
 * This sniff reproduces all eighteen, on the same lines, naming the same
 * parameters. On
 * passing.php both tools are silent. On divergences.php PHPMD reports three of
 * the six this sniff reports, and the three it skips are the constructs
 * PDepend never surfaces to a MethodAware rule.
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
 * - a read that happens only inside a nested closure, and one only inside a
 *   nested arrow function;
 * - `func_get_args()` both directly and from inside a nested closure;
 * - `compact('kappa')` naming the parameter;
 * - bodyless interface and abstract declarations;
 * - same-file override resolution, at one level, transitively at two, through
 *   an interface reached via the parent's own `implements` clause, and onto a
 *   method the parent draws from a trait;
 * - `@inheritdoc` bare, braced and mixed-case, and `#[\Override]` both alone
 *   and below a second attribute;
 * - promoted constructor properties, by visibility and by `readonly`;
 * - all seven fixed-signature magic methods.
 *
 * `prefixNearMiss()` is the one shape that is not an exemption: it reads
 * `$lambdaExtra`, which contains `$lambda` as a prefix, and reads `$lambda`
 * itself on its own line. Delete that second line and the word boundary in the
 * sniff's read test is the only thing standing between silence and a correct
 * report — which is what makes the boundary load-bearing rather than
 * decorative.
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
 *   makes the word boundary in the sniff's read test load-bearing: without it
 *   the prefix match counts as a read and this finding disappears silently.
 *   Mutation-checked — removing the `\b` reddens this test and nothing else.
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
    ]);
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
 * - line 59, a method of a class extending a base declared in another file.
 *   Both tools report it *here*, because PHPMD running over one file cannot
 *   see that base either.
 * - line 84, the second parameter of a signature spread across three lines.
 *   Column 12 is what proves the anchor is the parameter and not the
 *   declaration: the `function` keyword is two lines above, at column 1.
 * - line 94, a dynamic read. Neither tool resolves `${'unusedG'}`.
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
        ['line' => 59, 'column' => 35, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 84, 'column' => 12, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
        ['line' => 94, 'column' => 29, 'source' => UNUSED_FORMAL_PARAMETER_ERROR],
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
