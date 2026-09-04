<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ShortVariableSniff implements Sniff
{
    public const DEFAULT_MINIMUM = 3;

    public $minimum = self::DEFAULT_MINIMUM;

    public $exceptions = '';

    private const IMPLICIT_RECEIVER = 'this';

    private const CLASS_LIKE_TOKENS = [T_CLASS, T_ANON_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    private const NESTING_OPENERS = [T_OPEN_SHORT_ARRAY, T_OPEN_SQUARE_BRACKET, T_OPEN_PARENTHESIS];

    private const NESTING_CLOSERS = [T_CLOSE_SHORT_ARRAY, T_CLOSE_SQUARE_BRACKET, T_CLOSE_PARENTHESIS];

    public function register(): array
    {
        return array_merge([T_OPEN_TAG, T_FUNCTION], self::CLASS_LIKE_TOKENS);
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $occurrences = $this->occurrencesInScope($phpcsFile, $stackPtr);

        $this->reportFirstOccurrences($phpcsFile, $occurrences);
    }

    private function occurrencesInScope(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_OPEN_TAG) {
            // Only the first open tag owns the file scope. A file that closes
            // and reopens its PHP block has several, and processing each one
            // would report the same name once per tag.
            return $phpcsFile->findPrevious(T_OPEN_TAG, ($stackPtr - 1)) === false
                ? $this->occurrencesIn($phpcsFile, 0, ($phpcsFile->numTokens - 1))
                : [];
        }

        if ($code === T_FUNCTION) {
            return $this->functionOccurrences($phpcsFile, $stackPtr);
        }

        // A class-like owns only its property declarations: its methods are
        // T_FUNCTION scopes of their own, reached separately.
        $isBounded = isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']);

        return $isBounded === true
            ? $this->occurrencesIn(
                $phpcsFile,
                ($tokens[$stackPtr]['scope_opener'] + 1),
                ($tokens[$stackPtr]['scope_closer'] - 1)
            )
            : [];
    }

    private function functionOccurrences(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        $opener = $tokens[$stackPtr]['parenthesis_opener'] ?? null;
        $closer = $tokens[$stackPtr]['parenthesis_closer'] ?? null;

        if (
            $opener === null
            || $closer === null
        ) {
            return [];
        }

        return $this->occurrencesIn($phpcsFile, $opener, $tokens[$stackPtr]['scope_closer'] ?? $closer);
    }

    private function occurrencesIn(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $occurrences = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_FUNCTION) {
                $pointer = $this->endOfDeclaration($phpcsFile, $pointer);

                continue;
            }

            if ($this->isClassLikeBrace($phpcsFile, $pointer) === true) {
                $pointer = $tokens[$pointer]['scope_closer'];

                continue;
            }

            if ($code === T_VARIABLE) {
                $name = substr($tokens[$pointer]['content'], 1);

                $isOccurrence = $name !== self::IMPLICIT_RECEIVER
                    && $this->isStaticMemberAccess($phpcsFile, $pointer) === false;

                if ($isOccurrence === true) {
                    $occurrences[] = ['pointer' => $pointer, 'name' => $name];
                }

                continue;
            }

            foreach ($this->interpolatedNames($tokens[$pointer]) as $name) {
                // A string spells the receiver too, and it is dropped here for
                // the same reason — see self::IMPLICIT_RECEIVER, which records
                // that phpmd does report several of these spellings.
                if ($name === self::IMPLICIT_RECEIVER) {
                    continue;
                }

                $occurrences[] = ['pointer' => $pointer, 'name' => $name];
            }
        }

        return $occurrences;
    }

    private function isStaticMemberAccess(File $phpcsFile, int $pointer): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($pointer - 1), null, true);

        return $previous !== false
            && $phpcsFile->getTokens()[$previous]['code'] === T_DOUBLE_COLON;
    }

    private function endOfDeclaration(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_closer']) === true) {
            return $tokens[$stackPtr]['scope_closer'];
        }

        if (isset($tokens[$stackPtr]['parenthesis_closer']) === false) {
            return $stackPtr;
        }

        $semicolon = $phpcsFile->findNext(T_SEMICOLON, $tokens[$stackPtr]['parenthesis_closer']);

        return $semicolon === false ? $tokens[$stackPtr]['parenthesis_closer'] : $semicolon;
    }

    private function isClassLikeBrace(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();

        $isScopeOwner = isset($tokens[$pointer]['scope_condition'], $tokens[$pointer]['scope_closer']);

        if ($isScopeOwner === false) {
            return false;
        }

        if ($tokens[$pointer]['scope_opener'] !== $pointer) {
            return false;
        }

        return in_array(
            $tokens[$tokens[$pointer]['scope_condition']]['code'],
            self::CLASS_LIKE_TOKENS,
            true
        );
    }

    private function interpolatedNames(array $token): array
    {
        if (in_array($token['code'], [T_DOUBLE_QUOTED_STRING, T_HEREDOC], true) === false) {
            return [];
        }

        $matched = preg_match_all(
            '/(?<!\\\\)\$\{?([a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)/',
            (string) $token['content'],
            $matches
        );

        // A failed read leaves $matches holding an empty [1] key, or — when
        // the pattern never compiled — not writing to it at all, so returning
        // it hands back an offset read off null against this method's declared
        // array<int, string>. The empty list is the exit for it, and the only
        // honest one: names that were not read cannot be checked, so a short
        // name interpolated into this string goes unreported. The pattern's
        // lookbehind is fixed-width and its one quantifier is a character class
        // auto-possessified at the end of the pattern, with no `/u` modifier,
        // so nothing is known to reach the branch.
        if ($matched === false) {
            return [];
        }

        return $matches[1];
    }

    private function reportFirstOccurrences(File $phpcsFile, array $occurrences): void
    {
        $minimum = $this->minimum();
        $exceptions = $this->exceptions();
        $seen = [];

        foreach ($occurrences as $occurrence) {
            $name = $occurrence['name'];

            if (isset($seen[$name]) === true) {
                continue;
            }

            $seen[$name] = true;

            if (strlen($name) >= $minimum) {
                continue;
            }

            if ($this->isAllowedInContext($phpcsFile, $occurrence['pointer']) === true) {
                continue;
            }

            if (in_array($name, $exceptions, true) === true) {
                continue;
            }

            $phpcsFile->addError(
                'Avoid variables with short names like %s. Configured minimum length is %s.',
                $occurrence['pointer'],
                'TooShort',
                ["\${$name}", $minimum]
            );
        }
    }

    private function isAllowedInContext(File $phpcsFile, int $pointer): bool
    {
        return $this->isInForInit($phpcsFile, $pointer) === true
            || $this->isOwnedBy($phpcsFile, $pointer, T_CATCH) === true
            || $this->isForeachLoopVariable($phpcsFile, $pointer) === true;
    }

    private function isInForInit(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$pointer]['nested_parenthesis'] ?? []) as $opener) {
            if ($this->parenthesisOwnerCode($phpcsFile, (int) $opener) !== T_FOR) {
                continue;
            }

            $semicolon = $this->headerSemicolon($phpcsFile, (int) $opener);

            if ($semicolon === null) {
                continue;
            }

            if ($pointer < $semicolon) {
                return true;
            }
        }

        return false;
    }

    private function headerSemicolon(File $phpcsFile, int $opener): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$opener]['parenthesis_closer'];
        $depth = count($tokens[$opener]['nested_parenthesis'] ?? []) + 1;
        $conditions = count($tokens[$opener]['conditions'] ?? []);

        for ($pointer = ($opener + 1); $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] !== T_SEMICOLON) {
                continue;
            }

            if (count($tokens[$pointer]['nested_parenthesis'] ?? []) !== $depth) {
                continue;
            }

            if (count($tokens[$pointer]['conditions'] ?? []) === $conditions) {
                return $pointer;
            }
        }

        return null;
    }

    private function isOwnedBy(File $phpcsFile, int $pointer, int|string $ownerCode): bool
    {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$pointer]['nested_parenthesis'] ?? []) as $opener) {
            if ($this->parenthesisOwnerCode($phpcsFile, (int) $opener) === $ownerCode) {
                return true;
            }
        }

        return false;
    }

    private function isForeachLoopVariable(File $phpcsFile, int $pointer): bool
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $this->enclosingForeachParenthesis($phpcsFile, $pointer);

        if ($opener === null) {
            return false;
        }

        $closer = $tokens[$opener]['parenthesis_closer'];
        $asPointer = $phpcsFile->findNext(T_AS, ($opener + 1), $closer);

        if ($asPointer === false) {
            return false;
        }

        $depth = 0;

        for ($current = ($asPointer + 1); $current < $closer; $current++) {
            $code = $tokens[$current]['code'];

            if (in_array($code, self::NESTING_OPENERS, true) === true) {
                $depth++;

                continue;
            }

            if (in_array($code, self::NESTING_CLOSERS, true) === true) {
                $depth--;

                continue;
            }

            if ($current !== $pointer) {
                continue;
            }

            return $depth === 0 && $this->isByReference($phpcsFile, $current) === false;
        }

        return false;
    }

    private function enclosingForeachParenthesis(File $phpcsFile, int $pointer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $openers = array_keys($tokens[$pointer]['nested_parenthesis'] ?? []);

        if ($openers === []) {
            return null;
        }

        $innermost = (int) end($openers);

        $owner = $this->parenthesisOwnerCode($phpcsFile, $innermost);

        return $owner === T_FOREACH ? $innermost : null;
    }

    private function parenthesisOwnerCode(File $phpcsFile, int $opener): int|string|null
    {
        $tokens = $phpcsFile->getTokens();

        return isset($tokens[$opener]['parenthesis_owner']) === true
            ? $tokens[$tokens[$opener]['parenthesis_owner']]['code']
            : null;
    }

    private function isByReference(File $phpcsFile, int $pointer): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($pointer - 1), null, true);

        return $previous !== false && $phpcsFile->getTokens()[$previous]['code'] === T_BITWISE_AND;
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
