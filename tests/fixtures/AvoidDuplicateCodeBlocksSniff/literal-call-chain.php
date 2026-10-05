<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Chained calls whose every argument is a literal are compared like any other
 * code. apparatus() and footnotes() build the same chain, word for word, over
 * more than seventy tokens, so the pair is reported at the default thresholds.
 *
 * concordances() and crossReferences() differ in one literal, on the middle
 * link. Each side of it is four lines long, too short to match on its own, so
 * that pair is not reported.
 */
class LiteralCallChain
{
    public function apparatus(Builder $query): Builder
    {
        return $query
            ->whereColumn("entries.book_name", "books.name")
            ->whereColumn("entries.chapter_number", "chapters.number")
            ->whereColumn("entries.verse_number", "verses.number")
            ->whereColumn("verses.chapter_id", "chapters.id")
            ->whereColumn("chapters.book_id", "books.id")
            ->whereColumn("books.version_id", "versions.id")
            ->whereColumn("groups.verse_id", "verses.id")
            ->whereColumn("groups.chapter_id", "chapters.id")
            ->whereColumn("groups.book_id", "books.id")
            ->orderBy("entries.entry_order");
    }

    public function footnotes(Builder $builder): Builder
    {
        return $builder
            ->whereColumn("entries.book_name", "books.name")
            ->whereColumn("entries.chapter_number", "chapters.number")
            ->whereColumn("entries.verse_number", "verses.number")
            ->whereColumn("verses.chapter_id", "chapters.id")
            ->whereColumn("chapters.book_id", "books.id")
            ->whereColumn("books.version_id", "versions.id")
            ->whereColumn("groups.verse_id", "verses.id")
            ->whereColumn("groups.chapter_id", "chapters.id")
            ->whereColumn("groups.book_id", "books.id")
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

    public function crossReferences(Builder $query): Builder
    {
        return $query
            ->whereColumn("concordances.book_name", "books.name")
            ->whereColumn("concordances.chapter_number", "verses.chapter_number")
            ->whereColumn("concordances.verse_number", "verses.verse")
            ->whereColumn("concordances.version_id", "versions.id")
            ->whereColumn("concordances.chapter_id", "chapters.id")
            ->orderBy("concordances.position");
    }
}
