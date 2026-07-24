<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Methods;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the "Methods: Declared Parameters" standard.
 *
 * A method's signature is its contract: reading arguments the signature never
 * declared — via `func_get_args()`, `func_get_arg()`, or `func_num_args()` —
 * hides that contract inside the body, so a caller cannot tell what the method
 * accepts without reading its implementation (mental debt). This sniff flags
 * every call to those three functions, reporting at the call itself.
 *
 * The rule applies to every function-like declaration, not just methods: a
 * closure, an arrow function, and a plain function all declare a parameter
 * list, and hiding it behind a dynamic read costs the reader exactly the same.
 *
 * Deliberately not flagged:
 *
 * - **Variadic parameters** (`function join(string ...$parts)`) — a variadic
 *   *is* a declared parameter: it is named, optionally typed, and visible in
 *   the signature. It is the modern replacement for `func_get_args()`, and the
 *   standard asks for a declared list, not a fixed-length one.
 * - **Magic methods** — the standard's stated exception. `__call()` and friends
 *   are invoked by the engine against a fixed signature, so their bodies may
 *   legitimately reach for the dynamic argument list. The exemption belongs to
 *   the magic method itself: a closure or arrow function *inside* one declares
 *   its own parameter list and does not inherit it.
 * - **Namespaced lookalikes** (`Acme\func_get_args()`), method calls
 *   (`$collector->func_num_args()`, `Collector::func_num_args()`), and function
 *   declarations (`function func_get_args()`) — different symbols that merely
 *   share a name.
 *
 * Detection only: replacing a dynamic read with a declared parameter changes
 * the method's signature, and every call site has to change with it, so a
 * token-based auto-fix cannot be applied safely.
 */
class DeclaredParametersSniff implements Sniff
{
    /**
     * PHP's dynamic argument-list functions — the ones that read arguments a
     * signature never declared. Compared lowercased: PHP function names are
     * case-insensitive.
     */
    private const DYNAMIC_ARGUMENT_FUNCTIONS = [
        'func_get_args',
        'func_get_arg',
        'func_num_args',
    ];

    /**
     * PHP's magic methods, lowercased — the standard's stated exception.
     */
    private const MAGIC_METHODS = [
        '__construct',
        '__destruct',
        '__call',
        '__callstatic',
        '__get',
        '__set',
        '__isset',
        '__unset',
        '__sleep',
        '__wakeup',
        '__serialize',
        '__unserialize',
        '__tostring',
        '__invoke',
        '__set_state',
        '__clone',
        '__debuginfo',
    ];

    /**
     * Tokens that, sitting directly before the name, mean this is not a call to
     * PHP's own function: an object or static member access, or a declaration
     * of a same-named function in a namespace.
     */
    private const NOT_A_CALL_BEFORE = [
        T_DOUBLE_COLON,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_FUNCTION,
    ];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_STRING];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $name = $tokens[$stackPtr]['content'];

        if (in_array(strtolower($name), self::DYNAMIC_ARGUMENT_FUNCTIONS, true) === false) {
            return;
        }

        if ($this->isGlobalFunctionCall($phpcsFile, $stackPtr) === false) {
            return;
        }

        if ($this->isInsideMagicMethod($phpcsFile, $stackPtr) === true) {
            return;
        }

        $phpcsFile->addError(
            '%s() is not allowed; declare the parameter list instead of reading arguments dynamically',
            $stackPtr,
            'DynamicArguments',
            [$name]
        );
    }

    /**
     * Reports whether the name at $stackPtr is a call to PHP's own global
     * function rather than a same-named member, declaration, or namespaced
     * function.
     */
    private function isGlobalFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        // A name with nothing after it (a truncated file) cannot be a call, and
        // there is no token to inspect — refuse rather than index on `false`.
        $nextPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $nextPtr === false
            || $tokens[$nextPtr]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return false;
        }

        $prevPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);
        $prevCode = ($prevPtr === false ? null : $tokens[$prevPtr]['code']);

        if ($prevCode === T_NS_SEPARATOR) {
            // A bare leading separator qualifies the *global* namespace, so
            // `\func_get_args()` is still PHP's function; a name before it
            // (`Acme\func_get_args()`) makes it somebody else's.
            $qualifierPtr = $phpcsFile
                ->findPrevious(Tokens::$emptyTokens, ($prevPtr - 1), null, true);

            return $qualifierPtr === false || $tokens[$qualifierPtr]['code'] !== T_STRING;
        }

        return in_array($prevCode, self::NOT_A_CALL_BEFORE, true) === false;
    }

    /**
     * Reports whether the call at $stackPtr sits directly in a magic method's
     * body — the standard's one exception.
     */
    private function isInsideMagicMethod(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $functionPtr = null;

        // The innermost declared scope owns the parameter list the call reads.
        // A closure declares its own, so it never inherits the exemption of a
        // magic method it happens to sit in.
        foreach (array_reverse($tokens[$stackPtr]['conditions'], true) as $scopePtr => $code) {
            if ($code === T_CLOSURE) {
                return false;
            }

            if ($code === T_FUNCTION) {
                $functionPtr = $scopePtr;

                break;
            }
        }

        if ($functionPtr === null) {
            return false;
        }

        if ($this->isInsideArrowFunction($phpcsFile, $functionPtr, $stackPtr) === true) {
            return false;
        }

        return $this->isMagicMethod($phpcsFile, $functionPtr);
    }

    /**
     * Reports whether $stackPtr falls inside an arrow function — which, like a
     * closure, declares its own parameter list and so never inherits an
     * enclosing magic method's exemption.
     *
     * Arrow functions add no entry to a token's `conditions`, so an enclosing
     * one has to be found by span instead. $searchFrom bounds the scan to the
     * enclosing declaration: scopes nest, so an arrow function whose body
     * reaches the call cannot have been declared before it.
     */
    private function isInsideArrowFunction(File $phpcsFile, int $searchFrom, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $arrowPtr = $phpcsFile->findPrevious(T_FN, ($stackPtr - 1), $searchFrom);

        while ($arrowPtr !== false) {
            if (($tokens[$arrowPtr]['scope_closer'] ?? 0) >= $stackPtr) {
                return true;
            }

            $arrowPtr = $phpcsFile->findPrevious(T_FN, ($arrowPtr - 1), $searchFrom);
        }

        return false;
    }

    /**
     * Reports whether the function declared at $functionPtr is a magic method.
     * Magic methods only exist on object-oriented containers — a plain function
     * named `__get()` is just a function, and gets no exemption.
     */
    private function isMagicMethod(File $phpcsFile, int $functionPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $conditions = $tokens[$functionPtr]['conditions'];

        if (
            $conditions === []
            || in_array(end($conditions), Tokens::$ooScopeTokens, true) === false
        ) {
            return false;
        }

        $name = $phpcsFile->getDeclarationName($functionPtr);

        return $name !== null && in_array(strtolower($name), self::MAGIC_METHODS, true);
    }
}
