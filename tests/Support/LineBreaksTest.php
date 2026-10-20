<?php

declare(strict_types=1);

use MikeBronner\CleanCode\Support\LineBreaks;

function lineBreakCount(string $text): int|false
{
    return preg_match_all('/' . (new LineBreaks)->in($text) . '/', $text);
}

it('counts each line break in valid UTF-8 text once', function (string $break): void {
    expect(lineBreakCount("one{$break}two{$break}three"))->toBe(2);
})->with([
    'LF' => "\n",
    'CRLF' => "\r\n",
    'CR' => "\r",
    'vertical tab' => "\x0B",
    'form feed' => "\f",
    'NEL, U+0085' => "\u{0085}",
]);

it('never counts a 0x85 byte inside a UTF-8 character, nor U+2028 or U+2029', function (string $character): void {
    expect(lineBreakCount("one{$character}two{$character}three"))->toBe(0);
})->with([
    'Greek upsilon, CF 85' => "\u{03C5}",
    'N with cedilla, C5 85' => "\u{0145}",
    'four-per-em space, E2 80 85' => "\u{2005}",
    'line separator, U+2028' => "\u{2028}",
    'paragraph separator, U+2029' => "\u{2029}",
]);

it('counts text that is not valid UTF-8 byte by byte, as \R did', function (): void {
    $text = "caf\xE9\none\x85two\x0Bthree\r\nfour";

    expect(lineBreakCount($text))->toBe(preg_match_all('/\R/', $text))
        ->and(lineBreakCount($text))->toBe(4);
});
