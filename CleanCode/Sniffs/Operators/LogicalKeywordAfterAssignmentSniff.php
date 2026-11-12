<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class LogicalKeywordAfterAssignmentSniff implements Sniff
{
    public const LOGICAL_KEYWORDS = [
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
    ];

    public const SYMBOL_OPERATORS = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
    ];

    public const ASSIGNMENT_OPERATORS = [
        T_EQUAL,
        T_PLUS_EQUAL,
        T_MINUS_EQUAL,
        T_MUL_EQUAL,
        T_DIV_EQUAL,
        T_MOD_EQUAL,
        T_POW_EQUAL,
        T_CONCAT_EQUAL,
        T_COALESCE_EQUAL,
        T_AND_EQUAL,
        T_OR_EQUAL,
        T_XOR_EQUAL,
        T_SL_EQUAL,
        T_SR_EQUAL,
    ];

    public const NON_ASSIGNMENT_OPERATORS = [
        T_DOUBLE_ARROW,
    ];

    public const EXPRESSION_SCOPES = [
        T_CLOSURE,
        T_ANON_CLASS,
        T_MATCH,
    ];

    public const BRACELESS_BODY_SCOPES = [
        T_IF,
        T_ELSEIF,
        T_ELSE,
        T_WHILE,
        T_FOR,
        T_FOREACH,
        T_DO,
        T_DECLARE,
    ];

    public const BLOCK_SCOPES = [
        T_CLASS,
        T_INTERFACE,
        T_TRAIT,
        T_ENUM,
        T_NAMESPACE,
        T_FUNCTION,
        T_USE,
        T_SWITCH,
        T_CASE,
        T_DEFAULT,
        T_TRY,
        T_CATCH,
        T_FINALLY,
    ];

    private const TARGET_TOKENS = [
        T_VARIABLE,
        T_DOLLAR,
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
        T_STATIC,
        T_SELF,
        T_PARENT,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_LIST,
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_CURLY_BRACKET,
    ];

    private const EXPRESSION_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_FN_ARROW,
        T_OPEN_TAG_WITH_ECHO,
    ];

    private const BRACKET_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
    ];

    private const STATEMENT_BOUNDARIES = [
        T_SEMICOLON,
        T_OPEN_TAG,
        T_CLOSE_TAG,
    ];

    private const SUGGESTIONS = [
        'and' => 'Use && instead, or wrap the right-hand side in parentheses.',
        'or' => 'Use || instead, or wrap the right-hand side in parentheses.',
        'xor' => 'Wrap the right-hand side in parentheses.',
    ];

    public function register(): array
    {
        return self::LOGICAL_KEYWORDS;
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $boundary = $this->statementBoundary($phpcsFile, $stackPtr);

        if (
            $boundary === null
            || $this->isDirectlyInParentheses($phpcsFile, $stackPtr) === true
            || $this->startsWithAssignment($phpcsFile, $boundary + 1, $stackPtr) === false
        ) {
            return;
        }

        $keyword = strtolower($phpcsFile->getTokens()[$stackPtr]['content']);

        $phpcsFile->addError(
                'The %s keyword binds looser than the assignment before it,'
                    . ' so only its left operand is assigned. %s',
                $stackPtr,
                'Found',
                [$keyword, self::SUGGESTIONS[$keyword]]
            );
    }

    private function isDirectlyInParentheses(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $parentheses = $tokens[$stackPtr]['nested_parenthesis'] ?? [];

        if ($parentheses === []) {
            return false;
        }

        $scopeOwner = array_key_last($tokens[$stackPtr]['conditions']);
        $scopeOpener = $scopeOwner === null ? -1 : $tokens[$scopeOwner]['scope_opener'];

        return array_key_last($parentheses) > $scopeOpener;
    }

    private function statementBoundary(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $stackPtr - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            $code = $token['code'];

            if (in_array($code, self::EXPRESSION_OPENERS, true) === true) {
                return null;
            }

            if ($this->endsPreviousStatement($phpcsFile, $i) === true) {
                return $i;
            }

            if (in_array($code, self::BRACKET_CLOSERS, true) === true) {
                $i = $token['parenthesis_opener'] ?? $token['bracket_opener'];

                continue;
            }

            if ($code === T_OPEN_CURLY_BRACKET) {
                return $this->isMatchBody($phpcsFile, $i) === true ? null : $i;
            }

            if ($code === T_CLOSE_CURLY_BRACKET) {
                $opener = $this->expressionOpener($phpcsFile, $i);

                if ($opener === null) {
                    return $i;
                }

                $i = $opener;
            }
        }

        return null;
    }

    private function endsPreviousStatement(File $phpcsFile, int $stackPtr): bool
    {
        $code = $phpcsFile->getTokens()[$stackPtr]['code'];

        return in_array($code, self::STATEMENT_BOUNDARIES, true) === true
            || in_array($code, self::BRACELESS_BODY_SCOPES, true) === true
            || $this->opensColonBody($phpcsFile, $stackPtr) === true
            || $this->closesBracelessCondition($phpcsFile, $stackPtr) === true;
    }

    private function opensColonBody(File $phpcsFile, int $stackPtr): bool
    {
        $token = $phpcsFile->getTokens()[$stackPtr];

        return $token['code'] === T_COLON && isset($token['scope_condition']) === true;
    }

    private function closesBracelessCondition(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $owner = $tokens[$stackPtr]['parenthesis_owner'] ?? null;

        return $tokens[$stackPtr]['code'] === T_CLOSE_PARENTHESIS
            && $owner !== null
            && in_array($tokens[$owner]['code'], self::BRACELESS_BODY_SCOPES, true) === true;
    }

    private function isMatchBody(File $phpcsFile, int $opener): bool
    {
        $tokens = $phpcsFile->getTokens();
        $condition = $tokens[$opener]['scope_condition'] ?? null;

        return $condition !== null && $tokens[$condition]['code'] === T_MATCH;
    }

    private function expressionOpener(File $phpcsFile, int $closer): ?int
    {
        $tokens = $phpcsFile->getTokens();
        $condition = $tokens[$closer]['scope_condition'] ?? null;

        if ($condition === null) {
            return $tokens[$closer]['bracket_opener'] ?? null;
        }

        if (in_array($tokens[$condition]['code'], self::EXPRESSION_SCOPES, true) === false) {
            return null;
        }

        return $tokens[$closer]['scope_opener'];
    }

    private function startsWithAssignment(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = $start; $i < $end; $i++) {
            $code = $tokens[$i]['code'];

            if (isset(Tokens::EMPTY_TOKENS[$code]) === true) {
                continue;
            }

            if (in_array($code, self::ASSIGNMENT_OPERATORS, true) === true) {
                return true;
            }

            if (
                in_array($code, self::TARGET_TOKENS, true) === false
                || $this->isStaticDeclaration($phpcsFile, $i) === true
            ) {
                return false;
            }

            $i = $tokens[$i]['parenthesis_closer'] ?? $tokens[$i]['bracket_closer'] ?? $i;
        }

        return false;
    }

    private function isStaticDeclaration(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::EMPTY_TOKENS, $stackPtr + 1, null, true);

        return $tokens[$stackPtr]['code'] === T_STATIC
            && ($next === false || $tokens[$next]['code'] !== T_DOUBLE_COLON);
    }
}
