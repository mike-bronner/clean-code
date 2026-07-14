# Conditionals: Avoid Conditionals

## Standard

Avoid conditionals where possible.

**Why:** they increase cyclomatic complexity, which increases mental debt. Every
branch is another path the reader must hold in their head; code that replaces a
conditional with polymorphism, a mapping array, or an early return is cheaper to
reason about.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic / process standard. It is **not** enforced by
a PHPCS sniff. Enforcement is via **code review and developer discipline**.

A token-based PHPCS sniff inspects one file's tokens in isolation at lint time.
It can see that a conditional *exists*, but "avoid where possible" is a judgement
about whether a better construct — polymorphism, a mapping array, an early return
— was *available* for this particular logic. That requires understanding what the
code means, not what tokens it uses. No amount of token analysis can decide
whether a given `if` was avoidable.

## Partial enforcement assessment

A viable partial slice **does** exist — the complexity-metric warning the
standard itself anticipates ("not statically enforceable beyond a
complexity-metric warning"). Static analysis cannot judge whether any single
conditional was avoidable, but it *can* flag code where conditionals have
accumulated past a threshold. That slice is **already tracked by focused
issues**, so no new sniff issue is opened here:

- [#88 — PHPMD/CodeSize: CyclomaticComplexity](https://github.com/mike-bronner/phpcs-rules/issues/88)
- [#89 — PHPMD/CodeSize: NPathComplexity](https://github.com/mike-bronner/phpcs-rules/issues/89)
- [#87 — PHPMD/CodeSize: ExcessiveClassComplexity](https://github.com/mike-bronner/phpcs-rules/issues/87)
- [#36 — Indentation: Methods (max 2 nesting levels)](https://github.com/mike-bronner/phpcs-rules/issues/36)

Specific *shapes* of avoidable conditionals are likewise catalogued and enforced
as their own standards (e.g. no `else`/`elseif`, mapping arrays over branching,
combining conditionals), each on its own terms. This standard is the umbrella
discipline those slices approximate; the remainder is code review.
