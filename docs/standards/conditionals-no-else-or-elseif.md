# Conditionals: No else or elseif

## Standard

- If you have to use if-statements in PHP, never use `else` or `elseif`.

**Why:**

- They are unnecessary lines of code that can be achieved with fewer lines in
  simpler terms (reduces mental debt).
- Reduces code complexity (reduces mental debt).

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 1 (custom sniff)

Every `else` and `elseif` keyword is directly visible in the token stream, so
the standard is fully enforceable by a static analyzer. Enforced by
**`CleanCode.Conditionals.DisallowElse`**
([#14](https://github.com/mike-bronner/phpcs-rules/issues/14)):

- **`ElseFound`** — any `else` (braced, braceless, or alternative syntax).
- **`ElseIfFound`** — any `elseif`, including the space-separated `else if`
  form.
- Each occurrence in a nested or chained construct produces its own distinct
  violation.

### Existing sniffs evaluated first

Per the sniff-development process, the candidate Slevomat rules were run
against this standard's test fixture before writing a custom sniff:

- `SlevomatCodingStandard.ControlStructures.EarlyExit` flags only the
  `else`/`elseif` occurrences it can rewrite to an early exit — it misses the
  space-separated `else if` form, any `else` whose sibling branch does not
  terminate, and alternative-syntax `else :`. The standard forbids *every*
  `else`/`elseif`, so the detection scope falls short.
- `SlevomatCodingStandard.ControlStructures.UselessIfConditionWithReturn`
  only targets `if (…) { return true; } else { return false; }` shapes and
  reports nothing on the wider fixture.

Neither satisfies the tests exactly, so the custom sniff was written instead
of bending the tests.

### Auto-fixing

`phpcbf` rewrites an occurrence only when the rewrite provably preserves
behavior and content — when the branch before the `else`/`elseif` ends in a
terminating statement (`return`, `throw`, `continue`, `break`, `exit`) and
the construct uses the canonical one-brace-per-line layout (`} else {` /
`} elseif (…) {` with the closing brace first on its line):

- `} else { … }` — the wrapper is removed and its body dedented one level.
- `} elseif (…) {` / `} else if (…) {` — rewritten as a standalone `if`.

Everything else is flagged but left for a manual refactor, because rewriting
it automatically could change runtime behavior or silently drop source
content: non-terminating sibling branches, braceless bodies, alternative
syntax, comments adjacent to the keyword (e.g. `} // phpcs:ignore` before an
`else`) or trailing the `else` body's closing brace, and compact single-line
or inline-body layouts.

## What remains code review

The sniff enforces the letter of the rule completely. Whether the resulting
guard clauses read well — condition ordering, whether a lookup table or
polymorphism would beat the conditional entirely — stays with code review.
