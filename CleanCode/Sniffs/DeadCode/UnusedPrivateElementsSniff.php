<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\DeadCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class UnusedPrivateElementsSniff implements Sniff
{
    private const STATEMENT_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    private const ACCESS_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    public function register(): array
    {
        return [T_CLASS, T_ENUM, T_ANON_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $opener = $tokens[$stackPtr]['scope_opener'];
        $closer = $tokens[$stackPtr]['scope_closer'];

        [$properties, $methods] = $this->findPrivateDeclarations($phpcsFile, $opener, $closer);

        if (
            $properties === []
            && $methods === []
        ) {
            return;
        }

        [$usedProperties, $usedMethods] = $this->collectUsedNames($phpcsFile, $opener, $closer);

        foreach ($properties as $name => $declarationPtr) {
            if (isset($usedProperties[$name]) === false) {
                $phpcsFile->addError(
                    'Unused private property $%s must be removed — dead code answers no questions',
                    $declarationPtr,
                    'UnusedProperty',
                    [ltrim($tokens[$declarationPtr]['content'], '$')]
                );
            }
        }

        foreach ($methods as $name => $declarationPtr) {
            if (isset($usedMethods[$name]) === false) {
                $phpcsFile->addError(
                    'Unused private method %s() must be removed — dead code answers no questions',
                    $declarationPtr,
                    'UnusedMethod',
                    [$tokens[$declarationPtr]['content']]
                );
            }
        }
    }

    private function findPrivateDeclarations(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $properties = [];
        $methods = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] === T_FUNCTION) {
                $namePtr = $phpcsFile->findNext(T_STRING, ($i + 1));

                if (
                    $namePtr !== false
                    && $this->isPrivate($phpcsFile, $i) === true
                ) {
                    $name = strtolower($tokens[$namePtr]['content']);

                    if (str_starts_with($name, '__') === false) {
                        $methods[$name] = $namePtr;
                    }
                }

                $i = $tokens[$i]['scope_closer'] ?? $tokens[$i]['parenthesis_closer'] ?? $i;

                continue;
            }

            if (
                $tokens[$i]['code'] === T_ANON_CLASS
                && isset($tokens[$i]['scope_closer']) === true
            ) {
                $i = $tokens[$i]['scope_closer'];

                continue;
            }

            if (
                $tokens[$i]['code'] === T_VARIABLE
                && $this->isPrivate($phpcsFile, $i) === true
            ) {
                $properties[strtolower(ltrim($tokens[$i]['content'], '$'))] = $i;
            }
        }

        return [$properties, $methods];
    }

    private function isPrivate(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            if (in_array($tokens[$i]['code'], self::STATEMENT_BOUNDARIES, true) === true) {
                return false;
            }

            if ($tokens[$i]['code'] === T_PRIVATE) {
                return true;
            }
        }

        return false;
    }

    private function collectUsedNames(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $usedProperties = [];
        $usedMethods = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            $code = $tokens[$i]['code'];

            if ($this->opensAnonymousClass($phpcsFile, $i) === true) {
                $i = $tokens[$i]['scope_closer'];

                continue;
            }

            if (
                $code === T_STRING
                || $code === T_VARIABLE
            ) {
                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($i - 1), null, true);

                if (
                    $prev !== false
                    && in_array($tokens[$prev]['code'], self::ACCESS_OPERATORS, true) === true
                ) {
                    $name = strtolower(ltrim($tokens[$i]['content'], '$'));

                    if ($this->isCall($phpcsFile, $i) === true) {
                        $usedMethods[$name] = true;
                    } else {
                        $usedProperties[$name] = true;
                    }
                }

                continue;
            }

            if (in_array($code, self::STRING_TOKENS, true) === true) {
                $matched = preg_match_all(
                    '/[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*/',
                    $tokens[$i]['content'],
                    $matches
                );

                // A failed read leaves $matches holding an empty [0] key, or
                // — when the pattern never compiled — not writing to it at all,
                // so the foreach below iterates an offset read off null.
                // Skipping the token sends the failure the way the rest
                // of this walk already leans: a word this read did not collect
                // is a word no element is proven to use, so a used element can
                // be reported as unused — a report to argue with, not a
                // silence to miss. The pattern is two character classes, the
                // second auto-possessified at the end of the pattern, with no
                // `/u` modifier, so nothing is known to reach the branch.
                if ($matched === false) {
                    continue;
                }

                foreach ($matches[0] as $word) {
                    $usedProperties[strtolower($word)] = true;
                    $usedMethods[strtolower($word)] = true;
                }
            }
        }

        return [$usedProperties, $usedMethods];
    }

    private function opensAnonymousClass(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $token = $tokens[$stackPtr];

        if (isset($token['scope_condition'], $token['scope_opener'], $token['scope_closer']) === false) {
            return false;
        }

        return $token['scope_opener'] === $stackPtr
            && $tokens[$token['scope_condition']]['code'] === T_ANON_CLASS;
    }

    private function isCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        return $next !== false && $tokens[$next]['code'] === T_OPEN_PARENTHESIS;
    }
}
