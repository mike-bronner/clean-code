<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\DeadCode;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Flags private methods and private properties that are never referenced
 * inside their declaring class — dead code that must be removed.
 *
 * Replaces SlevomatCodingStandard.Classes.UnusedPrivateElements, which was
 * removed from slevomat/coding-standard in 7.0. Detection is deliberately
 * conservative to avoid false positives: any mention of an element's name in
 * the class body — property/method access, static access, or a string literal
 * (callable arrays, compact(), interpolation) — counts as a usage. Dynamic
 * access via variable variables cannot be detected and stays with code review.
 */
class UnusedPrivateElementsSniff implements Sniff
{
    /**
     * Tokens that terminate the statement a class-member declaration sits in,
     * used when scanning backwards for its visibility modifier.
     */
    private const STATEMENT_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * Object/static access tokens that mark the following name as a usage.
     */
    private const ACCESS_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    /**
     * String tokens whose contents are mined for usage mentions.
     */
    private const STRING_TOKENS = [
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $opener = $tokens[$stackPtr]['scope_opener'];
        $closer = $tokens[$stackPtr]['scope_closer'];

        [$properties, $methods] = $this->findPrivateDeclarations($phpcsFile, $opener, $closer);

        if ($properties === [] && $methods === []) {
            return;
        }

        $usedNames = $this->collectUsedNames($phpcsFile, $opener, $closer);

        foreach ($properties as $name => $declarationPtr) {
            if (isset($usedNames[$name]) === false) {
                $phpcsFile->addError(
                    'Unused private property $%s must be removed — dead code answers no questions',
                    $declarationPtr,
                    'UnusedProperty',
                    [ltrim($tokens[$declarationPtr]['content'], '$')]
                );
            }
        }

        foreach ($methods as $name => $declarationPtr) {
            if (isset($usedNames[$name]) === false) {
                $phpcsFile->addError(
                    'Unused private method %s() must be removed — dead code answers no questions',
                    $declarationPtr,
                    'UnusedMethod',
                    [$tokens[$declarationPtr]['content']]
                );
            }
        }
    }

    /**
     * Collects private property and method declarations at class-body level.
     * Method bodies (and parameter lists, which excludes promoted constructor
     * properties) are jumped over. Magic methods are never collected — they
     * are invoked implicitly.
     *
     * @return array{0: array<string, int>, 1: array<string, int>} lowercased
     *         name (property names without the $) => declaration name pointer
     */
    private function findPrivateDeclarations(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $properties = [];
        $methods = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] === T_FUNCTION) {
                $namePtr = $phpcsFile->findNext(T_STRING, ($i + 1));

                if ($namePtr !== false && $this->isPrivate($phpcsFile, $i) === true) {
                    $name = strtolower($tokens[$namePtr]['content']);

                    if (str_starts_with($name, '__') === false) {
                        $methods[$name] = $namePtr;
                    }
                }

                $i = $tokens[$i]['scope_closer'] ?? $tokens[$i]['parenthesis_closer'] ?? $i;

                continue;
            }

            if ($tokens[$i]['code'] === T_ANON_CLASS && isset($tokens[$i]['scope_closer']) === true) {
                $i = $tokens[$i]['scope_closer'];

                continue;
            }

            if ($tokens[$i]['code'] === T_VARIABLE && $this->isPrivate($phpcsFile, $i) === true) {
                $properties[strtolower(ltrim($tokens[$i]['content'], '$'))] = $i;
            }
        }

        return [$properties, $methods];
    }

    /**
     * Whether the member declaration at $stackPtr carries a private modifier,
     * found by scanning back to the start of its statement.
     */
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

    /**
     * Collects every name mentioned in the class body that could reference a
     * private element: names after `->`, `?->`, or `::`, static property
     * variables after `::`, and every word inside string literals (covering
     * callable arrays, compact(), and interpolation). All lowercased.
     *
     * @return array<string, true>
     */
    private function collectUsedNames(File $phpcsFile, int $opener, int $closer): array
    {
        $tokens = $phpcsFile->getTokens();
        $usedNames = [];

        for ($i = ($opener + 1); $i < $closer; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_STRING || $code === T_VARIABLE) {
                $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($i - 1), null, true);

                if ($prev !== false && in_array($tokens[$prev]['code'], self::ACCESS_OPERATORS, true) === true) {
                    $usedNames[strtolower(ltrim($tokens[$i]['content'], '$'))] = true;
                }

                continue;
            }

            if (in_array($code, self::STRING_TOKENS, true) === true) {
                preg_match_all('/[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*/', $tokens[$i]['content'], $matches);

                foreach ($matches[0] as $word) {
                    $usedNames[strtolower($word)] = true;
                }
            }
        }

        return $usedNames;
    }
}
