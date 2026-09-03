<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;
use PHP_CodeSniffer\Util\Tokens;

class BinaryOperatorSpacingSniff extends OperatorSpacingSniff
{
    public const UNARY_SIGN_PRECEDERS = [
        T_ASPERAND => T_ASPERAND,
        T_OPEN_TAG => T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO => T_OPEN_TAG_WITH_ECHO,
        T_SEMICOLON => T_SEMICOLON,
    ];

    protected function isOperator(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        if (
            $code === T_PLUS
            || $code === T_MINUS
        ) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

            // Nothing at all precedes the sign, so nothing can be its left
            // operand. The parent reads a false pointer as position 0 instead.
            if ($previous === false) {
                return false;
            }

            if (isset(self::UNARY_SIGN_PRECEDERS[$tokens[$previous]['code']]) === true) {
                return false;
            }
        }

        return parent::isOperator($phpcsFile, $stackPtr);
    }
}
