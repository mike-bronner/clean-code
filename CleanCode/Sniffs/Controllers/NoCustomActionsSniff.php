<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Controllers;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

class NoCustomActionsSniff implements Sniff
{
    public array $allowedMethods = [];

    private const ALWAYS_ALLOWED = [
        '__construct',
        '__invoke',
        'create',
        'destroy',
        'edit',
        'index',
        'show',
        'store',
        'update',
    ];

    public function register(): array
    {
        return [T_CLASS];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $className = $phpcsFile->getDeclarationName($stackPtr);

        if (
            $className === null
            || $this->isController($className) === false
        ) {
            return;
        }

        foreach ($this->actionPointers($phpcsFile, $stackPtr) as $actionPtr) {
            $this->reportCustomAction($phpcsFile, $actionPtr);
        }
    }

    private function isController(string $className): bool
    {
        return str_ends_with(strtolower($className), 'controller');
    }

    private function actionPointers(File $phpcsFile, int $classPtr): array
    {
        $tokens = $phpcsFile->getTokens();
        $end = $tokens[$classPtr]['scope_closer'] ?? null;
        $pointers = [];
        $ptr = $classPtr;

        while (($ptr = $phpcsFile->findNext(T_FUNCTION, ($ptr + 1), $end)) !== false) {
            if (array_key_last($tokens[$ptr]['conditions']) !== $classPtr) {
                continue;
            }

            if ($phpcsFile->getMethodProperties($ptr)['scope'] !== 'public') {
                continue;
            }

            $pointers[] = $ptr;
        }

        return $pointers;
    }

    private function reportCustomAction(File $phpcsFile, int $actionPtr): void
    {
        $method = $phpcsFile->getDeclarationName($actionPtr);

        if (
            $method === null
            || $this->isAllowed($method) === true
        ) {
            return;
        }

        $phpcsFile->addWarning(
            'Public method %s() is not a RESTful resource action; a controller should be '
                . 'RESTful or invokable, so extract the custom action into its own controller '
                . '(see docs/standards/controllers-no-business-logic.md)',
            $actionPtr,
            'Found',
            [$method]
        );
    }

    private function isAllowed(string $method): bool
    {
        $method = strtolower($method);

        if (in_array($method, self::ALWAYS_ALLOWED, true) === true) {
            return true;
        }

        return in_array($method, array_map('strtolower', $this->allowedMethods), true);
    }
}
