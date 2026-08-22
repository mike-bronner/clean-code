<?php

declare(strict_types=1);

/**
 * Violating fixture for CleanCode.Conditionals.TypeDiscriminatorDispatch.
 *
 * Every method here dispatches on one type-discriminator read across enough
 * literal branches to qualify, so each carries exactly one warning — at the
 * `switch` keyword or at the leading `if`, never once per arm.
 *
 * The fifteen cover both constructs against both discriminator shapes, the
 * counting rules that a naive implementation gets wrong, the two brace-less
 * bodies that hold an `if` the chain's own continuations do *not* bind to, the
 * two whose body holds a construct a statement walk steps over whole, and the
 * three whose body is a statement PHP writes as more than one scope:
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
 *   - tryCatchBody        a body PHP writes as two scopes, so the first one's
 *                         boundary is not the body's
 *   - tryCatchFinallyBody the same statement at three scopes
 *   - doWhileBody         the other multi-scope statement, whose `while` and
 *                         its semicolon sit after the block
 *   - bracelessDoWhileBody
 *                         the same `do` without braces, which carries no scope
 *                         to step over and ends at a later semicolon than its
 *                         body's
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

    /**
     * A brace-less clause whose body is a `try`/`catch`. PHP writes the
     * statement as two scopes, and the boundary of the first one is not the
     * boundary of the body — a walk that stops there finds `catch` where it
     * expects a continuation and reads a one-branch chain. Stepping over each
     * scope in turn arrives at the `elseif` that is really there.
     */
    public function tryCatchBody(object $shape): string
    {
        if ($shape->type === 'circle')
            try {
                return 'Round';
            } catch (\Exception $exception) {
                return 'Failed';
            }
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }

    /**
     * The same statement at three scopes rather than two, so the walk has to
     * keep stepping rather than stop after the second.
     */
    public function tryCatchFinallyBody(object $shape): string
    {
        if ($shape->type === 'circle')
            try {
                return 'Round';
            } catch (\Exception $exception) {
                return 'Failed';
            } finally {
                cleanUp();
            }
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }

    /**
     * The other multi-scope statement PHP writes: a `do` block whose `while`
     * sits after it, carrying the semicolon the body really ends at. The first
     * scope's boundary lands on `while`, which continues no chain, so the same
     * one-branch misread follows from stopping there.
     */
    public function doWhileBody(object $shape, bool $flag): string
    {
        if ($shape->type === 'circle')
            do {
                return 'Round';
            } while ($flag);
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }

    /**
     * The same `do`, brace-less. Dropping the braces costs it the one thing the
     * walk crossed it on: PHP_CodeSniffer gives a brace-less `do` no scope, so
     * there is nothing to step over and the body's own semicolon arrives
     * looking like the end of the clause. It is not — the `while ($flag);`
     * after it is still part of the same statement. This is the only shape in
     * PHP where that is true, and the only one the step-over cannot reach.
     */
    public function bracelessDoWhileBody(object $shape, bool $flag): string
    {
        if ($shape->type === 'circle')
            do
                doSomething();
            while ($flag);
        elseif ($shape->type === 'square')
            return 'Square';
        elseif ($shape->type === 'rect')
            return 'Rect';

        return 'Unknown';
    }
}
