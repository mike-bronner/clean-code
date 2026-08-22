<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Conditionals.TypeDiscriminatorDispatch.
 *
 * Every method here dispatches on one type-discriminator read across enough
 * literal branches to qualify, so each carries exactly one warning — at the
 * `switch` keyword or at the leading `if`, never once per arm.
 *
 * The twelve cover both constructs against both discriminator shapes, the
 * counting rules that a naive implementation gets wrong, the two brace-less
 * bodies that hold an `if` the chain's own continuations do *not* bind to, and
 * the two whose reported statement span runs past a continuation that is the
 * chain's own:
 *
 *   - switchOnProperty    switch, object-property discriminator
 *   - switchOnIndex       switch, array-index discriminator
 *   - ifOnProperty        if/elseif/else, object-property discriminator
 *   - ifOnIndex           if/elseif, array-index discriminator, no else, with
 *                         the literal on the left in one branch and `==` in
 *                         another
 *   - stackedFallthrough  two stacked labels sharing one body, plus a default:
 *                         three branches only if each label counts on its own
 *   - defaultFirst        the default written first, which still counts as one
 *   - nullsafeProperty    a nullsafe property read, which discriminates exactly
 *                         as a plain one does
 *   - signedLabels        negative and unsigned numeric labels, the two-token
 *                         spelling PHP gives a negative number
 *   - bracedLoopBody      a brace-less clause whose body is a braced loop
 *                         holding an `if`. The loop's braces close that `if`, so
 *                         the two `elseif`s after it are this chain's own
 *   - closureBody         the same, where the `if` sits inside a closure
 *   - bracelessLoopAroundBraced
 *                         a brace-less loop around a braced one, which the
 *                         reported body span runs straight past
 *   - bracelessLoopAroundSwitch
 *                         the same overrun, with a `switch` as the construct
 *                         the span steps over
 */

final class Dispatchers
{
    public function switchOnProperty(object $shape): float
    {
        switch ($shape->type) {
            case 'circle':
                return 3.14 * $shape->radius * $shape->radius;
            case 'square':
                return $shape->side * $shape->side;
            case 'rect':
                return $shape->width * $shape->height;
        }

        return 0.0;
    }

    /**
     * @param array<string, string> $row
     */
    public function switchOnIndex(array $row): string
    {
        switch ($row['type']) {
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
            default:
                return 'Unknown';
        }
    }

    public function ifOnProperty(object $shape): string
    {
        if ($shape->type === 'circle') {
            return 'Circle';
        } elseif ($shape->type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    /**
     * @param array<string, string> $row
     */
    public function ifOnIndex(array $row): string
    {
        if ('circle' === $row['type']) {
            return 'Circle';
        } elseif ($row['type'] == 'square') {
            return 'Square';
        } elseif ($row['type'] === 'rect') {
            return 'Rectangle';
        }

        return 'Unknown';
    }

    public function stackedFallthrough(object $shape): string
    {
        switch ($shape->type) {
            case 'circle':
            case 'ellipse':
                return 'Round';
            default:
                return 'Angular';
        }
    }

    public function defaultFirst(object $shape): string
    {
        switch ($shape->type) {
            default:
                return 'Unknown';
            case 'circle':
                return 'Circle';
            case 'square':
                return 'Square';
        }
    }

    public function nullsafeProperty(?object $shape): string
    {
        if ($shape?->type === 'circle') {
            return 'Circle';
        } elseif ($shape?->type === 'square') {
            return 'Square';
        } else {
            return 'Unknown';
        }
    }

    /**
     * @param array<string, int> $row
     */
    public function signedLabels(array $row): string
    {
        switch ($row['code']) {
            case -1:
                return 'Failed';
            case 0:
                return 'Pending';
            case 1:
                return 'Done';
        }

        return 'Unknown';
    }

    /**
     * @param array<int, int> $sides
     */
    public function bracedLoopBody(object $shape, array $sides, bool $flag): string
    {
        if ($shape->type === 'circle')
            foreach ($sides as $side) {
                if ($flag) {
                    return 'Round';
                }
            }
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }

    public function closureBody(object $shape, bool $flag): string
    {
        if ($shape->type === 'circle')
            return (string) (static function () use ($flag): int {
                if ($flag) {
                    return 1;
                }

                return 2;
            })();
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }

    /**
     * A brace-less clause whose body is a brace-less loop around a braced one.
     * PHPCS reports where a *statement* ends, the braced loop is stepped over
     * whole, and no `elseif` ends a statement — so the reported span runs past
     * this chain's own second clause and stops inside the third. Validating that
     * span is what hands the second clause back; without it the chain reads two
     * branches and goes silent.
     *
     * @param array<int, int> $sides
     */
    public function bracelessLoopAroundBraced(object $shape, array $sides, bool $flag): string
    {
        if ($shape->type === 'circle')
            foreach ($sides as $side)
                while ($flag) {
                    return 'Round';
                }
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }

    /**
     * The same overrun with a `switch` as the construct the span steps over.
     * Any construct its own braces seal reaches it, at any nesting depth, so the
     * span is validated by looking for the boundary rather than for the
     * construct that hid it.
     *
     * @param array<int, int> $sides
     */
    public function bracelessLoopAroundSwitch(object $shape, array $sides): string
    {
        if ($shape->type === 'circle')
            foreach ($sides as $side)
                switch ($side) {
                    case 1:
                        return 'One';
                }
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }
}
