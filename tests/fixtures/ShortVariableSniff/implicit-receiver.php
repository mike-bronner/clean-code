<?php

/**
 * `$this` is never an occurrence, however it is written.
 *
 * `this` is four characters, so at the default minimum of 3 the name clears
 * the length gate on its own and the exclusion is invisible. This fixture is
 * therefore driven at a minimum of 5, where every `$this` below would be
 * reported without it.
 *
 * Each method carries a two-character control name that must still be
 * reported, so the silence discriminates: a sniff that had simply given up on
 * this file, or on strings, would satisfy the silence just as well. The
 * controls are split by the code path that finds them — `$ma` and `$so` are
 * ordinary T_VARIABLE tokens, while `$si`, `$bi` and `$hi` appear nowhere but
 * inside a string, so each of the two places the receiver is dropped is
 * covered by a control that proves that same place still reports.
 *
 * Verified against phpmd 2.15 at a minimum of 5. phpmd is not uniform here:
 * it stays silent on the member-access receiver (`$this->value`) and on the
 * braced interpolation `"{$this->value}"`, because pdepend folds a chain's
 * receiver into a MemberPrimaryPrefix and builds no node for it — but it does
 * report `return $this;` and the simple interpolations `"$this->value"` and
 * the heredoc spelling, where pdepend emits an ordinary variable node. Those
 * are the reports this sniff deliberately drops, and the only place it is
 * quieter than phpmd: the name cannot be renamed, so the report is
 * unactionable rather than merely noisy.
 */

declare(strict_types=1);

namespace MikeBronner\CleanCode\Tests\Fixtures\ShortVariable;

class ImplicitReceiver
{
    private int $value = 1;

    public function memberAccess(): int
    {
        $ma = $this->value;

        return $ma + $this->value;
    }

    public function standalone(): self
    {
        $so = $this;

        return $so;
    }

    public function simpleInterpolation(): string
    {
        return "$this->value $si";
    }

    public function bracedInterpolation(): string
    {
        return "{$this->value} {$bi}";
    }

    public function heredocInterpolation(): string
    {
        return <<<EOT
            $this->value $hi
            EOT;
    }
}
