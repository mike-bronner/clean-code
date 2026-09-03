<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Support\InheritedMembers;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff as SlevomatPropertyTypeHint;

class PropertyTypeHintSniff extends SlevomatPropertyTypeHint
{
    // A PHP_CodeSniffer sniff's properties cannot carry native types. PHPCS
    // assigns them from ruleset XML as strings, so `<property name="minimum"
    // value="3"/>` puts "3" into the property and a native int throws
    // TypeError in the consumer's run rather than in this repository's.
    public function process(File $phpcsFile, $pointer): void
    {
        if (InheritedMembers::isCodeSnifferClass($phpcsFile, $pointer) === true) {
            return;
        }

        parent::process($phpcsFile, $pointer);
    }
}
