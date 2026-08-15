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
 * - **Same-named symbols** — different symbols that merely share a name:
 *   namespaced functions (`Acme\func_get_args()`), method calls
 *   (`$collector->func_num_args()`, `Collector::func_num_args()`), function
 *   declarations (`function func_get_args()`, including return-by-reference
 *   `function &func_get_args()`), instantiations (`new func_get_args()`), and
 *   names bound to another namespace by a function import — either the
 *   statement-wide `use function Acme\func_get_args;` or the per-entry prefix
 *   of a mixed group (`use Acme\{ClassA, function func_get_args};`).
 * - **`namespace\func_get_args()` inside a named namespace** — the relative
 *   qualifier resolves against the current namespace with no fallback to the
 *   global one, so it is not PHP's function. Where the current namespace *is*
 *   the global one — a file that declares no namespace, or a braced
 *   `namespace { … }` block whatever precedes it — it resolves to PHP's
 *   function and *is* flagged, as is a bare leading separator
 *   (`\func_get_args()`), which qualifies the global namespace.
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
     * PHP's own function: an object or static member access, a declaration of a
     * same-named function, or an instantiation of a same-named class.
     */
    private const NOT_A_CALL_BEFORE = [
        T_DOUBLE_COLON,
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_FUNCTION,
        T_NEW,
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
            return $this->isGlobalQualifiedName($phpcsFile, $prevPtr, $stackPtr);
        }

        // A return-by-reference declaration (`function &func_get_args()`) puts
        // a `&` between the keyword and the name, so T_FUNCTION is not the
        // immediately preceding token. Only a declaration is excused here: in
        // an expression (`$mask & func_get_args()`) the call is still a call.
        if ($prevCode === T_BITWISE_AND) {
            $beforeAmpersandPtr = $phpcsFile
                ->findPrevious(Tokens::$emptyTokens, ($prevPtr - 1), null, true);

            if (
                $beforeAmpersandPtr !== false
                && $tokens[$beforeAmpersandPtr]['code'] === T_FUNCTION
            ) {
                return false;
            }
        }

        if (in_array($prevCode, self::NOT_A_CALL_BEFORE, true) === true) {
            return false;
        }

        // An unqualified name resolves through the file's `use function`
        // imports before falling back to PHP's own function.
        $imported = $this->getImportedFunctionNames($phpcsFile);

        return isset($imported[strtolower($tokens[$stackPtr]['content'])]) === false;
    }

    /**
     * Reports whether the qualified name whose last separator sits at
     * $separatorPtr resolves to PHP's own function in the global namespace.
     */
    private function isGlobalQualifiedName(File $phpcsFile, int $separatorPtr, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $qualifierPtr = $phpcsFile
            ->findPrevious(Tokens::$emptyTokens, ($separatorPtr - 1), null, true);

        if ($qualifierPtr === false) {
            return true;
        }

        // `Acme\func_get_args()` — somebody else's function.
        if ($tokens[$qualifierPtr]['code'] === T_STRING) {
            return false;
        }

        // `namespace\func_get_args()` resolves against the *current*
        // namespace with no fallback to the global one, so it is PHP's
        // function only where that current namespace is itself the global
        // one — an undeclared namespace or a braced block. (PHPCS splits
        // PHP 8's T_NAME_RELATIVE back into T_NAMESPACE + T_NS_SEPARATOR +
        // T_STRING, so the qualifier is the `namespace` keyword itself.)
        if ($tokens[$qualifierPtr]['code'] === T_NAMESPACE) {
            return $this->isInsideNamedNamespace($phpcsFile, $stackPtr) === false;
        }

        // A bare leading separator qualifies the global namespace itself, so
        // `\func_get_args()` is still PHP's function.
        return true;
    }

    /**
     * Reports whether $stackPtr sits inside a named namespace, in which an
     * unqualified or `namespace\`-relative name no longer resolves to PHP's
     * global function by that route.
     */
    private function isInsideNamedNamespace(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        // A braced `namespace … { … }` block owns every token inside it, so
        // the enclosing declaration is in the call's own conditions. That
        // answer is authoritative: it stays right where an earlier, sibling
        // block would mislead a backwards scan — a call in `namespace { … }`
        // is in the global namespace however many named blocks precede it.
        foreach ($tokens[$stackPtr]['conditions'] as $scopePtr => $code) {
            if ($code === T_NAMESPACE) {
                return $this->isNamedNamespaceDeclaration($phpcsFile, $scopePtr);
            }
        }

        // The unbraced `namespace Acme;` form opens no scope, so the nearest
        // declaration above the call governs instead. A file may not mix the
        // two forms, so this runs only for unbraced files — where the global
        // namespace has no declaration of its own to find.
        $namespacePtr = $phpcsFile->findPrevious(T_NAMESPACE, ($stackPtr - 1));

        while ($namespacePtr !== false) {
            if ($this->isNamedNamespaceDeclaration($phpcsFile, $namespacePtr) === true) {
                return true;
            }

            $namespacePtr = $phpcsFile->findPrevious(T_NAMESPACE, ($namespacePtr - 1));
        }

        return false;
    }

    /**
     * Reports whether the `namespace` keyword at $namespacePtr declares a
     * *named* namespace (`namespace Acme;` or `namespace Acme { … }`).
     *
     * The unnamed global block (`namespace { … }`) is followed by a brace and
     * a `namespace\name` qualifier by a separator, so neither is a named
     * declaration.
     */
    private function isNamedNamespaceDeclaration(File $phpcsFile, int $namespacePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $afterPtr = $phpcsFile
            ->findNext(Tokens::$emptyTokens, ($namespacePtr + 1), null, true);

        return $afterPtr !== false && $tokens[$afterPtr]['code'] === T_STRING;
    }

    /**
     * The local names bound by a function import to a symbol outside the global
     * namespace, lowercased and used as keys (PHP function names are
     * case-insensitive). Both spellings count: the statement-wide
     * `use function Acme\one;` and the per-entry `use Acme\{ClassA, function
     * one};` of a mixed group.
     *
     * An unqualified import under the same name (`use function func_get_args;`)
     * binds PHP's own function and so is deliberately not collected — calls
     * through it are still dynamic argument reads. An alias that renames a
     * different function onto one of these names (`use function tally as
     * func_num_args;`) does bind a different symbol, and is collected.
     *
     * Imports are read file-wide rather than per namespace block. The only
     * file that misreads is one declaring several braced namespaces with
     * conflicting function imports, where the cost is a missed report rather
     * than a false one — the safer direction for a linter nobody keeps
     * switched on once it cries wolf.
     *
     * @return array<string, true>
     */
    private function getImportedFunctionNames(File $phpcsFile): array
    {
        $names = [];
        $usePtr = $phpcsFile->findNext(T_USE, 0);

        while ($usePtr !== false) {
            $startPtr = $phpcsFile
                ->findNext(Tokens::$emptyTokens, ($usePtr + 1), null, true);

            if (
                $startPtr !== false
                && $this->bindsFunctionNames($phpcsFile, $startPtr) === true
            ) {
                $this->collectImportedNames($phpcsFile, $startPtr, $names);
            }

            $usePtr = $phpcsFile->findNext(T_USE, ($usePtr + 1));
        }

        return $names;
    }

    /**
     * Reports whether the `use` statement starting at $startPtr can bind a
     * function name at all: it carries a `function` keyword of its own, or it
     * is a group, whose entries carry their own.
     *
     * Everything else binds names this sniff never consults — a trait's
     * `use SomeTrait;`, a plain class or const import — or is not an import at
     * all: a closure's `use ($captured)`, whose body must not be read as a list
     * of entries.
     */
    private function bindsFunctionNames(File $phpcsFile, int $startPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (
            $tokens[$startPtr]['code'] === T_STRING
            && strtolower($tokens[$startPtr]['content']) === 'function'
        ) {
            return true;
        }

        $endPtr = $phpcsFile->findNext(T_SEMICOLON, $startPtr);

        if ($endPtr === false) {
            return false;
        }

        return $phpcsFile->findNext(T_OPEN_USE_GROUP, $startPtr, $endPtr) !== false;
    }

    /**
     * Collects the local names a `use` statement binds to a function, reading
     * from $startPtr — the first token after the `use` keyword. Covers
     * comma-separated lists, group use (`use function Acme\{one, two};`) and
     * aliases (`… as name`).
     *
     * PHPCS tokenizes the `function` of an import as a plain T_STRING. It
     * prefixes the whole statement (`use function Acme\one;`) or, inside a
     * group, only the entry that follows it (`use Acme\{ClassA, function
     * one};`); an entry with no prefix of its own falls back to the
     * statement's, so the class entries of a mixed group bind no function name.
     * A `const` entry needs no handling of its own: its keyword reads as a name
     * segment, and the entry stays on the statement's kind either way.
     *
     * @param array<string, true> $names
     *
     * @return void
     */
    private function collectImportedNames(File $phpcsFile, int $startPtr, array &$names): void
    {
        $tokens = $phpcsFile->getTokens();
        $endPtr = $phpcsFile->findNext(T_SEMICOLON, $startPtr);

        if ($endPtr === false) {
            return;
        }

        $groupPrefix = [];
        $segments = [];
        $alias = null;
        $afterAs = false;
        $statementImportsFunctions = false;
        $entryImportsFunction = false;

        for ($ptr = $startPtr; $ptr <= $endPtr; $ptr++) {
            $code = $tokens[$ptr]['code'];

            if ($code === T_STRING) {
                if ($this->isFunctionKeyword($phpcsFile, $ptr) === true) {
                    $entryImportsFunction = true;

                    // Before the group opens the keyword prefixes every entry
                    // in the statement; inside it, only the entry that follows.
                    if ($groupPrefix === []) {
                        $statementImportsFunctions = true;
                    }

                    continue;
                }

                if ($afterAs === true) {
                    $alias = $tokens[$ptr]['content'];
                } else {
                    $segments[] = $tokens[$ptr]['content'];
                }

                continue;
            }

            if ($code === T_AS) {
                $afterAs = true;

                continue;
            }

            if ($code === T_OPEN_USE_GROUP) {
                $groupPrefix = $segments;
                $segments = [];

                continue;
            }

            if (in_array($code, [T_COMMA, T_CLOSE_USE_GROUP, T_SEMICOLON], true) === false) {
                continue;
            }

            // An entry ends here. It binds PHP's own function only when the
            // target is unqualified — a single segment, so the global
            // namespace — *and* is bound under that same name. Anything else
            // (a namespaced target, or an alias renaming one function onto
            // another's name) binds a different symbol.
            if ($entryImportsFunction === true && $segments !== []) {
                $targetName = (string) end($segments);
                $localName = $alias ?? $targetName;

                if (
                    (count($groupPrefix) + count($segments)) > 1
                    || strtolower($localName) !== strtolower($targetName)
                ) {
                    $names[strtolower($localName)] = true;
                }
            }

            $segments = [];
            $alias = null;
            $afterAs = false;
            $entryImportsFunction = $statementImportsFunctions;

            if ($code === T_CLOSE_USE_GROUP) {
                $groupPrefix = [];
            }
        }
    }

    /**
     * Reports whether the T_STRING at $ptr is an import's `function` keyword
     * rather than a name.
     *
     * PHP 8 allows a reserved word as a name segment, so `function` is not a
     * keyword everywhere it appears: in `use Acme\function\Collector;` it names
     * part of the namespace being imported from. A separator after it is what
     * tells the two apart — a keyword is followed by the name it prefixes.
     */
    private function isFunctionKeyword(File $phpcsFile, int $ptr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (strtolower($tokens[$ptr]['content']) !== 'function') {
            return false;
        }

        $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

        return $afterPtr !== false && $tokens[$afterPtr]['code'] !== T_NS_SEPARATOR;
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
