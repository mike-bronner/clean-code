<?php

/**
 * Which construct owns which variable.
 *
 * PHPMD applies this rule to class, trait, method, and function nodes, and
 * `findChildrenOfType()` descends the whole subtree of the node it is given.
 * Anything declared inside a function body is therefore reached through that
 * function and never visited a second time, and PDepend registers no artifact
 * at all for an anonymous class — so an anonymous class is reached only through
 * whatever encloses it, and at file scope nothing encloses it.
 *
 * Every name here is longer than the default maximum of 20, so each line either
 * reports exactly once or is out of scope entirely. Verified against a live
 * PHPMD 2.15.0 run over this file: it reports lines 45, 49, 58, 69, 80, and 94
 * — twice each for 45 and 94, where a class node and a method node both reach
 * the same field, and this sniff collapses those duplicates to one, as it
 * already does for a trait's.
 */

declare(strict_types=1);

namespace Tests\Fixtures\LongVariableSniff;

// An anonymous class at file scope is out of scope in both tools: PDepend
// builds no artifact for it, and there is no enclosing node to reach it
// through. Neither its field nor the local in its method is reported.
$fileScopeAnonymous = new class {
    public string $fileScopeAnonFieldName = 'x';

    public function fileScopeAnonMethod(): void
    {
        $fileScopeAnonMethodLocal = 1;
    }
};

class AnonymousInsideMethodHost
{
    public function makesAnonymousClass(): object
    {
        // Reached through this method, not as a container of its own. The
        // field on line 45 and the inner method's local on line 49 are each
        // reported once.
        return new class {
            public string $anonInMethodFieldName = 'x';

            public function innerAnonymousMethod(): void
            {
                $anonInMethodInnerLocal = 1;
            }
        };
    }
}

function makesAnonymousClassInFunction(): object
{
    return new class {
        public string $anonInFunctionFieldName = 'x';
    };
}

// A named class declared inside a function body. PDepend registers it as an
// artifact of its own, so PHPMD reports its field once, from the class; also
// sweeping it into the enclosing function's walk is what reported it twice.
function declaresNestedClass(): void
{
    class NestedInsideFunction
    {
        public string $nestedInFunctionFieldName = 'x';
    }
}

// A named function declared inside another function body is likewise its own
// artifact, so its local is reported from its own walk — and the enclosing
// walk must step over it rather than sweep the name up a second time.
function declaresNestedFunction(): void
{
    function nestedNamedFunction(): void
    {
        $nestedNamedFunctionLocal = 1;
    }
}

class DeclarationOrderHost
{
    public function sharesNameWithAnonymousField(): void
    {
        // PHPMD walks every declarator in the subtree before any plain
        // variable, so the anonymous class's *field* on line 94 is the
        // occurrence reported and this local is de-duplicated away.
        $sharedOverlongNameHere = 1;

        $holder = new class {
            public string $sharedOverlongNameHere = 'y';
        };
    }
}
