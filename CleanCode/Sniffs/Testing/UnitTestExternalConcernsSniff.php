<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags the external concerns a unit test must not carry (Testing: Test Suites,
 * #60, content-vs-directory slice #148) — docs/standards/testing-test-suites.md.
 *
 * The standard asks that a unit test concern only the class under test. Whether
 * a test's subject really is that one class is a judgement about its scope and
 * its runtime behaviour, and stays with code review — but three of the ways a
 * Laravel test reaches past its subject leave plain token traces in the test
 * file itself, and those are facts:
 *
 *   - an Illuminate testing trait that stands the database up (`DatabaseTrait`);
 *   - a facade fake, which doubles out a whole external subsystem (`FacadeFake`);
 *   - an HTTP-kernel request call on the test case, which boots the application
 *     and dispatches through the router (`HttpRequest`).
 *
 * Each one says the test exercises the database, the HTTP layer, or another
 * external concern, which is what tests/Feature/ is for. The sniff reports the
 * trace, never the intent.
 *
 * ## Scope, and where the path is anchored
 *
 * Only files under $unitTestPath are read at all. PHP_CodeSniffer sees one file
 * at a time and has no notion of "a unit test", so the path is the only signal
 * available, and the same tokens outside that directory are left alone
 * entirely — a feature test is *supposed* to carry them.
 *
 * The path is matched as whole segments, not as a substring, and anchored the
 * way CleanCode.Routes.ApiControllerNamespace and
 * CleanCode.Testing.TestSuiteNamespace anchor theirs, for the same reason:
 * PHP_CodeSniffer hands a sniff a fully resolved *absolute* path, so every
 * directory above the project is fair game and this package is distributed for
 * other repositories to require, which puts that location outside its control.
 *
 * The first segment of $unitTestPath is the root and the rest is the tail. The
 * root taken is the one *closest to the file*, and the tail must follow it
 * immediately:
 *
 *   - /srv/tests/my-app/tests/Unit/CalculatorTest.php is in scope — the
 *     application's own test root governs, not the server layout it is checked
 *     out under.
 *   - /srv/tests/Unit/my-app/tests/Feature/ReportTest.php is not. Anchor on the
 *     first root instead and an ancestor directory pulls a feature test into
 *     the unit suite, which is the case this anchoring exists for.
 *   - tests/UnitOfWork/ is not, because segments are compared whole. `unit`
 *     is, because they are compared case-insensitively.
 *
 * Backslashes are folded to forward slashes first, so a Windows-style path
 * segments the same way.
 *
 * ## What is never flagged
 *
 * - Any file outside $unitTestPath, whatever it contains.
 * - Piped input, which PHP_CodeSniffer names STDIN. It carries no directory for
 *   the rule to read.
 * - A file scoped by an *empty* $unitTestPath. A blank property names no
 *   directory, and the alternative reading — "match everything" — would turn a
 *   misconfiguration into a warning on every file in the project. Silence is
 *   the conservative answer, and the one a consumer can recover from.
 * - A closure's `use (...)` capture list, which shares the T_USE keyword with
 *   the imports and trait uses this sniff reads.
 * - A line carrying a `phpcs:ignore` or `@codingStandardsIgnoreLine` comment,
 *   handled by PHP_CodeSniffer itself and deliberately relied on rather than
 *   reimplemented. $this->get(...) can be an ordinary userland method on a
 *   project's own test case, so a per-line escape hatch is part of the rule
 *   rather than a workaround for it.
 *
 * ## Boundaries
 *
 * - **Intent is not read.** A unit test that couples to a live dependency
 *   through plain constructor injection carries none of these traces and is
 *   never flagged. This sniff catches the common Laravel mechanisms, not the
 *   concern itself.
 * - **Names are matched, not resolved.** A project class of its own called
 *   `Http` or a trait of its own called `RefreshDatabase` reads as the
 *   Illuminate one, and an aliased import (`use RefreshDatabase as Refresh;`)
 *   does not. Symbol resolution is cross-file and outside a single file's token
 *   stream — the same boundary CleanCode.Routes.DisallowNonResourceRoutes and
 *   CleanCode.Models.DisallowAlwaysOnEagerLoading already accept.
 * - **The HTTP-kernel list is derived from #148's own enumeration**, in both
 *   spellings of each verb it names — see HTTP_KERNEL_METHODS. Laravel is not a
 *   dependency of this package, so the wider `MakesHttpRequests` surface
 *   (`options`, `head`, `call`, `json`) cannot be accounted for against its
 *   source from here and is deliberately left out rather than guessed at.
 * - **Only `fake` is read on a facade**, not `fakeSequence` and not the
 *   assertion methods that follow a fake. `Http::fake(` is the call that
 *   installs the double; `Http::fakeSequence(` inside tests/Integration/ is
 *   #150's subject, and an `assertSent` only ever follows a `fake` that this
 *   sniff has already reported.
 *
 * Warnings, not errors, matching CleanCode.Testing.TestSuiteNamespace,
 * RequireTestFile and NoReflectionAccess. The rule reads a project's layout off
 * a configurable convention and matches names it cannot resolve, so a misread
 * must not fail a consumer's build.
 *
 * Detection only, and there is no autofixed.php fixture. The fix is moving the
 * test into tests/Feature/, or rewriting it to stop reaching outside its
 * subject; neither is a mechanical rewrite of the tokens on the line.
 *
 * Fixtured in tests/fixtures/UnitTestExternalConcernsSniff/ and covered by
 * tests/Standards/UnitTestExternalConcernsTest.php. The contract's passing.php
 * and failing.php have a fixed *location* as well as a fixed name, and that
 * location carries no `Unit` segment, so the nested trees under that directory
 * are what exercise the shipped scope. The sniff is held out of the generic
 * contract sweep for the same reason — see tests/Sniffs.php.
 */
class UnitTestExternalConcernsSniff implements Sniff
{
    /**
     * The path PHP_CodeSniffer reports when it lints piped input with no
     * --stdin-path. There is no directory for the scope to read.
     */
    private const UNKNOWN_PATH = 'STDIN';

    /**
     * The Illuminate testing traits that stand a database up for a test. Named
     * by #148, and each one is a documented `Illuminate\Foundation\Testing`
     * trait whose whole purpose is to prepare the database — which is the
     * external concern, whichever of the four a test reaches for.
     *
     * Compared against the trailing segment of a used name, case-insensitively,
     * PHP class names being case-insensitive.
     */
    private const DATABASE_TRAITS = [
        'databasemigrations',
        'databasetransactions',
        'lazilyrefreshdatabase',
        'refreshdatabase',
    ];

    /**
     * The facades whose `fake()` doubles out an external subsystem. The closed
     * list #148 names, one per subsystem: outbound HTTP, the event dispatcher,
     * the queue, the command bus, the filesystem, notifications, and mail.
     *
     * Compared against the trailing segment of the receiver, case-insensitively.
     */
    private const FAKEABLE_FACADES = [
        'bus',
        'event',
        'http',
        'mail',
        'notification',
        'queue',
        'storage',
    ];

    /**
     * The method that installs a facade double. See the class docblock for why
     * `fakeSequence` and the post-fake assertions are not here.
     */
    private const FAKE_METHOD = 'fake';

    /**
     * The HTTP-kernel request methods on the test case. The family is #148's
     * own enumeration — get, getJson, post, postJson, put, patch, delete —
     * closed under the spelling rule that enumeration itself states: it names
     * both `get`/`getJson` and `post`/`postJson`, so each verb is taken in its
     * plain and its `Json` form, which supplies putJson, patchJson and
     * deleteJson.
     *
     * That derivation is the whole provenance, and it is stated rather than
     * widened because it can be checked from inside this package. Laravel is
     * not a dependency here, so `Illuminate\Foundation\Testing\Concerns\
     * MakesHttpRequests` — the canonical source the family really answers to —
     * cannot be enumerated against from this repository. Its remaining request
     * methods (`options`, `optionsJson`, `head`, `call`, `json`) are therefore
     * excluded rather than guessed at, and recorded as a known false negative
     * in the class docblock and the standard's doc.
     *
     * Compared case-insensitively, PHP method names being case-insensitive.
     */
    private const HTTP_KERNEL_METHODS = [
        'delete',
        'deletejson',
        'get',
        'getjson',
        'patch',
        'patchjson',
        'post',
        'postjson',
        'put',
        'putjson',
    ];

    /**
     * The object-access operators a call on the test case can arrive through.
     * Both members of PHP's dereference-operator pair — `->` and its nullsafe
     * spelling `?->`. PHP_CodeSniffer tokenizes them as two distinct types and
     * groups no `Tokens::$…` array around just these two, so the pair is stated
     * here with that provenance: every operator that can sit between `$this`
     * and a method name.
     *
     * @var array<int, int|string>
     */
    private const OBJECT_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
    ];

    /**
     * The directory a unit test lives in, read as whole path segments. The
     * first segment is the root the match is anchored on and the rest must
     * follow it immediately; see the class docblock for why the root closest to
     * the file is the one that governs. Configurable via
     * <property name="unitTestPath" value="..."/>.
     */
    public string $unitTestPath = 'tests/Unit/';

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_USE,
            T_DOUBLE_COLON,
            T_VARIABLE,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($this->isUnitTestFile($phpcsFile) === false) {
            return;
        }

        $code = $phpcsFile->getTokens()[$stackPtr]['code'];

        if ($code === T_USE) {
            $this->processUse($phpcsFile, $stackPtr);

            return;
        }

        if ($code === T_DOUBLE_COLON) {
            $this->processFacadeFake($phpcsFile, $stackPtr);

            return;
        }

        $this->processHttpRequest($phpcsFile, $stackPtr);
    }

    /**
     * Reports every configured database trait named by the `use` statement at
     * $stackPtr.
     *
     * One statement can name several — `use RefreshDatabase, WithFaker;` — and
     * each is its own reach for the database, so each is reported where it is
     * written rather than once for the statement.
     */
    private function processUse(File $phpcsFile, int $stackPtr): void
    {
        foreach ($this->usedNames($phpcsFile, $stackPtr) as $namePtr => $name) {
            if (in_array(strtolower($this->trailingSegment($name)), self::DATABASE_TRAITS, true) === false) {
                continue;
            }

            $phpcsFile->addWarning(
                'A unit test concerns only the class under test: %s stands the database up,'
                    . ' which belongs in a %s test',
                $namePtr,
                'DatabaseTrait',
                [
                    $this->trailingSegment($name),
                    $this->suiteSibling(),
                ]
            );
        }
    }

    /**
     * Reports a `Facade::fake(` call at the `::` token $stackPtr.
     *
     * The receiver is compared on its trailing segment, so `Http`, `\Http` and
     * `Illuminate\Support\Facades\Http` all read as the facade. The `(` is part
     * of the match: `Http::fake` as a bare constant or a first-class callable
     * reference installs nothing on its own.
     */
    private function processFacadeFake(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($methodPtr === false || strtolower($tokens[$methodPtr]['content']) !== self::FAKE_METHOD) {
            return;
        }

        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if ($openPtr === false || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $facade = $this->trailingSegment($this->receiverBefore($phpcsFile, $stackPtr));

        if (in_array(strtolower($facade), self::FAKEABLE_FACADES, true) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'A unit test concerns only the class under test: %s::fake() doubles out an external'
                . ' subsystem, which belongs in a %s test',
            $stackPtr,
            'FacadeFake',
            [
                $facade,
                $this->suiteSibling(),
            ]
        );
    }

    /**
     * Reports an HTTP-kernel request call on the test case at the `$this`
     * token $stackPtr.
     *
     * Only `$this` is a receiver here. A request method reached through any
     * other variable is a call on some other object, which this sniff cannot
     * resolve and does not guess at.
     */
    private function processHttpRequest(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (strtolower($tokens[$stackPtr]['content']) !== '$this') {
            return;
        }

        $operatorPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($operatorPtr === false || in_array($tokens[$operatorPtr]['code'], self::OBJECT_OPERATORS, true) === false) {
            return;
        }

        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if ($methodPtr === false || $tokens[$methodPtr]['code'] !== T_STRING) {
            return;
        }

        $method = $tokens[$methodPtr]['content'];

        if (in_array(strtolower($method), self::HTTP_KERNEL_METHODS, true) === false) {
            return;
        }

        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if ($openPtr === false || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        $phpcsFile->addWarning(
            'A unit test concerns only the class under test: $this->%s() dispatches through the HTTP'
                . ' kernel, which belongs in a %s test',
            $methodPtr,
            'HttpRequest',
            [
                $method,
                $this->suiteSibling(),
            ]
        );
    }

    /**
     * The names a `use` statement at $stackPtr brings in, keyed by the pointer
     * each one starts at.
     *
     * The T_USE keyword covers four constructs, and the scan has to tell them
     * apart because two of them carry a brace block. PHP_CodeSniffer settles
     * three of the four at the token level, which is what this reads:
     *
     *   - an import, `use A\B;`, and a trait use, `use A;` — plain names, to a
     *     `;`.
     *   - a group import, `use A\{B, C};` — the brace is T_OPEN_USE_GROUP, a
     *     token PHP_CodeSniffer mints for exactly this construct. The prefix
     *     before it is dropped: only trailing segments are compared, so
     *     `A\{B}` and `A\B` answer the same.
     *   - a trait adaptation, `use A, B { A::c insteadof B; }` — an ordinary
     *     T_OPEN_CURLY_BRACKET, holding conflict-resolution rules rather than
     *     names, so the scan stops at it. Distinguishing it from a group
     *     import by the two token types is exact; inferring it from what
     *     precedes the brace is not. An *empty* adaptation block also carries
     *     no `;` of its own, which is what an unbounded scan runs past and
     *     into the next statement.
     *   - a closure's capture list, `function () use ($x)` — filtered at the
     *     `(`. It carries variables, not names.
     *
     * Two things inside a statement are deliberately *not* names:
     *
     *   - the `function`/`const` kind marker. PHP_CodeSniffer tokenizes it as
     *     T_STRING, not T_FUNCTION or T_CONST, both at the head of a statement
     *     and per-member inside a group (`use A\{function b, C};`) — so it is
     *     recognized by content at the position a member starts, and the whole
     *     member it marks is skipped. A trait is neither a function nor a
     *     constant, and matching one by name would report an unrelated
     *     same-named import.
     *   - an `as` alias, which is a different symbol from the trait it renames.
     *     The name is closed at the `as` and the alias skipped — to the next
     *     comma only, because a group import can carry further members after
     *     an aliased one.
     *
     * @return array<int, string>
     */
    private function usedNames(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $names = [];
        $current = '';
        $currentPtr = null;
        $skipMember = false;

        for ($i = ($stackPtr + 1); $i < $phpcsFile->numTokens; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_OPEN_PARENTHESIS) {
                return [];
            }

            if ($code === T_OPEN_CURLY_BRACKET) {
                break;
            }

            if ($code === T_OPEN_USE_GROUP) {
                $current = '';
                $currentPtr = null;
                $skipMember = false;

                continue;
            }

            if ($code === T_CLOSE_USE_GROUP || $code === T_SEMICOLON) {
                break;
            }

            if ($code === T_COMMA) {
                $names = $this->collect($names, $currentPtr, $current);
                $current = '';
                $currentPtr = null;
                $skipMember = false;

                continue;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true || $skipMember === true) {
                continue;
            }

            if ($code === T_AS) {
                $names = $this->collect($names, $currentPtr, $current);
                $current = '';
                $currentPtr = null;
                $skipMember = true;

                continue;
            }

            if ($currentPtr === null && $this->isKindMarker($tokens[$i]['content']) === true) {
                $skipMember = true;

                continue;
            }

            $currentPtr ??= $i;
            $current .= $tokens[$i]['content'];
        }

        return $this->collect($names, $currentPtr, $current);
    }

    /**
     * Whether $content is the `function`/`const` marker that states an
     * import's kind instead of naming it. Compared case-insensitively, both
     * being reserved words.
     */
    private function isKindMarker(string $content): bool
    {
        return in_array(strtolower($content), ['function', 'const'], true);
    }

    /**
     * Adds $name to $names under $pointer, unless there is no name to add.
     *
     * @param array<int, string> $names
     *
     * @return array<int, string>
     */
    private function collect(array $names, ?int $pointer, string $name): array
    {
        if ($pointer === null || trim($name, '\\') === '') {
            return $names;
        }

        $names[$pointer] = $name;

        return $names;
    }

    /**
     * The receiver name written immediately before the `::` at $stackPtr.
     *
     * Read backwards over the tokens a qualified class name is spelled with, so
     * a facade reaches this sniff the same way whether it is imported and used
     * bare or written out in full at the call site.
     */
    private function receiverBefore(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            $code = $tokens[$i]['code'];

            if (
                $code === T_STRING
                || $code === T_NS_SEPARATOR
                || $code === T_NAME_QUALIFIED
                || $code === T_NAME_FULLY_QUALIFIED
            ) {
                $name = $tokens[$i]['content'] . $name;

                continue;
            }

            break;
        }

        return $name;
    }

    /**
     * Whether PHP_CodeSniffer is looking at a file inside $unitTestPath.
     */
    private function isUnitTestFile(File $phpcsFile): bool
    {
        if ($phpcsFile->getFilename() === self::UNKNOWN_PATH) {
            return false;
        }

        $scope = $this->pathSegments($this->unitTestPath);

        // An empty property names no directory, so it reaches no file. Stated
        // rather than left to fall out: the scan below happens to reach the
        // same answer, because a null root matches no segment, but that is an
        // accident of array_keys() rather than the decision this rule makes.
        // The alternative reading — an empty prefix matching every path —
        // would turn one blank <property> element into a warning on every file
        // in a consuming project.
        if ($scope === []) {
            return false;
        }

        $segments = $this->pathSegments($phpcsFile->getFilename());

        array_pop($segments);

        $root = array_shift($scope);
        $roots = array_keys($segments, $root, true);

        if ($roots === []) {
            return false;
        }

        return array_slice($segments, (end($roots) + 1), count($scope)) === $scope;
    }

    /**
     * $path split into its lower-cased, non-empty segments. The fold to lower
     * case is what makes both sides of the scope comparison case-insensitive at
     * once — the configured property and the file's own path are both read
     * through here.
     *
     * @return array<int, string>
     */
    private function pathSegments(string $path): array
    {
        return array_map('strtolower', $this->splitPath($path));
    }

    /**
     * $path split into its non-empty segments, with the spelling it was written
     * in left alone.
     *
     * Backslashes are folded to forward slashes first, so a Windows-style path
     * segments the same way as a POSIX one. The casing survives because one
     * caller compares segments and the other prints them: pathSegments() lowers
     * them for the comparison, suiteSibling() keeps the configured spelling for
     * the message.
     *
     * @return array<int, string>
     */
    private function splitPath(string $path): array
    {
        return array_values(
            array_filter(
                explode('/', str_replace('\\', '/', $path)),
                static fn (string $segment): bool => $segment !== ''
            )
        );
    }

    /**
     * The last `\`-separated segment of a name, which is what a class is
     * written as once imported.
     */
    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', trim($name, '\\'));

        return (string) end($segments);
    }

    /**
     * The suite a flagged test belongs in, spelled from $unitTestPath so the
     * message stays true for a project that has retuned the scope.
     *
     * The unit suite's own directory with its last segment replaced by
     * `Feature`: a project keeping its suites under `app/Tests/Unit/` is told
     * about `app/Tests/Feature/`, not about a `tests/Feature/` it does not
     * have.
     */
    private function suiteSibling(): string
    {
        $segments = $this->splitPath($this->unitTestPath);

        array_pop($segments);

        $segments[] = 'Feature';

        return implode('/', $segments) . '/';
    }
}
