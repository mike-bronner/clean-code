<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\OperatorSpacingSniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces exactly one space between an "active" operator and the operands
 * it acts on:
 *
 * - arithmetic assignment: =, +=, -=, /=, *=, %=, **=
 * - bitwise assignment: &=, |=, ^=, <<=, >>=
 * - other assignment: .=, ??=
 * - logical: and, or, xor, !, &&, ||
 * - string concatenation: .
 *
 * Extends Squiz.WhiteSpace.OperatorSpacing (which covers only the assignment
 * operators from this list) rather than rewriting its spacing checks and
 * fixers, and adds the logical, string-concatenation, and NOT operators the
 * Squiz sniff does not target. Binary operators require one space on each
 * side; the unary ! requires one space before its operand only. A newline
 * beside an operator is valid separation, so multi-line concatenation and
 * multi-line logical expressions are untouched. All violations are
 * auto-fixable.
 *
 * Default values in function signatures ($a = 1) are inherited Squiz
 * territory and stay out of scope here — declaration-spacing rules own that
 * context.
 */
class ActiveOperatorSpacingSniff extends OperatorSpacingSniff
{
    /**
     * A newline beside an operator is valid separation (multi-line
     * concatenation and logical expressions).
     *
     * @var boolean
     */
    public $ignoreNewlines = true;

    /**
     * Alignment padding before assignments still violates the
     * exactly-one-space rule, so the parent's allowance is disabled.
     *
     * @var boolean
     */
    public $ignoreSpacingBeforeAssignments = false;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        // Let the parent initialise its internal operator-context state.
        parent::register();

        $targets = Tokens::$assignmentTokens;

        // Double arrows are array/match syntax, not active operators, and
        // T_ZSR_EQUAL (>>>=) exists only in JS tokenizing.
        unset($targets[T_DOUBLE_ARROW], $targets[T_ZSR_EQUAL]);

        // The logical operators the Squiz sniff does not target: &&, ||,
        // and, or, xor.
        $targets += Tokens::$booleanOperators;

        $targets[] = T_STRING_CONCAT;
        $targets[] = T_BOOLEAN_NOT;

        // Registered so the parent skips declare() statements wholesale.
        $targets[] = T_DECLARE;

        return $targets;
    }

    /**
     * @param int $stackPtr
     *
     * @return void|int
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] === T_BOOLEAN_NOT) {
            $this->processNotOperator($phpcsFile, $stackPtr);

            return;
        }

        return parent::process($phpcsFile, $stackPtr);
    }

    /**
     * The unary ! acts on the operand that follows it, so it needs exactly
     * one space after itself only — spacing before it belongs to whatever
     * token precedes it. Chained negation (!!$foo) is processed once per
     * NOT token, fixing to "! ! $foo".
     */
    private function processNotOperator(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[($stackPtr + 1)]) === false) {
            return;
        }

        $found = 0;

        if ($tokens[($stackPtr + 1)]['code'] === T_WHITESPACE) {
            $nextNonWhitespace = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

            if ($nextNonWhitespace === false) {
                return;
            }

            if ($tokens[$nextNonWhitespace]['line'] !== $tokens[$stackPtr]['line']) {
                if ($this->ignoreNewlines === true) {
                    return;
                }

                $found = 'newline';
            } else {
                $found = $tokens[($stackPtr + 1)]['length'];
            }
        }

        if ($found === 1) {
            return;
        }

        $error = 'Expected 1 space after "!"; %s found';
        $data = [$found];

        if ($found === 0) {
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'NoSpaceAfterNot', $data);

            if ($fix === true) {
                $phpcsFile->fixer->addContent($stackPtr, ' ');
            }

            return;
        }

        $fix = $phpcsFile->addFixableError($error, $stackPtr, 'SpacingAfterNot', $data);

        if ($fix === true) {
            $phpcsFile->fixer->replaceToken(($stackPtr + 1), ' ');
        }
    }
}
