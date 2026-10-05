<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A list of literals is compared like any other code. The rules and messages
 * maps are the same seven entries, word for word, and hold more than seventy
 * tokens, so the pair is reported at the default thresholds. The property names
 * are variables, so they do not break the match.
 *
 * The labels and hints maps differ in one literal, on the middle entry. Each
 * side of it is four lines long, too short to match on its own, so that pair
 * is not reported.
 */
class LiteralArrays extends Model
{
    protected $rules = [
        "bookName" => ["type" => "string", "nullable" => false],
        "chapterNumber" => ["type" => "integer", "nullable" => false],
        "verseNumber" => ["type" => "integer", "nullable" => false],
        "reference" => ["type" => "string", "nullable" => true],
        "createdAt" => ["type" => "datetime", "nullable" => true],
        "updatedAt" => ["type" => "datetime", "nullable" => true],
        "deletedAt" => ["type" => "datetime", "nullable" => true],
    ];

    protected $messages = [
        "bookName" => ["type" => "string", "nullable" => false],
        "chapterNumber" => ["type" => "integer", "nullable" => false],
        "verseNumber" => ["type" => "integer", "nullable" => false],
        "reference" => ["type" => "string", "nullable" => true],
        "createdAt" => ["type" => "datetime", "nullable" => true],
        "updatedAt" => ["type" => "datetime", "nullable" => true],
        "deletedAt" => ["type" => "datetime", "nullable" => true],
    ];

    protected $table = "verses";

    protected $labels = [
        "title" => ["width" => 12, "align" => "left"],
        "summary" => ["width" => 40, "align" => "left"],
        "author" => ["width" => 20, "align" => "left"],
        "status" => ["width" => 8, "align" => "center"],
        "views" => ["width" => 6, "align" => "right"],
        "rating" => ["width" => 6, "align" => "right"],
        "updated" => ["width" => 10, "align" => "right"],
    ];

    protected $hints = [
        "title" => ["width" => 12, "align" => "left"],
        "summary" => ["width" => 40, "align" => "left"],
        "author" => ["width" => 20, "align" => "left"],
        "status" => ["width" => 9, "align" => "center"],
        "views" => ["width" => 6, "align" => "right"],
        "rating" => ["width" => 6, "align" => "right"],
        "updated" => ["width" => 10, "align" => "right"],
    ];
}
