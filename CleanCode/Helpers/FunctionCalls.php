<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Helpers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Tokens;

final class FunctionCalls
{
    private ?string $analysisKey = null;

    private array $analysis = ['imports' => [], 'namespaces' => []];

    private array $analysisCounts = ['builds' => 0, 'hits' => 0];

    private const NON_CALL_PRECEDERS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
        T_FUNCTION,
        T_NEW,
    ];

    public function isGlobalFunctionCall(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();

        if (
            isset($tokens[$stackPtr]) === false
            || $tokens[$stackPtr]['code'] !== T_STRING
        ) {
            return false;
        }

        // Inside an attribute, the name is the attribute's class. Attribute
        // arguments are constant expressions and so can hold no call at all,
        // which makes the whole attribute region safe to rule out at once.
        if (isset($tokens[$stackPtr]['attribute_opener']) === true) {
            return false;
        }

        $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $next === false
            || $tokens[$next]['code'] !== T_OPEN_PARENTHESIS
        ) {
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
            && $this->isReturnByReferenceMarker($phpcsFile, $prev) === true
        ) {
            return false;
        }

        if ($tokens[$prev]['code'] === T_NS_SEPARATOR) {
            return $this->isGlobalQualifier($phpcsFile, $prev, $stackPtr);
        }

        return $this->isImportedFunctionName($phpcsFile, $stackPtr) === false;
    }

    private function isReturnByReferenceMarker(File $phpcsFile, int $ampersandPtr): bool
    {
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ampersandPtr - 1), null, true);

        return $before !== false && $phpcsFile->getTokens()[$before]['code'] === T_FUNCTION;
    }

    private function isGlobalQualifier(File $phpcsFile, int $separatorPtr, int $stackPtr): bool
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
            return $this->isInsideNamedNamespace($phpcsFile, $stackPtr) === false;
        }

        return true;
    }

    private function isInsideNamedNamespace(File $phpcsFile, int $stackPtr): bool
    {
        $declarations = $this->namespaceDeclarations($phpcsFile);
        $block = $this->namespaceBlockOf($declarations, $stackPtr);

        return $block !== 0 && $this->isNamedDeclaration($phpcsFile, $block);
    }

    private function isNamedDeclaration(File $phpcsFile, int $namespacePtr): bool
    {
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($namespacePtr + 1), null, true);

        return $after !== false && $phpcsFile->getTokens()[$after]['code'] === T_STRING;
    }

    private function isImportedFunctionName(File $phpcsFile, int $stackPtr): bool
    {
        $analysis = $this->analyze($phpcsFile);

        if ($analysis['imports'] === []) {
            return false;
        }

        $block = $this->namespaceBlockOf($analysis['namespaces'], $stackPtr);
        $name = strtolower($phpcsFile->getTokens()[$stackPtr]['content']);

        return isset($analysis['imports'][$block][$name]);
    }

    // The TokenStreams instance is held, never built per call. Its identities
    // are handed out by a WeakMap and a counter it owns, so a fresh instance
    // restarts at 1 and would give two different files the same key whenever
    // their token counts match — turning this memo into wrong answers rather
    // than a slow one.
    public function __construct(
        private TokenStreams $tokenStreams = new TokenStreams()
    ) {
    }

    private function analyze(File $phpcsFile): array
    {
        $tokenStreams = $this->tokenStreams;
        $key = $tokenStreams->key($phpcsFile);

        if ($this->analysisKey === $key) {
            $this->analysisCounts['hits']++;

            return $this->analysis;
        }

        $namespaces = $this->namespaceDeclarations($phpcsFile);
        $this->analysisCounts['builds']++;
        $this->analysisKey = $key;
        $this->analysis = [
            'namespaces' => $namespaces,
            'imports' => $this->functionImports($phpcsFile, $namespaces),
        ];

        return $this->analysis;
    }

    public function analysisCounts(): array
    {
        return $this->analysisCounts;
    }

    private function namespaceDeclarations(File $phpcsFile): array
    {
        $tokens = $phpcsFile->getTokens();
        $declarations = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if ($tokens[$ptr]['code'] !== T_NAMESPACE) {
                continue;
            }

            $next = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), null, true);

            if (
                $next === false
                || $tokens[$next]['code'] === T_NS_SEPARATOR
            ) {
                continue;
            }

            $declarations[$ptr] = $tokens[$ptr]['scope_closer'] ?? null;
        }

        return $declarations;
    }

    private function namespaceBlockOf(array $declarations, int $stackPtr): int
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

    private function functionImports(File $phpcsFile, array $declarations): array
    {
        $tokens = $phpcsFile->getTokens();
        $imports = [];

        for ($ptr = 0; $ptr < $phpcsFile->numTokens; $ptr++) {
            if (
                $tokens[$ptr]['code'] !== T_USE
                || $this->isNamespaceLevel($phpcsFile, $ptr) === false
            ) {
                continue;
            }

            $end = $phpcsFile->findNext(T_SEMICOLON, ($ptr + 1));

            if ($end === false) {
                continue;
            }

            $block = $this->namespaceBlockOf($declarations, $ptr);

            foreach ($this->importedFunctionNames($phpcsFile, $ptr, $end) as $name) {
                if ($name === '') {
                    continue;
                }

                $imports[$block][$name] = true;
            }
        }

        return $imports;
    }

    private function isNamespaceLevel(File $phpcsFile, int $usePtr): bool
    {
        foreach (($phpcsFile->getTokens()[$usePtr]['conditions'] ?? []) as $condition) {
            if ($condition !== T_NAMESPACE) {
                return false;
            }
        }

        return true;
    }

    private function importedFunctionNames(File $phpcsFile, int $usePtr, int $endPtr): array
    {
        $groupOpener = $phpcsFile->findNext(T_OPEN_USE_GROUP, ($usePtr + 1), $endPtr);
        $isFunctionUse = $this->isFunctionKeyword($phpcsFile, ($usePtr + 1), $endPtr);

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
            ? $this->commaEntries($phpcsFile, ($usePtr + 1), $endPtr)
            : $this->groupEntries($phpcsFile, $groupOpener, $endPtr);

        // A group's prefix carries the namespace for every entry inside the
        // braces, so no entry of a group can source from the global namespace.
        $prefixQualified = $groupOpener !== false;
        $names = [];

        foreach ($entries as [$start, $end]) {
            if (
                $isFunctionUse === false
                && $this->isFunctionKeyword($phpcsFile, $start, $end) === false
            ) {
                continue;
            }

            if ($this->bindsGlobalFunction($phpcsFile, $start, $end, $prefixQualified) === true) {
                continue;
            }

            $names[] = $this->boundName($phpcsFile, $start, $end);
        }

        return $names;
    }

    private function bindsGlobalFunction(
        File $phpcsFile,
        int $start,
        int $end,
        // phpcs:ignore CleanCode.Functions.DisallowBooleanArgumentFlag -- one term of a disjunctive guard, not a mode
        bool $prefixQualified
    ): bool {
        if (
            $prefixQualified === true
            || $phpcsFile->findNext(T_NS_SEPARATOR, $start, $end) !== false
        ) {
            return false;
        }

        $source = $this->sourceName($phpcsFile, $start, $end);

        return $source !== '' && $source === $this->boundName($phpcsFile, $start, $end);
    }

    private function sourceName(File $phpcsFile, int $start, int $end): string
    {
        $tokens = $phpcsFile->getTokens();
        $namePtr = $phpcsFile->findNext(T_STRING, $start, $end);

        if (
            $namePtr !== false
            && strtolower($tokens[$namePtr]['content']) === 'function'
        ) {
            $namePtr = $phpcsFile->findNext(T_STRING, ($namePtr + 1), $end);
        }

        return $namePtr === false ? '' : strtolower($tokens[$namePtr]['content']);
    }

    private function isFunctionKeyword(File $phpcsFile, int $start, int $end): bool
    {
        $tokens = $phpcsFile->getTokens();
        $first = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $end, true);

        if (
            $first === false
            || strtolower($tokens[$first]['content']) !== 'function'
        ) {
            return false;
        }

        $after = $phpcsFile->findNext(Tokens::$emptyTokens, ($first + 1), $end, true);

        return $after !== false && $tokens[$after]['code'] !== T_NS_SEPARATOR;
    }

    private function boundName(File $phpcsFile, int $start, int $end): string
    {
        $namePtr = $phpcsFile->findPrevious(T_STRING, ($end - 1), $start);

        return $namePtr === false ? '' : strtolower($phpcsFile->getTokens()[$namePtr]['content']);
    }

    private function groupEntries(File $phpcsFile, int $groupOpener, int $endPtr): array
    {
        $closer = $phpcsFile->findNext(T_CLOSE_USE_GROUP, ($groupOpener + 1), $endPtr);
        $closer = $closer === false ? $endPtr : $closer;

        return $this->commaEntries($phpcsFile, ($groupOpener + 1), $closer);
    }

    private function commaEntries(File $phpcsFile, int $start, int $end): array
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
