<?php

declare(strict_types=1);

namespace App\Fixtures;

// A class body left open mid-edit, with no state typed into it yet. It is the
// failing shape on its face, but the declaration is unfinished, so the sniff
// says nothing rather than judging a half-written class. Deliberately
// unparsable — never add the closing brace.
class HalfWritten
{
    public function format(string $input): string
    {
        return trim($input);
    }
