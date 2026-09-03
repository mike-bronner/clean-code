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
