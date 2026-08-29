<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

/**
 * Replicates PHPMD's Design/CouplingBetweenObjects rule (issue #114).
 *
 * A class that names many other types is hard to move, hard to test, and hard
 * to read: every dependency is another thing that has to exist before the class
 * can run. The sniff counts the *distinct* types a class couples itself to and
 * reports the class declaration once that count reaches $maximum — PHPMD's
 * `maximum` property, same default of 13.
 *
 * Counted, each type once however often it is named:
 *
 * - parameter type hints, including promoted constructor properties and the
 *   parameters of closures and arrow functions written inside the class;
 * - property type hints;
 * - return type hints;
 * - `new Type()` instantiations;
 * - static references — `Type::method()`, `Type::CONSTANT`, `Type::class`;
 * - `catch (Type $e)` and `$x instanceof Type`;
 * - `use` imports at the top of the file.
 *
 * Not counted: scalar and pseudo types (`int`, `string`, `array`, `mixed`,
 * `object`, `void`, `never`, …), `self`/`static`/`parent`, the class's own
 * name, the `extends`/`implements` clause, a `use` of a trait, an attribute,
 * and `use function`/`use const` imports.
 *
 * Names are compared fully qualified and lower-cased, so an import and the
 * short name it enables count once between them, and `\App\Foo`, `Foo`, and
 * `FOO` are all the same dependency.
 *
 * ## The threshold is inclusive
 *
 * PHPMD reports when the metric is *greater than or equal to* `maximum`:
 *
 * ```php
 * $cbo = $node->getMetric('cbo');
 * $threshold = $this->getIntProperty('maximum');
 * if ($cbo >= $threshold) {
 *     $this->addViolation($node, array($node->getName(), $cbo, $threshold));
 * }
 * ```
 *
 * So exactly 13 dependencies is already a violation, and phpmd.org's "maximum
 * number of acceptable dependencies" — echoed by #114's acceptance criteria —
 * is the advice, not the test. A live PHPMD 2.15.0 run over
 * tests/fixtures/CouplingBetweenObjectsSniff/boundaries.php agrees with the
 * code and not with the prose, and the tool is what this package replaces.
 *
 * ## Where this sniff and PHPMD 2.15.0 part company
 *
 * Every claim below was measured against a live PHPMD 2.15.0 (PDepend 2.16.2)
 * run over these fixtures, not read off phpmd.org, and each is pinned by
 * tests/fixtures/CouplingBetweenObjectsSniff/divergences.php:
 *
 * - **An unused import is counted here and not by PHPMD.** #114 makes imports
 *   a dependency source in their own right; PDepend only ever sees a type that
 *   is *used*. The gap is unobservable in code this ruleset passes, because
 *   rules.xml also wires SlevomatCodingStandard.Namespaces.UnusedUses, which
 *   makes an unused import an error of its own.
 * - **An anonymous class is its own scope.** PHPMD charges a nested anonymous
 *   class's dependencies to the class enclosing it and never reports the
 *   anonymous class itself. Here each class-like scope is counted on its own,
 *   matching the treatment CleanCode.Metrics.ExcessivePublicCount and
 *   CleanCode.Metrics.TooManyFields already give it.
 * - **`mixed` and `object` are not dependencies.** PDepend models both as
 *   class types, so PHPMD counts `function m(mixed $x)` as one dependency.
 *   Neither names a type, so neither is counted here.
 * - **Docblock-only types are not read.** PDepend takes types from `@var`,
 *   `@return`, and `@throws` annotations as well as from declarations. This
 *   sniff reads declarations only — rules.xml requires
 *   SlevomatCodingStandard.TypeHints.{Parameter,Return,Property}TypeHint, so a
 *   type this sniff cannot see is already an error under this ruleset.
 *
 * Only classes are examined, as in PHPMD, whose rule is `ClassAware`: a live
 * run reports nothing for a trait, an interface, an enum, or a plain function,
 * however many types they name.
 *
 * Detection only, matching PHPMD: cutting a class's dependencies means moving
 * behaviour to another object, which is a design decision and not a mechanical
 * rewrite.
 */
class CouplingBetweenObjectsSniff implements Sniff
{
    /**
     * Type names that never denote a dependency: PHP's scalar and pseudo
     * types, plus the three self-references. Compared lower-cased, because
     * type names in PHP are case-insensitive.
     *
     * `mixed` and `object` sit here deliberately. PDepend models them as class
     * types and PHPMD therefore counts them; neither names a type a class can
     * depend on.
     *
     * @var array<string, true>
     */
    private const NON_DEPENDENCY_TYPES = [
        'array' => true,
        'bool' => true,
        'callable' => true,
        'false' => true,
        'float' => true,
        'int' => true,
        'iterable' => true,
        'mixed' => true,
        'never' => true,
        'null' => true,
        'object' => true,
        'parent' => true,
        'self' => true,
        'static' => true,
        'string' => true,
        'true' => true,
        'void' => true,
    ];

    /**
     * The two token codes a qualified type name is built from. PHP 8 hands the
     * whole name over as one token, but PHP_CodeSniffer splits it back into
     * this pair for backwards compatibility, so a name is always a run of them.
     *
     * @var array<int|string, true>
     */
    private const NAME_TOKENS = [
        T_STRING => true,
        T_NS_SEPARATOR => true,
    ];

    /**
     * The dependency count at which a class is reported. Inclusive — a class
     * with exactly this many distinct dependencies is already a violation,
     * matching PHPMD's `maximum` property of the same name and default.
     *
     * Untyped so that a ruleset supplying it as XML (`<property name="maximum"
     * value="8"/>`, always a string) sets it without a TypeError; it is cast
     * where it is read.
     *
     * @var int|string
     */
    public $maximum = 13;

    /**
     * @return array<int|string>
     */
    public function register(): array
    {
        // Traits, interfaces, and enums are absent deliberately: PHPMD's rule
        // is declared `implements ClassAware`, and a live 2.15.0 run reports
        // none of the three however many types they name.
        return [T_CLASS, T_ANON_CLASS];
    }

    /**
     * @param int $stackPtr
     *
     * @return void
     */
    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // An unterminated declaration leaves PHPCS with no scope to walk. The
        // file is already a parse error; say nothing rather than guess.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return;
        }

        $maximum = (int) $this->maximum;
        $count = count($this->collectDependencies($phpcsFile, $stackPtr));

        if ($count < $maximum) {
            return;
        }

        $phpcsFile->addError(
            'The %s has a coupling between objects value of %s.'
                . ' Consider to reduce the number of dependencies under %s.',
            $stackPtr,
            'Found',
            [$this->describe($phpcsFile, $stackPtr), $count, $maximum]
        );
    }

    /**
     * The distinct types the class at $stackPtr couples itself to, keyed by
     * fully-qualified lower-cased name so that two spellings of one type — an
     * import and the short name it enables, say — collapse to one entry.
     *
     * @return array<string, true>
     */
    private function collectDependencies(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $namespace = $this->namespaceOf($phpcsFile, $stackPtr);
        $aliases = [];
        $dependencies = [];

        foreach ($this->imports($phpcsFile) as $alias => $fullyQualified) {
            $aliases[strtolower($alias)] = $fullyQualified;
            $dependencies[strtolower($fullyQualified)] = true;
        }

        $closer = $tokens[$stackPtr]['scope_closer'];

        for ($ptr = ($tokens[$stackPtr]['scope_opener'] + 1); $ptr < $closer; $ptr++) {
            $ptr = $this->collectAt($phpcsFile, $ptr, $stackPtr, $namespace, $aliases, $dependencies);
        }

        $own = $this->declaredType($phpcsFile, $stackPtr, $namespace);

        if ($own !== null) {
            unset($dependencies[$own]);
        }

        return $dependencies;
    }

    /**
     * Reads whatever dependency the token at $ptr carries into $dependencies,
     * and returns the pointer the walk continues from — past a nested scope, an
     * attribute, or a trait `use`, none of which is this class's own coupling.
     *
     * @param array<string, string> $aliases
     * @param array<string, true> $dependencies
     */
    private function collectAt(
        File $phpcsFile,
        int $ptr,
        int $stackPtr,
        string $namespace,
        array $aliases,
        array &$dependencies
    ): int {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$ptr]['code'];

        if ($this->isSkippableRegion($tokens, $ptr, $stackPtr) === true) {
            return $this->endOfSkippableRegion($phpcsFile, $ptr);
        }

        if ($code === T_FUNCTION || $code === T_CLOSURE || $code === T_FN) {
            foreach ($phpcsFile->getMethodParameters($ptr) as $parameter) {
                $this->addDeclaredType($dependencies, $parameter['type_hint'], $namespace, $aliases);
            }

            $return = $phpcsFile->getMethodProperties($ptr)['return_type'];
            $this->addDeclaredType($dependencies, $return, $namespace, $aliases);

            return $ptr;
        }

        if ($code === T_VARIABLE && $this->isDeclaredProperty($tokens, $ptr, $stackPtr) === true) {
            $type = $phpcsFile->getMemberProperties($ptr)['type'];
            $this->addDeclaredType($dependencies, $type, $namespace, $aliases);

            return $ptr;
        }

        if ($code === T_NEW || $code === T_INSTANCEOF) {
            return $this->addNameAfter($phpcsFile, $ptr, $namespace, $aliases, $dependencies);
        }

        if ($code === T_DOUBLE_COLON) {
            $this->addStaticReference($phpcsFile, $ptr, $namespace, $aliases, $dependencies);

            return $ptr;
        }

        if ($code === T_CATCH && isset($tokens[$ptr]['parenthesis_closer']) === true) {
            $this->addCaughtTypes($phpcsFile, $ptr, $namespace, $aliases, $dependencies);

            return $tokens[$ptr]['parenthesis_closer'];
        }

        return $ptr;
    }

    /**
     * Whether the token at $ptr opens a region that is not this class's
     * coupling: a nested class-like scope (counted separately, as its own
     * class), an attribute (PHPMD counts nothing there), or a `use` of a trait,
     * whose adaptation block can name a type this class never touches.
     *
     * A closure's `use ($x)` is not one: its innermost condition is never the
     * class, and its variables carry no type at all.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isSkippableRegion(array $tokens, int $ptr, int $stackPtr): bool
    {
        $code = $tokens[$ptr]['code'];

        if ($code === T_CLASS || $code === T_ANON_CLASS || $code === T_INTERFACE) {
            return true;
        }

        if ($code === T_TRAIT || $code === T_ENUM || $code === T_ATTRIBUTE) {
            return true;
        }

        return $code === T_USE && array_key_last($tokens[$ptr]['conditions']) === $stackPtr;
    }

    /**
     * The last pointer belonging to the region opened at $ptr. A region PHPCS
     * could not close leaves the walk at $ptr, so the caller advances by one
     * rather than looping forever on a parse error.
     */
    private function endOfSkippableRegion(File $phpcsFile, int $ptr): int
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$ptr]['code'] === T_ATTRIBUTE) {
            return $tokens[$ptr]['attribute_closer'] ?? $ptr;
        }

        if ($tokens[$ptr]['code'] !== T_USE) {
            return $tokens[$ptr]['scope_closer'] ?? $ptr;
        }

        // A trait `use` ends either at its semicolon or at the closing brace of
        // an adaptation block, whichever comes first.
        $end = $phpcsFile->findNext([T_SEMICOLON, T_OPEN_CURLY_BRACKET], ($ptr + 1));

        if ($end === false) {
            return $ptr;
        }

        return $tokens[$end]['bracket_closer'] ?? $end;
    }

    /**
     * Whether the variable at $ptr carries a property type worth reading.
     *
     * The innermost scope has to be the class, which rules out everything
     * written inside a method, a closure, or a nested anonymous class, and
     * which is also what makes getMemberProperties() safe to call: it throws
     * only when the nearest condition is not a class-like scope. A promoted
     * parameter reaches the class scope too, but through a parenthesised list,
     * and its type is read from the parameter list instead.
     *
     * Nothing further is needed to keep a property hook's body out.
     * PHP_CodeSniffer opens no scope for a hook, so a `$this` or a local
     * written inside one does arrive here looking class-scoped — but a variable
     * that declares no property has no type to read, and contributes nothing.
     * tests/fixtures/CouplingBetweenObjectsSniff/property-hooks.php pins that.
     *
     * @param array<int, array<string, mixed>> $tokens
     */
    private function isDeclaredProperty(array $tokens, int $ptr, int $stackPtr): bool
    {
        return array_key_last($tokens[$ptr]['conditions']) === $stackPtr
            && empty($tokens[$ptr]['nested_parenthesis']) === true;
    }

    /**
     * Splits a declared type into its members and records each one. PHPCS hands
     * the type over as written, so a nullable, union, intersection, or DNF type
     * arrives as one string — `?Foo`, `Foo|Bar`, `(A&B)|C` — and every type
     * named in it is a dependency of its own.
     *
     * @param array<string, true> $dependencies
     * @param array<string, string> $aliases
     */
    private function addDeclaredType(
        array &$dependencies,
        ?string $type,
        string $namespace,
        array $aliases
    ): void {
        if ($type === null || $type === '') {
            return;
        }

        $stripped = str_replace(['?', '(', ')'], '', $type);

        // A failed split is false and the foreach then throws a TypeError. The
        // unsplit type is the honest fallback: a single-member union is what a
        // type carrying no separator already reduces to, so a plain class name
        // is still counted as a dependency and only a union goes unread — an
        // undercount of coupling, never a miscount of an unrelated type.
        // `/[|&]/` is a literal character class with no quantifier and no `/u`
        // modifier, so preg_split() cannot fail; the ?: states that outright
        // rather than leaning on it, as MemberOrderingSniff does.
        foreach (preg_split('/[|&]/', $stripped) ?: [$stripped] as $member) {
            $this->addResolved($dependencies, $member, $namespace, $aliases);
        }
    }

    /**
     * Records the type named just after $operatorPtr — the operand of a `new`
     * or an `instanceof` — and returns the last pointer that name occupies. An
     * operand that is not a name (`new $class`, `new class {}`,
     * `$x instanceof $y`) names no type and leaves the walk where it was.
     *
     * @param array<string, string> $aliases
     * @param array<string, true> $dependencies
     */
    private function addNameAfter(
        File $phpcsFile,
        int $operatorPtr,
        string $namespace,
        array $aliases,
        array &$dependencies
    ): int {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if ($next === false || isset(self::NAME_TOKENS[$tokens[$next]['code']]) === false) {
            return $operatorPtr;
        }

        [$name, $end] = $this->readName($phpcsFile, $next);
        $this->addResolved($dependencies, $name, $namespace, $aliases);

        return $end;
    }

    /**
     * Records the type on the left of a `::`. `self::`, `static::`, and
     * `parent::` arrive as their own token codes rather than as a name, and
     * `$object::` as a variable, so none of them reaches the name reader.
     *
     * @param array<string, string> $aliases
     * @param array<string, true> $dependencies
     */
    private function addStaticReference(
        File $phpcsFile,
        int $ptr,
        string $namespace,
        array $aliases,
        array &$dependencies
    ): void {
        $tokens = $phpcsFile->getTokens();
        $end = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

        if ($end === false || $tokens[$end]['code'] !== T_STRING) {
            return;
        }

        $name = $this->readNameBackwards($phpcsFile, $end);
        $this->addResolved($dependencies, $name, $namespace, $aliases);
    }

    /**
     * Records every type in a `catch` list. A multi-catch names several types
     * at once — `catch (A | B $e)` — and each one is a dependency.
     *
     * @param array<string, string> $aliases
     * @param array<string, true> $dependencies
     */
    private function addCaughtTypes(
        File $phpcsFile,
        int $ptr,
        string $namespace,
        array $aliases,
        array &$dependencies
    ): void {
        $tokens = $phpcsFile->getTokens();
        $closer = $tokens[$ptr]['parenthesis_closer'];

        for ($i = ($tokens[$ptr]['parenthesis_opener'] + 1); $i < $closer; $i++) {
            if (isset(self::NAME_TOKENS[$tokens[$i]['code']]) === false) {
                continue;
            }

            [$name, $i] = $this->readName($phpcsFile, $i);
            $this->addResolved($dependencies, $name, $namespace, $aliases);
        }
    }

    /**
     * Resolves a written type name to its fully-qualified lower-cased form and
     * records it, unless it names nothing a class can depend on.
     *
     * @param array<string, true> $dependencies
     * @param array<string, string> $aliases
     */
    private function addResolved(
        array &$dependencies,
        string $name,
        string $namespace,
        array $aliases
    ): void {
        $resolved = $this->resolve($name, $namespace, $aliases);

        if ($resolved === null) {
            return;
        }

        $dependencies[$resolved] = true;
    }

    /**
     * The fully-qualified lower-cased form of a type name as written inside
     * this file, or null when the name denotes no dependency.
     *
     * Resolution follows PHP's own rules: a leading `\` means the name is
     * already qualified, a first segment matching an import is replaced by what
     * that import names, and anything else is relative to the current
     * namespace.
     *
     * @param array<string, string> $aliases
     */
    private function resolve(string $name, string $namespace, array $aliases): ?string
    {
        $name = trim($name);

        if ($name === '') {
            return null;
        }

        if (str_starts_with($name, '\\') === true) {
            return strtolower(ltrim($name, '\\'));
        }

        $segments = explode('\\', $name);
        $first = strtolower($segments[0]);

        if (count($segments) === 1 && isset(self::NON_DEPENDENCY_TYPES[$first]) === true) {
            return null;
        }

        if (isset($aliases[$first]) === true) {
            $segments[0] = $aliases[$first];

            return strtolower(ltrim(implode('\\', $segments), '\\'));
        }

        if ($first === 'namespace') {
            array_shift($segments);
        }

        return strtolower(trim($namespace . '\\' . implode('\\', $segments), '\\'));
    }

    /**
     * The namespace the class at $stackPtr sits in, or '' at global scope. The
     * nearest preceding declaration wins, so a file declaring several
     * namespaces resolves each class against its own.
     */
    private function namespaceOf(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $stackPtr;

        while (($ptr = $phpcsFile->findPrevious(T_NAMESPACE, ($ptr - 1))) !== false) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            // `namespace\Foo` is a relative name, not a declaration; the
            // declaration it is relative to is further back.
            if ($next !== false && $tokens[$next]['code'] === T_STRING) {
                return $this->readName($phpcsFile, $next)[0];
            }
        }

        return '';
    }

    /**
     * The fully-qualified lower-cased name of the class at $stackPtr, or null
     * for an anonymous class, which has no name to exclude.
     */
    private function declaredType(File $phpcsFile, int $stackPtr, string $namespace): ?string
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_ANON_CLASS) {
            return null;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if ($name === null || $name === '') {
            return null;
        }

        return strtolower(trim($namespace . '\\' . $name, '\\'));
    }

    /**
     * Every class import in the file, as alias => fully-qualified name. #114
     * makes an import a dependency source in its own right, so these seed the
     * count before the class body is walked; an import the body also uses
     * collapses onto the same key.
     *
     * Only a file-level `use` is an import: a `use` in a class body pulls in a
     * trait, and a `use` after a closure's parameter list captures variables.
     * `use function` and `use const` import no class and are skipped.
     *
     * @return array<string, string>
     */
    private function imports(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];
        $ptr = -1;

        while (($ptr = $phpcsFile->findNext(T_USE, ($ptr + 1))) !== false) {
            if ($tokens[$ptr]['conditions'] !== []) {
                continue;
            }

            $first = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            if ($first === false || isset(self::NAME_TOKENS[$tokens[$first]['code']]) === false) {
                continue;
            }

            if (in_array(strtolower($tokens[$first]['content']), ['function', 'const'], true) === true) {
                continue;
            }

            $imports += $this->readImportStatement($phpcsFile, $first);
        }

        return $imports;
    }

    /**
     * Reads one `use` statement, starting at its first name token, into
     * alias => fully-qualified pairs. Covers the plain form, the aliased form,
     * the comma-separated form, and the group form `use A\{B, C as D};`.
     *
     * @return array<string, string>
     */
    private function readImportStatement(File $phpcsFile, int $ptr): array
    {
        $tokens = $phpcsFile->getTokens();
        [$name, $end] = $this->readName($phpcsFile, $ptr);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

        if ($next !== false && $tokens[$next]['code'] === T_OPEN_USE_GROUP) {
            return $this->readImportGroup($phpcsFile, $next, $name);
        }

        $imports = [];

        // `use A\B, C\D;` — a comma continues the same statement with another
        // name, each carrying its own optional alias.
        while (true) {
            [$one, $end] = $this->readImportAlias($phpcsFile, $end, $name, '');
            $imports += $one;
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if ($next === false || $tokens[$next]['code'] !== T_COMMA) {
                return $imports;
            }

            $start = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

            if ($start === false || isset(self::NAME_TOKENS[$tokens[$start]['code']]) === false) {
                return $imports;
            }

            [$name, $end] = $this->readName($phpcsFile, $start);
        }
    }

    /**
     * Reads the members of a group import, each against the group's prefix.
     *
     * @return array<string, string>
     */
    private function readImportGroup(File $phpcsFile, int $opener, string $prefix): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true)) !== false) {
            if ($tokens[$ptr]['code'] === T_CLOSE_USE_GROUP || $tokens[$ptr]['code'] === T_SEMICOLON) {
                return $imports;
            }

            if (isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === false) {
                continue;
            }

            [$name, $end] = $this->readName($phpcsFile, $ptr);
            [$one, $ptr] = $this->readImportAlias($phpcsFile, $end, $name, $prefix);
            $imports += $one;
        }

        return $imports;
    }

    /**
     * One import — the name just read, plus the `as` alias following it when
     * there is one — and the last pointer the import occupies. Without an
     * alias, PHP binds the name's last segment.
     *
     * @return array{0: array<string, string>, 1: int}
     */
    private function readImportAlias(File $phpcsFile, int $ptr, string $name, string $prefix): array
    {
        $tokens = $phpcsFile->getTokens();
        $fullyQualified = ltrim($prefix . $name, '\\');
        $segments = explode('\\', $fullyQualified);
        $alias = end($segments);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

        if ($next !== false && $tokens[$next]['code'] === T_AS) {
            $aliasPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

            if ($aliasPtr !== false && $tokens[$aliasPtr]['code'] === T_STRING) {
                return [[$tokens[$aliasPtr]['content'] => $fullyQualified], $aliasPtr];
            }
        }

        return [($alias === '' ? [] : [$alias => $fullyQualified]), $ptr];
    }

    /**
     * The whole qualified name starting at $ptr, and the last pointer it
     * occupies. PHP 8 forbids whitespace inside a qualified name, but PHPCS
     * still tokenises files written for older versions, so the run is walked
     * across empty tokens rather than by raw adjacency.
     *
     * @return array{0: string, 1: int}
     */
    private function readName(File $phpcsFile, int $ptr): array
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';
        $end = $ptr;

        while ($ptr !== false && isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === true) {
            $name .= $tokens[$ptr]['content'];
            $end = $ptr;
            $ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);
        }

        return [$name, $end];
    }

    /**
     * The whole qualified name *ending* at $ptr — the shape a static reference
     * takes, where the `::` is met before the name preceding it.
     */
    private function readNameBackwards(File $phpcsFile, int $ptr): string
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        while ($ptr !== false && isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === true) {
            $name = $tokens[$ptr]['content'] . $name;
            $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);
        }

        return $name;
    }

    /**
     * How the violation message names the class — `class Foo`, matching the
     * "The class {0}" of PHPMD's own message, or `anonymous class`, which has
     * no name to report.
     */
    private function describe(File $phpcsFile, int $stackPtr): string
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_ANON_CLASS) {
            return 'anonymous class';
        }

        return 'class ' . $phpcsFile->getDeclarationName($stackPtr);
    }
}
