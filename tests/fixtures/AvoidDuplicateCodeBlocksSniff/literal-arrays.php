<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Flat lists of literals hold no logic, however long they run. The casts and
 * fillable lists share one shape per line, and so does every entry of the tag
 * list, so a comparison by token type alone matched each list against the
 * other and against itself. Lines made only of literals, names, and array
 * punctuation are left out of the comparison, so none of this is reported.
 */
class LiteralArrays extends Model
{
    protected $casts = [
        "bookName" => "string",
        "canBeDeleted" => "boolean",
        "canShowDetails" => "boolean",
        "chapterNumber" => "integer",
        "createdAt" => "datetime",
        "description" => "string",
        "icon" => "string",
        "model" => Model::class,
        "reference" => "string",
        "title" => "string",
        "type" => "string",
        "updatedAt" => "datetime",
    ];

    protected $fillable = [
        "bookName",
        "canBeDeleted",
        "canShowDetails",
        "chapterNumber",
        "createdAt",
        "description",
        "icon",
        "model",
        "reference",
        "title",
        "type",
        "updatedAt",
    ];

    public function stripBlocks(string $text): string
    {
        $blockElements = [
            "</address>",
            "</article>",
            "</aside>",
            "</blockquote>",
            "</div>",
            "</fieldset>",
            "</figure>",
            "</footer>",
            "</form>",
            "</h1>",
            "</h2>",
            "</h3>",
            "</h4>",
            "</header>",
            "</li>",
            "</main>",
            "</nav>",
            "</ol>",
            "</p>",
            "</pre>",
            "</section>",
            "<br>",
        ];

        return str_replace($blockElements, "\n", $text);
    }
}
