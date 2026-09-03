<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Routes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowNonResourceRoutesSniff implements Sniff
{
    public array $routeFilePatterns = [
        '*/routes/*',
    ];

    private const ROUTE_FACADE = 'route';

    private const ROUTE_VERBS = [
        'any',
        'delete',
        'get',
        'match',
        'options',
        'patch',
        'post',
        'put',
    ];

    private const RECEIVER_TOKENS = [
        T_STRING,
        T_NAME_QUALIFIED,
        T_NAME_FULLY_QUALIFIED,
        T_NAME_RELATIVE,
    ];

    public function register(): array
    {
        return [T_DOUBLE_COLON];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        if ($this->isRouteFile($phpcsFile->getFilename()) === false) {
            return;
        }

        if ($this->isRouteFacade($phpcsFile, $stackPtr) === false) {
            return;
        }

        $tokens = $phpcsFile->getTokens();
        $verbPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($verbPtr === false) {
            return;
        }

        if (in_array(strtolower($tokens[$verbPtr]['content']), self::ROUTE_VERBS, true) === false) {
            return;
        }

        if ($this->registersRoute($phpcsFile, $verbPtr) === false) {
            return;
        }

        $phpcsFile->addWarning(
            'Route::%s() registers a route with an HTTP verb; the standard asks for resource '
                . 'routes pointing at RESTful controllers (Route::resource(), Route::apiResource()), '
                . 'with an invokable-controller special-action route as a rare exception '
                . '(see docs/standards/routes-conventions-do-do-not.md)',
            $verbPtr,
            'Found',
            [$tokens[$verbPtr]['content']]
        );
    }

    private function isRouteFile(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($this->routeFilePatterns as $pattern) {
            if (fnmatch(str_replace('\\', '/', $pattern), $normalized) === true) {
                return true;
            }
        }

        return false;
    }

    private function isRouteFacade(File $phpcsFile, int $stackPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if ($receiverPtr === false) {
            return false;
        }

        if (in_array($tokens[$receiverPtr]['code'], self::RECEIVER_TOKENS, true) === false) {
            return false;
        }

        $segments = explode('\\', $tokens[$receiverPtr]['content']);

        return strtolower((string) end($segments)) === self::ROUTE_FACADE;
    }

    private function registersRoute(File $phpcsFile, int $verbPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $openPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($verbPtr + 1), null, true);

        if (
            $openPtr === false
            || $tokens[$openPtr]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return false;
        }

        return $this->isFirstClassCallable($phpcsFile, $openPtr) === false;
    }

    private function isFirstClassCallable(File $phpcsFile, int $openerPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $ellipsisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($openerPtr + 1), null, true);

        if (
            $ellipsisPtr === false
            || $tokens[$ellipsisPtr]['code'] !== T_ELLIPSIS
        ) {
            return false;
        }

        $afterEllipsisPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ellipsisPtr + 1), null, true);

        return $afterEllipsisPtr !== false && $tokens[$afterEllipsisPtr]['code'] === T_CLOSE_PARENTHESIS;
    }
}
