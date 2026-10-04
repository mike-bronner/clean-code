<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\WhiteSpace;

use MikeBronner\CleanCode\Helpers\EmptyClassBody;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Squiz\Sniffs\WhiteSpace\ScopeClosingBraceSniff as SquizBrace;

class ScopeClosingBraceSniff extends SquizBrace
{
    public function process(File $phpcsFile, int $stackPtr): void
    {
        if ((new EmptyClassBody())->isInline($phpcsFile, $stackPtr) === true) {
            return;
        }

        parent::process($phpcsFile, $stackPtr);
    }
}
