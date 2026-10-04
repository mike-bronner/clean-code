<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class RequireTestFileSniff implements Sniff
{
    private const UNKNOWN_PATH = 'STDIN';

    public array $sourceDirectories = [
        'app',
        'src',
    ];

    public string $testDirectory = 'tests';

    public array|string $testPathTemplate = [
        '{path}/{name}Test.php',
        '*/{path}/{name}Test.php',
    ];

    public array $excludePatterns = [];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, int $stackPtr): void
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

        $patterns = array_map(
                fn (string $template): string => $this->testPattern($segments, $sourceIndex, $template),
                (array) $this->testPathTemplate
            );

        if ($this->hasMatch($patterns) === true) {
            return;
        }

        $phpcsFile->addWarning(
                'Every class gets a unit test, and %s has none; expected a test file matching %s'
                    . ' (Testing: Development Process (TDD), #57 —'
                    . ' resources/boost/guidelines/'
                    . 'testing-development-process-tdd.md). Existence only: this says'
                    . ' nothing about whether the test was written first, nor about what it asserts',
                $stackPtr,
                'Missing',
                [
                    basename($path),
                    implode(' or ', $patterns),
                ]
            );
    }

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

    private function isExcluded(string $path): bool
    {
        foreach ($this->excludePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $path) === true) {
                return true;
            }
        }

        return false;
    }

    private function sourceRootIndex(array $segments): ?int
    {
        $matches = array_keys(array_intersect($segments, $this->sourceDirectories));

        return $matches === [] ? null : (int) end($matches);
    }

    private function testPattern(array $segments, int $sourceIndex, string $template): string
    {
        $relativeDirectory = implode('/', array_slice($segments, ($sourceIndex + 1), -1));
        $expected = strtr($template, [
            '{path}' => $this->quoteGlob($relativeDirectory),
            '{name}' => $this->quoteGlob(pathinfo(end($segments), PATHINFO_FILENAME)),
        ]);
        $projectRoot = $this->quoteGlob(implode('/', array_slice($segments, 0, $sourceIndex)));
        $leadingSeparator = $segments[0] === '' ? '/' : '';
        $parts = explode('/', "{$projectRoot}/{$this->testDirectory}/{$expected}");

        return $leadingSeparator . implode('/', array_filter($parts, 'strlen'));
    }

    private function quoteGlob(string $literal): string
    {
        return strtr($literal, [
            '*' => '[*]',
            '?' => '[?]',
            '[' => '[[]',
        ]);
    }

    private function hasMatch(array $patterns): bool
    {
        return array_filter($patterns, fn (string $pattern): bool => (bool) glob($pattern)) !== [];
    }
}
