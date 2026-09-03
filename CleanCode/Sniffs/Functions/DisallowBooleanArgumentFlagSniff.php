<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Functions;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowBooleanArgumentFlagSniff implements Sniff
{
    public string $exceptions = '';

    public string $ignorepattern = '';

    private const CLASS_LIKE_TOKENS = [
        T_ANON_CLASS,
        T_CLASS,
        T_ENUM,
        T_INTERFACE,
        T_TRAIT,
    ];

    public function register(): array
    {
        return [T_CLOSURE, T_FN, T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $code = $tokens[$stackPtr]['code'];

        // getDeclarationName() throws on T_FN and answers null for T_CLOSURE,
        // so only a named declaration is ever asked for its name.
        $name = $code === T_FUNCTION ? $phpcsFile->getDeclarationName($stackPtr) : null;

        if ($this->isIgnoredName($name) === true) {
            return;
        }

        $className = $this->enclosingClassName($phpcsFile, $stackPtr);

        if (
            $className !== null
            && $this->isExceptedClass($className) === true
        ) {
            return;
        }

        $subject = $this->describe($code, $name, $className);

        // A declaration cut short mid-edit has no parenthesis pair, and
        // getMethodParameters() answers with an empty list rather than raising.
        foreach ($phpcsFile->getMethodParameters($stackPtr) as $parameter) {
            if ($this->isBooleanFlag($parameter) === false) {
                continue;
            }

            $phpcsFile->addError(
                'The %s has a boolean flag argument %s, which is a certain sign of a '
                    . 'Single Responsibility Principle violation; extract each branch the '
                    . 'flag selects into its own method '
                    . '(see docs/phpmd/cleancode-booleanargumentflag.md)',
                $parameter['token'],
                'Found',
                [$subject, $parameter['name']]
            );
        }
    }

    private function isIgnoredName(?string $name): bool
    {
        $pattern = trim($this->ignorepattern);

        return $name !== null
            && $pattern !== ''
            && preg_match($pattern, $name) === 1;
    }

    private function isExceptedClass(string $className): bool
    {
        $names = array_map('trim', explode(',', $this->exceptions));

        return in_array($className, $names, true);
    }

    private function enclosingClassName(File $phpcsFile, int $stackPtr): ?string
    {
        $conditions = $phpcsFile->getTokens()[$stackPtr]['conditions'] ?? [];

        foreach (array_reverse($conditions, true) as $ptr => $code) {
            if (in_array($code, self::CLASS_LIKE_TOKENS, true) === true) {
                return $phpcsFile->getDeclarationName($ptr);
            }
        }

        return null;
    }

    private function describe(int|string $code, ?string $name, ?string $className): string
    {
        return match (true) {
            $code === T_CLOSURE => 'closure',
            $code === T_FN => 'arrow function',
            $name === null => 'function',
            $className !== null => "method {$name}()",
            default => "function {$name}()",
        };
    }

    private function isBooleanFlag(array $parameter): bool
    {
        if ($parameter['variable_length'] === true) {
            return false;
        }

        return $this->isBooleanType((string) $parameter['type_hint']) === true
            || $this->isBooleanDefault($parameter['default'] ?? null) === true;
    }

    private function isBooleanType(string $typeHint): bool
    {
        // The written hint rather than '' on a failed read: '' resolves to no
        // members at all, which reads exactly like a hint that is not boolean,
        // so the failure would silently exempt the parameter from the check.
        // The written hint still resolves correctly whenever it carries no
        // internal whitespace, which is every hint PHPCS hands over from a
        // native declaration. `/\s+/` is one auto-possessified quantifier, no
        // `/u` modifier, so preg_replace() cannot fail.
        $normalized = ltrim(strtolower(preg_replace('/\s+/', '', $typeHint) ?? $typeHint), '?');
        $types = array_values(array_diff(explode('|', $normalized), ['null', '']));

        return $types === ['bool'];
    }

    private function isBooleanDefault(?string $default): bool
    {
        return in_array(strtolower(trim((string) $default)), ['true', 'false'], true);
    }
}
