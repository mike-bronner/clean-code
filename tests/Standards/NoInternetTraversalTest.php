<?php

/**
 * Tests the custom CleanCode.Testing.NoInternetTraversal sniff (Testing: Test
 * Suites, #60, partial enforcement per #149). Fixtures live in
 * tests/fixtures/NoInternetTraversalSniff/: the sanctioned Http-facade route
 * and every near-miss shape in passing.php, the flagged primitives in
 * failing.php, the inline-suppression case in suppressed.php, and the two
 * shapes no well-formed file can produce in malformed.php. The rule is
 * detection-only, so there is no autofixed fixture.
 *
 * The sniff decides what to inspect from the file's own path, and the fixture
 * contract fixes both the fixture names and the directory they sit in —
 * tests/fixtures/NoInternetTraversalSniff/ holds no `tests/Feature` pair, so it
 * matches no default glob and the fixtures report nothing where they live. That
 * is also why the sniff is out of the generic contract sweep, which drives each
 * fixture in place: every assertion about the sniff's own behaviour runs against
 * a copy staged under a real feature-suite directory outside the repository
 * ($featureRun below), and the gate itself is pinned separately by the
 * inspects-nothing-outside-a-feature-test test, which requires the silence to
 * come from the path rather than from the sniff having nothing to say.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as sibling
 * standards land in rules.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Util\Tokens;

const NO_INTERNET_TRAVERSAL = 'CleanCode.Testing.NoInternetTraversal';

const NO_INTERNET_TRAVERSAL_WARNING = NO_INTERNET_TRAVERSAL . '.Found';

/**
 * The fixture directory this sniff owns, resolved once so the fixture path and
 * the shipped-binary run below cannot drift onto different files.
 */
const NO_INTERNET_TRAVERSAL_FIXTURES = 'NoInternetTraversalSniff';

/**
 * The suite directory the fixtures have to sit under to be seen at all. Spelled
 * once, because the staging helper and the shipped default glob have to agree
 * about it for any of these assertions to mean anything.
 */
const NO_INTERNET_TRAVERSAL_SUITE = 'tests/Feature';

// Fixtures are copied into a `tests/Feature` directory outside the repository
// before processing, because the sniff decides what to inspect from the file's
// path alone. The staged copies are removed by the afterEach() hook in
// tests/Pest.php.
$featurePath = static fn (string $fixture): string => stageFixtureOutsideTests(
    fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, $fixture),
    NO_INTERNET_TRAVERSAL_SUITE
);

$featureRun = static fn (string $fixture): LocalFile => analyzeWithSniffs(
    [NO_INTERNET_TRAVERSAL],
    $featurePath($fixture)
);

/**
 * Every warning failing.php owes, at the line and column of the token the
 * report is anchored to — the function name for a call, and the first token of
 * the written class name for an instantiation, which is the `\` on a
 * fully-qualified one. Shared by the in-process assertions and the
 * configurable-property one, which asserts the same verdicts arrive through a
 * retuned glob.
 *
 * @return array<int, array{line: int, column: int, source: string}>
 */
$expectedWarnings = static fn (): array => array_map(
    static fn (array $position): array => [
        'line' => $position[0],
        'column' => $position[1],
        'source' => NO_INTERNET_TRAVERSAL_WARNING,
    ],
    [
        [8, 11],
        [9, 1],
        [10, 11],
        [11, 11],
        [12, 12],
        [13, 10],
        [14, 9],
        [15, 10],
        [16, 11],
        [17, 12],
        [18, 17],
        [19, 15],
        [20, 16],
        [21, 18],
        [22, 19],
        [23, 13],
        [24, 14],
        [25, 11],
        [26, 11],
        [27, 12],
        [30, 11],
        [33, 17],
    ]
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_INTERNET_TRAVERSAL);
});

/**
 * The sanctioned route and every near-miss stay silent, inside a real feature
 * directory where the gate is open — so the silence is the sniff's verdict, not
 * the path's. Each group pins one decision, and a false positive on any of them
 * makes the rule unusable in a real feature test:
 *
 * - lines 9-10, `Http::fake()` and `Http::get()` — the route the standard asks
 *   for. The facade is not a watched primitive, which is what keeps it quiet;
 *   the AC calls this out separately because flagging the fake would invert the
 *   rule.
 * - lines 13-15, `file_get_contents()` on a local path — the function is
 *   ordinary. A magic-constant expression, a bare filename and a relative path
 *   each name no scheme.
 * - line 18, a variable argument — the file does not state where it points.
 * - line 19, `'https://…' . $path` — a whole literal, but only the head of an
 *   expression, so the argument's value is not stated either. This is the
 *   trailing-token guard.
 * - line 20, `$base . 'https://…'` — the same expression the other way round,
 *   where the argument's *first* token is not a literal at all. Both directions,
 *   because either guard alone would let one of them through.
 * - lines 21-22, a literal holding a newline — PHP_CodeSniffer tokenizes it one
 *   token per physical line, so the second fragment is what the trailing-token
 *   guard sees, and only a whole literal ever reaches the scheme test.
 *   Confirmed by mutation, not by reading: this fixture is one of the two that
 *   redden when that guard is removed.
 * - lines 25-29, names that are not calls to PHP's own function: a method call,
 *   a static call, a nullsafe method call, a property read with no call at all,
 *   and a variable that merely shares the spelling.
 * - lines 32-38, `new` of something that is not the Guzzle client: a same-named
 *   class from another namespace, its fully-qualified spelling, a dynamic class
 *   name, and an anonymous class.
 * - line 41, a `curl_exec` *declaration* — the T_FUNCTION preceder, which
 *   FunctionCalls::isGlobalFunctionCall() is what rules out.
 * - line 47, a call carrying no argument at all — the argument region is empty,
 *   so there is no first token to read a URL out of. Confirmed by mutation: this
 *   is the only fixture line that reddens when the empty-argument guard is made
 *   to fall open.
 * - line 51, a named argument carrying the URL under a *different* label —
 *   `context:` is not the parameter the file is read from, so the call states no
 *   filename. Confirmed by mutation: this line reddens when the label is matched
 *   by shape instead of by name.
 * - line 52, `filename:` given a variable — the label is the right one and the
 *   value is still not stated, which is the variable-URL boundary reached
 *   through the named spelling.
 * - lines 56-60, each watched name written as a first-class callable — the four
 *   network functions and the URL reader. `curl_init(...)` builds a Closure and
 *   calls nothing, so the line opens no connection and reads no URL. The four
 *   network functions are the discriminating half: each one reddens here when
 *   the first-class-callable guard is removed, because their branch reports on
 *   the name alone. `file_get_contents(...)` is silent either way — the
 *   string-literal precondition downstream already refuses the ellipsis — and is
 *   carried anyway so the shape is pinned for the whole rule rather than for the
 *   branch that happens to need the guard.
 * - lines 67-85, six heredoc and nowdoc arguments the file states no single URL
 *   for, one per guard on that branch: a local path, a body of several physical
 *   lines (line 70, which is the split-literal boundary reached through a
 *   heredoc), a body whose first line is blank (line 74, the same boundary with
 *   the URL below the fold), a body that is empty (line 78), a heredoc
 *   concatenated with a variable (line 80, the trailing-token guard), and a URL
 *   under a `context:` label with a local path for the filename (line 83). Each
 *   of the middle four reddens when the guard it names is removed.
 */
it('produces no violations on the compliant fixture', function () use ($featureRun): void {
    $file = $featureRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * The two guards a well-formed file can never reach, each one fail-closed.
 *
 * Both shapes in malformed.php stop the sniff before it has a token region to
 * read, and neither is reachable from source that parses:
 *
 * - line 12, an argument list that is never closed. PHP_CodeSniffer leaves the
 *   opening parenthesis with no `parenthesis_closer`, so the argument region has
 *   no end — and the URL written inside it must not be reported off a region the
 *   tokenizer never established.
 * - line 16, a `new` with nothing after it but whitespace, so there is no token
 *   to read a class name from.
 *
 * The fixture is deliberate, not a broken file left in the tree: a consumer runs
 * phpcs over whatever is on disk, including a file mid-edit, and a sniff that
 * reports off half a token region is worse than one that says nothing. Both
 * assertions discriminate — each guard, made to fall open, reddens this test.
 */
it('says nothing about source it cannot read to the end of', function () use ($featureRun): void {
    $file = $featureRun('malformed.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every flagged shape, at its own line and column. The twenty-two cover all
 * three detections and both halves of the case-insensitivity the sniff claims:
 *
 * - lines 8-11, one call per watched network function.
 * - line 12, `\curl_init()` — the leading separator qualifies the global
 *   namespace, which is the most explicit spelling of the very call watched for.
 * - line 13, `CURL_EXEC()` — PHP function names are case-insensitive, so the
 *   spelling of the call does not change the answer.
 * - lines 14-16, a `file_get_contents()` URL under each scheme, plus `HTTPS://`
 *   uppercased: PHP resolves a stream wrapper's scheme case-insensitively too,
 *   and both positions the sniff folds case at are pinned rather than only the
 *   one the happy path exercises.
 * - line 17, a second argument after the URL — the trailing-token guard admits a
 *   comma, so a context argument does not hide the request.
 * - line 18, an interpolated URL — the leading characters are still literal, and
 *   this is the only fixture reaching T_DOUBLE_QUOTED_STRING.
 * - lines 19-22, the four spellings of the Guzzle client that resolve to the
 *   same class: the imported short name, an alias, the fully-qualified name, and
 *   a lowercased short name.
 * - lines 23-24, the URL passed as a named argument: `filename:` leading the
 *   list, and `filename:` behind another named argument, because PHP orders
 *   named arguments freely and either spelling passes the same filename as the
 *   positional call on line 14. Both reddens are load-bearing — line 23 is lost
 *   when the label is not stepped over, line 24 when only a leading label is.
 * - line 25, a *nested* call carrying a `filename:` label of its own, ahead of
 *   this call's own one. The label read has to be the one belonging to this
 *   call: made to take the first `filename:` anywhere inside the parentheses,
 *   the sniff reads `filesize()`'s local path and goes silent on the URL beside
 *   it, which is what this line reddens on.
 * - line 26, `curl_init(...$arguments)` — a spread argument, which spells a real
 *   call and opens a real connection, unlike the first-class callable one token
 *   shorter in passing.php. This line is what holds the two apart: the guard
 *   made to take a leading ellipsis alone, without requiring the closing
 *   parenthesis behind it, loses this warning.
 * - lines 27, 30 and 33, the URL written as a heredoc and as a nowdoc, the last
 *   of them behind a `filename:` label. PHP passes the same string a quoted
 *   literal would, and this package's own CleanCode.Strings.MultilineStrings
 *   rewrites a multi-line double-quoted string *into* this shape, so a project
 *   following the standards is pushed toward it. All three lines are lost when
 *   the heredoc branch is removed; line 27 alone is lost when the closing
 *   marker's indentation is not stripped from the body, because the text then
 *   opens with spaces instead of the scheme.
 */
it('flags every violation at its own line and column', function () use (
    $featureRun,
    $expectedWarnings
): void {
    $file = $featureRun('failing.php');

    expect(warningTuples($file))->toBe($expectedWarnings());
});

/**
 * The message names the primitive that matched, which is the AC's own
 * requirement: a report that only said "traverses the internet" would leave the
 * reader hunting the line for which of six shapes tripped it.
 *
 * A call is named as it is written, so a case-insensitive match still points at
 * the text on the line. An instantiation is named by the *resolved* class,
 * because the written name can be an alias that appears nowhere in the standard.
 */
it('names the offending primitive in the warning message', function () use ($featureRun): void {
    $warnings = $featureRun('failing.php')->getWarnings();

    expect($warnings[8][11][0]['message'])->toStartWith('curl_init() traverses the internet')
        ->and($warnings[13][10][0]['message'])->toStartWith('CURL_EXEC() traverses the internet')
        ->and($warnings[14][9][0]['message'])->toStartWith('file_get_contents() traverses')
        ->and($warnings[20][16][0]['message'])->toStartWith('new GuzzleHttp\Client traverses')
        ->and($warnings[8][11][0]['message'])->toContain('Http facade')
        ->and($warnings[8][11][0]['message'])->toContain('docs/standards/testing-test-suites.md');
});

/**
 * The filename gate is load-bearing, not decoration.
 *
 * Three runs over the same bytes, because no one of them is enough on its own:
 * the two silent runs would pass just as well against a sniff that never fires
 * at all, and the reporting run alone says nothing about the gate. Together they
 * say the diagnostics turn on the path and nothing else.
 *
 * The in-repo run is where the fixture actually lives, under tests/fixtures/ —
 * the path the generic contract sweep would have driven, and the reason this
 * sniff is held out of it. The staged run outside a feature directory covers the
 * same absence at a path the repository does not control, so the silence cannot
 * be coming from some other tests/ scoping.
 */
it('inspects nothing outside a feature test', function () use ($featureRun): void {
    $inRepo = analyzeWithSniffs(
        [NO_INTERNET_TRAVERSAL],
        fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php')
    );

    $outsideSuite = analyzeWithSniffs(
        [NO_INTERNET_TRAVERSAL],
        stageFixtureOutsideTests(fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php'))
    );

    expect($inRepo->getWarnings())->toBe([])
        ->and($inRepo->getErrors())->toBe([])
        ->and($outsideSuite->getWarnings())->toBe([])
        ->and($outsideSuite->getErrors())->toBe([])
        ->and($featureRun('failing.php')->getWarnings())->toHaveCount(22);
});

/**
 * The gate's globs are a public sniff property, as the AC and the standard's doc
 * both require. The same bytes at the same path are run twice: under the shipped
 * default a copy staged in a suite this configuration does not name is silent,
 * and once the property names that directory the full nineteen warnings arrive.
 * A property that was ignored would leave both runs identical and fail the
 * second half.
 *
 * The replacement glob is derived from the staged path rather than written out,
 * so the test cannot pass by accident on a staging root that happened to match
 * the shipped default.
 */
it('exposes a configurable feature-test pattern list', function () use ($expectedWarnings): void {
    $staged = stageFixtureOutsideTests(
        fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php'),
        'suites/Acceptance'
    );

    $default = analyzeWithSniffs([NO_INTERNET_TRAVERSAL], $staged);

    $configured = analyzeWithSniffs(
        [NO_INTERNET_TRAVERSAL],
        $staged,
        static function (object $sniff) use ($staged): void {
            $sniff->featureTestPatterns = ['*/' . basename(dirname($staged)) . '/*'];
        }
    );

    expect($default->getWarnings())->toBe([])
        ->and(warningTuples($configured))->toBe($expectedWarnings());
});

/**
 * Reports stay suppressible the ordinary way, which the AC asks for explicitly.
 * Nothing in the sniff implements this — PHPCS's own annotations do it — so the
 * assertion exists to catch a future report that stops going through
 * addWarning() and starts bypassing the annotation layer.
 *
 * Both annotation forms are covered, and the un-annotated call on line 15 is the
 * control: without it a sniff that had simply gone silent on this file would
 * satisfy the suppression half.
 */
it('honours inline suppression', function () use ($featureRun): void {
    $file = $featureRun('suppressed.php');

    expect(warningTuples($file))->toBe([[
        'line' => 15,
        'column' => 13,
        'source' => NO_INTERNET_TRAVERSAL_WARNING,
    ]]);
});

/**
 * Pins the two severity decisions: the rule warns rather than errors, matching
 * the rest of the Testing standards, because it reads a project's suite layout
 * off a configurable convention and a primitive is a strong signal rather than
 * proof a request left the machine; and nothing is fixable, because replacing a
 * real request with a fake means writing the fake.
 */
it('reports detection-only warnings', function () use ($featureRun): void {
    $file = $featureRun('failing.php');

    expect($file->getWarningCount())->toBe(22)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
});

/**
 * STRING_TOKENS answers to a family it does not itself define, and this holds
 * the two together.
 *
 * A hand-typed token list quietly missing a member of its family is the defect
 * this repository keeps paying for (#316), and it cannot be seen from behaviour:
 * a missing member makes this sniff *quieter*, never louder, so every other test
 * here would stay green. The family is therefore taken from PHP_CodeSniffer at
 * run time — Tokens::$stringTokens is its own register of the tokens a string
 * literal arrives as — rather than restated in a second hand-typed array here,
 * which would go stale in exactly the same way.
 *
 * The constant's membership is parsed out of the sniff's source rather than read
 * by Reflection, because this package forbids Reflection in its own tests
 * (CleanCode.Testing.NoReflectionAccess). Every member is included and none is
 * excluded, so the two lists are compared whole.
 */
it('accounts for every string token PHPCS defines', function (): void {
    $path = cleanCodeRoot() . '/CleanCode/Sniffs/Testing/NoInternetTraversalSniff.php';
    $source = (string) file_get_contents($path);
    $declaration = strpos($source, 'private const STRING_TOKENS = [');

    expect($declaration)->not->toBeFalse('the constant is still declared under that name');

    $opening = (int) $declaration;
    $closing = (int) strpos($source, '];', $opening);
    $body = substr($source, $opening, $closing - $opening);

    preg_match_all('/^\s+(T_[A-Z_0-9]+),$/m', $body, $entries);

    // A member arrives as an int for a native token and as a PHPCS_-prefixed
    // string for a backfilled one, exactly as `accounts for every scope opener
    // PHPCS defines` reads Tokens::$scopeOpeners.
    $family = array_map(
        static fn (int|string $code): string => is_int($code) === true
            ? (string) token_name($code)
            : (string) preg_replace('/^PHPCS_/', '', $code),
        array_values(Tokens::$stringTokens)
    );

    sort($family);
    $accounted = $entries[1];
    sort($accounted);

    expect($accounted)->toBe($family);
});

/**
 * The same holding, for the heredoc family. HEREDOC_OPENERS and HEREDOC_BODIES
 * carry two members each, and PHP_CodeSniffer's Tokens::$heredocTokens carries
 * six — the two openers, the two bodies, and the two closing tokens the sniff
 * reaches through the opener's own scope_closer rather than by name. Comparing
 * the four accounted-for members against the family minus its closers is what
 * says the split is exhaustive: a seventh member in a later PHP_CodeSniffer
 * lands on neither side of it and reddens here.
 *
 * This matters more than the arithmetic suggests. The gap it guards is the one
 * this sniff shipped with — STRING_TOKENS is exactly Tokens::$stringTokens,
 * which excludes T_HEREDOC and T_NOWDOC, so a heredoc URL went unread — and it
 * is the third time this repository has paid for a token list omitting the
 * heredoc family (PR #222, PR #264).
 */
it('accounts for every heredoc token PHPCS defines', function (): void {
    $path = cleanCodeRoot() . '/CleanCode/Sniffs/Testing/NoInternetTraversalSniff.php';
    $source = (string) file_get_contents($path);
    $accounted = [];

    foreach (['HEREDOC_OPENERS', 'HEREDOC_BODIES'] as $constant) {
        $declaration = strpos($source, 'private const ' . $constant . ' = [');

        expect($declaration)->not->toBeFalse($constant . ' is still declared under that name');

        $opening = (int) $declaration;
        $closing = (int) strpos($source, '];', $opening);

        preg_match_all('/^\s+(T_[A-Z_0-9]+),$/m', substr($source, $opening, $closing - $opening), $entries);

        $accounted = array_merge($accounted, $entries[1]);
    }

    $closers = ['T_END_HEREDOC', 'T_END_NOWDOC'];

    // A member arrives as an int for a native token and as a PHPCS_-prefixed
    // string for a backfilled one, exactly as the string-token test above reads
    // Tokens::$stringTokens.
    $whole = array_map(
        static fn (int|string $code): string => is_int($code) === true
            ? (string) token_name($code)
            : (string) preg_replace('/^PHPCS_/', '', $code),
        array_values(Tokens::$heredocTokens)
    );

    $family = array_values(array_diff($whole, $closers));

    sort($family);
    sort($accounted);

    expect($accounted)->toBe($family)
        ->and(array_intersect($closers, $whole))
        ->toHaveCount(2, 'the closing tokens the scope_closer stands in for are still the two named here');
});

/**
 * The name-token run is the one enumeration here that no fixture can pin whole,
 * and this says so outright rather than leaving the gap to be discovered — the
 * same disclosure CleanCode.Routes.DisallowNonResourceRoutes makes about its own
 * receiver-token list.
 *
 * PHP_CodeSniffer 3.x undoes PHP 8's single qualified-name tokens back to the
 * pre-8.0 T_STRING/T_NS_SEPARATOR spelling, so the three T_NAME_* codes cannot
 * arrive at all, and T_NAMESPACE leads a `namespace\Client` relative name that
 * resolves to `\GuzzleHttp\namespace\Client` inside `namespace GuzzleHttp;` —
 * a literal segment matching no watched client, so that token reaches no
 * report either. Both halves of the
 * reachable pair are covered by failing.php — the short name on line 19, the
 * separator-led one on line 21 — and this test asserts the tokenizer behaviour
 * the disclosure rests on, so a release that stops undoing it reddens here
 * instead of silently unflagging every qualified spelling.
 */
it('sees a qualified class name in the pre-8.0 spelling', function (): void {
    $file = parseFixture(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php');
    $tokens = $file->getTokens();
    $codes = [];

    foreach ($tokens as $token) {
        if ($token['line'] === 21) {
            $codes[] = $token['type'];
        }
    }

    expect($codes)->toContain('T_NS_SEPARATOR')
        ->and($codes)->toContain('T_STRING')
        ->and($codes)->not->toContain('T_NAME_FULLY_QUALIFIED');
});

/**
 * The same verdict through the shipped, installed package.
 *
 * Every test above drives PHP_CodeSniffer in process through ConfigDouble, which
 * supplies the registration Composer would have supplied — so a package that
 * never registered itself with the installed standards passes all of them. This
 * one executes the real vendor/bin/phpcs as a separate process from outside the
 * package, against rules.xml, the file a consumer points --standard at. The
 * shared sweep in tests/Contract/ShippedPackageSmokeTest.php cannot reach this
 * sniff: it drives each fixture where it lives, under tests/, where the default
 * glob matches nothing.
 *
 * Asserted in the same paired shape as the gate test above rather than on the
 * positive half alone:
 *
 * - the copy staged under `tests/Feature` reports all nineteen, every message
 *   under this sniff's own code and at WARNING severity, at status 1 —
 *   violations, none of them fixable, which is what this detection-only rule
 *   owes. Status 2 would mean phpcbf had been offered a fix, and 3 is what a
 *   broken install exits with.
 * - the in-repo copy of the same bytes reports nothing and exits 0, so the
 *   reporting half cannot be coming from a run that ignores the gate.
 * - passing.php staged the same way reports nothing and exits 0 — the negative
 *   control, without which a shell-out that always reported would satisfy the
 *   first.
 */
it('reports the violation end to end through the installed package', function () use (
    $featurePath
): void {
    $staged = installedSniffRun(NO_INTERNET_TRAVERSAL, $featurePath('failing.php'));
    $inRepo = installedSniffRun(
        NO_INTERNET_TRAVERSAL,
        fixturePath(NO_INTERNET_TRAVERSAL_FIXTURES, 'failing.php')
    );
    $passing = installedSniffRun(NO_INTERNET_TRAVERSAL, $featurePath('passing.php'));

    expect(array_column($staged['messages'], 'source'))->toHaveCount(22)
        ->each->toBe(NO_INTERNET_TRAVERSAL_WARNING)
        ->and(array_unique(array_column($staged['messages'], 'type')))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(1)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
