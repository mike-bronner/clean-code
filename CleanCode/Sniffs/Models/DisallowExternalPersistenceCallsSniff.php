<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Models;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

class DisallowExternalPersistenceCallsSniff implements Sniff
{
    public array $persistenceMethods = [
        'create',
        'delete',
        'save',
        'update',
    ];

    public function register(): array
    {
        return [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
        ];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();

        $methodPtr = $phpcsFile->findNext(Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if (
            $methodPtr === false
            || $tokens[$methodPtr]['code'] !== T_STRING
        ) {
            return;
        }

        $method = strtolower($tokens[$methodPtr]['content']);

        if (in_array($method, array_map('strtolower', $this->persistenceMethods), true) === false) {
            return;
        }

        $afterMethod = $phpcsFile->findNext(Tokens::$emptyTokens, ($methodPtr + 1), null, true);

        if (
            $afterMethod === false
            || $tokens[$afterMethod]['code'] !== T_OPEN_PARENTHESIS
        ) {
            return;
        }

        $receiverPtr = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($stackPtr - 1), null, true);

        if (
            $receiverPtr !== false
            && $tokens[$receiverPtr]['code'] === T_VARIABLE
            && $tokens[$receiverPtr]['content'] === '$this'
        ) {
            return;
        }

        $phpcsFile->addWarning(
            'Generic Eloquent CRUD method %s() called on a receiver other than $this; '
                . 'persistence belongs inside the model behind a descriptive method '
                . '(see docs/standards/models-persistence-methods-repository-pattern.md)',
            $methodPtr,
            'Found',
            [$tokens[$methodPtr]['content']]
        );
    }
}
