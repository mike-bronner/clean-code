<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Support\InheritedMembers;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff as SlevomatPropertyTypeHint;

class PropertyTypeHintSniff extends SlevomatPropertyTypeHint
{
    public function process(File $phpcsFile, int $pointer): void
    {
        if ((new InheritedMembers())->isCodeSnifferClass($phpcsFile, $pointer) === true) {
            return;
        }

        parent::process($phpcsFile, $pointer);
    }
}
