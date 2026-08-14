<?php

declare(strict_types=1);

namespace App;

// AlphabeticallySortedUses reads only the first type of a comma-separated use,
// so it sees one import here and nothing to sort. MultipleUsesPerLine is what
// stops this file exiting clean.
use App\Zulu, App\Alpha;

class CommaSeparatedImports
{
    public function __construct(private Zulu $zulu, private Alpha $alpha)
    {
    }
}
