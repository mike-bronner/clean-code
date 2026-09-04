<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ShortMethodNameSniff implements Sniff
{
    public const DEFAULT_MINIMUM = 3;

    public $minimum = self::DEFAULT_MINIMUM;

    public $exceptions = '';

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
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

        if (in_array($name, $this->exceptions(), true) === true) {
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

        // Absent on a declaration PHP could not parse a parameter list for.
        // Refusing to guess is the closed behaviour: without the boundary
        // there is nothing to prove the token found is the name at all.
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
