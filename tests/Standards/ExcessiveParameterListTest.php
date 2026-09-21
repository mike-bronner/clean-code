<?php

/**
 * Tests the custom CleanCode.Functions.ExcessiveParameterList sniff (PHPMD
 * CodeSize ExcessiveParameterList, #95). Fixtures live in
 * tests/fixtures/ExcessiveParameterListSniff/, and the mapping is documented in
 * docs/phpmd/codesize-excessiveparameterlist.md.
 *
 * Every expectation below was cross-checked against a live PHPMD 2.15.0 run
 * over these very fixture files, because the point of the sniff is that `phpmd`
 * no longer has to run for this rule. PHPMD reports failing.php lines 7, 12,
 * 19, 26, 40, 44, 48, 51, 53, 58, 74, and 76, and boundaries.php lines 23 and
 * 27; it stays silent on passing.php and divergences.php. This sniff
 * reproduces all fourteen reports, on the same lines and with the same
 * method/function wording, and adds exactly one PHPMD misses (divergences.php)
 * — so its output is a strict superset and no PHPMD finding is lost.
 *
 * The threshold is *inclusive*, which is the single most important thing these
 * tests pin. PHPMD's rule body is `if ($count < $threshold) { return; }`, so a
 * declaration with exactly `minimum` parameters is reported. phpmd.org's prose
 * ("reducing the number of parameters to less than 10") and #95's acceptance
 * criteria ("≤10 → no violation") both read like a strict `>`; the tool
 * disagrees with them, and the tool is what this package has to replace.
 * boundaries.php exists to hold that line: flip the sniff's comparison to `>`
 * and the ten-parameter assertions here go red.
 *
 * The rule is detection-only. Collapsing a parameter list into an object means
 * introducing that object and rewriting every call site, so there is no
 * autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

const EXCESSIVE_PARAMETER_LIST = 'CleanCode.Functions.ExcessiveParameterList';

const EXCESSIVE_PARAMETER_LIST_ERROR = EXCESSIVE_PARAMETER_LIST . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(EXCESSIVE_PARAMETER_LIST);
});

/**
 * The compliant fixture carries the constructs the sniff registers on — an
 * interface method, a constructor, a plain method, an abstract method, a
 * variadic method, a zero-parameter method, a plain function — every one of
 * them at nine declared parameters, one below the default threshold. Alongside
 * them sit the near-miss shapes the sniff must stay silent on:
 *
 * - line 26, `collect()`, eight named parameters plus `...$rest`. A variadic
 *   counts once, exactly as PDepend counts it, so this is nine and not ten;
 *   counting it twice, or skipping it, would move the total either side.
 * - line 35, a closure, and line 38, an arrow function — both declaring
 *   *twelve* parameters. PHPMD's rule implements FunctionAware and MethodAware
 *   only, so it never visits either, and registering on T_FUNCTION alone
 *   reproduces that. Twelve rather than ten so the fixture would trip a
 *   T_CLOSURE/T_FN registration loudly rather than marginally.
 * - line 40, a *call* passing twelve arguments. The rule counts declared
 *   parameters, not passed arguments; a sniff that reached for the parenthesis
 *   pair without checking what owns it would report here.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every reported shape, on the line of its `function` keyword — which is where
 * PHPMD reports too, including for `wrapped()` on line 58, whose signature is
 * spread over eleven lines.
 *
 * - line 7, an interface method, and line 48, an abstract method: neither has
 *   a body, and neither needs one, since the count comes from the signature.
 * - line 12, a trait method, and line 19, an enum method.
 * - line 26, a constructor declaring ten *promoted* properties. Promotion does
 *   not stop them being parameters, for PDepend or for PHPCS.
 * - line 40, an ordinary method at exactly ten — the inclusive boundary.
 * - line 44, `collect()`, nine named parameters plus `...$rest`, so ten in
 *   total: the variadic is what tips it over, and its sibling on passing.php
 *   line 26 is the same shape one parameter short.
 * - line 51, a plain function at eleven, and line 53, a named function nested
 *   inside it, at ten.
 * - line 74, a method, and line 76, a named function declared inside that
 *   method's body. Both are reported, and they are reported with *different*
 *   subjects — see the wording test below, which is the reason this pair is in
 *   the fixture at all.
 */
it('flags every excessive parameter list in the failing fixture', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 7, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 12, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 19, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 26, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 40, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 44, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 48, 'column' => 21, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 51, 'column' => 1, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 53, 'column' => 5, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 58, 'column' => 1, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 74, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 76, 'column' => 9, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

/**
 * The message names the declaration, its actual parameter count, and the
 * threshold it reached, so a report over a whole codebase says which signature
 * to open and by how much it overshot. PHPMD's own message carries the same
 * four facts.
 */
it('names the declaration, the count, and the threshold in the message', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php')->getErrors();

    expect($errors[48][21][0]['message'])
        ->toContain('method draw()')
        ->toContain('11 parameters')
        ->toContain('maximum of 10');
});

/**
 * "method" versus "function" follows the *innermost* enclosing scope, not the
 * mere presence of a class somewhere above the declaration.
 *
 * `insideMethod()` on failing.php line 76 is the shape that discriminates. It
 * is a named function declared in the body of `build()`, which is a method of
 * `Host` — so its conditions hold T_CLASS *and* T_FUNCTION, and the two
 * candidate implementations disagree about it. Walking outwards and stopping
 * at the first scope of either kind reports "function"; the obvious
 * simplification — asking only whether a class-like token appears anywhere
 * among the conditions — reports "method", naming a subject that does not
 * exist. A live PHPMD 2.15.0 run over this fixture says "The function
 * insideMethod", so the walk is the one that matches.
 *
 * `nested()` on line 53 deliberately does *not* carry this assertion: it sits
 * inside `summarize()`, which sits at file scope, so no class appears in its
 * conditions and both implementations call it a function. Pinning the
 * distinction there would look like coverage and prove nothing — confirmed by
 * mutation, which left an earlier version of this test green.
 */
it('calls a function nested inside a method a function, not a method', function (): void {
    $errors = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php')->getErrors();

    expect($errors[76][9][0]['message'])->toContain('function insideMethod()')
        ->and($errors[74][12][0]['message'])->toContain('method build()')
        ->and($errors[53][5][0]['message'])->toContain('function nested()')
        ->and($errors[7][12][0]['message'])->toContain('method format()');
});

/**
 * The inclusive boundary, at PHPMD's shipped default of 10 and with no
 * configuration applied. boundaries.php declares methods of four, five, six,
 * nine, ten, and eleven parameters; only the last two are reported.
 *
 * Nine and ten are the pair that matters. A sniff comparing `>` instead of
 * `>=` still reports `eleven()` on line 27 and so still looks like it works —
 * line 23 is the only assertion that separates the two, which is why the
 * fixture carries a declaration at exactly the threshold rather than only
 * comfortably over it.
 */
it('reports at exactly the default threshold and not one below it', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'boundaries.php');

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 27, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

/**
 * The `minimum` property, spelled as PHPMD spells it, set the way a consuming
 * project actually sets it: in ruleset XML, which hands the sniff a *string*.
 * Driving it through real XML rather than through analyzeFixture()'s callback
 * is deliberate — see analyzeWithConfiguredRuleset() in tests/Helpers.php. A
 * native `int` type on the property survives the callback and dies here.
 *
 * At `minimum="5"` the boundary moves and stays inclusive: `four()` on line 7
 * (one inside) falls silent, `five()` on line 11 (exactly at) is reported, and
 * `six()` on line 15 (one outside) is reported. Asserting all three is what
 * catches an off-by-one that a test using only a distant threshold would miss.
 * A live PHPMD 2.15.0 run with the same property override reports exactly
 * these five lines.
 */
it('honours a minimum configured in ruleset XML, inclusively', function (): void {
    $file = analyzeWithConfiguredRuleset(
        EXCESSIVE_PARAMETER_LIST,
        'boundaries.php',
        ['minimum' => '5']
    );

    expect(violationTuples($file))->toBe([
        ['line' => 11, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 15, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 19, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 23, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 27, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

/**
 * A configuration mistake degrades to the documented default instead of
 * producing nonsense or taking the run down.
 *
 * `value="ten"` is the shape that matters: with a native `int` property this
 * exact ruleset aborts phpcs with an uncaught TypeError out of Ruleset.php
 * before a single file is scanned. The `int|string` union plus threshold()'s
 * validation is what turns that into "fall back to 10", and this test is the
 * only thing standing between the property and a well-meaning tightening of
 * its type.
 *
 * Zero is the other half. Left as written it would report *every* declaration
 * in a codebase, including one taking no parameters at all, so it is rejected
 * rather than honoured — boundaries.php would otherwise report all six of its
 * methods.
 *
 * @param string $configured
 */
it('falls back to the default minimum when the property is unusable', function (string $configured): void {
    $file = analyzeWithConfiguredRuleset(
        EXCESSIVE_PARAMETER_LIST,
        'boundaries.php',
        ['minimum' => $configured]
    );

    expect(violationTuples($file))->toBe([
        ['line' => 23, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
        ['line' => 27, 'column' => 12, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
})->with(['ten', '0', '-3', '', '7.5']);

/**
 * The one shape where this ruleset reports and PHPMD does not, confirmed
 * silent under a live `phpmd ... codesize` run over this very file: a method
 * of an *anonymous* class, at line 8, declaring ten parameters.
 *
 * PDepend never surfaces an anonymous class's methods to a MethodAware rule,
 * so PHPMD cannot see it. That is a gap in PHPMD rather than a decision about
 * the rule, and reproducing it would mean writing code whose only job is to
 * suppress a real defect. The extra report is kept, which leaves this sniff a
 * strict superset — the direction that keeps `phpmd` out of the pipeline.
 * `makeHandler()` itself takes no parameters and is correctly untouched.
 */
it('reports the anonymous-class method PHPMD misses', function (): void {
    $file = analyzeWithSniffs(
        [EXCESSIVE_PARAMETER_LIST],
        fixturePath('ExcessiveParameterListSniff', 'divergences.php')
    );

    expect(violationTuples($file))->toBe([
        ['line' => 8, 'column' => 16, 'source' => EXCESSIVE_PARAMETER_LIST_ERROR],
    ]);
});

/**
 * Detection only, matching PHPMD: no violation offers the fixer a hook, so
 * `phpcbf` leaves the failing fixture exactly as it found it. Asserting the
 * flags rather than only the fixed-count means a fixer added later cannot slip
 * in silently against a rule that has no safe mechanical rewrite.
 */
it('reports without offering a fix', function (): void {
    $file = analyzeFixture(EXCESSIVE_PARAMETER_LIST, 'failing.php');

    expect(violationFixableFlags($file))->toBe(array_fill(0, 12, false))
        ->and($file->getFixableCount())->toBe(0);
});
