<?php

/**
 * A static property access is not a variable occurrence.
 *
 * PHPCS spells the property half of `self::$sa` with a T_VARIABLE token,
 * where it spells the property half of `$this->sa` with a T_STRING. Skipping
 * that access entirely — rather than exempting it — is what keeps the local
 * `$sa` below reportable: an exempt occurrence would still have consumed the
 * one report the name gets in this scope, and the genuine violation on the
 * next line would have gone silent.
 *
 * Which is exactly what happens in phpmd. Verified against phpmd 2.15: it
 * reports line 27, the property declaration, and nothing else. pdepend nests
 * `self::$sa` under a MemberPrimaryPrefix, one of the contexts PHPMD allows a
 * short name in, and marks the name processed there — so the ordinary local
 * on line 32 never gets looked at. This file is therefore a divergence of the
 * same family as divergences.php: phpcs reports both lines, phpmd the first.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

class StaticAccess
{
    public static $sa = 1;

    public function reads(): int
    {
        self::$sa = 2;
        $sa = 3;

        return $sa + self::$sa + static::$sa;
    }
}
