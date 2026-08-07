<?php

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Ruleset\Fixtures\UndefinedVariable;

/**
 * Reads of names this file never assigns. PHPMD 2.15.0 and the configured
 * sniff agree on every case here, flagging the same lines — this fixture is
 * the parity set. Cases where the two tools disagree, or where both stay
 * silent, live in divergences.inc instead, so nothing here overstates parity.
 */
class Failing
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

    public function unsetsUndefined(): void
    {
        unset($neverAssigned);
    }
}
