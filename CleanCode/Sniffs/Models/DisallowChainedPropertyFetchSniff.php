<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use MikeBronner\CleanCode\Helpers\TokenStreams;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowChainedPropertyFetchSniff implements Sniff
{
    private const GROUP_PRECEDERS = [
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_SEMICOLON,
        T_OPEN_CURLY_BRACKET,
        T_GOTO_LABEL,

        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_COMMA,
        T_COLON,
        T_INLINE_THEN,
        T_INLINE_ELSE,
        T_FN_ARROW,
        T_MATCH_ARROW,

        T_RETURN,
        T_ECHO,
        T_PRINT,
        T_THROW,
        T_YIELD,
        T_YIELD_FROM,
        T_CASE,
        T_CLONE,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,

        T_ELSE,
        T_DO,

        T_BOOLEAN_NOT,
        T_BITWISE_NOT,
        T_ASPERAND,
        T_ELLIPSIS,

        T_STRING_CONCAT,
    ];

    private const GROUP_PRECEDER_TYPES = [
        'T_VOID_CAST',
        'T_PIPE',
    ];

    private ?string $rootsKey = null;

    private array $roots = [];

    private array $cacheCounts = [
        'roots.builds' => 0,
        'roots.hits' => 0,
    ];

    private int $walkSteps = 0;

    public function __construct(
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    public function cacheCounts(): array
    {
        return $this->cacheCounts;
    }

    public function walkSteps(): int
    {
        return $this->walkSteps;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $memberPtr = $this->propertyNameAfter($phpcsFile, $stackPtr);

        if ($memberPtr === false) {
            return;
        }

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $receiverPtr === false
            || $tokens[$receiverPtr]['code'] !== T_STRING
        ) {
            return;
        }

        $previousOperatorPtr = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($receiverPtr - 1),
            null,
            true
        );

        if (
            $previousOperatorPtr === false
            || $this->isObjectOperator($tokens, $previousOperatorPtr) === false
        ) {
            return;
        }

        if ($this->isPrecededByAnotherHop($phpcsFile, $previousOperatorPtr) === true) {
            return;
        }

        if ($this->isRootedInVariable($phpcsFile, $previousOperatorPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Chained property fetch %s; expose the value as an accessor attribute on the '
                . 'first model instead (e.g. getAuthorNameAttribute() so callers read '
                . '$book->authorName rather than $book->author->name) '
                . '(see docs/standards/models-relationship-properties.md)',
            $memberPtr,
            'Found',
            [
                $tokens[$receiverPtr]['content']
                    . $tokens[$stackPtr]['content']
                    . $tokens[$memberPtr]['content'],
            ]
        );
    }

    private function propertyNameAfter(File $phpcsFile, int $operatorPtr): int|false
    {
        $tokens = $phpcsFile->getTokens();

        $memberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if (
            $memberPtr === false
            || $tokens[$memberPtr]['code'] !== T_STRING
        ) {
            return false;
        }

        $afterMemberPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($memberPtr + 1), null, true);

        if (
            $afterMemberPtr !== false
            && $tokens[$afterMemberPtr]['code'] === T_OPEN_PARENTHESIS
        ) {
            return false;
        }

        return $memberPtr;
    }

    private function isRootedInVariable(File $phpcsFile, int $operatorPtr): bool
    {
        return $this->rootBefore($phpcsFile, $operatorPtr) !== false;
    }

    private function rootBefore(File $phpcsFile, int $beforePtr): int|false
    {
        return $this->rootFrom(
            $phpcsFile,
            $phpcsFile->findPrevious(Tokens::$emptyTokens, ($beforePtr - 1), null, true)
        );
    }

    // phpcs:ignore CleanCode.Functions.ExcessiveMethodLength -- one root resolution, split only by guard clauses
    private function rootFrom(File $phpcsFile, int|false $ptr): int|false
    {
        $this->discardRootsOfOtherStreams($phpcsFile);

        $tokens = $phpcsFile->getTokens();
        $walked = [];

        while ($ptr !== false) {
            if (array_key_exists($ptr, $this->roots) === true) {
                return $this->recordRoots($walked, $this->roots[$ptr]);
            }

            ++$this->walkSteps;
            $walked[] = $ptr;
            $code = $tokens[$ptr]['code'];

            if (
                $code === T_CLOSE_PARENTHESIS
                || $code === T_CLOSE_SQUARE_BRACKET
                || $code === T_CLOSE_CURLY_BRACKET
            ) {
                $openerPtr = $this->openerOf($tokens, $ptr);

                if ($openerPtr === false) {
                    return $this->recordRoots($walked, false);
                }

                $beforeOpenerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($openerPtr - 1), null, true);

                if ($code === T_CLOSE_CURLY_BRACKET) {
                    if (
                        $beforeOpenerPtr === false
                        || $this->isObjectOperator($tokens, $beforeOpenerPtr) === false
                    ) {
                        return $this->recordRoots($walked, false);
                    }

                    $ptr = $beforeOpenerPtr;

                    continue;
                }

                if (
                    $code === T_CLOSE_SQUARE_BRACKET
                    || $this->isInvokedOn($tokens, $beforeOpenerPtr) === true
                ) {
                    $ptr = $beforeOpenerPtr;

                    continue;
                }

                if ($this->isGroupingParenthesis($tokens, $beforeOpenerPtr) === false) {
                    return $this->recordRoots($walked, false);
                }

                return $this->recordRoots($walked, $this->rootInsideGroup($phpcsFile, $openerPtr, $ptr));
            }

            if ($this->isObjectOperator($tokens, $ptr) === true) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

                continue;
            }

            if (
                $code !== T_STRING
                && $code !== T_VARIABLE
            ) {
                return $this->recordRoots($walked, false);
            }

            $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if (
                $previousPtr !== false
                && $this->isObjectOperator($tokens, $previousPtr) === true
            ) {
                $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previousPtr - 1), null, true);

                continue;
            }

            if (
                $previousPtr !== false
                && $tokens[$previousPtr]['code'] === T_DOUBLE_COLON
            ) {
                return $this->recordRoots($walked, false);
            }

            return $this->recordRoots($walked, $code === T_VARIABLE ? $ptr : false);
        }

        return $this->recordRoots($walked, false);
    }

    private function discardRootsOfOtherStreams(File $phpcsFile): void
    {
        $tokenStreams = $this->tokenStreams;

        $key = $tokenStreams->key($phpcsFile);

        if ($this->rootsKey === $key) {
            $this->cacheCounts['roots.hits']++;

            return;
        }

        $this->cacheCounts['roots.builds']++;
        $this->rootsKey = $key;
        $this->roots = [];
        $this->walkSteps = 0;
    }

    private function recordRoots(array $walked, int|false $result): int|false
    {
        foreach ($walked as $ptr) {
            $this->roots[$ptr] = $result;
        }

        return $result;
    }

    private function rootInsideGroup(File $phpcsFile, int $openerPtr, int $closerPtr): int|false
    {
        $rootPtr = $this->rootBefore($phpcsFile, $closerPtr);

        if ($rootPtr === false) {
            return false;
        }

        $firstInGroupPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), $closerPtr, true);

        return $rootPtr === $firstInGroupPtr ? $openerPtr : false;
    }

    private function isPrecededByAnotherHop(File $phpcsFile, int $operatorPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($operatorPtr - 1), null, true);

        if (
            $receiverPtr === false
            || $tokens[$receiverPtr]['code'] !== T_STRING
        ) {
            return false;
        }

        $previousPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($receiverPtr - 1), null, true);

        return $previousPtr !== false && $this->isObjectOperator($tokens, $previousPtr) === true;
    }

    private function isObjectOperator(array $tokens, int $ptr): bool
    {
        return $tokens[$ptr]['code'] === T_OBJECT_OPERATOR
            || $tokens[$ptr]['code'] === T_NULLSAFE_OBJECT_OPERATOR;
    }

    private function isInvokedOn(array $tokens, int|false $beforeOpenerPtr): bool
    {
        if ($beforeOpenerPtr === false) {
            return false;
        }

        return in_array(
            $tokens[$beforeOpenerPtr]['code'],
            [
                T_STRING,
                T_VARIABLE,
                T_CLOSE_PARENTHESIS,
                T_CLOSE_SQUARE_BRACKET,
                T_CLOSE_CURLY_BRACKET,
            ],
            true
        );
    }

    private function isGroupingParenthesis(array $tokens, int|false $beforeOpenerPtr): bool
    {
        if ($beforeOpenerPtr === false) {
            return true;
        }

        $code = $tokens[$beforeOpenerPtr]['code'];

        return in_array($code, self::GROUP_PRECEDERS, true)
            || in_array($tokens[$beforeOpenerPtr]['type'], self::GROUP_PRECEDER_TYPES, true)
            || isset(Tokens::$assignmentTokens[$code]) === true
            || isset(Tokens::$operators[$code]) === true
            || isset(Tokens::$comparisonTokens[$code]) === true
            || isset(Tokens::$booleanOperators[$code]) === true
            || isset(Tokens::$castTokens[$code]) === true;
    }

    private function openerOf(array $tokens, int $ptr): int|false
    {
        if ($tokens[$ptr]['code'] === T_CLOSE_PARENTHESIS) {
            return $tokens[$ptr]['parenthesis_opener'] ?? false;
        }

        return $tokens[$ptr]['bracket_opener'] ?? false;
    }
}
