<?php

declare(strict_types=1);

namespace App\Fixtures;

// Every class below is one PHPMD 2.15.0 reports as well, with the same field
// count: 16 against the default threshold of 15. Constants and local variables
// are mixed in to prove they are excluded from the count rather than merely
// absent.

// Sixteen plain properties.
class Person
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
    private int $field16 = 16;
}

// Static properties are fields too.
class Registry
{
    private static int $cache1 = 1;
    private static int $cache2 = 2;
    private static int $cache3 = 3;
    private static int $cache4 = 4;
    private static int $cache5 = 5;
    private static int $cache6 = 6;
    private static int $cache7 = 7;
    private static int $cache8 = 8;
    private static int $cache9 = 9;
    private static int $cache10 = 10;
    private static int $cache11 = 11;
    private static int $cache12 = 12;
    private static int $cache13 = 13;
    private static int $cache14 = 14;
    private static int $cache15 = 15;
    private static int $cache16 = 16;
}

// A multi-property declaration counts once per variable, so eight lines carry
// sixteen fields.
class Address
{
    private $street1, $city1;
    private $street2, $city2;
    private $street3, $city3;
    private $street4, $city4;
    private $street5, $city5;
    private $street6, $city6;
    private $street7, $city7;
    private $street8, $city8;
}

// Sixteen fields alongside five constants and a method full of locals: the
// count is of the fields alone.
class Invoice
{
    public const TAX_1 = 1;
    public const TAX_2 = 2;
    public const TAX_3 = 3;
    public const TAX_4 = 4;
    public const TAX_5 = 5;

    public int $line1 = 1;
    public int $line2 = 2;
    public int $line3 = 3;
    public int $line4 = 4;
    public int $line5 = 5;
    public int $line6 = 6;
    public int $line7 = 7;
    public int $line8 = 8;
    public int $line9 = 9;
    public int $line10 = 10;
    public int $line11 = 11;
    public int $line12 = 12;
    public int $line13 = 13;
    public int $line14 = 14;
    public int $line15 = 15;
    public int $line16 = 16;

    public function total(): int
    {
        $sum = 0;
        $count = 0;
        $average = 0;

        return $sum + $count + $average;
    }
}

// The declaration keywords a property can open with, all counted, and all
// counted by PHPMD too: `var`, a bare `static`, a bare `readonly`, and a
// visibility modifier reached past an attribute. Sixteen fields between them.
class Legacy
{
    var $legacy1 = 1;
    var $legacy2 = 2;
    var $legacy3 = 3;
    var $legacy4 = 4;

    static int $shared1 = 1;
    static int $shared2 = 2;
    static int $shared3 = 3;
    static int $shared4 = 4;

    readonly int $frozen1;
    readonly int $frozen2;
    readonly int $frozen3;
    readonly int $frozen4;

    #[Deprecated]
    private int $tagged1 = 1;

    #[Deprecated]
    private int $tagged2 = 2;

    #[Deprecated]
    private int $tagged3 = 3;

    #[Deprecated]
    private int $tagged4 = 4;
}
