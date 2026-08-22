<?php

/**
 * Tests the custom CleanCode.ClearCode.ActionSingleEntryPoint sniff (Clear
 * Code: Encapsulate Related Methods in a Class, #15/#161). Fixtures live in
 * tests/fixtures/ActionSingleEntryPointSniff/.
 *
 * The sniff carries the one token-visible slice of an otherwise Tier 3
 * standard: an Action class encapsulates one concept behind one way in, so
 * every public method past the first — the constructor aside — is a second
 * entry point that belongs in an Action class of its own.
 *
 * Warnings, not errors. Scope is decided from a naming and namespace
 * convention the sniff cannot prove a class really follows, and a public
 * accessor is reported without any judgement about whether it earned its
 * place, so a misread must not fail a build.
 *
 * The rule is detection-only: splitting an Action in two means creating a
 * class, moving a method, and rewriting every call site that reaches it. There
 * is no autofixed fixture.
 *
 * rules.xml does not path-scope this sniff, so the fixtures are processed where
 * they live. The sniff is isolated from the rest of the master ruleset (loaded,
 * then $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

const ACTION_SINGLE_ENTRY_POINT = 'CleanCode.ClearCode.ActionSingleEntryPoint';

const ACTION_SINGLE_ENTRY_POINT_WARNING = ACTION_SINGLE_ENTRY_POINT . '.Found';

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(ACTION_SINGLE_ENTRY_POINT);
});

/**
 * The compliant fixture is silent, and so is every near miss the sniff must
 * stay out of:
 *
 * - `App\Actions\ArchivePost` — in scope through the namespace segment, with a
 *   constructor and one entry point. The constructor is what makes this a
 *   two-method class the sniff still leaves alone.
 * - `App\Actions\Reindex` — protected and private methods only, and
 *   `App\Actions\Placeholder` — no methods at all. Zero entry points is not a
 *   violation: the rule is about a *second* way in.
 * - `App\Actions\Dispatch` — a single `public static` entry point named
 *   neither `__invoke` nor `handle`. Naming is the doc's convention, not this
 *   sniff's rule, and a static method is still an entry point.
 * - `App\Actions\NotifySubscribers` — one entry point beside three non-public
 *   siblings, one of them `private static`.
 * - `App\Actions\Billing\ChargeCard` — a namespace *below* `App\Actions`. The
 *   segment has to be present, not final.
 * - `app\actions\Refund` — the namespace half is compared case-insensitively.
 * - `App\Support\PublishPostAction` — the class-name half, outside any Actions
 *   namespace.
 * - `App\Support\Transaction`, `Reaction` and `Interaction` — the near misses
 *   that make the class-name half's case sensitivity load-bearing. Each ends
 *   in "action" but not in "Action", and each declares two or three public
 *   methods a case-insensitive suffix match would report.
 * - `App\Support\ActionFactory` — "Action" inside the name without ending it,
 *   which a substring match would report.
 * - `App\Support\ReportBuilder` — three public methods, matching neither half.
 * - `interface ExportAction`, `trait LogsAction`, `enum StatusAction` — all
 *   three carry the suffix and declare two public methods each. Only T_CLASS
 *   is registered, and PHP_CodeSniffer gives each of these its own token type.
 * - `App\ActionsArchive\Restore` — the namespace half is an exact segment
 *   match; a substring match over the namespace would report `restore()`.
 */
it('produces no violations on the compliant fixture', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every public method past the first, reported at its own declaration:
 *
 * - line 16, `undo()` — the second entry point of a class in scope through its
 *   namespace. Line 12's `handle()` is the first and stays silent, and line
 *   8's `__construct` is not an entry point at all.
 * - lines 33, 37, 41 and 45 — the four declaration shapes an extra entry point
 *   can take, in a class in scope through its name: an ordinary public method,
 *   a `public static` one, one with no visibility modifier at all (PHP
 *   defaults that to public), and an `abstract public` declaration with no
 *   body. Line 29's `__invoke()` is the first entry point; lines 47 and 52 are
 *   `protected` and `private`.
 * - line 70, `getResult()` — a public accessor handing back what the entry
 *   point computed. The sniff makes no exception for it; whether it earned its
 *   place is the judgement the warning severity exists for.
 * - line 89, `preview()` — the class declares `__construct`, `__invoke` and
 *   `preview`, and only `preview` is reported. A sniff that counted the
 *   constructor as the first entry point would report `__invoke()` on line 85
 *   instead, so this fixture pins the exclusion rather than merely allowing
 *   for it.
 *
 * The assertion is an exact list, so a sniff that started reporting any of the
 * silences above fails here.
 */
it('flags every public method past the first entry point', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 16, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 33, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 37, 'column' => 23, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 41, 'column' => 9, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 45, 'column' => 25, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 70, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
            ['line' => 89, 'column' => 16, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

/**
 * The message names the method, so a report over a whole application says
 * which entry point to move.
 */
it('names the offending method in the warning message', function (): void {
    $warnings = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'failing.php')->getWarnings();

    expect($warnings[70][16][0]['message'])->toContain('getResult()');
});

/**
 * Only a method the class declares at its own top level is an entry point. The
 * first class here writes a named function, a closure, and an anonymous class
 * with two public methods of its own inside `__invoke()`; all four declarations
 * sit between the class braces and a scan that merely walked every T_FUNCTION
 * there would count them.
 *
 * The second class repeats the same shapes beside a genuine second entry point,
 * so the assertion is two-sided: `undo()` on line 52 is reported, which is what
 * distinguishes a sniff that reads nested declarations correctly from one that
 * has fallen silent altogether.
 */
it('ignores declarations nested inside an entry point', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'nested-declarations.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 52, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

/**
 * PHP 8.4 property hooks are the case the class-body walk is structural for.
 * PHP_CodeSniffer opens no scope for a hook body, so a named function declared
 * inside one carries the *class* as its innermost enclosing condition — exactly
 * what a real method carries. A membership test reading each declaration's
 * `conditions` cannot tell the two apart and would report all three helpers
 * here.
 *
 * The first class declares one entry point beside two hooks holding three such
 * functions between them, and is silent. The second repeats one of those hooks
 * beside a real second entry point, so `refund()` on line 68 is reported and
 * the assertion is two-sided.
 */
it('ignores functions declared inside a property hook body', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'property-hooks.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 68, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

/**
 * A trait-adaptation block is the other brace that opens at a class's own top
 * level, and the walk has to step over it and carry on rather than stop at it.
 * The second class uses the *empty* form, which holds no `;` at all — the shape
 * that catches a scan bounded by "the next semicolon" instead of by the block's
 * own closing brace. `undo()` on line 34 is reported, which is what proves the
 * walk resumed inside the class.
 */
it('steps over a trait-adaptation block', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'trait-adaptations.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 34, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

/**
 * A closed class whose last member is a bare `public function` — no name, no
 * body, and no `;` — so the walk cannot resolve where that declaration ends.
 * It stops there rather than guessing, and reports what it read before that
 * point: `undo()` on line 19. A walk that threw the class away on an
 * unresolvable declaration would be silent here.
 */
it('reports what it read before an unresolvable declaration', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'truncated.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe([
            ['line' => 19, 'column' => 12, 'source' => ACTION_SINGLE_ENTRY_POINT_WARNING],
        ]);
});

/**
 * An unterminated class body. PHP_CodeSniffer resolves no scope for it — the
 * T_CLASS token gets neither a scope_opener nor a scope_closer — so the two
 * public methods written inside resolve to no class at all and nothing is
 * reported.
 *
 * Silence is deliberate rather than incidental: with the file's structure
 * unresolved, attributing methods to a class the tokenizer never closed would
 * be a guess. The behaviour is recorded in the sniff's own docblock.
 */
it('reports nothing on an unterminated class body', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'unterminated.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * A bare `class` keyword at the end of the file, inside an `App\Actions`
 * namespace. It is the one shape that reaches the sniff with no class name:
 * `class {` is tokenized as T_ANON_CLASS, which is never registered, so an
 * anonymous class cannot get here.
 *
 * The namespace still puts it in scope, so the null name reaches the suffix
 * test — and the guard in front of that test is load-bearing rather than
 * defensive: without it, str_ends_with() is handed null and the run dies with a
 * TypeError. There is no body to walk, so the answer is silence.
 */
it('passes over a class keyword with no name', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'nameless.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The sniff run through the standard over the standard's own source, which is
 * the AC's self-lint clause. Every `.php` file this package ships under
 * CleanCode/ has to come back silent — no sniff here is named `*Action` and
 * none sits in an `Actions` namespace, so a report from any of them is the
 * detection scope having widened past the convention it is supposed to read.
 *
 * The token count is asserted per file because the paths come from a glob. A
 * file PHP_CodeSniffer cannot read yields no tokens and therefore no warnings,
 * so without it this test would go quietly green the day the tree moves —
 * passing for the one reason it must never pass for. The dataset is asserted
 * non-empty for the same reason one level up.
 */
it('leaves the package own source alone', function (): void {
    $paths = glob(cleanCodeRoot() . '/CleanCode/*/*/*.php');

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        $file = analyzeWithSniffs([ACTION_SINGLE_ENTRY_POINT], $path);

        expect($file->numTokens)->toBeGreaterThan(0)
            ->and(warningTuples($file))->toBe([])
            ->and($file->getErrors())->toBe([]);
    }
});

/**
 * Pins the detection-only decision at the severity the standard speaks in:
 * warnings, never errors, and nothing fixable. Splitting an Action in two
 * rewrites every call site that reaches the method being moved, so there is no
 * mechanical fix to offer.
 */
it('reports detection-only warnings', function (): void {
    $file = analyzeFixture(ACTION_SINGLE_ENTRY_POINT, 'failing.php');

    expect($file->getWarningCount())->toBe(7)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});
