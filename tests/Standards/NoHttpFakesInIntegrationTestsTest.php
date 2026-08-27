<?php

/**
 * Tests the custom CleanCode.Testing.NoHttpFakesInIntegrationTests sniff
 * (Testing: Test Suites, #60, partial enforcement per #150). Fixtures live in
 * tests/fixtures/NoHttpFakesInIntegrationTestsSniff/: the real requests the
 * suite exists to make and every near-miss shape in passing.php, the installed
 * fakes and the hand-built client doubles in failing.php. The rule is
 * detection-only, so there is no autofixed fixture.
 *
 * The sniff decides what to inspect from the file's own path, and the fixture
 * contract fixes both the fixture names and the directory they sit in —
 * tests/fixtures/NoHttpFakesInIntegrationTestsSniff/ holds no `tests/Integration`
 * pair and so matches no default glob, which means the fixtures report nothing
 * where they live. That is also why the sniff is out of the generic contract
 * sweep, which drives each fixture in place: every assertion about the sniff's
 * own behaviour runs against a copy staged under a real `tests/Integration`
 * directory outside the repository ($integrationRun below), and the gate itself
 * is pinned separately by the inspects-nothing-outside-an-integration-test test,
 * which requires the silence to come from the path rather than from the sniff
 * having nothing to say.
 *
 * The sniff is isolated from the rest of the master ruleset (loaded, then
 * $ruleset->sniffs is narrowed to it) so these assertions stay stable as
 * sibling standards land in rules.xml.
 */

declare(strict_types=1);

use PHP_CodeSniffer\Files\LocalFile;

const NO_HTTP_FAKES = 'CleanCode.Testing.NoHttpFakesInIntegrationTests';

const NO_HTTP_FAKES_FAKE = NO_HTTP_FAKES . '.FakedHttpClient';

const NO_HTTP_FAKES_MOCK = NO_HTTP_FAKES . '.MockedHttpClient';

/**
 * The fixture directory this sniff owns, resolved once so the fixture path and
 * the shipped-binary run below cannot drift onto different files.
 */
const NO_HTTP_FAKES_FIXTURES = 'NoHttpFakesInIntegrationTestsSniff';

// Fixtures are copied into a `tests/Integration` directory outside the
// repository before processing, because the sniff decides what to inspect from
// the file's path alone. The staged copies are removed by the afterEach() hook
// in tests/Pest.php.
$integrationPath = static fn (string $fixture): string => stageFixtureOutsideTests(
    fixturePath(NO_HTTP_FAKES_FIXTURES, $fixture),
    'tests/Integration'
);

$integrationRun = static fn (string $fixture): LocalFile => analyzeWithSniffs(
    [NO_HTTP_FAKES],
    $integrationPath($fixture)
);

/**
 * Every warning failing.php owes, at the line and column of the called member
 * itself. Shared by the in-process assertions and the configurable-property
 * one, which asserts the same verdicts arrive through a retuned glob.
 *
 * @return array<int, array{line: int, column: int, source: string}>
 */
$expectedWarnings = static fn (): array => array_map(
    static fn (array $position): array => [
        'line' => $position[0],
        'column' => $position[1],
        'source' => $position[2],
    ],
    [
        [3, 7, NO_HTTP_FAKES_FAKE],
        [4, 7, NO_HTTP_FAKES_FAKE],
        [5, 7, NO_HTTP_FAKES_FAKE],
        [6, 8, NO_HTTP_FAKES_FAKE],
        [7, 34, NO_HTTP_FAKES_FAKE],
        [8, 7, NO_HTTP_FAKES_FAKE],
        [9, 7, NO_HTTP_FAKES_FAKE],
        [10, 7, NO_HTTP_FAKES_FAKE],
        [12, 8, NO_HTTP_FAKES_MOCK],
        [13, 8, NO_HTTP_FAKES_MOCK],
        [14, 9, NO_HTTP_FAKES_MOCK],
        [15, 10, NO_HTTP_FAKES_MOCK],
        [16, 10, NO_HTTP_FAKES_MOCK],
        [17, 8, NO_HTTP_FAKES_MOCK],
        [18, 8, NO_HTTP_FAKES_MOCK],
    ]
);

it('is registered in the master ruleset', function (): void {
    [, $ruleset] = buildRuleset();

    expect($ruleset->sniffCodes)->toHaveKey(NO_HTTP_FAKES);
});

/**
 * The compliant shapes and every near-miss stay silent. Each group pins one of
 * the sniff's decisions, and a false positive on any of them makes the rule
 * unusable inside a real integration test:
 *
 * - lines 3-4, `Http::get()` and `Http::withToken()->post()` — the unfaked
 *   requests the suite exists to make. They are absent from the watched fake
 *   list, which is what keeps them quiet.
 * - line 5, `Http::FAKE` with no argument list — a class-constant read. The
 *   receiver and the member both match, and only the missing `(` separates it
 *   from an installed fake, so this is what pins the open-parenthesis half of
 *   callOpener().
 * - line 6, `Http::fake(...)` — PHP 8.1's first-class-callable syntax builds a
 *   Closure and installs nothing. Its paired case is failing.php line 10,
 *   `Http::fake(...$responses)`, an argument spread that *does* install one and
 *   is flagged; between them they pin both halves of the ellipsis check.
 * - lines 7-8, `Https::fake()` and `ApiHttp::fake()` — receivers whose trailing
 *   segment merely contains or extends `Http`. Without the whole-segment
 *   comparison these read as the facade.
 * - line 9, `$http::fake()` — a variable receiver. It is silent either way, and
 *   deleting isHttpFake()'s NAME_TOKENS check changes no result here (verified
 *   by mutation — the suite stays green without it), because the token's own
 *   content keeps its `$` and so can never equal `http`. That check is a type
 *   guard rather than a behavioural branch, stated plainly here rather than
 *   left to imply coverage no fixture can give it, exactly as
 *   CleanCode.Routes.DisallowNonResourceRoutes does for its own.
 * - line 11, `$this->http->fake()` — the instance-side fake the class docblock
 *   records as a known false negative, and the one shape that pins
 *   isHttpFake()'s `::` check: the token before the second `->` is the T_STRING
 *   `http`, so without that check the receiver comparison would match and this
 *   line would report. Deleting the check reddens this test.
 * - line 10, `$responses->fake()` — a `fake()` on some object the file never
 *   names. It falls out at the mock-creator check rather than at anything in
 *   isHttpFake(), and is asserted here so the shape is pinned rather than to
 *   imply coverage of a branch it does not reach.
 * - line 12, `$this->fakeIt()` — a member name that merely starts with a
 *   watched one. The comparison is on the whole name, not a prefix.
 * - line 14, `$this->createMock(PaymentGateway::class)` — a mock of something
 *   that is not an HTTP client at all, the ordinary case the rule must not
 *   touch.
 * - lines 15-17, `App\Support\Client::class`, `\Client::class` and
 *   `GuzzleHttp\ClientFactory::class` — qualified names that say which class
 *   they are and are not the watched ones. Each pins one half of
 *   isHttpClient(): line 15 that a qualified name is *not* matched on its
 *   trailing segment (or every project's own `Client` would report), line 16
 *   that a leading separator makes a one-segment name qualified, and line 17
 *   that the prefix comparison is segment-wise rather than textual —
 *   `GuzzleHttp\ClientFactory` starts with `GuzzleHttp\Client` as a string.
 * - lines 18-19, `Client::DEFAULT` and `CLIENT` — a constant *on* a class and a
 *   bare constant. Both pin classConstantEnd(): a class reference has to carry
 *   `::class`, and neither of these does. Line 19 is deliberately named for a
 *   configured client, so it is only the missing `::class` that separates it
 *   from a reported mock — a classConstantEnd() that fell back to the bare name
 *   would report it.
 * - line 20, `$this->mock($class)` — a variable argument, which no name run can
 *   read. Together with line 23 it covers the empty-run path: the pointer stays
 *   where it started and the token there is not `::`, which is what rejects it.
 * - lines 21-22, `Client::class . $suffix` and `'GuzzleHttp\Client' . $suffix`
 *   — a concatenation on each of the two argument paths. Both pin
 *   endsTheArgument(): the name resolves on its own, and only the token after
 *   it says the argument is a built string rather than a class reference. The
 *   string-literal path needs its own line because it returns before the
 *   name-run path is reached.
 * - line 23, `$this->mock()` — an empty argument list, where the token found
 *   where an argument would be is the closing parenthesis.
 * - line 24, `$this->createMock(originalClassName: Client::class)` — a named
 *   argument. The label is not a class reference and the `:` after it is not
 *   what ends an argument.
 * - line 25, `Mockery::close()` — a member on a mocking library that creates
 *   nothing.
 * - line 26, `$builder->mock` with no argument list — a property read of a
 *   watched creator name. This is callOpener()'s open-parenthesis half again,
 *   reached through `->` rather than `::`.
 */
it('produces no violations on the compliant fixture', function () use ($integrationRun): void {
    $file = $integrationRun('passing.php');

    expect($file->getErrors())->toBe([])
        ->and($file->getWarnings())->toBe([]);
});

/**
 * Every installed double is flagged once, anchored at the called member rather
 * than at the receiver or the statement:
 *
 * - lines 3-5, `Http::fake()`, `Http::fakeSequence()` and
 *   `Http::preventStrayRequests()` — the three watched facade methods, one per
 *   line. The third is the one the standard's own wording turns on: an
 *   integration test *is* the stray request.
 * - line 6, `\Http::fake()` at column 8 and line 7,
 *   `Illuminate\Support\Facades\Http::fake()` at column 34 — the qualified
 *   spellings of the same facade. The receiver is compared on its trailing
 *   segment, so both report, and their columns record that the anchor follows
 *   the receiver rather than opening the statement.
 * - line 8, `HTTP::fake()` — the receiver half of PHP's case-insensitivity.
 *   The receiver is the same length as the canonical `Http`, so the anchor
 *   stays at column 7 and only the casing differs from line 3: a comparison
 *   against a literal `Http` leaves this line unreported.
 * - line 9, `Http::FAKE()` — the member half of the same rule, at the same
 *   column for the same reason. Both positions the case-insensitivity applies
 *   to are pinned, not just the one the happy path exercises.
 * - line 10, `Http::fake(...$responses)` — an argument spread, not a
 *   first-class callable, so it installs a fake and is flagged. Its counterpart
 *   is passing.php line 6.
 * - line 12, `$this->createMock(Client::class)` and line 14,
 *   `$this?->mock(Client::class)` — an unqualified name resolved against the
 *   configured clients' own trailing segment, reached through `->` and through
 *   `?->`. The nullsafe operator is a separate token PHPCS never folds into
 *   the plain one, so it needs its own line.
 * - line 13, `$this->mock(\GuzzleHttp\Client::class)` — the fully-qualified
 *   spelling, matched as an equal rather than through a trailing segment.
 * - line 15, `Mockery::mock(Illuminate\Http\Client\Factory::class)` — a class
 *   *beneath* a configured namespace root, which is what the `Illuminate\Http\
 *   Client\*` half of the rule means, reached through `::`.
 * - line 16, `Mockery::mock(Client::class, $expectations)` — a second argument
 *   after the class reference. This is endsTheArgument()'s comma half; without
 *   it only the closing-parenthesis spelling would report.
 * - line 17, `$this->mock('GuzzleHttp\Client')` — a single-quoted string
 *   literal, which PHP never resolves through the file's imports and this sniff
 *   therefore reads as fully qualified.
 * - line 18, `$this->createMock("Illuminate\\Http\\Client\\PendingRequest")` —
 *   the double-quoted spelling of the same shape, whose escaped separators have
 *   to be collapsed to name the same class the single-quoted form would. A
 *   quote-stripping helper that did not unescape would leave this line
 *   unreported while line 17 still reported.
 */
it('flags every violation at its own line and column', function () use ($integrationRun, $expectedWarnings): void {
    $file = $integrationRun('failing.php');

    expect($file->getErrors())->toBe([])
        ->and(warningTuples($file))->toBe($expectedWarnings());
});

/**
 * The message names what was doubled, so a developer reading the report knows
 * which call to reconsider. Line 9's `FAKE` and line 18's escaped literal are
 * asserted because both comparisons normalise before matching while the message
 * does not: the source spelling has to survive into the output for the fake,
 * and the *unescaped* class name for the mock.
 */
it('names the doubled dependency in the warning message', function () use ($integrationRun): void {
    $warnings = $integrationRun('failing.php')->getWarnings();

    expect($warnings[3][7][0]['message'])->toContain('Http::fake()')
        ->and($warnings[9][7][0]['message'])->toContain('Http::FAKE()')
        ->and($warnings[18][8][0]['message'])->toContain('\Illuminate\Http\Client\PendingRequest');
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
 * sniff is held out of it. The staged run outside a `tests/Integration` pair
 * covers the same absence at a path the repository does not control, so the
 * silence cannot be coming from some other tests/ scoping.
 */
it('inspects nothing outside an integration test', function () use ($integrationRun): void {
    $inRepo = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php')
    );

    $outsideIntegration = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        stageFixtureOutsideTests(fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php'), 'tests/Feature')
    );

    expect($inRepo->getWarnings())->toBe([])
        ->and($inRepo->getErrors())->toBe([])
        ->and($outsideIntegration->getWarnings())->toBe([])
        ->and($outsideIntegration->getErrors())->toBe([])
        ->and($integrationRun('failing.php')->getWarnings())->toHaveCount(15);
});

/**
 * The gate's globs are a public sniff property, as the standard's doc
 * advertises. The same bytes at the same path are run twice: under the shipped
 * default a copy staged outside any `tests/Integration` pair is silent, and once
 * the property names that directory the full fifteen warnings arrive. A property
 * that was ignored would leave both runs identical and fail the second half.
 *
 * The replacement glob is derived from the staged path rather than written out,
 * so the test cannot pass by accident on a staging root that happened to match
 * the shipped default.
 */
it('exposes a configurable integration-test pattern list', function () use ($expectedWarnings): void {
    $staged = stageFixtureOutsideTests(
        fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php'),
        'suites/e2e'
    );

    $default = analyzeWithSniffs([NO_HTTP_FAKES], $staged);

    $configured = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff) use ($staged): void {
            $sniff->integrationPatterns = ['*/' . basename(dirname($staged)) . '/*'];
        }
    );

    expect($default->getWarnings())->toBe([])
        ->and(warningTuples($configured))->toBe($expectedWarnings());
});

/**
 * The three detection lists are public properties too, and each one really
 * drives its own half of the rule. Narrowing each in turn takes exactly the
 * violations that list is responsible for out of the report, which a list read
 * from somewhere else — or matched against a hardcoded name — could not do.
 *
 * `httpClientClasses` is emptied rather than narrowed: with no configured
 * client, every mock is somebody else's business, and only the eight facade
 * fakes remain.
 */
it('exposes the detection lists as configurable properties', function () use ($integrationPath): void {
    $staged = $integrationPath('failing.php');

    $withoutFakes = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff): void {
            $sniff->fakeMethods = ['fakeSequence'];
        }
    );

    $withoutCreators = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff): void {
            $sniff->mockCreators = ['createMock'];
        }
    );

    $withoutClients = analyzeWithSniffs(
        [NO_HTTP_FAKES],
        $staged,
        static function (object $sniff): void {
            $sniff->httpClientClasses = [];
        }
    );

    expect(array_column(warningTuples($withoutFakes), 'source'))
        ->toBe(array_merge([NO_HTTP_FAKES_FAKE], array_fill(0, 7, NO_HTTP_FAKES_MOCK)))
        ->and(array_column(warningTuples($withoutCreators), 'line'))
        ->toBe([3, 4, 5, 6, 7, 8, 9, 10, 12, 18])
        ->and(array_column(warningTuples($withoutClients), 'source'))
        ->toBe(array_fill(0, 8, NO_HTTP_FAKES_FAKE));
});

/**
 * Pins the two severity decisions this rule makes: it warns rather than errors,
 * because the class docblock's six boundaries have known false positives and
 * negatives a consumer must not have their build fail on; and nothing is
 * fixable, because removing a fake from an integration test means either
 * deleting coverage or moving the test to the feature suite, and only the
 * project says which.
 */
it('reports detection-only warnings', function () use ($integrationRun): void {
    $file = $integrationRun('failing.php');

    expect($file->getWarningCount())->toBe(15)
        ->and($file->getErrorCount())->toBe(0)
        ->and($file->getFixableCount())->toBe(0);
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
 * sniff: it drives each fixture where it lives, under tests/fixtures/, where the
 * default glob matches nothing.
 *
 * Asserted in the same paired shape as the gate test above rather than on the
 * positive half alone:
 *
 * - the copy staged under `tests/Integration` reports all fifteen, every message
 *   under this sniff's own codes and at WARNING severity, at status 1 —
 *   violations, none of them fixable, which is what this detection-only rule
 *   owes. Status 2 would mean phpcbf had been offered a fix, and 3 is what a
 *   broken install exits with.
 * - the in-repo copy of the same bytes reports nothing and exits 0, so the
 *   reporting half cannot be coming from a run that ignores the gate.
 * - passing.php staged the same way reports nothing and exits 0 — the negative
 *   control, without which a shell-out that always reported would satisfy the
 *   first.
 */
it('reports the violation end to end through the installed package', function () use ($integrationPath): void {
    $staged = installedSniffRun(NO_HTTP_FAKES, $integrationPath('failing.php'));
    $inRepo = installedSniffRun(
        NO_HTTP_FAKES,
        fixturePath(NO_HTTP_FAKES_FIXTURES, 'failing.php')
    );
    $passing = installedSniffRun(NO_HTTP_FAKES, $integrationPath('passing.php'));

    expect(array_values(array_unique(array_column($staged['messages'], 'source'))))
        ->toBe([NO_HTTP_FAKES_FAKE, NO_HTTP_FAKES_MOCK])
        ->and($staged['messages'])->toHaveCount(15)
        ->and(array_values(array_unique(array_column($staged['messages'], 'type'))))->toBe(['WARNING'])
        ->and($staged['status'])->toBe(1)
        ->and($inRepo['messages'])->toBe([])
        ->and($inRepo['status'])->toBe(0)
        ->and($passing['messages'])->toBe([])
        ->and($passing['status'])->toBe(0);
});
