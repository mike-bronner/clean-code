<?php

declare(strict_types=1);

namespace App\Fixtures;

// Five classes, each one public method over the default threshold of ten, each
// reported once at its own `class` keyword. A live PHPMD 2.15.0 run over this
// file reports the same five classes with the same counts. Method bodies are
// empty and on one line for the reason given in passing.php.

// Eleven public methods, nothing else to discount.
class ElevenPublicMethods
{
    public function one(): void {}
    public function two(): void {}
    public function three(): void {}
    public function four(): void {}
    public function five(): void {}
    public function six(): void {}
    public function seven(): void {}
    public function eight(): void {}
    public function nine(): void {}
    public function ten(): void {}
    public function eleven(): void {}
}

// A constructor and ten other public methods. PHPMD counts `__construct` — its
// default ignore pattern exempts accessor prefixes only — so eleven is the
// count in both tools. Exempting constructors here would leave this class
// silent and lose a PHPMD finding.
class TenPlusConstructor
{
    public function __construct() {}
    public function one(): void {}
    public function two(): void {}
    public function three(): void {}
    public function four(): void {}
    public function five(): void {}
    public function six(): void {}
    public function seven(): void {}
    public function eight(): void {}
    public function nine(): void {}
    public function ten(): void {}
}

// Five public static methods and six public abstract ones. Neither modifier
// changes a method's visibility, and PDepend counts both, so the total is
// eleven. The six abstract declarations also prove the count does not depend on
// a method having a body to scan.
abstract class StaticAndAbstract
{
    public static function one(): void {}
    public static function two(): void {}
    public static function three(): void {}
    public static function four(): void {}
    public static function five(): void {}
    abstract public function six(): void;
    abstract public function seven(): void;
    abstract public function eight(): void;
    abstract public function nine(): void;
    abstract public function ten(): void;
    abstract public function eleven(): void;
}

// Eleven methods declared with no visibility modifier at all. PHP defaults
// those to public and PDepend reads them the same way, so treating an
// unspecified scope as anything but public would leave this class silent.
class ImplicitlyPublic
{
    function one(): void {}
    function two(): void {}
    function three(): void {}
    function four(): void {}
    function five(): void {}
    function six(): void {}
    function seven(): void {}
    function eight(): void {}
    function nine(): void {}
    function ten(): void {}
    function eleven(): void {}
}

// Sixteen public methods, five exempted by the default ignore pattern, eleven
// counted — one past the threshold. The mirror of passing.php's
// TenAfterTheIgnorePattern: the pattern is applied here too, and still leaves
// enough behind to report.
class ElevenAfterTheIgnorePattern
{
    public function one(): void {}
    public function two(): void {}
    public function three(): void {}
    public function four(): void {}
    public function five(): void {}
    public function six(): void {}
    public function seven(): void {}
    public function eight(): void {}
    public function nine(): void {}
    public function ten(): void {}
    public function eleven(): void {}
    public function setName(string $name): void {}
    public function getName(): string { return ''; }
    public function isReady(): bool { return true; }
    public function hasItems(): bool { return true; }
    public function withTimeout(int $seconds): static { return $this; }
}
