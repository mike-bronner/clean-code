<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Sniffs\TypeHints;

use MikeBronner\CleanCode\Support\InheritedMembers;
use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\PropertyHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use SlevomatCodingStandard\Sniffs\TypeHints\PropertyTypeHintSniff as SlevomatPropertyTypeHint;

class PropertyTypeHintSniff extends SlevomatPropertyTypeHint
{
    public function process(File $phpcsFile, int $pointer): void
    {
        if ((new InheritedMembers)->isCodeSnifferClass($phpcsFile, $pointer) === true) {
            return;
        }

        if ($this->inheritsUntypedProperty($phpcsFile, $pointer) === true) {
            return;
        }

        parent::process($phpcsFile, $pointer);
    }

    private function inheritsUntypedProperty(File $phpcsFile, int $pointer): bool
    {
        $propertyPtr = TokenHelper::findNext($phpcsFile, [T_FUNCTION, T_CONST, T_VARIABLE], $pointer + 1);

        if (
            $propertyPtr === null
            || $phpcsFile->getTokens()[$propertyPtr]['code'] !== T_VARIABLE
            || PropertyHelper::isProperty($phpcsFile, $propertyPtr) === false
        ) {
            return false;
        }

        $property = $phpcsFile->getTokens()[$propertyPtr]['content'];

        return (new InheritedMembers)->inheritsUntypedProperty($phpcsFile, $propertyPtr, $property);
    }
}
