# Pattern: Don't Repeat Yourself (DRY)

## Standard

- Code should be abstracted/refactored into small, manageable parts.
- Unrelated code can then use common functionality abstracted from other code
  paths.
- Don't abstract prematurely; only start abstracting when other code needs to
  perform the same logic.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 2 (custom sniff)

The textual side of this standard **is statically lintable**: repeating blocks
of near-identical code are token-visible, and a custom sniff can flag them by
comparing normalized code blocks against a configurable minimum length.
Focused sniff issue:
[#134](https://github.com/mike-bronner/phpcs-rules/issues/134).

- **Detection** — two or more blocks of at least the configured number of
  lines whose normalized token streams (comments and whitespace stripped) are
  near-identical are flagged as duplication candidates.
- **Configurable threshold** — the minimum block length is a sniff property so
  projects can tune sensitivity; around 5 lines of near-identical code is the
  suggested default.
- **Warning severity, not error** — the standard explicitly tolerates
  duplication until an abstraction is warranted ("don't abstract
  prematurely"), so the sniff points at abstraction candidates rather than
  mandating a fix.
- **Same-file scope** — a PHPCS sniff sees one file's tokens at a time.
  Project-wide copy/paste detection is the domain of a dedicated copy/paste
  detector such as `phpcpd`, not a PHPCS sniff.

## What remains code review

DRY is ultimately about duplicated *knowledge*, not duplicated text: two
near-identical blocks may encode different business rules that merely coincide
today, and the standard itself defers abstraction until reuse actually
arrives. Whether a flagged duplication has earned an abstraction is a
judgement about intent and cross-file relationships — that call stays with
code review.
