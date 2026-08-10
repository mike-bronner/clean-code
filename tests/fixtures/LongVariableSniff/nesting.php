<?php

/**
 * Which construct owns which variable.
 *
 * PHPMD applies this rule to class, trait, method, and function nodes, and
 * `findChildrenOfType()` descends the whole subtree of the node it is given.
 * Anything declared inside a function body is therefore reached through that
 * function and never visited a second time, and PDepend registers no artifact
 * at all for an anonymous class — so an anonymous class is reached only through
 * whatever encloses it, and at file scope nothing encloses it. That reaches one
 * level: a named construct declared deeper inside one is an artifact again.
 *
 * Every name here is longer than the default maximum of 20, so each line either
 * reports exactly once or is out of scope entirely. Verified against a live
 * PHPMD 2.15.0 run: it reports lines 45, 49, 58, 69, 80, 94, 115, 122, 144,
 * 153, and 178 — twice each for 45 and 94, where a class node and a method node
 * both reach the same field, and this sniff collapses those duplicates to one.
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

// Being inside an anonymous class reaches one level, not the whole chain. Only
// a construct declared *directly* in an anonymous class body — a method of one
// — is reached through the enclosing walk. A named function declared inside
// that method is an artifact again, because PDepend registers a named construct
// wherever it is declared, so it is walked in its own right and the method's
// own walk steps over it.
//
// The two locals share a name, which is what makes the shape discriminating:
// both are reported only if the two constructs are separate de-duplication
// scopes. Judging by the whole conditions chain merged them, and the enclosing
// method's local on line 115 — first in source order — then silenced the nested
// function's own local on line 122 entirely.
class NamedFunctionInsideAnonymousHost
{
    public function declaresFunctionInAnonymous(): object
    {
        $sharedWithNestedLocal = 1;

        return new class {
            public function innerDeclaresFunction(): void
            {
                function namedInsideAnonymousMethod(): void
                {
                    $sharedWithNestedLocal = 2;
                }
            }
        };
    }
}

// The named-class twin, and the same distinction nesting.php already draws
// between a nested class and a nested function at lines 65 to 82. It sits one
// level deeper than its twin above because a `class` declared directly in any
// method body — an anonymous class's included — is a parse error in PHP
// ("Class declarations may not be nested"); inside a named *function* declared
// there, it parses. So this pins that the artifact test keeps recursing beneath
// an anonymous class rather than only stepping past the first level.
//
// It fails the other way round from its twin: with the scopes merged, the
// class's field on line 153 is a declarator, ordered ahead of every plain
// variable, so it is the enclosing method's local on line 144 that disappears.
class NamedClassBeneathAnonymousHost
{
    public function declaresClassBeneathAnon(): object
    {
        $nestedBeneathAnonName = 1;

        return new class {
            public function innerDeclaresAClass(): void
            {
                function declaresClassInsideAnon(): void
                {
                    class NamedBeneathAnonymousMethod
                    {
                        public string $nestedBeneathAnonName = 'x';
                    }
                }
            }
        };
    }
}

// The same one-level rule at file scope, where it costs a report outright
// rather than swapping which of two shares a name. Nothing encloses a
// file-scope anonymous class, so its field on line 170 and its method's local
// on line 174 are out of scope in both tools — but the named function on line
// 176 is an artifact wherever it is declared, so its local on line 178 is
// reported, by PHPMD too. Reading the whole conditions chain silenced that
// local along with everything else beneath the anonymous class, and with
// nothing enclosing it to sweep it into, the report simply vanished.
$fileScopeAnonymousWithFunction = new class {
    public string $unreachableAnonFieldName = 'x';

    public function unreachableAnonMethod(): void
    {
        $unreachableAnonMethodLocal = 1;

        function namedFunctionInFileScopeAnon(): void
        {
            $reportedFromItsOwnWalk = 1;
        }
    }
};
