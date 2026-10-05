<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Support\InheritedMembers;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff as SlevomatParameterTypeHint;

class ParameterTypeHintSniff extends SlevomatParameterTypeHint
{
    public function process(File $phpcsFile, int $functionPointer): void
    {
        if ((new InheritedMembers)->overridesUntypedParameter($phpcsFile, $functionPointer) === true) {
            return;
        }

        parent::process($phpcsFile, $functionPointer);
    }
}
