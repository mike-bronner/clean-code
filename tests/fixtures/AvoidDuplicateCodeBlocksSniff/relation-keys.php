<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Three relations built from the same call, each passing lists of model
 * classes and column names. apparatusEntries() and mergedEntries() pass the
 * same lists, word for word, over more than seventy tokens, so the pair is
 * reported at the default thresholds: a list of names and strings is compared
 * like any other code. concordances() shares shorter runs with both, and none
 * of them reaches the token minimum.
 */
class RelationKeys extends Model
{
    public function apparatusEntries(): HasManyDeep
    {
        return $this->hasManyDeep(
            Entry::class,
            [
                self::class . " as owner",
                self::class . " as grouped",
                Chapter::class,
                Book::class,
                Version::class,
                Testament::class,
            ],
            [
                "id",
                "chapter_id",
                "id",
                "id",
                "reference_version_abbreviation",
            ],
            [
                "id",
                "chapter_id",
                "book_id",
                "version_id",
                "abbreviation",
            ],
        );
    }

    public function concordances(): HasManyDeep
    {
        return $this->hasManyDeep(
            Concordance::class,
            [
                self::class . " as concordance_verse",
                self::class . " as concordance_group",
                Chapter::class,
                Book::class,
                Version::class,
            ],
            [
                "id",
                "id",
                "id",
                "id",
                "reference_version_abbreviation",
            ],
            [
                "id",
                "chapter_id",
                "book_id",
                "version_id",
                "abbreviation",
            ],
        );
    }

    public function mergedEntries(): HasManyDeep
    {
        return $this->hasManyDeep(
            Entry::class,
            [
                self::class . " as owner",
                self::class . " as grouped",
                Chapter::class,
                Book::class,
                Version::class,
                Testament::class,
            ],
            [
                "id",
                "chapter_id",
                "id",
                "id",
                "reference_version_abbreviation",
            ],
            [
                "id",
                "chapter_id",
                "book_id",
                "version_id",
                "abbreviation",
            ],
        );
    }
}
