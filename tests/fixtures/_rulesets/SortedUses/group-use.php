<?php

declare(strict_types=1);

namespace App;

// A group use makes AlphabeticallySortedUses skip the whole file, so the two
// plainly unsorted imports below go unreported by it. DisallowGroupUse and
// MultipleUsesPerLine are what stop this file exiting clean.
use App\Nested\{Alpha, Beta};
use App\Zulu;
use App\Charlie;

class GroupedImports
{
    public function __construct(
        private Alpha $alpha,
        private Beta $beta,
        private Zulu $zulu,
        private Charlie $charlie
    ) {
    }
}
