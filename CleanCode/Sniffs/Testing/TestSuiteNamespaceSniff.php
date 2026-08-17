<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Requires a test class's declared suite namespace and its suite directory to
 * agree (Testing: Test Suites, #60) — docs/standards/testing-test-suites.md.
 *
 * The standard itself is about what each suite is *for*: a unit test concerns
 * only the class under test, a feature test reaches further but stays off the
 * internet, an integration test goes out to the real dependency. None of that
 * is recoverable from a file's tokens — it is a judgement about a test's scope
 * and its runtime behaviour — so the standard's core stays with code review,
 * and the three content-vs-directory heuristics are tracked separately (#148,
 * #149, #150).
 *
 * One slice *is* token-visible, and it is the one this sniff carries: a test
 * class states which suite it belongs to twice — once in the directory it sits
 * in, once in its declared namespace — and those two statements can contradict
 * each other. A contradiction is a fact about the file, not a judgement, so it
 * can be checked.
 *
 * The check is symmetric, because either half alone is satisfiable by moving
 * the file rather than by fixing it:
 *
 *   - a test class under a suite directory whose namespace names a different
 *     suite, or none (`NamespaceMismatch`);
 *   - a test class declared in a suite namespace that does not live under the
 *     matching suite directory (`DirectoryMismatch`).
 *
 * ## Anchoring, on both sides
 *
 * "Under a suite segment" is read *relative to the test root* on both sides:
 * the suite is the segment immediately below the `tests` root, never a match
 * found anywhere in the path or the name. Without that anchor an ordinary
 * business-domain namespace spelled like a suite — `App\Domain\Feature\Toggle`
 * — reads as a misplaced feature test.
 *
 * Two things keep that root honest, both taken from
 * CleanCode.Routes.ApiControllerNamespace, which solves the same problem for
 * controllers. PHP_CodeSniffer hands a sniff a fully resolved *absolute* path,
 * so every directory above the project is fair game:
 *
 *   - the root is the `tests` segment closest to the file, not the first one
 *     encountered, so a checkout under /srv/tests/my-app anchors on the
 *     application's own test directory rather than on the server layout. That
 *     is also what settles an ambiguous path: where several suite segments
 *     could be read, the root nearest the file picks the one that governs.
 *   - a class whose declared namespace has no test root at all is left alone,
 *     whatever its path says. Its own namespace places it outside the test
 *     tree, and that beats an ancestor directory that happens to be called
 *     `tests`.
 *
 * Segments are compared whole and case-insensitively: `UnitOfWork` is not
 * `Unit`, while `unit` is. Paths are normalised to forward slashes first, so a
 * Windows-style path segments the same way.
 *
 * ## What is never flagged
 *
 * - A class with no declared namespace. The rule compares two declared names,
 *   and here one of them is absent — there is nothing to contradict the
 *   location. This is the one place the sniff departs from
 *   ApiControllerNamespaceSniff, which reads such a class from its path alone:
 *   that sniff asks whether an API *grouping* is present, a question a path can
 *   answer by itself, while this one compares two spellings of the same suite
 *   and cannot run on one.
 * - Piped input, which PHP_CodeSniffer names STDIN. There is no location for a
 *   namespace to disagree with.
 * - Interfaces, traits and enums, which the tokenizer keeps away from T_CLASS,
 *   and anonymous classes, which are T_ANON_CLASS. A shared support helper
 *   sitting under a suite directory is excluded the same way every non-test
 *   class is: it does not match $testClassSuffix and extends no
 *   $testBaseClasses entry.
 * - Abstract classes. A shared abstract test case is inherited by the real
 *   tests and is not itself one of them; unlike the tokenizer distinctions
 *   above, the modifier has to be read.
 *
 * Warnings, not errors, matching CleanCode.Testing.RequireTestFile and
 * CleanCode.Testing.NoReflectionAccess. The rule reads a project's layout off a
 * configurable convention and cannot prove it guessed right, so a misread must
 * not fail a consumer's build.
 *
 * Detection only, and there is no autofixed.php fixture. Reconciling a mismatch
 * means moving the file or renaming its namespace, and which of those is
 * correct depends on the project's layout rather than on anything in the file.
 * There is no safe mechanical rewrite, so there is no fixer.
 *
 * Only the namespace and class declarations are read — never the file's
 * contents. Whether the *code* in a test belongs in the suite it sits in is a
 * different question, owned by #148, #149 and #150.
 *
 * Fixtured in tests/fixtures/TestSuiteNamespaceSniff/ and covered by
 * tests/Standards/TestSuiteNamespaceTest.php.
 */
class TestSuiteNamespaceSniff implements Sniff
{
    /**
     * The path PHP_CodeSniffer reports when it lints piped input with no
     * --stdin-path. There is no file location to compare a namespace against,
     * so the sniff has nothing to say.
     */
    private const UNKNOWN_PATH = 'STDIN';

    /**
     * The segment that marks the test root, on both the namespace and the path
     * side. Compared case-insensitively against whole segments, which is what
     * lets the one property serve a `tests/` directory and a `Tests\`
     * namespace at once. Configurable via
     * <property name="testRoot" value="..."/>.
     */
    public string $testRoot = 'tests';

    /**
     * The suite names, as they are spelled directly below the test root. A
     * project that names its suites differently replaces the list. Compared
     * case-insensitively and whole, so `Unit` matches `unit` but not
     * `UnitOfWork`. Configurable via
     * <property name="suiteSegments" type="array" .../>.
     *
     * @var array<string>
     */
    public array $suiteSegments = [
        'Unit',
        'Feature',
        'Integration',
    ];

    /**
     * The class-name suffix that marks a test class, matched case-sensitively
     * because it is part of a declared name. Configurable via
     * <property name="testClassSuffix" value="..."/>.
     */
    public string $testClassSuffix = 'Test';

    /**
     * Base classes whose subclasses are test classes whatever they are named,
     * for a suite that does not use the suffix. Only the trailing segment is
     * compared, so an entry may be written qualified or bare and still match a
     * class extending either spelling. Configurable via
     * <property name="testBaseClasses" type="array" .../>.
     *
     * @var array<string>
     */
    public array $testBaseClasses = [
        'TestCase',
    ];

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
        if ($phpcsFile->getFilename() === self::UNKNOWN_PATH) {
            return;
        }

        if ($this->isTestClass($phpcsFile, $stackPtr) === false) {
            return;
        }

        $namespaceTail = $this->segmentsBelowTestRoot(
            $this->namespaceSegments($phpcsFile, $stackPtr)
        );

        // A namespace carrying no test root places the class outside the test
        // tree, and that is the stronger statement: the path can pick up a
        // `tests` segment from the checkout location, the namespace cannot.
        // A class that declares no namespace at all arrives here as the empty
        // segment list and takes the same exit — it has said nothing that could
        // contradict its location, so there is no contradiction to report.
        if ($namespaceTail === null) {
            return;
        }

        $namespaceSuite = $this->suiteOf($namespaceTail);
        $pathSuite = $this->suiteOf($this->segmentsBelowTestRoot($this->pathSegments($phpcsFile)));

        if ($namespaceSuite === $pathSuite) {
            return;
        }

        if ($pathSuite !== null) {
            $phpcsFile->addWarning(
                'A test class under the %s suite directory must declare a matching %s namespace'
                    . ' segment directly below its %s namespace root',
                $stackPtr,
                'NamespaceMismatch',
                [
                    $pathSuite,
                    $pathSuite,
                    $this->testRoot,
                ]
            );

            return;
        }

        $phpcsFile->addWarning(
            'A test class declared in the %s suite namespace must live under a matching %s'
                . ' directory directly below %s/',
            $stackPtr,
            'DirectoryMismatch',
            [
                $namespaceSuite,
                $namespaceSuite,
                $this->testRoot,
            ]
        );
    }

    /**
     * Whether the class at $stackPtr is one of the suite's own test classes.
     *
     * Two ways in, because a suite may use either convention: the configured
     * name suffix, or a configured base class for tests that do not carry it.
     * An abstract class is neither — a shared abstract test case is exercised
     * through the concrete tests that extend it, and lives wherever those
     * tests can reach it.
     */
    private function isTestClass(File $phpcsFile, int $stackPtr): bool
    {
        if ($phpcsFile->getClassProperties($stackPtr)['is_abstract'] === true) {
            return false;
        }

        $name = (string) $phpcsFile->getDeclarationName($stackPtr);

        return str_ends_with($name, $this->testClassSuffix)
            || $this->extendsTestBase($phpcsFile, $stackPtr);
    }

    /**
     * Whether the class at $stackPtr extends one of $testBaseClasses.
     *
     * Only the trailing segment of each name is compared. A test case is
     * routinely imported and then extended by its short name, so the declared
     * parent is `TestCase` where the configured entry may be written
     * `PHPUnit\Framework\TestCase` — or the other way round, when the parent is
     * written out as a fully qualified name at the point of extension.
     */
    private function extendsTestBase(File $phpcsFile, int $stackPtr): bool
    {
        $parent = $phpcsFile->findExtendedClassName($stackPtr);

        if ($parent === false) {
            return false;
        }

        $declared = $this->trailingSegment($parent);

        foreach ($this->testBaseClasses as $base) {
            if (strcasecmp($declared, $this->trailingSegment($base)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * The last `\`-separated segment of a class name, which is the name it is
     * extended by once imported.
     */
    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', trim($name, '\\'));

        return (string) end($segments);
    }

    /**
     * The suite named by $tail, or null when $tail names none.
     *
     * The suite is the segment *immediately* below the test root, never a match
     * found further down: `tests/Unit/Models/` is a unit test, and so is
     * `tests/Unit/Feature/` — the directory a test is filed under is the one
     * directly on the root, and anything deeper is that suite's own structure.
     *
     * $tail arrives already lowercased, so only the configured spellings are
     * folded here. The configured spelling is what comes back, because that is
     * what the message asks the reader to write.
     *
     * @param array<int, string>|null $tail
     */
    private function suiteOf(?array $tail): ?string
    {
        if ($tail === null || $tail === []) {
            return null;
        }

        foreach ($this->suiteSegments as $segment) {
            if (strtolower($segment) === $tail[0]) {
                return $segment;
            }
        }

        return null;
    }

    /**
     * The segments of $segments that follow the *last* test root one, or null
     * when there is no test root segment at all.
     *
     * The last rather than the first, because the root that governs a file is
     * the one closest to it. On the path side the segments above the project
     * are outside its control — an application checked out under
     * /srv/tests/my-app would otherwise anchor on the server layout and read
     * `my-app` as its suite.
     *
     * Null and [] are different answers: null means "this side does not
     * describe a test at all", [] means "sitting directly on the test root",
     * which names no suite but is still inside the test tree.
     *
     * @param array<int, string> $segments
     *
     * @return array<int, string>|null
     */
    private function segmentsBelowTestRoot(array $segments): ?array
    {
        $lowered = array_map('strtolower', $segments);
        $roots = array_keys($lowered, strtolower($this->testRoot), true);

        return $roots === [] ? null : array_slice($lowered, (end($roots) + 1));
    }

    /**
     * The directory segments of the file's own path, the file name dropped.
     *
     * Backslashes are folded to forward slashes first, so a Windows-style path
     * segments the same way as a POSIX one.
     *
     * @return array<int, string>
     */
    private function pathSegments(File $phpcsFile): array
    {
        $segments = explode('/', str_replace('\\', '/', $phpcsFile->getFilename()));

        array_pop($segments);

        return $segments;
    }

    /**
     * The segments of the namespace enclosing the class at $stackPtr, or [] for
     * the global namespace.
     *
     * The enclosing namespace is the nearest *declaration* above the class,
     * which covers both spellings: with the one-per-file form every class
     * follows the single declaration, and with braced blocks the nearest one
     * above is the block the class sits in. T_NAMESPACE is also the `namespace\`
     * relative-name operator (`namespace\formatted()`), which can appear in a
     * method body above a later class — a following T_NS_SEPARATOR is what
     * tells the two apart, and the operator is skipped rather than read as a
     * declaration.
     *
     * @return array<int, string>
     */
    private function namespaceSegments(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $searchFrom = $stackPtr;

        while (true) {
            $namespacePtr = $phpcsFile->findPrevious(T_NAMESPACE, ($searchFrom - 1));

            if ($namespacePtr === false) {
                return [];
            }

            $searchFrom = $namespacePtr;
            $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($namespacePtr + 1), null, true);

            if ($namePtr === false || $tokens[$namePtr]['code'] === T_NS_SEPARATOR) {
                continue;
            }

            return $this->readName($phpcsFile, $namePtr);
        }
    }

    /**
     * Reads a namespace name from $startPtr up to its `;` or `{` terminator.
     *
     * The name is rebuilt from raw token content rather than from a specific
     * token sequence, so it does not care whether the tokenizer spells a
     * qualified name as T_STRING/T_NS_SEPARATOR pairs or as one name token.
     *
     * @return array<int, string>
     */
    private function readName(File $phpcsFile, int $startPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($i = $startPtr; $i < $phpcsFile->numTokens; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_SEMICOLON || $code === T_OPEN_CURLY_BRACKET) {
                break;
            }

            if (isset(Tokens::$emptyTokens[$code]) === true) {
                continue;
            }

            $name .= $tokens[$i]['content'];
        }

        $name = trim($name, '\\');

        return $name === '' ? [] : explode('\\', $name);
    }
}
