<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class RequireLazyLoadingPreventionSniff implements Sniff
{
    public array $serviceProviderClasses = [
        'AppServiceProvider',
    ];

    private const PREVENTION_METHODS = [
        'preventlazyloading',
        'shouldbestrict',
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $name = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $name === null
            || $this->isWatchedProvider($name) === false
        ) {
            return;
        }

        if ($this->hasPreventionCall($phpcsFile, $stackPtr) === true) {
            return;
        }

        $phpcsFile->addWarning(
            '%s does not enable Laravel\'s lazy-loading safety check; call '
                . 'Model::preventLazyLoading() (or Model::shouldBeStrict()) so a missing '
                . 'eager load fails loudly instead of running silent N+1 queries '
                . '(see docs/standards/models-eager-loading.md)',
            $stackPtr,
            'Missing',
            [$name]
        );
    }

    private function isWatchedProvider(string $name): bool
    {
        return in_array(
            strtolower($name),
            array_map('strtolower', $this->serviceProviderClasses),
            true
        );
    }

    private function hasPreventionCall(File $phpcsFile, int $classPtr): bool
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$classPtr]['scope_closer'] ?? null;
        $ptr = $classPtr;

        while (($ptr = $phpcsFile->findNext(T_STRING, ($ptr + 1), $end)) !== false) {
            $method = strtolower($tokens[$ptr]['content']);

            if (in_array($method, self::PREVENTION_METHODS, true) === false) {
                continue;
            }

            // A T_STRING inside a class body always has a preceding non-empty
            // token — the class keyword at the very least — so findPrevious()
            // cannot fail here and needs no guard.
            $operatorPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($ptr - 1), null, true);

            if ($tokens[$operatorPtr]['code'] !== T_DOUBLE_COLON) {
                continue;
            }

            $afterPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($ptr + 1), $end, true);

            if (
                $afterPtr !== false
                && $tokens[$afterPtr]['code'] === T_OPEN_PARENTHESIS
            ) {
                return true;
            }
        }

        return false;
    }
}
