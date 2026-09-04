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

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use PHP_CodeSniffer\Util\Tokens;

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
        // The same shape carrying a qualified name, which is the case that
        // fails the moment the leading-`function` gate stops turning a capture
        // list away: its second entry then binds this name as an import, and
        // the bare call below reads as redirected rather than global.
        'probeCaptureLeak' => [false, true],
        // A class body's `use` pulls in a trait, not a function.
        'probeInsideMethod' => [true],
    ]);
});

/**
 * The closure capture list is the one shape whose safety does not come from the
 * code that measures it. `use ($value)` sits at namespace level exactly as an
 * import does, so the import scan measures it the same way — by the next
 * semicolon — and for a closure that semicolon is one inside its own body,
 * never the enclosing statement's terminator. No name is read out of that wrong
 * span only because the statement is turned away first, on two grammar
 * invariants: a capture list opens with `(` rather than the `function` keyword
 * an import leads with, and a closure body's `{` follows `)`, which PHPCS
 * tokenises as T_OPEN_CURLY_BRACKET — only a brace directly after `\` becomes
 * the T_OPEN_USE_GROUP that opens the group-import path.
 *
 * The verdict tests above cannot see any of this: they read `true` whether the
 * gates hold or the span is right. These assertions pin the two premises
 * directly, so weakening either gate — or measuring the span as if it were
 * trustworthy — fails here, beside the comment that states the reason, instead
 * of surviving on an outcome that happens to stay the same.
 */
it('rests a closure capture list on syntax rather than on its measured span', function (): void {
    $file = parseFixture('FunctionCalls', 'failing.php');
    $tokens = $file->getTokens();
    $captures = [];

    // A capture list is the `use` between a closure's parameter list and its
    // body, which is how it is found here — reading the token after it would
    // assume the very premise under test.
    foreach ($tokens as $pointer => $token) {
        if ($token['code'] !== T_CLOSURE) {
            continue;
        }

        $usePtr = $file->findNext(T_USE, ($pointer + 1), $token['scope_opener']);

        if ($usePtr !== false) {
            $captures[$usePtr] = $token['scope_closer'];
        }
    }

    // The fixture carries three capture lists. Pinning the count keeps this
    // test from passing on an empty loop if a closure is edited away.
    expect($captures)->toHaveCount(3);

    foreach ($captures as $usePtr => $scopeCloser) {
        $end = $file->findNext(T_SEMICOLON, ($usePtr + 1));
        $first = $file->findNext(Tokens::$emptyTokens, ($usePtr + 1), null, true);

        // Gate one: the statement cannot lead with the `function` keyword, so
        // the no-group import path can never claim it.
        expect($tokens[$first]['code'])->toBe(T_OPEN_PARENTHESIS);

        // Gate two: nothing in the span the scan would read opens a use group,
        // so the group import path can never claim it either.
        expect($file->findNext(T_OPEN_USE_GROUP, ($usePtr + 1), $end))->toBeFalse();

        // …and the span itself is the untrustworthy part: it stops inside the
        // closure body rather than at the statement's own terminator, which is
        // why both gates above have to carry the safety on their own.
        expect($end)->toBeLessThan($scopeCloser);
    }
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

const FUNCTION_CALLS_PROBE_SNIFF = 'CleanCode.Constructors.DisallowCombinedConstructor';

/**
 * The import scan reads its stream once, not once per name it is asked about.
 *
 * Both halves of the analysis walk the whole token stream, and every bare name
 * that reaches this far pays for them. The name reaching this far is one a
 * sniff already narrowed to its own list, so "rare" is the caller's property
 * rather than the helper's: DisallowCombinedConstructor asks about every type
 * predicate in a constructor, and a constructor may hold thousands. Uncached,
 * n names in one file cost n stream walks — measured at 0.45s/0.89s/2.65s for
 * n=500/1000/2000 through live phpcs, against 0.11s/0.12s/0.16s held.
 *
 * A cache that only shortens a scan changes no verdict, so no fixture reddens
 * on it and the shapes pinned above pass either way. The helper counts its own
 * analysis instead, and both numbers are pinned because each rules out a
 * different failure:
 *
 * - one build per stream, at any size, is the claim itself;
 * - n-1 hits keeps it from passing vacuously, since a helper that stopped
 *   consulting the held analysis would report one build and no hits.
 *
 * Mutation-checked by deleting the `$this->analysisKey === $key` guard, so
 * every name rebuilds: `composer test` then fails here at the smallest size,
 * n=2, reading 2 builds / 0 hits against the 1 / 1 asserted; n=4 reads 4 / 0
 * against 1 / 3, and n=8 reads 8 / 0 against 1 / 7.
 */
it('reads its stream once per analysis, not once per name', function (): void {
    foreach ([2, 4, 8] as $size) {
        $source = "<?php\n\nclass PredicateProbe\n{\n    public function __construct(mixed \$value)\n    {\n"
            . '        $this->mode = ' . implode(' || ', array_fill(0, $size, 'is_string($value)'))
            . " ? 1 : 2;\n    }\n}\n";

        // buildRuleset() memoises the ruleset per sniff-code key, so this is the
        // same sniff instance analyzeStdinSource() drives below, and its
        // FunctionCalls is the one that answers the run.
        $sniff = sniffInstance(FUNCTION_CALLS_PROBE_SNIFF);
        $before = $sniff->analysisCounts();
        $file = analyzeStdinSource([FUNCTION_CALLS_PROBE_SNIFF], $source);
        $counted = cacheCountsDelta($before, $sniff->analysisCounts());

        expect($file->getWarningCount())->toBe($size, "n={$size}: every predicate is still reported")
            ->and($counted['builds'])->toBe(
                1,
                "n={$size}: the stream is read once, not once per name"
            )
            ->and($counted['hits'])->toBe(
                $size - 1,
                "n={$size}: every name after the first answers from the analysis already built"
            );
    }
});

/**
 * Two streams are told apart, so the analysis of one never answers the other.
 *
 * The counter above cannot show this: one build is what a helper that cached
 * forever, across every file in the run, would also report. Two sources with
 * different imports drive it here, and the second's verdict is what proves the
 * held analysis was discarded rather than reused — the same collision
 * TokenStreams::key() was written for in #343.
 */
it('tells two streams apart', function (): void {
    $importing = "<?php\n\nnamespace App;\n\nuse function App\\Vendor\\is_string;\n\n"
        . "class Importing\n{\n    public function __construct(mixed \$value)\n    {\n"
        . "        \$this->mode = is_string(\$value) ? 1 : 2;\n    }\n}\n";
    $bare = "<?php\n\nnamespace App;\n\nclass Bare\n{\n    public function __construct(mixed \$value)\n    {\n"
        . "        \$this->mode = is_string(\$value) ? 1 : 2;\n    }\n}\n";

    $first = analyzeStdinSource([FUNCTION_CALLS_PROBE_SNIFF], $importing);
    $second = analyzeStdinSource([FUNCTION_CALLS_PROBE_SNIFF], $bare);

    expect($first->getWarningCount())->toBe(0, 'the redirected name is not PHP own predicate')
        ->and($second->getWarningCount())->toBe(1, 'the bare name in the next stream still is');
});
