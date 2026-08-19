<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
use MikeBronner\CleanCode\Support\StringLiteral;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use SlevomatCodingStandard\Helpers\NamespaceHelper;

/**
 * Warns on a raw internet-traversing primitive written inside a feature test
 * (Testing: Test Suites, #60, partial enforcement per #149) —
 * docs/standards/testing-test-suites.md.
 *
 * The standard says a feature test uses more than internal methods — database,
 * other classes, HTTP, WebSockets — but does **not** traverse the internet, and
 * that third-party APIs are exercised here through HTTP fakes instead. Whether
 * a request really leaves the machine is a runtime property no token scan can
 * settle, so the standard's core stays with code review. What *is* token-visible
 * is the raw mechanism: the primitives that can only traverse the internet are
 * spelled out in the file that calls them, and their presence in a feature test
 * is the signal (#149).
 *
 * This is the second slice of #60 to be enforced, and it deliberately does not
 * overlap the first. CleanCode.Testing.TestSuiteNamespace reads a test's
 * *declarations* — its namespace against its directory — and no file content at
 * all; this sniff reads only content, and takes the directory as given. The
 * sibling slices for the other two suites stay separate (#148, #150).
 *
 * ## What is flagged
 *
 * Three shapes, each reported under the one Found code with the primitive named
 * in the message:
 *
 * - a call to one of NETWORK_FUNCTIONS — `curl_init()`, `curl_exec()`,
 *   `fsockopen()` and `stream_socket_client()`. Each one exists to open a
 *   connection, so the call alone is the violation and no argument is read.
 * - a call to `file_get_contents()` whose first argument is a string literal
 *   whose text begins `http://` or `https://`. The function itself is ordinary,
 *   so here the URL is what makes it a network read.
 * - a `new` of a class resolving to `GuzzleHttp\Client`. The sanctioned route to
 *   a faked third-party API is Laravel's `Http` facade with `Http::fake()`;
 *   building the underlying Guzzle client by hand steps around it.
 *
 * The function names are matched case-insensitively, PHP function names being
 * case-insensitive, and only when FunctionCalls::isGlobalFunctionCall() agrees
 * the name is a real call to PHP's own function — a same-named method,
 * declaration, attribute or imported symbol is not one. The class name is
 * resolved through the file's own namespace and `use` imports, so
 * `new Client()` under `use GuzzleHttp\Client;` reports and an unrelated
 * `Client` from any other namespace does not.
 *
 * ## Scope
 *
 * The sniff inspects a file only when its path matches one of
 * $featureTestPatterns — fnmatch globs defaulting to any path holding a
 * `tests/Feature` directory pair. PHP_CodeSniffer sees one file at a time and
 * has no notion of "a feature test", so the path is the only available signal,
 * and the very same primitive in an integration test — where the standard says
 * it belongs — is left alone entirely. Two consequences worth knowing, both
 * shared with CleanCode.Routes.DisallowNonResourceRoutes: piped input reports
 * the path as `STDIN` and matches no default glob, so nothing is said about it;
 * and the glob matches the pair anywhere in the absolute path PHP_CodeSniffer
 * hands over, so a checkout living under `tests/Feature` widens the gate to the
 * whole project. A project with a different suite layout retunes the property.
 *
 * ## Boundaries, all seven also recorded in the standard's doc
 *
 * - **A request through an un-faked `Http` facade call (false negative).**
 *   `Http::get('https://…')` is the sanctioned API *and* a real request when no
 *   fake is active, and whether a fake is active is not statically decidable —
 *   it is set up in a different statement, often a different file. Only the raw
 *   primitives are read.
 * - **A request through a service class (false negative).** A feature test
 *   calling application code that itself opens a socket carries no primitive of
 *   its own. That needs project-wide symbol resolution and stays with code
 *   review.
 * - **A variable URL (false negative).** `file_get_contents($url)` says nothing
 *   about where `$url` points. So does a concatenation — `'https://…' . $path`
 *   is not a string literal, and the argument is required to be one whole
 *   literal rather than the head of an expression, which is what keeps the rule
 *   to facts about the file.
 * - **A URL whose scheme is spelled with escape sequences (false negative).**
 *   The prefix is read off the literal's own characters, so a double-quoted
 *   `"\x68ttp://…"` is not recognised. The two idiomatic spellings do agree:
 *   `'http://…'` and `"http://…"` hold identical characters between their
 *   delimiters, so neither is silently treated differently from the other.
 * - **A URL split across physical lines (false negative).** PHP_CodeSniffer
 *   tokenizes a literal containing a newline into one token per line, and only
 *   a whole literal is read.
 * - **A local transport through a socket primitive (false positive, kept).**
 *   `fsockopen()` and `stream_socket_client()` also address a `unix://` or
 *   `udg://` socket, which never leaves the machine, and both are reported all
 *   the same. The argument is not read because the standard's own wording, and
 *   the acceptance criteria taken from it, name these two calls outright — and a
 *   raw socket opened by hand is not what a feature test should hold whichever
 *   transport it names.
 * - **Another HTTP client (false negative).** NETWORK_CLIENTS names the one
 *   client the standard's own wording is about, and it is a constant rather than
 *   a property: the standard names Guzzle, and a list a consumer could retune
 *   would make the rule mean something different in each project. A project
 *   reaching the internet through a different library is outside this slice.
 *   $featureTestPatterns is the only configurable part, because a suite's
 *   *location* is a project's own convention while the primitives are not.
 *
 * Warnings, not errors, matching CleanCode.Testing.TestSuiteNamespace and the
 * rest of the Testing standards. The rule reads a project's suite layout off a
 * configurable convention and cannot prove it guessed right, and a primitive in
 * a feature test is a strong signal rather than a proof that a request leaves
 * the machine — so a misread must not fail a consumer's build.
 *
 * Detection only, and there is no autofixed.php fixture. Replacing a real
 * request with a fake means writing the fake — what to return, and for which
 * URLs — which is not recoverable from the call being replaced.
 *
 * Fixtured in tests/fixtures/NoInternetTraversalSniff/ and covered by
 * tests/Standards/NoInternetTraversalTest.php. Those fixtures cannot sit where
 * the generic contract sweep drives them, because tests/fixtures/ holds no
 * `tests/Feature` pair and so matches no default glob; the sniff is held out of
 * that sweep and its own test stages the fixtures under a real feature-suite
 * directory instead.
 */
class NoInternetTraversalSniff implements Sniff
{
    /**
     * Every token a class name can be made of, in either tokenisation: the
     * pre-8.0 spelling PHP_CodeSniffer backfills to (T_STRING joined by
     * T_NS_SEPARATOR, with T_NAMESPACE leading a relative name) and PHP 8's
     * single qualified-name tokens, in case a future PHPCS stops backfilling.
     * The family is every token `token_name()` reports for a name segment or a
     * name joiner; PHP's floor here is 8.1, so all three T_NAME_* constants are
     * defined. Taken whole from CleanCode.Testing.NoFirstPartyMocks, which
     * reads a written class name out of the token stream for the same reason.
     *
     * Only T_STRING and T_NS_SEPARATOR are reachable under this PHP_CodeSniffer,
     * and the sniff's test says so outright rather than implying coverage of the
     * rest, as CleanCode.Routes.DisallowNonResourceRoutes does for the same
     * split. The other four are inert here, in both directions: T_NAMESPACE
     * leads a `namespace\Client` relative name that resolves to this client only
     * inside the client's own namespace, and the three T_NAME_* codes cannot
     * arrive at all while PHPCS 3.x undoes PHP 8's qualified-name tokens back to
     * the pre-8.0 spelling. They are carried so a release that stops undoing it
     * finds the run already reading a whole name instead of half of one.
     *
     * @var array<int, int|string>
     */
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAMESPACE,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    /**
     * The tokens a string literal arrives as, which is the whole of
     * PHP_CodeSniffer's own Tokens::$stringTokens grouping — both members
     * included, neither excluded. T_CONSTANT_ENCAPSED_STRING covers a
     * single-quoted literal and a double-quoted one holding no interpolation;
     * T_DOUBLE_QUOTED_STRING covers an interpolated one, whose leading
     * characters are still literal text and so still name a scheme. The
     * constant is spelled out rather than read from Tokens at run time so this
     * accounting is what the sniff is measured against: a PHPCS release adding
     * a third member reddens `accounts for every string token PHPCS defines` in
     * tests/Standards/NoInternetTraversalTest.php instead of slipping past.
     *
     * @var array<int, int|string>
     */
    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
    ];

    /**
     * Functions that exist to open a connection, lowercased for comparison. The
     * call alone is the violation: unlike URL_READER below, none of them has an
     * ordinary local use that an argument could put it to. The two socket calls
     * can name a `unix://` transport that stays on the machine, and are reported
     * anyway — see the boundary of that name in the class docblock.
     *
     * @var array<int, string>
     */
    private const NETWORK_FUNCTIONS = [
        'curl_exec',
        'curl_init',
        'fsockopen',
        'stream_socket_client',
    ];

    /**
     * The function that traverses the internet only for some arguments,
     * lowercased for comparison. Reading a local path with it is ordinary, so
     * the URL decides.
     */
    private const URL_READER = 'file_get_contents';

    /**
     * The URL schemes that name a request leaving the machine, lowercased for
     * comparison. PHP resolves a stream wrapper's scheme case-insensitively, so
     * `HTTPS://` reaches the same wrapper as `https://` and is read the same
     * way here.
     *
     * @var array<int, string>
     */
    private const NETWORK_SCHEMES = [
        'http://',
        'https://',
    ];

    /**
     * The fully-qualified HTTP clients whose direct instantiation traverses the
     * internet, lowercased and without a leading separator for comparison. PHP
     * class names are case-insensitive.
     *
     * @var array<int, string>
     */
    private const NETWORK_CLIENTS = [
        'guzzlehttp\client',
    ];

    /**
     * Path globs (fnmatch syntax) that mark a file as a feature test. The sniff
     * inspects nothing outside them. Configurable from a ruleset via
     * <property name="featureTestPatterns" type="array" .../>.
     *
     * @var array<string>
     */
    public array $featureTestPatterns = [
        '*/tests/Feature/*',
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_NEW,
            T_STRING,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isFeatureTest($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_NEW) {
            $this->processInstantiation($phpcsFile, $stackPtr);

            return;
        }

        $this->processCall($phpcsFile, $stackPtr);
    }

    /**
     * Whether the file's path matches one of the configured feature-test globs.
     *
     * Windows separators are normalised to forward slashes first, so one glob
     * spelling matches on either platform. The match itself is case-sensitive,
     * for the reason CleanCode.Testing.NoFirstPartyMocks records: folding case
     * would make a directory glob swallow unrelated spellings, so the
     * directory spelling actually in use is listed out in $featureTestPatterns
     * instead.
     */
    private function isFeatureTest(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->featureTestPatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports the T_STRING at $stackPtr when it is a real call to one of the
     * watched global functions.
     */
    private function processCall(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $name = strtolower($tokens[$stackPtr]['content']);
        $isNetworkFunction = in_array($name, self::NETWORK_FUNCTIONS, true);

        if ($isNetworkFunction === false && $name !== self::URL_READER) {
            return;
        }

        if (FunctionCalls::isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($isNetworkFunction === false) {
            if ($this->readsNetworkUrl($phpcsFile, $stackPtr) === false) {
                return;
            }
        }

        $this->report($phpcsFile, $stackPtr, $tokens[$stackPtr]['content'] . '()');
    }

    /**
     * Whether the call opening after $stackPtr reads a URL naming one of the
     * network schemes.
     *
     * Two things have to hold, and each rules out a shape the file does not
     * state the value of:
     *
     * - the argument opens with a string literal, so `$base . '…'` is out on
     *   its first token;
     * - nothing follows it inside the argument: the next token closes the call
     *   or starts the next argument. That is what keeps a concatenation out —
     *   the head of `'https://…' . $path` is a whole literal, but the argument
     *   is an expression.
     *
     * The token-type check between them is a precondition rather than a
     * discriminator, and this says so outright rather than implying coverage the
     * fixtures do not have: no fixture can redden when it is removed, because
     * the scheme test downstream already rejects everything a non-literal
     * argument could produce — a token outside Tokens::$stringTokens that stands
     * alone as a whole argument cannot carry `http://` in the text inner()
     * returns. It stays because inner() is documented as taking a complete
     * literal, and the token type is what says this is one. Its membership is
     * pinned instead, against PHPCS's own register, by `accounts for every
     * string token PHPCS defines`.
     *
     * The second check is also what establishes StringLiteral::inner()'s stated
     * precondition, which is why no separate isComplete() call stands here: one
     * would be a branch nothing could ever take. PHP_CodeSniffer tokenizes a
     * literal holding a newline one token per physical line, so a fragment of
     * one always has a sibling fragment after it, and a sibling fragment is
     * neither the closing parenthesis nor a comma. Reaching inner() therefore
     * means the argument was a single token, and a single-token literal is a
     * whole one.
     */
    private function readsNetworkUrl(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($openPtr === false || isset($tokens[$openPtr]['parenthesis_closer']) === false) {
            return false;
        }

        $closePtr = $tokens[$openPtr]['parenthesis_closer'];
        $urlPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openPtr + 1), $closePtr, true);

        if ($urlPtr === false) {
            return false;
        }

        if (in_array($tokens[$urlPtr]['code'], self::STRING_TOKENS, true) === false) {
            return false;
        }

        $afterPtr = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            ($urlPtr + 1),
            ($closePtr + 1),
            true
        );

        $endsArgument = $afterPtr === $closePtr
            || ($afterPtr !== false && $tokens[$afterPtr]['code'] === T_COMMA);

        if ($endsArgument === false) {
            return false;
        }

        return $this->namesNetworkScheme(StringLiteral::inner($tokens[$urlPtr]['content']));
    }

    /**
     * Whether a literal's own text opens with one of the network schemes.
     */
    private function namesNetworkScheme(string $url): bool
    {
        $lowered = strtolower($url);

        foreach (self::NETWORK_SCHEMES as $scheme) {
            if (str_starts_with($lowered, $scheme) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports the `new` at $stackPtr when the class it names resolves to one of
     * the watched HTTP clients.
     */
    private function processInstantiation(File $phpcsFile, int $stackPtr): void
    {
        $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($namePtr === false) {
            return;
        }

        $written = $this->writtenName($phpcsFile, $namePtr);

        if ($written === null) {
            return;
        }

        $resolved = ltrim(NamespaceHelper::resolveClassName($phpcsFile, $written, $namePtr), '\\');

        if (in_array(strtolower($resolved), self::NETWORK_CLIENTS, true) === false) {
            return;
        }

        $this->report($phpcsFile, $namePtr, 'new ' . $resolved);
    }

    /**
     * The class name written from $namePtr onwards, or null when what stands
     * there is not a written name at all.
     *
     * Null is the answer for every dynamic or anonymous shape — `new $class`,
     * `new ($factory())`, `new class {}` — because none of them writes a name
     * the file can be read for. The token run is taken whole so a qualified
     * name arrives in one piece under either tokenisation.
     */
    private function writtenName(File $phpcsFile, int $namePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();
        $written = '';

        for ($pointer = $namePtr; $pointer < $phpcsFile->numTokens; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return $written === '' ? null : $written;
    }

    /**
     * The one warning this sniff reports, named for the primitive that matched.
     */
    private function report(File $phpcsFile, int $stackPtr, string $primitive): void
    {
        $phpcsFile->addWarning(
            '%s traverses the internet; a feature test must not, so fake the third-party API'
                . ' through the Http facade instead (see docs/standards/testing-test-suites.md)',
            $stackPtr,
            'Found',
            [$primitive]
        );
    }
}
