<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Three relations built from the same call, each passing lists of model
 * classes and column names. Only the first line of each relation holds code;
 * everything after it is a class name, a string, or array punctuation, so the
 * three relations are not reported as copies of each other.
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
                self::class . " as merged_owner",
                self::class . " as merged_verse",
                Chapter::class,
                Book::class,
                Version::class,
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
                "chapter_id",
                "version_id",
                "abbreviation",
            ],
        );
    }
}
