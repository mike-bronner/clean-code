<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\ControlStructures;

use MikeBronner\CleanCode\Helpers\FunctionCalls;
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
     *
     * "Is this name a call to PHP's own global function?" is
     * {@see FunctionCalls::isGlobalFunctionCall()}'s question, not this
     * sniff's: it rules out member access, declarations (including
     * `function &count()`), instantiation however the name is qualified,
     * another namespace's `Acme\count()`, an attribute name, and a bare name a
     * `use function` import redirects elsewhere. This sniff owns only *which*
     * names it cares about — SIZE_FUNCTIONS — and the one exclusion that is
     * about its own rule rather than about name resolution: a first-class
     * callable re-counts nothing per iteration.
     */
    private function isSizeFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (in_array(strtolower($tokens[$stackPtr]['content']), self::SIZE_FUNCTIONS, true) === false) {
            return false;
        }

        if (FunctionCalls::isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return false;
        }

        return $this->isFirstClassCallable($phpcsFile, $stackPtr) === false;
    }

    /**
     * Whether the name at $stackPtr is referenced through PHP 8.1's first-class
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
     * None of the three `=== false` halves can be discriminated by a fixture,
     * and this says so rather than leaving them to look covered. The first
     * cannot arrive at all: FunctionCalls::isGlobalFunctionCall() has already
     * required the next non-empty token to be the opening parenthesis, so the
     * opener is established before this method is reached. The other two need a
     * file that ends inside the argument list, and either one made to fall open
     * reads a token that is not there rather than returning a different verdict.
     * All three guard the array read beside them.
     */
    private function isFirstClassCallable(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openerPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($openerPtr === false) {
            return false;
        }

        $ellipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), null, true);

        if ($ellipsis === false || $tokens[$ellipsis]['code'] !== T_ELLIPSIS) {
            return false;
        }

        $afterEllipsis = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsis + 1), null, true);

        return $afterEllipsis !== false && $tokens[$afterEllipsis]['code'] === T_CLOSE_PARENTHESIS;
    }
}
