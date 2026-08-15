<?php

/**
 * Tests the custom CleanCode.Methods.DeclaredParameters sniff (Methods:
 * Declared Parameters, #69). Fixtures live in
 * tests/fixtures/DeclaredParametersSniff/: every shape that must stay silent in
 * passing.php, the flagged dynamic-argument reads in failing.php, and one
 * fixture per resolution rule the sniff has to get right — scopes.php for the
 * boundary of the magic-method exemption, imports.php, imports-aliased.php and
 * imports-mixed-group.php for function-import resolution, global-namespace.php
 * and braced-namespaces.php for what `namespace\` resolves against.
 *
 * The rule is detection-only — declaring the parameter list a call reads
 * dynamically needs names no fixer can invent — so there is no autofixed
 * fixture, and that omission is pinned rather than assumed: the last test here
 * asserts every reported violation is non-fixable across all seven flagging
 * fixtures.
 *
 * The sniff is isolated from the rest of the master ruleset (analyzeFixture()
 * narrows the ruleset to it) so these assertions stay stable as sibling
 * standards land in rules.xml — "zero violations" on passing.php means zero
 * from *this* sniff.
 */

declare(strict_types=1);

const DECLARED_PARAMETERS = 'CleanCode.Methods.DeclaredParameters';

const DECLARED_PARAMETERS_ERROR = DECLARED_PARAMETERS . '.DynamicArguments';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(DECLARED_PARAMETERS);
});

/**
 * Declared parameters, variadics, magic methods, same-named members, a
 * namespaced lookalike, a `namespace\`-relative call inside a named namespace,
 * a return-by-reference declaration, an instantiation in both its unqualified
 * and its fully qualified spelling, and a same-named function declaration all
 * stay silent. Every one of them is a near miss the sniff must not fire on.
 */
it('stays silent on every compliant and near-miss shape', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'passing.php');

    expect($file->getErrors())->toBeEmpty();
    expect($file->getWarnings())->toBeEmpty();
});

it('flags every dynamic-argument read at its own line and column', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'failing.php');

    expect(violationTuples($file))->toBe([
        // func_get_args(), func_num_args(), and func_get_arg() each read
        // arguments the signature never declared.
        ['line' => 11, 'column' => 17, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 13, 'column' => 13, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 14, 'column' => 21, 'source' => DECLARED_PARAMETERS_ERROR],
        // `\func_get_args()` qualifies the global namespace — still PHP's own
        // function.
        ['line' => 22, 'column' => 17, 'source' => DECLARED_PARAMETERS_ERROR],
        // PHP function names are case-insensitive.
        ['line' => 27, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
        // A `&` before the name excuses a return-by-reference declaration
        // (asserted silent on passing.php) and nothing else: in an expression
        // the call is still a call.
        ['line' => 34, 'column' => 24, 'source' => DECLARED_PARAMETERS_ERROR],
        // A call at file scope belongs to no declaration, so no magic-method
        // exemption can apply to it.
        ['line' => 40, 'column' => 9, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

/**
 * An unqualified call resolves through the file's `use function` imports before
 * PHP's own function, so a qualified import — plain, grouped, or aliased —
 * names a different symbol. An unqualified import still names PHP's function,
 * and a leading separator bypasses imports entirely.
 */
it('resolves use function imports before the global function', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'imports.php');

    expect(violationTuples($file))->toBe([
        // `use function func_get_arg;` imports PHP's own function, and the
        // same-named class alias does not change that.
        ['line' => 35, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
        // `\func_get_args()` ignores the group import above it.
        ['line' => 42, 'column' => 17, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

/**
 * An alias binds its *local* name: renaming another global function onto one of
 * these names is a different symbol and is exempt, while a self-alias renames
 * nothing and still names PHP's own function.
 */
it('binds an aliased import to the local name, not the builtin', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'imports-aliased.php');

    expect(violationTuples($file))->toBe([
        // The self-alias still names PHP's own function.
        ['line' => 32, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

/**
 * A mixed group import prefixes the individual entry rather than the whole
 * statement, so each entry's own kind decides what it binds: the
 * `function`-prefixed entry names a different symbol, while the unprefixed
 * entry beside it is a class import and leaves function resolution alone. PHP 8
 * also allows `function` as a name *segment*, which prefixes nothing.
 */
it('classifies each entry of a mixed group import on its own', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'imports-mixed-group.php');

    expect(violationTuples($file))->toBe([
        // The unprefixed entry imported a *class* of that name, so the call
        // still resolves to PHP's own function.
        ['line' => 31, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
        // `function\func_get_arg` is a class import from a namespace whose
        // segment is spelled `function` — not a function import.
        ['line' => 36, 'column' => 16, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

/**
 * `namespace\` resolves against the *current* namespace with no fallback to the
 * global one — so it is exempt inside a named namespace (asserted on
 * passing.php) but is PHP's own function in a file that declares no namespace.
 *
 * Reaching PHP's function is not the same as calling it: the relative
 * instantiation at line 28 stays silent beside the two flagged calls above it,
 * which differ from it only by the preceding `new`.
 */
it('flags a relative call when the file declares no namespace', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'global-namespace.php');

    expect(violationTuples($file))->toBe([
        ['line' => 14, 'column' => 26, 'source' => DECLARED_PARAMETERS_ERROR],
        ['line' => 19, 'column' => 26, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

/**
 * With braced namespace blocks, `namespace\` resolves against the block the
 * call sits *in* — not whichever declaration precedes it in the file. A call
 * inside `namespace { … }` is in the global namespace and is flagged however
 * many named blocks surround it.
 */
it('resolves the relative qualifier against the enclosing braced block', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'braced-namespaces.php');

    expect(violationTuples($file))->toBe([
        // `namespace\` inside the unnamed global block — PHP's own function,
        // despite the named block declared above it.
        ['line' => 28, 'column' => 30, 'source' => DECLARED_PARAMETERS_ERROR],
        // An unqualified call in the same block, for contrast.
        ['line' => 33, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

/**
 * The magic-method exemption belongs to the magic method's own body: a closure
 * or arrow function inside one declares its own parameter list, and a plain
 * function named like a magic method is not one.
 */
it('applies the exemption to the magic method body only', function (): void {
    $file = analyzeFixture(DECLARED_PARAMETERS, 'scopes.php');

    expect(violationTuples($file))->toBe([
        // Closure in an ordinary method.
        ['line' => 12, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
        // Closure inside __call().
        ['line' => 21, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
        // Arrow function inside __invoke().
        ['line' => 28, 'column' => 32, 'source' => DECLARED_PARAMETERS_ERROR],
        // Plain function named __get().
        ['line' => 44, 'column' => 12, 'source' => DECLARED_PARAMETERS_ERROR],
        // Function named __set() declared inside a method: magic methods
        // belong to an OO container, not a function body.
        ['line' => 56, 'column' => 20, 'source' => DECLARED_PARAMETERS_ERROR],
    ]);
});

/**
 * The message names the call as written, so a case variant reports itself
 * rather than a normalised name.
 */
it('names the offending call as written', function (): void {
    $errors = analyzeFixture(DECLARED_PARAMETERS, 'failing.php')->getErrors();

    expect($errors[13][13][0]['message'])->toBe(
        'func_num_args() is not allowed; declare the parameter list instead of'
            . ' reading arguments dynamically'
    );
    expect($errors[27][16][0]['message'])->toBe(
        'FUNC_GET_ARGS() is not allowed; declare the parameter list instead of'
            . ' reading arguments dynamically'
    );
});

/**
 * The justification for shipping no autofixed fixture. Asserted across every
 * flagging fixture rather than one, so a fixable violation added to any of them
 * has to come with the fixer the contract test would then demand.
 */
it('reports every violation as non-fixable', function (string $fixture): void {
    $flags = violationFixableFlags(analyzeFixture(DECLARED_PARAMETERS, $fixture));

    // Guards the assertion below against passing vacuously: an empty flag list
    // satisfies "none is true" just as well as a genuinely detection-only one.
    expect($flags)->not->toBeEmpty();
    expect($flags)->each->toBeFalse();
})->with([
    'failing.php',
    'scopes.php',
    'imports.php',
    'imports-aliased.php',
    'imports-mixed-group.php',
    'global-namespace.php',
    'braced-namespaces.php',
]);
