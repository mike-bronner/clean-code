<?php

declare(strict_types=1);

use PHP_CodeSniffer\Util\Common;

// PHP_CodeSniffer\Util\Common stands in for Laravel's Facade base class: a real,
// autoloadable ancestor declaring a public static method and a public static
// property. A fixture parent would not work — a fixture class is not
// autoloadable, so class_exists() is false for it and the sniff would take its
// unresolvable-ancestor path rather than the one under test.
class InheritsStatics extends Common
{
    // Not a violation: Common declares $allowedTypes static, and PHP refuses to
    // load a child that redeclares it non-static.
    public static $allowedTypes = [];

    // Not a violation: Common declares isPharFile() static, and PHP refuses to
    // load a child that makes it non-static.
    public static function isPharFile($path)
    {
        return parent::isPharFile($path);
    }

    // Violation: declared here, inherited from nothing. Keeps the fixture from
    // passing vacuously.
    private static $ownCache = [];

    // Violation: same, for a method.
    private static function ownHelper(): string
    {
        return 'own';
    }
}
