<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Support;

class LineBreaks
{
    private const UTF8 = '(?:\r\n|[\n\x0B\f\r]|\xC2\x85)';

    private const BYTES = '\R';

    public function patternFor(string $text): string
    {
        return preg_match('//u', $text) === 1 ? self::UTF8 : self::BYTES;
    }
}
