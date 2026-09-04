<?php

/**
 * CleanCode.TypeHints.ParameterTypeHint and CleanCode.TypeHints.PropertyTypeHint
 * — the two Slevomat sniffs, subclassed so they stop asking for a type PHP
 * refuses to accept.
 *
 * Both defects are runtime, not style. Typing a parameter an ancestor declares
 * untyped is a narrowing violation PHP rejects at declaration time: a Livewire
 * component that did so stopped an application booting. Typing a PHP_CodeSniffer
 * sniff property throws TypeError in the consumer's run, because PHPCS assigns
 * those from ruleset XML as strings — `value="3"` is "3", never 3.
 *
 * The skip is deliberately narrow. A resolvable ancestor that types everything
 * it declares constrains nothing, and an ancestor that cannot be resolved during
 * a lint run answers nothing — both keep reporting, so the subclass can only
 * ever hide a violation it has proven unfixable.
 */

declare(strict_types=1);

const CLEANCODE_PARAMETER_TYPE_HINT = 'CleanCode.TypeHints.ParameterTypeHint';

const CLEANCODE_PROPERTY_TYPE_HINT = 'CleanCode.TypeHints.PropertyTypeHint';

/**
 * Non-vacuous by mutation: making overridesUntypedParameter() return true
 * always empties this list; returning false always adds line 26, the
 * Sniff-interface override that cannot legally be typed.
 */
it('skips a parameter an ancestor declares untyped, and reports every other', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect(array_keys($file->getErrors()))->toBe([11, 34, 44]);
});

it('stays silent on the declaration whose ancestor leaves the parameter untyped', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->not->toHaveKey(26, 'process() overrides an untyped Sniff::process()');
});

/**
 * The skip is proof-based, not name-based: a parent that types what it declares
 * constrains nothing, so its child is still held to the rule.
 *
 * Non-vacuous by mutation: skipping whenever any ancestor exists drops line 34 —
 * the ancestor here is PHP_CodeSniffer's Sniff, which really is loadable.
 */
it('still reports a sibling declaration its loadable ancestor does not declare', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->toHaveKey(34);
});

/**
 * An ancestor a lint run cannot load answers nothing, so the sniff reports as
 * it did before. Failing the other way would let an unresolvable parent silence
 * a real violation.
 *
 * Non-vacuous by mutation: skipping on an unresolvable ancestor drops line 44.
 */
it('still reports a declaration whose ancestor cannot be resolved', function (): void {
    $file = analyzeFixture(CLEANCODE_PARAMETER_TYPE_HINT, 'failing.php');

    expect($file->getErrors())->toHaveKey(44);
});

/**
 * Non-vacuous by mutation: making isCodeSnifferClass() return true always
 * empties this list; returning false always adds lines 19 and 21, the two
 * ruleset-configurable properties a native type would break.
 */
it('skips a PHP_CodeSniffer class property, and reports an ordinary one', function (): void {
    $file = analyzeFixture(CLEANCODE_PROPERTY_TYPE_HINT, 'failing.php');

    expect(array_keys($file->getErrors()))->toBe([11]);
});

const INFERRED_RETURN_TYPE = 'CleanCode.TypeHints.InferredReturnType';

/**
 * CleanCode.TypeHints.InferredReturnType — the fixer
 * SlevomatCodingStandard.TypeHints.ReturnTypeHint cannot carry.
 *
 * That sniff writes a native hint only where a @return annotation already
 * states one, so a codebase with no docblocks gets a report and no fix. This
 * one writes the type wherever it is *provable* from the source: a type a
 * resolvable ancestor already declares (read by reflection), a body whose
 * returns are all literals, one returning $this, one returning a typed
 * parameter. Everything else it leaves alone, because a guessed return type is
 * not a lint finding in the consumer's code — it is a TypeError at runtime.
 *
 * It runs *alongside* the Slevomat sniff rather than extending it. Subclassing
 * silently drops reports: that sniff builds every message code from a
 * `private const NAME` through a `private` method using `self::`, so a subclass
 * can override neither, and the branches resolving severity through the
 * hardcoded name vanish once the parent is unregistered. Measured: lines 36 and
 * 118 of tests/fixtures/_rulesets/MethodTypeHints/failing.php disappeared
 * entirely under a subclass, with pure delegation and no interception at all.
 *
 * The reflection case extends SlevomatCodingStandard\Helpers\ClassHelper rather
 * than a fixture parent: a fixture class is not autoloadable, so class_exists()
 * is false for it and the ancestor path would be skipped — the test would pass
 * while exercising the wrong branch.
 */
it('reports only the return types it can prove', function (): void {
    $file = analyzeFixture(INFERRED_RETURN_TYPE, 'failing.php');

    expect(array_keys($file->getErrors()))->toBe([40, 50, 60, 66, 72, 95, 97, 110])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Non-vacuous by mutation: making ReturnTypeInference::infer() return null
 * always empties the list above; returning a fixed string writes that string
 * onto every declaration, the unprovable and magic ones included.
 */
it('writes each proven type onto its signature', function (): void {
    $fixed = autofixedContents(analyzeFixture(INFERRED_RETURN_TYPE, 'failing.php'));

    expect($fixed)
        ->toContain('public function bothBooleans(int $flag): bool')
        ->toContain('public function stringOrNull(int $flag): ?string')
        ->toContain('public function returnsThis(): static')
        ->toContain('public function typedParameter(File $phpcsFile): File')
        ->toContain('public function valueOrBareReturn(int $flag): ?int')
        ->toContain('public function closureReturnIsNotMine(): true')
        // Reflection: copied down from ClassHelper::getName(): string.
        ->toContain('public static function getName($phpcsFile, $classPointer): string')
        // Ceded to the Slevomat sniff, which owns the returns-nothing case.
        ->toContain('public function noReturnAtAll()' . "\n")
        ->toContain('public function bareReturnOnly(int $flag)' . "\n")
        // A constructor may not carry a return type at all.
        ->toContain('public function __construct(private int $flag = 0)' . "\n")
        // Not provable, so left exactly as written.
        ->toContain('public function unprovableCall(File $phpcsFile, int $ptr)' . "\n")
        ->toContain('public function unprovableExpression(int $flag)' . "\n");
});
