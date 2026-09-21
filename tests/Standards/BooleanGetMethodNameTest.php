<?php

/**
 * Tests the custom CleanCode.Naming.BooleanGetMethodName sniff (PHPMD Naming
 * BooleanGetMethodName, #116). Fixtures live in
 * tests/fixtures/BooleanGetMethodNameSniff/, and the mapping is documented in
 * docs/phpmd/naming-booleangetmethodname.md.
 *
 * Every expectation below was cross-checked against a live PHPMD 2.15.0 run
 * over the same fixture files, because the point of the sniff is that `phpmd`
 * no longer has to run for this rule:
 *
 *   phpmd tests/fixtures/BooleanGetMethodNameSniff/<fixture>.php text naming
 *
 * PHPMD reports all twelve lines of failing.php and all four of
 * configured.php, and stays silent on passing.php and divergences.php. The
 * sniff matches it line for line on failing.php, passing.php, and
 * configured.php — in both of configured.php's property states — and adds the
 * six reports of divergences.php, so its output is a strict superset and no
 * PHPMD finding is lost.
 *
 * The rule is detection-only. Renaming `getX()` to `isX()` rewrites every call
 * site, and choosing between `is` and `has` is a judgement about what the
 * method asks, so there is no autofixed fixture.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

use MikeBronner\CleanCode\Tests\PregFailure;

const BOOLEAN_GET_METHOD_NAME = 'CleanCode.Naming.BooleanGetMethodName';

const BOOLEAN_GET_METHOD_NAME_ERROR = BOOLEAN_GET_METHOD_NAME . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(BOOLEAN_GET_METHOD_NAME);
});

/**
 * The compliant fixture carries the constructs the sniff registers on —
 * methods with and without a doc comment, with and without a native return
 * type — plus the near-miss shapes it must stay silent on:
 *
 * - line 13, a `bool` *property* whose name starts with `get`. Not a method.
 * - lines 20 and 28, the compliant `isPublished()` and `hasAuthor()` spellings
 *   the rule asks for.
 * - lines 38 and 46, `get`-prefixed methods returning a string and an int.
 * - line 58, the union `bool|string`: a value, not a yes/no answer. Widening
 *   the type check to "mentions bool" would report it.
 * - line 67, `bool` as a *parameter* type and as the type of a local, never as
 *   the return type.
 * - line 80, the misspelled `@returns` tag — not an `@return` tag, so nothing
 *   declares a return type.
 * - line 89, neither a doc comment type nor a native return type.
 * - line 100, `forgetCache()`. `forget` contains `get`, and the prefix has to
 *   be at the start.
 * - line 114, `getIndexable()`, whose nearest doc comment above belongs to the
 *   constant on line 112. A declaration only owns the comment immediately
 *   above it; if it borrowed that one, this line would be reported.
 * - line 126, `getPromotable()`, whose `@return` tag carries no type and is
 *   followed by `@see bool`. Reading forward to the next doc-comment *string*
 *   without stopping at the next *tag* would report this line.
 * - line 138, the plain function `getVerified()`, and lines 147 and 151 a
 *   closure and an arrow function. PHPMD's rule is MethodAware, so it visits
 *   none of them, and neither does this sniff.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every shape PHPMD itself reports, at the method's *name* token — the name is
 * what the rule asks to be changed, so that is where the caret points. The
 * three columns that are not 21 are the declarations whose modifiers make the
 * name start further along the line: `public static function` (52),
 * `abstract protected function` (60), and the anonymous class's extra
 * indentation in divergences.php.
 *
 * - line 12, `@return boolean`, and line 20 `@return bool` — both spellings.
 * - line 28, a type followed by a description, on a method that *takes* a
 *   parameter: reported by default, since `checkParameterizedMethods` is off.
 * - line 36, `_getArchived()` with `@return BOOLEAN` — PHPMD's name pattern
 *   allows the leading underscore and its type pattern is case-insensitive.
 * - line 44, `getterCached()`, and line 52 `GetDraft()`. PHPMD's pattern is
 *   `(^_?get)i`, so neither a capital after `get` nor a lower-case `g` is
 *   required. Tightening the sniff to the `^get[A-Z_]` that phpmd.org's rule
 *   page paraphrases would silently drop these two and line 36 with them.
 * - lines 60 and 68, an abstract method and an interface method — neither has
 *   a body, and neither needs one.
 * - lines 76 and 89, a trait method and an enum method.
 * - line 104, a doc comment separated from its declaration by an attribute,
 *   and line 115 the same pair in the other order. The comment search steps
 *   over attributes; treating one as the end of the search drops line 104.
 */
it('flags every boolean getter in the failing fixture', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php');

    expect(violationTuples($file))->toBe([
        ['line' => 12, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 20, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 28, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 36, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 44, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 52, 'column' => 28, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 60, 'column' => 33, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 68, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 76, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 89, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 104, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 115, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

/**
 * The message names the method, so a report over a whole codebase says which
 * declaration to rename, and it names both replacements PHPMD's own message
 * offers.
 */
it('names the method and both replacements in the message', function (): void {
    $errors = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors();

    expect($errors[12][21][0]['message'])
        ->toContain('getVisible()')
        ->toContain('is...()')
        ->toContain('has...()');
});

/**
 * The six shapes where this ruleset reports and PHPMD does not, all confirmed
 * silent under a live `phpmd ... naming` run over this very file:
 *
 * - lines 13, 21, and 29, a native `bool`, `?bool`, and `bool|null` return
 *   type with no `@return` tag at all. PHPMD reads only the doc comment, so a
 *   native declaration never reaches its check. #116 requires this half, and
 *   CleanCode/ruleset.xml requires a native return type on every method
 *   (SlevomatCodingStandard.TypeHints.ReturnTypeHint), so it is the shape that
 *   actually occurs in code this ruleset governs.
 * - lines 40 and 51, the doc-comment types `?bool` and `bool|null`. PHPMD's
 *   pattern wants `bool` or `boolean` directly after `@return ` and whitespace
 *   directly after that, so the `?` and the `|` each hide the type from it.
 * - line 67, a method of an *anonymous* class. PDepend does not surface one to
 *   a MethodAware rule; a token scan sees no difference between it and any
 *   other method.
 *
 * Line 85 is the other half of that last decision and is reported by neither
 * tool: a *named function declared inside a method* is a function, not a
 * method. Its `conditions` list still holds the enclosing class, so a sniff
 * that looked for any class-like condition rather than the innermost one would
 * report it — and PHPMD, which never sees a nested function at all, would not.
 *
 * All six extra reports are true defects, so they are kept — the same call
 * CleanCode/ruleset.xml records for VariableAnalysis. Neither direction loses a PHPMD
 * finding, which is what keeps `phpmd` out of the pipeline for this rule.
 */
it('reports the shapes PHPMD misses, and no more', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'divergences.php');

    expect(violationTuples($file))->toBe([
        ['line' => 13, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 21, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 29, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 40, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 51, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 67, 'column' => 29, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

/**
 * `checkParameterizedMethods` ships `false`, exactly as PHPMD's naming.xml
 * ships it: out of the box every boolean getter is reported, however many
 * parameters it takes. This run is the baseline the property test below is
 * measured against — without it, a property that silenced the sniff outright
 * would look like a working exemption.
 *
 * Line 14 is parameterless; lines 25, 36, and 46 take a required parameter, an
 * optional one, and a variadic.
 */
it('reports parameterized methods by default', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'configured.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 25, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 36, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
        ['line' => 46, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

/**
 * Turning `checkParameterizedMethods` on *narrows* the rule to parameterless
 * methods — the direction is easy to read backwards from PHPMD's own property
 * description ("Applies only to methods without parameter when set to true"),
 * so it was taken from PHPMD 2.15.0's implementation and confirmed against a
 * live run over this fixture with the property set: four reports off, one on.
 *
 * Only line 14 survives. Line 36's parameter is optional and line 46's is
 * variadic, and both still count — PHPMD asks PDepend for the parameter
 * *count*, so a signature that can be called with no arguments is not the same
 * thing as a signature that declares none.
 */
it('reports only parameterless methods when checkParameterizedMethods is on', function (): void {
    $file = analyzeFixture(
        BOOLEAN_GET_METHOD_NAME,
        'configured.php',
        static function (object $sniff): void {
            $sniff->checkParameterizedMethods = true;
        }
    );

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 21, 'source' => BOOLEAN_GET_METHOD_NAME_ERROR],
    ]);
});

/**
 * Pins the detection-only decision: renaming a method rewrites every call site,
 * and `is` versus `has` is a judgement about what the method asks, so no
 * violation is offered to the fixer. PHPMD has no fix for this rule either.
 *
 * The severity is error, matching PHPMD, where a BooleanGetMethodName
 * violation fails the run. Reported as a warning, `phpcs` would exit 0 and
 * `phpmd` would still have to run for this rule.
 */
it('reports detection-only errors', function (): void {
    $file = analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php');

    expect($file->getErrorCount())->toBe(12)
        ->and($file->getWarningCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0)
        ->and(violationFixableFlags($file))->each->toBeFalse();
});

/**
 * A docblock annotation is split on whitespace so its first piece can be read
 * as the type. A failed split is false, and `false[0]` reads an offset off a
 * boolean rather than failing loudly. The guard falls back to the whole
 * annotation, which still starts with the written type — only a trailing
 * description rides along, and a description is enough to stop the annotated
 * method reading as `bool`.
 *
 * That is visible: the method typed only by its annotation stops being
 * reported, while every method with a native `bool` return stays reported.
 */
it('reads an unsplittable annotation as one piece', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_split',
            static fn (): array => violationSourcesByLine(
                analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors()
            ),
            static fn (string $pattern): bool => $pattern === '/\s+/'
        );
    });

    expect(array_keys($expected))->toContain(28)
        ->and(array_keys($degraded))->not->toContain(28)
        ->and($diagnostics)->toBe([]);
});

/**
 * isBooleanType() normalises a type before deciding whether it is `bool`. A
 * failed read cast to a string is '', which resolves to no members and reads
 * exactly like a type that is not boolean, so the failure would silently exempt
 * the method from the check. The guard falls back to the written type, which
 * resolves identically for every type PHPCS hands over — so every boolean
 * getter stays reported.
 */
it('reads a type that cannot be normalised as written', function (): void {
    $expected = violationSourcesByLine(analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors());

    [$degraded, $diagnostics] = withPhpDiagnostics(static function (): array {
        return PregFailure::during(
            'preg_replace',
            static fn (): array => violationSourcesByLine(
                analyzeFixture(BOOLEAN_GET_METHOD_NAME, 'failing.php')->getErrors()
            ),
            static fn (string $pattern): bool => $pattern === '/\s+/'
        );
    });

    expect($expected)->not->toBe([])
        ->and($degraded)->toBe($expected)
        ->and($diagnostics)->toBe([]);
});
