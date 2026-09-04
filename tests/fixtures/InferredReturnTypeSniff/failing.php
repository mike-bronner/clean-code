<?php

declare(strict_types=1);

use PHP_CodeSniffer\Files\File;
use SlevomatCodingStandard\Helpers\ClassHelper;

// Every declaration below is missing a return type. This sniff writes one only
// where it is provable from the source, and stays silent everywhere else. The
// Slevomat sniff runs alongside it and reports them all; it also owns the
// annotation and returns-nothing cases outright, which this one never claims.
class ProvableShapes
{
    // NOT reported by the subclass at all: PHP refuses to load a class whose
    // constructor declares a return type, so the `void` this body implies is a
    // fatal rather than a fix. The parent skips constructors too, so nothing is
    // reported on this line.
    public function __construct(private int $flag = 0)
    {
    }

    // Not this sniff: a body that returns nothing is the Slevomat sniff's own
    // case, which it already infers and fixes under a more specific code.
    public function noReturnAtAll()
    {
        $unused = 1;
    }

    // Not this sniff either, for the same reason.
    public function bareReturnOnly(int $flag)
    {
        if ($flag > 0) {
            return;
        }

        return;
    }

    // Violation, fixable: bool — true and false collapse rather than union.
    public function bothBooleans(int $flag)
    {
        if ($flag > 0) {
            return true;
        }

        return false;
    }

    // Violation, fixable: ?string — a lone null pairs into the shorthand.
    public function stringOrNull(int $flag)
    {
        if ($flag > 0) {
            return 'name';
        }

        return null;
    }

    // Violation, fixable: static.
    public function returnsThis()
    {
        return $this;
    }

    // Violation, fixable: File — the parameter carries the declaration.
    public function typedParameter(File $phpcsFile)
    {
        return $phpcsFile;
    }

    // Violation, fixable: ?int — a bare return contributes null to the union.
    public function valueOrBareReturn(int $flag)
    {
        if ($flag > 0) {
            return 5;
        }

        return;
    }

    // Violation, NOT fixable: the value comes from a call this cannot resolve.
    public function unprovableCall(File $phpcsFile, int $ptr)
    {
        return $phpcsFile->findNext([], $ptr);
    }

    // Violation, NOT fixable: an operation, not a single literal token.
    public function unprovableExpression(int $flag)
    {
        return $flag + 1;
    }

    // Violation, fixable: true — the closure's own return belongs to the
    // closure, not to this method, so it does not pollute the union.
    public function closureReturnIsNotMine()
    {
        $inner = static function () {
            return 5;
        };

        return true;
    }
}

// Reflection: ClassHelper::getName() is declared `: string` in the vendor, so
// the override's type is copied down rather than inferred.
class InheritsTypedAncestor extends ClassHelper
{
    // Violation, fixable: string.
    public static function getName($phpcsFile, $classPointer)
    {
        return parent::getName($phpcsFile, $classPointer);
    }
}
