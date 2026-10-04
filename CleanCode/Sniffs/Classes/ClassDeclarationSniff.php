<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\Classes;

use MikeBronner\CleanCode\Helpers\EmptyClassBody;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\PSR2\Sniffs\Classes\ClassDeclarationSniff as PsrDeclaration;

class ClassDeclarationSniff extends PsrDeclaration
{
    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ((new EmptyClassBody())->isInline($phpcsFile, $stackPtr) === true) {
            $this->processEmptyBody($phpcsFile, $stackPtr);
            $this->processOpen($phpcsFile, $stackPtr);
            $this->processClose($phpcsFile, $stackPtr);

            return;
        }

        parent::process($phpcsFile, $stackPtr);
    }

    private function processEmptyBody(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        $opener = $tokens[$stackPtr]['scope_opener'];
        $before = $tokens[$opener - 1];
        $spaces = $before['code'] === T_WHITESPACE ? $before['length'] : 0;

        if (
            $spaces === 1
            || $this->spaceAfterNameReports($phpcsFile, $stackPtr, $spaces) === true
        ) {
            return;
        }

        $fix = $phpcsFile->addFixableError(
                'Expected 1 space before the empty body of a %s; %s found',
                $opener,
                'SpaceBeforeEmptyBody',
                [strtolower($tokens[$stackPtr]['content']), $spaces]
            );

        if ($fix === false) {
            return;
        }

        $fixer = $phpcsFile->fixer;

        if ($spaces === 0) {
            $fixer->addContentBefore($opener, ' ');

            return;
        }

        $fixer->replaceToken($opener - 1, ' ');
    }

    private function spaceAfterNameReports(File $phpcsFile, int $stackPtr, int $spaces): bool
    {
        $opener = $phpcsFile->getTokens()[$stackPtr]['scope_opener'];
        $declarationEnd = $phpcsFile->findPrevious(T_WHITESPACE, $opener - 1, null, true);

        return $spaces > 0 && $declarationEnd === $phpcsFile->findNext(T_STRING, $stackPtr);
    }
}
