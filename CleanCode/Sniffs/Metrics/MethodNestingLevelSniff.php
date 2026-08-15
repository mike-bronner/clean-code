<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Indentation: Methods" clean-code standard: a method body must
 * not nest control structures more than 2 levels deep. Deep nesting signals
 * mixed concerns and is a prompt to refactor (extract a method, invert a
 * condition, use a guard clause).
 *
 * Nesting is counted per control structure — `if`/`elseif`/`else`, loops
 * (`for`/`foreach`/`while`/`do`), `switch`/`match`, `try`/`catch`/`finally`,
 * and anonymous functions (closures and arrow functions). `case`/`default`
 * labels do NOT add a level (they belong to the enclosing `switch`), and
 * `elseif`/`else`/`catch`/`finally` sit at the same level as the `if`/`try`
 * they continue rather than nesting beneath it.
 *
 * Two-word `else if` (a bare `else` followed by a fresh `if`) is treated as a
 * continuation of the same chain, exactly like the one-word `elseif` keyword —
 * it never adds a level of its own or re-reports the chain's leading `if`.
 *
 * Each control structure whose level exceeds the maximum is reported at its own
 * line, so every excess nesting level is flagged individually. The rule applies
 * only inside a function/method body; an anonymous function (closure or arrow
 * function `fn`) inside a method counts as a nesting level, both for itself and
 * for whatever its body holds — the two forms are interchangeable to a reader,
 * so they measure the same. Top-level script code is out of scope. A nested
 * *named* function declaration is not counted: it defines a new named symbol
 * rather than an inline block, is not part of the standard's control-structure
 * set, and cannot legally recur inside a method body — it falls outside this
 * rule.
 *
 * Not auto-fixable: reducing nesting requires a semantic refactor that cannot
 * be applied safely by a token rewriter.
 */
class MethodNestingLevelSniff implements Sniff
{
    /**
     * A method body may nest control structures at most this many levels deep.
     */
    private const MAX_NESTING_LEVEL = 2;

    /**
     * Control structures whose scope each adds one level of nesting to the
     * tokens inside it, counted via each token's `conditions`. `case`/`default`,
     * the enclosing class, and nested *named* functions (`T_FUNCTION`) are
     * deliberately excluded; `elseif`, `else`, `catch`, and `finally` are
     * included so statements inside those blocks are counted at the correct
     * depth. `T_CLOSURE` (`function () {}`) is included because a closure body
     * is a braced block whose statements PHPCS records as nested conditions.
     *
     * Arrow functions (`T_FN`) are absent here because listing them would be
     * inert, not because they are worth nothing: PHPCS does not record `T_FN` in
     * the conditions of the tokens inside its single-expression body — measured,
     * a closure written inside `fn () => (function () { … })()` lists only
     * `T_CLASS`, `T_FUNCTION` and the enclosing `T_IF`. An arrow function's level
     * is therefore added by arrowFunctionDepth(), which reads the span PHPCS does
     * record, and the `fn` itself is reported through register().
     *
     * @var array<int|string, true>
     */
    private const NESTING_TOKENS = [
        T_IF => true,
        T_ELSEIF => true,
        T_ELSE => true,
        T_FOR => true,
        T_FOREACH => true,
        T_WHILE => true,
        T_DO => true,
        T_SWITCH => true,
        T_MATCH => true,
        T_TRY => true,
        T_CATCH => true,
        T_FINALLY => true,
        T_CLOSURE => true,
    ];

    /**
     * The control-structure openers reported when they exceed the limit.
     * `elseif`/`else`/`catch`/`finally` are not listed: each continues a
     * construct whose opener (`if`/`try`) sits at the same level and is already
     * reported, so listing them would double-report one nesting level. Both
     * anonymous-function forms are listed — `T_CLOSURE` and `T_FN` — so an
     * over-nested closure *or* arrow function is flagged at its own position.
     *
     * @return array<int|string>
     */
    public function register(): array
    {
        return [
            T_IF,
            T_FOR,
            T_FOREACH,
            T_WHILE,
            T_DO,
            T_SWITCH,
            T_MATCH,
            T_TRY,
            T_CLOSURE,
            T_FN,
        ];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$stackPtr]['conditions'];

        // Braceless/abstract/interface bodies and the trailing while of a
        // do-while have no scope to measure.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        // The standard governs method bodies; skip top-level script code and
        // closures not enclosed by a function.
        if (in_array(T_FUNCTION, $conditions, true) === false) {
            return;
        }

        // Two-word `else if` tokenizes as a bare `T_ELSE` followed by a fresh
        // `T_IF` that sits at the same nesting level as the chain's leading
        // `if` (its conditions don't include the preceding branch). Reporting
        // that `T_IF` would emit a duplicate error for a level the leading `if`
        // already reports, so treat it as a continuation and skip it — mirroring
        // the one-word `elseif`, which is a single `T_ELSEIF` token that is not
        // registered. The `else`/`elseif` continuation still counts as a level
        // for statements nested inside the branch via NESTING_TOKENS.
        if ($tokens[$stackPtr]['code'] === T_IF) {
            $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);

            if ($previous !== false && $tokens[$previous]['code'] === T_ELSE) {
                return;
            }
        }

        $level = 1 + $this->arrowFunctionDepth($phpcsFile, $stackPtr, $conditions);

        foreach ($conditions as $conditionCode) {
            if (isset(self::NESTING_TOKENS[$conditionCode]) === true) {
                $level++;
            }
        }

        if ($level <= self::MAX_NESTING_LEVEL) {
            return;
        }

        $phpcsFile->addError(
            'Method nesting level (%s) exceeds the maximum of %s; refactor to reduce nesting',
            $stackPtr,
            'MaxExceeded',
            [$level, self::MAX_NESTING_LEVEL]
        );
    }

    /**
     * How many arrow functions enclose $stackPtr.
     *
     * Every other nesting token is counted off the token's own `conditions`,
     * which PHPCS fills from the enclosing scopes. Arrow functions are the one
     * form it leaves out: `fn` owns a scope, but the tokens inside its
     * single-expression body do not list `T_FN` among their conditions. Measured
     * rather than assumed — the closure in `fn () => (function () { … })()`
     * reports conditions of `T_CLASS`, `T_FUNCTION`, `T_IF` and nothing else. So
     * an arrow function's level has to be read off the one thing PHPCS does
     * record for it, its `scope_opener`/`scope_closer` span: the arrow functions
     * enclosing $stackPtr are those whose span covers it.
     *
     * Without this, `if { fn () => (function () { … })() }` and
     * `if { function () { … } }` — the same shape to a reader, and both
     * anonymous functions under the standard — would measure one level apart,
     * and the deeper one would be the one that went unreported.
     *
     * The walk starts at the enclosing declaration's body rather than at the
     * start of the file. An arrow function that encloses $stackPtr cannot open
     * before the declaration $stackPtr sits in, so a whole-file walk would
     * re-read the same tokens once per control structure to no purpose — this
     * runs on every registered token in every method of every file scanned.
     *
     * @param array<int, int|string> $conditions The token's own conditions, pointer => code.
     */
    private function arrowFunctionDepth(File $phpcsFile, int $stackPtr, array $conditions): int
    {
        $tokens = $phpcsFile->getTokens();
        $bodyStart = $this->declarationBodyStart($tokens, $conditions);
        $depth = 0;
        $pointer = $stackPtr;

        while (($pointer = $phpcsFile->findPrevious(T_FN, $pointer - 1, $bodyStart)) !== false) {
            if (
                isset($tokens[$pointer]['scope_closer']) === true
                && $tokens[$pointer]['scope_closer'] >= $stackPtr
            ) {
                $depth++;
            }
        }

        return $depth;
    }

    /**
     * The opening brace of the outermost named function enclosing the token —
     * the lower bound of arrowFunctionDepth()'s walk.
     *
     * The *outermost* one, not the nearest enclosing scope: an arrow function is
     * absent from `conditions`, so a bound taken from the nearest condition would
     * sit inside an arrow function that opened earlier in the method and miss it.
     * process() only reaches here for a token whose conditions contain a
     * `T_FUNCTION`, so the search always finds one.
     *
     * @param array<int, array<string, mixed>> $tokens
     * @param array<int, int|string>           $conditions
     */
    private function declarationBodyStart(array $tokens, array $conditions): int
    {
        foreach ($conditions as $pointer => $conditionCode) {
            if ($conditionCode === T_FUNCTION && isset($tokens[$pointer]['scope_opener']) === true) {
                return $tokens[$pointer]['scope_opener'];
            }
        }

        return 0;
    }
}
