<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Chained calls whose every argument is a literal name a different column on
 * each line, yet each line has the same token types. A chain link made only of
 * an object operator, a method name, and literal arguments holds no logic, so
 * neither chain is reported, against itself or against the other.
 */
class LiteralCallChain
{
    public function apparatus(Builder $query): Builder
    {
        return $query
            ->whereColumn("versions.id", "books.version_id")
            ->whereColumn("entries.book_name", "books.name")
            ->whereColumn("entries.chapter_number", "chapters.number")
            ->whereColumn("entries.verse_number", "verses.number")
            ->whereColumn("verses.crossbible_number", "groups.crossbible_number")
            ->whereColumn("verses.chapter_number", "groups.chapter_number")
            ->whereColumn("groups.version_id", "versions.id")
            ->whereColumn("groups.book_id", "books.id")
            ->whereColumn("groups.chapter_id", "chapters.id")
            ->whereColumn("groups.verse_id", "verses.id")
            ->orderBy("groups.crossbible_number")
            ->orderBy("entries.entry_order");
    }

    public function concordances(Builder $query): Builder
    {
        return $query
            ->whereColumn("concordances.book_name", "books.name")
            ->whereColumn("concordances.chapter_number", "verses.chapter_number")
            ->whereColumn("concordances.verse_number", "verses.number")
            ->whereColumn("concordances.version_id", "versions.id")
            ->whereColumn("concordances.chapter_id", "chapters.id")
            ->orderBy("concordances.position");
    }
}
