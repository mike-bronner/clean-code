<?php

declare(strict_types=1);

namespace App\Fixtures;

// Method bodies are empty and written on one line throughout this fixture. The
// rule counts *declarations*, so PSR-12 bodies would bury the only thing under
// test — the count — in three times as much whitespace;
// tests/fixtures/_rulesets/CasingConventions/failing.php uses the same
// compressed form for the same reason, and fixtures are excluded from
// `composer lint`.
//
// Every class-like below is a near miss the sniff must stay silent on, and a
// live PHPMD 2.15.0 run over this file reports nothing either.

// Exactly ten public methods: the threshold itself. PHPMD reports only when the
// count is *strictly* greater than maxmethods, so ten is silent and eleven is
// not. Relaxing the sniff's comparison from `<=` to `<` reports this class.
class TenPublicMethods
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
}

// Ten public methods among twenty declarations. Counting protected or private
// methods as well would take the total to twenty and report the class.
class TenPublicAmongTwenty
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
    protected function eleven(): void {}
    protected function twelve(): void {}
    protected function thirteen(): void {}
    protected function fourteen(): void {}
    protected function fifteen(): void {}
    private function sixteen(): void {}
    private function seventeen(): void {}
    private function eighteen(): void {}
    private function nineteen(): void {}
    private function twenty(): void {}
}

// Fifteen public methods, five of them exempted by the default ignore pattern —
// one per alternative it carries (set, get, is, has, with) — leaving exactly
// ten counted. Dropping any one alternative from the default takes the count to
// eleven and reports the class, so this pins the whole pattern rather than the
// get/set half of it.
class TenAfterTheIgnorePattern
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
    public function setName(string $name): void {}
    public function getName(): string { return ''; }
    public function isReady(): bool { return true; }
    public function hasItems(): bool { return true; }
    public function withTimeout(int $seconds): static { return $this; }
}

// PHPMD's rule implements ClassAware and nothing else, so PDepend never hands
// it an interface, a trait, an enum, or an anonymous class. Each of the four
// below declares fifteen public methods; both tools stay silent on all four.
// Adding T_INTERFACE, T_TRAIT, T_ENUM, or T_ANON_CLASS to the sniff's
// register() reports whichever one was added.
interface FifteenMethodContract
{
    public function one(): void;
    public function two(): void;
    public function three(): void;
    public function four(): void;
    public function five(): void;
    public function six(): void;
    public function seven(): void;
    public function eight(): void;
    public function nine(): void;
    public function ten(): void;
    public function eleven(): void;
    public function twelve(): void;
    public function thirteen(): void;
    public function fourteen(): void;
    public function fifteen(): void;
}

trait FifteenMethodTrait
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
    public function twelve(): void {}
    public function thirteen(): void {}
    public function fourteen(): void {}
    public function fifteen(): void {}
}

enum FifteenMethodEnum
{
    case Draft;

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
    public function twelve(): void {}
    public function thirteen(): void {}
    public function fourteen(): void {}
    public function fifteen(): void {}
}

$anonymous = new class {
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
    public function twelve(): void {}
    public function thirteen(): void {}
    public function fourteen(): void {}
    public function fifteen(): void {}
};

// Ten public methods of its own. The named function declared inside `build()`
// and the twelve methods of the anonymous class it returns belong to those
// inner scopes, not to this class — PDepend files them the same way. Searching
// for *any* enclosing class instead of the innermost enclosing scope pulls all
// thirteen in and reports this class.
class TenWithNestedDeclarations
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

    public function build(): object
    {
        function nestedHelper(): void
        {
        }

        return new class {
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
            public function twelve(): void {}
        };
    }
}

class EightPublicParent
{
    public function inheritedOne(): void {}
    public function inheritedTwo(): void {}
    public function inheritedThree(): void {}
    public function inheritedFour(): void {}
    public function inheritedFive(): void {}
    public function inheritedSix(): void {}
    public function inheritedSeven(): void {}
    public function inheritedEight(): void {}
}

trait EightPublicTrait
{
    public function importedOne(): void {}
    public function importedTwo(): void {}
    public function importedThree(): void {}
    public function importedFour(): void {}
    public function importedFive(): void {}
    public function importedSix(): void {}
    public function importedSeven(): void {}
    public function importedEight(): void {}
}

// Eight public methods of its own, twenty-four reachable on an instance. The
// eight it inherits and the eight it imports from the trait are both invisible
// to PDepend's ASTClass::getMethods(), which is what keeps this rule a
// single-file check in either tool. Resolving either set would report this
// class.
class EightDeclaredOfTwentyFour extends EightPublicParent
{
    use EightPublicTrait;

    public function ownOne(): void {}
    public function ownTwo(): void {}
    public function ownThree(): void {}
    public function ownFour(): void {}
    public function ownFive(): void {}
    public function ownSix(): void {}
    public function ownSeven(): void {}
    public function ownEight(): void {}
}
