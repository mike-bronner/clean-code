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
 * therefore bounded by depth-zero semicolons only — a semicolon nested inside a
 * closure body, an array literal, or a call's argument list is not a section
 * separator, and treating it as one would shift every later section and either
 * report the initialiser or miss the condition entirely.
 *
 * Within that range every token is visited, so a count() nested inside a call
 * in the condition is still reported, and so is one inside a closure, arrow
 * function, anonymous class, or match arm written in the condition. That is
 * deliberate rather than incidental: PHPMD keeps the condition's Expression
 * node and runs findChildrenOfType('FunctionPostfix') across its whole subtree,
 * so it flags those too, and the call really does re-run on every evaluation of
 * the condition.
 *
 * The one thing the scan steps over is a *nested* loop's own condition range.
 * That loop is registered in its own right and reports its condition itself, so
 * reading it here as well would report the same call twice. Only the condition
 * is skipped: the nested header's initialiser and increment — which the nested
 * loop's own pass ignores by the same section rule — and the nested body are
 * all still read here, because a call in any of them runs on every evaluation
 * of this condition and PHPMD reports it. See
 * docs/phpmd/design-countinloopexpression.md for the shape-by-shape comparison.
 *
 * Only a real *call* counts. A same-named method, static method, declaration,
 * or qualified name is a different function, and PHP 8.1's first-class callable
 * `count(...)` builds a Closure rather than invoking anything — none of them
 * re-counts an array per iteration, so none is reported.
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
     * The loop keywords that carry a condition of their own, and so are both
     * registered and — when one turns up nested inside another loop's
     * condition — skipped over by the scan below.
     */
    private const LOOP_TOKENS = [
        T_FOR,
        T_WHILE,
    ];

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
        return self::LOOP_TOKENS;
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
        $condition = $this->conditionRange($phpcsFile, $stackPtr);

        if ($condition === null) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        [$start, $end] = $condition;
        $claimed = $this->nestedConditions($phpcsFile, $start, $end);

        for ($i = $start; $i < $end; $i++) {
            // A nested loop's condition is that loop's own to report: it is
            // registered too, and its pass covers exactly this range. Step over
            // it, and only it — the nested header's initialiser and increment,
            // and the nested body, are all still read here, because a call in
            // any of them runs on every evaluation of this condition.
            if (isset($claimed[$i]) === true) {
                $i = $claimed[$i];

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
     * The token range a loop reports on, as [start, end) pointers.
     *
     * For a `while` — and so for a do-while, whose condition hangs off the
     * trailing `while` — that is everything between the parentheses. For a
     * `for` it is the middle section of the three: the continuation test.
     * Returns null when there is no condition to judge, which covers a
     * malformed header with no parenthesis bounds and a `for` whose header
     * carries no section separator at all.
     *
     * @return array{0: int, 1: int}|null
     */
    private function conditionRange(File $phpcsFile, int $loopPtr): ?array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$loopPtr]['parenthesis_opener'], $tokens[$loopPtr]['parenthesis_closer']) === false) {
            return null;
        }

        $start = ($tokens[$loopPtr]['parenthesis_opener'] + 1);
        $closer = $tokens[$loopPtr]['parenthesis_closer'];

        if ($tokens[$loopPtr]['code'] !== T_FOR) {
            return [$start, $closer];
        }

        $separators = $this->headerSeparators($phpcsFile, $start, $closer);

        if (isset($separators[0]) === false) {
            return null;
        }

        return [($separators[0] + 1), ($separators[1] ?? $closer)];
    }

    /**
     * The pointers of a `for` header's own section separators — the semicolons
     * at bracket depth zero.
     *
     * Depth is counted rather than jumped, and deliberately does not consult
     * `scope_closer`, which is the obvious-looking way to step over a nested
     * body. An arrow function is given a `scope_closer` despite having no
     * braces, and for `fn () => 1;` in a for header that pointer lands on the
     * header's own first separator — so jumping to it swallows a real separator
     * and silently loses the condition section.
     *
     * @return array<int, int>
     */
    private function headerSeparators(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $separators = [];
        $depth = 0;

        for ($i = $start; $i < $end; $i++) {
            $code = $tokens[$i]['code'];

            if (in_array($code, self::NESTING_OPENERS, true) === true) {
                $depth++;
            } elseif (in_array($code, self::NESTING_CLOSERS, true) === true) {
                $depth--;
            } elseif ($code === T_SEMICOLON && $depth === 0) {
                $separators[] = $i;
            }
        }

        return $separators;
    }

    /**
     * The condition ranges of every loop nested inside [start, end), keyed by
     * the pointer each range begins at so the scan can step over one the moment
     * it reaches it.
     *
     * @return array<int, int>
     */
    private function nestedConditions(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $ranges = [];

        for ($i = $start; $i < $end; $i++) {
            if (in_array($tokens[$i]['code'], self::LOOP_TOKENS, true) === false) {
                continue;
            }

            $nested = $this->conditionRange($phpcsFile, $i);

            if ($nested === null) {
                continue;
            }

            $ranges[$nested[0]] = $nested[1];
        }

        return $ranges;
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

        if ($this->isFirstClassCallable($phpcsFile, $next) === true) {
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
     * Whether the argument list opening at $openerPtr is PHP 8.1's first-class
     * callable syntax — `count(...)`, whose entire argument list is the literal
     * ellipsis. That expression builds a Closure referring to the function; it
     * never invokes it, so nothing is counted and there is no per-iteration
     * re-count for the rule to catch.
     *
     * The ellipsis has to be the whole list. `count(...$args)` spells a real
     * call with a spread argument, re-counts on every pass like any other call,
     * and stays reported — which is why the token after the ellipsis is checked
     * for the closing parenthesis rather than the ellipsis being taken alone.
     *
     * Neither `=== false` half is fixtured, and cannot be: findNext() only runs
     * out of tokens if the file ends inside this argument list, and an argument
     * list left open at EOF takes the loop's own closing parenthesis with it —
     * so process() has already returned on the missing parenthesis_closer before
     * this method is reached. They guard the array reads beside them, in the
     * same combined form the caller uses for its own findNext() result.
     */
    private function isFirstClassCallable(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ellipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), null, true);

        if ($ellipsis === false || $tokens[$ellipsis]['code'] !== T_ELLIPSIS) {
            return false;
        }

        $afterEllipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsis + 1), null, true);

        return $afterEllipsis !== false && $tokens[$afterEllipsis]['code'] === T_CLOSE_PARENTHESIS;
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
