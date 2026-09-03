<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use MikeBronner\CleanCode\Support\ParameterDeclaration;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class TooManyFieldsSniff implements Sniff
{
    private const PROPERTY_MODIFIERS = [
        T_VAR,
        T_STATIC,
        T_READONLY,
        T_FINAL,
    ];

    public $maxFields = 15;

    public function register(): array
    {
        return [T_CLASS, T_ANON_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // An unterminated class body is live coding, not a design smell: its
        // remaining fields have not been typed yet, so any count taken here
        // would be of a half-written class.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $threshold = (int) $this->maxFields;
        $fields = $this->countFields($phpcsFile, $stackPtr);

        if ($fields <= $threshold) {
            return;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        $phpcsFile->addError(
            'The class %s has %s fields; consider redesigning it to keep the number of fields under %s',
            $stackPtr,
            'MaxExceeded',
            [($name ?? '{anonymous}'), $fields, $threshold]
        );
    }

    private function countFields(File $phpcsFile, int $classPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$classPtr]['scope_closer'];
        $fields = 0;
        $ptr = $tokens[$classPtr]['scope_opener'];

        while (($ptr = $phpcsFile->findNext(T_VARIABLE, ($ptr + 1), $closer)) !== false) {
            if ($this->isFieldOf($phpcsFile, $ptr, $classPtr) === true) {
                $fields++;
            }
        }

        return $fields;
    }

    private function isFieldOf(File $phpcsFile, int $variablePtr, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (array_key_last($tokens[$variablePtr]['conditions']) !== $classPtr) {
            return false;
        }

        if (empty($tokens[$variablePtr]['nested_parenthesis']) === false) {
            return ParameterDeclaration::isPromotedParameter($phpcsFile, $variablePtr);
        }

        return $this->isPropertyDeclaration($phpcsFile, $variablePtr);
    }

    private function isPropertyDeclaration(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundary = $phpcsFile->findPrevious(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET],
            ($variablePtr - 1)
        );
        $start = ($boundary + 1);

        while (
            ($start = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $variablePtr, true)) !== false
            && $tokens[$start]['code'] === T_ATTRIBUTE
        ) {
            $start = ($tokens[$start]['attribute_closer'] + 1);
        }

        if ($start === false) {
            return false;
        }

        return in_array($tokens[$start]['code'], Tokens::$scopeModifiers, true) === true
            || in_array($tokens[$start]['code'], self::PROPERTY_MODIFIERS, true) === true;
    }
}
