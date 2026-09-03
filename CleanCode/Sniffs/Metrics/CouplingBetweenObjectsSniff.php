<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Metrics;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class CouplingBetweenObjectsSniff implements Sniff
{
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

    private const NAME_TOKENS = [
        T_STRING => true,
        T_NS_SEPARATOR => true,
    ];

    public $maximum = 13;

    public function register(): array
    {
        // Traits, interfaces, and enums are absent deliberately: PHPMD's rule
        // is declared `implements ClassAware`, and a live 2.15.0 run reports
        // none of the three however many types they name.
        return [T_CLASS, T_ANON_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
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

        if (
            $code === T_FUNCTION
            || $code === T_CLOSURE
            || $code === T_FN
        ) {
            foreach ($phpcsFile->getMethodParameters($ptr) as $parameter) {
                $this->addDeclaredType($dependencies, $parameter['type_hint'], $namespace, $aliases);
            }

            $return = $phpcsFile->getMethodProperties($ptr)['return_type'];
            $this->addDeclaredType($dependencies, $return, $namespace, $aliases);

            return $ptr;
        }

        if (
            $code === T_VARIABLE
            && $this->isDeclaredProperty($tokens, $ptr, $stackPtr) === true
        ) {
            $type = $phpcsFile->getMemberProperties($ptr)['type'];
            $this->addDeclaredType($dependencies, $type, $namespace, $aliases);

            return $ptr;
        }

        if (
            $code === T_NEW
            || $code === T_INSTANCEOF
        ) {
            return $this->addNameAfter($phpcsFile, $ptr, $namespace, $aliases, $dependencies);
        }

        if ($code === T_DOUBLE_COLON) {
            $this->addStaticReference($phpcsFile, $ptr, $namespace, $aliases, $dependencies);

            return $ptr;
        }

        if (
            $code === T_CATCH
            && isset($tokens[$ptr]['parenthesis_closer']) === true
        ) {
            $this->addCaughtTypes($phpcsFile, $ptr, $namespace, $aliases, $dependencies);

            return $tokens[$ptr]['parenthesis_closer'];
        }

        return $ptr;
    }

    private function isSkippableRegion(array $tokens, int $ptr, int $stackPtr): bool
    {
        $code = $tokens[$ptr]['code'];

        if (
            $code === T_CLASS
            || $code === T_ANON_CLASS
            || $code === T_INTERFACE
        ) {
            return true;
        }

        if (
            $code === T_TRAIT
            || $code === T_ENUM
            || $code === T_ATTRIBUTE
        ) {
            return true;
        }

        return $code === T_USE && array_key_last($tokens[$ptr]['conditions']) === $stackPtr;
    }

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

    private function isDeclaredProperty(array $tokens, int $ptr, int $stackPtr): bool
    {
        return array_key_last($tokens[$ptr]['conditions']) === $stackPtr
            && empty($tokens[$ptr]['nested_parenthesis']) === true;
    }

    private function addDeclaredType(
        array &$dependencies,
        ?string $type,
        string $namespace,
        array $aliases
    ): void {
        if (
            $type === null
            || $type === ''
        ) {
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

    private function addNameAfter(
        File $phpcsFile,
        int $operatorPtr,
        string $namespace,
        array $aliases,
        array &$dependencies
    ): int {
        $tokens = $phpcsFile->getTokens();
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($operatorPtr + 1), null, true);

        if (
            $next === false
            || isset(self::NAME_TOKENS[$tokens[$next]['code']]) === false
        ) {
            return $operatorPtr;
        }

        [$name, $end] = $this->readName($phpcsFile, $next);
        $this->addResolved($dependencies, $name, $namespace, $aliases);

        return $end;
    }

    private function addStaticReference(
        File $phpcsFile,
        int $ptr,
        string $namespace,
        array $aliases,
        array &$dependencies
    ): void {
        $tokens = $phpcsFile->getTokens();
        $end = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

        if (
            $end === false
            || $tokens[$end]['code'] !== T_STRING
        ) {
            return;
        }

        $name = $this->readNameBackwards($phpcsFile, $end);
        $this->addResolved($dependencies, $name, $namespace, $aliases);
    }

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

        if (
            count($segments) === 1
            && isset(self::NON_DEPENDENCY_TYPES[$first]) === true
        ) {
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

    private function namespaceOf(File $phpcsFile, int $stackPtr): string
    {
        $tokens = $phpcsFile->getTokens();
        $ptr = $stackPtr;

        while (($ptr = $phpcsFile->findPrevious(T_NAMESPACE, ($ptr - 1))) !== false) {
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            // `namespace\Foo` is a relative name, not a declaration; the
            // declaration it is relative to is further back.
            if (
                $next !== false
                && $tokens[$next]['code'] === T_STRING
            ) {
                return $this->readName($phpcsFile, $next)[0];
            }
        }

        return '';
    }

    private function declaredType(File $phpcsFile, int $stackPtr, string $namespace): ?string
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_ANON_CLASS) {
            return null;
        }

        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || $name === ''
        ) {
            return null;
        }

        return strtolower(trim($namespace . '\\' . $name, '\\'));
    }

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

            if (
                $first === false
                || isset(self::NAME_TOKENS[$tokens[$first]['code']]) === false
            ) {
                continue;
            }

            if (in_array(strtolower($tokens[$first]['content']), ['function', 'const'], true) === true) {
                continue;
            }

            $imports += $this->readImportStatement($phpcsFile, $first);
        }

        return $imports;
    }

    private function readImportStatement(File $phpcsFile, int $ptr): array
    {
        $tokens = $phpcsFile->getTokens();
        [$name, $end] = $this->readName($phpcsFile, $ptr);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

        if (
            $next !== false
            && $tokens[$next]['code'] === T_OPEN_USE_GROUP
        ) {
            return $this->readImportGroup($phpcsFile, $next, $name);
        }

        $imports = [];

        // `use A\B, C\D;` — a comma continues the same statement with another
        // name, each carrying its own optional alias.
        while (true) {
            [$one, $end] = $this->readImportAlias($phpcsFile, $end, $name, '');
            $imports += $one;
            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($end + 1), null, true);

            if (
                $next === false
                || $tokens[$next]['code'] !== T_COMMA
            ) {
                return $imports;
            }

            $start = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

            if (
                $start === false
                || isset(self::NAME_TOKENS[$tokens[$start]['code']]) === false
            ) {
                return $imports;
            }

            [$name, $end] = $this->readName($phpcsFile, $start);
        }
    }

    private function readImportGroup(File $phpcsFile, int $opener, string $prefix): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];
        $ptr = $opener;

        while (($ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true)) !== false) {
            if (
                $tokens[$ptr]['code'] === T_CLOSE_USE_GROUP
                || $tokens[$ptr]['code'] === T_SEMICOLON
            ) {
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

    private function readImportAlias(File $phpcsFile, int $ptr, string $name, string $prefix): array
    {
        $tokens = $phpcsFile->getTokens();
        $fullyQualified = ltrim($prefix . $name, '\\');
        $segments = explode('\\', $fullyQualified);
        $alias = end($segments);
        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

        if (
            $next !== false
            && $tokens[$next]['code'] === T_AS
        ) {
            $aliasPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($next + 1), null, true);

            if (
                $aliasPtr !== false
                && $tokens[$aliasPtr]['code'] === T_STRING
            ) {
                return [[$tokens[$aliasPtr]['content'] => $fullyQualified], $aliasPtr];
            }
        }

        return [($alias === '' ? [] : [$alias => $fullyQualified]), $ptr];
    }

    private function readName(File $phpcsFile, int $ptr): array
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';
        $end = $ptr;

        while (
            $ptr !== false
            && isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === true
        ) {
            $name .= $tokens[$ptr]['content'];
            $end = $ptr;
            $ptr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);
        }

        return [$name, $end];
    }

    private function readNameBackwards(File $phpcsFile, int $ptr): string
    {
        $tokens = $phpcsFile->getTokens();
        $name = '';

        while (
            $ptr !== false
            && isset(self::NAME_TOKENS[$tokens[$ptr]['code']]) === true
        ) {
            $name = $tokens[$ptr]['content'] . $name;
            $ptr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);
        }

        return $name;
    }

    private function describe(File $phpcsFile, int $stackPtr): string
    {
        if ($phpcsFile->getTokens()[$stackPtr]['code'] === T_ANON_CLASS) {
            return 'anonymous class';
        }

        return 'class ' . $phpcsFile->getDeclarationName($stackPtr);
    }
}
