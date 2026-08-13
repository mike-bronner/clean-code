<?php

/**
 * Compliant input for CleanCode.Naming.ShortVariable.
 *
 * Beyond names that simply clear the default minimum of three, this carries
 * the near-miss shapes the sniff has to stay silent on: the three contexts
 * PHPMD allows a short name in, the short tokens that are not variables at
 * all, the string forms that interpolate nothing, and a two-letter name that
 * measures three bytes. Each is a way the sniff could go wrong without any
 * name in failing.php changing.
 *
 * phpmd 2.15 running rulesets/naming.xml/ShortVariable at its defaults
 * reports nothing on this file either.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

class Compliant
{
    // Exactly at the minimum. One character shorter is a violation; this is
    // the passing half of that boundary.
    public $abc = 1;

    // Two letters, three bytes. PHPMD measures byte length via strlen(), and
    // so does this sniff, so both accept it.
    public $añ = 2;

    // Short names on declarations that are not variables: a class constant
    // and an enum-style case name are T_STRING to PHPCS and constant nodes to
    // pdepend, so neither tool measures them.
    public const N = 1;

    public function counters(int $total): int
    {
        $sum = 0;

        // The init section of a `for` header. Both the counter and the
        // variables its later sections read are exempt, because the name is
        // settled by its first occurrence.
        for ($i = 0, $j = $total; $i < $j; $i++) {
            $sum += $i;
        }

        return $sum;
    }

    public function loops(array $rows): int
    {
        $sum = 0;

        // A foreach key and value, plain and by value. Their uses inside the
        // body are the same names, already settled by the header.
        foreach ($rows as $k => $v) {
            $sum += $k + $v;
        }

        return $sum;
    }

    public function guards(): int
    {
        try {
            $result = $this->counters(1);
        } catch (\Throwable $e) {
            // The variable a catch binds is exempt in both tools.
            return (int) $e->getCode();
        }

        return $result;
    }

    public function strings(string $value): string
    {
        // A single-quoted string and a nowdoc interpolate nothing, so the
        // short names they spell are text, not variables.
        $plain = 'literal $ab text';
        $nowdoc = <<<'TXT'
            literal $cd text
            TXT;

        // An escaped dollar in a double-quoted string is text as well.
        return $plain . $nowdoc . "escaped \$ef {$value}";
    }

    public function accesses(Compliant $other): int
    {
        // Property accesses, not declarations. The property half of `->` is
        // a T_STRING and the property half of `::` a T_VARIABLE, but pdepend
        // builds a variable node for neither, so neither tool measures them.
        static::$sp = 1;
        Compliant::$sp = 2;

        return $other->abc + self::$sp + (int) $this->abc;
    }

    public function callsShortNamedThings(): int
    {
        // Calls and constants whose own names are short. The sniff measures
        // variables, so a function call, a class constant fetch and an array
        // key of the same spelling are all silent.
        $data = ['ab' => 1];

        return $data['ab'] + self::N + strlen('cd');
    }
}
