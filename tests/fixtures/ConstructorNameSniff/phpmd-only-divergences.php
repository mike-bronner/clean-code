<?php

/**
 * The divergences where PHPMD 2.15.0 IS STRICTER than this ruleset: two shapes
 * PHPMD reports and Generic.NamingConventions.ConstructorName stays silent on.
 *
 * Deliberately left in the global namespace — PHPMD's rule skips every
 * namespaced class outright, so a namespace here would silence PHPMD for the
 * wrong reason and the fixture would prove nothing. The namespaced divergence
 * has its own file for the same reason.
 *
 * - EnumWithTypeNamedMethod — the sniff registers on T_CLASS/T_ANON_CLASS only,
 *   so an enum is never inspected. An enum cannot declare a constructor at all,
 *   so the method is an ordinary method and PHPMD's report is a false positive.
 * - ClassWithBothConstructors — the sniff suppresses OldStyle when the class
 *   also declares __construct (ConstructorNameSniff.php checks its
 *   functionList). PHPMD compares names only and reports regardless. With a
 *   real constructor present the same-named method is an ordinary method, so
 *   again the sniff's silence is the defensible half.
 *
 * Both silences are the sniff's own behaviour, not an effect of rules.xml —
 * the test asserts that against the unconfigured Generic standard too.
 */

declare(strict_types=1);

enum EnumWithTypeNamedMethod
{
    case First;

    public function EnumWithTypeNamedMethod(): string
    {
        return 'not a constructor';
    }
}

class ClassWithBothConstructors
{
    public function __construct()
    {
    }

    public function ClassWithBothConstructors(): string
    {
        return 'an ordinary method, shadowed by the real constructor above';
    }
}
