<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

// Violation: an ordinary method with no ancestor constraining it.
class PlainClass
{
    public function untyped($value): bool
    {
        return $value !== null;
    }
}

// Silent: PHP_CodeSniffer's Sniff interface declares process()'s second
// parameter untyped, so writing the hint is a fatal narrowing error.
class InheritedFromSniffInterface implements Sniff
{
    public function register(): array
    {
        return [T_VARIABLE];
    }

    public function process(File $phpcsFile, $stackPtr): void
    {
        echo $stackPtr;
    }

    // Violation: the ancestor is loadable and does not declare this method, so
    // it constrains nothing and the hint can be written. Sharing the class with
    // process() above is the point — the skip is per declaration, not per class.
    public function helper($value): bool
    {
        return $value !== null;
    }
}

// Violation: an ancestor that cannot be resolved during a lint run answers
// nothing, so the read is reported exactly as it was before.
class InheritedFromUnresolvableParent extends \Vendor\Absent\BaseClass
{
    public function handle($value): bool
    {
        return $value !== null;
    }
}
