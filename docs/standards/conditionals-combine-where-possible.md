# Conditionals: Combine Where Possible

## Standard

- Combine sequential conditions that have the same result.

**Why:** reduces code complexity (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is a semantic standard: whether two sequential conditionals have the
"same result" is a question about behavior, not text. A token-based PHPCS
sniff sees only the token stream — it cannot evaluate what a body *does*, so
it cannot tell that two textually different bodies produce the same result,
nor that two textually identical bodies are safe to merge in their particular
context (side effects, short-circuiting, non-exiting bodies). Enforcement is
via code review and developer discipline.

## Partial enforcement — narrow token heuristic

One narrow slice **is** token-visible: adjacent `if` statements (no
`elseif`/`else`, no intervening statements) whose bodies are token-identical
after normalization (comments and whitespace stripped), where the shared body
unconditionally exits the enclosing scope (`return`, `throw`, `continue`,
`break`, `exit`). Under those constraints, combining the conditions with `||`
is behavior-preserving, and the detection is the same normalized-block
comparison already used by the DRY sniff
([#134](https://github.com/mike-bronner/phpcs-rules/issues/134)). Focused
sniff issue for this slice:
[#181](https://github.com/mike-bronner/phpcs-rules/issues/181).

## What remains code review

Everything beyond that slice: bodies that differ textually but produce the
same result, identical bodies that don't exit (merging changes how often the
body runs and whether the second condition is evaluated), conditions separated
by other statements, and the readability judgement of whether a combined
condition is actually clearer. "Same result" is a semantic call — it stays
with code review.
