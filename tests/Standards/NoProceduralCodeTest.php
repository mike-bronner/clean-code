<?php

/**
 * Tests the custom CleanCode.Files.NoProceduralCode sniff (Testing:
 * Development Process (TDD), #57, classes-only slice #129). Fixtures live in
 * tests/fixtures/NoProceduralCodeSniff/: the single declaration and its
 * allowed preamble in passing.php, every flagged top-level construct in
 * failing.php, and one fixture per edge case the standard has to settle. The
 * rule is detection-only, so there is no autofixed fixture.
 *
 * CleanCode/ruleset.xml scopes the sniff to source directories with <include-pattern>, and
 * PHPCS decides that from the file's path alone — so these fixtures report
 * nothing where they live, under tests/. Every assertion about the sniff's own
 * behaviour therefore runs against a copy staged into a `src/` directory
 * outside the repository ($sourceRun below), and the scoping itself is pinned
 * separately by the scoped-to-source-directories test, which drives the same
 * bytes from four different paths so the silence has to come from the path
 * rather than from the sniff having nothing to say.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in CleanCode/ruleset.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const PROCEDURAL = 'CleanCode.Files.NoProceduralCode';

const PROCEDURAL_STATEMENT = PROCEDURAL . '.ProceduralStatement';

const PROCEDURAL_DECLARATIONS = PROCEDURAL . '.MultipleDeclarations';

// Fixtures are copied into a src/ directory outside the repository before
// processing, because CleanCode/ruleset.xml restricts the sniff to source paths. The
// staged copies are removed by the afterEach() hook in tests/Pest.php.
$sourceRun = static fn (string $fixture, string $subdirectory = 'src') => analyzeWithSniffs(
    [PROCEDURAL],
    stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', $fixture), $subdirectory)
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(PROCEDURAL);
});

/**
 * The compliant shape and every near-miss stay silent. The fixture is built so
 * that a walk which descended into the declaration would report at once: the
 * class body holds a constant, a property assignment, method declarations, a
 * foreach, an if, a continue, returns, an arrow function, and an anonymous
 * class — each of them a construct the sniff flags at the top level.
 *
 * The preamble covers every form the allow-list names: declare, namespace, a
 * plain use, `use function`, `use const`, a docblock, an attribute, and the
 * `final` modifier ahead of the declaration.
 */
it('produces no violations on the compliant fixture', function () use ($sourceRun): void {
    $file = $sourceRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every top-level construct is flagged once, at its own line:
 *
 * - line 9, `const LIMIT = 10;` — a constant is a symbol, but not one of the
 *   four class-like declarations the standard allows.
 * - line 11, `require` — an include is executed, not declared.
 * - line 13, `$user = User::first();` — an assignment.
 * - line 15, the `if`/`else` — reported once, at the `if`. The `else` on line
 *   17 continues the same statement; without the continuation handling it
 *   would earn a second report.
 * - lines 21, 25, 33, the `foreach`, `while` and `switch`.
 * - line 29, the `do` block — its trailing `while ($tries < LIMIT);` on line
 *   31 is part of the same statement, and is told apart from a `while` loop
 *   by carrying no scope of its own.
 * - line 38, the `try` — the `catch` on line 40 and the `finally` on line 42
 *   continue it, and neither is reported separately.
 * - line 46, a standalone `function` declaration.
 * - lines 51, 53 and 55, a bare call, an `echo`, and a top-level `return`.
 * - line 58, the markup after the closing tag on line 57. The closing tag
 *   itself is left to PSR12.Files.ClosingTag, which owns it.
 */
it('flags every top-level construct at its own line', function () use ($sourceRun): void {
    $file = $sourceRun('failing.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 9, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 11, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 13, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 15, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 21, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 25, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 29, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 33, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 38, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 46, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 51, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 53, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 55, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 58, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
        ]);
});

/**
 * A compound statement is reported once however its continuation clauses are
 * written, and — the half that actually bites — whatever follows it is still
 * reached. A clause the walk mis-measures does not report anything extra; it
 * swallows the next top-level statement silently, which is a false negative
 * that ships procedural code undetected.
 *
 * failing.php covers the braced `else` and `catch`/`finally`. One fixture per
 * remaining shape, because each takes a different path through the walk:
 *
 * - continuation-spaced-else-if.php — `else if (…) { … }`, spaced. PHPCS puts
 *   the scope on the trailing `if`, never on the `else`, so measuring the
 *   `else` with the generic statement scan runs past the whole chain. Chained
 *   three deep, so the fix has to hold for each link and not just the first.
 * - continuation-elseif.php — merged `elseif`, one token with its own scope.
 *   Its own entry in the sniff's continuation list, consumed independently of
 *   `else`: deleting that entry leaves this the only red case.
 * - continuation-alternative-syntax.php — `if … elseif … else … endif;` and
 *   `foreach … endforeach;`. Here the continuation *is* the previous clause's
 *   end rather than the token after it, and a walk that only looks past the
 *   end reports the `:` as a statement of its own.
 * - continuation-braceless.php — `else` and a spaced `else if` with no braces
 *   at all, which own no scope and end at their semicolon.
 *
 * Each fixture's trailing assignments are the discriminating part: the first
 * tuple alone would hold against a walk that swallowed everything after the
 * chain.
 */
it('flags top-level code after every continuation-clause shape', function (
    string $fixture,
    array $lines
) use ($sourceRun): void {
    expect(violationTuples($sourceRun($fixture)))->toBe(array_map(
        static fn (int $line): array => ['line' => $line, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
        $lines
    ));
})->with([
    'spaced else if' => ['continuation-spaced-else-if.php', [7, 17, 19]],
    'merged elseif' => ['continuation-elseif.php', [7, 15]],
    'alternative syntax' => ['continuation-alternative-syntax.php', [7, 15, 19]],
    'brace-less clauses' => ['continuation-braceless.php', [7, 14]],
]);

/**
 * A file cut off mid-continuation terminates the walk instead of spinning on
 * it. PHP_CodeSniffer tokenizes files mid-edit, and a continuation keyword
 * with nothing after it gets neither a scope nor a statement end — so the
 * clause "ends" exactly where it starts, and a walk that took that as its new
 * position would measure the same token forever. The sniff returns instead.
 *
 * One fixture per continuation keyword, and the two syntax families split
 * across them, because each reaches the non-progress case by a different
 * route: `else` from a scope closer that lands on it, `elseif` from the
 * alternative syntax's own closer, `catch` and `finally` from a `try` whose
 * scope ends before them. Removing the guard spins on all four and on nothing
 * else — every other fixture in this directory reports identically without it
 * — so these four are the guard's only witnesses.
 *
 * Each fixture still reports the assignment above the truncation and the
 * construct the truncation belongs to, so a sniff that gave up on the whole
 * file would fail here rather than pass.
 */
it('terminates on a truncated continuation clause', function (
    string $fixture,
    array $tuples
) use ($sourceRun): void {
    expect(violationTuples($sourceRun($fixture)))->toBe(array_map(
        static fn (array $tuple): array => [
            'line' => $tuple[0],
            'column' => $tuple[1],
            'source' => PROCEDURAL_STATEMENT,
        ],
        $tuples
    ));
})->with([
    'braced else' => ['truncated-else.php', [[3, 1], [5, 1]]],
    'alternative-syntax elseif' => ['truncated-elseif.php', [[3, 1], [5, 1], [6, 5]]],
    'catch' => ['truncated-catch.php', [[3, 1], [5, 1]]],
    'finally' => ['truncated-finally.php', [[3, 1], [5, 1]]],
]);

/**
 * A file opened with `<?=` is a top-level echo, so it is procedural and is
 * reported at the tag. T_OPEN_TAG_WITH_ECHO is a separate entry in the sniff's
 * registration and a separate absence from its ignore list; nothing else in
 * the fixtures opens with a short echo tag, so without this case either could
 * change and the sniff would fall silent here unnoticed.
 */
it('flags a file opened with a short echo tag', function () use ($sourceRun): void {
    expect(violationTuples($sourceRun('short-echo-tag.php')))->toBe([
        ['line' => 1, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
    ]);
});

/**
 * The other half of the "nothing shipped already covers this" check the issue
 * asked for, and the half that can rot: composer.json floats Slevomat on
 * ^8.15, so a later minor could start covering the pure-procedural file and
 * make this sniff redundant with nothing to say so.
 *
 * The whole Slevomat standard is built, not the master ruleset: CleanCode/ruleset.xml
 * references Slevomat sniffs one at a time, so a sniff added in a future minor
 * would not be wired in and a master-ruleset run could never see it. The
 * master ruleset is asserted too, because that is what a consumer actually
 * runs — together they say no Slevomat sniff covers this file today, whether
 * or not CleanCode/ruleset.xml has opted into it.
 *
 * The fixture is deliberately clean under Slevomat's own style rules
 * (`strict_types = 1` spaced its way, a Yoda comparison, no imports), so the
 * assertion can be an empty set rather than a list of unrelated style findings
 * that would have to be curated on every upgrade.
 *
 * Three halves, asserted together. Empty Slevomat output alone would hold just
 * as well against a standard that failed to load and ran nothing, so the
 * control run pins that the same built standard does report on a fixture that
 * earns it; and the NoProceduralCode run pins that the file really is
 * procedural rather than trivially unremarkable.
 */
it('reports a purely procedural file the whole Slevomat standard passes', function () use ($sourceRun): void {
    $slevomatSources = static function (LocalFile $file): array {
        $sources = [];

        foreach (allViolationSourcesByLine($file) as $line => $lineSources) {
            foreach ($lineSources as $source) {
                if (str_starts_with($source, 'SlevomatCodingStandard.') === true) {
                    $sources[$line][] = $source;
                }
            }
        }

        return $sources;
    };

    $staged = stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', 'slevomat-clean.php'), 'src');
    $control = stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', 'no-declaration.php'), 'src');

    expect($slevomatSources(analyzeWithStandard('SlevomatCodingStandard', $staged)))->toBe([])
        ->and($slevomatSources(analyzeWithMasterRuleset($staged)))->toBe([])
        ->and($slevomatSources(analyzeWithStandard('SlevomatCodingStandard', $control)))->toBe([
            3 => ['SlevomatCodingStandard.TypeHints.DeclareStrictTypes.IncorrectStrictTypesFormat'],
            7 => ['SlevomatCodingStandard.Namespaces.UseOnlyWhitelistedNamespaces.NonFullyQualified'],
            11 => ['SlevomatCodingStandard.ControlStructures.RequireYodaComparison.RequiredYodaComparison'],
        ])
        ->and(violationTuples($sourceRun('slevomat-clean.php')))->toBe([
            ['line' => 7, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 9, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
        ]);
});

/**
 * The message names the construct, so a report says which statement to move.
 * A keyword is quoted from the source, a call keeps its parentheses so
 * `present()` does not read as a bare name, and markup — whose own content is
 * the entire HTML block — is named rather than quoted.
 *
 * A name *not* followed by parentheses takes the same verbatim-quoting path
 * every keyword takes, so it is not a separate behaviour and has no case of
 * its own here.
 */
it('names the offending construct in the message', function () use ($sourceRun): void {
    $errors = $sourceRun('failing.php')->getErrors();

    expect($errors[15][1][0]['message'])->toContain('"if"')
        ->and($errors[13][1][0]['message'])->toContain('"$user"')
        ->and($errors[51][1][0]['message'])->toContain('"present()"')
        ->and($errors[53][1][0]['message'])->toContain('"echo"')
        ->and($errors[58][1][0]['message'])->toContain('markup');
});

/**
 * The modifiers a declaration may carry, and the empty statement a stray
 * semicolon after it forms, are not statements of their own. Each is a
 * separate entry in the sniff's ignore list, and removing any one of them
 * turns this fixture red on its own line: `abstract`, `readonly`, and the
 * `;` closing line 9.
 */
it('passes over declaration modifiers and a trailing empty statement', function () use ($sourceRun): void {
    $file = $sourceRun('modifiers.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Blank lines after a closing tag tokenize as inline HTML exactly like real
 * markup does, so whitespace-only content is passed over — otherwise a file
 * ending in `?>` and a blank line would be reported for markup a reader cannot
 * see. The closing tag itself belongs to PSR12.Files.ClosingTag.
 *
 * The fixture ends in `?>` followed by *two* newlines deliberately: PHP eats
 * the one newline directly after a closing tag, so a file ending `?>\n` emits
 * no inline-HTML token at all and would pass this test whatever the sniff did.
 *
 * Complements the markup case in failing.php: that fixture pins that markup
 * *with* content after a closing tag is reported, this one pins that markup
 * without it is not.
 */
it('passes over the whitespace a closing tag leaves behind', function () use ($sourceRun): void {
    $file = $sourceRun('closing-tag.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * PHP_CodeSniffer tokenizes files mid-edit, so the walk has to survive a file
 * that stops in the middle of a construct. Both truncations end the walk at
 * the file rather than reading what follows the keyword as top-level code:
 *
 * - unterminated.php — `class Unterminated` with no body. Without the guard
 *   the class's own *name* is reported as a procedural statement, which is a
 *   diagnostic pointing at the one construct the standard asks for.
 * - unterminated-namespace.php — `namespace App\Support` with no semicolon.
 *   Without the guard the namespace's name is reported the same way.
 *
 * Each fixture still reports the assignment above the truncation, so a sniff
 * that fell silent on the whole file would fail here rather than pass.
 */
it('stops at a truncated construct without misreading it', function (
    string $fixture,
    int $line
) use ($sourceRun): void {
    expect(violationTuples($sourceRun($fixture)))->toBe([
        ['line' => $line, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
    ]);
})->with([
    ['unterminated.php', 5],
    ['unterminated-namespace.php', 3],
]);

/**
 * The point of the sniff: it is stricter than PSR1.Files.SideEffects, which
 * PSR12 already brings into CleanCode/ruleset.xml. That sniff forbids only *mixing* a
 * declaration with side effects, so a file that is nothing but procedural code
 * declares no symbol and passes it silently.
 *
 * Both halves are asserted from the same fixture. The PSR-1 half alone would
 * hold just as well against a file PSR-1 never looks at, and the NoProcedural
 * half alone would not show the gap this sniff exists to close.
 */
it('reports a purely procedural file that PSR-1 passes', function () use ($sourceRun): void {
    $staged = stageFixtureOutsideTests(
        fixturePath('NoProceduralCodeSniff', 'no-declaration.php'),
        'src'
    );

    $sideEffects = analyzeWithSniffs(['PSR1.Files.SideEffects'], $staged);

    expect($sideEffects->getErrors())->toBe([])
        ->and($sideEffects->getWarnings())->toBe([])
        ->and(violationTuples($sourceRun('no-declaration.php')))->toBe([
            ['line' => 9, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
            ['line' => 11, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
        ]);
});

/**
 * Exactly one class-like declaration is allowed, so each one after the first
 * is reported — at the declaration keyword, which is why line 11 is column 7
 * rather than column 1: `final` precedes it. The interface on line 7 is the
 * one kept, and none of the four is reported as a procedural statement.
 */
it('flags every declaration after the first', function () use ($sourceRun): void {
    $file = $sourceRun('multiple-declarations.php');

    expect($file->getWarnings())->toBe([])
        ->and(violationTuples($file))->toBe([
            ['line' => 11, 'column' => 7, 'source' => PROCEDURAL_DECLARATIONS],
            ['line' => 15, 'column' => 1, 'source' => PROCEDURAL_DECLARATIONS],
            ['line' => 19, 'column' => 1, 'source' => PROCEDURAL_DECLARATIONS],
        ]);
});

/**
 * The message names the extra declaration by keyword and name, so a report
 * says which one to move to its own file.
 */
it('names the additional declaration in the message', function () use ($sourceRun): void {
    $errors = $sourceRun('multiple-declarations.php')->getErrors();

    expect($errors[11][7][0]['message'])->toContain('class UserPresenter')
        ->and($errors[19][1][0]['message'])->toContain('enum Tone');
});

/**
 * A braced namespace is descended into rather than skipped: its body is the
 * file's top level, so the assignment on line 6 is procedural code and the
 * class on line 8 is the file's one declaration. Skipping the whole block
 * would leave this fixture silent, which is the shape that would let any
 * procedural file pass simply by wrapping itself in braces.
 */
it('reads the body of a braced namespace as the top level', function () use ($sourceRun): void {
    expect(violationTuples($sourceRun('braced-namespace.php')))->toBe([
        ['line' => 6, 'column' => 5, 'source' => PROCEDURAL_STATEMENT],
    ]);
});

/**
 * A .php file that is nothing but markup has no open tag at all, so the sniff
 * reaches it only through its inline-HTML registration. Dropping T_INLINE_HTML
 * from register() leaves this fixture silent while every other fixture here
 * still reports, which is what makes it worth its own case.
 */
it('flags a file that is nothing but markup', function () use ($sourceRun): void {
    expect(violationTuples($sourceRun('markup-only.php')))->toBe([
        ['line' => 1, 'column' => 1, 'source' => PROCEDURAL_STATEMENT],
    ]);
});

/**
 * A file that declares nothing and executes nothing holds no procedural code
 * to point at, so it is left alone. This is a deliberate boundary rather than
 * an oversight: reporting "this file has no class" would be an absence check,
 * and the absence of a class is what the companion issue #128 (a class with no
 * test file) is about, not this one.
 *
 * The empty fixture is 0 bytes, so it also pins that a file with no tokens at
 * all does not fall over — the sniff never fires there, since none of its
 * registered tokens exists.
 *
 * The warning half is filtered to this sniff rather than asserted empty,
 * because PHP_CodeSniffer raises `Internal.NoCodeFound` on a file holding no
 * PHP whenever the runtime has `short_open_tag` off. That is a property of the
 * PHP the suite runs on — it appears on CI and not on a developer machine with
 * the setting on — and says nothing about the sniff. The errors half stays
 * absolute: the sniff reports errors only, so an unfiltered empty-errors
 * assertion is still the strict one.
 */
it('leaves a file with no code alone', function (string $fixture) use ($sourceRun): void {
    $file = $sourceRun($fixture);
    $warnings = [];

    foreach (violationSourcesByLine($file->getWarnings()) as $sources) {
        $warnings = array_merge($warnings, $sources);
    }

    $ours = array_filter($warnings, static fn (string $source): bool => str_starts_with($source, PROCEDURAL));

    expect($file->getErrors())->toBe([])
        ->and(array_values($ours))->toBe([]);
})->with(['empty.php', 'comments-only.php']);

/**
 * CleanCode/ruleset.xml restricts the sniff to source directories, because entry points,
 * config files, route files and pre-Laravel-9-style migrations are
 * legitimately procedural and all of them live outside src/ and app/. The
 * scoping is a path match, so the same bytes are driven from four paths: both
 * allowed directories have to report, and both disallowed ones have to stay
 * silent.
 *
 * All four are asserted together. The silent halves alone would pass just as
 * well against a sniff that never fires at all, and the reporting halves alone
 * would pass against an include-pattern that had been dropped entirely.
 */
it('is scoped to source directories', function () use ($sourceRun): void {
    expect($sourceRun('failing.php', 'src')->getErrorCount())->toBe(14)
        ->and($sourceRun('failing.php', 'app')->getErrorCount())->toBe(14)
        ->and($sourceRun('failing.php', 'config')->getErrorCount())->toBe(0)
        ->and($sourceRun('failing.php', '')->getErrorCount())->toBe(0);
});

/**
 * Pins the detection-only decision: wrapping loose statements in a class
 * decides which class, which method, and which visibility, so no violation is
 * auto-fixable.
 */
it('reports detection-only errors', function () use ($sourceRun): void {
    $file = $sourceRun('failing.php');

    expect($file->getErrorCount())->toBe(14)
        ->and($file->getWarningCount())->toBe(0)
        ->and(violationFixableFlags($file))->toBe(array_fill(0, 14, false));
});

/**
 * The same verdict through the shipped, installed package.
 *
 * Every test above drives PHPCS in process through ConfigDouble, which supplies
 * the registration Composer would have supplied — so a package that never
 * registered itself with the installed standards passes all of them. This one
 * executes the real vendor/bin/phpcs as a separate process from outside the
 * package, against CleanCode/ruleset.xml, the file a consumer points --standard at. The
 * shared sweep in tests/Contract/ShippedPackageSmokeTest.php cannot reach this
 * sniff: it drives each fixture where it lives, under tests/, and this sniff's
 * <include-pattern> makes that path report nothing whatever the sniff does.
 *
 * Staged into src/ exactly as $sourceRun stages it, and asserted in the same
 * paired shape as the scoping test above rather than only on the positive half:
 *
 * - failing.php inside src/ reports all 14, every message under this sniff's
 *   own code, at status 1 — violations, none of them fixable, which is what
 *   this detection-only rule owes. Status 2 would mean phpcbf had been offered
 *   a fix, and 3 is what a broken install exits with.
 * - the same bytes outside src/ report nothing and exit 0, so the reporting
 *   half cannot be coming from a run that ignores CleanCode/ruleset.xml's path scoping.
 * - passing.php inside src/ reports nothing and exits 0 — the negative control,
 *   without which a shell-out that always reported would satisfy the first.
 */
it('reports the violation end to end through the installed package', function (): void {
    $failing = fixturePath('NoProceduralCodeSniff', 'failing.php');

    $inSource = installedSniffRun(PROCEDURAL, stageFixtureOutsideTests($failing, 'src'));
    $outsideSource = installedSniffRun(PROCEDURAL, stageFixtureOutsideTests($failing, 'config'));
    $passing = installedSniffRun(
        PROCEDURAL,
        stageFixtureOutsideTests(fixturePath('NoProceduralCodeSniff', 'passing.php'), 'src')
    );

    expect(array_column($inSource['messages'], 'source'))->toHaveCount(14)
        ->each->toStartWith(PROCEDURAL . '.')
        ->and(array_unique(array_column($inSource['messages'], 'type')))->toBe(['ERROR'])
        ->and($inSource['status'])->toBe(1)
        ->and($outsideSource['messages'])->toBe([])
        ->and($outsideSource['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
