<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Constructors;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Enforces the token-visible slice of the "Primary + Named Constructors"
 * standard (docs/standards/constructors-primary-named-constructors.md).
 *
 * A named constructor is recognized by shape alone: a `static` method whose
 * declared return type is `self`, `static`, or the declaring class's own name.
 * The standard asks every one of them to obtain its instance through the
 * primary constructor, so initialization logic lives in exactly one place. A
 * body that carries neither `new self(...)` / `new static(...)` /
 * `new <DeclaringClass>(...)` nor a static call to another method of the same
 * class builds its instance some other way — `unserialize()`, reflection, a
 * deserializer's output returned raw — and is reported.
 *
 * Reported on the `function` keyword under the single code `Missing`: the
 * defect is the *absence* of delegation across the whole method, so it has no
 * statement of its own to point at. One warning per method.
 *
 * **Warning severity, not error.** A named constructor may legitimately return
 * an instance it did not build — a singleton or registry accessor handing back
 * a cached static property on the warm path. The sniff points at delegation
 * candidates rather than mandating a fix.
 *
 * **Detection only.** Routing a body through the primary constructor means
 * deciding which of its parameters each local value feeds, which is not a
 * mechanical rewrite.
 *
 * Delegation is accepted at the breadth of *any* static method of the
 * declaring class, not only another named constructor (#184). A named
 * constructor calling a plain private static helper that itself does
 * `new self(...)` still routes through the primary constructor, one hop
 * further out; requiring the callee to be a named constructor too would report
 * that shape for no defect.
 *
 * Deliberately **not** reported, and why:
 *
 * - **Instance methods**, whatever they return. A `withX()` wither returning
 *   `self` modifies a copy of an existing object; it constructs nothing, so
 *   the standard has nothing to say about it.
 * - **Bodiless methods** — an abstract declaration or an interface signature
 *   has no body in which delegation could appear.
 * - **Enum methods.** `new` on an enum is a fatal error, so an enum's named
 *   constructor can only ever return a case (`self::Active`) or the result of
 *   the engine's own `from()`/`tryFrom()`. Flagging it would state a
 *   requirement the language forbids satisfying.
 * - **A trait method return-typed to its eventual consumer by name.** A trait
 *   is compiled into whichever class uses it, and one file never says which
 *   class that is, so `: Money` inside `trait Zeroable` matches neither
 *   `self`/`static` nor the trait's own name, and is not recognized as a named
 *   constructor however its body builds the instance. Nothing in a single file
 *   can resolve it. A trait method typed `self` or `static` — the spelling
 *   that names no class — is inspected in full: `new self(...)` in its body
 *   reaches the consumer's primary constructor at use-time, which is why
 *   `T_TRAIT` is a constructible scope.
 *
 * Two deliberate limits on what counts as delegation, both erring toward
 * reporting rather than staying silent:
 *
 * - The class reference must carry **no namespace segment** — `self`,
 *   `static`, the bare class name, or the root-qualified spelling of that name
 *   in a file declaring no namespace, where the two are one class. A name with
 *   a segment in it cannot be resolved from one file: in a file declaring
 *   `Money`, `new \Other\Money()` names a different class far more often than
 *   the same one, and under `namespace App` so does `new \Money()`.
 * - A named constructor calling **itself** does not delegate. Without a `new`
 *   anywhere in the recursion it never reaches a constructor at all.
 *
 * Detection is file-local, as every PHPCS sniff's is. A cross-file factory
 * hierarchy — a named constructor delegating to a builder declared elsewhere —
 * stays with code review.
 */
class PrimaryConstructorDelegationSniff implements Sniff
{
    /**
     * Scopes whose direct member functions are methods that `new` can build.
     * An enum is class-like but cannot be instantiated, so it is absent.
     *
     * @var array<int, int|string>
     */
    private const CONSTRUCTIBLE_SCOPES = [T_CLASS, T_ANON_CLASS, T_TRAIT];

    /**
     * Return types that name the declaring class without spelling it out.
     *
     * @var array<int, string>
     */
    private const SELF_TYPES = ['self', 'static'];

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $method = $phpcsFile->getDeclarationName($stackPtr);

        // Defensive only, and no fixture can pin it: PHPCS gives a closure its
        // own T_CLOSURE token, which this sniff never registers for, so the
        // one nameless T_FUNCTION is a truncated declaration — and a
        // truncation deep enough to strip the name also strips the class
        // scope the owner check below needs.
        if ($method === null) {
            return;
        }

        $ownerPtr = $this->constructibleOwner($tokens[$stackPtr]['conditions']);

        if ($ownerPtr === null) {
            return;
        }

        $properties = $phpcsFile->getMethodProperties($stackPtr);

        if ($properties['is_static'] === false) {
            return;
        }

        // An anonymous class has no name, so only self/static can name it.
        $className = $phpcsFile->getDeclarationName($ownerPtr);

        if ($this->returnsDeclaringClass($phpcsFile, $properties['return_type'], $className) === false) {
            return;
        }

        // An abstract method or interface signature has no body to scan.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        if ($this->delegates($phpcsFile, $stackPtr, $className, $method) === true) {
            return;
        }

        $phpcsFile->addWarning(
            '%s() returns an instance without routing through the primary constructor; build it'
                . ' with new self(...) / new static(...), or delegate to another static method of'
                . ' the class, so initialization stays in one place'
                . ' (see docs/standards/constructors-primary-named-constructors.md)',
            $stackPtr,
            'Missing',
            [$method]
        );
    }

    /**
     * The pointer of the class-like scope holding this function directly, when
     * that scope is one `new` can build.
     *
     * A function nested in another function, or declared at file scope, has no
     * declaring class at all — a plain `function make(): self` is not a named
     * constructor.
     *
     * @param array<int, int|string> $conditions
     */
    private function constructibleOwner(array $conditions): ?int
    {
        if ($conditions === []) {
            return null;
        }

        $ownerPtr = array_key_last($conditions);

        if (in_array($conditions[$ownerPtr], self::CONSTRUCTIBLE_SCOPES, true) === false) {
            return null;
        }

        return $ownerPtr;
    }

    /**
     * Whether a declared return type names the declaring class.
     *
     * Both nullable spellings a `tryFrom()` carries are recognized as the
     * named constructors they are: `?self` by stripping the mark, `self|null`
     * by splitting on the union and intersection separators. Missing either
     * would leave such a method silently uninspected.
     *
     * So is the root-qualified spelling of the class's own name, where the
     * file declares no namespace and the two are one class. Reading it as a
     * foreign type instead would leave a bypassing named constructor silently
     * uninspected on nothing but a leading separator. Inside a namespace it is
     * a foreign type — `\Money` under `namespace App` is not `App\Money` — and
     * so is any name carrying a segment of its own.
     */
    private function returnsDeclaringClass(File $phpcsFile, string $returnType, ?string $className): bool
    {
        foreach (preg_split('/[|&]/', $returnType) as $part) {
            $spelling = ltrim(trim($part), '?');
            $type = strtolower(ltrim($spelling, '\\'));

            if (in_array($type, self::SELF_TYPES, true) === true) {
                return true;
            }

            if ($className === null || $type !== strtolower($className)) {
                continue;
            }

            if (str_starts_with($spelling, '\\') === false || $this->inGlobalNamespace($phpcsFile) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the file declares no namespace, which is what makes a
     * root-qualified name and a bare one the same class.
     *
     * A declaration is a `namespace` keyword followed by a name, and it has to
     * be the file's first statement — so the first such keyword settles it.
     * The same keyword opening a relative name (`namespace\Money`) or the
     * explicit global block (`namespace { ... }`) is followed by a separator
     * or a brace instead, and leaves the file in the global namespace. Several
     * braced namespaces in one file would need a per-block answer rather than
     * this per-file one; PSR-12, which this package lints itself against,
     * does not allow them.
     */
    private function inGlobalNamespace(File $phpcsFile): bool
    {
        $tokens = $phpcsFile->getTokens();
        $keywordPtr = $phpcsFile->findNext(T_NAMESPACE, 0);

        if ($keywordPtr === false) {
            return true;
        }

        $namePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($keywordPtr + 1), null, true);

        return $namePtr === false || $tokens[$namePtr]['code'] !== T_STRING;
    }

    /**
     * Whether the method body reaches the declaring class's constructor, by
     * instantiating it or by handing off to another of its static methods.
     */
    private function delegates(File $phpcsFile, int $stackPtr, ?string $className, string $method): bool
    {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$stackPtr]['scope_closer'];

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            // Inside an anonymous class, `self` and `static` name *it*, not the
            // method's own class, so nothing in its body delegates here. A
            // closure or arrow function keeps the enclosing class binding and
            // is therefore walked into.
            if ($tokens[$pointer]['code'] === T_ANON_CLASS) {
                $pointer = $tokens[$pointer]['scope_closer'] ?? $closer;

                continue;
            }

            if (
                $tokens[$pointer]['code'] === T_NEW
                && $this->instantiatesDeclaringClass($phpcsFile, $pointer, $className) === true
            ) {
                return true;
            }

            if (
                $tokens[$pointer]['code'] === T_DOUBLE_COLON
                && $this->isSiblingStaticCall($phpcsFile, $pointer, $className, $method) === true
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a `new` builds the declaring class.
     *
     * A root-qualified `new \Money()` opens on the separator rather than the
     * name, so the name is one token further on. Whether that spelling reaches
     * this class is the name check's own question, not this one's — it steps
     * onto the name and asks.
     */
    private function instantiatesDeclaringClass(File $phpcsFile, int $newPtr, ?string $className): bool
    {
        $tokens = $phpcsFile->getTokens();
        $targetPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($newPtr + 1), null, true);

        if ($targetPtr !== false && $tokens[$targetPtr]['code'] === T_NS_SEPARATOR) {
            $targetPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($targetPtr + 1), null, true);
        }

        if ($targetPtr === false) {
            return false;
        }

        return $this->namesDeclaringClass($phpcsFile, $targetPtr, $className);
    }

    /**
     * Whether a `::` call hands off to another static method of the declaring
     * class.
     *
     * Three conditions, each of which a near-miss shape fails: the left-hand
     * side names this class, the right-hand side is a method *call* rather
     * than a constant fetch such as `self::class`, and the callee is not this
     * same method — recursion with no `new` in it never reaches a constructor.
     */
    private function isSiblingStaticCall(
        File $phpcsFile,
        int $colonPtr,
        ?string $className,
        string $method
    ): bool {
        $tokens = $phpcsFile->getTokens();
        $ownerPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($colonPtr - 1), null, true);

        if ($ownerPtr === false || $this->namesDeclaringClass($phpcsFile, $ownerPtr, $className) === false) {
            return false;
        }

        $calleePtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($colonPtr + 1), null, true);

        if ($calleePtr === false || $tokens[$calleePtr]['code'] !== T_STRING) {
            return false;
        }

        if (strtolower($tokens[$calleePtr]['content']) === strtolower($method)) {
            return false;
        }

        $parenthesisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($calleePtr + 1), null, true);

        return $parenthesisPtr !== false && $tokens[$parenthesisPtr]['code'] === T_OPEN_PARENTHESIS;
    }

    /**
     * Whether a single token names the declaring class: `self`, `static`, or
     * the class's own name carrying no namespace segment.
     *
     * `parent` is not among them — it builds the superclass, whose constructor
     * is a different one. Neither is a name with a segment in it: a separator
     * with another name before it makes the match the tail of
     * `new \Other\Money()`, a class the sniff cannot resolve from one file. A
     * separator with nothing before it is the root-qualified spelling of this
     * same class — but only where the file declares no namespace, since
     * `new \Money()` under `namespace App` builds the global class instead.
     */
    private function namesDeclaringClass(File $phpcsFile, int $pointer, ?string $className): bool
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$pointer]['code'];

        if ($code === T_SELF || $code === T_STATIC) {
            return true;
        }

        if ($code !== T_STRING || $className === null) {
            return false;
        }

        if (strtolower($tokens[$pointer]['content']) !== strtolower($className)) {
            return false;
        }

        $beforePtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($pointer - 1), null, true);

        if ($beforePtr === false || $tokens[$beforePtr]['code'] !== T_NS_SEPARATOR) {
            return true;
        }

        $segmentPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($beforePtr - 1), null, true);

        if ($segmentPtr !== false && $tokens[$segmentPtr]['code'] === T_STRING) {
            return false;
        }

        return $this->inGlobalNamespace($phpcsFile);
    }
}
