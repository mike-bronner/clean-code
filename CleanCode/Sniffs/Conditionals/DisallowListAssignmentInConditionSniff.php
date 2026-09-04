<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowListAssignmentInConditionSniff implements Sniff
{
    private const CONDITION_OWNERS = [T_IF, T_ELSEIF, T_FOR, T_SWITCH, T_WHILE, T_MATCH];

    public function register(): array
    {
        return [T_LIST];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        // An unterminated list() carries a null closer, so there is no
        // position to look past for an "=". Refuse rather than search from a
        // made-up offset. Dropping this guard does not change what any
        // fixture here reports — a search from the bogus offset lands on a
        // token that is not "=", and the check below rejects it — so the
        // guard is explicitness, not a behaviour the tests can pin.
        $closer = $tokens[$stackPtr]['parenthesis_closer'] ?? null;

        if ($closer === null) {
            return;
        }

        $assignment = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), null, true);

        if (
            $assignment === false
            || $tokens[$assignment]['code'] !== T_EQUAL
        ) {
            return;
        }

        if ($this->isInsideCondition($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Variable assignment found within a condition. Did you mean to do a comparison ?',
            $assignment,
            'Found'
        );
    }

    private function isInsideCondition(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['nested_parenthesis']) === false) {
            return false;
        }

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis']) as $opener) {
            if (isset($tokens[$opener]['parenthesis_owner']) === false) {
                continue;
            }

            $owner = $tokens[$opener]['parenthesis_owner'];

            if (in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true) === false) {
                continue;
            }

            if (
                $tokens[$owner]['code'] === T_FOR
                && $this->isInForConditionSection($phpcsFile, $owner, $stackPtr) === false
            ) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function isInForConditionSection(File $phpcsFile, int $forPtr, int $stackPtr): bool
    {
        $separators = $this->headerSeparators($phpcsFile, $forPtr);

        if (isset($separators[0], $separators[1]) === false) {
            return false;
        }

        return $stackPtr > $separators[0] && $stackPtr < $separators[1];
    }

    private function headerSeparators(File $phpcsFile, int $forPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$forPtr]['parenthesis_closer'];
        $separators = [];

        for ($i = ($tokens[$forPtr]['parenthesis_opener'] + 1); $i < $closer; $i++) {
            if ($tokens[$i]['code'] === T_SEMICOLON) {
                $separators[] = $i;

                continue;
            }

            $scopeCloser = $tokens[$i]['scope_closer'] ?? null;

            if (
                $scopeCloser !== null
                && $tokens[$scopeCloser]['code'] === T_CLOSE_CURLY_BRACKET
            ) {
                $i = $scopeCloser;
            }
        }

        return $separators;
    }
}
