<?php

declare(strict_types=1);

/**
 * The shape this standard is here for: a chained assignment, which binds two
 * or more names in one statement and so states more than one idea.
 *
 * Every report on this file comes under the sniff's Found code. The sniff
 * reports once per assignment beyond the first, so a three-target chain
 * reports twice — the count is what proves it reports per binding rather than
 * per line.
 */
class ChainedAssignments
{
    public function evaluate(array $data): string
    {
        $first = $second = 'a';

        $third = $fourth = $fifth = 'b';

        $this->cached = $computed = count($data);

        $data['left'] = $data['right'] = 'c';

        return $first . $second . $third . $fourth . $fifth . $computed . $data['left'];
    }

    /**
     * The memoise-and-return idiom, which reads as one thought but binds a
     * name and produces a value in the same statement.
     */
    public function memoised(array $data): int
    {
        return $this->total = count($data);
    }
}
