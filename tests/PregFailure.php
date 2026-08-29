<?php

/**
 * The switch that makes a PCRE failure reachable from a test.
 *
 * Every guard this package added for #376 protects a branch PHP takes only
 * when the regex engine gives up — `preg_replace()` returning null,
 * `preg_split()` and `preg_match_all()` returning false. A handful of the
 * patterns behind those calls can genuinely be driven there by a calibrated
 * adversarial subject, and `tests/fixtures/ComponentMarkupSniff/unreadable-*`
 * does exactly that for the four that were already guarded. Most cannot: a
 * pattern such as `/\s+/` or `/[|&]/` carries no unbounded quantifier that can
 * backtrack, no recursion, and no `/u` modifier, so no input reaches its
 * failure branch.
 *
 * A guard whose branch no test can enter is a guard nobody has read. This
 * class supplies the other technique the acceptance criteria allow — a
 * mockable call boundary — so each branch is exercised for what it does rather
 * than trusted for what it says.
 *
 * @see PregOverrides.php for the call boundary itself.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests;

use Closure;

final class PregFailure
{
    /**
     * The function whose next matching call fails, or null when nothing is
     * armed. Held statically because the overrides in PregOverrides.php are
     * plain functions with nowhere else to look.
     */
    private static ?string $function = null;

    /**
     * Narrows the arming to the calls whose pattern this accepts, or null to
     * fail every call to $function. A closure rather than a pattern string so
     * a test can read the pattern off the sniff instead of transcribing it —
     * a transcription keeps passing after the pattern it names is rewritten.
     */
    private static ?Closure $matches = null;

    /**
     * Makes every subsequent call to $function report failure, until
     * disarm() is called.
     */
    public static function arm(string $function, ?Closure $matches = null): void
    {
        self::$function = $function;
        self::$matches = $matches;
    }

    /**
     * Restores real PCRE behaviour. Every test that arms must call this, in
     * the same test, whether or not its assertions passed: the arming is
     * process-global and would otherwise leak into the next test.
     */
    public static function disarm(): void
    {
        self::$function = null;
        self::$matches = null;
    }

    /**
     * Whether this call is the one the test armed.
     */
    public static function armedFor(string $function, string $pattern): bool
    {
        if (self::$function !== $function) {
            return false;
        }

        return self::$matches === null || (self::$matches)($pattern) === true;
    }

    /**
     * Runs $body with $function armed, and disarms afterwards even when $body
     * throws or an assertion inside it fails. The whole point of the harness
     * is that it cannot leak, so no test arms by hand.
     *
     * @template T
     *
     * @param Closure(): T $body
     *
     * @return T
     */
    public static function during(string $function, Closure $body, ?Closure $matches = null): mixed
    {
        self::arm($function, $matches);

        try {
            return $body();
        } finally {
            self::disarm();
        }
    }
}
