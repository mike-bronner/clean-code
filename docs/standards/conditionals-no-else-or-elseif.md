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

- **`Found`** — any `else` (braced, braceless, or alternative syntax). The
  code reads `Found` rather than `ElseFound` because the sniff landed first
  for the PHPMD ElseExpression mapping
  ([#77](https://github.com/mike-bronner/phpcs-rules/issues/77)) and consumers
  may already exclude it by that name.
- **`ElseIfFound`** — any `elseif`, including the space-separated `else if`
  form. Separate from `Found` so a project that wants PHPMD's narrower
  boundaries can exclude this code alone.
- Each occurrence in a nested or chained construct produces its own distinct
  violation.

The same sniff carries the PHPMD `CleanCode/ElseExpression` mapping —
docs/phpmd/cleancode-elseexpression.md records where this standard is stricter
than that rule.

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
behavior and content — when **every** branch before the `else`/`elseif` ends in
a terminating statement (`return`, `throw`, `continue`, `break`, `exit`) and
the construct uses the canonical one-brace-per-line layout (`} else {` /
`} elseif (…) {` with the closing brace first on its line):

- `} else { … }` — the wrapper is removed and its body dedented one level.
- `} elseif (…) {` / `} else if (…) {` — rewritten as a standalone `if`.

*Every* branch, not just the one directly before the keyword. In
`if ($a) { $r = 1; } elseif ($b) { return 2; } else { $r = 3; }` the branch
before the `else` does terminate, but unwrapping it would let a true `$a` fall
through into `$r = 3`, so the whole chain is checked back to its head `if`.

Everything else is flagged but left for a manual refactor, because rewriting
it automatically could change runtime behavior or silently drop source
content: chains with a non-terminating branch, empty branches, a branch whose
last statement is a nested construct, braceless bodies, alternative syntax,
comments adjacent to the keyword (before it, between `else` and its brace,
between `else` and `if`) or trailing the `else` body's closing brace, and
compact single-line or inline-body layouts.

Multi-line strings are safe from the dedent by construction: a heredoc's body
and closing marker, and a double-quoted string's continuation lines, are all
string tokens rather than the line-leading whitespace tokens the fixer
rewrites, so their content is never touched.

`tests/Standards/DisallowElseTest.php` proves the "without changing runtime
behavior" claim by running the fixer live and calling both the original and the
rewritten code over the same inputs, rather than by comparing two committed
files.

## What remains code review

The sniff enforces the letter of the rule completely. Whether the resulting
guard clauses read well — condition ordering, whether a lookup table or
polymorphism would beat the conditional entirely — stays with code review.
