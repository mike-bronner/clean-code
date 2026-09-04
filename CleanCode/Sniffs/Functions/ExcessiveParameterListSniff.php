<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class ExcessiveParameterListSniff implements Sniff
{
    private const DEFAULT_MINIMUM = 10;

    public int|string|null $minimum = self::DEFAULT_MINIMUM;

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $threshold = $this->threshold();

        // A declaration cut short mid-edit has no parenthesis pair, and
        // getMethodParameters() answers with an empty list rather than raising.
        $count = count($phpcsFile->getMethodParameters($stackPtr));

        if ($count < $threshold) {
            return;
        }

        $phpcsFile->addError(
            'The %s declares %s parameters, reaching the maximum of %s; group the related '
                . 'parameters into an object instead '
                . '(see docs/phpmd/codesize-excessiveparameterlist.md)',
            $stackPtr,
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $count, $threshold]
        );
    }

    private function threshold(): int
    {
        $configured = trim((string) $this->minimum);

        if (
            preg_match('/^\d+$/', $configured) !== 1
            || (int) $configured < 1
        ) {
            return self::DEFAULT_MINIMUM;
        }

        return (int) $configured;
    }

    private function describe(File $phpcsFile, int $stackPtr): string
    {
        $classLike = [T_ANON_CLASS, T_CLASS, T_ENUM, T_INTERFACE, T_TRAIT];
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];
        $subject = 'function';

        foreach (array_reverse($conditions, true) as $code) {
            if (in_array($code, $classLike, true) === true) {
                $subject = 'method';

                break;
            }

            if (in_array($code, [T_CLOSURE, T_FN, T_FUNCTION], true) === true) {
                break;
            }
        }

        return "{$subject} {$phpcsFile->getDeclarationName($stackPtr)}()";
    }
}
