<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use MikeBronner\CleanCode\Helpers\ClassNames;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ShortMethodNameSniff implements Sniff
{
    public const DEFAULT_MINIMUM = 3;

    private const MIGRATION_METHOD = 'up';

    private const MIGRATION_CLASS = 'illuminate\\database\\migrations\\migration';

    public $minimum = self::DEFAULT_MINIMUM;

    public $exceptions = '';

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $namePtr = $this->namePointer($phpcsFile, $stackPtr);

        if ($namePtr === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $name = $tokens[$namePtr]['content'];
        $minimum = $this->minimum();

        if (strlen($name) >= $minimum) {
            return;
        }

        if (
            in_array($name, $this->exceptions(), true) === true
            || $this->isMigrationUp($phpcsFile, $stackPtr, $name) === true
        ) {
            return;
        }

        $phpcsFile->addError(
                'Avoid using short method names like %s(). The configured minimum method name length is %s.',
                $namePtr,
                'TooShort',
                [$name, $minimum]
            );
    }

    private function namePointer(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['parenthesis_opener']) === false) {
            return null;
        }

        $skipped = Tokens::$emptyTokens;
        $skipped[T_BITWISE_AND] = T_BITWISE_AND;

        $namePtr = $phpcsFile->findNext(
                $skipped,
                ($stackPtr + 1),
                $tokens[$stackPtr]['parenthesis_opener'],
                true
            );

        return $namePtr === false ? null : $namePtr;
    }

    private function isMigrationUp(File $phpcsFile, int $stackPtr, string $name): bool
    {
        $classPtr = array_key_last($phpcsFile->getTokens()[$stackPtr]['conditions']);
        $extends = $classPtr === null ? false : $phpcsFile->findExtendedClassName($classPtr);
        $parent = is_string($extends) === true
            ? (new ClassNames)->resolve($phpcsFile, $extends, $classPtr)
            : '';

        return $name === self::MIGRATION_METHOD
            && strtolower(ltrim($parent, '\\')) === self::MIGRATION_CLASS;
    }

    private function minimum(): int
    {
        $configured = filter_var($this->minimum, FILTER_VALIDATE_INT);

        return $configured === false || $configured < 1 ? self::DEFAULT_MINIMUM : $configured;
    }

    private function exceptions(): array
    {
        return explode(',', (string) $this->exceptions);
    }
}
