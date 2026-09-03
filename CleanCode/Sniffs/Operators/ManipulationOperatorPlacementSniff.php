<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use MikeBronner\CleanCode\Support\ConditionOperatorOwnership;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class ManipulationOperatorPlacementSniff implements Sniff
{
    private const MANIPULATION_OPERATORS = [
        T_PLUS,          // +
        T_MINUS,         // -
        T_MULTIPLY,      // *
        T_DIVIDE,        // /
        T_MODULUS,       // %
        T_POW,           // **
        T_BITWISE_AND,   // &
        T_BITWISE_OR,    // |
        T_BITWISE_XOR,   // ^
        T_SL,            // <<
        T_SR,            // >>
    ];

    private const UNARY_CAPABLE = [
        T_PLUS,
        T_MINUS,
    ];

    private const OPERAND_END_TOKENS = [
        T_VARIABLE,
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_DOUBLE_QUOTED_STRING,
        T_END_HEREDOC,
        T_END_NOWDOC,
        T_BACKTICK,
        T_STRING,
        T_TRUE,
        T_FALSE,
        T_NULL,
        T_INC,
        T_DEC,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    private const VALUE_PRODUCING_SCOPE_OWNERS = [
        T_ANON_CLASS,
        T_CLOSURE,
        T_MATCH,
    ];

    private const CURLY_DEREFERENCE_INTRODUCERS = [
        T_DOLLAR,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    private const STATEMENT_ANCHOR_GROUPING_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
    ];

    private const STATEMENT_ANCHOR_SEPARATORS = [
        T_COMMA,
        T_DOUBLE_ARROW,
    ];

    private const STATEMENT_ANCHOR_BOUNDARY_TOKENS = [
        T_OPEN_CURLY_BRACKET,
        T_OBJECT,
        T_OPEN_TAG,
        T_OPEN_TAG_WITH_ECHO,
        T_CLOSE_TAG,
        T_MATCH_ARROW,
    ];

    public function register(): array
    {
        return self::MANIPULATION_OPERATORS;
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        // Inside an if/elseif/while/for condition, OneConditionPerLine reports
        // (and fixes) the same wrap wholesale, so stand down there exactly
        // where CleanCode.Operators.OperatorLineBreak does.
        if (ConditionOperatorOwnership::isDeferredToOneConditionPerLine($phpcsFile, $stackPtr) === true) {
            return;
        }

        $tokens = $phpcsFile->getTokens();

        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $previous === false
            || $next === false
        ) {
            return;
        }

        // The operator only "trails" when its left-hand operand ends on the
        // operator's own line and the right-hand operand lives on a later one.
        // A left operand on an earlier line means the operator already leads —
        // including when it sits alone on its own line — which is compliant.
        if ($tokens[$previous]['line'] !== $tokens[$stackPtr]['line']) {
            return;
        }

        // Both operands on the operator's line: inline usage, compliant.
        if ($tokens[$next]['line'] === $tokens[$stackPtr]['line']) {
            return;
        }

        // A reference `&` (by-ref parameter, return, assignment, foreach, array
        // element, or by-ref call argument) is not a bitwise-AND manipulation
        // operator, whatever token precedes it. PHP_CodeSniffer resolves the
        // reference-vs-bitwise question directly, so defer to it.
        if (
            $tokens[$stackPtr]['code'] === T_BITWISE_AND
            && $phpcsFile->isReference($stackPtr) === true
        ) {
            return;
        }

        // A `|`/`&` separating types in a `catch (TypeA | TypeB $e)` clause is a
        // type-union/intersection separator, not a bitwise manipulation operator.
        // PHP_CodeSniffer retokenises union/intersection types to T_TYPE_UNION /
        // T_TYPE_INTERSECTION in parameter, return, and property positions (which
        // are never registered here), but leaves the catch-clause separator as
        // T_BITWISE_OR/T_BITWISE_AND, so exempt it by its enclosing context.
        if (
            in_array($tokens[$stackPtr]['code'], [T_BITWISE_OR, T_BITWISE_AND], true) === true
            && $this->isCatchTypeSeparator($phpcsFile, $stackPtr) === true
        ) {
            return;
        }

        // A unary sign (`-5`, `+5`) carries no left-hand operand, so `+`/`-` are
        // manipulation operators only when a real operand ends the previous line.
        if (
            in_array($tokens[$stackPtr]['code'], self::UNARY_CAPABLE, true) === true
            && $this->endsLeftOperand($phpcsFile, $previous) === false
        ) {
            return;
        }

        $error = "Manipulation operator \"%s\" must start the continuation line, not trail the previous one";
        $code = 'OperatorNotLeading';
        $data = [$tokens[$stackPtr]['content']];

        // Moving the operator across a comment that sits between the two
        // operands would reorder the comment, so leave that case unfixed.
        $hasComment = $phpcsFile->findNext(Tokens::$commentTokens, ($previous + 1), $next) !== false;

        if ($hasComment === true) {
            $phpcsFile->addError($error, $stackPtr, $code, $data);

            return;
        }

        $fix = $phpcsFile->addFixableError($error, $stackPtr, $code, $data);

        if ($fix === false) {
            return;
        }

        $this->moveOperatorToNextLine($phpcsFile, $stackPtr);
    }

    private function endsLeftOperand(File $phpcsFile, int $previous): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$previous]['code'];

        if ($code === T_CLOSE_CURLY_BRACKET) {
            return $this->closesValue($phpcsFile, $previous);
        }

        return in_array($code, self::OPERAND_END_TOKENS, true) === true
            || isset(Tokens::$magicConstants[$code]) === true;
    }

    private function closesValue(File $phpcsFile, int $closer): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$closer]['scope_condition']) === true) {
            $owner = $tokens[$tokens[$closer]['scope_condition']]['code'];

            return in_array($owner, self::VALUE_PRODUCING_SCOPE_OWNERS, true);
        }

        if (isset($tokens[$closer]['bracket_opener']) === false) {
            return false;
        }

        $introducer = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($tokens[$closer]['bracket_opener'] - 1),
            null,
            true
        );

        return $introducer !== false
            && in_array($tokens[$introducer]['code'], self::CURLY_DEREFERENCE_INTRODUCERS, true) === true;
    }

    private function isCatchTypeSeparator(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $opener = array_key_last($tokens[$stackPtr]['nested_parenthesis']);

        return isset($tokens[$opener]['parenthesis_owner']) === true
            && $tokens[$tokens[$opener]['parenthesis_owner']]['code'] === T_CATCH;
    }

    private function moveOperatorToNextLine(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $indent = $this->continuationIndent($phpcsFile, $stackPtr);

        $phpcsFile->fixer
            ->beginChangeset();

        for ($i = ($stackPtr - 1); $tokens[$i]['code'] === T_WHITESPACE; $i--) {
            $phpcsFile->fixer
                ->replaceToken($i, '');
        }

        for ($i = ($stackPtr + 1); $tokens[$i]['code'] === T_WHITESPACE; $i++) {
            $phpcsFile->fixer
                ->replaceToken($i, '');
        }

        $phpcsFile->fixer
            ->addContentBefore($stackPtr, $phpcsFile->eolChar . $indent);
        $phpcsFile->fixer
            ->addContent($stackPtr, ' ');

        $phpcsFile->fixer
            ->endChangeset();
    }

    private function outermostStatementStart(File $phpcsFile, int $stackPtr): int
    {
        $tokens = $phpcsFile->getTokens();
        $start = $phpcsFile->findStartOfStatement($stackPtr);

        while ($start > 0) {
            $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($start - 1), null, true);

            if ($before === false) {
                break;
            }

            $escapes = $this->escapesStatementAnchor($phpcsFile, $before)
                || in_array($tokens[$start]['code'], self::STATEMENT_ANCHOR_GROUPING_OPENERS, true);

            if ($escapes === false) {
                break;
            }

            // findStartOfStatement() never returns past the token handed to it,
            // and $before is already before $start, so $start strictly decreases.
            $start = $phpcsFile->findStartOfStatement($before);
        }

        return $start;
    }

    private function escapesStatementAnchor(File $phpcsFile, int $before): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$before]['code'];

        if ($code === T_COLON) {
            $label = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($before - 1), null, true);

            return $label !== false && $tokens[$label]['code'] === T_PARAM_NAME;
        }

        if ($code === T_SEMICOLON) {
            return $this->isForHeaderSeparator($phpcsFile, $before);
        }

        return in_array($code, self::STATEMENT_ANCHOR_GROUPING_OPENERS, true)
            || in_array($code, self::STATEMENT_ANCHOR_SEPARATORS, true);
    }

    private function isForHeaderSeparator(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (empty($tokens[$stackPtr]['nested_parenthesis']) === true) {
            return false;
        }

        $opener = max(array_keys($tokens[$stackPtr]['nested_parenthesis']));

        if (isset($tokens[$opener]['parenthesis_owner']) === false) {
            return false;
        }

        $owner = $tokens[$opener]['parenthesis_owner'];

        if ($tokens[$owner]['code'] !== T_FOR) {
            return false;
        }

        $dividers = ConditionOperatorOwnership::checkedRegion($phpcsFile, $owner);

        return $dividers !== null && in_array($stackPtr, $dividers, true);
    }

    private function continuationIndent(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $start = $this->outermostStatementStart($phpcsFile, $stackPtr);
        $line = $tokens[$start]['line'];
        $firstOnLine = $start;

        while (
            $firstOnLine > 0
            && $tokens[$firstOnLine - 1]['line'] === $line
        ) {
            $firstOnLine--;
        }

        $indent = '';

        if ($tokens[$firstOnLine]['code'] === T_WHITESPACE) {
            $indent = str_replace(["\r", "\n"], '', $tokens[$firstOnLine]['content']);
        }

        return "{$indent}    ";
    }
}
