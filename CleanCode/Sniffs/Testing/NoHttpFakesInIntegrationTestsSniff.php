<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Warns on an HTTP double installed inside an integration test.
 *
 * Partial enforcement of "Testing: Test Suites" (#60) —
 * docs/standards/testing-test-suites.md — whose third bullet says integration
 * tests are "dedicated to requests that test external dependencies over the
 * internet", existing precisely as the unfaked twin of a feature test's
 * HTTP-faked coverage (#150). Whether a test's subject really is an external
 * dependency is a judgement, but one half of the bullet is plain single-file
 * token content: a test under the integration suite that doubles out the HTTP
 * client defeats the suite's whole purpose, and the doubling is token-visible.
 *
 * Two shapes are reported, each under its own code:
 *
 * - `FakedHttpClient` — a static call on an `Http` receiver whose method is
 *   one of $fakeMethods: Laravel's `Http::fake()` and `Http::fakeSequence()`
 *   install a fake responder, and `Http::preventStrayRequests()` turns the one
 *   request the suite exists to make into a failure.
 * - `MockedHttpClient` — a mock-creation call from $mockCreators whose first
 *   argument names an HTTP client from $httpClientClasses. A hand-rolled
 *   double of `GuzzleHttp\Client` or of anything under
 *   `Illuminate\Http\Client` doubles out the same dependency the facade fakes
 *   above do, without going through the facade.
 *
 * Both are matched case-insensitively, PHP class and method names being
 * case-insensitive themselves, and the receiver is compared on its trailing
 * namespace segment, so `Http`, `\Http` and
 * `Illuminate\Support\Facades\Http` all read as the facade while `Https` and
 * `ApiHttp` do not — the same arrangement
 * CleanCode.Routes.DisallowNonResourceRoutes uses for the `Route` facade.
 *
 * The mocked class is matched on what the file writes, not on a resolved
 * symbol, and the two spellings are read differently because PHP reads them
 * differently:
 *
 * - a *qualified* name — one carrying a separator, which includes every string
 *   literal, since PHP never resolves a class name given as a string through a
 *   file's imports — says exactly which class it is, so it reports only when
 *   it equals a configured client or sits beneath one.
 *   `\GuzzleHttp\Client::class`, `'GuzzleHttp\Client'` and
 *   `Illuminate\Http\Client\Factory::class` all report; `App\Support\Client`
 *   and the global `\Client` do not.
 * - an *unqualified* name is whatever the file's `use` imports bind it to,
 *   which one file's tokens do not say, so it reports when it equals a
 *   configured client's own trailing segment. `Client::class` under
 *   `use GuzzleHttp\Client;` is the shape this catches.
 *
 * Scope: the sniff inspects a file only when its path matches one of
 * $integrationPatterns (fnmatch globs, defaulting to any path holding a
 * `tests/Integration` directory pair). PHP_CodeSniffer sees one file at a time
 * and has no notion of a suite, so the filename is the only available signal,
 * and a fake outside the integration suite — where a feature test is *supposed*
 * to install one — is left alone entirely. Piped input reports its path as
 * `STDIN`, which matches no default glob, so the sniff says nothing about it.
 *
 * Warning severity, not error, matching CleanCode.Testing.NoReflectionAccess,
 * CleanCode.Testing.NoFirstPartyMocks and the rest of the Testing standards:
 * the boundaries below have known false positives and negatives, so a report
 * is a prompt to look rather than a proven defect and must not fail a
 * consumer's build. Detection only — removing a fake from an integration test
 * means either deleting coverage or moving the test to the feature suite, and
 * only the project says which, so there is no mechanical rewrite and no
 * autofixed fixture.
 *
 * Boundaries, six by design and all recorded in the standard's doc:
 *
 * - **Instance-side fakes (false negative).** `$this->http->fake()` on an
 *   injected `Illuminate\Http\Client\Factory` installs the same fake, but the
 *   receiver is a property whose type one file's tokens do not carry. Reading
 *   the property *name* would flag any object called `http`, so the fake half
 *   is confined to the static facade call and the mock half below is what
 *   catches a hand-built client double.
 * - **A fake installed elsewhere (false negative).** A fake set up in a shared
 *   base class, a trait, or a Pest `beforeEach` in another file is invisible to
 *   a single-file scan. Only the file holding the call is inspected.
 * - **Suite intent (false negative).** This catches the faking mechanism, not
 *   the suite's purpose: an "integration" test that simply never calls the
 *   external service is not flagged. Test *absence* is #128's territory.
 * - **Symbol resolution.** Like CleanCode.Routes.DisallowNonResourceRoutes,
 *   the sniff cannot resolve which symbol an import actually binds, so an
 *   unrelated class named `Client` reports and an aliased import
 *   (`use GuzzleHttp\Client as Transport;`) does not. The qualified/unqualified
 *   split above is what keeps this to the one ambiguous spelling instead of
 *   every name ending in a watched segment.
 * - **Imported clients under a watched namespace (false negative).**
 *   `use Illuminate\Http\Client\Factory;` then `mock(Factory::class)` writes an
 *   unqualified name whose trailing segment is `Factory`, not `Client`, so it
 *   is not reported. Resolving it needs the file's imports, which
 *   CleanCode.Testing.NoFirstPartyMocks carries for its own rule and this one
 *   deliberately does not duplicate.
 * - **Creator receivers (false positive).** $mockCreators matches on member
 *   *name*, not on receiver type, which a single-file scan cannot resolve, so
 *   `$this->mock(Client::class)` and `$container->mock(Client::class)` are
 *   indistinguishable here — the same limit NoFirstPartyMocks records.
 *
 * Fixtured in tests/fixtures/NoHttpFakesInIntegrationTestsSniff/ and covered by
 * tests/Standards/NoHttpFakesInIntegrationTestsTest.php. Those fixtures cannot
 * sit where the generic contract sweep drives them, because tests/fixtures/
 * holds no `tests/Integration` pair and so matches no default glob; the sniff
 * is held out of that sweep and its own test stages the fixtures under a real
 * `tests/Integration` directory instead.
 */
class NoHttpFakesInIntegrationTestsSniff implements Sniff
{
    /**
     * The trailing receiver segment that marks Laravel's HTTP-client facade,
     * lowercased for comparison. PHP class names are case-insensitive.
     */
    private const HTTP_FACADE = 'http';

    /**
     * Every token a receiver's or an argument's class name can arrive as.
     *
     * Only T_STRING and T_NS_SEPARATOR are reachable today: PHP_CodeSniffer
     * 3.x deliberately "undoes" PHP 8's single qualified-name tokens back to
     * the pre-8.0 spelling (Tokenizers/PHP.php, the PHP_VERSION_ID >= 80000
     * branch), so `Illuminate\Http\Client\Factory` arrives as an alternating
     * run rather than as one token. The three T_NAME_* codes are defensive,
     * against a future release that stops undoing it. PHP's floor here is 8.1,
     * so all three constants are defined.
     */
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    /**
     * Path globs (fnmatch syntax) that mark a file as an integration test. The
     * sniff inspects nothing outside them. Configurable from a ruleset via
     * <property name="integrationPatterns" type="array" .../>.
     *
     * @var array<string>
     */
    public array $integrationPatterns = [
        '*/tests/Integration/*',
    ];

    /**
     * The static `Http` facade methods that install a fake or forbid the real
     * request. Configurable via
     * <property name="fakeMethods" type="array" .../>.
     *
     * @var array<string>
     */
    public array $fakeMethods = [
        'fake',
        'fakeSequence',
        'preventStrayRequests',
    ];

    /**
     * Member names whose call creates a mock or a stub — PHPUnit's
     * `createMock()`, and the `mock()` of both `Mockery::mock()` and Laravel's
     * `$this->mock()` test helper. Configurable via
     * <property name="mockCreators" type="array" .../>.
     *
     * @var array<string>
     */
    public array $mockCreators = [
        'createMock',
        'mock',
    ];

    /**
     * The HTTP-client classes and namespace roots a mock of which doubles out
     * the external dependency. A qualified argument reports when it equals one
     * of these or sits beneath it; an unqualified one when it equals one's
     * trailing segment. Configurable via
     * <property name="httpClientClasses" type="array" .../>.
     *
     * @var array<string>
     */
    public array $httpClientClasses = [
        'GuzzleHttp\Client',
        'Illuminate\Http\Client',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_DOUBLE_COLON,
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isIntegrationTest($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($memberPtr === false || $tokens[$memberPtr]['code'] !== T_STRING) {
            return;
        }

        $openPtr = $this->callOpener($phpcsFile, $memberPtr);

        if ($openPtr === null) {
            return;
        }

        $member = $tokens[$memberPtr]['content'];

        if ($this->isHttpFake($phpcsFile, $stackPtr, $member) === true) {
            $phpcsFile->addWarning(
                'Http::%s() doubles out the external dependency this integration test exists to'
                    . ' exercise; keep the fake in the feature-test twin instead'
                    . ' (see docs/standards/testing-test-suites.md)',
                $memberPtr,
                'FakedHttpClient',
                [$member]
            );

            return;
        }

        if ($this->matches($member, $this->mockCreators) === false) {
            return;
        }

        $mocked = $this->mockedHttpClient($phpcsFile, $openPtr);

        if ($mocked === null) {
            return;
        }

        $phpcsFile->addWarning(
            'Mocking %s doubles out the external dependency this integration test exists to'
                . ' exercise; keep the double in the feature-test twin instead'
                . ' (see docs/standards/testing-test-suites.md)',
            $memberPtr,
            'MockedHttpClient',
            [$mocked]
        );
    }

    /**
     * Whether the file's path matches one of the configured integration-test
     * globs.
     *
     * Windows separators are normalised to forward slashes on both sides, so
     * one glob spelling matches on either platform. The match is
     * case-sensitive: the directory pair the standard names is
     * `tests/Integration`, and folding case buys nothing a project cannot get
     * by adding its own glob.
     */
    private function isIntegrationTest(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->integrationPatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The pointer to the parenthesis opening the call to the member at
     * $memberPtr, or null when the member is not called at all.
     *
     * Two things separate a call from a name that merely reads like one: an
     * open parenthesis has to follow, so a class-constant read (`Http::FAKE`)
     * and a property read (`$builder->mock`) are not mistaken for one; and the
     * argument list must not be PHP 8.1's first-class-callable `...`, which
     * builds a Closure and installs nothing. The ellipsis counts only when the
     * parenthesis closes straight after it, so an argument spread
     * (`$this->mock(...$arguments)`) stays a real call — the same guard
     * CleanCode.Routes.DisallowNonResourceRoutes carries, in the same shape.
     */
    private function callOpener(File $phpcsFile, int $memberPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if ($openPtr === false || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS) {
            return null;
        }

        $ellipsisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        if ($ellipsisPtr === false || $tokens[$ellipsisPtr]['code'] !== T_ELLIPSIS) {
            return $openPtr;
        }

        $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsisPtr + 1), null, true);
        $isCallable = $afterPtr !== false && $tokens[$afterPtr]['code'] === T_CLOSE_PARENTHESIS;

        return $isCallable === true ? null : $openPtr;
    }

    /**
     * Whether the call reached through the operator at $stackPtr installs an
     * HTTP fake: a watched method, called statically on the `Http` facade.
     *
     * The receiver is only meaningful behind `::`. `$responses->fake()` is a
     * method on some object the file never names, so the operator check is
     * what keeps it out rather than the receiver comparison, which has no name
     * token to read there at all.
     *
     * The NAME_TOKENS check is a type guard rather than a behavioural branch,
     * and is unpinnable for the reason CleanCode.Routes.DisallowNonResourceRoutes
     * records about its own: no token that can sit before `::` and is *not* a
     * name carries content a trailing-segment comparison could match — a
     * variable keeps its `$`, and `self`, `static` and `parent` spell
     * themselves. Deleting it changes no result, verified by mutation, and it
     * is stated here rather than left to imply coverage no fixture can give it.
     */
    private function isHttpFake(File $phpcsFile, int $stackPtr, string $member): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] !== T_DOUBLE_COLON) {
            return false;
        }

        if ($this->matches($member, $this->fakeMethods) === false) {
            return false;
        }

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($receiverPtr === false) {
            return false;
        }

        if (in_array($tokens[$receiverPtr]['code'], self::NAME_TOKENS, true) === false) {
            return false;
        }

        return $this->trailingSegment($tokens[$receiverPtr]['content']) === self::HTTP_FACADE;
    }

    /**
     * The HTTP-client name the call opening at $openPtr mocks, as the source
     * spells it, or null when its first argument does not name one.
     */
    private function mockedHttpClient(File $phpcsFile, int $openPtr): ?string
    {
        // An empty argument list needs no guard of its own: the token found
        // here is then the closing parenthesis, which is neither a string
        // literal nor the start of a name, so classReference() rejects it.
        $argumentPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), null, true);

        if ($argumentPtr === false) {
            return null;
        }

        $written = $this->classReference($phpcsFile, $argumentPtr);

        if ($written === null) {
            return null;
        }

        return $this->isHttpClient($written) === true ? $written : null;
    }

    /**
     * The class reference written as the first argument at $argumentPtr, as
     * source text, or null when the argument is not a class reference at all.
     *
     * The name is read as a *run* of tokens rather than as one, because
     * PHP_CodeSniffer 3.x backfills PHP 8's single qualified-name tokens into
     * the pre-8.0 spelling — `\GuzzleHttp\Client` arrives as T_NS_SEPARATOR and
     * T_STRING alternating. Reading one token there yields `\`.
     *
     * Whatever the argument is, it has to *be* the whole argument: the token
     * after it must close the call or separate the next argument. Without that
     * check `$this->mock(Client::class . $suffix)` and `$this->mock(CLIENT)` —
     * a concatenation and a constant, neither of them a class reference —
     * would read as one. A named argument
     * (`$this->createMock(originalClassName: Client::class)`) falls out the
     * same way: the label is not a class reference, and the `:` after it is not
     * what ends an argument.
     */
    private function classReference(File $phpcsFile, int $argumentPtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$argumentPtr]['code'] === T_CONSTANT_ENCAPSED_STRING) {
            // PHP never resolves a class name given as a string through the
            // file's imports, so the literal is already fully qualified
            // whatever it is written as. The leading separator is what says so
            // to isHttpClient().
            $written = '\\' . ltrim($this->literalValue($tokens[$argumentPtr]['content']), '\\');

            return $this->endsTheArgument($phpcsFile, ($argumentPtr + 1)) === true ? $written : null;
        }

        // An argument carrying no name at all needs no guard of its own: the
        // run is then empty and leaves the pointer where it started, and the
        // token there — a variable, an ellipsis, `self`, the closing
        // parenthesis — is not `::`, so classConstantEnd() rejects it.
        [$written, $pointer] = $this->nameRun($tokens, $argumentPtr);
        $pointer = $this->classConstantEnd($phpcsFile, $pointer);

        if ($pointer === null) {
            return null;
        }

        return $this->endsTheArgument($phpcsFile, $pointer) === true ? $written : null;
    }

    /**
     * The run of name tokens starting at $pointer as source text, paired with
     * the pointer just past it. Empty text when the first token carries no
     * name.
     *
     * @param array<int, array<string, mixed>> $tokens
     *
     * @return array{0: string, 1: int}
     */
    private function nameRun(array $tokens, int $pointer): array
    {
        $written = '';

        for (; isset($tokens[$pointer]) === true; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return [$written, $pointer];
    }

    /**
     * The pointer just past a `::class` suffix following the name that ends at
     * $pointer, or null when there is none.
     *
     * The suffix is required rather than optional: without it the argument is
     * a constant fetch, not a class reference, so `$this->mock(CLIENT)`
     * names a constant that merely reads like a class and
     * `$this->mock(Client)` is a runtime error either way. Demanding `::class`
     * is what keeps both out.
     *
     * PHP_CodeSniffer tokenises the `class` in `Client::class` as T_STRING,
     * not T_CLASS, so the content is what identifies it. PHP's own lexer
     * accepts any casing there, and `Client::DEFAULT` names a constant on the
     * class rather than the class itself.
     */
    private function classConstantEnd(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $doubleColonPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if ($doubleColonPtr === false || $tokens[$doubleColonPtr]['code'] !== T_DOUBLE_COLON) {
            return null;
        }

        $constantPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($doubleColonPtr + 1), null, true);

        if ($constantPtr === false || strtolower($tokens[$constantPtr]['content']) !== 'class') {
            return null;
        }

        return ($constantPtr + 1);
    }

    /**
     * Whether the next meaningful token from $pointer ends the first argument,
     * either by closing the call or by separating the argument that follows.
     */
    private function endsTheArgument(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundaryPtr = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer, null, true);

        if ($boundaryPtr === false) {
            return false;
        }

        return in_array($tokens[$boundaryPtr]['code'], [T_COMMA, T_CLOSE_PARENTHESIS], true);
    }

    /**
     * The class name a string literal spells. Both quote styles escape the
     * separator the same way — PHP's own lexer collapses `\\` to one backslash
     * in a single-quoted literal just as it does in a double-quoted one — so
     * `'GuzzleHttp\\Client'` and `"GuzzleHttp\\Client"` both name
     * `GuzzleHttp\Client`. A single separator is already what it says and comes
     * through untouched.
     */
    private function literalValue(string $content): string
    {
        return str_replace('\\\\', '\\', trim($content, '\'"'));
    }

    /**
     * Whether $written names one of the configured HTTP clients.
     *
     * A qualified name — one carrying a separator, which every string literal
     * is made into above — says which class it is, so it has to equal a
     * configured client or sit beneath it, compared segment-wise so
     * `GuzzleHttp\ClientFactory` never matches `GuzzleHttp\Client`. An
     * unqualified name is whatever the file's imports bind it to, which this
     * file's tokens do not say, so its one segment is compared against each
     * configured client's own trailing segment instead.
     *
     * Both comparisons fold case, PHP class names being case-insensitive.
     */
    private function isHttpClient(string $written): bool
    {
        $segments = array_map('strtolower', explode('\\', ltrim($written, '\\')));
        $isQualified = str_contains($written, '\\');

        foreach ($this->httpClientClasses as $client) {
            $clientSegments = array_map('strtolower', explode('\\', ltrim($client, '\\')));

            if ($isQualified === false) {
                if ($segments === [(string) end($clientSegments)]) {
                    return true;
                }

                continue;
            }

            if (array_slice($segments, 0, count($clientSegments)) === $clientSegments) {
                return true;
            }
        }

        return false;
    }

    /**
     * The last segment of a possibly-qualified name, lowercased: `\Http` and
     * `Illuminate\Support\Facades\Http` both yield `http`.
     */
    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', $name);

        return strtolower((string) end($segments));
    }

    /**
     * Case-insensitive membership, because PHP method names are themselves
     * case-insensitive.
     *
     * @param array<string> $candidates
     */
    private function matches(string $name, array $candidates): bool
    {
        return in_array(strtolower($name), array_map('strtolower', $candidates), true);
    }
}
