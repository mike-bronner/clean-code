# Conditionals: Mapping Arrays

## Standard

Use mapping arrays instead of multiple if-statements when inspecting different
values of the same variable.

**Why:** reduces code complexity (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The trigger pattern of this standard **is statically lintable**: an
`if`/`elseif` chain whose conditions all compare the same variable against
different scalar literals is token-visible, and a custom sniff can flag such
chains as mapping-array (or `match`) candidates. Focused sniff issue:
[#163](https://github.com/mike-bronner/phpcs-rules/issues/163).

- **Detection** — an `if`/`elseif` chain in which every condition is
  `<same variable> === <scalar literal>` (or `==`), with each branch body a
  single `return` or a single assignment to the same target, is flagged as a
  mapping-array candidate.
- **Configurable threshold** — the minimum branch count is a sniff property so
  projects can tune sensitivity; 3 branches (counting a trailing `else` as the
  default entry) is the suggested default.
- **Warning severity, not error** — the sniff points at rewrite candidates;
  whether a mapping array actually improves a given case is a judgement call.
- **Boundaries** — mixed operators (`>`, `instanceof`, ranges), compound
  boolean conditions, function calls in conditions, and multi-statement or
  side-effectful branch bodies stay out: flagging those from tokens alone is
  noise-prone, and an array literal evaluates all its values eagerly, so
  hoisting non-literal branch expressions into a map can change behaviour.

## What remains code review

Everything outside the flagged slice: branches with side effects, compound or
mixed-operator conditions, range checks, and — above all — the judgement of
whether a mapping array actually reduces complexity for a given case. A chain
of conditions can look mapping-shaped and still encode logic a lookup table
would obscure; that call stays with code review.
