<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures;

/**
 * Two methods that build the same array from different models. Literal entries
 * sit between the lines that read the model, and the second method carries
 * two more of them. A line of literals is compared like any other, so the
 * differing entries break the run: no five lines in a row repeat, and neither
 * method is reported, even with no token minimum.
 */
class MixedLogic
{
    public function fromFootnote(Footnote $footnote): array
    {
        $text = trim($footnote->text);

        return [
            "canBeDeleted" => false,
            "createdAt" => data_get($footnote, "createdAt"),
            "icon" => "note",
            "description" => data_get($footnote, "text"),
            "model" => $footnote,
            "summary" => $text,
            "type" => "footnote",
        ];
    }

    public function fromVerse(Verse $verse): array
    {
        $text = trim($verse->text);

        return [
            "canBeDeleted" => false,
            "canShowDetails" => true,
            "createdAt" => data_get($verse, "createdAt"),
            "icon" => "book-open",
            "iconClass" => "",
            "description" => data_get($verse, "text"),
            "model" => $verse,
            "summary" => $text,
            "type" => "verse",
        ];
    }
}
