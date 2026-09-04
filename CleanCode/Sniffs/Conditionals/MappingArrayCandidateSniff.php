<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Conditionals;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class MappingArrayCandidateSniff implements Sniff
{
    public $minimumBranches = 3;

    private const EQUALITY_OPERATORS = [
        T_IS_IDENTICAL,
        T_IS_EQUAL,
    ];

    private const SCALAR_LITERALS = [
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_TRUE,
        T_FALSE,
    ];

    private const NUMERIC_LITERALS = [
        T_LNUMBER,
        T_DNUMBER,
    ];

    private const SIGN_TOKENS = [
        T_MINUS,
        T_PLUS,
    ];

    private const OPERAND_POSITION_TOKENS = [
        T_COMMA,
        T_DOUBLE_ARROW,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_SQUARE_BRACKET,
    ];

    private const VALUE_TOKENS = [
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_TRUE,
        T_FALSE,
        T_NULL,
        T_VARIABLE,
        T_STRING,
        T_SELF,
        T_STATIC,
        T_PARENT,
        T_DOUBLE_COLON,
        T_NS_SEPARATOR,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OPEN_SQUARE_BRACKET,
        T_CLOSE_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_CLOSE_SHORT_ARRAY,
        T_DOUBLE_ARROW,
        T_COMMA,
        T_MINUS,
        T_PLUS,
    ];

    private array $scanCounts = [
        'braceless.headRefusals' => 0,
        'braceless.endScans' => 0,
    ];

    public function register(): array
    {
        return [T_IF];
    }

    public function scanCounts(): array
    {
        return $this->scanCounts;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isChainHead($phpcsFile, $stackPtr) === false) {
            return;
        }

        $clauses = $this->collectClauses($phpcsFile, $stackPtr);

        if (
            $clauses === null
            || count($clauses) < (int) $this->minimumBranches
        ) {
            return;
        }

        $subject = $this->sharedSubject($clauses);

        if (
            $subject === null
            || $this->bodiesAgree($clauses) === false
        ) {
            return;
        }

        $phpcsFile->addWarning(
            "Mapping-array candidate: %d branches all compare \"%s\" against a scalar literal and"
                . " do"
                . ' nothing but produce a value. Prefer a mapping array or match where one'
                . ' applies.',
            $stackPtr,
            'IfChain',
            [count($clauses), $subject]
        );
    }

    private function isChainHead(File $phpcsFile, int $stackPtr): bool
    {
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

        if ($previous === false) {
            return true;
        }

        return $phpcsFile->getTokens()[$previous]['code'] !== T_ELSE;
    }

    private function collectClauses(File $phpcsFile, int $stackPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $clauses = [];
        $pointer = $stackPtr;

        while ($pointer !== null) {
            $code = $tokens[$pointer]['code'];

            if ($code === T_ELSE) {
                $next = $phpcsFile->findNext(Tokens::$emptyTokens, $pointer + 1, null, true);

                // A spaced `else if`: the trailing `if` carries the condition
                // and the scope, so hand the clause to it.
                if (
                    $next !== false
                    && $tokens[$next]['code'] === T_IF
                ) {
                    $pointer = $next;

                    continue;
                }
            }

            if (in_array($code, [T_IF, T_ELSEIF, T_ELSE], true) === false) {
                break;
            }

            $subject = null;

            if ($code !== T_ELSE) {
                $subject = $this->conditionSubject($phpcsFile, $pointer);

                if ($subject === null) {
                    return null;
                }
            }

            $extent = $this->clauseExtent($phpcsFile, $pointer);

            if ($extent === null) {
                return null;
            }

            $body = $this->bodyShape($phpcsFile, $extent['bodyStart'], $extent['bodyEnd']);

            if ($body === null) {
                return null;
            }

            $clauses[] = $body + ['subject' => $subject];

            if ($code === T_ELSE) {
                break;
            }

            $pointer = $extent['next'];
        }

        return $clauses;
    }

    private function conditionSubject(File $phpcsFile, int $clausePtr): ?string
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['parenthesis_opener'], $tokens[$clausePtr]['parenthesis_closer']) === false) {
            return null;
        }

        $condition = $this->significantTokens(
            $phpcsFile,
            $tokens[$clausePtr]['parenthesis_opener'] + 1,
            $tokens[$clausePtr]['parenthesis_closer'] - 1
        );

        $operators = [];

        foreach ($condition as $index => $pointer) {
            if (in_array($tokens[$pointer]['code'], self::EQUALITY_OPERATORS, true) === true) {
                $operators[] = $index;
            }
        }

        if (count($operators) !== 1) {
            return null;
        }

        $left = array_slice($condition, 0, $operators[0]);
        $right = array_slice($condition, $operators[0] + 1);

        if (
            $this->isPlainVariable($tokens, $left) === true
            && $this->isScalarLiteral($tokens, $right) === true
        ) {
            return $tokens[$left[0]]['content'];
        }

        if (
            $this->isPlainVariable($tokens, $right) === true
            && $this->isScalarLiteral($tokens, $left) === true
        ) {
            return $tokens[$right[0]]['content'];
        }

        return null;
    }

    private function isPlainVariable(array $tokens, array $pointers): bool
    {
        return count($pointers) === 1 && $tokens[$pointers[0]]['code'] === T_VARIABLE;
    }

    private function isScalarLiteral(array $tokens, array $pointers): bool
    {
        if (count($pointers) === 1) {
            return in_array($tokens[$pointers[0]]['code'], self::SCALAR_LITERALS, true);
        }

        return count($pointers) === 2
            && in_array($tokens[$pointers[0]]['code'], self::SIGN_TOKENS, true) === true
            && in_array($tokens[$pointers[1]]['code'], self::NUMERIC_LITERALS, true) === true;
    }

    private function clauseExtent(File $phpcsFile, int $clausePtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$clausePtr]['scope_opener'], $tokens[$clausePtr]['scope_closer']) === true) {
            $closer = $tokens[$clausePtr]['scope_closer'];
            $isBraced = $tokens[$closer]['code'] === T_CLOSE_CURLY_BRACKET;
            $next = $isBraced === true
                ? $phpcsFile->findNext(Tokens::$emptyTokens, $closer + 1, null, true)
                : $closer;

            return [
                'bodyStart' => $tokens[$clausePtr]['scope_opener'] + 1,
                'bodyEnd' => $closer - 1,
                'next' => $next === false ? null : $next,
            ];
        }

        $afterCondition = isset($tokens[$clausePtr]['parenthesis_closer']) === true
            ? $tokens[$clausePtr]['parenthesis_closer'] + 1
            : $clausePtr + 1;
        // findEndOfStatement() reads the token it is handed, so it has to start
        // on the statement's first real token, never the whitespace before it.
        $bodyStart = $phpcsFile->findNext(Tokens::$emptyTokens, $afterCondition, null, true);

        if ($bodyStart === false) {
            return null;
        }

        if ($this->isStatementHead($tokens[$bodyStart]['code']) === false) {
            $this->scanCounts['braceless.headRefusals']++;

            return null;
        }

        $this->scanCounts['braceless.endScans']++;
        $bodyEnd = $phpcsFile->findEndOfStatement($bodyStart);

        if ($bodyEnd <= $bodyStart) {
            return null;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, $bodyEnd + 1, null, true);

        return [
            'bodyStart' => $bodyStart,
            'bodyEnd' => $bodyEnd,
            'next' => $next === false ? null : $next,
        ];
    }

    private function bodyShape(File $phpcsFile, int $bodyStart, int $bodyEnd): ?array
    {
        $tokens = $phpcsFile->getTokens();
        $last = $phpcsFile->findPrevious(Tokens::$emptyTokens, $bodyEnd, $bodyStart, true);

        // A body that does not end at a semicolon is not one statement: an
        // inner `if`, a loop, or a nested block all end on a brace instead.
        if (
            $last === false
            || $tokens[$last]['code'] !== T_SEMICOLON
        ) {
            return null;
        }

        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $bodyStart, $last, true);

        if (
            $first === false
            || $this->isStatementHead($tokens[$first]['code']) === false
        ) {
            return null;
        }

        if ($tokens[$first]['code'] === T_RETURN) {
            return $this->isValueExpression($phpcsFile, $first + 1, $last - 1) === true
                ? ['kind' => 'return', 'target' => null]
                : null;
        }

        $operator = $phpcsFile->findNext(Tokens::$emptyTokens, $first + 1, $last, true);

        if (
            $operator === false
            || $tokens[$operator]['code'] !== T_EQUAL
        ) {
            return null;
        }

        return $this->isValueExpression($phpcsFile, $operator + 1, $last - 1) === true
            ? ['kind' => 'assign', 'target' => $tokens[$first]['content']]
            : null;
    }

    private function isStatementHead(int|string $code): bool
    {
        return $code === T_RETURN || $code === T_VARIABLE;
    }

    private function isValueExpression(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = $this->significantTokens($phpcsFile, $start, $end);

        if ($pointers === []) {
            return false;
        }

        foreach ($pointers as $index => $pointer) {
            $code = $tokens[$pointer]['code'];

            if (in_array($code, self::VALUE_TOKENS, true) === false) {
                return false;
            }

            if (
                in_array($code, self::SIGN_TOKENS, true) === true
                && $this->isUnarySign($tokens, $pointers, $index) === false
            ) {
                return false;
            }
        }

        return true;
    }

    private function isUnarySign(array $tokens, array $pointers, int $index): bool
    {
        $next = $pointers[$index + 1] ?? null;

        if (
            $next === null
            || in_array($tokens[$next]['code'], self::NUMERIC_LITERALS, true) === false
        ) {
            return false;
        }

        if ($index === 0) {
            return true;
        }

        return in_array($tokens[$pointers[$index - 1]]['code'], self::OPERAND_POSITION_TOKENS, true);
    }

    private function sharedSubject(array $clauses): ?string
    {
        $subjects = array_unique(array_filter(
            array_column($clauses, 'subject'),
            static fn (?string $subject): bool => $subject !== null
        ));

        return count($subjects) === 1 ? (string) reset($subjects) : null;
    }

    private function bodiesAgree(array $clauses): bool
    {
        if (count(array_unique(array_column($clauses, 'kind'))) !== 1) {
            return false;
        }

        // Every `return` branch carries a null target, so one shared value here
        // means "all returns" just as much as it means "one assignment target".
        return count(array_unique(array_column($clauses, 'target'))) === 1;
    }

    private function significantTokens(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $pointers = [];

        for ($pointer = $start; $pointer <= $end; $pointer++) {
            if (
                isset($tokens[$pointer]) === true
                && isset(Tokens::$emptyTokens[$tokens[$pointer]['code']]) === false
            ) {
                $pointers[] = $pointer;
            }
        }

        return $pointers;
    }
}
