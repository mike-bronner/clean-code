<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Flags a concrete class under the source directory that has no companion test
 * file.
 *
 * Testing: Development Process (TDD) (#57) —
 * docs/standards/testing-development-process-tdd.md — is a *process* standard,
 * and the process leaves no trace in the tokens: whether a test was written
 * first, whether the code grew through Red/Green/Refactor, whether the author
 * held the right perspective. None of that is recoverable from a file. One
 * slice is, though, and it is an end state rather than a process fact: a class
 * that has no test at all is visibly out of compliance however it was written
 * (#128).
 *
 * That is the whole of what this sniff claims. It asserts a file *exists*. It
 * says nothing about test-first order, and nothing about whether the test it
 * found asserts anything — both stay with code review, as the standard's own
 * doc records.
 *
 * ## Resolving the companion test
 *
 * The lookup is driven from the file's path rather than from its class name,
 * because the path is what the standard's example maps: `src/Foo/Bar.php` is
 * expected to have a test named for `Bar`. Four public properties spell the
 * mapping out, so a project retunes it without overriding the ruleset:
 *
 * - $sourceDirectories — the directory names that mark a source root. The
 *   *last* one on the path wins, and everything above it is the project root.
 * - $testDirectory — the test root, relative to that project root.
 * - $testPathTemplate — the rest of the expected path, below the test root.
 *   `{path}` is the file's directory relative to the source root, `{name}` its
 *   base name. Empty segments collapse, so a file sitting directly on the
 *   source root does not produce a doubled separator.
 * - $excludePatterns — fnmatch globs for paths the rule does not speak about.
 *
 * The resolved path is a **glob pattern**, not a literal, and that is what
 * makes one filesystem call enough for layouts that are not a plain mirror. A
 * Laravel application splitting its suite sets `$testDirectory` to `tests/*`
 * and both `tests/Unit/Models/UserTest.php` and
 * `tests/Feature/Models/UserTest.php` satisfy the same single lookup. A suite
 * that ignores the source-relative directory sets `$testPathTemplate` to
 * `{name}Test.php`.
 *
 * The wildcards are the configured halves' alone. `{path}`, `{name}` and the
 * directories above the source root are read off the filesystem, where `*`, `?`
 * and `[...]` are all legal characters in a name, so each is quoted into a form
 * the pattern matches literally before it is substituted in. A project checked
 * out under a directory called `build[1]` resolves its companions from
 * `build[1]/tests/`, not from whatever `build1` might happen to be.
 *
 * Exactly one `glob()` call is made per class declaration. There is no
 * directory walk and no recursive search: `*` in a glob pattern does not cross
 * a separator, so the cost of the lookup is fixed by the configured pattern
 * rather than by the size of the test suite.
 *
 * A `glob()` that returns `false` is treated as "no test found" rather than as
 * "cannot tell". The two are not distinguishable in the first place — some
 * platforms return `false` where others return an empty array for a pattern
 * that simply matches nothing — and of the two readings, only this one fails
 * closed.
 *
 * ## What is never flagged
 *
 * - Interfaces, traits and enums. PHP_CodeSniffer tokenises those as
 *   T_INTERFACE, T_TRAIT and T_ENUM, so registering T_CLASS alone excludes
 *   them. An anonymous class is T_ANON_CLASS and falls out the same way — it
 *   has no name for a test to be named after.
 * - Abstract classes, which are exercised through their concrete subclasses.
 *   This one is not a token distinction (an abstract class is still T_CLASS)
 *   and is read from the declaration's modifiers.
 * - Anything matching $excludePatterns, which ships empty: framework
 *   scaffolding varies per project, so nothing is opted out until the
 *   consuming ruleset says so.
 * - A file with no source root on its path at all. The sniff scopes itself
 *   from $sourceDirectories rather than from a ruleset <include-pattern>, so a
 *   project whose classes live elsewhere retunes the property instead of
 *   overriding this ruleset — the same arrangement
 *   CleanCode.Testing.NoReflectionAccess uses for the inverse case.
 * - Piped input, which PHP_CodeSniffer names STDIN. There is no location to
 *   resolve a companion against.
 *
 * Warnings, not errors. The rule reads a project's layout off a configurable
 * convention and cannot prove it guessed right, so a misread must not fail a
 * build. Detection only: writing the missing test is the fix, and no fixer can
 * write it.
 *
 * Fixtured in tests/fixtures/RequireTestFileSniff/ and covered by
 * tests/Standards/RequireTestFileTest.php.
 */
class RequireTestFileSniff implements Sniff
{
    /**
     * The path PHP_CodeSniffer reports when it lints piped input with no
     * --stdin-path. There is no file location to resolve a companion test
     * against, so the sniff has nothing to say.
     */
    private const UNKNOWN_PATH = 'STDIN';

    /**
     * Directory names that mark a source root. Compared case-sensitively
     * against whole path segments: these are literal directory names, and a
     * project that spells one differently adds that spelling here.
     * Configurable via <property name="sourceDirectories" type="array" .../>.
     *
     * @var array<string>
     */
    public array $sourceDirectories = [
        'app',
        'src',
    ];

    /**
     * The test root, relative to the project root. May itself hold a glob
     * wildcard — `tests/*` covers a suite split into Unit/ and Feature/
     * without costing a second lookup. Configurable via
     * <property name="testDirectory" value="..."/>.
     */
    public string $testDirectory = 'tests';

    /**
     * The expected test path below the test root. `{path}` is the file's
     * directory relative to the source root, `{name}` its base name. Both may
     * be surrounded by glob wildcards. Configurable via
     * <property name="testPathTemplate" value="..."/>.
     */
    public string $testPathTemplate = '{path}/{name}Test.php';

    /**
     * Paths the rule does not speak about, as fnmatch globs matched against
     * the whole (forward-slash normalised) file path. Ships empty, so nothing
     * is opted out until a consuming ruleset names it — which framework
     * scaffolding needs opting out is a property of the application, not of
     * the standard. Configurable via
     * <property name="excludePatterns" type="array" .../>.
     *
     * @var array<string>
     */
    public array $excludePatterns = [];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $path = str_replace('\\', '/', $phpcsFile->getFilename());

        if ($this->isExempt($phpcsFile, $stackPtr, $path) === true) {
            return;
        }

        $segments = explode('/', $path);
        $sourceIndex = $this->sourceRootIndex($segments);

        if ($sourceIndex === null) {
            return;
        }

        $pattern = $this->expectedTestPattern($segments, $sourceIndex);

        if ($this->hasMatch($pattern) === true) {
            return;
        }

        $phpcsFile->addWarning(
            'Every class gets a unit test, and %s has none; expected a test file matching %s'
                . ' (Testing: Development Process (TDD), #57 —'
                . ' docs/standards/testing-development-process-tdd.md). Existence only: this says'
                . ' nothing about whether the test was written first, nor about what it asserts',
            $stackPtr,
            'Missing',
            [
                basename($path),
                $pattern,
            ]
        );
    }

    /**
     * Whether this declaration is outside the rule regardless of where its
     * companion test would live.
     *
     * Abstract classes are exercised through their concrete subclasses, so a
     * test named after the abstract one is not what the standard asks for.
     * Unlike interfaces, traits and enums — which the tokenizer already keeps
     * away from T_CLASS — the modifier has to be read.
     */
    private function isExempt(File $phpcsFile, int $stackPtr, string $path): bool
    {
        if ($path === self::UNKNOWN_PATH) {
            return true;
        }

        if ($this->isExcluded($path) === true) {
            return true;
        }

        return $phpcsFile->getClassProperties($stackPtr)['is_abstract'] === true;
    }

    /**
     * Whether the path matches one of the configured exclude globs.
     */
    private function isExcluded(string $path): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $path) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The index of the source root in $segments, or null when the path holds
     * no source directory at all.
     *
     * The *last* match rather than the first, for the reason
     * CleanCode.Routes.ApiControllerNamespace anchors on its closest
     * `Controllers` segment: PHP_CodeSniffer hands the sniff a fully resolved
     * absolute path, and every directory above the project is part of it. A
     * checkout under /srv/app/my-project would otherwise take /srv as the
     * project root and look for its tests in /srv/tests.
     *
     * @param array<int, string> $segments
     */
    private function sourceRootIndex(array $segments): ?int
    {
        $matches = array_keys(array_intersect($segments, $this->sourceDirectories));

        return $matches === [] ? null : (int) end($matches);
    }

    /**
     * The glob pattern the companion test is expected to match.
     *
     * Empty segments are dropped before the pattern is rejoined, so a file
     * sitting directly on the source root — where `{path}` resolves to nothing
     * — yields `<root>/tests/BarTest.php` rather than `<root>/tests//BarTest.php`.
     * A leading separator is put back afterwards, because an absolute path's
     * first segment is itself empty.
     *
     * @param array<int, string> $segments
     */
    private function expectedTestPattern(array $segments, int $sourceIndex): string
    {
        $relativeDirectory = implode('/', array_slice($segments, ($sourceIndex + 1), -1));
        $expected = strtr($this->testPathTemplate, [
            '{path}' => $this->quoteGlob($relativeDirectory),
            '{name}' => $this->quoteGlob(pathinfo(end($segments), PATHINFO_FILENAME)),
        ]);
        $projectRoot = $this->quoteGlob(implode('/', array_slice($segments, 0, $sourceIndex)));
        $leadingSeparator = $segments[0] === '' ? '/' : '';
        $parts = explode('/', $projectRoot . '/' . $this->testDirectory . '/' . $expected);

        return $leadingSeparator . implode('/', array_filter($parts, 'strlen'));
    }

    /**
     * The form of a literal path segment that a glob pattern matches as itself.
     *
     * Everything the expected path is built from splits in two. The template and
     * the test root are written by whoever configures the sniff, and a wildcard
     * in either is the point — `tests/*` is how a split suite is spelled. The
     * rest is read off the filesystem: the directories above the source root,
     * the file's directory relative to it, and the file's own name. None of
     * those is constrained to be free of `*`, `?` or `[...]`, so each is quoted
     * here and only the configured halves keep their wildcards.
     *
     * Quoted by wrapping each character in a class of its own rather than by
     * escaping it with a backslash: the path is normalised to forward slashes
     * before it reaches this point, and a backslash is a path separator on the
     * platform that normalisation is there for. `]` needs no quoting — outside a
     * class it is already literal, and every class opened here is closed
     * immediately.
     */
    private function quoteGlob(string $literal): string
    {
        return strtr($literal, [
            '*' => '[*]',
            '?' => '[?]',
            '[' => '[[]',
        ]);
    }

    /**
     * Whether anything on disk matches the expected pattern.
     *
     * One glob() call, and glob() never crosses a separator on `*`, so the cost
     * of the lookup is fixed by the configured pattern rather than by the size
     * of the test suite.
     *
     * glob() answers a pattern matching nothing with an empty array on most
     * platforms and with false on some, and answers a directory it could not
     * read with false as well. All three say the same thing here — no companion
     * was found — so they are folded into one truthiness test rather than told
     * apart. That is also the only reading that fails closed: compare against
     * the empty array alone and a false return says "a test exists".
     */
    private function hasMatch(string $pattern): bool
    {
        return (bool) glob($pattern);
    }
}
