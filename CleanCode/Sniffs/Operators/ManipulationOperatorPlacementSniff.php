<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Operators: Manipulative" standard.
 *
 * A manipulation operator that joins two operands across a line break must
 * *start* the continuation line, never trail the end of the previous one. So
 *
 *     $result = 4
 *         + 4;
 *
 * is compliant, while
 *
 *     $result = 4 +
 *         4;
 *
 * is not. Operators used inline — both operands on the same line, e.g.
 * `floor(4 + 4.1)` — are always fine; the rule only governs how a
 * genuinely wrapped expression breaks.
 *
 * Manipulation operators covered: string concatenation (`.`); the math
 * operators `+ - * / % **`; the logical operators `&& ||`; and the bitwise
 * operators `& | ^ << >>`. Unary bitwise NOT (`~`) has no binary/continuation
 * form and is not subject to the rule. The unary/reference forms of `+`, `-`,
 * and `&` (sign, `&$ref`) are likewise ignored — the operator is only flagged
 * when a real left-hand operand ends the previous line.
 *
 * The auto-fixer moves the trailing operator down to lead the continuation
 * line, indented one level past the statement's first line, with a single
 * space before its right-hand operand. A comment sitting between the two
 * operands makes the move unsafe (it would be reordered), so those violations
 * are reported but left for the developer.
 */
class ManipulationOperatorPlacementSniff implements Sniff
{
    /**
     * The manipulation operators the standard governs. `~` (unary bitwise NOT)
     * is intentionally excluded: it has no binary form, so "start the new line"
     * is meaningless for it.
     *
     * @var array<int|string>
     */
    private const MANIPULATION_OPERATORS = [
        T_STRING_CONCAT, // .
        T_PLUS,          // +
        T_MINUS,         // -
        T_MULTIPLY,      // *
        T_DIVIDE,        // /
        T_MODULUS,       // %
        T_POW,           // **
        T_BOOLEAN_AND,   // &&
        T_BOOLEAN_OR,    // ||
        T_BITWISE_AND,   // &
        T_BITWISE_OR,    // |
        T_BITWISE_XOR,   // ^
        T_SL,            // <<
        T_SR,            // >>
    ];

    /**
     * Operators whose token can also be unary (sign) or a reference marker,
     * so they are only treated as manipulation operators when a real operand
     * ends the previous line.
     *
     * @var array<int|string>
     */
    private const UNARY_CAPABLE = [
        T_PLUS,
        T_MINUS,
        T_BITWISE_AND,
    ];

    /**
     * Tokens that can terminate a left-hand operand. Used to tell a binary
     * `+`/`-`/`&` (preceded by a value) from its unary/reference form
     * (preceded by punctuation, a keyword, or another operator).
     *
     * @var array<int|string>
     */
    private const OPERAND_END_TOKENS = [
        T_VARIABLE,
        T_LNUMBER,
        T_DNUMBER,
        T_CONSTANT_ENCAPSED_STRING,
        T_STRING,
        T_TRUE,
        T_FALSE,
        T_NULL,
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return self::MANIPULATION_OPERATORS;
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($previous === false || $next === false) {
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

        // Sign (`-5`) and reference (`&$ref`) uses of the ambiguous tokens are
        // not manipulation operators: they carry no left-hand operand.
        if (
            in_array($tokens[$stackPtr]['code'], self::UNARY_CAPABLE, true) === true
            && in_array($tokens[$previous]['code'], self::OPERAND_END_TOKENS, true) === false
        ) {
            return;
        }

        $error = 'Manipulation operator "%s" must start the continuation line, not trail the previous one';
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

    /**
     * Rewrites the whitespace around a trailing operator so it leads the
     * continuation line: the space before it becomes a newline plus the
     * continuation indent, and the newline after it collapses to a single
     * space before the right-hand operand.
     */
    private function moveOperatorToNextLine(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $indent = $this->continuationIndent($phpcsFile, $stackPtr);

        $phpcsFile->fixer->beginChangeset();

        for ($i = ($stackPtr - 1); $tokens[$i]['code'] === T_WHITESPACE; $i--) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        for ($i = ($stackPtr + 1); $tokens[$i]['code'] === T_WHITESPACE; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->addContentBefore($stackPtr, $phpcsFile->eolChar . $indent);
        $phpcsFile->fixer->addContent($stackPtr, ' ');

        $phpcsFile->fixer->endChangeset();
    }

    /**
     * Continuation indent for a wrapped operator: the leading whitespace of the
     * line the statement starts on, plus one four-space level. Basing it on the
     * statement's root line (not the operator's line) keeps every continuation
     * operator of a multi-line statement level, rather than stair-stepping.
     */
    private function continuationIndent(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $start = $phpcsFile->findStartOfStatement($stackPtr);
        $line = $tokens[$start]['line'];
        $firstOnLine = $start;

        while ($firstOnLine > 0 && $tokens[$firstOnLine - 1]['line'] === $line) {
            $firstOnLine--;
        }

        $indent = '';

        if ($tokens[$firstOnLine]['code'] === T_WHITESPACE) {
            $indent = str_replace(["\r", "\n"], '', $tokens[$firstOnLine]['content']);
        }

        return $indent . '    ';
    }
}
