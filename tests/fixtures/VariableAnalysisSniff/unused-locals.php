<?php

declare(strict_types=1);

namespace App;

/**
 * Local variables assigned and never read. PHPMD 2.15.0, run with only
 * UnusedLocalVariable enabled, and the configured sniff agree on every name
 * here — this fixture is the parity set for issue #118.
 *
 * The two tools do not agree on how many times to report a name assigned more
 * than once, so that shape lives in unused-locals-divergences.php instead.
 * Nothing here overstates parity.
 */
class UnusedLocals
{
    /**
     * The rule's headline case, straight from PHPMD's own documentation.
     */
    public function simpleAssignment(): string
    {
        $i = 5;

        return 'result';
    }

    /**
     * A foreach value nobody reads. PHPMD's allow-unused-foreach-variables
     * defaults to false, and rules.xml sets the sniff's inverted default to
     * match, so this is flagged.
     */
    public function foreachValue(array $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            $count++;
        }

        return $count;
    }

    /**
     * The key half of the same property, on the `key => value` form.
     */
    public function foreachKey(array $rows): int
    {
        $total = 0;

        foreach ($rows as $key => $value) {
            $total += $value;
        }

        return $total;
    }

    /**
     * And the value half of the `key => value` form.
     */
    public function foreachAssociativeValue(array $rows): int
    {
        $total = 0;

        foreach ($rows as $key => $value) {
            $total += $key;
        }

        return $total;
    }

    /**
     * Destructuring binds two names; only one is read.
     */
    public function destructuredElement(array $pair): int
    {
        [$left, $right] = $pair;

        return $left;
    }

    /**
     * `static` keeps a value between calls, which does not make reading it
     * unnecessary.
     */
    public function staticLocal(): int
    {
        static $counter = 0;

        return 1;
    }

    /**
     * A name imported from the global scope and then ignored.
     */
    public function importedGlobal(): int
    {
        global $registry;

        return 1;
    }

    /**
     * A closure body is a scope of its own to both tools, so its locals are
     * checked the same way a method's are.
     */
    public function closureLocal(): callable
    {
        return static function (): int {
            $innerUnused = 1;

            return 0;
        };
    }

    /**
     * An alias bound by reference is still a local name. Assigning the
     * reference is not reading it.
     */
    public function referenceAlias(array $source): int
    {
        $alias = &$source;

        return 1;
    }

    /**
     * extract() may define names, but it never *reads* one. Both tools flag
     * the assignment above it.
     */
    public function assignedBeforeExtract(array $data): string
    {
        $before = 'unused';
        extract($data);

        return 'result';
    }
}
