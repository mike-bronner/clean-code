<?php

/**
 * Tests MikeBronner\CleanCode\Helpers\FunctionCalls directly.
 *
 * The helper is the one answer to "is this T_STRING a call to PHP's own global
 * function?", shared by every sniff that flags one. Testing it through a sniff
 * would only ever see the shapes that sniff's own name list lets through, so
 * these tests read its verdict for every shape at once.
 *
 * Each case in the fixtures carries a `probe`-prefixed name that says which
 * shape it is, and the assertions are keyed by that name rather than by line
 * number: a name survives editing the fixture, and it makes a wrong verdict
 * read as the case that produced it. The value is the list of verdicts for
 * every occurrence of that name in source order — an imported name appears in
 * its own `use` statement as well as at the call site, and both verdicts are
 * pinned so neither can drift unnoticed.
 */

declare(strict_types=1);

it('rejects every shape that is not a call to a global function', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'passing.php'), 'probe');

    expect($verdicts)->toBe([
        // The import statements themselves — a name being imported is not a
        // call, whichever of the four import spellings introduces it.
        'probeImported' => [false, false],
        'probeSource' => [false],
        'probeAliased' => [false, false],
        'probeListedFirst' => [false, false],
        'probeListedLast' => [false, false],
        'probeListedAliased' => [false],
        'probeListedAlias' => [false, false],
        'probeListedTrailing' => [false, false],
        'probeGrouped' => [false, false],
        'probeRenamed' => [false],
        'probeGroupAlias' => [false, false],
        'probeMixed' => [false, false],
        // Member access.
        'probeMethod' => [false],
        'probeNullsafeMethod' => [false],
        'probeStatic' => [false],
        // Declarations, return-by-reference included.
        'probeDeclared' => [false],
        'probeByReference' => [false],
        'probeMethodDeclaration' => [false],
        'probeByReferenceMethod' => [false],
        // Instantiation, bare and behind either leading qualifier.
        'probeInstance' => [false],
        'probeGlobalInstance' => [false],
        'probeRelativeInstance' => [false],
        'probeNamespacedInstance' => [false],
        // Qualified names resolve outside the global namespace.
        'probeQualified' => [false],
        'probeRelative' => [false],
        // An attribute names a class.
        'probeAttribute' => [false],
        // Not a call at all.
        'probeNotCalled' => [false],
    ]);
});

/**
 * The other half of the contract, and the half an over-eager exclusion breaks:
 * every call here does reach PHP's own function, several of them sitting right
 * beside an import that has no say in resolving it.
 */
it('accepts a call that reaches PHP own global function', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'failing.php'), 'probe');

    expect($verdicts)->toBe([
        // A class import binds a class, and takes no part in resolving a call.
        'probeClassImport' => [false, true],
        // Nor does a constant import.
        'probeConstantImport' => [false, true],
        // An aliased import binds the alias, leaving the source name global.
        'probeAliasedAway' => [false, true],
        'probeAliasTarget' => [false],
        // An import belongs to its own namespace block and no other.
        'probeOtherBlock' => [false, true],
        // `function` as a name *segment* prefixes nothing: that entry imports a
        // class, and a class import binds no function name.
        'probeSegmentNamed' => [false, true],
        // A bare name nothing redirects.
        'probeBare' => [true],
        // A leading separator qualifies the global namespace.
        'probeFullyQualified' => [true],
        // An `&` before the name is a declaration's marker only between
        // `function` and the name; this one is the bitwise operator.
        'probeBitwiseOperator' => [true],
        // A closure captures variables with `use`; it imports nothing, not even
        // when a nested closure puts `function` straight after one of the
        // statement's commas.
        'probeInsideClosure' => [true],
        'probeInsideCapture' => [true, true],
        // A class body's `use` pulls in a trait, not a function.
        'probeInsideMethod' => [true],
    ]);
});

/**
 * Two verdicts depend on the namespace in force rather than on the tokens
 * around the name, and both invert in the global namespace: `namespace\foo()`
 * resolves to PHP's own function there, and an import that binds a global
 * function under its own name redirects nothing. The other fixtures all sit
 * inside `namespace App;`, so neither shape can be reached from them.
 */
it('resolves the shapes that depend on the namespace in force', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'global-namespace.php'), 'probe');

    expect($verdicts)->toBe([
        // The import statements: a name being imported is never a call.
        'probeSelfImport' => [false, true],
        // The redundant `as` spelling names the same symbol twice.
        'probeSelfSame' => [false, false, true],
        'probeSourceRenamed' => [false],
        // An unqualified source under a different alias binds that source, so
        // the call reaches it rather than PHP's function of the name written.
        'probeSelfAlias' => [false, false],
        // `namespace\` against the global namespace is PHP's own function.
        'probeRelativeGlobal' => [true],
        // …but an instantiation stays an instantiation behind that qualifier.
        'probeRelativeInstance' => [false],
    ]);
});

/**
 * The braced `namespace A { … }` form takes its own path through the block
 * lookup: a braced declaration carries a scope closer, so its block ends at
 * that brace rather than running on to the next declaration. The unbraced
 * fixtures cannot reach that path — PHP forbids mixing the two forms in one
 * file — and getting it wrong leaks an import's suppression across a namespace
 * boundary, which is the one thing block scoping exists to stop.
 */
it('scopes an import to its own braced namespace block', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'braced-namespaces.php'), 'probe');

    expect($verdicts)->toBe([
        // The import statement, the call it redirects in the same block, then
        // the same call in a sibling block and in the global block — neither of
        // which the import reaches, so both are calls to PHP's own function.
        'probeBracedImport' => [false, false, true, true],
    ]);
});

/**
 * A trait use and an import share the T_USE token, and only the import binds a
 * name. The trait use has to be ruled out before the statement is measured,
 * because measuring is what is unsafe: an import ends at a semicolon, a trait
 * use with an empty adaptation block has none, and a scan for one runs on into
 * the next statement that does — a real import, in another block, whose
 * entries then bind against the block holding the trait use. That silences a
 * call in a block that imported nothing, which is the failure this pins.
 */
it('keeps a trait use out of the import scan', function (): void {
    $verdicts = globalFunctionCallVerdicts(parseFixture('FunctionCalls', 'trait-adaptation.php'), 'probe');

    expect($verdicts)->toBe([
        // The call in the block holding the trait use, then the import entry
        // in the other block, then the call that import really does redirect.
        'probeAdaptationLeak' => [true, false, false],
    ]);
});
