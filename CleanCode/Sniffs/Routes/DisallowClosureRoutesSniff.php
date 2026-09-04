<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowClosureRoutesSniff implements Sniff
{
    private const ROUTE_ACTION_METHODS = [
        'any',
        'delete',
        'fallback',
        'get',
        'match',
        'options',
        'patch',
        'post',
        'put',
    ];

    private const NESTED_CLOSERS = [
        'scope_closer',
        'parenthesis_closer',
        'bracket_closer',
    ];

    public function register(): array
    {
        return [T_STRING];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $method = strtolower($tokens[$stackPtr]['content']);

        if (in_array($method, self::ROUTE_ACTION_METHODS, true) === false) {
            return;
        }

        $opener = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $opener === false
            || $tokens[$opener]['code'] !== T_OPEN_PARENTHESIS
            || isset($tokens[$opener]['parenthesis_closer']) === false
        ) {
            return;
        }

        if ($this->resolvesToRouteFacade($phpcsFile, $stackPtr) === false) {
            return;
        }

        $this->reportClosureArguments(
            $phpcsFile,
            $opener,
            $tokens[$opener]['parenthesis_closer'],
            $tokens[$stackPtr]['content']
        );
    }

    private function resolvesToRouteFacade(File $phpcsFile, int $namePtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $previous = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($namePtr - 1), null, true);

        if ($previous === false) {
            return false;
        }

        if ($tokens[$previous]['code'] === T_DOUBLE_COLON) {
            return $this->isRouteClassName($phpcsFile, $previous);
        }

        $chained = [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR];

        if (in_array($tokens[$previous]['code'], $chained, true) === false) {
            return false;
        }

        $closer = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($previous - 1), null, true);

        if (
            $closer === false
            || $tokens[$closer]['code'] !== T_CLOSE_PARENTHESIS
            || isset($tokens[$closer]['parenthesis_opener']) === false
        ) {
            return false;
        }

        $callName = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($tokens[$closer]['parenthesis_opener'] - 1),
            null,
            true
        );

        if (
            $callName === false
            || $tokens[$callName]['code'] !== T_STRING
        ) {
            return false;
        }

        return $this->resolvesToRouteFacade($phpcsFile, $callName);
    }

    private function isRouteClassName(File $phpcsFile, int $doubleColonPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $classPtr = $phpcsFile->findPrevious(
            Tokens::$emptyTokens,
            ($doubleColonPtr - 1),
            null,
            true
        );

        if (
            $classPtr === false
            || $tokens[$classPtr]['code'] !== T_STRING
        ) {
            return false;
        }

        return strtolower($tokens[$classPtr]['content']) === 'route';
    }

    private function reportClosureArguments(
        File $phpcsFile,
        int $opener,
        int $closer,
        string $method
    ): void {
        $tokens = $phpcsFile->getTokens();
        $argument = $phpcsFile->findNext(Tokens::$emptyTokens, ($opener + 1), $closer, true);

        while ($argument !== false) {
            $action = $this->unwrapArgument($phpcsFile, $argument, $closer);
            $isClosure = $action !== false
                && in_array($tokens[$action]['code'], [T_CLOSURE, T_FN], true) === true;

            if ($isClosure === true) {
                $phpcsFile->addError(
                    'Route::%s() must not take a closure action; a closure cannot be serialized by'
                        . ' route:cache — point the route at a controller instead',
                    $action,
                    'ClosureAction',
                    [$method]
                );
            }

            $comma = $this->nextArgumentSeparator($phpcsFile, $argument, $closer);

            if ($comma === false) {
                return;
            }

            $argument = $phpcsFile->findNext(Tokens::$emptyTokens, ($comma + 1), $closer, true);
        }
    }

    private function unwrapArgument(File $phpcsFile, int $argument, int $closer): int|false
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$argument]['code'] === T_PARAM_NAME) {
            $colon = $phpcsFile->findNext(Tokens::$emptyTokens, ($argument + 1), $closer, true);

            if (
                $colon === false
                || $tokens[$colon]['code'] !== T_COLON
            ) {
                return false;
            }

            $argument = $phpcsFile->findNext(Tokens::$emptyTokens, ($colon + 1), $closer, true);

            if ($argument === false) {
                return false;
            }
        }

        if ($tokens[$argument]['code'] !== T_STATIC) {
            return $argument;
        }

        return $phpcsFile->findNext(Tokens::$emptyTokens, ($argument + 1), $closer, true);
    }

    private function nextArgumentSeparator(File $phpcsFile, int $start, int $closer): int|false
    {
        $tokens = $phpcsFile->getTokens();

        for ($pointer = $start; $pointer < $closer; $pointer++) {
            if ($tokens[$pointer]['code'] === T_COMMA) {
                return $pointer;
            }

            $nestedCloser = $this->nestedCloserAt($phpcsFile, $pointer);

            if ($nestedCloser !== false) {
                $pointer = $nestedCloser;
            }
        }

        return false;
    }

    private function nestedCloserAt(File $phpcsFile, int $pointer): int|false
    {
        $token = $phpcsFile->getTokens()[$pointer];

        foreach (self::NESTED_CLOSERS as $closerKey) {
            $closer = $token[$closerKey] ?? $pointer;

            if ($closer > $pointer) {
                return $closer;
            }
        }

        return false;
    }
}
