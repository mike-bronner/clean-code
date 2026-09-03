<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Support\InheritedMembers;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Sniffs\TypeHints\ParameterTypeHintSniff as SlevomatParameterTypeHint;

class ParameterTypeHintSniff extends SlevomatParameterTypeHint
{
    // The Slevomat sniff asks for a native hint on every parameter, including
    // ones an ancestor declares untyped. Writing that hint is a fatal error,
    // not a style change: PHP reads it as narrowing an inherited parameter and
    // refuses to load the class. The whole declaration is skipped rather than
    // the one parameter, because the ancestor constrains the signature it
    // declares.
    //
    // $functionPointer stays untyped for that same reason — the parent
    // declares it so.
    public function process(File $phpcsFile, $functionPointer): void
    {
        if (InheritedMembers::overridesUntypedParameter($phpcsFile, $functionPointer) === true) {
            return;
        }

        parent::process($phpcsFile, $functionPointer);
    }
}
