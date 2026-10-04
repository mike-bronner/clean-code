<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class AvoidConditionalsSniff implements Sniff
{
    private const CONSTRUCTS = [
        T_IF => ['if', 'IfStatement'],
        T_ELSEIF => ['elseif', 'ElseIfStatement'],
        T_INLINE_THEN => ['ternary', 'TernaryExpression'],
        T_SWITCH => ['switch', 'SwitchStatement'],
    ];

    public function register(): array
    {
        return array_keys(self::CONSTRUCTS);
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        [$construct, $violationCode] = self::CONSTRUCTS[$tokens[$stackPtr]['code']];

        $phpcsFile->addWarning(
                "Avoid conditionals where possible: \"%s\" adds a branch, which raises cyclomatic"
                    . " complexity."
                    . ' Prefer polymorphism, a mapping array, or match where one applies.',
                $stackPtr,
                $violationCode,
                [$construct]
            );
    }
}
