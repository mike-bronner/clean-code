<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Naming;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class LongVariableSniff implements Sniff
{
    public int $maximum = 20;

    public string $subtractPrefixes = '';

    public string $subtractSuffixes = '';

    private const MEMBER_ACCESS_OPERATORS = [
        T_OBJECT_OPERATOR,
        T_NULLSAFE_OBJECT_OPERATOR,
        T_DOUBLE_COLON,
    ];

    private const PROPERTY_MODIFIERS = [
        T_VAR,
        T_STATIC,
        T_READONLY,
        T_FINAL,
    ];

    private const ARTIFACT_TOKENS = [
        T_FUNCTION,
        T_CLASS,
        T_TRAIT,
        T_INTERFACE,
        T_ENUM,
    ];

    public function register(): array
    {
        return [T_CLASS, T_TRAIT, T_INTERFACE, T_ENUM, T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isReachableArtifact($phpcsFile, $stackPtr) === false) {
            return;
        }

        $variables = $phpcsFile->getTokens()[$stackPtr]['code'] === T_FUNCTION
            ? $this->functionVariables($phpcsFile, $stackPtr)
            : $this->fieldVariables($phpcsFile, $stackPtr);

        $this->report($phpcsFile, $variables);
    }

    private function isReachableArtifact(File $phpcsFile, int $stackPtr): bool
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        return end($conditions) !== T_ANON_CLASS;
    }

    private function fieldVariables(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        // An interface or a forward declaration with no body has no field to
        // measure. `class Foo;` is not valid PHP, but a truncated file is what
        // PHPCS hands a sniff while an editor is mid-keystroke.
        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return [];
        }

        $candidates = $this->collect(
            $phpcsFile,
            $tokens[$stackPtr]['scope_opener'] + 1,
            $tokens[$stackPtr]['scope_closer']
        );

        return array_values(array_filter(
            $candidates,
            fn (int $variablePtr): bool => $this->isPropertyDeclaration($phpcsFile, $variablePtr)
        ));
    }

    private function isPropertyDeclaration(File $phpcsFile, int $variablePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $boundary = $phpcsFile->findPrevious(
            [T_SEMICOLON, T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET],
            $variablePtr - 1
        );
        $start = $boundary + 1;

        while (
            ($start = $phpcsFile->findNext(Tokens::$emptyTokens, $start, $variablePtr, true)) !== false
            && $tokens[$start]['code'] === T_ATTRIBUTE
        ) {
            $start = $tokens[$start]['attribute_closer'] + 1;
        }

        if ($start === false) {
            return false;
        }

        return in_array($tokens[$start]['code'], Tokens::$scopeModifiers, true)
            || in_array($tokens[$start]['code'], self::PROPERTY_MODIFIERS, true);
    }

    private function functionVariables(File $phpcsFile, int $stackPtr): array
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['parenthesis_opener'], $tokens[$stackPtr]['parenthesis_closer']) === false) {
            return [];
        }

        $parameters = $this->collect(
            $phpcsFile,
            $tokens[$stackPtr]['parenthesis_opener'] + 1,
            $tokens[$stackPtr]['parenthesis_closer']
        );

        if (isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer']) === false) {
            return $parameters;
        }

        $body = $this->collect(
            $phpcsFile,
            $tokens[$stackPtr]['scope_opener'] + 1,
            $tokens[$stackPtr]['scope_closer']
        );

        $declarations = [];
        $plain = [];

        foreach ($body as $variablePtr) {
            if ($this->isPropertyDeclaration($phpcsFile, $variablePtr)) {
                $declarations[] = $variablePtr;

                continue;
            }

            $plain[] = $variablePtr;
        }

        return array_merge($parameters, $declarations, $plain);
    }

    private function collect(File $phpcsFile, int $start, int $end): array
    {
        $tokens = $phpcsFile->getTokens();
        $variables = [];
        $current = $start;
        $wanted = array_merge([T_VARIABLE], self::ARTIFACT_TOKENS);

        while ($current < $end) {
            $next = $phpcsFile->findNext($wanted, $current, $end);

            if ($next === false) {
                break;
            }

            if ($tokens[$next]['code'] === T_VARIABLE) {
                $variables[] = $next;
                $current = $next + 1;

                continue;
            }

            if ($this->isReachableArtifact($phpcsFile, $next) === false) {
                $current = $next + 1;

                continue;
            }

            $current = $this->endOfArtifact($tokens, $next) + 1;
        }

        return $variables;
    }

    private function endOfArtifact(array $tokens, int $stackPtr): int
    {
        return $tokens[$stackPtr]['scope_closer']
            ?? $tokens[$stackPtr]['parenthesis_closer']
            ?? $stackPtr;
    }

    private function report(File $phpcsFile, array $variables): void
    {
        $tokens = $phpcsFile->getTokens();
        $seen = [];

        foreach ($variables as $stackPtr) {
            $name = $tokens[$stackPtr]['content'];

            if (isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;

            if ($this->isMemberAccess($phpcsFile, $stackPtr)) {
                continue;
            }

            $length = $this->lengthWithoutPrefixesAndSuffixes(ltrim($name, '$'));

            if ($length <= $this->maximum) {
                continue;
            }

            $phpcsFile->addError(
                'Name %s is %s characters long; keep it to %s or fewer',
                $stackPtr,
                'TooLong',
                [$name, $length, $this->maximum]
            );
        }
    }

    private function isMemberAccess(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $before = $phpcsFile->findPrevious(Tokens::$emptyTokens, $stackPtr - 1, null, true);
        $after = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        foreach ([$before, $after] as $neighbour) {
            if (
                $neighbour !== false
                && in_array($tokens[$neighbour]['code'], self::MEMBER_ACCESS_OPERATORS, true)
            ) {
                return true;
            }
        }

        return false;
    }

    private function lengthWithoutPrefixesAndSuffixes(string $name): int
    {
        $length = strlen($name);

        foreach ($this->splitToList($this->subtractSuffixes) as $suffix) {
            if (substr($name, -strlen($suffix)) === $suffix) {
                $length -= strlen($suffix);

                break;
            }
        }

        foreach ($this->splitToList($this->subtractPrefixes) as $prefix) {
            if (strncmp($name, $prefix, strlen($prefix)) === 0) {
                $length -= strlen($prefix);

                break;
            }
        }

        return $length;
    }

    private function splitToList(string $value): array
    {
        return array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $entry): bool => $entry !== ''
        );
    }
}
