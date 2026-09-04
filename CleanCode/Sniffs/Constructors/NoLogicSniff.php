<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Constructors;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class NoLogicSniff implements Sniff
{
    private const BLOCK_STATEMENT_TOKENS = [
        T_IF,
        T_ELSEIF,
        T_ELSE,
        T_FOR,
        T_FOREACH,
        T_WHILE,
        T_DO,
        T_SWITCH,
        T_TRY,
        T_CATCH,
        T_FINALLY,
        T_DECLARE,
        T_FUNCTION,
    ];

    private const CONTINUATION_KEYWORDS = [
        T_ELSEIF,
        T_ELSE,
        T_CATCH,
        T_FINALLY,
    ];

    private const ALTERNATIVE_SYNTAX_CLOSERS = [
        T_ENDIF,
        T_ENDFOR,
        T_ENDFOREACH,
        T_ENDWHILE,
        T_ENDSWITCH,
        T_ENDDECLARE,
    ];

    private const BRACKET_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_CURLY_BRACKET,
    ];

    private const BRACKET_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    private const INTERPOLATABLE_STRING_TOKENS = [
        T_DOUBLE_QUOTED_STRING,
        T_HEREDOC,
    ];

    private const INVOKING_TOKENS = [
        T_BACKTICK,
        T_NEW,
        T_CLONE,
        T_EXIT,
        T_PRINT,
        T_THROW,
        T_YIELD,
        T_YIELD_FROM,
        T_INCLUDE,
        T_INCLUDE_ONCE,
        T_REQUIRE,
        T_REQUIRE_ONCE,
    ];

    private const WRITING_TOKENS = [
        T_INC,
        T_DEC,
    ];

    private const NON_WRITING_ASSIGNMENT_TOKENS = [
        T_DOUBLE_ARROW,
    ];

    private const GROUPING_PARENTHESIS_PRECEDERS = [
        // Arithmetic and bitwise.
        T_PLUS,
        T_MINUS,
        T_MULTIPLY,
        T_DIVIDE,
        T_MODULUS,
        T_POW,
        T_BITWISE_AND,
        T_BITWISE_OR,
        T_BITWISE_XOR,
        T_BITWISE_NOT,
        T_SL,
        T_SR,
        T_STRING_CONCAT,
        // Comparison.
        T_IS_EQUAL,
        T_IS_NOT_EQUAL,
        T_IS_IDENTICAL,
        T_IS_NOT_IDENTICAL,
        T_IS_GREATER_OR_EQUAL,
        T_IS_SMALLER_OR_EQUAL,
        T_GREATER_THAN,
        T_LESS_THAN,
        T_SPACESHIP,
        // Logical.
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_BOOLEAN_NOT,
        T_LOGICAL_AND,
        T_LOGICAL_OR,
        T_LOGICAL_XOR,
        // Conditional.
        T_INLINE_THEN,
        T_INLINE_ELSE,
        T_COALESCE,
        // Type checks and error control.
        T_INSTANCEOF,
        T_ASPERAND,
        // Casts, each a single token, so the parenthesis after one is grouping.
        T_INT_CAST,
        T_DOUBLE_CAST,
        T_STRING_CAST,
        T_ARRAY_CAST,
        T_OBJECT_CAST,
        T_BOOL_CAST,
        T_UNSET_CAST,
        T_BINARY_CAST,
        // Openers and separators.
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_CURLY_BRACKET,
        T_COMMA,
        T_DOUBLE_ARROW,
    ];

    private const GROUP_CLOSER_KEYS = [
        'parenthesis_closer',
        'bracket_closer',
        'scope_closer',
    ];

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || strtolower($name) !== '__construct'
        ) {
            return;
        }

        // A constructor is a method of an object-oriented container. A free
        // function named __construct is legal PHP but not a constructor, so its
        // innermost enclosing scope must be a class/trait/enum/interface —
        // mirrors the guard convention in the sibling DisallowStaticMembersSniff.
        $conditions = $tokens[$stackPtr]['conditions'];

        if (
            $conditions === []
            || in_array(end($conditions), Tokens::$ooScopeTokens, true) === false
        ) {
            return;
        }

        // Abstract and interface constructors declare no body.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $opener = $tokens[$stackPtr]['scope_opener'];
        $closer = $tokens[$stackPtr]['scope_closer'];

        $statementStart = $phpcsFile->findNext(Tokens::$emptyTokens, ($opener + 1), $closer, true);

        while (
            $statementStart !== false
            && $statementStart < $closer
        ) {
            $statementEnd = $this->endOfStatement($phpcsFile, $statementStart, $closer);

            if (
                $this->isPropertyAssignment($phpcsFile, $statementStart, $statementEnd) === false
                && $this->isParentConstructorCall($phpcsFile, $statementStart, $statementEnd) === false
            ) {
                $phpcsFile->addError(
                    'Constructors must contain no logic, only property assignments; move this'
                        . ' statement '
                        . 'into a named constructor, factory, or collaborator',
                    $statementStart,
                    'LogicFound'
                );
            }

            $statementStart = $phpcsFile->findNext(Tokens::$emptyTokens, ($statementEnd + 1), $closer, true);
        }
    }

    private function endOfStatement(File $phpcsFile, int $start, int $limit): int
    {
        $tokens = $phpcsFile->getTokens();
        $end = $this->endOfClause($phpcsFile, $start, $limit);

        while (true) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), $limit, true);

            if ($next === false) {
                break;
            }

            $code = $tokens[$next]['code'];

            if (in_array($code, self::CONTINUATION_KEYWORDS, true)) {
                $end = $this->endOfClause($phpcsFile, $next, $limit);

                continue;
            }

            // The `while (...);` tail of a `do … while` carries the loop
            // condition only (no scope of its own).
            if (
                $code === T_WHILE
                && $tokens[$start]['code'] === T_DO
                && isset($tokens[$next]['scope_opener']) === false
            ) {
                $end = $this->endOfSimpleStatement($phpcsFile, $next, $limit);

                continue;
            }

            break;
        }

        return $end;
    }

    private function endOfClause(File $phpcsFile, int $start, int $limit): int
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$start]['code'];

        // A free `{ … }` block: PHPCS gives it a bracket pair, not a scope.
        if ($code === T_OPEN_CURLY_BRACKET) {
            return $this->groupCloser($tokens, $start, $limit);
        }

        if (in_array($code, self::BLOCK_STATEMENT_TOKENS, true) === false) {
            return $this->endOfSimpleStatement($phpcsFile, $start, $limit);
        }

        if (isset($tokens[$start]['scope_closer']) === false) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($start + 1), $limit, true);

            if (
                $next !== false
                && in_array($tokens[$next]['code'], self::BLOCK_STATEMENT_TOKENS, true)
            ) {
                return $this->endOfClause($phpcsFile, $next, $limit);
            }

            return $this->endOfSimpleStatement($phpcsFile, $start, $limit);
        }

        $closer = $tokens[$start]['scope_closer'];
        $closerCode = $tokens[$closer]['code'];

        if (in_array($closerCode, self::ALTERNATIVE_SYNTAX_CLOSERS, true)) {
            $semicolon = $phpcsFile->findNext(Tokens::$emptyTokens, ($closer + 1), $limit, true);

            if (
                $semicolon !== false
                && $tokens[$semicolon]['code'] === T_SEMICOLON
            ) {
                return $semicolon;
            }

            return $closer;
        }

        if (in_array($closerCode, self::CONTINUATION_KEYWORDS, true)) {
            return ($closer - 1);
        }

        return $closer;
    }

    private function endOfSimpleStatement(File $phpcsFile, int $start, int $limit): int
    {
        $tokens = $phpcsFile->getTokens();

        for ($ptr = $start; $ptr <= $limit; $ptr++) {
            $ptr = $this->groupCloser($tokens, $ptr, $limit);

            if ($tokens[$ptr]['code'] === T_SEMICOLON) {
                return $ptr;
            }
        }

        return $limit;
    }

    private function groupCloser(array $tokens, int $ptr, int $limit): int
    {
        foreach (self::GROUP_CLOSER_KEYS as $key) {
            if (
                isset($tokens[$ptr][$key])
                && $tokens[$ptr][$key] > $ptr
            ) {
                return min($tokens[$ptr][$key], $limit);
            }
        }

        return $ptr;
    }

    private function isPropertyAssignment(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (
            $tokens[$start]['code'] !== T_VARIABLE
            || $tokens[$start]['content'] !== '$this'
        ) {
            return false;
        }

        $access = $phpcsFile->findNext(Tokens::$emptyTokens, ($start + 1), ($end + 1), true);

        if (
            $access === false
            || $tokens[$access]['code'] !== T_OBJECT_OPERATOR
        ) {
            return false;
        }

        return $this->hasPlainAssignmentTarget($phpcsFile, $start, $end);
    }

    private function hasPlainAssignmentTarget(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $depth = 0;

        for ($ptr = $start; $ptr <= $end; $ptr++) {
            $code = $tokens[$ptr]['code'];

            // The statement's own assignment operator: the target ends here, and
            // it is plain. Tested before the rejection below, which every other
            // assignment operator — and this one nested inside a bracket — falls
            // into.
            if (
                $code === T_EQUAL
                && $depth === 0
            ) {
                return true;
            }

            if ($this->writes($code) === true) {
                return false;
            }

            if (in_array($code, self::INVOKING_TOKENS, true)) {
                return false;
            }

            if (
                $code === T_OPEN_PARENTHESIS
                && $this->isGroupingParenthesis($phpcsFile, $ptr, $start) === false
            ) {
                return false;
            }

            if (
                in_array($code, self::INTERPOLATABLE_STRING_TOKENS, true)
                && $this->hasComplexInterpolation($tokens[$ptr]['content']) === true
            ) {
                return false;
            }

            // Disjoint token sets, so these read as one choice written apart.
            if (in_array($code, self::BRACKET_OPENERS, true)) {
                $depth++;
            }

            if (in_array($code, self::BRACKET_CLOSERS, true)) {
                $depth--;
            }
        }

        return false;
    }

    private function writes(int|string $code): bool
    {
        if (in_array($code, self::WRITING_TOKENS, true)) {
            return true;
        }

        return isset(Tokens::$assignmentTokens[$code])
            && in_array($code, self::NON_WRITING_ASSIGNMENT_TOKENS, true) === false;
    }

    private function isGroupingParenthesis(File $phpcsFile, int $ptr, int $start): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), $start, true);

        // $start is the statement's own `$this`, so a parenthesis in the target
        // always has a token before it. Read an unexpected miss as a call.
        if ($previous === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        return in_array($tokens[$previous]['code'], self::GROUPING_PARENTHESIS_PRECEDERS, true);
    }

    private function hasComplexInterpolation(string $content): bool
    {
        $unescaped = preg_replace('/\\\\[\\\\$]/', '', $content) ?? $content;

        if (preg_match('/(?<!\\\\)\{\$/', $unescaped) !== 0) {
            return true;
        }

        return str_contains($unescaped, '${');
    }

    private function isParentConstructorCall(File $phpcsFile, int $start, int $end): bool
    {
        $open = $this->parentConstructorParenthesis($phpcsFile, $start, $end);

        if (
            $open === null
            || $this->isFirstClassCallable($phpcsFile, $open) === true
        ) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        $after = $phpcsFile->findNext(
            Tokens::$emptyTokens,
            ($tokens[$open]['parenthesis_closer'] + 1),
            ($end + 1),
            true
        );

        return $after === false || $tokens[$after]['code'] === T_SEMICOLON;
    }

    private function isFirstClassCallable(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$openerPtr]['parenthesis_closer'];

        $ellipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), $closer, true);

        if (
            $ellipsis === false
            || $tokens[$ellipsis]['code'] !== T_ELLIPSIS
        ) {
            return false;
        }

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsis + 1), $closer, true) === false;
    }

    private function parentConstructorParenthesis(File $phpcsFile, int $start, int $end): ?int
    {
        $method = $this->parentConstructorName($phpcsFile, $start, $end);

        if ($method === null) {
            return null;
        }

        $tokens = $phpcsFile->getTokens();
        $open = $phpcsFile->findNext(Tokens::$emptyTokens, ($method + 1), ($end + 1), true);

        if (
            $open === false
            || $tokens[$open]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return null;
        }

        return isset($tokens[$open]['parenthesis_closer']) === true ? $open : null;
    }

    private function parentConstructorName(File $phpcsFile, int $start, int $end): ?int
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$start]['code'] !== T_PARENT) {
            return null;
        }

        $colon = $phpcsFile->findNext(Tokens::$emptyTokens, ($start + 1), ($end + 1), true);

        if (
            $colon === false
            || $tokens[$colon]['code'] !== T_DOUBLE_COLON
        ) {
            return null;
        }

        $method = $phpcsFile->findNext(Tokens::$emptyTokens, ($colon + 1), ($end + 1), true);

        if (
            $method === false
            || $tokens[$method]['code'] !== T_STRING
        ) {
            return null;
        }

        return strtolower($tokens[$method]['content']) === '__construct' ? $method : null;
    }
}
