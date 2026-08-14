<?php

declare(strict_types=1);

namespace App\Fixtures;

// Every shape below sits at or under the threshold, or declares something that
// is not a field at all. The file must produce zero violations: it carries the
// boundary case (a class with exactly 15 fields), each near-miss the counter
// has to stay silent on, and the two class-likes PHPMD's ClassAware rule never
// examines.

// Exactly at the threshold. One more field would be reported, so this pins the
// boundary from the silent side.
class Coordinates
{
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
}

// Constants are not fields, in PHPMD or here.
class Statuses
{
    public const STATUS_1 = '1';
    public const STATUS_2 = '2';
    public const STATUS_3 = '3';
    public const STATUS_4 = '4';
    public const STATUS_5 = '5';
    public const STATUS_6 = '6';
    public const STATUS_7 = '7';
    public const STATUS_8 = '8';
    public const STATUS_9 = '9';
    public const STATUS_10 = '10';
    public const STATUS_11 = '11';
    public const STATUS_12 = '12';
    public const STATUS_13 = '13';
    public const STATUS_14 = '14';
    public const STATUS_15 = '15';
    public const STATUS_16 = '16';
}

// Enum cases are not fields either, and an enum cannot declare a property.
enum Suit: string
{
    case Suit1 = 's1';
    case Suit2 = 's2';
    case Suit3 = 's3';
    case Suit4 = 's4';
    case Suit5 = 's5';
    case Suit6 = 's6';
    case Suit7 = 's7';
    case Suit8 = 's8';
    case Suit9 = 's9';
    case Suit10 = 's10';
    case Suit11 = 's11';
    case Suit12 = 's12';
    case Suit13 = 's13';
    case Suit14 = 's14';
    case Suit15 = 's15';
    case Suit16 = 's16';
}

// An interface declares constants and signatures, never fields.
interface HasManyConstants
{
    public const LIMIT_1 = 1;
    public const LIMIT_2 = 2;
    public const LIMIT_3 = 3;
    public const LIMIT_4 = 4;
    public const LIMIT_5 = 5;
    public const LIMIT_6 = 6;
    public const LIMIT_7 = 7;
    public const LIMIT_8 = 8;
    public const LIMIT_9 = 9;
    public const LIMIT_10 = 10;
    public const LIMIT_11 = 11;
    public const LIMIT_12 = 12;
    public const LIMIT_13 = 13;
    public const LIMIT_14 = 14;
    public const LIMIT_15 = 15;
    public const LIMIT_16 = 16;
}

// A trait's fields belong to the trait. PHPMD's rule is ClassAware, so it never
// looks at one, and neither does this sniff.
trait ManyFields
{
    private int $shared1 = 1;
    private int $shared2 = 2;
    private int $shared3 = 3;
    private int $shared4 = 4;
    private int $shared5 = 5;
    private int $shared6 = 6;
    private int $shared7 = 7;
    private int $shared8 = 8;
    private int $shared9 = 9;
    private int $shared10 = 10;
    private int $shared11 = 11;
    private int $shared12 = 12;
    private int $shared13 = 13;
    private int $shared14 = 14;
    private int $shared15 = 15;
    private int $shared16 = 16;
}

// The using class declares two fields of its own; the trait's stay the trait's.
class UsesTheTrait
{
    use ManyFields;

    private int $own = 0;

    private int $alsoOwn = 0;
}

// Local variables, closure parameters, and closure locals all live in a scope
// of their own, so none of the 18 variables below is a field.
class Calculator
{
    private int $total = 0;

    public function run(int $seed): int
    {
        $a = $seed;
        $b = $a + 1;
        $c = $b + 1;
        $d = $c + 1;
        $e = $d + 1;
        $f = $e + 1;
        $g = $f + 1;
        $h = $g + 1;
        $i = $h + 1;
        $j = $i + 1;
        $k = $j + 1;
        $l = $k + 1;
        $m = $l + 1;
        $n = $m + 1;
        $o = $n + 1;
        $p = $o + 1;

        $add = static function (int $left, int $right): int {
            return $left + $right;
        };

        return $add($p, $this->total);
    }
}

// Plain parameters are arguments, not fields: only a promoted one declares a
// field, and none of these 16 is promoted.
abstract class Configurable
{
    public function __construct(
        int $option1,
        int $option2,
        int $option3,
        int $option4,
        int $option5,
        int $option6,
        int $option7,
        int $option8,
        int $option9,
        int $option10,
        int $option11,
        int $option12,
        int $option13,
        int $option14,
        int $option15,
        int $option16,
    ) {
    }

    abstract public function apply(): void;
}

// Inheritance is not summed. Each class declares 10 fields; PHPMD counts the
// fields a class declares itself, and 20 would be over the threshold.
class BaseRecord
{
    protected int $base1 = 1;
    protected int $base2 = 2;
    protected int $base3 = 3;
    protected int $base4 = 4;
    protected int $base5 = 5;
    protected int $base6 = 6;
    protected int $base7 = 7;
    protected int $base8 = 8;
    protected int $base9 = 9;
    protected int $base10 = 10;
}

class DerivedRecord extends BaseRecord
{
    private int $derived1 = 1;
    private int $derived2 = 2;
    private int $derived3 = 3;
    private int $derived4 = 4;
    private int $derived5 = 5;
    private int $derived6 = 6;
    private int $derived7 = 7;
    private int $derived8 = 8;
    private int $derived9 = 9;
    private int $derived10 = 10;
}

// A nested anonymous class is a class of its own. Neither it (2 fields) nor its
// host (3 fields) is anywhere near the threshold.
class Host
{
    private int $one = 1;

    private int $two = 2;

    private int $three = 3;

    public function make(): object
    {
        return new class () {
            private int $inner = 1;

            private int $alsoInner = 2;
        };
    }
}

// A constructor mixing promoted and plain parameters: 14 fields and 4
// arguments. Reading the promoted parameters' modifiers past the commas that
// separate them would make this 18 and report the class.
class MixedConstructor
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
        int $option1,
        int $option2,
        int $option3,
        int $option4,
    ) {
    }
}
