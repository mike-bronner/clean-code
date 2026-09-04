<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class DisallowConstructorInstantiationSniff implements Sniff
{
    private const CLASS_LIKE_SCOPES = [T_CLASS, T_ANON_CLASS, T_TRAIT, T_INTERFACE, T_ENUM];

    private const GROUP_CLOSERS = [
        T_CLOSE_PARENTHESIS,
        T_CLOSE_SQUARE_BRACKET,
        T_CLOSE_SHORT_ARRAY,
        T_CLOSE_CURLY_BRACKET,
    ];

    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || strtolower($name) !== '__construct'
        ) {
            return;
        }

        // A function is only a constructor when a class-like scope holds it
        // directly. A plain `function __construct()` at file scope, or nested
        // inside another function, injects into nothing.
        $enclosing = $tokens[$stackPtr]['conditions'];

        if (! in_array(end($enclosing), self::CLASS_LIKE_SCOPES, true)) {
            return;
        }

        // An abstract or interface constructor has no body to scan.
        if (! isset($tokens[$stackPtr]['scope_opener'], $tokens[$stackPtr]['scope_closer'])) {
            return;
        }

        $closer = $tokens[$stackPtr]['scope_closer'];

        for ($pointer = $tokens[$stackPtr]['scope_opener'] + 1; $pointer < $closer; $pointer++) {
            // Everything a `throw` raises is exception construction, however it
            // is spelled — jump the whole operand rather than inspecting the
            // single token that follows the keyword.
            if ($tokens[$pointer]['code'] === T_THROW) {
                $pointer = $this->endOfThrownExpression($phpcsFile, $pointer, $closer);

                continue;
            }

            if ($tokens[$pointer]['code'] === T_NEW) {
                $this->reportInstantiation($phpcsFile, $pointer);

                continue;
            }

            // Nested scopes (closures, arrow functions, anonymous classes and
            // their methods) run on their own terms — jump the whole scope.
            // Conditionals and loops carry a scope too, but their bodies are
            // constructor code, so only declarations are skipped.
            if (
                $this->isNestedDeclaration($tokens[$pointer]['code'])
                && isset($tokens[$pointer]['scope_closer'])
            ) {
                $pointer = $tokens[$pointer]['scope_closer'];
            }
        }
    }

    private function isNestedDeclaration(int|string $code): bool
    {
        return in_array($code, [T_FUNCTION, T_CLOSURE, T_FN, T_ANON_CLASS], true);
    }

    private function endOfThrownExpression(File $phpcsFile, int $throwPtr, int $closer): int
    {
        $tokens = $phpcsFile->getTokens();
        $openTernaries = 0;

        for ($pointer = $throwPtr + 1; $pointer < $closer; $pointer++) {
            $groupCloser = $this->groupCloser($tokens[$pointer], $pointer);

            if ($groupCloser !== null) {
                $pointer = $groupCloser;

                continue;
            }

            $code = $tokens[$pointer]['code'];

            if ($code === T_INLINE_THEN) {
                $openTernaries++;

                continue;
            }

            if (
                $code === T_INLINE_ELSE
                && $openTernaries > 0
            ) {
                $openTernaries--;

                continue;
            }

            if (
                $code === T_SEMICOLON
                || $code === T_COMMA
                || $code === T_INLINE_ELSE
                || in_array($code, self::GROUP_CLOSERS, true)
            ) {
                return $pointer;
            }
        }

        return $closer - 1;
    }

    private function groupCloser(array $token, int $pointer): ?int
    {
        foreach (['parenthesis_closer', 'bracket_closer', 'scope_closer'] as $key) {
            if (
                isset($token[$key])
                && $token[$key] > $pointer
            ) {
                return (int) $token[$key];
            }
        }

        return null;
    }

    private function reportInstantiation(File $phpcsFile, int $newPtr): void
    {
        $phpcsFile->addWarning(
            'Constructing a collaborator inside __construct() hard-wires it; inject it as a'
                . ' constructor parameter so it can be resolved through IoC'
                . ' (see docs/standards/dependency-injection.md)',
            $newPtr,
            'Found'
        );
    }
}
