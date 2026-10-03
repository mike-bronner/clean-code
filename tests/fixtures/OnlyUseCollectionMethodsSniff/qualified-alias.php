<?php

declare(strict_types=1);

// Only a bare name resolves through an import alias. A rooted or qualified name
// that ends in the alias names some other class, so only the last call is
// flagged.

use Illuminate\Support\Collection as Items;

function rooted(array $values): int
{
    return count(\Items::make($values));
}

function qualified(array $values): int
{
    return count(Sub\Items::make($values));
}

function aliased(array $values): int
{
    return count(Items::make($values));
}
