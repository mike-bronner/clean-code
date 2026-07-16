# Conditionals: Mapping Arrays

## Standard

Use mapping arrays instead of multiple if-statements when inspecting different
values of the same variable.

**Why:** reduces code complexity (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This standard is **not statically enforceable via PHPCS**. Whether a mapping
array is the better construct for a given conditional is a semantic judgement
— it depends on what the branches do, not on what the tokens look like.
Enforcement is via code review and developer discipline.

### Partial enforcement — focused sniff slice

One narrow shape *is* token-visible: an `if`/`elseif` chain whose conditions
all compare the same variable against different scalar literals, with each
branch body a single return or a single assignment to the same target — a
mechanical mapping-array (or `match`) candidate. That slice is tracked as a
focused sniff issue:
[#163](https://github.com/mike-bronner/phpcs-rules/issues/163).

## What remains code review

Everything outside that slice: branches with side effects, compound or
mixed-operator conditions, range checks, and — above all — the judgement of
whether a mapping array actually reduces complexity for a given case. A chain
of conditions can look mapping-shaped and still encode logic a lookup table
would obscure; that call stays with code review.
