<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures\UndefinedVariable;

class Violations
{
    public function readsUndefinedScalar(): string
    {
        return $undefinedScalar;
    }

    public function readsUndefinedArrayElement(): string
    {
        return $undefinedArray['key'];
    }

    public function readsUndefinedInInterpolatedString(): string
    {
        return "value: {$undefinedInString}";
    }

    public function readsBeforeItIsAssigned(): int
    {
        $total = $assignedLater + 1;
        $assignedLater = 2;

        return $total;
    }

    public function unsetsUndefined(): void
    {
        unset($neverAssigned);
    }
}
