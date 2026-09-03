<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Testing;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NoReflectionAccessSniff implements Sniff
{
    private const NAME_TOKENS = [
        T_STRING,
        T_NS_SEPARATOR,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    public array $testFilePatterns = [
        '*/tests/*',
        '*/Tests/*',
        '*Test.php',
    ];

    public array $reflectionClasses = [
        'ReflectionMethod',
        'ReflectionProperty',
    ];

    public array $reflectionMembers = [
        'getMethod',
        'getProperty',
        'invoke',
        'invokeArgs',
        'setAccessible',
    ];

    public function register(): array
    {
        return [
            T_NEW,
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isTestFile($phpcsFile->getFilename()) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_NEW) {
            $this->processInstantiation($phpcsFile, $stackPtr);

            return;
        }

        $this->processMemberAccess($phpcsFile, $stackPtr);
    }

    private function isTestFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->testFilePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    private function processInstantiation(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($classPtr === false) {
            return;
        }

        $written = $this->readName($tokens, $classPtr);

        if ($this->matches($this->trailingSegment($written), $this->reflectionClasses) === false) {
            return;
        }

        $this->report($phpcsFile, $classPtr, $written);
    }

    private function readName(array $tokens, int $startPtr): string
    {
        $written = '';

        for ($pointer = $startPtr; isset($tokens[$pointer]) === true; $pointer++) {
            if (in_array($tokens[$pointer]['code'], self::NAME_TOKENS, true) === false) {
                break;
            }

            $written .= $tokens[$pointer]['content'];
        }

        return $written;
    }

    private function processMemberAccess(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $memberPtr === false
            || $tokens[$memberPtr]['code'] !== T_STRING
        ) {
            return;
        }

        if ($this->matches($tokens[$memberPtr]['content'], $this->reflectionMembers) === false) {
            return;
        }

        $afterMember = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if (
            $afterMember === false
            || $tokens[$afterMember]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $this->report($phpcsFile, $memberPtr, $tokens[$memberPtr]['content']);
    }

    private function trailingSegment(string $name): string
    {
        $segments = explode('\\', $name);

        return (string) end($segments);
    }

    private function matches(string $name, array $candidates): bool
    {
        return in_array(strtolower($name), array_map('strtolower', $candidates), true);
    }

    private function report(File $phpcsFile, int $stackPtr, string $name): void
    {
        $phpcsFile->addWarning(
            'Reflection (%s) reaches a non-public member; test through the public API instead'
                . ' (see docs/standards/testing-guidelines.md)',
            $stackPtr,
            'Found',
            [$name]
        );
    }
}
