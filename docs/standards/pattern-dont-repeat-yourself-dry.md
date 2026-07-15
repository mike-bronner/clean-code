# Pattern: Don't Repeat Yourself (DRY)

## Standard

- Code should be abstracted/refactored into small, manageable parts.
- Unrelated code can then use common functionality abstracted from other code
  paths.
- Don't abstract prematurely; only start abstracting when other code needs to
  perform the same logic.

_Source: [mikebronner.dev/clean-code](https://mikebronner.dev/clean-code)_

## Enforceability — Tier 3 (not statically enforceable)

This is an architectural / semantic standard. It is **not** enforced by a PHPCS
sniff. Enforcement is via **code review and developer discipline**.

DRY is about duplicated *knowledge*, not duplicated text: two token-identical
blocks may encode different business rules that merely coincide today, and the
standard itself defers abstraction until reuse actually arrives. Whether a
piece of duplication has earned an abstraction is a judgement about intent and
cross-file relationships that no single file's tokens can decide.

## Partial enforcement assessment

The standard was assessed for a narrow, token-based heuristic that could catch
a subset:

- **Duplicate method bodies within a file** — *heuristic found.* Two or more
  function/method bodies in the same file whose normalized token streams
  (comments and whitespace stripped) are identical are a high-confidence
  copy-paste signal, detectable by pure single-file token analysis. Because #4
  explicitly tolerates duplication until an abstraction is warranted, the
  sniff is scoped as a warning that points at abstraction candidates rather
  than an error. Focused sniff issue:
  [#134](https://github.com/mike-bronner/phpcs-rules/issues/134).
- **Cross-file duplication** — *out of sniff reach.* Project-wide copy/paste
  detection needs every file's tokens at once; that is the domain of a
  dedicated copy/paste detector such as `phpcpd`, not a PHPCS sniff. No issue
  opened here — it would be a tooling recommendation, not a rule in this set.

The semantic core of the standard — recognizing duplicated *knowledge* and
judging when duplication has earned an abstraction — remains enforced by code
review.
