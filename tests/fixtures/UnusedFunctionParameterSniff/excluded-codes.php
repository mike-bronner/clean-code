<?php

/**
 * The six sniff codes rules.xml excludes, each raised at least once.
 *
 * Generic.CodeAnalysis.UnusedFunctionParameter swaps its error code — never
 * its verdict — when the enclosing class extends a class or implements an
 * interface. rules.xml excludes all six of those codes, because PHPMD exempts
 * a parameter that only exists to satisfy an inherited signature. The three
 * plain Found* codes stay, and are covered by failing.php.
 *
 * Every method below starts with a statement other than `return`: the sniff
 * treats a leading `return;` or `return <token>;` in an implementing class as
 * an interface stub and bails out before reporting anything.
 */

declare(strict_types=1);

interface Auditor
{
    public function audit(string $subject): void;
}

class Base
{
    public function inspect(string $subject): void
    {
        echo $subject;
    }
}

class ExtendingSubject extends Base
{
    // FoundInExtendedClass — the only parameter, and it is unused.
    public function inspect(string $subject): void
    {
        echo 'inspected';
    }

    // FoundInExtendedClassBeforeLastUsed — $unusedFirst precedes the last
    // parameter the body actually reads.
    public function compare(string $unusedFirst, string $used): void
    {
        echo $used;
    }

    // FoundInExtendedClassAfterLastUsed — $unusedSecond follows it.
    public function merge(string $used, string $unusedSecond): void
    {
        echo $used;
    }
}

class ImplementingSubject implements Auditor
{
    // FoundInImplementedInterface — the only parameter, and it is unused.
    public function audit(string $subject): void
    {
        echo 'audited';
    }

    // FoundInImplementedInterfaceBeforeLastUsed.
    public function reconcile(string $unusedFirst, string $used): void
    {
        echo $used;
    }

    // FoundInImplementedInterfaceAfterLastUsed.
    public function summarize(string $used, string $unusedSecond): void
    {
        echo $used;
    }
}
