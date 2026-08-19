<?php

/**
 * Two properties of the comparison itself: one displaced member earns one
 * error rather than one for every member behind it, and letter case does not
 * decide the order.
 */

declare(strict_types=1);

/**
 * $alpha is the member in the wrong place, and the only one reported: it
 * becomes the baseline the members after it are compared against, so $bravo —
 * correctly placed relative to $alpha, and only "out of order" against the
 * $charlie that displaced it — stays silent.
 */
class CascadeModel extends Model
{
    public string $charlie = '';

    public string $alpha = '';

    public string $bravo = '';
}

/**
 * Alphabetical order the way a reader reads it, not the way ASCII orders the
 * upper case alphabet ahead of the lower case one. Under a case-sensitive
 * comparison $Delta would sort before $charlie and report.
 */
class MixedCaseModel extends Model
{
    public string $Bravo = '';

    public string $charlie = '';

    public string $Delta = '';
}
