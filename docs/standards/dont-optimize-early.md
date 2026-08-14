# Don't Optimize Early

## Standard

Optimizing without a specific need (when code standards are already met and no
apparent issues exist) is pointless and can make code worse. Save optimization
for the last possible moment, as changes will inform how code should be
optimized. Premature optimization increases complexity, wastes time and
resources, and compromises code quality.

**Takeaway:** follow coding standards primarily; only optimize when the need
arises.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. It is **not** enforced
by a PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint time.
Whether an optimization is *premature* is a judgement about need and timing —
does a measured performance problem exist, and has the design settled enough to
know what to optimize? Neither fact is visible in a file's tokens. The same
code is a sound optimization when a profiler demanded it and a premature one
when nobody did.

## Partial enforcement assessment

No slice of this rule is catchable by a token-based sniff, so **no sniff is
planned**.

The constructs commonly reached for when micro-optimizing — swapping
`array_key_exists()` for `isset()`, hand-caching values in locals, unrolling
loops, reordering conditions for short-circuit gains — are all legitimate when
a real, measured need exists. The standard does not prohibit any construct; it
prohibits applying them *without need*, and "need" lives in profiling data and
project history, not in tokens. Flagging the constructs themselves would
produce false positives on every justified use.

If a future, narrower heuristic is identified, open a focused sniff issue for
that specific slice and link it here.
