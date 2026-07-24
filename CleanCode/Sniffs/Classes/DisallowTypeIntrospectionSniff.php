<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Classes: Introspection / Type Casting" standard.
 *
 * Asking an object what type it is in order to decide a branch couples the
 * caller to a concrete type that the method parameter or class property should
 * already have declared. Reaching for introspection means the logic belongs on
 * the object (polymorphism) or the type belongs in the signature.
 *
 * The sniff flags type introspection *only where it decides a branch*:
 *
 * - `instanceof`
 * - `get_class()`, `get_debug_type()`, `gettype()`, `is_a()`,
 *   `is_subclass_of()`
 *
 * used inside the condition of an `if`/`elseif`/`while`, the subject of a
 * `switch`/`match`, a `case` label, a `match` arm condition, or the condition
 * of a ternary.
 *
 * Introspection outside a branch decision is deliberately left alone — an
 * exception message, a log line, an assertion, or a `return $x instanceof Y;`
 * predicate reports a type, it does not choose behaviour based on one.
 *
 * A closure or arrow function bounds the search. Introspection inside a
 * callback decides that callback's *return value*, so it is a predicate — even
 * when the callback is itself an argument inside some enclosing branch's
 * condition, as in `if (array_filter($rows, fn ($r) => $r instanceof Failure))`.
 * Every branch a check is measured against must therefore live inside the same
 * callback the check does.
 *
 * Detection only: replacing a type check with polymorphism means moving
 * behaviour onto the object (or narrowing a signature) and updating call
 * sites, so no token-based auto-fix can be applied.
 *
 * Where the token stream lacks the structure a check needs — a `match` with no
 * scope opener, an unclosed group — the sniff stays silent rather than
 * guessing. Only malformed source reaches those paths (PHPCS reports the parse
 * error itself), and a linter that invents violations there is worse than one
 * that misses them.
 */
class DisallowTypeIntrospectionSniff implements Sniff
{
    /**
     * Global functions that report the runtime type of a value. Compared
     * against the lower-cased call name — PHP function names are
     * case-insensitive.
     */
    private const INTROSPECTION_FUNCTIONS = [
        'get_class',
        'get_debug_type',
        'gettype',
        'is_a',
        'is_subclass_of',
    ];

    /**
     * Constructs whose parenthesised expression *is* the branch decision.
     *
     * `for` is deliberately absent: its parentheses hold the initialiser and
     * the increment alongside the condition, and a token-level check cannot
     * tell them apart.
     */
    private const CONDITION_OWNERS = [
        T_ELSEIF,
        T_IF,
        T_MATCH,
        T_SWITCH,
        T_WHILE,
    ];

    /**
     * Tokens that close the expression a forward scan started inside, without
     * that expression having turned out to be a ternary condition.
     */
    private const EXPRESSION_TERMINATORS = [
        T_CLOSE_CURLY_BRACKET,
        T_COLON,
        T_COMMA,
        T_DOUBLE_ARROW,
        T_FN_ARROW,
        T_INLINE_ELSE,
        T_MATCH_ARROW,
        T_OPEN_CURLY_BRACKET,
        T_SEMICOLON,
    ];

    /**
     * Tokens which, sitting directly before a `name(`, mean the name is not a
     * call to the global introspection function of that name.
     */
    private const NOT_A_GLOBAL_CALL = [
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_OBJECT_OPERATOR,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_INSTANCEOF, T_STRING];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_STRING) {
            $this->processIntrospectionFunction($phpcsFile, $stackPtr);

            return;
        }

        if ($this->decidesABranch($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Do not branch on `instanceof`; declare the type in the parameter or property, '
                . 'or move the behaviour onto the object',
            $stackPtr,
            'InstanceOf'
        );
    }

    /**
     * Reports a call to a type-introspection function when that call decides a
     * branch. Names that are not such a call — a method of the same name, a
     * function *declaration*, a namespaced same-name function — are ignored.
     */
    private function processIntrospectionFunction(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        if (in_array(strtolower($tokens[$stackPtr]['content']), self::INTROSPECTION_FUNCTIONS, true) === false) {
            return;
        }

        if ($this->isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->decidesABranch($phpcsFile, $stackPtr) === false) {
            return;
        }

        $phpcsFile->addError(
            'Do not branch on %s; declare the type in the parameter or property, '
                . 'or move the behaviour onto the object',
            $stackPtr,
            'IntrospectionFunction',
            [$tokens[$stackPtr]['content'] . '()']
        );
    }

    /**
     * True when the name at $stackPtr is invoked as the global function of
     * that name: followed by `(`, and not qualified as a method, a class
     * member, a `new` target, a declaration, or another namespace's function.
     */
    private function isGlobalFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return false;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($prev === false) {
            return true;
        }

        if (in_array($tokens[$prev]['code'], self::NOT_A_GLOBAL_CALL, true)) {
            return false;
        }

        if ($tokens[$prev]['code'] !== T_NS_SEPARATOR) {
            return true;
        }

        // `\get_class()` is the global function; `Vendor\get_class()` is not.
        $qualifier = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($prev - 1), null, true);

        return $qualifier === false
            || in_array($tokens[$qualifier]['code'], [T_NAMESPACE, T_STRING], true) === false;
    }

    /**
     * True when the introspection at $stackPtr decides which branch runs.
     *
     * The innermost callback enclosing the token is resolved once and passed to
     * each check, which confines it to that callback's own body.
     */
    private function decidesABranch(File $phpcsFile, int $stackPtr): bool
    {
        $callback = $this->enclosingCallback($phpcsFile, $stackPtr);

        if ($this->isInsideAConditionParenthesis($phpcsFile, $stackPtr, $callback)) {
            return true;
        }

        if ($this->isATernaryCondition($phpcsFile, $stackPtr, $callback)) {
            return true;
        }

        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'];

        if ($conditions === []) {
            return false;
        }

        $innermost = end($conditions);

        if ($innermost === T_MATCH) {
            $matchPtr = (int) array_key_last($conditions);

            return $this->isAMatchArmCondition($phpcsFile, $stackPtr, $matchPtr, $callback);
        }

        return $innermost === T_SWITCH
            && $this->isASwitchCaseCondition($phpcsFile, $stackPtr, $callback);
    }

    /**
     * Returns the pointer of the innermost closure or arrow function whose body
     * contains $stackPtr, or null when the token sits in no callback.
     *
     * Scanning backwards, the first such callback found is the innermost, since
     * any callback enclosing the token starts before it and the nearest opener
     * is the most deeply nested. A callback whose scope PHPCS could not resolve
     * is skipped rather than assumed to enclose the token: that only happens on
     * source PHPCS already reports a parse error for, and treating it as a
     * boundary would silence the sniff for the whole remainder of the file.
     */
    private function enclosingCallback(File $phpcsFile, int $stackPtr): ?int
    {
        $tokens = $phpcsFile->getTokens();

        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            if (in_array($tokens[$i]['code'], [T_CLOSURE, T_FN], true) === false) {
                continue;
            }

            $opener = $tokens[$i]['scope_opener'] ?? null;
            $closer = $tokens[$i]['scope_closer'] ?? null;

            if (
                $opener !== null
                && $closer !== null
                && $opener < $stackPtr
                && $stackPtr < $closer
            ) {
                return $i;
            }
        }

        return null;
    }

    /**
     * True when the token sits inside the parentheses of an `if`, `elseif`,
     * `while`, `switch`, or `match` — i.e. inside the branch condition itself,
     * at any nesting depth.
     *
     * A condition opening *before* the enclosing callback belongs to the code
     * that receives the callback, not to the callback's body: the token decides
     * what the callback returns, and the caller decides the branch.
     */
    private function isInsideAConditionParenthesis(
        File $phpcsFile,
        int $stackPtr,
        ?int $callback
    ): bool {
        $tokens = $phpcsFile->getTokens();

        foreach (array_keys($tokens[$stackPtr]['nested_parenthesis'] ?? []) as $opener) {
            if (
                $callback !== null
                && $opener < $callback
            ) {
                continue;
            }

            $owner = $tokens[$opener]['parenthesis_owner'] ?? null;

            if ($owner !== null && in_array($tokens[$owner]['code'], self::CONDITION_OWNERS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the token sits in the *condition* of a ternary — a `?` follows
     * it before the expression it belongs to ends.
     *
     * Balanced groups are skipped whole, so an unmatched closer means the scan
     * has stepped out to the enclosing expression and simply keeps walking
     * outward: `f(get_class($x)) ? a : b` is a ternary condition, whereas
     * `f(get_class($x), $y ? a : b)` terminates at the argument comma.
     *
     * An enclosing callback caps that outward walk at its own end, so a `?`
     * belonging to the caller — `array_filter($i, fn ($x) => $x instanceof Y) ? a : b`
     * — is never mistaken for the arrow function's own ternary.
     */
    private function isATernaryCondition(File $phpcsFile, int $stackPtr, ?int $callback): bool
    {
        $tokens = $phpcsFile->getTokens();
        $limit = $callback === null ? $phpcsFile->numTokens : $tokens[$callback]['scope_closer'];

        for ($i = ($stackPtr + 1); $i < $limit; $i++) {
            $code = $tokens[$i]['code'];

            if ($code === T_INLINE_THEN) {
                return true;
            }

            if (in_array($code, self::EXPRESSION_TERMINATORS, true)) {
                return false;
            }

            $i = $this->skipGroupForward($tokens, $i);
        }

        return false;
    }

    /**
     * True when the token sits in a `match` arm's condition rather than its
     * result.
     *
     * Walking back from the token to the match's `{`, the nearest `=>` ends
     * the previous arm — unless a comma separates it from the token, which
     * means a new arm's condition list has started (and commas *within* one
     * arm's condition list keep the flag set, so multi-condition arms are
     * covered). Reaching `{` with no `=>` behind is the first arm.
     *
     * A callback opening inside the `match` holds the token in its own body, so
     * the arm boundaries around it are the caller's, not the token's.
     */
    private function isAMatchArmCondition(
        File $phpcsFile,
        int $stackPtr,
        int $matchPtr,
        ?int $callback
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $scopeOpener = $tokens[$matchPtr]['scope_opener'] ?? null;

        if ($scopeOpener === null) {
            return false;
        }

        if (
            $callback !== null
            && $callback > $scopeOpener
        ) {
            return false;
        }

        $passedComma = false;

        for ($i = ($stackPtr - 1); $i > $scopeOpener; $i--) {
            $code = $tokens[$i]['code'];

            if ($code === T_MATCH_ARROW) {
                return $passedComma;
            }

            if ($code === T_COMMA) {
                $passedComma = true;
            }

            $i = $this->skipGroupBackward($tokens, $i);
        }

        return true;
    }

    /**
     * True when the token sits in a `switch` case *label* rather than a case
     * body — walking back reaches `case` before the label's `:` or any
     * statement boundary.
     *
     * An enclosing callback floors that walk: a `case` further back than the
     * callback's own opener labels the caller's branch, not the token's.
     */
    private function isASwitchCaseCondition(File $phpcsFile, int $stackPtr, ?int $callback): bool
    {
        $tokens = $phpcsFile->getTokens();
        $floor = $callback ?? 0;

        for ($i = ($stackPtr - 1); $i > $floor; $i--) {
            $code = $tokens[$i]['code'];

            if ($code === T_CASE) {
                return true;
            }

            if (in_array($code, [T_CLOSE_CURLY_BRACKET, T_COLON, T_OPEN_CURLY_BRACKET, T_SEMICOLON], true)) {
                return false;
            }

            $i = $this->skipGroupBackward($tokens, $i);
        }

        return false;
    }

    /**
     * Returns the pointer a forward scan should continue from: the closer of a
     * balanced group opening at $stackPtr, or $stackPtr itself.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function skipGroupForward(array $tokens, int $stackPtr): int
    {
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_OPEN_PARENTHESIS && isset($tokens[$stackPtr]['parenthesis_closer'])) {
            return $tokens[$stackPtr]['parenthesis_closer'];
        }

        if (
            ($code === T_OPEN_SHORT_ARRAY || $code === T_OPEN_SQUARE_BRACKET)
            && isset($tokens[$stackPtr]['bracket_closer'])
        ) {
            return $tokens[$stackPtr]['bracket_closer'];
        }

        return $stackPtr;
    }

    /**
     * Returns the pointer a backward scan should continue from: the opener of
     * a balanced group closing at $stackPtr, or $stackPtr itself.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function skipGroupBackward(array $tokens, int $stackPtr): int
    {
        $code = $tokens[$stackPtr]['code'];

        if ($code === T_CLOSE_PARENTHESIS && isset($tokens[$stackPtr]['parenthesis_opener'])) {
            return $tokens[$stackPtr]['parenthesis_opener'];
        }

        if (
            ($code === T_CLOSE_SHORT_ARRAY || $code === T_CLOSE_SQUARE_BRACKET)
            && isset($tokens[$stackPtr]['bracket_opener'])
        ) {
            return $tokens[$stackPtr]['bracket_opener'];
        }

        return $stackPtr;
    }
}
