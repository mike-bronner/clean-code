<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

/**
 * The one answer to "is this T_STRING a call to PHP's own global function?".
 *
 * Every sniff that flags a global function call — debug helpers, `eval`-likes,
 * superglobal accessors, the native array functions — needs to tell a real call
 * apart from a same-named method, declaration, class, or imported symbol. Each
 * one used to hand-roll that test, and the copies drifted: one handled
 * `namespace\foo()` and missed `function &foo()`, the next inherited the gap
 * and dropped the fix. This class is the single implementation they all route
 * through, so a case fixed here is fixed everywhere at once.
 *
 * The caller still owns *which* names it cares about. This class answers only
 * whether the name at `$stackPtr` is being called and resolves to the global
 * namespace; matching it against a list of forbidden functions is the sniff's
 * own job.
 */
final class FunctionCalls
{
    /**
     * Tokens that, directly before the name, mean this is not a call to a
     * global function: a member access, a declaration, or an instantiation.
     */
    private const NON_CALL_PRECEDERS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
    ];

    /**
     * Whether the T_STRING at $stackPtr is a call to a global-namespace
     * function.
     *
     * False for member access (`$o->foo()`, `$o?->foo()`, `C::foo()`), for
     * declarations (`function foo()`, and `function &foo()` where the `&` sits
     * between the keyword and the name), for instantiation (`new foo()`,
     * `new \foo()`, `new namespace\foo()`), for qualified names
     * (`Acme\foo()`), for attribute names (`#[foo(1)]`), and for a bare name a
     * `use function` import redirects elsewhere.
     *
     * True for a bare `foo()` with no import redirecting it, and for
     * `\foo()` — a leading separator qualifies the global namespace, so it is
     * the most explicit form of the very call being looked for.
     *
     * `namespace\foo()` resolves against whichever namespace is in force where
     * it is written, so it is PHP's own function exactly where that namespace
     * is the global one — an undeclared file or a `namespace { … }` block — and
     * somebody else's everywhere a name has been declared.
     *
     * Known limitation, deliberate: a function *declared* in the current
     * namespace shadows the global fallback for bare calls in that namespace,
     * and this does not model that. Handling it means resolving declarations
     * across a whole namespace rather than reading one statement, and the
     * shape does not occur in the standards these sniffs enforce.
     */
    public static function isGlobalFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]) === false || $tokens[$stackPtr]['code'] !== T_STRING) {
            return false;
        }

        // Inside an attribute, the name is the attribute's class. Attribute
        // arguments are constant expressions and so can hold no call at all,
        // which makes the whole attribute region safe to rule out at once.
        if (isset($tokens[$stackPtr]['attribute_opener']) === true) {
            return false;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($next === false || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS) {
            return false;
        }

        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($prev === false) {
            return false;
        }

        if (in_array($tokens[$prev]['code'], self::NON_CALL_PRECEDERS, true) === true) {
            return false;
        }

        if (
            $tokens[$prev]['code'] === T_BITWISE_AND
            && self::isReturnByReferenceMarker($phpcsFile, $prev) === true
        ) {
            return false;
        }

        if ($tokens[$prev]['code'] === T_NS_SEPARATOR) {
            return self::isGlobalQualifier($phpcsFile, $prev, $stackPtr);
        }

        return self::isImportedFunctionName($phpcsFile, $stackPtr) === false;
    }

    /**
     * Whether the T_BITWISE_AND at $ampersandPtr is the return-by-reference
     * marker of a declaration — `function &foo()` — rather than an operator.
     *
     * The marker sits between the keyword and the name, so the declaration is
     * invisible to a check that only reads the token directly before the name.
     */
    private static function isReturnByReferenceMarker(File $phpcsFile, int $ampersandPtr): bool
    {
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ampersandPtr - 1), null, true);

        return $before !== false && $phpcsFile->getTokens()[$before]['code'] === T_FUNCTION;
    }

    /**
     * Whether the name whose last separator sits at $separatorPtr reaches PHP's
     * own function — both that it resolves to the global namespace, and that
     * the construct is a call at all.
     *
     * `\foo()` does; `Acme\foo()` names somebody else's function; and
     * `namespace\foo()` does only where the namespace in force is the global
     * one. Every exclusion is applied to the token in front of the *whole*
     * name, qualifier included: qualifying a name changes which symbol it
     * reaches, never what the construct is, so `new \foo()` and
     * `new namespace\foo()` are the instantiations their bare spellings are.
     * That is the reason this looks past the qualifier rather than one token
     * back — a leading qualifier hides the `T_NEW` from the preceder check.
     */
    private static function isGlobalQualifier(File $phpcsFile, int $separatorPtr, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($separatorPtr - 1), null, true);

        if ($before === false) {
            return false;
        }

        if ($tokens[$before]['code'] === T_STRING) {
            return false;
        }

        $isRelative = ($tokens[$before]['code'] === T_NAMESPACE);
        $preceder = $isRelative === true
            ? $phpcsFile->findPrevious(Tokens::$emptyTokens, ($before - 1), null, true)
            : $before;

        if (
            $preceder !== false
            && in_array($tokens[$preceder]['code'], self::NON_CALL_PRECEDERS, true) === true
        ) {
            return false;
        }

        if ($isRelative === true) {
            return self::isInsideNamedNamespace($phpcsFile, $stackPtr) === false;
        }

        return true;
    }

    /**
     * Whether $stackPtr sits inside a *named* namespace, where a `namespace\`
     * relative qualifier no longer reaches PHP's own function.
     *
     * The unnamed `namespace { … }` block is the global namespace, however many
     * named blocks precede it in the file, so the block a call sits *in*
     * decides — never whichever declaration happens to appear above it.
     */
    private static function isInsideNamedNamespace(File $phpcsFile, int $stackPtr): bool
    {
        $declarations = self::namespaceDeclarations($phpcsFile);
        $block = self::namespaceBlockOf($declarations, $stackPtr);

        return $block !== 0 && self::isNamedDeclaration($phpcsFile, $block);
    }

    /**
     * Whether the `namespace` keyword at $namespacePtr names its namespace
     * (`namespace Acme;`, `namespace Acme { … }`) rather than opening the
     * global block (`namespace { … }`), which is followed by a brace.
     */
    private static function isNamedDeclaration(File $phpcsFile, int $namespacePtr): bool
    {
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($namespacePtr + 1), null, true);

        return $after !== false && $phpcsFile->getTokens()[$after]['code'] === T_STRING;
    }

    /**
     * Whether a bare name is redirected by a `use function` import in the same
     * namespace block, in which case the call never reaches PHP's own function.
     */
    private static function isImportedFunctionName(File $phpcsFile, int $stackPtr): bool
    {
        $analysis = self::analyze($phpcsFile);

        if ($analysis['imports'] === []) {
            return false;
        }

        $block = self::namespaceBlockOf($analysis['namespaces'], $stackPtr);
        $name = strtolower($phpcsFile->getTokens()[$stackPtr]['content']);

        return isset($analysis['imports'][$block][$name]);
    }

    /**
     * The file's `use function` imports and namespace blocks.
     *
     * Deliberately recomputed rather than cached. Caching it would have to
     * survive PHPCS's fixer re-parsing the *same* File object with new content
     * after every fix pass, and every cheap invalidation signal available here
     * — the token count, the content length — can be left unchanged by a
     * same-length rewrite. A wrong answer from a stale entry costs more than
     * the scan does, and the scan is only ever reached by a bare name that
     * everything else has already failed to explain: a call behind an operator,
     * a qualifier, `new`, a declaration, or an attribute never gets this far,
     * and a sniff has narrowed the name to its own list before that.
     *
     * @return array{imports: array<int, array<string, true>>, namespaces: array<int, int|null>}
     */
    private static function analyze(File $phpcsFile): array
    {
        $namespaces = self::namespaceDeclarations($phpcsFile);

        return [
            'namespaces' => $namespaces,
            'imports' => self::functionImports($phpcsFile, $namespaces),
        ];
    }

    /**
     * Every namespace *declaration* in the file, mapped to the pointer its
     * block ends at — the closing brace for `namespace A { … }`, or null for
     * the unbraced `namespace A;` form, whose block runs to the next
     * declaration or the end of the file.
     *
     * T_NAMESPACE is also the `namespace\foo()` relative-qualifier operator.
     * A declaration is followed by a name or an opening brace; the operator is
     * always followed by a separator, which is what tells the two apart.
     *
     * @return array<int, int|null>
     */
    private static function namespaceDeclarations(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $declarations = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_NAMESPACE) {
                continue;
            }

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            if ($next === false || $tokens[$next]['code'] === T_NS_SEPARATOR) {
                continue;
            }

            $declarations[$ptr] = $tokens[$ptr]['scope_closer'] ?? null;
        }

        return $declarations;
    }

    /**
     * The namespace block a pointer sits in, identified by its declaration's
     * pointer — 0 for code outside any declared namespace.
     *
     * @param array<int, int|null> $declarations
     */
    private static function namespaceBlockOf(array $declarations, int $stackPtr): int
    {
        $block = 0;

        foreach ($declarations as $declaration => $closer) {
            if ($declaration >= $stackPtr) {
                break;
            }

            // An unbraced block runs until the next declaration, so it always
            // claims a later pointer; a braced one claims it only up to its
            // closing brace, after which the file is back outside a namespace.
            $block = ($closer === null || $stackPtr < $closer) ? $declaration : 0;
        }

        return $block;
    }

    /**
     * Every name a `use function` import binds, grouped by namespace block.
     *
     * Covers the plain form, the comma-separated list, the aliased form, group
     * use, and the mixed group use that carries `function` on the individual
     * entry. Imports of classes and constants are deliberately absent: neither
     * takes part in resolving a function call, so neither may suppress one.
     *
     * @param array<int, int|null> $declarations
     *
     * @return array<int, array<string, true>>
     */
    private static function functionImports(File $phpcsFile, array $declarations): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_USE || self::isNamespaceLevel($phpcsFile, $ptr) === false) {
                continue;
            }

            $end = $phpcsFile->findNext(T_SEMICOLON, ($ptr + 1));

            if ($end === false) {
                continue;
            }

            $block = self::namespaceBlockOf($declarations, $ptr);

            foreach (self::importedFunctionNames($phpcsFile, $ptr, $end) as $name) {
                if ($name === '') {
                    continue;
                }

                $imports[$block][$name] = true;
            }
        }

        return $imports;
    }

    /**
     * Whether the T_USE at $usePtr sits at namespace level, where an import is
     * the only thing it can be.
     *
     * A trait use is ruled out here rather than by reading the statement,
     * because reading it is what is unsafe: an import is measured by the next
     * semicolon, and a trait use whose adaptation block is empty
     * (`use A, B {}`) has none of its own. Measuring one that way runs the span
     * on to whatever semicolon comes next in the file — a real import in some
     * other namespace block, whose entries then bind against the block holding
     * the trait use, silencing calls that block never imported anything for.
     *
     * A class-like or function condition is what marks that trait use, since
     * an import is legal only where nothing encloses it but a namespace. The
     * closure capture list that also sits at namespace level needs no such
     * care: it ends at its own statement's semicolon like any expression, and
     * the parenthesis it opens with is not the `function` keyword an import
     * has to lead with.
     */
    private static function isNamespaceLevel(File $phpcsFile, int $usePtr): bool
    {
        foreach (($phpcsFile->getTokens()[$usePtr]['conditions'] ?? []) as $condition) {
            if ($condition !== T_NAMESPACE) {
                return false;
            }
        }

        return true;
    }

    /**
     * The function names bound by the single `use` statement spanning
     * $usePtr..$endPtr, lower-cased because PHP resolves function names
     * case-insensitively. Empty for any statement that is not a function
     * import — a class or constant import, or a closure's captured variables,
     * none of which lead with the `function` keyword. A trait use never reaches
     * here; isNamespaceLevel() has already turned it away.
     *
     * Both spellings bind more than one name at a time — `use function A\b,
     * A\c;` as much as `use function A\{b, c};` — so both are read as a comma
     * separated list. Reading either as a single entry keeps only its last
     * name, and every earlier one is then left looking like a call to PHP's
     * own function.
     *
     * @return array<int, string>
     */
    private static function importedFunctionNames(File $phpcsFile, int $usePtr, int $endPtr): array
    {
        $groupOpener = $phpcsFile->findNext(T_OPEN_USE_GROUP, ($usePtr + 1), $endPtr);
        $isFunctionUse = self::isFunctionKeyword($phpcsFile, ($usePtr + 1), $endPtr);

        // The `function` keyword leads the whole statement in the no-group
        // form, so one that does not carry it binds nothing however many names
        // it lists — a class or constant import, or a closure's captured
        // variables, whose own commas must never be read as import entries.
        if (
            $groupOpener === false
            && $isFunctionUse === false
        ) {
            return [];
        }

        $entries = $groupOpener === false
            ? self::commaEntries($phpcsFile, ($usePtr + 1), $endPtr)
            : self::groupEntries($phpcsFile, $groupOpener, $endPtr);

        // A group's prefix carries the namespace for every entry inside the
        // braces, so no entry of a group can source from the global namespace.
        $prefixQualified = $groupOpener !== false;
        $names = [];

        foreach ($entries as [$start, $end]) {
            if ($isFunctionUse === false && self::isFunctionKeyword($phpcsFile, $start, $end) === false) {
                continue;
            }

            if (self::bindsGlobalFunction($phpcsFile, $start, $end, $prefixQualified) === true) {
                continue;
            }

            $names[] = self::boundName($phpcsFile, $start, $end);
        }

        return $names;
    }

    /**
     * Whether the entry spanning $start..$end imports PHP's own function under
     * its own name — `use function json_encode;`, and the redundant
     * `use function json_encode as json_encode;`.
     *
     * Such an import redirects nothing: the name it binds is the very function
     * a bare call would reach anyway, so treating it as a redirect would
     * silence every call in the file. An unqualified source under a *different*
     * alias does bind another symbol (`use function tally as json_encode;`
     * makes `json_encode()` call `tally`), and so is not one of these.
     */
    private static function bindsGlobalFunction(
        File $phpcsFile,
        int $start,
        int $end,
        bool $prefixQualified
    ): bool {
        if ($prefixQualified === true || $phpcsFile->findNext(T_NS_SEPARATOR, $start, $end) !== false) {
            return false;
        }

        $source = self::sourceName($phpcsFile, $start, $end);

        return $source !== '' && $source === self::boundName($phpcsFile, $start, $end);
    }

    /**
     * The name an import entry reads *from*, which is its first name token.
     *
     * PHPCS tokenises the `function` keyword of an import as a plain T_STRING,
     * so it has to be stepped over by content: it leads the statement in the
     * no-group form and an individual entry in a mixed group.
     */
    private static function sourceName(File $phpcsFile, int $start, int $end): string
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findNext(T_STRING, $start, $end);

        if ($namePtr !== false && strtolower($tokens[$namePtr]['content']) === 'function') {
            $namePtr = $phpcsFile->findNext(T_STRING, ($namePtr + 1), $end);
        }

        return $namePtr === false ? '' : strtolower($tokens[$namePtr]['content']);
    }

    /**
     * Whether the first meaningful token in $start..$end is the `function`
     * keyword. PHPCS tokenises it as a plain T_STRING in a `use` statement
     * rather than T_FUNCTION, so this reads the content, not the type.
     *
     * PHP 8 allows a reserved word as a name segment, so the content alone does
     * not settle it: in `use Acme\{function\Collector, …}` the word names part
     * of the namespace being imported from, and the entry is a class import.
     * What tells the two apart is the token after it — a keyword prefixes the
     * name it imports, a segment is followed by the separator to the next.
     */
    private static function isFunctionKeyword(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $end, true);

        if ($first === false || strtolower($tokens[$first]['content']) !== 'function') {
            return false;
        }

        $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($first + 1), $end, true);

        return $after !== false && $tokens[$after]['code'] !== T_NS_SEPARATOR;
    }

    /**
     * The name a single import entry binds into the current namespace.
     *
     * That is always the entry's last name token: the alias in
     * `Acme\dump as render`, the trailing segment in `Acme\Support\dump`. The
     * distinction matters because an alias leaves the *source* name alone —
     * `use function Acme\dump as render;` binds `render`, and a bare `dump()`
     * in that file still resolves to PHP's own function.
     */
    private static function boundName(File $phpcsFile, int $start, int $end): string
    {
        $namePtr = $phpcsFile->findPrevious(T_STRING, ($end - 1), $start);

        return $namePtr === false ? '' : strtolower($phpcsFile->getTokens()[$namePtr]['content']);
    }

    /**
     * The comma-separated entries between a group use's braces, each as a
     * [start, end) pointer pair.
     *
     * @return array<int, array{int, int}>
     */
    private static function groupEntries(File $phpcsFile, int $groupOpener, int $endPtr): array
    {
        $closer = $phpcsFile->findNext(T_CLOSE_USE_GROUP, ($groupOpener + 1), $endPtr);
        $closer = $closer === false ? $endPtr : $closer;

        return self::commaEntries($phpcsFile, ($groupOpener + 1), $closer);
    }

    /**
     * A comma-separated list spanning $start..$end, each entry as a
     * [start, end) pointer pair. A `use` statement's entries hold no nesting of
     * their own — neither a group body nor a plain list may contain a further
     * comma-bearing construct — so splitting on every comma in the span is
     * exact.
     *
     * @return array<int, array{int, int}>
     */
    private static function commaEntries(File $phpcsFile, int $start, int $end): array
    {
        $entries = [];

        while ($start < $end) {
            $comma = $phpcsFile->findNext(T_COMMA, $start, $end);
            $entryEnd = $comma === false ? $end : $comma;
            $entries[] = [$start, $entryEnd];
            $start = ($entryEnd + 1);
        }

        return $entries;
    }
}
