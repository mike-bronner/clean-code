<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

// Violation: an ordinary class, so its property can carry a native type.
class PlainHolder
{
    public $minimum = 3;
}

// Silent: PHPCS assigns a sniff's properties from ruleset XML as strings, so a
// native type throws TypeError in the consumer's run.
class ConfigurableSniff implements Sniff
{
    public $minimum = 3;

    public $exceptions = '';

    public function register(): array
    {
        return [T_VARIABLE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        echo $stackPtr;
    }
}
