<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ControlStructures;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Forbids count()/sizeof() inside a loop's condition expression.
 *
 * Replicates PHPMD's Design/CountInLoopExpression rule
 * (docs/phpmd/design-countinloopexpression.md, issue #99). The array is
 * re-counted on every iteration, and a loop that mutates the array while
 * re-reading its size is the bug the rule exists to catch. Assign the size to a
 * variable before the loop instead.
 *
 * Only the *condition* counts. A `for` header has three sections, and PHPMD
 * looks at the middle one alone: count() in the initialiser runs once, and
 * count() in the increment is not the loop's continuation test. The section is
 * therefore tracked by depth-zero semicolons only — a semicolon nested inside a
 * closure body, an array literal, or a call's argument list is not a section
 * separator, and treating it as one would shift every later section and either
 * report the initialiser or miss the condition entirely.
 *
 * That depth counter deliberately does not consult `scope_closer`, which is the
 * obvious-looking way to jump over a nested body. An arrow function is given a
 * `scope_closer` despite having no braces, and for `fn () => 1;` in a for
 * header that pointer lands on the header's own first separator — so jumping to
 * it swallows a real separator and silently loses the condition section.
 * Counting brackets visits every token instead, which is also what lets a
 * count() nested inside a call in the condition still be reported.
 */
class DisallowCountInLoopExpressionSniff implements Sniff
{
    /**
     * The size functions PHPMD's rule names. Compared case-insensitively,
     * because PHP function names are.
     */
    private const SIZE_FUNCTIONS = [
        'count',
        'sizeof',
    ];

    /**
     * The middle section of a `for` header — the continuation test. Sections
     * are numbered from zero in header order: initialiser, condition,
     * increment.
     */
    private const FOR_CONDITION_SECTION = 1;

    /**
     * Tokens that open a nesting level, so any semicolon inside them belongs
     * to that construct rather than to a `for` header's section separators.
     */
    private const NESTING_OPENERS = [
        T_OPEN_PARENTHESIS,
        T_OPEN_SQUARE_BRACKET,
        T_OPEN_SHORT_ARRAY,
        T_OPEN_CURLY_BRACKET,
    ];

    /**
     * The matching closers for NESTING_OPENERS.
     */
    private const NESTING_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    /**
     * Tokens that, when directly preceding the function name, mean this is
     * not a global function call (method call, static call, declaration, …).
     */
    private const NON_FUNCTION_CALL_PRECEDERS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FOR, T_WHILE];
    }

    /**
     * T_DO carries no parentheses of its own; a do-while's condition hangs off
     * the trailing T_WHILE, so registering T_WHILE covers both loop forms.
     *
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer']) === false) {
            return;
        }

        $closer = $tokens[$stackPtr]['parenthesis_closer'];
        $isForLoop = $tokens[$stackPtr]['code'] === T_FOR;
        $depth = 0;
        $section = 0;

        for ($i = ($tokens[$stackPtr]['parenthesis_opener'] + 1); $i < $closer; $i++) {
            $code = $tokens[$i]['code'];

            if (in_array($code, self::NESTING_OPENERS, true) === true) {
                $depth++;
            } elseif (in_array($code, self::NESTING_CLOSERS, true) === true) {
                $depth--;
            } elseif ($code === T_SEMICOLON && $depth === 0) {
                $section++;

                continue;
            }

            if ($isForLoop === true && $section !== self::FOR_CONDITION_SECTION) {
                continue;
            }

            if ($this->isSizeFunctionCall($phpcsFile, $i) === false) {
                continue;
            }

            $phpcsFile->addError(
                '%s() must not be called in a loop condition; assign its result to a variable before the loop',
                $i,
                'Found',
                [$tokens[$i]['content']]
            );
        }
    }

    /**
     * Whether the token at $stackPtr is a call to one of the global size
     * functions, rather than a same-named method, static method, or
     * declaration.
     */
    private function isSizeFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['code'] !== T_STRING) {
            return false;
        }

        if (in_array(strtolower($tokens[$stackPtr]['content']), self::SIZE_FUNCTIONS, true) === false) {
            return false;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return false;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($prev === false) {
            return true;
        }

        if (in_array($tokens[$prev]['code'], self::NON_FUNCTION_CALL_PRECEDERS, true) === true) {
            return false;
        }

        return $tokens[$prev]['code'] !== T_NS_SEPARATOR
            || $this->isQualifiedName($phpcsFile, $prev) === false;
    }

    /**
     * Whether the T_NS_SEPARATOR at $separatorPtr belongs to a qualified name
     * (App\Support\count, namespace\count) rather than a fully-qualified global
     * one (\count). Qualified names resolve outside the global namespace, so
     * they are never the global size functions.
     */
    private function isQualifiedName(File $phpcsFile, int $separatorPtr): bool
    {
        $beforeSeparator = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($separatorPtr - 1), null, true);

        if ($beforeSeparator === false) {
            return false;
        }

        $tokens = $phpcsFile->getTokens();

        return in_array($tokens[$beforeSeparator]['code'], [T_STRING, T_NAMESPACE], true);
    }
}
