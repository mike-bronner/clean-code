<?php

declare(strict_types=1);

namespace App\Fixtures;

// The two shapes where this sniff and PHPMD 2.15.0 disagree. Both divergences
// are deliberate and are described in docs/phpmd/codesize-toomanyfields.md;
// this fixture exists so neither can quietly drift back into an unearned parity
// claim. tests/Standards/TooManyFieldsTest.php pins exactly which lines are
// reported here.

// Divergence 1 — promoted constructor properties.
// This sniff counts all 16. PHPMD reads the class as having zero fields,
// because PDepend, which supplies its field metric, does not model promotion.
// This ruleset requires promotion
// (SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion), so
// copying that blind spot would leave the rule blind to the classes it is
// meant to police.
class PromotedPerson
{
    public function __construct(
        private int $field1,
        private int $field2,
        private int $field3,
        private int $field4,
        private int $field5,
        private int $field6,
        private int $field7,
        private int $field8,
        private int $field9,
        private int $field10,
        private int $field11,
        private int $field12,
        private int $field13,
        private int $field14,
        private int $field15,
        private int $field16,
    ) {
    }
}

// Divergence 2a — a nested anonymous class.
// This sniff reports the anonymous class, which declares all 16 fields. PHPMD
// reports the *enclosing* class instead, which declares none of them.
class Enclosing
{
    public function make(): object
    {
        return new class () {
            private int $field1 = 1;
            private int $field2 = 2;
            private int $field3 = 3;
            private int $field4 = 4;
            private int $field5 = 5;
            private int $field6 = 6;
            private int $field7 = 7;
            private int $field8 = 8;
            private int $field9 = 9;
            private int $field10 = 10;
            private int $field11 = 11;
            private int $field12 = 12;
            private int $field13 = 13;
            private int $field14 = 14;
            private int $field15 = 15;
            private int $field16 = 16;
        };
    }
}

// Divergence 2b — a top-level anonymous class.
// This sniff reports it. PHPMD reports nothing at all: with no enclosing class
// to charge the fields to, the anonymous class goes unexamined.
$detached = new class () {
    private int $field1 = 1;
    private int $field2 = 2;
    private int $field3 = 3;
    private int $field4 = 4;
    private int $field5 = 5;
    private int $field6 = 6;
    private int $field7 = 7;
    private int $field8 = 8;
    private int $field9 = 9;
    private int $field10 = 10;
    private int $field11 = 11;
    private int $field12 = 12;
    private int $field13 = 13;
    private int $field14 = 14;
    private int $field15 = 15;
    private int $field16 = 16;
};
