<?php

declare(strict_types=1);

class Verse
{
    public function apparatus(): HasManyDeep
    {
        return $this->hasManyDeep(
            TextualApparatusEntry::class,
            [
                self::class . " as apparatus_owner",
            ],
        )
            ->whereColumn("versions.id", "supplying_books.version_id")
            ->orderBy("textual_apparatus_entries.entry_order");
    }

    public function entries(): HasManyDeep
    {
        return $this->hasManyDeep(
            TextualApparatusEntry::class,
            [
                self::class . " as apparatus_owner",
            ],
        );
    }
}

doSomething(
    $first,
    $second,
);

$entries = Entry::query(
    $first,
)->get();

$entries = Entry::query(
    $first,
)->where('active', true)
    ->get();

$profile = Profile::find(
    $id,
)
    ?->name();

$factory = Factory::using(
    $config,
)
    ::make('first');

$result = $query
    ->where(
        'active',
        true,
    )
    ->get();

$entries = $this->hasManyDeep(
    Entry::class,
)
    ->where(
        'active',
        true,
    )
    ->get();

run(
    $this->relation(
        $first,
    )
        ->get(),
    $context
);

$entries = outer(
    inner(
        $first,
    )
        ->get(),
)
    ->all();

$entries = Entry::query(
    $first,
)
    // keeps the chain in view
    ->get();

$invoice = new Invoice(
    customer: $customer,
    total: $total,
);

$found = in_array($code, [
    'first',
    'second',
], true);

$mapped = array_map(function ($item) {
    return $item * 2;
}, $items);

$called = $callback(
    $first,
);

$curried = $factory()(
    $first,
);

$handled = $handlers['save'](
    $first,
);

$built = \App\Support\build(
    $first,
);

$total = (
    $first + $second
) * 2;
