<?php

/**
 * Compliant code for Generic.NamingConventions.ConstructorName, plus the
 * near-miss shapes the sniff must stay silent on.
 *
 * Every class here declares the constructs the sniff registers on (T_CLASS
 * bodies containing T_FUNCTION), so a silence here is an asserted silence
 * rather than the absence of anything to look at.
 *
 * The trait and interface at the bottom are the deliberate near-misses: each
 * declares a method carrying its own type's name, which is the exact shape
 * flagged inside a class. PHPMD 2.15.0 skips both explicitly
 * (ConstructorWithNameAsEnclosingClass::apply() returns early on an ASTTrait
 * and on an InterfaceNode), and the sniff registers on T_CLASS/T_ANON_CLASS
 * only — so the two tools agree, and that agreement is what these pin.
 */

declare(strict_types=1);

class ModernConstructor
{
    public function __construct(private readonly string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }
}

class NoConstructorAtAll
{
    public function describe(): string
    {
        return 'nothing to construct';
    }
}

/**
 * A method named after a *different* class in the same file. Only a match
 * against the enclosing class is a PHP4 constructor.
 */
class BorrowsAnotherName
{
    public function ModernConstructor(): string
    {
        return 'not my own name';
    }
}

class DelegatesToParent extends ModernConstructor
{
    public function __construct()
    {
        parent::__construct('delegated');
    }
}

trait TypeNamedMethodInTrait
{
    public function TypeNamedMethodInTrait(): string
    {
        return 'a trait method is never a constructor';
    }
}

interface TypeNamedMethodInInterface
{
    public function TypeNamedMethodInInterface(): string;
}
