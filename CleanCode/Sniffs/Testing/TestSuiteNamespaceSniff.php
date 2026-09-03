<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class TestSuiteNamespaceSniff implements Sniff
{
    private const UNKNOWN_PATH = 'STDIN';

    public string $testRoot = 'tests';

    public array $suiteSegments = [
        'Unit',
        'Feature',
        'Integration',
    ];

    public string $testClassSuffix = 'Test';

    public array $testBaseClasses = [
        'TestCase',
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
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

    private function isTestClass(File $phpcsFile, int $stackPtr): bool
    {
        if ($phpcsFile->getClassProperties($stackPtr)['is_abstract'] === true) {
            return false;
        }

        $name = (string) $phpcsFile->getDeclarationName($stackPtr);

        return str_ends_with($name, $this->testClassSuffix)
            || $this->extendsTestBase($phpcsFile, $stackPtr);
    }

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

    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', trim($name, '\\'));

        return (string) end($segments);
    }

    private function suiteOf(?array $tail): ?string
    {
        if (
            $tail === null
            || $tail === []
        ) {
            return null;
        }

        foreach ($this->suiteSegments as $segment) {
            if (strtolower($segment) === $tail[0]) {
                return $segment;
            }
        }

        return null;
    }

    private function segmentsBelowTestRoot(array $segments): ?array
    {
        $lowered = array_map('strtolower', $segments);
        $roots = array_keys($lowered, strtolower($this->testRoot), true);

        return $roots === [] ? null : array_slice($lowered, (end($roots) + 1));
    }

    private function pathSegments(File $phpcsFile): array
    {
        $segments = explode('/', str_replace('\\', '/', $phpcsFile->getFilename()));

        array_pop($segments);

        return $segments;
    }

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

            if (
                $namePtr === false
                || $tokens[$namePtr]['code'] === T_NS_SEPARATOR
            ) {
                continue;
            }

            return $this->readName($phpcsFile, $namePtr);
        }
    }

    private function readName(File $phpcsFile, int $startPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        for ($i = $startPtr; $i < $phpcsFile->numTokens; $i++) {
            $code = $tokens[$i]['code'];

            if (
                $code === T_SEMICOLON
                || $code === T_OPEN_CURLY_BRACKET
            ) {
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
