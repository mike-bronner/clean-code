# Conditionals: Combine Where Possible

## Standard

- Combine sequential conditions that have the same result.

**Why:** reduces code complexity (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The trigger pattern of this standard **is statically lintable**: sequential
conditions with the same result surface in the token stream as adjacent
branches whose bodies are token-identical after normalization (comments and
whitespace stripped) — the same normalized-block comparison
`CleanCode.Pattern.AvoidDuplicateCodeBlocks` already applies to runs of code
lines for the DRY standard
([#134](https://github.com/mike-bronner/phpcs-rules/issues/134)).
Two combination patterns are behavior-preserving and safe to flag, and the
custom sniff `CleanCode.Conditionals.CombinableConditions` reports both
([#181](https://github.com/mike-bronner/phpcs-rules/issues/181)).

- **Detection — adjacent branches of one `if`/`elseif` chain** — adjacent
  branches whose normalized bodies are identical are always combinable with
  `||`: only one branch of a chain ever runs, and short-circuit evaluation
  preserves the order and count of condition evaluations exactly, so
  `if ($a) { X } elseif ($b) { X }` is behavior-identical to
  `if ($a || $b) { X }` whatever `X` does.
- **Detection — adjacent separate `if` statements** — adjacent plain `if`
  statements (no `elseif`/`else`, no intervening statements) with identical
  normalized bodies are flagged when the shared body **unconditionally
  exits** the enclosing scope (`return`, `throw`, `continue`, `break`,
  `exit`). The exit is what makes `if ($a) { … } if ($b) { … }` equivalent
  to `if ($a || $b) { … }` — the body can never run twice, and the second
  condition is skipped exactly when the original would have skipped it.
- **Warning severity, not error** — the sniff points at combination
  candidates; whether the combined condition actually reads better is a
  judgement call.
- **One warning per participating branch**, at its own `if`/`elseif` keyword,
  naming the others by line. Combinability is a property the branches share,
  not something the later one did to the earlier — the same reporting choice
  `CleanCode.Pattern.AvoidDuplicateCodeBlocks` makes for duplicated blocks.
  A run of three identical branches is therefore three warnings, not two.
- **Boundaries** — separate `if` statements whose identical bodies do *not*
  exit stay out (combining changes how often the body runs and whether the
  second condition is evaluated), as do non-adjacent branches of a chain
  (merging them reorders condition evaluation). Flagging those would suggest
  behavior-changing rewrites. An `else` branch is out too — it carries no
  condition to join with `||` — as are an empty body and a brace-less body
  that is itself a control structure, neither of which is a statement worth
  comparing.

## What remains code review

Everything outside the flagged slices: bodies that differ textually but
produce the same result, conditionals separated by other statements,
non-exiting duplicate `if` statements, and — above all — the readability
judgement of whether a combined condition is actually clearer. "Same result"
in full generality is a semantic call — it stays with code review.
